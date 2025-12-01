<?php
/**
 * SiegeHandler - Stateless siege mechanics helper.
 */
class SiegeHandler
{
    private const WALL_BASE_MULTIPLIER = 1.037;
    private const WALL_LEVEL_THRESHOLD = 10;
    private const WALL_STRONG_MULTIPLIER = 1.05;

    /**
     * Calculate wall defense multiplier.
     */
    public function calculateWallMultiplier(int $wallLevel): float
    {
        if ($wallLevel <= 0) {
            return 1.0;
        }
        if ($wallLevel > self::WALL_LEVEL_THRESHOLD) {
            $baseMult = pow(self::WALL_BASE_MULTIPLIER, self::WALL_LEVEL_THRESHOLD);
            $additional = $wallLevel - self::WALL_LEVEL_THRESHOLD;
            return $baseMult * pow(self::WALL_STRONG_MULTIPLIER, $additional);
        }
        return pow(self::WALL_BASE_MULTIPLIER, $wallLevel);
    }

    /**
     * Apply ram damage to wall level using design formula.
     */
    public function applyRamDamage(int $wallLevel, int $survivingRams, array $worldConfig): int
    {
        if ($wallLevel <= 0 || $survivingRams <= 0) {
            return max(0, $wallLevel);
        }
        $worldSpeed = (float)($worldConfig['speed'] ?? 1.0);
        $ramsPerLevel = max(1, (int)ceil((2 + $wallLevel * 0.5) / $worldSpeed));
        $drop = (int)floor($survivingRams / $ramsPerLevel);
        return max(0, $wallLevel - $drop);
    }

    /**
     * Apply catapult damage to a building level if attacker won.
     */
    public function applyCatapultDamage(int $buildingLevel, int $survivingCatapults, array $worldConfig, bool $attackerWon): int
    {
        if (!$attackerWon || $survivingCatapults <= 0 || $buildingLevel <= 0) {
            return max(0, $buildingLevel);
        }
        $worldSpeed = (float)($worldConfig['speed'] ?? 1.0);
        $catapultsPerLevel = max(1, (int)ceil((8 + $buildingLevel * 2) / $worldSpeed));
        $drop = (int)floor($survivingCatapults / $catapultsPerLevel);
        return max(0, $buildingLevel - $drop);
    }

    /**
     * Select a random building from a list (deterministic by key order to keep tests stable).
     */
    public function selectRandomBuilding(array $buildings): ?string
    {
        if (empty($buildings)) {
            return null;
        }
        $keys = array_keys($buildings);
        sort($keys);
        return $keys[0];
    }
}
