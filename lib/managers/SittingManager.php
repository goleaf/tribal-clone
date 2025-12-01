<?php
declare(strict_types=1);

/**
 * Account sitting manager: owners can authorize sitters for a limited time window.
 */
class SittingManager
{
    private mysqli $conn;
    private const MAX_DURATION_HOURS = 168; // 7 days

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a sitting assignment from owner to sitter (by user_id).
     */
    public function createSitting(int $ownerId, int $sitterId, ?int $durationHours = null): array
    {
        $durationHours = $durationHours !== null ? max(1, min(self::MAX_DURATION_HOURS, $durationHours)) : 24;
        $now = new DateTimeImmutable('now');
        $endsAt = $now->modify("+{$durationHours} hours")->format('Y-m-d H:i:s');
        $startsAt = $now->format('Y-m-d H:i:s');

        // Prevent duplicates; update if exists and not revoked
        $stmt = $this->conn->prepare("
            INSERT INTO account_sittings (owner_user_id, sitter_user_id, starts_at, ends_at, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE starts_at = VALUES(starts_at), ends_at = VALUES(ends_at), revoked_at = NULL
        ");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not prepare sitter grant.'];
        }
        $stmt->bind_param("iiss", $ownerId, $sitterId, $startsAt, $endsAt);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok
            ? ['success' => true, 'message' => 'Sitter added until ' . $endsAt]
            : ['success' => false, 'message' => 'Failed to add sitter.'];
    }

    public function revokeSitting(int $ownerId, int $sitterId): bool
    {
        $stmt = $this->conn->prepare("UPDATE account_sittings SET revoked_at = NOW() WHERE owner_user_id = ? AND sitter_user_id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ii", $ownerId, $sitterId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getActiveForOwner(int $ownerId): array
    {
        $stmt = $this->conn->prepare("
            SELECT s.*, u.username as sitter_username
            FROM account_sittings s
            JOIN users u ON u.id = s.sitter_user_id
            WHERE s.owner_user_id = ?
              AND s.revoked_at IS NULL
              AND s.starts_at <= NOW()
              AND s.ends_at >= NOW()
            ORDER BY s.ends_at ASC
        ");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("i", $ownerId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    public function getActiveForSitter(int $sitterId): array
    {
        $stmt = $this->conn->prepare("
            SELECT s.*, u.username as owner_username
            FROM account_sittings s
            JOIN users u ON u.id = s.owner_user_id
            WHERE s.sitter_user_id = ?
              AND s.revoked_at IS NULL
              AND s.starts_at <= NOW()
              AND s.ends_at >= NOW()
            ORDER BY s.ends_at ASC
        ");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("i", $sitterId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    /**
     * Check if sitter has an active assignment for owner.
     */
    public function isActive(int $ownerId, int $sitterId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM account_sittings
            WHERE owner_user_id = ? AND sitter_user_id = ?
              AND revoked_at IS NULL
              AND starts_at <= NOW()
              AND ends_at >= NOW()
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("ii", $ownerId, $sitterId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
