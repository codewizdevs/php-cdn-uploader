-- PHP CDN Uploader Database Schema
-- This schema creates the necessary database and table for the CDN system

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `cdn` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `cdn`;

-- Drop table if it exists (for clean installation)
DROP TABLE IF EXISTS `cdn_files`;

-- Create the main files table
CREATE TABLE IF NOT EXISTS `cdn_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `thumb_filename` varchar(255) NULL,
  `file_hash` varchar(32) NOT NULL,
  `original_width` int(11) NOT NULL DEFAULT 0,
  `original_height` int(11) NOT NULL DEFAULT 0,
  `width` int(11) NOT NULL DEFAULT 0,
  `height` int(11) NOT NULL DEFAULT 0,
  `thumb_width` int(11) NOT NULL DEFAULT 0,
  `thumb_height` int(11) NOT NULL DEFAULT 0,
  `file_size` bigint(20) NOT NULL,
  `thumb_size` bigint(20) NOT NULL DEFAULT 0,
  `extension` varchar(10) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filename` (`filename`),
  UNIQUE KEY `file_hash` (`file_hash`),
  KEY `idx_extension` (`extension`),
  KEY `idx_file_size` (`file_size`),
  KEY `idx_thumb_filename` (`thumb_filename`),
  KEY `idx_mime_type` (`mime_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data (optional - for testing)
-- INSERT INTO `cdn_files` (`filename`, `thumb_filename`, `file_hash`, `original_width`, `original_height`, `width`, `height`, `thumb_width`, `thumb_height`, `file_size`, `thumb_size`, `extension`, `mime_type`) VALUES
-- ('sample.jpg', 'sample.jpg', 'd41d8cd98f00b204e9800998ecf8427e', 1920, 1080, 700, 394, 300, 169, 45678, 12345, 'jpg', 'image/jpeg');

-- Show table structure
DESCRIBE `cdn_files`;

-- Show indexes
SHOW INDEX FROM `cdn_files`; 