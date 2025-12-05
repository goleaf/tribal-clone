<?php
declare(strict_types=1);

class BuildingConfigManager {
    private $conn;
    private $buildingConfigs = []; // Cache for building configurations
    private $buildingRequirements = []; // Cache for requirements lookups
    private array $costCache = [];
    private array $timeCache = [];
    private ?string $configVersion = null;
    private const COST_FACTOR_MIN = 1.01;
    private const COST_FACTOR_MAX = 1.6;

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

    public function getMaxLevel(string $internalName): ?int
    {
        $config = $this->getBuildingConfig($internalName);
        if (!$config) {
            return null;
        }
        if (isset($config['max_level']) && $config['max_level'] !== null) {
            return (int)$config['max_level'];
        }
        // Fallback caps via global constants when not defined in config rows.
        $internal = strtolower($internalName);
        if ($internal === 'wall' && defined('WALL_MAX_LEVEL')) {
            return (int)WALL_MAX_LEVEL;
        }
        if (in_array($internal, ['watchtower', 'watch_tower'], true) && defined('WATCHTOWER_MAX_LEVEL')) {
            return (int)WATCHTOWER_MAX_LEVEL;
        }
        return null;
    }

    // Calculate upgrade/build cost to the next level
    public function calculateUpgradeCost(string $internalName, int $currentLevel): ?array {
        $config = $this->getBuildingConfig($internalName);

        if (!$config) {
            return null; // Building config not found
        }

        $maxLevel = $this->getMaxLevel($internalName);
        if ($maxLevel !== null && $currentLevel >= $maxLevel) {
            return null; // Already at cap
        }

        // Cost for level $currentLevel + 1
        if (isset($this->costCache[$internalName][$currentLevel])) {
            return $this->costCache[$internalName][$currentLevel];
        }

        $nextLevel = $currentLevel + 1; // kept for readability if/when level-specific logic is expanded
        $costFactor = $this->clampCostFactor((float)$config['cost_factor']);
        $costWood = round($config['cost_wood_initial'] * ($costFactor ** $currentLevel));
        $costClay = round($config['cost_clay_initial'] * ($costFactor ** $currentLevel));
        $costIron = round($config['cost_iron_initial'] * ($costFactor ** $currentLevel));

        $costs = [
            'wood' => $costWood,
            'clay' => $costClay,
            'iron' => $costIron
        ];
        $this->costCache[$internalName][$currentLevel] = $costs;
        return $costs;
    }

    // Calculate build/upgrade time to the next level
    // Includes the town hall bonus
    public function calculateUpgradeTime(string $internalName, int $currentLevel, int $mainBuildingLevel = 0): ?int {
        $config = $this->getBuildingConfig($internalName);

        if (!$config) {
            return null; // Building config not found
        }

        $maxLevel = $this->getMaxLevel($internalName);
        if ($maxLevel !== null && $currentLevel >= $maxLevel) {
            return null; // At cap
        }

        // Base time for level $currentLevel + 1
        $targetLevel = $currentLevel + 1;
        $levelFactor = defined('BUILD_TIME_LEVEL_FACTOR') ? BUILD_TIME_LEVEL_FACTOR : 1.18;
        $baseTime = round($config['base_build_time_initial'] * ($levelFactor ** $currentLevel));

        // Global world speed (per-world)
        require_once __DIR__ . '/WorldManager.php';
        $wm = new WorldManager($this->conn);
        $worldSpeed = $wm->getWorldSpeed();
        $buildSpeed = $wm->getBuildSpeed();

        $cacheKey = implode('|', [$currentLevel, max(0, $mainBuildingLevel), $worldSpeed, $buildSpeed]);
        if (isset($this->timeCache[$internalName][$cacheKey])) {
            return $this->timeCache[$internalName][$cacheKey];
        }

        // Headquarters (main_building) reduces build time by 2% per level: divide by (1 + 0.02 * level)
        $hqBonus = 1 + (max(0, $mainBuildingLevel) * (defined('MAIN_BUILDING_TIME_REDUCTION_PER_LEVEL') ? MAIN_BUILDING_TIME_REDUCTION_PER_LEVEL : 0.02));
        $effectiveTime = $baseTime / max(0.1, $worldSpeed * $buildSpeed * $hqBonus);

        // Enforce gentle floors so mid-tier builds sit in the 30–90 min range
        // and higher tiers give 2–4h “anchor” builds for overnight queuing.
        $tierFloors = [
            ['min' => 5,  'max' => 8,  'seconds' => 1800],  // 30m+
            ['min' => 9,  'max' => 12, 'seconds' => 3600],  // 1h+
            ['min' => 13, 'max' => 20, 'seconds' => 7200],  // 2h+
        ];
        foreach ($tierFloors as $tier) {
            if ($targetLevel >= $tier['min'] && $targetLevel <= $tier['max']) {
                $effectiveTime = max($effectiveTime, $tier['seconds']);
                break;
            }
        }

        // Minimal build time (e.g., 1 second)
        $finalTime = (int)max(1, (int)ceil($effectiveTime));
        $this->timeCache[$internalName][$cacheKey] = $finalTime;
        return $finalTime;
    }

