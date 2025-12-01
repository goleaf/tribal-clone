<?php
declare(strict_types=1);

/**
 * Coordinates periodic tasks: resources/queues/trade arrivals/attacks/inactivity cleanup.
 */
class CronRunner
{
    private $conn;
    private VillageManager $villageManager;
    private BattleManager $battleManager;
    private NotificationManager $notificationManager;

    public function __construct($conn, VillageManager $villageManager, BattleManager $battleManager, NotificationManager $notificationManager)
    {
        $this->conn = $conn;
        $this->villageManager = $villageManager;
        $this->battleManager = $battleManager;
        $this->notificationManager = $notificationManager;
    }

    public function run(): array
    {
        $summary = [
            'villages_processed' => 0,
            'task_messages' => 0,
            'attack_messages' => 0,
            'abandoned_converted' => 0
        ];

        $villagesStmt = $this->conn->prepare("SELECT id, user_id FROM villages WHERE user_id > 0");
        $villagesStmt->execute();
        $villagesRes = $villagesStmt->get_result();
        $villages = $villagesRes ? $villagesRes->fetch_all(MYSQLI_ASSOC) : [];
        $villagesStmt->close();

        $userIds = [];
        foreach ($villages as $village) {
            $vid = (int)$village['id'];
            $uid = (int)$village['user_id'];
            $userIds[$uid] = true;

            $messages = $this->villageManager->processCompletedTasksForVillage($vid);
            $summary['villages_processed']++;
            $summary['task_messages'] += count($messages);
        }

        foreach (array_keys($userIds) as $uid) {
            $messages = $this->battleManager->processCompletedAttacks($uid);
            $summary['attack_messages'] += count($messages);
            if (!empty($messages)) {
                $this->notificationManager->addNotification(
                    $uid,
                    sprintf('%d attack(s) resolved while you were away.', count($messages)),
                    'info',
                    '/messages/reports.php'
                );
            }
        }

        if (defined('INACTIVE_TO_BARBARIAN_DAYS') && dbColumnExists($this->conn, 'users', 'last_activity_at')) {
            $summary['abandoned_converted'] = $this->convertInactivePlayersToBarbarians((int)INACTIVE_TO_BARBARIAN_DAYS);
        }

        return $summary;
    }

    /**
     * Demotes villages of players inactive for a configurable number of days.
     */
    private function convertInactivePlayersToBarbarians(int $days): int
    {
        if ($days <= 0) return 0;

        $isSQLite = is_object($this->conn) && method_exists($this->conn, 'getPdo');
        $thresholdSql = $isSQLite
            ? "datetime('now', '-{$days} days')"
            : "DATE_SUB(NOW(), INTERVAL {$days} DAY)";

        $stmt = $this->conn->prepare("
            SELECT id FROM users 
            WHERE is_admin = 0 AND is_banned = 0 
              AND (last_activity_at IS NULL OR last_activity_at <= {$thresholdSql})
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $inactiveUsers = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        if (empty($inactiveUsers)) {
            return 0;
        }

        $converted = 0;
        foreach ($inactiveUsers as $userRow) {
            $uid = (int)$userRow['id'];
            $stmtVillages = $this->conn->prepare("SELECT id, x_coord, y_coord FROM villages WHERE user_id = ?");
            $stmtVillages->bind_param("i", $uid);
            $stmtVillages->execute();
            $villages = $stmtVillages->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtVillages->close();

            foreach ($villages as $v) {
                $vid = (int)$v['id'];
                $name = sprintf("Abandoned (%d|%d)", $v['x_coord'], $v['y_coord']);

                $stmtUpdate = $this->conn->prepare("UPDATE villages SET user_id = -1, name = ? WHERE id = ?");
                $stmtUpdate->bind_param("si", $name, $vid);
                if ($stmtUpdate->execute()) {
                    $converted++;
                }
                $stmtUpdate->close();
            }
        }

        return $converted;
    }
}
