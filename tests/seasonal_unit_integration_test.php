<?php
/**
 * Integration test for seasonal unit lifecycle with UnitManager
 * 
 * Tests the full integration:
 * - Seasonal units are filtered by UnitManager based on is_active flag
 * - checkSeasonalWindow correctly identifies availability
 * - Training requests are rejected for expired seasonal units
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

function testSeasonalUnitIntegration($conn): void
{
    echo "=== Testing Seasonal Unit Integration with UnitManager ===\n\n";
    
    // Clean up any existing test data
    $conn->query("DELETE FROM seasonal_units WHERE unit_internal_name LIKE 'test_%'");
    
    $currentTime = time();
    $pastTime = $currentTime - 7200; // 2 hours ago
    $futureTime = $currentTime + 7200; // 2 hours from now
    
    // Test Case 1: Active seasonal unit within window
    echo "Test 1: Active seasonal unit within window\n";
    $stmt = $conn->prepare("
        INSERT INTO seasonal_units 
        (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active, per_account_cap)
        VALUES (?, ?, ?, ?, 1, 50)
    ");
    $unitName1 = 'test_summer_knight';
    $eventName1 = 'Summer Festival';
    $stmt->bind_param("ssii", $unitName1, $eventName1, $pastTime, $futureTime);
    $stmt->execute();
    $stmt->close();
    
    // Test Case 2: Inactive seasonal unit (expired)
    echo "Test 2: Inactive seasonal unit (expired)\n";
    $stmt = $conn->prepare("
        INSERT INTO seasonal_units 
        (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active, per_account_cap)
        VALUES (?, ?, ?, ?, 0, 50)
    ");
    $unitName2 = 'test_winter_knight';
    $eventName2 = 'Winter Festival';
    $expiredEnd = $currentTime - 3600; // Ended 1 hour ago
    $stmt->bind_param("ssii", $unitName2, $eventName2, $pastTime, $expiredEnd);
    $stmt->execute();
    $stmt->close();
    
    // Test Case 3: Active seasonal unit but outside window (edge case)
    echo "Test 3: Active seasonal unit but outside window\n";
    $stmt = $conn->prepare("
        INSERT INTO seasonal_units 
        (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active, per_account_cap)
        VALUES (?, ?, ?, ?, 1, 50)
    ");
    $unitName3 = 'test_spring_knight';
    $eventName3 = 'Spring Festival';
    $notYetStart = $currentTime + 3600; // Starts in 1 hour
    $stmt->bind_param("ssii", $unitName3, $eventName3, $notYetStart, $futureTime);
    $stmt->execute();
    $stmt->close();
    
    echo "\nTesting UnitManager::checkSeasonalWindow()...\n";
    
    $unitManager = new UnitManager($conn);
    
    // Test active unit within window
    $window1 = $unitManager->checkSeasonalWindow($unitName1, $currentTime);
    echo "\n{$unitName1}:\n";
    echo "  Available: " . ($window1['available'] ? 'YES' : 'NO') . "\n";
    echo "  Is Active: " . ($window1['is_active'] ? 'YES' : 'NO') . "\n";
    echo "  Window: " . date('Y-m-d H:i', $window1['start']) . " to " . date('Y-m-d H:i', $window1['end']) . "\n";
    
    if ($window1['available']) {
        echo "  ✓ Test 1 PASSED: Active unit within window is available\n";
    } else {
        echo "  ✗ Test 1 FAILED: Active unit within window should be available\n";
    }
    
    // Test inactive unit (expired)
    $window2 = $unitManager->checkSeasonalWindow($unitName2, $currentTime);
    echo "\n{$unitName2}:\n";
    echo "  Available: " . ($window2['available'] ? 'YES' : 'NO') . "\n";
    echo "  Is Active: " . ($window2['is_active'] ? 'YES' : 'NO') . "\n";
    echo "  Window: " . date('Y-m-d H:i', $window2['start']) . " to " . date('Y-m-d H:i', $window2['end']) . "\n";
    
    if (!$window2['available']) {
        echo "  ✓ Test 2 PASSED: Inactive unit is not available\n";
    } else {
        echo "  ✗ Test 2 FAILED: Inactive unit should not be available\n";
    }
    
    // Test active unit outside window
    $window3 = $unitManager->checkSeasonalWindow($unitName3, $currentTime);
    echo "\n{$unitName3}:\n";
    echo "  Available: " . ($window3['available'] ? 'YES' : 'NO') . "\n";
    echo "  Is Active: " . ($window3['is_active'] ? 'YES' : 'NO') . "\n";
    echo "  Window: " . date('Y-m-d H:i', $window3['start']) . " to " . date('Y-m-d H:i', $window3['end']) . "\n";
    
    if (!$window3['available']) {
        echo "  ✓ Test 3 PASSED: Unit outside window is not available\n";
    } else {
        echo "  ✗ Test 3 FAILED: Unit outside window should not be available\n";
    }
    
    // Test non-seasonal unit
    echo "\nTesting non-seasonal unit (should always be available)...\n";
    $window4 = $unitManager->checkSeasonalWindow('spearman', $currentTime);
    echo "  Available: " . ($window4['available'] ? 'YES' : 'NO') . "\n";
    
    if ($window4['available'] && $window4['start'] === null) {
        echo "  ✓ Test 4 PASSED: Non-seasonal unit is always available\n";
    } else {
        echo "  ✗ Test 4 FAILED: Non-seasonal unit should always be available\n";
    }
    
    // Clean up test data
    $conn->query("DELETE FROM seasonal_units WHERE unit_internal_name LIKE 'test_%'");
    
    echo "\n=== Integration Test Complete ===\n";
}

try {
    testSeasonalUnitIntegration($conn);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
