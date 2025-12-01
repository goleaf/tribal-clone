<?php
/**
 * Creates a new player-to-player trade offer.
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

    $offerResources = [
        'wood' => isset($_POST['offer_wood']) ? (int)$_POST['offer_wood'] : 0,
        'clay' => isset($_POST['offer_clay']) ? (int)$_POST['offer_clay'] : 0,
        'iron' => isset($_POST['offer_iron']) ? (int)$_POST['offer_iron'] : 0,
    ];
    $requestResources = [
        'wood' => isset($_POST['request_wood']) ? (int)$_POST['request_wood'] : 0,
        'clay' => isset($_POST['request_clay']) ? (int)$_POST['request_clay'] : 0,
        'iron' => isset($_POST['request_iron']) ? (int)$_POST['request_iron'] : 0,
    ];

    if ($villageId <= 0) {
        AjaxResponse::error('Invalid village selected.', null, 400, 'ERR_INPUT');
    }

    $villageManager = new VillageManager($conn);
    $tradeManager = new TradeManager($conn);

    // Refresh resources before validation
    $villageManager->updateResources($villageId);

    $result = $tradeManager->createOffer($userId, $villageId, $offerResources, $requestResources);
    if (!$result['success']) {
        AjaxResponse::error(
            $result['message'] ?? 'Could not create offer.',
            null,
            400,
            $result['code'] ?? null
        );
    }

    AjaxResponse::success(['offer_id' => $result['offer_id']], 'Offer created.');
} catch (Throwable $e) {
    AjaxResponse::handleException($e);
}
