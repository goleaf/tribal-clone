<?php
/**
 * Cleanup Completed Builds
 * 
 * This script archives or removes old completed building queue items
 * to keep the database clean and performant.
 * 
 * Run: php tools/cleanup_completed_builds.php [--dry-run] [--days=30]
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../config/config.php';

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv, true);
$daysToKeep = 30;

foreach ($argv as $arg) {
    if (strpos($arg, '--days=') === 0) {
        $daysToKeep = (int)substr($arg, 7);
    }
}

echo "=== Cleanup Completed Builds ===\n\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no changes)" : "LIVE") . "\n";
echo "Keeping completed items from last {$daysToKeep} days\n\n";

try {
    $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $database->getConnection();
    
    // Find old completed items
    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
    echo "Cutoff date: {$cutoffDate}\n\n";
    
    $stmt = $conn->prepare("
        SELECT bq.id, bq.village_id, bt.name, bq.level, bq.finish_time, bq.status
        FROM building_queue bq
        JOIN building_types bt ON bq.building_type_id = bt.id
        WHERE bq.status = 'completed'
          AND bq.finish_time < ?
        ORDER BY bq.finish_time ASC
    ");
    $stmt->bind_param("s", $cutoffDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $itemsToDelete = [];
    while ($row = $result->fetch_assoc()) {
        $itemsToDelete[] = $row;
    }
    $stmt->close();
    
    $count = count($itemsToDelete);
    
    if ($count === 0) {
        echo "✓ No old completed items found\n";
        echo "Database is clean!\n";
        exit(0);
    }
    
    echo "Found {$count} old completed items:\n\n";
    
    // Show items to be deleted
    foreach ($itemsToDelete as $item) {
        echo "  ID {$item['id']}: Village {$item['village_id']} - {$item['name']} Level {$item['level']} (finished: {$item['finish_time']})\n";
    }
    
    echo "\n";
    
    if ($dryRun) {
        echo "DRY RUN: Would delete {$count} items\n";
        echo "Run without --dry-run to actually delete them\n";
    } else {
        echo "Deleting {$count} items...\n";
        
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("DELETE FROM building_queue WHERE status = 'completed' AND finish_time < ?");
            $stmt->bind_param("s", $cutoffDate);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            
            $conn->commit();
            
            echo "✓ Deleted {$deleted} old completed items\n";
            
            // Show remaining stats
            $result = $conn->query("
                SELECT status, COUNT(*) as cnt 
                FROM building_queue 
                GROUP BY status
            ");
            
            echo "\nRemaining queue items:\n";
            while ($row = $result->fetch_assoc()) {
                $status = $row['status'] ?? 'NULL';
                $count = $row['cnt'];
                echo "  {$status}: {$count} items\n";
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
    
    echo "\n=== Cleanup Complete ===\n";
    
} catch (Exception $e) {
    echo "\n✗ Cleanup Failed: " . $e->getMessage() . "\n";
    exit(1);
}
