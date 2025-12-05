<?php
/**
 * Test for UnitManager::checkSeasonalWindow() method
 * 
 * Task: 2.6 Implement checkSeasonalWindow() method
 * Requirements: 10.1, 10.2, 10.4
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Testing UnitManager::checkSeasonalWindow() method...\n\n";

$unitManager = new UnitManager($conn);
$testsPassed = 0;
$testsFailed = 0;

/**
 * Helper function to assert test results
 */
function assertTest(string $testName, bool $condition, string $message = ''): void
{
    global $testsPassed, $testsFailed;
    
    if ($condition) {
        echo "✓ PASS: $testName\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $testName";
        if ($message) {
            echo " - $message";
        }
        echo "\n";
        $testsFailed++;
    }
}

// Clean up any existing test data
$conn->query("DELETE FROM seasonal_units WHERE unit_internal_name LIKE 'test_%'");

// Test 1: Non-seasonal unit should return available=true with null window
echo "Test 1: Non-seasonal unit returns available=true\n";
$result = $unitManager->checkSeasonalWindow('pikeneer', time());
assertTest(
    'Non-seasonal unit is available',
    $result['available'] === true,
    "Expected available=true, got " . var_export($result['available'], true)
);
assertTest(
    'Non-seasonal unit has null start',
    $result['start'] === null,
    "Expected start=null, got " . var_export($result['start'], true)
);
assertTest(
    'Non-seasonal unit has null end',
    $result['end'] === null,
    "Expected end=null, got " . var_export($result['end'], true)
);

// Test 2: Seasonal unit within window should be available
echo "\nTest 2: Seasonal unit within active window\n";
$now = time();
$start = $now - 3600; // 1 hour ago
$end = $now + 3600;   // 1 hour from now

$conn->query("INSERT INTO seasonal_units (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active) 
              VALUES ('test_knight_active', 'Test Event', $start, $end, 1)");

$result = $unitManager->checkSeasonalWindow('test_knight_active', $now);
assertTest(
    'Active seasonal unit within window is available',
    $result['available'] === true,
    "Expected available=true, got " . var_export($result['available'], true)
);
assertTest(
    'Active seasonal unit has correct start',
    $result['start'] === $start,
    "Expected start=$start, got " . var_export($result['start'], true)
);
assertTest(
    'Active seasonal unit has correct end',
    $result['end'] === $end,
    "Expected end=$end, got " . var_export($result['end'], true)
);

// Test 3: Seasonal unit before window should not be available
echo "\nTest 3: Seasonal unit before window starts\n";
$futureStart = $now + 7200; // 2 hours from now
$futureEnd = $now + 10800;  // 3 hours from now

$conn->query("INSERT INTO seasonal_units (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active) 
              VALUES ('test_knight_future', 'Future Event', $futureStart, $futureEnd, 1)");

$result = $unitManager->checkSeasonalWindow('test_knight_future', $now);
assertTest(
    'Seasonal unit before window is not available',
    $result['available'] === false,
    "Expected available=false, got " . var_export($result['available'], true)
);
assertTest(
    'Future seasonal unit has correct start',
    $result['start'] === $futureStart,
    "Expected start=$futureStart, got " . var_export($result['start'], true)
);

// Test 4: Seasonal unit after window should not be available
echo "\nTest 4: Seasonal unit after window expires\n";
$pastStart = $now - 7200; // 2 hours ago
$pastEnd = $now - 3600;   // 1 hour ago

$conn->query("INSERT INTO seasonal_units (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active) 
              VALUES ('test_knight_expired', 'Expired Event', $pastStart, $pastEnd, 1)");

$result = $unitManager->checkSeasonalWindow('test_knight_expired', $now);
assertTest(
    'Seasonal unit after window is not available',
    $result['available'] === false,
    "Expected available=false, got " . var_export($result['available'], true)
);
assertTest(
    'Expired seasonal unit has correct end',
    $result['end'] === $pastEnd,
    "Expected end=$pastEnd, got " . var_export($result['end'], true)
);

// Test 5: Inactive seasonal unit should not be available even within window
echo "\nTest 5: Inactive seasonal unit within window\n";
$conn->query("INSERT INTO seasonal_units (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active) 
              VALUES ('test_knight_inactive', 'Inactive Event', $start, $end, 0)");

$result = $unitManager->checkSeasonalWindow('test_knight_inactive', $now);
assertTest(
    'Inactive seasonal unit is not available',
    $result['available'] === false,
    "Expected available=false, got " . var_export($result['available'], true)
);
assertTest(
    'Inactive flag is returned',
    isset($result['is_active']) && $result['is_active'] === false,
    "Expected is_active=false"
);

// Test 6: Edge case - exactly at start timestamp
echo "\nTest 6: Seasonal unit at exact start timestamp\n";
$exactStart = $now;
$exactEnd = $now + 3600;

$conn->query("INSERT INTO seasonal_units (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active) 
              VALUES ('test_knight_start', 'Start Event', $exactStart, $exactEnd, 1)");

$result = $unitManager->checkSeasonalWindow('test_knight_start', $exactStart);
assertTest(
    'Seasonal unit at exact start is available',
    $result['available'] === true,
    "Expected available=true at exact start, got " . var_export($result['available'], true)
);

// Test 7: Edge case - exactly at end timestamp
echo "\nTest 7: Seasonal unit at exact end timestamp\n";
$result = $unitManager->checkSeasonalWindow('test_knight_start', $exactEnd);
assertTest(
    'Seasonal unit at exact end is available',
    $result['available'] === true,
    "Expected available=true at exact end, got " . var_export($result['available'], true)
);

// Clean up test data
$conn->query("DELETE FROM seasonal_units WHERE unit_internal_name LIKE 'test_%'");

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "Test Summary:\n";
echo "  Passed: $testsPassed\n";
echo "  Failed: $testsFailed\n";
echo "  Total:  " . ($testsPassed + $testsFailed) . "\n";
echo str_repeat("=", 50) . "\n";

if ($testsFailed > 0) {
    echo "\n❌ Some tests failed!\n";
    exit(1);
} else {
    echo "\n✅ All tests passed!\n";
    exit(0);
}
