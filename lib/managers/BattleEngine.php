<?php
/**
 * BattleEngine - Advanced combat resolution system
 * Implements detailed battle mechanics including:
 * - Offense/Defense calculations with unit class matching
 * - Wall, morale, night bonus, and luck modifiers
 * - Square-root casualty mechanics
 * - Siege mechanics (rams and catapults)
 */
class BattleEngine
{
    private $conn;
    private $unitData;
    
    // Configuration constants
    private const LUCK_MIN = 0.75;
    private const LUCK_MAX = 1.25;
    private const MORALE_MIN = 0.5;
    private const MORALE_MAX = 1.5;
    private const MORALE_BASE = 0.3;
    private const CASUALTY_EXPONENT = 1.5;
    private const NIGHT_BONUS = 1.5;
    private const WALL_BASE_MULTIPLIER = 1.037;
    private const WALL_LEVEL_THRESHOLD = 10;
    
    // Unit class mappings
    private const UNIT_CLASSES = [
        // Legacy/internal names
        'spear' => 'infantry',
        'sword' => 'infantry',
        'axe' => 'infantry',
        'archer' => 'archer',
        'scout' => 'cavalry',
        'light' => 'cavalry',
        'heavy' => 'cavalry',
        'ram' => 'infantry',
        'catapult' => 'infantry',
        'noble' => 'cavalry',
        'paladin' => 'cavalry',
        'marcher' => 'archer',

        // Design-roster names
        'tribesman' => 'infantry',
        'spearguard' => 'infantry',
        'axe_warrior' => 'infantry',
        'bowman' => 'archer',
        'slinger' => 'archer',
        'raider' => 'cavalry',
        'lancer' => 'cavalry',
        'horse_archer' => 'archer',
        'battering_ram' => 'infantry',
        'supply_cart' => 'infantry',
        'berserker' => 'infantry',
        'shieldmaiden' => 'infantry',
        'warlord' => 'cavalry',
        'rune_priest' => 'infantry'
    ];
    
    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->loadUnitData();
    }
    
    /**
     * Load unit data from database or JSON file
     */
    private function loadUnitData()
    {
        $jsonPath = __DIR__ . '/../../data/units.json';
        if (file_exists($jsonPath)) {
            $this->unitData = json_decode(file_get_contents($jsonPath), true);
        } else {
            throw new Exception('Unit data file not found');
        }
    }
    
    /**
     * Main battle resolution function
     * 
     * @param array $attackerUnits Map of unit internal_name => count
     * @param array $defenderUnits Map of unit internal_name => count
     * @param int $wallLevel Defender's wall level
     * @param int $defenderPoints Defender's total points
     * @param int $attackerPoints Attacker's total points
     * @param array $worldConfig World configuration (speed, night bonus enabled, etc.)
     * @param string|null $targetBuilding Target building for catapults
     * @param int $targetBuildingLevel Current level of target building
     * @return array Battle result with casualties, loot, siege effects
     */
    public function resolveBattle(
        array $attackerUnits,
        array $defenderUnits,
        int $wallLevel,
        int $defenderPoints,
        int $attackerPoints,
        array $worldConfig = [],
        ?string $targetBuilding = null,
        int $targetBuildingLevel = 0
    ): array {
        // Generate luck factor
        $luck = $this->generateLuck();
        
        // Calculate morale
        $morale = $this->calculateMorale($defenderPoints, $attackerPoints);
        
        // Calculate total offense
        $totalOff = $this->calculateTotalOffense($attackerUnits) * $luck * $morale;
        
        // Calculate effective defense
        $effectiveDef = $this->calculateEffectiveDefense($defenderUnits, $attackerUnits);
        
        // Apply wall multiplier
        $wallMult = $this->calculateWallMultiplier($wallLevel);
        $effectiveDef *= $wallMult;
        
        // Apply night bonus if enabled
        if ($this->isNightTime($worldConfig)) {
            $effectiveDef *= self::NIGHT_BONUS;
        }

        // Apply optional terrain/weather modifiers from world config
        $terrainOffMult = (float)($worldConfig['terrain_attack_multiplier'] ?? 1.0);
        $terrainDefMult = (float)($worldConfig['terrain_defense_multiplier'] ?? 1.0);
        $weatherOffMult = (float)($worldConfig['weather_attack_multiplier'] ?? 1.0);
        $weatherDefMult = (float)($worldConfig['weather_defense_multiplier'] ?? 1.0);
        $totalOff *= $terrainOffMult * $weatherOffMult;
        $effectiveDef *= $terrainDefMult * $weatherDefMult;
        
        // Calculate battle ratio
        $ratio = $effectiveDef > 0 ? $totalOff / $effectiveDef : PHP_FLOAT_MAX;
        
        // Determine winner and calculate losses
        $attackerWon = $ratio >= 1;
        $attackerLossFactor = $attackerWon ? 1 / pow($ratio, self::CASUALTY_EXPONENT) : 1;
        $defenderLossFactor = $attackerWon ? 1 : pow($ratio, self::CASUALTY_EXPONENT);
        
        // Apply losses
        $attackerSurvivors = $this->applyLosses($attackerUnits, $attackerLossFactor);
        $defenderSurvivors = $this->applyLosses($defenderUnits, $defenderLossFactor);
        
        // Calculate casualties
        $attackerLosses = $this->calculateLosses($attackerUnits, $attackerSurvivors);
        $defenderLosses = $this->calculateLosses($defenderUnits, $defenderSurvivors);
        
        // Siege effects
        $wallAfter = $this->applyRamDamage($wallLevel, $attackerSurvivors['ram'] ?? 0, $worldConfig);
        $buildingAfter = $targetBuildingLevel;
        
        if ($attackerWon && $targetBuilding) {
            $buildingAfter = $this->applyCatapultDamage(
                $targetBuildingLevel,
                $attackerSurvivors['catapult'] ?? 0,
                $worldConfig
            );
        }
        
        return [
            'outcome' => $attackerWon ? 'attacker_win' : 'defender_hold',
            'luck' => $luck,
            'morale' => $morale,
            'ratio' => $ratio,
            'wall' => [
                'start' => $wallLevel,
                'end' => $wallAfter
            ],
            'building' => [
                'target' => $targetBuilding,
                'start' => $targetBuildingLevel,
                'end' => $buildingAfter
            ],
            'attacker' => [
                'sent' => $attackerUnits,
                'lost' => $attackerLosses,
                'survivors' => $attackerSurvivors
            ],
            'defender' => [
                'present' => $defenderUnits,
                'lost' => $defenderLosses,
                'survivors' => $defenderSurvivors
            ]
        ];
    }
    
    /**
     * Generate random luck factor
     */
    private function generateLuck(): float
    {
        return self::LUCK_MIN + (mt_rand() / mt_getrandmax()) * (self::LUCK_MAX - self::LUCK_MIN);
    }
    
    /**
     * Calculate morale based on point difference
     */
    private function calculateMorale(int $defenderPoints, int $attackerPoints): float
    {
        if ($attackerPoints <= 0) {
            return self::MORALE_MAX;
        }
        
        $morale = self::MORALE_BASE + ($defenderPoints / $attackerPoints);
        return max(self::MORALE_MIN, min(self::MORALE_MAX, $morale));
    }
    
    /**
     * Calculate total offensive power
     */
    private function calculateTotalOffense(array $units): float
    {
        $totalOff = 0;
        
        foreach ($units as $unitType => $count) {
            if ($count > 0 && isset($this->unitData[$unitType])) {
                $totalOff += $this->unitData[$unitType]['off'] * $count;
            }
        }
        
        return $totalOff;
    }
    
    /**
     * Calculate effective defense considering unit classes
     */
    private function calculateEffectiveDefense(array $defenderUnits, array $attackerUnits): float
    {
        // Count attacking units by class
        $attackerClasses = $this->countUnitsByClass($attackerUnits);
        
        // Calculate weighted defense
        $totalDef = 0;
        
        foreach ($defenderUnits as $unitType => $count) {
            if ($count > 0 && isset($this->unitData[$unitType])) {
                $unitDef = $this->unitData[$unitType]['def'];
                
                // Determine which defense value to use based on attacker composition
                $defValue = $this->getWeightedDefense($unitDef, $attackerClasses);
                $totalDef += $defValue * $count;
            }
        }
        
        return $totalDef;
    }
    
    /**
     * Count units by their class (infantry, cavalry, archer)
     */
    private function countUnitsByClass(array $units): array
    {
        $classes = ['infantry' => 0, 'cavalry' => 0, 'archer' => 0];
        
        foreach ($units as $unitType => $count) {
            if ($count > 0 && isset(self::UNIT_CLASSES[$unitType])) {
                $class = self::UNIT_CLASSES[$unitType];
                $classes[$class] += $count;
            }
        }
        
        return $classes;
    }
    
    /**
     * Get weighted defense value based on attacker composition
     */
    private function getWeightedDefense(array $defValues, array $attackerClasses): float
    {
        $totalAttackers = array_sum($attackerClasses);
        
        if ($totalAttackers === 0) {
            return $defValues['gen'];
        }
        
        $weightedDef = 0;
        
        // Weight defense by attacker class distribution
        if ($attackerClasses['infantry'] > 0) {
            $weight = $attackerClasses['infantry'] / $totalAttackers;
            $weightedDef += $defValues['gen'] * $weight;
        }
        
        if ($attackerClasses['cavalry'] > 0) {
            $weight = $attackerClasses['cavalry'] / $totalAttackers;
            $weightedDef += $defValues['cav'] * $weight;
        }
        
        if ($attackerClasses['archer'] > 0) {
            $weight = $attackerClasses['archer'] / $totalAttackers;
            $weightedDef += ($defValues['arc'] ?? $defValues['gen']) * $weight;
        }
        
        return $weightedDef;
    }
    
    /**
     * Calculate wall defense multiplier
     */
    private function calculateWallMultiplier(int $wallLevel): float
    {
        if ($wallLevel <= 0) {
            return 1.0;
        }
        
        // Stronger curve after level 10
        if ($wallLevel > self::WALL_LEVEL_THRESHOLD) {
            $baseMult = pow(self::WALL_BASE_MULTIPLIER, self::WALL_LEVEL_THRESHOLD);
            $additionalLevels = $wallLevel - self::WALL_LEVEL_THRESHOLD;
            $strongerMult = pow(1.05, $additionalLevels); // Stronger multiplier
            return $baseMult * $strongerMult;
        }
        
        return pow(self::WALL_BASE_MULTIPLIER, $wallLevel);
    }
    
    /**
     * Check if it's night time (if night bonus is enabled)
     */
    private function isNightTime(array $worldConfig): bool
    {
        $enabled = (bool)($worldConfig['night_bonus_enabled'] ?? false);
        if (!$enabled) {
            return false;
        }

        $hour = (int)date('H');
        $nightStart = $worldConfig['night_start_hour'] ?? $worldConfig['night_start'] ?? 22;
        $nightEnd = $worldConfig['night_end_hour'] ?? $worldConfig['night_end'] ?? 6;

        if ($nightStart > $nightEnd) {
            // Night spans midnight
            return $hour >= $nightStart || $hour < $nightEnd;
        }

        return $hour >= $nightStart && $hour < $nightEnd;
    }
    
    /**
     * Apply loss factor to units and return survivors
     */
    private function applyLosses(array $units, float $lossFactor): array
    {
        $survivors = [];
        
        foreach ($units as $unitType => $count) {
            $lost = (int)ceil($count * $lossFactor);
            $survivors[$unitType] = max(0, $count - $lost);
        }
        
        return $survivors;
    }
    
    /**
     * Calculate losses by comparing original and survivors
     */
    private function calculateLosses(array $original, array $survivors): array
    {
        $losses = [];
        
        foreach ($original as $unitType => $count) {
            $survivorCount = $survivors[$unitType] ?? 0;
            $losses[$unitType] = $count - $survivorCount;
        }
        
        return $losses;
    }
    
    /**
     * Apply ram damage to wall
     */
    private function applyRamDamage(int $wallLevel, int $survivingRams, array $worldConfig): int
    {
        if ($survivingRams <= 0 || $wallLevel <= 0) {
            return $wallLevel;
        }
        
        $worldSpeed = $worldConfig['speed'] ?? 1.0;
        $ramsPerLevel = $this->getRamsPerLevel($wallLevel, $worldSpeed);
        
        $wallDrop = (int)floor($survivingRams / $ramsPerLevel);
        
        return max(0, $wallLevel - $wallDrop);
    }
    
    /**
     * Calculate rams needed per wall level
     */
    private function getRamsPerLevel(int $wallLevel, float $worldSpeed): int
    {
        // Base formula: more rams needed for higher levels
        $base = 2 + ($wallLevel * 0.5);
        return max(1, (int)ceil($base / $worldSpeed));
    }
    
    /**
     * Apply catapult damage to building
     */
    private function applyCatapultDamage(int $buildingLevel, int $survivingCatapults, array $worldConfig): int
    {
        if ($survivingCatapults <= 0 || $buildingLevel <= 0) {
            return $buildingLevel;
        }
        
        $worldSpeed = $worldConfig['speed'] ?? 1.0;
        $catapultsPerLevel = $this->getCatapultsPerLevel($buildingLevel, $worldSpeed);
        
        $levelsDrop = (int)floor($survivingCatapults / $catapultsPerLevel);
        
        return max(0, $buildingLevel - $levelsDrop);
    }
    
    /**
     * Calculate catapults needed per building level
     */
    private function getCatapultsPerLevel(int $buildingLevel, float $worldSpeed): int
    {
        // Base formula: more catapults needed for higher levels
        $base = 8 + ($buildingLevel * 2);
        return max(1, (int)ceil($base / $worldSpeed));
    }
}
