<?php
require_once '../../init.php';
require_once __DIR__ . '/../../lib/managers/UnitManager.php';
require_once __DIR__ . '/../../lib/managers/VillageManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../../lib/managers/ResourceManager.php';

function logRecruitTelemetry(int $userId, int $villageId, int $unitId, int $count, string $status, string $code, string $message): void
{
    $logFile = __DIR__ . '/../../logs/recruit_telemetry.log';
    $entry = [
        'ts' => date('c'),
        'user_id' => $userId,
        'village_id' => $villageId,
        'unit_id' => $unitId,
        'count' => $count,
        'status' => $status,
        'code' => $code,
        'message' => $message
    ];
    $line = json_encode($entry) . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// Initialize managers
$unitManager = new UnitManager($conn);
$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$resourceManager = new ResourceManager($conn, $buildingManager);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$village_id = $_GET['village_id'] ?? null;
$building_internal_name = $_GET['building'] ?? null;

if (!$village_id || !$building_internal_name) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters.']);
    exit();
}

// Verify that the user owns the village
$village = $villageManager->getVillageInfo($village_id);
if (!$village || $village['user_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not own this village.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET request: Display the recruitment panel
    try {
        // Get building details
        $building_level = $buildingManager->getBuildingLevel($village_id, $building_internal_name);
        if ($building_level === 0) {
            echo "<p>You must build the {$building_internal_name} first.</p>";
            exit();
        }

        // Get available units for this building
        $available_units = $unitManager->getAvailableUnitsByBuilding($building_internal_name, $building_level);

        // Get current units in the village
        $village_units = $unitManager->getVillageUnits($village_id);

        // Get recruitment queue for this building
        $recruitment_queue = $unitManager->getRecruitmentQueues($village_id, $building_internal_name);

        // Render the recruitment panel view
        include '../../buildings/recruit_panel.php';

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle POST request: Process recruitment
    $data = json_decode(file_get_contents('php://input'), true);
    $unit_id = $data['unit_id'] ?? null;
    $count = $data['count'] ?? null;

    if (!$unit_id || !$count || !is_numeric($count) || $count <= 0) {
        http_response_code(400);
        $msg = 'Invalid input.';
        echo json_encode(['error' => $msg, 'code' => 'ERR_INPUT']);
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, (int)$count, 'fail', 'ERR_INPUT', $msg);
        exit();
    }

    $count = intval($count);
    $building_level = $buildingManager->getBuildingLevel($village_id, $building_internal_name);

    $unitMeta = $unitManager->getUnitById($unit_id);
    $unitInternal = $unitMeta['internal_name'] ?? '';

    // Check requirements
    $requirements = $unitManager->checkRecruitRequirements($unit_id, $village_id);
    if (!$requirements['can_recruit']) {
        http_response_code(400);
        $msg = "Cannot recruit unit: " . $requirements['reason'];
        echo json_encode(['error' => $msg, 'code' => 'ERR_PREREQ']);
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_PREREQ', $msg);
        exit();
    }

    // Extra nobleman requirements
    if (in_array($unitInternal, ['noble', 'nobleman', 'nobleman_unit'], true)) {
        $statueLevel = $buildingManager->getBuildingLevel($village_id, 'statue');
        $academyLevel = $buildingManager->getBuildingLevel($village_id, 'academy');
        $smithyLevel = $buildingManager->getBuildingLevel($village_id, 'smithy');
        $marketLevel = $buildingManager->getBuildingLevel($village_id, 'market');
        if ($statueLevel <= 0 || $academyLevel < 1 || $smithyLevel < 20 || $marketLevel < 10) {
            http_response_code(400);
            $msg = 'Noble requirements not met (statue, academy 1, smithy 20, market 10).';
            echo json_encode(['error' => $msg, 'code' => 'ERR_PREREQ']);
            logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_PREREQ', $msg);
            exit();
        }
        $userNobles = $unitManager->countUserNobles($user_id);
        $maxNobles = $unitManager->getMaxNoblesForUser($user_id);
        if ($userNobles + $count > $maxNobles) {
            http_response_code(400);
            $msg = 'Noble cap reached';
            echo json_encode(['error' => $msg, 'code' => 'ERR_CAP', 'max_nobles' => $maxNobles, 'current_nobles' => $userNobles]);
            logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_CAP', $msg);
            exit();
        }
        // Coin check
        $stmtCoins = $conn->prepare("SELECT coins FROM villages WHERE id = ?");
        $stmtCoins->bind_param("i", $village_id);
        $stmtCoins->execute();
        $rowCoins = $stmtCoins->get_result()->fetch_assoc();
        $stmtCoins->close();
        $coinsAvailable = (int)($rowCoins['coins'] ?? 0);
        if ($coinsAvailable < $count) {
            http_response_code(400);
            $msg = 'Not enough coins';
            echo json_encode(['error' => $msg, 'code' => 'ERR_RES', 'coins' => $coinsAvailable]);
            logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_RES', $msg);
            exit();
        }
    }

    // Check resources
    $resource_check = $unitManager->checkResourcesForRecruitment($unit_id, $count, $village);
    if (!$resource_check['can_afford']) {
        http_response_code(400);
        $msg = 'Not enough resources.';
        echo json_encode([
            'error' => $msg,
            'code' => 'ERR_RES',
            'missing' => $resource_check['missing'] ?? null
        ]);
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_RES', $msg);
        exit();
    }

    // Use a transaction to ensure atomicity
    $conn->begin_transaction();
    try {
        // Deduct resources via ResourceManager (validates again)
        $costs = $resource_check['total_costs'];
        $spend = $resourceManager->spendResources($village_id, $costs);
        if (!$spend['success']) {
            throw new Exception($spend['message']);
        }

        if (in_array($unitInternal, ['noble', 'nobleman', 'nobleman_unit'], true)) {
            $stmtDeductCoin = $conn->prepare("UPDATE villages SET coins = coins - ? WHERE id = ? AND coins >= ?");
            $stmtDeductCoin->bind_param("iii", $count, $village_id, $count);
            $stmtDeductCoin->execute();
            if ($stmtDeductCoin->affected_rows === 0) {
                throw new Exception('Not enough coins to recruit noble.');
            }
            $stmtDeductCoin->close();
        }

        // Add to recruitment queue
        $result = $unitManager->recruitUnits($village_id, $unit_id, $count, $building_level);

        if ($result['success']) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => $result['message'], 'resources' => $spend['resources']]);
            logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'success', 'OK', $result['message']);
        } else {
            throw new Exception($result['error']);
        }
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        $msg = 'An error occurred during recruitment: ' . $e->getMessage();
        echo json_encode(['error' => $msg, 'code' => 'ERR_SERVER']);
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_SERVER', $msg);
    }
}
?>
