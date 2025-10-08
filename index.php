<?php
/**
 * ID Photo Cropper - Main Application
 * 
 * This script handles file uploads, image processing, and serves the web interface.
 */

// Set error reporting and display settings
ini_set('display_errors', 0); // Don't show errors to users in production
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set('UTC');

// Define log file path
$logFile = '/tmp/idcrop_debug.log';

// Define application constants
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_TOTAL_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/jpg' => 'jpg'
]);

// Initialize logging
function logMessage($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    error_log($logMessage, 3, $logFile);
}

// Custom exception handler
function handleException(Throwable $e) {
    $message = sprintf(
        'Uncaught Exception: %s in %s on line %d',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    
    logMessage($message, 'ERROR');
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request.'
    ]);
    
    exit(1);
}

// Set exception handler
set_exception_handler('handleException');

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
});

// Shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        handleException(new ErrorException(
            $error['message'], 0, $error['type'], $error['file'], $error['line']
        ));
    }
});

// Log request details
logMessage(str_repeat('=', 80));
logMessage("New Request: " . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']);
logMessage("Remote IP: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']));
logMessage("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));

// Log POST and FILES data for debugging
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logMessage("POST Data: " . print_r($_POST, true));
    
    if (!empty($_FILES)) {
        $filesInfo = [];
        foreach ($_FILES as $field => $file) {
            if (is_array($file['name'])) {
                // Handle multiple file uploads
                $fileCount = count($file['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    $filesInfo[] = [
                        'field' => $field,
                        'name' => $file['name'][$i],
                        'type' => $file['type'][$i],
                        'size' => $file['size'][$i],
                        'error' => $file['error'][$i]
                    ];
                }
            } else {
                // Handle single file upload
                $filesInfo[] = [
                    'field' => $field,
                    'name' => $file['name'],
                    'type' => $file['type'],
                    'size' => $file['size'],
                    'error' => $file['error']
                ];
            }
        }
        logMessage("FILES Data: " . print_r($filesInfo, true));
    }
}

// Start secure session
function startSecureSession() {
    // Set session cookie parameters
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $httponly = true;
    $samesite = 'Lax';
    
    if (PHP_VERSION_ID < 70300) {
        session_set_cookie_params(
            0,
            '/; samesite=' . $samesite,
            $_SERVER['HTTP_HOST'],
            $secure,
            $httponly
        );
    } else {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);
    }
    
    // Prevent session fixation
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Regenerate session ID every 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Initialize session with error handling
try {
    // Start secure session
    startSecureSession();
    
    // Initialize session variables if not set
    if (!isset($_SESSION['uploaded_files'])) {
        $_SESSION['uploaded_files'] = [];
    }
    
    // Set upload and output directories
    $uploadDir = __DIR__ . '/uploads/';
    $outputDir = __DIR__ . '/output/';
    
    // Create directories if they don't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    // Ensure directories are writable
    if (!is_writable($uploadDir) || !is_writable($outputDir)) {
        throw new Exception('Upload or output directory is not writable');
    }
    
} catch (Exception $e) {
    logMessage('Session initialization error: ' . $e->getMessage(), 'ERROR');
    // Continue execution but log the error
}

// Ensure directories exist and are writable
function ensureDirectory($dir, $permissions = 0775) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, $permissions, true)) {
            throw new Exception("Failed to create directory: $dir");
        }
        
        // Set permissions explicitly
        if (!chmod($dir, $permissions)) {
            logMessage("Warning: Failed to set permissions for directory: $dir", 'WARNING');
        }
    } elseif (!is_dir($dir)) {
        throw new Exception("Path exists but is not a directory: $dir");
    } elseif (!is_writable($dir)) {
        // Try to make it writable
        if (!chmod($dir, $permissions)) {
            throw new Exception("Directory is not writable and could not be made writable: $dir");
        }
    }
    
    return true;
}

// Create and verify required directories
$requiredDirs = [
    'uploads' => $uploadDir,
    'output' => $outputDir,
    'temp' => $uploadDir . 'temp/'
];

try {
    foreach ($requiredDirs as $name => $dir) {
        ensureDirectory($dir);
        logMessage("Verified directory: $name => $dir");
    }
} catch (Exception $e) {
    logMessage('Directory error: ' . $e->getMessage(), 'ERROR');
    // Continue execution but log the error
}

// Format bytes to human-readable format
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Get a user-friendly error message for upload errors
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}

// Clean up old temporary files (older than 24 hours by default)
function cleanupOldTempFiles($dir, $maxAge = 86400) {
    if (!is_dir($dir)) {
        return;
    }
    
    $now = time();
    $files = glob($dir . '*');
    
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file) > $maxAge)) {
            @unlink($file);
            logMessage("Cleaned up old file: $file");
        }
    }
}

