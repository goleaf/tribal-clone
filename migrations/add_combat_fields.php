<?php
/**
 * Migration: Add Combat System Fields
 * 
 * Adds necessary fields for the combat system including:
 * - Battle reports enhancements
 * - Occupation/hold system
 * - Command tracking
 * 
 * Run: php migrations/add_combat_fields.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../config/config.php';

echo "=== Combat System Fields Migration ===\n\n";

try {
    $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $database->getConnection();
    
    echo "Connected to database: " . DB_NAME . "\n\n";
    
    // Check and add fields to battle_reports table
    echo "Checking battle_reports table...\n";
    $result = $conn->query("PRAGMA table_info(battle_reports)");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['name'];
    }
    
    $fieldsToAdd = [
        'correlation_id' => "TEXT",
        'report_version' => "INTEGER DEFAULT 1",
        'morale_value' => "REAL DEFAULT 1.0",
        'luck_value' => "REAL DEFAULT 0.0",
        'night_bonus' => "REAL DEFAULT 1.0",
        'weather_modifier' => "REAL DEFAULT 1.0",
        'terrain_modifier' => "REAL DEFAULT 1.0",
        'overstack_penalty' => "REAL DEFAULT 1.0",
        'wall_before' => "INTEGER DEFAULT 0",
        'wall_after' => "INTEGER DEFAULT 0",
        'building_targeted' => "TEXT",
        'building_damage' => "INTEGER DEFAULT 0",
        'allegiance_before' => "INTEGER DEFAULT 100",
        'allegiance_after' => "INTEGER DEFAULT 100",
        'captured' => "INTEGER DEFAULT 0",
        'vault_protection_pct' => "REAL DEFAULT 0.0",
        'resources_protected_wood' => "INTEGER DEFAULT 0",
        'resources_protected_clay' => "INTEGER DEFAULT 0",
        'resources_protected_iron' => "INTEGER DEFAULT 0",
        'plunder_cap_applied' => "INTEGER DEFAULT 0",
        'intel_level' => "TEXT DEFAULT 'full'",
        'reason_code' => "TEXT"
    ];
    
    $added = 0;
    foreach ($fieldsToAdd as $field => $definition) {
        if (!in_array($field, $columns, true)) {
            echo "  Adding column: {$field}\n";
            $conn->query("ALTER TABLE battle_reports ADD COLUMN {$field} {$definition}");
            $added++;
        }
    }
    
    if ($added === 0) {
        echo "  ✓ All battle_reports columns already exist\n";
    } else {
        echo "  ✓ Added {$added} columns to battle_reports\n";
    }
    
    echo "\n";
    
    // Check and add fields to commands table
    echo "Checking commands table...\n";
    $result = $conn->query("PRAGMA table_info(commands)");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['name'];
    }
    
    $commandFields = [
        'sequence' => "INTEGER DEFAULT 0",
        'correlation_id' => "TEXT",
        'is_fake' => "INTEGER DEFAULT 0",
        'min_pop_validated' => "INTEGER DEFAULT 1",
        'rate_limit_checked' => "INTEGER DEFAULT 1",
        'occupation_duration' => "INTEGER DEFAULT 0",
        'occupation_ends_at' => "TEXT"
    ];
    
    $added = 0;
    foreach ($commandFields as $field => $definition) {
        if (!in_array($field, $columns, true)) {
            echo "  Adding column: {$field}\n";
            $conn->query("ALTER TABLE commands ADD COLUMN {$field} {$definition}");
            $added++;
        }
    }
    
    if ($added === 0) {
        echo "  ✓ All commands columns already exist\n";
    } else {
        echo "  ✓ Added {$added} columns to commands\n";
    }
    
    echo "\n";
    
    // Create occupation_states table if it doesn't exist
    echo "Checking occupation_states table...\n";
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='occupation_states'");
    
    if ($result->num_rows === 0) {
        echo "  Creating occupation_states table...\n";
        $conn->query("
            CREATE TABLE occupation_states (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                village_id INTEGER NOT NULL,
                occupier_user_id INTEGER NOT NULL,
                command_id INTEGER NOT NULL,
                started_at TEXT NOT NULL,
                ends_at TEXT NOT NULL,
                attrition_rate REAL DEFAULT 0.0,
                debuff_production REAL DEFAULT 1.0,
                debuff_recruit REAL DEFAULT 1.0,
                status TEXT DEFAULT 'active',
                ended_at TEXT,
                end_reason TEXT,
                FOREIGN KEY (village_id) REFERENCES villages(id),
                FOREIGN KEY (occupier_user_id) REFERENCES users(id),
                FOREIGN KEY (command_id) REFERENCES commands(id)
            )
        ");
        echo "  ✓ Created occupation_states table\n";
    } else {
        echo "  ✓ occupation_states table already exists\n";
    }
    
    echo "\n";
    
    // Create command_rate_limits table if it doesn't exist
    echo "Checking command_rate_limits table...\n";
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='command_rate_limits'");
    
    if ($result->num_rows === 0) {
        echo "  Creating command_rate_limits table...\n";
        $conn->query("
            CREATE TABLE command_rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                target_village_id INTEGER,
                command_type TEXT NOT NULL,
                window_start INTEGER NOT NULL,
                count INTEGER DEFAULT 1,
                last_command_at INTEGER NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (target_village_id) REFERENCES villages(id)
            )
        ");
        
        // Add indexes for performance
        $conn->query("CREATE INDEX idx_rate_limits_user ON command_rate_limits(user_id, window_start)");
        $conn->query("CREATE INDEX idx_rate_limits_target ON command_rate_limits(user_id, target_village_id, window_start)");
        
        echo "  ✓ Created command_rate_limits table with indexes\n";
    } else {
        echo "  ✓ command_rate_limits table already exists\n";
    }
    
    echo "\n";
    
    // Create battle_traces table for audit/debugging
    echo "Checking battle_traces table...\n";
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='battle_traces'");
    
    if ($result->num_rows === 0) {
        echo "  Creating battle_traces table...\n";
        $conn->query("
            CREATE TABLE battle_traces (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                correlation_id TEXT NOT NULL,
                battle_id INTEGER,
                timestamp INTEGER NOT NULL,
                event_type TEXT NOT NULL,
                data TEXT,
                FOREIGN KEY (battle_id) REFERENCES battle_reports(id)
            )
        ");
        
        $conn->query("CREATE INDEX idx_traces_correlation ON battle_traces(correlation_id)");
        $conn->query("CREATE INDEX idx_traces_battle ON battle_traces(battle_id)");
        
        echo "  ✓ Created battle_traces table with indexes\n";
    } else {
        echo "  ✓ battle_traces table already exists\n";
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "✓ Combat system database schema is ready\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
}
