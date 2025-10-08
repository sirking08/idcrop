# ID Photo Cropper

A PHP web application that automatically detects faces in ID photos, crops them to a 1:1 aspect ratio, and extracts ID numbers using OCR.

## Features

- Upload multiple ID photos at once
- Automatic face detection and cropping
- ID number extraction using OCR
- Batch processing of multiple images
- Clean, responsive web interface
- Drag and drop file upload
- Progress tracking

## Requirements

- PHP 7.4 or higher
- Web server (Apache/Nginx)
- Required PHP extensions:
  - GD (for image processing)
  - Zip (for creating ZIP archives)
  - Fileinfo (for file type detection)
- For OCR functionality (choose one):
  - Tesseract OCR (recommended) or
  - Google Cloud Vision API or
  - AWS Textract

## Installation

1. Clone or download this repository to your web server's document root:
   ```bash
   git clone https://github.com/yourusername/id-photo-cropper.git /var/www/idcrop
   ```

2. Set the correct permissions:
   ```bash
   chmod -R 755 /var/www/idcrop
   chown -R www-data:www-data /var/www/idcrop
   ```

3. Make sure the following directories are writable by the web server:
   ```bash
   chmod -R 777 /var/www/idcrop/uploads
   chmod -R 777 /var/www/idcrop/output
   ```

4. Install Tesseract OCR (for local OCR processing):
   ```bash
   # On Ubuntu/Debian
   sudo apt update
   sudo apt install tesseract-ocr
   
   # On CentOS/RHEL
   sudo yum install tesseract
   
   # On macOS (using Homebrew)
   brew install tesseract
   ```

5. Install PHP extensions if not already installed:
   ```bash
   # On Ubuntu/Debian
   sudo apt install php-gd php-zip php-fileinfo
   
   # Restart your web server
   sudo systemctl restart apache2  # For Apache
   # or
   sudo systemctl restart nginx    # For Nginx
   ```

## Configuration

1. For Tesseract OCR (default), no additional configuration is needed.

2. For Google Cloud Vision API:
   - Create a service account and download the JSON key file
   - Set the environment variable:
     ```bash
     export GOOGLE_APPLICATION_CREDENTIALS="/path/to/your-service-account-key.json"
     ```

3. For AWS Textract:
   - Install AWS SDK for PHP:
     ```bash
     composer require aws/aws-sdk-php
     ```
   - Configure AWS credentials:
     ```bash
     aws configure
     ```

## Usage

1. Access the application through your web browser:
   ```
   http://your-server-address/idcrop/
   ```

2. Upload ID photos using the drag-and-drop interface or click to browse.

3. Click "Process Images" to start the face detection and OCR process.

4. Once processing is complete, click the download link to get a ZIP file containing all processed images.

## How It Works

1. **Upload**: Users upload one or more ID photos.
2. **Face Detection**: The application detects faces in each image using computer vision.
3. **Cropping**: Each face is cropped to a 1:1 aspect ratio and resized to 300x300 pixels.
4. **OCR**: The application extracts text from the image to find the ID number.
5. **Naming**: The cropped image is saved with the extracted ID number as the filename.
6. **Download**: All processed images are packaged into a ZIP file for download.

## Troubleshooting

- **Face not detected**: Make sure the face is clearly visible and well-lit.
- **Incorrect ID extraction**: The OCR might have difficulty with certain fonts or low-quality images.
- **Permission errors**: Ensure the `uploads` and `output` directories are writable by the web server.
- **Large files**: The application has a default file size limit of 10MB per image.

## Security Considerations

- The application validates file types to prevent uploading malicious files.
- File permissions are set to the minimum required level.
- Uploaded files are stored with random names to prevent directory traversal attacks.
- Consider implementing rate limiting in production.

## License

This project is open-source and available under the MIT License.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
