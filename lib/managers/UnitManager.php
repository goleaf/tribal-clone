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
     * @return array Status and reason on failure
     */
    public function checkRecruitRequirements($unit_type_id, $village_id)
    {
        if (!isset($this->unit_types_cache[$unit_type_id])) {
            return ['can_recruit' => false, 'reason' => 'unit_not_found'];
        }

        $unit = $this->unit_types_cache[$unit_type_id];

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
            return ['can_recruit' => false, 'reason' => 'building_not_found'];
        }

        $building = $result->fetch_assoc();
        $stmt->close();

        // Check building level
        if ($building['level'] < $unit['required_building_level']) {
            return [
                'can_recruit' => false,
                'reason' => 'building_level_too_low',
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

        // Calculate training time
        $time_per_unit = $this->calculateRecruitmentTime($unit_type_id, $building_level);
        $total_time = $time_per_unit * $count;

        // Current timestamp
        $current_time = time();
        $finish_time = $current_time + $total_time;

        // Insert into recruitment queue
        $stmt = $this->conn->prepare("
            INSERT INTO unit_queue
            (village_id, unit_type_id, count, count_finished, started_at, finish_at, building_type)
            VALUES (?, ?, ?, 0, ?, ?, ?)
        ");

        $stmt->bind_param("iiiiss", $village_id, $unit_type_id, $count, $current_time, $finish_time, $building_type);

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'error' => 'Database error while adding to the recruitment queue.'
            ];
        }

        $queue_id = $stmt->insert_id;
        $stmt->close();

        return [
            'success' => true,
            'message' => "Started recruiting $count units. Finishes at " . date('H:i:s d.m.Y', $finish_time),
            'queue_id' => $queue_id,
            'finish_time' => $finish_time
        ];
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
     */
    private function getVillageUnitCountWithQueue(int $villageId, array $internalNames): int
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
}

?> 
