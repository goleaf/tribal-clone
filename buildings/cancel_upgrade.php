<?php
require '../init.php';
validateCSRF();
header('Content-Type: text/html; charset=UTF-8'); // Return HTML after POST

// Configuration and DB connection provided by init.php
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'BuildingManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'VillageManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'BuildingConfigManager.php'; // Need BuildingConfigManager

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'You are not logged in.']);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>You must be logged in to perform this action.</p>";
        header("Location: ../auth/login.php");
    }
    exit();
}

// Validate queue item ID
if (!isset($_POST['queue_item_id']) || !is_numeric($_POST['queue_item_id'])) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid queue item ID.']);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Invalid queue item ID.</p>";
        header("Location: ../game/game.php");
    }
    exit();
}

$queue_item_id = (int)$_POST['queue_item_id'];
$user_id = $_SESSION['user_id'];

$buildingManager = new BuildingManager($conn);
$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);

try {
    // Fetch queue item and ensure it belongs to the user's village
    $stmt = $conn->prepare("
        SELECT bq.id, bq.village_id, bq.village_building_id, bt.name, bq.building_type_id, bq.level
        FROM building_queue bq
        JOIN building_types bt ON bq.building_type_id = bt.id
        JOIN villages v ON bq.village_id = v.id
        WHERE bq.id = ? AND v.user_id = ?
    ");
    $stmt->bind_param("ii", $queue_item_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Task does not exist or you do not have access to it.']);
        } else {
            $_SESSION['game_message'] = "<p class='error-message'>Task does not exist or you do not have access to it.</p>";
            header("Location: ../game/game.php");
        }
        $stmt->close();
        exit();
    }

    $queue_item = $result->fetch_assoc();
    $stmt->close();
    
    // Fetch building details for the cancelled task (to compute costs)
    $building_type_id = $queue_item['building_type_id']; // building_queue should include building_type_id
    $cancelled_level = $queue_item['level'];
    
    // Get internal_name from building_types by ID
    $stmt_get_internal_name = $conn->prepare("SELECT internal_name FROM building_types WHERE id = ?");
    $stmt_get_internal_name->bind_param("i", $building_type_id);
    $stmt_get_internal_name->execute();
    $building_type_row = $stmt_get_internal_name->get_result()->fetch_assoc();
    $stmt_get_internal_name->close();
    
    if (!$building_type_row) {
         throw new Exception("Building type not found for the cancelled task.");
    }
    $cancelled_building_internal_name = $building_type_row['internal_name'];

    // Calculate upgrade cost for the cancelled level (previous level + 1)
    $cost_level_before_cancel = $cancelled_level - 1; 
    $upgrade_costs = $buildingConfigManager->calculateUpgradeCost($cancelled_building_internal_name, $cost_level_before_cancel);
    
    if (!$upgrade_costs) {
         error_log("Error calculating costs for cancelled build: " . $cancelled_building_internal_name . " level " . $cancelled_level);
         // Continue removal even if costs cannot be calculated
    }
    
    // Transaction for atomic queue removal and resource refund
    $conn->begin_transaction();

    // Delete task from queue
    $stmt_delete = $conn->prepare("DELETE FROM building_queue WHERE id = ?");
    $stmt_delete->bind_param("i", $queue_item_id);
    $success = $stmt_delete->execute();
    $stmt_delete->close();

    if (!$success) {
         throw new Exception("Failed to remove task from queue.");
    }

    // Refund part of the resources if costs were available
    if ($upgrade_costs) {
        $return_percentage = 0.9; // 90% refund
        $returned_wood = floor($upgrade_costs['wood'] * $return_percentage);
        $returned_clay = floor($upgrade_costs['clay'] * $return_percentage);
        $returned_iron = floor($upgrade_costs['iron'] * $return_percentage);
        
        // Add resources back to the village
        $stmt_add_resources = $conn->prepare("
            UPDATE villages 
            SET wood = wood + ?, clay = clay + ?, iron = iron + ? 
            WHERE id = ?
        ");
        $stmt_add_resources->bind_param("iiii", $returned_wood, $returned_clay, $returned_iron, $queue_item['village_id']);
        
        if (!$stmt_add_resources->execute()) {
            // Log error but do not rollback queue deletion
            error_log("Error refunding resources for cancelled task ID " . $queue_item_id . ": " . $conn->error);
        }
         $stmt_add_resources->close();
    }

    // Commit transaction
    $conn->commit();

    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        // Fetch updated village resources after refund
        $updatedVillageInfo = $villageManager->getVillageInfo($queue_item['village_id']);
        $response = [
            'success' => true,
            'message' => 'Construction task cancelled. A portion of the resources has been refunded.',
            'queue_item_id' => $queue_item_id, // Return cancelled task ID
            'village_id' => $queue_item['village_id'],
            'village_building_id' => $queue_item['village_building_id'],
            'building_internal_name' => $cancelled_building_internal_name, // Return internal_name
            'new_resources' => null // Optionally return updated resources via resource updater
        ];
         if ($updatedVillageInfo) {
             // Return current resources, population, and capacities
             $response['village_info'] = [
                 'wood' => $updatedVillageInfo['wood'],
                 'clay' => $updatedVillageInfo['clay'],
                 'iron' => $updatedVillageInfo['iron'],
                 'population' => $updatedVillageInfo['population'], // Could change if farm was cancelled
                 'warehouse_capacity' => $updatedVillageInfo['warehouse_capacity'],
                 'farm_capacity' => $updatedVillageInfo['farm_capacity']
             ];
         }

        echo json_encode($response);
    } else {
        $_SESSION['game_message'] = "<p class='success-message'>Construction task cancelled. A portion of the resources has been refunded.</p>";
        header("Location: ../game/game.php");
    }

} catch (Exception $e) {
    $conn->rollback(); // Roll back on error
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>An error occurred: " . htmlspecialchars($e->getMessage()) . "</p>";
        header("Location: ../game/game.php");
    }
}

$conn->close();
?> 
