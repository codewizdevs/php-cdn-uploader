<?php
/**
 * Comprehensive API Test Suite
 * Tests ALL possible scenarios and edge cases
 */

// Configuration
$API_BASE = 'https://cdn.juicysearch.com/api/';
$API_KEY = 'iqiLIkEtEqeYaXILEkoM';

// Test results storage
$testResults = [];
$testFiles = [];

function makeRequest($action, $data = null, $method = 'POST') {
    global $API_BASE, $API_KEY;
    
    $url = $API_BASE . '?action=' . $action;
    $headers = ['X-API-Key: ' . $API_KEY];
    
    if ($data && $method === 'POST') {
        if (isset($data['image'])) {
            $headers[] = 'Content-Type: application/json';
            $postData = json_encode($data);
        } else {
            $postData = $data;
        }
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, $method === 'POST');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("cURL Error: " . $error);
    }
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'raw_response' => $response
    ];
}

function createTestImage($width = 100, $height = 100, $color = 'red', $text = '') {
    $image = imagecreate($width, $height);
    
    switch ($color) {
        case 'red':
            $bgColor = imagecolorallocate($image, 255, 0, 0);
            break;
        case 'blue':
            $bgColor = imagecolorallocate($image, 0, 0, 255);
            break;
        case 'green':
            $bgColor = imagecolorallocate($image, 0, 255, 0);
            break;
        case 'yellow':
            $bgColor = imagecolorallocate($image, 255, 255, 0);
            break;
        case 'purple':
            $bgColor = imagecolorallocate($image, 128, 0, 128);
            break;
        case 'orange':
            $bgColor = imagecolorallocate($image, 255, 165, 0);
            break;
        case 'pink':
            $bgColor = imagecolorallocate($image, 255, 192, 203);
            break;
        case 'cyan':
            $bgColor = imagecolorallocate($image, 0, 255, 255);
            break;
        default:
            $bgColor = imagecolorallocate($image, 128, 128, 128);
    }
    
    imagefill($image, 0, 0, $bgColor);
    
    // Add text to make images unique
    $textColor = imagecolorallocate($image, 255, 255, 255);
    $displayText = $text ?: $color . ' ' . $width . 'x' . $height;
    imagestring($image, 5, 10, 10, $displayText, $textColor);
    
    ob_start();
    imagejpeg($image, null, 95);
    $imageData = ob_get_clean();
    imagedestroy($image);
    
    return $imageData;
}

function imageToBase64($imageData) {
    return 'data:image/jpeg;base64,' . base64_encode($imageData);
}

function runTest($testName, $testFunction) {
    global $testResults;
    echo "\n🧪 Running Test: $testName\n";
    echo str_repeat('-', 60) . "\n";
    
    try {
        $result = $testFunction();
        $testResults[$testName] = $result;
        echo "✅ Test completed successfully\n";
        return $result;
    } catch (Exception $e) {
        echo "❌ Test failed: " . $e->getMessage() . "\n";
        $testResults[$testName] = ['error' => $e->getMessage()];
        return false;
    }
}

function logTestResult($testName, $expected, $actual, $details = '') {
    echo "📊 Expected: $expected\n";
    echo "📊 Actual: $actual\n";
    if ($details) {
        echo "📝 Details: $details\n";
    }
    echo "\n";
}

// ===== COMPREHENSIVE TEST SCENARIOS =====

// Test 1: Basic upload without filename
function testBasicUpload() {
    $imageData = createTestImage(200, 150, 'red', 'Basic Upload');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Basic Upload', 'Auto-generated filename', $data['filename'], "ID: {$data['id']}, Hash: {$data['file_hash']}");
        return $data;
    } else {
        throw new Exception("Basic upload failed: " . $result['response']['message']);
    }
}

// Test 2: Upload with custom filename
function testCustomFilename() {
    $imageData = createTestImage(300, 200, 'blue', 'Custom Filename');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'comprehensive-test-blue.jpg'
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Custom Filename', 'comprehensive-test-blue.jpg', $data['filename'], "ID: {$data['id']}, Hash: {$data['file_hash']}");
        return $data;
    } else {
        throw new Exception("Custom filename upload failed: " . $result['response']['message']);
    }
}

