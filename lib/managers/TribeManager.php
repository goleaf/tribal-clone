<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

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
        'edit_profile' => ['leader', 'co_leader'],
    ];
    private const ALLOWED_RECRUITMENT_POLICIES = ['open', 'application', 'invite', 'closed'];
    private const MIN_NAP_DAYS = 7;
    private const MIN_ALLY_DAYS = 14;
    private const MIN_TRUCE_HOURS = 12;
    private const MIN_WAR_HOURS = 24;
    private const ALLY_REFORM_COOLDOWN_HOURS = 48;

    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->ensureCooldownColumn();
        $this->ensureRecruitmentPolicyColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        // SQLite
        if (class_exists('SQLiteAdapter') && $this->conn instanceof SQLiteAdapter) {
            $stmt = $this->conn->prepare("PRAGMA table_info($table)");
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    if (($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
            }
            return false;
        }

        // MySQL
        $stmt = $this->conn->prepare("SHOW COLUMNS FROM $table LIKE ?");
        if ($stmt) {
            $stmt->bind_param("s", $column);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result && $result->num_rows > 0;
            $stmt->close();
            return $exists;
        }
        return false;
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }
        $this->conn->query("ALTER TABLE $table ADD COLUMN $column $definition");
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

    private function getUserPoints(int $userId): ?int
    {
        $stmt = $this->conn->prepare("SELECT points FROM users WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ? (int)$row['points'] : null;
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
        // Patch legacy tables with richer state metadata
        $this->addColumnIfMissing('tribe_diplomacy', 'is_pending', "INTEGER NOT NULL DEFAULT 0");
        $this->addColumnIfMissing('tribe_diplomacy', 'starts_at', "INTEGER DEFAULT NULL");
        $this->addColumnIfMissing('tribe_diplomacy', 'ends_at', "INTEGER DEFAULT NULL");
        $this->addColumnIfMissing('tribe_diplomacy', 'requested_by_user_id', "INTEGER DEFAULT NULL");
        $this->addColumnIfMissing('tribe_diplomacy', 'accepted_by_user_id', "INTEGER DEFAULT NULL");
        $this->addColumnIfMissing('tribe_diplomacy', 'reason', "TEXT DEFAULT ''");
        $this->addColumnIfMissing('tribe_diplomacy', 'cooldown_until', "INTEGER NOT NULL DEFAULT 0");
        $this->addColumnIfMissing('tribe_diplomacy', 'updated_at', "INTEGER NOT NULL DEFAULT (strftime('%s','now'))");
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_diplomacy_cooldowns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tribe_id INTEGER NOT NULL,
                target_tribe_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                cooldown_until TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(tribe_id, target_tribe_id, type)
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

    public function createTribe(int $founderId, string $name, string $tag, string $description = '', string $internalText = '', string $recruitmentPolicy = 'invite'): array
    {
        $name = trim($name);
        $tag = strtoupper(trim($tag));
        $description = trim($description);
        $internalText = trim($internalText);
        $recruitmentPolicy = $this->normalizeRecruitmentPolicy($recruitmentPolicy);

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

        $cooldownBlock = $this->checkJoinCooldown($founderId);
        if ($cooldownBlock !== null) {
            return $cooldownBlock;
        }

        $points = $this->getUserPoints($founderId);
        if ($points === null) {
            return ['success' => false, 'message' => 'Unable to verify your points to create a tribe.'];
        }
        if ($points < 500) {
            return ['success' => false, 'message' => 'You need at least 500 points to found a tribe.'];
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

        $stmt = $this->conn->prepare("INSERT INTO tribes (name, tag, description, internal_text, founder_id, recruitment_policy) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            error_log("TribeManager::createTribe insert prepare failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to create tribe right now.'];
        }
        $stmt->bind_param("ssssis", $name, $tag, $description, $internalText, $founderId, $recruitmentPolicy);
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

    public function updateTribeProfile(int $tribeId, int $actorUserId, string $description, string $internalText = ''): array
    {
        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId) {
            return ['success' => false, 'message' => 'You are not a member of this tribe.'];
        }
        if (!$this->roleHasPermission($membership['role'], 'edit_profile')) {
            return ['success' => false, 'message' => 'You do not have permission to edit tribe profile.'];
        }

        $description = trim($description);
        $internalText = trim($internalText);

        if (strlen($description) > 2000 || strlen($internalText) > 2000) {
            return ['success' => false, 'message' => 'Text too long. Keep descriptions under 2000 characters.'];
        }

        $stmt = $this->conn->prepare("UPDATE tribes SET description = ?, internal_text = ? WHERE id = ?");
        if ($stmt === false) {
            error_log("TribeManager::updateTribeProfile prepare failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to update tribe profile right now.'];
        }
        $stmt->bind_param("ssi", $description, $internalText, $tribeId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $this->logActivity($tribeId, $actorUserId, 'profile_updated', [
                'desc_len' => strlen($description),
                'internal_len' => strlen($internalText)
            ]);
            return ['success' => true, 'message' => 'Tribe profile updated.'];
        }

        return ['success' => false, 'message' => 'Failed to save tribe profile.'];
    }

    public function updateRecruitmentPolicy(int $tribeId, int $actorUserId, string $policy): array
    {
        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId) {
            return ['success' => false, 'message' => 'You are not a member of this tribe.'];
        }
        if (!$this->roleHasPermission($membership['role'], 'edit_profile')) {
            return ['success' => false, 'message' => 'You do not have permission to edit tribe profile.'];
        }

        $policy = $this->normalizeRecruitmentPolicy($policy);
        $stmt = $this->conn->prepare("UPDATE tribes SET recruitment_policy = ? WHERE id = ?");
        if ($stmt === false) {
            error_log("TribeManager::updateRecruitmentPolicy prepare failed: " . $this->conn->error);
            return ['success' => false, 'message' => 'Unable to update recruitment policy right now.'];
        }
        $stmt->bind_param("si", $policy, $tribeId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $this->logActivity($tribeId, $actorUserId, 'policy_updated', ['policy' => $policy]);
            return ['success' => true, 'message' => 'Recruitment policy updated.'];
        }

        return ['success' => false, 'message' => 'Failed to save recruitment policy.'];
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

        $cooldownBlock = $this->checkJoinCooldown($userId);
        if ($cooldownBlock !== null) {
            return $cooldownBlock;
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
        $this->setJoinCooldownHours($userId, 24);
        $this->recalculateTribePoints($tribeId);

        return ['success' => true, 'message' => 'You have left the tribe.'];
    }

    public function disbandTribe(int $tribeId, int $requesterId): array
    {
        $membership = $this->getMembership($requesterId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || $membership['role'] !== 'leader') {
            return ['success' => false, 'message' => 'Only the tribe leader can disband the tribe.'];
        }

        if (!$this->performDisband($tribeId)) {
            return ['success' => false, 'message' => 'Unable to disband tribe.'];
        }

        return ['success' => true, 'message' => 'Tribe has been disbanded.'];
    }

    /**
     * System-level disband that bypasses role checks (used by cleanup jobs).
     */
    public function systemDisbandTribe(int $tribeId, string $reason = 'system_cleanup'): array
    {
        $ok = $this->performDisband($tribeId);
        if ($ok) {
            $this->logActivity($tribeId, 0, 'tribe_disbanded', ['reason' => $reason]);
        }
        return $ok
            ? ['success' => true, 'message' => 'Tribe has been disbanded.']
            : ['success' => false, 'message' => 'Unable to disband tribe.'];
    }

    /**
     * Shared disband logic used by both leader-driven and system disbands.
     */
    private function performDisband(int $tribeId): bool
    {
        // Reset ally_id for all members
        $memberIds = $this->conn->prepare("SELECT user_id FROM tribe_members WHERE tribe_id = ?");
        if ($memberIds === false) {
            error_log("TribeManager::performDisband member fetch failed: " . $this->conn->error);
            return false;
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
            error_log("TribeManager::performDisband delete failed: " . $this->conn->error);
            return false;
        }
        $delete->bind_param("i", $tribeId);
        $delete->execute();
        $delete->close();

        return true;
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
        $this->setJoinCooldownHours($targetUserId, 72);
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
        $cooldownBlock = $this->checkJoinCooldown($userId);
        if ($cooldownBlock !== null) {
            return $cooldownBlock;
        }

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

    /**
     * Counts members whose last_activity_at is on/after the given timestamp.
     */
    public function getActiveMemberCountSince(int $tribeId, string $sinceTimestamp): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM tribe_members tm
            JOIN users u ON u.id = tm.user_id
            WHERE tm.tribe_id = ? AND u.last_activity_at >= ?
        ");
        if ($stmt === false) {
            error_log("TribeManager::getActiveMemberCountSince prepare failed: " . $this->conn->error);
            return 0;
        }
        $stmt->bind_param("is", $tribeId, $sinceTimestamp);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
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
        if ($status === 'ally') {
            $status = 'alliance';
        } elseif ($status === 'enemy') {
            $status = 'war';
        }
        if (!in_array($status, ['nap', 'alliance', 'war', 'truce', 'neutral'], true)) {
            return ['success' => false, 'message' => 'Invalid diplomacy status.'];
        }
        if ($tribeId === $targetTribeId) {
            return ['success' => false, 'message' => 'Cannot set diplomacy with your own tribe.'];
        }

        $membership = $this->getMembership($actorUserId);
        if (!$membership || $membership['tribe_id'] !== $tribeId || !$this->roleHasPermission($membership['role'], 'diplomacy')) {
            return ['success' => false, 'message' => 'You do not have permission to manage diplomacy.'];
        }

        return match ($status) {
            'war' => $this->declareWar($tribeId, $actorUserId, $targetTribeId),
            'nap', 'alliance', 'truce' => $this->proposeOrAcceptTreaty($tribeId, $actorUserId, $targetTribeId, $status),
            'neutral' => $this->dropToNeutral($tribeId, $actorUserId, $targetTribeId),
            default => ['success' => false, 'message' => 'Unsupported state.']
        };
    }

    public function getDiplomacyRelations(int $tribeId): array
    {
        $this->ensureExtrasTables();
        $stmt = $this->conn->prepare("
            SELECT td.target_tribe_id, td.status, td.created_at, td.starts_at, td.ends_at, td.is_pending,
                   td.cooldown_until, td.reason, t.name, t.tag
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
            $row['canonical_status'] = $this->canonicalDiplomacyStatus($row['status'] ?? 'neutral');
            $relations[] = $row;
        }
        $stmt->close();
        return $relations;
    }

    private function getDiplomacyRelation(int $tribeId, int $targetTribeId): ?array
    {
        return $this->getDiplomacyRow($tribeId, $targetTribeId);
    }

    private function proposeOrAcceptTreaty(int $tribeId, int $actorUserId, int $targetTribeId, string $type): array
    {
        $now = time();
        $existing = $this->getDiplomacyRow($tribeId, $targetTribeId);
        $pendingKey = 'pending_' . $type;
        $existingStatus = $this->canonicalDiplomacyStatus($existing['status'] ?? 'neutral');

        if ($existing && (int)($existing['cooldown_until'] ?? 0) > $now) {
            $remaining = $this->formatRemainingSeconds((int)$existing['cooldown_until'] - $now);
            return ['success' => false, 'message' => 'Cooldown active. Wait ' . $remaining . ' before changing diplomacy.'];
        }
        if ($existing && (int)($existing['is_pending'] ?? 0) === 0 && $existingStatus !== 'neutral' && $existingStatus !== $type) {
            $minDuration = $this->getMinDurationSeconds($existingStatus);
            $start = (int)($existing['starts_at'] ?? 0);
            if ($start === 0 && !empty($existing['created_at'])) {
                $start = strtotime((string)$existing['created_at']) ?: 0;
            }
            if ($minDuration > 0 && $start > 0 && ($start + $minDuration) > $now) {
                $remaining = $this->formatRemainingSeconds(($start + $minDuration) - $now);
                return ['success' => false, 'message' => 'Minimum duration not met. Wait ' . $remaining . '.'];
            }
        }
        if ($existing && $existingStatus === $type && (int)($existing['is_pending'] ?? 0) === 0) {
            return ['success' => true, 'message' => 'Already in ' . $type . ' state.'];
        }

        // Accept incoming pending request
        if ($existing && (int)($existing['is_pending'] ?? 0) === 1 && $existing['status'] === $pendingKey) {
            $minDuration = $this->getMinDurationSeconds($type);
            $start = $now;
            $end = $minDuration > 0 ? $start + $minDuration : null;
            $this->writeRelationPair($tribeId, $targetTribeId, [
                'status' => $type,
                'is_pending' => 0,
                'starts_at' => $start,
                'ends_at' => $end,
                'requested_by_user_id' => $existing['requested_by_user_id'] ?? $actorUserId,
                'accepted_by_user_id' => $actorUserId,
                'reason' => $existing['reason'] ?? '',
                'cooldown_until' => 0,
            ]);
            return ['success' => true, 'message' => ucfirst($type) . ' accepted.'];
        }

        // Otherwise, send a request
        $minDuration = $this->getMinDurationSeconds($type);
        $start = $now;
        $end = $minDuration > 0 ? $start + $minDuration : null;
        $this->writeRelationPair($tribeId, $targetTribeId, [
            'status' => $pendingKey,
            'is_pending' => 1,
            'starts_at' => $start,
            'ends_at' => $end,
            'requested_by_user_id' => $actorUserId,
            'accepted_by_user_id' => null,
            'reason' => '',
            'cooldown_until' => 0,
        ]);
        return ['success' => true, 'message' => ucfirst($type) . ' request sent.'];
    }

    private function declareWar(int $tribeId, int $actorUserId, int $targetTribeId): array
    {
        $now = time();
        $existing = $this->getDiplomacyRow($tribeId, $targetTribeId);
        if ($existing) {
            if (($existing['status'] ?? '') === 'war' && (int)($existing['is_pending'] ?? 0) === 0) {
                return ['success' => false, 'message' => 'War already active with this tribe.'];
            }
            if ((int)($existing['cooldown_until'] ?? 0) > $now) {
                $remaining = $this->formatRemainingSeconds((int)$existing['cooldown_until'] - $now);
                return ['success' => false, 'message' => 'War cooldown active. Wait ' . $remaining . '.'];
            }
        }

        $minDuration = $this->getMinDurationSeconds('war');
        $start = $now;
        $end = $minDuration > 0 ? $start + $minDuration : null;
        $this->writeRelationPair($tribeId, $targetTribeId, [
            'status' => 'war',
            'is_pending' => 0,
            'starts_at' => $start,
            'ends_at' => $end,
            'requested_by_user_id' => $actorUserId,
            'accepted_by_user_id' => null,
            'reason' => '',
            'cooldown_until' => 0,
        ]);

        return ['success' => true, 'message' => 'War declared.'];
    }

    private function dropToNeutral(int $tribeId, int $actorUserId, int $targetTribeId): array
    {
        $now = time();
        $current = $this->getDiplomacyRow($tribeId, $targetTribeId);
        if (!$current) {
            return ['success' => true, 'message' => 'Already neutral.'];
        }

        $status = $this->canonicalDiplomacyStatus($current['status'] ?? 'neutral');
        $start = (int)($current['starts_at'] ?? 0);
        if ($start === 0 && !empty($current['created_at'])) {
            $start = strtotime((string)$current['created_at']) ?: 0;
        }
        $minDuration = (int)($current['is_pending'] ?? 0) === 1 ? 0 : $this->getMinDurationSeconds($status);
        if ($minDuration > 0 && ($start + $minDuration) > $now) {
            $remaining = $this->formatRemainingSeconds(($start + $minDuration) - $now);
            return ['success' => false, 'message' => 'Minimum duration not met. Wait ' . $remaining . '.'];
        }

        $cooldown = 0;
        if ($status === 'war') {
            $cooldown = $now + (self::MIN_WAR_HOURS * 3600);
        } elseif ($status === 'alliance') {
            $cooldown = $now + (self::ALLY_REFORM_COOLDOWN_HOURS * 3600);
        }

        $this->writeRelationPair($tribeId, $targetTribeId, [
            'status' => 'neutral',
            'is_pending' => 0,
            'starts_at' => $now,
            'ends_at' => $now,
            'requested_by_user_id' => $actorUserId,
            'accepted_by_user_id' => null,
            'reason' => 'returned_to_neutral',
            'cooldown_until' => $cooldown,
        ]);

        return ['success' => true, 'message' => 'Relation set to Neutral.'];
    }

    private function getMinDurationSeconds(string $status): int
    {
        return match ($status) {
            'nap', 'pending_nap' => self::MIN_NAP_DAYS * 86400,
            'alliance', 'ally', 'pending_alliance' => self::MIN_ALLY_DAYS * 86400,
            'truce', 'pending_truce' => self::MIN_TRUCE_HOURS * 3600,
            'war', 'enemy' => self::MIN_WAR_HOURS * 3600,
            default => 0
        };
    }

    private function canonicalDiplomacyStatus(?string $status): string
    {
        $status = strtolower((string)($status ?? 'neutral'));
        return match ($status) {
            'ally' => 'alliance',
            'enemy' => 'war',
            default => ($status !== '' ? $status : 'neutral'),
        };
    }

    private function getDiplomacyRow(int $tribeId, int $targetTribeId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM tribe_diplomacy
            WHERE tribe_id = ? AND target_tribe_id = ?
            LIMIT 1
        ");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("ii", $tribeId, $targetTribeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    private function writeRelationPair(int $tribeId, int $targetTribeId, array $data): void
    {
        $this->writeRelation($tribeId, $targetTribeId, $data);
        $this->writeRelation($targetTribeId, $tribeId, $data);
    }

    private function writeRelation(int $tribeId, int $targetTribeId, array $data): void
    {
        $now = time();
        $payload = [
            'status' => $data['status'] ?? 'neutral',
            'is_pending' => (int)($data['is_pending'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'requested_by_user_id' => $data['requested_by_user_id'] ?? null,
            'accepted_by_user_id' => $data['accepted_by_user_id'] ?? null,
            'reason' => $data['reason'] ?? '',
            'cooldown_until' => (int)($data['cooldown_until'] ?? 0),
            'updated_at' => $now,
        ];
        $statusToStore = $payload['status'];
        if ($statusToStore === 'alliance') {
            $statusToStore = 'ally';
        } elseif ($statusToStore === 'war') {
            $statusToStore = 'enemy';
        }

        $existing = $this->getDiplomacyRow($tribeId, $targetTribeId);
        if ($existing) {
            $stmt = $this->conn->prepare("
                UPDATE tribe_diplomacy
                SET status = ?, is_pending = ?, starts_at = ?, ends_at = ?, requested_by_user_id = ?, accepted_by_user_id = ?, reason = ?, cooldown_until = ?, updated_at = ?
                WHERE tribe_id = ? AND target_tribe_id = ?
            ");
            if ($stmt) {
                $stmt->bind_param(
                    "siiiiisiiii",
                    $statusToStore,
                    $payload['is_pending'],
                    $payload['starts_at'],
                    $payload['ends_at'],
                    $payload['requested_by_user_id'],
                    $payload['accepted_by_user_id'],
                    $payload['reason'],
                    $payload['cooldown_until'],
                    $payload['updated_at'],
                    $tribeId,
                    $targetTribeId
                );
                $stmt->execute();
                $stmt->close();
            }
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO tribe_diplomacy
            (tribe_id, target_tribe_id, status, created_by, created_at, is_pending, starts_at, ends_at, requested_by_user_id, accepted_by_user_id, reason, cooldown_until, updated_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param(
                "iisiiiiiisii",
                $tribeId,
                $targetTribeId,
                $statusToStore,
                $payload['requested_by_user_id'],
                $payload['is_pending'],
                $payload['starts_at'],
                $payload['ends_at'],
                $payload['requested_by_user_id'],
                $payload['accepted_by_user_id'],
                $payload['reason'],
                $payload['cooldown_until'],
                $payload['updated_at']
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    private function getAllianceCooldown(int $tribeId, int $targetTribeId): ?string
    {
        $stmt = $this->conn->prepare("
            SELECT cooldown_until
            FROM tribe_diplomacy_cooldowns
            WHERE type = 'ally'
              AND ((tribe_id = ? AND target_tribe_id = ?) OR (tribe_id = ? AND target_tribe_id = ?))
            LIMIT 1
        ");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("iiii", $tribeId, $targetTribeId, $targetTribeId, $tribeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row || empty($row['cooldown_until'])) {
            return null;
        }
        $ts = strtotime($row['cooldown_until']);
        if ($ts !== false && $ts <= time()) {
            // expire it
            $this->clearAllianceCooldown($tribeId, $targetTribeId);
            return null;
        }
        return $row['cooldown_until'];
    }

    private function setAllianceCooldown(int $tribeId, int $targetTribeId, int $hours): void
    {
        if ($hours <= 0) {
            return;
        }
        $until = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $this->upsertCooldown($tribeId, $targetTribeId, 'ally', $until);
        $this->upsertCooldown($targetTribeId, $tribeId, 'ally', $until);
    }

    private function upsertCooldown(int $tribeId, int $targetTribeId, string $type, string $until): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO tribe_diplomacy_cooldowns (tribe_id, target_tribe_id, type, cooldown_until)
            VALUES (?, ?, ?, ?)
            ON CONFLICT(tribe_id, target_tribe_id, type) DO UPDATE SET cooldown_until = excluded.cooldown_until, created_at = CURRENT_TIMESTAMP
        ");
        if ($stmt) {
            $stmt->bind_param("iiss", $tribeId, $targetTribeId, $type, $until);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function clearAllianceCooldown(int $tribeId, int $targetTribeId): void
    {
        $stmt = $this->conn->prepare("
            DELETE FROM tribe_diplomacy_cooldowns
            WHERE type = 'ally'
              AND ((tribe_id = ? AND target_tribe_id = ?) OR (tribe_id = ? AND target_tribe_id = ?))
        ");
        if ($stmt) {
            $stmt->bind_param("iiii", $tribeId, $targetTribeId, $targetTribeId, $tribeId);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function formatRemainingSeconds(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0m';
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        if ($hours > 0 && $minutes > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        if ($hours > 0) {
            return $hours . 'h';
        }
        return $minutes . 'm';
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
        $cooldownBlock = $this->checkJoinCooldown((int)$row['user_id']);
        if ($cooldownBlock !== null) {
            return $cooldownBlock;
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

    /**
     * Auto-disband tribes that have fewer than $minActiveMembers active members in the last $inactiveDays days.
     * Returns number of tribes disbanded.
     */
    public function disbandInactiveTribes(int $minActiveMembers = 3, int $inactiveDays = 14): int
    {
        if ($minActiveMembers <= 0 || $inactiveDays <= 0) {
            return 0;
        }
        if (!function_exists('dbColumnExists') || !dbColumnExists($this->conn, 'users', 'last_activity_at')) {
            return 0;
        }

        $threshold = date('Y-m-d H:i:s', time() - ($inactiveDays * 86400));
        $stmt = $this->conn->prepare("
            SELECT 
                t.id,
                COUNT(tm.user_id) AS member_count,
                SUM(CASE WHEN u.last_activity_at IS NOT NULL AND u.last_activity_at >= ? THEN 1 ELSE 0 END) AS active_recent
            FROM tribes t
            LEFT JOIN tribe_members tm ON tm.tribe_id = t.id
            LEFT JOIN users u ON u.id = tm.user_id
            GROUP BY t.id
            HAVING active_recent < ? AND member_count > 0
        ");
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param("si", $threshold, $minActiveMembers);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        if (empty($rows)) {
            return 0;
        }

        $disbanded = 0;
        foreach ($rows as $row) {
            $tribeId = (int)$row['id'];
            if ($this->forceDisbandTribe($tribeId, 'inactive_low_activity')) {
                $disbanded++;
            }
        }

        return $disbanded;
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

    /**
     * Disband a tribe without requiring leader approval (used by cron cleanup).
     */
    private function forceDisbandTribe(int $tribeId, string $reason = 'system'): bool
    {
        // Fetch member ids
        $memberIds = [];
        $stmtMembers = $this->conn->prepare("SELECT user_id FROM tribe_members WHERE tribe_id = ?");
        if ($stmtMembers) {
            $stmtMembers->bind_param("i", $tribeId);
            $stmtMembers->execute();
            $res = $stmtMembers->get_result();
            while ($row = $res->fetch_assoc()) {
                $memberIds[] = (int)$row['user_id'];
            }
            $stmtMembers->close();
        }

        // Clear ally_id for members
        foreach ($memberIds as $uid) {
            $stmt = $this->conn->prepare("UPDATE users SET ally_id = NULL WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $stmt->close();
            }
        }

        $this->logActivity($tribeId, 0, 'tribe_disbanded_auto', ['reason' => $reason, 'member_count' => count($memberIds)]);

        $stmtDelMembers = $this->conn->prepare("DELETE FROM tribe_members WHERE tribe_id = ?");
        if ($stmtDelMembers) {
            $stmtDelMembers->bind_param("i", $tribeId);
            $stmtDelMembers->execute();
            $stmtDelMembers->close();
        }

        $stmtDel = $this->conn->prepare("DELETE FROM tribes WHERE id = ?");
        if ($stmtDel === false) {
            return false;
        }
        $stmtDel->bind_param("i", $tribeId);
        $ok = $stmtDel->execute();
        $stmtDel->close();

        return $ok;
    }

    private function ensureCooldownColumn(): void
    {
        if (function_exists('dbColumnExists') && !dbColumnExists($this->conn, 'users', 'tribe_join_cooldown_until')) {
            $this->conn->query("ALTER TABLE users ADD COLUMN tribe_join_cooldown_until DATETIME DEFAULT NULL");
        }
    }

    private function ensureRecruitmentPolicyColumn(): void
    {
        if (function_exists('dbColumnExists') && !dbColumnExists($this->conn, 'tribes', 'recruitment_policy')) {
            $this->conn->query("ALTER TABLE tribes ADD COLUMN recruitment_policy VARCHAR(16) NOT NULL DEFAULT 'invite'");
        }
    }

    private function setJoinCooldownHours(int $userId, int $hours): void
    {
        if ($hours <= 0) {
            return;
        }
        $until = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $stmt = $this->conn->prepare("UPDATE users SET tribe_join_cooldown_until = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $until, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function getJoinCooldownUntil(int $userId): ?string
    {
        $stmt = $this->conn->prepare("SELECT tribe_join_cooldown_until FROM users WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row || empty($row['tribe_join_cooldown_until'])) {
            return null;
        }

        $ts = strtotime($row['tribe_join_cooldown_until']);
        if ($ts !== false && $ts <= time()) {
            $clear = $this->conn->prepare("UPDATE users SET tribe_join_cooldown_until = NULL WHERE id = ?");
            if ($clear) {
                $clear->bind_param("i", $userId);
                $clear->execute();
                $clear->close();
            }
            return null;
        }

        return $row['tribe_join_cooldown_until'];
    }

    private function formatCooldownRemaining(string $until): string
    {
        $ts = strtotime($until);
        if ($ts === false) {
            return 'a short time';
        }
        $remaining = max(0, $ts - time());
        $hours = intdiv($remaining, 3600);
        $minutes = intdiv($remaining % 3600, 60);
        if ($hours > 0 && $minutes > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        if ($hours > 0) {
            return $hours . 'h';
        }
        return $minutes . 'm';
    }

    private function checkJoinCooldown(int $userId): ?array
    {
        $until = $this->getJoinCooldownUntil($userId);
        if ($until === null) {
            return null;
        }
        $remaining = $this->formatCooldownRemaining($until);
        return [
            'success' => false,
            'message' => 'You must wait ' . $remaining . ' before joining a tribe.'
        ];
    }

    private function normalizeRecruitmentPolicy(string $policy): string
    {
        $policy = strtolower(trim($policy));
        return in_array($policy, self::ALLOWED_RECRUITMENT_POLICIES, true) ? $policy : 'invite';
    }
}
