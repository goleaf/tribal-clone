<?php
declare(strict_types=1);

/**
 * Migration: Intel system scaffolding (fog of war, scouting, sharing).
 *
 * Creates:
 * - scout_mission_types: canonical mission presets (light scout, deep spy, etc.)
 * - scout_missions: attaches a mission type to an outgoing spy attack
 * - intel_reports: normalized intel snapshots produced by scout missions
 * - intel_shares: tribe-level sharing of reports
 * - intel_tags / intel_report_tags: lightweight tagging system for intel cards
 */

require_once __DIR__ . '/../init.php';

if (!isset($conn)) {
    echo "Database connection not available.\n";
    exit(1);
}

echo "Running intel system migration...\n";

/**
 * Best-effort table existence check that works for both SQLiteAdapter and mysqli.
 */
function tableExistsIntel($conn, string $table): bool
{
    $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $sqliteCheck = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$safeName}'");
    if ($sqliteCheck instanceof SQLiteResult) {
        return $sqliteCheck->num_rows > 0;
    }
    if ($sqliteCheck instanceof mysqli_result) {
        return $sqliteCheck->num_rows > 0;
    }
    return false;
}

$ddl = [
    // Canonical mission presets from the design doc
    "CREATE TABLE IF NOT EXISTS scout_mission_types (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        internal_name TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        summary TEXT DEFAULT '',
        category TEXT NOT NULL DEFAULT 'scout',
        base_detection_risk INTEGER NOT NULL DEFAULT 25,
        base_quality INTEGER NOT NULL DEFAULT 60,
        speed_factor REAL NOT NULL DEFAULT 1.0,
        min_units INTEGER NOT NULL DEFAULT 1,
        default_units INTEGER NOT NULL DEFAULT 1,
        allows_loot INTEGER NOT NULL DEFAULT 0,
        payload TEXT DEFAULT NULL,
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
    )",
    // Outgoing scout/spying commands mapped to attacks
    "CREATE TABLE IF NOT EXISTS scout_missions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        attack_id INTEGER NOT NULL UNIQUE,
        mission_type TEXT NOT NULL DEFAULT 'light_scout',
        requested_by_user_id INTEGER NOT NULL,
        requested_by_village_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'enroute',
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        resolved_at INTEGER DEFAULT NULL
    )",
    // Intel snapshots with freshness/quality metadata
    "CREATE TABLE IF NOT EXISTS intel_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        attack_id INTEGER DEFAULT NULL,
        mission_type TEXT NOT NULL DEFAULT 'light_scout',
        outcome TEXT NOT NULL DEFAULT 'partial',
        quality INTEGER NOT NULL DEFAULT 50,
        confidence INTEGER NOT NULL DEFAULT 50,
        detection INTEGER NOT NULL DEFAULT 0,
        lost_units INTEGER NOT NULL DEFAULT 0,
        source_village_id INTEGER NOT NULL,
        source_village_name TEXT,
        source_user_id INTEGER NOT NULL,
        target_village_id INTEGER NOT NULL,
        target_village_name TEXT,
        target_x INTEGER DEFAULT NULL,
        target_y INTEGER DEFAULT NULL,
        target_user_id INTEGER DEFAULT NULL,
        intel_json TEXT NOT NULL,
        gathered_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
    )",
    // Tribe sharing
    "CREATE TABLE IF NOT EXISTS intel_shares (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        report_id INTEGER NOT NULL,
        tribe_id INTEGER NOT NULL,
        shared_by_user_id INTEGER NOT NULL,
        shared_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        UNIQUE(report_id, tribe_id)
    )",
    // Tag catalog
    "CREATE TABLE IF NOT EXISTS intel_tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        color TEXT DEFAULT '#888888',
        description TEXT DEFAULT '',
        created_by_user_id INTEGER DEFAULT NULL,
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
    )",
    // Tag assignments
    "CREATE TABLE IF NOT EXISTS intel_report_tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        report_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        UNIQUE(report_id, tag_id)
    )",
];

foreach ($ddl as $sql) {
    $ok = $conn->query($sql);
    if ($ok === false) {
        echo "[!] Failed to run DDL: " . $conn->error . "\n";
        exit(1);
    }
}
echo "- Tables ensured.\n";

