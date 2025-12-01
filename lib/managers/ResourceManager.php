<?php
// require_once 'lib/BuildingManager.php'; // Old path
require_once __DIR__ . '/BuildingManager.php'; // Corrected path

class ResourceManager {
    private $conn;
    private $buildingManager;

    public function __construct($conn, $buildingManager) {
        $this->conn = $conn;
        $this->buildingManager = $buildingManager;
    }

    /**
     * Gets hourly resource production for a village (all types)
     */
    public function getProductionRates(int $village_id): array {
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

        return [
            'wood' => $this->buildingManager->getHourlyProduction('sawmill', $levels['sawmill'] ?? 0),
            'clay' => $this->buildingManager->getHourlyProduction('clay_pit', $levels['clay_pit'] ?? 0),
            'iron' => $this->buildingManager->getHourlyProduction('iron_mine', $levels['iron_mine'] ?? 0),
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
        return $this->buildingManager->getHourlyProduction($building_internal_name, $level);
    }

    /**
     * Updates village resources in the database and returns the refreshed village data.
     */
    public function updateVillageResources(array $village): array {
        $village_id = (int)$village['id'];
        $rates = $this->getProductionRates($village_id);
        $now = time();
        $last_update = strtotime($village['last_resource_update']);
        $elapsed = max(0, $now - $last_update);

        $produced_wood = ($rates['wood'] / 3600) * $elapsed;
        $produced_clay = ($rates['clay'] / 3600) * $elapsed;
        $produced_iron = ($rates['iron'] / 3600) * $elapsed;

        // Warehouse capacity based on the current warehouse level
        $warehouse_level = $this->buildingManager->getBuildingLevel($village_id, 'warehouse');
        $warehouse_capacity = $this->buildingManager->getWarehouseCapacityByLevel($warehouse_level);

        $village['wood'] = min($village['wood'] + $produced_wood, $warehouse_capacity);
        $village['clay'] = min($village['clay'] + $produced_clay, $warehouse_capacity);
        $village['iron'] = min($village['iron'] + $produced_iron, $warehouse_capacity);

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
        
        // Return the updated village array
        return $village;
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
} 
