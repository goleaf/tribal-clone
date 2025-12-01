<?php
declare(strict_types=1);

/**
 * PopulationManager
 * 
 * Manages village population capacity and consumption:
 * - Population cap is derived from Farm level: popCap(level) = floor(240 * 1.17^(level-1))
 * - Population used = buildings + own troops + supporting troops
 * - Enforces population limits before construction/recruitment
 * - Updates population on events (build complete, unit train/death, support arrival/departure)
 */
class PopulationManager
{
    private $conn;
    private const BASE_POPULATION = 240;
    private const GROWTH_FACTOR = 1.17;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    /**
     * Calculate population capacity from farm level.
     * Formula: floor(240 * 1.17^(level-1))
     * 
     * @param int $farmLevel Farm building level
     * @return int Population capacity
     */
    public function calculateFarmCapacity(int $farmLevel): int
    {
        if ($farmLevel <= 0) {
            return self::BASE_POPULATION; // Level 0 or negative defaults to base
        }
        
        $capacity = self::BASE_POPULATION * pow(self::GROWTH_FACTOR, max($farmLevel, 1) - 1);
        return (int)floor($capacity);
    }

    /**
     * Get current population state for a village.
     * 
     * @param int $villageId Village ID
     * @return array ['used' => int, 'cap' => int, 'available' => int]
     */
    public function getPopulationState(int $villageId): array
    {
        $cap = $this->getPopulationCap($villageId);
        $used = $this->getPopulationUsed($villageId);
        
        return [
            'used' => $used,
            'cap' => $cap,
            'available' => max(0, $cap - $used)
        ];
    }

    /**
     * Get population capacity for a village (based on farm level).
     * 
     * @param int $villageId Village ID
     * @return int Population capacity
     */
    public function getPopulationCap(int $villageId): int
    {
        $farmLevel = $this->getFarmLevel($villageId);
        return $this->calculateFarmCapacity($farmLevel);
    }

    /**
     * Get total population used in a village.
     * Sum of: building costs + own troops + supporting troops
     * 
     * @param int $villageId Village ID
     * @return int Total population used
     */
    public function getPopulationUsed(int $villageId): int
    {
        $buildingPop = $this->getBuildingPopulation($villageId);
        $troopPop = $this->getTroopPopulation($villageId);
        $supportPop = $this->getSupportPopulation($villageId);
        
        return $buildingPop + $troopPop + $supportPop;
    }

    /**
     * Get population consumed by buildings in a village.
     * 
     * @param int $villageId Village ID
     * @return int Building population cost
     */
    public function getBuildingPopulation(int $villageId): int
    {
        // Query building population costs
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(bt.population_cost), 0) as total_pop
            FROM buildings b
            JOIN building_types bt ON b.building_type = bt.internal_name
            WHERE b.village_id = ? AND b.level > 0
        ");
        
