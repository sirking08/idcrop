# ID Photo Cropper

A PHP web application that automatically detects faces in ID photos, crops them to a 1:1 aspect ratio, and extracts ID numbers using OCR.

## ✨ Features

- **Face Detection**: Automatically detects and centers on faces in ID photos using OpenCV
- **Smart Cropping**: Intelligently crops to focus on the main subject with proper padding
- **Fallback Mechanism**: Gracefully falls back to original image if face detection fails
- **Multiple Formats**: Supports JPG, PNG, and other common image formats
- **Batch Processing**: Process multiple images in one go with ZIP download
- **Responsive Design**: Works on both desktop and mobile devices
- **Drag & Drop**: Simple and intuitive file upload interface
- **Real-time Progress**: Track upload and processing status
- **Secure**: File type validation and secure processing
- **Detailed Logging**: Comprehensive error logging for troubleshooting

## 🚀 System Requirements

- **Web Server**: Apache 2.4+ or Nginx
- **PHP**: 8.1+ (recommended) or PHP 8.0+
- **Python**: 3.6+ (required for face detection)
- **PHP Extensions**:
  - ✅ GD (for image processing)
  - ✅ ZIP (for creating archives)
  - ✅ Fileinfo (for file validation)
  - ✅ JSON (for API responses)
  - ✅ OpenSSL (for secure connections)
  - ✅ MBString (for string handling)
  - ✅ EXIF (for image orientation handling)
- **Recommended Server Specs**:
  - Minimum: 1GB RAM, 1 CPU core
  - Recommended: 2GB+ RAM, 2+ CPU cores for better performance with multiple concurrent users

### For Face Detection:
- **OpenCV with Python** (Required for face detection):
  ```bash
  # Update package list and install required dependencies
  sudo apt-get update
  
  # Install OpenCV and required system packages
  sudo apt-get install -y python3-opencv python3-pip python3-pil python3-olefile \
    libimagequant0 libraqm0 libwebpdemux2 opencv-data
  
  # Verify installation
  python3 -c "import cv2, os; print(f'OpenCV version: {cv2.__version__}'); print('Haar Cascade file exists:', 'Yes' if any(os.path.exists(p) for p in ['/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml', '/usr/local/share/opencv4/haarcascades/haarcascade_frontalface_default.xml', '/usr/local/share/opencv/haarcascades/haarcascade_frontalface_default.xml', 'haarcascade_frontalface_default.xml']) else 'No')"
  
  # Download Haar Cascade classifier file
  wget https://raw.githubusercontent.com/opencv/opencv/4.x/data/haarcascades/haarcascade_frontalface_default.xml -O /path/to/your/project/haarcascade_frontalface_default.xml
  ```

  **Note**: The application will automatically look for the Haar Cascade classifier file in these locations:
  - `/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml` (recommended)
  - `/usr/local/share/opencv4/haarcascades/haarcascade_frontalface_default.xml`
  - `/usr/local/share/opencv/haarcascades/haarcascade_frontalface_default.xml`
  - `haarcascade_frontalface_default.xml` (in the project directory)

- **PHP Requirements**:
  ```bash
  # Required PHP extensions
  sudo apt-get install php-gd php-zip php-mbstring
  
  # Enable PHP error logging (if not already enabled)
  sudo sed -i 's/^;error_log = .*/error_log = \/var\/log\/php_errors.log/' /etc/php/8.1/apache2/php.ini
  sudo touch /var/log/php_errors.log
  sudo chown www-data:www-data /var/log/php_errors.log
  ```

### For OCR (Optional):
- Tesseract OCR (recommended for local processing)
  ```bash
  # On Ubuntu/Debian
  sudo apt-get install tesseract-ocr
  
  # On CentOS/RHEL
  sudo yum install tesseract
  
  # On macOS (using Homebrew)
  brew install tesseract
  ```

## 🔧 Recommended Server Configuration

```apache
# Apache Configuration Example
<Directory "/var/www/html/idcrop">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# PHP Settings
php_value upload_max_filesize 10M
php_value post_max_size 12M
php_value max_execution_time 300
php_value max_input_time 300
```

