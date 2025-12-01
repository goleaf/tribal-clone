<?php
declare(strict_types=1);

/**
 * Migration: Add conquest system tables and columns
 *
 * Adds:
 * - conquest_attempts audit log table
 * - control_meter and uptime columns to villages
 * - Performance indexes for conquest queries
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Running conquest system migration...\n";

/**
 * Check if a column exists on a table
 */
function columnExists($conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("SHOW COLUMNS FROM $table LIKE ?");
    if ($stmt) {
        $stmt->bind_param("s", $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    return false;
}

/**
 * Check if a table exists
 */
function tableExists($conn, string $table): bool
{
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

/**
 * Check if an index exists
 */
function indexExists($conn, string $table, string $index): bool
{
    $result = $conn->query("SHOW INDEX FROM $table WHERE Key_name = '$index'");
    return $result && $result->num_rows > 0;
}

// Add control/uptime columns to villages table
echo "\n1. Adding control/uptime columns to villages...\n";

$villageColumns = [
    'control_meter' => "INTEGER NOT NULL DEFAULT 0",
    'uptime_started_at' => "DATETIME DEFAULT NULL",
];

foreach ($villageColumns as $column => $definition) {
    if (columnExists($conn, 'villages', $column)) {
        echo " - Column $column already exists, skipping.\n";
    } else {
        $sql = "ALTER TABLE villages ADD COLUMN $column $definition";
        if ($conn->query($sql)) {
            echo " - Added $column\n";
        } else {
            echo " [!] Failed to add $column: " . $conn->error . "\n";
        }
    }
}

// Create conquest_attempts audit log table
echo "\n2. Creating conquest_attempts audit log table...\n";

if (tableExists($conn, 'conquest_attempts')) {
    echo " - Table conquest_attempts already exists, skipping.\n";
} else {
    $sql = "CREATE TABLE conquest_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        world_id INTEGER NOT NULL,
        attacker_id INTEGER NOT NULL,
        defender_id INTEGER NOT NULL,
        village_id INTEGER NOT NULL,
        surviving_envoys INTEGER NOT NULL DEFAULT 0,
        allegiance_before INTEGER NOT NULL,
        allegiance_after INTEGER NOT NULL,
        drop_amount INTEGER NOT NULL DEFAULT 0,
        captured INTEGER NOT NULL DEFAULT 0,
        reason_code TEXT DEFAULT NULL,
        wall_level INTEGER DEFAULT NULL,
        modifiers TEXT DEFAULT NULL,
        FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
        FOREIGN KEY (attacker_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (defender_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql)) {
        echo " - Created conquest_attempts table\n";
        // Create indexes separately for SQLite
        $conn->query("CREATE INDEX idx_conquest_village_timestamp ON conquest_attempts(village_id, timestamp)");
        $conn->query("CREATE INDEX idx_conquest_attacker ON conquest_attempts(attacker_id)");
        $conn->query("CREATE INDEX idx_conquest_defender ON conquest_attempts(defender_id)");
        $conn->query("CREATE INDEX idx_conquest_world ON conquest_attempts(world_id)");
        echo " - Created indexes for conquest_attempts\n";
    } else {
        echo " [!] Failed to create conquest_attempts: " . $conn->error . "\n";
    }
}

// Add performance indexes
echo "\n3. Adding performance indexes...\n";

$indexes = [
    'idx_allegiance_update' => "CREATE INDEX idx_allegiance_update ON villages(allegiance_last_update)",
    'idx_capture_cooldown' => "CREATE INDEX idx_capture_cooldown ON villages(capture_cooldown_until)",
    'idx_anti_snipe' => "CREATE INDEX idx_anti_snipe ON villages(anti_snipe_until)",
    'idx_control_meter' => "CREATE INDEX idx_control_meter ON villages(control_meter)",
];

foreach ($indexes as $indexName => $sql) {
    if (indexExists($conn, 'villages', $indexName)) {
        echo " - Index $indexName already exists, skipping.\n";
    } else {
        if ($conn->query($sql)) {
            echo " - Created index $indexName\n";
        } else {
            echo " [!] Failed to create index $indexName: " . $conn->error . "\n";
        }
    }
}

echo "\nConquest system migration completed.\n";
