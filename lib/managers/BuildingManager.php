<?php

class BuildingManager {
    private $conn;
    private $buildingConfigManager;

    public function __construct($db_connection, BuildingConfigManager $buildingConfigManager) {
        $this->conn = $db_connection;
        $this->buildingConfigManager = $buildingConfigManager;
    }

    /**
     * Calculates hourly resource production for a building at a given level.
     * Returns 0 when the building does not produce or data is missing.
     */
    public function getHourlyProduction($building_internal_name, $level) {
        if ($level == 0) return 0;
        $config = $this->buildingConfigManager->getBuildingConfig($building_internal_name);
        
        if (!$config || !$config['production_type'] || $config['production_initial'] === null || $config['production_factor'] === null) {
            return 0;
        }
        
        return floor($this->buildingConfigManager->calculateProduction($building_internal_name, $level));
    }

    /**
     * Calculates warehouse capacity at a given level.
     * Currently uses a config-based constant, but can be made dynamic later.
     */
    public function getWarehouseCapacityByLevel($warehouse_level) {
         if ($warehouse_level <= 0) {
             return defined('INITIAL_WAREHOUSE_CAPACITY') ? INITIAL_WAREHOUSE_CAPACITY : 1000;
         }
         
         $capacity = $this->buildingConfigManager->calculateWarehouseCapacity($warehouse_level);
         
         return $capacity ?? (defined('INITIAL_WAREHOUSE_CAPACITY') ? INITIAL_WAREHOUSE_CAPACITY : 1000);
    }

    public function getBuildingDisplayName($internal_name) {
        $config = $this->buildingConfigManager->getBuildingConfig($internal_name);
        return $config ? $config['name'] : 'Unknown building';
    }

    public function getBuildingMaxLevel($internal_name) {
        $config = $this->buildingConfigManager->getBuildingConfig($internal_name);
        return $config ? (int)$config['max_level'] : 0;
    }

    /**
     * Calculates the cost to upgrade a building to the next level.
     * Returns ['wood' => cost, 'clay' => cost, 'iron' => cost] or null on error.
     */
    public function getBuildingUpgradeCost($internal_name, $next_level) {
        if ($next_level <= 0) return null;
        $config = $this->buildingConfigManager->getBuildingConfig($internal_name);

        if (!$config || $next_level > $config['max_level']) {
            return null;
        }

        return $this->buildingConfigManager->calculateUpgradeCost($internal_name, $next_level - 1);
    }

    /**
     * Calculates the time to upgrade a building to the next level (seconds).
     * The time depends on the town hall level.
     */
    public function getBuildingUpgradeTime($internal_name, $next_level, $main_building_level) {
        if ($next_level <= 0) return null;
        $config = $this->buildingConfigManager->getBuildingConfig($internal_name);
        if (!$config || $next_level > $config['max_level']) {
            return null;
        }

        return $this->buildingConfigManager->calculateUpgradeTime($internal_name, $next_level - 1, $main_building_level);
    }

    public function getBuildingInfo($internal_name) {
        return $this->buildingConfigManager->getBuildingConfig($internal_name);
    }

