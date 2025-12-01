<?php
/**
 * PlunderCalculator - Stateless loot calculation helper.
 *
 * Responsibilities:
 * - Compute lootable resources after vault/hiding-place protection and optional caps/DR.
 * - Calculate total carrying capacity of surviving, plunder-capable units.
 * - Deterministically distribute loot across resources up to carry/cap limits.
 *
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
 */
class PlunderCalculator
{
    private const RESOURCES = ['wood', 'clay', 'iron'];
    private const DEFAULT_PRIORITY = ['wood', 'clay', 'iron'];
    private const MAX_VAULT_PERCENT = 100.0;
    private const MIN_VAULT_PERCENT = 0.0;
    private const DEFAULT_PLUNDER_MULTIPLIER = 1.0;

    /**
     * Siege and conquest payload units that should never contribute carry.
     */
    private const SIEGE_UNIT_INTERNALS = ['ram', 'battering_ram', 'catapult', 'trebuchet'];
    private const CONQUEST_UNIT_INTERNALS = ['noble', 'chieftain', 'senator', 'chief', 'envoy', 'standard_bearer'];

    private array $unitData;

    public function __construct(array $unitData)
    {
        $this->unitData = $unitData;
    }

    /**
     * Calculate lootable resources after applying protection, optional caps, and diminishing returns.
     *
     * @param array $resources          Raw defender resources ['wood' => int, 'clay' => int, 'iron' => int]
     * @param int   $hiddenPerResource  Fixed hiding-place protection per resource (>=0)
     * @param float $vaultPercent       Vault protection percentage (0-100)
     * @param array|null $plunderCap    Optional cap: ['absolute' => int] or ['percent' => float]
     * @param float $diminishingReturns Diminishing-returns multiplier applied before carry split (0-1)
     *
     * @return array{
     *   lootable: array<string,int>,    // after protection + DR + cap
     *   protected: array<string,int>,   // max of hiding place vs vault per resource
     *   available: array<string,int>,   // after protection, before DR/cap
     *   cap_applied: bool,
     *   cap_value: int|null,
     *   diminishing_returns: float
     * }
     */
    public function calculateAvailableLoot(
        array $resources,
        int $hiddenPerResource,
        float $vaultPercent,
        ?array $plunderCap = null,
        float $diminishingReturns = 1.0
    ): array {
        $cleanResources = $this->sanitizeResources($resources);
        $hidden = max(0, $hiddenPerResource);
        $vaultFactor = $this->clamp($vaultPercent, self::MIN_VAULT_PERCENT, self::MAX_VAULT_PERCENT) / 100.0;
        $dr = $this->clamp($diminishingReturns, 0.0, 1.0);

        $protected = [];
        $available = [];
        foreach (self::RESOURCES as $resource) {
            $vaultProtected = (int)ceil($cleanResources[$resource] * $vaultFactor);
            $protectedAmount = max($hidden, $vaultProtected);
            $protected[$resource] = $protectedAmount;
            $available[$resource] = max(0, $cleanResources[$resource] - $protectedAmount);
        }

        // Apply diminishing returns before any caps or carry limits.
        $lootable = [];
        foreach (self::RESOURCES as $resource) {
            $lootable[$resource] = (int)floor($available[$resource] * $dr);
        }

        $capValue = $this->resolveCap($plunderCap, $cleanResources);
        $capApplied = false;
        if ($capValue !== null) {
            $currentTotal = array_sum($lootable);
            if ($capValue < $currentTotal) {
                $lootable = $this->scaleLootToTarget($lootable, $capValue, self::DEFAULT_PRIORITY);
                $capApplied = true;
            }
        }

        return [
            'lootable' => $lootable,
            'protected' => $protected,
            'available' => $available,
            'cap_applied' => $capApplied,
            'cap_value' => $capValue,
            'diminishing_returns' => $dr
        ];
    }

    /**
     * Calculate total carry capacity for surviving, plunder-capable units.
     *
     * @param array $units Map of unit internal name => count
     * @param float $plunderMultiplier Optional carry modifier (e.g., raid bonus)
     */
    public function calculateCarryCapacity(array $units, float $plunderMultiplier = self::DEFAULT_PLUNDER_MULTIPLIER): int
    {
        $multiplier = $this->clamp($plunderMultiplier, 0.0, PHP_FLOAT_MAX);
        $total = 0;

        foreach ($units as $unitType => $count) {
            if ($count <= 0 || !isset($this->unitData[$unitType])) {
                continue;
            }

            if ($this->isNonPlunderUnit($unitType)) {
                continue;
            }

            $carry = (int)($this->unitData[$unitType]['carry'] ?? 0);
            if ($carry <= 0) {
                continue;
            }

            $total += $carry * (int)$count;
        }

        return (int)floor($total * $multiplier);
    }

