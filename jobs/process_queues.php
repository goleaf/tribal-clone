<?php
declare(strict_types=1);

// CLI helper to process queues/attacks/trades and inactivity cleanup.

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

$root = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? $root;
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/jobs/process_queues.php';

require $root . '/init.php';
require_once $root . '/lib/managers/BuildingConfigManager.php';
require_once $root . '/lib/managers/BuildingManager.php';
require_once $root . '/lib/managers/VillageManager.php';
require_once $root . '/lib/managers/BattleManager.php';
require_once $root . '/lib/managers/NotificationManager.php';
require_once $root . '/lib/managers/TribeManager.php';
require_once $root . '/lib/managers/CronRunner.php';

$start = microtime(true);

$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$villageManager = new VillageManager($conn);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);
$notificationManager = new NotificationManager($conn);
$tribeManager = new TribeManager($conn);
$cron = new CronRunner($conn, $villageManager, $battleManager, $notificationManager, $tribeManager);

$summary = $cron->run();
$duration = microtime(true) - $start;

echo "Processed {$summary['villages_processed']} village(s)\n";
echo "Completed task messages: {$summary['task_messages']}\n";
echo "Attack resolutions: {$summary['attack_messages']}\n";
if (!empty($summary['abandoned_converted'])) {
    echo "Converted {$summary['abandoned_converted']} village(s) to barbarian due to inactivity\n";
}
if (!empty($summary['wars_started'])) {
    echo "Wars started after prep: {$summary['wars_started']}\n";
}
if (!empty($summary['tribes_disbanded'])) {
    echo "Auto-disbanded {$summary['tribes_disbanded']} inactive tribe(s)\n";
}
echo "Elapsed: " . number_format($duration, 3) . "s\n";
