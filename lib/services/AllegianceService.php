<?php
declare(strict_types=1);

/**
 * AllegianceService - encapsulates allegiance (loyalty/control) math for conquest.
 */
class AllegianceService
{
    private float $baseRegenPerHour;
    private float $wallReductionPerLevel;
    private int $floorAfterCapture;

    public function __construct(
        float $baseRegenPerHour = 2.0,
        float $wallReductionPerLevel = 0.02,
        int $floorAfterCapture = 25
    ) {
        $this->baseRegenPerHour = $baseRegenPerHour;
        $this->wallReductionPerLevel = $wallReductionPerLevel;
        $this->floorAfterCapture = $floorAfterCapture;
    }

    /**
     * Apply a standard bearer wave to current allegiance.
     *
     * @param int $current Current allegiance 0-100
     * @param int $survivingBearers Count of standard bearers alive
     * @param int $wallLevel Defender wall level
     * @param bool $attackerWon Whether the attacker won the battle
     * @param float|null $multiplier Optional global conquest multiplier
     * @param bool $antiSnipeActive If anti-snipe grace prevents capture
     * @return array [new_allegiance, captured(bool), drop(int), reason(string|null)]
     */
    public function applyWave(
        int $current,
        int $survivingBearers,
        int $wallLevel,
        bool $attackerWon,
        ?float $multiplier = null,
        bool $antiSnipeActive = false
    ): array {
        $current = $this->clamp($current);
        if (!$attackerWon || $survivingBearers <= 0) {
            return [$current, false, 0, 'no_bearer_or_loss'];
        }

        $mult = $multiplier ?? 1.0;
        $baseDropPerBearer = random_int(18, 28);
        $rawDrop = $baseDropPerBearer * $survivingBearers * $mult;
        $wallReduction = max(0.0, min(0.5, $wallLevel * $this->wallReductionPerLevel));
        $effectiveDrop = max(1, (int)round($rawDrop * (1 - $wallReduction)));

        $newAllegiance = max(0, $current - $effectiveDrop);
        $captured = !$antiSnipeActive && $newAllegiance <= 0;
        if ($captured) {
            $newAllegiance = max(0, $this->floorAfterCapture);
        }
        return [$newAllegiance, $captured, $effectiveDrop, null];
    }

    /**
     * Apply regeneration over elapsed seconds.
     */
    public function regen(int $current, int $elapsedSeconds, bool $paused = false): int
    {
        if ($paused || $elapsedSeconds <= 0) {
            return $this->clamp($current);
        }
        $perSecond = $this->baseRegenPerHour / 3600.0;
        $regen = $perSecond * $elapsedSeconds;
        return $this->clamp((int)round($current + $regen));
    }

    private function clamp(int $val): int
    {
        return max(0, min(100, $val));
    }
}
