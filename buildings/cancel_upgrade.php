<?php
require '../init.php';
validateCSRF();
header('Content-Type: text/html; charset=UTF-8'); // Return HTML after POST

// Configuration and DB connection provided by init.php
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'BuildingManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'VillageManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'BuildingConfigManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'BuildingQueueManager.php';

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
$queueManager = new BuildingQueueManager($conn, $buildingConfigManager);

try {
    // Use the queue manager to cancel the build
    $result = $queueManager->cancelBuild($queue_item_id, $user_id);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    // Fetch queue item details for response
    $stmt = $conn->prepare("
        SELECT bq.village_id, bq.village_building_id, bt.internal_name
        FROM building_queue bq
        JOIN building_types bt ON bq.building_type_id = bt.id
        WHERE bq.id = ?
    ");
    $stmt->bind_param("i", $queue_item_id);
    $stmt->execute();
    $queue_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // If queue item was already deleted, fetch from result
    $village_id = $queue_item ? $queue_item['village_id'] : null;

    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'message' => 'Construction task cancelled. 90% of resources have been refunded.',
            'queue_item_id' => $queue_item_id
        ];
        
        if ($village_id) {
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
        }

        echo json_encode($response);
    } else {
        $_SESSION['game_message'] = "<p class='success-message'>Construction task cancelled. 90% of resources have been refunded.</p>";
        header("Location: ../game/game.php");
    }

} catch (Exception $e) {
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
