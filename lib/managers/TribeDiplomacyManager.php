<?php
declare(strict_types=1);

/**
 * TribeDiplomacyManager - stores tribe-to-tribe relationship state
 * and supports war declarations with a prep window.
 */
class TribeDiplomacyManager
{
    private $conn;

    private const STATES = ['neutral', 'nap', 'alliance', 'war', 'truce'];
    private const MIN_DURATION_HOURS = [
        'neutral' => 0,
        'nap' => 24 * 7,
        'alliance' => 24 * 14,
        'war' => 24,
        'truce' => 12,
    ];
    private const WAR_PREP_HOURS = 12;
    private const WAR_DECLARATION_COOLDOWN_HOURS = 24;
    private const STATE_CHANGE_COOLDOWN_HOURS = 6;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $isSQLite = is_object($this->conn) && method_exists($this->conn, 'getPdo');

        if ($isSQLite) {
            $this->conn->query("
                CREATE TABLE IF NOT EXISTS tribe_diplomacy (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tribe_a_id INTEGER NOT NULL,
                    tribe_b_id INTEGER NOT NULL,
                    state TEXT NOT NULL DEFAULT 'neutral',
                    pending_state TEXT NULL,
                    pending_at TEXT NULL,
                    active_from TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
                    min_duration_until TEXT NULL,
                    cooldown_until TEXT NULL,
                    reason TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
                    updated_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
                    UNIQUE(tribe_a_id, tribe_b_id)
                )
            ");
        } else {
            $this->conn->query("
                CREATE TABLE IF NOT EXISTS tribe_diplomacy (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tribe_a_id INT NOT NULL,
                    tribe_b_id INT NOT NULL,
                    state VARCHAR(16) NOT NULL DEFAULT 'neutral',
                    pending_state VARCHAR(16) NULL,
                    pending_at DATETIME NULL,
                    active_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    min_duration_until DATETIME NULL,
                    cooldown_until DATETIME NULL,
                    reason VARCHAR(255) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_pair (tribe_a_id, tribe_b_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        // Change log table (SQLite/MySQL compatible DDL)
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_diplomacy_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tribe_a_id INTEGER NOT NULL,
                tribe_b_id INTEGER NOT NULL,
                actor_user_id INTEGER NULL,
                from_state TEXT,
                to_state TEXT,
                reason TEXT NULL,
                created_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP)
            )
        ");
        $this->conn->query("CREATE INDEX IF NOT EXISTS idx_tribe_diplomacy_logs_pair ON tribe_diplomacy_logs(tribe_a_id, tribe_b_id)");
    }

    /**
     * Get the current relation between two tribes.
     */
    public function getRelation(int $tribeIdA, int $tribeIdB): ?array
    {
        [$a, $b] = $this->normalizePair($tribeIdA, $tribeIdB);
        if ($a === 0 || $b === 0 || $a === $b) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT * FROM tribe_diplomacy
            WHERE tribe_a_id = ? AND tribe_b_id = ?
            LIMIT 1
        ");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("ii", $a, $b);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $this->createNeutralRelation($a, $b);
            $stmt2 = $this->conn->prepare("
                SELECT * FROM tribe_diplomacy
                WHERE tribe_a_id = ? AND tribe_b_id = ?
                LIMIT 1
            ");
            if ($stmt2 === false) {
                return null;
            }
            $stmt2->bind_param("ii", $a, $b);
            $stmt2->execute();
            $row = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        }

        return $row;
    }

    /**
     * Declare war with a prep timer (default 12h). Unilateral.
     */
    public function declareWar(int $sourceTribeId, int $targetTribeId, ?int $startTimestamp = null, ?string $reason = null): array
    {
        [$a, $b] = $this->normalizePair($sourceTribeId, $targetTribeId);
        if ($a === 0 || $b === 0 || $a === $b) {
            return ['success' => false, 'message' => 'Cannot declare war on same tribe.'];
        }

        $relation = $this->getRelation($a, $b);
        if ($relation === null) {
            $this->createNeutralRelation($a, $b);
            $relation = $this->getRelation($a, $b);
        }
        $now = time();

        if (!empty($relation['cooldown_until']) && strtotime((string)$relation['cooldown_until']) > $now) {
            return ['success' => false, 'message' => 'Cannot declare war yet due to cooldown.'];
        }
        if ($relation['state'] === 'war' || $relation['pending_state'] === 'war') {
            return ['success' => false, 'message' => 'War already active or pending.'];
        }

        $startTs = $startTimestamp ?? ($now + self::WAR_PREP_HOURS * 3600);
        $startAt = date('Y-m-d H:i:s', $startTs);
        $cooldownUntil = date('Y-m-d H:i:s', $now + self::WAR_DECLARATION_COOLDOWN_HOURS * 3600);
        $reason = $reason ? substr($reason, 0, 255) : null;

        $stmt = $this->conn->prepare("
            UPDATE tribe_diplomacy
            SET pending_state = 'war',
                pending_at = ?,
                reason = ?,
                cooldown_until = ?
            WHERE tribe_a_id = ? AND tribe_b_id = ?
        ");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Failed to declare war (prepare).'];
        }
        $stmt->bind_param("sssii", $startAt, $reason, $cooldownUntil, $a, $b);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $this->logChange($a, $b, $relation['state'] ?? null, 'pending_war', $sourceTribeId, $reason);
            return ['success' => true, 'pending_start' => $startAt];
        }

        return ['success' => false, 'message' => 'Failed to declare war.'];
    }

    /**
     * Immediately set a relation state (admin/back-office or mutual agreement).
     */
    public function setState(int $tribeIdA, int $tribeIdB, string $state, ?string $reason = null, ?int $actorUserId = null): array
    {
        $state = strtolower($state);
        if (!in_array($state, self::STATES, true)) {
            return ['success' => false, 'message' => 'Invalid state.'];
        }

        [$a, $b] = $this->normalizePair($tribeIdA, $tribeIdB);
        if ($a === 0 || $b === 0 || $a === $b) {
            return ['success' => false, 'message' => 'Invalid tribe ids.'];
        }
        $this->createNeutralRelation($a, $b);

        $current = $this->getRelation($a, $b);
        $now = time();

        if ($current) {
            $minUntilTs = !empty($current['min_duration_until']) ? strtotime((string)$current['min_duration_until']) : 0;
            $cooldownTs = !empty($current['cooldown_until']) ? strtotime((string)$current['cooldown_until']) : 0;
            if ($state !== ($current['state'] ?? null)) {
                if ($minUntilTs > $now) {
                    return ['success' => false, 'message' => 'Current state minimum duration not met.'];
                }
                if ($cooldownTs > $now) {
                    return ['success' => false, 'message' => 'Diplomacy cooldown active.'];
                }
            }
            if (($current['state'] ?? null) === $state && empty($current['pending_state'])) {
                return ['success' => true, 'state' => $state, 'message' => 'State unchanged.'];
            }
        }

        $minHours = self::MIN_DURATION_HOURS[$state] ?? 0;
        $now = time();
        $minUntil = $minHours > 0 ? date('Y-m-d H:i:s', $now + ($minHours * 3600)) : null;
        $reason = $reason ? substr($reason, 0, 255) : null;
        $cooldownUntil = date('Y-m-d H:i:s', $now + self::STATE_CHANGE_COOLDOWN_HOURS * 3600);

        $stmt = $this->conn->prepare("
            UPDATE tribe_diplomacy
            SET state = ?, pending_state = NULL, pending_at = NULL,
                active_from = CURRENT_TIMESTAMP,
                min_duration_until = ?,
                cooldown_until = ?,
                reason = ?
            WHERE tribe_a_id = ? AND tribe_b_id = ?
        ");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Failed to set state (prepare).'];
        }
        $stmt->bind_param("ssssii", $state, $minUntil, $cooldownUntil, $reason, $a, $b);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && $current) {
            $this->logChange($a, $b, $current['state'] ?? null, $state, $actorUserId, $reason);
        }

        return $ok ? ['success' => true, 'state' => $state] : ['success' => false, 'message' => 'Failed to update state.'];
    }

