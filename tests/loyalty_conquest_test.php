<?php
declare(strict_types=1);

/**
 * Lightweight sanity checks for loyalty/conquest rules.
 * Uses an in-memory SQLite DB via SQLiteAdapter (Database.php).
 */

define('DB_DRIVER', 'sqlite');

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/services/AllegianceService.php';

$db = new Database(null, null, null, ':memory:');
$conn = $db->getConnection();

// Helpers
function ok(bool $cond, string $label, string $detail = ''): void {
    echo ($cond ? "[PASS] " : "[FAIL] ") . $label;
    if ($detail !== '') {
        echo " :: " . $detail;
    }
    echo "\n";
}

function approx(float $a, float $b, float $tol = 0.1): bool {
    return abs($a - $b) <= $tol;
}

// Schema (minimal)
$conn->query("
    CREATE TABLE villages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        x_coord INTEGER NOT NULL,
        y_coord INTEGER NOT NULL,
        loyalty INTEGER NOT NULL DEFAULT 100,
        last_loyalty_update TEXT DEFAULT CURRENT_TIMESTAMP,
        is_capital INTEGER NOT NULL DEFAULT 0,
        conquered_at TEXT DEFAULT NULL
    )
");
$conn->query("
    CREATE TABLE battle_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        target_village_id INTEGER NOT NULL,
        battle_time TEXT NOT NULL,
        attacker_won INTEGER NOT NULL
    )
");
$conn->query("
    CREATE TABLE building_types (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        internal_name TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL
    )
");
$conn->query("
    CREATE TABLE village_buildings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        village_id INTEGER NOT NULL,
        building_type_id INTEGER NOT NULL,
        level INTEGER NOT NULL DEFAULT 0
    )
");
$conn->query("
    CREATE TABLE village_units (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        village_id INTEGER NOT NULL,
        unit_type_id INTEGER NOT NULL,
        count INTEGER NOT NULL
    )
");

// Seed building types
$conn->query("INSERT INTO building_types (internal_name, name) VALUES ('main_building','HQ')");
$conn->query("INSERT INTO building_types (internal_name, name) VALUES ('wall','Wall')");
$conn->query("INSERT INTO building_types (internal_name, name) VALUES ('church','Church')");

// Seed villages: capital at 0,0 and distant village at 100,0
$conn->query("INSERT INTO villages (id, user_id, name, x_coord, y_coord, loyalty, is_capital) VALUES (1, 1, 'Capital', 0, 0, 150, 1)");
$conn->query("INSERT INTO villages (id, user_id, name, x_coord, y_coord, loyalty, is_capital, last_loyalty_update) VALUES (2, 1, 'Outpost', 100, 0, 50, 0, datetime('now','-1 day'))");

// Seed buildings for both villages
// Capital buildings
$conn->query("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (1, 1, 20)"); // HQ lvl 20
$conn->query("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (1, 2, 10)"); // Wall lvl 10
$conn->query("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (1, 3, 1)");  // Church

// Outpost buildings (lower bonuses)
$conn->query("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (2, 1, 10)"); // HQ lvl 10
$conn->query("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (2, 2, 5)");  // Wall lvl 5

// Seed garrisons
$conn->query("INSERT INTO village_units (village_id, unit_type_id, count) VALUES (1, 1, 200)");
$conn->query("INSERT INTO village_units (village_id, unit_type_id, count) VALUES (2, 1, 200)");

// Recent battle reports hitting Outpost (3 attacks, 1 successful defense)
$now = time();
$times = [
    date('Y-m-d H:i:s', $now - 3 * 3600),
    date('Y-m-d H:i:s', $now - 8 * 3600),
    date('Y-m-d H:i:s', $now - 20 * 3600),
];
$attackerWonFlags = [1, 1, 0]; // last one is a successful defense
foreach ($times as $i => $ts) {
    $won = $attackerWonFlags[$i];
    $stmt = $conn->prepare("INSERT INTO battle_reports (target_village_id, battle_time, attacker_won) VALUES (2, ?, ?)");
    $stmt->bind_param("si", $ts, $won);
    $stmt->execute();
    $stmt->close();
}

$vm = new VillageManager($conn);
$as = new AllegianceService();

// Test 1: Capital cap with bonuses (HQ20, Wall10, garrison 200, church)
$capCapital = $vm->getEffectiveLoyaltyCap(1);
ok($capCapital > 170 && $capCapital < 195, 'Capital cap includes bonuses', 'cap=' . $capCapital);

// Test 2: Outpost cap with penalties (distance + recent attacks)
$capOutpost = $vm->getEffectiveLoyaltyCap(2);
ok($capOutpost > 85 && $capOutpost < 105, 'Outpost cap reflects penalties', 'cap=' . $capOutpost);

// Test 3: Drop multiplier (should be >1 due to penalties)
$dropMult = $vm->getLoyaltyDropMultiplier(2);
ok($dropMult > 1.0 && $dropMult <= 1.3, 'Drop multiplier amplified by penalties', 'mult=' . round($dropMult, 3));

// Test 4: Regen over 24h on Outpost (should rise from 50 toward cap)
$before = $conn->query("SELECT loyalty FROM villages WHERE id = 2")->fetch_assoc()['loyalty'];
$vm->getVillageInfo(2); // triggers regen
$after = $conn->query("SELECT loyalty FROM villages WHERE id = 2")->fetch_assoc()['loyalty'];
ok($after > $before, 'Regen increases loyalty', "before={$before}, after={$after}, cap={$capOutpost}");

// Test 5: Regen does not exceed cap
ok($after <= ceil($capOutpost), 'Regen clamped to cap', "after={$after}, cap={$capOutpost}");

// Test 6: Anti-snipe floor prevents drop below floor when active
[$newAlleg, $captured, $drop, $floorApplied] = $as->applyWave(15, 1, 5, true, null, true);
ok($newAlleg === $as->getAntiSnipeSettings()['floor'], 'Anti-snipe floor enforced', "new={$newAlleg}, floor=" . $as->getAntiSnipeSettings()['floor']);

// Test 7: Regen pauses during anti-snipe and clamps to 100
$regenPaused = $as->regen(50, 3600, true);
$regenApplied = $as->regen(95, 3600, false);
ok($regenPaused === 50, 'Regen paused during anti-snipe', "regenPaused={$regenPaused}");
ok($regenApplied <= 100, 'Regen clamped to 100', "regenApplied={$regenApplied}");
