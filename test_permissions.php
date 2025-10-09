<?php
// Test script to check permissions and Python environment
header('Content-Type: text/plain');

echo "=== Environment Test ===\n";

// Check Python version
$pythonPath = trim(shell_exec('which python3'));
echo "Python path: $pythonPath\n";
$pythonVersion = shell_exec('python3 --version 2>&1');
echo "Python version: $pythonVersion";

// Check OpenCV installation
$cvCheck = shell_exec('python3 -c "import cv2; print(\'OpenCV version: \' + cv2.__version__)" 2>&1');
echo "OpenCV check: $cvCheck";

// Check cascade file
$cascadePath = '/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml';
echo "Cascade file exists: " . (file_exists($cascadePath) ? 'Yes' : 'No') . "\n";
if (file_exists($cascadePath)) {
    echo "Cascade file readable: " . (is_readable($cascadePath) ? 'Yes' : 'No') . "\n";
    echo "Cascade file permissions: " . substr(sprintf('%o', fileperms($cascadePath)), -4) . "\n";
}

// Test running the script
echo "\n=== Script Test ===\n";
$testImage = __DIR__ . '/test_face.jpg';
$outputImage = __DIR__ . '/output/test/test_output.jpg';

// Create test image if it doesn't exist
if (!file_exists($testImage)) {
    file_put_contents($testImage, file_get_contents('https://via.placeholder.com/300'));
}

$command = sprintf(
    '%s %s %s %s 2>&1',
    escapeshellarg($pythonPath),
    escapeshellarg(__DIR__ . '/detect_face.py'),
    escapeshellarg($testImage),
    escapeshellarg($outputImage)
);

echo "Running command: $command\n\n";

echo "Output:\n";
system($command, $returnVar);

echo "\nReturn code: $returnVar\n";
if (file_exists($outputImage)) {
    echo "Output file created successfully!\n";
    unlink($outputImage); // Clean up
} else {
    echo "Failed to create output file\n";
}

// Show directory permissions
echo "\n=== Directory Permissions ===\n";
$dirs = [
    __DIR__ . '/uploads',
    __DIR__ . '/output',
    '/usr/share/opencv4',
    '/usr/share/opencv4/haarcascades'
];

foreach ($dirs as $dir) {
    if (file_exists($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        $owner = posix_getpwuid(fileowner($dir))['name'];
        $group = posix_getgrgid(filegroup($dir))['name'];
        echo "$dir: $perms (owner: $owner, group: $group)\n";
    } else {
        echo "$dir: Does not exist\n";
    }
}
?>
