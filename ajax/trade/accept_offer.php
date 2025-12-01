<?php
/**
 * Accepts an open trade offer.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils/AjaxResponse.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils/EconomyError.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/RateLimiter.php';
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
    $limiter = new RateLimiter($conn);
    $windowSeconds = 10;
    $maxRequests = 6;
    $rateKey = "trade_accept_user_{$userId}";
    if (!$limiter->allow($rateKey, $maxRequests, $windowSeconds)) {
        AjaxResponse::error('Too many trade actions. Please wait a moment.', ['retry_after_sec' => $windowSeconds], 429, EconomyError::ERR_RATE_LIMIT);
    }

    $villageId = isset($_POST['village_id']) ? (int)$_POST['village_id'] : 0;
    $offerId = isset($_POST['offer_id']) ? (int)$_POST['offer_id'] : 0;

    if ($villageId <= 0 || $offerId <= 0) {
        AjaxResponse::error('Invalid request data.', null, 400, 'ERR_INPUT');
    }

    $villageManager = new VillageManager($conn);
    $tradeManager = new TradeManager($conn);

    // Refresh resources before attempting to accept
    $villageManager->updateResources($villageId);

    $result = $tradeManager->acceptOffer($userId, $villageId, $offerId);
    if (!$result['success']) {
        AjaxResponse::error($result['message'] ?? 'Could not accept this offer.', null, 400, $result['code'] ?? null);
    }

    AjaxResponse::success(
        [
            'arrival_time' => $result['arrival_time'],
            'traders_used' => $result['traders_used']
        ],
        'Offer accepted. Traders are on the way.'
    );
} catch (Throwable $e) {
    AjaxResponse::handleException($e);
}
