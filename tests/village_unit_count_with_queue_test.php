<?php
/**
 * Test for getVillageUnitCountWithQueue() helper method
 * Validates Requirements: 9.5
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

// Test setup
$db = Database::getInstance()->getConnection();

// Clean up test data
$db->query("DELETE FROM villages WHERE name LIKE 'Test Village Cap%'");
$db->query("DELETE FROM users WHERE username LIKE 'test_cap_user%'");

// Create test user
$username = 'test_cap_user_' . time();
$password = password_hash('test123', PASSWORD_DEFAULT);
$email = 'test_cap_' . time() . '@example.com';
$db->query("INSERT INTO users (username, password, email) VALUES ('$username', '$password', '$email')");
$userId = $db->insert_id;

// Create test village
$villageName = 'Test Village Cap ' . time();
$db->query("INSERT INTO villages (name, user_id, x, y, wood, clay, iron, farm_capacity) 
            VALUES ('$villageName', $userId, 500, 500, 10000, 10000, 10000, 1000)");
$villageId = $db->insert_id;

// Get unit type IDs for siege units
$ramResult = $db->query("SELECT id FROM unit_types WHERE internal_name = 'ram' LIMIT 1");
$ramRow = $ramResult->fetch_assoc();
$ramId = $ramRow ? $ramRow['id'] : null;

$catapultResult = $db->query("SELECT id FROM unit_types WHERE internal_name = 'catapult' LIMIT 1");
$catapultRow = $catapultResult->fetch_assoc();
$catapultId = $catapultRow ? $catapultRow['id'] : null;

if (!$ramId || !$catapultId) {
    echo "SKIP: Required unit types (ram, catapult) not found in database\n";
    exit(0);
}

$unitManager = new UnitManager($db);

echo "Testing getVillageUnitCountWithQueue()...\n";

// Test 1: Empty village should have 0 siege units
$count = $unitManager->getVillageUnitCountWithQueue($villageId, ['ram', 'catapult']);
assert($count === 0, "Test 1 Failed: Empty village should have 0 siege units, got $count");
echo "✓ Test 1 passed: Empty village has 0 siege units\n";

// Test 2: Add existing units
$db->query("INSERT INTO village_units (village_id, unit_type_id, count) VALUES ($villageId, $ramId, 10)");
$db->query("INSERT INTO village_units (village_id, unit_type_id, count) VALUES ($villageId, $catapultId, 5)");

$count = $unitManager->getVillageUnitCountWithQueue($villageId, ['ram', 'catapult']);
assert($count === 15, "Test 2 Failed: Should have 15 existing siege units, got $count");
echo "✓ Test 2 passed: Correctly counts existing units (15)\n";

// Test 3: Add queued units
$currentTime = time();
$finishTime = $currentTime + 3600;
$db->query("INSERT INTO unit_queue (village_id, unit_type_id, count, count_finished, started_at, finish_at, building_type) 
            VALUES ($villageId, $ramId, 20, 5, $currentTime, $finishTime, 'workshop')");

$count = $unitManager->getVillageUnitCountWithQueue($villageId, ['ram', 'catapult']);
// Should be: 10 existing rams + 5 existing catapults + (20-5) queued rams = 30
assert($count === 30, "Test 3 Failed: Should have 30 total siege units (existing + queued), got $count");
echo "✓ Test 3 passed: Correctly counts existing + queued units (30)\n";

// Test 4: Test with only one unit type
$ramCount = $unitManager->getVillageUnitCountWithQueue($villageId, ['ram']);
// Should be: 10 existing + 15 queued = 25
assert($ramCount === 25, "Test 4 Failed: Should have 25 rams (existing + queued), got $ramCount");
echo "✓ Test 4 passed: Correctly counts single unit type (25 rams)\n";

// Test 5: Test with empty array
$emptyCount = $unitManager->getVillageUnitCountWithQueue($villageId, []);
assert($emptyCount === 0, "Test 5 Failed: Empty array should return 0, got $emptyCount");
echo "✓ Test 5 passed: Empty array returns 0\n";

// Cleanup
$db->query("DELETE FROM unit_queue WHERE village_id = $villageId");
$db->query("DELETE FROM village_units WHERE village_id = $villageId");
$db->query("DELETE FROM villages WHERE id = $villageId");
$db->query("DELETE FROM users WHERE id = $userId");

echo "\n✅ All getVillageUnitCountWithQueue() tests passed!\n";
?>
