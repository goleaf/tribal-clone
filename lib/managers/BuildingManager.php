<?php
declare(strict_types=1);

class BuildingManager {
    private $conn;
    private $buildingConfigManager;
    private $populationManager;
    private string $wallDecayLog;

    public function __construct($db_connection, BuildingConfigManager $buildingConfigManager) {
        $this->conn = $db_connection;
        $this->buildingConfigManager = $buildingConfigManager;
        
        // Lazy-load PopulationManager when needed
        $this->populationManager = null;
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $this->wallDecayLog = $logDir . '/wall_decay.log';
    }
    
    /**
     * Get or create PopulationManager instance.
     */
    private function getPopulationManager(): PopulationManager
    {
        if ($this->populationManager === null) {
            require_once __DIR__ . '/PopulationManager.php';
            $this->populationManager = new PopulationManager($this->conn);
        }
        return $this->populationManager;
    }

    /**
     * Apply passive wall decay for inactive villages when world flag is enabled.
     * Returns decay info array on decay, or null when no decay applied.
     */
    public function applyWallDecayIfNeeded(array $village, ?array $worldConfig = null): ?array
    {
        $villageId = (int)($village['id'] ?? 0);
        if ($villageId <= 0) {
            return null;
        }
        if (!class_exists('WorldManager')) {
            require_once __DIR__ . '/WorldManager.php';
        }
        $wm = class_exists('WorldManager') ? new WorldManager($this->conn) : null;
        $worldId = isset($village['world_id']) ? (int)$village['world_id'] : (defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1);
        $settings = $worldConfig ?? ($wm ? $wm->getSettings($worldId) : []);
        if (empty($settings['wall_decay_enabled'])) {
            return null;
        }

        $inactiveHours = defined('WALL_DECAY_INACTIVE_HOURS') ? (int)WALL_DECAY_INACTIVE_HOURS : 72;
        $intervalHours = defined('WALL_DECAY_INTERVAL_HOURS') ? (int)WALL_DECAY_INTERVAL_HOURS : 24;
        $now = time();
        $lastDecayVal = $village['last_wall_decay_at'] ?? null;
        if ($lastDecayVal === null) {
            $stmtDecay = $this->conn->prepare("SELECT last_wall_decay_at FROM villages WHERE id = ? LIMIT 1");
            if ($stmtDecay) {
                $stmtDecay->bind_param("i", $villageId);
                $stmtDecay->execute();
                $rowDecay = $stmtDecay->get_result()->fetch_assoc();
                $stmtDecay->close();
                if ($rowDecay && !empty($rowDecay['last_wall_decay_at'])) {
                    $lastDecayVal = $rowDecay['last_wall_decay_at'];
                }
            }
        }
        $lastDecayTs = $lastDecayVal ? strtotime($lastDecayVal) : null;
        if ($lastDecayTs && ($now - $lastDecayTs) < ($intervalHours * 3600)) {
            return null;
        }

        $userId = isset($village['user_id']) ? (int)$village['user_id'] : 0;
        $lastActivityTs = null;
        if ($userId > 0) {
            $stmt = $this->conn->prepare("SELECT last_activity_at FROM users WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!empty($row['last_activity_at'])) {
                    $lastActivityTs = strtotime($row['last_activity_at']);
                }
            }
        }
        if ($lastActivityTs === null) {
            $lastActivityTs = $now;
        }
        if (($now - $lastActivityTs) < ($inactiveHours * 3600)) {
            return null;
        }

        $wallLevel = $this->getBuildingLevel($villageId, 'wall');
        if ($wallLevel <= 0) {
            return null;
        }

        $wallTypeId = $this->getBuildingTypeIdByInternal('wall');
        if (!$wallTypeId) {
            return null;
        }

        $newLevel = max(0, $wallLevel - 1);
        $stmtUpdate = $this->conn->prepare("UPDATE village_buildings SET level = ? WHERE village_id = ? AND building_type_id = ?");
        if ($stmtUpdate) {
            $stmtUpdate->bind_param("iii", $newLevel, $villageId, $wallTypeId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }

        $nowSql = date('Y-m-d H:i:s', $now);
        $stmtVillage = $this->conn->prepare("UPDATE villages SET last_wall_decay_at = ? WHERE id = ?");
        if ($stmtVillage) {
            $stmtVillage->bind_param("si", $nowSql, $villageId);
            $stmtVillage->execute();
            $stmtVillage->close();
        }

        $this->logWallDecay($villageId, $userId, $wallLevel, $newLevel);

        return [
            'decayed' => true,
            'from_level' => $wallLevel,
            'to_level' => $newLevel,
            'at' => $now
        ];
    }