    /**
     * Deterministically split loot up to the carry capacity.
     *
     * @param array $lootable       Lootable resources after protection/caps ['wood' => int, ...]
     * @param int   $carryCapacity  Total carry capacity of surviving units
     * @param array $priority       Resource order for remainder distribution
     *
     * @return array{
     *   loot: array<string,int>,
     *   carry_used: int,
     *   carry_unused: int
     * }
     */
    public function distributePlunder(array $lootable, int $carryCapacity, array $priority = self::DEFAULT_PRIORITY): array
    {
        $cleanLoot = $this->sanitizeResources($lootable);
        $capacity = max(0, $carryCapacity);
        $target = min($capacity, array_sum($cleanLoot));

        if ($target === array_sum($cleanLoot)) {
            $allocated = $cleanLoot;
        } else {
            $allocated = $this->scaleLootToTarget($cleanLoot, $target, $priority);
        }

        $carryUsed = array_sum($allocated);

        return [
            'loot' => $allocated,
            'carry_used' => $carryUsed,
            'carry_unused' => max(0, $capacity - $carryUsed)
        ];
    }

    /**
     * Resolve an optional loot cap to a concrete integer total.
     *
     * Supports:
     * - ['absolute' => int] total resource cap
     * - ['percent' => float] cap relative to stored resources (0-100)
     */
    private function resolveCap(?array $plunderCap, array $storedResources): ?int
    {
        if ($plunderCap === null) {
            return null;
        }

        if (array_key_exists('absolute', $plunderCap)) {
            return max(0, (int)$plunderCap['absolute']);
        }

        if (array_key_exists('percent', $plunderCap)) {
            $pct = $this->clamp((float)$plunderCap['percent'], 0.0, 100.0) / 100.0;
            return (int)floor(array_sum($storedResources) * $pct);
        }

        return null;
    }

    /**
     * Scale loot down to a deterministic target total while respecting per-resource ceilings.
     *
     * Uses proportional allocation then distributes any remainder by priority order to preserve determinism.
     */
    private function scaleLootToTarget(array $loot, int $targetTotal, array $priority): array
    {
        $currentTotal = array_sum($loot);
        if ($targetTotal >= $currentTotal || $currentTotal === 0) {
            return $loot;
        }

        $scaled = [];
        foreach ($priority as $resource) {
            $available = $loot[$resource] ?? 0;
            $share = $currentTotal > 0 ? ($available / $currentTotal) : 0;
            $scaled[$resource] = (int)floor($targetTotal * $share);
        }

        // Include any resources not listed in priority with zero to avoid undefined index warnings.
        foreach ($loot as $resource => $amount) {
            if (!array_key_exists($resource, $scaled)) {
                $scaled[$resource] = 0;
            }
        }

        // Distribute remaining capacity deterministically following the priority order.
        $allocated = array_sum($scaled);
        $remaining = $targetTotal - $allocated;
        while ($remaining > 0) {
            $progressed = false;
            foreach ($priority as $resource) {
                $available = ($loot[$resource] ?? 0) - ($scaled[$resource] ?? 0);
                if ($available <= 0) {
                    continue;
                }
                $scaled[$resource]++;
                $remaining--;
                $progressed = true;
                if ($remaining === 0) {
                    break;
                }
            }

            // If no further distribution is possible (all resources exhausted), break to avoid infinite loop.
            if (!$progressed) {
                break;
            }
        }

        return $scaled;
    }

    /**
     * Clamp a numeric value to a range.
     */
    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * Sanitize a resource array to ensure all expected keys exist and are non-negative ints.
     */
    private function sanitizeResources(array $resources): array
    {
        $clean = [];
        foreach (self::RESOURCES as $resource) {
            $clean[$resource] = max(0, (int)round($resources[$resource] ?? 0));
        }
        return $clean;
    }

    /**
     * Determine if a unit should be excluded from plunder carrying capacity.
     */
    private function isNonPlunderUnit(string $unitType): bool
    {
        return in_array($unitType, self::SIEGE_UNIT_INTERNALS, true)
            || in_array($unitType, self::CONQUEST_UNIT_INTERNALS, true);
    }
}
