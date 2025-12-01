<?php
declare(strict_types=1);

// CLI helper to process all queues (build, recruit, research, trade) and resolve finished attacks.
// Intended for cron: php jobs/process_queues.php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

$root = dirname(__DIR__);
// Provide minimal server globals for init.php when running via CLI
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? $root;
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/jobs/process_queues.php';
require $root . '/init.php';

require_once $root . '/lib/managers/BuildingConfigManager.php';
require_once $root . '/lib/managers/BuildingManager.php';
require_once $root . '/lib/managers/VillageManager.php';
require_once $root . '/lib/managers/BattleManager.php';
require_once $root . '/lib/managers/NotificationManager.php';

$start = microtime(true);
$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);
$notificationManager = new NotificationManager($conn);

// Fetch all villages with owners (skip barbarian/system villages with user_id <= 0)
$villagesStmt = $conn->prepare("SELECT id, user_id FROM villages WHERE user_id > 0");
$villagesStmt->execute();
$villagesRes = $villagesStmt->get_result();
$villages = $villagesRes ? $villagesRes->fetch_all(MYSQLI_ASSOC) : [];
$villagesStmt->close();

$processedVillages = 0;
$taskMessages = 0;
$attackMessages = 0;
$userIds = [];
$abandonedCount = 0;

foreach ($villages as $village) {
    $vid = (int)$village['id'];
    $uid = (int)$village['user_id'];
    $userIds[$uid] = true;

    $messages = $villageManager->processCompletedTasksForVillage($vid);
    $processedVillages++;
    $taskMessages += count($messages);
    if (!empty($messages)) {
        foreach ($messages as $msg) {
            // Strip HTML tags before persisting
            $clean = trim(strip_tags($msg));
            $notificationManager->addNotification(
                $uid,
                $clean,
                'info',
                '/game/game.php'
            );
        }
    }
}

// Process attacks for every user that has at least one village
foreach (array_keys($userIds) as $uid) {
    $messages = $battleManager->processCompletedAttacks($uid);
    $attackMessages += count($messages);
    if (!empty($messages)) {
        $notificationManager->addNotification(
            $uid,
            sprintf('%d attack(s) resolved while you were away.', count($messages)),
            'info',
            '/messages/reports.php'
        );
    }
}

// Convert inactive player villages to barbarian (requires last_activity_at column)
if (defined('INACTIVE_TO_BARBARIAN_DAYS') && dbColumnExists($conn, 'users', 'last_activity_at')) {
    $abandonedCount = convertInactivePlayersToBarbarians($conn, (int)INACTIVE_TO_BARBARIAN_DAYS);
}

$duration = microtime(true) - $start;

echo "Processed {$processedVillages} village(s)\n";
echo "Completed task messages: {$taskMessages}\n";
echo "Attack resolutions: {$attackMessages}\n";
if ($abandonedCount > 0) {
    echo "Converted {$abandonedCount} village(s) to barbarian due to inactivity\n";
}
echo "Elapsed: " . number_format($duration, 3) . "s\n";

/**
 * Demotes villages of players inactive for a configurable number of days.
 */
function convertInactivePlayersToBarbarians($conn, int $days): int {
    if ($days <= 0) return 0;

    $isSQLite = is_object($conn) && method_exists($conn, 'getPdo');
    $thresholdSql = $isSQLite
        ? "datetime('now', '-{$days} days')"
        : "DATE_SUB(NOW(), INTERVAL {$days} DAY)";

    $stmt = $conn->prepare("
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
        // Convert all villages of this user to barbarian (-1)
        $stmtVillages = $conn->prepare("SELECT id, x_coord, y_coord FROM villages WHERE user_id = ?");
        $stmtVillages->bind_param("i", $uid);
        $stmtVillages->execute();
        $villages = $stmtVillages->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtVillages->close();

        foreach ($villages as $v) {
            $vid = (int)$v['id'];
            $name = sprintf("Abandoned (%d|%d)", $v['x_coord'], $v['y_coord']);

            $stmtUpdate = $conn->prepare("UPDATE villages SET user_id = -1, name = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $name, $vid);
            if ($stmtUpdate->execute()) {
                $converted++;
            }
            $stmtUpdate->close();
        }
    }

    return $converted;
}
