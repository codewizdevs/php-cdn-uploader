#!/usr/bin/env php
<?php
/**
 * CDN File Migration Script
 * 
 * This script processes existing files in the img/ directory and:
 * 1. Creates database records for files that don't exist in the database
 * 2. Creates thumbnails for JPG, JPEG, PNG files that don't have thumbnails
 * 3. Updates database records with file creation dates
 * 
 * Usage: php migrate_files.php
 * 
 * Requirements:
 * - Must be run from CLI
 * - GD library must be installed
 * - Database must be accessible
 * - img/ and thumbs/ directories must exist
 */

// Prevent web execution
if (php_sapi_name() !== 'cli') {
    die("This script can only be executed from the command line.\n");
}

// Include configuration
require_once __DIR__ . '/api/config.php';

// Database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    echo "✓ Database connection established\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Check if GD library is available
if (!extension_loaded('gd')) {
    die("GD library is required but not installed.\n");
}

// Check directories
if (!is_dir(IMAGES_DIR)) {
    die("Images directory not found: " . IMAGES_DIR . "\n");
}

if (!is_dir(THUMBS_DIR)) {
    echo "Creating thumbs directory: " . THUMBS_DIR . "\n";
    mkdir(THUMBS_DIR, 0755, true);
}

echo "✓ Directories verified\n\n";

// Statistics
$stats = [
    'total_files' => 0,
    'new_records' => 0,
    'new_thumbnails' => 0,
    'updated_records' => 0,
    'errors' => 0
];

// Get all files from img directory
$files = glob(IMAGES_DIR . '*');
$totalFiles = count($files);

echo "Found {$totalFiles} files in " . IMAGES_DIR . "\n\n";

if ($totalFiles === 0) {
    echo "No files to process.\n";
    exit(0);
}

