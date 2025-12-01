<?php
require '../init.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start(); // Start output buffering

try {
    header('Content-Type: application/json');

    require_once '../lib/managers/BuildingManager.php';
    require_once '../lib/managers/VillageManager.php';
    require_once '../lib/functions.php';
    require_once '../lib/managers/BuildingConfigManager.php';
    require_once '../lib/managers/ResourceManager.php'; // Needed for current resources

    // Ensure user is logged in
    if (!isset($_SESSION['user_id'])) {
        ob_clean();
        echo json_encode(['error' => 'You are not logged in.']);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Validate village_id and building_internal_name
    if (!isset($_GET['village_id']) || !is_numeric($_GET['village_id']) ||
        !isset($_GET['building_internal_name']) || empty($_GET['building_internal_name'])) {
        ob_clean();
        echo json_encode(['error' => 'Invalid parameters (village_id, building_internal_name).']);
        exit();
    }

    $village_id = (int)$_GET['village_id'];
    $internal_name = $_GET['building_internal_name'];

    // Database connection provided by init.php ($conn)
    if (!$conn) {
        ob_clean();
        echo json_encode(['error' => 'Failed to connect to the database.']);
        exit();
    }

    // Instantiate managers
    $buildingConfigManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfigManager); // Pass BuildingConfigManager
    $villageManager = new VillageManager($conn);
    $resourceManager = new ResourceManager($conn, $buildingManager); // Pass BuildingManager to ResourceManager

    // Ensure the village belongs to the user
    $villageData = $villageManager->getVillageInfo($village_id);
    if (!$villageData || $villageData['user_id'] != $user_id) {
        ob_clean();
        echo json_encode(['error' => 'You do not have access to this village.']);
        exit();
    }

    // Fetch specific building data in the village
    $building = $buildingManager->getVillageBuilding($village_id, $internal_name);

    $current_level = $building ? (int)$building['level'] : 0;

    // Fetch building configuration (needed for max_level, costs, time)
    $buildingConfig = $buildingConfigManager->getBuildingConfig($internal_name);
    if (!$buildingConfig) {
        ob_clean();
        echo json_encode(['error' => 'Building configuration not found.']);
        exit();
    }
    $max_level = (int)$buildingConfig['max_level'];

    // Check if the building is upgrading (via BuildingManager queue)
    $queue_item = $buildingManager->getBuildingQueueItem($village_id);
    $is_upgrading = ($queue_item && $queue_item['internal_name'] === $internal_name);
    $upgrade_info = $is_upgrading ? $queue_item : null;

    // Main building level for build-time calculations
    $main_building_level = $buildingManager->getBuildingLevel($village_id, 'main_building');

    // Prepare response data
    $response = [
        'internal_name' => $internal_name,
        'name' => $buildingConfig['name'],
        'level' => $current_level,
        'max_level' => $max_level,
        'description' => $buildingConfig['description'] ?? 'No description available.',
        'production_type' => $buildingConfig['production_type'],
        'is_upgrading' => $is_upgrading,
        'queue_finish_time' => null, // Default
        'queue_level_after' => null, // Default
        'can_upgrade' => false, // Default
        'upgrade_costs' => null,
        'upgrade_time_seconds' => null,
        'upgrade_time_formatted' => null,
        'requirements' => [],
        'current_village_resources' => [
             'wood' => (int)($villageData['wood'] ?? 0),
             'clay' => (int)($villageData['clay'] ?? 0),
             'iron' => (int)($villageData['iron'] ?? 0),
             'population' => (int)($villageData['population'] ?? 0),
             'warehouse_capacity' => (int)($villageData['warehouse_capacity'] ?? 0),
             'farm_capacity' => (int)($villageData['farm_capacity'] ?? 0) // Requires column or calculation
        ],
        'production_info' => null, // Production/capacity info
        'upgrade_not_available_reason' => ''
    ];

    // If upgrading, include queue details
    if ($is_upgrading) {
        $response['queue_level_after'] = (int)$upgrade_info['level'];
        $response['queue_finish_time'] = (int)strtotime($upgrade_info['finish_time']);
        $response['upgrade_not_available_reason'] = 'Building is currently upgrading.';
    } else {
        // If not upgrading, check whether the next level can be started
        if ($current_level < $max_level) {
            $queueUsage = $buildingManager->getQueueUsage($village_id);
            if ($queueUsage['is_full']) {
                 $response['upgrade_not_available_reason'] = 'Build queue is full (max ' . $queueUsage['limit'] . ' items).';
            } else {
                // Calculate costs/time for next level
                $next_level = $current_level + 1;
                $upgrade_costs = $buildingConfigManager->calculateUpgradeCost($internal_name, $current_level);
                $upgrade_time_seconds = $buildingConfigManager->calculateUpgradeTime($internal_name, $current_level, $main_building_level);
                
                if ($upgrade_costs && $upgrade_time_seconds !== null) {
                    $response['upgrade_costs'] = $upgrade_costs;
                    $response['upgrade_time_seconds'] = $upgrade_time_seconds;
                    $response['upgrade_time_formatted'] = formatDuration($upgrade_time_seconds);

                    // Check other building requirements
                    $requirementsCheck = $buildingManager->checkBuildingRequirements($internal_name, $village_id);
                    $response['requirements'] = $requirementsCheck;

                    // Check resources
                    $hasEnoughResources = true;
                    $missingResources = [];
                    if ($villageData['wood'] < $upgrade_costs['wood']) { $hasEnoughResources = false; $missingResources[] = 'Wood'; }
                    if ($villageData['clay'] < $upgrade_costs['clay']) { $hasEnoughResources = false; $missingResources[] = 'Clay'; }
                    if ($villageData['iron'] < $upgrade_costs['iron']) { $hasEnoughResources = false; $missingResources[] = 'Iron'; }

                    // Check population cap (farm capacity)
                    $populationCost = $buildingConfigManager->calculatePopulationCost($internal_name, $current_level);
                    $currentPopulation = $villageData['population'];
                    $farmCapacity = $villageData['farm_capacity'];

                    $populationCheck = ['success' => true, 'message' => ''];
                    if ($populationCost !== null && ($currentPopulation + $populationCost > $farmCapacity)) {
                        $populationCheck = ['success' => false, 'message' => 'Not enough free population. Required: ' . $populationCost . '. Available: ' . ($farmCapacity - $currentPopulation) . '.'];
                    }

                    if ($hasEnoughResources && $requirementsCheck['success'] && $populationCheck['success']) {
                         $response['can_upgrade'] = true;
                         $response['upgrade_not_available_reason'] = '';
                    } else {
                         $response['can_upgrade'] = false;
                         if (!$hasEnoughResources) {
                             $response['upgrade_not_available_reason'] = 'Insufficient resources: ' . implode(', ', $missingResources) . '.';
                         } elseif (!$requirementsCheck['success']) {
                              $response['upgrade_not_available_reason'] = $requirementsCheck['message'];
                         } elseif (!$populationCheck['success']) {
                              $response['upgrade_not_available_reason'] = $populationCheck['message'];
                         }
                    }

                } else {
                    $response['upgrade_not_available_reason'] = 'Unable to calculate upgrade cost or time.';
                }
            }
        } else {
            $response['upgrade_not_available_reason'] = 'Building has reached maximum level.';
        }
    }

    // Add production or capacity info
    $productionInfo = $buildingConfigManager->getProductionOrCapacityInfo($internal_name, $current_level);
    if ($productionInfo) {
        $response['production_info'] = $productionInfo;
        // Calculate for next level if not maxed
        if ($current_level < $max_level) {
             $productionInfoNextLevel = $buildingConfigManager->getProductionOrCapacityInfo($internal_name, $current_level + 1);
             if ($productionInfoNextLevel) {
                  if ($productionInfo['type'] === 'production') {
                       $response['production_info']['amount_per_hour_next_level'] = $productionInfoNextLevel['amount_per_hour'];
                  } elseif ($productionInfo['type'] === 'capacity') {
                       $response['production_info']['amount_next_level'] = $productionInfoNextLevel['amount'];
                  }
             }
        }
    }

    ob_clean(); // Clear buffer before sending JSON
    echo json_encode($response);

} catch (Exception $e) {
    ob_clean(); // Clear buffer
    error_log("Error in get_building_details.php: " . $e->getMessage());
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

// DB connection will close automatically at script end

?>
