<?php
declare(strict_types=1);
/**
 * AJAX Endpoint: Enqueue a building upgrade
 * 
 * Requirements: 1.1, 7.1, 8.4
 * - Validates CSRF token
 * - Validates ownership
 * - Returns structured JSON with error codes
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

// CSRF validation (handled by init.php for POST requests)
// validateCSRF() is already called in init.php

$userId = (int)$_SESSION['user_id'];
$villageId = isset($_POST['village_id']) ? (int)$_POST['village_id'] : null;
$buildingInternalName = isset($_POST['building_internal_name']) ? trim($_POST['building_internal_name']) : null;

// Validate required parameters
if (!$villageId || !$buildingInternalName) {
    AjaxResponse::error('Missing required parameters (village_id, building_internal_name).', null, 400, 'ERR_INPUT');
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
    
    // Enqueue the build
    $result = $queueManager->enqueueBuild($villageId, $buildingInternalName, $userId);
    
    if ($result['success']) {
        AjaxResponse::success([
            'queue_item_id' => $result['queue_item_id'],
            'status' => $result['status'],
            'start_at' => $result['start_at'],
            'finish_at' => $result['finish_at'],
            'level' => $result['level'],
            'building_internal_name' => $result['building_internal_name']
        ], 'Building upgrade queued successfully.');
    } else {
        // Return error with appropriate error code
        $errorCode = $result['error_code'] ?? 'ERR_SERVER';
        $httpCode = match($errorCode) {
            'ERR_RES' => 400,
            'ERR_CAP', 'ERR_QUEUE_CAP' => 400,
            'ERR_PREREQ' => 400,
            'ERR_PROTECTED' => 403,
            default => 500
        };
        
        AjaxResponse::error($result['message'], null, $httpCode, $errorCode);
    }
    
} catch (Exception $e) {
    AjaxResponse::handleException($e);
}
