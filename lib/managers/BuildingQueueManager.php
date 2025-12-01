<?php
declare(strict_types=1);

/**
 * BuildingQueueManager
 * 
 * Manages the building construction queue with support for:
 * - Multiple queued items per village
 * - Immediate resource deduction
 * - Sequential processing (only one active build at a time)
 * - Automatic promotion of pending items when active completes
 */
class BuildingQueueManager
{
    private $conn;
    private BuildingConfigManager $configManager;
    private int $maxQueueItems;
    private int $baseSlots;
    private int $hqMilestoneStep;
    private int $maxSlots;
    private string $logFile;

    public function __construct($conn, BuildingConfigManager $configManager)
    {
        $this->conn = $conn;
        $this->configManager = $configManager;
        $this->maxQueueItems = defined('BUILDING_QUEUE_MAX_ITEMS') ? (int)BUILDING_QUEUE_MAX_ITEMS : 10;
        $this->baseSlots = defined('BUILDING_BASE_QUEUE_SLOTS') ? (int)BUILDING_BASE_QUEUE_SLOTS : 1;
        $this->hqMilestoneStep = defined('BUILDING_HQ_MILESTONE_STEP') ? (int)BUILDING_HQ_MILESTONE_STEP : 5;
        $this->maxSlots = defined('BUILDING_MAX_QUEUE_SLOTS') ? (int)BUILDING_MAX_QUEUE_SLOTS : 3;
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $this->logFile = $logDir . '/build_queue.log';
    }