// Run cleanup on the temp directory
cleanupOldTempFiles($uploadDir . 'temp/');

// Process an uploaded file
function processUploadedFile(array $file, string $uploadDir) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(getUploadErrorMessage($file['error']));
    }
    
    // Verify file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception(sprintf(
            'File size (%s) exceeds maximum allowed size of %s',
            formatBytes($file['size']),
            formatBytes(MAX_FILE_SIZE)
        ));
    }
    
    // Verify file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : mime_content_type($file['tmp_name']);
    if ($finfo) finfo_close($finfo);
    
    if (!in_array($mimeType, array_keys(ALLOWED_TYPES))) {
        throw new Exception(sprintf(
            'Invalid file type: %s. Allowed types: %s',
            $mimeType,
            implode(', ', array_keys(ALLOWED_TYPES))
        ));
    }
    
    // Generate a unique filename
    $extension = ALLOWED_TYPES[$mimeType];
    $filename = uniqid('img_', true) . '.' . $extension;
    $destination = rtrim($uploadDir, '/') . '/' . $filename;
    
    // Move the uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Set proper permissions
    chmod($destination, 0644);
    
    return [
        'original_name' => $file['name'],
        'saved_name' => $filename,
        'path' => $destination,
        'size' => $file['size'],
        'type' => $mimeType
    ];
}

// Handle file upload request
function handleFileUpload() {
    global $uploadDir;
    
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'message' => '',
        'files' => [],
        'errors' => [],
        'stats' => [
            'total' => 0,
            'uploaded' => 0,
            'failed' => 0,
            'skipped' => 0
        ]
    ];
    
    try {
        // Check if files were uploaded
        if (empty($_FILES['images'])) {
            throw new Exception('No files were uploaded');
        }
        
        $files = [];
        
        // Handle both single and multiple file uploads
        if (is_array($_FILES['images']['name'])) {
            // Multiple files
            $fileCount = count($_FILES['images']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                $files[] = [
                    'name' => $_FILES['images']['name'][$i],
                    'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i]
                ];
            }
        } else {
            // Single file
            $files[] = [
                'name' => $_FILES['images']['name'],
                'type' => $_FILES['images']['type'],
                'tmp_name' => $_FILES['images']['tmp_name'],
                'error' => $_FILES['images']['error'],
                'size' => $_FILES['images']['size']
            ];
        }
        
        $response['stats']['total'] = count($files);
        $uploadedFiles = [];
        
        // Process each file
        foreach ($files as $file) {
            try {
                $result = processUploadedFile($file, $uploadDir);
                $uploadedFiles[] = $result;
                $response['files'][] = [
                    'original_name' => $result['original_name'],
                    'saved_name' => $result['saved_name'],
                    'size' => $result['size'],
                    'type' => $result['type']
                ];
                $response['stats']['uploaded']++;
                logMessage("Successfully uploaded: {$result['original_name']} as {$result['saved_name']}");
            } catch (Exception $e) {
                $response['errors'][] = [
                    'file' => $file['name'],
                    'message' => $e->getMessage()
                ];
                $response['stats']['failed']++;
                logMessage("Upload failed for {$file['name']}: " . $e->getMessage(), 'ERROR');
            }
        }
        
        // Store uploaded files in session for further processing
        if (!empty($uploadedFiles)) {
            if (!isset($_SESSION['uploaded_files'])) {
                $_SESSION['uploaded_files'] = [];
            }
            
            foreach ($uploadedFiles as $file) {
                $_SESSION['uploaded_files'][] = [
                    'original_name' => $file['original_name'],
                    'saved_name' => $file['saved_name'],
                    'path' => $file['path'],
                    'size' => $file['size'],
                    'type' => $file['type']
                ];
            }
            
            $response['success'] = $response['stats']['failed'] === 0;
            $response['message'] = $response['success'] 
                ? 'All files were uploaded successfully' 
                : 'Some files could not be uploaded';
        } else {
            $response['message'] = 'No files were processed';
        }
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        logMessage('Upload error: ' . $e->getMessage(), 'ERROR');
    }
    
    echo json_encode($response);
    exit;
}

// Handle file upload only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    handleFileUpload();
}

