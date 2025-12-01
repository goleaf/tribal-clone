<?php
declare(strict_types=1);

/**
 * Manages wounded troop recovery via Hospital.
 */
class HospitalManager
{
    private $conn;

    // Configurable knobs
    private const BASE_RECOVERY_PERCENT = 0.1; // 10% at Hospital level 1
    private const RECOVERY_PER_LEVEL = 0.02;   // +2% per level
    private const MAX_RECOVERY_PERCENT = 0.5;  // 50% cap
    private const MAX_POOL_PER_HOSPITAL_LEVEL = 200; // wounded capacity per level

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->ensureColumn();
    }

    /**
     * Add wounded to the village pool, capped by hospital level capacity.
     */
    public function addWounded(int $villageId, int $wounded): void
    {
        if ($wounded <= 0) {
            return;
        }

        $current = $this->getWoundedPool($villageId);
        $cap = $this->getWoundedCap($villageId);
        $newPool = min($cap, $current + $wounded);

        $stmt = $this->conn->prepare("UPDATE villages SET wounded_pool = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $newPool, $villageId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Heal wounded into healthy units, returning count healed.
     * Assumes unit composition handling is done elsewhere; this just drains the pool.
     */
    public function healWounded(int $villageId): int
    {
        $pool = $this->getWoundedPool($villageId);
        if ($pool <= 0) {
            return 0;
        }

        $percent = $this->getRecoveryPercent($villageId);
        $healed = (int)floor($pool * $percent);
        if ($healed <= 0) {
            return 0;
        }

        $stmt = $this->conn->prepare("UPDATE villages SET wounded_pool = wounded_pool - ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $healed, $villageId);
            $stmt->execute();
            $stmt->close();
        }

        return $healed;
    }

    /**
     * Current wounded pool.
     */
    public function getWoundedPool(int $villageId): int
    {
        $stmt = $this->conn->prepare("SELECT wounded_pool FROM villages WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['wounded_pool'] ?? 0);
    }

    private function getWoundedCap(int $villageId): int
    {
        $level = $this->getHospitalLevel($villageId);
        return $level * self::MAX_POOL_PER_HOSPITAL_LEVEL;
    }

    private function getRecoveryPercent(int $villageId): float
    {
        $level = $this->getHospitalLevel($villageId);
        $percent = self::BASE_RECOVERY_PERCENT + ($level - 1) * self::RECOVERY_PER_LEVEL;
        return min(self::MAX_RECOVERY_PERCENT, max(0.0, $percent));
    }

    private function getHospitalLevel(int $villageId): int
    {
        $stmt = $this->conn->prepare("
            SELECT vb.level
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = 'hospital'
            LIMIT 1
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['level'] ?? 0);
    }

    private function ensureColumn(): void
    {
        try {
            $this->conn->query("ALTER TABLE villages ADD COLUMN wounded_pool INT NOT NULL DEFAULT 0");
        } catch (\Throwable $e) {
            // Ignore if exists
        }
    }
}