// Process each file
foreach ($files as $filePath) {
    $stats['total_files']++;
    
    // Skip directories
    if (is_dir($filePath)) {
        continue;
    }
    
    $filename = basename($filePath);
    $fileInfo = pathinfo($filename);
    $extension = strtolower($fileInfo['extension']);
    
    // Skip files without extension or unsupported extensions
    if (empty($extension) || !in_array($extension, ALLOWED_EXTENSIONS)) {
        echo "⚠ Skipping {$filename} (unsupported extension)\n";
        continue;
    }
    
    echo "Processing: {$filename} ({$stats['total_files']}/{$totalFiles})\n";
    
    try {
        // Get file information
        $fileSize = filesize($filePath);
        $fileTime = filemtime($filePath);
        $uploadDate = date('Y-m-d H:i:s', $fileTime);
        
        // Determine file type and get dimensions
        $fileData = file_get_contents($filePath);
        $fileHash = sprintf('%08x', crc32($fileData)); // Calculate CRC32 hash
        $fileTypeInfo = getFileInfo($fileData);
        $isImage = $fileTypeInfo['is_image'];
        $mimeType = $fileTypeInfo['mime_type'];
        
        // Initialize variables
        $thumbFilename = '';
        $thumbSize = 0;
        $width = 0;
        $height = 0;
        $thumbWidth = 0;
        $thumbHeight = 0;
        
        // Get image dimensions if it's an image
        if ($isImage) {
            $imageInfo = getimagesize($filePath);
            if ($imageInfo !== false) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }
        
        // Check if database record exists
        $stmt = $pdo->prepare("SELECT * FROM cdn_files WHERE filename = ?");
        $stmt->execute([$filename]);
        $existingRecord = $stmt->fetch();
        
        // Create thumbnail if it's a supported image format
        if ($isImage && in_array($extension, THUMBNAIL_EXTENSIONS)) {
            $thumbFilename = $filename; // Same filename for thumbnail
            $thumbPath = THUMBS_DIR . $thumbFilename;
            
            // Check if thumbnail exists
            $thumbnailExists = file_exists($thumbPath);
            
            if (!$thumbnailExists) {
                echo "  🖼️  Creating thumbnail...\n";
                $thumbnailCreated = createThumbnail($filePath, $thumbPath, $extension);
                if ($thumbnailCreated) {
                    $thumbSize = filesize($thumbPath);
                    $thumbDimensions = getImageDimensions($thumbPath);
                    $thumbWidth = $thumbDimensions['width'];
                    $thumbHeight = $thumbDimensions['height'];
                    $stats['new_thumbnails']++;
                } else {
                    echo "  ❌ Failed to create thumbnail\n";
                    $stats['errors']++;
                    continue;
                }
            } else {
                // Use existing thumbnail
                $thumbSize = filesize($thumbPath);
                $thumbDimensions = getImageDimensions($thumbPath);
                $thumbWidth = $thumbDimensions['width'];
                $thumbHeight = $thumbDimensions['height'];
            }
        }
        
        if (!$existingRecord) {
            // Create new database record
            echo "  📝 Creating database record...\n";
            
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO cdn_files (
                    filename, thumb_filename, file_hash, original_width, original_height,
                    width, height, thumb_width, thumb_height, file_size, thumb_size,
                    extension, mime_type, upload_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $filename,
                $thumbFilename,
                $fileHash,
                $width,
                $height,
                $width,
                $height,
                $thumbWidth,
                $thumbHeight,
                $fileSize,
                $thumbSize,
                $extension,
                $mimeType,
                $uploadDate
            ]);
            
            $stats['new_records']++;
            echo "  ✅ Record created successfully\n";
            
        } else {
            // Update existing record with file creation date
            echo "  🔄 Updating existing record...\n";
            
            // Create thumbnail if it doesn't exist and should have one
            if ($isImage && in_array($extension, THUMBNAIL_EXTENSIONS) && empty($thumbFilename)) {
                $thumbFilename = $filename;
                $thumbPath = THUMBS_DIR . $thumbFilename;
                
                if (!file_exists($thumbPath)) {
                    echo "  🖼️  Creating thumbnail...\n";
                    $thumbnailCreated = createThumbnail($filePath, $thumbPath, $extension);
                    if ($thumbnailCreated) {
                        $thumbSize = filesize($thumbPath);
                        $thumbDimensions = getImageDimensions($thumbPath);
                        $thumbWidth = $thumbDimensions['width'];
                        $thumbHeight = $thumbDimensions['height'];
                        $stats['new_thumbnails']++;
                        
                        // Update record with new thumbnail info
                        $stmt = $pdo->prepare("
                            UPDATE cdn_files SET 
                            thumb_filename = ?, thumb_width = ?, thumb_height = ?, 
                            thumb_size = ?, upload_date = ? 
                            WHERE filename = ?
                        ");
                        $stmt->execute([
                            $thumbFilename,
                            $thumbWidth,
                            $thumbHeight,
                            $thumbSize,
                            $uploadDate,
                            $filename
                        ]);
                    } else {
                        echo "  ❌ Failed to create thumbnail\n";
                        $stats['errors']++;
                        continue;
                    }
                } else {
                    // Update upload date only
                    $stmt = $pdo->prepare("UPDATE cdn_files SET upload_date = ? WHERE filename = ?");
                    $stmt->execute([$uploadDate, $filename]);
                }
            } else {
                // Update upload date only
                $stmt = $pdo->prepare("UPDATE cdn_files SET upload_date = ? WHERE filename = ?");
                $stmt->execute([$uploadDate, $filename]);
            }
            
            $stats['updated_records']++;
            echo "  ✅ Record updated successfully\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ Error processing {$filename}: " . $e->getMessage() . "\n";
        $stats['errors']++;
    }
    
    echo "\n";
}

// Display final statistics
echo "=== Migration Complete ===\n";
echo "Total files processed: {$stats['total_files']}\n";
echo "New database records: {$stats['new_records']}\n";
echo "New thumbnails created: {$stats['new_thumbnails']}\n";
echo "Records updated: {$stats['updated_records']}\n";
echo "Errors: {$stats['errors']}\n";

if ($stats['errors'] > 0) {
    echo "\n⚠ Some files had errors during processing.\n";
    exit(1);
} else {
    echo "\n✅ Migration completed successfully!\n";
    exit(0);
}

/**
 * Get file information (copied from upload handler)
 */
function getFileInfo($fileData) {
    // Try to get image info first
    $imageInfo = getimagesizefromstring($fileData);
    if ($imageInfo !== false) {
        $mimeType = $imageInfo['mime'];
        $extension = getExtensionFromMime($mimeType);
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

/**
 * Get extension from MIME type (copied from upload handler)
 */
function getExtensionFromMime($mimeType) {
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

/**
 * Create thumbnail from source image
 */
function createThumbnail($sourcePath, $thumbPath, $extension) {
    try {
        // Create image resource
        $image = imagecreatefromstring(file_get_contents($sourcePath));
        if (!$image) {
            return false;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Resize for thumbnail
        $thumbImage = resizeImage($image, $width, $height, MAX_THUMB_SIZE);
        
        // Save thumbnail
        $saved = saveImage($thumbImage, $thumbPath, $extension);
        
        // Clean up
        imagedestroy($image);
        imagedestroy($thumbImage);
        
        return $saved;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Resize image maintaining aspect ratio
 */
function resizeImage($image, $width, $height, $maxSize) {
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

/**
 * Save image with appropriate format
 */
function saveImage($image, $path, $extension) {
    $extension = strtolower($extension);
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            return imagejpeg($image, $path, JPEG_QUALITY);
        case 'png':
            return imagepng($image, $path, PNG_COMPRESSION);
        case 'gif':
            return imagegif($image, $path);
        case 'webp':
            return imagewebp($image, $path, JPEG_QUALITY);
        case 'bmp':
            return imagewbmp($image, $path);
        default:
            return imagejpeg($image, $path, JPEG_QUALITY);
    }
}

/**
 * Get image dimensions
 */
function getImageDimensions($path) {
    $imageInfo = getimagesize($path);
    return [
        'width' => $imageInfo[0],
        'height' => $imageInfo[1]
    ];
}
?> 