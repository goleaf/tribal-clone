<?php
/**
 * Cancel an outgoing command and send a return march.
 */
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/utils/AjaxResponse.php';
require_once __DIR__ . '/../../lib/managers/VillageManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../../lib/managers/BattleManager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('You are not logged in.', null, 401);
}

try {
    $userId = (int)$_SESSION['user_id'];
    $attackId = isset($_POST['attack_id']) ? (int)$_POST['attack_id'] : 0;
    if ($attackId <= 0) {
        AjaxResponse::error('Invalid attack id.');
    }

    $villageManager = new VillageManager($conn);
    $buildingConfig = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfig);
    $battleManager = new BattleManager($conn, $villageManager, $buildingManager);

    $result = $battleManager->cancelAttack($attackId, $userId);
    if (!$result['success']) {
        AjaxResponse::error($result['error'] ?? 'Could not cancel command.');
    }

    AjaxResponse::success(
        [
            'return_attack_id' => $result['return_attack_id'] ?? null,
            'return_arrival' => $result['return_arrival'] ?? null
        ],
        $result['message'] ?? 'Command canceled.'
    );
} catch (Throwable $e) {
    AjaxResponse::handleException($e);
}
