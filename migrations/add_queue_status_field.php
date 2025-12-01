<?php
/**
 * Migration: Add status field to building_queue table
 * 
 * This migration ensures the building_queue table has the status field
 * required for the refactored queue system.
 * 
 * Run: php migrations/add_queue_status_field.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../config/config.php';

echo "=== Building Queue Status Field Migration ===\n\n";

try {
    $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $database->getConnection();
    
    echo "Connected to database: " . DB_NAME . "\n\n";
    
    // Check if status column exists
    echo "Checking if 'status' column exists in building_queue...\n";
    $result = $conn->query("PRAGMA table_info(building_queue)");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['name'];
    }
    
    $hasStatus = in_array('status', $columns, true);
    
    if ($hasStatus) {
        echo "✓ Status column already exists\n\n";
    } else {
        echo "✗ Status column missing, adding it now...\n";
        
        // Add status column with default value
        $conn->query("ALTER TABLE building_queue ADD COLUMN status TEXT DEFAULT 'active'");
        
        echo "✓ Status column added successfully\n\n";
    }
    
    // Update existing rows without status
    echo "Updating existing queue items...\n";
    $conn->query("UPDATE building_queue SET status = 'active' WHERE status IS NULL");
    $affected = $conn->affected_rows;
    echo "✓ Updated {$affected} rows\n\n";
    
    // Check for items that should be marked as completed
    echo "Checking for items that should be marked as completed...\n";
    $result = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM building_queue 
        WHERE finish_time < datetime('now', '-1 day')
          AND (status IS NULL OR status = 'active')
    ");
    $row = $result->fetch_assoc();
    $oldItems = $row['cnt'] ?? 0;
    
    if ($oldItems > 0) {
        echo "Found {$oldItems} old items (finished > 24h ago)\n";
        echo "Marking them as completed...\n";
        $conn->query("
            UPDATE building_queue 
            SET status = 'completed' 
            WHERE finish_time < datetime('now', '-1 day')
              AND (status IS NULL OR status = 'active')
        ");
        echo "✓ Marked {$oldItems} old items as completed\n\n";
    } else {
        echo "✓ No old items found\n\n";
    }
    
    // Show current queue status
    echo "Current queue status:\n";
    $result = $conn->query("
        SELECT status, COUNT(*) as cnt 
        FROM building_queue 
        GROUP BY status
    ");
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'] ?? 'NULL';
        $count = $row['cnt'];
        echo "  {$status}: {$count} items\n";
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "✓ Building queue table is ready\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
}
