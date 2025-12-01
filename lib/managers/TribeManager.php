<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

class TribeManager
{
    private const ALLOWED_ROLES = ['leader', 'baron', 'diplomat', 'recruiter', 'member'];

    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function createTribe(int $founderId, string $name, string $tag, string $description = '', string $internalText = ''): array
    {
        $name = trim($name);
        $tag = strtoupper(trim($tag));
        $description = trim($description);
        $internalText = trim($internalText);

        if (strlen($name) < 3 || strlen($name) > 64) {
            return ['success' => false, 'message' => 'Tribe name must be between 3 and 64 characters.'];
        }

        if (!preg_match('/^[A-Za-z0-9 _\\-]{3,64}$/', $name)) {
            return ['success' => false, 'message' => 'Tribe name may only contain letters, numbers, spaces, underscores, and hyphens.'];
        }

        if (strlen($tag) < 2 || strlen($tag) > 12) {
            return ['success' => false, 'message' => 'Tribe tag must be between 2 and 12 characters.'];
        }

        if (!preg_match('/^[A-Z0-9]+$/', $tag)) {
            return ['success' => false, 'message' => 'Tribe tag may only contain uppercase letters and numbers.'];
        }

        if ($this->getTribeForUser($founderId)) {
            return ['success' => false, 'message' => 'You are already a member of a tribe.'];
        }

        $check = $this->conn->prepare("SELECT id FROM tribes WHERE name = ? OR tag = ? LIMIT 1");
        if ($check === false) {
            error_log("TribeManager::createTribe prepare failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to create tribe right now.'];
        }
        $check->bind_param("ss", $name, $tag);
        $check->execute();
        $exists = $check->get_result();
        if ($exists && $exists->num_rows > 0) {
            $check->close();
            return ['success' => false, 'message' => 'A tribe with that name or tag already exists.'];
        }
        $check->close();

        $stmt = $this->conn->prepare("INSERT INTO tribes (name, tag, description, internal_text, founder_id) VALUES (?, ?, ?, ?, ?)");
        if ($stmt === false) {
            error_log("TribeManager::createTribe insert prepare failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to create tribe right now.'];
        }
        $stmt->bind_param("ssssi", $name, $tag, $description, $internalText, $founderId);
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to create tribe. Please try again.'];
        }

        $tribeId = $this->conn->insert_id ?? ($stmt->insert_id ?? null);
        $stmt->close();

        if (!$tribeId) {
            return ['success' => false, 'message' => 'Unable to determine new tribe id.'];
        }

        $addMember = $this->addMember($tribeId, $founderId, 'leader');
        if (!$addMember['success']) {
            return $addMember;
        }

        $this->setUserTribe($founderId, $tribeId);
        $this->recalculateTribePoints($tribeId);

        return ['success' => true, 'message' => 'Tribe created successfully.', 'tribe_id' => $tribeId];
    }

    public function getTribeForUser(int $userId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                t.*,
                (SELECT COUNT(*) FROM tribe_members tmc WHERE tmc.tribe_id = t.id) as member_count,
                tm.role, 
                tm.joined_at
            FROM tribe_members tm
            JOIN tribes t ON t.id = tm.tribe_id
            WHERE tm.user_id = ?
            LIMIT 1
        ");
        if ($stmt === false) {
            error_log("TribeManager::getTribeForUser prepare failed: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tribe = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $tribe ?: null;
    }

    public function getTribeById(int $tribeId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                t.*,
                (SELECT COUNT(*) FROM tribe_members tm WHERE tm.tribe_id = t.id) as member_count
            FROM tribes t
            WHERE t.id = ?
            LIMIT 1
        ");
        if ($stmt === false) {
            error_log("TribeManager::getTribeById prepare failed: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tribe = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $tribe ?: null;
    }

    public function getTribeMembers(int $tribeId): array
    {
        $stmt = $this->conn->prepare("
            SELECT tm.user_id, tm.role, tm.joined_at, u.username, u.points
            FROM tribe_members tm
            JOIN users u ON u.id = tm.user_id
            WHERE tm.tribe_id = ?
            ORDER BY tm.role = 'leader' DESC, u.username ASC
        ");
        if ($stmt === false) {
            error_log("TribeManager::getTribeMembers prepare failed: " . $this->conn->error);
            return [];
        }
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();

        return $members;
    }

    public function getTribeStats(int $tribeId): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(DISTINCT tm.user_id) as member_count,
                COUNT(DISTINCT v.id) as village_count,
                COALESCE(SUM(v.population), 0) as total_population
            FROM tribe_members tm
            LEFT JOIN villages v ON v.user_id = tm.user_id
            WHERE tm.tribe_id = ?
        ");
        if ($stmt === false) {
            error_log("TribeManager::getTribeStats prepare failed: " . $this->conn->error);
            return ['member_count' => 0, 'village_count' => 0, 'points' => 0];
        }
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : ['member_count' => 0, 'village_count' => 0, 'total_population' => 0];
        $stmt->close();

        $points = (int)($row['total_population'] ?? 0) * 10;

        return [
            'member_count' => (int)($row['member_count'] ?? 0),
            'village_count' => (int)($row['village_count'] ?? 0),
            'points' => $points
        ];
    }

    public function getInvitationsForUser(int $userId): array
    {
        $stmt = $this->conn->prepare("
            SELECT ti.*, t.name as tribe_name, t.tag as tribe_tag
            FROM tribe_invitations ti
            JOIN tribes t ON t.id = ti.tribe_id
            WHERE ti.invited_user_id = ? AND ti.status = 'pending'
            ORDER BY ti.created_at ASC
        ");
        if ($stmt === false) {
            error_log("TribeManager::getInvitationsForUser prepare failed: " . $this->conn->error);
            return [];
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $invites = [];
        while ($row = $result->fetch_assoc()) {
            $invites[] = $row;
        }
        $stmt->close();

        return $invites;
    }

    public function getInvitationsForTribe(int $tribeId): array
    {
        $stmt = $this->conn->prepare("
            SELECT ti.*, u.username AS invited_username
            FROM tribe_invitations ti
            JOIN users u ON u.id = ti.invited_user_id
            WHERE ti.tribe_id = ? AND ti.status = 'pending'
            ORDER BY ti.created_at ASC
        ");
        if ($stmt === false) {
            error_log("TribeManager::getInvitationsForTribe prepare failed: " . $this->conn->error);
            return [];
        }
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $invites = [];
        while ($row = $result->fetch_assoc()) {
            $invites[] = $row;
        }
        $stmt->close();
        return $invites;
    }

    public function inviteUser(int $tribeId, int $inviterId, string $targetUsername): array
    {
        $targetUsername = trim($targetUsername);
        if ($targetUsername === '') {
            return ['success' => false, 'message' => 'Please provide a player name to invite.'];
        }

        $membership = $this->getMembership($inviterId);
        if (!$membership || $membership['tribe_id'] !== $tribeId) {
            return ['success' => false, 'message' => 'You are not a member of that tribe.'];
        }
        if ($membership['role'] !== 'leader') {
            return ['success' => false, 'message' => 'Only tribe leaders can send invitations.'];
        }

        $userStmt = $this->conn->prepare("SELECT id, ally_id FROM users WHERE username = ? LIMIT 1");
        if ($userStmt === false) {
            error_log("TribeManager::inviteUser user lookup failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to send invite right now.'];
        }
        $userStmt->bind_param("s", $targetUsername);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $target = $userResult ? $userResult->fetch_assoc() : null;
        $userStmt->close();

        if (!$target) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        if ($this->getTribeForUser((int)$target['id'])) {
            return ['success' => false, 'message' => 'This player is already in a tribe.'];
        }

        $existing = $this->conn->prepare("SELECT id, status FROM tribe_invitations WHERE tribe_id = ? AND invited_user_id = ? LIMIT 1");
        if ($existing === false) {
            error_log("TribeManager::inviteUser invitation lookup failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to send invite right now.'];
        }
        $existing->bind_param("ii", $tribeId, $target['id']);
        $existing->execute();
        $res = $existing->get_result();
        $invite = $res ? $res->fetch_assoc() : null;
        $existing->close();

        if ($invite && $invite['status'] === 'pending') {
            return ['success' => false, 'message' => 'An invitation is already pending for this player.'];
        }

        if ($invite) {
            $update = $this->conn->prepare("UPDATE tribe_invitations SET status = 'pending', created_at = CURRENT_TIMESTAMP, responded_at = NULL, inviter_id = ? WHERE id = ?");
            if ($update === false) {
                error_log("TribeManager::inviteUser update invite failed: " . $this->conn->error);
                return ['success' => false, 'message' => 'Unable to refresh invitation.'];
            }
            $update->bind_param("ii", $inviterId, $invite['id']);
            $update->execute();
            $update->close();
        } else {
            $insert = $this->conn->prepare("INSERT INTO tribe_invitations (tribe_id, invited_user_id, inviter_id) VALUES (?, ?, ?)");
            if ($insert === false) {
                error_log("TribeManager::inviteUser insert invite failed: " . $this->conn->error);
                return ['success' => false, 'message' => 'Unable to send invitation.'];
            }
            $insert->bind_param("iii", $tribeId, $target['id'], $inviterId);
            if (!$insert->execute()) {
                $insert->close();
                return ['success' => false, 'message' => 'Unable to send invitation.'];
            }
            $insert->close();
        }

        return ['success' => true, 'message' => 'Invitation sent to ' . $targetUsername];
    }

    public function cancelInvitation(int $tribeId, int $actorUserId, int $inviteId): array
    {
        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || $membership['role'] !== 'leader') {
            return ['success' => false, 'message' => 'Only the tribe leader can cancel invitations.'];
        }

        $stmt = $this->conn->prepare("UPDATE tribe_invitations SET status = 'cancelled', responded_at = CURRENT_TIMESTAMP WHERE id = ? AND tribe_id = ? AND status = 'pending'");
        if ($stmt === false) {
            error_log("TribeManager::cancelInvitation prepare failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to cancel invitation.'];
        }
        $stmt->bind_param("ii", $inviteId, $tribeId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected <= 0) {
            return ['success' => false, 'message' => 'Invitation not found or already handled.'];
        }

        return ['success' => true, 'message' => 'Invitation cancelled.'];
    }

    public function respondToInvitation(int $inviteId, int $userId, string $decision): array
    {
        $decision = strtolower($decision);
        if (!in_array($decision, ['accept', 'decline'], true)) {
            return ['success' => false, 'message' => 'Invalid decision.'];
        }

        $stmt = $this->conn->prepare("
            SELECT ti.*, t.name as tribe_name
            FROM tribe_invitations ti
            JOIN tribes t ON t.id = ti.tribe_id
            WHERE ti.id = ? AND ti.invited_user_id = ? AND ti.status = 'pending'
            LIMIT 1
        ");
        if ($stmt === false) {
            error_log("TribeManager::respondToInvitation select failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to process invitation.'];
        }
        $stmt->bind_param("ii", $inviteId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $invite = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$invite) {
            return ['success' => false, 'message' => 'Invitation not found or already handled.'];
        }

        if ($decision === 'decline') {
            $update = $this->conn->prepare("UPDATE tribe_invitations SET status = 'declined', responded_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($update === false) {
                error_log("TribeManager::respondToInvitation decline failed: " . $this->conn->error);
                return ['success' => false, 'message' => 'Unable to decline invitation.'];
            }
            $update->bind_param("i", $inviteId);
            $update->execute();
            $update->close();
            return ['success' => true, 'message' => 'Invitation declined.'];
        }

        // Accept path
        if ($this->getTribeForUser($userId)) {
            return ['success' => false, 'message' => 'You have already joined a tribe.'];
        }

        $add = $this->addMember((int)$invite['tribe_id'], $userId, 'member');
        if (!$add['success']) {
            return $add;
        }

        $this->setUserTribe($userId, (int)$invite['tribe_id']);

        $update = $this->conn->prepare("UPDATE tribe_invitations SET status = 'accepted', responded_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($update !== false) {
            $update->bind_param("i", $inviteId);
            $update->execute();
            $update->close();
        }

        // Decline other pending invites for this user
        $cleanup = $this->conn->prepare("UPDATE tribe_invitations SET status = 'declined', responded_at = CURRENT_TIMESTAMP WHERE invited_user_id = ? AND status = 'pending' AND id != ?");
        if ($cleanup !== false) {
            $cleanup->bind_param("ii", $userId, $inviteId);
            $cleanup->execute();
            $cleanup->close();
        }

        $this->recalculateTribePoints((int)$invite['tribe_id']);

        return ['success' => true, 'message' => 'You have joined ' . $invite['tribe_name'] . '.'];
    }

    public function leaveTribe(int $userId): array
    {
        $membership = $this->getMembership($userId);
        if (!$membership) {
            return ['success' => false, 'message' => 'You are not part of a tribe.'];
        }

        $tribeId = $membership['tribe_id'];
        if ($membership['role'] === 'leader') {
            $memberCount = $this->getMemberCount($tribeId);
            if ($memberCount > 1) {
                return ['success' => false, 'message' => 'Leader cannot leave while other members remain. Disband or transfer leadership first.'];
            }
            return $this->disbandTribe($tribeId, $userId);
        }

        $stmt = $this->conn->prepare("DELETE FROM tribe_members WHERE user_id = ?");
        if ($stmt === false) {
            error_log("TribeManager::leaveTribe delete failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to leave tribe right now.'];
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $this->setUserTribe($userId, null);
        $this->recalculateTribePoints($tribeId);

        return ['success' => true, 'message' => 'You have left the tribe.'];
    }

    public function disbandTribe(int $tribeId, int $requesterId): array
    {
        $membership = $this->getMembership($requesterId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || $membership['role'] !== 'leader') {
            return ['success' => false, 'message' => 'Only the tribe leader can disband the tribe.'];
        }

        // Reset ally_id for all members
        $memberIds = $this->conn->prepare("SELECT user_id FROM tribe_members WHERE tribe_id = ?");
        if ($memberIds === false) {
            error_log("TribeManager::disbandTribe member fetch failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to disband tribe right now.'];
        }
        $memberIds->bind_param("i", $tribeId);
        $memberIds->execute();
        $res = $memberIds->get_result();
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['user_id'];
        }
        $memberIds->close();

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $sql = "UPDATE users SET ally_id = NULL WHERE id IN ($placeholders)";
            $updateUsers = $this->conn->prepare($sql);
            if ($updateUsers !== false) {
                $updateUsers->bind_param($types, ...$ids);
                $updateUsers->execute();
                $updateUsers->close();
            }
        }

        $delete = $this->conn->prepare("DELETE FROM tribes WHERE id = ?");
        if ($delete === false) {
            error_log("TribeManager::disbandTribe delete failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to disband tribe.'];
        }
        $delete->bind_param("i", $tribeId);
        $delete->execute();
        $delete->close();

        return ['success' => true, 'message' => 'Tribe has been disbanded.'];
    }

    public function recalculateTribePoints(int $tribeId): void
    {
        $stmt = $this->conn->prepare("
            SELECT 
                COALESCE(SUM(v.population), 0) AS total_population
            FROM tribe_members tm
            LEFT JOIN villages v ON v.user_id = tm.user_id
            WHERE tm.tribe_id = ?
        ");
        if ($stmt === false) {
            error_log("TribeManager::recalculateTribePoints prepare failed: " . $this->conn->error);
            return;
        }
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : ['total_population' => 0];
        $stmt->close();

        $points = (int)($row['total_population'] ?? 0) * 10;

        $update = $this->conn->prepare("UPDATE tribes SET points = ? WHERE id = ?");
        if ($update === false) {
            error_log("TribeManager::recalculateTribePoints update failed: " . $this->conn->error);
            return;
        }
        $update->bind_param("ii", $points, $tribeId);
        $update->execute();
        $update->close();
    }

    private function setUserTribe(int $userId, ?int $tribeId): void
    {
        if ($tribeId === null) {
            $stmt = $this->conn->prepare("UPDATE users SET ally_id = NULL WHERE id = ?");
            if ($stmt === false) {
                error_log("TribeManager::setUserTribe prepare failed: " . $this->conn->error);
                return;
            }
            $stmt->bind_param("i", $userId);
        } else {
            $stmt = $this->conn->prepare("UPDATE users SET ally_id = ? WHERE id = ?");
            if ($stmt === false) {
                error_log("TribeManager::setUserTribe prepare failed: " . $this->conn->error);
                return;
            }
            $stmt->bind_param("ii", $tribeId, $userId);
        }
        $stmt->execute();
        $stmt->close();
    }

    private function getMembership(int $userId): ?array
    {
        $stmt = $this->conn->prepare("SELECT tribe_id, role FROM tribe_members WHERE user_id = ? LIMIT 1");
        if ($stmt === false) {
            error_log("TribeManager::getMembership prepare failed: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }

    private function addMember(int $tribeId, int $userId, string $role = 'member'): array
    {
        $role = $this->sanitizeRole($role);

        $stmt = $this->conn->prepare("INSERT INTO tribe_members (tribe_id, user_id, role) VALUES (?, ?, ?)");
        if ($stmt === false) {
            error_log("TribeManager::addMember prepare failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to add member.'];
        }
        $stmt->bind_param("iis", $tribeId, $userId, $role);
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Unable to add member to the tribe.'];
        }
        $stmt->close();

        return ['success' => true];
    }

    private function getMemberCount(int $tribeId): int
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as cnt FROM tribe_members WHERE tribe_id = ?");
        if ($stmt === false) {
            error_log("TribeManager::getMemberCount prepare failed: " . $this->conn->error);
            return 0;
        }
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : ['cnt' => 0];
        $stmt->close();

        return (int)($row['cnt'] ?? 0);
    }

    private function sanitizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        return in_array($role, self::ALLOWED_ROLES, true) ? $role : 'member';
    }

    public function changeMemberRole(int $tribeId, int $actorUserId, int $targetUserId, string $newRole): array
    {
        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || $membership['role'] !== 'leader') {
            return ['success' => false, 'message' => 'Only the tribe leader can change roles.'];
        }

        $newRole = $this->sanitizeRole($newRole);
        if ($newRole === 'leader') {
            return ['success' => false, 'message' => 'There can be only one leader.'];
        }

        $target = $this->getMembership($targetUserId);
        if (!$target || $target['tribe_id'] !== $tribeId) {
            return ['success' => false, 'message' => 'Target is not in your tribe.'];
        }
        if ($target['role'] === 'leader') {
            return ['success' => false, 'message' => 'You cannot change the leader role.'];
        }

        $stmt = $this->conn->prepare("UPDATE tribe_members SET role = ? WHERE tribe_id = ? AND user_id = ?");
        if ($stmt === false) {
            error_log("TribeManager::changeMemberRole prepare failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to update role.'];
        }
        $stmt->bind_param("sii", $newRole, $tribeId, $targetUserId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Unable to update role.'];
        }

        return ['success' => true, 'message' => 'Role updated to ' . ucfirst($newRole) . '.'];
    }

    public function kickMember(int $tribeId, int $actorUserId, int $targetUserId): array
    {
        if ($actorUserId === $targetUserId) {
            return ['success' => false, 'message' => 'Use leave tribe instead.'];
        }

        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || $membership['role'] !== 'leader') {
            return ['success' => false, 'message' => 'Only the tribe leader can remove members.'];
        }

        $target = $this->getMembership($targetUserId);
        if (!$target || $target['tribe_id'] !== $tribeId) {
            return ['success' => false, 'message' => 'Target is not in your tribe.'];
        }
        if ($target['role'] === 'leader') {
            return ['success' => false, 'message' => 'Cannot remove the leader.'];
        }

        $stmt = $this->conn->prepare("DELETE FROM tribe_members WHERE tribe_id = ? AND user_id = ?");
        if ($stmt === false) {
            error_log("TribeManager::kickMember prepare failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to remove member.'];
        }
        $stmt->bind_param("ii", $tribeId, $targetUserId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Unable to remove member.'];
        }

        $this->clearUserTribe($targetUserId);
        $this->recalculateTribePoints($tribeId);
        return ['success' => true, 'message' => 'Member removed from tribe.'];
    }
}
