<?php
/**
 * Migration: Add military units system columns to unit_types table
 * 
 * Adds:
 * - category: Unit classification (infantry, cavalry, ranged, siege, scout, support, conquest)
 * - rps_bonuses: JSON column for rock-paper-scissors combat modifiers
 * - special_abilities: JSON column for unit abilities (aura, siege, conquest)
 * - aura_config: JSON column for support unit aura configuration
 * 
 * Requirements: 1.4, 6.3, 14.2
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Starting unit_types table military columns migration...\n";

/**
 * Check if a column exists on a table
 */
function columnExists($conn, string $table, string $column): bool
{
    $result = $conn->query("PRAGMA table_info($table)");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['name'] === $column) {
                return true;
            }
        }
    }
    return false;
}

try {
    // Define columns to add
    $columns = [
        'category' => "TEXT DEFAULT 'infantry'",
        'rps_bonuses' => "TEXT DEFAULT NULL",  // JSON stored as TEXT in SQLite
        'special_abilities' => "TEXT DEFAULT NULL",  // JSON stored as TEXT in SQLite
        'aura_config' => "TEXT DEFAULT NULL",  // JSON stored as TEXT in SQLite
    ];
    
    echo "\nAdding columns to unit_types table...\n";
    
    foreach ($columns as $column => $definition) {
        if (columnExists($conn, 'unit_types', $column)) {
            echo " - Column $column already exists, skipping.\n";
        } else {
            $sql = "ALTER TABLE unit_types ADD COLUMN $column $definition";
            if ($conn->query($sql)) {
                echo " - Added $column\n";
            } else {
                echo " [!] Failed to add $column: " . $conn->error . "\n";
            }
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Columns added to unit_types table:\n";
    echo "  - category (default: 'infantry')\n";
    echo "  - rps_bonuses (JSON, nullable)\n";
    echo "  - special_abilities (JSON, nullable)\n";
    echo "  - aura_config (JSON, nullable)\n";
    echo "\nNote: JSON columns are stored as TEXT in SQLite.\n";
    
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