// Process an uploaded image with face detection and cropping
function processImage($file, $uploadDir, $outputDir) {
    // This is a placeholder for the actual image processing logic
    // You would typically use a library like OpenCV or a service for face detection
    
    $result = [
        'success' => false,
        'message' => '',
        'output_path' => '',
        'original_path' => $file['tmp_name']
    ];
    
    try {
        // Generate output filename
        $filename = uniqid('processed_', true) . '.jpg';
        $outputPath = rtrim($outputDir, '/') . '/' . $filename;
        
        // For now, just copy the file as a placeholder
        if (copy($file['tmp_name'], $outputPath)) {
            $result['success'] = true;
            $result['message'] = 'Image processed successfully';
            $result['output_path'] = $outputPath;
        } else {
            throw new Exception('Failed to save processed image');
        }
    } catch (Exception $e) {
        $result['message'] = $e->getMessage();
        logMessage('Image processing error: ' . $e->getMessage(), 'ERROR');
    }
    
    return $result;
}

// Handle processing of uploaded files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_uploaded_files') {
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'message' => '',
        'download_url' => '',
        'errors' => []
    ];
    
    try {
        if (empty($_SESSION['uploaded_files'])) {
            throw new Exception('No files available for processing');
        }
        
        $uploadedFiles = $_SESSION['uploaded_files'];
        $zip = new ZipArchive();
        $zipName = 'processed_' . time() . '.zip';
        $zipPath = $outputDir . $zipName;
        
        logMessage("Starting processing of " . count($uploadedFiles) . " files");
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception('Failed to create ZIP file');
        }
        
        $processedCount = 0;
        $totalFiles = count($uploadedFiles);
        $errors = [];
        
        foreach ($uploadedFiles as $file) {
            try {
                $result = processImage([
                    'name' => $file['original_name'],
                    'type' => $file['type'],
                    'tmp_name' => $file['path'],
                    'error' => 0,
                    'size' => $file['size']
                ], $uploadDir, $outputDir);
                
                if ($result['success']) {
                    if ($zip->addFile($result['output_path'], basename($result['output_path']))) {
                        $processedCount++;
                        logMessage("Added to ZIP: " . $file['original_name']);
                    } else {
                        $errors[] = 'Failed to add ' . $file['original_name'] . ' to ZIP';
                        logMessage("Failed to add to ZIP: " . $file['original_name'], 'ERROR');
                    }
                } else {
                    $errors[] = 'Failed to process ' . $file['original_name'] . ': ' . ($result['message'] ?? 'Unknown error');
                    logMessage("Processing failed: " . $file['original_name'] . ": " . ($result['message'] ?? 'Unknown error'), 'ERROR');
                }
            } catch (Exception $e) {
                $errors[] = 'Error processing ' . $file['original_name'] . ': ' . $e->getMessage();
                logMessage("Exception processing " . $file['original_name'] . ": " . $e->getMessage(), 'ERROR');
            }
        }
        
        // Close the ZIP file
        $zip->close();
        
        // Set appropriate permissions
        chmod($zipPath, 0644);
        
        if ($processedCount > 0) {
            $response['success'] = true;
            $response['message'] = "Successfully processed $processedCount of $totalFiles files";
            $response['download_url'] = '/output/' . $zipName;
            
            if (!empty($errors)) {
                $response['message'] .= ' (' . count($errors) . ' errors)';
                $response['errors'] = $errors;
            }
        } else {
            throw new Exception('No files were successfully processed');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        logMessage('Processing error: ' . $e->getMessage(), 'ERROR');
    }
    
    echo json_encode($response);
    exit;
}

