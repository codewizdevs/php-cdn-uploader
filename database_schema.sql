-- CDN Database Schema
-- Import this file into phpMyAdmin to create the required database structure
-- Optimized for millions of records with proper indexing

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `cdn` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the cdn database
USE `cdn`;

-- Create the main files table
CREATE TABLE IF NOT EXISTS `cdn_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `thumb_filename` varchar(255) NULL,
  `file_hash` varchar(8) NOT NULL COMMENT 'CRC32 hash of file content for deduplication',
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
  `upload_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filename` (`filename`),
  UNIQUE KEY `file_hash` (`file_hash`),
  KEY `idx_upload_date` (`upload_date`),
  KEY `idx_extension` (`extension`),
  KEY `idx_file_size` (`file_size`),
  KEY `idx_thumb_filename` (`thumb_filename`),
  KEY `idx_mime_type` (`mime_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create composite indexes for common query patterns
CREATE INDEX `idx_extension_upload_date` ON `cdn_files` (`extension`, `upload_date`);
CREATE INDEX `idx_upload_date_extension` ON `cdn_files` (`upload_date`, `extension`);
CREATE INDEX `idx_file_size_upload_date` ON `cdn_files` (`file_size`, `upload_date`);
CREATE INDEX `idx_extension_file_size` ON `cdn_files` (`extension`, `file_size`);

-- Create covering indexes for list queries (includes commonly selected columns)
CREATE INDEX `idx_list_covering` ON `cdn_files` (`upload_date`, `extension`, `filename`, `thumb_filename`, `width`, `height`, `file_size`, `mime_type`);

-- Create index for filename search (for LIKE '%str%' queries)
CREATE INDEX `idx_filename_search` ON `cdn_files` (`filename`);

-- Create index for date range queries
CREATE INDEX `idx_date_range` ON `cdn_files` (`upload_date`, `id`);

-- Create index for size-based queries
CREATE INDEX `idx_size_range` ON `cdn_files` (`file_size`, `upload_date`); 