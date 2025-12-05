<?php
declare(strict_types=1);
/**
 * AJAX Endpoint: Cancel a queued building upgrade
 * 
 * Requirements: 3.1, 7.1, 8.4
 * - Validates CSRF token
 * - Validates ownership
 * - Returns structured JSON with error codes
 * - Refunds 90% of resources
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
$queueItemId = isset($_POST['queue_item_id']) ? (int)$_POST['queue_item_id'] : null;

// Validate required parameters
if (!$queueItemId) {
    AjaxResponse::error('Missing required parameter (queue_item_id).', null, 400, 'ERR_INPUT');
}

try {
    // Initialize managers
    $configManager = new BuildingConfigManager($conn);
    $queueManager = new BuildingQueueManager($conn, $configManager);
    
    // Cancel the build (ownership validation is done inside cancelBuild)
    $result = $queueManager->cancelBuild($queueItemId, $userId);
    
    if ($result['success']) {
        AjaxResponse::success([
            'refund' => $result['refund']
        ], 'Building upgrade canceled successfully. Resources refunded (90%).');
    } else {
        // Return error
        AjaxResponse::error($result['message'], null, 400, 'ERR_PREREQ');
    }
    
} catch (Exception $e) {
    AjaxResponse::handleException($e);
}
