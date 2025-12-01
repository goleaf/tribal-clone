<?php
declare(strict_types=1);

/**
 * Migration: Add conquest resource columns to villages table
 *
 * Adds:
 * - noble_coins: Number of noble coins available for training nobles
 * - standards: Number of standards available for training standard bearers
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Running conquest resources migration...\n";

/**
 * Check if a column exists on a table
 */
function columnExistsConquest($conn, string $table, string $column): bool
{
    // Try MySQL first
    $stmt = $conn->prepare("SHOW COLUMNS FROM $table LIKE ?");
    if ($stmt) {
        $stmt->bind_param("s", $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    
    // Fallback for SQLite
    $result = $conn->query("PRAGMA table_info($table)");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }
    }
    return false;
}

function addConquestColumn($conn, string $table, string $column, string $definition): void
{
    if (columnExistsConquest($conn, $table, $column)) {
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

echo "\nAdding conquest resource columns to villages table...\n";

// Add noble_coins column for training nobles/noblemen
addConquestColumn($conn, 'villages', 'noble_coins', "INTEGER NOT NULL DEFAULT 0");

// Add standards column for training standard bearers
addConquestColumn($conn, 'villages', 'standards', "INTEGER NOT NULL DEFAULT 0");

echo "\nConquest resources migration completed.\n";
echo "Note: Villages start with 0 noble_coins and 0 standards.\n";
echo "Use the minting/crafting system to create these resources before training conquest units.\n";
