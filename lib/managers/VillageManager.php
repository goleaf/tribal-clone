<?php
declare(strict_types=1);

/**
 * Village manager inspired by the legacy VeryOldTemplate classes.
 */
class VillageManager
{
    private $conn;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    /**
     * Fetches basic village info.
     */
    public function getVillageInfo($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM villages 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $village_info = $result->fetch_assoc();
        $stmt->close();

        return $village_info;
    }

    /**
     * Alias for getVillageInfo (backward compatibility).
     */
    public function getVillageDetails($village_id)
    {
        return $this->getVillageInfo($village_id);
    }

    /**
     * Updates village resources based on production time.
     * Delegates logic to ResourceManager.
     */
    public function updateResources($village_id): bool
    {
        // Fetch village data
        $village = $this->getVillageInfo($village_id);
        if (!$village) {
            return false; // Village not found
        }
        
        // Check whether enough time has passed for production
        $last_update = strtotime($village['last_resource_update']);
        $elapsed_seconds = time() - $last_update;

        if ($elapsed_seconds <= 0) {
            return false; // Nothing changed
        }

        // ResourceManager needs BuildingManager to compute production
        require_once __DIR__ . '/BuildingManager.php';
        // BuildingManager potrzebuje BuildingConfigManager
        require_once __DIR__ . '/BuildingConfigManager.php';

        // Instantiating managers inline; ideally use dependency injection.
        $buildingConfigManager = new BuildingConfigManager($this->conn);
        $buildingManager = new BuildingManager($this->conn, $buildingConfigManager);

        require_once __DIR__ . '/ResourceManager.php';
        $resourceManager = new ResourceManager($this->conn, $buildingManager);

        // Delegate resource update
        // ResourceManager::updateVillageResources requires full village array
        $updated_village = $resourceManager->updateVillageResources($village);
        
        // updateVillageResources already persists changes; return status only.
        return true;
    }

