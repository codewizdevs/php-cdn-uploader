<?php
class ListHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handle() {
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = min(MAX_PAGE_SIZE, max(1, intval($_GET['per_page'] ?? DEFAULT_PAGE_SIZE)));
        $extension = $_GET['extension'] ?? '';
        $search = $_GET['search'] ?? '';
        
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // Build WHERE clause
        $whereConditions = [];
        $params = [];
        
        if (!empty($extension)) {
            $whereConditions[] = "extension = ?";
            $params[] = strtolower($extension);
        }
        
        if (!empty($search)) {
            $whereConditions[] = "filename LIKE ?";
            $params[] = '%' . $search . '%';
        }
        
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM cdn_files " . $whereClause;
        $totalResult = $this->db->fetch($countSql, $params);
        $total = $totalResult['total'];
        
        // Get files for current page
        $filesSql = "SELECT * FROM cdn_files " . $whereClause . " ORDER BY upload_date DESC LIMIT ? OFFSET ?";
        $filesParams = array_merge($params, [$perPage, $offset]);
        $files = $this->db->fetchAll($filesSql, $filesParams);
        
        // Calculate pagination info
        $totalPages = ceil($total / $perPage);
        $hasNextPage = $page < $totalPages;
        $hasPrevPage = $page > 1;
        
        // Format file data
        $formattedFiles = array_map(function($file) {
            return [
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
                'upload_date' => $file['upload_date'],
                'urls' => [
                    'image' => CDN_IMAGES_URL . $file['filename'],
                    'thumbnail' => $file['thumb_filename'] ? CDN_THUMBS_URL . $file['thumb_filename'] : null
                ]
            ];
        }, $files);
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'files' => $formattedFiles,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_next_page' => $hasNextPage,
                    'has_prev_page' => $hasPrevPage,
                    'next_page' => $hasNextPage ? $page + 1 : null,
                    'prev_page' => $hasPrevPage ? $page - 1 : null
                ],
                'filters' => [
                    'extension' => $extension,
                    'search' => $search
                ]
            ]
        ]);
    }
}
?> 