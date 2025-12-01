<?php
declare(strict_types=1);

/**
 * Daily/weekly tasks backend with reroll and claim state.
 */
class TaskManager
{
    private $conn;
    private string $logFile;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->ensureTables();
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $this->logFile = $logDir . '/tasks.log';
    }

    private function ensureTables(): void
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS user_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                task_key TEXT NOT NULL,
                task_type TEXT NOT NULL, -- daily/weekly
                progress INTEGER NOT NULL DEFAULT 0,
                target INTEGER NOT NULL DEFAULT 0,
                reward_json TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active', -- active, completed, claimed, expired
                expires_at TEXT NOT NULL,
                rerolls_used INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, task_key, task_type)
            )
        ");
        // Helpful indexes for lookups/expiry
        $this->conn->query("CREATE INDEX IF NOT EXISTS idx_user_tasks_user_type ON user_tasks(user_id, task_type)");
        $this->conn->query("CREATE INDEX IF NOT EXISTS idx_user_tasks_expiry ON user_tasks(expires_at)");
    }

    public function getTasks(int $userId, string $type = 'daily'): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM user_tasks
            WHERE user_id = ? AND task_type = ?
            ORDER BY created_at ASC
        ");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param("is", $userId, $type);
        $stmt->execute();
        $res = $stmt->get_result();
        $tasks = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $this->expireTasksInMemory($tasks);
    }

    /**
     * Refresh tasks for a user: if none exist or all expired/claimed, seed new ones.
     *
     * @param array<int,array{key:string,target:int,reward:array}> $taskDefs
     */
    public function refreshTasks(int $userId, string $type, array $taskDefs, int $ttlHours, int $count = 3): array
    {
        $tasks = $this->getTasks($userId, $type);
        $now = time();
        $cycleExpiry = $this->getCycleExpiry($type, $ttlHours);
        $active = array_filter($tasks, static function ($t) use ($now) {
            return in_array($t['status'], ['active', 'completed'], true) && strtotime($t['expires_at']) > $now;
        });

        if (empty($active)) {
            $selected = $this->pickTasks($taskDefs, $count);
            $this->upsertTasks($userId, $selected, $type, $cycleExpiry);
            $tasks = $this->getTasks($userId, $type);
            $this->logEvent($userId, 'refresh_seed', [
                'type' => $type,
                'count' => count($selected),
                'expires_at' => $cycleExpiry
            ]);
        }

        return $tasks;
    }

    /**
     * Pick up to $count task definitions randomly without duplicates.
     *
     * @param array<int,array{key:string,target:int,reward:array}> $taskDefs
     */
    private function pickTasks(array $taskDefs, int $count): array
    {
        if ($count >= count($taskDefs)) {
            return $taskDefs;
        }
        $keys = array_rand($taskDefs, $count);
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        $selected = [];
        foreach ($keys as $k) {
            $selected[] = $taskDefs[$k];
        }
        return $selected;
    }

    /**
     * Expire tasks and return updated list (in-memory status patch).
     *
     * @param array<int,array> $tasks
     * @return array<int,array>
     */
    private function expireTasksInMemory(array $tasks): array
    {
        $now = time();
        foreach ($tasks as &$task) {
            if ($task['status'] !== 'claimed' && strtotime($task['expires_at']) < $now) {
                $task['status'] = 'expired';
            }
        }
        return $tasks;
    }

    /**
     * Compute cycle expiry aligned to daily/weekly boundaries.
     * Falls back to ttlHours from caller when alignment is not needed.
     */
    private function getCycleExpiry(string $type, int $ttlHours): int
    {
        $now = time();
        $dt = new DateTimeImmutable('now');
        if ($type === 'weekly') {
            // Next Monday 00:00 UTC
            $weekStart = $dt->modify('monday this week');
            $next = $weekStart <= $dt ? $weekStart->modify('+1 week') : $weekStart;
            return (int)$next->getTimestamp();
        }
        // Daily: next midnight UTC
        $tomorrow = $dt->modify('tomorrow');
        return $tomorrow ? (int)$tomorrow->getTimestamp() : $now + ($ttlHours * 3600);
    }

    public function upsertTasks(int $userId, array $taskDefs, string $type, int $expiresAt): void
    {
        foreach ($taskDefs as $def) {
            $stmt = $this->conn->prepare("
                INSERT INTO user_tasks (user_id, task_key, task_type, progress, target, reward_json, status, expires_at)
                VALUES (?, ?, ?, 0, ?, ?, 'active', ?)
                ON CONFLICT(user_id, task_key, task_type) DO UPDATE SET expires_at = excluded.expires_at, status = 'active', progress = 0, rerolls_used = 0
            ");
            if ($stmt) {
                $rewardJson = json_encode($def['reward'] ?? []);
                $expiresSql = date('Y-m-d H:i:s', $expiresAt);
                $stmt->bind_param("ississ", $userId, $def['key'], $type, $def['target'], $rewardJson, $expiresSql);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    public function addProgress(int $userId, string $taskKey, string $type, int $amount): void
    {
        $stmt = $this->conn->prepare("
            UPDATE user_tasks
            SET progress = MIN(target, progress + ?),
                updated_at = CURRENT_TIMESTAMP,
                status = CASE WHEN progress + ? >= target THEN 'completed' ELSE status END
            WHERE user_id = ? AND task_key = ? AND task_type = ? AND status IN ('active', 'completed')
        ");
        if ($stmt) {
            $stmt->bind_param("isiis", $amount, $amount, $userId, $taskKey, $type);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function claimTask(int $userId, string $taskKey, string $type): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, reward_json, status FROM user_tasks
            WHERE user_id = ? AND task_key = ? AND task_type = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Task not found.'];
        }
        $stmt->bind_param("iss", $userId, $taskKey, $type);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return ['success' => false, 'message' => 'Task not found.'];
        }
        if ($row['status'] !== 'completed') {
            return ['success' => false, 'message' => 'Task not completed yet.'];
        }

        $update = $this->conn->prepare("UPDATE user_tasks SET status = 'claimed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($update) {
            $update->bind_param("i", $row['id']);
            $update->execute();
            $update->close();
        }
        $this->logEvent($userId, 'claim', ['task_key' => $taskKey, 'type' => $type]);
        return [
            'success' => true,
            'reward' => json_decode($row['reward_json'] ?? '[]', true)
        ];
    }

    public function rerollTask(int $userId, string $taskKey, string $type, array $newDef, int $maxRerolls = 1): array
    {
        $stmt = $this->conn->prepare("SELECT rerolls_used, expires_at FROM user_tasks WHERE user_id = ? AND task_key = ? AND task_type = ? LIMIT 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Task not found.'];
        }
        $stmt->bind_param("iss", $userId, $taskKey, $type);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return ['success' => false, 'message' => 'Task not found.'];
        }
        if ((int)$row['rerolls_used'] >= $maxRerolls) {
            return ['success' => false, 'message' => 'No rerolls left.'];
        }

        $expiresAt = $row['expires_at'] ?? date('Y-m-d H:i:s', $this->getCycleExpiry($type, $type === 'weekly' ? 168 : 24));
        $stmtUp = $this->conn->prepare("
            UPDATE user_tasks
            SET task_key = ?, progress = 0, target = ?, reward_json = ?, status = 'active', expires_at = ?, rerolls_used = rerolls_used + 1
            WHERE user_id = ? AND task_key = ? AND task_type = ?
        ");
        if ($stmtUp) {
            $rewardJson = json_encode($newDef['reward'] ?? []);
            $stmtUp->bind_param("sississ", $newDef['key'], $newDef['target'], $rewardJson, $expiresAt, $userId, $taskKey, $type);
            $stmtUp->execute();
            $stmtUp->close();
        }

        $this->logEvent($userId, 'reroll', [
            'old_task_key' => $taskKey,
            'new_task_key' => $newDef['key'] ?? '',
            'type' => $type
        ]);

        return ['success' => true];
    }

    private function logEvent(int $userId, string $event, array $meta = []): void
    {
        $line = sprintf(
            "[%s] user=%d event=%s meta=%s\n",
            date('Y-m-d H:i:s'),
            $userId,
            $event,
            json_encode($meta)
        );
        @file_put_contents($this->logFile, $line, FILE_APPEND);
    }
}
