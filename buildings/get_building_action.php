<?php
require '../init.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start(); // Start output buffering

try {
    header('Content-Type: application/json');

    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'BuildingManager.php';
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'VillageManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'utils' . DIRECTORY_SEPARATOR . 'AjaxResponse.php'; // Include AjaxResponse

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_clean(); // Clear buffer
    echo json_encode(['error' => 'User not logged in.']);
    exit();
}

    $user_id = $_SESSION['user_id'];

    if (!$conn) {
        ob_clean();
        echo json_encode(['error' => 'Database connection failed (from init.php).']);
        exit();
    }

// Fetch the logged-in user's village ID
$stmt_village = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
$stmt_village->bind_param("i", $user_id);
$stmt_village->execute();
    $result_village = $stmt_village->get_result();
    $village = $result_village->fetch_assoc();
    $stmt_village->close();

if (!$village) {
    ob_clean();
    echo json_encode(['error' => 'No village found for this user.']);
    exit();
}
    $village_id = $village['id'];

// Get main building level for build-time calculations
$main_building_level = 0;
    $stmt_mb_level = $conn->prepare("SELECT vb.level FROM village_buildings vb JOIN building_types bt ON vb.building_type_id = bt.id WHERE vb.village_id = ? AND bt.internal_name = 'main_building' LIMIT 1");
    $stmt_mb_level->bind_param("i", $village_id);
    $stmt_mb_level->execute();
    $mb_result = $stmt_mb_level->get_result()->fetch_assoc();
    if ($mb_result) {
        $main_building_level = (int)$mb_result['level'];
    }
    $stmt_mb_level->close();

// Fetch building_id (village_buildings.id) and building_type (building_types.internal_name) from GET
$village_building_id = isset($_GET['building_id']) ? (int)$_GET['building_id'] : 0;
$building_type = isset($_GET['building_type']) ? $_GET['building_type'] : '';

if ($village_building_id <= 0 || empty($building_type)) {
    ob_clean();
    echo json_encode(['error' => 'Invalid request data.']);
    exit();
}

