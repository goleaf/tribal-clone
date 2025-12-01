<?php
declare(strict_types=1);

/**
 * TribeWarManager - basic tribe vs tribe war tracking.
 * Supports starting wars and accumulating scores from battles between member villages.
 */
class TribeWarManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        // Main wars table
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_wars (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                attacker_tribe_id INTEGER NOT NULL,
                defender_tribe_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'active', -- active, finished
                attacker_score INTEGER NOT NULL DEFAULT 0,
                defender_score INTEGER NOT NULL DEFAULT 0,
                target_score INTEGER NOT NULL DEFAULT 10,
                victory_condition TEXT NOT NULL DEFAULT 'first_to_score',
                winner_tribe_id INTEGER NULL,
                started_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
                ended_at TEXT NULL,
                FOREIGN KEY (attacker_tribe_id) REFERENCES tribes(id) ON DELETE CASCADE,
                FOREIGN KEY (defender_tribe_id) REFERENCES tribes(id) ON DELETE CASCADE
            )
        ");

        // Optional event log for wars
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_war_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                war_id INTEGER NOT NULL,
                attacker_tribe_id INTEGER NOT NULL,
                defender_tribe_id INTEGER NOT NULL,
                winner TEXT NOT NULL,
                attack_id INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
                FOREIGN KEY (war_id) REFERENCES tribe_wars(id) ON DELETE CASCADE
            )
        ");
    }

    /**
     * Start a new war between two tribes.
     */
    public function startWar(int $attackerTribeId, int $defenderTribeId, int $targetScore = 10, string $victoryCondition = 'first_to_score'): array
    {
        if ($attackerTribeId <= 0 || $defenderTribeId <= 0 || $attackerTribeId === $defenderTribeId) {
            return ['success' => false, 'message' => 'Invalid tribe ids.'];
        }

        // Prevent duplicate active war between the same pair
        $stmt = $this->conn->prepare("
            SELECT id FROM tribe_wars
            WHERE status = 'active'
              AND ((attacker_tribe_id = ? AND defender_tribe_id = ?) OR (attacker_tribe_id = ? AND defender_tribe_id = ?))
            LIMIT 1
        ");
        $stmt->bind_param("iiii", $attackerTribeId, $defenderTribeId, $defenderTribeId, $attackerTribeId);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            return ['success' => false, 'message' => 'An active war between these tribes already exists.'];
        }

        $stmtInsert = $this->conn->prepare("
            INSERT INTO tribe_wars (attacker_tribe_id, defender_tribe_id, target_score, victory_condition)
            VALUES (?, ?, ?, ?)
        ");
        if ($stmtInsert === false) {
            return ['success' => false, 'message' => 'Could not create war.'];
        }
        $stmtInsert->bind_param("iiis", $attackerTribeId, $defenderTribeId, $targetScore, $victoryCondition);
        $ok = $stmtInsert->execute();
        $warId = $stmtInsert->insert_id;
        $stmtInsert->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to start war.'];
        }

        return ['success' => true, 'war_id' => $warId];
    }

    /**
     * Record a battle result into any active war between the given tribes.
     */
    public function recordBattleResult(int $attackerTribeId, int $defenderTribeId, bool $attackerWon, ?int $attackId = null): void
    {
        if ($attackerTribeId <= 0 || $defenderTribeId <= 0 || $attackerTribeId === $defenderTribeId) {
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT * FROM tribe_wars
            WHERE status = 'active'
              AND ((attacker_tribe_id = ? AND defender_tribe_id = ?) OR (attacker_tribe_id = ? AND defender_tribe_id = ?))
            LIMIT 1
        ");
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param("iiii", $attackerTribeId, $defenderTribeId, $defenderTribeId, $attackerTribeId);
        $stmt->execute();
        $war = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$war) {
            return; // no active war between these tribes
        }

        $warId = (int)$war['id'];
        $attackerIsA = ($war['attacker_tribe_id'] == $attackerTribeId);

        // Update scores
        if ($attackerWon) {
            if ($attackerIsA) {
                $this->conn->query("UPDATE tribe_wars SET attacker_score = attacker_score + 1 WHERE id = {$warId}");
            } else {
                $this->conn->query("UPDATE tribe_wars SET defender_score = defender_score + 1 WHERE id = {$warId}");
            }
        } else {
            if ($attackerIsA) {
                $this->conn->query("UPDATE tribe_wars SET defender_score = defender_score + 1 WHERE id = {$warId}");
            } else {
                $this->conn->query("UPDATE tribe_wars SET attacker_score = attacker_score + 1 WHERE id = {$warId}");
            }
        }

        // Insert event log
        $winner = $attackerWon ? 'attacker' : 'defender';
        $stmtEvent = $this->conn->prepare("
            INSERT INTO tribe_war_events (war_id, attacker_tribe_id, defender_tribe_id, winner, attack_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        if ($stmtEvent) {
            $stmtEvent->bind_param("iiisi", $warId, $attackerTribeId, $defenderTribeId, $winner, $attackId);
            $stmtEvent->execute();
            $stmtEvent->close();
        }

        // Check victory condition
        $this->checkVictory($warId);
    }

    private function checkVictory(int $warId): void
    {
        $stmt = $this->conn->prepare("SELECT attacker_score, defender_score, target_score, attacker_tribe_id, defender_tribe_id, status FROM tribe_wars WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param("i", $warId);
        $stmt->execute();
        $war = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$war || $war['status'] !== 'active') {
            return;
        }

        $attackerScore = (int)$war['attacker_score'];
        $defenderScore = (int)$war['defender_score'];
        $target = (int)$war['target_score'];
        $winnerTribeId = null;
        if ($attackerScore >= $target || $defenderScore >= $target) {
            $winnerTribeId = ($attackerScore >= $target) ? (int)$war['attacker_tribe_id'] : (int)$war['defender_tribe_id'];
        }

        if ($winnerTribeId) {
            $stmtUpdate = $this->conn->prepare("
                UPDATE tribe_wars
                SET status = 'finished', winner_tribe_id = ?, ended_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            if ($stmtUpdate) {
                $stmtUpdate->bind_param("ii", $winnerTribeId, $warId);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        }
    }
}
