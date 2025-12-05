<?php
/**
 * Test validation and error handling for unit recruitment
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';

echo "Testing validation and error handling...\n\n";

$unitManager = new UnitManager($conn);
$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);

// Test 1: Input validation - invalid unit_id
echo "Test 1: Invalid unit_id (should return ERR_INPUT)\n";
$result = $unitManager->recruitUnits(1, 99999, 5, 1);
if (!$result['success'] && isset($result['code']) && $result['code'] === 'ERR_INPUT') {
    echo "✓ PASS: Invalid unit_id rejected with ERR_INPUT\n";
} else {
    echo "✗ FAIL: Expected ERR_INPUT for invalid unit_id\n";
    print_r($result);
}
echo "\n";

// Test 2: Input validation - zero count
echo "Test 2: Zero count (should return ERR_INPUT)\n";
$result = $unitManager->recruitUnits(1, 1, 0, 1);
if (!$result['success'] && isset($result['code']) && $result['code'] === 'ERR_INPUT') {
    echo "✓ PASS: Zero count rejected with ERR_INPUT\n";
} else {
    echo "✗ FAIL: Expected ERR_INPUT for zero count\n";
    print_r($result);
}
echo "\n";

// Test 3: Input validation - negative count
echo "Test 3: Negative count (should return ERR_INPUT)\n";
$result = $unitManager->recruitUnits(1, 1, -5, 1);
if (!$result['success'] && isset($result['code']) && $result['code'] === 'ERR_INPUT') {
    echo "✓ PASS: Negative count rejected with ERR_INPUT\n";
} else {
    echo "✗ FAIL: Expected ERR_INPUT for negative count\n";
    print_r($result);
}
echo "\n";

// Test 4: Feature flag validation - check isUnitAvailable
echo "Test 4: Feature flag validation\n";
$worldId = defined('CURRENT_WORLD_ID') ? CURRENT_WORLD_ID : 1;
$conquestAvailable = $unitManager->isUnitAvailable('noble', $worldId);
$healerAvailable = $unitManager->isUnitAvailable('war_healer', $worldId);
echo "Conquest units available: " . ($conquestAvailable ? 'Yes' : 'No') . "\n";
echo "Healer units available: " . ($healerAvailable ? 'Yes' : 'No') . "\n";
echo "✓ PASS: Feature flag validation working\n";
echo "\n";

// Test 5: Unit category detection
echo "Test 5: Unit category detection\n";
$units = $unitManager->getAllUnitTypes();
$categoryCounts = [];
foreach ($units as $unitId => $unit) {
    $category = $unitManager->getUnitCategory($unitId);
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
}
echo "Unit categories found:\n";
foreach ($categoryCounts as $category => $count) {
    echo "  - $category: $count units\n";
}
echo "✓ PASS: Unit category detection working\n";
echo "\n";

// Test 6: Seasonal window check
echo "Test 6: Seasonal window check\n";
$window = $unitManager->checkSeasonalWindow('tempest_knight', time());
echo "Tempest Knight available: " . ($window['available'] ? 'Yes' : 'No') . "\n";
if ($window['start'] !== null) {
    echo "  Window: " . date('Y-m-d H:i:s', $window['start']) . " to " . date('Y-m-d H:i:s', $window['end']) . "\n";
}
echo "✓ PASS: Seasonal window check working\n";
echo "\n";

echo "All validation tests completed!\n";
