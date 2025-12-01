<?php
declare(strict_types=1);

/**
 * Report creation/retrieval helpers.
 */
class ReportManager
{
    private $conn;
    private array $reportColumnsCache = [];

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a generic report (reports table).
     */
    public function createReport(int $userId, string $type, ?int $relatedId = null): int
    {
        $stmt = $this->conn->prepare("INSERT INTO reports (user_id, report_type, created_at, related_id) VALUES (?, ?, NOW(), ?)");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("isi", $userId, $type, $relatedId);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return (int)$id;
    }

    /**
     * Backwards-compatible wrapper used by other managers.
     * Stores the report row and, when optional columns exist, persists title/payload too.
     */
    public function addReport(int $userId, string $type, string $title, array $details = [], ?int $relatedId = null): int
    {
        $reportId = $this->createReport($userId, $type, $relatedId);
        if ($reportId <= 0) {
            return 0;
        }

        // Some deployments add title/payload columns to reports; persist when available.
        if ($this->hasReportColumn('title')) {
            $stmt = $this->conn->prepare("UPDATE reports SET title = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $title, $reportId);
                $stmt->execute();
                $stmt->close();
            }
        }
        if ($this->hasReportColumn('payload_json')) {
            $payload = json_encode(['title' => $title, 'details' => $details], JSON_UNESCAPED_UNICODE);
            $stmt = $this->conn->prepare("UPDATE reports SET payload_json = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $payload, $reportId);
                $stmt->execute();
                $stmt->close();
            }
        } elseif (!empty($details)) {
            // When no payload column exists, log to debug for auditing rather than failing.
            error_log(sprintf('ReportManager addReport payload (report_id %d): %s', $reportId, json_encode($details)));
        }

        return $reportId;
    }

    private function hasReportColumn(string $column): bool
    {
        if (isset($this->reportColumnsCache[$column])) {
            return $this->reportColumnsCache[$column];
        }
        try {
            if ($this->conn instanceof SQLiteAdapter) {
                $res = $this->conn->query('PRAGMA table_info("reports")');
                if ($res instanceof SQLiteResult) {
                    foreach ($res->fetch_all() as $row) {
                        if (($row['name'] ?? '') === $column) {
                            return $this->reportColumnsCache[$column] = true;
                        }
                    }
                } elseif ($res) {
                    while ($row = $res->fetch_assoc()) {
                        if (($row['name'] ?? '') === $column) {
                            return $this->reportColumnsCache[$column] = true;
                        }
                    }
                }
            } else {
                $stmt = $this->conn->prepare("SHOW COLUMNS FROM reports LIKE ?");
                if ($stmt) {
                    $stmt->bind_param("s", $column);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $exists = $res && $res->num_rows > 0;
                    $stmt->close();
                    return $this->reportColumnsCache[$column] = $exists;
                }
            }
        } catch (Throwable $e) {
            error_log('ReportManager column check failed: ' . $e->getMessage());
        }
        return $this->reportColumnsCache[$column] = false;
    }

    /**
     * Fetch a single battle report visible to the user.
     */
    public function getBattleReport(int $reportId, int $userId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM battle_reports 
            WHERE id = ? AND (attacker_user_id = ? OR defender_user_id = ?)
            LIMIT 1
        ");
        if (!$stmt) return null;
        $stmt->bind_param("iii", $reportId, $userId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * List recent battle reports for a user.
     */
    public function listBattleReports(int $userId, int $limit = 20): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM battle_reports 
            WHERE attacker_user_id = ? OR defender_user_id = ?
            ORDER BY battle_time DESC
            LIMIT ?
        ");
        if (!$stmt) return [];
        $stmt->bind_param("iii", $userId, $userId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }
}
