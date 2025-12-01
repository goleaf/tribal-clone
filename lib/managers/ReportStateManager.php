<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

/**
 * Tracks per-user state for battle reports (read/starred flags).
 */
class ReportStateManager
{
    private $conn;
    private bool $tableEnsured = false;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Ensure the report_states table exists (SQLite/MySQL compatible).
     */
    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        if (!dbTableExists($this->conn, 'report_states')) {
            // Per-user state for battle_reports entries
            $createSql = "
                CREATE TABLE IF NOT EXISTS report_states (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    report_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    is_starred INTEGER NOT NULL DEFAULT 0,
                    is_read INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(report_id, user_id),
                    FOREIGN KEY (report_id) REFERENCES battle_reports(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ";
            $this->conn->query($createSql);
            // Basic indexes for lookups
            $this->conn->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_report_states_user_report ON report_states(report_id, user_id)");
            $this->conn->query("CREATE INDEX IF NOT EXISTS idx_report_states_user_read ON report_states(user_id, is_read)");
        }

        $this->tableEnsured = true;
    }

    /**
     * Get state for a single report/user pair.
     */
    public function getState(int $reportId, int $userId): array
    {
        $this->ensureTable();
        $stmt = $this->conn->prepare("
            SELECT is_starred, is_read
            FROM report_states
            WHERE report_id = ? AND user_id = ?
            LIMIT 1
        ");
        if ($stmt === false) {
            return ['is_starred' => 0, 'is_read' => 0];
        }

        $stmt->bind_param("ii", $reportId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return ['is_starred' => 0, 'is_read' => 0];
        }

        return [
            'is_starred' => (int)$row['is_starred'],
            'is_read' => (int)$row['is_read'],
        ];
    }

    /**
     * Mark a report as read for a user.
     */
    public function markRead(int $reportId, int $userId): bool
    {
        $this->ensureTable();

        $stmt = $this->conn->prepare("
            UPDATE report_states
            SET is_read = 1, updated_at = CURRENT_TIMESTAMP
            WHERE report_id = ? AND user_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $reportId, $userId);
            $stmt->execute();
            $updated = $stmt->affected_rows > 0;
            $stmt->close();
            if ($updated) {
                return true;
            }
        }

        // Insert if missing
        $stmtInsert = $this->conn->prepare("
            INSERT INTO report_states (report_id, user_id, is_starred, is_read, created_at, updated_at)
            VALUES (?, ?, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        if ($stmtInsert === false) {
            return false;
        }
        $stmtInsert->bind_param("ii", $reportId, $userId);
        $ok = $stmtInsert->execute();
        $stmtInsert->close();
        return (bool)$ok;
    }

    /**
     * Toggle the starred flag for a report.
     */
    public function setStarred(int $reportId, int $userId, bool $isStarred): bool
    {
        $this->ensureTable();
        $flag = $isStarred ? 1 : 0;

        $stmt = $this->conn->prepare("
            UPDATE report_states
            SET is_starred = ?, updated_at = CURRENT_TIMESTAMP
            WHERE report_id = ? AND user_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("iii", $flag, $reportId, $userId);
            $stmt->execute();
            $updated = $stmt->affected_rows > 0;
            $stmt->close();
            if ($updated) {
                return true;
            }
        }

        $stmtInsert = $this->conn->prepare("
            INSERT INTO report_states (report_id, user_id, is_starred, is_read, created_at, updated_at)
            VALUES (?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        if ($stmtInsert === false) {
            return false;
        }
        $stmtInsert->bind_param("iii", $reportId, $userId, $flag);
        $ok = $stmtInsert->execute();
        $stmtInsert->close();
        return (bool)$ok;
    }

    /**
     * Number of unread reports for a user (attacker or defender).
     */
    public function countUnreadForUser(int $userId): int
    {
        $this->ensureTable();
        $sql = "
            SELECT COUNT(*) AS unread_count
            FROM battle_reports br
            JOIN villages sv ON br.source_village_id = sv.id
            JOIN villages tv ON br.target_village_id = tv.id
            LEFT JOIN report_states rs ON rs.report_id = br.id AND rs.user_id = ?
            WHERE (sv.user_id = ? OR tv.user_id = ?)
              AND COALESCE(rs.is_read, 0) = 0
        ";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param("iii", $userId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ? (int)$row['unread_count'] : 0;
    }
}