// Handle direct file uploads (not using the form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES) && (!isset($_POST['action']) || $_POST['action'] !== 'process_uploaded_files')) {
    $response = [
        'success' => false,
        'message' => 'Please use the file upload form',
        'errors' => []
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>ID Photo Cropper</title>
    
    <!-- Preconnect to CDNs for better performance -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- Bootstrap 5.3.2 CSS with SRI -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" 
          rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" 
          crossorigin="anonymous">
    
    <!-- Font Awesome 6.4.2 with SRI -->
    <link rel="stylesheet" 
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" 
          integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" 
          crossorigin="anonymous" 
          referrerpolicy="no-referrer" />
          
    <!-- Google Fonts for better typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 2rem;
        }
        .upload-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .drop-zone {
            border: 2px dashed #ced4da;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            margin-bottom: 1rem;
        }
        .drop-zone:hover {
            border-color: #0d6efd;
            background-color: #f1f8ff;
        }
        .drop-zone.dragover {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .file-list {
            margin-top: 1.5rem;
        }
        .file-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }
        .file-item .file-icon {
            margin-right: 1rem;
            color: #6c757d;
        }
        .file-item .file-info {
            flex-grow: 1;
        }
        .file-item .file-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .file-item .file-size {
            font-size: 0.8rem;
            color: #6c757d;
        }
        /* Preview Container Styles */
        #previewContainer {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            margin: 20px 0;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.3s ease-out;
            position: relative;
            z-index: 1000;
        }
        
        #previewImages {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            padding: 10px;
        }
        
        #previewContainer .card {
            border: none;
            box-shadow: none;
            background: transparent;
            width: 100%;
        }
        
        #previewContainer .card-body {
            padding: 0;
        }
        
        .preview-container .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.125);
            font-weight: 600;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 70vh;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            border: 2px solid #007bff;
            border-radius: 6px;
            background-color: #fff;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .img-preview-container {
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 1rem 0;
            padding: 2rem;
            border: 2px dashed #dee2e6;
            transition: all 0.3s ease;
        }
        
        .img-preview-container:hover {
            border-color: #adb5bd;
            background-color: #f1f3f5;
        }
        
        .file-item .file-remove {
            color: #dc3545;
            cursor: pointer;
            margin-left: 1rem;
        }
        .progress {
            height: 0.5rem;
            margin-top: 0.5rem;
        }
        
        /* Animation for preview */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Preview container styles */
        .preview-container {
            display: none;
            margin: 2rem 0;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.3s ease-out;
        }
        
        #previewContainer.show {
            display: block !important;
        }
        
        .preview-image {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn-upload {
            margin-top: 1rem;
        }
        
        .preview-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .processing-indicator {
            display: none;
            text-align: center;
            margin: 1rem 0;
        .processing-spinner {
            width: 2rem;
            height: 2rem;
            margin: 0 auto 0.5rem;
            border: 0.25rem solid #f3f3f3;
            border-top: 0.25rem solid #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .alert {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="upload-container">
            <h1 class="text-center mb-4">ID Photo Cropper</h1>
            <p class="text-center text-muted mb-4">Upload your photos and we'll automatically crop them to ID photo specifications</p>
            
            <div class="drop-zone" id="dropZone">
                <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #0d6efd;"></i>
                <h4>Drag & drop your photos here</h4>
                <p class="text-muted">or click to browse files</p>
                <input type="file" id="fileInput" class="d-none" multiple accept="image/*">
                <button class="btn btn-primary btn-upload" id="browseBtn">
                    <i class="fas fa-folder-open me-2"></i>Browse Files
                </button>
            </div>
            
            <div class="file-list" id="fileList">
                <!-- Files will be listed here -->
            </div>
            
            <div class="processing-indicator" id="processingIndicator">
                <div class="processing-spinner"></div>
                <p>Processing your photos...</p>
            </div>
            
            <div class="d-grid gap-2">
                <button class="btn btn-success btn-lg" id="processBtn" disabled>
                    <i class="fas fa-magic me-2"></i>Process Photos
                </button>
            </div>
            
            <div class="alert alert-info mt-3" id="infoAlert" style="display: none;">
                <i class="fas fa-info-circle me-2"></i>
                <span id="infoMessage"></span>
            </div>
            
            <div class="alert alert-danger mt-3" id="errorAlert" style="display: none;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span id="errorMessage"></span>
            </div>
            
            <div class="preview-container" id="previewContainer" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Preview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row" id="previewImages">
                            <!-- Preview images will be added here -->
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <a href="#" class="btn btn-primary" id="downloadBtn" style="display: none;">
                            <i class="fas fa-download me-2"></i>Download All
                        </a>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5.3.2 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" 
            crossorigin="anonymous"></script>
    
    <!-- Add a loading state to prevent FOUC (Flash of Unstyled Content) -->
    <style>
        /* Hide everything until the page is fully loaded */
        body:not(.loaded) {
            opacity: 0;
            transition: opacity 0.3s ease-in;
        }
        /* Show loading indicator */
        .loading-indicator {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }
    </style>
    
    <!-- Add loading indicator -->
    <div class="loading-indicator">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
    <!-- Initialize app after DOM is loaded -->
    <script>
        // Mark page as loaded when everything is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Add loaded class to body
            document.body.classList.add('loaded');
            
            // Remove loading indicator after a short delay
            const loadingIndicator = document.querySelector('.loading-indicator');
            if (loadingIndicator) {
                setTimeout(() => {
                    loadingIndicator.style.display = 'none';
                }, 300);
            }
            
            // Initialize app after a small delay to ensure all resources are loaded
            setTimeout(initializeApp, 100);
        });
        
        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error || e.message, e.filename, 'line:', e.lineno);
            // Show error to user
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');
            if (errorAlert && errorMessage) {
                errorMessage.textContent = 'An error occurred. Please try again.';
                errorAlert.classList.remove('d-none');
            }
            // Hide loading indicator if still visible
            const loadingIndicator = document.querySelector('.loading-indicator');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
        });
    </script>
    
    <!-- Load custom JS -->
    <script src="assets/app.js" defer></script>
</body>
</html>
