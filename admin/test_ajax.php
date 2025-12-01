<?php
// test_ajax.php
// This file checks whether get_resources.php is reachable from the server side.

$file_path = $_SERVER['DOCUMENT_ROOT'] . '/ajax/resources/get_resources.php';

if (file_exists($file_path)) {
    echo "File exists: " . $file_path . "\n";
    // Try to load the file contents
    $content = file_get_contents($file_path);
    if ($content !== false) {
        echo "File contents:\n";
        echo $content;
    } else {
        echo "File read error.\n";
    }
} else {
    echo "File does not exist: " . $file_path . "\n";
}
?>
