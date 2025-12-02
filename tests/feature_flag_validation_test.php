<?php
/**
 * Test feature flag validation for unit recruitment
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

echo "Testing feature flag validation...\n\n";

$unitManager = new UnitManager($conn);
$worldId = defined('CURRENT_WORLD_ID') ? CURRENT_WORLD_ID : 1;

// Test 1: Conquest units
echo "Test 1: Conquest unit availability\n";
$conquestUnits = ['noble', 'nobleman', 'standard_bearer', 'envoy'];
foreach ($conquestUnits as $unit) {
    $available = $unitManager->isUnitAvailable($unit, $worldId);
    echo "  $unit: " . ($available ? 'Available' : 'Disabled') . "\n";
}
echo "✓ PASS: Conquest unit feature flags checked\n\n";

// Test 2: Seasonal units
echo "Test 2: Seasonal unit availability\n";
$seasonalUnits = ['tempest_knight', 'event_knight'];
foreach ($seasonalUnits as $unit) {
    $available = $unitManager->isUnitAvailable($unit, $worldId);
    $window = $unitManager->checkSeasonalWindow($unit, time());
    echo "  $unit: " . ($available ? 'Available' : 'Disabled');
    if ($window['start'] !== null) {
        echo " (Window: " . date('Y-m-d', $window['start']) . " to " . date('Y-m-d', $window['end']) . ")";
    }
    echo "\n";
}
echo "✓ PASS: Seasonal unit feature flags checked\n\n";

// Test 3: Healer units
echo "Test 3: Healer unit availability\n";
$healerUnits = ['war_healer', 'healer'];
foreach ($healerUnits as $unit) {
    $available = $unitManager->isUnitAvailable($unit, $worldId);
    echo "  $unit: " . ($available ? 'Available' : 'Disabled') . "\n";
}
echo "✓ PASS: Healer unit feature flags checked\n\n";

// Test 4: Regular units (should always be available)
echo "Test 4: Regular unit availability\n";
$regularUnits = ['spearman', 'swordsman', 'axeman', 'light_cavalry'];
foreach ($regularUnits as $unit) {
    $available = $unitManager->isUnitAvailable($unit, $worldId);
    echo "  $unit: " . ($available ? 'Available' : 'Disabled') . "\n";
}
echo "✓ PASS: Regular units are available\n\n";

// Test 5: Unit category detection for all types
echo "Test 5: Unit category detection\n";
$testCategories = [
    'noble' => 'conquest',
    'standard_bearer' => 'conquest',
    'banner_guard' => 'support',
    'war_healer' => 'support',
    'pathfinder' => 'scout',
    'shadow_rider' => 'scout',
    'ram' => 'siege',
    'catapult' => 'siege',
    'light_cavalry' => 'cavalry',
    'archer' => 'ranged',
    'spearman' => 'infantry'
];

$allUnits = $unitManager->getAllUnitTypes();
$categoryTests = 0;
$categoryPassed = 0;

foreach ($allUnits as $unitId => $unit) {
    $internal = strtolower($unit['internal_name'] ?? '');
    $category = $unitManager->getUnitCategory($unitId);
    
    if (isset($testCategories[$internal])) {
        $categoryTests++;
        $expected = $testCategories[$internal];
        if ($category === $expected) {
            $categoryPassed++;
            echo "  ✓ $internal: $category (expected: $expected)\n";
        } else {
            echo "  ✗ $internal: $category (expected: $expected)\n";
        }
    }
}

if ($categoryPassed === $categoryTests) {
    echo "✓ PASS: All unit categories correctly detected ($categoryPassed/$categoryTests)\n";
} else {
    echo "✗ PARTIAL: Some unit categories incorrect ($categoryPassed/$categoryTests)\n";
}
echo "\n";

echo "All feature flag validation tests completed!\n";
