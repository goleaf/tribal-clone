<?php
// require_once 'lib/BuildingManager.php'; // Old path
require_once __DIR__ . '/BuildingManager.php'; // Corrected path

class ResourceManager {
    private $conn;
    private $buildingManager;
    private array $worldEconomyCache = [];
    private ?CatchupManager $catchupManager = null;

    public function __construct($conn, $buildingManager) {
        $this->conn = $conn;
        $this->buildingManager = $buildingManager;
        if (class_exists('CatchupManager')) {
            $this->catchupManager = new CatchupManager($conn);
        }
    }

    /**
     * Gets hourly resource production for a village (all types)
     */
    public function getProductionRates(int $village_id): array {
        $worldId = $this->getWorldIdForVillage($village_id);
        $worldConfig = $this->getWorldEconomyConfig($worldId);
        $resourceMultiplier = $worldConfig['resource_multiplier'] ?? 1.0;
        $catchupMultiplier = 1.0;
        $ownerId = $this->getUserIdByVillage($village_id);
        if ($ownerId !== null && $this->catchupManager) {
            $catchupMultiplier = $this->catchupManager->getMultiplier($ownerId);
        }

        $stmt = $this->conn->prepare(
            "SELECT bt.internal_name, vb.level
             FROM village_buildings vb
             JOIN building_types bt ON vb.building_type_id = bt.id
             WHERE vb.village_id = ? AND bt.production_type IS NOT NULL"
        );
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $levels = [];
        while ($row = $result->fetch_assoc()) {
            $levels[$row['internal_name']] = (int)$row['level'];
        }
        $stmt->close();

        $multiplier = $this->catchupManager ? $this->catchupManager->getMultiplier($this->getUserIdByVillage($village_id)) : 1.0;

        $mult = $resourceMultiplier * $catchupMultiplier;

        return [
            'wood' => $this->buildingManager->getHourlyProduction('sawmill', $levels['sawmill'] ?? 0) * $mult,
            'clay' => $this->buildingManager->getHourlyProduction('clay_pit', $levels['clay_pit'] ?? 0) * $mult,
            'iron' => $this->buildingManager->getHourlyProduction('iron_mine', $levels['iron_mine'] ?? 0) * $mult,
        ];
    }

