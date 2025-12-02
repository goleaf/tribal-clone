<?php
/**
 * UnitManager - handles military units.
 */
class UnitManager
{
    private $conn;
    private $unit_types_cache = [];
    private WorldManager $worldManager;
    private const SIEGE_CAP_PER_VILLAGE = 200;
    private const SIEGE_INTERNALS = ['ram', 'battering_ram', 'catapult', 'stone_hurler'];
    private ?array $unitConfigVersion = null;

    /**
     * Constructor
     *
     * @param mysqli $conn Database connection
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
        require_once __DIR__ . '/WorldManager.php';
        $this->worldManager = new WorldManager($conn);
        $this->loadUnitTypes();
        $this->unitConfigVersion = $this->loadUnitConfigVersion();
    }

    /**
     * Load all unit types into cache.
     */
    private function loadUnitTypes()
    {
        $result = $this->conn->query("SELECT * FROM unit_types WHERE is_active = 1");
        $worldId = defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1;
        $conquestEnabled = $this->worldManager->isConquestUnitEnabled($worldId);
        $seasonalEnabled = $this->worldManager->isSeasonalUnitsEnabled($worldId);
        $healerEnabled = $this->worldManager->isHealerEnabled($worldId);
        $seasonalUnits = ['tempest_knight', 'event_knight'];
        $healerUnits = ['war_healer', 'healer'];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($this->unitConfigVersion && isset($row['updated_at'])) {
                    $row['config_version'] = $this->unitConfigVersion['version'];
                    $row['config_updated_at'] = $this->unitConfigVersion['updated_at'];
                }
                $internal = $row['internal_name'] ?? '';
                if (
                    !$this->worldManager->isArcherEnabled() &&
                    in_array($internal, ['archer', 'marcher', 'bowman', 'slinger', 'horse_archer'], true)
                ) {
                    continue;
                }
                if (!$this->worldManager->isPaladinEnabled() && $internal === 'paladin') {
                    continue;
                }
                if (
                    !$conquestEnabled &&
                    in_array($internal, ['noble', 'nobleman', 'standard_bearer', 'envoy'], true)
                ) {
                    continue;
                }
                if (!$seasonalEnabled && in_array($internal, $seasonalUnits, true)) {
                    continue;
                }
                if (!$healerEnabled && in_array($internal, $healerUnits, true)) {
                    continue;
                }
                $this->unit_types_cache[$row['id']] = $row;
            }
        }
    }

    /**
     * Get all unit types.
     *
     * @return array Unit types
     */
    public function getAllUnitTypes()
    {
        return $this->unit_types_cache;
    }

    public function getUnitById(int $id): ?array
    {
        return $this->unit_types_cache[$id] ?? null;
    }

    /**
     * Resolve a coarse archetype for a unit for world multipliers.
     */
    private function resolveUnitArchetype(array $unit): string
    {
        $buildingType = strtolower($unit['building_type'] ?? '');
        $internal = strtolower($unit['internal_name'] ?? '');

        if ($buildingType === 'stable') {
            return 'cav';
        }
        if ($buildingType === 'workshop' || $buildingType === 'garage') {
            return 'siege';
        }
        if ($buildingType === 'barracks') {
            // Treat obvious ranged names as ranged archetype.
            if (str_contains($internal, 'archer') || str_contains($internal, 'bow') || str_contains($internal, 'ranger')) {
                return 'rng';
            }
            return 'inf';
        }
        // Default to infantry if not matched.
        return 'inf';
    }

    /**
     * Get unit config version info if available.
     */
    public function getUnitConfigVersion(): ?array
    {
        return $this->unitConfigVersion;
    }

    /**
     * Get units that can be recruited in a building.
     *
     * @param string $building_type Building type (barracks, stable, workshop, academy, statue)
     * @param int $building_level Building level
     * @return array Units available for recruitment
     */
    public function getAvailableUnitsByBuilding($building_type, $building_level)
    {
        $available_units = [];

        foreach ($this->unit_types_cache as $unit) {
            if ($unit['building_type'] === $building_type && $unit['required_building_level'] <= $building_level) {
                $available_units[] = $unit;
            }
        }

        return $available_units;
    }

    /**
     * Calculate recruitment time for a unit.
     *
     * @param int $unit_type_id Unit type ID
     * @param int $building_level Building level
     * @return int Time in seconds
     */
    public function calculateRecruitmentTime($unit_type_id, $building_level)
    {
        if (!isset($this->unit_types_cache[$unit_type_id])) {
            return 0;
        }

        $unit = $this->unit_types_cache[$unit_type_id];
        $base_time = $unit['training_time_base'];

        // Higher building level -> faster recruitment (5% per level)
        $time = $base_time * pow(0.95, $building_level - 1);

        require_once __DIR__ . '/WorldManager.php';
        $wm = new WorldManager($this->conn);
        $worldSpeed = $wm->getWorldSpeed();
        $archetype = $this->resolveUnitArchetype($unit);
        $trainMultiplier = $wm->getTrainSpeedForArchetype($archetype);

        $time = $time / ($worldSpeed * $trainMultiplier);

        // Encourage meaningful batch times; set per-unit minimums by training building.
        $minPerUnit = 0;
        $buildingType = $unit['building_type'] ?? '';
        if ($buildingType === 'barracks') {
            $minPerUnit = 30; // 30s floor for infantry
        } elseif ($buildingType === 'stable') {
            $minPerUnit = 60; // 60s floor for cavalry
        } elseif (in_array($buildingType, ['garage', 'workshop'], true)) {
            $minPerUnit = 120; // 2m floor for siege
        }
        if ($minPerUnit > 0) {
            $time = max($time, $minPerUnit);
        }

        return (int)floor($time);
    }

    /**
     * Check requirements for recruiting a unit.
     *
     * @param int $unit_type_id Unit type ID
     * @param int $village_id Village ID
     * @param int $count Number of units to recruit (for cap checks)
     * @return array Status and reason on failure
     */
    public function checkRecruitRequirements($unit_type_id, $village_id, $count = 1)
    {
        if (!isset($this->unit_types_cache[$unit_type_id])) {
            return ['can_recruit' => false, 'reason' => 'unit_not_found', 'code' => 'ERR_PREREQ'];
        }

        $unit = $this->unit_types_cache[$unit_type_id];
        $internal = $unit['internal_name'] ?? '';

        // Get world ID for feature flag checks
        $worldId = defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1;

        // Check world feature flags
        if (!$this->isUnitAvailable($internal, $worldId)) {
            return [
                'can_recruit' => false,
                'reason' => 'feature_disabled',
                'code' => 'ERR_FEATURE_DISABLED',
                'unit' => $internal
            ];
        }

        // Check seasonal window
        $window = $this->checkSeasonalWindow($internal, time());
        if (!$window['available'] && $window['start'] !== null) {
            return [
                'can_recruit' => false,
                'reason' => 'seasonal_expired',
                'code' => 'ERR_SEASONAL_EXPIRED',
                'unit' => $internal,
                'window_start' => $window['start'],
                'window_end' => $window['end']
            ];
        }

        // Get user ID for elite cap checks
        $stmtUser = $this->conn->prepare("SELECT user_id FROM villages WHERE id = ? LIMIT 1");
        if (!$stmtUser) {
            return ['can_recruit' => false, 'reason' => 'database_error', 'code' => 'ERR_SERVER'];
        }
        $stmtUser->bind_param("i", $village_id);
        $stmtUser->execute();
        $villageRow = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();
        
        if (!$villageRow) {
            return ['can_recruit' => false, 'reason' => 'village_not_found', 'code' => 'ERR_PREREQ'];
        }
        $userId = (int)$villageRow['user_id'];

        // Check elite unit caps
        $capCheck = $this->checkEliteUnitCap($userId, $internal, $count);
        if (!$capCheck['can_train']) {
            return [
                'can_recruit' => false,
                'reason' => 'elite_cap_reached',
                'code' => 'ERR_CAP',
                'unit' => $internal,
                'current_count' => $capCheck['current'],
                'max_cap' => $capCheck['max']
            ];
        }

        // Check required building exists
        $stmt = $this->conn->prepare("
            SELECT vb.level
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE bt.internal_name = ? AND vb.village_id = ?
        ");

        $building_type = $unit['building_type'];
        $stmt->bind_param("si", $building_type, $village_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return ['can_recruit' => false, 'reason' => 'building_not_found', 'code' => 'ERR_PREREQ'];
        }

        $building = $result->fetch_assoc();
        $stmt->close();

        // Check building level
        if ($building['level'] < $unit['required_building_level']) {
            return [
                'can_recruit' => false,
                'reason' => 'building_level_too_low',
                'code' => 'ERR_PREREQ',
                'required_building_level' => $unit['required_building_level'],
                'current_building_level' => $building['level']
            ];
        }

        // Check research requirements
        if (!empty($unit['required_tech']) && $unit['required_tech_level'] > 0) {
            $stmt = $this->conn->prepare("
                SELECT level
                FROM village_research
                WHERE village_id = ? AND research_type_id = (
                    SELECT id FROM research_types WHERE internal_name = ?
                )
            ");

            $stmt->bind_param("is", $village_id, $unit['required_tech']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $stmt->close();
                return [
                    'can_recruit' => false,
                    'reason' => 'tech_level_too_low',
                    'code' => 'ERR_PREREQ',
                    'required_tech' => $unit['required_tech'],
                    'required_tech_level' => $unit['required_tech_level'],
                    'current_tech_level' => 0
                ];
            }

            $research = $result->fetch_assoc();
            $stmt->close();

            if ($research['level'] < $unit['required_tech_level']) {
                return [
                    'can_recruit' => false,
                    'reason' => 'tech_level_too_low',
                    'code' => 'ERR_PREREQ',
                    'required_tech' => $unit['required_tech'],
                    'required_tech_level' => $unit['required_tech_level'],
                    'current_tech_level' => $research['level']
                ];
            }
        }

        return ['can_recruit' => true];
    }

    /**
     * Count total nobles a user owns across all villages.
     */
    public function countUserNobles(int $userId): int
    {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(vu.count), 0) AS nobles
            FROM village_units vu
            JOIN villages v ON vu.village_id = v.id
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE v.user_id = ? AND ut.internal_name IN ('noble','nobleman','nobleman_unit')
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['nobles'] ?? 0);
    }

    public function getMaxNoblesForUser(int $userId): int
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM villages WHERE user_id = ?");
        if (!$stmt) {
            return 1;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $villages = (int)($row['cnt'] ?? 1);
        return max(1, (int)floor($villages / 3) + 1);
    }

    /**
     * Check whether a player has enough resources for recruitment.
     *
     * @param int $unit_type_id Unit type ID
     * @param int $count Number of units
     * @param array $resources Player resources (wood, clay, iron)
     * @return array Status and costs
     */
    public function checkResourcesForRecruitment($unit_type_id, $count, $resources)
    {
        if (!isset($this->unit_types_cache[$unit_type_id])) {
            return [
            'can_afford' => false,
                'reason' => 'unit_not_found'
            ];
        }

        $unit = $this->unit_types_cache[$unit_type_id];

        $wood_cost = $unit['cost_wood'] * $count;
        $clay_cost = $unit['cost_clay'] * $count;
        $iron_cost = $unit['cost_iron'] * $count;

        $can_afford = (
            $resources['wood'] >= $wood_cost &&
            $resources['clay'] >= $clay_cost &&
            $resources['iron'] >= $iron_cost
        );

        return [
            'can_afford' => $can_afford,
            'total_costs' => [
                'wood' => $wood_cost,
                'clay' => $clay_cost,
                'iron' => $iron_cost
            ],
            'missing' => [
                'wood' => max(0, $wood_cost - $resources['wood']),
                'clay' => max(0, $clay_cost - $resources['clay']),
                'iron' => max(0, $iron_cost - $resources['iron'])
            ]
        ];
    }

    /**
     * Recruit units - add to recruitment queue.
     *
     * @param int $village_id Village ID
     * @param int $unit_type_id Unit type ID
     * @param int $count Number of units to recruit
     * @param int $building_level Building level
     * @return array Operation status
     */
    public function recruitUnits($village_id, $unit_type_id, $count, $building_level)
    {
        if ($count === null || (int)$count <= 0) {
            return [
                'success' => false,
                'error' => 'You must recruit at least one unit.',
                'code' => 'ERR_INPUT'
            ];
        }
        $count = (int)$count;

        if ($building_level === null || (int)$building_level <= 0) {
            return [
                'success' => false,
                'error' => 'Training building not available.',
                'code' => 'ERR_PREREQ'
            ];
        }
        $building_level = (int)$building_level;

        if (!isset($this->unit_types_cache[$unit_type_id])) {
            return [
                'success' => false,
                'error' => 'Unit does not exist.'
            ];
        }

        $unit = $this->unit_types_cache[$unit_type_id];
        $building_type = $unit['building_type'];
        $unitPop = (int)($unit['population'] ?? 0);
        $internal = $unit['internal_name'] ?? '';

        // Get user ID for elite cap checks
        $stmtUser = $this->conn->prepare("SELECT user_id FROM villages WHERE id = ? LIMIT 1");
        if (!$stmtUser) {
            return ['success' => false, 'error' => 'Database error.'];
        }
        $stmtUser->bind_param("i", $village_id);
        $stmtUser->execute();
        $villageRow = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();
        
        if (!$villageRow) {
            return ['success' => false, 'error' => 'Village not found.'];
        }
        $userId = (int)$villageRow['user_id'];

        // Check elite unit caps
        $capCheck = $this->checkEliteUnitCap($userId, $internal, $count);
        if (!$capCheck['can_train']) {
            return [
                'success' => false,
                'error' => 'Elite unit cap reached for your account.',
                'code' => 'ERR_CAP',
                'cap' => $capCheck['max'],
                'current' => $capCheck['current']
            ];
        }

        // Check conquest unit resource requirements (coins/standards)
        $isConquestUnit = in_array($internal, ['noble', 'nobleman', 'standard_bearer', 'envoy'], true);
        if ($isConquestUnit) {
            // Per-command conquest cap: limit training batch size
            // This prevents training more conquest units than can be used in a single command
            $maxConquestPerCommand = 1; // MAX_LOYALTY_UNITS_PER_COMMAND from BattleManager
            if ($count > $maxConquestPerCommand) {
                return [
                    'success' => false,
                    'error' => "Cannot train more than $maxConquestPerCommand conquest unit(s) at once (per-command limit).",
                    'code' => 'ERR_CAP',
                    'cap' => $maxConquestPerCommand,
                    'requested' => $count
                ];
            }
            
            // Check if village has enough coins/standards
            $resourceField = in_array($internal, ['noble', 'nobleman'], true) ? 'noble_coins' : 'standards';
            
            $stmtRes = $this->conn->prepare("SELECT $resourceField FROM villages WHERE id = ? LIMIT 1");
            if ($stmtRes) {
                $stmtRes->bind_param("i", $village_id);
                $stmtRes->execute();
                $resRow = $stmtRes->get_result()->fetch_assoc();
                $stmtRes->close();
                
                $available = (int)($resRow[$resourceField] ?? 0);
                if ($available < $count) {
                    return [
                        'success' => false,
                        'error' => "Not enough $resourceField to train conquest units.",
                        'code' => 'ERR_RES',
                        'required' => $count,
                        'available' => $available
                    ];
                }
            }
        }

        // Siege cap per village (counts existing + queued)
        if (self::SIEGE_CAP_PER_VILLAGE > 0 && in_array($internal, self::SIEGE_INTERNALS, true)) {
            $siegeCount = $this->getVillageUnitCountWithQueue($village_id, self::SIEGE_INTERNALS);
            if (($siegeCount + $count) > self::SIEGE_CAP_PER_VILLAGE) {
                return [
                    'success' => false,
                    'error' => 'Siege cap reached for this village.',
                    'code' => 'ERR_CAP',
                    'cap' => self::SIEGE_CAP_PER_VILLAGE,
                    'current' => $siegeCount
                ];
            }
        }

        // Population cap check (farm capacity)
        $farmCapacity = $this->getFarmCapacity($village_id);
        $popUsage = $this->getPopulationUsage($village_id);
        $pendingPop = $unitPop * $count;
        if ($farmCapacity > 0 && ($popUsage['used'] + $popUsage['queued'] + $pendingPop) > $farmCapacity) {
            return [
                'success' => false,
                'error' => 'Not enough farm capacity to recruit these units.',
                'code' => 'ERR_POP',
                'farm_capacity' => $farmCapacity,
                'population_used' => $popUsage,
                'population_needed' => $pendingPop
            ];
        }

        // Resource availability validation
        $stmtResources = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ? LIMIT 1");
        if (!$stmtResources) {
            return ['success' => false, 'error' => 'Database error.', 'code' => 'ERR_SERVER'];
        }
        $stmtResources->bind_param("i", $village_id);
        $stmtResources->execute();
        $resourceRow = $stmtResources->get_result()->fetch_assoc();
        $stmtResources->close();
        
        if (!$resourceRow) {
            return ['success' => false, 'error' => 'Village not found.', 'code' => 'ERR_SERVER'];
        }
        
        $wood_cost = (int)($unit['cost_wood'] ?? 0) * $count;
        $clay_cost = (int)($unit['cost_clay'] ?? 0) * $count;
        $iron_cost = (int)($unit['cost_iron'] ?? 0) * $count;
        
        $wood_available = (int)($resourceRow['wood'] ?? 0);
        $clay_available = (int)($resourceRow['clay'] ?? 0);
        $iron_available = (int)($resourceRow['iron'] ?? 0);
        
        $wood_missing = max(0, $wood_cost - $wood_available);
        $clay_missing = max(0, $clay_cost - $clay_available);
        $iron_missing = max(0, $iron_cost - $iron_available);
        
        if ($wood_missing > 0 || $clay_missing > 0 || $iron_missing > 0) {
            return [
                'success' => false,
                'error' => 'Not enough resources to recruit these units.',
                'code' => 'ERR_RES',
                'missing' => [
                    'wood' => $wood_missing,
                    'clay' => $clay_missing,
                    'iron' => $iron_missing
                ],
                'required' => [
                    'wood' => $wood_cost,
                    'clay' => $clay_cost,
                    'iron' => $iron_cost
                ],
                'available' => [
                    'wood' => $wood_available,
                    'clay' => $clay_available,
                    'iron' => $iron_available
                ]
            ];
        }

        // Calculate training time
        $time_per_unit = $this->calculateRecruitmentTime($unit_type_id, $building_level);
        $total_time = $time_per_unit * $count;

        // Current timestamp
        $current_time = time();
        $finish_time = $current_time + $total_time;

        // Begin transaction for atomic resource deduction
        $this->conn->begin_transaction();

        try {
            // Deduct conquest resources if applicable
            if ($isConquestUnit) {
                $resourceField = in_array($internal, ['noble', 'nobleman'], true) ? 'noble_coins' : 'standards';
                $stmtDeduct = $this->conn->prepare("UPDATE villages SET $resourceField = $resourceField - ? WHERE id = ?");
                if (!$stmtDeduct) {
                    throw new Exception("Failed to prepare resource deduction");
                }
                $stmtDeduct->bind_param("ii", $count, $village_id);
                if (!$stmtDeduct->execute()) {
                    throw new Exception("Failed to deduct conquest resources");
                }
                $stmtDeduct->close();
            }

            // Insert into recruitment queue
            $stmt = $this->conn->prepare("
                INSERT INTO unit_queue
                (village_id, unit_type_id, count, count_finished, started_at, finish_at, building_type)
                VALUES (?, ?, ?, 0, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Failed to prepare queue insertion");
            }

            $stmt->bind_param("iiiiss", $village_id, $unit_type_id, $count, $current_time, $finish_time, $building_type);

            if (!$stmt->execute()) {
                throw new Exception("Failed to insert into queue");
            }

            $queue_id = $stmt->insert_id;
            $stmt->close();

            // Commit transaction
            $this->conn->commit();

            return [
                'success' => true,
                'message' => "Started recruiting $count units. Finishes at " . date('H:i:s d.m.Y', $finish_time),
                'queue_id' => $queue_id,
                'finish_time' => $finish_time
            ];

        } catch (Exception $e) {
            // Rollback on error
            $this->conn->rollback();
            return [
                'success' => false,
                'error' => 'Database error while adding to the recruitment queue: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update recruitment queues - process finished units
     */
    public function updateRecruitmentQueues()
    {
        $current_time = time();

        // Fetch all active recruitment queues
        $stmt = $this->conn->prepare("
            SELECT id, village_id, unit_type_id, count, count_finished, finish_at
            FROM unit_queue
            WHERE count_finished < count
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        while ($queue = $result->fetch_assoc()) {
            $queue_id = $queue['id'];
            $village_id = $queue['village_id'];
            $unit_type_id = $queue['unit_type_id'];
            $total_units = $queue['count'];
            $finished_units = $queue['count_finished'];
            $finish_time = $queue['finish_at'];

            // Check whether the queue is completed
            if ($current_time >= $finish_time) {
                // Update finished units count
                $remaining_units = $total_units - $finished_units;
                $new_finished = $total_units;

                // Update queue
                $update_stmt = $this->conn->prepare("
                    UPDATE unit_queue
                    SET count_finished = ?
                    WHERE id = ?
                ");

                $update_stmt->bind_param("ii", $new_finished, $queue_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Add completed units to the village
                $this->addUnitsToVillage($village_id, $unit_type_id, $remaining_units);
            }
        }

        $stmt->close();

        // Remove finished queues
        $this->cleanupFinishedQueues();
    }

    /**
     * Add units to a village after recruitment completes.
     *
     * @param int $village_id Village ID
     * @param int $unit_type_id Unit type ID
     * @param int $count Number of units to add
     */
    private function addUnitsToVillage($village_id, $unit_type_id, $count)
    {
        // Check if this unit type already exists in the village
        $stmt = $this->conn->prepare("
            SELECT id, count
            FROM village_units
            WHERE village_id = ? AND unit_type_id = ?
        ");

        $stmt->bind_param("ii", $village_id, $unit_type_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing units
            $unit = $result->fetch_assoc();
            $new_count = $unit['count'] + $count;

            $update_stmt = $this->conn->prepare("
                UPDATE village_units
                SET count = ?
                WHERE id = ?
            ");

            $update_stmt->bind_param("ii", $new_count, $unit['id']);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Insert new units
            $insert_stmt = $this->conn->prepare("
                INSERT INTO village_units
                (village_id, unit_type_id, count)
                VALUES (?, ?, ?)
            ");

            $insert_stmt->bind_param("iii", $village_id, $unit_type_id, $count);
            $insert_stmt->execute();
            $insert_stmt->close();
        }

        $stmt->close();
    }

    /**
     * Get population usage (existing + queued) for a village.
     */
    private function getPopulationUsage(int $villageId): array
    {
        $used = 0;
        $queued = 0;

        $stmtUsed = $this->conn->prepare("
            SELECT SUM(vu.count * ut.population) AS pop_used
            FROM village_units vu
            JOIN unit_types ut ON ut.id = vu.unit_type_id
            WHERE vu.village_id = ?
        ");
        if ($stmtUsed) {
            $stmtUsed->bind_param("i", $villageId);
            $stmtUsed->execute();
            $row = $stmtUsed->get_result()->fetch_assoc();
            $used = (int)($row['pop_used'] ?? 0);
            $stmtUsed->close();
        }

        $stmtQueued = $this->conn->prepare("
            SELECT SUM((uq.count - uq.count_finished) * ut.population) AS pop_queued
            FROM unit_queue uq
            JOIN unit_types ut ON ut.id = uq.unit_type_id
            WHERE uq.village_id = ?
        ");
        if ($stmtQueued) {
            $stmtQueued->bind_param("i", $villageId);
            $stmtQueued->execute();
            $rowQ = $stmtQueued->get_result()->fetch_assoc();
            $queued = (int)($rowQ['pop_queued'] ?? 0);
            $stmtQueued->close();
        }

        return ['used' => $used, 'queued' => $queued];
    }

    /**
     * Fetch farm capacity for a village (population cap).
     */
    private function getFarmCapacity(int $villageId): int
    {
        $stmt = $this->conn->prepare("SELECT farm_capacity FROM villages WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($row['farm_capacity']) ? (int)$row['farm_capacity'] : 0;
    }

    /**
     * Get total count of specified unit internals in village (existing + queued).
     * 
     * @param int $villageId Village ID
     * @param array $internalNames Array of unit internal names to count
     * @return int Total count of units (existing + queued)
     * 
     * Requirements: 9.5
     */
    public function getVillageUnitCountWithQueue(int $villageId, array $internalNames): int
    {
        if (empty($internalNames)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($internalNames), '?'));
        $types = str_repeat('s', count($internalNames));

        // Existing units
        $existing = 0;
        $sqlExisting = "
            SELECT COALESCE(SUM(vu.count), 0) AS cnt
            FROM village_units vu
            JOIN unit_types ut ON ut.id = vu.unit_type_id
            WHERE vu.village_id = ? AND ut.internal_name IN ($placeholders)
        ";
        $stmt = $this->conn->prepare($sqlExisting);
        if ($stmt) {
            $stmt->bind_param('i' . $types, $villageId, ...$internalNames);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $existing = (int)($row['cnt'] ?? 0);
            $stmt->close();
        }

        // Queued units
        $queued = 0;
        $sqlQueued = "
            SELECT COALESCE(SUM((uq.count - uq.count_finished)), 0) AS cnt
            FROM unit_queue uq
            JOIN unit_types ut ON ut.id = uq.unit_type_id
            WHERE uq.village_id = ? AND ut.internal_name IN ($placeholders)
        ";
        $stmtQ = $this->conn->prepare($sqlQueued);
        if ($stmtQ) {
            $stmtQ->bind_param('i' . $types, $villageId, ...$internalNames);
            $stmtQ->execute();
            $rowQ = $stmtQ->get_result()->fetch_assoc();
            $queued = (int)($rowQ['cnt'] ?? 0);
            $stmtQ->close();
        }

        return $existing + $queued;
    }

    /**
     * Remove finished recruitment queues
     */
    private function cleanupFinishedQueues()
    {
        $this->conn->query("
            DELETE FROM unit_queue
            WHERE count_finished >= count
        ");
    }

    /**
     * Get current units in a village
     *
     * @param int $village_id Village ID
     * @return array Unit array
     */
    public function getVillageUnits($village_id)
    {
        $units = [];

        $stmt = $this->conn->prepare("
            SELECT vu.unit_type_id, vu.count, ut.internal_name, ut.name,
                   ut.attack, ut.defense, ut.speed, ut.population
            FROM village_units vu
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE vu.village_id = ?
        ");

        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($unit = $result->fetch_assoc()) {
            $units[$unit['unit_type_id']] = $unit;
        }

        $stmt->close();
        return $units;
    }

    /**
     * Get current recruitment queues for a village
     *
     * @param int $village_id Village ID
     * @param string $building_type Optional building type
     * @return array Recruitment queues
     */
    public function getRecruitmentQueues($village_id, $building_type = null)
    {
        $queues = [];

        $sql = "
            SELECT uq.id, uq.unit_type_id, uq.count, uq.count_finished,
                   uq.started_at, uq.finish_at, uq.building_type,
                   ut.name, ut.internal_name
            FROM unit_queue uq
            JOIN unit_types ut ON uq.unit_type_id = ut.id
            WHERE uq.village_id = ?
        ";

        if ($building_type) {
            $sql .= " AND uq.building_type = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("is", $village_id, $building_type);
        } else {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $village_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($queue = $result->fetch_assoc()) {
            $queues[] = $queue;
        }

        $stmt->close();
        return $queues;
    }

    /**
     * Process the recruitment queue for a specific village.
     *
     * @param int $village_id Village ID
     * @return array Info about completed and updated queues
     */
    public function processRecruitmentQueue($village_id)
    {
        $current_time = time();
        $result = [
            'completed_queues' => [],
            'updated_queues' => []
        ];

        // Fetch active recruitment queues for the village
        $stmt = $this->conn->prepare("
            SELECT id, unit_type_id, count, count_finished, finish_at, building_type
            FROM unit_queue
            WHERE village_id = ? AND count_finished < count
        ");

        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $queues = $stmt->get_result();

        while ($queue = $queues->fetch_assoc()) {
            $queue_id = $queue['id'];
            $unit_type_id = $queue['unit_type_id'];
            $total_units = $queue['count'];
            $finished_units = $queue['count_finished'];
            $finish_time = $queue['finish_at'];

            // Get the unit name
            $unit_name = "";
            if (isset($this->unit_types_cache[$unit_type_id])) {
                $unit_name = $this->unit_types_cache[$unit_type_id]['name'];
            }

            // Calculate how many units should be finished at this time
            $time_per_unit = ($finish_time - $queue['count_finished']) / ($total_units - $finished_units);
            $elapsed_time = $current_time - $finish_time + ($total_units - $finished_units) * $time_per_unit;
            $units_should_be_finished = min($total_units, $finished_units + floor($elapsed_time / $time_per_unit));

            if ($units_should_be_finished > $finished_units) {
                // New units to add
                $new_units = $units_should_be_finished - $finished_units;

                // Add units to the village
                $this->addUnitsToVillage($village_id, $unit_type_id, $new_units);

                // Update queue state
                $update_stmt = $this->conn->prepare("
                    UPDATE unit_queue
                    SET count_finished = ?
                    WHERE id = ?
                ");

                $update_stmt->bind_param("ii", $units_should_be_finished, $queue_id);
                $update_stmt->execute();
                $update_stmt->close();

                // If all units are finished
                if ($units_should_be_finished >= $total_units) {
                    $result['completed_queues'][] = [
                        'queue_id' => $queue_id,
                        'unit_type_id' => $unit_type_id,
                        'unit_name' => $unit_name,
                        'count' => $total_units,
                        'produced_now' => $new_units,
                    ];
                } else {
                    $result['updated_queues'][] = [
                        'queue_id' => $queue_id,
                        'unit_type_id' => $unit_type_id,
                        'unit_name' => $unit_name,
                        'units_finished' => $new_units,
                        'produced_now' => $new_units,
                        'total_units' => $total_units,
                        'remaining_units' => $total_units - $units_should_be_finished
                    ];
                }
            }
        }

        $stmt->close();

        // Remove finished queues
        $this->cleanupFinishedQueues();

        return $result;
    }

    /**
     * Cancel unit recruitment from the queue
     *
     * @param int $queue_id Recruitment queue ID to cancel
     * @param int $user_id User ID
     * @return array Operation status
     */
    public function cancelRecruitment($queue_id, $user_id)
    {
        // Fetch queue info and ensure it belongs to the user
        $stmt = $this->conn->prepare("
            SELECT uq.id, uq.village_id, uq.unit_type_id, uq.count, uq.count_finished, ut.name
            FROM unit_queue uq
            JOIN unit_types ut ON uq.unit_type_id = ut.id
            JOIN villages v ON uq.village_id = v.id
            WHERE uq.id = ? AND v.user_id = ?
        ");

        $stmt->bind_param("ii", $queue_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'The recruitment queue does not exist or you do not have access to it.'
            ];
        }

        $queue = $result->fetch_assoc();
        $stmt->close();

        // Add already trained units to the village
        if ($queue['count_finished'] > 0) {
            $this->addUnitsToVillage($queue['village_id'], $queue['unit_type_id'], $queue['count_finished']);
        }

        // Delete the recruitment queue
        $stmt_delete = $this->conn->prepare("DELETE FROM unit_queue WHERE id = ?");
        $stmt_delete->bind_param("i", $queue_id);
        $success = $stmt_delete->execute();
        $stmt_delete->close();

        if (!$success) {
            return [
                'success' => false,
                'error' => 'An error occurred while cancelling recruitment.'
            ];
        }

        $message = '';
        if ($queue['count_finished'] > 0) {
            $message = "Cancelled recruitment of {$queue['name']} units. Added {$queue['count_finished']} finished units to the village.";
        } else {
            $message = "Cancelled recruitment of {$queue['name']} units.";
        }

        return [
            'success' => true,
            'message' => $message,
            'village_id' => $queue['village_id'],
            'unit_type_id' => $queue['unit_type_id'],
            'count_finished' => $queue['count_finished']
        ];
    }

    /**
     * Load unit configuration version for audits; fallback to max(updated_at) from unit_types.
     */
    private function loadUnitConfigVersion(): ?array
    {
        try {
            $check = $this->conn->query("SHOW TABLES LIKE 'unit_config_versions'");
            if ($check && $check->num_rows > 0) {
                $res = $this->conn->query("SELECT version, updated_at FROM unit_config_versions ORDER BY updated_at DESC LIMIT 1");
                if ($res && ($row = $res->fetch_assoc())) {
                    return [
                        'version' => $row['version'] ?? null,
                        'updated_at' => $row['updated_at'] ?? null
                    ];
                }
            }
        } catch (Throwable $e) {
            // Ignore and fallback
        }

        try {
            $res = $this->conn->query("SELECT MAX(updated_at) AS updated_at FROM unit_types");
            if ($res && ($row = $res->fetch_assoc()) && !empty($row['updated_at'])) {
                return [
                    'version' => sha1($row['updated_at']),
                    'updated_at' => $row['updated_at']
                ];
            }
        } catch (Throwable $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Get unit category/archetype for RPS calculations.
     * 
     * @param int $unitTypeId Unit type ID
     * @return string Category: 'infantry', 'cavalry', 'ranged', 'siege', 'scout', 'support', 'conquest'
     * 
     * Requirements: 1.4, 3.3, 8.4
     */
    public function getUnitCategory(int $unitTypeId): string
    {
        if (!isset($this->unit_types_cache[$unitTypeId])) {
            return 'infantry'; // Default fallback
        }

        $unit = $this->unit_types_cache[$unitTypeId];
        
        // Check if category is explicitly defined in unit data
        if (!empty($unit['category'])) {
            return strtolower($unit['category']);
        }

        // Fallback: infer from building type and internal name
        $buildingType = strtolower($unit['building_type'] ?? '');
        $internal = strtolower($unit['internal_name'] ?? '');

        // Conquest units
        if (in_array($internal, ['noble', 'nobleman', 'standard_bearer', 'envoy'], true)) {
            return 'conquest';
        }

        // Support units
        if (in_array($internal, ['banner_guard', 'war_healer', 'healer'], true)) {
            return 'support';
        }

        // Scout units
        if (in_array($internal, ['pathfinder', 'shadow_rider', 'scout'], true)) {
            return 'scout';
        }

        // Siege units
        if ($buildingType === 'workshop' || $buildingType === 'garage' ||
            in_array($internal, ['ram', 'battering_ram', 'catapult', 'stone_hurler', 'mantlet', 'mantlet_crew'], true)) {
            return 'siege';
        }

        // Cavalry units
        if ($buildingType === 'stable' ||
            in_array($internal, ['skirmisher_cavalry', 'lancer', 'light', 'heavy', 'cavalry', 'knight'], true)) {
            return 'cavalry';
        }

        // Ranged units
        if (str_contains($internal, 'archer') || str_contains($internal, 'bow') || 
            str_contains($internal, 'ranger') || in_array($internal, ['militia_bowman', 'longbow_scout'], true)) {
            return 'ranged';
        }

        // Default to infantry
        return 'infantry';
    }

    /**
     * Check if unit is available based on world features.
     * 
     * @param string $unitInternal Internal unit name
     * @param int $worldId World ID
     * @return bool True if unit is enabled
     * 
     * Requirements: 10.1, 10.2, 15.5
     */
    public function isUnitAvailable(string $unitInternal, int $worldId): bool
    {
        $internal = strtolower(trim($unitInternal));

        // Check conquest units
        if (in_array($internal, ['noble', 'nobleman', 'standard_bearer', 'envoy'], true)) {
            return $this->worldManager->isConquestUnitEnabled($worldId);
        }

        // Check seasonal units
        $seasonalUnits = ['tempest_knight', 'event_knight'];
        if (in_array($internal, $seasonalUnits, true)) {
            if (!$this->worldManager->isSeasonalUnitsEnabled($worldId)) {
                return false;
            }
            // Also check seasonal window
            $window = $this->checkSeasonalWindow($internal, time());
            return $window['available'];
        }

        // Check healer units
        if (in_array($internal, ['war_healer', 'healer'], true)) {
            return $this->worldManager->isHealerEnabled($worldId);
        }

        // All other units are available by default
        return true;
    }

    /**
     * Get effective unit stats after world multipliers.
     * 
     * @param int $unitTypeId Unit type ID
     * @param int $worldId World ID
     * @return array Effective stats with multipliers applied
     * 
     * Requirements: 11.1, 11.2, 11.3, 11.4
     */
    public function getEffectiveUnitStats(int $unitTypeId, int $worldId): array
    {
        if (!isset($this->unit_types_cache[$unitTypeId])) {
            return [];
        }

        $unit = $this->unit_types_cache[$unitTypeId];
        $archetype = $this->resolveUnitArchetype($unit);

        // Get world multipliers
        $worldSpeed = $this->worldManager->getWorldSpeed($worldId);
        $trainMultiplier = $this->worldManager->getTrainSpeedForArchetype($archetype, $worldId);
        $costMultiplier = $this->worldManager->getCostMultiplierForArchetype($archetype, $worldId);

        // Apply training time multiplier
        $baseTime = (int)($unit['training_time_base'] ?? 0);
        $effectiveTime = $baseTime / ($worldSpeed * $trainMultiplier);

        // Apply cost multipliers by archetype
        $baseCosts = [
            'wood' => (int)($unit['cost_wood'] ?? 0),
            'clay' => (int)($unit['cost_clay'] ?? 0),
            'iron' => (int)($unit['cost_iron'] ?? 0)
        ];
        
        $effectiveCosts = [
            'wood' => (int)floor($baseCosts['wood'] * $costMultiplier),
            'clay' => (int)floor($baseCosts['clay'] * $costMultiplier),
            'iron' => (int)floor($baseCosts['iron'] * $costMultiplier)
        ];

        return [
            'unit_type_id' => $unitTypeId,
            'name' => $unit['name'] ?? '',
            'internal_name' => $unit['internal_name'] ?? '',
            'category' => $this->getUnitCategory($unitTypeId),
            'attack' => (int)($unit['attack'] ?? 0),
            'defense_infantry' => (int)($unit['defense_infantry'] ?? 0),
            'defense_cavalry' => (int)($unit['defense_cavalry'] ?? 0),
            'defense_ranged' => (int)($unit['defense_ranged'] ?? 0),
            'speed_min_per_field' => (int)($unit['speed'] ?? $unit['speed_min_per_field'] ?? 0),
            'carry_capacity' => (int)($unit['carry'] ?? $unit['carry_capacity'] ?? 0),
            'population' => (int)($unit['population'] ?? 0),
            'training_time_base' => $baseTime,
            'training_time_effective' => (int)floor($effectiveTime),
            'cost_wood_base' => $baseCosts['wood'],
            'cost_clay_base' => $baseCosts['clay'],
            'cost_iron_base' => $baseCosts['iron'],
            'cost_wood' => $effectiveCosts['wood'],
            'cost_clay' => $effectiveCosts['clay'],
            'cost_iron' => $effectiveCosts['iron'],
            'world_speed_multiplier' => $worldSpeed,
            'archetype_train_multiplier' => $trainMultiplier,
            'archetype_cost_multiplier' => $costMultiplier
        ];
    }

    /**
     * Check seasonal unit availability window.
     * 
     * @param string $unitInternal Internal unit name
     * @param int $timestamp Current timestamp
     * @return array ['available' => bool, 'start' => int|null, 'end' => int|null]
     * 
     * Requirements: 10.1, 10.2, 10.4
     */
    public function checkSeasonalWindow(string $unitInternal, int $timestamp): array
    {
        $stmt = $this->conn->prepare("
            SELECT start_timestamp, end_timestamp, is_active
            FROM seasonal_units
            WHERE unit_internal_name = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return ['available' => false, 'start' => null, 'end' => null];
        }

        $stmt->bind_param("s", $unitInternal);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            // Not a seasonal unit, so it's available
            return ['available' => true, 'start' => null, 'end' => null];
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        $start = (int)($row['start_timestamp'] ?? 0);
        $end = (int)($row['end_timestamp'] ?? 0);
        $isActive = (bool)($row['is_active'] ?? false);

        $available = $isActive && $timestamp >= $start && $timestamp <= $end;

        return [
            'available' => $available,
            'start' => $start,
            'end' => $end,
            'is_active' => $isActive
        ];
    }

    /**
     * Enforce per-account elite unit caps.
     * 
     * @param int $userId User ID
     * @param string $unitInternal Internal unit name
     * @param int $count Requested count
     * @return array ['can_train' => bool, 'current' => int, 'max' => int]
     * 
     * Requirements: 9.2
     */
    public function checkEliteUnitCap(int $userId, string $unitInternal, int $count): array
    {
        // Define default elite unit caps
        $defaultEliteUnitCaps = [
            'warden' => 100,
            'ranger' => 100,
            'tempest_knight' => 50,
            'event_knight' => 50
        ];

        $internal = strtolower(trim($unitInternal));

        // If not an elite unit, no cap applies
        if (!isset($defaultEliteUnitCaps[$internal])) {
            return ['can_train' => true, 'current' => 0, 'max' => -1];
        }

        $maxCap = $defaultEliteUnitCaps[$internal];

        // Count existing units across all villages for this user
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(vu.count), 0) AS total
            FROM village_units vu
            JOIN villages v ON vu.village_id = v.id
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE v.user_id = ? AND ut.internal_name = ?
        ");

        if (!$stmt) {
            return ['can_train' => false, 'current' => 0, 'max' => $maxCap, 'error' => 'database_error'];
        }

        $stmt->bind_param("is", $userId, $internal);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $currentCount = (int)($row['total'] ?? 0);

        // Count queued units
        $stmtQueue = $this->conn->prepare("
            SELECT COALESCE(SUM(uq.count - uq.count_finished), 0) AS queued
            FROM unit_queue uq
            JOIN villages v ON uq.village_id = v.id
            JOIN unit_types ut ON uq.unit_type_id = ut.id
            WHERE v.user_id = ? AND ut.internal_name = ?
        ");

        if ($stmtQueue) {
            $stmtQueue->bind_param("is", $userId, $internal);
            $stmtQueue->execute();
            $rowQ = $stmtQueue->get_result()->fetch_assoc();
            $stmtQueue->close();
            $currentCount += (int)($rowQ['queued'] ?? 0);
        }

        $canTrain = ($currentCount + $count) <= $maxCap;

        return [
            'can_train' => $canTrain,
            'current' => $currentCount,
            'max' => $maxCap
        ];
    }

    /**
     * Check if unit is available based on world features.
     * 
     * @param string $unitInternal Internal unit name
     * @param int $worldId World ID
     * @return bool True if unit is enabled
     * 
     * Requirements: 10.1, 10.2, 15.5
     */
    public function isUnitAvailable(string $unitInternal, int $worldId): bool
    {
        $conquestUnits = ['noble', 'nobleman', 'standard_bearer', 'envoy'];
        $seasonalUnits = ['tempest_knight', 'event_knight'];
        $healerUnits = ['war_healer', 'healer'];

        // Check conquest units
        if (in_array($unitInternal, $conquestUnits, true)) {
            return $this->worldManager->isConquestUnitEnabled($worldId);
        }

        // Check seasonal units
        if (in_array($unitInternal, $seasonalUnits, true)) {
            return $this->worldManager->isSeasonalUnitsEnabled($worldId);
        }

        // Check healer units
        if (in_array($unitInternal, $healerUnits, true)) {
            return $this->worldManager->isHealerEnabled($worldId);
        }

        // All other units are available by default
        return true;
    }

    /**
     * Get unit category/archetype for RPS calculations.
     * 
     * @param int $unitTypeId Unit type ID
     * @return string Category: 'infantry', 'cavalry', 'ranged', 'siege', 'scout', 'support', 'conquest'
     * 
     * Requirements: 1.4, 3.3, 8.4
     */
    public function getUnitCategory(int $unitTypeId): string
    {
        if (!isset($this->unit_types_cache[$unitTypeId])) {
            return 'infantry'; // Default fallback
        }

        $unit = $this->unit_types_cache[$unitTypeId];
        $internal = strtolower($unit['internal_name'] ?? '');
        $buildingType = strtolower($unit['building_type'] ?? '');

        // Conquest units
        if (in_array($internal, ['noble', 'nobleman', 'standard_bearer', 'envoy'], true)) {
            return 'conquest';
        }

        // Support units
        if (in_array($internal, ['banner_guard', 'war_healer', 'healer'], true)) {
            return 'support';
        }

        // Scout units
        if (in_array($internal, ['pathfinder', 'shadow_rider', 'scout'], true)) {
            return 'scout';
        }

        // Siege units (by building type or internal name)
        if ($buildingType === 'workshop' || $buildingType === 'garage' ||
            in_array($internal, ['ram', 'battering_ram', 'catapult', 'stone_hurler', 'mantlet', 'mantlet_crew'], true)) {
            return 'siege';
        }

        // Cavalry units (by building type)
        if ($buildingType === 'stable' ||
            in_array($internal, ['skirmisher_cavalry', 'lancer', 'cavalry', 'knight', 'light', 'heavy'], true)) {
            return 'cavalry';
        }

        // Ranged units (by internal name patterns)
        if (str_contains($internal, 'archer') || str_contains($internal, 'bow') || 
            str_contains($internal, 'ranger') || in_array($internal, ['militia_bowman', 'longbow_scout'], true)) {
            return 'ranged';
        }

        // Infantry (default for barracks units)
        return 'infantry';
    }

    /**
     * Check seasonal unit availability window.
     * 
     * @param string $unitInternal Internal unit name
     * @param int $timestamp Current timestamp
     * @return array ['available' => bool, 'start' => int|null, 'end' => int|null]
     * 
     * Requirements: 10.1, 10.2, 10.4
     */
    public function checkSeasonalWindow(string $unitInternal, int $timestamp): array
    {
        // Check if this is a seasonal unit
        $seasonalUnits = ['tempest_knight', 'event_knight'];
        if (!in_array($unitInternal, $seasonalUnits, true)) {
            // Not a seasonal unit, always available
            return ['available' => true, 'start' => null, 'end' => null];
        }

        // Query seasonal_units table for this unit
        $stmt = $this->conn->prepare("
            SELECT start_timestamp, end_timestamp, is_active
            FROM seasonal_units
            WHERE unit_internal_name = ?
            LIMIT 1
        ");

        if (!$stmt) {
            // If table doesn't exist or query fails, assume not available
            return ['available' => false, 'start' => null, 'end' => null];
        }

        $stmt->bind_param("s", $unitInternal);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            // No seasonal window configured, not available
            return ['available' => false, 'start' => null, 'end' => null];
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        $start = (int)$row['start_timestamp'];
        $end = (int)$row['end_timestamp'];
        $isActive = (bool)$row['is_active'];

        // Check if current timestamp is within window and unit is active
        $available = $isActive && $timestamp >= $start && $timestamp <= $end;

        return [
            'available' => $available,
            'start' => $start,
            'end' => $end
        ];
    }
}

?> 