    /**
     * Resolve pending war declarations whose prep window expired.
     */
    public function processPendingWars(): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare("
            SELECT id, tribe_a_id, tribe_b_id
            FROM tribe_diplomacy
            WHERE pending_state = 'war'
              AND pending_at IS NOT NULL
              AND pending_at <= ?
        ");
        $processed = [];
        if ($stmt === false) {
            return $processed;
        }
        $stmt->bind_param("s", $now);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        foreach ($rows as $row) {
            $minUntil = date('Y-m-d H:i:s', time() + (self::MIN_DURATION_HOURS['war'] * 3600));
            $update = $this->conn->prepare("
                UPDATE tribe_diplomacy
                SET state = 'war',
                    pending_state = NULL,
                    pending_at = NULL,
                    active_from = CURRENT_TIMESTAMP,
                    min_duration_until = ?
                WHERE id = ?
            ");
            if ($update) {
                $update->bind_param("si", $minUntil, $row['id']);
                if ($update->execute()) {
                    $processed[] = (int)$row['id'];
                    $this->logChange((int)$row['tribe_a_id'], (int)$row['tribe_b_id'], 'pending_war', 'war', null, null);
                }
                $update->close();
            }
        }

        return $processed;
    }

    /**
     * End an active war into a truce (enforces minimum truce duration and war cooldown).
     */
    public function endWarWithTruce(int $tribeIdA, int $tribeIdB, ?string $reason = null): array
    {
        [$a, $b] = $this->normalizePair($tribeIdA, $tribeIdB);
        if ($a === 0 || $b === 0 || $a === $b) {
            return ['success' => false, 'message' => 'Invalid tribe ids.'];
        }
        $relation = $this->getRelation($a, $b);
        if (!$relation || $relation['state'] !== 'war') {
            return ['success' => false, 'message' => 'No active war to end.'];
        }

        $now = time();
        $truceUntil = date('Y-m-d H:i:s', $now + (self::MIN_DURATION_HOURS['truce'] * 3600));
        $cooldownUntil = date('Y-m-d H:i:s', $now + (self::WAR_DECLARATION_COOLDOWN_HOURS * 3600));
        $reason = $reason ? substr($reason, 0, 255) : null;

        $stmt = $this->conn->prepare("
            UPDATE tribe_diplomacy
            SET state = 'truce',
                pending_state = NULL,
                pending_at = NULL,
                active_from = CURRENT_TIMESTAMP,
                min_duration_until = ?,
                cooldown_until = ?,
                reason = ?
            WHERE tribe_a_id = ? AND tribe_b_id = ?
        ");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Failed to end war (prepare).'];
        }
        $stmt->bind_param("sssii", $truceUntil, $cooldownUntil, $reason, $a, $b);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok ? ['success' => true, 'state' => 'truce', 'truce_until' => $truceUntil] : ['success' => false, 'message' => 'Failed to end war.'];
    }

    /**
     * Persist a state change for auditing/diagnostics.
     */
    private function logChange(int $tribeA, int $tribeB, ?string $fromState, ?string $toState, ?int $actorUserId, ?string $reason): void
    {
        [$a, $b] = $this->normalizePair($tribeA, $tribeB);
        $stmt = $this->conn->prepare("
            INSERT INTO tribe_diplomacy_logs (tribe_a_id, tribe_b_id, actor_user_id, from_state, to_state, reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        if ($stmt === false) {
            return;
        }
        $reason = $reason ? substr($reason, 0, 255) : null;
        $stmt->bind_param("iiisss", $a, $b, $actorUserId, $fromState, $toState, $reason);
        $stmt->execute();
        $stmt->close();
    }

    private function createNeutralRelation(int $tribeA, int $tribeB): void
    {
        if ($tribeA <= 0 || $tribeB <= 0 || $tribeA === $tribeB) {
            return;
        }

        $isSQLite = is_object($this->conn) && method_exists($this->conn, 'getPdo');
        $sql = $isSQLite
            ? "INSERT OR IGNORE INTO tribe_diplomacy (tribe_a_id, tribe_b_id, state) VALUES (?, ?, 'neutral')"
            : "INSERT IGNORE INTO tribe_diplomacy (tribe_a_id, tribe_b_id, state) VALUES (?, ?, 'neutral')";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param("ii", $tribeA, $tribeB);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Always store pair in sorted order to keep uniqueness consistent.
     */
    private function normalizePair(int $tribeA, int $tribeB): array
    {
        if ($tribeA <= 0 || $tribeB <= 0) {
            return [0, 0];
        }
        return ($tribeA < $tribeB) ? [$tribeA, $tribeB] : [$tribeB, $tribeA];
    }
}
