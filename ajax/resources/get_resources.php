<?php
/**
 * AJAX - Fetch current village resources.
 * Returns current resource values and other village details as JSON.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils/AjaxResponse.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/BuildingConfigManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/BuildingManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/VillageManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/ResourceManager.php';


// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('User is not logged in', null, 401);
}

try {
    // Create manager instances
    $buildingConfigManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfigManager);
    $villageManager = new VillageManager($conn);
    $resourceManager = new ResourceManager($conn, $buildingManager);

    // Get village ID
    $village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;
    
    // If no village ID is provided, fetch the user's first village
    if (!$village_id) {
        $village_data = $villageManager->getFirstVillage($_SESSION['user_id']);
        
        if (!$village_data) {
            AjaxResponse::error('Village not found', null, 404);
        }
        $village_id = $village_data['id'];
    }
    
    // Confirm that the village belongs to the logged-in user
    $stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $village_owner = $result->fetch_assoc();
    $stmt->close();
    
    if (!$village_owner || $village_owner['user_id'] != $_SESSION['user_id']) {
        AjaxResponse::error('No permission for this village', null, 403);
    }
    
    // Update village resources
    $villageManager->updateResources($village_id);
    
    // Fetch current village data
    $village = $villageManager->getVillageInfo($village_id);
    
    // Fetch production buildings and their levels (optimized query)
    $stmt = $conn->prepare("
        SELECT bt.internal_name, vb.level, bt.production_type, bt.production_initial, bt.production_factor
        FROM village_buildings vb
        JOIN building_types bt ON vb.building_type_id = bt.id
        WHERE vb.village_id = ? AND (bt.production_type IS NOT NULL OR bt.internal_name = 'warehouse')
    ");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $production_buildings = [];
    $warehouse_level = 0;
    $wood_production = 0;
    $clay_production = 0;
    $iron_production = 0;
    
    while ($row = $result->fetch_assoc()) {
        $internal_name = $row['internal_name'];
        $level = $row['level'];
        
        // Capture warehouse level
        if ($internal_name === 'warehouse') {
            $warehouse_level = $level;
            continue;
        }
        
        // Calculate production for resource buildings
        if ($row['production_type'] && $row['production_initial'] && $row['production_factor']) {
            $production = floor($row['production_initial'] * pow($row['production_factor'], $level - 1));
            
            // Assign to the correct resource
            if ($internal_name === 'sawmill' || $internal_name === 'wood_production') {
                $wood_production = $production;
            } else if ($internal_name === 'clay_pit' || $internal_name === 'clay_production') {
                $clay_production = $production;
            } else if ($internal_name === 'iron_mine' || $internal_name === 'iron_production') {
                $iron_production = $production;
            }
        }
    }
    $stmt->close();
    
    // Calculate warehouse capacity
    $warehouse_capacity = $buildingManager->getWarehouseCapacityByLevel($warehouse_level);
    
    // Check if buildings are in the construction queue
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM building_queue 
        WHERE village_id = ? AND finish_time > NOW()
    ");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $building_queue = $result->fetch_assoc();
    $stmt->close();
    
    // Check if units are in recruitment
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM unit_queue 
        WHERE village_id = ? AND finish_at > UNIX_TIMESTAMP()
    ");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recruitment_queue = $result->fetch_assoc();
    $stmt->close();
    
    // Prepare response data
    $resources_data = [
        'wood' => [
            'amount' => round($village['wood']),
            'capacity' => $village['warehouse_capacity'],
            'production' => $wood_production,
            'production_per_second' => round($wood_production / 3600, 2)
        ],
        'clay' => [
            'amount' => round($village['clay']),
            'capacity' => $village['warehouse_capacity'],
            'production' => $clay_production,
            'production_per_second' => round($clay_production / 3600, 2)
        ],
        'iron' => [
            'amount' => round($village['iron']),
            'capacity' => $village['warehouse_capacity'],
            'production' => $iron_production,
            'production_per_second' => round($iron_production / 3600, 2)
        ],
        'population' => [
            'amount' => round($village['population']),
            'capacity' => $village['farm_capacity']
        ],
        'village_name' => $village['name'],
        'village_id' => $village_id,
        'coords' => $village['x_coord'] . '|' . $village['y_coord'],
        'buildings_in_queue' => $building_queue['count'],
        'units_in_recruitment' => $recruitment_queue['count'],
        'last_update' => $village['last_resource_update'],
        'current_server_time' => date('Y-m-d H:i:s')
    ];
    
    // Return data as JSON
    AjaxResponse::success($resources_data);
    
} catch (Exception $e) {
    // Handle exception and return a detailed error
    AjaxResponse::error(
        'An error occurred: ' . $e->getMessage(),
        [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ],
        500
    );
}
