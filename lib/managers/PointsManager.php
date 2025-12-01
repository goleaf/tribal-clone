<?php
declare(strict_types=1);

class PointsManager
{
    private mysqli $conn;
    private bool $historyReady = false;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Calculates and updates points for a single village.
     */
    public function updateVillagePoints(int $villageId, bool $updateOwner = true): int
    {
        $points = $this->calculateVillagePoints($villageId);

        $stmt = $this->conn->prepare("UPDATE villages SET points = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $points, $villageId);
            $stmt->execute();
            $stmt->close();
        }

        if ($updateOwner) {
            $ownerStmt = $this->conn->prepare("SELECT user_id FROM villages WHERE id = ? LIMIT 1");
            if ($ownerStmt) {
                $ownerStmt->bind_param("i", $villageId);
                $ownerStmt->execute();
                $row = $ownerStmt->get_result()->fetch_assoc();
                $ownerStmt->close();
                if ($row && isset($row['user_id'])) {
                    $this->updatePlayerPoints((int)$row['user_id'], true);
                }
            }
        }

        return $points;
    }

    /**
     * Calculates points for a village using Σ(level^1.2) across all buildings.
     */
    public function calculateVillagePoints(int $villageId): int
    {
        $stmt = $this->conn->prepare("
            SELECT SUM(POWER(CAST(vb.level AS REAL), 1.2)) AS score
            FROM village_buildings vb
            WHERE vb.village_id = ?
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $score = $row && isset($row['score']) ? (float)$row['score'] : 0.0;
        return (int)round($score);
    }

    /**
     * Updates the player points based on all owned villages and refreshes tribe + history.
     */
    public function updatePlayerPoints(int $userId, bool $updateTribe = true): int
    {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(points), 0) AS total_points
            FROM villages
            WHERE user_id = ?
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $points = $row && isset($row['total_points']) ? (int)$row['total_points'] : 0;

        $update = $this->conn->prepare("UPDATE users SET points = ? WHERE id = ?");
        if ($update) {
            $update->bind_param("ii", $points, $userId);
            $update->execute();
            $update->close();
        }

        $this->recordPointsHistory($userId, $points);

        if ($updateTribe) {
            $tribeStmt = $this->conn->prepare("SELECT ally_id FROM users WHERE id = ? LIMIT 1");
            if ($tribeStmt) {
                $tribeStmt->bind_param("i", $userId);
                $tribeStmt->execute();
                $tribeRow = $tribeStmt->get_result()->fetch_assoc();
                $tribeStmt->close();
                if ($tribeRow && !empty($tribeRow['ally_id'])) {
                    $this->updateTribePoints((int)$tribeRow['ally_id']);
                }
            }
        }

        return $points;
    }

    /**
     * Updates tribe points based on the sum of member points.
     */
    public function updateTribePoints(int $tribeId): int
    {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(points), 0) AS total_points
            FROM users
            WHERE ally_id = ?
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $points = $row && isset($row['total_points']) ? (int)$row['total_points'] : 0;

        $update = $this->conn->prepare("UPDATE tribes SET points = ? WHERE id = ?");
        if ($update) {
            $update->bind_param("ii", $points, $tribeId);
            $update->execute();
            $update->close();
        }

        return $points;
    }

    /**
     * Records a daily snapshot for growth calculations.
     */
    public function recordPointsHistory(int $userId, int $points): void
    {
        $this->ensureHistoryTable();
        $today = date('Y-m-d');
        $sql = "INSERT OR REPLACE INTO player_points_history (user_id, recorded_on, points) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("isi", $userId, $today, $points);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Returns the growth delta for a user over the given number of days.
     */
    public function getGrowthDelta(int $userId, int $days): int
    {
        $baseline = $this->getPointsAtDate($userId, $days);
        $current = $this->getCurrentPoints($userId);
        if ($baseline === null) {
            return 0;
        }
        return max(0, $current - $baseline);
    }

    private function getCurrentPoints(int $userId): int
    {
        $stmt = $this->conn->prepare("SELECT points FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($row['points']) ? (int)$row['points'] : 0;
    }

    /**
     * Returns points snapshot at or before N days ago.
     */
    private function getPointsAtDate(int $userId, int $days): ?int
    {
        $this->ensureHistoryTable();
        $stmt = $this->conn->prepare("
            SELECT points
            FROM player_points_history
            WHERE user_id = ? AND recorded_on <= date('now', ?)
            ORDER BY recorded_on DESC
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }
        $modifier = sprintf('-%d day', $days);
        $stmt->bind_param("is", $userId, $modifier);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['points'] : null;
    }

    private function ensureHistoryTable(): void
    {
        if ($this->historyReady) {
            return;
        }
        $this->historyReady = true;
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS player_points_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                recorded_on TEXT NOT NULL,
                points INTEGER NOT NULL,
                UNIQUE(user_id, recorded_on)
            )
        ");
    }
}
