<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

class UploadHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handle() {
        try {
            // Get upload data (multipart or base64)
            $uploadData = $this->detectAndGetUploadData();
            
            if (!$uploadData) {
                throw new Exception('No file data provided');
            }
            
            $fileData = $uploadData['data'];
            $filename = $uploadData['filename'] ?? '';
            $force = $uploadData['force'] ?? false;
            
            // Process the upload
            $result = $this->processUpload($fileData, $filename, $force);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'File uploaded successfully',
                'data' => $result
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Upload failed: ' . $e->getMessage()
            ]);
        }
    }
    
    private function detectAndGetUploadData() {
        // Check if it's a multipart upload
        if (!empty($_FILES['file'])) {
            $file = $_FILES['file'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error: ' . $file['error']);
            }
            
            if ($file['size'] > MAX_FILE_SIZE) {
                throw new Exception('File too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
            }
            
            $fileData = file_get_contents($file['tmp_name']);
            $filename = $_POST['filename'] ?? $file['name'];
            $force = isset($_POST['force']) && $_POST['force'] === 'true';
            
            return [
                'data' => $fileData,
                'filename' => $filename,
                'force' => $force
            ];
        }
        
        // Check if it's a JSON base64 upload
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!$data || !isset($data['image'])) {
                throw new Exception('Invalid JSON data');
            }
            
            // Extract base64 data from data URL format
            $base64Data = $data['image'];
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Data, $matches)) {
                $base64Data = $matches[2];
            }
            
            $fileData = base64_decode($base64Data);
            if ($fileData === false) {
                throw new Exception('Invalid base64 data');
            }
            
            if (strlen($fileData) > MAX_FILE_SIZE) {
                throw new Exception('File too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
            }
            
            $filename = $data['filename'] ?? '';
            $force = isset($data['force']) && $data['force'] === true;
            
            return [
                'data' => $fileData,
                'filename' => $filename,
                'force' => $force
            ];
        }
        
        return null;
    }
    
    private function processUpload($fileData, $filename, $force = false) {
        // Calculate file hash for deduplication
        $fileHash = md5($fileData);
        
        // Determine file type and extension
        $fileInfo = $this->getFileInfo($fileData);
        $extension = $fileInfo['extension'];
        $mimeType = $fileInfo['mime_type'];
        $isImage = $fileInfo['is_image'];
        
        // Check if extension is allowed
        if (!in_array(strtolower($extension), ALLOWED_EXTENSIONS)) {
            throw new Exception('File type not allowed');
        }
        
        // Generate filename if not provided
        if (empty($filename)) {
            $filename = $this->generateRandomFilename($extension);
        } else {
            // Normalize filename if enabled
            $normalizedFilename = $this->normalizeFilename($filename);
            if ($normalizedFilename === null) {
                // If normalization resulted in empty name, generate random filename
                $filename = $this->generateRandomFilename($extension);
            } else {
                $filename = $normalizedFilename;
            }
            
            // Ensure filename has correct extension
            $filename = $this->ensureExtension($filename, $extension);
        }
        
        // Handle force flag - replace existing file by filename
        if ($force) {
            // 1. Check if target filename exists
            $existingFile = $this->db->fetch(
                "SELECT * FROM cdn_files WHERE filename = ?",
                [$filename]
            );
            
            if ($existingFile) {
                // Filename exists - check hash
                if ($existingFile['file_hash'] === $fileHash) {
                    // Same content - update existing record
                    return $this->forceReplaceFile($existingFile, $fileData, $fileHash, $extension, $mimeType, $isImage);
                } else {
                    // Different content - check if new hash exists elsewhere
                    $hashMatch = $this->db->fetch(
                        "SELECT * FROM cdn_files WHERE file_hash = ? AND id != ?",
                        [$fileHash, $existingFile['id']]
                    );
                    if ($hashMatch) {
                        // Hash exists elsewhere - delete target file and return existing file
                        $this->deleteFiles($existingFile['filename'], $existingFile['thumb_filename']);
                        $this->db->query("DELETE FROM cdn_files WHERE id = ?", [$existingFile['id']]);
                        return $this->getExistingFileData($hashMatch);
                    } else {
                        // New hash - replace target file
                        return $this->forceReplaceFile($existingFile, $fileData, $fileHash, $extension, $mimeType, $isImage);
                    }
                }
            } else {
                // Filename doesn't exist - check if hash exists
                $hashMatch = $this->db->fetch(
                    "SELECT * FROM cdn_files WHERE file_hash = ?",
                    [$fileHash]
                );
                if ($hashMatch) {
                    // Hash exists - return existing file
                    return $this->getExistingFileData($hashMatch);
                } else {
                    // Neither exists - create new
                    return $this->createNewFileRecord($fileData, $filename, $fileHash, $extension, $mimeType, $isImage);
                }
            }
        }
        
        // Check for existing file by hash (deduplication) - only when force=false
        $existingFile = $this->db->fetch(
            "SELECT * FROM cdn_files WHERE file_hash = ?",
            [$fileHash]
        );
        
        if ($existingFile) {
            // File with same content already exists
            if (DEDUPLICATE_UPLOADS) {
                // Return existing file data without any changes
                return $this->getExistingFileData($existingFile);
            } else {
                // Update existing record with new filename but keep same hash
                $this->updateExistingFile($existingFile, $filename, $extension, $fileData, $isImage);
                return $this->getUpdatedFileData($existingFile, $filename, $extension, $fileData, $isImage);
            }
        }
        
        // Handle filename conflicts (different content, same filename)
        $finalFilename = $filename;
        $filenameConflict = $this->db->fetch(
            "SELECT * FROM cdn_files WHERE filename = ?",
            [$filename]
        );
        
        if ($filenameConflict) {
            // Always generate unique filename for filename conflicts (different content)
            $finalFilename = $this->generateUniqueFilename($filename);
        }
        
        return $this->createNewFileRecord($fileData, $finalFilename, $fileHash, $extension, $mimeType, $isImage);
    }
    
    private function updateExistingFile($existingFile, $newFilename, $extension, $fileData, $isImage) {
        // Delete old files if filename changed
        if ($existingFile['filename'] !== $newFilename) {
            $this->deleteFiles($existingFile['filename'], $existingFile['thumb_filename']);
        }
        
        // Save new file
        $filePath = IMAGES_DIR . $newFilename;
        file_put_contents($filePath, $fileData);
        
        // Process thumbnail if needed
        $thumbFilename = '';
        $thumbWidth = 0;
        $thumbHeight = 0;
        $thumbSize = 0;
        
        if (in_array(strtolower($extension), THUMBNAIL_EXTENSIONS) && $isImage) {
            $thumbFilename = $newFilename;
            $thumbData = $this->createThumbnail($fileData, $extension);
            $thumbPath = THUMBS_DIR . $thumbFilename;
            file_put_contents($thumbPath, $thumbData);
            $thumbSize = strlen($thumbData);
            
            // Get thumbnail dimensions
            $thumbInfo = getimagesizefromstring($thumbData);
            if ($thumbInfo) {
                $thumbWidth = $thumbInfo[0];
                $thumbHeight = $thumbInfo[1];
            }
        }
        
        // Update database record
        $this->db->query(
            "UPDATE cdn_files SET 
                filename = ?, 
                thumb_filename = ?, 
                file_hash = ?, 
                original_width = ?, 
                original_height = ?, 
                width = ?, 
                height = ?, 
                thumb_width = ?, 
                thumb_height = ?, 
                file_size = ?, 
                thumb_size = ?, 
                extension = ?, 
                mime_type = ?, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?",
            [
                $newFilename,
                $thumbFilename,
                md5($fileData),
                $this->getImageDimensions($fileData, $isImage)['width'],
                $this->getImageDimensions($fileData, $isImage)['height'],
                $this->getImageDimensions($fileData, $isImage)['width'],
                $this->getImageDimensions($fileData, $isImage)['height'],
                $thumbWidth,
                $thumbHeight,
                strlen($fileData),
                $thumbSize,
                $extension,
                $mimeType,
                $existingFile['id']
            ]
        );
    }
    
    private function forceReplaceFile($existingFile, $fileData, $fileHash, $extension, $mimeType, $isImage) {
        // Delete old files (both main file and thumbnail)
        $this->deleteFiles($existingFile['filename'], $existingFile['thumb_filename']);
        
        // Save new file
        $filePath = IMAGES_DIR . $existingFile['filename'];
        file_put_contents($filePath, $fileData);
        
        // Process thumbnail if needed
        $thumbFilename = '';
        $thumbWidth = 0;
        $thumbHeight = 0;
        $thumbSize = 0;
        
        if (in_array(strtolower($extension), THUMBNAIL_EXTENSIONS) && $isImage) {
            $thumbFilename = $existingFile['filename'];
            $thumbData = $this->createThumbnail($fileData, $extension);
            $thumbPath = THUMBS_DIR . $thumbFilename;
            file_put_contents($thumbPath, $thumbData);
            $thumbSize = strlen($thumbData);
            
            // Get thumbnail dimensions
            $thumbInfo = getimagesizefromstring($thumbData);
            if ($thumbInfo) {
                $thumbWidth = $thumbInfo[0];
                $thumbHeight = $thumbInfo[1];
            }
        }
        
        // Update database record with all new metadata
        $this->db->query(
            "UPDATE cdn_files SET 
                thumb_filename = ?, 
                file_hash = ?, 
                original_width = ?, 
                original_height = ?, 
                width = ?, 
                height = ?, 
                thumb_width = ?, 
                thumb_height = ?, 
                file_size = ?, 
                thumb_size = ?, 
                extension = ?, 
                mime_type = ?, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?",
            [
                $thumbFilename,
                $fileHash,
                $this->getImageDimensions($fileData, $isImage)['width'],
                $this->getImageDimensions($fileData, $isImage)['height'],
                $this->getImageDimensions($fileData, $isImage)['width'],
                $this->getImageDimensions($fileData, $isImage)['height'],
                $thumbWidth,
                $thumbHeight,
                strlen($fileData),
                $thumbSize,
                $extension,
                $mimeType,
                $existingFile['id']
            ]
        );
        
        // Return updated file data
        return [
            'id' => intval($existingFile['id']),
            'filename' => $existingFile['filename'],
            'thumb_filename' => $thumbFilename,
            'file_hash' => $fileHash,
            'original_width' => $this->getImageDimensions($fileData, $isImage)['width'],
            'original_height' => $this->getImageDimensions($fileData, $isImage)['height'],
            'width' => $this->getImageDimensions($fileData, $isImage)['width'],
            'height' => $this->getImageDimensions($fileData, $isImage)['height'],
            'thumb_width' => $thumbWidth,
            'thumb_height' => $thumbHeight,
            'file_size' => strlen($fileData),
            'thumb_size' => $thumbSize,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'created_at' => $existingFile['created_at'],
            'updated_at' => date('Y-m-d H:i:s'),
            'urls' => [
                'image' => CDN_IMAGES_URL . $existingFile['filename'],
                'thumbnail' => $thumbFilename ? CDN_THUMBS_URL . $thumbFilename : null
            ]
        ];
    }
    
    private function getUpdatedFileData($existingFile, $newFilename, $extension, $fileData, $isImage) {
        // Get updated record
        $updatedRecord = $this->db->fetch(
            "SELECT * FROM cdn_files WHERE id = ?",
            [$existingFile['id']]
        );
        
        return [
            'id' => intval($updatedRecord['id']),
            'filename' => $updatedRecord['filename'],
            'thumb_filename' => $updatedRecord['thumb_filename'],
            'file_hash' => $updatedRecord['file_hash'],
            'original_width' => intval($updatedRecord['original_width']),
            'original_height' => intval($updatedRecord['original_height']),
            'width' => intval($updatedRecord['width']),
            'height' => intval($updatedRecord['height']),
            'thumb_width' => intval($updatedRecord['thumb_width']),
            'thumb_height' => intval($updatedRecord['thumb_height']),
            'file_size' => intval($updatedRecord['file_size']),
            'thumb_size' => intval($updatedRecord['thumb_size']),
            'extension' => $updatedRecord['extension'],
            'mime_type' => $updatedRecord['mime_type'],
            'created_at' => $updatedRecord['created_at'],
            'updated_at' => $updatedRecord['updated_at'],
            'urls' => [
                'image' => CDN_IMAGES_URL . $updatedRecord['filename'],
                'thumbnail' => $updatedRecord['thumb_filename'] ? CDN_THUMBS_URL . $updatedRecord['thumb_filename'] : null
            ]
        ];
    }
    
    private function getExistingFileData($existingFile) {
        // Update updated_at timestamp when existing file is accessed (deduplication)
        $this->db->query(
            "UPDATE cdn_files SET updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$existingFile['id']]
        );
        
        // Get updated file data
        $updatedFile = $this->db->fetch(
            "SELECT * FROM cdn_files WHERE id = ?",
            [$existingFile['id']]
        );
        
        // Return updated file data
        return [
            'id' => intval($updatedFile['id']),
            'filename' => $updatedFile['filename'],
            'thumb_filename' => $updatedFile['thumb_filename'],
            'file_hash' => $updatedFile['file_hash'],
            'original_width' => intval($updatedFile['original_width']),
            'original_height' => intval($updatedFile['original_height']),
            'width' => intval($updatedFile['width']),
            'height' => intval($updatedFile['height']),
            'thumb_width' => intval($updatedFile['thumb_width']),
            'thumb_height' => intval($updatedFile['thumb_height']),
            'file_size' => intval($updatedFile['file_size']),
            'thumb_size' => intval($updatedFile['thumb_size']),
            'extension' => $updatedFile['extension'],
            'mime_type' => $updatedFile['mime_type'],
            'created_at' => $updatedFile['created_at'],
            'updated_at' => $updatedFile['updated_at'],
            'urls' => [
                'image' => CDN_IMAGES_URL . $updatedFile['filename'],
                'thumbnail' => $updatedFile['thumb_filename'] ? CDN_THUMBS_URL . $updatedFile['thumb_filename'] : null
            ]
        ];
    }
    
    private function createNewFileRecord($fileData, $filename, $fileHash, $extension, $mimeType, $isImage) {
        // Save main file
        $filePath = IMAGES_DIR . $filename;
        file_put_contents($filePath, $fileData);
        $fileSize = strlen($fileData);
        
        // Get file dimensions
        $width = 0;
        $height = 0;
        $originalWidth = 0;
        $originalHeight = 0;
        
        if ($isImage) {
            $imageInfo = getimagesizefromstring($fileData);
            if ($imageInfo) {
                $originalWidth = $imageInfo[0];
                $originalHeight = $imageInfo[1];
                
                // Resize if needed
                if ($originalWidth > MAX_IMAGE_SIZE || $originalHeight > MAX_IMAGE_SIZE) {
                    $resizedData = $this->resizeImage($fileData, $extension);
                    file_put_contents($filePath, $resizedData);
                    $fileSize = strlen($resizedData);
                    
                    $resizedInfo = getimagesizefromstring($resizedData);
                    if ($resizedInfo) {
                        $width = $resizedInfo[0];
                        $height = $resizedInfo[1];
                    }
                } else {
                    $width = $originalWidth;
                    $height = $originalHeight;
                }
            }
        }
        
        // Process thumbnail
        $thumbFilename = '';
        $thumbWidth = 0;
        $thumbHeight = 0;
        $thumbSize = 0;
        
        if (in_array(strtolower($extension), THUMBNAIL_EXTENSIONS) && $isImage) {
            $thumbFilename = $filename;
            $thumbData = $this->createThumbnail($fileData, $extension);
            $thumbPath = THUMBS_DIR . $thumbFilename;
            file_put_contents($thumbPath, $thumbData);
            $thumbSize = strlen($thumbData);
            
            // Get thumbnail dimensions
            $thumbInfo = getimagesizefromstring($thumbData);
            if ($thumbInfo) {
                $thumbWidth = $thumbInfo[0];
                $thumbHeight = $thumbInfo[1];
            }
        }
        
        // Insert database record
        $this->db->query(
            "INSERT INTO cdn_files (
                filename, thumb_filename, file_hash, original_width, original_height,
                width, height, thumb_width, thumb_height, file_size, thumb_size,
                extension, mime_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $filename, $thumbFilename, $fileHash, $originalWidth, $originalHeight,
                $width, $height, $thumbWidth, $thumbHeight, $fileSize, $thumbSize,
                $extension, $mimeType
            ]
        );
        
        $id = $this->db->lastInsertId();
        
        return [
            'id' => intval($id),
            'filename' => $filename,
            'thumb_filename' => $thumbFilename,
            'file_hash' => $fileHash,
            'original_width' => intval($originalWidth),
            'original_height' => intval($originalHeight),
            'width' => intval($width),
            'height' => intval($height),
            'thumb_width' => intval($thumbWidth),
            'thumb_height' => intval($thumbHeight),
            'file_size' => intval($fileSize),
            'thumb_size' => intval($thumbSize),
            'extension' => $extension,
            'mime_type' => $mimeType,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'urls' => [
                'image' => CDN_IMAGES_URL . $filename,
                'thumbnail' => $thumbFilename ? CDN_THUMBS_URL . $thumbFilename : null
            ]
        ];
    }
    
    private function getFileInfo($fileData) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $fileData);
        finfo_close($finfo);
        
        // Check if it's an image
        $isImage = strpos($mimeType, 'image/') === 0;
        
        // Get extension from MIME type
        $extension = $this->getExtensionFromMime($mimeType);
        
        return [
            'mime_type' => $mimeType,
            'is_image' => $isImage,
            'extension' => $extension
        ];
    }
    
    private function getExtensionFromMime($mimeType) {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico',
            'image/avif' => 'avif',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/avi' => 'avi',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-ms-wmv' => 'wmv',
            'video/x-flv' => 'flv',
            'video/x-matroska' => 'mkv',
            'video/x-m4v' => 'm4v',
            'video/3gpp' => '3gp',
            'video/ogg' => 'ogv'
        ];
        
        return $mimeToExt[$mimeType] ?? 'bin';
    }
    
    private function resizeImage($imageData, $extension) {
        $image = imagecreatefromstring($imageData);
        if (!$image) {
            throw new Exception('Failed to create image from data');
        }
        
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);
        
        // Calculate new dimensions
        if ($originalWidth > $originalHeight) {
            $newWidth = MAX_IMAGE_SIZE;
            $newHeight = intval(($originalHeight * MAX_IMAGE_SIZE) / $originalWidth);
        } else {
            $newHeight = MAX_IMAGE_SIZE;
            $newWidth = intval(($originalWidth * MAX_IMAGE_SIZE) / $originalHeight);
        }
        
        // Create resized image
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        if (strtolower($extension) === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefill($resized, 0, 0, $transparent);
        }
        
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // Output to buffer
        ob_start();
        if (strtolower($extension) === 'jpg' || strtolower($extension) === 'jpeg') {
            imagejpeg($resized, null, JPEG_QUALITY);
        } elseif (strtolower($extension) === 'png') {
            imagepng($resized, null, PNG_COMPRESSION);
        } else {
            imagepng($resized, null, PNG_COMPRESSION);
        }
        $resizedData = ob_get_clean();
        
        imagedestroy($image);
        imagedestroy($resized);
        
        return $resizedData;
    }
    
    private function createThumbnail($imageData, $extension) {
        $image = imagecreatefromstring($imageData);
        if (!$image) {
            throw new Exception('Failed to create image from data');
        }
        
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);
        
        // Calculate thumbnail dimensions
        if ($originalWidth > $originalHeight) {
            $thumbWidth = MAX_THUMB_SIZE;
            $thumbHeight = intval(($originalHeight * MAX_THUMB_SIZE) / $originalWidth);
        } else {
            $thumbHeight = MAX_THUMB_SIZE;
            $thumbWidth = intval(($originalWidth * MAX_THUMB_SIZE) / $originalHeight);
        }
        
        // Create thumbnail
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Preserve transparency for PNG
        if (strtolower($extension) === 'png') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }
        
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $originalWidth, $originalHeight);
        
        // Output to buffer
        ob_start();
        if (strtolower($extension) === 'jpg' || strtolower($extension) === 'jpeg') {
            imagejpeg($thumbnail, null, JPEG_QUALITY);
        } elseif (strtolower($extension) === 'png') {
            imagepng($thumbnail, null, PNG_COMPRESSION);
        } else {
            imagepng($thumbnail, null, PNG_COMPRESSION);
        }
        $thumbnailData = ob_get_clean();
        
        imagedestroy($image);
        imagedestroy($thumbnail);
        
        return $thumbnailData;
    }
    
    private function generateRandomFilename($extension) {
        return uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }
    
    private function generateUniqueFilename($filename) {
        $pathInfo = pathinfo($filename);
        $name = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';
        
        $counter = 2;
        $newFilename = $filename;
        
        while ($this->db->fetch("SELECT id FROM cdn_files WHERE filename = ?", [$newFilename])) {
            $newFilename = $name . '_' . $counter . '.' . $extension;
            $counter++;
        }
        
        return $newFilename;
    }
    
    private function normalizeFilename($filename) {
        if (!NORMALIZE_FILENAMES) {
            return $filename;
        }
        
        // Get path info
        $pathInfo = pathinfo($filename);
        $name = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';
        
        // Replace problematic characters with hyphens
        $normalizedName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);
        
        // Remove multiple consecutive hyphens
        $normalizedName = preg_replace('/-+/', '-', $normalizedName);
        
        // Remove leading and trailing hyphens
        $normalizedName = trim($normalizedName, '-');
        
        // If normalization resulted in empty name, return null to trigger random generation
        if (empty($normalizedName)) {
            return null;
        }
        
        // Reconstruct filename with extension
        $normalizedFilename = $normalizedName;
        if (!empty($extension)) {
            $normalizedFilename .= '.' . $extension;
        }
        
        return $normalizedFilename;
    }
    
    private function ensureExtension($filename, $extension) {
        $pathInfo = pathinfo($filename);
        $currentExtension = $pathInfo['extension'] ?? '';
        
        if (empty($currentExtension)) {
            return $filename . '.' . $extension;
        }
        
        return $filename;
    }
    
    private function deleteFiles($filename, $thumbFilename) {
        // Delete main file
        $filePath = IMAGES_DIR . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete thumbnail if exists
        if (!empty($thumbFilename)) {
            $thumbPath = THUMBS_DIR . $thumbFilename;
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
    }

    private function getImageDimensions($fileData, $isImage) {
        $width = 0;
        $height = 0;

        if ($isImage) {
            $imageInfo = getimagesizefromstring($fileData);
            if ($imageInfo) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }
        return ['width' => $width, 'height' => $height];
    }
}
?> 