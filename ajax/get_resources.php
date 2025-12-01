<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/functions.php'; // Make sure global helpers are available
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/VillageManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/BuildingManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/BuildingConfigManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/ResourceManager.php'; // Needed for production calculations

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'You are not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;

if (!$village_id) {
    echo json_encode(['error' => 'Village ID is missing.']);
    exit();
}

$villageManager = new VillageManager($conn);
$resourceManager = new ResourceManager($conn, new BuildingManager($conn, new BuildingConfigManager($conn))); // BuildingManager and BuildingConfigManager are required by ResourceManager

// Ensure the village belongs to the logged-in user
$village = $villageManager->getVillageInfo($village_id);
if (!$village || $village['user_id'] !== $user_id) {
    echo json_encode(['error' => 'No access to this village.']);
    exit();
}

// Update village resources before returning them
$villageManager->updateResources($village_id);

// Fetch the updated resources
$currentRes = $villageManager->getVillageInfo($village_id);

if ($currentRes) {
    $wood_prod_per_hour = (int)$resourceManager->getHourlyProductionRate($village_id, 'wood');
    $clay_prod_per_hour = (int)$resourceManager->getHourlyProductionRate($village_id, 'clay');
    $iron_prod_per_hour = (int)$resourceManager->getHourlyProductionRate($village_id, 'iron');

    $response_data = [
        'wood' => [
            'amount' => (int)$currentRes['wood'],
            'capacity' => (int)$currentRes['warehouse_capacity'],
            'production_per_hour' => $wood_prod_per_hour,
            'production_per_second' => $wood_prod_per_hour / 3600,
        ],
        'clay' => [
            'amount' => (int)$currentRes['clay'],
            'capacity' => (int)$currentRes['warehouse_capacity'],
            'production_per_hour' => $clay_prod_per_hour,
            'production_per_second' => $clay_prod_per_hour / 3600,
        ],
        'iron' => [
            'amount' => (int)$currentRes['iron'],
            'capacity' => (int)$currentRes['warehouse_capacity'],
            'production_per_hour' => $iron_prod_per_hour,
            'production_per_second' => $iron_prod_per_hour / 3600,
        ],
        'population' => [
            'amount' => (int)$currentRes['population'],
            'capacity' => (int)$currentRes['farm_capacity'],
        ],
        'current_server_time' => date('Y-m-d H:i:s'),
    ];
    echo json_encode(['status' => 'success', 'data' => $response_data]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch village resources.']);
}

$conn->close();
?>
