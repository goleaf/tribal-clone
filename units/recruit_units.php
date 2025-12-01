<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') validateCSRF();

class RecruitmentException extends Exception
{
    private string $reasonCode;

    public function __construct(string $message, string $reasonCode = 'RECRUITMENT_ERROR', int $code = 0, ?Throwable $previous = null)
    {
        $this->reasonCode = $reasonCode;
        parent::__construct($message, $code, $previous);
    }

    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }
}

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'You are not logged in.']);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>You must be logged in to perform this action.</p>";
        header('Location: ../auth/login.php');
    }
    exit();
}

// Validate required parameters
if (!isset($_POST['village_id']) || !is_numeric($_POST['village_id']) || 
    (!isset($_POST['building_id']) && !isset($_POST['building_type'])) ||
    !isset($_POST['recruit']) || !is_array($_POST['recruit'])) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Required parameters are missing.', 'code' => 'MISSING_PARAMETERS']);
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>Required parameters are missing.</p>";
        header('Location: ../game/game.php');
    }
    exit();
}

$user_id = $_SESSION['user_id'];
$village_id = (int)$_POST['village_id'];
$building_id = isset($_POST['building_id']) ? (int)$_POST['building_id'] : 0;
$recruit_data = $_POST['recruit'];
$building_type_from_request = isset($_POST['building_type']) ? trim((string)$_POST['building_type']) : '';

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'UnitManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'VillageManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'BuildingManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'BuildingConfigManager.php';

// Database connection: $conn is provided by init.php
if (!$conn) {
    $_SESSION['game_message'] = 'Error: Could not connect to the database.';
    header('Location: ../game/game.php');
    exit();
}

