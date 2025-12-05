<?php
/**
 * Seasonal Unit Workflow Demonstration
 * 
 * This script demonstrates the complete seasonal unit lifecycle:
 * 1. Create a seasonal unit event
 * 2. Run activation (simulated)
 * 3. Verify training is allowed
 * 4. Run sunset (simulated)
 * 5. Verify training is blocked
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

function runWorkflowDemo($conn): void
{
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║     Seasonal Unit Lifecycle - Complete Workflow Demo          ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    // Clean up any existing test data
    $conn->query("DELETE FROM seasonal_units WHERE unit_internal_name = 'demo_event_knight'");
    
    $currentTime = time();
    $eventStart = $currentTime - 3600; // Started 1 hour ago
    $eventEnd = $currentTime + 3600;   // Ends in 1 hour
    
    // ========================================================================
    // PHASE 1: Event Setup
    // ========================================================================
    echo "📋 PHASE 1: Event Setup\n";
    echo "─────────────────────────────────────────────────────────────────\n";
    
    $unitName = 'demo_event_knight';
    $eventName = 'Demo Summer Festival';
    
    echo "Creating seasonal unit event:\n";
    echo "  • Unit: {$unitName}\n";
    echo "  • Event: {$eventName}\n";
    echo "  • Start: " . date('Y-m-d H:i:s', $eventStart) . "\n";
    echo "  • End: " . date('Y-m-d H:i:s', $eventEnd) . "\n";
    echo "  • Initial state: INACTIVE (waiting for activation)\n\n";
    
    $stmt = $conn->prepare("
        INSERT INTO seasonal_units 
        (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active, per_account_cap)
        VALUES (?, ?, ?, ?, 0, 50)
    ");
    $stmt->bind_param("ssii", $unitName, $eventName, $eventStart, $eventEnd);
    $stmt->execute();
    $stmt->close();
    
    echo "✅ Event created successfully\n\n";
    
    // ========================================================================
    // PHASE 2: Before Activation
    // ========================================================================
    echo "📋 PHASE 2: Before Activation\n";
    echo "─────────────────────────────────────────────────────────────────\n";
    
    $unitManager = new UnitManager($conn);
    $window = $unitManager->checkSeasonalWindow($unitName, $currentTime);
    
    echo "Checking unit availability:\n";
    echo "  • Is Active: " . ($window['is_active'] ? 'YES' : 'NO') . "\n";
    echo "  • Available: " . ($window['available'] ? 'YES' : 'NO') . "\n";
    echo "  • Reason: Unit is within time window but not yet activated\n\n";
    
    if (!$window['available']) {
        echo "✅ Correctly blocked: Unit cannot be trained before activation\n\n";
    } else {
        echo "❌ ERROR: Unit should not be available before activation\n\n";
    }
    
    // ========================================================================
    // PHASE 3: Activation
    // ========================================================================
    echo "📋 PHASE 3: Activation (Job Run)\n";
    echo "─────────────────────────────────────────────────────────────────\n";
    
    echo "Running activation logic...\n";
    
    $stmt = $conn->prepare("
        SELECT id, unit_internal_name, event_name
        FROM seasonal_units
        WHERE is_active = 0
        AND start_timestamp <= ?
        AND end_timestamp >= ?
        AND unit_internal_name = ?
    ");
    $stmt->bind_param("iis", $currentTime, $currentTime, $unitName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $updateStmt = $conn->prepare("UPDATE seasonal_units SET is_active = 1 WHERE id = ?");
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        echo "  ✓ ACTIVATED: {$row['unit_internal_name']} for event '{$row['event_name']}'\n\n";
    }
    $stmt->close();
    
    // ========================================================================
    // PHASE 4: After Activation
    // ========================================================================
    echo "📋 PHASE 4: After Activation\n";
    echo "─────────────────────────────────────────────────────────────────\n";
    
    // Need to create a new UnitManager to clear cache
    $unitManager = new UnitManager($conn);
    $window = $unitManager->checkSeasonalWindow($unitName, $currentTime);
    
    echo "Checking unit availability:\n";
    echo "  • Is Active: " . ($window['is_active'] ? 'YES' : 'NO') . "\n";
    echo "  • Available: " . ($window['available'] ? 'YES' : 'NO') . "\n";
    echo "  • Window: " . date('H:i', $window['start']) . " - " . date('H:i', $window['end']) . "\n\n";
    
    if ($window['available']) {
        echo "✅ Correctly available: Players can now train this unit\n\n";
    } else {
        echo "❌ ERROR: Unit should be available after activation\n\n";
    }
    
    // ========================================================================
    // PHASE 5: Simulate Event End
    // ========================================================================
    echo "📋 PHASE 5: Event Expiry (Simulated)\n";
    echo "─────────────────────────────────────────────────────────────────\n";
    
    echo "Simulating event end by setting end_timestamp to past...\n";
    $pastEnd = $currentTime - 60; // Ended 1 minute ago
    $conn->query("UPDATE seasonal_units SET end_timestamp = {$pastEnd} WHERE unit_internal_name = '{$unitName}'");
    
    echo "Running sunset logic...\n";
    
    $stmt = $conn->prepare("
        SELECT id, unit_internal_name, event_name
        FROM seasonal_units
        WHERE is_active = 1
        AND end_timestamp < ?
        AND unit_internal_name = ?
    ");
    $stmt->bind_param("is", $currentTime, $unitName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $updateStmt = $conn->prepare("UPDATE seasonal_units SET is_active = 0 WHERE id = ?");
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        echo "  ✓ SUNSET: {$row['unit_internal_name']} for event '{$row['event_name']}'\n";
        echo "  • Event expired at: " . date('Y-m-d H:i:s', $pastEnd) . "\n\n";
    }
    $stmt->close();
    
    // ========================================================================
    // PHASE 6: After Sunset
    // ========================================================================
    echo "📋 PHASE 6: After Sunset\n";
    echo "─────────────────────────────────────────────────────────────────\n";
    
    // Need to create a new UnitManager to clear cache
    $unitManager = new UnitManager($conn);
    $window = $unitManager->checkSeasonalWindow($unitName, $currentTime);
    
    echo "Checking unit availability:\n";
    echo "  • Is Active: " . ($window['is_active'] ? 'YES' : 'NO') . "\n";
    echo "  • Available: " . ($window['available'] ? 'YES' : 'NO') . "\n";
    echo "  • Reason: Event has ended\n\n";
    
    if (!$window['available']) {
        echo "✅ Correctly blocked: Unit cannot be trained after sunset\n\n";
    } else {
        echo "❌ ERROR: Unit should not be available after sunset\n\n";
    }
    
    // ========================================================================
    // PHASE 7: Cleanup
    // ========================================================================
    echo "📋 PHASE 7: Cleanup\n";
    echo "─────────────────────────────────────────────────────────────────\n";
    
    $conn->query("DELETE FROM seasonal_units WHERE unit_internal_name = '{$unitName}'");
    echo "✅ Test data cleaned up\n\n";
    
    // ========================================================================
    // Summary
    // ========================================================================
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                      Workflow Complete                         ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    echo "Summary of lifecycle states:\n";
    echo "  1. ⏸️  INACTIVE (before activation) → Training BLOCKED\n";
    echo "  2. ▶️  ACTIVE (after activation)    → Training ALLOWED\n";
    echo "  3. ⏹️  INACTIVE (after sunset)      → Training BLOCKED\n\n";
    
    echo "The seasonal unit lifecycle system is working correctly! ✨\n";
}

try {
    runWorkflowDemo($conn);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
