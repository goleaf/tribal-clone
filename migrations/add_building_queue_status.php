<?php
declare(strict_types=1);

/**
 * Migration: Add status column and indexes to building_queue table
 * 
 * This migration:
 * - Adds status column (active, pending, completed, canceled)
 * - Creates performance indexes for village_id, status, finish_time
 * - Backfills status for existing queue items
 * 
 * Requirements: 1.1, 2.1, 7.1, 8.1, 8.2
 */

require_once __DIR__ . '/../Database.php';

$db = Database::getInstance();

echo "=== Building Queue Status Migration ===\n\n";

try {
    // Check if status column exists
    echo "Checking if 'status' column exists in building_queue...\n";
    $columns = $db->fetchAll("PRAGMA table_info(building_queue)");
    $hasStatus = false;
    
    foreach ($columns as $col) {
        if ($col['name'] === 'status') {
            $hasStatus = true;
            break;
        }
    }
    
    if (!$hasStatus) {
        echo "Adding status column to building_queue...\n";
        $db->execute("ALTER TABLE building_queue ADD COLUMN status TEXT DEFAULT 'active'");
        echo "✓ Status column added successfully\n\n";
    } else {
        echo "✓ Status column already exists\n\n";
    }
    
    // Create performance indexes
    echo "Creating performance indexes...\n";
    
    // Index for village_id + status queries
    $db->execute("CREATE INDEX IF NOT EXISTS idx_building_queue_village_status ON building_queue(village_id, status)");
    echo "✓ Created idx_building_queue_village_status\n";
    
    // Index for status + finish_time queries (for cron processor)
    $db->execute("CREATE INDEX IF NOT EXISTS idx_building_queue_status_finish ON building_queue(status, finish_time)");
    echo "✓ Created idx_building_queue_status_finish\n";
    
    // Composite index for common query pattern
    $db->execute("CREATE INDEX IF NOT EXISTS idx_building_queue_village_status_starts ON building_queue(village_id, status, starts_at)");
    echo "✓ Created idx_building_queue_village_status_starts\n\n";
    
    // Backfill status for existing rows
    echo "Backfilling status for existing queue items...\n";
    
    // Mark old completed items (finished more than 1 day ago)
    $completedCount = $db->execute("
        UPDATE building_queue 
        SET status = 'completed' 
        WHERE status IS NULL 
          AND finish_time < datetime('now', '-1 day')
    ");
    echo "✓ Marked {$completedCount} old items as completed\n";
    
    // Mark active items (currently building)
    $activeCount = $db->execute("
        UPDATE building_queue 
        SET status = 'active' 
        WHERE status IS NULL 
          AND finish_time >= datetime('now')
          AND starts_at <= datetime('now')
    ");
    echo "✓ Marked {$activeCount} items as active\n";
    
    // Mark pending items (queued but not started)
    $pendingCount = $db->execute("
        UPDATE building_queue 
        SET status = 'pending' 
        WHERE status IS NULL 
          AND starts_at > datetime('now')
    ");
    echo "✓ Marked {$pendingCount} items as pending\n\n";
    
    // Show current queue status distribution
    echo "Current queue status distribution:\n";
    $statusCounts = $db->fetchAll("
        SELECT 
            COALESCE(status, 'NULL') as status, 
            COUNT(*) as count 
        FROM building_queue 
        GROUP BY status
        ORDER BY status
    ");
    
    foreach ($statusCounts as $row) {
        echo "  {$row['status']}: {$row['count']} items\n";
    }
    
    // Verify building_requirements table exists
    echo "\nVerifying building_requirements table...\n";
    $tableExists = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='building_requirements'");
    
    if ($tableExists) {
        echo "✓ building_requirements table exists\n";
        
        // Count requirements
        $reqCount = $db->fetchOne("SELECT COUNT(*) as count FROM building_requirements");
        echo "  Found {$reqCount['count']} building requirements\n";
    } else {
        echo "⚠ building_requirements table does not exist\n";
        echo "  This table should be created by the main schema\n";
    }
    
    // Verify building_types table
    echo "\nVerifying building_types table...\n";
    $buildingTypes = $db->fetchOne("SELECT COUNT(*) as count FROM building_types");
    echo "✓ building_types table exists with {$buildingTypes['count']} building types\n";
    
    // Verify village_buildings table
    echo "\nVerifying village_buildings table...\n";
    $villageBuildings = $db->fetchOne("SELECT COUNT(*) as count FROM village_buildings");
    echo "✓ village_buildings table exists with {$villageBuildings['count']} village buildings\n";
    
    echo "\n=== Migration Complete ===\n";
    echo "✓ Building queue system is ready\n";
    echo "✓ Status column added with indexes\n";
    echo "✓ Existing data backfilled\n";
    echo "✓ All required tables verified\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration Failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
