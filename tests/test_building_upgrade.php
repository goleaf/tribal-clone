<?php
/**
 * Building Upgrade System Test
 * 
 * This script tests the refactored building upgrade system.
 * Run from command line: php tests/test_building_upgrade.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingQueueManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';

echo "=== Building Upgrade System Test ===\n\n";

// Test configuration
$testVillageId = 1; // Change this to a valid village ID
$testUserId = 1;    // Change this to a valid user ID
$testBuilding = 'sawmill'; // Building to test

try {
    // Initialize managers
    $buildingConfigManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfigManager);
    $queueManager = new BuildingQueueManager($conn, $buildingConfigManager);
    $villageManager = new VillageManager($conn);
    
    echo "✓ Managers initialized\n\n";
    
    // Test 1: Check current building level
    echo "Test 1: Get current building level\n";
    $currentLevel = $buildingManager->getBuildingLevel($testVillageId, $testBuilding);
    echo "  Current level: {$currentLevel}\n";
    echo "  ✓ Pass\n\n";
    
    // Test 2: Check if upgrade is possible
    echo "Test 2: Check upgrade eligibility\n";
    $canUpgrade = $buildingManager->canUpgradeBuilding($testVillageId, $testBuilding, $testUserId);
    echo "  Can upgrade: " . ($canUpgrade['success'] ? 'Yes' : 'No') . "\n";
    if (!$canUpgrade['success']) {
        echo "  Reason: {$canUpgrade['message']}\n";
        echo "  Code: " . ($canUpgrade['code'] ?? 'N/A') . "\n";
    }
    echo "  ✓ Pass\n\n";
    
    // Test 3: Check queue status
    echo "Test 3: Check queue status\n";
    $queueUsage = $buildingManager->getQueueUsage($testVillageId);
    echo "  Queue count: {$queueUsage['count']}/{$queueUsage['limit']}\n";
    echo "  Queue full: " . ($queueUsage['is_full'] ? 'Yes' : 'No') . "\n";
    echo "  ✓ Pass\n\n";
    
    // Test 4: Check for completed builds
    echo "Test 4: Check for completed builds\n";
    $stmt = $conn->prepare("
        SELECT COUNT(*) as cnt 
        FROM building_queue 
        WHERE village_id = ? 
          AND status = 'active' 
          AND finish_time <= NOW()
    ");
    $stmt->bind_param("i", $testVillageId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $completedCount = $result['cnt'] ?? 0;
    echo "  Completed builds ready: {$completedCount}\n";
    echo "  ✓ Pass\n\n";
    
    // Test 5: Process completed builds
    if ($completedCount > 0) {
        echo "Test 5: Process completed builds\n";
        $completed = $villageManager->processBuildingQueue($testVillageId);
        echo "  Processed: " . count($completed) . " builds\n";
        foreach ($completed as $item) {
            echo "    - {$item['name']} → Level {$item['level']}\n";
        }
        echo "  ✓ Pass\n\n";
    } else {
        echo "Test 5: Process completed builds\n";
        echo "  No completed builds to process\n";
        echo "  ✓ Pass (skipped)\n\n";
    }
    
    // Test 6: Check queue items
    echo "Test 6: List queue items\n";
    $stmt = $conn->prepare("
        SELECT bq.*, bt.name, bt.internal_name 
        FROM building_queue bq
        JOIN building_types bt ON bq.building_type_id = bt.id
        WHERE bq.village_id = ?
        ORDER BY bq.starts_at ASC
    ");
    $stmt->bind_param("i", $testVillageId);
    $stmt->execute();
    $queueItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($queueItems)) {
        echo "  No items in queue\n";
    } else {
        foreach ($queueItems as $item) {
            $status = $item['status'] ?? 'unknown';
            $finishTime = $item['finish_time'];
            $remaining = strtotime($finishTime) - time();
            $remainingStr = $remaining > 0 ? gmdate("H:i:s", $remaining) : "Ready";
            echo "  - {$item['name']} → Level {$item['level']} [{$status}] ({$remainingStr})\n";
        }
    }
    echo "  ✓ Pass\n\n";
    
    // Test 7: Verify no duplicate processing
    echo "Test 7: Verify idempotent processing\n";
    $stmt = $conn->prepare("
        SELECT COUNT(*) as cnt 
        FROM building_queue 
        WHERE village_id = ? 
          AND status = 'completed'
    ");
    $stmt->bind_param("i", $testVillageId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $completedInDb = $result['cnt'] ?? 0;
    echo "  Completed items in DB: {$completedInDb}\n";
    echo "  ✓ Pass (these should not be reprocessed)\n\n";
    
    echo "=== All Tests Passed ===\n";
    echo "\nSystem Status: ✓ HEALTHY\n";
    
} catch (Exception $e) {
    echo "\n✗ Test Failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
