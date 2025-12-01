<?php
/**
 * ModifierApplier - Stateless component for applying combat modifiers
 * 
 * Handles all combat modifiers in the correct order:
 * 1. Overstack penalty (if enabled)
 * 2. Wall multiplier
 * 3. Environment modifiers (night, terrain, weather)
 * 4. Morale (applied to attacker offense)
 * 5. Luck (applied to attacker offense)
 * 
 * Requirements: 3.1, 3.2, 3.3, 7.1, 7.2, 7.3, 7.4, 8.1, 8.2, 8.3
 */
class ModifierApplier
{
    // Morale constants
    private const MORALE_MIN = 0.5;
    private const MORALE_MAX = 1.5;
    private const MORALE_BASE = 0.3;
    
    // Luck constants (default, can be overridden by world config)
    private const LUCK_MIN_DEFAULT = 0.75;
    private const LUCK_MAX_DEFAULT = 1.25;
    
    // Wall constants
    private const WALL_BASE_MULTIPLIER = 1.037;
    private const WALL_LEVEL_THRESHOLD = 10;
    private const WALL_STRONG_MULTIPLIER = 1.05;
    
    // Night bonus constants
    private const NIGHT_DEFENSE_MULTIPLIER_DEFAULT = 1.5;
    
    /**
     * Calculate morale modifier based on point difference
     * 
     * Formula: clamp(0.5, 1.5, 0.3 + defenderPoints / attackerPoints)
     * 
     * @param int $defenderPoints Defender's total points
     * @param int $attackerPoints Attacker's total points
     * @return float Morale value between 0.5 and 1.5
     */
    public function calculateMorale(int $defenderPoints, int $attackerPoints): float
    {
        // Prevent division by zero
        if ($attackerPoints <= 0) {
            return self::MORALE_MAX;
        }
        
        $morale = self::MORALE_BASE + ($defenderPoints / $attackerPoints);
        
        // Clamp to valid range
        return max(self::MORALE_MIN, min(self::MORALE_MAX, $morale));
    }
    
    /**
     * Generate random luck factor within configured bounds
     * 
     * @param array $worldConfig World configuration with luck_min and luck_max
     * @return float Random luck value within configured range
     */
    public function generateLuck(array $worldConfig = []): float
    {
        $luckMin = (float)($worldConfig['luck_min'] ?? self::LUCK_MIN_DEFAULT);
        $luckMax = (float)($worldConfig['luck_max'] ?? self::LUCK_MAX_DEFAULT);
        
        // Generate random value between min and max
        $randomFactor = mt_rand() / mt_getrandmax();
        return $luckMin + ($randomFactor * ($luckMax - $luckMin));
    }
    
    /**
     * Calculate wall defense multiplier
     * 
     * Formula:
     * - Levels 1-10: 1.037^wallLevel
     * - Levels 11+: 1.037^10 × 1.05^(wallLevel - 10)
     * 
     * @param int $wallLevel Current wall level
     * @return float Wall multiplier (1.0 if no wall)
     */
    public function calculateWallMultiplier(int $wallLevel): float
    {
        if ($wallLevel <= 0) {
            return 1.0;
        }
        
        // Two-tier formula: stronger curve after level 10
        if ($wallLevel > self::WALL_LEVEL_THRESHOLD) {
            $baseMult = pow(self::WALL_BASE_MULTIPLIER, self::WALL_LEVEL_THRESHOLD);
            $additionalLevels = $wallLevel - self::WALL_LEVEL_THRESHOLD;
            $strongerMult = pow(self::WALL_STRONG_MULTIPLIER, $additionalLevels);
            return $baseMult * $strongerMult;
        }
        
        return pow(self::WALL_BASE_MULTIPLIER, $wallLevel);
    }
    
    /**
     * Calculate overstack penalty when defending population exceeds threshold
     * 
     * Formula: max(minMult, 1 - penaltyRate × max(0, (defPop - threshold) / threshold))
     * 
     * @param int $defendingPopulation Total population of defending forces
     * @param array $worldConfig World configuration with overstack settings
     * @return float Penalty multiplier (1.0 if no penalty, lower if overstacked)
     */
    public function calculateOverstackPenalty(int $defendingPopulation, array $worldConfig): float
    {
        // Check if overstack is enabled
        $enabled = (bool)($worldConfig['overstack_enabled'] ?? false);
        if (!$enabled) {
            return 1.0;
        }
        
        $threshold = (int)($worldConfig['overstack_threshold'] ?? 30000);
        $penaltyRate = (float)($worldConfig['overstack_penalty_rate'] ?? 0.3);
        $minMultiplier = (float)($worldConfig['overstack_min_multiplier'] ?? 0.5);
        
        // No penalty if below threshold
        if ($defendingPopulation <= $threshold) {
            return 1.0;
        }
        
        // Calculate penalty
        $excessRatio = max(0, ($defendingPopulation - $threshold) / $threshold);
        $penalty = 1.0 - ($penaltyRate * $excessRatio);
        
        // Clamp to minimum multiplier
        return max($minMultiplier, $penalty);
    }
    
