<?php
declare(strict_types=1);

/**
 * Daily/weekly tasks backend with reroll and claim state.
 */
class TaskManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->ensureTables();
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

        $now = time();
        foreach ($tasks as &$task) {
            if ($task['status'] !== 'claimed' && strtotime($task['expires_at']) < $now) {
                $task['status'] = 'expired';
            }
        }
        return $tasks;
    }

    public function upsertTasks(int $userId, array $taskDefs, string $type, int $ttlHours): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlHours * 3600));
        foreach ($taskDefs as $def) {
            $stmt = $this->conn->prepare("
                INSERT INTO user_tasks (user_id, task_key, task_type, progress, target, reward_json, status, expires_at)
                VALUES (?, ?, ?, 0, ?, ?, 'active', ?)
                ON CONFLICT(user_id, task_key, task_type) DO UPDATE SET expires_at = excluded.expires_at, status = 'active', progress = 0, rerolls_used = 0
            ");
            if ($stmt) {
                $rewardJson = json_encode($def['reward'] ?? []);
                $stmt->bind_param("ississ", $userId, $def['key'], $type, $def['target'], $rewardJson, $expiresAt);
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
        return [
            'success' => true,
            'reward' => json_decode($row['reward_json'] ?? '[]', true)
        ];
    }

    public function rerollTask(int $userId, string $taskKey, string $type, array $newDef, int $maxRerolls = 1): array
    {
        $stmt = $this->conn->prepare("SELECT rerolls_used FROM user_tasks WHERE user_id = ? AND task_key = ? AND task_type = ? LIMIT 1");
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

        $expiresAt = date('Y-m-d H:i:s', time() + ($type === 'weekly' ? 7 * 86400 : 86400));
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

        return ['success' => true];
    }
}
