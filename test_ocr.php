<?php
/**
 * Test script for OCR functionality with file upload
 */

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$uploadDir = __DIR__ . '/uploads/';
$outputDir = __DIR__ . '/output/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

// Create directories if they don't exist
foreach ([$uploadDir, $outputDir] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Process form submission
$message = '';
$outputImage = '';
$extractedText = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['image'])) {
    $file = $_FILES['image'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Error uploading file. Error code: ' . $file['error'];
    } elseif (!in_array($file['type'], $allowedTypes)) {
        $message = 'Invalid file type. Please upload a JPEG, PNG, or GIF image.';
    } else {
        // Generate unique filename
        $filename = uniqid() . '_' . basename($file['name']);
        $uploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Prepare command - use the virtual environment's Python
            $pythonPath = __DIR__ . '/venv/bin/python3';
            $pythonScript = __DIR__ . '/detect_face.py';
            $command = sprintf(
                '%s %s %s %s --use-id 2>&1',
                escapeshellarg($pythonPath),
                escapeshellarg($pythonScript),
                escapeshellarg($uploadPath),
                escapeshellarg($outputDir)
            );
            
            // Log the command for debugging
            error_log("Executing command: $command");
            
            // Execute the command with error handling
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            // Log the output for debugging
            error_log("Command output: " . implode("\n", $output));
            error_log("Return code: $returnVar");
            
            // Check if Python is accessible
            if (!file_exists($pythonPath) || !is_executable($pythonPath)) {
                $message = "Python interpreter not found or not executable at: $pythonPath";
                error_log($message);
            } elseif (!file_exists($pythonScript)) {
                $message = "Python script not found at: $pythonScript";
                error_log($message);
            }
            
            // Process output
            foreach ($output as $line) {
                if (strpos($line, 'Extracted ID:') !== false) {
                    $extractedText = trim(explode('Extracted ID:', $line)[1]);
                }
                if (strpos($line, 'saved to:') !== false) {
                    $outputImage = trim(explode('saved to:', $line)[1]);
                }
            }
            
            if ($returnVar !== 0) {
                $message = 'Error processing image: ' . implode("\n", $output);
            } elseif (empty($outputImage) || !file_exists($outputImage)) {
                $message = 'No face detected or error saving output.';
                if (!empty($output)) {
                    $message .= ' ' . implode("\n", $output);
                }
            }
        } else {
            $message = 'Failed to save uploaded file.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Photo Cropper - OCR Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .upload-container {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .preview {
            margin-top: 20px;
            text-align: center;
        }
        .preview img {
            max-width: 100%;
            max-height: 400px;
            border: 1px solid #ddd;
            margin-top: 10px;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h1>ID Photo Cropper - OCR Test</h1>
    
    <div class="upload-container">
        <h2>Upload an ID Photo</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="image" id="image" accept="image/*" required>
            <button type="submit" class="btn">Process Image</button>
        </form>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo strpos(strtolower($message), 'error') !== false ? 'error' : 'success'; ?>">
            <?php echo nl2br(htmlspecialchars($message)); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($extractedText)): ?>
        <div class="result">
            <h3>Extracted ID:</h3>
            <p><?php echo htmlspecialchars($extractedText); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($outputImage) && file_exists($outputImage)): ?>
        <div class="preview">
            <h3>Processed Image:</h3>
            <img src="<?php echo str_replace(__DIR__, '', $outputImage); ?>" alt="Processed ID Photo">
            <p>
                <a href="<?php echo str_replace(__DIR__, '', $outputImage); ?>" download class="btn">Download Image</a>
            </p>
        </div>
    <?php endif; ?>
</body>
</html>
