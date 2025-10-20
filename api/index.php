<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include configuration and database connection
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

// Initialize database connection
$db = new Database();

// Check authentication
$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication required. Please provide valid API key in X-API-Key header.'
    ]);
    exit;
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            if ($action === 'upload') {
                require_once 'handlers/upload.php';
                $handler = new UploadHandler($db);
                $handler->handle();
            } elseif ($action === 'delete') {
                require_once 'handlers/delete.php';
                $handler = new DeleteHandler($db);
                $handler->handle();
            } else {
                throw new Exception('Invalid action for POST request');
            }
            break;
            
        case 'GET':
            if ($action === 'list') {
                require_once 'handlers/list.php';
                $handler = new ListHandler($db);
                $handler->handle();
            } elseif ($action === 'search') {
                require_once 'handlers/search.php';
                $handler = new SearchHandler($db);
                $handler->handle();
            } elseif ($action === 'get') {
                require_once 'handlers/get.php';
                $handler = new GetHandler($db);
                $handler->handle();
            } else {
                throw new Exception('Invalid action for GET request');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?> 