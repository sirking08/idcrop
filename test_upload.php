<?php
// Display all errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Test file upload settings
echo "<h2>File Upload Test</h2>";
echo "<form action='' method='post' enctype='multipart/form-data'>";
echo "<input type='file' name='testfile'><br>";
echo "<input type='submit' value='Upload Test'>";
echo "</form>";

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['testfile'])) {
    echo "<h3>Upload Results:</h3>";
    echo "<pre>";
    echo "File Info:\n";
    print_r($_FILES['testfile']);
    echo "\n\nPOST Data:\n";
    print_r($_POST);
    echo "\n\nServer Info:\n";
    echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
    echo "post_max_size: " . ini_get('post_max_size') . "\n";
    echo "upload_tmp_dir: " . ini_get('upload_tmp_dir') . "\n";
    echo "Directory Permissions:\n";
    echo "Uploads dir exists: " . (is_dir(__DIR__ . '/uploads/') ? 'Yes' : 'No') . "\n";
    echo "Uploads dir writable: " . (is_writable(__DIR__ . '/uploads/') ? 'Yes' : 'No') . "\n";
    echo "</pre>";
    
    // Try to move the uploaded file
    $target = __DIR__ . '/uploads/test_' . basename($_FILES['testfile']['name']);
    if (move_uploaded_file($_FILES['testfile']['tmp_name'], $target)) {
        echo "<p style='color:green'>File uploaded successfully to: $target</p>";
    } else {
        echo "<p style='color:red'>Failed to move uploaded file. Error: " . error_get_last()['message'] . "</p>";
    }
}