    /**
     * Fetches all buildings in the village.
     */
    public function getVillageBuildings($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT vb.id, vb.level, 
                   bt.id AS building_type_id, bt.internal_name, bt.name
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ?
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $buildings = [];
        while ($row = $result->fetch_assoc()) {
            // Prepare building data, including upgrade queue status
            $building = $row;
            $building['is_upgrading'] = false; // Default: not upgrading
            $building['queue_finish_time'] = null;
            $building['queue_level_after'] = null;

            // Check build queue for this specific village_buildings.id
            $stmt_queue = $this->conn->prepare("
                SELECT level, finish_time
                FROM building_queue
                WHERE village_building_id = ?
                LIMIT 1
            ");
            $stmt_queue->bind_param("i", $building['id']);
            $stmt_queue->execute();
            $queue_result = $stmt_queue->get_result();
            $queue_item = $queue_result->fetch_assoc();
            $stmt_queue->close();

            if ($queue_item) {
                $building['is_upgrading'] = true;
                $building['queue_finish_time'] = strtotime($queue_item['finish_time']);
                $building['queue_level_after'] = (int)$queue_item['level'];
            }
            
            $buildings[$building['internal_name']] = $building; // Use internal_name as key
        }
        $stmt->close();

        return $buildings;
    }

    /**
     * Processes completed tasks for the village (build, recruit, research).
     * Should be called e.g., at the start of game.php.
     * Returns an array of messages to display.
     */
    public function processCompletedTasksForVillage(int $village_id): array
    {
        $messages = [];
        $village = $this->getVillageInfo($village_id);
        if (!$village) {
            return $messages;
        }
        $userId = (int)$village['user_id'];
        
        // 1. Update resources (covers offline production)
        $this->updateResources($village_id);
        // Refresh village after resource update
        $village = $this->getVillageInfo($village_id);

        // Required managers
        require_once __DIR__ . '/BuildingManager.php';
        require_once __DIR__ . '/UnitManager.php';
        require_once __DIR__ . '/ResearchManager.php';
        require_once __DIR__ . '/BuildingConfigManager.php';
        require_once __DIR__ . '/ResourceManager.php';
        require_once __DIR__ . '/AchievementManager.php';
        require_once __DIR__ . '/TradeManager.php';
        require_once __DIR__ . '/NotificationManager.php';

        // Instantiate managers (DI would be better)
        $buildingConfigManager = new BuildingConfigManager($this->conn);
        $buildingManager = new BuildingManager($this->conn, $buildingConfigManager);
        $unitManager = new UnitManager($this->conn);
        $researchManager = new ResearchManager($this->conn);
        $achievementManager = new AchievementManager($this->conn);
        $tradeManager = new TradeManager($this->conn);
        $notificationManager = new NotificationManager($this->conn);
        // BattleManager could be added here when needed.

        // Evaluate snapshot-based achievements and resource milestones
        if ($userId) {
            $achievementManager->evaluateAutoUnlocks($userId);
            $achievementManager->checkResourceStock($userId, $village, $village_id);
        }

        // 2. Complete finished building tasks
        $completed_buildings = $this->processBuildingQueue($village_id);
        foreach ($completed_buildings as $item) {
            $messages[] = "<p class='success-message'>Upgrade completed: <b>" . htmlspecialchars($item['name']) . "</b> to level " . $item['level'] . ".</p>";
            if ($userId) {
                $achievementManager->checkBuildingLevel($userId, $item['internal_name'], (int)$item['level'], $village_id);
                $notificationManager->addNotification(
                    $userId,
                    sprintf('Upgrade completed: %s to level %d.', $item['name'], $item['level']),
                    'success',
                    '/game/game.php'
                );
            }
        }

        // 3. Complete unit recruitment tasks
        $recruitmentUpdate = $unitManager->processRecruitmentQueue($village_id);
         if (!empty($recruitmentUpdate['completed_queues'])) {
            foreach ($recruitmentUpdate['completed_queues'] as $queue) {
                $messages[] = "<p class='success-message'>Recruitment completed: " . $queue['count'] . " units of '" . htmlspecialchars($queue['unit_name']) . "'.</p>";
                if ($userId) {
                    $achievementManager->addUnitsTrainedProgress($userId, (int)($queue['produced_now'] ?? $queue['count'] ?? 0));
                    $notificationManager->addNotification(
                        $userId,
                        sprintf('Recruitment completed: %d × %s.', $queue['count'], $queue['unit_name']),
                        'success',
                        '/game/game.php'
                    );
                }
            }
        }
         if (!empty($recruitmentUpdate['updated_queues']) && empty($recruitmentUpdate['completed_queues'])) {
            foreach ($recruitmentUpdate['updated_queues'] as $update) {
                 $messages[] = "<p class='success-message'>Produced " . $update['units_finished'] . " units of '" . htmlspecialchars($update['unit_name']) . "'. Recruitment continues...</p>";
                 if ($userId) {
                     $achievementManager->addUnitsTrainedProgress($userId, (int)($update['produced_now'] ?? $update['units_finished'] ?? 0));
                 }
            }
        }

        // 4. Complete research tasks
        $researchUpdate = $researchManager->processResearchQueue($village_id);
         if (!empty($researchUpdate['completed_research'])) {
            foreach ($researchUpdate['completed_research'] as $research) {
                $messages[] = "<p class='success-message'>Research completed: <b>" . htmlspecialchars($research['research_name']) . "</b> to level " . $research['level'] . ".</p>";
                if ($userId) {
                    $notificationManager->addNotification(
                        $userId,
                        sprintf('Research completed: %s to level %d.', $research['research_name'], $research['level']),
                        'info',
                        '/game/game.php'
                    );
                }
            }
        }

        // 5. Complete finished trade routes involving this village
        $tradeMessages = $tradeManager->processArrivedTradesForVillage($village_id);
        foreach ($tradeMessages as $tradeMessage) {
            $messages[] = "<p class='success-message'>" . htmlspecialchars($tradeMessage) . "</p>";
            if ($userId) {
                $notificationManager->addNotification(
                    $userId,
                    $tradeMessage,
                    'success',
                    '/game/game.php'
                );
            }
        }

        // 6. Attack processing is handled in BattleManager (see game.php usage).

        return $messages;
    }

    /**
     * Processes the building_queue, updating levels and clearing entries.
     * Returns a list of completed build tasks.
     */
    public function processBuildingQueue(int $village_id): array
    {
        $completed_queue_items = [];
        
        // Fetch completed builds from the queue
        $stmt_check_finished = $this->conn->prepare("SELECT bq.id, bq.village_building_id, bq.level, bq.is_demolition, bq.refund_wood, bq.refund_clay, bq.refund_iron, bt.name, bt.internal_name FROM building_queue bq JOIN building_types bt ON bq.building_type_id = bt.id WHERE bq.village_id = ? AND bq.finish_time <= NOW()");
        $stmt_check_finished->bind_param("i", $village_id);
        $stmt_check_finished->execute();
        $result_finished = $stmt_check_finished->get_result();
        
        // Begin transaction for processing completed builds
        $this->conn->begin_transaction();

        try {
            while ($finished_building_queue_item = $result_finished->fetch_assoc()) {
                // Update building level in village_buildings
                $stmt_update_vb_level = $this->conn->prepare("UPDATE village_buildings SET level = ? WHERE id = ?");
                $stmt_update_vb_level->bind_param("ii", $finished_building_queue_item['level'], $finished_building_queue_item['village_building_id']);
                if (!$stmt_update_vb_level->execute()) {
                    throw new Exception("Failed to update building level after completion for village_building_id " . $finished_building_queue_item['village_building_id'] . ".");
                }
                $stmt_update_vb_level->close();

                // Apply demolition refunds (if any)
                if (!empty($finished_building_queue_item['is_demolition'])) {
                    $refundWood = (int)$finished_building_queue_item['refund_wood'];
                    $refundClay = (int)$finished_building_queue_item['refund_clay'];
                    $refundIron = (int)$finished_building_queue_item['refund_iron'];
                    if ($refundWood || $refundClay || $refundIron) {
                        $stmt_refund = $this->conn->prepare("
                            UPDATE villages
                            SET wood = wood + ?, clay = clay + ?, iron = iron + ?
                            WHERE id = ?
                        ");
                        if ($stmt_refund) {
                            $stmt_refund->bind_param("iiii", $refundWood, $refundClay, $refundIron, $village_id);
                            $stmt_refund->execute();
                            $stmt_refund->close();
                        }
                    }
                }

                // Remove task from build queue
                $stmt_delete_queue_item = $this->conn->prepare("DELETE FROM building_queue WHERE id = ?");
                $stmt_delete_queue_item->bind_param("i", $finished_building_queue_item['id']);
                if (!$stmt_delete_queue_item->execute()) {
                     throw new Exception("Failed to remove queue task after build completion for id " . $finished_building_queue_item['id'] . ".");
                }
                $stmt_delete_queue_item->close();
                
                $completed_queue_items[] = $finished_building_queue_item; // Add to completed list
            }

            // Commit transaction
            $this->conn->commit();

        } catch (Exception $e) {
            // Roll back transaction on error
            $this->conn->rollback();
             error_log("Error in processBuildingQueue for village {$village_id}: " . $e->getMessage());
            throw $e; // Re-throw
        } finally {
             $result_finished->free();
            $stmt_check_finished->close();
        }

        return $completed_queue_items;
    }

    /**
     * Updates village population capacity based on building levels.
     * Called after completing a building (e.g., farm).
     */
    public function updateVillagePopulation(int $village_id): bool
    {
        // Requires BuildingConfigManager to calculate farm capacity
         require_once __DIR__ . '/BuildingConfigManager.php';
         $buildingConfigManager = new BuildingConfigManager($this->conn); // Create instance (should be injected)

        // Fetch farm level
        $farm_level = 0;
        $farm_stmt = $this->conn->prepare("
            SELECT vb.level 
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = 'farm'
        ");
        $farm_stmt->bind_param("i", $village_id);
        $farm_stmt->execute();
        $farm_result = $farm_stmt->get_result();
        if ($farm_row = $farm_result->fetch_assoc()) {
            $farm_level = $farm_row['level'];
        }
        $farm_stmt->close();

        // Calculate farm capacity
        $max_population = $buildingConfigManager->calculateFarmCapacity($farm_level);
        
        // Ideally population should sum building/unit usage; currently only farm capacity is updated.

        $update_stmt = $this->conn->prepare("
            UPDATE villages 
            SET farm_capacity = ?
            WHERE id = ?
        ");
        $update_stmt->bind_param("ii", $max_population, $village_id);
        $result = $update_stmt->execute();
        $update_stmt->close();

        return $result;
    }

    /**
     * Creates a new village with starter buildings (transactional).
     */
    public function createVillage($user_id, $name = '', $x = null, $y = null)
    {
        // Fetch username
        $stmt_user = $this->conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $user = $stmt_user->get_result()->fetch_assoc();
        $stmt_user->close();
        $username = $user ? $user['username'] : 'Player';
        
        // Unique village name
        $village_name = $name ?: ("Village " . $username);
        
        // Random free coordinates near map center (e.g., 40-60)
        $found = false;
        $tries = 0;
        do {
            $x_try = $x ?? random_int(40, 60);
            $y_try = $y ?? random_int(40, 60);
            $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM villages WHERE x_coord = ? AND y_coord = ?");
            $stmt->bind_param("ii", $x_try, $y_try);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
            if ($cnt == 0) { $found = true; $x = $x_try; $y = $y_try; }
            $tries++;
        } while (!$found && $tries < 100);
        if (!$found) return ['success'=>false,'message'=>'No free tiles in the map center!'];
        
        // Starting resources and buildings
        $worldId = defined('INITIAL_WORLD_ID') ? INITIAL_WORLD_ID : 1;
        $wood = defined('INITIAL_WOOD') ? INITIAL_WOOD : 500;
        $clay = defined('INITIAL_CLAY') ? INITIAL_CLAY : 500;
        $iron = defined('INITIAL_IRON') ? INITIAL_IRON : 500;
        $warehouse = defined('INITIAL_WAREHOUSE_CAPACITY') ? INITIAL_WAREHOUSE_CAPACITY : 1000;
        $population = defined('INITIAL_POPULATION') ? INITIAL_POPULATION : 1;
        
        $stmt = $this->conn->prepare("INSERT INTO villages (user_id, world_id, name, x_coord, y_coord, wood, clay, iron, warehouse_capacity, population, last_resource_update) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisiiiiiii", $user_id, $worldId, $village_name, $x, $y, $wood, $clay, $iron, $warehouse, $population);
        if (!$stmt->execute()) {
            return ['success' => false, 'message' => 'Error while creating village: ' . $stmt->error];
        }
        $village_id = $stmt->insert_id;
        $stmt->close();
        
        // Starter buildings: town hall, sawmill, clay pit, iron mine, warehouse, farm (all level 1)
        $basic_buildings = [
            'main_building' => 1,
            'sawmill' => 1,
            'clay_pit' => 1,
            'iron_mine' => 1,
            'warehouse' => 1,
            'farm' => 1
        ];
        foreach ($basic_buildings as $building_name => $level) {
            $this->createBuildingInVillage($village_id, $building_name, $level);
        }
        $this->updateVillagePopulation($village_id);
        // Recalculate player points
        if (method_exists($this, 'recalculatePlayerPoints')) {
            $this->recalculatePlayerPoints($user_id);
        }

        // Evaluate starting achievements (e.g., first village, initial buildings)
        require_once __DIR__ . '/AchievementManager.php';
        $achievementManager = new AchievementManager($this->conn);
        $achievementManager->evaluateAutoUnlocks((int)$user_id);

        return [
            'success' => true, 
            'message' => 'Village created successfully!', 
            'village_id' => $village_id
        ];
    }

    /**
     * Creates default buildings for a new village.
     * @param int $village_id Newly created village ID.
     * @return bool Sukces operacji.
     */
    private function createInitialBuildings(int $village_id): bool
    {
        // Requires BuildingConfigManager to fetch building types
         require_once __DIR__ . '/BuildingConfigManager.php';
         $buildingConfigManager = new BuildingConfigManager($this->conn); // Create instance (should be injected)

        // Fetch all building types from DB
        $buildingTypes = $buildingConfigManager->getAllBuildingConfigs(); // Potrzebna publiczna metoda

        if (empty($buildingTypes)) {
            error_log("Error: No building types in database during village creation.");
            return false; // Cannot create buildings if types are missing
        }

        $insert_stmt = $this->conn->prepare("
            INSERT INTO village_buildings (village_id, building_type_id, level)
            VALUES (?, ?, ?)
        ");
        
        foreach ($buildingTypes as $type) {
             // For a new village, all buildings start at 0 or 1 (main building)
             $initial_level = ($type['internal_name'] === 'main_building') ? 1 : 0;
             // Resolve building_type_id from internal_name
             $building_type_id = $type['id']; // id expected in config

            $insert_stmt->bind_param("iii", $village_id, $building_type_id, $initial_level);
            if (!$insert_stmt->execute()) {
                error_log("Error adding building '{$type['internal_name']}' to village {$village_id}: " . $insert_stmt->error);
                $insert_stmt->close();
                return false; // Failed to add building
            }
        }
        
        $insert_stmt->close();

        return true;
    }


    /**
     * Creates a single building in a village with a given level.
     * Used mainly during village initialization.
     * @deprecated Preferuj createInitialBuildings.
     */
    private function createBuildingInVillage($village_id, $building_internal_name, $level = 0)
    {
        // Fetch building type ID
        $type_stmt = $this->conn->prepare("
            SELECT id 
            FROM building_types 
            WHERE internal_name = ?
        ");
        $type_stmt->bind_param("s", $building_internal_name);
        $type_stmt->execute();
        $type_result = $type_stmt->get_result();
        
        if ($type_result->num_rows === 0) {
            return false;
        }
        
        $building_type_id = $type_result->fetch_assoc()['id'];
        $type_stmt->close();
        
        // Add building to the village
        $stmt = $this->conn->prepare("
            INSERT INTO village_buildings (village_id, building_type_id, level) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iii", $village_id, $building_type_id, $level);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Renames a village.
     */
    public function renameVillage($village_id, $user_id, $new_name)
    {
        // Verify the village belongs to the user
        $stmt = $this->conn->prepare("
            SELECT id 
            FROM villages 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $village_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'You do not have access to this village.'];
        }
        $stmt->close();
        
        // Rename village
        $update_stmt = $this->conn->prepare("
            UPDATE villages 
            SET name = ? 
            WHERE id = ?
        ");
        $update_stmt->bind_param("si", $new_name, $village_id);
        
        if (!$update_stmt->execute()) {
            return ['success' => false, 'message' => 'Error while changing name: ' . $update_stmt->error];
        }
        $update_stmt->close();
        
        return ['success' => true, 'message' => 'Village name has been changed.'];
    }

    /**
     * Gets all villages for a user.
     * @return array List of villages (or empty array).
     */
    public function getUserVillages(int $user_id): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, name, x_coord, y_coord
            FROM villages
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $villages = [];
        while ($row = $result->fetch_assoc()) {
            $villages[] = $row;
        }
        $stmt->close();

        return $villages;
    }

     /**
     * Gets only IDs of all villages for a user (e.g., for attack checks).
     * @return array List of village IDs (or empty array).
     */
    public function getUserVillageIds(int $user_id): array
    {
        $stmt = $this->conn->prepare("
            SELECT id
            FROM villages
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $village_ids = [];
        while ($row = $result->fetch_assoc()) {
            $village_ids[] = (int)$row['id'];
        }
        $stmt->close();

        return $village_ids;
    }

    /**
     * Gets the first (default) village for a user (e.g., after login).
     * @return array|null Village data or null if none.
     */
    public function getFirstVillage(int $user_id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, name, x_coord, y_coord, wood, clay, iron, warehouse_capacity, population, farm_capacity, last_resource_update
            FROM villages
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $village = $result->fetch_assoc();
        $stmt->close();

        return $village;
    }

    /**
     * Fetches current hourly production for a resource in a village.
     * @deprecated Prefer ResourceManager->getHourlyProductionRate
     */
    public function getResourceProduction($village_id, $resource_type)
    {
        // Deprecated; should be handled by ResourceManager. Temporary fallback:
        
        require_once __DIR__ . '/BuildingConfigManager.php';
        require_once __DIR__ . '/BuildingManager.php';
        require_once __DIR__ . '/ResourceManager.php';

        // Temporary instantiation
        $buildingConfigManager = new BuildingConfigManager($this->conn);
        $buildingManager = new BuildingManager($this->conn, $buildingConfigManager);
        $resourceManager = new ResourceManager($this->conn, $buildingManager);

        // Use ResourceManager to fetch production
        return $resourceManager->getHourlyProductionRate($village_id, $resource_type);
    }

    /**
     * Fetches the current (first in queue) build task for a village.
     * Returns task data or null if queue is empty.
     */
    public function getBuildingQueueItem(int $village_id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT bq.id, bq.village_building_id, bq.building_type_id, bq.level, bq.starts_at, bq.finish_time, 
                   bt.internal_name, bt.name
            FROM building_queue bq
            JOIN building_types bt ON bq.building_type_id = bt.id
            WHERE bq.village_id = ?
            ORDER BY bq.starts_at ASC
            LIMIT 1
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();

        return $item;
    }
} 
