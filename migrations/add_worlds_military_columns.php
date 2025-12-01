<?php
/**
 * Migration: Add military units system columns to worlds table
 * 
 * Adds:
 * - Feature flag columns (conquest_units_enabled, seasonal_units_enabled, healer_enabled)
 * - Training multiplier columns (train_multiplier_inf, train_multiplier_cav, train_multiplier_rng, train_multiplier_siege)
 * - healer_recovery_cap column
 * 
 * Requirements: 11.1, 11.2, 15.5
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Starting worlds table military columns migration...\n";

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
        // Feature flags
        'conquest_units_enabled' => "INTEGER NOT NULL DEFAULT 1",
        'seasonal_units_enabled' => "INTEGER NOT NULL DEFAULT 1",
        'healer_enabled' => "INTEGER NOT NULL DEFAULT 0",
        
        // Training multipliers by archetype
        'train_multiplier_inf' => "REAL NOT NULL DEFAULT 1.0",
        'train_multiplier_cav' => "REAL NOT NULL DEFAULT 1.0",
        'train_multiplier_rng' => "REAL NOT NULL DEFAULT 1.0",
        'train_multiplier_siege' => "REAL NOT NULL DEFAULT 1.0",
        
        // Healer recovery cap
        'healer_recovery_cap' => "REAL NOT NULL DEFAULT 0.15",
    ];
    
    echo "\nAdding columns to worlds table...\n";
    
    foreach ($columns as $column => $definition) {
        if (columnExists($conn, 'worlds', $column)) {
            echo " - Column $column already exists, skipping.\n";
        } else {
            $sql = "ALTER TABLE worlds ADD COLUMN $column $definition";
            if ($conn->query($sql)) {
                echo " - Added $column\n";
            } else {
                echo " [!] Failed to add $column: " . $conn->error . "\n";
            }
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Columns added to worlds table:\n";
    echo "  Feature flags:\n";
    echo "    - conquest_units_enabled (default: 1)\n";
    echo "    - seasonal_units_enabled (default: 1)\n";
    echo "    - healer_enabled (default: 0)\n";
    echo "  Training multipliers:\n";
    echo "    - train_multiplier_inf (default: 1.0)\n";
    echo "    - train_multiplier_cav (default: 1.0)\n";
    echo "    - train_multiplier_rng (default: 1.0)\n";
    echo "    - train_multiplier_siege (default: 1.0)\n";
    echo "  Healer settings:\n";
    echo "    - healer_recovery_cap (default: 0.15)\n";
    
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
