#!/usr/bin/env php
<?php
/**
 * CDN File Migration Script
 * 
 * This script migrates existing files in the img/ directory to the CDN database.
 * It will:
 * 1. Create database records for files without records
 * 2. Generate thumbnails for JPG/JPEG/PNG files if they don't exist
 * 3. Calculate MD5 hashes for all files
 * 4. Update file creation dates
 * 
 * Usage: php migrate_files.php
 */

// Load configuration
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/database.php';

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    echo "✅ Database connection established\n";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Function to get file information
function getFileInfo($fileData) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_buffer($finfo, $fileData);
    finfo_close($finfo);
    
    // Check if it's an image
    $isImage = strpos($mimeType, 'image/') === 0;
    
    // Get extension from MIME type
    $extension = getExtensionFromMime($mimeType);
    
    return [
        'mime_type' => $mimeType,
        'is_image' => $isImage,
        'extension' => $extension
    ];
}

function getExtensionFromMime($mimeType) {
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

// Function to create thumbnail
function createThumbnail($imageData, $extension) {
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

// Function to resize image if needed
function resizeImage($imageData, $extension) {
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

// Check if img directory exists
if (!is_dir(IMAGES_DIR)) {
    die("❌ Images directory not found: " . IMAGES_DIR . "\n");
}

// Create thumbs directory if it doesn't exist
if (!is_dir(THUMBS_DIR)) {
    mkdir(THUMBS_DIR, 0755, true);
    echo "📁 Created thumbs directory: " . THUMBS_DIR . "\n";
}

// Get all files in img directory
$files = glob(IMAGES_DIR . '*');
$totalFiles = count($files);
$processedFiles = 0;
$createdRecords = 0;
$updatedRecords = 0;
$createdThumbnails = 0;
$skippedRecords = 0;

echo "🚀 Starting migration of {$totalFiles} files...\n\n";

foreach ($files as $filePath) {
    $filename = basename($filePath);
    $processedFiles++;
    
    echo "[{$processedFiles}/{$totalFiles}] Processing: {$filename}\n";
    
    // Check if file exists and is readable
    if (!is_file($filePath) || !is_readable($filePath)) {
        echo "  ⚠️  Skipping: File not readable\n";
        continue;
    }
    
    // Get file information
    $fileSize = filesize($filePath);
    $fileTime = filemtime($filePath);
    $createdDate = date('Y-m-d H:i:s', $fileTime);
    
    // Determine file type and get dimensions
    $fileData = file_get_contents($filePath);
    $fileHash = md5($fileData); // Calculate MD5 hash
    $fileTypeInfo = getFileInfo($fileData);
    $isImage = $fileTypeInfo['is_image'];
    $mimeType = $fileTypeInfo['mime_type'];
    $extension = $fileTypeInfo['extension'];
    
    // Check if extension is allowed
    if (!in_array(strtolower($extension), ALLOWED_EXTENSIONS)) {
        echo "  ⚠️  Skipping: Extension '{$extension}' not allowed\n";
        continue;
    }
    
    // Get image dimensions
    $width = 0;
    $height = 0;
    $originalWidth = 0;
    $originalHeight = 0;
    
    if ($isImage) {
        $imageInfo = getimagesizefromstring($fileData);
        if ($imageInfo) {
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            
            // Check if image needs resizing
            if ($originalWidth > MAX_IMAGE_SIZE || $originalHeight > MAX_IMAGE_SIZE) {
                echo "  🔄 Resizing image from {$originalWidth}x{$originalHeight} to max " . MAX_IMAGE_SIZE . "px\n";
                $resizedData = resizeImage($fileData, $extension);
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
    
    // Check if database record exists by filename
    $existingRecord = $pdo->query("SELECT * FROM cdn_files WHERE filename = '{$filename}'")->fetch();
    
    // Check if file with same hash already exists
    $existingHashRecord = $pdo->query("SELECT * FROM cdn_files WHERE file_hash = '{$fileHash}'")->fetch();
    
    if (!$existingRecord && !$existingHashRecord) {
        // Create new database record
        echo "  📝 Creating database record...\n";
        
        // Process thumbnail if needed
        $thumbFilename = '';
        $thumbWidth = 0;
        $thumbHeight = 0;
        $thumbSize = 0;
        
        if (in_array(strtolower($extension), THUMBNAIL_EXTENSIONS) && $isImage) {
            $thumbPath = THUMBS_DIR . $filename;
            
            if (!file_exists($thumbPath)) {
                echo "  🖼️  Creating thumbnail...\n";
                $thumbData = createThumbnail($fileData, $extension);
                file_put_contents($thumbPath, $thumbData);
                $thumbSize = strlen($thumbData);
                $createdThumbnails++;
                
                // Get thumbnail dimensions
                $thumbInfo = getimagesizefromstring($thumbData);
                if ($thumbInfo) {
                    $thumbWidth = $thumbInfo[0];
                    $thumbHeight = $thumbInfo[1];
                }
            } else {
                echo "  ✅ Thumbnail already exists\n";
                $thumbSize = filesize($thumbPath);
                
                // Get thumbnail dimensions
                $thumbInfo = getimagesize($thumbPath);
                if ($thumbInfo) {
                    $thumbWidth = $thumbInfo[0];
                    $thumbHeight = $thumbInfo[1];
                }
            }
            
            $thumbFilename = $filename;
        }
        
        // Insert new record with duplicate handling
        try {
            $stmt = $pdo->prepare("
                INSERT INTO cdn_files (
                    filename, thumb_filename, file_hash, original_width, original_height,
                    width, height, thumb_width, thumb_height, file_size, thumb_size,
                    extension, mime_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $filename,
                $thumbFilename,
                $fileHash,
                $originalWidth,
                $originalHeight,
                $width,
                $height,
                $thumbWidth,
                $thumbHeight,
                $fileSize,
                $thumbSize,
                $extension,
                $mimeType
            ]);
            
            $createdRecords++;
            echo "  ✅ Record created successfully\n";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "  ⚠️  Record already exists (hash collision), skipping...\n";
                $skippedRecords++;
            } else {
                throw $e; // Re-throw if it's not a duplicate key error
            }
        }
        
    } elseif ($existingHashRecord && !$existingRecord) {
        // File with same hash exists but different filename - update the existing record
        echo "  🔄 File with same content exists, updating existing record...\n";
        
        // Update the existing record with new filename and metadata
        $stmt = $pdo->prepare("
            UPDATE cdn_files SET 
                filename = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE file_hash = ?
        ");
        
        $stmt->execute([$filename, $fileHash]);
        
        $updatedRecords++;
        echo "  ✅ Record updated with new filename\n";
        
    } else {
        // Update existing record
        echo "  🔄 Updating existing record...\n";
        
        // Check if the hash we're trying to set already exists in another record
        $hashExistsElsewhere = $pdo->query("SELECT COUNT(*) FROM cdn_files WHERE file_hash = '{$fileHash}' AND filename != '{$filename}'")->fetchColumn();
        
        if ($hashExistsElsewhere > 0) {
            echo "  ⚠️  Hash already exists in another record, skipping hash update...\n";
            
            // Only update timestamp, not the hash
            $stmt = $pdo->prepare("
                UPDATE cdn_files SET 
                    updated_at = CURRENT_TIMESTAMP
                WHERE filename = ?
            ");
            
            $stmt->execute([$filename]);
        } else {
            // Update hash and timestamp
            $stmt = $pdo->prepare("
                UPDATE cdn_files SET 
                    file_hash = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE filename = ?
            ");
            
            $stmt->execute([$fileHash, $filename]);
        }
        
        // Create thumbnail if missing and applicable
        if (in_array(strtolower($extension), THUMBNAIL_EXTENSIONS) && $isImage) {
            $thumbPath = THUMBS_DIR . $filename;
            
            if (!file_exists($thumbPath)) {
                echo "  🖼️  Creating missing thumbnail...\n";
                $thumbData = createThumbnail($fileData, $extension);
                file_put_contents($thumbPath, $thumbData);
                $thumbSize = strlen($thumbData);
                $createdThumbnails++;
                
                // Get thumbnail dimensions
                $thumbInfo = getimagesizefromstring($thumbData);
                if ($thumbInfo) {
                    $thumbWidth = $thumbInfo[0];
                    $thumbHeight = $thumbInfo[1];
                }
                
                // Update thumbnail info in database
                $stmt = $pdo->prepare("
                    UPDATE cdn_files SET 
                        thumb_filename = ?,
                        thumb_width = ?,
                        thumb_height = ?,
                        thumb_size = ?
                    WHERE filename = ?
                ");
                
                $stmt->execute([$filename, $thumbWidth, $thumbHeight, $thumbSize, $filename]);
            } else {
                echo "  ✅ Thumbnail already exists\n";
            }
        }
        
        $updatedRecords++;
        echo "  ✅ Record updated successfully\n";
    }
    
    echo "\n";
}

// Summary
echo "🎉 Migration completed!\n";
echo "📊 Summary:\n";
echo "   • Total files processed: {$processedFiles}\n";
echo "   • New records created: {$createdRecords}\n";
echo "   • Records updated: {$updatedRecords}\n";
echo "   • Records skipped (duplicates): {$skippedRecords}\n";
echo "   • Thumbnails created: {$createdThumbnails}\n";
echo "   • Hash algorithm: MD5\n";
echo "   • Timestamps: created_at/updated_at\n";

echo "\n✅ Migration script completed successfully!\n";
?> 