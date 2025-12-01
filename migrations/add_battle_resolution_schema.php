<?php
/**
 * Migration: Add/ensure battle resolution schema tables.
 *
 * Creates commands, battle_reports, battle_metrics, and rate_limit_tracking tables if missing.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../config/config.php';

echo "=== Battle Resolution Schema Migration ===\n\n";

try {
    $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $database->getConnection();
    echo "Connected to database: " . DB_NAME . "\n\n";

    // Commands table
    $conn->query("
        CREATE TABLE IF NOT EXISTS commands (
            command_id INTEGER PRIMARY KEY,
            attacker_id INTEGER NOT NULL,
            defender_id INTEGER NOT NULL,
            source_village_id INTEGER NOT NULL,
            target_village_id INTEGER NOT NULL,
            command_type TEXT NOT NULL,
            units TEXT NOT NULL,
            sent_at DATETIME NOT NULL,
            arrival_at DATETIME NOT NULL,
            sequence_number INTEGER NOT NULL DEFAULT 0,
            target_building TEXT,
            status TEXT DEFAULT 'pending',
            correlation_id TEXT,
            is_fake INTEGER DEFAULT 0,
            min_pop_validated INTEGER DEFAULT 1,
            rate_limit_checked INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_commands_arrival ON commands(arrival_at, sequence_number)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_commands_target ON commands(target_village_id, arrival_at)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_commands_attacker ON commands(attacker_id, sent_at)");

    // Battle reports table
    $conn->query("
        CREATE TABLE IF NOT EXISTS battle_reports (
            report_id INTEGER PRIMARY KEY,
            command_id INTEGER NOT NULL,
            battle_id TEXT NOT NULL,
            recipient_id INTEGER NOT NULL,
            perspective TEXT NOT NULL,
            outcome TEXT NOT NULL,
            attacker_id INTEGER NOT NULL,
            defender_id INTEGER NOT NULL,
            attacker_village_id INTEGER NOT NULL,
            defender_village_id INTEGER NOT NULL,
            luck REAL NOT NULL,
            morale REAL NOT NULL,
            wall_multiplier REAL NOT NULL,
            night_bonus REAL,
            overstack_penalty REAL,
            environment_modifiers TEXT,
            attacker_sent TEXT NOT NULL,
            attacker_lost TEXT NOT NULL,
            attacker_survivors TEXT NOT NULL,
            defender_present TEXT NOT NULL,
            defender_lost TEXT NOT NULL,
            defender_survivors TEXT NOT NULL,
            wall_start INTEGER,
            wall_end INTEGER,
            building_target TEXT,
            building_start INTEGER,
            building_end INTEGER,
            plunder_wood INTEGER,
            plunder_clay INTEGER,
            plunder_iron INTEGER,
            vault_protection TEXT,
            allegiance_start INTEGER,
            allegiance_end INTEGER,
            village_captured INTEGER DEFAULT 0,
            defender_intel TEXT,
            report_version INTEGER DEFAULT 1,
            is_read INTEGER DEFAULT 0,
            is_starred INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_reports_recipient ON battle_reports(recipient_id, created_at)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_reports_battle ON battle_reports(battle_id)");

    // Battle metrics table
    $conn->query("
        CREATE TABLE IF NOT EXISTS battle_metrics (
            metric_id INTEGER PRIMARY KEY,
            battle_id TEXT NOT NULL,
            resolver_latency_ms INTEGER NOT NULL,
            outcome TEXT NOT NULL,
            attacker_points INTEGER NOT NULL,
            defender_points INTEGER NOT NULL,
            total_attacker_pop INTEGER NOT NULL,
            total_defender_pop INTEGER NOT NULL,
            morale REAL NOT NULL,
            luck REAL NOT NULL,
            wall_level INTEGER NOT NULL,
            had_siege INTEGER NOT NULL,
            had_conquest INTEGER NOT NULL,
            village_captured INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_metrics_created ON battle_metrics(created_at)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_metrics_outcome ON battle_metrics(outcome)");

    // Rate limit tracking table
    $conn->query("
        CREATE TABLE IF NOT EXISTS rate_limit_tracking (
            tracking_id INTEGER PRIMARY KEY,
            player_id INTEGER NOT NULL,
            target_village_id INTEGER,
            command_type TEXT NOT NULL,
            timestamp DATETIME NOT NULL
        )
    ");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_rate_limit_player ON rate_limit_tracking(player_id, timestamp)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_rate_limit_target ON rate_limit_tracking(target_village_id, timestamp)");

    echo "Schema ensured.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
