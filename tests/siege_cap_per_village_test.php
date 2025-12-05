<?php
/**
 * Test for per-village siege cap enforcement in recruitUnits()
 * 
 * Validates: Requirements 9.1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

global $conn;
$db = $conn;

echo "Testing Per-Village Siege Cap Enforcement...\n\n";

// Clean up test data
$db->query("DELETE FROM villages WHERE name LIKE 'Test Siege Cap%'");
$db->query("DELETE FROM users WHERE username LIKE 'test_siege_cap%'");

// Create test user
$username = 'test_siege_cap_' . time();
$password = password_hash('test123', PASSWORD_DEFAULT);
$email = 'test_siege_' . time() . '@example.com';
$db->query("INSERT INTO users (username, password, email) VALUES ('$username', '$password', '$email')");
$userId = $db->insert_id;

// Create test village with workshop
$villageName = 'Test Siege Cap ' . time();
$worldId = 1;
$x = 500;
$y = 500;
$stmt = $db->prepare("INSERT INTO villages (name, user_id, world_id, x_coord, y_coord, wood, clay, iron, farm_capacity) 
            VALUES (?, ?, ?, ?, ?, 100000, 100000, 100000, 10000)");
$stmt->bind_param("siiii", $villageName, $userId, $worldId, $x, $y);
$stmt->execute();
$villageId = $stmt->insert_id;
$stmt->close();

// Add workshop building
$workshopTypeResult = $db->query("SELECT id FROM building_types WHERE internal_name = 'workshop' LIMIT 1");
$workshopTypeRow = $workshopTypeResult->fetch_assoc();
$workshopTypeId = $workshopTypeRow['id'];

$db->query("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES ($villageId, $workshopTypeId, 10)");

// Get ram unit type (try both 'ram' and 'battering_ram')
$ramResult = $db->query("SELECT id FROM unit_types WHERE internal_name IN ('ram', 'battering_ram') LIMIT 1");
$ramRow = $ramResult->fetch_assoc();
$ramId = $ramRow ? $ramRow['id'] : null;

if (!$ramId) {
    echo "SKIP: Ram unit type not found\n";
    exit(0);
}

$unitManager = new UnitManager($db);

// Test 1: Can recruit when under cap
echo "Test 1: Can recruit siege units when under cap\n";
$result = $unitManager->recruitUnits($villageId, $ramId, 50, 10);
if (!$result['success']) {
    echo "Failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    exit(1);
}
echo "✓ Test 1 passed: Can recruit 50 rams when under cap\n\n";

// Test 2: Add existing siege units to approach cap (200 total)
echo "Test 2: Add existing units to approach cap\n";
$db->query("INSERT INTO village_units (village_id, unit_type_id, count) VALUES ($villageId, $ramId, 140)");
// Now we have: 140 existing + 50 queued = 190 total

$result2 = $unitManager->recruitUnits($villageId, $ramId, 10, 10);
if (!$result2['success']) {
    echo "Failed: " . ($result2['error'] ?? 'Unknown error') . "\n";
    exit(1);
}
echo "✓ Test 2 passed: Can recruit 10 more rams (190 + 10 = 200, at cap)\n\n";

// Test 3: Cannot exceed cap
echo "Test 3: Cannot exceed siege cap\n";
$result3 = $unitManager->recruitUnits($villageId, $ramId, 1, 10);
if ($result3['success']) {
    echo "Failed: Should have been rejected for exceeding cap\n";
    exit(1);
}
if ($result3['code'] !== 'ERR_CAP') {
    echo "Failed: Wrong error code. Expected ERR_CAP, got " . ($result3['code'] ?? 'none') . "\n";
    exit(1);
}
if (!isset($result3['cap']) || $result3['cap'] !== 200) {
    echo "Failed: Cap value not returned correctly\n";
    exit(1);
}
echo "✓ Test 3 passed: Correctly rejects recruitment exceeding cap with ERR_CAP\n";
echo "  Error message: " . $result3['error'] . "\n";
echo "  Current count: " . $result3['current'] . ", Cap: " . $result3['cap'] . "\n\n";

// Test 4: Test with catapults (different siege unit)
echo "Test 4: Cap applies to all siege units combined\n";
$catapultResult = $db->query("SELECT id FROM unit_types WHERE internal_name = 'catapult' LIMIT 1");
$catapultRow = $catapultResult->fetch_assoc();
$catapultId = $catapultRow ? $catapultRow['id'] : null;

if ($catapultId) {
    $result4 = $unitManager->recruitUnits($villageId, $catapultId, 1, 10);
    if ($result4['success']) {
        echo "Failed: Should have been rejected (cap applies to all siege)\n";
        exit(1);
    }
    if ($result4['code'] !== 'ERR_CAP') {
        echo "Failed: Wrong error code for catapult\n";
        exit(1);
    }
    echo "✓ Test 4 passed: Cap applies to all siege unit types combined\n\n";
} else {
    echo "⊘ Test 4 skipped: Catapult unit type not found\n\n";
}

// Cleanup
$db->query("DELETE FROM unit_queue WHERE village_id = $villageId");
$db->query("DELETE FROM village_units WHERE village_id = $villageId");
$db->query("DELETE FROM village_buildings WHERE village_id = $villageId");
$db->query("DELETE FROM villages WHERE id = $villageId");
$db->query("DELETE FROM users WHERE id = $userId");

echo "✅ All per-village siege cap tests passed!\n";
?>
