<?php
/**
 * CommandProcessor - Stateless validation/sorting/rate limiting helper.
 *
 * Responsibilities:
 * - Validate commands (structure, troop counts, min population).
 * - Deterministically sort commands (arrival -> sequence -> type priority -> ID).
 * - Enforce simple sliding-window rate limits (per player, per target).
 * - Tag fake attacks based on population threshold.
 */
class CommandProcessor
{
    private array $unitData;

    /**
     * @param array $unitData Unit definitions keyed by internal name (from units.json).
     */
    public function __construct(array $unitData)
    {
        $this->unitData = $unitData;
    }

    /**
     * Validate a command structure and minimum population.
     *
     * @param array $command Command payload: arrival_at (timestamp string), command_id, command_type, units (map internal_name=>count)
     * @param array $worldConfig Config with min_attack_population and fake_attack_threshold
     * @return array ['valid' => bool, 'error' => null|string]
     */
    public function validateCommand(array $command, array $worldConfig): array
    {
        if (empty($command['units']) || !is_array($command['units'])) {
            return ['valid' => false, 'error' => 'ERR_VALIDATION'];
        }
        foreach ($command['units'] as $type => $count) {
            if ($count < 0 || !isset($this->unitData[$type])) {
                return ['valid' => false, 'error' => 'ERR_VALIDATION'];
            }
        }
        $minPop = (int)($worldConfig['min_attack_population'] ?? 0);
        if (!$this->checkMinimumPopulation($command['units'], $minPop)) {
            return ['valid' => false, 'error' => 'ERR_MIN_POP'];
        }
        return ['valid' => true, 'error' => null];
    }

    /**
     * Deterministically sort commands: arrival_at -> sequence_number -> type priority -> command_id.
     *
     * @param array $commands List of associative command arrays.
     * @return array Sorted commands.
     */
    public function sortCommands(array $commands): array
    {
        $priority = [
            'support' => 0,
            'attack' => 1,
            'raid' => 2,
            'siege' => 3,
            'spy' => 4,
            'fake' => 5,
            'return' => 6,
        ];

        usort($commands, function ($a, $b) use ($priority) {
            $cmpArrival = strcmp((string)$a['arrival_at'], (string)$b['arrival_at']);
            if ($cmpArrival !== 0) {
                return $cmpArrival;
            }
            $seqA = $a['sequence_number'] ?? 0;
            $seqB = $b['sequence_number'] ?? 0;
            if ($seqA !== $seqB) {
                return $seqA <=> $seqB;
            }
            $typeA = $priority[$a['command_type'] ?? 'attack'] ?? PHP_INT_MAX;
            $typeB = $priority[$b['command_type'] ?? 'attack'] ?? PHP_INT_MAX;
            if ($typeA !== $typeB) {
                return $typeA <=> $typeB;
            }
            return ($a['command_id'] ?? 0) <=> ($b['command_id'] ?? 0);
        });

        return $commands;
    }

    /**
     * Enforce per-player and per-target rate limits using in-memory history buckets.
     *
     * @param int $playerId
     * @param int|null $targetId
     * @param int $now Unix timestamp
     * @param array &$history Mutable history ['player' => [playerId => [ts,...]], 'target' => ["player:target" => [ts,...]]]
     * @param array $worldConfig rate_limits => ['per_player' => int, 'per_target' => int, 'window_seconds' => int]
     * @return array ['allowed' => bool, 'retry_after' => int|null]
     */
    public function enforceRateLimits(int $playerId, ?int $targetId, int $now, array &$history, array $worldConfig): array
    {
        $limits = $worldConfig['rate_limits'] ?? ['per_player' => PHP_INT_MAX, 'per_target' => PHP_INT_MAX, 'window_seconds' => 60];
        $window = (int)($limits['window_seconds'] ?? 60);
        $perPlayer = (int)($limits['per_player'] ?? PHP_INT_MAX);
        $perTarget = (int)($limits['per_target'] ?? PHP_INT_MAX);

        // Cleanup old entries
        $history['player'][$playerId] = array_values(array_filter($history['player'][$playerId] ?? [], fn($ts) => ($now - $ts) <= $window));
        $key = $playerId . ':' . ($targetId ?? 'null');
        $history['target'][$key] = array_values(array_filter($history['target'][$key] ?? [], fn($ts) => ($now - $ts) <= $window));

        $playerCount = count($history['player'][$playerId]);
        $targetCount = count($history['target'][$key]);

        if ($playerCount >= $perPlayer) {
            $oldest = min($history['player'][$playerId]);
            return ['allowed' => false, 'retry_after' => max(1, $window - ($now - $oldest))];
        }
        if ($targetId !== null && $targetCount >= $perTarget) {
            $oldest = min($history['target'][$key]);
            return ['allowed' => false, 'retry_after' => max(1, $window - ($now - $oldest))];
        }

        // Record this command
        $history['player'][$playerId][] = $now;
        $history['target'][$key][] = $now;

        return ['allowed' => true, 'retry_after' => null];
    }

    /**
     * Check minimum population requirement.
     *
     * @param array $units map internal_name => count
     * @param int $minPop minimum population required
     */
    public function checkMinimumPopulation(array $units, int $minPop): bool
    {
        $pop = 0;
        foreach ($units as $type => $count) {
            $pop += ($this->unitData[$type]['pop'] ?? 0) * $count;
        }
        return $pop >= $minPop;
    }

    /**
     * Determine if a command should be tagged as fake based on population threshold.
     *
     * @param array $units
     * @param int $fakeThreshold population threshold
     */
    public function isFakeAttack(array $units, int $fakeThreshold): bool
    {
        $pop = 0;
        foreach ($units as $type => $count) {
            $pop += ($this->unitData[$type]['pop'] ?? 0) * $count;
        }
        return $pop < $fakeThreshold;
    }
}
