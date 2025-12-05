<?php
/**
 * Test resource and population validation in recruitUnits
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';

echo "Testing resource and population validation...\n\n";

$unitManager = new UnitManager($conn);
$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);

// Get a test village
$stmt = $conn->prepare("SELECT id FROM villages LIMIT 1");
$stmt->execute();
$villageRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$villageRow) {
    echo "No villages found in database. Creating test village...\n";
    // Create a test village
    $stmt = $conn->prepare("INSERT INTO villages (name, user_id, x, y, wood, clay, iron, farm_capacity) VALUES (?, 1, 0, 0, 100, 100, 100, 100)");
    $villageName = 'Test Village';
    $stmt->bind_param("s", $villageName);
    $stmt->execute();
    $villageId = $stmt->insert_id;
    $stmt->close();
} else {
    $villageId = (int)$villageRow['id'];
}

echo "Using village ID: $villageId\n\n";

// Get a unit type for testing
$units = $unitManager->getAllUnitTypes();
$testUnit = null;
foreach ($units as $unitId => $unit) {
    if ($unit['building_type'] === 'barracks') {
        $testUnit = $unit;
        $testUnitId = $unitId;
        break;
    }
}

if (!$testUnit) {
    echo "No barracks units found for testing.\n";
    exit(1);
}

echo "Using unit: {$testUnit['name']} (ID: $testUnitId)\n";
echo "Unit costs: Wood={$testUnit['cost_wood']}, Clay={$testUnit['cost_clay']}, Iron={$testUnit['cost_iron']}\n";
echo "Unit population: {$testUnit['population']}\n\n";

// Test 1: Resource validation - insufficient resources
echo "Test 1: Insufficient resources (should return ERR_RES)\n";
// Set village resources to very low
$conn->query("UPDATE villages SET wood = 10, clay = 10, iron = 10 WHERE id = $villageId");
$result = $unitManager->recruitUnits($villageId, $testUnitId, 100, 1);
if (!$result['success'] && isset($result['code']) && $result['code'] === 'ERR_RES') {
    echo "✓ PASS: Insufficient resources rejected with ERR_RES\n";
    if (isset($result['missing'])) {
        echo "  Missing resources: Wood={$result['missing']['wood']}, Clay={$result['missing']['clay']}, Iron={$result['missing']['iron']}\n";
    }
} else {
    echo "✗ FAIL: Expected ERR_RES for insufficient resources\n";
    print_r($result);
}
echo "\n";

// Test 2: Population validation - insufficient farm capacity
echo "Test 2: Insufficient farm capacity (should return ERR_POP)\n";
// Set village resources to high but farm capacity to low
$conn->query("UPDATE villages SET wood = 100000, clay = 100000, iron = 100000, farm_capacity = 10 WHERE id = $villageId");
$result = $unitManager->recruitUnits($villageId, $testUnitId, 100, 1);
if (!$result['success'] && isset($result['code']) && $result['code'] === 'ERR_POP') {
    echo "✓ PASS: Insufficient farm capacity rejected with ERR_POP\n";
    if (isset($result['farm_capacity'], $result['population_needed'])) {
        echo "  Farm capacity: {$result['farm_capacity']}\n";
        echo "  Population needed: {$result['population_needed']}\n";
    }
} else {
    echo "✗ FAIL: Expected ERR_POP for insufficient farm capacity\n";
    print_r($result);
}
echo "\n";

// Test 3: Successful recruitment with sufficient resources and population
echo "Test 3: Successful recruitment with sufficient resources and population\n";
// Set village resources and farm capacity to high
$conn->query("UPDATE villages SET wood = 100000, clay = 100000, iron = 100000, farm_capacity = 10000 WHERE id = $villageId");
$result = $unitManager->recruitUnits($villageId, $testUnitId, 5, 1);
if ($result['success']) {
    echo "✓ PASS: Recruitment successful with sufficient resources and population\n";
    if (isset($result['finish_time'])) {
        echo "  Finish time: " . date('Y-m-d H:i:s', $result['finish_time']) . "\n";
    }
} else {
    echo "✗ FAIL: Expected successful recruitment\n";
    print_r($result);
}
echo "\n";

echo "All resource and population validation tests completed!\n";
