<?php
class DeleteHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handle() {
        $filename = $_GET['filename'] ?? '';
        
        if (empty($filename)) {
            throw new Exception('Filename is required');
        }
        
        // Get file record from database
        $file = $this->db->fetch(
            "SELECT * FROM cdn_files WHERE filename = ?",
            [$filename]
        );
        
        if (!$file) {
            throw new Exception('File not found');
        }
        
        // Delete physical files
        $this->deletePhysicalFiles($file['filename'], $file['thumb_filename']);
        
        // Delete database record
        $this->db->query(
            "DELETE FROM cdn_files WHERE filename = ?",
            [$filename]
        );
        
        echo json_encode([
            'status' => 'success',
            'message' => 'File deleted successfully',
            'data' => [
                'filename' => $filename,
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