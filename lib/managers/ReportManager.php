<?php
declare(strict_types=1);

/**
 * Report creation/retrieval helpers.
 */
class ReportManager
{
    private $conn;

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
