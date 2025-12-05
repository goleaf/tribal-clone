<?php
declare(strict_types=1);

/**
 * Test script for Building Queue System
 * 
 * Run: php tests/test_building_queue.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingQueueManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';

echo "=== Building Queue System Test ===\n\n";

try {
    $configManager = new BuildingConfigManager($conn);
    $queueManager = new BuildingQueueManager($conn, $configManager);
    $villageManager = new VillageManager($conn);
    
    // Get first user and village for testing
    $stmt = $conn->prepare("SELECT id FROM users LIMIT 1");
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        echo "❌ No users found. Please create a user first.\n";
        exit(1);
    }
    
    $userId = $user['id'];
    echo "✓ Using user ID: $userId\n";
    
    $stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $village = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$village) {
        echo "❌ No villages found for user. Please create a village first.\n";
        exit(1);
    }
    
    $villageId = $village['id'];
    echo "✓ Using village ID: $villageId\n\n";
    
    // Test 1: Get current queue
    echo "Test 1: Get current queue\n";
    $queue = $queueManager->getVillageQueue($villageId);
    echo "Current queue items: " . count($queue) . "\n";
    foreach ($queue as $item) {
        echo "  - Building type ID {$item['building_type_id']}, Level {$item['level']}, Status: {$item['status']}\n";
    }
    echo "\n";
    
    // Test 2: Try to enqueue a build (barracks)
    echo "Test 2: Enqueue barracks upgrade\n";
    $result = $queueManager->enqueueBuild($villageId, 'barracks', $userId);
    
    if ($result['success']) {
        echo "✓ Build queued successfully!\n";
        echo "  Queue Item ID: {$result['queue_item_id']}\n";
        echo "  Status: {$result['status']}\n";
        echo "  Level: {$result['level']}\n";
        echo "  Finish at: " . date('Y-m-d H:i:s', $result['finish_at']) . "\n";
    } else {
        echo "❌ Failed to queue build: {$result['message']}\n";
    }
    echo "\n";
    
    // Test 3: Try to enqueue another build
    echo "Test 3: Enqueue stable upgrade (should be pending)\n";
    $result2 = $queueManager->enqueueBuild($villageId, 'stable', $userId);
    
    if ($result2['success']) {
        echo "✓ Build queued successfully!\n";
        echo "  Queue Item ID: {$result2['queue_item_id']}\n";
        echo "  Status: {$result2['status']}\n";
        echo "  Level: {$result2['level']}\n";
        echo "  Finish at: " . date('Y-m-d H:i:s', $result2['finish_at']) . "\n";
    } else {
        echo "❌ Failed to queue build: {$result2['message']}\n";
    }
    echo "\n";
    
    // Test 4: Get updated queue
    echo "Test 4: Get updated queue\n";
    $queue = $queueManager->getVillageQueue($villageId);
    echo "Current queue items: " . count($queue) . "\n";
    foreach ($queue as $item) {
        echo "  - Building type ID {$item['building_type_id']}, Level {$item['level']}, Status: {$item['status']}\n";
    }
    echo "\n";
    
    // Test 5: Process completed builds (should do nothing if not ready)
    echo "Test 5: Process completed builds\n";
    $processed = $queueManager->processCompletedBuilds();
    echo "Processed " . count($processed) . " builds\n";
    foreach ($processed as $p) {
        if ($p['result']['success']) {
            echo "  ✓ Completed queue item #{$p['queue_item_id']}\n";
        } else {
            echo "  ⏳ Queue item #{$p['queue_item_id']}: {$p['result']['message']}\n";
        }
    }
    echo "\n";
    
    echo "=== All tests completed ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
