<?php
/**
 * Test for per-account elite cap enforcement in recruitUnits()
 * 
 * Validates: Requirements 9.2
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

global $conn;
$db = $conn;

echo "Testing Per-Account Elite Cap Enforcement in recruitUnits()...\n\n";

// Clean up test data
$db->query("DELETE FROM villages WHERE name LIKE 'Test Elite Cap%'");
$db->query("DELETE FROM users WHERE username LIKE 'test_elite_cap%'");

// Create test user
$username = 'test_elite_cap_' . time();
$password = password_hash('test123', PASSWORD_DEFAULT);
$email = 'test_elite_' . time() . '@example.com';
$db->query("INSERT INTO users (username, password, email) VALUES ('$username', '$password', '$email')");
$userId = $db->insert_id;

// Create two test villages for the same user
$worldId = 1;
$villages = [];
for ($i = 1; $i <= 2; $i++) {
    $villageName = "Test Elite Cap Village $i " . time();
    $x = 500 + $i;
    $y = 500 + $i;
    $stmt = $db->prepare("INSERT INTO villages (name, user_id, world_id, x_coord, y_coord, wood, clay, iron, farm_capacity) 
                VALUES (?, ?, ?, ?, ?, 100000, 100000, 100000, 10000)");
    $stmt->bind_param("siiii", $villageName, $userId, $worldId, $x, $y);
    $stmt->execute();
    $villages[$i] = $stmt->insert_id;
    $stmt->close();
    
    // Add barracks to each village
    $barracksTypeResult = $db->query("SELECT id FROM building_types WHERE internal_name = 'barracks' LIMIT 1");
    $barracksTypeRow = $barracksTypeResult->fetch_assoc();
    $barracksTypeId = $barracksTypeRow['id'];
    $db->query("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES ({$villages[$i]}, $barracksTypeId, 15)");
}

// Get warden unit type (elite infantry)
$wardenResult = $db->query("SELECT id FROM unit_types WHERE internal_name = 'warden' LIMIT 1");
$wardenRow = $wardenResult->fetch_assoc();
$wardenId = $wardenRow ? $wardenRow['id'] : null;

if (!$wardenId) {
    echo "SKIP: Warden unit type not found (elite units may not be in database)\n";
    // Cleanup
    foreach ($villages as $vid) {
        $db->query("DELETE FROM village_buildings WHERE village_id = $vid");
        $db->query("DELETE FROM villages WHERE id = $vid");
    }
    $db->query("DELETE FROM users WHERE id = $userId");
    exit(0);
}

$unitManager = new UnitManager($db);

// Test 1: Can recruit elite units when under cap (cap is 100 for wardens)
echo "Test 1: Can recruit elite units when under cap\n";
$result = $unitManager->recruitUnits($villages[1], $wardenId, 50, 15);
if (!$result['success']) {
    echo "Failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    echo "Code: " . ($result['code'] ?? 'none') . "\n";
    exit(1);
}
echo "✓ Test 1 passed: Can recruit 50 wardens in village 1\n\n";

// Test 2: Can recruit more in a different village (same account)
echo "Test 2: Can recruit more elite units in different village (same account)\n";
$result2 = $unitManager->recruitUnits($villages[2], $wardenId, 40, 15);
if (!$result2['success']) {
    echo "Failed: " . ($result2['error'] ?? 'Unknown error') . "\n";
    exit(1);
}
echo "✓ Test 2 passed: Can recruit 40 more wardens in village 2 (90 total across account)\n\n";

// Test 3: Cannot exceed per-account cap
echo "Test 3: Cannot exceed per-account elite cap\n";
$result3 = $unitManager->recruitUnits($villages[1], $wardenId, 11, 15);
if ($result3['success']) {
    echo "Failed: Should have been rejected for exceeding account cap\n";
    exit(1);
}
if ($result3['code'] !== 'ERR_CAP') {
    echo "Failed: Wrong error code. Expected ERR_CAP, got " . ($result3['code'] ?? 'none') . "\n";
    exit(1);
}
if (!isset($result3['cap']) || $result3['cap'] !== 100) {
    echo "Failed: Cap value not returned correctly. Expected 100, got " . ($result3['cap'] ?? 'none') . "\n";
    exit(1);
}
echo "✓ Test 3 passed: Correctly rejects recruitment exceeding account cap with ERR_CAP\n";
echo "  Error message: " . $result3['error'] . "\n";
echo "  Current count: " . $result3['current'] . ", Cap: " . $result3['cap'] . "\n\n";

// Test 4: Can recruit exactly up to cap
echo "Test 4: Can recruit exactly up to cap\n";
$result4 = $unitManager->recruitUnits($villages[2], $wardenId, 10, 15);
if (!$result4['success']) {
    echo "Failed: Should be able to recruit exactly to cap. Error: " . ($result4['error'] ?? 'Unknown') . "\n";
    exit(1);
}
echo "✓ Test 4 passed: Can recruit exactly to cap (100 total)\n\n";

// Test 5: Now at cap, cannot recruit any more
echo "Test 5: At cap, cannot recruit any more\n";
$result5 = $unitManager->recruitUnits($villages[1], $wardenId, 1, 15);
if ($result5['success']) {
    echo "Failed: Should be rejected when at cap\n";
    exit(1);
}
if ($result5['code'] !== 'ERR_CAP') {
    echo "Failed: Wrong error code when at cap\n";
    exit(1);
}
echo "✓ Test 5 passed: Correctly rejects when at cap\n\n";

// Cleanup
foreach ($villages as $vid) {
    $db->query("DELETE FROM unit_queue WHERE village_id = $vid");
    $db->query("DELETE FROM village_units WHERE village_id = $vid");
    $db->query("DELETE FROM village_buildings WHERE village_id = $vid");
    $db->query("DELETE FROM villages WHERE id = $vid");
}
$db->query("DELETE FROM users WHERE id = $userId");

echo "✅ All per-account elite cap tests passed!\n";
?>