$unitManager = new UnitManager($conn);
$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$seasonalEnabled = defined('FEATURE_SEASONAL_UNITS') ? (bool)FEATURE_SEASONAL_UNITS : true;
$healerEnabled = defined('FEATURE_HEALER_ENABLED') ? (bool)FEATURE_HEALER_ENABLED : true;
$conquestEnabled = defined('FEATURE_CONQUEST_UNIT_ENABLED') ? (bool)FEATURE_CONQUEST_UNIT_ENABLED : true;
$seasonalUnits = ['tempest_knight', 'event_knight'];
$healerUnits = ['war_healer', 'healer'];
$conquestUnits = ['standard_bearer', 'envoy'];
function getBuildingLevelByInternal(mysqli $conn, int $villageId, string $internal): int
{
    $stmt = $conn->prepare("
        SELECT vb.level 
        FROM village_buildings vb
        JOIN building_types bt ON vb.building_type_id = bt.id
        WHERE vb.village_id = ? AND bt.internal_name = ?
        LIMIT 1
    ");
    if (!$stmt) return 0;
    $stmt->bind_param('is', $villageId, $internal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['level'] ?? 0);
}

try {
    $conn->begin_transaction();
    
    // Verify the village belongs to the user
    $stmt_check_village = $conn->prepare("SELECT id FROM villages WHERE id = ? AND user_id = ?");
    $stmt_check_village->bind_param('ii', $village_id, $user_id);
    $stmt_check_village->execute();
    $result_village = $stmt_check_village->get_result();
    
    if ($result_village->num_rows === 0) {
        throw new RecruitmentException('You do not have access to this village.', 'NO_VILLAGE_ACCESS');
    }
    $stmt_check_village->close();

    // Fetch village data for resource/population validation
    $village_data = $villageManager->getVillageInfo($village_id);
    if (!$village_data) {
        throw new RecruitmentException('Village not found.', 'VILLAGE_NOT_FOUND');
    }
    
// Resolve building by id or building_type
if ($building_id > 0) {
    $stmt_check_building = $conn->prepare("
        SELECT vb.id, bt.internal_name, vb.level 
        FROM village_buildings vb 
        JOIN building_types bt ON vb.building_type_id = bt.id 
        WHERE vb.id = ? AND vb.village_id = ?
    ");
    $stmt_check_building->bind_param('ii', $building_id, $village_id);
} else {
    $stmt_check_building = $conn->prepare("
        SELECT vb.id, bt.internal_name, vb.level 
        FROM village_buildings vb 
        JOIN building_types bt ON vb.building_type_id = bt.id 
        WHERE bt.internal_name = ? AND vb.village_id = ?
    ");
    $stmt_check_building->bind_param('si', $building_type_from_request, $village_id);
}
$stmt_check_building->execute();
$result_building = $stmt_check_building->get_result();

if ($result_building->num_rows === 0) {
    throw new RecruitmentException('Invalid building.', 'INVALID_BUILDING');
}

$building = $result_building->fetch_assoc();
$building_internal_name = $building['internal_name'];
$building_level = (int)$building['level'];
$building_id = (int)$building['id'];
$stmt_check_building->close();

// Optional: ensure the client-specified building_type matches the resolved one
if ($building_type_from_request && $building_type_from_request !== $building_internal_name) {
    throw new RecruitmentException('Mismatched building type for recruitment.', 'MISMATCHED_BUILDING');
}
    
    // Check current recruitment queues for this building
$stmt_check_queue = $conn->prepare("
        SELECT COUNT(*) as queue_count 
        FROM unit_queue 
        WHERE village_id = ? AND building_type = ?
");
$queue_building_type = $building_internal_name;

$stmt_check_queue->bind_param('is', $village_id, $queue_building_type);
$stmt_check_queue->execute();
    $result_queue = $stmt_check_queue->get_result();
    $queue_count = $result_queue->fetch_assoc()['queue_count'];
    $stmt_check_queue->close();
    
    // Enforce queue limit (static for now, could depend on building level)
    $max_queues = 2;
    if ($queue_count >= $max_queues) {
        throw new RecruitmentException("The maximum recruitment queue count ($max_queues) has been reached for this building.", 'QUEUE_FULL');
    }

    // Use cached village data for current resources/population
    $resources = [
        'wood' => (int)($village_data['wood'] ?? 0),
        'clay' => (int)($village_data['clay'] ?? 0),
        'iron' => (int)($village_data['iron'] ?? 0),
        'population' => (int)($village_data['population'] ?? 0),
        'farm_capacity' => (int)($village_data['farm_capacity'] ?? 0),
    ];
    
    // Aggregate requested units and total costs
    $total_units = 0;
    $total_population = 0;
    $total_wood = 0;
    $total_clay = 0;
    $total_iron = 0;
    $units_to_recruit = [];
    $requiredCoins = 0;
    $requiresConquestGate = false;
    
    foreach ($recruit_data as $unit_type_id => $count) {
        $count = (int)$count;
        if ($count <= 0) {
            throw new RecruitmentException('Unit counts must be positive integers.', 'INVALID_UNIT_COUNT');
        }
        
        $total_units += $count;
        
        // Pull unit info
        $stmt_unit = $conn->prepare("
            SELECT internal_name, name, building_type, cost_wood, cost_clay, cost_iron, population, training_time_base, required_building_level
            FROM unit_types 
            WHERE id = ? AND building_type = ?
        ");
        $stmt_unit->bind_param('is', $unit_type_id, $building_internal_name);
        $stmt_unit->execute();
        $result_unit = $stmt_unit->get_result();
        
        if ($result_unit->num_rows === 0) {
            throw new RecruitmentException('Invalid unit for this building.', 'INVALID_UNIT_FOR_BUILDING');
        }
        
        $unit = $result_unit->fetch_assoc();
        $stmt_unit->close();

        $internalName = strtolower($unit['internal_name']);
        if (!$seasonalEnabled && in_array($internalName, $seasonalUnits, true)) {
            throw new RecruitmentException('Seasonal units are disabled on this world.', 'SEASONAL_DISABLED');
        }
        if (!$healerEnabled && in_array($internalName, $healerUnits, true)) {
            throw new RecruitmentException('Healer units are disabled on this world.', 'HEALER_DISABLED');
        }
        if (!$conquestEnabled && in_array($internalName, $conquestUnits, true)) {
            throw new RecruitmentException('Conquest units are disabled on this world.', 'CONQUEST_DISABLED');
        }
        if (in_array($internalName, $conquestUnits, true)) {
            $requiresConquestGate = true;
            $requiredCoins += $count; // treat coins as standards/crests sink
        }
        
        // Ensure the building level is high enough
        if ($building_level < $unit['required_building_level']) {
            throw new RecruitmentException(ucfirst($building_internal_name) . ' level is too low for unit ' . $unit['name'] . '.', 'BUILDING_LEVEL_TOO_LOW');
        }
        
        // Calculate costs and training time
        $wood_cost = $count * $unit['cost_wood'];
        $clay_cost = $count * $unit['cost_clay'];
        $iron_cost = $count * $unit['cost_iron'];
        $population_cost = $count * $unit['population'];
        
        $total_wood += $wood_cost;
        $total_clay += $clay_cost;
        $total_iron += $iron_cost;
        $total_population += $population_cost;
        
        // Training time per unit (5% faster per building level)
        $training_time_base = $unit['training_time_base'];
        $training_time_per_unit = floor($training_time_base * pow(0.95, $building_level - 1));
        
        $units_to_recruit[] = [
            'unit_type_id' => $unit_type_id,
            'count' => $count,
            'training_time_per_unit' => $training_time_per_unit,
            'name' => $unit['name'],
            'internal_name' => $unit['internal_name']
        ];
    }
    
    if ($total_units === 0) {
        throw new RecruitmentException('No units selected for recruitment.', 'NO_UNITS_SELECTED');
    }
    
    // Validate resources and population capacity
    if ($resources['wood'] < $total_wood || 
        $resources['clay'] < $total_clay || 
        $resources['iron'] < $total_iron ||
        ($resources['population'] + $total_population) > $resources['farm_capacity']) {
        // Fetch full village data for current farm capacity
        $village_data = $villageManager->getVillageInfo($village_id);
        if (!$village_data || ($village_data['population'] + $total_population) > $village_data['farm_capacity']) {
            throw new RecruitmentException('Not enough free population in the village.', 'POPULATION_LIMIT');
        }
        
        throw new RecruitmentException('Not enough resources to recruit the selected units.', 'RESOURCES_LIMIT');
    }
    
    // Deduct resources and population
    $stmt_deduct_resources = $conn->prepare('UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ?, population = population + ? WHERE id = ?');
    $stmt_deduct_resources->bind_param('ddiii', $total_wood, $total_clay, $total_iron, $total_population, $village_id);
    if (!$stmt_deduct_resources->execute()) {
        throw new Exception('Error while deducting resources and adding population: ' . $stmt_deduct_resources->error);
    }
    $stmt_deduct_resources->close();

    // Queue recruitment tasks
    $recruited_queues = [];
    $current_time = time();
    $last_finish_time = $current_time;

    $stmt_last_queue = $conn->prepare('SELECT finish_at FROM unit_queue WHERE village_id = ? AND building_type = ? ORDER BY finish_at DESC LIMIT 1');
    $stmt_last_queue->bind_param('is', $village_id, $queue_building_type);
    $stmt_last_queue->execute();
    $result_last_queue = $stmt_last_queue->get_result();
    if ($row_last_queue = $result_last_queue->fetch_assoc()) {
        $last_finish_time = max($last_finish_time, $row_last_queue['finish_at']);
    }
    $stmt_last_queue->close();

    foreach ($units_to_recruit as $recruit_data) {
        $unit_type_id = $recruit_data['unit_type_id'];
        $count = $recruit_data['count'];
        $training_time_per_unit = $recruit_data['training_time_per_unit'];
        
        $total_training_time_for_batch = $training_time_per_unit * $count;
        $started_at_this_task = $last_finish_time;
        $finish_at_this_task = $started_at_this_task + $total_training_time_for_batch;
        
        $stmt_add_queue = $conn->prepare('INSERT INTO unit_queue (village_id, unit_type_id, count, count_finished, started_at, finish_at, building_type) VALUES (?, ?, ?, 0, ?, ?, ?)');
        $stmt_add_queue->bind_param('iiiiss', $village_id, $unit_type_id, $count, $started_at_this_task, $finish_at_this_task, $queue_building_type);
        
        if (!$stmt_add_queue->execute()) {
            throw new Exception('Error while adding the recruitment task to the queue: ' . $stmt_add_queue->error);
        }
        
        $last_finish_time = $finish_at_this_task;
        
        $recruited_queues[] = [
            'queue_id' => $conn->insert_id,
            'unit_name' => $recruit_data['name'],
            'count' => $count,
            'finish_at' => $finish_at_this_task
        ];
    }
    
    $stmt_add_queue->close();

    $conn->commit();

    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        $updatedVillageInfo = $villageManager->getVillageInfo($village_id);
        AjaxResponse::success([
            'message' => 'Units have been added to the recruitment queue!',
            'recruited_queues' => $recruited_queues,
            'village_info' => [
                'wood' => $updatedVillageInfo['wood'],
                'clay' => $updatedVillageInfo['clay'],
                'iron' => $updatedVillageInfo['iron'],
                'population' => $updatedVillageInfo['population'],
                'warehouse_capacity' => $updatedVillageInfo['warehouse_capacity'],
                'farm_capacity' => $updatedVillageInfo['farm_capacity']
            ]
        ]);
    } else {
        $_SESSION['game_message'] = "<p class='success-message'>Unit recruitment has been added to the queue.</p>";
        header('Location: ../game/game.php');
    }
    
} catch (Exception $e) {
    $conn->rollback();

    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        $reasonCode = ($e instanceof RecruitmentException) ? $e->getReasonCode() : 'RECRUITMENT_ERROR';
        AjaxResponse::error(
            'An error occurred while recruiting units: ' . $e->getMessage(),
            [
                'code' => $reasonCode,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ],
            400
        );
    } else {
        $_SESSION['game_message'] = "<p class='error-message'>An error occurred while recruiting units: " . htmlspecialchars($e->getMessage()) . "</p>";
        header('Location: ../game/game.php');
    }
} finally {
    // init.php manages the connection lifecycle
    $conn->close();
}
?>
