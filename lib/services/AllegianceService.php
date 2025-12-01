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
    private int $antiSnipeFloor;
    private int $antiSnipeSeconds;
    private float $maxRegenMultiplier;
    private int $regenPauseWindowMs;
    private float $abandonDecayPerHour;
    private float $shrineBonusPerLevel;
    private float $hallFlatPerLevel;
    private float $tribeRegenMult;

    public function __construct(
        float $baseRegenPerHour = null,
        float $wallReductionPerLevel = 0.02,
        int $floorAfterCapture = 25,
        int $antiSnipeFloor = 10,
        int $antiSnipeSeconds = 900,
        float $maxRegenMultiplier = null,
        int $regenPauseWindowMs = null,
        float $abandonDecayPerHour = null,
        float $shrineBonusPerLevel = null,
        float $hallFlatPerLevel = null,
        float $tribeRegenMult = null
    ) {
        $this->baseRegenPerHour = $baseRegenPerHour ?? (defined('ALLEG_REGEN_PER_HOUR') ? (float)ALLEG_REGEN_PER_HOUR : 2.0);
        $this->wallReductionPerLevel = $wallReductionPerLevel;
        $this->floorAfterCapture = $floorAfterCapture;
        $this->antiSnipeFloor = $antiSnipeFloor;
        $this->antiSnipeSeconds = $antiSnipeSeconds;
        $this->maxRegenMultiplier = $maxRegenMultiplier ?? (defined('ALLEG_MAX_REGEN_MULT') ? (float)ALLEG_MAX_REGEN_MULT : 1.75);
        $this->regenPauseWindowMs = $regenPauseWindowMs ?? (defined('ALLEG_REGEN_PAUSE_WINDOW_MS') ? (int)ALLEG_REGEN_PAUSE_WINDOW_MS : 5000);
        $this->abandonDecayPerHour = $abandonDecayPerHour ?? (defined('ALLEG_ABANDON_DECAY_PER_HOUR') ? (float)ALLEG_ABANDON_DECAY_PER_HOUR : 0.0);
        $this->shrineBonusPerLevel = $shrineBonusPerLevel ?? (defined('ALLEG_SHRINE_REGEN_BONUS_PER_LEVEL') ? (float)ALLEG_SHRINE_REGEN_BONUS_PER_LEVEL : 0.02);
        $this->hallFlatPerLevel = $hallFlatPerLevel ?? (defined('ALLEG_HALL_REGEN_FLAT_PER_LEVEL') ? (float)ALLEG_HALL_REGEN_FLAT_PER_LEVEL : 0.25);
        $this->tribeRegenMult = $tribeRegenMult ?? (defined('ALLEG_TRIBE_REGEN_MULT') ? (float)ALLEG_TRIBE_REGEN_MULT : 0.15);
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
        $floorApplied = null;
        if ($captured) {
            $newAllegiance = max(0, $this->floorAfterCapture);
            $floorApplied = $this->floorAfterCapture;
        } elseif ($antiSnipeActive && $newAllegiance < $this->antiSnipeFloor) {
            $floorApplied = $this->antiSnipeFloor;
            $newAllegiance = $this->antiSnipeFloor;
        }
        return [$newAllegiance, $captured, $effectiveDrop, $floorApplied];
    }

    /**
     * Apply regeneration over elapsed seconds.
     */
    public function regen(int $current, int $elapsedSeconds, bool $paused = false, array $context = []): int
    {
        return $this->doRegen($current, $elapsedSeconds, $paused, $context)[0];
    }

    /**
     * Get anti-snipe configuration (floor and duration).
     */
    public function getAntiSnipeSettings(): array
    {
        return [
            'floor' => $this->antiSnipeFloor,
            'duration_seconds' => $this->antiSnipeSeconds
        ];
    }

    /**
     * Full resolution helper: regen tick + wave application + anti-snipe metadata.
     */
    public function resolveWaveWithRegen(
        int $current,
        int $elapsedSeconds,
        int $survivingBearers,
        int $wallLevel,
        bool $attackerWon,
        bool $antiSnipeActive,
        ?float $multiplier = null,
        ?int $antiSnipeUntil = null,
        array $regenContext = []
    ): AllegianceResult {
        [$regenValue, $regenApplied, $regenReason] = $this->doRegen($current, $elapsedSeconds, $antiSnipeActive, $regenContext);
        [$afterDrop, $captured, $dropApplied, $floorApplied] = $this->applyWave(
            $regenValue,
            $survivingBearers,
            $wallLevel,
            $attackerWon,
            $multiplier,
            $antiSnipeActive
        );

        $nextTickAt = time();
        return new AllegianceResult(
            $afterDrop,
            $captured,
            $dropApplied,
            $regenApplied,
            $floorApplied,
            $nextTickAt,
            $antiSnipeActive,
            $antiSnipeUntil,
            $regenReason
        );
    }

    private function clamp(int $val): int
    {
        return max(0, min(100, $val));
    }

    private function doRegen(int $current, int $elapsedSeconds, bool $paused, array $context): array
    {
        $current = $this->clamp($current);
        if ($paused || $elapsedSeconds <= 0) {
            return [$current, 0, $paused ? 'paused' : null];
        }
        $hostileEtaMs = isset($context['hostile_eta_ms']) ? (int)$context['hostile_eta_ms'] : null;
        if ($hostileEtaMs !== null && $hostileEtaMs <= $this->regenPauseWindowMs) {
            return [$current, 0, 'hostile_eta'];
        }
        if (!empty($context['is_occupied']) || !empty($context['uptime_active'])) {
            return [$current, 0, 'occupied'];
        }

        $regenPerHour = $this->computeRegenPerHour($context);
        $perSecond = $regenPerHour / 3600.0;
        $regenAmount = $perSecond * $elapsedSeconds;

        $decayAmount = 0.0;
        $reason = null;
        $ownerInactiveHours = isset($context['owner_inactive_hours']) ? (int)$context['owner_inactive_hours'] : 0;
        $applyDecay = !empty($context['apply_decay']) || ($ownerInactiveHours >= 72 && empty($context['has_garrison']));
        if ($this->abandonDecayPerHour > 0 && $applyDecay) {
            $decayPerSecond = $this->abandonDecayPerHour / 3600.0;
            $decayAmount = $decayPerSecond * $elapsedSeconds;
            $reason = 'decay';
        }

        $net = $regenAmount - $decayAmount;
        $newValue = $this->clamp((int)round($current + $net));
        return [$newValue, (int)round($net), $reason];
    }

    private function computeRegenPerHour(array $context): float
    {
        $base = isset($context['base_regen_per_hour']) ? (float)$context['base_regen_per_hour'] : $this->baseRegenPerHour;
        $shrineLevel = isset($context['shrine_level']) ? (int)$context['shrine_level'] : 0;
        $hallLevel = isset($context['hall_level']) ? (int)$context['hall_level'] : 0;
        $tribeMult = isset($context['tribe_regen_mult']) ? (float)$context['tribe_regen_mult'] : $this->tribeRegenMult;

        $multiplier = 1.0 + max(0.0, $tribeMult) + max(0.0, $shrineLevel * $this->shrineBonusPerLevel);
        $multiplier = min($this->maxRegenMultiplier, max(0.0, $multiplier));
        $regen = $base * $multiplier;
        $regen += max(0, $hallLevel) * $this->hallFlatPerLevel;
        return max(0.0, $regen);
    }
}
