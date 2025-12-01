<?php
declare(strict_types=1);

require_once '../../init.php';
require_once '../../lib/functions.php';
require_once '../../lib/managers/VillageManager.php';
require_once '../../lib/managers/BuildingConfigManager.php';
require_once '../../lib/managers/BuildingManager.php';
require_once '../../lib/managers/BattleManager.php';
require_once '../../lib/managers/ReportStateManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You are not logged in.']);
    exit();
}

validateCSRF();

$reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
$starred = isset($_POST['starred']) && (int)$_POST['starred'] === 1;
$userId = (int)$_SESSION['user_id'];

if ($reportId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing report ID.']);
    exit();
}

$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);
$stateManager = new ReportStateManager($conn);

if (!$battleManager->userCanViewReport($reportId, $userId)) {
    echo json_encode(['status' => 'error', 'message' => 'Report not found or access denied.']);
    exit();
}

$updated = $stateManager->setStarred($reportId, $userId, $starred);

if ($updated) {
    echo json_encode(['status' => 'success', 'starred' => $starred ? 1 : 0]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Could not update report star.']);
}

if (method_exists($conn, 'close')) {
    $conn->close();
}
