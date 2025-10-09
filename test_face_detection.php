<?php
/**
 * Test Page for Face Detection
 * 
 * This page provides an interface to test the face detection and cropping functionality.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$uploadDir = __DIR__ . '/uploads/test/';
$outputDir = __DIR__ . '/output/test/';

// Create directories if they don't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$message = '';
$originalImage = '';
$processedImage = '';
$detectionTime = 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $uploadedFile = $_FILES['image'];
    $filename = uniqid('face_test_') . '_' . basename($uploadedFile['name']);
    $uploadPath = $uploadDir . $filename;
    $outputPath = $outputDir . 'cropped_' . $filename;
    
    if (move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
        $startTime = microtime(true);
        
        // Execute the Python script with full path to python3
        $pythonPath = trim(shell_exec('which python3'));
        $command = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg($pythonPath),
            escapeshellarg(__DIR__ . '/detect_face.py'),
            escapeshellarg($uploadPath),
            escapeshellarg($outputPath)
        );
        
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        $detectionTime = round((microtime(true) - $startTime) * 1000, 2); // in milliseconds
        
        // Debug output
        error_log("Command: " . $command);
        error_log("Return code: " . $returnVar);
        error_log("Output: " . implode("\n", $output));
        
        // Check if output file was created
        if (!file_exists($outputPath)) {
            $message = 'Output file was not created. ';
            $message .= 'Check server error logs for details. ';
            $message .= 'Command output: ' . implode("\n", $output);
        }
        
        if ($returnVar === 0 && file_exists($outputPath)) {
            $message = 'Face detected and cropped successfully! Time taken: ' . $detectionTime . 'ms';
            $originalImage = 'uploads/test/' . $filename;
            $processedImage = 'output/test/cropped_' . $filename;
        } else {
            $message = 'Error processing image: ' . implode("\n", $output);
            // Clean up the uploaded file if processing failed
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }
        }
    } else {
        $message = 'Failed to upload file. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Detection Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 2rem 0;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
        }
        .image-container {
            margin: 2rem 0;
            text-align: center;
        }
        .image-preview {
            max-width: 100%;
            max-height: 400px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .result-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .alert {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Face Detection Test</h1>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Upload an Image</h5>
                <p class="text-muted">Upload a photo containing a face to test the face detection and cropping functionality.</p>
                
                <form method="POST" enctype="multipart/form-data" class="mb-4">
                    <div class="mb-3">
                        <input type="file" class="form-control" name="image" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Detect Face</button>
                    <a href="index.php" class="btn btn-outline-secondary">Back to Main App</a>
                </form>
                
                <?php if ($message): ?>
                    <div class="alert <?php echo strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($originalImage || $processedImage): ?>
        <div class="result-section">
            <h4>Results</h4>
            <div class="row">
                <div class="col-md-6">
                    <h6>Original Image</h6>
                    <img src="<?php echo htmlspecialchars($originalImage); ?>" alt="Original" class="img-fluid image-preview">
                </div>
                <div class="col-md-6">
                    <h6>Processed Image</h6>
                    <img src="<?php echo htmlspecialchars($processedImage); ?>" alt="Processed" class="img-fluid image-preview">
                </div>
            </div>
            <?php if ($detectionTime): ?>
                <div class="mt-3 text-muted">
                    Processing time: <?php echo $detectionTime; ?>ms
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="mt-4 text-center text-muted">
            <small>Face detection powered by OpenCV and Haar Cascade Classifier</small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