```nginx
# Nginx Configuration Example
location /idcrop {
    root /var/www/html;
    index index.php index.html;
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 🛠 Installation

1. **Clone the repository**:
   ```bash
   # Clone to your web root
   sudo git clone https://github.com/yourusername/id-photo-cropper.git /var/www/html/idcrop
   cd /var/www/html/idcrop
   ```

2. **Set up permissions**:
   ```bash
   # Set ownership to web server user (typically www-data or apache)
   sudo chown -R www-data:www-data /var/www/html/idcrop
   
   # Set directory permissions
   sudo find /var/www/html/idcrop -type d -exec chmod 755 {} \;
   sudo find /var/www/html/idcrop -type f -exec chmod 644 {} \;
   
   # Make upload and output directories writable
   sudo chmod -R 775 /var/www/html/idcrop/uploads
   sudo chmod -R 775 /var/www/html/idcrop/output
   ```

3. **Install required PHP extensions**:
   ```bash
   # On Ubuntu/Debian
   sudo apt update
   sudo apt install -y php-gd php-zip php-fileinfo php-mbstring php-json php-xml
   
   # Set proper permissions for web server
   sudo chown -R www-data:www-data /var/www/html/idcrop
   sudo chmod -R 775 /var/www/html/idcrop/uploads /var/www/html/idcrop/output
   
   # Create necessary directories if they don't exist
   mkdir -p /var/www/html/idcrop/uploads/temp
   mkdir -p /var/www/html/idcrop/output
   
   # On CentOS/RHEL
   sudo yum install -y php-gd php-zip php-fileinfo php-mbstring php-json php-xml
   
   # On macOS (using Homebrew)
   brew install php-gd php-zip php-fileinfo php-mbstring
   ```

4. **Configure PHP settings**:
   ```bash
   # Edit your php.ini (location varies by system)
   sudo nano /etc/php/8.2/apache2/php.ini  # Adjust path as needed
   
   # Update these values:
   upload_max_filesize = 10M
   post_max_size = 12M
   max_execution_time = 300
   max_input_time = 300
   memory_limit = 256M
   ```

5. **Restart your web server**:
   ```bash
   # For Apache
   sudo systemctl restart apache2
   
   # For Nginx with PHP-FPM
   sudo systemctl restart nginx
   sudo systemctl restart php8.2-fpm  # Adjust version as needed
   ```

6. **Verify installation**:
   Create a test PHP file with `<?php phpinfo(); ?>` and check that all required extensions are loaded.

## 🐍 Python Virtual Environment Setup

For better dependency management, it's recommended to use a Python virtual environment:

```bash
# Navigate to your project directory
cd /var/www/html/idcrop

# Install Python virtualenv if not already installed
sudo apt-get install python3-venv

# Create a new virtual environment
python3 -m venv venv

# Activate the virtual environment
source venv/bin/activate

# Install required Python packages
pip install opencv-python-headless Pillow

# Verify the installations
python -c "import cv2; print(f'OpenCV version: {cv2.__version__}')"
python -c "from PIL import Image; print(f'Pillow version: {Image.__version__}'); print('Pillow installation verified!')"

# Optional: Test basic image processing
python -c "
from PIL import Image, ImageDraw, ImageFont
import numpy as np

# Create a test image
img = Image.new('RGB', (200, 100), color='white')
draw = ImageDraw.Draw(img)
draw.text((10, 10), 'Pillow Works!', fill='black')

# Save test image
test_path = 'pillow_test.png'
img.save(test_path)
print(f'Test image saved to: {test_path}')

# Test OpenCV integration
img_cv = cv2.imread(test_path)
if img_cv is not None:
    print('OpenCV can read Pillow-created images')
    print(f'Image dimensions: {img_cv.shape[1]}x{img_cv.shape[0]}')
else:
    print('Warning: OpenCV could not read the test image')
"

# To deactivate the virtual environment when done
deactivate
```

### 🚀 Auto-activation for Web Server

To ensure the virtual environment is activated when the web server runs the Python script, add this to the top of your `detect_face.py`:

```python
#!/usr/bin/env python3

# Activate virtual environment
activate_this = '/var/www/html/idcrop/venv/bin/activate_this.py'
with open(activate_this) as f:
    exec(f.read(), {'__file__': activate_this})