    public function getBuildingInfoById($building_type_id) {
         error_log("WARNING: BuildingManager::getBuildingInfoById is used. Prefer getBuildingInfo by internal_name.");
        $stmt = $this->conn->prepare("SELECT * FROM building_types WHERE id = ? LIMIT 1");
         if ($stmt === false) {
             error_log("Prepare failed for getBuildingInfoById: " . $this->conn->error);
             return null;
         }
        $stmt->bind_param("i", $building_type_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $config = $result->fetch_assoc();
        $stmt->close();
        return $config;

    }
    
    /**
     * Alias for getBuildingUpgradeCost for backward compatibility
     */
    public function getUpgradeCosts($internal_name, $next_level) {
        return $this->getBuildingUpgradeCost($internal_name, $next_level);
    }
    
    /**
     * Alias for getBuildingUpgradeTime for backward compatibility
     */
    public function getUpgradeTimeInSeconds($internal_name, $next_level, $main_building_level) {
        return $this->getBuildingUpgradeTime($internal_name, $next_level, $main_building_level);
    }
    
    /**
     * Checks whether requirements for other buildings are met to upgrade this building.
     * Inspired by the legacy builds::check_needed method.
     */
    public function checkBuildingRequirements($internal_name, $village_id) {
        $requirements = $this->buildingConfigManager->getBuildingRequirements($internal_name);

        if (empty($requirements)) {
            return ['success' => true, 'message' => 'No additional building requirements.'];
        }

        $stmt = $this->conn->prepare("
            SELECT bt.internal_name, vb.level 
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ?
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $village_buildings = [];
        while ($row = $result->fetch_assoc()) {
            $village_buildings[$row['internal_name']] = $row['level'];
        }
        $stmt->close();
        
        foreach ($requirements as $req) {
            $requiredBuildingName = $req['required_building'];
            $requiredLevel = $req['required_level'];

            $currentLevel = $village_buildings[$requiredBuildingName] ?? 0;

            if ($currentLevel < $requiredLevel) {
                $requiredBuildingDisplayName = $this->buildingConfigManager->getBuildingConfig($requiredBuildingName)['name'] ?? $requiredBuildingName;
                return [
                    'success' => false,
                    'message' => "Requires " . htmlspecialchars($requiredBuildingDisplayName) . " at level " . $requiredLevel . ". Your current level: " . $currentLevel
                ];
            }
        }

        return ['success' => true, 'message' => 'Requirements met.'];
    }

    public function getBuildingLevel(int $villageId, string $internalName): int
    {
        $stmt = $this->conn->prepare("
            SELECT vb.level
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = ?
        ");
        
        if ($stmt === false) {
             error_log("Prepare failed for getBuildingLevel: " . $this->conn->error);
             return 0;
        }

        $stmt->bind_param("is", $villageId, $internalName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $level = (int)$result->fetch_assoc()['level'];
            $stmt->close();
            return $level;
        }
        
        $stmt->close();
        return 0;
    }

    public function canUpgradeBuilding(int $villageId, string $internalName): array
    {
        $currentLevel = $this->getBuildingLevel($villageId, $internalName);
        
        $config = $this->buildingConfigManager->getBuildingConfig($internalName);
        if (!$config) {
             return ['success' => false, 'message' => 'Unknown building type.'];
        }
        
        if ($currentLevel >= $config['max_level']) {
            return ['success' => false, 'message' => 'Maximum level reached for this building.'];
        }
        
        $stmt_queue = $this->conn->prepare("SELECT COUNT(*) as count FROM building_queue WHERE village_id = ?");
         if ($stmt_queue === false) {
             error_log("Prepare failed for queue check: " . $this->conn->error);
             return ['success' => false, 'message' => 'Server error while checking the queue.'];
        }
        $stmt_queue->bind_param("i", $villageId);
        $stmt_queue->execute();
        $queue_result = $stmt_queue->get_result()->fetch_assoc();
        $stmt_queue->close();
        
        if ($queue_result['count'] > 0) {
            return ['success' => false, 'message' => 'Another upgrade is already in progress in this village.'];
        }

        $nextLevel = $currentLevel + 1;
        $upgradeCosts = $this->buildingConfigManager->calculateUpgradeCost($internalName, $currentLevel);
        
        if (!$upgradeCosts) {
             return ['success' => false, 'message' => 'Cannot calculate upgrade costs.'];
        }

        $stmt_resources = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
         if ($stmt_resources === false) {
              error_log("Prepare failed for resource check: " . $this->conn->error);
              return ['success' => false, 'message' => 'Server error while fetching resources.'];
         }
        $stmt_resources->bind_param("i", $villageId);
        $stmt_resources->execute();
        $resources = $stmt_resources->get_result()->fetch_assoc();
        $stmt_resources->close();
        
        if (!$resources) {
             return ['success' => false, 'message' => 'Cannot fetch village resources.'];
        }

        if ($resources['wood'] < $upgradeCosts['wood'] || 
            $resources['clay'] < $upgradeCosts['clay'] || 
            $resources['iron'] < $upgradeCosts['iron']) {
            return ['success' => false, 'message' => 'Not enough resources.'];
        }
        
        $requirementsCheck = $this->checkBuildingRequirements($internalName, $villageId);
        if (!$requirementsCheck['success']) {
            return $requirementsCheck;
        }
        
        return ['success' => true, 'message' => 'Upgrade possible.'];
    }
    
    public function addBuildingToQueue(int $villageId, string $internalName): array
    {
        // Check if there's already an item in the queue for this village
        if ($this->isAnyBuildingInQueue($villageId)) {
            return ['success' => false, 'message' => 'Another task is already in this village\'s build queue.'];
        }

        // Get current building level
        $currentLevel = $this->getBuildingLevel($villageId, $internalName);
        $nextLevel = $currentLevel + 1;

        // Get building config to check max level
        $config = $this->buildingConfigManager->getBuildingConfig($internalName);
        if (!$config) {
             return ['success' => false, 'message' => 'Unknown building type.'];
        }
        
        if ($currentLevel >= $config['max_level']) {
            return ['success' => false, 'message' => 'Maximum level reached for this building.'];
        }

        // Check if requirements are met (although this should be checked before calling this method, good to double-check)
        $requirementsCheck = $this->checkBuildingRequirements($internalName, $villageId);
         if (!$requirementsCheck['success']) {
            return $requirementsCheck; // Return the specific requirement message
        }

        // Calculate upgrade time (need Main Building level)
        $mainBuildingLevel = $this->getBuildingLevel($villageId, 'main_building');
        $upgradeTimeSeconds = $this->buildingConfigManager->calculateUpgradeTime($internalName, $currentLevel, $mainBuildingLevel); // calculateUpgradeTime uses currentLevel

        if ($upgradeTimeSeconds === null) {
             return ['success' => false, 'message' => 'Cannot calculate upgrade time.'];
        }

        $finishTime = date('Y-m-d H:i:s', time() + $upgradeTimeSeconds);

        // Get the village_building_id for the specific building in this village
        $villageBuilding = $this->getVillageBuilding($villageId, $internalName);
        if (!$villageBuilding) {
             return ['success' => false, 'message' => 'Building not found in the village.'];
        }
        $villageBuildingId = $villageBuilding['village_building_id'] ?? null; // Ensure this is the correct column name
        
        // Need building_type_id for the queue table
        $buildingTypeId = $config['id'] ?? null;
        if ($villageBuildingId === null || $buildingTypeId === null) {
             error_log("Missing village_building_id ($villageBuildingId) or building_type_id ($buildingTypeId) for village $villageId, building $internalName");
             return ['success' => false, 'message' => 'Internal server error (missing IDs).'];
        }

        // Add to building_queue table
        $stmt = $this->conn->prepare("INSERT INTO building_queue (village_id, village_building_id, building_type_id, level, start_time, finish_time) VALUES (?, ?, ?, ?, NOW(), ?)");
        if ($stmt === false) {
            error_log("Prepare failed for addBuildingToQueue INSERT: " . $this->conn->error);
            return ['success' => false, 'message' => 'Server error while adding to the queue.'];
        }
        // Bind parameters: i (village_id), i (village_building_id), i (building_type_id), i (level), s (finish_time)
        $stmt->bind_param("iiiis", $villageId, $villageBuildingId, $buildingTypeId, $nextLevel, $finishTime);
        
        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Upgrade added to the queue.'];
        } else {
             error_log("Execute failed for addBuildingToQueue INSERT: " . $stmt->error);
            $stmt->close();
            return ['success' => false, 'message' => 'Database error while adding to the queue.'];
        }
    }

    /**
     * Retrieves data for a specific building in a village (e.g., its level).
     * @return array|null Building data or null when missing.
     */
    public function getVillageBuilding(int $villageId, string $internalName): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT vb.village_id, vb.building_type_id, vb.level, bt.internal_name, bt.name, bt.production_type
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = ? LIMIT 1
        ");

        if ($stmt === false) {
             error_log("Prepare failed for getVillageBuilding: " . $this->conn->error);
             return null;
        }

        $stmt->bind_param("is", $villageId, $internalName);
        $stmt->execute();
        $result = $stmt->get_result();

        $building = $result->fetch_assoc();
        $stmt->close();

        return $building;
    }

    /**
     * Retrieves building data in a village by its village_buildings ID.
     * Used to load details before an action (e.g., upgrade).
     * Verifies the building belongs to the given village and matches the internal name.
     * @return array|null Building data (vb.id, vb.level, bt.internal_name, bt.name, bt.description, etc.) or null when missing.
     */
    public function getVillageBuildingDetailsById(int $villageBuildingId, int $villageId, string $internalName): ?array
    {
         $stmt = $this->conn->prepare("
             SELECT vb.id, vb.level, 
                    bt.internal_name, bt.name, bt.description, 
                    bt.production_type, bt.production_initial, bt.production_factor, 
                    bt.max_level, bt.id AS building_type_id
             FROM village_buildings vb
             JOIN building_types bt ON vb.building_type_id = bt.id
             WHERE vb.id = ? AND vb.village_id = ? AND bt.internal_name = ? LIMIT 1
         ");

         if ($stmt === false) {
             error_log("Prepare failed for getVillageBuildingDetailsById: " . $this->conn->error);
             return null;
         }

         $stmt->bind_param("iis", $villageBuildingId, $villageId, $internalName);
         $stmt->execute();
         $result = $stmt->get_result();

         $building = $result->fetch_assoc();
         $stmt->close();

         return $building;
    }

    /**
     * Checks whether any build task exists in the village queue.
     * @return bool True when the queue is not empty, false otherwise.
     */
    public function isAnyBuildingInQueue(int $villageId): bool
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM building_queue WHERE village_id = ? LIMIT 1");
         if ($stmt === false) {
             error_log("Prepare failed for isAnyBuildingInQueue: " . $this->conn->error);
             return false;
        }

        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_row();
        $stmt->close();

        return (int)($row[0] ?? 0) > 0;
    }

    /**
     * Fetches a single queue item for the village build queue.
     * Useful for checking upgrade status.
     * @return array|null Build task data or null when none.
     */
    public function getBuildingQueueItem(int $villageId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT bq.id, bq.village_building_id, bq.level, bq.finish_time, bt.name, bt.internal_name
            FROM building_queue bq
            JOIN building_types bt ON bq.building_type_id = bt.id
            WHERE bq.village_id = ?
            ORDER BY bq.finish_time ASC LIMIT 1
        ");
         if ($stmt === false) {
             error_log("Prepare failed for getBuildingQueueItem: " . $this->conn->error);
             return null;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        return $item;
    }

    /**
     * Collects all building data for the village view, combining config, current level,
     * queue status, and upgrade information.
     *
     * @param int $villageId Village ID.
     * @param int $mainBuildingLevel Town hall level in this village (needed for upgrade time calculations).
     * @return array Building data array ready for display.
     */
    public function getVillageBuildingsViewData(int $villageId, int $mainBuildingLevel): array
    {
        $buildingsViewData = [];

        // 1. Fetch levels of all buildings for this village
        $villageBuildingsLevels = $this->getVillageBuildingsLevels($villageId); // Implemented below

        // 2. Fetch the current build queue item for this village
        $queueItem = $this->getBuildingQueueItem($villageId);

        // 3. Fetch configs for all buildings
        $allBuildingConfigs = $this->buildingConfigManager->getAllBuildingConfigs();

        // 4. Merge data to prepare view structure
        foreach ($allBuildingConfigs as $config) {
            $internal_name = $config['internal_name'];
            $current_level = $villageBuildingsLevels[$internal_name] ?? 0;
            $max_level = (int)($config['max_level'] ?? 0);

            // Check whether the building is currently in the upgrade queue
            $is_upgrading = false;
            $queue_finish_time = null;
            $queue_level_after = null;

            if ($queueItem && $queueItem['village_id'] == $villageId && $queueItem['building_internal_name'] == $internal_name) {
                $is_upgrading = true;
                $queue_finish_time = $queueItem['finish_time'];
                // Queue level is always current level + 1
                $queue_level_after = $current_level + 1;
            }

            // Prepare next-upgrade data if available
            $next_level = $current_level + 1;
            $upgrade_costs = null;
            $upgrade_time_seconds = null;
            $can_upgrade = false;
            $upgrade_not_available_reason = '';

            if (!$is_upgrading && $current_level < $max_level) {
                // Check only building requirements and global queue
                // canUpgradeBuilding already checks requirements and queue state
                $can_upgrade_check = $this->canUpgradeBuilding($villageId, $internal_name);
                $can_upgrade = $can_upgrade_check['success'];
                $upgrade_not_available_reason = $can_upgrade_check['message'];
                
                // Resource validation happens on the front end or AJAX handler.
                // Here we compute cost and time only when the upgrade is technically possible.
                if ($can_upgrade) {
                     $upgrade_costs = $this->buildingConfigManager->calculateUpgradeCost($internal_name, $current_level);
                     $upgrade_time_seconds = $this->buildingConfigManager->calculateUpgradeTime($internal_name, $current_level, $mainBuildingLevel);
                }
            }

            $buildingsViewData[$internal_name] = [
                'internal_name' => $internal_name,
                'name' => $config['name'] ?? $internal_name,
                'level' => (int)$current_level,
                'description' => $config['description'] ?? 'No description.',
                'max_level' => (int)$max_level,
                'is_upgrading' => $is_upgrading,
                'queue_finish_time' => $queue_finish_time,
                'queue_level_after' => $queue_level_after,
                'next_level' => $next_level,
                'upgrade_costs' => $upgrade_costs, // null if not upgradable or upgrading
                'upgrade_time_seconds' => $upgrade_time_seconds, // null if not upgradable or upgrading
                'can_upgrade' => $can_upgrade, // Based on requirements and global queue
                'upgrade_not_available_reason' => $upgrade_not_available_reason,
                 // Additional config data
                'production_type' => $config['production_type'] ?? null,
                'population_cost' => $config['population_cost'] ?? null, // Population cost at THIS level
                'next_level_population_cost' => $this->buildingConfigManager->calculatePopulationCost($internal_name, $next_level) // Population cost at the next level
            ];
        }
        
        // Sort buildings (default by internal_name)
        ksort($buildingsViewData);

        return $buildingsViewData;
    }

     /**
      * Helper method to get levels of all buildings for a village.
      *
      * @param int $villageId Village ID.
      * @return array Assoc array of building_internal_name => level.
      */
     private function getVillageBuildingsLevels(int $villageId): array
     {
         $levels = [];
         $stmt = $this->conn->prepare("
             SELECT bt.internal_name, vb.level
             FROM village_buildings vb
             JOIN building_types bt ON vb.building_type_id = bt.id
             WHERE vb.village_id = ?
         ");

         if ($stmt === false) {
              error_log("BuildingManager::getVillageBuildingsLevels prepare failed: " . $this->conn->error);
              return $levels; // Return empty array on error
         }

         $stmt->bind_param("i", $villageId);
         $stmt->execute();
         $result = $stmt->get_result();

         while ($row = $result->fetch_assoc()) {
             $levels[$row['internal_name']] = (int)$row['level'];
         }

        $stmt->close();

        return $levels;
    }

    /**
     * Calculates the defense bonus granted by the wall at a given level.
     * Bonus grows exponentially, e.g. 1.04^wall_level.
     * @param int $wall_level Wall level.
     * @return float Defense multiplier.
     */
    public function getWallDefenseBonus(int $wall_level): float
    {
        if ($wall_level <= 0) {
            return 1.0; // No bonus
        }

        // Example: 4% compound bonus per level
        $base_factor = 1.04;

        return pow($base_factor, $wall_level);
    }

    /**
     * Sets the level of a building in a village.
     * @param int $villageId Village ID.
     * @param string $internalName Building internal name.
     * @param int $newLevel New building level.
     * @return bool True on success, false otherwise.
     */
    public function setBuildingLevel(int $villageId, string $internalName, int $newLevel): bool
    {
        $config = $this->buildingConfigManager->getBuildingConfig($internalName);
        if (!$config) {
            return false; // Unknown building
        }

        $buildingTypeId = $config['id'];
        $maxLevel = $config['max_level'];

        // Level cannot be negative or exceed the maximum
        if ($newLevel < 0 || $newLevel > $maxLevel) {
            return false;
        }

        $stmt = $this->conn->prepare(
            "UPDATE village_buildings SET level = ?
             WHERE village_id = ? AND building_type_id = ?"
        );

        if ($stmt === false) {
            error_log("Prepare failed for setBuildingLevel: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("iii", $newLevel, $villageId, $buildingTypeId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}
