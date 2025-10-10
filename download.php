<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the directory where processed files are stored
$baseDir = __DIR__ . '/output/';

// Check if file parameter is set
if (!isset($_GET['file'])) {
    die('No file specified');
}

// Sanitize the filename to prevent directory traversal
$filename = basename($_GET['file']);
$filepath = $baseDir . $filename;

// Check if file exists and is a ZIP file
if (!file_exists($filepath) || !preg_match('/\.zip$/i', $filename)) {
    http_response_code(404);
    die('File not found or invalid file type');
}

// Set headers for file download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Pragma: no-cache');
header('Expires: 0');

// Clear output buffer and send file
ob_clean();
flush();
readfile($filepath);

// Optionally delete the file after download
// unlink($filepath);

exit;
