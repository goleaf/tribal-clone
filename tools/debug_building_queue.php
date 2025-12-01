<?php
/**
 * Building Queue Debugger
 * 
 * This script provides detailed information about the building queue
 * for debugging purposes.
 * 
 * Usage: php tools/debug_building_queue.php [village_id]
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../config/config.php';

$villageId = isset($argv[1]) ? (int)$argv[1] : null;

echo "=== Building Queue Debugger ===\n\n";

try {
    $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $database->getConnection();
    
    // Overall statistics
    echo "OVERALL STATISTICS\n";
    echo str_repeat("-", 50) . "\n";
    
    $result = $conn->query("
        SELECT 
            status,
            COUNT(*) as count,
            MIN(finish_time) as earliest,
            MAX(finish_time) as latest
        FROM building_queue
        GROUP BY status
    ");
    
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'] ?? 'NULL';
        echo "Status: {$status}\n";
        echo "  Count: {$row['count']}\n";
        echo "  Earliest: {$row['earliest']}\n";
        echo "  Latest: {$row['latest']}\n\n";
    }
    
    // Items ready to process
    echo "\nITEMS READY TO PROCESS\n";
    echo str_repeat("-", 50) . "\n";
    
    $result = $conn->query("
        SELECT bq.*, bt.name, bt.internal_name, v.name as village_name
        FROM building_queue bq
        JOIN building_types bt ON bq.building_type_id = bt.id
        JOIN villages v ON v.id = bq.village_id
        WHERE bq.status = 'active'
          AND bq.finish_time <= datetime('now')
        ORDER BY bq.finish_time ASC
    ");
    
    $readyCount = 0;
    while ($row = $result->fetch_assoc()) {
        $readyCount++;
        $finishedAgo = time() - strtotime($row['finish_time']);
        $finishedAgoStr = gmdate("H:i:s", $finishedAgo);
        echo "ID {$row['id']}: {$row['village_name']} (#{$row['village_id']})\n";
        echo "  Building: {$row['name']} → Level {$row['level']}\n";
        echo "  Finished: {$finishedAgoStr} ago\n";
        echo "  Status: {$row['status']}\n\n";
    }
    
    if ($readyCount === 0) {
        echo "✓ No items ready to process\n\n";
    }
    
    // Village-specific details
    if ($villageId) {
        echo "\nVILLAGE #{$villageId} DETAILS\n";
        echo str_repeat("-", 50) . "\n";
        
        // Village info
        $stmt = $conn->prepare("SELECT * FROM villages WHERE id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $village = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$village) {
            echo "✗ Village not found\n";
        } else {
            echo "Village: {$village['name']}\n";
            echo "Resources: Wood={$village['wood']}, Clay={$village['clay']}, Iron={$village['iron']}\n\n";
            
            // Queue items
            echo "Queue Items:\n";
            $stmt = $conn->prepare("
                SELECT bq.*, bt.name, bt.internal_name
                FROM building_queue bq
                JOIN building_types bt ON bq.building_type_id = bt.id
                WHERE bq.village_id = ?
                ORDER BY bq.starts_at ASC
            ");
            $stmt->bind_param("i", $villageId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $queueItems = [];
            while ($row = $result->fetch_assoc()) {
                $queueItems[] = $row;
            }
            $stmt->close();
            
            if (empty($queueItems)) {
                echo "  (empty queue)\n";
            } else {
                foreach ($queueItems as $idx => $item) {
                    $num = $idx + 1;
                    $status = $item['status'] ?? 'NULL';
                    $startTime = strtotime($item['starts_at']);
                    $finishTime = strtotime($item['finish_time']);
                    $now = time();
                    
                    $startIn = $startTime - $now;
                    $finishIn = $finishTime - $now;
                    
                    $startStr = $startIn > 0 ? "starts in " . gmdate("H:i:s", $startIn) : "started";
                    $finishStr = $finishIn > 0 ? "finishes in " . gmdate("H:i:s", $finishIn) : "finished";
                    
                    echo "\n  #{$num} - {$item['name']} → Level {$item['level']}\n";
                    echo "      Status: {$status}\n";
                    echo "      Start: {$item['starts_at']} ({$startStr})\n";
                    echo "      Finish: {$item['finish_time']} ({$finishStr})\n";
                }
            }
            
            echo "\n";
            
            // Building levels
            echo "Current Building Levels:\n";
            $stmt = $conn->prepare("
                SELECT bt.name, bt.internal_name, vb.level
                FROM village_buildings vb
                JOIN building_types bt ON vb.building_type_id = bt.id
                WHERE vb.village_id = ?
                  AND vb.level > 0
                ORDER BY bt.name ASC
            ");
            $stmt->bind_param("i", $villageId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                echo "  {$row['name']}: Level {$row['level']}\n";
            }
            $stmt->close();
        }
    }
    
    // Potential issues
    echo "\n\nPOTENTIAL ISSUES\n";
    echo str_repeat("-", 50) . "\n";
    
    $issues = [];
    
    // Check for items with NULL status
    $result = $conn->query("SELECT COUNT(*) as cnt FROM building_queue WHERE status IS NULL");
    $row = $result->fetch_assoc();
    if ($row['cnt'] > 0) {
        $issues[] = "⚠ {$row['cnt']} items with NULL status (should be 'active', 'pending', or 'completed')";
    }
    
    // Check for very old active items
    $result = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM building_queue 
        WHERE status = 'active' 
          AND finish_time < datetime('now', '-1 hour')
    ");
    $row = $result->fetch_assoc();
    if ($row['cnt'] > 0) {
        $issues[] = "⚠ {$row['cnt']} active items finished over 1 hour ago (may need processing)";
    }
    
    // Check for multiple active items in same village
    $result = $conn->query("
        SELECT village_id, COUNT(*) as cnt
        FROM building_queue
        WHERE status = 'active'
        GROUP BY village_id
        HAVING cnt > 1
    ");
    while ($row = $result->fetch_assoc()) {
        $issues[] = "⚠ Village {$row['village_id']} has {$row['cnt']} active items (should be max 1)";
    }
    
    if (empty($issues)) {
        echo "✓ No issues detected\n";
    } else {
        foreach ($issues as $issue) {
            echo "{$issue}\n";
        }
    }
    
    echo "\n=== Debug Complete ===\n";
    
} catch (Exception $e) {
    echo "\n✗ Debug Failed: " . $e->getMessage() . "\n";
    exit(1);
}
