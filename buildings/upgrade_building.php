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
error_log("DEBUG: upgrade_building.php - Managers created");

try {
    error_log("DEBUG: upgrade_building.php - Inside try block");
    $conn->begin_transaction();

    $villageData = $villageManager->getVillageInfo($village_id);
    if (!$villageData || $villageData['user_id'] != $user_id) {
         throw new Exception("You do not have access to this village.");
    }
    
    // === Check if the village already has a construction task in queue ===
    $existingQueueItem = $villageManager->getBuildingQueueItem($village_id); // Using VillageManager
    if ($existingQueueItem) {
        throw new Exception("Another construction is already in progress in this village. Wait for it to finish.");
    }
    // =========================================================================

    $building_config = $buildingConfigManager->getBuildingConfig($internal_name);
    if (!$building_config) {
        throw new Exception("Building configuration not found.");
    }
    if ($building_config['internal_name'] !== $internal_name) {
         throw new Exception("Invalid building type.");
    }
    $building_name = $building_config['name'];

    $actualCurrentLevel = $buildingManager->getBuildingLevel($village_id, $internal_name);
    if ($actualCurrentLevel !== $current_level) {
         throw new Exception("Building level mismatch. Current level is " . $actualCurrentLevel . ". Please refresh the page.");
    }

    $canUpgradeResult = $buildingManager->canUpgradeBuilding($village_id, $internal_name);
    if (!$canUpgradeResult['success']) {
        throw new Exception($canUpgradeResult['message']);
    }
    
    $next_level = $current_level + 1;
    $upgrade_costs = $buildingConfigManager->calculateUpgradeCost($internal_name, $current_level);
    $upgrade_time_seconds = $buildingConfigManager->calculateUpgradeTime($internal_name, $current_level, $buildingManager->getBuildingLevel($village_id, 'main_building'));

    if (!$upgrade_costs || $upgrade_time_seconds === null) {
        throw new Exception("Unable to calculate upgrade cost or time.");
    }

    $villageResources = $villageManager->getVillageInfo($village_id); // Get current resources
    if (!$villageResources) {
         throw new Exception("Failed to fetch current village resources.");
    }

    $newWood = $villageResources['wood'] - $upgrade_costs['wood'];
    $newClay = $villageResources['clay'] - $upgrade_costs['clay'];
    $newIron = $villageResources['iron'] - $upgrade_costs['iron'];

    $stmt_deduct = $conn->prepare("UPDATE villages SET wood = ?, clay = ?, iron = ? WHERE id = ?");
    if ($stmt_deduct === false) {
         throw new Exception("Database prepare failed for resource deduct: " . $conn->error);
    }
    $stmt_deduct->bind_param("iiii", $newWood, $newClay, $newIron, $village_id);
    if (!$stmt_deduct->execute()) {
         throw new Exception("Resource deduction query failed: " . $stmt_deduct->error);
    }
    $stmt_deduct->close();

    // Fetch village_building_id for this building in the village
    $villageBuilding = $buildingManager->getVillageBuilding($village_id, $internal_name);
    if (!$villageBuilding || !isset($villageBuilding['village_building_id'])) {
        throw new Exception("Building ID not found for this village.");
    }
    $village_building_id = $villageBuilding['village_building_id'];

    $finish_time = date('Y-m-d H:i:s', time() + $upgrade_time_seconds);
    $stmt_queue_add = $conn->prepare("
        INSERT INTO building_queue (village_id, village_building_id, building_type_id, level, starts_at, finish_time)
        VALUES (?, ?, (SELECT id FROM building_types WHERE internal_name = ?), ?, NOW(), ?)
    ");
    if ($stmt_queue_add === false) {
         throw new Exception("Database prepare failed for queue add: " . $conn->error);
    }
    $stmt_queue_add->bind_param("iiiis", $village_id, $village_building_id, $internal_name, $next_level, $finish_time);
    
    if (!$stmt_queue_add->execute()) {
        throw new Exception("Queue insertion failed: " . $stmt_queue_add->error);
    }
    $stmt_queue_add->close();

    $conn->commit();

    $response = ['status' => 'success', 'message' => "Upgrade of " . htmlspecialchars($building_name) . " to level " . $next_level . " started. Completion: " . formatTimeToHuman(strtotime($finish_time))];

    error_log("DEBUG: upgrade_building.php - Success: " . $response['message']);
    
    header('Content-Type: application/json');
    // Include updated resources in the AJAX response
    $response['new_resources'] = [
        'wood' => $newWood,
        'clay' => $newClay,
        'iron' => $newIron
    ];
    // Add building queue details
    $response['building_queue_item'] = [
        'building_internal_name' => $internal_name,
        'level' => $next_level,
        'finish_time' => strtotime($finish_time) // Return timestamp for JS
    ];
     // Include updated village info (warehouse/population caps)
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

    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    error_log("DEBUG: upgrade_building.php - Error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => "Building upgrade failed: " . $e->getMessage()]);
    exit();
}
?>
