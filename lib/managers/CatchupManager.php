<?php
declare(strict_types=1);

/**
 * Catch-up buff manager - grants temporary production multipliers for late joiners or rebuilds.
 */
class CatchupManager
{
    private $conn;
    private WorldManager $worldManager;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->worldManager = new WorldManager($conn);
        $this->ensureColumns();
    }

    private function ensureColumns(): void
    {
        if (function_exists('dbColumnExists')) {
            if (!dbColumnExists($this->conn, 'users', 'catchup_expires_at')) {
                $this->conn->query("ALTER TABLE users ADD COLUMN catchup_expires_at DATETIME NULL");
            }
            if (!dbColumnExists($this->conn, 'users', 'catchup_multiplier')) {
                $this->conn->query("ALTER TABLE users ADD COLUMN catchup_multiplier REAL NOT NULL DEFAULT 1.0");
            }
        }
    }

    /**
     * Determine if a user should receive a catch-up buff.
     */
    public function shouldGrant(int $userId): bool
    {
        $settings = $this->worldManager->getSettings(CURRENT_WORLD_ID);
        $mult = (float)($settings['catchup_multiplier'] ?? 1.0);
        $durationHours = (int)($settings['catchup_duration_hours'] ?? 0);
        if ($mult <= 1.0 || $durationHours <= 0) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT created_at, points, catchup_expires_at FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return false;

        // New or wiped players: points below a threshold
        $pointsCap = defined('CATCHUP_POINTS_CAP') ? (int)CATCHUP_POINTS_CAP : 500;
        if ((int)($row['points'] ?? 0) > $pointsCap) {
            return false;
        }

        // Only grant if not already active
        if (!empty($row['catchup_expires_at'])) {
            $expiresTs = strtotime($row['catchup_expires_at']);
            if ($expiresTs !== false && $expiresTs > time()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Grant a catch-up buff to a user.
     */
    public function grant(int $userId): bool
    {
        if (!$this->shouldGrant($userId)) {
            return false;
        }
        $settings = $this->worldManager->getSettings(CURRENT_WORLD_ID);
        $mult = (float)($settings['catchup_multiplier'] ?? 1.0);
        $durationHours = (int)($settings['catchup_duration_hours'] ?? 0);
        $expiresAt = date('Y-m-d H:i:s', time() + ($durationHours * 3600));

        $stmt = $this->conn->prepare("UPDATE users SET catchup_multiplier = ?, catchup_expires_at = ? WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param("dsi", $mult, $expiresAt, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Get current multiplier for a user.
     */
    public function getMultiplier(int $userId): float
    {
        $stmt = $this->conn->prepare("SELECT catchup_multiplier, catchup_expires_at FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return 1.0;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return 1.0;
        }
        if (!empty($row['catchup_expires_at'])) {
            $expires = strtotime($row['catchup_expires_at']);
            if ($expires !== false && $expires < time()) {
                return 1.0;
            }
        }
        return max(1.0, (float)($row['catchup_multiplier'] ?? 1.0));
    }
}
