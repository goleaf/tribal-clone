<?php
declare(strict_types=1);

/**
 * Migration: Expand tribe diplomacy to support full state machine
 * (neutral, NAP, alliance, war, truce) with timers and cooldowns.
 */

require_once __DIR__ . '/../init.php';

if (!isset($conn)) {
    echo "No DB connection.\n";
    exit(1);
}

echo "Updating diplomacy schema...\n";

/**
 * Check if a column exists on a table (SQLite or MySQL).
 */
function columnExistsDiplomacy($conn, string $table, string $column): bool
{
    // SQLite path
    if ($conn instanceof SQLiteAdapter) {
        $stmt = $conn->prepare("PRAGMA table_info($table)");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }
        }
        return false;
    }

    // MySQL path
    $stmt = $conn->prepare("SHOW COLUMNS FROM $table LIKE ?");
    if ($stmt) {
        $stmt->bind_param("s", $column);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    return false;
}

/**
 * Add column if missing.
 */
function addColumnDiplomacy($conn, string $table, string $column, string $definition): void
{
    if (columnExistsDiplomacy($conn, $table, $column)) {
        echo " - Column $column already exists\n";
        return;
    }
    $sql = "ALTER TABLE $table ADD COLUMN $column $definition";
    $ok = $conn->query($sql);
    if ($ok === false) {
        echo " [!] Failed to add column $column: " . $conn->error . "\n";
    } else {
        echo " - Added column $column\n";
    }
}

// Ensure base table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS tribe_diplomacy (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tribe_id INTEGER NOT NULL,
        target_tribe_id INTEGER NOT NULL,
        status TEXT NOT NULL,
        created_by INTEGER NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(tribe_id, target_tribe_id)
    )
");

// Add new fields for timing, pending state, and cooldowns
addColumnDiplomacy($conn, 'tribe_diplomacy', 'is_pending', "INTEGER NOT NULL DEFAULT 0");
addColumnDiplomacy($conn, 'tribe_diplomacy', 'starts_at', "INTEGER DEFAULT NULL");
addColumnDiplomacy($conn, 'tribe_diplomacy', 'ends_at', "INTEGER DEFAULT NULL");
addColumnDiplomacy($conn, 'tribe_diplomacy', 'requested_by_user_id', "INTEGER DEFAULT NULL");
addColumnDiplomacy($conn, 'tribe_diplomacy', 'accepted_by_user_id', "INTEGER DEFAULT NULL");
addColumnDiplomacy($conn, 'tribe_diplomacy', 'reason', "TEXT DEFAULT ''");
addColumnDiplomacy($conn, 'tribe_diplomacy', 'cooldown_until', "INTEGER NOT NULL DEFAULT 0");
addColumnDiplomacy($conn, 'tribe_diplomacy', 'updated_at', "INTEGER NOT NULL DEFAULT (strftime('%s','now'))");

// Helpful indexes
$conn->query("CREATE INDEX IF NOT EXISTS idx_tribe_diplomacy_pair ON tribe_diplomacy(tribe_id, target_tribe_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_tribe_diplomacy_status ON tribe_diplomacy(status)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_tribe_diplomacy_updated ON tribe_diplomacy(updated_at)");

echo "Diplomacy schema updated.\n";