# Rest of your imports and code
import cv2
import sys
# ...
```

## ⚙️ Configuration

### Face Detection Settings

1. **Basic Face Detection** (enabled by default):
   - Uses PHP's GD library for simple face detection
   - Works without additional dependencies
   - Best for standard ID photos with clear frontal faces

2. **Advanced Face Detection** (recommended for production):
   - Uses OpenCV for more accurate face detection
   - Requires OpenCV Python bindings:
     ```bash
     sudo apt-get install python3-opencv
     ```
   - Or PHP-OpenCV extension (if available for your system)

### OCR Configuration (Optional)

1. **Tesseract OCR** (recommended for local processing):
   ```bash
   # Install Tesseract
   sudo apt-get install tesseract-ocr
   
   # Install additional language packs if needed
   sudo apt-get install tesseract-ocr-eng tesseract-ocr-spa  # etc.
   ```

2. **Google Cloud Vision API**:
   - Enable the Cloud Vision API in Google Cloud Console
   - Create a service account and download the JSON key
   - Set the environment variable:
     ```bash
     echo 'export GOOGLE_APPLICATION_CREDENTIALS="/path/to/your-service-account-key.json"' >> ~/.bashrc
     source ~/.bashrc
     ```

3. **AWS Textract**:
   - Install AWS SDK:
     ```bash
     composer require aws/aws-sdk-php
     ```
   - Configure AWS credentials:
     ```bash
     aws configure
     # Enter your AWS Access Key ID, Secret Access Key, and default region
     ```

### Security Configuration

1. **File Upload Security**:
   - File types are strictly validated
   - Maximum file size is limited to 10MB
   - Files are scanned for potential threats

2. **Directory Protection**:
   ```apache
   # Prevent directory listing
   Options -Indexes
   
   # Protect sensitive files
   <FilesMatch "^\.|\.(ini|log|sh|sql)$">
       Require all denied
   </FilesMatch>
   ```

## 🚀 Quick Start

1. **Access the Application**:
   Open your web browser and navigate to:
   ```
   http://your-server-address/idcrop/
   ```

2. **Upload Photos**:
   - Drag and drop images onto the upload area, or
   - Click "Browse Files" to select images
   - Supported formats: JPG, PNG, WebP
   - Maximum file size: 10MB per image

3. **Process Images**:
   - Click the "Process Images" button
   - Watch the progress bar for each image
   - The system will automatically detect and crop faces

4. **Download Results**:
   - Once processing is complete, a download button will appear
   - Click to download a ZIP file containing all processed images
   - Each image will be named with a timestamp and original filename

## 🎯 Advanced Usage

### Batch Processing
- Select multiple files at once (Ctrl+Click or Shift+Click)
- The system will process up to 50 images in one batch
- Progress is shown for each file

### Keyboard Shortcuts
- `Ctrl/Cmd + O`: Open file dialog
- `Ctrl/Cmd + D`: Clear selected files
- `Esc`: Cancel current operation

### API Access
You can also use the application programmatically:

```bash
# Upload and process images via cURL
curl -X POST -F "images[]=@/path/to/your/photo1.jpg" -F "images[]=@/path/to/your/photo2.jpg" http://your-server-address/idcrop/
```

### Expected Output
Processed images will be:
- Cropped to focus on the detected face
- Resized to optimal dimensions
- Saved in high quality
- Named with the pattern: `processed_TIMESTAMP_ORIGINALNAME.jpg`

## 🧠 How It Works

### 1. Upload & Validation
- Files are uploaded to a temporary directory
- File type and size are validated
- Images are checked for potential issues

### 2. Face Detection & Processing
1. **Face Detection**:
   - The system first tries OpenCV for precise face detection
   - Falls back to PHP-GD based skin-tone detection if OpenCV is not available
   - Finally uses center-cropping as a fallback

2. **Image Enhancement**:
   - Auto-orientation based on EXIF data
   - Contrast and brightness adjustment
   - Noise reduction

3. **Smart Cropping**:
   - Detects the main subject
   - Applies optimal padding
   - Maintains aspect ratio
   - Ensures minimum size requirements

### 3. Output Generation
- Images are saved in the highest quality
- Metadata is preserved (where applicable)
- Files are organized in a ZIP archive
- Cleanup of temporary files

## 🛠 Troubleshooting

### Common Issues & Solutions

#### Face Detection Issues
- **No face detected**:
  - Ensure the face is clearly visible and well-lit
  - Try with a higher resolution image
  - Make sure the face takes up at least 30% of the image

- **Multiple faces detected**:
  - The system will prioritize the largest face
  - For group photos, consider cropping to individual faces first

#### Upload Issues
- **File upload fails**:
  - Check file size (max 10MB)
  - Verify file type (JPG, PNG, WebP)
  - Ensure PHP has write permissions to upload directories
  ```bash
  sudo chown -R www-data:www-data /var/www/html/idcrop/uploads/
  sudo chmod -R 775 /var/www/html/idcrop/uploads/
  ```

- **Processing timeout**:
  - Increase PHP execution time in php.ini:
    ```ini
    max_execution_time = 300
    max_input_time = 300
    ```

#### Performance Issues
- **Slow processing**:
  - Optimize image size before upload (recommended max 2000x2000px)
  - Enable PHP OPcache
  - Consider using a more powerful server for large batches

### Logs
Check the following logs for detailed error information:
- PHP error log: `/var/log/php/error.log`
- Apache error log: `/var/log/apache2/error.log`
- Nginx error log: `/var/log/nginx/error.log`

### Getting Help
If you encounter any issues, please:
1. Check the error message
2. Verify file permissions
3. Check server logs
4. [Open an issue](https://github.com/yourusername/id-photo-cropper/issues) with details

## 🔒 Security Considerations

### File Upload Security
- **Strict Validation**:
  - MIME type verification
  - File signature checking
  - Size limitations (10MB max)
  - Blacklisted extensions

### Server Security
- **Secure Permissions**:
  ```bash
  # Recommended permissions
  chmod 750 /var/www/html/idcrop
  chmod 750 /var/www/html/idcrop/uploads
  chmod 750 /var/www/html/idcrop/output
  ```

- **PHP Hardening**:
  ```ini
  ; php.ini settings
  expose_php = Off
  display_errors = Off
  log_errors = On
  allow_url_fopen = Off
  allow_url_include = Off
  ```

### Data Protection
- **Temporary Files**:
  - Automatically deleted after processing
  - Stored outside web root when possible

- **Privacy**:
  - No personal data is stored permanently
  - Uploaded files are deleted after download
  - Consider adding HTTPS for production use

### Rate Limiting
For production environments, implement rate limiting:

```apache
# Apache rate limiting
<IfModule mod_ratelimit.c>
    <Location "/idcrop/upload.php">
        RLimitPost 10485760
        RLimitSpeed 1048576
    </Location>
