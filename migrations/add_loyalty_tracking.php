<?php
/**
 * Migration: Add loyalty tracking column to villages table
 * 
 * This migration adds the last_loyalty_update column to track when
 * village loyalty was last modified. This is useful for:
 * - Loyalty regeneration systems
 * - Analytics and reporting
 * - Debugging conquest issues
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'];

echo "Starting loyalty tracking migration...\n";

try {
    // Check if column already exists
    $result = $conn->query("SHOW COLUMNS FROM villages LIKE 'last_loyalty_update'");
    
    if ($result && $result->num_rows > 0) {
        echo "Column 'last_loyalty_update' already exists. Skipping migration.\n";
        exit(0);
    }
    
    // Add the column
    echo "Adding 'last_loyalty_update' column to villages table...\n";
    $conn->query("ALTER TABLE villages ADD COLUMN last_loyalty_update TIMESTAMP NULL DEFAULT NULL");
    
    // Initialize the column for existing villages with current timestamp
    echo "Initializing last_loyalty_update for existing villages...\n";
    $conn->query("UPDATE villages SET last_loyalty_update = CURRENT_TIMESTAMP WHERE last_loyalty_update IS NULL");
    
    echo "Migration completed successfully!\n";
    echo "- Added 'last_loyalty_update' column to villages table\n";
    echo "- Initialized timestamps for existing villages\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
