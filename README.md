# 🚀 PHP CDN Uploader API - Professional File Management System

A comprehensive, production-ready PHP-based CDN (Content Delivery Network) uploader system with automatic image processing, hash-based deduplication, and robust file management capabilities. Perfect for web applications, e-commerce platforms, and content management systems requiring efficient file storage and delivery.

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Database](https://img.shields.io/badge/Database-MySQL-4479A1.svg)](https://mysql.com)
[![CDN](https://img.shields.io/badge/CDN-Ready-orange.svg)](https://en.wikipedia.org/wiki/Content_delivery_network)

## ✨ Key Features

- 🔐 **Secure API Authentication** - Single API key authentication system
- 📁 **Multipart & Base64 Uploads** - Dual upload support with auto-detection
- 🖼️ **Automatic Image Processing** - Resize images to configurable dimensions
- 🎯 **Hash-Based Deduplication** - MD5 hash prevents duplicate storage
- 🔄 **Force File Replacement** - Replace existing files by filename with `force=true`
- 📏 **Conditional Thumbnail Generation** - Creates thumbnails for JPG, JPEG, PNG
- 🔍 **Advanced Search & Filtering** - Search by filename, extension, size, date
- 📊 **Database Tracking** - Complete file metadata storage and retrieval
- 🎬 **Multi-Format Support** - All image formats + popular video formats
- 📦 **File Size Limits** - Configurable 20MB maximum file size
- 🎨 **Configurable Quality** - Adjustable compression settings
- 🔄 **Deduplication Control** - Choose between replacing or creating new records
- 🧹 **Filename Normalization** - Automatic filesystem-safe filename cleaning
- 📄 **Pagination Support** - Efficient handling of large file collections

## 🛠️ System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Extensions**: GD Library, PDO MySQL
- **Permissions**: File system write access

## 🚀 Quick Start

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/php-cdn-uploader.git
cd php-cdn-uploader
```

### 2. Database Setup

1. Create a MySQL database
2. Import the `database_schema.sql` file:
   ```bash
   mysql -u youruser -p yourdatabase < database_schema.sql
   ```

### 3. Configuration

1. Copy the sample configuration:
   ```bash
   cp api/config.sample.php api/config.php
   ```

2. Edit `api/config.php` with your settings:

```php
// Domain Configuration
define('CDN_DOMAIN', 'https://cdn.yourdomain.com');
define('CDN_BASE_URL', CDN_DOMAIN . '/api/');
define('CDN_IMAGES_URL', CDN_DOMAIN . '/img/');
define('CDN_THUMBS_URL', CDN_DOMAIN . '/thumbs/');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// API Configuration
define('API_KEY', 'your-secure-api-key-here');
```

### 4. Directory Permissions

```bash
chmod 755 img/
chmod 755 thumbs/
```

### 5. Test the Installation

Access `test_upload.php` in your browser to test the API.

## 📁 Project Structure

```
php-cdn-uploader/
├── api/
│   ├── index.php              # Main API entry point
│   ├── config.php             # Configuration settings
│   ├── config.sample.php      # Sample configuration
│   ├── database.php           # Database connection class
│   ├── auth.php               # Authentication handler
│   └── handlers/
│       ├── upload.php         # Upload handler
│       ├── delete.php         # Delete handler
│       ├── list.php           # List handler
│       ├── search.php         # Search handler
│       └── get.php            # Get by ID handler
├── img/                       # Main image storage
├── thumbs/                    # Thumbnail storage
├── database_schema.sql        # Database schema
├── migrate_files.php          # File migration script
├── test_upload.php            # Test interface
├── README.md                  # This documentation
├── LICENSE                    # MIT License
└── .gitignore                 # Git ignore rules
```

## 🔧 Configuration Options

### Domain Configuration
```php
define('CDN_DOMAIN', 'https://cdn.yourdomain.com');
```
Update this to match your domain. The system will automatically generate:
- API base URL: `https://cdn.yourdomain.com/api/`
- Images URL: `https://cdn.yourdomain.com/img/`
- Thumbs URL: `https://cdn.yourdomain.com/thumbs/`

### Image Processing
```php
define('MAX_IMAGE_SIZE', 700);      // Max largest side for main images
define('MAX_THUMB_SIZE', 300);      // Max largest side for thumbnails
define('JPEG_QUALITY', 95);         // JPEG quality (0-100)
define('PNG_COMPRESSION', 6);       // PNG compression (0-9)
```

### File Management
```php
define('MAX_FILE_SIZE', 20 * 1024 * 1024);  // 20MB limit
define('DEDUPLICATE_UPLOADS', true);        // Hash-based deduplication
define('NORMALIZE_FILENAMES', true);        // Filesystem-safe names
```

## 🔐 Authentication

All API requests require the `X-API-Key` header:

```bash
X-API-Key: your-secure-api-key-here
```

## 📡 API Endpoints

### 1. Upload File

**POST** `/api/?action=upload`

Upload images or videos (max 20MB). Only JPG, JPEG, and PNG files get thumbnails generated.

#### Method 1: Multipart Upload

**Headers:**
```
X-API-Key: your-secure-api-key-here
```

**Request Body (multipart/form-data):**
```
file: [binary file data]
filename: my-photo.jpg (optional)
force: true (optional) - Force replace existing file with same filename
```

#### Method 2: Base64 Upload

**Headers:**
```
Content-Type: application/json
X-API-Key: your-secure-api-key-here
```

**Request Body (JSON):**
```json
{
    "image": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD...",
    "filename": "my-photo.jpg",
    "force": true
}
```

**Parameters:**
- `filename` (optional): Custom filename for the uploaded file
- `force` (optional): If `true`, replaces existing file with the same filename (ignores hash deduplication)

**Response:**
```json
{
    "status": "success",
    "message": "File uploaded successfully",
    "data": {
        "id": 123,
        "filename": "my-photo.jpg",
        "thumb_filename": "my-photo.jpg",
        "file_hash": "a1b2c3d4",
        "original_width": 1920,
        "original_height": 1080,
        "width": 700,
        "height": 394,
        "thumb_width": 300,
        "thumb_height": 169,
        "file_size": 45678,
        "thumb_size": 12345,
        "extension": "jpg",
        "mime_type": "image/jpeg",
        "created_at": "2024-01-15 10:30:00"
    }
}
```

### 2. Delete File

**POST** `/api/?action=delete`

Delete a file and its thumbnail from the CDN by database ID.

**Headers:**
```
X-API-Key: your-secure-api-key-here
```

**Parameters:**
- `id` (required): Database ID of the file to delete

**Example:**
```
POST /api/?action=delete&id=123
```

**Response:**
```json
{
    "status": "success",
    "message": "File deleted successfully",
    "data": {
        "id": 123,
        "filename": "my-photo.jpg",
        "thumb_filename": "my-photo.jpg"
    }
}
```

### 3. List Files

**GET** `/api/?action=list`

List files with pagination and filtering.

**Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20, max: 100)
- `extension` (optional): Filter by file extension
- `search` (optional): Search in filenames

**Headers:**
```
X-API-Key: your-secure-api-key-here
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "files": [
            {
                "id": 123,
                "filename": "my-photo.jpg",
                "thumb_filename": "my-photo.jpg",
                "file_hash": "a1b2c3d4",
                "original_width": 1920,
                "original_height": 1080,
                "width": 700,
                "height": 394,
                "thumb_width": 300,
                "thumb_height": 169,
                "file_size": 45678,
                "thumb_size": 12345,
                "extension": "jpg",
                "mime_type": "image/jpeg",
                "created_at": "2024-01-15 10:30:00",
                "urls": {
                    "image": "https://cdn.yourdomain.com/img/my-photo.jpg",
                    "thumbnail": "https://cdn.yourdomain.com/thumbs/my-photo.jpg"
                }
            }
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total": 150,
            "total_pages": 8,
            "has_next_page": true,
            "has_prev_page": false,
            "next_page": 2,
            "prev_page": null
        },
        "filters": {
            "extension": "jpg",
            "search": "photo"
        }
    }
}
```

### 4. Get File by ID

**GET** `/api/?action=get`

Retrieve a single file by its database ID.

**Parameters:**
- `id` (required): Database ID of the file

**Headers:**
```
X-API-Key: your-secure-api-key-here
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "id": 123,
        "filename": "my-photo.jpg",
        "thumb_filename": "my-photo.jpg",
        "file_hash": "a1b2c3d4",
        "original_width": 1920,
        "original_height": 1080,
        "width": 700,
        "height": 394,
        "thumb_width": 300,
        "thumb_height": 169,
        "file_size": 45678,
        "thumb_size": 12345,
        "extension": "jpg",
        "mime_type": "image/jpeg",
        "created_at": "2024-01-15 10:30:00",
        "urls": {
            "image": "https://cdn.yourdomain.com/img/my-photo.jpg",
            "thumbnail": "https://cdn.yourdomain.com/thumbs/my-photo.jpg"
        }
    }
}
```

### 5. Search Files

**GET** `/api/?action=search`

Advanced search with multiple filters.

**Parameters:**
- `q` (required): Search query for filename LIKE '%str%'
- `extension` (optional): Filter by file extension
- `min_size` (optional): Minimum file size in bytes
- `max_size` (optional): Maximum file size in bytes
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20, max: 100)

**Headers:**
```
X-API-Key: your-secure-api-key-here
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "query": "photo",
        "files": [
            {
                "id": 123,
                "filename": "vacation-photo.jpg",
                "thumb_filename": "vacation-photo.jpg",
                "file_hash": "a1b2c3d4",
                "original_width": 1920,
                "original_height": 1080,
                "width": 700,
                "height": 394,
                "thumb_width": 300,
                "thumb_height": 169,
                "file_size": 45678,
                "thumb_size": 12345,
                "extension": "jpg",
                "mime_type": "image/jpeg",
                "created_at": "2024-01-15 10:30:00",
                "urls": {
                    "image": "https://cdn.yourdomain.com/img/vacation-photo.jpg",
                    "thumbnail": "https://cdn.yourdomain.com/thumbs/vacation-photo.jpg"
                }
            }
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total": 25,
            "total_pages": 2,
            "has_next_page": true,
            "has_prev_page": false,
            "next_page": 2,
            "prev_page": null
        },
        "filters": {
            "extension": "jpg",
            "min_size": 10000,
            "max_size": 100000,
            "date_from": "2024-01-01",
            "date_to": "2024-01-31"
        }
    }
}
```

## 🎯 Advanced Features

### Hash-Based Deduplication

The system uses MD5 hash-based deduplication:

- **Enabled** (`DEDUPLICATE_UPLOADS = true`): 
  - If hash matches: Returns existing file data without changes
  - If hash doesn't match but filename does: Creates new file with appended name (e.g., `image_2.jpg`)
- **Disabled** (`DEDUPLICATE_UPLOADS = false`): 
  - If hash matches: Updates existing record with new filename (same content, new name)
  - If hash doesn't match but filename does: Creates new file with appended name (e.g., `image_2.jpg`)
- **Both modes**: Preserve all files, never delete existing content

### Filename Normalization

Automatic filename cleaning for filesystem compatibility:

- Replaces spaces, commas, special characters with hyphens
- Examples: `"My Photo, 2024!.jpg"` → `"My-Photo-2024.jpg"`
- Falls back to random names if normalization results in empty filename

### Supported File Formats

#### Images
- **Full Support**: JPG, JPEG, PNG (with thumbnail generation)
- **Upload Only**: GIF, WebP, BMP, TIFF, SVG, ICO, AVIF, HEIC

#### Videos
- **Full Support**: MP4, WebM, AVI, MOV, WMV, FLV, MKV, M4V, 3GP, OGV

## 🔄 Migration Script

For existing files, use the migration script:

```bash
php migrate_files.php
```

This script will:
- Create database records for files without records
- Generate thumbnails for JPG/JPEG/PNG files
- Calculate MD5 hashes for all files
- Update file creation dates

## 🧪 Testing

### Web Interface
Access `test_upload.php` for a complete web-based testing interface.

### cURL Examples

**Upload File:**
```bash
curl -X POST "https://cdn.yourdomain.com/api/?action=upload" \
  -H "X-API-Key: your-api-key" \
  -F "file=@/path/to/image.jpg" \
  -F "filename=my-image.jpg"
```

**List Files:**
```bash
curl "https://cdn.yourdomain.com/api/?action=list&page=1&per_page=10" \
  -H "X-API-Key: your-api-key"
```

**Delete File:**
```bash
curl -X POST "https://cdn.yourdomain.com/api/?action=delete&id=123" \
  -H "X-API-Key: your-api-key"
```

**Search Files:**
```bash
curl "https://cdn.yourdomain.com/api/?action=search&q=photo&extension=jpg" \
  -H "X-API-Key: your-api-key"
```

### JavaScript Examples

**Multipart Upload:**
```javascript
const uploadFile = async (file, filename) => {
    const formData = new FormData();
    formData.append('file', file);
    if (filename) {
        formData.append('filename', filename);
    }
    
    const response = await fetch('https://cdn.yourdomain.com/api/?action=upload', {
        method: 'POST',
        headers: {
            'X-API-Key': 'your-secure-api-key-here'
        },
        body: formData
    });
    
    return await response.json();
};
```

**List Files:**
```javascript
const listFiles = async (page = 1, perPage = 20, extension = '', search = '') => {
    const params = new URLSearchParams({
        page: page.toString(),
        per_page: perPage.toString()
    });
    
    if (extension) params.append('extension', extension);
    if (search) params.append('search', search);
    
    const response = await fetch(`https://cdn.yourdomain.com/api/?action=list&${params}`, {
        headers: {
            'X-API-Key': 'your-secure-api-key-here'
        }
    });
    
    return await response.json();
};
```

## 📊 Database Schema

The system uses a single `cdn_files` table with comprehensive indexing:

```sql
CREATE TABLE cdn_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL UNIQUE,
  thumb_filename VARCHAR(255) NULL,
  file_hash VARCHAR(32) NOT NULL UNIQUE,
  original_width INT DEFAULT 0,
  original_height INT DEFAULT 0,
  width INT DEFAULT 0,
  height INT DEFAULT 0,
  thumb_width INT DEFAULT 0,
  thumb_height INT DEFAULT 0,
  file_size BIGINT NOT NULL,
  thumb_size BIGINT DEFAULT 0,
  extension VARCHAR(10) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 🔒 Security Considerations

