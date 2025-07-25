<?php
// Sample Configuration File
// Copy this file to config.php and update with your settings

// Domain Configuration
define('CDN_DOMAIN', 'https://cdn.yourdomain.com');
define('CDN_BASE_URL', CDN_DOMAIN . '/api/');
define('CDN_IMAGES_URL', CDN_DOMAIN . '/img/');
define('CDN_THUMBS_URL', CDN_DOMAIN . '/thumbs/');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// File Storage Configuration - Using absolute paths
define('PROJECT_ROOT', dirname(__DIR__)); // Go up one level from api/ directory
define('IMAGES_DIR', PROJECT_ROOT . '/img/');
define('THUMBS_DIR', PROJECT_ROOT . '/thumbs/');

// Image Processing Configuration
define('MAX_IMAGE_SIZE', 700);  // Maximum largest side for main images
define('MAX_THUMB_SIZE', 300);  // Maximum largest side for thumbnails
define('JPEG_QUALITY', 95);     // JPEG quality (0-100)
define('PNG_COMPRESSION', 6);   // PNG compression level (0-9, 0 = no compression)

// File Size Configuration
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB in bytes

// API Configuration - Single hardcoded API key
define('API_KEY', 'your-secure-api-key-here');

// Pagination Configuration
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Allowed file extensions (all image formats + popular video formats)
define('ALLOWED_EXTENSIONS', [
    // Image formats
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'svg', 'ico', 'avif', 'heic', 'heif',
    // Video formats
    'mp4', 'webm', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'm4v', '3gp', 'ogv'
]);

// Formats that get thumbnails generated (only jpg, jpeg, png)
define('THUMBNAIL_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Upload Configuration
define('DEDUPLICATE_UPLOADS', false); // If true, replace existing files; if false, create new records with appended numbers
define('NORMALIZE_FILENAMES', true); // If true, normalize filenames to be filesystem-safe; if false, use as-is

// Create directories if they don't exist
if (!is_dir(IMAGES_DIR)) {
    mkdir(IMAGES_DIR, 0755, true);
}

if (!is_dir(THUMBS_DIR)) {
    mkdir(THUMBS_DIR, 0755, true);
}
?> 