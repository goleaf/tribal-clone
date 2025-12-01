<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') validateCSRF();

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'utils' . DIRECTORY_SEPARATOR . 'AjaxResponse.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['ajax'])) {
        AjaxResponse::error('You are not logged in.', null, 401);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>You must be logged in to perform this action.</p>";
        header('Location: ../auth/login.php');
    }
    exit();
}

$user_id = $_SESSION['user_id'];

// Validate queue item id
if (!isset($_POST['queue_item_id']) || !is_numeric($_POST['queue_item_id'])) {
    if (isset($_POST['ajax'])) {
        AjaxResponse::error('Invalid recruitment queue ID.', null, 400);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Invalid recruitment queue ID.</p>";
        header('Location: ../game/game.php');
    }
    exit();
}

$queue_item_id = (int)$_POST['queue_item_id'];

$unitManager = new UnitManager($conn);
$villageManager = new VillageManager($conn);
$unitConfigManager = new UnitConfigManager($conn);

try {
    // Fetch queue item and ensure it belongs to the logged-in user's village
    $stmt = $conn->prepare("
        SELECT uq.id, uq.village_id, uq.unit_type_id, uq.count, uq.count_finished, ut.name, ut.internal_name
        FROM unit_queue uq
        JOIN unit_types ut ON uq.unit_type_id = ut.id
        JOIN villages v ON uq.village_id = v.id
        WHERE uq.id = ? AND v.user_id = ?
    ");

    $stmt->bind_param('ii', $queue_item_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        if (isset($_POST['ajax'])) {
            AjaxResponse::error('The recruitment task does not exist or you do not have access to it.', null, 404);
        } else {
            $_SESSION['game_message'] = "<p class='error-message'>The recruitment task does not exist or you do not have access to it.</p>";
            header('Location: ../game/game.php');
        }
        $stmt->close();
        exit();
    }

    $queue = $result->fetch_assoc();
    $stmt->close();

    $village_id = $queue['village_id'];
    $unit_type_id = $queue['unit_type_id'];
    $total_count = $queue['count'];
    $finished_count = $queue['count_finished'];
    $unit_name = $queue['name'];
    $unit_internal_name = $queue['internal_name'];

    // Units to cancel (not yet finished)
    $to_cancel_count = $total_count - $finished_count;

    $conn->begin_transaction();

    // Delete queue entry
    $stmt_delete = $conn->prepare('DELETE FROM unit_queue WHERE id = ?');
    $stmt_delete->bind_param('i', $queue_item_id);
    $success = $stmt_delete->execute();

    if (!$success) {
        throw new Exception('Error while removing the recruitment task from the queue.');
    }

    // Return part of the resources and population for canceled units
    $unit_config = $unitConfigManager->getUnitConfig($unit_internal_name);
    if ($unit_config) {
        $return_percentage = 0.9; // 90% refund

        $returned_wood = floor($unit_config['cost_wood'] * $to_cancel_count * $return_percentage);
        $returned_clay = floor($unit_config['cost_clay'] * $to_cancel_count * $return_percentage);
        $returned_iron = floor($unit_config['cost_iron'] * $to_cancel_count * $return_percentage);
        $returned_population = floor($unit_config['population'] * $to_cancel_count * $return_percentage);

        $stmt_update_village = $conn->prepare('
            UPDATE villages 
            SET wood = wood + ?, clay = clay + ?, iron = iron + ?, population = population - ? 
            WHERE id = ?
        ');

        $stmt_update_village->bind_param('ddiii', $returned_wood, $returned_clay, $returned_iron, $returned_population, $village_id);

        if (!$stmt_update_village->execute()) {
            // Log the error but keep the queue removal
            error_log('Error while refunding resources/population for canceled recruitment queue ID ' . $queue_item_id . ': ' . $conn->error);
        }
    } else {
        error_log('Missing unit configuration for refund calculation: ' . $unit_internal_name);
    }

    // Add any finished units to the village
    if ($finished_count > 0) {
        $stmt_add_units = $conn->prepare('
            INSERT INTO village_units (village_id, unit_type_id, count) 
            VALUES (?, ?, ?) 
            ON CONFLICT(village_id, unit_type_id) DO UPDATE SET count = count + excluded.count
        ');
        $stmt_add_units->bind_param('iii', $village_id, $unit_type_id, $finished_count);
        if (!$stmt_add_units->execute()) {
            error_log('Error while adding finished units after canceling recruitment: ' . $conn->error);
        }
        $message_finished = ". Added $finished_count finished units to the village.";
    } else {
        $message_finished = '.';
    }

    $conn->commit();

    if (isset($_POST['ajax'])) {
        $updatedVillageInfo = $villageManager->getVillageInfo($village_id);
        AjaxResponse::success([
            'success' => true,
            'message' => "Canceled recruitment of {$unit_name} (x$to_cancel_count)$message_finished",
            'queue_item_id' => $queue_item_id,
            'village_id' => $village_id,
            'unit_internal_name' => $unit_internal_name,
            'village_info' => $updatedVillageInfo
        ]);
    } else {
        $_SESSION['game_message'] = "<p class='success-message'>Canceled recruitment of {$unit_name} (x$to_cancel_count)$message_finished</p>";
        header('Location: ../game/game.php');
    }

} catch (Exception $e) {
    $conn->rollback();

    if (isset($_POST['ajax'])) {
        AjaxResponse::error(
            'An error occurred while canceling recruitment: ' . $e->getMessage(),
            ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()],
            500
        );
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>An error occurred while canceling recruitment: " . htmlspecialchars($e->getMessage()) . "</p>";
        header('Location: ../game/game.php');
    }
} finally {
    if (isset($stmt_delete) && $stmt_delete !== null) { $stmt_delete->close(); }
    if (isset($stmt_update_village) && $stmt_update_village !== null) { $stmt_update_village->close(); }
    if (isset($stmt_add_units) && $stmt_add_units !== null) { $stmt_add_units->close(); }
    // init.php manages the connection lifecycle
    // $conn->close();
}
?>
