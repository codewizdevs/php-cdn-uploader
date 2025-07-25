<?php
class GetHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handle() {
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
        
                       // Format response
               $response = [
                   'id' => $file['id'],
                   'filename' => $file['filename'],
                   'thumb_filename' => $file['thumb_filename'],
                   'file_hash' => $file['file_hash'],
                   'original_width' => $file['original_width'],
                   'original_height' => $file['original_height'],
                   'width' => $file['width'],
                   'height' => $file['height'],
                   'thumb_width' => $file['thumb_width'],
                   'thumb_height' => $file['thumb_height'],
                   'file_size' => $file['file_size'],
                   'thumb_size' => $file['thumb_size'],
                   'extension' => $file['extension'],
                   'mime_type' => $file['mime_type'],
                   'upload_date' => $file['upload_date'],
                   'urls' => [
                       'image' => CDN_IMAGES_URL . $file['filename'],
                       'thumbnail' => $file['thumb_filename'] ? CDN_THUMBS_URL . $file['thumb_filename'] : null
                   ]
               ];
        
        echo json_encode([
            'status' => 'success',
            'data' => $response
        ]);
    }
}
?> 