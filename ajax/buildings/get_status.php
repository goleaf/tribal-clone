<?php
declare(strict_types=1);
/**
 * AJAX - Fetch current building status.
 * Returns the current build queue state and related building details as JSON.
 */
require_once '../../init.php';
require_once '../../lib/utils/AjaxResponse.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('User is not logged in', null, 401);
}

try {
    // Get village ID
    $village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;
    
    // If no village ID is provided, fetch the user's first village
    if (!$village_id) {
        require_once '../../lib/managers/VillageManager.php';
        $villageManager = new VillageManager($conn);
        $village_id = $villageManager->getFirstVillage($_SESSION['user_id']);
        
        if (!$village_id) {
            AjaxResponse::error('Village not found', null, 404);
        }
    }
    
    // Confirm the village belongs to the logged-in user
    $stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $village_owner = $result->fetch_assoc();
    $stmt->close();
    
    if (!$village_owner || $village_owner['user_id'] != $_SESSION['user_id']) {
        AjaxResponse::error('No permission for this village', null, 403);
    }
    
    // Fetch build queue data
    $building_queue = [];
    $completed_count = 0;
    
    // Check for completed buildings
    $current_time = date('Y-m-d H:i:s');
    $stmt_completed = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM building_queue 
        WHERE village_id = ? AND finish_time <= ?
    ");
    $stmt_completed->bind_param("is", $village_id, $current_time);
    $stmt_completed->execute();
    $result_completed = $stmt_completed->get_result();
    $completed_row = $result_completed->fetch_assoc();
    $completed_count = $completed_row['count'];
    $stmt_completed->close();
    
    // Fetch active queue items
    $stmt_queue = $conn->prepare("
        SELECT bq.id, bq.building_type_id, bq.level, bq.starts_at, bq.finish_time, 
               bt.name as building_name, bt.internal_name
        FROM building_queue bq
        JOIN building_types bt ON bq.building_type_id = bt.id
        WHERE bq.village_id = ? AND bq.finish_time > ?
        ORDER BY bq.finish_time ASC
    ");
    $stmt_queue->bind_param("is", $village_id, $current_time);
    $stmt_queue->execute();
    $result_queue = $stmt_queue->get_result();
    
    while ($row = $result_queue->fetch_assoc()) {
        // Calculate remaining time and progress percentage
        $end_time = strtotime($row['finish_time']);
        $start_time = strtotime($row['starts_at']);
        $current_time_stamp = time();
        
        $total_duration = $end_time - $start_time;
        $elapsed_time = $current_time_stamp - $start_time;
        $remaining_time = $end_time - $current_time_stamp;
        
        $progress_percent = min(100, max(0, ($elapsed_time / $total_duration) * 100));
        
        $building_queue[] = [
            'id' => $row['id'],
            'building_type_id' => $row['building_type_id'],
            'building_name' => $row['building_name'],
            'internal_name' => $row['internal_name'],
            'level_after' => $row['level'],
            'level' => $row['level'],
            'finish_time' => $row['finish_time'],
            'ends_at' => $row['finish_time'], // alias for legacy consumers
            'starts_at' => $row['starts_at'],
            'remaining_time' => $remaining_time,
            'remaining_time_formatted' => formatTime($remaining_time),
            'progress_percent' => $progress_percent
        ];
    }
    $stmt_queue->close();
    
    // Return data as JSON
    AjaxResponse::success([
        'building_queue' => $building_queue,
        'completed_count' => $completed_count,
        'current_server_time' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Handle exception and return error
    AjaxResponse::handleException($e);
}
