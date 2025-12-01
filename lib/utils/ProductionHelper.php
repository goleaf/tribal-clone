<?php
declare(strict_types=1);

/**
 * Production Helper - Reference implementation for resource production calculations
 * 
 * This class provides utility functions for calculating resource production
 * following the formula: prod(l) = base * growth^(l-1) * world_speed * building_speed
 * 
 * Constants tuned to Tribal-like pacing:
 * - Timber base=30, Clay base=30, Iron base=25
 * - Growth factor=1.163
 * - Levels capped at 30
 */
class ProductionHelper
{
    /**
     * Calculate production per hour for a resource building at a given level.
     * 
     * @param int $level Building level (1-30)
     * @param float $base Base production (30 for timber/clay, 25 for iron)
     * @param float $growth Growth factor (default 1.163)
     * @param float $worldSpeed World speed multiplier (default 1.0)
     * @param float $buildingSpeed Building speed multiplier (default 1.0)
     * @return float Production per hour
     */
    public static function calcProductionPerHour(
        int $level,
        float $base = 30.0,
        float $growth = 1.163,
        float $worldSpeed = 1.0,
        float $buildingSpeed = 1.0
    ): float {
        if ($level <= 0) {
            return 0.0;
        }
        
        // prod(l) = base * growth^(l-1) * world_speed * building_speed
        return $base * pow($growth, max($level, 1) - 1) * $worldSpeed * $buildingSpeed;
    }

    /**
     * Apply offline production to a village's resources.
     * 
     * @param array $village Village data with resources and building levels
     * @param int $now Current timestamp
     * @return array Updated village resources
     */
    public static function applyOfflineProduction(array $village, int $now): array
    {
        // Calculate elapsed time in hours
        $lastTickAt = strtotime($village['resources']['lastTickAt'] ?? 'now');
        $dtHours = ($now - $lastTickAt) / 3600.0;
        
        if ($dtHours <= 0) {
            return $village['resources'];
        }

        // Get world multipliers (fallback to 1.0 if not set)
        $worldSpeed = $village['world']['speed'] ?? 1.0;
        $resourceSpeed = $village['world']['resourceSpeed'] ?? 1.0;

        // Calculate production rates for each resource
        $timberRate = self::calcProductionPerHour(
            $village['buildings']['timber_camp_lvl'] ?? 0,
            30.0,
            1.163,
            $worldSpeed,
            $resourceSpeed
        );
        
        $clayRate = self::calcProductionPerHour(
            $village['buildings']['clay_pit_lvl'] ?? 0,
            30.0,
            1.163,
            $worldSpeed,
            $resourceSpeed
        );
        
        $ironRate = self::calcProductionPerHour(
            $village['buildings']['iron_mine_lvl'] ?? 0,
            25.0,
            1.163,
            $worldSpeed,
            $resourceSpeed
        );

        // Calculate warehouse capacity with 2% buffer
        $warehouseCap = $village['resources']['warehouseCap'] ?? 1000;
        $warehouseCapWithBuffer = $warehouseCap * 1.02;

        // Apply gains and clamp to warehouse capacity
        $nextWood = min(
            ($village['resources']['wood'] ?? 0) + ($timberRate * $dtHours),
            $warehouseCapWithBuffer
        );
        
        $nextClay = min(
            ($village['resources']['clay'] ?? 0) + ($clayRate * $dtHours),
            $warehouseCapWithBuffer
        );
        
        $nextIron = min(
            ($village['resources']['iron'] ?? 0) + ($ironRate * $dtHours),
            $warehouseCapWithBuffer
        );

        return [
            'wood' => $nextWood,
            'clay' => $nextClay,
            'iron' => $nextIron,
            'lastTickAt' => $now,
            'warehouseCap' => $warehouseCap
        ];
    }

    /**
     * Balance notes:
     * - Adjust base and growth to get your desired early/late-game curve
     * - Higher base speeds day 1, higher growth stretches late-game scaling
     * - Cap level at 30 unless your world rules differ
     * - The 2% warehouse buffer provides overflow tolerance before rounding down to display
     */
}
