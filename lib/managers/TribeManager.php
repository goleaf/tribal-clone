<?php
declare(strict_types=1);

/**
 * Tribe/alliance operations with role permissions, diplomacy, and forum stubs.
 */
class TribeManager
{
    // Canonical roles exposed to the app.
    private const ROLES = ['leader', 'co_leader', 'officer', 'member'];
    // Backward-compatible aliases stored in legacy data.
    private const LEGACY_ROLE_ALIASES = [
        'baron' => 'co_leader',
        'diplomat' => 'officer',
        'recruiter' => 'officer',
        'moderator' => 'officer',
    ];
    // Values persisted to legacy DB enum columns.
    private const ROLE_DB_MAP = [
        'leader' => 'leader',
        'co_leader' => 'baron',
        'officer' => 'diplomat',
        'member' => 'member',
    ];
    // Capability matrix for tribe roles.
    private const PERMISSIONS = [
        'invite' => ['leader', 'co_leader', 'officer'],
        'diplomacy' => ['leader', 'co_leader'],
        'forum' => ['leader', 'co_leader', 'officer', 'member'],
        'forum_admin' => ['leader', 'co_leader'],
        'manage_roles' => ['leader', 'co_leader'],
    ];

    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    private function canonicalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        if (isset(self::LEGACY_ROLE_ALIASES[$role])) {
            return self::LEGACY_ROLE_ALIASES[$role];
        }
        return in_array($role, self::ROLES, true) ? $role : 'member';
    }

    private function encodeRoleForDb(string $canonical): string
    {
        $canonical = $this->canonicalizeRole($canonical);
        return self::ROLE_DB_MAP[$canonical] ?? 'member';
    }

    private function ensureExtrasTables(): void
    {
        // Best-effort creation; errors are logged by SQLiteAdapter if any.
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_diplomacy (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tribe_id INTEGER NOT NULL,
                target_tribe_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(tribe_id, target_tribe_id)
            )
        ");

        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_forum_threads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tribe_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                author_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_forum_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                thread_id INTEGER NOT NULL,
                author_id INTEGER NOT NULL,
                body TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tribe_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                message TEXT DEFAULT '',
                status TEXT NOT NULL DEFAULT 'pending',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                responded_at TEXT DEFAULT NULL,
                UNIQUE(tribe_id, user_id)
            )
        ");

        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tribe_id INTEGER NOT NULL,
                actor_user_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                meta TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function roleHasPermission(string $role, string $permission): bool
    {
        $role = $this->canonicalizeRole($role);
        $allowed = self::PERMISSIONS[$permission] ?? [];
        return in_array($role, $allowed, true);
    }

    private function upsertDiplomacy(int $tribeId, int $targetTribeId, string $status, int $actorUserId): bool
    {
        $this->ensureExtrasTables();

        $check = $this->conn->prepare("SELECT id FROM tribe_diplomacy WHERE tribe_id = ? AND target_tribe_id = ? LIMIT 1");
        if ($check === false) {
            return false;
        }
        $check->bind_param("ii", $tribeId, $targetTribeId);
        $check->execute();
        $res = $check->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $check->close();

        if ($row) {
            $stmt = $this->conn->prepare("UPDATE tribe_diplomacy SET status = ?, created_by = ?, created_at = CURRENT_TIMESTAMP WHERE tribe_id = ? AND target_tribe_id = ?");
            if ($stmt === false) {
                return false;
            }
            $stmt->bind_param("siii", $status, $actorUserId, $tribeId, $targetTribeId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        }

        $stmt = $this->conn->prepare("INSERT INTO tribe_diplomacy (tribe_id, target_tribe_id, status, created_by) VALUES (?, ?, ?, ?)");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param("iisi", $tribeId, $targetTribeId, $status, $actorUserId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
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
        if (strlen($tag) < 2 || strlen($tag) > 12 || !preg_match('/^[A-Z0-9]+$/', $tag)) {
            return ['success' => false, 'message' => 'Tribe tag must be 2-12 chars, uppercase letters/numbers only.'];
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

        if ($tribe) {
            $tribe['role'] = $this->canonicalizeRole($tribe['role'] ?? 'member');
        }

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

    public function getTribeByTag(string $tag): ?array
    {
        $tag = strtoupper(trim($tag));
        if ($tag === '') {
            return null;
        }
        $stmt = $this->conn->prepare("SELECT * FROM tribes WHERE tag = ? LIMIT 1");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("s", $tag);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
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
            $row['role'] = $this->canonicalizeRole($row['role'] ?? 'member');
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
        if (!$this->roleHasPermission($membership['role'], 'invite')) {
            return ['success' => false, 'message' => 'You do not have permission to send invitations.'];
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

        $this->logActivity($tribeId, $inviterId, 'invite_sent', ['target_username' => $targetUsername]);
        return ['success' => true, 'message' => 'Invitation sent to ' . $targetUsername];
    }

    public function cancelInvitation(int $tribeId, int $actorUserId, int $inviteId): array
    {
        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || !$this->roleHasPermission($membership['role'], 'invite')) {
            return ['success' => false, 'message' => 'You do not have permission to cancel invitations.'];
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
            $this->logActivity((int)$invite['tribe_id'], $userId, 'invite_declined', ['invite_id' => $inviteId]);
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

        $this->logActivity((int)$invite['tribe_id'], $userId, 'invite_accepted', ['invite_id' => $inviteId]);
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

    public function changeMemberRole(int $tribeId, int $actorUserId, int $targetUserId, string $newRole): array
    {
        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || !$this->roleHasPermission($membership['role'], 'manage_roles')) {
            return ['success' => false, 'message' => 'You do not have permission to change roles.'];
        }

        $newRole = $this->canonicalizeRole($newRole);
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

        $dbRole = $this->encodeRoleForDb($newRole);
        $stmt = $this->conn->prepare("UPDATE tribe_members SET role = ? WHERE tribe_id = ? AND user_id = ?");
        if ($stmt === false) {
            error_log("TribeManager::changeMemberRole prepare failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to update role.'];
        }
        $stmt->bind_param("sii", $dbRole, $tribeId, $targetUserId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Unable to update role.'];
        }

        return ['success' => true, 'message' => 'Role updated to ' . ucfirst(str_replace('_', ' ', $newRole)) . '.'];
    }

    public function kickMember(int $tribeId, int $actorUserId, int $targetUserId): array
    {
        if ($actorUserId === $targetUserId) {
            return ['success' => false, 'message' => 'Use leave tribe instead.'];
        }

        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || !$this->roleHasPermission($membership['role'], 'manage_roles')) {
            return ['success' => false, 'message' => 'You do not have permission to remove members.'];
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

    private function clearUserTribe(int $userId): void
    {
        $stmt = $this->conn->prepare("UPDATE users SET ally_id = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function getMembership(int $userId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT tm.tribe_id, tm.role, t.world_id 
            FROM tribe_members tm 
            JOIN tribes t ON tm.tribe_id = t.id
            WHERE tm.user_id = ?
            LIMIT 1
        ");
        if ($stmt === false) {
            error_log("TribeManager::getMembership prepare failed: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $row['role'] = $this->canonicalizeRole($row['role'] ?? 'member');
        }

        return $row ?: null;
    }

    public function getMembershipPublic(int $userId): ?array
    {
        return $this->getMembership($userId);
    }

    private function addMemberInternal(int $tribeId, int $userId, string $role): array
    {
        // Enforce per-world member limit if set
        $worldManager = class_exists('WorldManager') ? new WorldManager($this->conn) : null;
        $limit = $worldManager ? $worldManager->getTribeLimit() : null;
        if ($limit !== null && $limit > 0) {
            $stmtCount = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM tribe_members WHERE tribe_id = ?");
            if ($stmtCount) {
                $stmtCount->bind_param("i", $tribeId);
                $stmtCount->execute();
                $row = $stmtCount->get_result()->fetch_assoc();
                $stmtCount->close();
                if (($row['cnt'] ?? 0) >= $limit) {
                    return ['success' => false, 'message' => 'Tribe member limit reached for this world.'];
                }
            }
        }

        // Update user ally_id
        $stmtUser = $this->conn->prepare("UPDATE users SET ally_id = ? WHERE id = ?");
        if ($stmtUser) {
            $stmtUser->bind_param("ii", $tribeId, $userId);
            $stmtUser->execute();
            $stmtUser->close();
        }

        $dbRole = $this->encodeRoleForDb($role);
        // Try update first
        $stmtUpdate = $this->conn->prepare("UPDATE tribe_members SET role = ? WHERE tribe_id = ? AND user_id = ?");
        if ($stmtUpdate) {
            $stmtUpdate->bind_param("sii", $dbRole, $tribeId, $userId);
            $stmtUpdate->execute();
            $affected = $stmtUpdate->affected_rows;
            $stmtUpdate->close();
            if ($affected > 0) {
                return ['success' => true];
            }
        }

        $stmt = $this->conn->prepare("INSERT INTO tribe_members (tribe_id, user_id, role, joined_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Unable to add member.'];
        }
        $stmt->bind_param("iis", $tribeId, $userId, $dbRole);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok ? ['success' => true] : ['success' => false, 'message' => 'Unable to add member.'];
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
        return $this->canonicalizeRole($role);
    }

    public function addMember(int $tribeId, int $userId, string $role = 'member'): array
    {
        return $this->addMemberInternal($tribeId, $userId, $role);
    }

    // Diplomacy
    public function setDiplomacyStatus(int $tribeId, int $actorUserId, int $targetTribeId, string $status): array
    {
        $status = strtolower($status);
        if (!in_array($status, ['nap', 'ally', 'enemy'], true)) {
            return ['success' => false, 'message' => 'Invalid diplomacy status.'];
        }
        if ($tribeId === $targetTribeId) {
            return ['success' => false, 'message' => 'Cannot set diplomacy with your own tribe.'];
        }

        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || !$this->roleHasPermission($membership['role'], 'diplomacy')) {
            return ['success' => false, 'message' => 'You do not have permission to manage diplomacy.'];
        }

        $ok1 = $this->upsertDiplomacy($tribeId, $targetTribeId, $status, $actorUserId);
        $ok2 = $this->upsertDiplomacy($targetTribeId, $tribeId, $status, $actorUserId);

        return ($ok1 && $ok2) ? ['success' => true, 'message' => 'Diplomacy updated.'] : ['success' => false, 'message' => 'Unable to update diplomacy.'];
    }

    public function getDiplomacyRelations(int $tribeId): array
    {
        $this->ensureExtrasTables();
        $stmt = $this->conn->prepare("
            SELECT td.target_tribe_id, td.status, td.created_at, t.name, t.tag
            FROM tribe_diplomacy td
            LEFT JOIN tribes t ON t.id = td.target_tribe_id
            WHERE td.tribe_id = ?
        ");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $relations = [];
        while ($row = $res->fetch_assoc()) {
            $relations[] = $row;
        }
        $stmt->close();
        return $relations;
    }

    // Tribe forum (simple private board)
    public function createThread(int $tribeId, int $authorId, string $title, string $body): array
    {
        $this->ensureExtrasTables();
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || $body === '') {
            return ['success' => false, 'message' => 'Title and body are required.'];
        }

        $membership = $this->getMembership($authorId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || !$this->roleHasPermission($membership['role'], 'forum')) {
            return ['success' => false, 'message' => 'You do not have permission to post in tribe forum.'];
        }

        $stmt = $this->conn->prepare("INSERT INTO tribe_forum_threads (tribe_id, title, author_id) VALUES (?, ?, ?)");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Unable to create thread.'];
        }
        $stmt->bind_param("isi", $tribeId, $title, $authorId);
        $ok = $stmt->execute();
        $threadId = $stmt->insert_id;
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Unable to create thread.'];
        }

        $postResult = $this->addPost($tribeId, $threadId, $authorId, $body);
        if (!$postResult['success']) {
            return $postResult;
        }

        return ['success' => true, 'thread_id' => $threadId];
    }

    public function addPost(int $tribeId, int $threadId, int $authorId, string $body): array
    {
        $this->ensureExtrasTables();
        $body = trim($body);
        if ($body === '') {
            return ['success' => false, 'message' => 'Post body is required.'];
        }

        $membership = $this->getMembership($authorId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || !$this->roleHasPermission($membership['role'], 'forum')) {
            return ['success' => false, 'message' => 'You do not have permission to post.'];
        }

        // Ensure thread belongs to tribe
        $check = $this->conn->prepare("SELECT tribe_id FROM tribe_forum_threads WHERE id = ? LIMIT 1");
        if ($check === false) {
            return ['success' => false, 'message' => 'Unable to post right now.'];
        }
        $check->bind_param("i", $threadId);
        $check->execute();
        $res = $check->get_result();
        $thread = $res ? $res->fetch_assoc() : null;
        $check->close();

        if (!$thread || (int)$thread['tribe_id'] !== $tribeId) {
            return ['success' => false, 'message' => 'Thread not found for this tribe.'];
        }

        $stmt = $this->conn->prepare("INSERT INTO tribe_forum_posts (thread_id, author_id, body) VALUES (?, ?, ?)");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Unable to add post.'];
        }
        $stmt->bind_param("iis", $threadId, $authorId, $body);
        $ok = $stmt->execute();
        $postId = $stmt->insert_id;
        $stmt->close();

        return $ok ? ['success' => true, 'post_id' => $postId] : ['success' => false, 'message' => 'Unable to add post.'];
    }

    public function getThreads(int $tribeId): array
    {
        $this->ensureExtrasTables();
        $stmt = $this->conn->prepare("
            SELECT t.id, t.title, t.author_id, u.username as author_username, t.created_at
            FROM tribe_forum_threads t
            LEFT JOIN users u ON u.id = t.author_id
            WHERE t.tribe_id = ?
            ORDER BY t.created_at DESC
        ");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $threads = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $threads;
    }

    public function getPosts(int $threadId, int $tribeId): array
    {
        $this->ensureExtrasTables();
        $stmt = $this->conn->prepare("
            SELECT p.id, p.author_id, u.username as author_username, p.body, p.created_at
            FROM tribe_forum_posts p
            JOIN tribe_forum_threads t ON t.id = p.thread_id
            LEFT JOIN users u ON u.id = p.author_id
            WHERE p.thread_id = ? AND t.tribe_id = ?
            ORDER BY p.created_at ASC
        ");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param("ii", $threadId, $tribeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $posts = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $posts;
    }

    // Applications
    public function submitApplication(int $userId, int $tribeId, string $message = ''): array
    {
        $this->ensureExtrasTables();
        if ($this->getTribeForUser($userId)) {
            return ['success' => false, 'message' => 'You are already in a tribe.'];
        }
        $message = trim($message);
        $stmt = $this->conn->prepare("
            INSERT INTO tribe_applications (tribe_id, user_id, message, status)
            VALUES (?, ?, ?, 'pending')
            ON CONFLICT(tribe_id, user_id) DO UPDATE SET message=excluded.message, status='pending', created_at=CURRENT_TIMESTAMP, responded_at=NULL
        ");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Unable to apply.'];
        }
        $stmt->bind_param("iis", $tribeId, $userId, $message);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            $this->logActivity($tribeId, $userId, 'application_submitted', ['message' => $message]);
            return ['success' => true, 'message' => 'Application submitted.'];
        }
        return ['success' => false, 'message' => 'Unable to apply.'];
    }

    public function getApplications(int $tribeId): array
    {
        $this->ensureExtrasTables();
        $stmt = $this->conn->prepare("
            SELECT ta.*, u.username
            FROM tribe_applications ta
            JOIN users u ON u.id = ta.user_id
            WHERE ta.tribe_id = ? AND ta.status = 'pending'
            ORDER BY ta.created_at ASC
        ");
        if ($stmt === false) return [];
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    public function respondToApplication(int $tribeId, int $actorUserId, int $applicationId, string $decision): array
    {
        $this->ensureExtrasTables();
        $decision = strtolower($decision);
        if (!in_array($decision, ['accept', 'decline'], true)) {
            return ['success' => false, 'message' => 'Invalid decision.'];
        }
        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || !$this->roleHasPermission($membership['role'], 'invite')) {
            return ['success' => false, 'message' => 'No permission to manage applications.'];
        }

        $stmt = $this->conn->prepare("
            SELECT ta.*, u.username
            FROM tribe_applications ta
            JOIN users u ON u.id = ta.user_id
            WHERE ta.id = ? AND ta.tribe_id = ? AND ta.status = 'pending'
            LIMIT 1
        ");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Application not found.'];
        }
        $stmt->bind_param("ii", $applicationId, $tribeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return ['success' => false, 'message' => 'Application not found or already handled.'];
        }

        if ($decision === 'decline') {
            $up = $this->conn->prepare("UPDATE tribe_applications SET status='declined', responded_at=CURRENT_TIMESTAMP WHERE id=?");
            if ($up) {
                $up->bind_param("i", $applicationId);
                $up->execute();
                $up->close();
            }
            $this->logActivity($tribeId, $actorUserId, 'application_declined', ['app_id' => $applicationId, 'user_id' => $row['user_id']]);
            return ['success' => true, 'message' => 'Application declined.'];
        }

        // accept
        if ($this->getTribeForUser((int)$row['user_id'])) {
            return ['success' => false, 'message' => 'Player already in a tribe.'];
        }
        $add = $this->addMember($tribeId, (int)$row['user_id'], 'member');
        if (!$add['success']) {
            return $add;
        }
        $this->setUserTribe((int)$row['user_id'], $tribeId);
        $up = $this->conn->prepare("UPDATE tribe_applications SET status='accepted', responded_at=CURRENT_TIMESTAMP WHERE id=?");
        if ($up) {
            $up->bind_param("i", $applicationId);
            $up->execute();
            $up->close();
        }
        $this->logActivity($tribeId, $actorUserId, 'application_accepted', ['app_id' => $applicationId, 'user_id' => $row['user_id']]);
        $this->recalculateTribePoints($tribeId);
        return ['success' => true, 'message' => 'Application accepted.'];
    }

    public function getActivityLog(int $tribeId, int $limit = 50): array
    {
        $this->ensureExtrasTables();
        $stmt = $this->conn->prepare("
            SELECT tal.id, tal.actor_user_id, u.username, tal.action, tal.meta, tal.created_at
            FROM tribe_activity_log tal
            LEFT JOIN users u ON u.id = tal.actor_user_id
            WHERE tal.tribe_id = ?
            ORDER BY tal.created_at DESC
            LIMIT ?
        ");
        if ($stmt === false) return [];
        $stmt->bind_param("ii", $tribeId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    private function logActivity(int $tribeId, int $actorId, string $action, array $meta = []): void
    {
        $this->ensureExtrasTables();
        $json = $meta ? json_encode($meta) : null;
        $stmt = $this->conn->prepare("INSERT INTO tribe_activity_log (tribe_id, actor_user_id, action, meta) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iiss", $tribeId, $actorId, $action, $json);
            $stmt->execute();
            $stmt->close();
        }
    }
}