// Indexes to keep dashboards snappy
$conn->query("CREATE INDEX IF NOT EXISTS idx_intel_reports_source ON intel_reports(source_user_id, created_at)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_intel_reports_target ON intel_reports(target_village_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_intel_reports_gathered ON intel_reports(gathered_at)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_intel_shares_report ON intel_shares(report_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_scout_missions_attack ON scout_missions(attack_id)");

echo "- Indexes ensured.\n";

// Seed canonical mission presets from the design doc
$missionPresets = [
    [
        'internal_name' => 'light_scout',
        'name' => 'Light Scout',
        'summary' => 'Fast, cheap recon. Basic intel.',
        'category' => 'scout',
        'base_detection_risk' => 50,
        'base_quality' => 55,
        'speed_factor' => 1.0,
        'min_units' => 1,
        'default_units' => 3,
        'allows_loot' => 0,
    ],
    [
        'internal_name' => 'deep_spy',
        'name' => 'Deep Spy',
        'summary' => 'Detailed infiltration with low detection.',
        'category' => 'spy',
        'base_detection_risk' => 20,
        'base_quality' => 90,
        'speed_factor' => 0.85,
        'min_units' => 1,
        'default_units' => 1,
        'allows_loot' => 0,
    ],
    [
        'internal_name' => 'scout_cavalry',
        'name' => 'Scout Cavalry',
        'summary' => 'Mobile recon with pursuit capability.',
        'category' => 'scout',
        'base_detection_risk' => 60,
        'base_quality' => 65,
        'speed_factor' => 1.15,
        'min_units' => 1,
        'default_units' => 5,
        'allows_loot' => 1,
    ],
    [
        'internal_name' => 'counter_scout',
        'name' => 'Counter-Scout Patrol',
        'summary' => 'Defensive patrol mission for interception.',
        'category' => 'defense',
        'base_detection_risk' => 15,
        'base_quality' => 0,
        'speed_factor' => 1.0,
        'min_units' => 1,
        'default_units' => 3,
        'allows_loot' => 0,
    ],
    [
        'internal_name' => 'infiltrator',
        'name' => 'Infiltrator',
        'summary' => 'Long-term embedded agent, excellent intel.',
        'category' => 'spy',
        'base_detection_risk' => 10,
        'base_quality' => 95,
        'speed_factor' => 0.7,
        'min_units' => 1,
        'default_units' => 1,
        'allows_loot' => 0,
    ],
    [
        'internal_name' => 'scout_ship',
        'name' => 'Scout Ship',
        'summary' => 'Naval reconnaissance of coastal targets.',
        'category' => 'naval',
        'base_detection_risk' => 70,
        'base_quality' => 50,
        'speed_factor' => 0.9,
        'min_units' => 1,
        'default_units' => 1,
        'allows_loot' => 1,
    ],
    [
        'internal_name' => 'merchant_scout',
        'name' => 'Merchant Scout',
        'summary' => 'Covert economic intel under trade cover.',
        'category' => 'economic',
        'base_detection_risk' => 5,
        'base_quality' => 70,
        'speed_factor' => 0.6,
        'min_units' => 1,
        'default_units' => 1,
        'allows_loot' => 1,
    ],
];

$stmtCheck = $conn->prepare("SELECT id FROM scout_mission_types WHERE internal_name = ? LIMIT 1");
$stmtInsert = $conn->prepare("
    INSERT INTO scout_mission_types (
        internal_name, name, summary, category, base_detection_risk,
        base_quality, speed_factor, min_units, default_units, allows_loot
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if ($stmtCheck && $stmtInsert) {
    foreach ($missionPresets as $preset) {
        $stmtCheck->bind_param("s", $preset['internal_name']);
        $stmtCheck->execute();
        $res = $stmtCheck->get_result();
        $exists = $res && $res->num_rows > 0;
        $res && $res->free();

        if ($exists) {
            continue;
        }

        $stmtInsert->bind_param(
            "sssiiidiii",
            $preset['internal_name'],
            $preset['name'],
            $preset['summary'],
            $preset['category'],
            $preset['base_detection_risk'],
            $preset['base_quality'],
            $preset['speed_factor'],
            $preset['min_units'],
            $preset['default_units'],
            $preset['allows_loot']
        );
        $stmtInsert->execute();
    }
    $stmtCheck->close();
    $stmtInsert->close();
}

echo "- Mission presets seeded.\n";
echo "Intel system migration completed.\n";