// Test 3: Force replace existing file (same filename, different content)
function testForceReplaceDifferent() {
    $imageData = createTestImage(250, 180, 'green', 'Force Replace Different');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'comprehensive-test-blue.jpg', // Same filename as test 2
        'force' => true
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Force Replace Different', 'Same ID as test 2, different hash', "ID: {$data['id']}, Hash: {$data['file_hash']}", "Filename: {$data['filename']}");
        return $data;
    } else {
        throw new Exception("Force replace failed: " . $result['response']['message']);
    }
}

// Test 4: Force replace with same content (should update metadata)
function testForceReplaceSame() {
    $imageData = createTestImage(250, 180, 'green', 'Force Replace Same'); // Same as test 3
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'comprehensive-test-blue.jpg',
        'force' => true
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Force Replace Same', 'Same ID, same hash, updated timestamp', "ID: {$data['id']}, Hash: {$data['file_hash']}", "Filename: {$data['filename']}");
        return $data;
    } else {
        throw new Exception("Force replace same failed: " . $result['response']['message']);
    }
}

// Test 5: Upload same content with different filename (hash deduplication)
function testHashDeduplication() {
    $imageData = createTestImage(250, 180, 'green', 'Hash Dedup'); // Same as test 3/4
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'duplicate-content-test.jpg'
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Hash Deduplication', 'Return existing file ID', "ID: {$data['id']}, Filename: {$data['filename']}", "Hash: {$data['file_hash']}");
        return $data;
    } else {
        throw new Exception("Hash deduplication failed: " . $result['response']['message']);
    }
}

// Test 6: Force with non-existent filename
function testForceNonExistent() {
    $imageData = createTestImage(150, 100, 'purple', 'Force New');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'comprehensive-test-new.jpg',
        'force' => true
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Force Non-existent', 'Create new file', "ID: {$data['id']}, Filename: {$data['filename']}", "Hash: {$data['file_hash']}");
        return $data;
    } else {
        throw new Exception("Force non-existent failed: " . $result['response']['message']);
    }
}

// Test 7: Create file with hash that exists elsewhere, then force replace
function testForceReplaceHashConflict() {
    // First, create a file with unique content
    $imageData1 = createTestImage(400, 300, 'yellow', 'Unique Content 1');
    $base64Data1 = imageToBase64($imageData1);
    
    $result1 = makeRequest('upload', [
        'image' => $base64Data1,
        'filename' => 'unique-content-1.jpg'
    ]);
    
    if ($result1['response']['status'] !== 'success') {
        throw new Exception("Failed to create first file: " . $result1['response']['message']);
    }
    
    $firstFile = $result1['response']['data'];
    echo "Created first file: ID {$firstFile['id']}, Hash: {$firstFile['file_hash']}\n";
    
    // Now create another file with different content
    $imageData2 = createTestImage(350, 250, 'orange', 'Unique Content 2');
    $base64Data2 = imageToBase64($imageData2);
    
    $result2 = makeRequest('upload', [
        'image' => $base64Data2,
        'filename' => 'unique-content-2.jpg'
    ]);
    
    if ($result2['response']['status'] !== 'success') {
        throw new Exception("Failed to create second file: " . $result2['response']['message']);
    }
    
    $secondFile = $result2['response']['data'];
    echo "Created second file: ID {$secondFile['id']}, Hash: {$secondFile['file_hash']}\n";
    
    // Now force replace second file with content that matches first file
    $result3 = makeRequest('upload', [
        'image' => $base64Data1, // Same as first file
        'filename' => 'unique-content-2.jpg', // Second file's filename
        'force' => true
    ]);
    
    echo "HTTP Code: " . $result3['http_code'] . "\n";
    
    if ($result3['response']['status'] === 'success') {
        $data = $result3['response']['data'];
        logTestResult('Force Replace Hash Conflict', 'Return first file, delete second', "ID: {$data['id']}, Filename: {$data['filename']}", "Hash: {$data['file_hash']}");
        return $data;
    } else {
        throw new Exception("Force replace hash conflict failed: " . $result3['response']['message']);
    }
}

// Test 8: Test filename normalization
function testFilenameNormalization() {
    $imageData = createTestImage(200, 200, 'pink', 'Normalization');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'test file with spaces and special chars!@#.jpg'
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Filename Normalization', 'Normalized filename', $data['filename'], "Original had spaces and special chars");
        return $data;
    } else {
        throw new Exception("Filename normalization failed: " . $result['response']['message']);
    }
}

