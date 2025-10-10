<?php
/**
 * Test Page: Face Detection with ID Number Extraction
 * 
 * This script demonstrates face detection and ID number extraction from ID cards.
 */

// Set error reporting for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Enable error logging to a file
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

// Function to log errors
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, '/tmp/idcrop_errors.log');
}

// Set exception handler
set_exception_handler(function($e) {
    $error = 'Uncaught Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
    logError($error);
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'An error occurred. Please check the error log.';
    } else {
        echo '\n<!-- Error: ' . htmlspecialchars($error) . ' -->';
    }
    exit(1);
});

// Include required files
require_once __DIR__ . '/includes/image_processor.php';

// Define directories
$uploadDir = __DIR__ . '/uploads';
$outputDir = __DIR__ . '/output';

// Create directories if they don't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$message = '';
$outputFile = '';
$idNumber = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['id_card'])) {
    $file = $_FILES['id_card'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Error uploading file. Error code: ' . $file['error'];
    } else {
        // Generate unique filename
        $filename = uniqid('id_') . '_' . basename($file['name']);
        $uploadPath = $uploadDir . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            try {
                // Step 1: Extract ID number using OCR
                $idNumber = extractIdNumber($uploadPath);
                
                // Step 2: Detect and crop face
                if ($idNumber) {
                    $outputFile = $outputDir . '/' . preg_replace('/[^a-zA-Z0-9]/', '_', $idNumber) . '.jpg';
                    
                    // Call the face detection script
                    $command = sprintf(
                        'python3 %s %s %s 2>&1',
                        escapeshellarg(__DIR__ . '/detect_face.py'),
                        escapeshellarg($uploadPath),
                        escapeshellarg($outputFile)
                    );
                    
                    $output = [];
                    $returnVar = 0;
                    exec($command, $output, $returnVar);
                    
                    if ($returnVar !== 0 || !file_exists($outputFile)) {
                        throw new Exception('Face detection failed: ' . implode("\n", $output));
                    }
                    
                    $message = 'ID number extracted and face cropped successfully!';
                } else {
                    $message = 'Could not extract ID number from the image. Please try with a clearer image.';
                }
            } catch (Exception $e) {
                $message = 'Error processing image: ' . $e->getMessage();
                error_log($message);
            }
        } else {
            $message = 'Failed to move uploaded file.';
        }
    }
}

/**
 * Extract ID number from an image using Tesseract OCR
 * 
 * @param string $imagePath Path to the image file
 * @return string|false Extracted ID number or false if not found
 */
function extractIdNumber($imagePath) {
    try {
        if (!file_exists($imagePath)) {
            throw new Exception("Image file does not exist: $imagePath");
        }
        
        // Create a temporary file for the output
        $tempBase = tempnam(sys_get_temp_dir(), 'ocr_');
        $outputFile = $tempBase . '.txt';
        
        // Enhanced Tesseract command with better configuration
        $command = sprintf(
            'tesseract %s %s -l eng --psm 6 -c preserve_interword_spaces=1 -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 2>&1',
            escapeshellarg($imagePath),
            escapeshellarg($tempBase) // Tesseract will add .txt extension
        );
        
        // Execute the command
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception('Tesseract command failed: ' . implode("\n", $output));
        }
        
        // Read the OCR result
        if (!file_exists($outputFile)) {
            throw new Exception('OCR output file not found: ' . $outputFile);
        }
        
        $ocrText = trim(file_get_contents($outputFile));
        
        // For debugging - log the raw OCR text
        error_log("Raw OCR text: " . $ocrText);
        
        // Clean up
        @unlink($outputFile);
        @unlink($tempBase);
        
        // First, try to find the ID number pattern that matches the image format
        if (preg_match('/\b(\d{8})\b/', $ocrText, $matches)) {
            $fullNumber = $matches[1];
            // Take the last 7 digits of the 8-digit number
            $last7digits = substr($fullNumber, -7);
            $id = 'HS' . $last7digits;
            error_log("Extracted 8-digit number: $fullNumber, Using last 7 digits: $last7digits, Generated ID: $id");
            return $id;
        }
        
        // If no 8-digit number found, look for any 7+ digit number
        if (preg_match('/\b(\d{7,})\b/', $ocrText, $matches)) {
            $number = $matches[1];
            $last7digits = substr($number, -7);
            $id = 'HS' . $last7digits;
            error_log("Found number: $number, Using last 7 digits: $last7digits, Generated ID: $id");
            return $id;
        }
        
        // If still no match, try to find the longest number sequence
        if (preg_match_all('/\d+/', $ocrText, $matches)) {
            $longest = '';
            foreach ($matches[0] as $match) {
                if (strlen($match) > strlen($longest)) {
                    $longest = $match;
                }
            }
            if (strlen($longest) >= 7) {
                $last7digits = substr($longest, -7);
                $id = 'HS' . $last7digits;
                error_log("Using longest number sequence: $longest, Last 7 digits: $last7digits, Generated ID: $id");
                return $id;
            }
        }
        
        error_log("No suitable number sequence found in OCR text");
        
        // If we got here, no pattern matched
        throw new Exception('No valid ID number found in the image. OCR text: ' . substr($ocrText, 0, 100) . '...');
    } catch (Exception $e) {
        $errorMsg = 'Error in extractIdNumber: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        error_log($errorMsg);
        
        // If we have OCR text, include it in the error message for debugging
        if (isset($ocrText)) {
            $errorMsg .= '\nOCR Output: ' . substr($ocrText, 0, 200) . (strlen($ocrText) > 200 ? '...' : '');
        }
        
        throw new Exception('Error extracting ID number: ' . $errorMsg, $e->getCode(), $e);
    }
}
?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card Face Detection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .preview { max-width: 100%; margin: 20px 0; }
        .result { margin-top: 20px; padding: 20px; background-color: #f8f9fa; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">ID Card Face Detection</h1>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Upload ID Card Image</h5>
                <p class="text-muted">Upload a clear photo of an ID card to detect the face and extract the ID number.</p>
                
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <input type="file" class="form-control" name="id_card" accept="image/*" required>
                        <div class="form-text">Supported formats: JPG, PNG</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Process</button>
                </form>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?= strpos($message, 'successfully') !== false ? 'success' : 'warning' ?> mt-3">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($idNumber): ?>
                    <div class="result">
                        <h6>Extracted ID Number:</h6>
                        <p class="h4"><?= htmlspecialchars($idNumber) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($outputFile && file_exists($outputFile)): ?>
                    <div class="result mt-3">
                        <h6>Cropped Face:</h6>
                        <img src="/idcrop/output/<?= basename($outputFile) ?>?t=<?= time() ?>" class="img-fluid rounded preview">
                        <div class="mt-2">
                            <a href="/idcrop/output/<?= basename($outputFile) ?>" class="btn btn-sm btn-outline-primary" download>
                                Download Image
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
