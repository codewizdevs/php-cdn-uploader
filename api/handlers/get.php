<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

class GetHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handle() {
        try {
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new Exception('ID parameter is required');
            }
            
            if (!is_numeric($id) || $id <= 0) {
                throw new Exception('Invalid ID parameter');
            }
            
            $file = $this->db->fetch(
                "SELECT * FROM cdn_files WHERE id = ?",
                [$id]
            );
            
            if (!$file) {
                throw new Exception('File not found with ID: ' . $id);
            }
            
            // Update updated_at timestamp when file is accessed
            $this->db->query(
                "UPDATE cdn_files SET updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$id]
            );
            
            // Get updated file data
            $file = $this->db->fetch(
                "SELECT * FROM cdn_files WHERE id = ?",
                [$id]
            );
            
            // Format response
            $response = [
                'id' => intval($file['id']),
                'filename' => $file['filename'],
                'thumb_filename' => $file['thumb_filename'],
                'file_hash' => $file['file_hash'],
                'original_width' => intval($file['original_width']),
                'original_height' => intval($file['original_height']),
                'width' => intval($file['width']),
                'height' => intval($file['height']),
                'thumb_width' => intval($file['thumb_width']),
                'thumb_height' => intval($file['thumb_height']),
                'file_size' => intval($file['file_size']),
                'thumb_size' => intval($file['thumb_size']),
                'extension' => $file['extension'],
                'mime_type' => $file['mime_type'],
                'created_at' => $file['created_at'],
                'updated_at' => $file['updated_at'],
                'urls' => [
                    'image' => CDN_IMAGES_URL . $file['filename'],
                    'thumbnail' => $file['thumb_filename'] ? CDN_THUMBS_URL . $file['thumb_filename'] : null
                ]
            ];
            
            echo json_encode([
                'status' => 'success',
                'data' => $response
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Get failed: ' . $e->getMessage()
            ]);
        }
    }
}
?> 