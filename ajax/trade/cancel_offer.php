<?php
/**
 * Cancels an open trade offer and refunds resources.
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
    $offerId = isset($_POST['offer_id']) ? (int)$_POST['offer_id'] : 0;

    if ($villageId <= 0 || $offerId <= 0) {
        AjaxResponse::error('Invalid request data.');
    }

    $tradeManager = new TradeManager($conn);
    $result = $tradeManager->cancelOffer($userId, $villageId, $offerId);
    if (!$result['success']) {
        AjaxResponse::error($result['message'] ?? 'Could not cancel this offer.');
    }

    AjaxResponse::success(null, 'Offer canceled and resources returned.');
} catch (Throwable $e) {
    AjaxResponse::handleException($e);
}
