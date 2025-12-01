<?php
declare(strict_types=1);

/**
 * Building Queue Processor
 * 
 * Run this script via cron every minute to process completed builds:
 * * * * * * php /path/to/jobs/process_building_queue.php >> /path/to/logs/queue_processor.log 2>&1
 */

// Set CLI flag before init
$IS_CLI = true;

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingQueueManager.php';

$logFile = __DIR__ . '/../logs/queue_processor.log';

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logMessage("Starting queue processor...");
    
    $configManager = new BuildingConfigManager($conn);
    $queueManager = new BuildingQueueManager($conn, $configManager);
    
    $results = $queueManager->processCompletedBuilds();
    
    if (empty($results)) {
        logMessage("No completed builds to process.");
    } else {
        foreach ($results as $result) {
            if ($result['result']['success']) {
                logMessage("Processed queue item #{$result['queue_item_id']} successfully.");
                if ($result['result']['next_item_id']) {
                    logMessage("  → Promoted queue item #{$result['result']['next_item_id']} to active.");
                }
            } else {
                logMessage("Failed to process queue item #{$result['queue_item_id']}: {$result['result']['message']}");
            }
        }
    }
    
    logMessage("Queue processor finished.\n");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    exit(1);
}
