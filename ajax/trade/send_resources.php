<?php
/**
 * Starts a resource transport between villages.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils/AjaxResponse.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/VillageManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/TradeManager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('You are not logged in.', null, 401);
}

try {
    $userId = (int)$_SESSION['user_id'];
    $villageId = isset($_POST['village_id']) ? (int)$_POST['village_id'] : 0;
    $targetCoords = trim($_POST['target_coords'] ?? '');
    $resources = [
        'wood' => isset($_POST['wood']) ? (int)$_POST['wood'] : 0,
        'clay' => isset($_POST['clay']) ? (int)$_POST['clay'] : 0,
        'iron' => isset($_POST['iron']) ? (int)$_POST['iron'] : 0,
    ];

    if ($villageId <= 0) {
        AjaxResponse::error('Invalid village selected.');
    }

    $villageManager = new VillageManager($conn);
    $tradeManager = new TradeManager($conn);

    // Refresh resources before validating amounts
    $villageManager->updateResources($villageId);

    $result = $tradeManager->sendResources($userId, $villageId, $targetCoords, $resources);
    if (!$result['success']) {
        AjaxResponse::error(
            $result['message'] ?? 'Could not send resources.',
            null,
            400,
            $result['code'] ?? null
        );
    }

    $updatedVillage = $villageManager->getVillageInfo($villageId);

    AjaxResponse::success(
        [
            'route_id' => $result['route_id'],
            'arrival_time' => $result['arrival_time'],
            'traders_used' => $result['traders_used'],
            'village_info' => [
                'wood' => $updatedVillage['wood'],
                'clay' => $updatedVillage['clay'],
                'iron' => $updatedVillage['iron']
            ]
        ],
        'Resources are on their way.'
    );
} catch (Throwable $e) {
    AjaxResponse::handleException($e);
}
