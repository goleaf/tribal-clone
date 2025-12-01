<?php
declare(strict_types=1);
error_log("DEBUG: upgrade_building.php - Start");
require '../init.php';
error_log("DEBUG: upgrade_building.php - After init.php");

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

error_log("DEBUG: upgrade_building.php - After validateCSRF()");

require_once '../lib/managers/BuildingManager.php';
require_once '../lib/managers/VillageManager.php';
require_once '../lib/managers/BuildingConfigManager.php';
require_once '../lib/managers/BuildingQueueManager.php';

error_log("DEBUG: upgrade_building.php - After including managers");

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("DEBUG: upgrade_building.php - User not logged in");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'You are not logged in.', 'redirect' => 'auth/login.php']);
    exit();
}

error_log("DEBUG: upgrade_building.php - User logged in: " . $_SESSION['user_id']);

// Validate required parameters
if (!isset($_POST['building_type_internal_name']) || empty($_POST['building_type_internal_name']) || 
    !isset($_POST['current_level']) || !is_numeric($_POST['current_level']) ||
    !isset($_POST['village_id']) || !is_numeric($_POST['village_id'])) {
    error_log("DEBUG: upgrade_building.php - Missing parameters or invalid village_id");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters (building_type_internal_name, current_level, village_id).']);
    exit();
}

error_log("DEBUG: upgrade_building.php - Parameters received: building_type_internal_name=" . $_POST['building_type_internal_name'] . ", current_level=" . $_POST['current_level'] . ", village_id=" . $_POST['village_id']);

$user_id = $_SESSION['user_id'];
$internal_name = $_POST['building_type_internal_name'];
$current_level = (int)$_POST['current_level'];
$village_id = (int)$_POST['village_id'];

error_log("DEBUG: upgrade_building.php - Variables set: user_id=" . $user_id . ", internal_name=" . $internal_name . ", current_level=" . $current_level . ", village_id=" . $village_id);

// Database connection provided by init.php
error_log("DEBUG: upgrade_building.php - Creating Managers");
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$villageManager = new VillageManager($conn);
$queueManager = new BuildingQueueManager($conn, $buildingConfigManager);
error_log("DEBUG: upgrade_building.php - Managers created");

try {
    error_log("DEBUG: upgrade_building.php - Inside try block");

    // Verify village ownership
    $villageData = $villageManager->getVillageInfo($village_id);
    if (!$villageData || $villageData['user_id'] != $user_id) {
         throw new Exception("You do not have access to this village.");
    }

    $building_config = $buildingConfigManager->getBuildingConfig($internal_name);
    if (!$building_config) {
        throw new Exception("Building configuration not found.");
    }
    $building_name = $building_config['name'];

    $actualCurrentLevel = $buildingManager->getBuildingLevel($village_id, $internal_name);
    if ($actualCurrentLevel !== $current_level) {
         throw new Exception("Building level mismatch. Current level is " . $actualCurrentLevel . ". Please refresh the page.");
    }

    $canUpgradeResult = $buildingManager->canUpgradeBuilding($village_id, $internal_name, $user_id);
    if (!$canUpgradeResult['success']) {
        throw new Exception($canUpgradeResult['message']);
    }

    // Use the new queue manager to enqueue the build
    $result = $queueManager->enqueueBuild($village_id, $internal_name, $user_id);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    $response = [
        'status' => 'success', 
        'message' => "Upgrade of " . htmlspecialchars($building_name) . " to level " . $result['level'] . " " . 
                     ($result['status'] === 'active' ? 'started' : 'queued') . "."
    ];

    error_log("DEBUG: upgrade_building.php - Success: " . $response['message']);
    
    header('Content-Type: application/json');
    
    // Get updated village info
    $updatedVillageInfo = $villageManager->getVillageInfo($village_id);
    if ($updatedVillageInfo) {
        $response['village_info'] = [
            'wood' => $updatedVillageInfo['wood'],
            'clay' => $updatedVillageInfo['clay'],
            'iron' => $updatedVillageInfo['iron'],
            'population' => $updatedVillageInfo['population'],
            'warehouse_capacity' => $updatedVillageInfo['warehouse_capacity'],
            'farm_capacity' => $updatedVillageInfo['farm_capacity']
        ];
    }
    
    // Add queue item details
    $response['building_queue_item'] = [
        'queue_item_id' => $result['queue_item_id'],
        'building_internal_name' => $result['building_internal_name'],
        'level' => $result['level'],
        'status' => $result['status'],
        'finish_time' => $result['finish_at']
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("DEBUG: upgrade_building.php - Error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => "Building upgrade failed: " . $e->getMessage()]);
    exit();
}
?>
