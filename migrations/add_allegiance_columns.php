<?php
declare(strict_types=1);

/**
 * Migration: Add allegiance/conquest tracking columns to villages.
 *
 * Adds:
 * - allegiance (INT, 0-100 control meter; default 100)
 * - allegiance_last_update (DATETIME for regen)
 * - capture_cooldown_until (DATETIME anti-rebound)
 * - anti_snipe_until (DATETIME grace period after capture)
 * - allegiance_floor (INT floor during anti-snipe)
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Running allegiance columns migration...\n";

/**
 * Check if a column exists on a table (SQLite or MySQL).
 */
function columnExistsAllegiance($conn, string $table, string $column): bool
{
    // SQLite path
    if (class_exists('SQLiteAdapter') && $conn instanceof SQLiteAdapter) {
        $stmt = $conn->prepare("PRAGMA table_info($table)");
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
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
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    return false;
}

function addColumn($conn, string $table, string $column, string $definition): void
{
    if (columnExistsAllegiance($conn, $table, $column)) {
        echo " - Column $column already exists, skipping.\n";
        return;
    }
    $sql = "ALTER TABLE $table ADD COLUMN $column $definition";
    $ok = $conn->query($sql);
    if ($ok === false) {
        echo " [!] Failed to add $column: " . $conn->error . "\n";
    } else {
        echo " - Added $column\n";
    }
}

addColumn($conn, 'villages', 'allegiance', "INTEGER NOT NULL DEFAULT 100");
addColumn($conn, 'villages', 'allegiance_last_update', "DATETIME DEFAULT CURRENT_TIMESTAMP");
addColumn($conn, 'villages', 'capture_cooldown_until', "DATETIME DEFAULT NULL");
addColumn($conn, 'villages', 'anti_snipe_until', "DATETIME DEFAULT NULL");
addColumn($conn, 'villages', 'allegiance_floor', "INTEGER NOT NULL DEFAULT 0");

echo "Allegiance columns migration completed.\n";