    private function logWallDecay(int $villageId, int $userId, int $from, int $to): void
    {
        $line = sprintf(
            "[%s] village=%d user=%d wall_decay from=%d to=%d\n",
            date('c'),
            $villageId,
            $userId,
            $from,
            $to
        );
        @file_put_contents($this->wallDecayLog, $line, FILE_APPEND);
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
        
        return (float)$this->buildingConfigManager->calculateProduction($building_internal_name, $level);
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

    private function getBuildingTypeIdByInternal(string $internal): ?int
    {
        $stmt = $this->conn->prepare("SELECT id FROM building_types WHERE internal_name = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("s", $internal);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id'] : null;
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
     * Validate and prepare a demolition (downgrade) of a building by 1 level.
     * Returns refund, duration, and target level.
     */
    public function canDemolishBuilding(int $villageId, string $internalName): array
    {
        $currentLevel = $this->getBuildingLevel($villageId, $internalName);
        if ($currentLevel <= 0) {
            return ['success' => false, 'message' => 'Building is already at level 0.'];
        }

        $newLevel = $currentLevel - 1;

        // Do not break prerequisites for other buildings
        $stmt = $this->conn->prepare("
            SELECT bt.internal_name AS dependent_internal_name, br.required_level, vb.level
            FROM building_requirements br
            JOIN building_types bt ON br.building_type_id = bt.id
            JOIN village_buildings vb ON vb.building_type_id = bt.id AND vb.village_id = ?
            WHERE br.required_building = ? AND vb.level > 0 AND br.required_level > ?
        ");
        if ($stmt === false) {
            error_log("Prepare failed for canDemolishBuilding: " . $this->conn->error);
            return ['success' => false, 'message' => 'Server error while checking prerequisites.'];
        }
        $stmt->bind_param("isi", $villageId, $internalName, $newLevel);
        $stmt->execute();
        $result = $stmt->get_result();
        $blocking = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($blocking) {
            return [
                'success' => false,
                'message' => "Cannot demolish below level {$blocking['required_level']} because {$blocking['dependent_internal_name']} depends on it."
            ];
        }

        $upgradeCost = $this->buildingConfigManager->calculateUpgradeCost($internalName, $newLevel);
        if (!$upgradeCost) {
            return ['success' => false, 'message' => 'Unable to calculate demolition refund.'];
        }
        $refund = [
            'wood' => (int)floor($upgradeCost['wood'] * 0.9),
            'clay' => (int)floor($upgradeCost['clay'] * 0.9),
            'iron' => (int)floor($upgradeCost['iron'] * 0.9),
        ];

        $mainBuildingLevel = $this->getBuildingLevel($villageId, 'main_building');
        $duration = $this->buildingConfigManager->calculateUpgradeTime($internalName, $newLevel, $mainBuildingLevel);

        return [
            'success' => true,
            'message' => 'Demolition possible.',
            'target_level' => $newLevel,
            'duration_seconds' => $duration,
            'refund' => $refund
        ];
    }

    /**
     * Queue a demolition task that downgrades a building by 1 level and refunds 90% of the last level cost.
     */
    public function queueDemolition(int $villageId, string $internalName): array
    {
        $check = $this->canDemolishBuilding($villageId, $internalName);
        if (!$check['success']) {
            return $check;
        }

        // Block if another build/demolition is in progress
        $stmt_queue = $this->conn->prepare("SELECT COUNT(*) as count FROM building_queue WHERE village_id = ?");
        if ($stmt_queue === false) {
            error_log("Prepare failed for queue check (demolition): " . $this->conn->error);
            return ['success' => false, 'message' => 'Server error while checking the queue.'];
        }
        $stmt_queue->bind_param("i", $villageId);
        $stmt_queue->execute();
        $queue_result = $stmt_queue->get_result()->fetch_assoc();
        $stmt_queue->close();
        if ($queue_result && (int)$queue_result['count'] > 0) {
            return ['success' => false, 'message' => 'Another construction task is already in progress.'];
        }

        $building = $this->getVillageBuilding($villageId, $internalName);
        if (!$building) {
            return ['success' => false, 'message' => 'Building not found in this village.'];
        }

        $finish_time = date('Y-m-d H:i:s', time() + ($check['duration_seconds'] ?? 0));

        $stmt_queue_add = $this->conn->prepare("
            INSERT INTO building_queue (
                village_id, village_building_id, building_type_id, level, starts_at, finish_time,
                is_demolition, refund_wood, refund_clay, refund_iron
            ) VALUES (?, ?, ?, ?, NOW(), ?, 1, ?, ?, ?)
        ");

        if ($stmt_queue_add === false) {
            return ['success' => false, 'message' => 'Database error while queuing demolition.'];
        }

        $targetLevel = (int)$check['target_level'];
        $refund = $check['refund'];
        $stmt_queue_add->bind_param(
            "iiiisiii",
            $villageId,
            $building['village_building_id'],
            $building['building_type_id'],
            $targetLevel,
            $finish_time,
            $refund['wood'],
            $refund['clay'],
            $refund['iron']
        );

        if (!$stmt_queue_add->execute()) {
            $stmt_queue_add->close();
            return ['success' => false, 'message' => 'Failed to queue demolition.'];
        }
        $stmt_queue_add->close();

        return [
            'success' => true,
            'message' => 'Demolition started.',
            'target_level' => $targetLevel,
            'finish_time' => strtotime($finish_time),
            'refund' => $refund
        ];
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
                    'message' => "Requires " . htmlspecialchars($requiredBuildingDisplayName) . " at level " . $requiredLevel . ". Your current level: " . $currentLevel,
                    'code' => 'ERR_PREREQ'
                ];
            }
        }

        return ['success' => true, 'message' => 'Requirements met.'];
    }

    /**
     * Checks if the player already owns a First Church in another village.
     */
    private function userHasBuiltFirstChurch(int $userId, int $currentVillageId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as cnt
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            JOIN villages v ON v.id = vb.village_id
            WHERE bt.internal_name = 'first_church'
              AND vb.level > 0
              AND v.user_id = ?
              AND vb.village_id <> ?
        ");

        if ($stmt === false) {
            error_log("Prepare failed for userHasBuiltFirstChurch: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("ii", $userId, $currentVillageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return isset($result['cnt']) && (int)$result['cnt'] > 0;
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

    /**
     * Comprehensive validation for building upgrade eligibility.
     * Implements complete validation chain as per requirements 1.1, 1.2, 1.3, 14.1-14.4, 19.1-19.3.
     * 
     * Validation order:
     * 1. Input validation (building ID exists)
     * 2. Protection status checks (emergency shield, protection mode)
     * 3. Maximum level caps
     * 4. Prerequisites (buildings, research, special conditions like First Church)
     * 5. Queue capacity
     * 6. Resource availability
     * 7. Population capacity
     * 8. Storage capacity (for large builds)
     * 
     * @param int $villageId Village ID
     * @param string $internalName Building internal name
     * @param int|null $userId User ID (required for First Church uniqueness check)
     * @return array ['success' => bool, 'message' => string, 'code' => string, 'details' => array]
     */
    public function canUpgradeBuilding(int $villageId, string $internalName, ?int $userId = null): array
    {
        // 1. Input validation - verify building exists
        $config = $this->buildingConfigManager->getBuildingConfig($internalName);
        if (!$config) {
            return [
                'success' => false, 
                'message' => 'Unknown building type.', 
                'code' => 'ERR_INPUT'
            ];
        }
        
        $currentLevel = $this->getBuildingLevel($villageId, $internalName);
        $nextLevel = $currentLevel + 1;
        
        // 2. Protection status checks
        $protectionCheck = $this->checkProtectionStatus($villageId, $internalName);
        if (!$protectionCheck['success']) {
            return $protectionCheck;
        }
        
        // 3. Maximum level cap enforcement
        $maxLevel = (int)$config['max_level'];
        if ($currentLevel >= $maxLevel) {
            return [
                'success' => false, 
                'message' => 'Maximum level reached for this building.', 
                'code' => 'ERR_CAP',
                'details' => [
                    'current_level' => $currentLevel,
                    'max_level' => $maxLevel
                ]
            ];
        }
        
        // 4. Prerequisites validation
        // 4a. Building prerequisites
        $requirementsCheck = $this->checkBuildingRequirements($internalName, $villageId);
        if (!$requirementsCheck['success']) {
            $requirementsCheck['code'] = 'ERR_PREREQ';
            return $requirementsCheck;
        }
        
        // 4b. Research prerequisites (if applicable)
        $researchCheck = $this->checkResearchPrerequisites($internalName, $villageId);
        if (!$researchCheck['success']) {
            return $researchCheck;
        }
        
        // 4c. Special building uniqueness (First Church)
        if ($userId !== null && $internalName === 'first_church') {
            if ($this->userHasBuiltFirstChurch($userId, $villageId)) {
                return [
                    'success' => false, 
                    'message' => 'You can only have one First Church across all villages.', 
                    'code' => 'ERR_PREREQ'
                ];
            }
        }
        
        // 5. Queue capacity validation
        $queueCount = $this->getActivePendingQueueCount($villageId);
        $maxQueueItems = $this->getQueueLimit();
        if ($queueCount >= $maxQueueItems) {
            return [
                'success' => false, 
                'message' => "Build queue is full (max {$maxQueueItems} items).", 
                'code' => 'ERR_QUEUE_FULL',
                'details' => [
                    'current_count' => $queueCount,
                    'max_items' => $maxQueueItems
                ]
            ];
        }
        
        // 6. Resource availability validation
        $upgradeCosts = $this->buildingConfigManager->calculateUpgradeCost($internalName, $currentLevel);
        if (!$upgradeCosts) {
            return [
                'success' => false, 
                'message' => 'Cannot calculate upgrade costs.', 
                'code' => 'ERR_INPUT'
            ];
        }
        
        $stmt_resources = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        if ($stmt_resources === false) {
            error_log("Prepare failed for resource check: " . $this->conn->error);
            return [
                'success' => false, 
                'message' => 'Server error while fetching resources.', 
                'code' => 'ERR_SERVER'
            ];
        }
        $stmt_resources->bind_param("i", $villageId);
        $stmt_resources->execute();
        $resources = $stmt_resources->get_result()->fetch_assoc();
        $stmt_resources->close();
        
        if (!$resources) {
            return [
                'success' => false, 
                'message' => 'Cannot fetch village resources.', 
                'code' => 'ERR_SERVER'
            ];
        }
        
        $missingResources = [];
        if ($resources['wood'] < $upgradeCosts['wood']) {
            $missingResources['wood'] = $upgradeCosts['wood'] - $resources['wood'];
        }
        if ($resources['clay'] < $upgradeCosts['clay']) {
            $missingResources['clay'] = $upgradeCosts['clay'] - $resources['clay'];
        }
        if ($resources['iron'] < $upgradeCosts['iron']) {
            $missingResources['iron'] = $upgradeCosts['iron'] - $resources['iron'];
        }
        
        if (!empty($missingResources)) {
            return [
                'success' => false, 
                'message' => 'Not enough resources.', 
                'code' => 'ERR_RES',
                'details' => [
                    'required' => $upgradeCosts,
                    'available' => [
                        'wood' => $resources['wood'],
                        'clay' => $resources['clay'],
                        'iron' => $resources['iron']
                    ],
                    'missing' => $missingResources
                ]
            ];
        }
        
        // 7. Population capacity validation
        $popManager = $this->getPopulationManager();
        $popCheck = $popManager->canAffordBuildingPopulation($villageId, $internalName, $nextLevel);
        if (!$popCheck['success']) {
            $popCheck['code'] = $popCheck['code'] ?? 'ERR_POP';
            return $popCheck;
        }
        
        // 8. Storage capacity validation (for large builds)
        $storageCheck = $this->checkStorageCapacity($villageId, $upgradeCosts);
        if (!$storageCheck['success']) {
            return $storageCheck;
        }
        
        return [
            'success' => true, 
            'message' => 'Upgrade possible.'
        ];
    }
    
    /**
     * Check if village is under protection that blocks building upgrades.
     * Implements requirement 14.4 - protection mode military building blocking.
     * 
     * @param int $villageId Village ID
     * @param string $internalName Building internal name
     * @return array ['success' => bool, 'message' => string, 'code' => string]
     */
    private function checkProtectionStatus(int $villageId, string $internalName): array
    {
        // Check if village has emergency shield/protection
        $stmt = $this->conn->prepare("
            SELECT v.id, v.protection_until, w.block_military_during_protection
            FROM villages v
            LEFT JOIN worlds w ON v.world_id = w.id
            WHERE v.id = ?
            LIMIT 1
        ");
        
        if ($stmt === false) {
            error_log("Prepare failed for protection check: " . $this->conn->error);
            return ['success' => true, 'message' => 'Protection check skipped.']; // Fail open
        }
        
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) {
            return ['success' => true, 'message' => 'No protection.'];
        }
        
        // Check if protection is active
        $protectionUntil = $result['protection_until'] ?? null;
        $blockMilitary = (bool)($result['block_military_during_protection'] ?? false);
        
        if ($protectionUntil && strtotime($protectionUntil) > time()) {
            // Protection is active
            if ($blockMilitary && $this->isMilitaryBuilding($internalName)) {
                return [
                    'success' => false,
                    'message' => 'Cannot upgrade military buildings while under protection.',
                    'code' => 'ERR_PROTECTED'
                ];
            }
        }
        
        return ['success' => true, 'message' => 'No protection blocking.'];
    }
    
    /**
     * Check if building is a military building.
     * 
     * @param string $internalName Building internal name
     * @return bool True if military building
     */
    private function isMilitaryBuilding(string $internalName): bool
    {
        $militaryBuildings = [
            'barracks',
            'stable',
            'workshop',
            'siege_foundry',
            'garrison',
            'hall_of_banners'
        ];
        
        return in_array($internalName, $militaryBuildings, true);
    }
    
    /**
     * Check research prerequisites for a building.
     * Implements requirement 14.3 - research prerequisite validation.
     * 
     * @param string $internalName Building internal name
     * @param int $villageId Village ID
     * @return array ['success' => bool, 'message' => string, 'code' => string]
     */
    private function checkResearchPrerequisites(string $internalName, int $villageId): array
    {
        // Get village's user_id to check research
        $stmt = $this->conn->prepare("SELECT user_id FROM villages WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            error_log("Prepare failed for village user lookup: " . $this->conn->error);
            return ['success' => true, 'message' => 'Research check skipped.']; // Fail open
        }
        
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $villageData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$villageData) {
            return ['success' => true, 'message' => 'No research requirements.'];
        }
        
        $userId = (int)$villageData['user_id'];
        
        // Check if research_requirements table exists
        $tableCheck = $this->conn->query("
            SELECT name FROM sqlite_master 
            WHERE type='table' AND name='research_requirements'
        ");
        
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return ['success' => true, 'message' => 'No research system.'];
        }
        
        // Get research requirements for this building
        $config = $this->buildingConfigManager->getBuildingConfig($internalName);
        if (!$config) {
            return ['success' => true, 'message' => 'No research requirements.'];
        }
        
        $buildingTypeId = (int)$config['id'];
        
        $stmt = $this->conn->prepare("
            SELECT rr.research_id, r.name as research_name
            FROM research_requirements rr
            LEFT JOIN research r ON rr.research_id = r.id
            WHERE rr.building_type_id = ?
        ");
        
        if ($stmt === false) {
            return ['success' => true, 'message' => 'No research requirements.'];
        }
        
        $stmt->bind_param("i", $buildingTypeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $missingResearch = [];
        while ($row = $result->fetch_assoc()) {
            $researchId = (int)$row['research_id'];
            $researchName = $row['research_name'] ?? "Research #{$researchId}";
            
            // Check if user has completed this research
            $checkStmt = $this->conn->prepare("
                SELECT id FROM user_research 
                WHERE user_id = ? AND research_id = ? AND completed = 1
                LIMIT 1
            ");
            
            if ($checkStmt) {
                $checkStmt->bind_param("ii", $userId, $researchId);
                $checkStmt->execute();
                $completed = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();
                
                if (!$completed) {
                    $missingResearch[] = $researchName;
                }
            }
        }
        $stmt->close();
        
        if (!empty($missingResearch)) {
            return [
                'success' => false,
                'message' => 'Missing required research: ' . implode(', ', $missingResearch),
                'code' => 'ERR_RESEARCH',
                'details' => [
                    'missing_research' => $missingResearch
                ]
            ];
        }
        
        return ['success' => true, 'message' => 'Research requirements met.'];
    }
    
    /**
     * Check if storage capacity is sufficient for the upgrade cost.
     * Implements requirement 5.5 - storage capacity prerequisite.
     * 
     * @param int $villageId Village ID
     * @param array $upgradeCosts Upgrade costs ['wood' => int, 'clay' => int, 'iron' => int]
     * @return array ['success' => bool, 'message' => string, 'code' => string]
     */
    private function checkStorageCapacity(int $villageId, array $upgradeCosts): array
    {
        // Get storage and warehouse levels
        $storageLevel = $this->getBuildingLevel($villageId, 'storage');
        $warehouseLevel = $this->getBuildingLevel($villageId, 'warehouse');
        
        // Calculate total capacity
        $storageCapacity = $this->buildingConfigManager->calculateWarehouseCapacity($storageLevel) ?? 1000;
        $warehouseCapacity = $this->buildingConfigManager->calculateWarehouseCapacity($warehouseLevel) ?? 0;
        $totalCapacity = $storageCapacity + $warehouseCapacity;
        
        // Check if any resource cost exceeds capacity
        $maxCost = max($upgradeCosts['wood'], $upgradeCosts['clay'], $upgradeCosts['iron']);
        
        if ($maxCost > $totalCapacity) {
            return [
                'success' => false,
                'message' => 'Upgrade cost exceeds storage capacity. Upgrade your Storage or Warehouse first.',
                'code' => 'ERR_STORAGE_CAP',
                'details' => [
                    'max_cost' => $maxCost,
                    'storage_capacity' => $totalCapacity,
                    'required_capacity' => $maxCost
                ]
            ];
        }
        
        return ['success' => true, 'message' => 'Storage capacity sufficient.'];
    }
    
    public function addBuildingToQueue(int $villageId, string $internalName): array
    {
        // Check if there's already an item in the queue for this village
        if ($this->isAnyBuildingInQueue($villageId)) {
            return ['success' => false, 'message' => 'Another task is already in this village\'s build queue.', 'code' => 'ERR_CAP'];
        }

        // Get current building level
        $currentLevel = $this->getBuildingLevel($villageId, $internalName);
        $nextLevel = $currentLevel + 1;

        // Get building config to check max level
        $config = $this->buildingConfigManager->getBuildingConfig($internalName);
        if (!$config) {
             return ['success' => false, 'message' => 'Unknown building type.', 'code' => 'ERR_INPUT'];
        }
        
        if ($currentLevel >= $config['max_level']) {
            return ['success' => false, 'message' => 'Maximum level reached for this building.', 'code' => 'ERR_CAP'];
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
             return ['success' => false, 'message' => 'Cannot calculate upgrade time.', 'code' => 'ERR_INPUT'];
        }

        $finishTime = date('Y-m-d H:i:s', time() + $upgradeTimeSeconds);

        // Get the village_building_id for the specific building in this village
        $villageBuilding = $this->getVillageBuilding($villageId, $internalName);
        if (!$villageBuilding) {
             return ['success' => false, 'message' => 'Building not found in the village.', 'code' => 'ERR_INPUT'];
        }
        $villageBuildingId = $villageBuilding['village_building_id'] ?? null; // Ensure this is the correct column name
        
        // Need building_type_id for the queue table
        $buildingTypeId = $config['id'] ?? null;
        if ($villageBuildingId === null || $buildingTypeId === null) {
             error_log("Missing village_building_id ($villageBuildingId) or building_type_id ($buildingTypeId) for village $villageId, building $internalName");
             return ['success' => false, 'message' => 'Internal server error (missing IDs).', 'code' => 'ERR_CAP'];
        }

        // Add to building_queue table
        $stmt = $this->conn->prepare("INSERT INTO building_queue (village_id, village_building_id, building_type_id, level, starts_at, finish_time) VALUES (?, ?, ?, ?, NOW(), ?)");
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
            SELECT vb.id AS village_building_id, vb.village_id, vb.building_type_id, vb.level, bt.internal_name, bt.name, bt.production_type
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
        return $this->getActivePendingQueueCount($villageId) > 0;
    }

    /**
     * Returns queue usage data for a village.
     */
    public function getQueueUsage(int $villageId): array
    {
        $count = $this->getActivePendingQueueCount($villageId);
        $limit = $this->getQueueLimit();

        return [
            'count' => $count,
            'limit' => $limit,
            'is_full' => $count >= $limit,
        ];
    }

    public function getActivePendingQueueCount(int $villageId): int
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM building_queue WHERE village_id = ? AND (status IS NULL OR status IN ('active','pending'))");
        if ($stmt === false) {
            error_log("Prepare failed for getActivePendingQueueCount: " . $this->conn->error);
            return 0;
        }

        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['cnt'] : 0;
    }

    private function getQueueLimit(): int
    {
        return defined('BUILDING_QUEUE_MAX_ITEMS') ? (int)BUILDING_QUEUE_MAX_ITEMS : 10;
    }

    /**
     * Fetches a single queue item for the village build queue.
     * Useful for checking upgrade status.
     * @return array|null Build task data or null when none.
     */
    public function getBuildingQueueItem(int $villageId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT bq.id, bq.village_id, bq.village_building_id, bq.level, bq.starts_at, bq.finish_time, bt.name, bt.internal_name, bt.internal_name AS building_internal_name
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
        $villageBuildingsLevels = $this->getVillageBuildingsLevels($villageId);

        // 2. Fetch the current build queue item for this village
        $queueItem = $this->getBuildingQueueItem($villageId);
        $queueCount = $this->getActivePendingQueueCount($villageId);
        $maxQueueItems = $this->getQueueLimit();

        // 3. Fetch village resources once (used for every upgrade check)
        $villageResources = $this->getVillageResources($villageId);

        // 4. Fetch configs for all buildings and prepare a quick name lookup
        $allBuildingConfigs = $this->buildingConfigManager->getAllBuildingConfigs();
        $buildingNames = [];
        foreach ($allBuildingConfigs as $config) {
            $buildingNames[$config['internal_name']] = $config['name'] ?? $config['internal_name'];
        }

        // 5. Merge data to prepare view structure
        foreach ($allBuildingConfigs as $config) {
            $internal_name = $config['internal_name'];
            $current_level = $villageBuildingsLevels[$internal_name] ?? 0;
            $max_level = (int)($config['max_level'] ?? 0);

            // Check whether the building is currently in the upgrade queue
            $is_upgrading = false;
            $queue_finish_time = null;
            $queue_start_time = null;
            $queue_level_after = null;
            $current_upgrade_duration = null;

            if ($queueItem && $queueItem['village_id'] == $villageId && $queueItem['building_internal_name'] == $internal_name) {
                $is_upgrading = true;
                $queue_finish_time = $queueItem['finish_time'] ? strtotime($queueItem['finish_time']) : null;
                $queue_start_time = isset($queueItem['starts_at']) ? strtotime($queueItem['starts_at']) : null;
                $queue_level_after = $queueItem['level'] ?? ($current_level + 1);
                if ($queue_start_time && $queue_finish_time) {
                    $current_upgrade_duration = max(1, $queue_finish_time - $queue_start_time);
                }
            }

            // Prepare next-upgrade data if available
            $next_level = $current_level + 1;
            $upgrade_costs = null;
            $upgrade_time_seconds = null;
            $can_upgrade = false;
            $upgrade_not_available_reason = 'Upgrade possible.';

            if ($current_level >= $max_level) {
                $upgrade_not_available_reason = 'Maximum level reached for this building.';
            } elseif ($is_upgrading) {
                $upgrade_not_available_reason = 'Upgrade already in progress in this village.';
            } elseif ($current_level < $max_level) {
                $assessment = $this->assessUpgradeReadiness(
                    $internal_name,
                    $config,
                    $current_level,
                    $villageResources,
                    $queueCount,
                    $maxQueueItems,
                    $villageBuildingsLevels,
                    $buildingNames
                );
                $can_upgrade = $assessment['success'];
                $upgrade_not_available_reason = $assessment['message'];

                if ($can_upgrade) {
                    $upgrade_costs = $assessment['upgrade_costs'];
                    $upgrade_time_seconds = $this->buildingConfigManager->calculateUpgradeTime($internal_name, $current_level, $mainBuildingLevel);
                }
            }

            // Estimate duration for active upgrades when start_time is missing
            if ($is_upgrading && !$current_upgrade_duration && $queue_finish_time) {
                $current_upgrade_duration = $this->buildingConfigManager->calculateUpgradeTime($internal_name, $current_level, $mainBuildingLevel);
                if (!$queue_start_time && $current_upgrade_duration) {
                    $queue_start_time = $queue_finish_time - $current_upgrade_duration;
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
                'queue_start_time' => $queue_start_time,
                'queue_level_after' => $queue_level_after,
                'next_level' => $next_level,
                'upgrade_costs' => $upgrade_costs, // null if not upgradable or upgrading
                'upgrade_time_seconds' => $upgrade_time_seconds ?? $current_upgrade_duration, // null if not upgradable or upgrading
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

    private function getVillageResources(int $villageId): ?array
    {
        $stmt = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        if ($stmt === false) {
            error_log("Prepare failed for getVillageResources: " . $this->conn->error);
            return null;
        }

        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $resources = $result->fetch_assoc();
        $stmt->close();

        return $resources ?: null;
    }

    private function checkRequirementsFromLevels(string $internalName, array $villageLevels, array $buildingNames): array
    {
        $requirements = $this->buildingConfigManager->getBuildingRequirements($internalName);

        if (empty($requirements)) {
            return ['success' => true, 'message' => 'No additional building requirements.'];
        }

        foreach ($requirements as $req) {
            $requiredBuildingName = $req['required_building'];
            $requiredLevel = (int)$req['required_level'];

            $currentLevel = $villageLevels[$requiredBuildingName] ?? 0;

            if ($currentLevel < $requiredLevel) {
                $requiredBuildingDisplayName = $buildingNames[$requiredBuildingName] ?? $requiredBuildingName;
                return [
                    'success' => false,
                    'message' => "Requires " . htmlspecialchars($requiredBuildingDisplayName) . " at level " . $requiredLevel . ". Your current level: " . $currentLevel
                ];
            }
        }

        return ['success' => true, 'message' => 'Requirements met.'];
    }

    private function assessUpgradeReadiness(
        string $internalName,
        array $config,
        int $currentLevel,
        ?array $villageResources,
        int $queueCount,
        int $maxQueueItems,
        array $villageBuildingLevels,
        array $buildingNames
    ): array {
        $maxLevel = (int)($config['max_level'] ?? 0);

        if ($currentLevel >= $maxLevel) {
            return ['success' => false, 'message' => 'Maximum level reached for this building.', 'upgrade_costs' => null];
        }

        if ($queueCount >= $maxQueueItems) {
            return ['success' => false, 'message' => "Build queue is full (max {$maxQueueItems} items).", 'upgrade_costs' => null];
        }

        $upgradeCosts = $this->buildingConfigManager->calculateUpgradeCost($internalName, $currentLevel);

        if (!$upgradeCosts) {
            return ['success' => false, 'message' => 'Cannot calculate upgrade costs.', 'upgrade_costs' => null];
        }

        if ($villageResources === null) {
            return ['success' => false, 'message' => 'Cannot fetch village resources.', 'upgrade_costs' => null];
        }

        if ($this->hasInsufficientResources($villageResources, $upgradeCosts)) {
            return ['success' => false, 'message' => 'Not enough resources.', 'upgrade_costs' => null];
        }

        $requirementsCheck = $this->checkRequirementsFromLevels($internalName, $villageBuildingLevels, $buildingNames);
        if (!$requirementsCheck['success']) {
            return ['success' => false, 'message' => $requirementsCheck['message'], 'upgrade_costs' => null];
        }

        return ['success' => true, 'message' => 'Upgrade possible.', 'upgrade_costs' => $upgradeCosts];
    }

    private function hasInsufficientResources(array $resources, array $upgradeCosts): bool
    {
        $wood = $resources['wood'] ?? 0;
        $clay = $resources['clay'] ?? 0;
        $iron = $resources['iron'] ?? 0;

        return $wood < $upgradeCosts['wood'] || $clay < $upgradeCosts['clay'] || $iron < $upgradeCosts['iron'];
    }

    /**
     * Calculates the defense bonus granted by the wall at a given level.
     * Bonus grows linearly: +8% defense per level.
     * @param int $wall_level Wall level.
     * @return float Defense multiplier.
     */
    public function getWallDefenseBonus(int $wall_level): float
    {
        if ($wall_level <= 0) {
            return 1.0; // No bonus
        }

        return 1 + (0.08 * $wall_level);
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
