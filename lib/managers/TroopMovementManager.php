<?php
declare(strict_types=1);

/**
 * Wraps troop movement helpers on top of BattleManager.
 */
class TroopMovementManager
{
    private BattleManager $battleManager;

    public function __construct(BattleManager $battleManager)
    {
        $this->battleManager = $battleManager;
    }

    /**
     * Returns incoming movements for a village.
     */
    public function getIncoming(int $villageId): array
    {
        return $this->battleManager->getIncomingAttacks($villageId);
    }

    /**
     * Returns outgoing movements for a village.
     */
    public function getOutgoing(int $villageId): array
    {
        return $this->battleManager->getOutgoingAttacks($villageId);
    }

    /**
     * Process arrivals for a given user (delegates to BattleManager).
     */
    public function processArrivalsForUser(int $userId): array
    {
        return $this->battleManager->processCompletedAttacks($userId);
    }
}
