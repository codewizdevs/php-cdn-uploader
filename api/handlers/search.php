<?php
class SearchHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handle() {
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = min(MAX_PAGE_SIZE, max(1, intval($_GET['per_page'] ?? DEFAULT_PAGE_SIZE)));
        $query = trim($_GET['q'] ?? '');
        $extension = $_GET['extension'] ?? '';
        $minSize = intval($_GET['min_size'] ?? 0);
        $maxSize = intval($_GET['max_size'] ?? 0);
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        if (empty($query)) {
            throw new Exception('Search query is required');
        }
        
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // Build WHERE clause
        $whereConditions = [];
        $params = [];
        
        // Search query (LIKE '%str%')
        $whereConditions[] = "filename LIKE ?";
        $params[] = '%' . $query . '%';
        
        // Extension filter
        if (!empty($extension)) {
            $whereConditions[] = "extension = ?";
            $params[] = strtolower($extension);
        }
        
        // File size filters
        if ($minSize > 0) {
            $whereConditions[] = "file_size >= ?";
            $params[] = $minSize;
        }
        
        if ($maxSize > 0) {
            $whereConditions[] = "file_size <= ?";
            $params[] = $maxSize;
        }
        
        // Date range filters
        if (!empty($dateFrom)) {
            $whereConditions[] = "upload_date >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        
        if (!empty($dateTo)) {
            $whereConditions[] = "upload_date <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM cdn_files " . $whereClause;
        $totalResult = $this->db->fetch($countSql, $params);
        $total = $totalResult['total'];
        
        // Get search results for current page
        $searchSql = "SELECT * FROM cdn_files " . $whereClause . " ORDER BY upload_date DESC LIMIT ? OFFSET ?";
        $searchParams = array_merge($params, [$perPage, $offset]);
        $files = $this->db->fetchAll($searchSql, $searchParams);
        
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
    }
}
?> 