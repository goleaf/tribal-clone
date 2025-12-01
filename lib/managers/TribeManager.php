<?php
declare(strict_types=1);

/**
 * Basic tribe/alliance operations.
 */
class TribeManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function createTribe(int $founderId, string $name, string $tag, string $description = ''): array
    {
        $name = trim($name);
        $tag = trim($tag);
        if ($name === '' || $tag === '') {
            return ['success' => false, 'message' => 'Name and tag are required.'];
        }

        $stmt = $this->conn->prepare("INSERT INTO tribes (name, tag, description, founder_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not create tribe.'];
        }
        $stmt->bind_param("sssi", $name, $tag, $description, $founderId);
        $ok = $stmt->execute();
        $tribeId = $stmt->insert_id;
        $stmt->close();

        if ($ok) {
            $this->addMember($tribeId, $founderId, 'leader');
            return ['success' => true, 'tribe_id' => $tribeId];
        }
        return ['success' => false, 'message' => 'Could not create tribe.'];
    }

    public function addMember(int $tribeId, int $userId, string $role = 'member'): bool
    {
        // Update user ally_id
        $stmtUser = $this->conn->prepare("UPDATE users SET ally_id = ? WHERE id = ?");
        if ($stmtUser) {
            $stmtUser->bind_param("ii", $tribeId, $userId);
            $stmtUser->execute();
            $stmtUser->close();
        }

        $stmt = $this->conn->prepare("INSERT INTO tribe_members (tribe_id, user_id, role, joined_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE role = VALUES(role)");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("iis", $tribeId, $userId, $role);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function removeMember(int $tribeId, int $userId): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM tribe_members WHERE tribe_id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $tribeId, $userId);
            $stmt->execute();
            $stmt->close();
        }
        $stmtUser = $this->conn->prepare("UPDATE users SET ally_id = NULL WHERE id = ? AND ally_id = ?");
        if ($stmtUser) {
            $stmtUser->bind_param("ii", $userId, $tribeId);
            $stmtUser->execute();
            $stmtUser->close();
        }
        return true;
    }

    public function invite(int $tribeId, int $invitedUserId, ?int $inviterId = null): bool
    {
        $stmt = $this->conn->prepare("
            INSERT INTO tribe_invitations (tribe_id, invited_user_id, inviter_id, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
            ON DUPLICATE KEY UPDATE status = 'pending', inviter_id = VALUES(inviter_id), created_at = NOW()
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("iii", $tribeId, $invitedUserId, $inviterId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function respondInvitation(int $tribeId, int $userId, string $response): bool
    {
        $allowed = ['accepted', 'declined', 'cancelled'];
        if (!in_array($response, $allowed, true)) {
            return false;
        }
        $stmt = $this->conn->prepare("
            UPDATE tribe_invitations 
            SET status = ?, responded_at = NOW() 
            WHERE tribe_id = ? AND invited_user_id = ? AND status = 'pending'
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("sii", $response, $tribeId, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && $response === 'accepted') {
            $this->addMember($tribeId, $userId);
        }
        return $ok;
    }

    public function getTribe(int $tribeId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM tribes WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function listMembers(int $tribeId): array
    {
        $stmt = $this->conn->prepare("
            SELECT tm.user_id, tm.role, u.username, u.points 
            FROM tribe_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.tribe_id = ?
            ORDER BY tm.role, u.username
        ");
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $members;
    }
}