</IfModule>
```

```nginx
# Nginx rate limiting
limit_req_zone $binary_remote_addr zone=upload:10m rate=1r/s;

location /idcrop/upload.php {
    limit_req zone=upload burst=5;
    # ... other config ...
}
```

## 📜 Changelog

### [1.3.0] - 2025-10-09
- **Fixed**: Face detection integration with main application
- **Improved**: Enhanced error handling and logging for face detection
- **Added**: Graceful fallback to original images when face detection fails
- **Optimized**: Improved memory management for batch processing
- **Updated**: Comprehensive documentation and system requirements

### [1.2.0] - 2025-10-08
- **Added**: Advanced face detection with OpenCV fallback
- **Improved**: Better handling of various image formats and qualities
- **Enhanced**: More accurate face detection and cropping
- **Fixed**: Memory leaks in image processing
- **Updated**: Security improvements for file uploads

### [1.1.0] - 2025-10-07
- **Added**: Support for batch processing
- **Improved**: Progress tracking and UI feedback
- **Enhanced**: Error handling and logging

### [1.0.1] - 2025-10-06
- Fixed: Syntax error in `assets/app.js`
- Added: Better error handling for form submission
- Improved: File validation and security

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

## 👥 Contributing

We welcome contributions! Here's how you can help:

1. **Report Bugs**: [Open an issue](https://github.com/yourusername/id-photo-cropper/issues) with detailed steps to reproduce
2. **Suggest Features**: Share your ideas for improvements
3. **Submit Code**: Send a pull request with your changes

### Development Setup

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add some amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a pull request

### Testing
Before submitting a pull request, please ensure:
- All tests pass
- Code follows PSR-12 coding standards
- New features include appropriate tests
- Documentation is updated

## 🙏 Acknowledgments

- Built with PHP, JavaScript, and love
- Uses OpenCV and GD for image processing
- Inspired by the need for simple, effective ID photo processing
