<?php
declare(strict_types=1);
/**
 * AJAX Endpoint: Get building queue for a village
 * 
 * Requirements: 6.1, 6.2, 6.3, 6.5, 7.1, 8.4
 * - Returns all active and pending queue items
 * - Ordered by start time ascending
 * - Includes building name, level, status, finish time
 * - Validates ownership
 * - Returns structured JSON
 */

require_once '../../init.php';
require_once '../../lib/managers/BuildingQueueManager.php';
require_once '../../lib/managers/BuildingConfigManager.php';
require_once '../../lib/utils/AjaxResponse.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('You are not logged in.', null, 401);
}

$userId = (int)$_SESSION['user_id'];
$villageId = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;

// Validate required parameters
if (!$villageId) {
    AjaxResponse::error('Missing required parameter (village_id).', null, 400, 'ERR_INPUT');
}

try {
    // Verify village ownership
    $stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
    $stmt->bind_param("i", $villageId);
    $stmt->execute();
    $village = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$village || $village['user_id'] !== $userId) {
        AjaxResponse::error('No access to this village.', null, 403, 'ERR_PREREQ');
    }
    
    // Initialize managers
    $configManager = new BuildingConfigManager($conn);
    $queueManager = new BuildingQueueManager($conn, $configManager);
    
    // Get queue items (Requirements 6.1, 6.2, 6.3)
    $queueItems = $queueManager->getVillageQueue($villageId);
    
    // Filter to active and pending only (Requirement 6.1)
    $filteredQueue = array_filter($queueItems, function($item) {
        return in_array($item['status'], ['active', 'pending']);
    });
    
    // Sort by starts_at ascending (Requirement 6.3)
    usort($filteredQueue, function($a, $b) {
        return strtotime($a['starts_at']) <=> strtotime($b['starts_at']);
    });
    
    // Format response with required fields (Requirement 6.2)
    $formattedQueue = [];
    $position = 1;
    foreach ($filteredQueue as $item) {
        // Get building name
        $stmt = $conn->prepare("SELECT name, internal_name FROM building_types WHERE id = ?");
        $stmt->bind_param("i", $item['building_type_id']);
        $stmt->execute();
        $buildingInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $formattedQueue[] = [
            'id' => $item['id'],
            'building_name' => $buildingInfo['name'] ?? 'Unknown',
            'building_internal_name' => $buildingInfo['internal_name'] ?? 'unknown',
            'level' => $item['level'],
            'status' => $item['status'],
            'starts_at' => strtotime($item['starts_at']),
            'finish_time' => strtotime($item['finish_time']),
            'position' => $item['status'] === 'pending' ? $position : null // Requirement 6.5
        ];
        
        if ($item['status'] === 'pending') {
            $position++;
        }
    }
    
    AjaxResponse::success([
        'queue' => $formattedQueue,
        'config_version' => $configManager->getConfigVersion()
    ]);
    
} catch (Exception $e) {
    AjaxResponse::handleException($e);
}

