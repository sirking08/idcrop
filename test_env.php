<?php
/**
 * Test script to check Python environment and Tesseract integration
 */

// Configuration
$pythonPath = __DIR__ . '/venv/bin/python3';
$pythonScript = __DIR__ . '/detect_face.py';
$testImage = __DIR__ . '/test_ocr_image.jpg';

// Check if Python is accessible
function checkPython($path) {
    if (!file_exists($path)) {
        return [false, "Python not found at: $path"];
    }
    if (!is_executable($path)) {
        return [false, "Python is not executable at: $path"];
    }
    
    // Get Python version
    $output = [];
    $returnVar = 0;
    exec(escapeshellarg($path) . ' --version 2>&1', $output, $returnVar);
    
    if ($returnVar !== 0) {
        return [false, "Failed to execute Python. Error: " . implode("\n", $output)];
    }
    
    return [true, "Python found: " . implode("\n", $output)];
}

// Check if Python script exists
function checkPythonScript($path) {
    if (!file_exists($path)) {
        return [false, "Python script not found at: $path"];
    }
    
    if (!is_readable($path)) {
        return [false, "Python script is not readable: $path"];
    }
    
    return [true, "Python script found and readable: $path"];
}

// Check if test image exists
function checkTestImage($path) {
    if (!file_exists($path)) {
        return [false, "Test image not found at: $path"];
    }
    
    if (!is_readable($path)) {
        return [false, "Test image is not readable: $path"];
    }
    
    return [true, "Test image found and readable: $path"];
}

// Check Tesseract
function checkTesseract() {
    $output = [];
    $returnVar = 0;
    exec('which tesseract 2>&1', $output, $returnVar);
    
    if ($returnVar !== 0) {
        return [false, "Tesseract not found. Please install it with: sudo apt-get install tesseract-ocr"];
    }
    
    $tesseractPath = trim(implode("\n", $output));
    return [true, "Tesseract found at: $tesseractPath"];
}

// Run tests
$tests = [
    'Python' => checkPython($pythonPath),
    'Python Script' => checkPythonScript($pythonScript),
    'Test Image' => checkTestImage($testImage),
    'Tesseract' => checkTesseract()
];

// Display results
header('Content-Type: text/plain');
echo "Environment Test Results\n";
echo "====================\n\n";

$allPassed = true;
foreach ($tests as $name => $test) {
    list($success, $message) = $test;
    $status = $success ? '✓' : '✗';
    echo "[$status] $name: $message\n\n";
    
    if (!$success) {
        $allPassed = false;
    }
}

// Run a test command if all checks passed
if ($allPassed) {
    echo "\nRunning a test command...\n";
    $outputDir = __DIR__ . '/output';
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $command = sprintf(
        '%s %s %s %s --use-id 2>&1',
        escapeshellarg($pythonPath),
        escapeshellarg($pythonScript),
        escapeshellarg($testImage),
        escapeshellarg($outputDir)
    );
    
    echo "Command: $command\n\n";
    
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    echo "Output:\n" . implode("\n", $output) . "\n\n";
    echo "Return code: $returnVar\n";
    
    if ($returnVar === 0) {
        echo "\n✅ Test completed successfully!\n";
    } else {
        echo "\n❌ Test failed. Please check the output above for details.\n";
    }
} else {
    echo "\n❌ Some tests failed. Please fix the issues above before proceeding.\n";
}
