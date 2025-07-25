<?php
class Auth {
    public function isAuthenticated() {
        // Try multiple methods to get the API key header
        $apiKey = $this->getApiKeyFromHeaders();
        
        // Fallback: Check query parameter (temporary workaround)
        if (!$apiKey && isset($_GET['api_key'])) {
            $apiKey = $_GET['api_key'];
        }
        
        if (!$apiKey) {
            return false;
        }
        
        return $apiKey === API_KEY;
    }
    
    private function getApiKeyFromHeaders() {
        // Method 1: Try $_SERVER variables first (most reliable)
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }
        
        // Method 2: Try getallheaders() function with case-insensitive search
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            
            // Check for various case combinations
            $headerKeys = ['X-API-Key', 'x-api-key', 'X-Api-Key', 'x-Api-Key'];
            foreach ($headerKeys as $key) {
                if (isset($headers[$key])) {
                    return $headers[$key];
                }
            }
        }
        
        // Method 3: Try apache_request_headers() if available
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            
            // Check for various case combinations
            $headerKeys = ['X-API-Key', 'x-api-key', 'X-Api-Key', 'x-Api-Key'];
            foreach ($headerKeys as $key) {
                if (isset($headers[$key])) {
                    return $headers[$key];
                }
            }
        }
        
        // Method 4: Try with different case variations in $_SERVER
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                if (strcasecmp($key, 'HTTP_X_API_KEY') === 0) {
                    return $value;
                }
            }
        }
        
        return null;
    }
    
    public function getApiKey() {
        return $this->getApiKeyFromHeaders();
    }
}
?> 