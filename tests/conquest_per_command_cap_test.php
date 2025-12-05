<?php
/**
 * Test for per-command conquest cap enforcement in recruitUnits()
 * 
 * Validates: Requirements 9.3
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

global $conn;
$db = $conn;

echo "Testing Per-Command Conquest Cap Enforcement...\n\n";

// Clean up test data
$db->query("DELETE FROM villages WHERE name LIKE 'Test Conquest Cap%'");
$db->query("DELETE FROM users WHERE username LIKE 'test_conquest_cap%'");

// Create test user
$username = 'test_conquest_cap_' . time();
$password = password_hash('test123', PASSWORD_DEFAULT);
$email = 'test_conquest_' . time() . '@example.com';
$db->query("INSERT INTO users (username, password, email) VALUES ('$username', '$password', '$email')");
$userId = $db->insert_id;

// Create test village
$villageName = 'Test Conquest Cap ' . time();
$worldId = 1;
$x = 500;
$y = 500;
$stmt = $db->prepare("INSERT INTO villages (name, user_id, world_id, x_coord, y_coord, wood, clay, iron, farm_capacity, noble_coins, standards) 
            VALUES (?, ?, ?, ?, ?, 100000, 100000, 100000, 10000, 10, 10)");
$stmt->bind_param("siiii", $villageName, $userId, $worldId, $x, $y);
$stmt->execute();
$villageId = $stmt->insert_id;
$stmt->close();

// Add academy building for nobles
$academyTypeResult = $db->query("SELECT id FROM building_types WHERE internal_name = 'academy' LIMIT 1");
$academyTypeRow = $academyTypeResult->fetch_assoc();
if ($academyTypeRow) {
    $academyTypeId = $academyTypeRow['id'];
    $db->query("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES ($villageId, $academyTypeId, 10)");
}

// Get noble unit type
$nobleResult = $db->query("SELECT id FROM unit_types WHERE internal_name IN ('noble', 'nobleman') LIMIT 1");
$nobleRow = $nobleResult->fetch_assoc();
$nobleId = $nobleRow ? $nobleRow['id'] : null;

if (!$nobleId) {
    echo "SKIP: Noble unit type not found\n";
    // Cleanup
    $db->query("DELETE FROM village_buildings WHERE village_id = $villageId");
    $db->query("DELETE FROM villages WHERE id = $villageId");
    $db->query("DELETE FROM users WHERE id = $userId");
    exit(0);
}

$unitManager = new UnitManager($db);

// Test 1: Can recruit 1 conquest unit (at the limit)
echo "Test 1: Can recruit 1 conquest unit (at per-command limit)\n";
$result = $unitManager->recruitUnits($villageId, $nobleId, 1, 10);
if (!$result['success']) {
    echo "Failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    echo "Code: " . ($result['code'] ?? 'none') . "\n";
    exit(1);
}
echo "✓ Test 1 passed: Can recruit 1 noble (at limit)\n\n";

// Clear the queue for next test
$db->query("DELETE FROM unit_queue WHERE village_id = $villageId");

// Test 2: Cannot recruit more than 1 conquest unit in a single batch
echo "Test 2: Cannot recruit more than 1 conquest unit in a single batch\n";
$result2 = $unitManager->recruitUnits($villageId, $nobleId, 2, 10);
if ($result2['success']) {
    echo "Failed: Should have been rejected for exceeding per-command cap\n";
    exit(1);
}
if ($result2['code'] !== 'ERR_CAP') {
    echo "Failed: Wrong error code. Expected ERR_CAP, got " . ($result2['code'] ?? 'none') . "\n";
    exit(1);
}
if (!isset($result2['cap']) || $result2['cap'] !== 1) {
    echo "Failed: Cap value not returned correctly. Expected 1, got " . ($result2['cap'] ?? 'none') . "\n";
    exit(1);
}
echo "✓ Test 2 passed: Correctly rejects training >1 conquest unit with ERR_CAP\n";
echo "  Error message: " . $result2['error'] . "\n";
echo "  Cap: " . $result2['cap'] . ", Requested: " . $result2['requested'] . "\n\n";

// Test 3: Can recruit multiple conquest units sequentially (different batches)
echo "Test 3: Can recruit multiple conquest units in separate batches\n";
$result3a = $unitManager->recruitUnits($villageId, $nobleId, 1, 10);
if (!$result3a['success']) {
    echo "Failed on first batch: " . ($result3a['error'] ?? 'Unknown') . "\n";
    exit(1);
}
$result3b = $unitManager->recruitUnits($villageId, $nobleId, 1, 10);
if (!$result3b['success']) {
    echo "Failed on second batch: " . ($result3b['error'] ?? 'Unknown') . "\n";
    exit(1);
}
echo "✓ Test 3 passed: Can recruit multiple conquest units in separate batches\n\n";

// Test 4: Test with envoy/standard bearer if available
$envoyResult = $db->query("SELECT id FROM unit_types WHERE internal_name IN ('standard_bearer', 'envoy') LIMIT 1");
$envoyRow = $envoyResult->fetch_assoc();
$envoyId = $envoyRow ? $envoyRow['id'] : null;

if ($envoyId) {
    echo "Test 4: Per-command cap applies to all conquest unit types\n";
    $result4 = $unitManager->recruitUnits($villageId, $envoyId, 2, 10);
    if ($result4['success']) {
        echo "Failed: Should have been rejected for envoy/standard bearer too\n";
        exit(1);
    }
    if ($result4['code'] !== 'ERR_CAP') {
        echo "Failed: Wrong error code for envoy\n";
        exit(1);
    }
    echo "✓ Test 4 passed: Cap applies to all conquest unit types\n\n";
} else {
    echo "⊘ Test 4 skipped: Envoy/Standard Bearer unit type not found\n\n";
}

// Cleanup
$db->query("DELETE FROM unit_queue WHERE village_id = $villageId");
$db->query("DELETE FROM village_units WHERE village_id = $villageId");
$db->query("DELETE FROM village_buildings WHERE village_id = $villageId");
$db->query("DELETE FROM villages WHERE id = $villageId");
$db->query("DELETE FROM users WHERE id = $userId");

echo "✅ All per-command conquest cap tests passed!\n";
?>
