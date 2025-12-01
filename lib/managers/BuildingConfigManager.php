<?php
declare(strict_types=1);

class BuildingConfigManager {
    private $conn;
    private $buildingConfigs = []; // Cache for building configurations
    private $buildingRequirements = []; // Cache for requirements lookups

    public function __construct($conn) {
        $this->conn = $conn;
        // Optionally load all configs on construct for performance
        // $this->loadAllBuildingConfigs();
    }

    // Load all building configurations into cache
    private function loadAllBuildingConfigs() {
        $sql = "SELECT * FROM building_types";
        $result = $this->conn->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->buildingConfigs[$row['internal_name']] = $row;
            }
            $result->free();
        }
    }

    // Fetch configuration of a single building type.
    // Prefers cache but falls back to DB when missing.
    public function getBuildingConfig(string $internalName): ?array {
        if (isset($this->buildingConfigs[$internalName])) {
            return $this->buildingConfigs[$internalName];
        }

        $stmt = $this->conn->prepare("SELECT *, population_cost FROM building_types WHERE internal_name = ? LIMIT 1");
        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("s", $internalName);
        $stmt->execute();
        $result = $stmt->get_result();
        $config = $result->fetch_assoc();
        $stmt->close();

        if ($config) {
            $this->buildingConfigs[$internalName] = $config; // Cache the result
            return $config;
        }

        return null; // Building type not found
    }

    /**
     * Retrieves all building configurations.
     * Uses cache, loads from DB if cache is empty.
     * @return array Config array keyed by internal_name.
     */
    public function getAllBuildingConfigs(): array
    {
        if (empty($this->buildingConfigs)) {
            $this->loadAllBuildingConfigs();
        }
        return $this->buildingConfigs;
    }

    // Calculate upgrade/build cost to the next level
    public function calculateUpgradeCost(string $internalName, int $currentLevel): ?array {
        $config = $this->getBuildingConfig($internalName);

        if (!$config) {
            return null; // Building config not found
        }

        // Cost for level $currentLevel + 1
        $nextLevel = $currentLevel + 1;
        $costWood = round($config['cost_wood_initial'] * ($config['cost_factor'] ** $currentLevel));
        $costClay = round($config['cost_clay_initial'] * ($config['cost_factor'] ** $currentLevel));
        $costIron = round($config['cost_iron_initial'] * ($config['cost_factor'] ** $currentLevel));

        return [
            'wood' => $costWood,
            'clay' => $costClay,
            'iron' => $costIron
        ];
    }

    // Calculate build/upgrade time to the next level
    // Includes the town hall bonus
    public function calculateUpgradeTime(string $internalName, int $currentLevel, int $mainBuildingLevel = 0): ?int {
        $config = $this->getBuildingConfig($internalName);

        if (!$config) {
            return null; // Building config not found
        }

        // Base time for level $currentLevel + 1
        $levelFactor = defined('BUILD_TIME_LEVEL_FACTOR') ? BUILD_TIME_LEVEL_FACTOR : 1.18;
        $baseTime = round($config['base_build_time_initial'] * ($levelFactor ** $currentLevel));

        // Global world speed
        $worldSpeed = defined('WORLD_SPEED') ? max(0.1, WORLD_SPEED) : 1.0;
        $buildSpeed = defined('BUILD_SPEED_MULTIPLIER') ? max(0.1, BUILD_SPEED_MULTIPLIER) : 1.0;

        // Headquarters (main_building) reduces build time by 2% per level: divide by (1 + 0.02 * level)
        $hqBonus = 1 + (max(0, $mainBuildingLevel) * (defined('MAIN_BUILDING_TIME_REDUCTION_PER_LEVEL') ? MAIN_BUILDING_TIME_REDUCTION_PER_LEVEL : 0.02));
        $effectiveTime = $baseTime / max(0.1, $worldSpeed * $buildSpeed * $hqBonus);

        // Minimal build time (e.g., 1 second)
        return (int)max(1, (int)ceil($effectiveTime));
    }

     // Calculate resource production for a given building and level
    public function calculateProduction(string $internalName, int $level): ?float {
        if ($level <= 0) {
            return 0.0;
        }

        $config = $this->getBuildingConfig($internalName);

        if (!$config || $config['production_type'] === NULL) {
            return null; // Does not produce resources
        }

        // Resource production is standardized for the three mines:
        // base 30 per hour * 1.163 ^ level
        if (in_array($internalName, ['sawmill', 'clay_pit', 'iron_mine'], true)) {
            $base = 30;
            $factor = 1.163;
            return $base * pow($factor, $level);
        }

        // Fallback to config for any other producing building types
        return $config['production_initial'] * ($config['production_factor'] ** ($level - 1));
    }
    
    // Calculate warehouse capacity for a given level
    public function calculateWarehouseCapacity(int $level): ?int {
         // Formula: 1000 * 1.229 ^ level
         $base = 1000;
         $factor = 1.229;
         $capacity = $base * pow($factor, $level);
         return (int) round($capacity);
    }
    
    // Calculate maximum population for a given farm level
    public function calculateFarmCapacity(int $level): ?int {
        $config = $this->getBuildingConfig('farm');
        
        if (!$config) {
            return null; // Farm config not found
        }
        
        // Farm capacity: 240 * 1.172^level
        $base = $config['production_initial'] ?? 240;
        $factor = defined('FARM_GROWTH_FACTOR') ? FARM_GROWTH_FACTOR : ($config['production_factor'] ?? 1.172);
        $capacity = $base * pow($factor, $level);
        
        return (int) round($capacity);
    }

    /**
     * Calculates population cost to upgrade a building to the next level.
     * @param string $internalName Building internal name.
     * @param int $currentLevel Current building level.
     * @return int|null Population cost or null on error.
     */
    public function calculatePopulationCost(string $internalName, int $currentLevel): ?int {
        $config = $this->getBuildingConfig($internalName);

        if (!$config || !isset($config['population_cost'])) {
            return null; // Building config not found or no population_cost defined
        }

        // Population cost for the next level (currentLevel + 1)
        // Assumes population_cost is per-level cost
        return (int)$config['population_cost'];
    }

     // Fetch requirements for a given building type
    public function getBuildingRequirements(string $internalName): array {
        if (isset($this->buildingRequirements[$internalName])) {
            return $this->buildingRequirements[$internalName];
        }

        $config = $this->getBuildingConfig($internalName);
        
        if (!$config) {
            return []; // Building config not found
        }
        
        $buildingTypeId = $config['id'];
        
        $stmt = $this->conn->prepare("SELECT required_building, required_level FROM building_requirements WHERE building_type_id = ?");
         if ($stmt === false) {
            error_log("Prepare failed for requirements: " . $this->conn->error);
            return [];
         }
        $stmt->bind_param("i", $buildingTypeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $requirements = [];
        while ($row = $result->fetch_assoc()) {
            $requirements[] = $row;
        }
        
        $stmt->close();
        $this->buildingRequirements[$internalName] = $requirements;

        return $this->buildingRequirements[$internalName];
    }

    /**
     * Returns production or capacity info for a building at the given level.
     * @param string $internalName Building internal name.
     * @param int $level Building level.
     * @return array|null Array with type ('production' or 'capacity') and value, or null when missing.
     */
    public function getProductionOrCapacityInfo(string $internalName, int $level): ?array {
        $config = $this->getBuildingConfig($internalName);

        if (!$config) {
            return null; // Building config not found
        }

        if ($config['production_type'] !== null) {
            $production = $this->calculateProduction($internalName, $level);
            if ($production !== null) {
                return [
                    'type' => 'production',
                    'amount_per_hour' => $production * 3600, // Convert per second to per hour
                    'resource_type' => $config['production_type']
                ];
            }
        }

        if ($internalName === 'warehouse') {
            $capacity = $this->calculateWarehouseCapacity($level);
            if ($capacity !== null) {
                return [
                    'type' => 'capacity',
                    'amount' => $capacity
                ];
            }
        } elseif ($internalName === 'farm') {
             $capacity = $this->calculateFarmCapacity($level);
             if ($capacity !== null) {
                 return [
                     'type' => 'capacity',
                     'amount' => $capacity
                 ];
             }
        }

        return null; // No production or capacity info for this building
    }
}

?>
