<?php
/**
 * Batch ID Card Processing with Face Detection
 * 
 * This script processes multiple ID card images to extract ID numbers and detect faces.
 */

// Set error reporting for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Enable error logging to a file
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

// Function to log errors
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, '/tmp/idcrop_batch_errors.log');
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

// Handle "Download All" request for zipping all processed face images
if (isset($_GET['download']) && $_GET['download'] === '1') {
    try {
        // Collect all face image files (jpg/png) from the output directory
        $faceFiles = glob($outputDir . '/*.{jpg,jpeg,png}', GLOB_BRACE);

        if (empty($faceFiles)) {
            throw new Exception('No face images found to download.');
        }

        // Create temporary ZIP archive
        $zipFilename = 'faces_' . date('Ymd_His') . '.zip';
        $zipPath = sys_get_temp_dir() . '/' . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Could not create ZIP archive.');
        }

        foreach ($faceFiles as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        if (!file_exists($zipPath)) {
            throw new Exception('Failed to create ZIP file.');
        }

        // Output headers and the ZIP file for download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="faces.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);

        // Clean up temporary file
        @unlink($zipPath);
        exit;
    } catch (Exception $e) {
        // Log error and show a user-friendly message later in the page
        logError('Download All error: ' . $e->getMessage());
        $error = 'Download failed: ' . $e->getMessage();
    }
}

$results = [];
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['id_cards'])) {
    $files = $_FILES['id_cards'];
    $fileCount = count($files['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $results[] = [
                'filename' => $files['name'][$i],
                'success' => false,
                'message' => 'Upload error: ' . $files['error'][$i]
            ];
            continue;
        }
        
        $originalName = basename($files['name'][$i]);
        $tempPath = $files['tmp_name'][$i];
        $uploadPath = $uploadDir . '/' . uniqid('id_') . '_' . $originalName;
        
        if (move_uploaded_file($tempPath, $uploadPath)) {
            try {
                // Extract ID number
                $idNumber = extractIdNumber($uploadPath);
                
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
                    
                    $faceDetected = ($returnVar === 0 && file_exists($outputFile));
                    
                    $results[] = [
                        'filename' => $originalName,
                        'success' => true,
                        'id_number' => $idNumber,
                        'face_detected' => $faceDetected,
                        'output_file' => $faceDetected ? basename($outputFile) : null,
                        'message' => $faceDetected ? 'Successfully processed' : 'No face detected'
                    ];
                } else {
                    $results[] = [
                        'filename' => $originalName,
                        'success' => false,
                        'message' => 'Could not extract ID number'
                    ];
                }
            } catch (Exception $e) {
                $results[] = [
                    'filename' => $originalName,
                    'success' => false,
                    'message' => 'Processing error: ' . $e->getMessage()
                ];
                logError("Error processing $originalName: " . $e->getMessage());
            }
        } else {
            $results[] = [
                'filename' => $originalName,
                'success' => false,
                'message' => 'Failed to save uploaded file'
            ];
        }
    }
}

/**
 * Extract ID number from an image using Tesseract OCR
 * (Same as in test_face_id.php)
 */
