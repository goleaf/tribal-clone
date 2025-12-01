<?php
declare(strict_types=1);

/**
 * Migration: Add conquest_attempts audit log table
 * 
 * Creates the conquest_attempts table for tracking all conquest attempts
 * with detailed metadata for debugging and analysis.
 * 
 * Requirements: 7.1, 8.4
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Starting conquest_attempts table migration...\n";

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
function indexExists($conn, string $index): bool
{
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='index' AND name='$index'");
    return $result && $result->num_rows > 0;
}

try {
    // Create conquest_attempts table
    echo "\n1. Creating conquest_attempts table...\n";
    
    if (tableExists($conn, 'conquest_attempts')) {
        echo " - Table conquest_attempts already exists, skipping.\n";
    } else {
        $sql = "CREATE TABLE conquest_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp INTEGER NOT NULL,
            world_id INTEGER NOT NULL,
            attacker_id INTEGER NOT NULL,
            defender_id INTEGER NOT NULL,
            village_id INTEGER NOT NULL,
            surviving_envoys INTEGER NOT NULL,
            allegiance_before INTEGER NOT NULL,
            allegiance_after INTEGER NOT NULL,
            drop_amount INTEGER NOT NULL,
            captured INTEGER NOT NULL DEFAULT 0,
            reason_code TEXT,
            wall_level INTEGER,
            modifiers TEXT,
            resolution_order INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE,
            FOREIGN KEY (attacker_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (defender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE
        )";
        
        if ($conn->query($sql)) {
            echo " - Created conquest_attempts table\n";
        } else {
            echo " [!] Failed to create conquest_attempts: " . $conn->error . "\n";
            exit(1);
        }
    }
    
    // Create indexes for conquest_attempts
    echo "\n2. Creating indexes for conquest_attempts...\n";
    
    $indexes = [
        'idx_conquest_village_time' => "CREATE INDEX idx_conquest_village_time ON conquest_attempts(village_id, timestamp DESC)",
        'idx_conquest_attacker_time' => "CREATE INDEX idx_conquest_attacker_time ON conquest_attempts(attacker_id, timestamp DESC)",
        'idx_conquest_defender_time' => "CREATE INDEX idx_conquest_defender_time ON conquest_attempts(defender_id, timestamp DESC)",
        'idx_conquest_world_time' => "CREATE INDEX idx_conquest_world_time ON conquest_attempts(world_id, timestamp DESC)",
        'idx_conquest_captured' => "CREATE INDEX idx_conquest_captured ON conquest_attempts(captured, timestamp DESC)"
    ];
    
    foreach ($indexes as $indexName => $sql) {
        if (indexExists($conn, $indexName)) {
            echo " - Index $indexName already exists, skipping.\n";
        } else {
            if ($conn->query($sql)) {
                echo " - Created index $indexName\n";
            } else {
                echo " [!] Failed to create index $indexName: " . $conn->error . "\n";
            }
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Created:\n";
    echo "  - conquest_attempts table\n";
    echo "  - Performance indexes for conquest queries\n";
    
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