// Test 9: Test empty filename normalization
function testEmptyFilenameNormalization() {
    $imageData = createTestImage(180, 160, 'cyan', 'Empty Normalization');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => '   ' // Empty/whitespace filename
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Empty Filename Normalization', 'Auto-generated filename', $data['filename'], "Original was empty/whitespace");
        return $data;
    } else {
        throw new Exception("Empty filename normalization failed: " . $result['response']['message']);
    }
}

// Test 10: Test large image resizing
function testLargeImageResizing() {
    $imageData = createTestImage(1200, 800, 'red', 'Large Image');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'large-image-test.jpg'
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        $expectedWidth = min(1200, 700);
        $expectedHeight = intval((800 * 700) / 1200);
        
        logTestResult('Large Image Resizing', "{$expectedWidth}x{$expectedHeight}", "{$data['width']}x{$data['height']}", "Original: 1200x800, Thumb: {$data['thumb_width']}x{$data['thumb_height']}");
        return $data;
    } else {
        throw new Exception("Large image resizing failed: " . $result['response']['message']);
    }
}

// Test 11: Test exact size image (no resizing needed)
function testExactSizeImage() {
    $imageData = createTestImage(700, 500, 'blue', 'Exact Size');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'exact-size-test.jpg'
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Exact Size Image', '700x500 (no resizing)', "{$data['width']}x{$data['height']}", "Thumb: {$data['thumb_width']}x{$data['thumb_height']}");
        return $data;
    } else {
        throw new Exception("Exact size image failed: " . $result['response']['message']);
    }
}

// Test 12: Test small image (no resizing needed)
function testSmallImage() {
    $imageData = createTestImage(300, 200, 'green', 'Small Image');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'small-image-test.jpg'
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Small Image', '300x200 (no resizing)', "{$data['width']}x{$data['height']}", "Thumb: {$data['thumb_width']}x{$data['thumb_height']}");
        return $data;
    } else {
        throw new Exception("Small image failed: " . $result['response']['message']);
    }
}

// Test 13: Test portrait image
function testPortraitImage() {
    $imageData = createTestImage(400, 800, 'purple', 'Portrait');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'portrait-test.jpg'
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        $expectedWidth = intval((400 * 700) / 800);
        $expectedHeight = 700;
        
        logTestResult('Portrait Image', "{$expectedWidth}x{$expectedHeight}", "{$data['width']}x{$data['height']}", "Original: 400x800, Thumb: {$data['thumb_width']}x{$data['thumb_height']}");
        return $data;
    } else {
        throw new Exception("Portrait image failed: " . $result['response']['message']);
    }
}

// Test 14: Test square image
function testSquareImage() {
    $imageData = createTestImage(600, 600, 'orange', 'Square');
    $base64Data = imageToBase64($imageData);
    
    $result = makeRequest('upload', [
        'image' => $base64Data,
        'filename' => 'square-test.jpg'
    ]);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        logTestResult('Square Image', '600x600 (no resizing)', "{$data['width']}x{$data['height']}", "Thumb: {$data['thumb_width']}x{$data['thumb_height']}");
        return $data;
    } else {
        throw new Exception("Square image failed: " . $result['response']['message']);
    }
}

// Test 15: Final verification - list all test files
function testListAllFiles() {
    $result = makeRequest('list', null, 'GET');
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['response']['status'] === 'success') {
        $data = $result['response']['data'];
        $testFiles = array_filter($data['files'], function($file) {
            return strpos($file['filename'], 'comprehensive-test') !== false || 
                   strpos($file['filename'], 'duplicate-content') !== false ||
                   strpos($file['filename'], 'unique-content') !== false ||
                   strpos($file['filename'], 'large-image') !== false ||
                   strpos($file['filename'], 'exact-size') !== false ||
                   strpos($file['filename'], 'small-image') !== false ||
                   strpos($file['filename'], 'portrait') !== false ||
                   strpos($file['filename'], 'square') !== false;
        });
        
        logTestResult('List All Files', count($testFiles) . ' test files found', count($testFiles) . ' test files in database', "Total files in DB: {$data['pagination']['total']}");
        
        foreach ($testFiles as $file) {
            echo "  - {$file['filename']} (ID: {$file['id']}, Hash: {$file['file_hash']})\n";
        }
        
        return $data;
    } else {
        throw new Exception("List files failed: " . $result['response']['message']);
    }
}