    /**
     * Enqueue a building upgrade
     */
    public function enqueueBuild(int $villageId, string $buildingInternalName, int $userId): array
    {
        try {
            $this->conn->begin_transaction();
            $errorCode = null;

            // Lock village row for update
            $stmt = $this->conn->prepare("
                SELECT v.*, u.is_protected 
                FROM villages v 
                JOIN users u ON u.id = v.user_id
                WHERE v.id = ? AND v.user_id = ?
            ");
            $stmt->bind_param("ii", $villageId, $userId);
            $stmt->execute();
            $village = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$village) {
                $errorCode = 'ERR_PREREQ';
                throw new Exception("Village not found or access denied.");
            }

            // Get current building level
            $currentLevel = $this->getBuildingLevel($villageId, $buildingInternalName);
            if ($currentLevel < 0) {
                $currentLevel = 0;
            }

            // Enforce max level cap
            $maxLevel = $this->configManager->getMaxLevel($buildingInternalName);
            if ($maxLevel !== null && $currentLevel >= $maxLevel) {
                $errorCode = 'ERR_CAP';
                throw new Exception("{$buildingInternalName} is already at max level ({$maxLevel}).");
            }
            
            // Calculate costs and time
            $costs = $this->configManager->calculateUpgradeCost($buildingInternalName, $currentLevel);
            $hqLevel = $this->getBuildingLevel($villageId, 'main_building');
            $buildTime = $this->configManager->calculateUpgradeTime($buildingInternalName, $currentLevel, $hqLevel);

            if (!$costs || $buildTime === null) {
                $errorCode = 'ERR_PREREQ';
                throw new Exception("Unable to calculate upgrade cost or time.");
            }

            // Check queue capacity (active + pending)
            $queueCount = $this->getQueueCount($villageId);
            $slotLimit = $this->getQueueSlotLimit($hqLevel);
            if ($queueCount >= $this->maxQueueItems) {
                $errorCode = 'ERR_CAP';
                throw new Exception("Build queue is full (max {$this->maxQueueItems}).");
            }
            if ($queueCount >= $slotLimit) {
                $errorCode = 'ERR_QUEUE_CAP';
                throw new Exception("No free building queue slots. Increase Town Hall level to unlock more slots.");
            }

            // Check resources
            if (!$this->hasResources($village, $costs)) {
                $errorCode = 'ERR_RES';
                throw new Exception("Not enough resources.");
            }

            // Protected zones may forbid wall builds (e.g., beginner protection worlds)
            $blockWallInProtection = defined('SAFE_ZONE_BLOCK_WALL') ? (bool)SAFE_ZONE_BLOCK_WALL : true;
            if ($blockWallInProtection && $buildingInternalName === 'wall' && !empty($village['is_protected'])) {
                $errorCode = 'ERR_PROTECTED';
                throw new Exception("Cannot build Wall while under protection.");
            }

            // Deduct resources immediately
            $this->deductResources($villageId, $costs);

            $hasQueuedBuild = $queueCount > 0;
            $now = time();
            $tailFinish = $this->getQueueTailFinishTime($villageId);

            $startAt = $hasQueuedBuild ? max($tailFinish ?? $now, $now) : $now;
            $status = $hasQueuedBuild ? 'pending' : 'active';
            $finishAt = $startAt + $buildTime;
            $nextLevel = $currentLevel + 1;

            // Get village_building_id
            $villageBuildingId = $this->getVillageBuildingId($villageId, $buildingInternalName);
            if (!$villageBuildingId) {
                throw new Exception("Building not found in village.");
            }

            // Get building_type_id
            $buildingTypeId = $this->getBuildingTypeId($buildingInternalName);
            if (!$buildingTypeId) {
                throw new Exception("Building type not found.");
            }

            // Insert queue item
            $queueItemId = $this->insertQueueItem([
                'village_id' => $villageId,
                'village_building_id' => $villageBuildingId,
                'building_type_id' => $buildingTypeId,
                'level' => $nextLevel,
                'starts_at' => date('Y-m-d H:i:s', $startAt),
                'finish_time' => date('Y-m-d H:i:s', $finishAt),
                'status' => $status
            ]);

            // Rebalance queue to eliminate gaps after insert
            $this->rebalanceQueue($villageId);

            $inserted = $this->getQueueItemById($queueItemId);
            $finalStatus = $inserted['status'] ?? $status;
            $finalStart = isset($inserted['starts_at']) ? strtotime($inserted['starts_at']) : $startAt;
            $finalFinish = isset($inserted['finish_time']) ? strtotime($inserted['finish_time']) : $finishAt;

            $this->conn->commit();

            return [
                'success' => true,
                'queue_item_id' => $queueItemId,
                'status' => $finalStatus,
                'start_at' => $finalStart,
                'finish_at' => $finalFinish,
                'level' => $nextLevel,
                'building_internal_name' => $buildingInternalName
            ];

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logQueueEvent('enqueue_failed', [
                'village_id' => $villageId,
                'building' => $buildingInternalName,
                'level' => $currentLevel,
                'hq_level' => $hqLevel,
                'slot_limit' => $slotLimit,
                'queue_count' => $queueCount,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'code' => $errorCode
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $errorCode
            ];
        }
    }

    /**
     * Process completed builds and promote next pending item
     */
    public function onBuildComplete(int $queueItemId): array
    {
        try {
            $this->conn->begin_transaction();

            // Get queue item
            $stmt = $this->conn->prepare("SELECT * FROM building_queue WHERE id = ?");
            $stmt->bind_param("i", $queueItemId);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$item) {
                throw new Exception("Queue item not found.");
            }

            // Idempotent guard
            if ($item['status'] !== 'active' || strtotime($item['finish_time']) > time()) {
                $this->conn->rollback();
                $this->logQueueEvent('complete_failed', [
                    'queue_item_id' => $queueItemId,
                    'reason' => 'not_ready'
                ]);
                return ['success' => false, 'message' => 'Build not ready or already completed.'];
            }

            // Apply effect: increment building level
            $stmt = $this->conn->prepare("UPDATE village_buildings SET level = level + 1 WHERE id = ? AND village_id = ?");
            $stmt->bind_param("ii", $item['village_building_id'], $item['village_id']);
            $stmt->execute();
            $stmt->close();

            // Mark as completed
            $stmt = $this->conn->prepare("UPDATE building_queue SET status = ? WHERE id = ?");
            $status = 'completed';
            $stmt->bind_param("si", $status, $queueItemId);
            $stmt->execute();
            $stmt->close();

            // Promote and resequence any remaining queue
            $this->rebalanceQueue($item['village_id']);

            $this->conn->commit();

            $this->logQueueEvent('complete', [
                'queue_item_id' => $queueItemId,
                'village_id' => $item['village_id'],
                'building_type_id' => $item['building_type_id'],
                'level' => $item['level']
            ]);

            return [
                'success' => true,
                'next_item_id' => $this->getActiveQueueItemId($item['village_id'])
            ];

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logQueueEvent('complete_failed', [
                'queue_item_id' => $queueItemId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel a queued build and refund resources
     */
    public function cancelBuild(int $queueItemId, int $userId): array
    {
        try {
            $this->conn->begin_transaction();

            $stmt = $this->conn->prepare("SELECT * FROM building_queue WHERE id = ?");
            $stmt->bind_param("i", $queueItemId);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$item) {
                throw new Exception("Queue item not found.");
            }

            // Verify ownership
            $stmt = $this->conn->prepare("SELECT * FROM villages WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $item['village_id'], $userId);
            $stmt->execute();
            $village = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$village) {
                throw new Exception("Access denied.");
            }

            // Calculate refund (90%)
            $buildingInternalName = $this->getBuildingInternalNameById($item['building_type_id']);
            $costs = $this->configManager->calculateUpgradeCost($buildingInternalName, $item['level'] - 1);
            
            $refund = null;
            if ($costs) {
                $refund = [
                    'wood' => (int)floor($costs['wood'] * 0.9),
                    'clay' => (int)floor($costs['clay'] * 0.9),
                    'iron' => (int)floor($costs['iron'] * 0.9)
                ];
                $this->refundResources($item['village_id'], $refund);
            }

            // If canceling active item, promote next pending
            $wasActive = ($item['status'] === 'active');
            
            // Delete queue item
            $stmt = $this->conn->prepare("DELETE FROM building_queue WHERE id = ?");
            $stmt->bind_param("i", $queueItemId);
            $stmt->execute();
            $stmt->close();

            $this->rebalanceQueue($item['village_id']);

            $this->conn->commit();

            return ['success' => true, 'refund' => $refund];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ========== Private Helper Methods ==========

    private function getQueueItemById(int $queueItemId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM building_queue WHERE id = ?");
        $stmt->bind_param("i", $queueItemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function getQueueCount(int $villageId): int
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM building_queue WHERE village_id = ? AND (status IS NULL OR status IN ('active','pending'))");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['cnt'] : 0;
    }

    private function getQueueTailFinishTime(int $villageId): ?int
    {
        $stmt = $this->conn->prepare("SELECT finish_time FROM building_queue WHERE village_id = ? AND (status IS NULL OR status IN ('active','pending')) ORDER BY finish_time DESC LIMIT 1");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row && isset($row['finish_time']) ? strtotime($row['finish_time']) : null;
    }

    private function getActiveQueueItemId(int $villageId): ?int
    {
        $active = $this->getActiveQueueItem($villageId);
        return $active ? (int)$active['id'] : null;
    }

    private function getActiveQueueItem(int $villageId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM building_queue WHERE village_id = ? AND (status = 'active' OR status IS NULL) ORDER BY starts_at ASC LIMIT 1");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function getNextPendingItem(int $villageId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM building_queue WHERE village_id = ? AND status = 'pending' ORDER BY starts_at ASC, id ASC LIMIT 1");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function getPendingItems(int $villageId): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM building_queue WHERE village_id = ? AND status = 'pending' ORDER BY starts_at ASC, id ASC");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    }

    private function calculateDuration(array $item): int
    {
        $startTs = isset($item['starts_at']) ? strtotime($item['starts_at']) : null;
        $finishTs = isset($item['finish_time']) ? strtotime($item['finish_time']) : null;
        if (!$finishTs) {
            return 1;
        }

        $duration = $finishTs - ($startTs ?? $finishTs);
        return max(1, $duration);
    }

    private function updateQueueItemTiming(int $queueItemId, int $startAt, int $finishAt, ?string $status = null): void
    {
        if ($status) {
            $stmt = $this->conn->prepare("UPDATE building_queue SET starts_at = ?, finish_time = ?, status = ? WHERE id = ?");
            $startsAt = date('Y-m-d H:i:s', $startAt);
            $finishTime = date('Y-m-d H:i:s', $finishAt);
            $stmt->bind_param("sssi", $startsAt, $finishTime, $status, $queueItemId);
        } else {
            $stmt = $this->conn->prepare("UPDATE building_queue SET starts_at = ?, finish_time = ? WHERE id = ?");
            $startsAt = date('Y-m-d H:i:s', $startAt);
            $finishTime = date('Y-m-d H:i:s', $finishAt);
            $stmt->bind_param("ssi", $startsAt, $finishTime, $queueItemId);
        }
        $stmt->execute();
        $stmt->close();
    }

    private function rebalanceQueue(int $villageId): void
    {
        $now = time();

        $active = $this->getActiveQueueItem($villageId);
        if (!$active) {
            $next = $this->getNextPendingItem($villageId);
            if ($next) {
                $duration = $this->calculateDuration($next);
                $startAt = $now;
                $finishAt = $startAt + $duration;
                $this->updateQueueItemTiming((int)$next['id'], $startAt, $finishAt, 'active');
                $active = $this->getActiveQueueItem($villageId);
            }
        }

        $baseStart = $active ? max(strtotime($active['finish_time']), $now) : $now;
        $pendingItems = $this->getPendingItems($villageId);
        foreach ($pendingItems as $item) {
            $duration = $this->calculateDuration($item);
            $startAt = $baseStart;
            $finishAt = $startAt + $duration;
            $this->updateQueueItemTiming((int)$item['id'], $startAt, $finishAt, 'pending');
            $baseStart = $finishAt;
        }
    }

    private function getBuildingLevel(int $villageId, string $internalName): int
    {
        $stmt = $this->conn->prepare("
            SELECT vb.level FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = ?
        ");
        $stmt->bind_param("is", $villageId, $internalName);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ? (int)$result['level'] : 0;
    }

    private function hasResources(array $village, array $costs): bool
    {
        return $village['wood'] >= $costs['wood'] &&
               $village['clay'] >= $costs['clay'] &&
               $village['iron'] >= $costs['iron'];
    }

    private function deductResources(int $villageId, array $costs): void
    {
        $stmt = $this->conn->prepare("UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ? WHERE id = ?");
        $stmt->bind_param("iiii", $costs['wood'], $costs['clay'], $costs['iron'], $villageId);
        $stmt->execute();
        $stmt->close();
    }

    private function refundResources(int $villageId, array $refund): void
    {
        $stmt = $this->conn->prepare("UPDATE villages SET wood = wood + ?, clay = clay + ?, iron = iron + ? WHERE id = ?");
        $stmt->bind_param("iiii", $refund['wood'], $refund['clay'], $refund['iron'], $villageId);
        $stmt->execute();
        $stmt->close();
    }

    private function getBuildQueue(int $villageId): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM building_queue WHERE village_id = ? ORDER BY starts_at ASC");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    private function getLastQueueItem(array $queue): ?array
    {
        if (empty($queue)) {
            return null;
        }
        
        // Find last active or pending item
        foreach (array_reverse($queue) as $item) {
            if (in_array($item['status'], ['active', 'pending'])) {
                return $item;
            }
        }
        
        return null;
    }

    /**
     * Calculate allowed concurrent queue slots based on HQ level milestones.
     */
    private function getQueueSlotLimit(int $hqLevel): int
    {
        $hqLevel = max(0, $hqLevel);
        $slots = $this->baseSlots;
        if ($this->hqMilestoneStep > 0 && $hqLevel > 0) {
            $slots += (int)floor(($hqLevel - 1) / $this->hqMilestoneStep);
        }
        return max(1, min($this->maxSlots, $slots));
    }

    private function getVillageBuildingId(int $villageId, string $internalName): ?int
    {
        $stmt = $this->conn->prepare("
            SELECT vb.id FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = ?
        ");
        $stmt->bind_param("is", $villageId, $internalName);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ? (int)$result['id'] : null;
    }

    private function getBuildingTypeId(string $internalName): ?int
    {
        $stmt = $this->conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
        $stmt->bind_param("s", $internalName);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ? (int)$result['id'] : null;
    }

    private function getBuildingInternalNameById(int $buildingTypeId): ?string
    {
        $stmt = $this->conn->prepare("SELECT internal_name FROM building_types WHERE id = ?");
        $stmt->bind_param("i", $buildingTypeId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ? $result['internal_name'] : null;
    }

    private function insertQueueItem(array $data): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO building_queue 
            (village_id, village_building_id, building_type_id, level, starts_at, finish_time, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiiisss",
            $data['village_id'],
            $data['village_building_id'],
            $data['building_type_id'],
            $data['level'],
            $data['starts_at'],
            $data['finish_time'],
            $data['status']
        );
        $stmt->execute();
        $id = $this->conn->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Get all queue items for a village (for display)
     */
    public function getVillageQueue(int $villageId): array
    {
        return $this->getBuildQueue($villageId);
    }

    /**
     * Process all completed builds across all villages (for cron job)
     */
    public function processCompletedBuilds(): array
    {
        $processed = [];
        
        $stmt = $this->conn->prepare("SELECT id FROM building_queue WHERE status = 'active' AND finish_time <= NOW()");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $processResult = $this->onBuildComplete($row['id']);
            $processed[] = [
                'queue_item_id' => $row['id'],
                'result' => $processResult
            ];
        }
        
        $stmt->close();
        return $processed;
    }

    /**
     * Append queue event to audit log.
     */
    private function logQueueEvent(string $type, array $payload): void
    {
        $payload['type'] = $type;
        $payload['ts'] = $payload['ts'] ?? time();
        $payload['date'] = date('c', $payload['ts']);
        $line = json_encode($payload);
        if ($line === false) {
            return;
        }
        @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND);
    }
}
