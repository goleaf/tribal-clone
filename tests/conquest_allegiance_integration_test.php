<?php
declare(strict_types=1);

/**
 * Integration test for processConquestAllegiance method in BattleManager
 */

define('DB_DRIVER', 'sqlite');

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';

$db = new Database(null, null, null, ':memory:');
$conn = $db->getConnection();

// Create minimal schema
$conn->query("
    CREATE TABLE villages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        x_coord INTEGER NOT NULL,
        y_coord INTEGER NOT NULL,
        loyalty INTEGER NOT NULL DEFAULT 100,
        last_loyalty_update TEXT DEFAULT CURRENT_TIMESTAMP,
        conquered_at TEXT DEFAULT NULL,
        capture_cooldown_until TEXT DEFAULT NULL
    )
");

$conn->query("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        email TEXT NOT NULL,
        password TEXT NOT NULL
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
    CREATE TABLE unit_types (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        internal_name TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        attack INTEGER NOT NULL,
        defense INTEGER NOT NULL,
        speed INTEGER NOT NULL,
        carry_capacity INTEGER NOT NULL,
        population INTEGER NOT NULL
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

// Insert test data
$conn->query("INSERT INTO users (id, username, email, password) VALUES (1, 'attacker', 'attacker@test.com', 'hash')");
$conn->query("INSERT INTO users (id, username, email, password) VALUES (2, 'defender', 'defender@test.com', 'hash')");

$conn->query("INSERT INTO villages (id, user_id, name, x_coord, y_coord, loyalty) VALUES (1, 1, 'Attacker Village', 0, 0, 100)");
$conn->query("INSERT INTO villages (id, user_id, name, x_coord, y_coord, loyalty) VALUES (2, 2, 'Defender Village', 10, 10, 100)");

$conn->query("INSERT INTO unit_types (id, internal_name, name, attack, defense, speed, carry_capacity, population) VALUES (1, 'noble', 'Noble', 30, 100, 35, 0, 100)");
$conn->query("INSERT INTO unit_types (id, internal_name, name, attack, defense, speed, carry_capacity, population) VALUES (2, 'spearman', 'Spearman', 10, 15, 18, 25, 1)");

// Create managers
$vm = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$bm = new BuildingManager($conn, $buildingConfigManager);
$battleManager = new BattleManager($conn, $vm, $bm);

echo "=== Conquest Allegiance Integration Test ===\n\n";

// Test 1: Successful conquest with nobles
echo "Test 1: Successful conquest with nobles\n";
$attackingUnits = [
    1 => [
        'unit_type_id' => 1,
        'internal_name' => 'noble',
        'name' => 'Noble',
        'category' => 'conquest',
        'attack' => 30,
        'defense' => 100,
        'count' => 1,
        'carry_capacity' => 0,
        'building_type' => 'academy'
    ]
];

// Use reflection to call the private method
$reflection = new ReflectionClass($battleManager);
$method = $reflection->getMethod('processConquestAllegiance');
$method->setAccessible(true);

$result = $method->invoke(
    $battleManager,
    2, // target village ID
    $attackingUnits,
    true, // attacker won
    1, // attacker user ID
    2, // defender user ID
    1 // attack ID
);

echo "  Allegiance reduced: " . $result['allegiance_reduced'] . "\n";
echo "  New allegiance: " . $result['new_allegiance'] . "\n";
echo "  Captured: " . ($result['captured'] ? 'Yes' : 'No') . "\n";
echo "  Reason: " . $result['reason'] . "\n";

if ($result['allegiance_reduced'] > 0 && $result['new_allegiance'] < 100) {
    echo "  ✅ PASS: Allegiance was reduced\n";
} else {
    echo "  ❌ FAIL: Allegiance should have been reduced\n";
}

// Test 2: Failed attack with nobles (attacker lost)
echo "\nTest 2: Failed attack with nobles (attacker lost)\n";
$result2 = $method->invoke(
    $battleManager,
    2, // target village ID
    $attackingUnits,
    false, // attacker lost
    1, // attacker user ID
    2, // defender user ID
    2 // attack ID
);

echo "  Allegiance reduced: " . $result2['allegiance_reduced'] . "\n";
echo "  New allegiance: " . $result2['new_allegiance'] . "\n";
echo "  Captured: " . ($result2['captured'] ? 'Yes' : 'No') . "\n";
echo "  Reason: " . $result2['reason'] . "\n";

if ($result2['allegiance_reduced'] >= 0 && !$result2['captured']) {
    echo "  ✅ PASS: Village not captured on failed attack\n";
} else {
    echo "  ❌ FAIL: Village should not be captured on failed attack\n";
}

// Test 3: No nobles present
echo "\nTest 3: No nobles present\n";
$noNobleUnits = [
    2 => [
        'unit_type_id' => 2,
        'internal_name' => 'spearman',
        'name' => 'Spearman',
        'category' => 'infantry',
        'attack' => 10,
        'defense' => 15,
        'count' => 100,
        'carry_capacity' => 25,
        'building_type' => 'barracks'
    ]
];

$result3 = $method->invoke(
    $battleManager,
    2, // target village ID
    $noNobleUnits,
    true, // attacker won
    1, // attacker user ID
    2, // defender user ID
    3 // attack ID
);

echo "  Allegiance reduced: " . $result3['allegiance_reduced'] . "\n";
echo "  Reason: " . $result3['reason'] . "\n";

if ($result3['allegiance_reduced'] === 0 && $result3['reason'] === 'no_noble_present') {
    echo "  ✅ PASS: No allegiance reduction without nobles\n";
} else {
    echo "  ❌ FAIL: Should not reduce allegiance without nobles\n";
}

echo "\n=== All tests completed ===\n";
