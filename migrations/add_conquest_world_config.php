<?php
declare(strict_types=1);

/**
 * Migration: Add conquest system configuration columns to worlds table
 * 
 * Adds all conquest-related configuration fields to support both allegiance-drop
 * and control-uptime conquest modes with full configurability per world.
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/functions.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection available.\n";
    exit(1);
}

echo "Adding conquest configuration columns to worlds table...\n";

$isSqlite = is_object($conn) && method_exists($conn, 'getPdo');

// Define all conquest configuration columns
$columns = [
    // Core conquest feature flags
    'conquest_enabled' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_enabled INTEGER NOT NULL DEFAULT 1"
        : "ALTER TABLE worlds ADD COLUMN conquest_enabled TINYINT(1) NOT NULL DEFAULT 1",
    
    // Conquest mode: 'allegiance' (drop mode) or 'control' (uptime mode)
    'conquest_mode' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_mode TEXT NOT NULL DEFAULT 'allegiance'"
        : "ALTER TABLE worlds ADD COLUMN conquest_mode VARCHAR(32) NOT NULL DEFAULT 'allegiance'",
    
    // Allegiance regeneration rate (percentage points per hour)
    'alleg_regen_per_hour' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN alleg_regen_per_hour REAL NOT NULL DEFAULT 2.0"
        : "ALTER TABLE worlds ADD COLUMN alleg_regen_per_hour DECIMAL(6,3) NOT NULL DEFAULT 2.0",
    
    // Wall reduction factor per level (e.g., 0.02 = 2% per level)
    'alleg_wall_reduction_per_level' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN alleg_wall_reduction_per_level REAL NOT NULL DEFAULT 0.02"
        : "ALTER TABLE worlds ADD COLUMN alleg_wall_reduction_per_level DECIMAL(6,4) NOT NULL DEFAULT 0.02",
    
    // Allegiance drop range per Envoy (min)
    'alleg_drop_min' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN alleg_drop_min INTEGER NOT NULL DEFAULT 18"
        : "ALTER TABLE worlds ADD COLUMN alleg_drop_min INT NOT NULL DEFAULT 18",
    
    // Allegiance drop range per Envoy (max)
    'alleg_drop_max' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN alleg_drop_max INTEGER NOT NULL DEFAULT 28"
        : "ALTER TABLE worlds ADD COLUMN alleg_drop_max INT NOT NULL DEFAULT 28",
    
    // Anti-snipe floor value (minimum allegiance during grace period)
    'anti_snipe_floor' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN anti_snipe_floor INTEGER NOT NULL DEFAULT 10"
        : "ALTER TABLE worlds ADD COLUMN anti_snipe_floor INT NOT NULL DEFAULT 10",
    
    // Anti-snipe duration in seconds (e.g., 900 = 15 minutes)
    'anti_snipe_seconds' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN anti_snipe_seconds INTEGER NOT NULL DEFAULT 900"
        : "ALTER TABLE worlds ADD COLUMN anti_snipe_seconds INT NOT NULL DEFAULT 900",
    
    // Post-capture starting allegiance value
    'post_capture_start' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN post_capture_start INTEGER NOT NULL DEFAULT 25"
        : "ALTER TABLE worlds ADD COLUMN post_capture_start INT NOT NULL DEFAULT 25",
    
    // Capture cooldown duration in seconds
    'capture_cooldown_seconds' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN capture_cooldown_seconds INTEGER NOT NULL DEFAULT 900"
        : "ALTER TABLE worlds ADD COLUMN capture_cooldown_seconds INT NOT NULL DEFAULT 900",
    
    // Uptime duration required for capture (control mode)
    'uptime_duration_seconds' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN uptime_duration_seconds INTEGER NOT NULL DEFAULT 900"
        : "ALTER TABLE worlds ADD COLUMN uptime_duration_seconds INT NOT NULL DEFAULT 900",
    
    // Control gain rate per minute (control mode)
    'control_gain_rate_per_min' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN control_gain_rate_per_min INTEGER NOT NULL DEFAULT 5"
        : "ALTER TABLE worlds ADD COLUMN control_gain_rate_per_min INT NOT NULL DEFAULT 5",
    
    // Control decay rate per minute (control mode)
    'control_decay_rate_per_min' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN control_decay_rate_per_min INTEGER NOT NULL DEFAULT 3"
        : "ALTER TABLE worlds ADD COLUMN control_decay_rate_per_min INT NOT NULL DEFAULT 3",
    
    // Minimum wave spacing in milliseconds
    'wave_spacing_ms' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN wave_spacing_ms INTEGER NOT NULL DEFAULT 300"
        : "ALTER TABLE worlds ADD COLUMN wave_spacing_ms INT NOT NULL DEFAULT 300",
    
    // Maximum Envoys per command
    'max_envoys_per_command' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN max_envoys_per_command INTEGER NOT NULL DEFAULT 1"
        : "ALTER TABLE worlds ADD COLUMN max_envoys_per_command INT NOT NULL DEFAULT 1",
    
    // Daily influence crest minting cap per account
    'conquest_daily_mint_cap' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_daily_mint_cap INTEGER NOT NULL DEFAULT 5"
        : "ALTER TABLE worlds ADD COLUMN conquest_daily_mint_cap INT NOT NULL DEFAULT 5",
    
    // Daily Envoy training cap per account
    'conquest_daily_train_cap' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_daily_train_cap INTEGER NOT NULL DEFAULT 3"
        : "ALTER TABLE worlds ADD COLUMN conquest_daily_train_cap INT NOT NULL DEFAULT 3",
    
    // Minimum defender points required for conquest
    'conquest_min_defender_points' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_min_defender_points INTEGER NOT NULL DEFAULT 1000"
        : "ALTER TABLE worlds ADD COLUMN conquest_min_defender_points INT NOT NULL DEFAULT 1000",
    
    // Optional building loss on capture (enabled/disabled)
    'conquest_building_loss_enabled' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_building_loss_enabled INTEGER NOT NULL DEFAULT 0"
        : "ALTER TABLE worlds ADD COLUMN conquest_building_loss_enabled TINYINT(1) NOT NULL DEFAULT 0",
    
    // Building loss probability (e.g., 0.100 = 10%)
    'conquest_building_loss_chance' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_building_loss_chance REAL NOT NULL DEFAULT 0.100"
        : "ALTER TABLE worlds ADD COLUMN conquest_building_loss_chance DECIMAL(5,3) NOT NULL DEFAULT 0.100",
    
    // Resource transfer percentage on capture (e.g., 1.000 = 100%)
    'conquest_resource_transfer_pct' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_resource_transfer_pct REAL NOT NULL DEFAULT 1.000"
        : "ALTER TABLE worlds ADD COLUMN conquest_resource_transfer_pct DECIMAL(5,3) NOT NULL DEFAULT 1.000",
    
    // Abandonment decay feature (enabled/disabled)
    'conquest_abandonment_decay_enabled' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_abandonment_decay_enabled INTEGER NOT NULL DEFAULT 0"
        : "ALTER TABLE worlds ADD COLUMN conquest_abandonment_decay_enabled TINYINT(1) NOT NULL DEFAULT 0",
    
    // Abandonment threshold in hours (e.g., 168 = 7 days)
    'conquest_abandonment_threshold_hours' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_abandonment_threshold_hours INTEGER NOT NULL DEFAULT 168"
        : "ALTER TABLE worlds ADD COLUMN conquest_abandonment_threshold_hours INT NOT NULL DEFAULT 168",
    
    // Abandonment decay rate (percentage points per hour)
    'conquest_abandonment_decay_rate' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN conquest_abandonment_decay_rate REAL NOT NULL DEFAULT 1.0"
        : "ALTER TABLE worlds ADD COLUMN conquest_abandonment_decay_rate DECIMAL(6,3) NOT NULL DEFAULT 1.0",
];

foreach ($columns as $name => $sql) {
    if (dbColumnExists($conn, 'worlds', $name)) {
        echo "- Column {$name} already exists. Skipping.\n";
        continue;
    }
    
    $ok = $conn->query($sql);
    if ($ok === false) {
        $error = isset($conn->error) ? $conn->error : 'Unknown error';
        echo "- Failed to add {$name}: {$error}\n";
    } else {
        echo "- Added column {$name}.\n";
    }
}

echo "\nConquest configuration migration complete.\n";
echo "All worlds now support conquest system configuration.\n";
echo "Default values have been set for backward compatibility.\n";