// Fetch building details from DB and ensure it belongs to the user's village
 $stmt_building = $conn->prepare("
         SELECT vb.id, vb.level, bt.internal_name, bt.name, bt.description, bt.production_type, bt.production_initial, bt.production_factor, bt.max_level, bt.id AS building_type_id
         FROM village_buildings vb
         JOIN building_types bt ON vb.building_type_id = bt.id
         WHERE vb.id = ? AND vb.village_id = ? AND bt.internal_name = ? LIMIT 1
     ");
     $stmt_building->bind_param("iis", $village_building_id, $village_id, $building_type);
     $stmt_building->execute();
     $result_building = $stmt_building->get_result();
     $building_details = $result_building->fetch_assoc();
     $stmt_building->close();

     if (!$building_details) {
         ob_clean();
         echo json_encode(['error' => 'Building not found in your village with the provided parameters.']);
         exit();
     }

    $buildingManager = new BuildingManager($conn);
    $response_data = [];

    $response = [
        'building_type_id' => $building_details['building_type_id'],
        'name' => $building_details['name'],
        'level' => $building_details['level'],
        'description' => $building_details['description'],
        'action_type' => 'upgrade', // Default action is upgrade
        'additional_info_html' => '', // Optional HTML for UI
    ];

    // Switch based on building type to set action and supplemental data
    switch ($building_details['building_type_id']) {
        case 1: // Town hall
            $response['action_type'] = 'manage_village';
            // Fetch village population and name
            $stmt_pop = $conn->prepare("SELECT population, name FROM villages WHERE id = ? LIMIT 1");
            $stmt_pop->bind_param("i", $village_id);
            $stmt_pop->execute();
            $pop_result = $stmt_pop->get_result()->fetch_assoc();
            $stmt_pop->close();
            $population = $pop_result ? (int)$pop_result['population'] : 0;
            $village_name = $pop_result ? $pop_result['name'] : 'Village';

            // Count player villages
            $stmt_villages_count = $conn->prepare("SELECT COUNT(*) as cnt FROM villages WHERE user_id = ?");
            $stmt_villages_count->bind_param("i", $user_id);
            $stmt_villages_count->execute();
            $villages_count_result = $stmt_villages_count->get_result()->fetch_assoc();
            $stmt_villages_count->close();
            $villages_count = $villages_count_result ? (int)$villages_count_result['cnt'] : 1;

            // --- Building upgrade menu ---
            $stmt_buildings = $conn->prepare("
                SELECT vb.id, vb.level, bt.name, bt.internal_name, bt.max_level
                FROM village_buildings vb
                JOIN building_types bt ON vb.building_type_id = bt.id
                WHERE vb.village_id = ?
                ORDER BY bt.id
            ");
            $stmt_buildings->bind_param("i", $village_id);
            $stmt_buildings->execute();
            $buildings_result = $stmt_buildings->get_result();

            $buildings_data = [];
            while ($b = $buildings_result->fetch_assoc()) {
                 $buildings_data[] = [
                     'id' => $b['id'],
                     'level' => (int)$b['level'],
                     'name' => $b['name'],
                     'internal_name' => $b['internal_name'],
                     'max_level' => (int)$b['max_level']
                 ];
            }
            $stmt_buildings->close();

            // Add current village resources and capacities for overview (assuming they are fetched earlier)
            // If not fetched earlier, need to fetch them here
             if (!isset($villageData)) {
                  $villageManager = new VillageManager($conn);
                 $villageData = $villageManager->getVillageInfo($village_id);
             }

             $currentResourcesAndCapacity = [
                  'wood' => $villageData['wood'] ?? 0,
                  'clay' => $villageData['clay'] ?? 0,
                  'iron' => $villageData['iron'] ?? 0,
                  'population' => $villageData['population'] ?? 0,
                  'warehouse_capacity' => $villageData['warehouse_capacity'] ?? 0,
                  'farm_capacity' => $villageData['farm_capacity'] ?? 0
             ];


            // Return data as JSON
            AjaxResponse::success([
                'village_name' => $village_name,
                'main_building_level' => $building_details['level'],
                'population' => $population,
                'villages_count' => $villages_count,
                 'buildings_list' => $buildings_data,
                 'resources_capacity' => $currentResourcesAndCapacity // Add resources/capacity info
            ]);
            break;
        case 2: // Barracks
            $response['action_type'] = 'recruit_barracks';

            // Fetch units available in Barracks
            require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'UnitManager.php';
            $unitManager = new UnitManager($conn);
            $barracksUnits = $unitManager->getUnitTypes('barracks');
            
            // Fetch current units in the village
            $villageUnits = $unitManager->getVillageUnits($village_id);
            
            // Fetch recruitment queue
            $recruitmentQueue = $unitManager->getRecruitmentQueue($village_id, 'barracks');
            
            // Prepare unit data for JSON
            $availableUnitsData = [];
            if (!empty($barracksUnits)) {
                 require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'UnitConfigManager.php';
                 $unitConfigManager = new UnitConfigManager($conn);

                foreach ($barracksUnits as $unitInternal => $unit) {
                    $canRecruit = true;
                    $disableReason = '';
                    
                    // Check requirements
                    $requirementsCheck = $unitManager->checkRecruitRequirements($unit['id'], $village_id);
                    if (!$requirementsCheck['can_recruit']) {
                        $canRecruit = false;
                        $disableReason = 'Required building level: ' . $requirementsCheck['required_building_level'];
                    }
                    
                    // Calculate recruitment time
                    $recruitTime = $unitManager->calculateRecruitmentTime($unit['id'], $building_details['level']);
                    
                    $availableUnitsData[] = [
                        'internal_name' => $unitInternal,
                        'name' => $unit['name'],
                        'description' => $unit['description'],
                        'cost_wood' => $unit['cost_wood'],
                        'cost_clay' => $unit['cost_clay'],
                        'cost_iron' => $unit['cost_iron'],
                        'population_cost' => $unit['population_cost'] ?? 0,
                        'recruit_time_seconds' => $recruitTime,
                        'attack' => $unit['attack'],
                        'defense' => $unit['defense'],
                        'owned' => $villageUnits[$unitInternal] ?? 0,
                        'can_recruit' => $canRecruit,
                        'disable_reason' => $disableReason
                    ];
                }
            }

            // Prepare recruitment queue data for JSON
            $recruitmentQueueData = [];
            if (!empty($recruitmentQueue)) {
                foreach ($recruitmentQueue as $queue) {
                     $recruitmentQueueData[] = [
                         'id' => $queue['id'],
                         'unit_id' => $queue['unit_id'],
                         'unit_internal_name' => $queue['unit_internal_name'],
                         'unit_name' => $queue['unit_name'], // Assuming unit_name is available
                         'count' => $queue['count'],
                         'count_finished' => $queue['count_finished'],
                         'started_at' => strtotime($queue['started_at']), // Convert to Unix timestamp
                         'finish_at' => strtotime($queue['finish_at']), // Convert to Unix timestamp
                         'time_remaining' => $queue['time_remaining'], // Calculated seconds
                         'building_internal_name' => $queue['building_internal_name'], // barracks or stable
                     ];
                }
            }

            // Return data as JSON
            AjaxResponse::success([
                'building_name' => $building_details['name'],
                'building_level' => $building_details['level'],
                'available_units' => $availableUnitsData,
                'recruitment_queue' => $recruitmentQueueData
            ]);
            break;
        case 3: // Stable
            $response['action_type'] = 'recruit_stable';
            
            // Fetch units available in Stable
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'UnitManager.php';
            $unitManager = new UnitManager($conn);
            $stableUnits = $unitManager->getUnitTypes('stable');
            
            // Fetch current units in the village
            $villageUnits = $unitManager->getVillageUnits($village_id);
            
            // Fetch recruitment queue
            $recruitmentQueue = $unitManager->getRecruitmentQueue($village_id, 'stable');
            
            // Prepare unit data for JSON
            $availableUnitsData = [];
            if (!empty($stableUnits)) {
                 require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'UnitConfigManager.php';
                 $unitConfigManager = new UnitConfigManager($conn);

                foreach ($stableUnits as $unitInternal => $unit) {
                    $canRecruit = true;
                    $disableReason = '';
                    
                    // Check requirements
                    $requirementsCheck = $unitManager->checkRecruitRequirements($unit['id'], $village_id);
                    if (!$requirementsCheck['can_recruit']) {
                        $canRecruit = false;
                        $disableReason = 'Required building level: ' . $requirementsCheck['required_building_level'];
                    }
                    
                    // Calculate recruitment time
                    $recruitTime = $unitManager->calculateRecruitmentTime($unit['id'], $building_details['level']);
                    
                    $availableUnitsData[] = [
                        'internal_name' => $unitInternal,
                        'name' => $unit['name'],
                        'description' => $unit['description'],
                        'cost_wood' => $unit['cost_wood'],
                        'cost_clay' => $unit['cost_clay'],
                        'cost_iron' => $unit['cost_iron'],
                        'population_cost' => $unit['population_cost'] ?? 0,
                        'recruit_time_seconds' => $recruitTime,
                        'attack' => $unit['attack'],
                        'defense' => $unit['defense'],
                        'owned' => $villageUnits[$unitInternal] ?? 0,
                        'can_recruit' => $canRecruit,
                        'disable_reason' => $disableReason
                    ];
                }
            }

            // Prepare recruitment queue data for JSON
            $recruitmentQueueData = [];
            if (!empty($recruitmentQueue)) {
                foreach ($recruitmentQueue as $queue) {
                     $recruitmentQueueData[] = [
                         'id' => $queue['id'],
                         'unit_id' => $queue['unit_id'],
                         'unit_internal_name' => $queue['unit_internal_name'],
                         'unit_name' => $queue['unit_name'], // Assuming unit_name is available
                         'count' => $queue['count'],
                         'count_finished' => $queue['count_finished'],
                         'started_at' => strtotime($queue['started_at']), // Convert to Unix timestamp
                         'finish_at' => strtotime($queue['finish_at']), // Convert to Unix timestamp
                         'time_remaining' => $queue['time_remaining'], // Calculated seconds
                         'building_internal_name' => $queue['building_internal_name'], // barracks or stable
                     ];
               }
            }

            // Return data as JSON
            AjaxResponse::success([
                'building_name' => $building_details['name'],
                'building_level' => $building_details['level'],
                'available_units' => $availableUnitsData,
                'recruitment_queue' => $recruitmentQueueData
            ]);
            break;
        case 4: // Smithy
            $response['action_type'] = 'research';
            
            // Fetch research available in smithy
            require_once 'lib/ResearchManager.php';
            $researchManager = new ResearchManager($conn);
            $smithy_research_types = $researchManager->getResearchTypesForBuilding('smithy');

            // Get current smithy level
            $smithy_level = $building_details['level'];

            // Get current research levels for the village
            $village_research_levels = $researchManager->getVillageResearchLevels($village_id);

            // Get current research queue for the village
            $research_queue = $researchManager->getResearchQueue($village_id);
            $current_research_ids = [];
            foreach ($research_queue as $queue_item) {
                $current_research_ids[$queue_item['research_type_id']] = true;
            }

            // Prepare HTML for the research UI
            $researchHtml = '<h3>Technology research</h3>';
            $researchHtml .= '<p>Research new military technologies and weapon upgrades here.</p>';
            
            if (!empty($smithy_research_types)) {
                $researchHtml .= '<div class="research-list">';
                
                // Show ongoing research first, if any
                if (!empty($research_queue)) {
                    $researchHtml .= '<h4>Current research:</h4>';
                    $researchHtml .= '<table class="research-queue">';
                    $researchHtml .= '<tr><th>Research</th><th>Target level</th><th>Remaining time</th><th>Action</th></tr>';
                    
                    foreach ($research_queue as $queue) {
                        if ($queue['building_type'] === 'smithy') {
                            $end_time = strtotime($queue['ends_at']);
                            $current_time = time();
                            $remaining_time = max(0, $end_time - $current_time);
                            $time_remaining = gmdate("H:i:s", $remaining_time);
                            
                            $researchHtml .= '<tr>';
                            $researchHtml .= '<td>' . htmlspecialchars($queue['research_name']) . '</td>';
                            $researchHtml .= '<td>' . $queue['level_after'] . '</td>';
                            $researchHtml .= '<td class="build-timer" data-ends-at="' . ($queue['ends_at']) . '" data-item-description="Technology research">';
                            $researchHtml .= $time_remaining;
                            $researchHtml .= '</td>';
                            $researchHtml .= '<td><a href="cancel_research.php?research_queue_id=' . $queue['id'] . '" class="cancel-button">Cancel</a></td>';
                            $researchHtml .= '</tr>';
                        }
                    }
                    
                    $researchHtml .= '</table>';
                }
                
                // Then show available research
                $researchHtml .= '<h4>Available research:</h4>';
                $researchHtml .= '<table class="research-options">';
                $researchHtml .= '<tr><th>Technology</th><th>Level</th><th colspan="3">Cost</th><th>Time</th><th>Action</th></tr>';
                
                foreach ($smithy_research_types as $research) {
                    $research_id = $research['id'];
                    $internal_name = $research['internal_name'];
                    $name = $research['name'];
                    $description = $research['description'];
                    $required_level = $research['required_building_level'];
                    $max_level = $research['max_level'];
                    $current_level = $village_research_levels[$internal_name] ?? 0;
                    $next_level = $current_level + 1;

                    // Check availability
                    $is_available = $smithy_level >= $required_level;
                    $is_at_max_level = $current_level >= $max_level;
                    $is_in_progress = isset($current_research_ids[$research_id]);
                    
                    // Calculate cost/time for next level
                    $cost = null;
                    $time = null;
                    $can_research = false;

                    if (!$is_at_max_level && $is_available && !$is_in_progress) {
                        $cost = $researchManager->getResearchCost($research_id, $next_level);
                        $time = $researchManager->calculateResearchTime($research_id, $next_level, $smithy_level);
                        
                        // Check resources
                        $can_research = $village['wood'] >= $cost['wood'] && 
                                        $village['clay'] >= $cost['clay'] && 
                                        $village['iron'] >= $cost['iron'];
                    }
                    
                    $researchHtml .= '<tr class="research-item ' . (!$is_available ? 'unavailable' : '') . '">';
                    $researchHtml .= '<td><strong>' . htmlspecialchars($name) . '</strong><br><small>' . htmlspecialchars($description) . '</small></td>';
                    $researchHtml .= '<td>' . $current_level . '/' . $max_level . '</td>';
                    
                    if (!$is_at_max_level && !$is_in_progress) {
                        if ($is_available) {
                            $researchHtml .= '<td><img src="img/wood.png" title="Wood" alt="Wood"> ' . $cost['wood'] . '</td>';
                            $researchHtml .= '<td><img src="img/stone.png" title="Clay" alt="Clay"> ' . $cost['clay'] . '</td>';
                            $researchHtml .= '<td><img src="img/iron.png" title="Iron" alt="Iron"> ' . $cost['iron'] . '</td>';
                            $researchHtml .= '<td>' . gmdate("H:i:s", $time) . '</td>';
                            
                            $researchHtml .= '<td>';
                            $researchHtml .= '<form action="start_research.php" method="post" class="research-form">';
                            $researchHtml .= '<input type="hidden" name="village_id" value="' . $village_id . '">';
                            $researchHtml .= '<input type="hidden" name="research_type_id" value="' . $research_id . '">';
                            $researchHtml .= '<button type="submit" class="research-button" ' . ($can_research ? '' : 'disabled') . '>Research</button>';
                            $researchHtml .= '</form>';
                            $researchHtml .= '</td>';
                        } else {
                            $researchHtml .= '<td colspan="4">Required smithy level: ' . $required_level . '</td>';
                            $researchHtml .= '<td><button disabled>Unavailable</button></td>';
                        }
                    } else if ($is_at_max_level) {
                        $researchHtml .= '<td colspan="4">Maximum level reached</td>';
                        $researchHtml .= '<td>-</td>';
                    } else if ($is_in_progress) {
                        $researchHtml .= '<td colspan="4">Research in progress</td>';
                        $researchHtml .= '<td>-</td>';
                    }
                    
                    $researchHtml .= '</tr>';
                }
                
                $researchHtml .= '</table>';
                $researchHtml .= '</div>';
            } else {
                $researchHtml .= '<p>No research available in the smithy.</p>';
            }
            
            $response['additional_info_html'] = $researchHtml;
            break;
        case 5: // Tartak
        case 6: // Clay pit
        case 7: // Iron mine
            $response['action_type'] = 'info_production';
            // Fetch production data
            $productionPerHour = $buildingManager->getHourlyProduction($building_details['internal_name'], $building_details['level']); // Use internal_name for getHourlyProduction
            $resourceName = '';
            switch ($building_details['building_type_id']) {
                case 5: $resourceName = 'Wood'; break;
                case 6: $resourceName = 'Clay'; break;
                case 7: $resourceName = 'Iron'; break;
            }
            $response['additional_info_html'] = '<p>Description: ' . htmlspecialchars($building_details['description']) . '</p><p>Production: '. $productionPerHour .' per hour of '. $resourceName .'</p>';
            break;
        case 8: // Warehouse
            $response['action_type'] = 'info';
            // Get capacity data
            $storageCapacity = $buildingManager->getWarehouseCapacityByLevel($building_details['level']);
             $response['additional_info_html'] = '<p>Description: ' . htmlspecialchars($building_details['description']) . '</p><p>Warehouse capacity: '. $storageCapacity .'</p>';
            break;
        case 9: // Market
            $response['action_type'] = 'trade';
            
            // Fetch market/trader info
            $market_level = $building_details['level'];
            $traders_capacity = 3 + floor($market_level * 0.7); // Example formula

            // Fetch active transports
            $active_trades = [];
            $stmt = $conn->prepare("
                SELECT * FROM trade_routes 
                WHERE (source_village_id = ? OR target_village_id = ?) 
                AND arrival_time > NOW()
                ORDER BY arrival_time ASC
            ");
            $stmt->bind_param("ii", $village_id, $village_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $is_outgoing = $row['source_village_id'] == $village_id;
                $direction = $is_outgoing ? 'outgoing' : 'incoming';
                
                // Fetch target/source village data
                $other_village_id = $is_outgoing ? $row['target_village_id'] : $row['source_village_id'];
                $other_village_stmt = $conn->prepare("
                    SELECT v.name, v.x_coord, v.y_coord, u.username 
                    FROM villages v 
                    JOIN users u ON v.user_id = u.id 
                    WHERE v.id = ?
                ");
                $other_village_stmt->bind_param("i", $other_village_id);
                $other_village_stmt->execute();
                $other_village = $other_village_stmt->get_result()->fetch_assoc();
                $other_village_stmt->close();
                
                $village_name = $other_village ? $other_village['name'] : 'Unknown village';
                $coords = $other_village ? $other_village['x_coord'] . '|' . $other_village['y_coord'] : '?|?';
                $player_name = $other_village ? $other_village['username'] : 'Unknown player';
                
                $arrival_time = strtotime($row['arrival_time']);
                $current_time = time();
                $remaining_time = max(0, $arrival_time - $current_time);
                
                $active_trades[] = [
                    'id' => $row['id'],
                    'direction' => $direction,
                    'wood' => $row['wood'],
                    'clay' => $row['clay'],
                    'iron' => $row['iron'],
                    'village_name' => $village_name,
                    'coords' => $coords,
                    'player_name' => $player_name,
                    'arrival_time' => $row['arrival_time'],
                    'remaining_time' => $remaining_time,
                    'traders_count' => $row['traders_count']
                ];
            }
            $stmt->close();
            
            // Calculate number of available traders
            $traders_in_use = 0;
            foreach ($active_trades as $trade) {
                if ($trade['direction'] == 'outgoing') {
                    $traders_in_use += $trade['traders_count'];
                }
            }
            $available_traders = max(0, $traders_capacity - $traders_in_use);
            
            // Generate HTML
            ob_start();
            echo '<div class="building-actions">';
            echo '<h3>Market - trading</h3>';
            echo '<p>Trade resources with other players here.</p>';
            
            echo '<div class="market-info">';
            echo '<p>Available traders: <strong>' . $available_traders . '/' . $traders_capacity . '</strong></p>';
            echo '</div>';
            
            if ($available_traders > 0) {
                echo '<div class="send-resources">';
                echo '<h4>Send resources</h4>';
                echo '<form action="send_resources.php" method="post" id="send-resources-form">';
                echo '<input type="hidden" name="village_id" value="' . $village_id . '">';
                
                echo '<div class="form-group">';
                echo '<label for="target_coords">Target (coordinates x|y):</label>';
                echo '<input type="text" id="target_coords" name="target_coords" placeholder="500|500" pattern="\d+\|\d+" required>';
                echo '</div>';
                
                echo '<div class="resource-inputs">';
                echo '<div class="resource-input">';
                echo '<label for="wood">Wood:</label>';
                echo '<input type="number" id="wood" name="wood" min="0" value="0" required>';
                echo '</div>';
                
                echo '<div class="resource-input">';
                echo '<label for="clay">Clay:</label>';
                echo '<input type="number" id="clay" name="clay" min="0" value="0" required>';
                echo '</div>';
                
                echo '<div class="resource-input">';
                echo '<label for="iron">Iron:</label>';
                echo '<input type="number" id="iron" name="iron" min="0" value="0" required>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="current-resources">';
                echo '<p>Available resources: ';
                echo 'Wood: <strong>' . floor($village['wood']) . '</strong>, ';
                echo 'Clay: <strong>' . floor($village['clay']) . '</strong>, ';
                echo 'Iron: <strong>' . floor($village['iron']) . '</strong>';
                echo '</p>';
                echo '</div>';
                
                echo '<div class="form-actions">';
                echo '<button type="submit" class="send-button">Send resources</button>';
                echo '</div>';
                echo '</form>';
                echo '</div>';
            } else {
                echo '<div class="no-traders">';
                echo '<p>You have no traders available to send resources. Please wait for them to return.</p>';
                echo '</div>';
            }
            
            // Display active transports
            if (!empty($active_trades)) {
                echo '<div class="active-trades">';
                echo '<h4>Active transports</h4>';
                echo '<table class="trades-table">';
                echo '<tr><th>Direction</th><th>Resources</th><th>Target/Source</th><th>Arrival time</th></tr>';
                
                foreach ($active_trades as $trade) {
                    echo '<tr>';
                    echo '<td>' . ($trade['direction'] == 'outgoing' ? 'Outgoing' : 'Incoming') . '</td>';
                    echo '<td>';
                    echo 'Wood: ' . $trade['wood'] . '<br>';
                    echo 'Clay: ' . $trade['clay'] . '<br>';
                    echo 'Iron: ' . $trade['iron'];
                    echo '</td>';
                    echo '<td>';
                    echo htmlspecialchars($trade['village_name']) . ' (' . $trade['coords'] . ')<br>';
                    echo 'Player: ' . htmlspecialchars($trade['player_name']);
                    echo '</td>';
                    echo '<td class="trade-timer" data-ends-at="' . $trade['arrival_time'] . '">' . gmdate("H:i:s", $trade['remaining_time']) . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
                echo '</div>';
            } else {
                echo '<div class="no-trades">';
                echo '<p>You have no active transports.</p>';
                echo '</div>';
            }
            
            // Trade offer functionality - future feature
            echo '<div class="market-offers">';
            echo '<h4>Trade offers</h4>';
            echo '<p>Trade offers functionality will be added in a future update.</p>';
            echo '</div>';
            
            echo '</div>';
            $content = ob_get_clean();
            
            $response['additional_info_html'] = $content;
            break;
        case 10: // Palace/Residence
            $response['action_type'] = 'noble';
             $response['additional_info_html'] = '
                 <h3>Palace/Residence</h3>
                 <p>Recruit/manage nobles and mint coins (Palace only) here.</p>
                 <h4>Options:</h4>
                 <ul>
                     <li>Noble recruitment: TODO</li>
                     <li>Coin minting: TODO (Palace only)</li>
                     <!-- Add more options -->
                 </ul>
             ';
            // TODO: Add noble interface
            break;
         case 11: // Workshop
             $response['action_type'] = 'recruit_siege';
            
            // Fetch units available in the Workshop
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'UnitManager.php';
            $unitManager = new UnitManager($conn);
            $garageUnits = $unitManager->getUnitTypes('garage');
            
            // Fetch current units in the village
            $villageUnits = $unitManager->getVillageUnits($village_id);
            
            // Fetch recruitment queue
            $recruitmentQueue = $unitManager->getRecruitmentQueue($village_id, 'garage');
            
            // Build HTML for recruitment queue
            $queueHtml = '';
            if (!empty($recruitmentQueue)) {
                $queueHtml .= '<h4>Machines currently in production:</h4>';
                $queueHtml .= '<table class="recruitment-queue">';
                $queueHtml .= '<tr><th>Unit</th><th>Count</th><th>Remaining time</th><th>Action</th></tr>';
                
                foreach ($recruitmentQueue as $queue) {
                    $remaining = $queue['count'] - $queue['count_finished'];
                    $timeRemaining = gmdate("H:i:s", $queue['time_remaining']);
                    
                    $queueHtml .= '<tr>';
                    $queueHtml .= '<td>' . htmlspecialchars($queue['unit_name']) . '</td>';
                    $queueHtml .= '<td>' . $remaining . ' / ' . $queue['count'] . '</td>';
                    $queueHtml .= '<td class="build-timer" data-ends-at="' . ($queue['finish_at']) . '" data-item-description="Siege machine production">';
                    $queueHtml .= $timeRemaining;
                    $queueHtml .= '</td>';
                    $queueHtml .= '<td><a href="cancel_recruitment.php?queue_id=' . $queue['id'] . '" class="cancel-button">Cancel</a></td>';
                    $queueHtml .= '</tr>';
                }
                
                $queueHtml .= '</table>';
            }
            
            // Build HTML for recruitment form
            $unitsHtml = '';
            if (!empty($garageUnits)) {
                $unitsHtml .= '<h4>Available machines:</h4>';
                $unitsHtml .= '<form action="recruit_units.php" method="post" id="recruit-form">';
                $unitsHtml .= '<input type="hidden" name="building_type" value="garage">';
                $unitsHtml .= '<table class="recruitment-units">';
                $unitsHtml .= '<tr><th>Unit</th><th colspan="3">Cost</th><th>Time</th><th>Attack/Defense</th><th>Owned</th><th>Recruit</th></tr>';
                
                foreach ($garageUnits as $unitInternal => $unit) {
                    $canRecruit = true;
                    $disableReason = '';
                    
                    // Check requirements
                    $requirementsCheck = $unitManager->checkRecruitRequirements($unit['id'], $village_id);
                    if (!$requirementsCheck['can_recruit']) {
                        $canRecruit = false;
                        $disableReason = 'Required building level: ' . $requirementsCheck['required_building_level'];
                    }
                    
                    // Calculate recruitment time
                    $recruitTime = $unitManager->calculateRecruitmentTime($unit['id'], $building_details['level']);
                    $recruitTimeFormatted = gmdate("H:i:s", $recruitTime);
                    
                    $unitsHtml .= '<tr>';
                    $unitsHtml .= '<td><strong>' . htmlspecialchars($unit['name']) . '</strong><br><small>' . htmlspecialchars($unit['description']) . '</small></td>';
                    $unitsHtml .= '<td><img src="img/wood.png" title="Wood" alt="Wood"> ' . $unit['cost_wood'] . '</td>';
                    $unitsHtml .= '<td><img src="img/stone.png" title="Clay" alt="Clay"> ' . $unit['cost_clay'] . '</td>';
                    $unitsHtml .= '<td><img src="img/iron.png" title="Iron" alt="Iron"> ' . $unit['cost_iron'] . '</td>';
                    $unitsHtml .= '<td>' . $recruitTimeFormatted . '</td>';
                    $unitsHtml .= '<td>' . $unit['attack'] . '/' . $unit['defense'] . '</td>';
                    $unitsHtml .= '<td>' . ($villageUnits[$unitInternal] ?? 0) . '</td>';
                    
                    if ($canRecruit) {
                        $unitsHtml .= '<td>';
                        $unitsHtml .= '<input type="number" name="count" class="recruit-count" min="1" max="100" value="1">';
                        $unitsHtml .= '<input type="hidden" name="unit_type_id" value="' . $unit['id'] . '">';
                        $unitsHtml .= '<button type="submit" class="recruit-button">Produce</button>';
                        $unitsHtml .= '</td>';
                    } else {
                        $unitsHtml .= '<td title="' . htmlspecialchars($disableReason) . '"><button disabled>Unavailable</button></td>';
                    }
                    
                    $unitsHtml .= '</tr>';
                }
                
                $unitsHtml .= '</table>';
                $unitsHtml .= '</form>';
            } else {
                $unitsHtml .= '<p>No machines available to produce.</p>';
            }
            
            // Combine all HTML sections
             $response['additional_info_html'] = '
                <h3>Workshop</h3>
                <p>Produce siege machines here.</p>
                ' . $queueHtml . '
                ' . $unitsHtml . '
            ';
             break;
        case 12: // Academy
             $response['action_type'] = 'research_advanced';
             
             // Fetch available academy research
             require_once 'lib/ResearchManager.php';
             if (!isset($researchManager)) {
                 $researchManager = new ResearchManager($conn);
             }
             $academy_research_types = $researchManager->getResearchTypesForBuilding('academy');

            // Get current academy level
            $academy_level = $building_details['level'];

             // Get current research levels for the village
             $village_research_levels = $researchManager->getVillageResearchLevels($village_id);

             // Get current research queue for the village
             $research_queue = $researchManager->getResearchQueue($village_id);
             $current_research_ids = [];
             foreach ($research_queue as $queue_item) {
                 $current_research_ids[$queue_item['research_type_id']] = true;
             }

             // Build HTML for research interface
             $researchHtml = '<h3>Academy - Advanced Technologies</h3>';
             $researchHtml .= '<p>Research advanced military and civic technologies here.</p>';
             
             if (!empty($academy_research_types)) {
                 $researchHtml .= '<div class="research-list">';
                 
                 // First display ongoing research if present
                 if (!empty($research_queue)) {
                     $researchHtml .= '<h4>Current research:</h4>';
                     $researchHtml .= '<table class="research-queue">';
                     $researchHtml .= '<tr><th>Research</th><th>Target level</th><th>Remaining time</th><th>Action</th></tr>';
                     
                     foreach ($research_queue as $queue) {
                         if ($queue['building_type'] === 'academy') {
                             $end_time = strtotime($queue['ends_at']);
                             $current_time = time();
                             $remaining_time = max(0, $end_time - $current_time);
                             $time_remaining = gmdate("H:i:s", $remaining_time);
                             
                             $researchHtml .= '<tr>';
                             $researchHtml .= '<td>' . htmlspecialchars($queue['research_name']) . '</td>';
                             $researchHtml .= '<td>' . $queue['level_after'] . '</td>';
                             $researchHtml .= '<td class="build-timer" data-ends-at="' . ($queue['ends_at']) . '" data-item-description="Advanced research">';
                             $researchHtml .= $time_remaining;
                             $researchHtml .= '</td>';
                             $researchHtml .= '<td><a href="cancel_research.php?research_queue_id=' . $queue['id'] . '" class="cancel-button">Cancel</a></td>';
                             $researchHtml .= '</tr>';
                         }
                     }
                     
                     $researchHtml .= '</table>';
                 }
                 
                 // Then show available research
                 $researchHtml .= '<h4>Available research:</h4>';
                 $researchHtml .= '<table class="research-options">';
                 $researchHtml .= '<tr><th>Technology</th><th>Level</th><th colspan="3">Cost</th><th>Time</th><th>Action</th></tr>';
                 
                 foreach ($academy_research_types as $research) {
                     $research_id = $research['id'];
                     $internal_name = $research['internal_name'];
                     $name = $research['name'];
                     $description = $research['description'];
                     $required_level = $research['required_building_level'];
                     $max_level = $research['max_level'];
                     $current_level = $village_research_levels[$internal_name] ?? 0;
                     $next_level = $current_level + 1;

                     // Check availability
                     $is_available = $academy_level >= $required_level;
                     $is_at_max_level = $current_level >= $max_level;
                     $is_in_progress = isset($current_research_ids[$research_id]);
                     
                     // Check prerequisite research if present
                     $prereq_message = '';
                     if ($research['prerequisite_research_id'] && $is_available) {
                         $prereq = $researchManager->getResearchTypeById($research['prerequisite_research_id']);
                         if ($prereq) {
                             $prereq_internal_name = $prereq['internal_name'];
                             $prereq_required_level = $research['prerequisite_research_level'];
                             $prereq_current_level = $village_research_levels[$prereq_internal_name] ?? 0;
                             
                             if ($prereq_current_level < $prereq_required_level) {
                                 $is_available = false;
                                 $prereq_message = "Required research: " . $prereq['name'] . " level " . $prereq_required_level;
                             }
                         }
                     }
                     
                     // Calculate cost for the next level
                     $cost = null;
                     $time = null;
                     $can_research = false;

                     if (!$is_at_max_level && $is_available && !$is_in_progress) {
                         $cost = $researchManager->getResearchCost($research_id, $next_level);
                         $time = $researchManager->calculateResearchTime($research_id, $next_level, $academy_level);
                         
                         // Verify sufficient resources
                         $can_research = $village['wood'] >= $cost['wood'] && 
                                         $village['clay'] >= $cost['clay'] && 
                                         $village['iron'] >= $cost['iron'];
                     }
                     
                     $researchHtml .= '<tr class="research-item ' . (!$is_available ? 'unavailable' : '') . '">';
                     $researchHtml .= '<td><strong>' . htmlspecialchars($name) . '</strong><br><small>' . htmlspecialchars($description) . '</small></td>';
                     $researchHtml .= '<td>' . $current_level . '/' . $max_level . '</td>';
                     
                     if (!$is_at_max_level && !$is_in_progress) {
                         if ($is_available) {
                             $researchHtml .= '<td><img src="img/wood.png" title="Wood" alt="Wood"> ' . $cost['wood'] . '</td>';
                             $researchHtml .= '<td><img src="img/stone.png" title="Clay" alt="Clay"> ' . $cost['clay'] . '</td>';
                             $researchHtml .= '<td><img src="img/iron.png" title="Iron" alt="Iron"> ' . $cost['iron'] . '</td>';
                             $researchHtml .= '<td>' . gmdate("H:i:s", $time) . '</td>';
                             
                             $researchHtml .= '<td>';
                             $researchHtml .= '<form action="start_research.php" method="post" class="research-form">';
                             $researchHtml .= '<input type="hidden" name="village_id" value="' . $village_id . '">';
                             $researchHtml .= '<input type="hidden" name="research_type_id" value="' . $research_id . '">';
                             $researchHtml .= '<button type="submit" class="research-button" ' . ($can_research ? '' : 'disabled') . '>Research</button>';
                             $researchHtml .= '</form>';
                             $researchHtml .= '</td>';
                         } else {
                             if ($prereq_message) {
                                 $researchHtml .= '<td colspan="4">' . $prereq_message . '</td>';
                             } else {
                                 $researchHtml .= '<td colspan="4">Required academy level: ' . $required_level . '</td>';
                             }
                             $researchHtml .= '<td><button disabled>Unavailable</button></td>';
                         }
                     } else if ($is_at_max_level) {
                         $researchHtml .= '<td colspan="4">Maximum level reached</td>';
                         $researchHtml .= '<td>-</td>';
                     } else if ($is_in_progress) {
                         $researchHtml .= '<td colspan="4">Research in progress</td>';
                         $researchHtml .= '<td>-</td>';
                     }
                     
                     $researchHtml .= '</tr>';
                 }
                 
                 $researchHtml .= '</table>';
                 $researchHtml .= '</div>';
             } else {
                 $researchHtml .= '<p>No advanced research available in the academy.</p>';
             }
             
             $response['additional_info_html'] = $researchHtml;
             break;
         case 13: // Mint (dla monet)
             $response['action_type'] = 'mint';
              $response['additional_info_html'] = '
                  <h3>Mint</h3>
                  <p>Mint coins here (Palace only).</p>
                  <h4>Options:</h4>
                  <p>TODO: Coin minting interface.</p>
              ';
             // TODO: Dodaj interfejs production monet
             break;
        // Add more cases for other buildings

         default:
            // Default to 'upgrade' when no specific action exists
            $response['action_type'] = 'upgrade';

            $current_level = $building_details['level'];
            $next_level = $current_level + 1;
            $max_level = $building_details['max_level'];
            $internal_name = $building_details['internal_name'];

            $upgradeDetails = null;

            // Check if upgrade is possible (max level not reached)
            if ($current_level < $max_level) {
                 // Fetch upgrade cost
                 $costDetails = $buildingManager->getBuildingUpgradeCost($internal_name, $next_level);

                // Fetch build time (requires Town Hall level)
                 // $main_building_level is retrieved at the start of the script
                 $timeInSeconds = $buildingManager->getBuildingUpgradeTime($internal_name, $next_level, $main_building_level);
                 $timeFormatted = ($timeInSeconds !== null) ? gmdate("H:i:s", $timeInSeconds) : null;

                 if ($costDetails !== null && $timeFormatted !== null) {

                     // Fetch current village resources
                      $villageManager = new VillageManager($conn);
                      $currentResources = $villageManager->getVillageResources($village_id);

                     // Check resource sufficiency
                     $has_resources = ($currentResources['wood'] >= $costDetails['wood'] &&
                                       $currentResources['clay'] >= $costDetails['clay'] &&
                                       $currentResources['iron'] >= $costDetails['iron']);

                     // TODO: Verify structural requirements (e.g., town hall level, other buildings)
                     // Placeholder: always true for now; add dependency logic later
                     $requirements_met = true; // Temporary value

                     $can_upgrade = $has_resources && $requirements_met;

                     $upgradeDetails = [
                          'next_level' => $next_level,
                          'max_level' => $max_level,
                          'cost' => [
                              'wood' => $costDetails['wood'],
                              'clay' => $costDetails['clay'],
                              'iron' => $costDetails['iron'],
                          ],
                          'time' => $timeFormatted, // Formatted build time
                          'can_upgrade_structurally' => $requirements_met, // Structural requirements met
                          'has_resources' => $has_resources, // Whether the player has the resources
                          'can_upgrade' => $can_upgrade // Whether upgrade is allowed
                     ];

                     // Additional info for upgrade view (optional)
                      $response['additional_info_html'] = '<p>Description: ' . htmlspecialchars($building_details['description']) . '</p>'; // Additional building bonuses can be included here
                 }
            }

             $response['details'] = [
                 'building_type_id' => $building_details['building_type_id'],
                 'name' => $building_details['name'],
                 'level' => $building_details['level'],
                 'description' => $building_details['description'],
                 'additional_info_html' => $response['additional_info_html'], // Przekazujemy dodatkowe info
                 'upgrade' => $upgradeDetails,
             ];

            break;
    }

    // If action is 'upgrade' and there are no upgrade details (e.g., max level or data error),
    // ensure the 'upgrade' key in 'details' is null or absent.
    // Previous logic handles this, but double-check for safety.
    if ($response['action_type'] === 'upgrade' && (!isset($response['details']['upgrade']) || !$response['details']['upgrade'])) {
         if (isset($response['details']['upgrade'])) {
             // If upgrade is false/null but the key exists, unset it for clarity
             unset($response['details']['upgrade']);
         }
        // You could set another action_type (e.g., 'info_max_level') for max-level views
         // Na razie pozostajemy przy action_type 'upgrade', ale bez sekcji 'upgrade' w odpowiedzi
    }

    // Clear output buffer before sending final JSON response
    ob_clean();
    echo json_encode($response);

} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'A server error occurred: ' . $e->getMessage()]);
    error_log("Error in get_building_action.php: " . $e->getMessage());
} catch (Error $e) {
     ob_clean();
     header('Content-Type: application/json');
     echo json_encode(['error' => 'A critical server error occurred: ' . $e->getMessage()]);
     error_log("Critical error in get_building_action.php: " . $e->getMessage());
}

// Cosmetic comment to potentially refresh PHP opcode cache

?> 
