<?php
class DeleteHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handle() {
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            throw new Exception('ID parameter is required');
        }
        
        if (!is_numeric($id) || $id <= 0) {
            throw new Exception('Invalid ID parameter');
        }
        
        // Get file record from database
        $file = $this->db->fetch(
            "SELECT * FROM cdn_files WHERE id = ?",
            [$id]
        );
        
        if (!$file) {
            throw new Exception('File not found with ID: ' . $id);
        }
        
        // Update updated_at timestamp before deletion (for audit trail)
        $this->db->query(
            "UPDATE cdn_files SET updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$id]
        );
        
        // Delete physical files
        $this->deletePhysicalFiles($file['filename'], $file['thumb_filename']);
        
        // Delete database record
        $this->db->query(
            "DELETE FROM cdn_files WHERE id = ?",
            [$id]
        );
        
        echo json_encode([
            'status' => 'success',
            'message' => 'File deleted successfully',
            'data' => [
                'id' => intval($id),
                'filename' => $file['filename'],
                'thumb_filename' => $file['thumb_filename']
            ]
        ]);
    }
    
    private function deletePhysicalFiles($filename, $thumbFilename) {
        $imagePath = IMAGES_DIR . $filename;
        $thumbPath = THUMBS_DIR . $thumbFilename;
        
        $deletedFiles = [];
        
        if (file_exists($imagePath)) {
            if (unlink($imagePath)) {
                $deletedFiles[] = 'main_image';
            }
        }
        
        if (file_exists($thumbPath)) {
            if (unlink($thumbPath)) {
                $deletedFiles[] = 'thumbnail';
            }
        }
        
        return $deletedFiles;
    }
}
?> 