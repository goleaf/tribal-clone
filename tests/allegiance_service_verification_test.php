<?php
declare(strict_types=1);

/**
 * Verification test for AllegianceService implementation
 * Tests the core calculation methods to ensure they work correctly
 */

define('DB_DRIVER', 'sqlite');
define('CURRENT_WORLD_ID', 1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';
require_once __DIR__ . '/../lib/services/AllegianceService.php';

$db = new Database(null, null, null, ':memory:');
$conn = $db->getConnection();

// Helper functions
function ok(bool $cond, string $label, string $detail = ''): void {
    echo ($cond ? "[PASS] " : "[FAIL] ") . $label;
    if ($detail !== '') {
        echo " :: " . $detail;
    }
    echo "\n";
}

// Create minimal schema
$conn->query("
    CREATE TABLE worlds (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        conquest_enabled INTEGER NOT NULL DEFAULT 1,
        conquest_mode TEXT NOT NULL DEFAULT 'allegiance',
        alleg_regen_per_hour REAL NOT NULL DEFAULT 2.0,
        alleg_wall_reduction_per_level REAL NOT NULL DEFAULT 0.02,
        alleg_drop_min INTEGER NOT NULL DEFAULT 18,
        alleg_drop_max INTEGER NOT NULL DEFAULT 28,
        anti_snipe_floor INTEGER NOT NULL DEFAULT 10,
        anti_snipe_seconds INTEGER NOT NULL DEFAULT 900,
        post_capture_start INTEGER NOT NULL DEFAULT 25,
        capture_cooldown_seconds INTEGER NOT NULL DEFAULT 900,
        uptime_duration_seconds INTEGER NOT NULL DEFAULT 900,
        control_gain_rate_per_min INTEGER NOT NULL DEFAULT 5,
        control_decay_rate_per_min INTEGER NOT NULL DEFAULT 3
    )
");

$conn->query("
    CREATE TABLE villages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        x_coord INTEGER NOT NULL,
        y_coord INTEGER NOT NULL,
        allegiance INTEGER NOT NULL DEFAULT 100,
        allegiance_last_update TEXT DEFAULT CURRENT_TIMESTAMP,
        anti_snipe_until TEXT DEFAULT NULL,
        allegiance_floor INTEGER DEFAULT 0,
        capture_cooldown_until TEXT DEFAULT NULL,
        control_meter INTEGER DEFAULT 0,
        uptime_started_at TEXT DEFAULT NULL
    )
");

// Insert test world
$conn->query("INSERT INTO worlds (id, name) VALUES (1, 'Test World')");

// Insert test villages
$conn->query("INSERT INTO villages (id, user_id, name, x_coord, y_coord, allegiance) VALUES (1, 1, 'Village1', 0, 0, 100)");
$conn->query("INSERT INTO villages (id, user_id, name, x_coord, y_coord, allegiance) VALUES (2, 2, 'Village2', 10, 10, 50)");
$conn->query("INSERT INTO villages (id, user_id, name, x_coord, y_coord, allegiance, anti_snipe_until, allegiance_floor) 
    VALUES (3, 3, 'Village3', 20, 20, 30, datetime('now', '+1 hour'), 25)");

$service = new AllegianceService($conn);

echo "\n=== AllegianceService Verification Tests ===\n\n";

// Test 1: Calculate drop with no Envoys
echo "Test 1: Calculate drop with no Envoys\n";
$result = $service->calculateDrop(1, 0, 10, [], 1);
ok($result['drop_amount'] === 0, 'No drop when no Envoys survive', 'drop=' . $result['drop_amount']);
ok($result['new_allegiance'] === null, 'New allegiance is null when no Envoys', 'new_allegiance=' . var_export($result['new_allegiance'], true));

// Test 2: Calculate drop with Envoys
echo "\nTest 2: Calculate drop with Envoys\n";
$result = $service->calculateDrop(1, 2, 10, [], 1);
ok($result['drop_amount'] > 0, 'Drop amount is positive with Envoys', 'drop=' . $result['drop_amount']);
ok($result['new_allegiance'] < 100, 'Allegiance decreased', 'new_allegiance=' . $result['new_allegiance']);
ok($result['new_allegiance'] >= 0 && $result['new_allegiance'] <= 100, 'Allegiance clamped to [0, 100]', 'new_allegiance=' . $result['new_allegiance']);

// Test 3: Wall reduction effect
echo "\nTest 3: Wall reduction effect\n";
$resultNoWall = $service->calculateDrop(1, 1, 0, [], 1);
$resultHighWall = $service->calculateDrop(1, 1, 20, [], 1);
ok($resultHighWall['wall_reduction'] < 1.0, 'Wall reduction factor applied', 'reduction=' . $resultHighWall['wall_reduction']);
ok($resultHighWall['drop_amount'] < $resultNoWall['drop_amount'], 'Higher wall reduces drop', 
    'no_wall=' . $resultNoWall['drop_amount'] . ', high_wall=' . $resultHighWall['drop_amount']);

// Test 4: Anti-snipe floor enforcement
echo "\nTest 4: Anti-snipe floor enforcement\n";
$result = $service->calculateDrop(3, 5, 0, [], 1);
ok($result['floor_active'] === true, 'Anti-snipe floor is active', 'floor_active=' . var_export($result['floor_active'], true));
ok($result['new_allegiance'] >= 25, 'Allegiance not below floor', 'new_allegiance=' . $result['new_allegiance'] . ', floor=25');

// Test 5: Regeneration calculation
echo "\nTest 5: Regeneration calculation\n";
$newAllegiance = $service->applyRegeneration(2, 50, 3600, [], 1);
ok($newAllegiance > 50, 'Regeneration increases allegiance', 'before=50, after=' . $newAllegiance);
ok($newAllegiance <= 100, 'Regeneration clamped to 100', 'after=' . $newAllegiance);

// Test 6: Regeneration with bonuses
echo "\nTest 6: Regeneration with bonuses\n";
$noBonusRegen = $service->applyRegeneration(2, 50, 3600, [], 1);
$withBonusRegen = $service->applyRegeneration(2, 50, 3600, ['building_multiplier' => 1.5, 'tech_multiplier' => 1.2], 1);
ok($withBonusRegen > $noBonusRegen, 'Bonuses increase regeneration', 
    'no_bonus=' . $noBonusRegen . ', with_bonus=' . $withBonusRegen);

// Test 7: Regeneration pause during anti-snipe
echo "\nTest 7: Regeneration pause during anti-snipe\n";
$pausedRegen = $service->applyRegeneration(3, 30, 3600, [], 1);
ok($pausedRegen === 30, 'Regeneration paused during anti-snipe', 'before=30, after=' . $pausedRegen);

// Test 8: Floor enforcement
echo "\nTest 8: Floor enforcement\n";
$clamped = $service->enforceFloor(3, 20, 1);
ok($clamped === 25, 'Floor enforced when anti-snipe active', 'proposed=20, clamped=' . $clamped);

// Test 9: Floor not enforced when expired
echo "\nTest 9: Floor not enforced when expired\n";
$notClamped = $service->enforceFloor(1, 20, 1);
ok($notClamped === 20, 'Floor not enforced when anti-snipe inactive', 'proposed=20, result=' . $notClamped);

// Test 10: Capture detection - allegiance mode
echo "\nTest 10: Capture detection - allegiance mode\n";
$captured = $service->checkCapture(1, 0, 1);
ok($captured === true, 'Capture detected when allegiance reaches 0', 'captured=' . var_export($captured, true));

$notCaptured = $service->checkCapture(1, 50, 1);
ok($notCaptured === false, 'No capture when allegiance > 0', 'captured=' . var_export($notCaptured, true));

// Test 11: Capture blocked by anti-snipe
echo "\nTest 11: Capture blocked by anti-snipe\n";
$blockedCapture = $service->checkCapture(3, 0, 1);
ok($blockedCapture === false, 'Capture blocked during anti-snipe', 'captured=' . var_export($blockedCapture, true));

echo "\n=== All verification tests complete ===\n";
