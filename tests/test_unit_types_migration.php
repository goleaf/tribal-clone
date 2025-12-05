<?php
/**
 * Test: Verify unit_types table migration for military units system
 * 
 * Tests that the migration added all required columns and they can store JSON data
 */

require_once __DIR__ . '/bootstrap.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "FAIL: No database connection.\n";
    exit(1);
}

echo "Testing unit_types table migration...\n\n";

$allPassed = true;

// Test 1: Verify category column exists and has default value
echo "Test 1: Verify category column exists with default value...\n";
$result = $conn->query("PRAGMA table_info(unit_types)");
$categoryFound = false;
$categoryDefault = null;
while ($row = $result->fetch_assoc()) {
    if ($row['name'] === 'category') {
        $categoryFound = true;
        $categoryDefault = $row['dflt_value'];
        break;
    }
}
if ($categoryFound && $categoryDefault === "'infantry'") {
    echo "  PASS: category column exists with default 'infantry'\n";
} else {
    echo "  FAIL: category column not found or wrong default\n";
    $allPassed = false;
}

// Test 2: Verify rps_bonuses column exists
echo "\nTest 2: Verify rps_bonuses column exists...\n";
$result = $conn->query("PRAGMA table_info(unit_types)");
$rpsBonusesFound = false;
while ($row = $result->fetch_assoc()) {
    if ($row['name'] === 'rps_bonuses') {
        $rpsBonusesFound = true;
        break;
    }
}
if ($rpsBonusesFound) {
    echo "  PASS: rps_bonuses column exists\n";
} else {
    echo "  FAIL: rps_bonuses column not found\n";
    $allPassed = false;
}

// Test 3: Verify special_abilities column exists
echo "\nTest 3: Verify special_abilities column exists...\n";
$result = $conn->query("PRAGMA table_info(unit_types)");
$specialAbilitiesFound = false;
while ($row = $result->fetch_assoc()) {
    if ($row['name'] === 'special_abilities') {
        $specialAbilitiesFound = true;
        break;
    }
}
if ($specialAbilitiesFound) {
    echo "  PASS: special_abilities column exists\n";
} else {
    echo "  FAIL: special_abilities column not found\n";
    $allPassed = false;
}

// Test 4: Verify aura_config column exists
echo "\nTest 4: Verify aura_config column exists...\n";
$result = $conn->query("PRAGMA table_info(unit_types)");
$auraConfigFound = false;
while ($row = $result->fetch_assoc()) {
    if ($row['name'] === 'aura_config') {
        $auraConfigFound = true;
        break;
    }
}
if ($auraConfigFound) {
    echo "  PASS: aura_config column exists\n";
} else {
    echo "  FAIL: aura_config column not found\n";
    $allPassed = false;
}

// Test 5: Test JSON storage and retrieval
echo "\nTest 5: Test JSON data storage and retrieval...\n";
$testUnitId = 1; // Use existing unit

// Store JSON data
$rpsBonus = json_encode(['vs_cavalry' => 1.4]);
$specialAbilities = json_encode(['pike_formation']);
$auraConfig = json_encode(['def_multiplier' => 1.15, 'resolve_bonus' => 5]);

$stmt = $conn->prepare("UPDATE unit_types SET category = ?, rps_bonuses = ?, special_abilities = ?, aura_config = ? WHERE id = ?");
$category = 'infantry';
$stmt->bind_param('ssssi', $category, $rpsBonus, $specialAbilities, $auraConfig, $testUnitId);
$updateSuccess = $stmt->execute();

if ($updateSuccess) {
    // Retrieve and verify
    $result = $conn->query("SELECT category, rps_bonuses, special_abilities, aura_config FROM unit_types WHERE id = $testUnitId");
    $row = $result->fetch_assoc();
    
    $retrievedRps = json_decode($row['rps_bonuses'], true);
    $retrievedAbilities = json_decode($row['special_abilities'], true);
    $retrievedAura = json_decode($row['aura_config'], true);
    
    if ($row['category'] === 'infantry' &&
        $retrievedRps['vs_cavalry'] === 1.4 &&
        in_array('pike_formation', $retrievedAbilities) &&
        $retrievedAura['def_multiplier'] === 1.15) {
        echo "  PASS: JSON data stored and retrieved correctly\n";
    } else {
        echo "  FAIL: JSON data not stored/retrieved correctly\n";
        $allPassed = false;
    }
} else {
    echo "  FAIL: Could not update test data\n";
    $allPassed = false;
}

// Test 6: Test NULL values are allowed
echo "\nTest 6: Test NULL values are allowed for JSON columns...\n";
$stmt = $conn->prepare("UPDATE unit_types SET rps_bonuses = NULL, special_abilities = NULL, aura_config = NULL WHERE id = ?");
$stmt->bind_param('i', $testUnitId);
$nullUpdateSuccess = $stmt->execute();

if ($nullUpdateSuccess) {
    $result = $conn->query("SELECT rps_bonuses, special_abilities, aura_config FROM unit_types WHERE id = $testUnitId");
    $row = $result->fetch_assoc();
    
    if ($row['rps_bonuses'] === null && $row['special_abilities'] === null && $row['aura_config'] === null) {
        echo "  PASS: NULL values stored correctly\n";
    } else {
        echo "  FAIL: NULL values not stored correctly\n";
        $allPassed = false;
    }
} else {
    echo "  FAIL: Could not update to NULL values\n";
    $allPassed = false;
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
if ($allPassed) {
    echo "ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED\n";
    exit(1);
}
