<?php
declare(strict_types=1);

/**
 * Migration: Add conquest configuration columns to worlds table
 *
 * Adds world-specific conquest settings for both allegiance-drop and control-uptime modes
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Running conquest world configuration migration...\n";

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

// Conquest configuration columns for worlds table (SQLite syntax)
$conquestColumns = [
    // Core settings
    'conquest_enabled' => "INTEGER NOT NULL DEFAULT 1",
    'conquest_mode' => "TEXT NOT NULL DEFAULT 'allegiance'",
    
    // Allegiance/Control mechanics
    'alleg_regen_per_hour' => "REAL NOT NULL DEFAULT 2.0",
    'alleg_wall_reduction_per_level' => "REAL NOT NULL DEFAULT 0.02",
    'alleg_drop_min' => "INTEGER NOT NULL DEFAULT 18",
    'alleg_drop_max' => "INTEGER NOT NULL DEFAULT 28",
    
    // Anti-snipe protection
    'anti_snipe_floor' => "INTEGER NOT NULL DEFAULT 10",
    'anti_snipe_seconds' => "INTEGER NOT NULL DEFAULT 900",
    'post_capture_start' => "INTEGER NOT NULL DEFAULT 25",
    'capture_cooldown_seconds' => "INTEGER NOT NULL DEFAULT 900",
    
    // Control/Uptime mode settings
    'uptime_duration_seconds' => "INTEGER NOT NULL DEFAULT 900",
    'control_gain_rate_per_min' => "INTEGER NOT NULL DEFAULT 5",
    'control_decay_rate_per_min' => "INTEGER NOT NULL DEFAULT 3",
    
    // Wave mechanics
    'wave_spacing_ms' => "INTEGER NOT NULL DEFAULT 300",
    'max_envoys_per_command' => "INTEGER NOT NULL DEFAULT 1",
    
    // Training limits
    'conquest_daily_mint_cap' => "INTEGER NOT NULL DEFAULT 5",
    'conquest_daily_train_cap' => "INTEGER NOT NULL DEFAULT 3",
    'conquest_min_defender_points' => "INTEGER NOT NULL DEFAULT 1000",
    
    // Post-capture settings
    'conquest_building_loss_enabled' => "INTEGER NOT NULL DEFAULT 0",
    'conquest_building_loss_chance' => "REAL NOT NULL DEFAULT 0.100",
    'conquest_resource_transfer_pct' => "REAL NOT NULL DEFAULT 1.000",
    
    // Abandonment decay
    'conquest_abandonment_decay_enabled' => "INTEGER NOT NULL DEFAULT 0",
    'conquest_abandonment_threshold_hours' => "INTEGER NOT NULL DEFAULT 168",
    'conquest_abandonment_decay_rate' => "REAL NOT NULL DEFAULT 1.0",
];

echo "\nAdding conquest configuration columns to worlds table...\n";

foreach ($conquestColumns as $column => $definition) {
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

// Update existing worlds with default values
echo "\nBackfilling default values for existing worlds...\n";
$defaults = [
    'conquest_enabled' => 1,
    'conquest_mode' => "'allegiance'",
    'alleg_regen_per_hour' => 2.0,
    'alleg_wall_reduction_per_level' => 0.02,
    'alleg_drop_min' => 18,
    'alleg_drop_max' => 28,
    'anti_snipe_floor' => 10,
    'anti_snipe_seconds' => 900,
    'post_capture_start' => 25,
    'capture_cooldown_seconds' => 900,
    'uptime_duration_seconds' => 900,
    'control_gain_rate_per_min' => 5,
    'control_decay_rate_per_min' => 3,
    'wave_spacing_ms' => 300,
    'max_envoys_per_command' => 1,
    'conquest_daily_mint_cap' => 5,
    'conquest_daily_train_cap' => 3,
    'conquest_min_defender_points' => 1000,
    'conquest_building_loss_enabled' => 0,
    'conquest_building_loss_chance' => 0.100,
    'conquest_resource_transfer_pct' => 1.000,
    'conquest_abandonment_decay_enabled' => 0,
    'conquest_abandonment_threshold_hours' => 168,
    'conquest_abandonment_decay_rate' => 1.0,
];

foreach ($defaults as $col => $val) {
    $conn->query("UPDATE worlds SET {$col} = {$val} WHERE {$col} IS NULL");
}

echo "\nConquest world configuration migration completed.\n";