1. **API Key**: Use a strong, unique API key in production
2. **File Validation**: Only allowed extensions are processed
3. **Size Limits**: Configurable file size limits prevent abuse
4. **Path Traversal**: Filename normalization prevents path traversal attacks
5. **Database**: Use prepared statements to prevent SQL injection

## 🚀 Performance Features

- **Hash-Based Lookups**: Fast MD5 hash-based deduplication
- **Database Indexing**: Comprehensive indexes for all query patterns
- **Image Optimization**: Configurable compression and resizing
- **Pagination**: Efficient handling of large datasets
- **Conditional Processing**: Only generates thumbnails when needed

## 📈 Use Cases

- **E-commerce Platforms**: Product image management
- **Content Management Systems**: Media file organization
- **Social Media Applications**: User upload handling
- **Blog Platforms**: Article image storage
- **Portfolio Websites**: Gallery management
- **Mobile Applications**: Backend file storage
- **Web Applications**: General file upload needs

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- 🐛 **Issues**: Report bugs via GitHub Issues
- 💡 **Feature Requests**: Suggest new features via GitHub Issues
- 📧 **Contact**: Open an issue for questions or support

## 🙏 Acknowledgments

- Built with PHP and MySQL
- Image processing powered by GD Library
- MD5 hash-based deduplication for storage efficiency
- Comprehensive error handling and validation

---

## 🏢 Development Credits

**Developed by [CodeWizDev](https://codewizdev.com)** - Your Full Stack Development Agency

Specializing in:
- 🚀 Custom Web Applications
- 📱 Mobile App Development
- 🛒 E-commerce Solutions
- 🔧 API Development & Integration
- ☁️ Cloud Infrastructure
- 🎨 UI/UX Design

**Contact us for your next project:**
- 🌐 Website: [https://codewizdev.com](https://codewizdev.com)
- 📧 Email: [hello@codewizdev.com](mailto:hello@codewizdev.com)
- 💼 Services: Custom development, consulting, and technical solutions

---

**⭐ Star this repository if you find it useful!** 
