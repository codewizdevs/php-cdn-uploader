<?php
class UploadHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handle() {
        // Auto-detect upload type and get file data
        $uploadData = $this->detectAndGetUploadData();
        $filename = $uploadData['filename'];
        $fileData = $uploadData['file_data'];
        
        // Process upload
        $fileData = $this->processUpload($fileData, $filename);
        
        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => 'File uploaded successfully',
            'data' => $fileData
        ]);
    }
    
    private function detectAndGetUploadData() {
        $filename = '';
        $fileData = null;
        
        // Check if it's a multipart upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            // Multipart upload detected
            $uploadedFile = $_FILES['file'];
            
            // Check file size
            if ($uploadedFile['size'] > MAX_FILE_SIZE) {
                throw new Exception('File size exceeds 20MB limit');
            }
            
            // Read file data
            $fileData = file_get_contents($uploadedFile['tmp_name']);
            if ($fileData === false) {
                throw new Exception('Failed to read uploaded file');
            }
            
            $filename = $_POST['filename'] ?? '';
            
        } else {
            // Check if it's a JSON base64 upload
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) {
                    throw new Exception('Invalid JSON input');
                }
                
                $base64Data = $input['image'] ?? '';
                if (empty($base64Data)) {
                    throw new Exception('No image data provided in JSON');
                }
                
                // Handle data URL format (data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...)
                if (strpos($base64Data, 'data:') === 0) {
                    // Extract the base64 part from data URL
                    $parts = explode(',', $base64Data, 2);
                    if (count($parts) !== 2) {
                        throw new Exception('Invalid data URL format');
                    }
                    $base64Data = $parts[1];
                }
                
                // Decode base64 data
                $fileData = base64_decode($base64Data);
                if ($fileData === false) {
                    throw new Exception('Invalid base64 data');
                }
                
                // Check file size
                if (strlen($fileData) > MAX_FILE_SIZE) {
                    throw new Exception('File size exceeds 20MB limit');
                }
                
                $filename = $input['filename'] ?? '';
                
            } else {
                // Check for multipart upload errors
                if (isset($_FILES['file'])) {
                    $error = $_FILES['file']['error'];
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                    ];
                    throw new Exception($errorMessages[$error] ?? 'File upload failed');
                } else {
                    throw new Exception('No file data provided. Use multipart upload or JSON with base64 data.');
                }
            }
        }
        
        return [
            'filename' => $filename,
            'file_data' => $fileData
        ];
    }
    
    private function processUpload($fileData, $filename) {
        // Calculate file hash for deduplication
        $fileHash = sprintf('%08x', crc32($fileData));
        
        // Determine file type and extension
        $fileInfo = $this->getFileInfo($fileData);
        $extension = $fileInfo['extension'];
        $mimeType = $fileInfo['mime_type'];
        $isImage = $fileInfo['is_image'];
        
        // Check if extension is allowed
        if (!in_array(strtolower($extension), ALLOWED_EXTENSIONS)) {
            throw new Exception('File type not allowed');
        }
        
        // Check for existing file by hash (deduplication)
        $existingFile = $this->db->fetch(
            "SELECT * FROM cdn_files WHERE file_hash = ?",
            [$fileHash]
        );
        
        if ($existingFile) {
            // File with same content already exists
            if (DEDUPLICATE_UPLOADS) {
                // Update existing record with new filename and metadata
                $this->updateExistingFile($existingFile, $filename, $extension, $fileData, $isImage);
                return $this->getUpdatedFileData($existingFile, $filename, $extension, $fileData, $isImage);
            } else {
                // Create new record with different filename but same hash
                $finalFilename = $this->generateUniqueFilename($filename);
                return $this->createNewFileRecord($fileData, $finalFilename, $fileHash, $extension, $mimeType, $isImage);
            }
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
        
        // Handle filename conflicts (different content, same filename)
        $finalFilename = $filename;
        $filenameConflict = $this->db->fetch(
            "SELECT * FROM cdn_files WHERE filename = ?",
            [$filename]
        );
        
        if ($filenameConflict) {
            if (DEDUPLICATE_UPLOADS) {
                // Delete existing file with different content
                $this->deleteFiles($filenameConflict['filename'], $filenameConflict['thumb_filename']);
            } else {
                // Generate unique filename
                $finalFilename = $this->generateUniqueFilename($filename);
            }
        }
        
        return $this->createNewFileRecord($fileData, $finalFilename, $fileHash, $extension, $mimeType, $isImage);
    }
    
    private function updateExistingFile($existingFile, $filename, $extension, $fileData, $isImage) {
        // Update filename in database
        $this->db->query(
            "UPDATE cdn_files SET filename = ?, upload_date = ? WHERE id = ?",
            [$filename, date('Y-m-d H:i:s'), $existingFile['id']]
        );
        
        // Update physical file if filename changed
        if ($existingFile['filename'] !== $filename) {
            $oldPath = IMAGES_DIR . $existingFile['filename'];
            $newPath = IMAGES_DIR . $filename;
            
            if (file_exists($oldPath)) {
                rename($oldPath, $newPath);
            }
            
            // Update thumbnail if it exists
            if ($existingFile['thumb_filename']) {
                $oldThumbPath = THUMBS_DIR . $existingFile['thumb_filename'];
                $newThumbPath = THUMBS_DIR . $filename;
                
                if (file_exists($oldThumbPath)) {
                    rename($oldThumbPath, $newThumbPath);
                    
                    // Update thumbnail filename in database
                    $this->db->query(
                        "UPDATE cdn_files SET thumb_filename = ? WHERE id = ?",
                        [$filename, $existingFile['id']]
                    );
                }
            }
        }
    }
    
    private function getUpdatedFileData($existingFile, $filename, $extension, $fileData, $isImage) {
        // Return updated file data with new filename
        return [
            'id' => $existingFile['id'],
            'filename' => $filename,
            'thumb_filename' => $filename, // Same filename for thumbnail
            'file_hash' => $existingFile['file_hash'],
            'original_width' => intval($existingFile['original_width']),
            'original_height' => intval($existingFile['original_height']),
            'width' => intval($existingFile['width']),
            'height' => intval($existingFile['height']),
            'thumb_width' => intval($existingFile['thumb_width']),
            'thumb_height' => intval($existingFile['thumb_height']),
            'file_size' => intval($existingFile['file_size']),
            'thumb_size' => intval($existingFile['thumb_size']),
            'extension' => $existingFile['extension'],
            'mime_type' => $existingFile['mime_type'],
            'upload_date' => date('Y-m-d H:i:s')
        ];
    }
    
    private function createNewFileRecord($fileData, $filename, $fileHash, $extension, $mimeType, $isImage) {
        // Save main file
        $filePath = IMAGES_DIR . $filename;
        if (file_put_contents($filePath, $fileData) === false) {
            throw new Exception('Failed to save file');
        }
        
        // Initialize variables
        $thumbFilename = '';
        $thumbSize = 0;
        $width = 0;
        $height = 0;
        $thumbWidth = 0;
        $thumbHeight = 0;
        
        // Process image and create thumbnail if it's a supported image format
        if ($isImage && in_array(strtolower($extension), THUMBNAIL_EXTENSIONS)) {
            $imageInfo = getimagesizefromstring($fileData);
            if ($imageInfo !== false) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
                
                // Create image resource
                $image = imagecreatefromstring($fileData);
                if ($image) {
                    // Resize image if needed
                    $processedImage = $this->resizeImage($image, $width, $height, MAX_IMAGE_SIZE);
                    $thumbImage = $this->resizeImage($image, $width, $height, MAX_THUMB_SIZE);
                    
                    // Save resized main image
                    $this->saveImage($processedImage, $filePath, $extension);
                    
                    // Save thumbnail
                    $thumbFilename = $filename; // Same filename for thumbnail
                    $thumbPath = THUMBS_DIR . $thumbFilename;
                    $this->saveImage($thumbImage, $thumbPath, $extension);
                    
                    // Get new dimensions and sizes
                    $newDimensions = $this->getImageDimensions($processedImage);
                    $thumbDimensions = $this->getImageDimensions($thumbImage);
                    $width = $newDimensions['width'];
                    $height = $newDimensions['height'];
                    $thumbWidth = $thumbDimensions['width'];
                    $thumbHeight = $thumbDimensions['height'];
                    $thumbSize = filesize($thumbPath);
                    
                    // Clean up image resources
                    imagedestroy($image);
                    imagedestroy($processedImage);
                    imagedestroy($thumbImage);
                }
            }
        }
        
        // Ensure thumb_filename is never null for current database schema
        if ($thumbFilename === null) {
            $thumbFilename = ''; // Empty string instead of null for files without thumbnails
        }
        
        // Get final file size
        $finalFileSize = filesize($filePath);
        
        // Save to database
        $dbData = [
            'filename' => $filename,
            'thumb_filename' => $thumbFilename,
            'file_hash' => $fileHash,
            'original_width' => $width,
            'original_height' => $height,
            'width' => $width,
            'height' => $height,
            'thumb_width' => $thumbWidth,
            'thumb_height' => $thumbHeight,
            'file_size' => $finalFileSize,
            'thumb_size' => $thumbSize,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'upload_date' => date('Y-m-d H:i:s')
        ];
        
        // Insert new record
        $this->db->query(
            "INSERT INTO cdn_files (filename, thumb_filename, file_hash, original_width, original_height, 
            width, height, thumb_width, thumb_height, file_size, thumb_size, extension, mime_type, upload_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $dbData['filename'], $dbData['thumb_filename'], $dbData['file_hash'], $dbData['original_width'], $dbData['original_height'],
                $dbData['width'], $dbData['height'], $dbData['thumb_width'], $dbData['thumb_height'],
                $dbData['file_size'], $dbData['thumb_size'], $dbData['extension'], $dbData['mime_type'],
                $dbData['upload_date']
            ]
        );
        $dbData['id'] = $this->db->lastInsertId();
        
        return $dbData;
    }
    
    private function getFileInfo($fileData) {
        // Try to get image info first
        $imageInfo = getimagesizefromstring($fileData);
        if ($imageInfo !== false) {
            $mimeType = $imageInfo['mime'];
            $extension = $this->getExtensionFromMime($mimeType);
            return [
                'extension' => $extension,
                'mime_type' => $mimeType,
                'is_image' => true
            ];
        }
        
        // If not an image, try to determine video format by checking file header
        $header = substr($fileData, 0, 12);
        
        // Video format detection
        if (strpos($header, 'ftyp') !== false) {
            // MP4, M4V, 3GP
            if (strpos($header, 'mp4') !== false || strpos($header, 'M4V') !== false) {
                return ['extension' => 'mp4', 'mime_type' => 'video/mp4', 'is_image' => false];
            }
            if (strpos($header, '3gp') !== false) {
                return ['extension' => '3gp', 'mime_type' => 'video/3gpp', 'is_image' => false];
            }
        }
        
        if (strpos($header, 'RIFF') === 0 && strpos($header, 'WEBM', 8) !== false) {
            return ['extension' => 'webm', 'mime_type' => 'video/webm', 'is_image' => false];
        }
        
        if (strpos($header, 'RIFF') === 0 && strpos($header, 'AVI', 8) !== false) {
            return ['extension' => 'avi', 'mime_type' => 'video/x-msvideo', 'is_image' => false];
        }
        
        if (strpos($header, 'GIF8') === 0) {
            return ['extension' => 'gif', 'mime_type' => 'image/gif', 'is_image' => true];
        }
        
        // Default to mp4 if we can't determine
        return ['extension' => 'mp4', 'mime_type' => 'video/mp4', 'is_image' => false];
    }
    
    private function getExtensionFromMime($mimeType) {
        $mimeMap = [
            // Images
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/tif' => 'tif',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico',
            'image/avif' => 'avif',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            // Videos
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/x-msvideo' => 'avi',
            'video/quicktime' => 'mov',
            'video/x-ms-wmv' => 'wmv',
            'video/x-flv' => 'flv',
            'video/x-matroska' => 'mkv',
            'video/x-m4v' => 'm4v',
            'video/3gpp' => '3gp',
            'video/ogg' => 'ogv'
        ];
        
        return $mimeMap[$mimeType] ?? 'mp4';
    }
    
    private function generateRandomFilename($extension) {
        return uniqid() . '_' . time() . '.' . $extension;
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
        $currentExt = pathinfo($filename, PATHINFO_EXTENSION);
        if (empty($currentExt)) {
            return $filename . '.' . $extension;
        }
        return $filename;
    }
    
    private function generateThumbFilename($filename) {
        return $filename;
    }
    
    private function generateUniqueFilename($filename) {
        $pathInfo = pathinfo($filename);
        $name = $pathInfo['filename'];
        $extension = $pathInfo['extension'];
        $counter = 1;
        $uniqueFilename = $filename;
        
        // Keep trying until we find a unique filename
        while ($this->db->fetch("SELECT id FROM cdn_files WHERE filename = ?", [$uniqueFilename])) {
            $counter++;
            $uniqueFilename = $name . '_' . $counter . '.' . $extension;
        }
        
        return $uniqueFilename;
    }
    
    private function resizeImage($image, $width, $height, $maxSize) {
        if ($width <= $maxSize && $height <= $maxSize) {
            return $image;
        }
        
        $ratio = min($maxSize / $width, $maxSize / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
        
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        return $resized;
    }
    
    private function saveImage($image, $path, $extension) {
        $extension = strtolower($extension);
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $path, JPEG_QUALITY);
                break;
            case 'png':
                imagepng($image, $path, PNG_COMPRESSION);
                break;
            case 'gif':
                imagegif($image, $path);
                break;
            case 'webp':
                imagewebp($image, $path, JPEG_QUALITY);
                break;
            case 'bmp':
                imagewbmp($image, $path);
                break;
            default:
                imagejpeg($image, $path, JPEG_QUALITY);
        }
    }
    
    private function getImageDimensions($image) {
        return [
            'width' => imagesx($image),
            'height' => imagesy($image)
        ];
    }
    
    private function deleteFiles($filename, $thumbFilename) {
        $imagePath = IMAGES_DIR . $filename;
        $thumbPath = THUMBS_DIR . $thumbFilename;
        
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        if ($thumbFilename && file_exists($thumbPath)) {
            unlink($thumbPath);
        }
    }
}
?> 