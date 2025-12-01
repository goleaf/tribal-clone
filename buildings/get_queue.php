<?php
declare(strict_types=1);

require '../init.php';
header('Content-Type: application/json');

require_once '../lib/managers/BuildingConfigManager.php';
require_once '../lib/managers/BuildingQueueManager.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'You are not logged in.']);
    exit();
}

// Validate village_id
if (!isset($_GET['village_id']) || !is_numeric($_GET['village_id'])) {
    echo json_encode(['error' => 'Invalid village_id.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$village_id = (int)$_GET['village_id'];

try {
    // Verify village ownership
    $stmt = $conn->prepare("SELECT id FROM villages WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $village_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Access denied.']);
        exit();
    }
    $stmt->close();

    $configManager = new BuildingConfigManager($conn);
    $queueManager = new BuildingQueueManager($conn, $configManager);
    
    $queue = $queueManager->getVillageQueue($village_id);
    
    // Enrich queue items with building names
    $enrichedQueue = [];
    foreach ($queue as $item) {
        $stmt = $conn->prepare("SELECT name, internal_name FROM building_types WHERE id = ?");
        $stmt->bind_param("i", $item['building_type_id']);
        $stmt->execute();
        $building = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $enrichedQueue[] = [
            'id' => $item['id'],
            'building_name' => $building['name'] ?? 'Unknown',
            'building_internal_name' => $building['internal_name'] ?? '',
            'level' => $item['level'],
            'status' => $item['status'],
            'starts_at' => strtotime($item['starts_at']),
            'finish_time' => strtotime($item['finish_time']),
            'time_remaining' => max(0, strtotime($item['finish_time']) - time())
        ];
    }
    
    echo json_encode([
        'success' => true,
        'queue' => $enrichedQueue
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
