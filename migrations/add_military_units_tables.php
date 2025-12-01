<?php
/**
 * Migration: Add military units system tables
 * 
 * Creates:
 * - seasonal_units: Tracks time-limited event units with availability windows
 * - elite_unit_caps: Enforces per-account caps on elite and seasonal units
 * 
 * Requirements: 10.1, 10.2, 9.2
 */

require_once __DIR__ . '/../init.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Starting military units tables migration...\n";
    
    // Create seasonal_units table
    echo "Creating seasonal_units table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS seasonal_units (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            unit_internal_name TEXT NOT NULL UNIQUE,
            event_name TEXT NOT NULL,
            start_timestamp INTEGER NOT NULL,
            end_timestamp INTEGER NOT NULL,
            per_account_cap INTEGER DEFAULT 50,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create index for seasonal window queries
    $db->exec("
        CREATE INDEX IF NOT EXISTS idx_seasonal_active_window 
        ON seasonal_units(is_active, start_timestamp, end_timestamp)
    ");
    
    echo "✓ seasonal_units table created\n";
    
    // Create elite_unit_caps table
    echo "Creating elite_unit_caps table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS elite_unit_caps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            unit_internal_name TEXT NOT NULL,
            current_count INTEGER NOT NULL DEFAULT 0,
            last_updated INTEGER NOT NULL,
            UNIQUE(user_id, unit_internal_name),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Create index for cap lookups
    $db->exec("
        CREATE INDEX IF NOT EXISTS idx_elite_caps_user 
        ON elite_unit_caps(user_id, unit_internal_name)
    ");
    
    echo "✓ elite_unit_caps table created\n";
    
    echo "\nMigration completed successfully!\n";
    echo "Tables created:\n";
    echo "  - seasonal_units (with idx_seasonal_active_window)\n";
    echo "  - elite_unit_caps (with idx_elite_caps_user)\n";
    
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
