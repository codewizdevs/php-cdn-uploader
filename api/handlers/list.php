<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

class ListHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handle() {
        try {
            // Get pagination parameters
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(MAX_PAGE_SIZE, max(1, intval($_GET['per_page'] ?? DEFAULT_PAGE_SIZE)));
            $offset = ($page - 1) * $perPage;
            
            // Get filter parameters
            $extension = $_GET['extension'] ?? '';
            $search = $_GET['search'] ?? '';
            
            // Build WHERE clause
            $whereConditions = [];
            $params = [];
            
            if (!empty($extension)) {
                $whereConditions[] = "extension = ?";
                $params[] = $extension;
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
            $countQuery = "SELECT COUNT(*) as total FROM cdn_files " . $whereClause;
            $totalResult = $this->db->fetch($countQuery, $params);
            $total = $totalResult['total'];
            
            // Calculate pagination info
            $totalPages = ceil($total / $perPage);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;
            
            // Get files
            $query = "SELECT * FROM cdn_files " . $whereClause . " ORDER BY updated_at DESC LIMIT ? OFFSET ?";
            $params[] = $perPage;
            $params[] = $offset;
            
            $files = $this->db->fetchAll($query, $params);
            
            // Format response
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
                    'created_at' => $file['created_at'],
                    'updated_at' => $file['updated_at'],
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
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'List failed: ' . $e->getMessage()
            ]);
        }
    }
}
?> 