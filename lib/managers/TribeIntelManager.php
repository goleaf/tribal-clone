<?php
declare(strict_types=1);

require_once __DIR__ . '/TribeManager.php';

/**
 * Tribe Intel Manager
 * Shared tribe markers, operations, and claim slots for coordination.
 */
class TribeIntelManager
{
    private const MARKER_TYPES = ['target', 'defense', 'farm', 'safehouse', 'threat', 'note', 'support'];
    private const OPERATION_TYPES = ['attack', 'defense', 'raid'];
    private const CLAIM_ROLES = ['noble', 'offense', 'support', 'fake', 'scout', 'clean', 'siege'];
    private const MANAGE_ROLES = ['leader', 'co_leader', 'officer'];

    private $conn;
    private TribeManager $tribeManager;

    public function __construct($conn, TribeManager $tribeManager)
    {
        $this->conn = $conn;
        $this->tribeManager = $tribeManager;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        // Map markers
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_markers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tribe_id INTEGER NOT NULL,
                world_id INTEGER NOT NULL DEFAULT 1,
                type TEXT NOT NULL,
                title TEXT NOT NULL,
                x INTEGER NOT NULL,
                y INTEGER NOT NULL,
                notes TEXT DEFAULT '',
                tags TEXT DEFAULT NULL,
                confidence INTEGER NOT NULL DEFAULT 3,
                freshness_minutes INTEGER NOT NULL DEFAULT 60,
                expires_at TEXT DEFAULT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->conn->query("CREATE INDEX IF NOT EXISTS idx_tribe_markers_tribe ON tribe_markers(tribe_id, world_id)");

        // Operations (attack/defense/raid)
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_operations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tribe_id INTEGER NOT NULL,
                world_id INTEGER NOT NULL DEFAULT 1,
                marker_id INTEGER NULL,
                type TEXT NOT NULL,
                title TEXT NOT NULL,
                target_x INTEGER NOT NULL,
                target_y INTEGER NOT NULL,
                launch_at TEXT DEFAULT NULL,
                status TEXT NOT NULL DEFAULT 'open',
                notes TEXT DEFAULT '',
                required_roles TEXT DEFAULT NULL,
                expires_at TEXT DEFAULT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->conn->query("CREATE INDEX IF NOT EXISTS idx_tribe_ops_tribe ON tribe_operations(tribe_id, world_id)");

        // Claims against operations (role slots)
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_operation_claims (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                operation_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role TEXT NOT NULL,
                note TEXT DEFAULT '',
                expires_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->conn->query("CREATE INDEX IF NOT EXISTS idx_tribe_op_claims_op ON tribe_operation_claims(operation_id)");
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function isExpired(?string $ts): bool
    {
        if ($ts === null || $ts === '') {
            return false;
        }
        $time = strtotime($ts);
        return $time !== false && $time <= time();
    }

    private function normalizeCoordinate(int $value): int
    {
        return max(0, min(999, $value));
    }

    private function hydrateMarker(array $row): array
    {
        $freshMinutes = (int)($row['freshness_minutes'] ?? 0);
        $createdAt = isset($row['created_at']) ? strtotime($row['created_at']) : null;
        $staleAt = ($createdAt && $freshMinutes > 0) ? $createdAt + ($freshMinutes * 60) : null;

        return [
            'id' => (int)$row['id'],
            'tribe_id' => (int)$row['tribe_id'],
            'world_id' => (int)$row['world_id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'x' => (int)$row['x'],
            'y' => (int)$row['y'],
            'notes' => $row['notes'] ?? '',
            'tags' => $row['tags'],
            'confidence' => (int)($row['confidence'] ?? 0),
            'freshness_minutes' => $freshMinutes,
            'expires_at' => $row['expires_at'],
            'created_by' => (int)$row['created_by'],
            'created_at' => $row['created_at'],
            'is_expired' => $this->isExpired($row['expires_at'] ?? null),
            'is_stale' => $staleAt ? (time() >= $staleAt) : false,
            'stale_at' => $staleAt ? date('Y-m-d H:i:s', $staleAt) : null,
        ];
    }

    private function hydrateOperation(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'tribe_id' => (int)$row['tribe_id'],
            'world_id' => (int)$row['world_id'],
            'marker_id' => $row['marker_id'] !== null ? (int)$row['marker_id'] : null,
            'type' => $row['type'],
            'title' => $row['title'],
            'target_x' => (int)$row['target_x'],
            'target_y' => (int)$row['target_y'],
            'launch_at' => $row['launch_at'],
            'status' => $row['status'],
            'notes' => $row['notes'] ?? '',
            'required_roles' => $row['required_roles'] ? json_decode($row['required_roles'], true) : null,
            'expires_at' => $row['expires_at'],
            'created_by' => (int)$row['created_by'],
            'created_at' => $row['created_at'],
            'is_expired' => $this->isExpired($row['expires_at'] ?? null),
        ];
    }

    public function listMarkers(int $tribeId, int $worldId, bool $includeExpired = false): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM tribe_markers
            WHERE tribe_id = ? AND world_id = ?
            ORDER BY created_at DESC
        ");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param("ii", $tribeId, $worldId);
        $stmt->execute();
        $res = $stmt->get_result();
        $markers = [];
        while ($row = $res->fetch_assoc()) {
            $marker = $this->hydrateMarker($row);
            if (!$includeExpired && $marker['is_expired']) {
                continue;
            }
            $markers[] = $marker;
        }
        $stmt->close();
        return $markers;
    }

    public function createMarker(
        int $tribeId,
        int $worldId,
        int $userId,
        string $role,
        string $type,
        string $title,
        int $x,
        int $y,
        ?string $expiresAt,
        int $freshnessMinutes,
        int $confidence,
        ?string $tags,
        string $notes
    ): array {
        $type = strtolower(trim($type));
        if (!in_array($type, self::MARKER_TYPES, true)) {
            return ['success' => false, 'message' => 'Unsupported marker type.'];
        }
        $title = trim($title);
        if ($title === '') {
            $title = ucfirst($type) . ' marker';
        }

        $x = $this->normalizeCoordinate($x);
        $y = $this->normalizeCoordinate($y);
        $freshnessMinutes = max(5, min(1440, $freshnessMinutes));
        $confidence = max(1, min(5, $confidence));
        $tags = $tags !== null ? trim($tags) : null;
        $notes = trim($notes);

        if ($expiresAt !== null && strtotime($expiresAt) === false) {
            return ['success' => false, 'message' => 'Invalid expiration date.'];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO tribe_markers (tribe_id, world_id, type, title, x, y, notes, tags, confidence, freshness_minutes, expires_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Unable to save marker.'];
        }
        $stmt->bind_param(
            "iissiissiisi",
            $tribeId,
            $worldId,
            $type,
            $title,
            $x,
            $y,
            $notes,
            $tags,
            $confidence,
            $freshnessMinutes,
            $expiresAt,
            $userId
        );
        $ok = $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Unable to save marker.'];
        }

        $marker = $this->getMarkerById((int)$id);
        return ['success' => true, 'id' => (int)$id, 'marker' => $marker];
    }

    public function deleteMarker(int $tribeId, int $userId, string $role, int $markerId): array
    {
        $marker = $this->getMarkerById($markerId);
        if (!$marker || $marker['tribe_id'] !== $tribeId) {
            return ['success' => false, 'message' => 'Marker not found.'];
        }

        $canManage = in_array($role, self::MANAGE_ROLES, true);
        if ($marker['created_by'] !== $userId && !$canManage) {
            return ['success' => false, 'message' => 'No permission to delete this marker.'];
        }

        $stmt = $this->conn->prepare("DELETE FROM tribe_markers WHERE id = ? AND tribe_id = ?");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Unable to delete marker.'];
        }
        $stmt->bind_param("ii", $markerId, $tribeId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok ? ['success' => true] : ['success' => false, 'message' => 'Unable to delete marker.'];
    }

    private function getMarkerById(int $markerId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM tribe_markers WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("i", $markerId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ? $this->hydrateMarker($row) : null;
    }

    public function listOperations(int $tribeId, int $worldId, bool $includeExpired = false): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM tribe_operations
            WHERE tribe_id = ? AND world_id = ?
            ORDER BY created_at DESC
        ");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param("ii", $tribeId, $worldId);
        $stmt->execute();
        $res = $stmt->get_result();
        $operations = [];
        $opIds = [];
        while ($row = $res->fetch_assoc()) {
            $op = $this->hydrateOperation($row);
            if (!$includeExpired && $op['is_expired']) {
                continue;
            }
            $operations[] = $op;
            $opIds[] = $op['id'];
        }
        $stmt->close();

        $claimsByOp = $this->getClaimsForOperations($opIds);
        foreach ($operations as &$op) {
            $op['claims'] = $claimsByOp[$op['id']] ?? [];
        }

        return $operations;
    }

    private function getClaimsForOperations(array $operationIds): array
    {
        if (empty($operationIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($operationIds), '?'));
        $types = str_repeat('i', count($operationIds));
        $stmt = $this->conn->prepare("
            SELECT c.*, u.username
            FROM tribe_operation_claims c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.operation_id IN ($placeholders)
            ORDER BY c.created_at ASC
        ");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param($types, ...$operationIds);
        $stmt->execute();
        $res = $stmt->get_result();
        $claims = [];
        while ($row = $res->fetch_assoc()) {
            $claim = [
                'id' => (int)$row['id'],
                'operation_id' => (int)$row['operation_id'],
                'user_id' => (int)$row['user_id'],
                'username' => $row['username'] ?? null,
                'role' => $row['role'],
                'note' => $row['note'] ?? '',
                'expires_at' => $row['expires_at'],
                'created_at' => $row['created_at'],
                'is_expired' => $this->isExpired($row['expires_at'] ?? null),
            ];
            $claims[$claim['operation_id']][] = $claim;
        }
        $stmt->close();
        return $claims;
    }

    public function createOperation(
        int $tribeId,
        int $worldId,
        int $userId,
        string $role,
        string $type,
        string $title,
        int $targetX,
        int $targetY,
        ?string $launchAt,
        ?int $markerId,
        ?array $requiredRoles,
        ?string $expiresAt,
        string $notes
    ): array {
        $type = strtolower(trim($type));
        if (!in_array($type, self::OPERATION_TYPES, true)) {
            return ['success' => false, 'message' => 'Unsupported operation type.'];
        }
        if (!in_array($role, self::MANAGE_ROLES, true)) {
            return ['success' => false, 'message' => 'No permission to create operations.'];
        }

        $title = trim($title);
        if ($title === '') {
            $title = ucfirst($type) . ' op';
        }
        $targetX = $this->normalizeCoordinate($targetX);
        $targetY = $this->normalizeCoordinate($targetY);
        $notes = trim($notes);
        if ($launchAt !== null && strtotime($launchAt) === false) {
            return ['success' => false, 'message' => 'Invalid launch time.'];
        }
        if ($expiresAt !== null && strtotime($expiresAt) === false) {
            return ['success' => false, 'message' => 'Invalid expiration.'];
        }

        if ($markerId !== null) {
            $marker = $this->getMarkerById($markerId);
            if (!$marker || $marker['tribe_id'] !== $tribeId) {
                return ['success' => false, 'message' => 'Marker not found for this tribe.'];
            }
        }

        $requiredRolesJson = $requiredRoles ? json_encode(array_values(array_unique($requiredRoles))) : null;

        $stmt = $this->conn->prepare("
            INSERT INTO tribe_operations (tribe_id, world_id, marker_id, type, title, target_x, target_y, launch_at, status, notes, required_roles, expires_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, ?)
        ");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Unable to save operation.'];
        }
        $stmt->bind_param(
            "iiissiissssi",
            $tribeId,
            $worldId,
            $markerId,
            $type,
            $title,
            $targetX,
            $targetY,
            $launchAt,
            $notes,
            $requiredRolesJson,
            $expiresAt,
            $userId
        );
        $ok = $stmt->execute();
        $opId = $stmt->insert_id;
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Unable to save operation.'];
        }

        $operation = $this->getOperationById((int)$opId);
        return ['success' => true, 'id' => (int)$opId, 'operation' => $operation];
    }

    private function getOperationById(int $operationId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM tribe_operations WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("i", $operationId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ? $this->hydrateOperation($row) : null;
    }

    public function claimOperation(
        int $tribeId,
        int $worldId,
        int $userId,
        string $role,
        int $operationId,
        string $claimRole,
        ?string $note,
        ?int $ttlMinutes
    ): array {
        $operation = $this->getOperationById($operationId);
        if (!$operation || $operation['tribe_id'] !== $tribeId || $operation['world_id'] !== $worldId) {
            return ['success' => false, 'message' => 'Operation not found.'];
        }
        if ($operation['is_expired']) {
            return ['success' => false, 'message' => 'Operation is expired.'];
        }

        $claimRole = strtolower(trim($claimRole));
        if (!in_array($claimRole, self::CLAIM_ROLES, true)) {
            return ['success' => false, 'message' => 'Unsupported claim role.'];
        }

        $expiresAt = null;
        if ($ttlMinutes !== null) {
            $ttlMinutes = max(15, min(24 * 60, $ttlMinutes));
            $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
        }
        $note = $note !== null ? trim($note) : '';

        // Upsert-like: if the same user already has a claim for this role, update the note/expiry.
        $stmtCheck = $this->conn->prepare("
            SELECT id FROM tribe_operation_claims
            WHERE operation_id = ? AND user_id = ? AND role = ?
            LIMIT 1
        ");
        if ($stmtCheck === false) {
            return ['success' => false, 'message' => 'Unable to claim slot.'];
        }
        $stmtCheck->bind_param("iis", $operationId, $userId, $claimRole);
        $stmtCheck->execute();
        $existing = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if ($existing) {
            $stmtUpdate = $this->conn->prepare("
                UPDATE tribe_operation_claims
                SET note = ?, expires_at = ?, created_at = ?
                WHERE id = ?
            ");
            if ($stmtUpdate === false) {
                return ['success' => false, 'message' => 'Unable to claim slot.'];
            }
            $now = $this->now();
            $claimId = (int)$existing['id'];
            $stmtUpdate->bind_param("sssi", $note, $expiresAt, $now, $claimId);
            $ok = $stmtUpdate->execute();
            $stmtUpdate->close();
            if (!$ok) {
                return ['success' => false, 'message' => 'Unable to claim slot.'];
            }
            $claim = $this->getClaimById($claimId);
            return ['success' => true, 'claim_id' => $claimId, 'claim' => $claim];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO tribe_operation_claims (operation_id, user_id, role, note, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Unable to claim slot.'];
        }
        $stmt->bind_param("iisss", $operationId, $userId, $claimRole, $note, $expiresAt);
        $ok = $stmt->execute();
        $claimId = $stmt->insert_id;
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Unable to claim slot.'];
        }

        $claim = $this->getClaimById((int)$claimId);
        return ['success' => true, 'claim_id' => (int)$claimId, 'claim' => $claim];
    }

    private function getClaimById(int $claimId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT c.*, u.username
            FROM tribe_operation_claims c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.id = ?
            LIMIT 1
        ");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("i", $claimId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'operation_id' => (int)$row['operation_id'],
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'] ?? null,
            'role' => $row['role'],
            'note' => $row['note'] ?? '',
            'expires_at' => $row['expires_at'],
            'created_at' => $row['created_at'],
            'is_expired' => $this->isExpired($row['expires_at'] ?? null),
        ];
    }

    public function releaseClaim(int $tribeId, int $userId, string $role, int $claimId): array
    {
        $claim = $this->getClaimById($claimId);
        if (!$claim) {
            return ['success' => false, 'message' => 'Claim not found.'];
        }
        $operation = $this->getOperationById($claim['operation_id']);
        if (!$operation || $operation['tribe_id'] !== $tribeId) {
            return ['success' => false, 'message' => 'Claim not found for this tribe.'];
        }

        $canManage = in_array($role, self::MANAGE_ROLES, true);
        if ($claim['user_id'] !== $userId && !$canManage) {
            return ['success' => false, 'message' => 'No permission to remove claim.'];
        }

        $stmt = $this->conn->prepare("DELETE FROM tribe_operation_claims WHERE id = ?");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Unable to remove claim.'];
        }
        $stmt->bind_param("i", $claimId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok ? ['success' => true] : ['success' => false, 'message' => 'Unable to remove claim.'];
    }
}
