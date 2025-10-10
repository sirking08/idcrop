# ID Card Face Detection Test Page

This test page allows you to upload ID card images, automatically detect faces, and extract ID numbers with the format `HS` followed by the last 7 digits of the ID.

## Features

- **Face Detection**: Automatically detects and crops faces from ID card images
- **ID Number Extraction**: Extracts ID numbers and formats them as `HS` followed by the last 7 digits
- **Image Preview**: Displays the original and cropped images
- **Download**: Option to download the processed images

## Requirements

- PHP 7.4 or higher
- Tesseract OCR
- OpenCV with Python bindings
- Required PHP extensions: `gd`, `zip`, `fileinfo`, `mbstring`

## Installation

1. Install system dependencies:
   ```bash
   sudo apt update
   sudo apt install tesseract-ocr python3-opencv python3-pil
   ```

2. Ensure the following directories exist and are writable by the web server:
   - `/var/www/idcrop/uploads/`
   - `/var/www/idcrop/output/`

3. Set proper permissions:
   ```bash
   sudo chown -R www-data:www-data /var/www/idcrop
   sudo chmod -R 775 /var/www/idcrop/uploads /var/www/idcrop/output
   ```

## Usage

1. Access the test page at: `http://your-server-address/idcrop/test_face_id.php`
2. Click "Choose File" and select an ID card image
3. Click "Process" to upload and process the image
4. The system will:
   - Detect the face in the image
   - Extract and display the ID number
   - Show the cropped face image
5. Use the "Download Image" button to save the cropped face

## ID Number Format

The system expects ID numbers in one of these formats:
- 8-digit number (e.g., `22022261`)
- Any 7+ digit number
- The last 7 digits will be taken and prefixed with `HS` (e.g., `HS2022261`)

## Troubleshooting

- **No face detected**: Ensure the ID card image is clear and the face is visible
- **Incorrect ID number**: Check the raw OCR text in the error log
- **Permission errors**: Verify directory permissions and ownership

## Logs

Debug information is logged to:
- PHP error log: `/var/log/apache2/error.log` or `/var/log/php_errors.log`
- Application log: `/tmp/idcrop_errors.log`

## Dependencies

- [Tesseract OCR](https://github.com/tesseract-ocr/tesseract)
- [OpenCV](https://opencv.org/)
- [Bootstrap 5](https://getbootstrap.com/) (loaded via CDN)

## License

This project is open source and available under the [MIT License](LICENSE).
