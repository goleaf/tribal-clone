<?php
/**
 * ConquestHandler - Stateless allegiance/conquest helper.
 */
class ConquestHandler
{
    /**
     * Reduce allegiance based on surviving conquest units if attacker won and cooldown not active.
     *
     * @return array ['new_allegiance' => int, 'dropped' => int, 'blocked' => bool, 'reason' => null|string]
     */
    public function reduceAllegiance(
        int $currentAllegiance,
        int $survivingConquestUnits,
        array $worldConfig,
        bool $attackerWon,
        bool $cooldownActive = false
    ): array {
        if (!$attackerWon) {
            return ['new_allegiance' => $currentAllegiance, 'dropped' => 0, 'blocked' => true, 'reason' => 'ERR_NO_WIN'];
        }
        if ($cooldownActive) {
            return ['new_allegiance' => $currentAllegiance, 'dropped' => 0, 'blocked' => true, 'reason' => 'ERR_CONQUEST_COOLDOWN'];
        }
        $dropPerUnit = (int)($worldConfig['allegiance_drop_per_noble'] ?? 25);
        $drop = $survivingConquestUnits * $dropPerUnit;
        $new = max(0, $currentAllegiance - $drop);

        return ['new_allegiance' => $new, 'dropped' => $drop, 'blocked' => false, 'reason' => null];
    }

    /**
     * Check if ownership should transfer (allegiance <= 0).
     */
    public function checkCaptureConditions(int $newAllegiance): bool
    {
        return $newAllegiance <= 0;
    }

    /**
     * Apply post-capture allegiance floor.
     */
    public function applyPostCaptureAllegiance(array $worldConfig): int
    {
        return (int)($worldConfig['post_capture_allegiance'] ?? 25);
    }

    /**
     * Placeholder transferOwnership hook; returns metadata for integration.
     */
    public function transferOwnership(int $villageId, int $newOwnerId): array
    {
        return ['village_id' => $villageId, 'new_owner_id' => $newOwnerId];
    }
}