     /**
     * Calculate resource production per hour for a given building and level.
     * Formula: prod(l) = base * growth^(l-1) for level l (1–30)
     * Applies world speed and building speed multipliers.
     * 
     * @param string $internalName Building internal name (sawmill, clay_pit, iron_mine)
     * @param int $level Building level (1-30)
     * @return float|null Production per hour, or null if building doesn't produce
     */
    public function calculateProduction(string $internalName, int $level): ?float {
        if ($level <= 0) {
            return 0.0;
        }

        $config = $this->getBuildingConfig($internalName);

        if (!$config || $config['production_type'] === NULL) {
            return null; // Does not produce resources
        }

        // Get world speed multipliers (uses CURRENT_WORLD_ID by default)
        require_once __DIR__ . '/WorldManager.php';
        $wm = new WorldManager($this->conn);
        $worldSpeed = $wm->getWorldSpeed();
        $buildSpeed = $wm->getBuildSpeed();

        // Resource production formula: base * growth^(level-1) * world_speed * building_speed
        // Timber base=30, Clay base=30, Iron base=25; growth=1.163
        if (in_array($internalName, ['sawmill', 'clay_pit', 'iron_mine'], true)) {
            $base = ($internalName === 'iron_mine') ? 25 : 30;
            $growth = 1.163;
            
            // prod(l) = base * growth^(l-1) * world_speed * building_speed
            $baseProduction = $base * pow($growth, max($level, 1) - 1);
            return $baseProduction * $worldSpeed * $buildSpeed;
        }

        // Fallback to config for any other producing building types
        $baseProduction = $config['production_initial'] * ($config['production_factor'] ** ($level - 1));
        return $baseProduction * $worldSpeed * $buildSpeed;
    }

    /**
     * Return a deterministic version string for current building configs.
     * Useful for API/cache headers so clients can bust cached curves on change.
     */
    public function getConfigVersion(): string
    {
        if ($this->configVersion !== null) {
            return $this->configVersion;
        }
        $configs = $this->getAllBuildingConfigs();
        // Stable hash over config content; short prefix keeps payload light
        $this->configVersion = substr(md5(json_encode($configs)), 0, 12);
        return $this->configVersion;
    }

    /**
     * Clear cached configs/costs/times; call after config changes.
     */
    public function invalidateCache(): void
    {
        $this->buildingConfigs = [];
        $this->buildingRequirements = [];
        $this->costCache = [];
        $this->timeCache = [];
        $this->configVersion = null;
    }
    
    // Calculate warehouse capacity for a given level
    public function calculateWarehouseCapacity(int $level): ?int {
         // Formula: 1000 * 1.229 ^ level
         $base = 1000;
         $factor = 1.229;
         $capacity = $base * pow($factor, $level);
         return (int) round($capacity);
    }
    
    /**
     * Calculate Hiding Place capacity for a given level.
     * Formula: 150 * 1.233 ^ level
     * This determines how many resources per type are protected from plunder.
     * 
     * @param int $level Hiding Place level (0-10 typically)
     * @return int Protected resources per type (wood, clay, iron)
     */
    public function calculateHidingPlaceCapacity(int $level): int {
        if ($level <= 0) {
            return 0;
        }
        // Formula: 150 * 1.233 ^ level
        $base = 150;
        $factor = 1.233;
        $capacity = $base * pow($factor, $level);
        return (int) floor($capacity);
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

    /**
     * Clamp cost factor to guardrails to prevent runaway costs or trivially cheap scaling.
     */
    private function clampCostFactor(float $factor): float
    {
        if ($factor <= 0) {
            $factor = 1.0;
        }
        $factor = max(self::COST_FACTOR_MIN, min(self::COST_FACTOR_MAX, $factor));
        return $factor;
    }
}

?>