        if ($stmt === false) {
            error_log("PopulationManager::getBuildingPopulation prepare failed: " . $this->conn->error);
            return 0;
        }
        
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return (int)($result['total_pop'] ?? 0);
    }

    /**
     * Get population consumed by own troops in a village.
     * 
     * @param int $villageId Village ID
     * @return int Troop population cost
     */
    public function getTroopPopulation(int $villageId): int
    {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(u.quantity * ut.population), 0) as total_pop
            FROM units u
            JOIN unit_types ut ON u.unit_type = ut.internal_name
            WHERE u.village_id = ?
        ");
        
        if ($stmt === false) {
            error_log("PopulationManager::getTroopPopulation prepare failed: " . $this->conn->error);
            return 0;
        }
        
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return (int)($result['total_pop'] ?? 0);
    }

    /**
     * Get population consumed by supporting troops (from allies) in a village.
     * 
     * @param int $villageId Village ID
     * @return int Support troop population cost
     */
    public function getSupportPopulation(int $villageId): int
    {
        // Check if support_units table exists
        if (!$this->tableExists('support_units')) {
            return 0; // Table doesn't exist yet
        }
        
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(su.quantity * ut.population), 0) as total_pop
            FROM support_units su
            JOIN unit_types ut ON su.unit_type = ut.internal_name
            WHERE su.stationed_village_id = ?
        ");
        
        if ($stmt === false) {
            error_log("PopulationManager::getSupportPopulation prepare failed: " . $this->conn->error);
            return 0;
        }
        
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return (int)($result['total_pop'] ?? 0);
    }

    /**
     * Check if village has enough population for a given cost.
     * 
     * @param int $villageId Village ID
     * @param int $populationCost Population cost to check
     * @return bool True if enough population available
     */
    public function hasPopulation(int $villageId, int $populationCost): bool
    {
        $state = $this->getPopulationState($villageId);
        return ($state['used'] + $populationCost) <= $state['cap'];
    }

    /**
     * Check if village can afford building population cost.
     * 
     * @param int $villageId Village ID
     * @param string $buildingType Building internal name
     * @param int $targetLevel Target building level
     * @return array ['success' => bool, 'message' => string]
     */
    public function canAffordBuildingPopulation(int $villageId, string $buildingType, int $targetLevel): array
    {
        $popCost = $this->getBuildingPopulationCost($buildingType, $targetLevel);
        
        if ($popCost === null) {
            return ['success' => false, 'message' => 'Unknown building type.'];
        }
        
        // Get current building level to calculate delta
        $currentLevel = $this->getCurrentBuildingLevel($villageId, $buildingType);
        $currentPopCost = $this->getBuildingPopulationCost($buildingType, $currentLevel) ?? 0;
        $deltaPop = $popCost - $currentPopCost;
        
        if ($deltaPop <= 0) {
            return ['success' => true, 'message' => 'No additional population required.'];
        }
        
        if (!$this->hasPopulation($villageId, $deltaPop)) {
            $state = $this->getPopulationState($villageId);
            return [
                'success' => false,
                'message' => sprintf(
                    'Not enough population. Required: %d, Available: %d (Used: %d/%d)',
                    $deltaPop,
                    $state['available'],
                    $state['used'],
                    $state['cap']
                )
            ];
        }
        
        return ['success' => true, 'message' => 'Population available.'];
    }

    /**
     * Check if village can afford unit recruitment population cost.
     * 
     * @param int $villageId Village ID
     * @param string $unitType Unit internal name
     * @param int $quantity Number of units to recruit
     * @return array ['success' => bool, 'message' => string]
     */
    public function canAffordUnitPopulation(int $villageId, string $unitType, int $quantity): array
    {
        $unitPopCost = $this->getUnitPopulationCost($unitType);
        
        if ($unitPopCost === null) {
            return ['success' => false, 'message' => 'Unknown unit type.'];
        }
        
        $totalPopCost = $unitPopCost * $quantity;
        
        if (!$this->hasPopulation($villageId, $totalPopCost)) {
            $state = $this->getPopulationState($villageId);
            return [
                'success' => false,
                'message' => sprintf(
                    'Not enough population. Required: %d, Available: %d (Used: %d/%d)',
                    $totalPopCost,
                    $state['available'],
                    $state['used'],
                    $state['cap']
                )
            ];
        }
        
        return ['success' => true, 'message' => 'Population available.'];
    }

    /**
     * Update village population after farm level change.
     * Recalculates capacity based on new farm level.
     * 
     * @param int $villageId Village ID
     * @return array ['old_cap' => int, 'new_cap' => int, 'used' => int]
     */
    public function updateFarmCapacity(int $villageId): array
    {
        $oldCap = $this->getPopulationCap($villageId);
        $farmLevel = $this->getFarmLevel($villageId);
        $newCap = $this->calculateFarmCapacity($farmLevel);
        $used = $this->getPopulationUsed($villageId);
        
        return [
            'old_cap' => $oldCap,
            'new_cap' => $newCap,
            'used' => $used,
            'available' => max(0, $newCap - $used)
        ];
    }

    /**
     * Perform sanity check: recompute population from authoritative sources.
     * Useful for periodic validation to correct drift.
     * 
     * @param int $villageId Village ID
     * @return array ['buildings' => int, 'troops' => int, 'support' => int, 'total' => int, 'cap' => int]
     */
    public function sanityCheck(int $villageId): array
    {
        $buildings = $this->getBuildingPopulation($villageId);
        $troops = $this->getTroopPopulation($villageId);
        $support = $this->getSupportPopulation($villageId);
        $total = $buildings + $troops + $support;
        $cap = $this->getPopulationCap($villageId);
        
        return [
            'buildings' => $buildings,
            'troops' => $troops,
            'support' => $support,
            'total' => $total,
            'cap' => $cap,
            'available' => max(0, $cap - $total),
            'over_capacity' => $total > $cap
        ];
    }

    // ---- Helper methods ----

    /**
     * Get farm level for a village.
     */
    private function getFarmLevel(int $villageId): int
    {
        $stmt = $this->conn->prepare("
            SELECT level
            FROM buildings
            WHERE village_id = ? AND building_type = 'farm'
            LIMIT 1
        ");
        
        if ($stmt === false) {
            error_log("PopulationManager::getFarmLevel prepare failed: " . $this->conn->error);
            return 0;
        }
        
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return (int)($result['level'] ?? 0);
    }

    /**
     * Get population cost for a building at a specific level.
     */
    private function getBuildingPopulationCost(string $buildingType, int $level): ?int
    {
        if ($level <= 0) {
            return 0;
        }
        
        $stmt = $this->conn->prepare("
            SELECT population_cost
            FROM building_types
            WHERE internal_name = ?
            LIMIT 1
        ");
        
        if ($stmt === false) {
            error_log("PopulationManager::getBuildingPopulationCost prepare failed: " . $this->conn->error);
            return null;
        }
        
        $stmt->bind_param("s", $buildingType);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) {
            return null;
        }
        
        // Population cost might scale with level (implement scaling if needed)
        return (int)($result['population_cost'] ?? 0);
    }

    /**
     * Get current building level in a village.
     */
    private function getCurrentBuildingLevel(int $villageId, string $buildingType): int
    {
        $stmt = $this->conn->prepare("
            SELECT level
            FROM buildings
            WHERE village_id = ? AND building_type = ?
            LIMIT 1
        ");
        
        if ($stmt === false) {
            error_log("PopulationManager::getCurrentBuildingLevel prepare failed: " . $this->conn->error);
            return 0;
        }
        
        $stmt->bind_param("is", $villageId, $buildingType);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return (int)($result['level'] ?? 0);
    }

    /**
     * Get population cost for a unit type.
     */
    private function getUnitPopulationCost(string $unitType): ?int
    {
        $stmt = $this->conn->prepare("
            SELECT population
            FROM unit_types
            WHERE internal_name = ?
            LIMIT 1
        ");
        
        if ($stmt === false) {
            error_log("PopulationManager::getUnitPopulationCost prepare failed: " . $this->conn->error);
            return null;
        }
        
        $stmt->bind_param("s", $unitType);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result ? (int)$result['population'] : null;
    }

    /**
     * Check if a table exists in the database.
     */
    private function tableExists(string $tableName): bool
    {
        $stmt = $this->conn->prepare("
            SELECT name 
            FROM sqlite_master 
            WHERE type='table' AND name=?
        ");
        
        if ($stmt === false) {
            return false;
        }
        
        $stmt->bind_param("s", $tableName);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result !== null;
    }
}
