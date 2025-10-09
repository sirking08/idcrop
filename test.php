<?php
$testFile = '/var/www/html/idcrop/output/test_write.txt';
if (file_put_contents($testFile, 'test') === false) {
    echo "Failed to write to file. Check permissions for directory: " . dirname($testFile);
} else {
    echo "Successfully wrote to file. Removing test file...";
    unlink($testFile);
}