<?php
// Include configuration
require_once 'api/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CDN Upload Test Interface</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #007bff;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-bottom: 10px;
        }
        button:hover {
            background-color: #0056b3;
        }
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #545b62;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-success:hover {
            background-color: #1e7e34;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 14px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .loading {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .preview {
            margin-top: 20px;
            text-align: center;
        }
        .preview img {
            max-width: 300px;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .api-key-input {
            margin-bottom: 20px;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            color: #666;
            border-bottom: 2px solid transparent;
        }
        .tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .upload-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .upload-tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            color: #666;
            border-bottom: 2px solid transparent;
            width: auto;
            margin-bottom: 0;
        }
        .upload-tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        .upload-section {
            display: none;
        }
        .upload-section.active {
            display: block;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: monospace;
            font-size: 14px;
            resize: vertical;
        }
        .file-display {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .file-display h3 {
            margin-top: 0;
            color: #333;
        }
        #getFileInfo {
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 14px;
            line-height: 1.5;
        }
        #getFilePreview {
            text-align: center;
        }
        #getFilePreview img,
        #getFilePreview video {
            max-width: 300px;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .file-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .file-card img,
        .file-card video {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .file-info {
            font-size: 14px;
            line-height: 1.4;
            margin-bottom: 15px;
        }
        .file-actions {
            display: flex;
            gap: 10px;
        }
        .file-actions button {
            flex: 1;
            margin-bottom: 0;
            padding: 8px 12px;
            font-size: 14px;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }
        .pagination button {
            width: auto;
            margin-bottom: 0;
        }
        .search-filters {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .search-filters h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>CDN Upload Test Interface</h1>
        
        <div class="api-key-input">
            <label for="apiKey">API Key:</label>
            <input type="text" id="apiKey" placeholder="Enter your API key">
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('upload')">Upload</button>
            <button class="tab" onclick="showTab('search')">Search</button>
            <button class="tab" onclick="showTab('list')">List Files</button>
            <button class="tab" onclick="showTab('get')">Get by ID</button>
        </div>
        
        <!-- Upload Tab -->
        <div id="upload-tab" class="tab-content active">
            <h2>Upload File</h2>
            
            <!-- Upload Method Tabs -->
            <div class="upload-tabs">
                <button class="upload-tab active" onclick="switchUploadMethod('multipart')">File Upload</button>
                <button class="upload-tab" onclick="switchUploadMethod('base64')">Base64 Upload</button>
            </div>
            
            <!-- Multipart Upload Section -->
            <div id="multipart-upload" class="upload-section active">
                <div class="form-group">
                    <label for="fileInput">Select File:</label>
                    <input type="file" id="fileInput" accept="image/*,video/*">
                </div>
                
                <div class="form-group">
                    <label for="filename">Custom Filename (optional):</label>
                    <input type="text" id="filename" placeholder="Leave empty for auto-generated filename">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="forceReplace"> Force Replace
                    </label>
                    <small>If checked, will replace existing file with same filename (ignores hash deduplication)</small>
                </div>
                
                <button id="uploadBtn" onclick="uploadMultipart()">Upload File</button>
            </div>
            
            <!-- Base64 Upload Section -->
            <div id="base64-upload" class="upload-section">
                <div class="form-group">
                    <label for="base64Input">Base64 Data:</label>
                    <textarea id="base64Input" placeholder="Paste base64 data or data URL here..." rows="6"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="base64Filename">Custom Filename (optional):</label>
                    <input type="text" id="base64Filename" placeholder="Leave empty for auto-generated filename">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="forceReplaceBase64"> Force Replace
                    </label>
                    <small>If checked, will replace existing file with same filename (ignores hash deduplication)</small>
                </div>
                
                <button id="base64UploadBtn" onclick="uploadBase64()">Upload Base64</button>
            </div>
            
            <div id="uploadResult" class="result" style="display: none;"></div>
            
            <div id="preview" class="preview" style="display: none;">
                <h3>Preview:</h3>
                <img id="previewImg" src="" alt="Preview">
            </div>
        </div>
        
        <!-- Search Tab -->
        <div id="search-tab" class="tab-content">
            <h2>Search Files</h2>
            
            <div class="search-filters">
                <h3>Search Filters</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="searchQuery">Search Query:</label>
                        <input type="text" id="searchQuery" placeholder="Enter filename to search (e.g., 'image', 'photo')">
                    </div>
                    <div class="form-group">
                        <label for="searchExtension">Extension:</label>
                        <select id="searchExtension">
                            <option value="">All extensions</option>
                            <option value="jpg">JPG</option>
                            <option value="jpeg">JPEG</option>
                            <option value="png">PNG</option>
                            <option value="gif">GIF</option>
                            <option value="webp">WebP</option>
                            <option value="bmp">BMP</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="minSize">Min Size (bytes):</label>
                        <input type="number" id="minSize" placeholder="0" min="0">
                    </div>
                    <div class="form-group">
                        <label for="maxSize">Max Size (bytes):</label>
                        <input type="number" id="maxSize" placeholder="0" min="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dateFrom">Date From:</label>
                        <input type="date" id="dateFrom">
                    </div>
                    <div class="form-group">
                        <label for="dateTo">Date To:</label>
                        <input type="date" id="dateTo">
                    </div>
                </div>
            </div>
            
            <button id="searchBtn" onclick="searchFiles()">Search Files</button>
            <button class="btn-secondary" onclick="clearSearch()">Clear Filters</button>
            
            <div id="searchResult" class="result" style="display: none;"></div>
            <div id="searchResults" class="file-grid" style="display: none;"></div>
            <div id="searchPagination" class="pagination" style="display: none;"></div>
        </div>
        
        <!-- List Tab -->
        <div id="list-tab" class="tab-content">
            <h2>List Files</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="listPage">Page:</label>
                    <input type="number" id="listPage" value="1" min="1">
                </div>
                <div class="form-group">
                    <label for="listPerPage">Per Page:</label>
                    <select id="listPerPage">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="listExtension">Extension:</label>
                    <select id="listExtension">
                        <option value="">All extensions</option>
                        <option value="jpg">JPG</option>
                        <option value="jpeg">JPEG</option>
                        <option value="png">PNG</option>
                        <option value="gif">GIF</option>
                        <option value="webp">WebP</option>
                        <option value="bmp">BMP</option>
                        <option value="mp4">MP4</option>
                        <option value="webm">WebM</option>
                        <option value="avi">AVI</option>
                    </select>
                </div>
            </div>
            
            <button id="listBtn" onclick="listFiles()">List Files</button>
            
            <div id="listResult" class="result" style="display: none;"></div>
            <div id="listResults" class="file-grid" style="display: none;"></div>
            <div id="listPagination" class="pagination" style="display: none;"></div>
        </div>
        
        <!-- Get by ID Tab -->
        <div id="get-tab" class="tab-content">
            <h2>Get File by ID</h2>
            
            <div class="form-group">
                <label for="fileId">File ID:</label>
                <input type="number" id="fileId" placeholder="Enter file ID" min="1">
            </div>
            
            <button id="getBtn" onclick="getFileById()">Get File</button>
            
            <div id="getResult" class="result" style="display: none;"></div>
            
            <div id="getFileDisplay" class="file-display" style="display: none;">
                <h3>File Details:</h3>
                <div id="getFileInfo"></div>
                <div id="getFilePreview"></div>
            </div>
        </div>
    </div>

    <script>
        // CDN Configuration from PHP config
        const CDN_CONFIG = {
            domain: '<?php echo CDN_DOMAIN; ?>',
            apiBase: '<?php echo CDN_BASE_URL; ?>',
            imagesUrl: '<?php echo CDN_IMAGES_URL; ?>',
            thumbsUrl: '<?php echo CDN_THUMBS_URL; ?>'
        };
        
        const apiKeyInput = document.getElementById('apiKey');
        let currentSearchPage = 1;
        let currentListPage = 1;

        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Upload functionality
        const fileInput = document.getElementById('fileInput');
        const filenameInput = document.getElementById('filename');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadResult = document.getElementById('uploadResult');
        const previewDiv = document.getElementById('preview');
        const previewImg = document.getElementById('previewImg');
        const base64Input = document.getElementById('base64Input');
        const base64Filename = document.getElementById('base64Filename');
        const base64UploadBtn = document.getElementById('base64UploadBtn');

        // Upload method switching
        function switchUploadMethod(method) {
            // Update upload tab buttons
            document.querySelectorAll('.upload-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show/hide upload sections
            document.querySelectorAll('.upload-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(method + '-upload').classList.add('active');
        }

        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewDiv.style.display = 'block';
                };
                reader.readAsDataURL(file);
                
                if (!filenameInput.value) {
                    filenameInput.value = file.name;
                }
            }
        });

        async function uploadMultipart() {
            const file = fileInput.files[0];
            const filename = filenameInput.value.trim();
            const apiKey = apiKeyInput.value.trim();
            const forceReplace = document.getElementById('forceReplace').checked;
            
            if (!file) {
                showResult('Please select a file first.', 'error', uploadResult);
                return;
            }
            
            if (!apiKey) {
                showResult('Please enter a valid API key.', 'error', uploadResult);
                return;
            }
            
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            showResult('Uploading file...', 'loading', uploadResult);
            
            try {
                const formData = new FormData();
                formData.append('file', file);
                if (filename) {
                    formData.append('filename', filename);
                }
                if (forceReplace) {
                    formData.append('force', 'true');
                }
                
                const response = await fetch(CDN_CONFIG.apiBase + '?action=upload', {
                    method: 'POST',
                    headers: {
                        'X-API-Key': apiKey
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    const thumbUrl = result.data.thumb_filename ? 
                        `${CDN_CONFIG.thumbsUrl}${result.data.thumb_filename}` : 
                        'No thumbnail generated';
                    
                    showResult(`Upload successful!\n\nID: ${result.data.id}\nFilename: ${result.data.filename}\nThumbnail: ${result.data.thumb_filename || 'None'}\nSize: ${result.data.file_size} bytes\nDimensions: ${result.data.width}x${result.data.height}\n\nFile URL: ${CDN_CONFIG.imagesUrl}${result.data.filename}\nThumbnail URL: ${thumbUrl}`, 'success', uploadResult);
                } else {
                    showResult(`Upload failed: ${result.message}`, 'error', uploadResult);
                }
            } catch (error) {
                showResult(`Error: ${error.message}`, 'error', uploadResult);
            } finally {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload File';
            }
        }

        async function uploadBase64() {
            const base64Data = base64Input.value.trim();
            const filename = base64Filename.value.trim();
            const apiKey = apiKeyInput.value.trim();
            const forceReplace = document.getElementById('forceReplaceBase64').checked;
            
            if (!base64Data) {
                showResult('Please enter base64 data.', 'error', uploadResult);
                return;
            }
            
            if (!apiKey) {
                showResult('Please enter a valid API key.', 'error', uploadResult);
                return;
            }
            
            base64UploadBtn.disabled = true;
            base64UploadBtn.textContent = 'Uploading...';
            showResult('Uploading base64 data...', 'loading', uploadResult);
            
            try {
                const response = await fetch(CDN_CONFIG.apiBase + '?action=upload', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': apiKey
                    },
                    body: JSON.stringify({
                        image: base64Data,
                        filename: filename || undefined,
                        force: forceReplace
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    const thumbUrl = result.data.thumb_filename ? 
                        `${CDN_CONFIG.thumbsUrl}${result.data.thumb_filename}` : 
                        'No thumbnail generated';
                    
                    showResult(`Upload successful!\n\nID: ${result.data.id}\nFilename: ${result.data.filename}\nThumbnail: ${result.data.thumb_filename || 'None'}\nSize: ${result.data.file_size} bytes\nDimensions: ${result.data.width}x${result.data.height}\n\nFile URL: ${CDN_CONFIG.imagesUrl}${result.data.filename}\nThumbnail URL: ${thumbUrl}`, 'success', uploadResult);
                } else {
                    showResult(`Upload failed: ${result.message}`, 'error', uploadResult);
                }
            } catch (error) {
                showResult(`Error: ${error.message}`, 'error', uploadResult);
            } finally {
                base64UploadBtn.disabled = false;
                base64UploadBtn.textContent = 'Upload Base64';
            }
        }

        // Search functionality
        async function searchFiles(page = 1) {
            const apiKey = apiKeyInput.value.trim();
            const query = document.getElementById('searchQuery').value.trim();
            const extension = document.getElementById('searchExtension').value;
            const minSize = document.getElementById('minSize').value;
            const maxSize = document.getElementById('maxSize').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            if (!query) {
                showResult('Please enter a search query.', 'error', document.getElementById('searchResult'));
                return;
            }
            
            if (!apiKey) {
                showResult('Please enter a valid API key.', 'error', document.getElementById('searchResult'));
                return;
            }
            
            const searchBtn = document.getElementById('searchBtn');
            searchBtn.disabled = true;
            searchBtn.textContent = 'Searching...';
            
            try {
                const params = new URLSearchParams({
                    action: 'search',
                    q: query,
                    page: page,
                    per_page: 20
                });
                
                if (extension) params.append('extension', extension);
                if (minSize) params.append('min_size', minSize);
                if (maxSize) params.append('max_size', maxSize);
                if (dateFrom) params.append('date_from', dateFrom);
                if (dateTo) params.append('date_to', dateTo);
                
                const response = await fetch(`${CDN_CONFIG.apiBase}?${params}`, {
                    headers: {
                        'X-API-Key': apiKey
                    }
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    displayFiles(result.data.files, 'searchResults', result.data.pagination, 'search');
                    currentSearchPage = page;
                } else {
                    showResult(`Search failed: ${result.message}`, 'error', document.getElementById('searchResult'));
                }
            } catch (error) {
                showResult(`Error: ${error.message}`, 'error', document.getElementById('searchResult'));
            } finally {
                searchBtn.disabled = false;
                searchBtn.textContent = 'Search Files';
            }
        }

        // List functionality
        async function listFiles(page = 1) {
            const apiKey = apiKeyInput.value.trim();
            const extension = document.getElementById('listExtension').value;
            const perPage = document.getElementById('listPerPage').value;
            
            if (!apiKey) {
                showResult('Please enter a valid API key.', 'error', document.getElementById('listResult'));
                return;
            }
            
            const listBtn = document.getElementById('listBtn');
            listBtn.disabled = true;
            listBtn.textContent = 'Loading...';
            
            try {
                const params = new URLSearchParams({
                    action: 'list',
                    page: page,
                    per_page: perPage
                });
                
                if (extension) params.append('extension', extension);
                
                const response = await fetch(`${CDN_CONFIG.apiBase}?${params}`, {
                    headers: {
                        'X-API-Key': apiKey
                    }
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    displayFiles(result.data.files, 'listResults', result.data.pagination, 'list');
                    currentListPage = page;
                } else {
                    showResult(`List failed: ${result.message}`, 'error', document.getElementById('listResult'));
                }
            } catch (error) {
                showResult(`Error: ${error.message}`, 'error', document.getElementById('listResult'));
            } finally {
                listBtn.disabled = false;
                listBtn.textContent = 'List Files';
            }
        }

        // Get file by ID
        async function getFileById() {
            const fileId = document.getElementById('fileId').value.trim();
            const apiKey = apiKeyInput.value.trim();
            const getResult = document.getElementById('getResult');
            const getFileDisplay = document.getElementById('getFileDisplay');
            const getFileInfo = document.getElementById('getFileInfo');
            const getFilePreview = document.getElementById('getFilePreview');
            const getBtn = document.getElementById('getBtn');
            
            if (!fileId) {
                showResult('Please enter a file ID.', 'error', getResult);
                return;
            }
            
            if (!apiKey) {
                showResult('Please enter a valid API key.', 'error', getResult);
                return;
            }
            
            getBtn.disabled = true;
            getBtn.textContent = 'Loading...';
            showResult('Loading file data...', 'loading', getResult);
            getFileDisplay.style.display = 'none';
            
            try {
                const response = await fetch(`${CDN_CONFIG.apiBase}?action=get&id=${encodeURIComponent(fileId)}`, {
                    headers: {
                        'X-API-Key': apiKey
                    }
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    const file = result.data;
                    const isVideo = ['mp4', 'webm', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'm4v', '3gp', 'ogv'].includes(file.extension.toLowerCase());
                    const hasThumbnail = file.thumb_filename !== null;
                    
                    // Display file info
                    getFileInfo.innerHTML = `
                        <strong>File Details:</strong><br>
                        ID: ${file.id}<br>
                        Filename: ${file.filename}<br>
                        Size: ${formatBytes(file.file_size)}<br>
                        ${file.width > 0 && file.height > 0 ? `Dimensions: ${file.width}x${file.height}<br>` : ''}
                        Type: ${isVideo ? 'Video' : 'Image'}<br>
                        Extension: ${file.extension}<br>
                        MIME Type: ${file.mime_type}<br>
                        ${hasThumbnail ? `Thumbnail: ${file.thumb_filename}<br>Thumbnail Size: ${formatBytes(file.thumb_size)}<br>Thumbnail Dimensions: ${file.thumb_width}x${file.thumb_height}<br>` : 'Thumbnail: None<br>'}
                        Upload Date: ${new Date(file.created_at).toLocaleString()}<br><br>
                        <strong>URLs:</strong><br>
                        File URL: <a href="${file.urls.image}" target="_blank">${file.urls.image}</a><br>
                        ${hasThumbnail ? `Thumbnail URL: <a href="${file.urls.thumbnail}" target="_blank">${file.urls.thumbnail}</a><br>` : ''}
                    `;
                    
                    // Display preview
                    if (isVideo) {
                        getFilePreview.innerHTML = `<video src="${file.urls.image}" controls style="max-width: 300px; max-height: 300px;"></video>`;
                    } else {
                        const previewUrl = hasThumbnail ? file.urls.thumbnail : file.urls.image;
                        getFilePreview.innerHTML = `<img src="${previewUrl}" alt="${file.filename}" onerror="this.src='${file.urls.image}'">`;
                    }
                    
                    getFileDisplay.style.display = 'block';
                    getResult.style.display = 'none';
                } else {
                    showResult(`Get failed: ${result.message}`, 'error', getResult);
                    getFileDisplay.style.display = 'none';
                }
            } catch (error) {
                showResult(`Error: ${error.message}`, 'error', getResult);
                getFileDisplay.style.display = 'none';
            } finally {
                getBtn.disabled = false;
                getBtn.textContent = 'Get File';
            }
        }

        // Display files in grid
        function displayFiles(files, containerId, pagination, type) {
            const container = document.getElementById(containerId);
            const paginationContainer = document.getElementById(type + 'Pagination');
            
            if (files.length === 0) {
                container.innerHTML = '<p>No files found.</p>';
                container.style.display = 'block';
                paginationContainer.style.display = 'none';
                return;
            }
            
            container.innerHTML = files.map(file => {
                const isVideo = ['mp4', 'webm', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'm4v', '3gp', 'ogv'].includes(file.extension.toLowerCase());
                const hasThumbnail = file.thumb_filename !== null;
                const thumbnailUrl = hasThumbnail ? file.urls.thumbnail : file.urls.image;
                
                return `
                    <div class="file-card">
                        ${isVideo ? 
                            `<video src="${file.urls.image}" width="150" height="150" controls style="object-fit: cover;"></video>` :
                            `<img src="${thumbnailUrl}" alt="${file.filename}" onerror="this.src='${file.urls.image}'">`
                        }
                        <div class="file-info">
                            <strong>${file.filename}</strong><br>
                            ID: ${file.id}<br>
                            Size: ${formatBytes(file.file_size)}<br>
                            ${file.width > 0 && file.height > 0 ? `Dimensions: ${file.width}x${file.height}<br>` : ''}
                            Type: ${isVideo ? 'Video' : 'Image'}<br>
                            Extension: ${file.extension}<br>
                            ${hasThumbnail ? 'Has Thumbnail: Yes' : 'Has Thumbnail: No'}<br>
                            Uploaded: ${new Date(file.created_at).toLocaleDateString()}
                        </div>
                        <div class="file-actions">
                            <button class="btn-secondary" onclick="window.open('${file.urls.image}', '_blank')">View</button>
                            <button class="btn-danger" onclick="deleteFile(${file.id})">Delete</button>
                        </div>
                    </div>
                `;
            }).join('');
            
            container.style.display = 'grid';
            
            // Display pagination
            if (pagination.total_pages > 1) {
                paginationContainer.innerHTML = `
                    ${pagination.has_prev_page ? `<button onclick="${type}Files(${pagination.prev_page})">Previous</button>` : ''}
                    <span>Page ${pagination.current_page} of ${pagination.total_pages}</span>
                    ${pagination.has_next_page ? `<button onclick="${type}Files(${pagination.next_page})">Next</button>` : ''}
                `;
                paginationContainer.style.display = 'flex';
            } else {
                paginationContainer.style.display = 'none';
            }
        }

        // Delete file
        async function deleteFile(fileId) {
            if (!confirm(`Are you sure you want to delete file with ID ${fileId}?`)) {
                return;
            }
            
            const apiKey = apiKeyInput.value.trim();
            
            try {
                const response = await fetch(`${CDN_CONFIG.apiBase}?action=delete&id=${encodeURIComponent(fileId)}`, {
                    method: 'POST',
                    headers: {
                        'X-API-Key': apiKey
                    }
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    alert('File deleted successfully!');
                    // Refresh current view
                    if (document.getElementById('search-tab').classList.contains('active')) {
                        searchFiles(currentSearchPage);
                    } else if (document.getElementById('list-tab').classList.contains('active')) {
                        listFiles(currentListPage);
                    }
                } else {
                    alert(`Delete failed: ${result.message}`);
                }
            } catch (error) {
                alert(`Error: ${error.message}`);
            }
        }

        // Utility functions
        
        function showResult(message, type, element) {
            element.textContent = message;
            element.className = `result ${type}`;
            element.style.display = 'block';
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function clearSearch() {
            document.getElementById('searchQuery').value = '';
            document.getElementById('searchExtension').value = '';
            document.getElementById('minSize').value = '';
            document.getElementById('maxSize').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('searchPagination').style.display = 'none';
            document.getElementById('searchResult').style.display = 'none';
        }
    </script>
</body>
</html> 