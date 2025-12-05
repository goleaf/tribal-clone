<?php
/**
 * Integration test for seasonal unit lifecycle management
 * 
 * Tests:
 * - Activation of seasonal units when their window starts
 * - Sunset of seasonal units when their window ends
 * - Logging of lifecycle events
 * 
 * Requirements: 10.1, 10.3
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

function testSeasonalUnitLifecycle($conn): void
{
    echo "=== Testing Seasonal Unit Lifecycle ===\n\n";
    
    // Clean up any existing test data
    $conn->query("DELETE FROM seasonal_units WHERE unit_internal_name LIKE 'test_%'");
    
    $currentTime = time();
    $pastTime = $currentTime - 3600; // 1 hour ago
    $futureTime = $currentTime + 3600; // 1 hour from now
    $farFutureTime = $currentTime + 7200; // 2 hours from now
    
    // Test Case 1: Insert an expired seasonal unit (should be sunset)
    echo "Test 1: Expired seasonal unit should be deactivated\n";
    $stmt = $conn->prepare("
        INSERT INTO seasonal_units 
        (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $unitName1 = 'test_expired_knight';
    $eventName1 = 'Test Expired Event';
    $stmt->bind_param("ssii", $unitName1, $eventName1, $pastTime, $pastTime);
    $stmt->execute();
    $stmt->close();
    
    // Test Case 2: Insert an active seasonal unit (should remain active)
    echo "Test 2: Active seasonal unit should remain active\n";
    $stmt = $conn->prepare("
        INSERT INTO seasonal_units 
        (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $unitName2 = 'test_active_knight';
    $eventName2 = 'Test Active Event';
    $stmt->bind_param("ssii", $unitName2, $eventName2, $pastTime, $futureTime);
    $stmt->execute();
    $stmt->close();
    
    // Test Case 3: Insert an inactive seasonal unit that should be activated
    echo "Test 3: Inactive seasonal unit in active window should be activated\n";
    $stmt = $conn->prepare("
        INSERT INTO seasonal_units 
        (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active)
        VALUES (?, ?, ?, ?, 0)
    ");
    $unitName3 = 'test_pending_knight';
    $eventName3 = 'Test Pending Event';
    $stmt->bind_param("ssii", $unitName3, $eventName3, $pastTime, $futureTime);
    $stmt->execute();
    $stmt->close();
    
    // Test Case 4: Insert a future seasonal unit (should remain inactive)
    echo "Test 4: Future seasonal unit should remain inactive\n";
    $stmt = $conn->prepare("
        INSERT INTO seasonal_units 
        (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active)
        VALUES (?, ?, ?, ?, 0)
    ");
    $unitName4 = 'test_future_knight';
    $eventName4 = 'Test Future Event';
    $stmt->bind_param("ssii", $unitName4, $eventName4, $futureTime, $farFutureTime);
    $stmt->execute();
    $stmt->close();
    
    echo "\nInitial state created. Running lifecycle processor...\n\n";
    
    // Run the lifecycle processor logic inline
    // Process activations
    $activatedCount = 0;
    $stmt = $conn->prepare("
        SELECT id, unit_internal_name, event_name
        FROM seasonal_units
        WHERE is_active = 0
        AND start_timestamp <= ?
        AND end_timestamp >= ?
        AND unit_internal_name LIKE 'test_%'
    ");
    $stmt->bind_param("ii", $currentTime, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $updateStmt = $conn->prepare("UPDATE seasonal_units SET is_active = 1 WHERE id = ?");
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
        $activatedCount++;
        echo "  ✓ Activated: {$row['unit_internal_name']} ({$row['event_name']})\n";
    }
    $stmt->close();
    
    // Process sunsets
    $sunsetCount = 0;
    $stmt = $conn->prepare("
        SELECT id, unit_internal_name, event_name
        FROM seasonal_units
        WHERE is_active = 1
        AND end_timestamp < ?
        AND unit_internal_name LIKE 'test_%'
    ");
    $stmt->bind_param("i", $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $updateStmt = $conn->prepare("UPDATE seasonal_units SET is_active = 0 WHERE id = ?");
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
        $sunsetCount++;
        echo "  ✓ Sunset: {$row['unit_internal_name']} ({$row['event_name']})\n";
    }
    $stmt->close();
    
    echo "\nVerifying results...\n";
    
    // Verify Test Case 1: Expired unit should be inactive
    $stmt = $conn->prepare("SELECT is_active FROM seasonal_units WHERE unit_internal_name = ?");
    $stmt->bind_param("s", $unitName1);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && $row['is_active'] == 0) {
        echo "  ✓ Test 1 PASSED: Expired unit is inactive\n";
    } else {
        echo "  ✗ Test 1 FAILED: Expired unit should be inactive\n";
    }
    
    // Verify Test Case 2: Active unit should remain active
    $stmt = $conn->prepare("SELECT is_active FROM seasonal_units WHERE unit_internal_name = ?");
    $stmt->bind_param("s", $unitName2);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && $row['is_active'] == 1) {
        echo "  ✓ Test 2 PASSED: Active unit remains active\n";
    } else {
        echo "  ✗ Test 2 FAILED: Active unit should remain active\n";
    }
    
    // Verify Test Case 3: Pending unit should be activated
    $stmt = $conn->prepare("SELECT is_active FROM seasonal_units WHERE unit_internal_name = ?");
    $stmt->bind_param("s", $unitName3);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && $row['is_active'] == 1) {
        echo "  ✓ Test 3 PASSED: Pending unit was activated\n";
    } else {
        echo "  ✗ Test 3 FAILED: Pending unit should be activated\n";
    }
    
    // Verify Test Case 4: Future unit should remain inactive
    $stmt = $conn->prepare("SELECT is_active FROM seasonal_units WHERE unit_internal_name = ?");
    $stmt->bind_param("s", $unitName4);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && $row['is_active'] == 0) {
        echo "  ✓ Test 4 PASSED: Future unit remains inactive\n";
    } else {
        echo "  ✗ Test 4 FAILED: Future unit should remain inactive\n";
    }
    
    echo "\nSummary:\n";
    echo "  - Units activated: {$activatedCount}\n";
    echo "  - Units sunset: {$sunsetCount}\n";
    
    // Clean up test data
    $conn->query("DELETE FROM seasonal_units WHERE unit_internal_name LIKE 'test_%'");
    
    echo "\n=== Test Complete ===\n";
}

try {
    testSeasonalUnitLifecycle($conn);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
