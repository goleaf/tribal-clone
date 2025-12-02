<?php
require_once '../../init.php';
require_once __DIR__ . '/../../lib/managers/UnitManager.php';
require_once __DIR__ . '/../../lib/managers/VillageManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../../lib/managers/ResourceManager.php';

function logRecruitTelemetry(int $userId, int $villageId, int $unitId, int $count, string $status, string $code, string $message, ?int $worldId = null): void
{
    $logFile = __DIR__ . '/../../logs/recruit_telemetry.log';
    
    // Get world ID if not provided
    if ($worldId === null) {
        $worldId = defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1;
    }
    
    $entry = [
        'ts' => date('c'),
        'user_id' => $userId,
        'village_id' => $villageId,
        'world_id' => $worldId,
        'unit_id' => $unitId,
        'count' => $count,
        'status' => $status,
        'code' => $code,
        'message' => $message
    ];
    $line = json_encode($entry) . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function incrementCapHitCounter(int $unitId, int $worldId, string $unitInternal = ''): void
{
    $counterFile = __DIR__ . '/../../logs/cap_hit_counters.log';
    $entry = [
        'ts' => date('c'),
        'world_id' => $worldId,
        'unit_id' => $unitId,
        'unit_internal' => $unitInternal,
        'event' => 'cap_hit'
    ];
    $line = json_encode($entry) . PHP_EOL;
    @file_put_contents($counterFile, $line, FILE_APPEND | LOCK_EX);
}

function incrementErrorCounter(string $errorCode): void
{
    $counterFile = __DIR__ . '/../../logs/error_counters.log';
    $entry = [
        'ts' => date('c'),
        'error_code' => $errorCode,
        'event' => 'error'
    ];
    $line = json_encode($entry) . PHP_EOL;
    @file_put_contents($counterFile, $line, FILE_APPEND | LOCK_EX);
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

    // Get world ID early for telemetry
    $worldId = defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1;
    
    // Input validation: Validate unit_id exists and is numeric
    if (!$unit_id || !is_numeric($unit_id) || $unit_id <= 0) {
        http_response_code(400);
        $msg = 'Invalid unit_id.';
        echo json_encode(['error' => $msg, 'code' => 'ERR_INPUT', 'details' => ['field' => 'unit_id']]);
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, (int)$count, 'fail', 'ERR_INPUT', $msg, $worldId);
        incrementErrorCounter('ERR_INPUT');
        exit();
    }

    // Input validation: Validate count is positive integer
    if (!$count || !is_numeric($count) || $count <= 0 || $count != intval($count)) {
        http_response_code(400);
        $msg = 'Invalid count. Must be a positive integer.';
        echo json_encode(['error' => $msg, 'code' => 'ERR_INPUT', 'details' => ['field' => 'count', 'value' => $count]]);
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, (int)$count, 'fail', 'ERR_INPUT', $msg, $worldId);
        incrementErrorCounter('ERR_INPUT');
        exit();
    }

    $count = intval($count);
    $unit_id = intval($unit_id);
    
    // Input validation: Verify unit_id exists in database
    $unitMeta = $unitManager->getUnitById($unit_id);
    if (!$unitMeta) {
        http_response_code(400);
        $msg = 'Unit does not exist.';
        echo json_encode(['error' => $msg, 'code' => 'ERR_INPUT', 'details' => ['field' => 'unit_id', 'value' => $unit_id]]);
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_INPUT', $msg, $worldId);
        incrementErrorCounter('ERR_INPUT');
        exit();
    }

    $building_level = $buildingManager->getBuildingLevel($village_id, $building_internal_name);

    $unitInternal = $unitMeta['internal_name'] ?? '';

    // Feature flag validation: Check world feature flags for conquest/seasonal/healer units
    if (!$unitManager->isUnitAvailable($unitInternal, $worldId)) {
        http_response_code(400);
        $msg = "Unit type '{$unitInternal}' is disabled on this world.";
        echo json_encode(['error' => $msg, 'code' => 'ERR_FEATURE_DISABLED', 'unit' => $unitInternal]);
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_FEATURE_DISABLED', $msg, $worldId);
        incrementErrorCounter('ERR_FEATURE_DISABLED');
        exit();
    }

    // Check requirements
    $requirements = $unitManager->checkRecruitRequirements($unit_id, $village_id, $count);
    if (!$requirements['can_recruit']) {
        http_response_code(400);
        $msg = "Cannot recruit unit: " . $requirements['reason'];
        $code = $requirements['code'] ?? 'ERR_PREREQ';
        $response = ['error' => $msg, 'code' => $code];
        
        // Add additional details for specific error types
        if ($code === 'ERR_CAP' && isset($requirements['current_count'], $requirements['max_cap'])) {
            $response['current_count'] = $requirements['current_count'];
            $response['max_cap'] = $requirements['max_cap'];
            // Increment cap hit counter
            incrementCapHitCounter($unit_id, $worldId, $unitInternal);
        } elseif ($code === 'ERR_SEASONAL_EXPIRED' && isset($requirements['window_start'], $requirements['window_end'])) {
            $response['window_start'] = $requirements['window_start'];
            $response['window_end'] = $requirements['window_end'];
        }
        
        echo json_encode($response);
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', $code, $msg, $worldId);
        // Increment error counter for all errors
        incrementErrorCounter($code);
        exit();
    }

    $isNoble = in_array($unitInternal, ['noble', 'nobleman', 'nobleman_unit'], true);
    $isConquestBearer = in_array($unitInternal, ['standard_bearer', 'envoy'], true);

    // Extra nobleman requirements
    if ($isNoble) {
        $statueLevel = $buildingManager->getBuildingLevel($village_id, 'statue');
        $academyLevel = $buildingManager->getBuildingLevel($village_id, 'academy');
        $smithyLevel = $buildingManager->getBuildingLevel($village_id, 'smithy');
        $marketLevel = $buildingManager->getBuildingLevel($village_id, 'market');
        if ($statueLevel <= 0 || $academyLevel < 1 || $smithyLevel < 20 || $marketLevel < 10) {
            http_response_code(400);
            $msg = 'Noble requirements not met (statue, academy 1, smithy 20, market 10).';
            echo json_encode(['error' => $msg, 'code' => 'ERR_PREREQ']);
            logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_PREREQ', $msg, $worldId);
            incrementErrorCounter('ERR_PREREQ');
            exit();
        }
        $userNobles = $unitManager->countUserNobles($user_id);
        $maxNobles = $unitManager->getMaxNoblesForUser($user_id);
        if ($userNobles + $count > $maxNobles) {
            http_response_code(400);
            $msg = 'Noble cap reached';
            echo json_encode(['error' => $msg, 'code' => 'ERR_CAP', 'max_nobles' => $maxNobles, 'current_nobles' => $userNobles]);
            logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_CAP', $msg, $worldId);
            incrementCapHitCounter($unit_id, $worldId, $unitInternal);
            incrementErrorCounter('ERR_CAP');
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
            logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_RES', $msg, $worldId);
            incrementErrorCounter('ERR_RES');
            exit();
        }
    } elseif ($isConquestBearer) {
        // Standard Bearer/Envoy gate: require academy + smithy + minted coins as standards sink
        $academyLevel = $buildingManager->getBuildingLevel($village_id, 'academy');
        $smithyLevel = $buildingManager->getBuildingLevel($village_id, 'smithy');
        if ($academyLevel < 5 || $smithyLevel < 15) {
            http_response_code(400);
            $msg = 'Conquest unit requirements not met (academy 5, smithy 15).';
            echo json_encode(['error' => $msg, 'code' => 'ERR_PREREQ']);
            logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_PREREQ', $msg, $worldId);
            incrementErrorCounter('ERR_PREREQ');
            exit();
        }
        $stmtCoins = $conn->prepare("SELECT coins FROM villages WHERE id = ?");
        $stmtCoins->bind_param("i", $village_id);
        $stmtCoins->execute();
        $rowCoins = $stmtCoins->get_result()->fetch_assoc();
        $stmtCoins->close();
        $coinsAvailable = (int)($rowCoins['coins'] ?? 0);
        if ($coinsAvailable < $count) {
            http_response_code(400);
            $msg = 'Not enough standards/coins for conquest units.';
            echo json_encode(['error' => $msg, 'code' => 'ERR_RES', 'coins' => $coinsAvailable]);
            logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_RES', $msg, $worldId);
            incrementErrorCounter('ERR_RES');
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
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_RES', $msg, $worldId);
        incrementErrorCounter('ERR_RES');
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

        if ($isNoble || $isConquestBearer) {
            $stmtDeductCoin = $conn->prepare("UPDATE villages SET coins = coins - ? WHERE id = ? AND coins >= ?");
            $stmtDeductCoin->bind_param("iii", $count, $village_id, $count);
            $stmtDeductCoin->execute();
            if ($stmtDeductCoin->affected_rows === 0) {
                throw new Exception('Not enough coins/standards to recruit this unit.');
            }
            $stmtDeductCoin->close();
        }

        // Add to recruitment queue
        $result = $unitManager->recruitUnits($village_id, $unit_id, $count, $building_level);

        if ($result['success']) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => $result['message'], 'resources' => $spend['resources']]);
            logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'success', 'OK', $result['message'], $worldId);
        } else {
            throw new Exception($result['error']);
        }
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        $msg = 'An error occurred during recruitment: ' . $e->getMessage();
        echo json_encode(['error' => $msg, 'code' => 'ERR_SERVER']);
        logRecruitTelemetry($user_id, (int)$village_id, (int)$unit_id, $count, 'fail', 'ERR_SERVER', $msg, $worldId);
        incrementErrorCounter('ERR_SERVER');
    }
}
?>
