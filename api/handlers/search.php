<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

class SearchHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handle() {
        try {
            // Get search parameters
            $query = $_GET['q'] ?? '';
            $extension = $_GET['extension'] ?? '';
            $minSize = $_GET['min_size'] ?? '';
            $maxSize = $_GET['max_size'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(MAX_PAGE_SIZE, max(1, intval($_GET['per_page'] ?? DEFAULT_PAGE_SIZE)));
            $offset = ($page - 1) * $perPage;
            
            // Validate required parameters
            if (empty($query)) {
                throw new Exception('Search query (q) is required');
            }
            
            // Build WHERE clause
            $whereConditions = ["filename LIKE ?"];
            $params = ['%' . $query . '%'];
            
            if (!empty($extension)) {
                $whereConditions[] = "extension = ?";
                $params[] = $extension;
            }
            
            if (!empty($minSize) && is_numeric($minSize)) {
                $whereConditions[] = "file_size >= ?";
                $params[] = intval($minSize);
            }
            
            if (!empty($maxSize) && is_numeric($maxSize)) {
                $whereConditions[] = "file_size <= ?";
                $params[] = intval($maxSize);
            }
            
            if (!empty($dateFrom)) {
                $whereConditions[] = "DATE(created_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if (!empty($dateTo)) {
                $whereConditions[] = "DATE(created_at) <= ?";
                $params[] = $dateTo;
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM cdn_files " . $whereClause;
            $totalResult = $this->db->fetch($countQuery, $params);
            $total = $totalResult['total'];
            
            // Calculate pagination info
            $totalPages = ceil($total / $perPage);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;
            
            // Get files
            $searchQuery = "SELECT * FROM cdn_files " . $whereClause . " ORDER BY updated_at DESC LIMIT ? OFFSET ?";
            $searchParams = array_merge($params, [$perPage, $offset]);
            $files = $this->db->fetchAll($searchQuery, $searchParams);
            
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
                    'query' => $query,
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
                        'min_size' => $minSize,
                        'max_size' => $maxSize,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Search failed: ' . $e->getMessage()
            ]);
        }
    }
}
?> 