function extractIdNumber($imagePath) {
    try {
        if (!file_exists($imagePath)) {
            throw new Exception("Image file does not exist: $imagePath");
        }
        
        $tempBase = tempnam(sys_get_temp_dir(), 'ocr_');
        $outputFile = $tempBase . '.txt';
        
        $command = sprintf(
            'tesseract %s %s -l eng --psm 6 -c preserve_interword_spaces=1 -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 2>&1',
            escapeshellarg($imagePath),
            escapeshellarg($tempBase)
        );
        
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception('Tesseract command failed: ' . implode("\n", $output));
        }
        
        if (!file_exists($outputFile)) {
            throw new Exception('OCR output file not found: ' . $outputFile);
        }
        
        $ocrText = trim(file_get_contents($outputFile));
        
        // Clean up
        @unlink($outputFile);
        @unlink($tempBase);
        
        // Try to find ID number patterns
        if (preg_match('/\b(\d{8})\b/', $ocrText, $matches)) {
            $last7digits = substr($matches[1], -7);
            return 'HS' . $last7digits;
        }
        
        if (preg_match('/\b(\d{7,})\b/', $ocrText, $matches)) {
            return 'HS' . substr($matches[1], -7);
        }
        
        // If no pattern matched, try to find the longest number sequence
        if (preg_match_all('/\d+/', $ocrText, $matches)) {
            $longest = '';
            foreach ($matches[0] as $match) {
                if (strlen($match) > strlen($longest)) {
                    $longest = $match;
                }
            }
            if (strlen($longest) >= 7) {
                return 'HS' . substr($longest, -7);
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        logError('Error in extractIdNumber: ' . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch ID Card Processor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .preview-image {
            max-width: 200px;
            max-height: 150px;
            margin: 10px 0;
        }
        .result-card {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <h1 class="mb-4">Batch ID Card Processor</h1>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Upload ID Cards</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post" enctype="multipart/form-data" id="uploadForm">
                            <div class="mb-3">
                                <label class="form-label">Select ID Card Images</label>
                                <div class="input-group">
                                    <!-- Hidden native file input -->
                                    <input type="file" class="d-none" id="idCards" name="id_cards[]" multiple accept="image/*" required>
                                    <!-- Custom button -->
                                    <label class="btn btn-outline-primary" for="idCards" id="idCardsButton">
                                        <i class="bi bi-upload"></i> Choose Files
                                    </label>
                                    <span class="form-control bg-white" id="idCardsFilename" style="pointer-events:none;">No files selected</span>
                                </div>
                                <div class="form-text">You can select multiple files (JPG, PNG, etc.)</div>
                            </div>
                            <button type="submit" class="btn btn-primary">Process ID Cards</button>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($results)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="d-inline">Processing Results</h5>
                                <a href="?download=1" class="btn btn-success btn-sm float-end">Download All Faces (ZIP)</a>
                        </div>
                        <div class="card-body">
                            <?php foreach ($results as $result): ?>
                                <div class="result-card <?php echo $result['success'] ? 'success' : 'error'; ?>">
                                    <h6><?php echo htmlspecialchars($result['filename']); ?></h6>
                                    <p class="mb-1">
                                        Status: 
                                        <?php if ($result['success']): ?>
                                            <span class="text-success">Success</span>
                                        <?php else: ?>
                                            <span class="text-danger">Failed</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <?php if ($result['success']): ?>
                                        <p class="mb-1">ID Number: <strong><?php echo htmlspecialchars($result['id_number']); ?></strong></p>
                                        <p class="mb-1">Face Detected: <?php echo $result['face_detected'] ? 'Yes' : 'No'; ?></p>
                                        
                                        <?php if ($result['face_detected']): ?>
                                            <div class="mt-2">
                                                <img src="output/<?php echo htmlspecialchars($result['output_file']); ?>" 
                                                     alt="Extracted Face" class="img-thumbnail preview-image">
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="mb-0">Error: <?php echo htmlspecialchars($result['message']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Icons for upload icon -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">

    <script>
        // Update filename preview when files are selected
        const fileInput = document.getElementById('idCards');
        const filenameDisplay = document.getElementById('idCardsFilename');

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length === 0) {
                filenameDisplay.textContent = 'No files selected';
            } else if (fileInput.files.length === 1) {
                filenameDisplay.textContent = fileInput.files[0].name;
            } else {
                filenameDisplay.textContent = `${fileInput.files.length} files selected`;
            }
        });

        // Simple form validation
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (fileInput.files.length === 0) {
                e.preventDefault();
                alert('Please select at least one file to upload.');
                return false;
            }
            return true;
        });
    </script>
</body>
</html>