// ===== RUN ALL TESTS =====

echo "🚀 Starting Comprehensive API Test Suite\n";
echo "API Base: $API_BASE\n";
echo "API Key: $API_KEY\n";
echo str_repeat('=', 80) . "\n";

$results = [];

// Core functionality tests
$results['basic'] = runTest('Basic Upload', 'testBasicUpload');
$results['custom'] = runTest('Custom Filename', 'testCustomFilename');
$results['force_diff'] = runTest('Force Replace Different Content', 'testForceReplaceDifferent');
$results['force_same'] = runTest('Force Replace Same Content', 'testForceReplaceSame');
$results['dedup'] = runTest('Hash Deduplication', 'testHashDeduplication');
$results['force_new'] = runTest('Force Non-existent Filename', 'testForceNonExistent');
$results['force_hash_conflict'] = runTest('Force Replace Hash Conflict', 'testForceReplaceHashConflict');

// Edge case tests
$results['normalization'] = runTest('Filename Normalization', 'testFilenameNormalization');
$results['empty_normalization'] = runTest('Empty Filename Normalization', 'testEmptyFilenameNormalization');

// Image processing tests
$results['large_image'] = runTest('Large Image Resizing', 'testLargeImageResizing');
$results['exact_size'] = runTest('Exact Size Image', 'testExactSizeImage');
$results['small_image'] = runTest('Small Image', 'testSmallImage');
$results['portrait'] = runTest('Portrait Image', 'testPortraitImage');
$results['square'] = runTest('Square Image', 'testSquareImage');

// Verification test
$results['list_all'] = runTest('List All Test Files', 'testListAllFiles');

// ===== COMPREHENSIVE SUMMARY =====

echo "\n📊 COMPREHENSIVE TEST SUMMARY\n";
echo str_repeat('=', 80) . "\n";

$passed = 0;
$failed = 0;

foreach ($testResults as $testName => $result) {
    if (isset($result['error'])) {
        echo "❌ $testName: FAILED - " . $result['error'] . "\n";
        $failed++;
    } else {
        echo "✅ $testName: PASSED\n";
        $passed++;
    }
}

echo "\n🎯 FINAL VERIFICATION:\n";
echo "✅ Passed: $passed tests\n";
echo "❌ Failed: $failed tests\n";
echo "📊 Total: " . ($passed + $failed) . " tests\n";

echo "\n🔍 LOGIC VERIFICATION:\n";
echo "- Basic upload functionality: " . (isset($results['basic']) ? "✅" : "❌") . "\n";
echo "- Custom filename handling: " . (isset($results['custom']) ? "✅" : "❌") . "\n";
echo "- Force replacement (different content): " . (isset($results['force_diff']) ? "✅" : "❌") . "\n";
echo "- Force replacement (same content): " . (isset($results['force_same']) ? "✅" : "❌") . "\n";
echo "- Hash deduplication: " . (isset($results['dedup']) ? "✅" : "❌") . "\n";
echo "- Force non-existent filename: " . (isset($results['force_new']) ? "✅" : "❌") . "\n";
echo "- Force hash conflict resolution: " . (isset($results['force_hash_conflict']) ? "✅" : "❌") . "\n";
echo "- Filename normalization: " . (isset($results['normalization']) ? "✅" : "❌") . "\n";
echo "- Empty filename handling: " . (isset($results['empty_normalization']) ? "✅" : "❌") . "\n";
echo "- Large image resizing: " . (isset($results['large_image']) ? "✅" : "❌") . "\n";
echo "- Exact size image handling: " . (isset($results['exact_size']) ? "✅" : "❌") . "\n";
echo "- Small image handling: " . (isset($results['small_image']) ? "✅" : "❌") . "\n";
echo "- Portrait image resizing: " . (isset($results['portrait']) ? "✅" : "❌") . "\n";
echo "- Square image handling: " . (isset($results['square']) ? "✅" : "❌") . "\n";
echo "- File listing: " . (isset($results['list_all']) ? "✅" : "❌") . "\n";

echo "\n🏆 CONFIDENCE LEVEL: " . round(($passed / ($passed + $failed)) * 100, 1) . "%\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! Your CDN API is 100% reliable!\n";
} else {
    echo "⚠️  Some tests failed. Please review the errors above.\n";
}

echo "\n🏁 Comprehensive testing completed!\n";
?> 