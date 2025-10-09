<?php
/**
 * Image Processor for ID Photo Cropper
 * 
 * This file contains the image processing functionality including face detection
 * and cropping operations.
 */

namespace IDPhotoCropper;

use function error_log;
use function file_exists;
use function filesize;
use function getimagesize;
use function imagecolorallocate;
use function imagecolorallocatealpha;
use function imagecopyresampled;
use function imagecreatefromjpeg;
use function imagecreatefrompng;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefill;
use function imagejpeg;
use function imagepng;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function imagerotate;
use function imagescale;
use function imagealphablending;
use function is_dir;
use function is_resource;
use function is_writable;
use function mkdir;
use function pathinfo;
use function rtrim;
use function sprintf;
use function strtolower;
use function tempnam;
use function unlink;
use function uniqid;
use function is_array;
use function array_sum;
use function array_column;
use function count;
use function max;
use function min;
use function extension_loaded;
use function chmod;

// If we're not in a namespace, we need to use the global Exception class
if (!class_exists('Exception') && class_exists('\Exception')) {
    class_alias('\Exception', 'Exception');
}

// If we're not in a namespace, we need to use the global RuntimeException class
if (!class_exists('RuntimeException') && class_exists('\RuntimeException')) {
    class_alias('\RuntimeException', 'RuntimeException');
}

/**
 * Process an uploaded image with face detection and cropping
 * 
 * @param array $file The uploaded file array (similar to $_FILES)
 * @param string $uploadDir Directory where uploaded files are stored
 * @param string $outputDir Directory where processed files should be saved
 * @return array Result array with success status and output path
 */
function processImage($file, $uploadDir, $outputDir) {
    $result = [
        'success' => false,
        'message' => '',
        'output_path' => '',
        'original_path' => $file['tmp_name']
    ];
    
    // Initialize image resources
    $image = null;
    $cropped = null;
    
    try {
        // Ensure output directory exists and is writable
        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new \RuntimeException(sprintf('Output directory "%s" was not created', $outputDir));
        }
        
        if (!is_writable($outputDir)) {
            throw new \RuntimeException(sprintf('Output directory "%s" is not writable', $outputDir));
        }
        
        // Generate output filename with original extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('processed_', true) . '.' . $ext;
        $outputPath = rtrim($outputDir, '/') . '/' . $filename;
        
        // Check if the uploaded file exists
        if (!file_exists($file['tmp_name'])) {
            throw new \Exception('Temporary file not found');
        }
        
        // Get image info and create image resource
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new \Exception('Invalid image file');
        }
        
        $mime = $imageInfo['mime'];
        
        // Create a temporary file for face detection
        $tempImagePath = tempnam(sys_get_temp_dir(), 'face_detect_') . '.' . $ext;
        if (!copy($file['tmp_name'], $tempImagePath)) {
            throw new \Exception('Failed to create temporary image for face detection');
        }
        
        // Detect face in the image
        $face = detectFace($tempImagePath);
        
        // Clean up temporary file
        @unlink($tempImagePath);
        
        // Load the original image for processing
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file['tmp_name']);
                break;
            default:
                throw new \Exception('Unsupported image type: ' . $mime);
        }
        
        if (!$image) {
            throw new \Exception('Failed to load image. The file might be corrupted or not a valid image.');
        }
        
        // Get image dimensions
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Ensure face coordinates are within image bounds
        $faceX = max(0, min($width - 10, $face['x']));
        $faceY = max(0, min($height - 10, $face['y']));
        $faceWidth = min($width - $faceX, $face['width']);
        $faceHeight = min($height - $faceY, $face['height']);
        
        // Add some padding around the detected face (20% of face size)
        $paddingX = $faceWidth * 0.2;
        $paddingY = $faceHeight * 0.2;
        
        $cropX = max(0, $faceX - $paddingX);
        $cropY = max(0, $faceY - $paddingY);
        $cropWidth = min($width - $cropX, $faceWidth + ($paddingX * 2));
        $cropHeight = min($height - $cropY, $faceHeight + ($paddingY * 2));
        
        // Ensure minimum size
        $minSize = min($width, $height) * 0.3; // At least 30% of the smaller dimension
        if ($cropWidth < $minSize) {
            $cropX = max(0, $cropX - (($minSize - $cropWidth) / 2));
            $cropWidth = $minSize;
        }
        if ($cropHeight < $minSize) {
            $cropY = max(0, $cropY - (($minSize - $cropHeight) / 2));
            $cropHeight = $minSize;
        }
        
        // Ensure we don't go out of bounds
        $cropX = max(0, (int)$cropX);
        $cropY = max(0, (int)$cropY);
        $cropWidth = min($width - $cropX, (int)$cropWidth);
        $cropHeight = min($height - $cropY, (int)$cropHeight);
        
        // Create a new true color image for the cropped face
        $cropped = imagecreatetruecolor($cropWidth, $cropHeight);
        
        // Preserve transparency for PNG
        if ($mime === 'image/png') {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
            $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
            imagefill($cropped, 0, 0, $transparent);
        } else {
            // For JPEG, use white background
            $white = imagecolorallocate($cropped, 255, 255, 255);
            imagefill($cropped, 0, 0, $white);
        }
        
        // Copy the face region from the original image to the new image
        imagecopyresampled(
            $cropped, $image,
            0, 0, $cropX, $cropY,
            $cropWidth, $cropHeight, $cropWidth, $cropHeight
        );
        
        // Save the cropped image
        $success = false;
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $success = imagejpeg($cropped, $outputPath, 90);
                break;
            case 'image/png':
                $success = imagepng($cropped, $outputPath, 9);
                break;
        }
        
        if (!$success) {
            throw new \Exception('Failed to save processed image');
        }
        
        // Free up memory
        imagedestroy($image);
        imagedestroy($cropped);
        
        // Set proper permissions
        if (!chmod($outputPath, 0664)) {
            error_log('Warning: Failed to set permissions for ' . $outputPath, 3, '/tmp/idcrop_errors.log');
        }
        
        $result['success'] = true;
        $result['message'] = 'Image processed and cropped to face successfully';
        $result['output_path'] = $outputPath;
        
    } catch (\Exception $e) {
        $result['message'] = $e->getMessage();
        error_log('Image processing error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 3, '/tmp/idcrop_errors.log');
        
        // Clean up any created resources
        if (isset($image) && is_resource($image)) {
            imagedestroy($image);
        }
        if (isset($cropped) && is_resource($cropped)) {
            imagedestroy($cropped);
        }
        
        // If we failed but have a partial output file, clean it up
        if (isset($outputPath) && file_exists($outputPath)) {
            @unlink($outputPath);
        }
        
        // If we have a temporary file, clean it up
        if (isset($tempImagePath) && file_exists($tempImagePath)) {
            @unlink($tempImagePath);
        }
    }
    
    return $result;
}

