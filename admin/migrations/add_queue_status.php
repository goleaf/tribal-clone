<?php
declare(strict_types=1);

/**
 * Migration: Add status column to building_queue table
 * 
 * This enables proper queue management with pending/active/completed/canceled states
 */

require_once __DIR__ . '/../../init.php';

try {
    echo "Adding status column to building_queue table...\n";
    
    // Check if column already exists
    $result = $conn->query("PRAGMA table_info(building_queue)");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    
    $hasStatus = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'status') {
            $hasStatus = true;
            break;
        }
    }
    
    if (!$hasStatus) {
        // Add status column with default 'active' for backward compatibility
        $conn->query("
            ALTER TABLE building_queue 
            ADD COLUMN status TEXT NOT NULL DEFAULT 'active'
        ");
        
        echo "✓ Status column added successfully\n";
        
        // Create index for faster queries
        $conn->query("
            CREATE INDEX IF NOT EXISTS idx_building_queue_status 
            ON building_queue(village_id, status, starts_at)
        ");
        
        echo "✓ Index created successfully\n";
    } else {
        echo "✓ Status column already exists\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