    /**
     * Gets hourly production for a single resource type for the village.
     */
    public function getHourlyProductionRate(int $village_id, string $resource_type): float {
        // Validate the resource type and its matching building
        $building_map = [
            'wood' => 'sawmill',
            'clay' => 'clay_pit',
            'iron' => 'iron_mine',
        ];

        if (!isset($building_map[$resource_type])) {
            // Invalid resource type, return 0
            return 0.0;
        }

        $building_internal_name = $building_map[$resource_type];

        // Get level of the relevant production building
        $stmt = $this->conn->prepare("
            SELECT vb.level 
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = ?
        ");
        $stmt->bind_param("is", $village_id, $building_internal_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $level = 0;
        if ($row = $result->fetch_assoc()) {
            $level = (int)$row['level'];
        }
        $stmt->close();

        // Use BuildingManager to calculate hourly production
        $worldId = $this->getWorldIdForVillage($village_id);
        if (!class_exists('WorldManager')) {
            require_once __DIR__ . '/WorldManager.php';
        }
        $worldManager = class_exists('WorldManager') ? new WorldManager($this->conn) : null;
        $resourceMultiplier = $worldManager ? $worldManager->getResourceProductionMultiplier($worldId) : 1.0;

        return $this->buildingManager->getHourlyProduction($building_internal_name, $level) * $resourceMultiplier;
    }

    /**
     * Updates village resources in the database and returns the refreshed village data.
     * Implements offline gain calculation with production formula:
     * prod(l) = base * growth^(l-1) * world_speed * building_speed
     * 
     * Warehouse cap with optional 2% buffer for overflow tolerance.
     */
    public function updateVillageResources(array $village): array {
        $village_id = (int)$village['id'];
        $worldId = $this->getWorldIdForVillage($village_id);
        $worldConfig = $this->getWorldEconomyConfig($worldId);
        $now = time();
        $last_update = strtotime($village['last_resource_update']);
        
        // Calculate elapsed time in hours
        $dt_hours = max(0, ($now - $last_update) / 3600.0);
        
        if ($dt_hours <= 0) {
            return $village; // No time elapsed, no update needed
        }

        // Get production rates (already includes world_speed and building_speed multipliers)
        $rates = $this->getProductionRates($village_id);
        
        // Calculate gained resources: prod_eff * dt_hours
        $gained_wood = $rates['wood'] * $dt_hours;
        $gained_clay = $rates['clay'] * $dt_hours;
        $gained_iron = $rates['iron'] * $dt_hours;

        // Warehouse capacity based on the current warehouse level
        $warehouse_level = $this->buildingManager->getBuildingLevel($village_id, 'warehouse');
        $warehouse_capacity = $this->buildingManager->getWarehouseCapacityByLevel($warehouse_level);
        
        // Apply 2% buffer for overflow tolerance before clamping to display
        $warehouse_cap_with_buffer = $warehouse_capacity * 1.02;

        // Track if we hit cap this tick (before buffer)
        $hitCap = [
            'wood' => ($village['wood'] < $warehouse_capacity) && ($village['wood'] + $gained_wood >= $warehouse_capacity),
            'clay' => ($village['clay'] < $warehouse_capacity) && ($village['clay'] + $gained_clay >= $warehouse_capacity),
            'iron' => ($village['iron'] < $warehouse_capacity) && ($village['iron'] + $gained_iron >= $warehouse_capacity),
        ];

        // Apply gains with buffer, then round down for display
        $village['wood'] = min($village['wood'] + $gained_wood, $warehouse_cap_with_buffer);
        $village['clay'] = min($village['clay'] + $gained_clay, $warehouse_cap_with_buffer);
        $village['iron'] = min($village['iron'] + $gained_iron, $warehouse_cap_with_buffer);

        // Optional decay for hoarded resources above threshold
        $decayEnabled = $worldConfig['resource_decay_enabled'] ?? (defined('RESOURCE_DECAY_ENABLED') ? (bool)RESOURCE_DECAY_ENABLED : false);
        if ($decayEnabled) {
            $threshold = $worldConfig['resource_decay_threshold_pct'] ?? (defined('RESOURCE_DECAY_THRESHOLD') ? (float)RESOURCE_DECAY_THRESHOLD : 0.8); // 80% of cap
            $ratePerHour = $worldConfig['resource_decay_rate_per_hour'] ?? (defined('RESOURCE_DECAY_RATE') ? (float)RESOURCE_DECAY_RATE : 0.01); // 1% of overage per hour
            $thresholdAmount = $warehouse_capacity * $threshold;

            foreach (['wood', 'clay', 'iron'] as $res) {
                $current = $village[$res];
                if ($current > $thresholdAmount && $ratePerHour > 0) {
                    $over = $current - $thresholdAmount;
                    $decay = max(0, $over * $ratePerHour * $dt_hours);
                    $village[$res] = max($thresholdAmount, $current - $decay);
                }
            }
        }

        $nowSql = date('Y-m-d H:i:s', $now);

        $stmt = $this->conn->prepare(
            "UPDATE villages
             SET wood = ?, clay = ?, iron = ?, warehouse_capacity = ?, last_resource_update = ?
             WHERE id = ?"
        );
        // Bind numeric (double) values for resources
        $stmt->bind_param("dddisi", $village['wood'], $village['clay'], $village['iron'], $warehouse_capacity, $nowSql, $village_id);
        $stmt->execute();
        $stmt->close();

        $village['warehouse_capacity'] = $warehouse_capacity;
        $village['last_resource_update'] = $nowSql;

        // Notify if any resource hit capacity
        if (in_array(true, $hitCap, true)) {
            if (!class_exists('NotificationManager')) {
                require_once __DIR__ . '/NotificationManager.php';
            }
            $notificationManager = new NotificationManager($this->conn);
            // Fetch owner to target notification
            $ownerStmt = $this->conn->prepare("SELECT user_id, name FROM villages WHERE id = ?");
            if ($ownerStmt) {
                $ownerStmt->bind_param("i", $village_id);
                $ownerStmt->execute();
                $ownerData = $ownerStmt->get_result()->fetch_assoc();
                $ownerStmt->close();
                if ($ownerData && !empty($ownerData['user_id'])) {
                    $resourceList = [];
                    foreach (['wood','clay','iron'] as $res) {
                        if ($hitCap[$res]) {
                            $resourceList[] = ucfirst($res);
                        }
                    }
                    $msg = sprintf(
                        'Warehouse full for %s in %s.',
                        implode(', ', $resourceList),
                        $ownerData['name'] ?? 'village'
                    );
                    $notificationManager->addNotification((int)$ownerData['user_id'], $msg, 'warning', '/game/game.php');
                }
            }
        }
        
        // Return the updated village array
        return $village;
    }

    private function getUserIdByVillage(int $villageId): ?int
    {
        $stmt = $this->conn->prepare("SELECT user_id FROM villages WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row && isset($row['user_id']) ? (int)$row['user_id'] : null;
    }

    /**
     * Attempts to deduct the given resource costs from a village.
     *
     * @param int $villageId
     * @param array $costs ['wood' => int, 'clay' => int, 'iron' => int]
     * @return array ['success' => bool, 'message' => string, 'resources' => array|null]
     */
    public function spendResources(int $villageId, array $costs): array
    {
        $costs = [
            'wood' => (int)($costs['wood'] ?? 0),
            'clay' => (int)($costs['clay'] ?? 0),
            'iron' => (int)($costs['iron'] ?? 0),
        ];

        $stmt = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Cannot load village resources.', 'resources' => null];
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$res) {
            return ['success' => false, 'message' => 'Village not found.', 'resources' => null];
        }

        if ($res['wood'] < $costs['wood'] || $res['clay'] < $costs['clay'] || $res['iron'] < $costs['iron']) {
            return ['success' => false, 'message' => 'Not enough resources.', 'resources' => $res];
        }

        $newWood = $res['wood'] - $costs['wood'];
        $newClay = $res['clay'] - $costs['clay'];
        $newIron = $res['iron'] - $costs['iron'];

        $stmtUpdate = $this->conn->prepare("
            UPDATE villages
            SET wood = ?, clay = ?, iron = ?
            WHERE id = ?
        ");
        if ($stmtUpdate === false) {
            return ['success' => false, 'message' => 'Failed to prepare resource update.', 'resources' => $res];
        }
        $stmtUpdate->bind_param("iiii", $newWood, $newClay, $newIron, $villageId);
        $ok = $stmtUpdate->execute();
        $stmtUpdate->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to update resources.', 'resources' => $res];
        }

        return [
            'success' => true,
            'message' => 'Resources deducted.',
            'resources' => [
                'wood' => $newWood,
                'clay' => $newClay,
                'iron' => $newIron
            ]
        ];
    }

    private function getWorldIdForVillage(int $villageId): int
    {
        $stmt = $this->conn->prepare("SELECT world_id FROM villages WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ? (int)$res['world_id'] : (defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1);
    }

    private function getWorldEconomyConfig(int $worldId): array
    {
        if (isset($this->worldEconomyCache[$worldId])) {
            return $this->worldEconomyCache[$worldId];
        }

        if (!class_exists('WorldManager')) {
            require_once __DIR__ . '/WorldManager.php';
        }
        if (class_exists('WorldManager')) {
            $wm = new WorldManager($this->conn);
            $this->worldEconomyCache[$worldId] = [
                'resource_multiplier' => $wm->getResourceProductionMultiplier($worldId),
                'vault_protect_pct' => (int)$wm->getVaultProtectionPercent($worldId),
                'resource_decay_enabled' => $wm->isResourceDecayEnabled($worldId),
                'resource_decay_threshold_pct' => $wm->getResourceDecayThresholdPct($worldId),
                'resource_decay_rate_per_hour' => $wm->getResourceDecayRatePerHour($worldId),
            ];
            return $this->worldEconomyCache[$worldId];
        }

        // Fallback if WorldManager is unavailable
        return $this->worldEconomyCache[$worldId] = [
            'resource_multiplier' => 1.0,
            'vault_protect_pct' => 10
        ];
    }
}
