<?php
/**
 * CombatTickProcessor - resolves all due attack commands in one pass.
 * This is a thin orchestrator that pulls pending rows from `attacks`
 * and delegates per-mission handling to BattleManager.
 */
if (!class_exists('BattleManager')) {
    require_once __DIR__ . '/BattleManager.php';
}

class CombatTickProcessor
{
    private $conn;
    private $battleManager;

    public function __construct(mysqli $conn, BattleManager $battleManager)
    {
        $this->conn = $conn;
        $this->battleManager = $battleManager;
    }

    /**
     * Process due attacks (arrival_time <= NOW()).
     *
     * @param int|null $limit Optional max number to process to avoid long ticks.
     * @return array List of results keyed by attack_id.
     */
    public function processDueAttacks(?int $limit = null): array
    {
        $results = [];

        $limitSql = $limit !== null ? " LIMIT " . (int)$limit : "";
        $query = "
            SELECT id
            FROM attacks
            WHERE is_completed = 0
              AND is_canceled = 0
              AND arrival_time <= NOW()
            ORDER BY arrival_time ASC
            {$limitSql}
        ";

        $pending = $this->conn->query($query);
        if ($pending === false) {
            return ['error' => 'Failed to fetch pending attacks.'];
        }

        while ($row = $pending->fetch_assoc()) {
            $attackId = (int)$row['id'];
            $results[$attackId] = $this->battleManager->processAttackArrival($attackId);
        }

        $pending->free();
        return $results;
    }
}
