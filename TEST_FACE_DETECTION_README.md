# Face Detection Test Page

This document provides detailed information about the `test_face_detection.php` script, which is a testing interface for the face detection and cropping functionality.

## Overview

The `test_face_detection.php` is a standalone web interface that allows you to test the face detection and cropping functionality provided by `detect_face.py`. It's designed to help with development, testing, and debugging of the face detection feature.

## Prerequisites

1. PHP 7.4 or higher
2. Python 3.6 or higher
3. Required Python packages:
   - opencv-python
   - Pillow
4. Web server (Apache/Nginx) with PHP support
5. Sufficient permissions for the web server user (www-data) to execute Python scripts

## Directory Structure

```
idcrop/
├── test_face_detection.php    # The test interface
├── detect_face.py            # Face detection script
├── assets/                   # Static assets (CSS, JS)
├── uploads/                  # Uploaded test images
│   └── test/                 # Test-specific uploads
└── output/                   # Processed images
    └── test/                 # Test-specific outputs
```

## How It Works

1. User uploads an image through the web interface
2. The PHP script saves the image to the `uploads/test/` directory
3. The script executes `detect_face.py` with the input and output paths
4. The Python script processes the image, detects faces, and saves the cropped result
5. The web interface displays both the original and processed images

## Usage

1. Access the test page at: `http://your-domain.com/idcrop/test_face_detection.php`
2. Click "Choose File" and select an image containing a face
3. Click "Detect Face" to process the image
4. View the results showing both original and processed images

## Troubleshooting

### Common Issues

1. **Face not detected**
   - Ensure the image contains a clear, front-facing face
   - Check that the Haar Cascade file is accessible at `/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml`
   - Verify that the web server user has read permissions for the cascade file

2. **Permission denied errors**
   - Ensure the web server user (www-data) has write permissions to the `output/test/` directory
   - Run: `sudo chown -R www-data:www-data /var/www/html/idcrop/output`
   - Run: `sudo chmod -R 775 /var/www/html/idcrop/output`

3. **Python module not found**
   - Ensure required Python packages are installed:
     ```
     pip install opencv-python pillow
     ```
   - If using a virtual environment:
     ```bash
     # Activate the virtual environment
     source /var/www/html/idcrop/venv/bin/activate
     
     # Install required packages
     pip install pillow opencv-python
     
     # Run the script using the virtual environment's Python
     venv/bin/python3 detect_face.py test.jpg output.jpg
     ```
   - Make sure the virtual environment is activated when the script runs

### Verifying Installation

To verify that Pillow is installed correctly, run:

```bash
python3 -c "from PIL import Image; print('Pillow works')"
```

You should see the output: `Pillow works`

### Debugging

1. Check the PHP error log for any error messages:
   ```
   sudo tail -f /var/log/apache2/error.log
   ```

2. Test the Python script directly from the command line:
   ```
   python3 detect_face.py /path/to/input.jpg /path/to/output.jpg
   ```

3. Verify Python environment:
   ```
   python3 -c "import cv2; print('OpenCV version:', cv2.__version__)"
   ```

## Security Considerations

1. The test page is designed for development and testing purposes only
2. File uploads should be restricted to trusted users in production
3. Consider adding authentication if the test page is accessible from the internet
4. Regularly clean up old test files from the `uploads/test/` and `output/test/` directories

## Dependencies

- **PHP**: Handles file uploads and web interface
- **Python 3**: Runs the face detection script
- **OpenCV**: Provides computer vision capabilities
- **Pillow**: Handles image processing and saving

## License

This test interface is part of the ID Photo Cropper application. Please refer to the main project's license for usage terms.

## Support

For issues or questions, please contact the development team or open an issue in the project repository.
