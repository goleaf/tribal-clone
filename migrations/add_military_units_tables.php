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

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Starting military units tables migration...\n";

/**
 * Check if a table exists
 */
function tableExists($conn, string $table): bool
{
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
    return $result && $result->num_rows > 0;
}

/**
 * Check if an index exists
 */
function indexExists($conn, string $table, string $index): bool
{
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='index' AND name='$index'");
    return $result && $result->num_rows > 0;
}

try {
    
    // Create seasonal_units table
    echo "\n1. Creating seasonal_units table...\n";
    
    if (tableExists($conn, 'seasonal_units')) {
        echo " - Table seasonal_units already exists, skipping.\n";
    } else {
        $sql = "CREATE TABLE seasonal_units (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            unit_internal_name TEXT NOT NULL UNIQUE,
            event_name TEXT NOT NULL,
            start_timestamp INTEGER NOT NULL,
            end_timestamp INTEGER NOT NULL,
            per_account_cap INTEGER DEFAULT 50,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql)) {
            echo " - Created seasonal_units table\n";
            
            // Create index for seasonal window queries
            if (!indexExists($conn, 'seasonal_units', 'idx_seasonal_active_window')) {
                $conn->query("CREATE INDEX idx_seasonal_active_window ON seasonal_units(is_active, start_timestamp, end_timestamp)");
                echo " - Created index idx_seasonal_active_window\n";
            }
        } else {
            echo " [!] Failed to create seasonal_units: " . $conn->error . "\n";
        }
    }
    
    // Create elite_unit_caps table
    echo "\n2. Creating elite_unit_caps table...\n";
    
    if (tableExists($conn, 'elite_unit_caps')) {
        echo " - Table elite_unit_caps already exists, skipping.\n";
    } else {
        $sql = "CREATE TABLE elite_unit_caps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            unit_internal_name TEXT NOT NULL,
            current_count INTEGER NOT NULL DEFAULT 0,
            last_updated INTEGER NOT NULL,
            UNIQUE(user_id, unit_internal_name),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        if ($conn->query($sql)) {
            echo " - Created elite_unit_caps table\n";
            
            // Create index for cap lookups
            if (!indexExists($conn, 'elite_unit_caps', 'idx_elite_caps_user')) {
                $conn->query("CREATE INDEX idx_elite_caps_user ON elite_unit_caps(user_id, unit_internal_name)");
                echo " - Created index idx_elite_caps_user\n";
            }
        } else {
            echo " [!] Failed to create elite_unit_caps: " . $conn->error . "\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Tables created:\n";
    echo "  - seasonal_units (with idx_seasonal_active_window)\n";
    echo "  - elite_unit_caps (with idx_elite_caps_user)\n";
    
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
