<?php
require '../init.php';
require_once '../lib/managers/UnitManager.php';
require_once '../lib/utils/AjaxResponse.php';
require_once '../lib/managers/VillageManager.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('User is not logged in', null, 401);
}
$user_id = $_SESSION['user_id'];

try {
    // Fetch the player's village (needed for validation)
    $village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;

    if (!$village_id) {
        // If village_id is not provided, try to fetch the user's first village
        $villageManager = new VillageManager($conn);
        $village_data = $villageManager->getFirstVillage($user_id);
        if (!$village_data) {
            AjaxResponse::error('No village found for the user', null, 404);
        }
        $village_id = $village_data['id'];
    } else {
        // If village_id is provided, confirm it belongs to the user
        $villageManager = new VillageManager($conn);
        $village_data = $villageManager->getVillageInfo($village_id);
        if (!$village_data || $village_data['user_id'] != $user_id) {
            AjaxResponse::error('You do not have access to this village', null, 403);
        }
    }

    $unitManager = new UnitManager($conn);
    // Fetch recruitment queues (optionally for a specific building type, e.g., barracks)
    // For now we pull all queues for the village; the frontend can filter or we can add a building_type parameter
    $queue = $unitManager->getRecruitmentQueues($village_id);

    // Prepare data for JSON response and include needed fields such as timestamps
    $queue_data = [];
    foreach ($queue as $item) {
        $queue_data[] = [
            'id' => (int)$item['id'],
            'unit_type_id' => (int)$item['unit_type_id'],
            'count' => (int)$item['count'],
            'count_finished' => (int)$item['count_finished'],
            'started_at' => strtotime($item['started_at']), // Timestamp
            'finish_at' => strtotime($item['finish_at']), // Timestamp
            'building_type' => $item['building_type'],
            'unit_name' => $item['name'],
            'unit_internal_name' => $item['internal_name']
            // icon_url can be added if UnitManager returns it
        ];
    }

    // Return JSON data
    AjaxResponse::success([
        'village_id' => $village_id,
        'queue' => $queue_data,
        'current_server_time' => time() // Add current server timestamp
    ]);

} catch (Exception $e) {
    // Handle the exception and return an error JSON
    AjaxResponse::error(
        'An error occurred while fetching the recruitment queue: ' . $e->getMessage(),
        ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()],
        500 // HTTP status code
    );
}

// Database connection is managed by init.php
// $conn->close(); // Removed
?> 
