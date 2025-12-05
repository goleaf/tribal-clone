#!/usr/bin/env php
<?php
/**
 * Run all property-based tests for the resource-system spec
 * This script runs all property tests
 */

echo "=== Running All Property-Based Tests ===\n\n";

$testFiles = [
    'resource_manager_property_test.php',
    'building_manager_property_test.php',
    'recruitment_resource_deduction_property_test.php',
    'movement_entry_property_test.php',
    'conquest_loyalty_property_test.php',
];

$allPassed = true;
$results = [];

foreach ($testFiles as $testFile) {
    $testPath = __DIR__ . '/' . $testFile;
    if (!file_exists($testPath)) {
        echo "⚠ SKIP: $testFile (file not found)\n\n";
        continue;
    }
    
    echo "Running: $testFile\n";
    echo str_repeat('-', 60) . "\n";
    
    // Run the test directly
    $output = [];
    $returnCode = 0;
    exec("php " . escapeshellarg($testPath) . " 2>&1", $output, $returnCode);
    
    $outputStr = implode("\n", $output);
    echo $outputStr . "\n\n";
    
    $passed = ($returnCode === 0);
    $results[$testFile] = $passed;
    
    if (!$passed) {
        $allPassed = false;
    }
}

// Summary
echo "\n" . str_repeat('=', 60) . "\n";
echo "=== Test Summary ===\n";
echo str_repeat('=', 60) . "\n";

foreach ($results as $testFile => $passed) {
    $status = $passed ? '✓ PASS' : '✗ FAIL';
    echo "$status: $testFile\n";
}

echo "\n";
if ($allPassed) {
    echo "✓ All property-based tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the output above.\n";
    exit(1);
}
