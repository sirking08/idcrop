<?php
/**
 * Image Processor for ID Photo Cropper
 * 
 * This file contains the image processing functionality including face detection
 * and cropping operations.
 */

// No need to import global functions in PHP

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
/**
 * Process an image with face detection and cropping
 * 
 * @param string $sourcePath Path to the source image file
 * @param string $outputDir Directory where processed files should be saved
 * @return array Result array with success status and output path
 */
function processImageWithFaceDetection($sourcePath, $outputDir) {
    $result = [
        'success' => false,
        'message' => '',
        'output_path' => ''
    ];
    
    if (!file_exists($sourcePath)) {
        $result['message'] = 'Source file does not exist: ' . $sourcePath;
        error_log($result['message']);
        return $result;
    }
    
    // Generate output filename with a unique ID to avoid conflicts
    $filename = 'cropped_' . uniqid() . '_' . basename($sourcePath);
    $outputPath = rtrim($outputDir, '/') . '/' . $filename;
    
    try {
        // Ensure output directory exists and is writable
        if (!file_exists($outputDir)) {
            if (!mkdir($outputDir, 0777, true)) {
                throw new \Exception('Failed to create output directory: ' . $outputDir);
            }
        } elseif (!is_writable($outputDir)) {
            throw new \Exception('Output directory is not writable: ' . $outputDir);
        }
        
        // Get the Python path and verify the script exists
        $pythonPath = trim(shell_exec('which python3') ?: '');
        if (empty($pythonPath)) {
            throw new \Exception('Python 3 is not installed or not in PATH');
        }
        
        $scriptPath = __DIR__ . '/../detect_face.py';
        if (!file_exists($scriptPath)) {
            throw new \Exception('Face detection script not found at: ' . $scriptPath);
        }
        
        // Build the command to run the Python script
        $command = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg($pythonPath),
            escapeshellarg($scriptPath),
            escapeshellarg($sourcePath),
            escapeshellarg($outputPath)
        );
        
        // Log the command for debugging
        error_log('Executing face detection command: ' . $command);
        
        // Execute the command
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        // Log the output for debugging
        error_log('Face detection output: ' . implode("\n", $output));
        
        // Check if the output file was created
        if (!file_exists($outputPath)) {
            // Try to get the actual output path from the Python script's output
            foreach ($output as $line) {
                if (strpos($line, 'saved to:') !== false) {
                    $parts = explode('saved to:', $line);
                    if (isset($parts[1])) {
                        $possiblePath = trim($parts[1]);
                        if (file_exists($possiblePath)) {
                            $outputPath = $possiblePath;
                            break;
                        }
                    }
                }
            }
            
            // If still no file, throw an error
            if (!file_exists($outputPath)) {
                $error = 'Face detection failed. Output file was not created. ';
                $error .= 'Return code: ' . $returnVar . '. ';
                $error .= 'Output: ' . implode("\n", $output);
                throw new \Exception($error);
            }
        }
        
        // Verify the output file is not empty
        if (filesize($outputPath) === 0) {
            @unlink($outputPath);
            throw new \Exception('Face detection failed: Output file is empty');
        }
        
        // Set the result
        $result['success'] = true;
        $result['output_path'] = $outputPath;
        $result['message'] = 'Image processed successfully with face detection';
        
        return $result;
        
    } catch (\Exception $e) {
        // Clean up any created files
        if (isset($outputPath) && file_exists($outputPath)) {
            @unlink($outputPath);
        }
        
        $result['message'] = $e->getMessage();
        error_log('Face detection processing error: ' . $e->getMessage());
        
        // Fallback to the original image if face detection fails
        if (file_exists($sourcePath)) {
            $result['output_path'] = $sourcePath;
            $result['message'] = 'Using original image (face detection failed: ' . $e->getMessage() . ')';
            error_log('Falling back to original image: ' . $sourcePath);
        }
        
        return $result;
    }
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
        'original_path' => ''
    ];
    
    try {
        // Create directories if they don't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new \Exception('Failed to create upload directory');
            }
        }
        if (!file_exists($outputDir)) {
            if (!mkdir($outputDir, 0777, true)) {
                throw new \Exception('Failed to create output directory');
            }
        }
        
        // Generate filenames
        $filename = uniqid('processed_') . '_' . basename($file['name']);
        $uploadPath = rtrim($uploadDir, '/') . '/' . $filename;
        $outputPath = rtrim($outputDir, '/') . '/cropped_' . $filename;
        $result['original_path'] = $uploadPath;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new \Exception('Failed to save uploaded file');
        }
        
        // Execute the Python script
        $pythonPath = trim(shell_exec('which python3'));
        if (empty($pythonPath)) {
            throw new \Exception('Python 3 is not installed or not in PATH');
        }
        
        $scriptPath = __DIR__ . '/../detect_face.py';
        if (!file_exists($scriptPath)) {
            throw new \Exception('Face detection script not found');
        }
        
        $command = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg($pythonPath),
            escapeshellarg($scriptPath),
            escapeshellarg($uploadPath),
            escapeshellarg($outputPath)
        );
        
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        // Log the command and output for debugging
        error_log("Face detection command: " . $command);
        error_log("Return code: " . $returnVar);
        error_log("Output: " . implode("\n", $output));
        
        if ($returnVar !== 0 || !file_exists($outputPath)) {
            $error = 'Face detection failed. ';
            $error .= 'Return code: ' . $returnVar . '. ';
            $error .= 'Output: ' . implode("\n", $output);
            throw new \Exception($error);
        }
        
        $result['success'] = true;
        $result['output_path'] = $outputPath;
        $result['message'] = 'Image processed successfully';
        
        return $result;
        
    } catch (\Exception $e) {
        // Clean up any created files
        if (isset($uploadPath) && file_exists($uploadPath)) {
            @unlink($uploadPath);
        }
        if (isset($outputPath) && file_exists($outputPath)) {
            @unlink($outputPath);
        }
        
        $result['message'] = $e->getMessage();
        error_log('Image processing error: ' . $e->getMessage());
        return $result;
    }
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
