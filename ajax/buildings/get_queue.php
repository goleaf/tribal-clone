<?php
declare(strict_types=1);
require_once '../../init.php';
require_once '../../lib/managers/BuildingManager.php';
require_once '../../lib/managers/BuildingConfigManager.php'; // Needed for BuildingManager
require_once '../../lib/managers/VillageManager.php'; // Needed to check permissions

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You are not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;

if (!$village_id) {
    echo json_encode(['status' => 'error', 'message' => 'Village ID is missing.']);
    exit();
}

$villageManager = new VillageManager($conn);
// Ensure the village belongs to the logged-in user
$village = $villageManager->getVillageInfo($village_id);
if (!$village || $village['user_id'] !== $user_id) {
    echo json_encode(['status' => 'error', 'message' => 'No access to this village.']);
    exit();
}

$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);

$queue_item = $buildingManager->getBuildingQueueItem($village_id);

if ($queue_item) {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'config_version' => $buildingConfigManager->getConfigVersion(),
            'queue_item' => [
                'id' => $queue_item['id'],
                'building_name' => $queue_item['name'],
                'internal_name' => $queue_item['internal_name'],
                'building_internal_name' => $queue_item['building_internal_name'] ?? $queue_item['internal_name'],
                'level' => $queue_item['level'],
                'finish_time' => strtotime($queue_item['finish_time']), // Return timestamp
                'start_time' => isset($queue_item['starts_at']) ? strtotime($queue_item['starts_at']) : null
            ]
        ]
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'config_version' => $buildingConfigManager->getConfigVersion(),
            'queue_item' => null
        ]
    ]);
}

$conn->close();
?>
