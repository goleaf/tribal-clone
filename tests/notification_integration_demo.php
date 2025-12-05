<?php
/**
 * Integration Demo: Notification System for Building Queue
 * 
 * This demonstrates the complete flow of notification creation when a build completes.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingQueueManager.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    die("No database connection available.\n");
}

echo "=== Building Queue Notification Integration Demo ===\n\n";

// Setup
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingQueueManager = new BuildingQueueManager($conn, $buildingConfigManager);

// Create test user
$stmt = $conn->prepare("SELECT id FROM users WHERE username = 'demo_user' LIMIT 1");
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userRow) {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_protected) VALUES (?, ?, ?, 0)");
    $username = 'demo_user';
    $email = 'demo@example.com';
    $password = password_hash('demo', PASSWORD_DEFAULT);
    $stmt->bind_param("sss", $username, $email, $password);
    $stmt->execute();
    $userId = $conn->insert_id;
    $stmt->close();
    echo "✓ Created test user (ID: {$userId})\n";
} else {
    $userId = (int)$userRow['id'];
    echo "✓ Using existing test user (ID: {$userId})\n";
}

// Create test village
$stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$villageRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$villageRow) {
    $stmt = $conn->prepare("INSERT INTO villages (user_id, name, x_coord, y_coord, wood, clay, iron, world_id) VALUES (?, ?, ?, ?, 10000, 10000, 10000, 1)");
    $villageName = 'Demo Village';
    $x = 50;
    $y = 50;
    $stmt->bind_param("isii", $userId, $villageName, $x, $y);
    $stmt->execute();
    $villageId = $conn->insert_id;
    $stmt->close();
    echo "✓ Created test village (ID: {$villageId})\n";
} else {
    $villageId = (int)$villageRow['id'];
    echo "✓ Using existing test village (ID: {$villageId})\n";
}

// Setup main_building
$stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = 'main_building'");
$stmt->execute();
$mainBuildingResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($mainBuildingResult) {
    $mainBuildingTypeId = $mainBuildingResult['id'];
    $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
    $stmt->bind_param("ii", $villageId, $mainBuildingTypeId);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 10)");
    $stmt->bind_param("ii", $villageId, $mainBuildingTypeId);
    $stmt->execute();
    $stmt->close();
    echo "✓ Setup main building at level 10\n";
}

// Setup barracks at level 2
$stmt = $conn->prepare("SELECT id, name FROM building_types WHERE internal_name = 'barracks'");
$stmt->execute();
$barracksResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($barracksResult) {
    $barracksTypeId = $barracksResult['id'];
    $barracksName = $barracksResult['name'];
    
    $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
    $stmt->bind_param("ii", $villageId, $barracksTypeId);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 2)");
    $stmt->bind_param("ii", $villageId, $barracksTypeId);
    $stmt->execute();
    $villageBuildingId = $conn->insert_id;
    $stmt->close();
    echo "✓ Setup barracks at level 2\n";
}

// Clear previous notifications
$conn->query("DELETE FROM notifications WHERE user_id = {$userId}");
echo "✓ Cleared previous notifications\n\n";

// Count notifications before
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$beforeCount = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

echo "Notifications before: {$beforeCount}\n\n";

// Create a completed queue item (barracks level 3)
$finishTime = date('Y-m-d H:i:s', time() - 1);
$stmt = $conn->prepare("
    INSERT INTO building_queue 
    (village_id, village_building_id, building_type_id, level, starts_at, finish_time, status)
    VALUES (?, ?, ?, 3, ?, ?, 'active')
");
$stmt->bind_param("iiiss", $villageId, $villageBuildingId, $barracksTypeId, $finishTime, $finishTime);
$stmt->execute();
$queueItemId = $conn->insert_id;
$stmt->close();

echo "✓ Created queue item for barracks upgrade to level 3\n";
echo "  Queue Item ID: {$queueItemId}\n";
echo "  Status: active\n";
echo "  Finish Time: {$finishTime}\n\n";

// Complete the build
echo "Completing build...\n";
$result = $buildingQueueManager->onBuildComplete($queueItemId);

if ($result['success']) {
    echo "✓ Build completed successfully\n\n";
} else {
    echo "✗ Build completion failed: " . ($result['message'] ?? 'Unknown error') . "\n\n";
    exit(1);
}

// Count notifications after
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$afterCount = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

echo "Notifications after: {$afterCount}\n";
echo "New notifications: " . ($afterCount - $beforeCount) . "\n\n";

// Get the notification details
$stmt = $conn->prepare("SELECT message, type, link FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$notification = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($notification) {
    echo "=== Notification Details ===\n";
    echo "Message: {$notification['message']}\n";
    echo "Type: {$notification['type']}\n";
    echo "Link: {$notification['link']}\n\n";
    
    // Verify requirements
    echo "=== Requirement Verification ===\n";
    
    // Requirement 10.1: Notification created
    echo "✓ Requirement 10.1: Notification created for village owner\n";
    
    // Requirement 10.2: Contains building name and level
    if (stripos($notification['message'], $barracksName) !== false) {
        echo "✓ Requirement 10.2: Message contains building name ('{$barracksName}')\n";
    } else {
        echo "✗ Requirement 10.2: Message missing building name\n";
    }
    
    if (stripos($notification['message'], '3') !== false) {
        echo "✓ Requirement 10.2: Message contains level (3)\n";
    } else {
        echo "✗ Requirement 10.2: Message missing level\n";
    }
    
    // Requirement 10.3: Links to village overview
    if (strpos($notification['link'], 'game.php') !== false && strpos($notification['link'], "village_id={$villageId}") !== false) {
        echo "✓ Requirement 10.3: Notification links to village overview\n";
    } else {
        echo "✗ Requirement 10.3: Notification link incorrect\n";
    }
    
    echo "\n";
} else {
    echo "✗ No notification found!\n\n";
}

// Cleanup
$conn->query("DELETE FROM building_queue WHERE id = {$queueItemId}");
$conn->query("DELETE FROM notifications WHERE user_id = {$userId}");

echo "✓ Cleanup complete\n";
echo "\n=== Demo Complete ===\n";