    /**
     * Apply environment modifiers (night, terrain, weather) to combat values
     * 
     * Modifiers are applied in order: night → terrain → weather
     * 
     * @param float $attackerOffense Base attacker offensive power
     * @param float $defenderDefense Base defender defensive power
     * @param array $worldConfig World configuration
     * @param DateTime|null $battleTime Time of battle (for night bonus)
     * @return array ['offense' => float, 'defense' => float]
     */
    public function applyEnvironmentModifiers(
        float $attackerOffense,
        float $defenderDefense,
        array $worldConfig,
        ?DateTime $battleTime = null
    ): array {
        $offense = $attackerOffense;
        $defense = $defenderDefense;
        
        // Apply night bonus to defense if enabled and it's night time
        if ($this->isNightTime($worldConfig, $battleTime)) {
            $nightMultiplier = (float)($worldConfig['night_defense_multiplier'] ?? self::NIGHT_DEFENSE_MULTIPLIER_DEFAULT);
            $defense *= $nightMultiplier;
        }
        
        // Apply terrain modifiers if enabled
        if ($worldConfig['terrain_enabled'] ?? false) {
            $terrainOffMult = (float)($worldConfig['terrain_attack_multiplier'] ?? 1.0);
            $terrainDefMult = (float)($worldConfig['terrain_defense_multiplier'] ?? 1.0);
            $offense *= $terrainOffMult;
            $defense *= $terrainDefMult;
        }
        
        // Apply weather modifiers if enabled
        if ($worldConfig['weather_enabled'] ?? false) {
            $weatherOffMult = (float)($worldConfig['weather_attack_multiplier'] ?? 1.0);
            $weatherDefMult = (float)($worldConfig['weather_defense_multiplier'] ?? 1.0);
            $offense *= $weatherOffMult;
            $defense *= $weatherDefMult;
        }
        
        return [
            'offense' => $offense,
            'defense' => $defense
        ];
    }
    
    /**
     * Check if it's currently night time based on world configuration
     * 
     * @param array $worldConfig World configuration with night settings
     * @param DateTime|null $battleTime Time to check (defaults to current time)
     * @return bool True if night bonus should apply
     */
    private function isNightTime(array $worldConfig, ?DateTime $battleTime = null): bool
    {
        $enabled = (bool)($worldConfig['night_bonus_enabled'] ?? false);
        if (!$enabled) {
            return false;
        }
        
        // Use provided time or current time
        $time = $battleTime ?? new DateTime();
        $hour = (int)$time->format('H');
        
        $nightStart = (int)($worldConfig['night_start_hour'] ?? 22);
        $nightEnd = (int)($worldConfig['night_end_hour'] ?? 6);
        
        // Handle night spanning midnight
        if ($nightStart > $nightEnd) {
            return $hour >= $nightStart || $hour < $nightEnd;
        }
        
        return $hour >= $nightStart && $hour < $nightEnd;
    }
    
    /**
     * Apply all modifiers in the correct order
     * 
     * Order: overstack → wall → environment → morale → luck
     * 
     * @param float $baseOffense Base attacker offensive power
     * @param float $baseDefense Base defender defensive power
     * @param int $wallLevel Defender's wall level
     * @param int $defendingPopulation Total defending population
     * @param int $defenderPoints Defender's total points
     * @param int $attackerPoints Attacker's total points
     * @param array $worldConfig World configuration
     * @param DateTime|null $battleTime Time of battle
     * @return array ['offense' => float, 'defense' => float, 'modifiers' => array]
     */
    public function applyAllModifiers(
        float $baseOffense,
        float $baseDefense,
        int $wallLevel,
        int $defendingPopulation,
        int $defenderPoints,
        int $attackerPoints,
        array $worldConfig,
        ?DateTime $battleTime = null
    ): array {
        $offense = $baseOffense;
        $defense = $baseDefense;
        $modifiers = [];
        
        // 1. Apply overstack penalty to defense
        $overstackPenalty = $this->calculateOverstackPenalty($defendingPopulation, $worldConfig);
        $defense *= $overstackPenalty;
        $modifiers['overstack_penalty'] = $overstackPenalty;
        
        // 2. Apply wall multiplier to defense
        $wallMultiplier = $this->calculateWallMultiplier($wallLevel);
        $defense *= $wallMultiplier;
        $modifiers['wall_multiplier'] = $wallMultiplier;
        
        // 3. Apply environment modifiers
        $envResult = $this->applyEnvironmentModifiers($offense, $defense, $worldConfig, $battleTime);
        $offense = $envResult['offense'];
        $defense = $envResult['defense'];
        
        // Track environment modifiers
        if ($this->isNightTime($worldConfig, $battleTime)) {
            $modifiers['night_bonus'] = (float)($worldConfig['night_defense_multiplier'] ?? self::NIGHT_DEFENSE_MULTIPLIER_DEFAULT);
        }
        if ($worldConfig['terrain_enabled'] ?? false) {
            $modifiers['terrain_attack'] = (float)($worldConfig['terrain_attack_multiplier'] ?? 1.0);
            $modifiers['terrain_defense'] = (float)($worldConfig['terrain_defense_multiplier'] ?? 1.0);
        }
        if ($worldConfig['weather_enabled'] ?? false) {
            $modifiers['weather_attack'] = (float)($worldConfig['weather_attack_multiplier'] ?? 1.0);
            $modifiers['weather_defense'] = (float)($worldConfig['weather_defense_multiplier'] ?? 1.0);
        }
        
        // 4. Apply morale to attacker offense
        $morale = $this->calculateMorale($defenderPoints, $attackerPoints);
        $offense *= $morale;
        $modifiers['morale'] = $morale;
        
        // 5. Apply luck to attacker offense
        $luck = $this->generateLuck($worldConfig);
        $offense *= $luck;
        $modifiers['luck'] = $luck;
        
        return [
            'offense' => $offense,
            'defense' => $defense,
            'modifiers' => $modifiers
        ];
    }
}