/**
 * Detect face in an image using OpenCV if available, or fall back to simpler method
 * 
 * @param string $imagePath Path to the image file
 * @return array Face coordinates (x, y, width, height)
 */
function detectFace($imagePath) {
    // Try using OpenCV if available
    if (extension_loaded('opencv')) {
        try {
            // Load the pre-trained face detection model
            $faceCascade = new \CascadeClassifier();
            $modelFile = '/usr/share/opencv/haarcascades/haarcascade_frontalface_default.xml';
            
            if (!file_exists($modelFile)) {
                throw new \Exception('Face detection model not found');
            }
            
            if (!$faceCascade->load($modelFile)) {
                throw new \Exception('Failed to load face detection model');
            }
            
            // Read the image
            $image = \cv\imread($imagePath);
            if ($image->empty()) {
                throw new \Exception('Failed to load image with OpenCV');
            }
            
            // Convert to grayscale as face detection works better on grayscale images
            $gray = new \Mat();
            \cv\cvtColor($image, $gray, \cv\COLOR_BGR2GRAY);
            
            // Detect faces
            $faces = new \RectVector();
            $faceCascade->detectMultiScale($gray, $faces);
            
            // If we found faces, return the first one
            if ($faces->size() > 0) {
                $face = $faces->get(0);
                return [
                    'x' => $face->x,
                    'y' => $face->y,
                    'width' => $face->width,
                    'height' => $face->height
                ];
            }
        } catch (\Exception $e) {
            error_log('OpenCV face detection failed: ' . $e->getMessage(), 3, '/tmp/idcrop_errors.log');
        }
    }
    
    // Fallback: Use face detection with PHP's GD
    // This is a simpler method that looks for skin tones
    $img = @imagecreatefromjpeg($imagePath);
    if (!$img) {
        $img = @imagecreatefrompng($imagePath);
    }
    
    if (!$img) {
        // If we can't load the image, return a default center region
        return [
            'x' => 0,
            'y' => 0,
            'width' => 100,
            'height' => 100
        ];
    }
    
    $width = imagesx($img);
    $height = imagesy($img);
    
    // Sample points in the image to find skin tones
    $samplePoints = [
        ['x' => $width * 0.25, 'y' => $height * 0.25],
        ['x' => $width * 0.5, 'y' => $height * 0.25],
        ['x' => $width * 0.75, 'y' => $height * 0.25],
        ['x' => $width * 0.4, 'y' => $height * 0.4],
        ['x' => $width * 0.6, 'y' => $height * 0.4],
    ];
    
    $skinPoints = [];
    foreach ($samplePoints as $point) {
        $rgb = imagecolorat($img, $point['x'], $point['y']);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        
        // Simple skin tone detection (adjust these values based on your needs)
        if ($r > 95 && $g > 40 && $b > 20 && 
            ($r - $g > 15) && ($r > $g) && ($r > $b)) {
            $skinPoints[] = $point;
        }
    }
    
    imagedestroy($img);
    
    // If we found skin tones, use the average position
    if (count($skinPoints) > 2) {
        $avgX = array_sum(array_column($skinPoints, 'x')) / count($skinPoints);
        $avgY = array_sum(array_column($skinPoints, 'y')) / count($skinPoints);
        
        // Return a region around the detected face
        $faceSize = min($width, $height) * 0.4; // 40% of the smaller dimension
        return [
            'x' => max(0, $avgX - $faceSize/2),
            'y' => max(0, $avgY - $faceSize/2),
            'width' => min($width, $faceSize * 1.5),  // Slightly wider than tall
            'height' => min($height, $faceSize)
        ];
    }
    
    // Default: return center of image if no face/skin detected
    return [
        'x' => $width * 0.25,
        'y' => $height * 0.25,
        'width' => $width * 0.5,
        'height' => $height * 0.5
    ];
}
