<?php
/**
 * Test for checkEliteUnitCap() method
 * 
 * Validates: Requirements 9.2
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

function testEliteUnitCap() {
    global $conn;
    
    echo "Testing Elite Unit Cap Enforcement...\n\n";
    
    // Create test user
    $testUserId = createTestUser($conn);
    echo "Created test user ID: $testUserId\n";
    
    // Create test villages for the user
    $village1 = createTestVillage($conn, $testUserId, "Test Village 1");
    $village2 = createTestVillage($conn, $testUserId, "Test Village 2");
    echo "Created test villages: $village1, $village2\n";
    
    // Get warden unit type
    $wardenUnit = getUnitByInternal($conn, 'warden');
    if (!$wardenUnit) {
        echo "ERROR: Warden unit not found in database\n";
        cleanup($conn, $testUserId);
        return false;
    }
    echo "Found warden unit (ID: {$wardenUnit['id']})\n\n";
    
    $unitManager = new UnitManager($conn);
    
    // Test 1: Check cap with no existing units
    echo "Test 1: Check cap with no existing units\n";
    $result = $unitManager->checkEliteUnitCap($testUserId, 'warden', 10);
    assert($result['can_train'] === true, "Should be able to train with no existing units");
    assert($result['current'] === 0, "Current count should be 0");
    assert($result['max'] === 100, "Max cap should be 100 for warden");
    echo "✓ Can train 10 wardens (0/100)\n\n";
    
    // Test 2: Add some units to village 1
    echo "Test 2: Add 30 wardens to village 1\n";
    addUnitsToVillage($conn, $village1, $wardenUnit['id'], 30);
    $result = $unitManager->checkEliteUnitCap($testUserId, 'warden', 10);
    assert($result['can_train'] === true, "Should be able to train more");
    assert($result['current'] === 30, "Current count should be 30");
    echo "✓ Can train 10 more wardens (30/100)\n\n";
    
    // Test 3: Add units to village 2
    echo "Test 3: Add 40 wardens to village 2\n";
    addUnitsToVillage($conn, $village2, $wardenUnit['id'], 40);
    $result = $unitManager->checkEliteUnitCap($testUserId, 'warden', 10);
    assert($result['can_train'] === true, "Should be able to train more");
    assert($result['current'] === 70, "Current count should be 70 (30+40)");
    echo "✓ Can train 10 more wardens (70/100)\n\n";
    
    // Test 4: Try to exceed cap
    echo "Test 4: Try to train 40 wardens (would exceed cap)\n";
    $result = $unitManager->checkEliteUnitCap($testUserId, 'warden', 40);
    assert($result['can_train'] === false, "Should NOT be able to train (would exceed cap)");
    assert($result['current'] === 70, "Current count should still be 70");
    assert($result['max'] === 100, "Max cap should be 100");
    echo "✓ Cannot train 40 wardens (70/100) - would exceed cap\n\n";
    
    // Test 5: Train exactly to cap
    echo "Test 5: Train exactly 30 wardens (to reach cap)\n";
    $result = $unitManager->checkEliteUnitCap($testUserId, 'warden', 30);
    assert($result['can_train'] === true, "Should be able to train exactly to cap");
    assert($result['current'] === 70, "Current count should be 70");
    echo "✓ Can train exactly 30 wardens to reach cap (70/100)\n\n";
    
    // Test 6: Add queued units
    echo "Test 6: Add 20 wardens to queue\n";
    addUnitsToQueue($conn, $village1, $wardenUnit['id'], 20);
    $result = $unitManager->checkEliteUnitCap($testUserId, 'warden', 15);
    assert($result['can_train'] === false, "Should NOT be able to train (queued units count)");
    assert($result['current'] === 90, "Current count should be 90 (70 existing + 20 queued)");
    echo "✓ Cannot train 15 more (90/100 with queued units)\n\n";
    
    // Test 7: Non-elite unit (no cap)
    echo "Test 7: Check non-elite unit (pikeneer)\n";
    $result = $unitManager->checkEliteUnitCap($testUserId, 'pikeneer', 1000);
    assert($result['can_train'] === true, "Should be able to train any amount of non-elite units");
    assert($result['max'] === -1, "Max should be -1 for non-elite units");
    echo "✓ No cap for non-elite units\n\n";
    
    // Test 8: Different elite unit (ranger)
    echo "Test 8: Check different elite unit (ranger)\n";
    $result = $unitManager->checkEliteUnitCap($testUserId, 'ranger', 50);
    assert($result['can_train'] === true, "Should be able to train rangers (different unit)");
    assert($result['current'] === 0, "Current ranger count should be 0");
    assert($result['max'] === 100, "Max cap should be 100 for ranger");
    echo "✓ Can train rangers (separate cap from wardens)\n\n";
    
    // Cleanup
    cleanup($conn, $testUserId);
    
    echo "All tests passed! ✓\n";
    return true;
}

function createTestUser($conn) {
    $username = 'test_user_' . time() . '_' . rand(1000, 9999);
    $email = $username . '@test.com';
    $password = password_hash('test123', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password);
    $stmt->execute();
    $userId = $stmt->insert_id;
    $stmt->close();
    
    return $userId;
}

function createTestVillage($conn, $userId, $name) {
    $x = rand(100, 200);
    $y = rand(100, 200);
    
    $stmt = $conn->prepare("
        INSERT INTO villages (user_id, name, x, y, farm_capacity, wood, clay, iron)
        VALUES (?, ?, ?, ?, 1000, 10000, 10000, 10000)
    ");
    $stmt->bind_param("isii", $userId, $name, $x, $y);
    $stmt->execute();
    $villageId = $stmt->insert_id;
    $stmt->close();
    
    return $villageId;
}

function getUnitByInternal($conn, $internal) {
    $stmt = $conn->prepare("SELECT * FROM unit_types WHERE internal_name = ? LIMIT 1");
    $stmt->bind_param("s", $internal);
    $stmt->execute();
    $result = $stmt->get_result();
    $unit = $result->fetch_assoc();
    $stmt->close();
    
    return $unit;
}

function addUnitsToVillage($conn, $villageId, $unitTypeId, $count) {
    // Check if units already exist
    $stmt = $conn->prepare("SELECT id, count FROM village_units WHERE village_id = ? AND unit_type_id = ?");
    $stmt->bind_param("ii", $villageId, $unitTypeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $newCount = $row['count'] + $count;
        $stmt->close();
        
        $updateStmt = $conn->prepare("UPDATE village_units SET count = ? WHERE id = ?");
        $updateStmt->bind_param("ii", $newCount, $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        $stmt->close();
        
        $insertStmt = $conn->prepare("INSERT INTO village_units (village_id, unit_type_id, count) VALUES (?, ?, ?)");
        $insertStmt->bind_param("iii", $villageId, $unitTypeId, $count);
        $insertStmt->execute();
        $insertStmt->close();
    }
}

function addUnitsToQueue($conn, $villageId, $unitTypeId, $count) {
    $startTime = time();
    $finishTime = $startTime + 3600; // 1 hour from now
    
    $stmt = $conn->prepare("
        INSERT INTO unit_queue (village_id, unit_type_id, count, count_finished, started_at, finish_at, building_type)
        VALUES (?, ?, ?, 0, ?, ?, 'barracks')
    ");
    $stmt->bind_param("iiiii", $villageId, $unitTypeId, $count, $startTime, $finishTime);
    $stmt->execute();
    $stmt->close();
}

function cleanup($conn, $userId) {
    // Delete test data
    $conn->query("DELETE FROM unit_queue WHERE village_id IN (SELECT id FROM villages WHERE user_id = $userId)");
    $conn->query("DELETE FROM village_units WHERE village_id IN (SELECT id FROM villages WHERE user_id = $userId)");
    $conn->query("DELETE FROM villages WHERE user_id = $userId");
    $conn->query("DELETE FROM users WHERE id = $userId");
}

// Run the test
try {
    $success = testEliteUnitCap();
    exit($success ? 0 : 1);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
