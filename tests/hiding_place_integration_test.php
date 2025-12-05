<?php
/**
 * Integration test for Hiding Place resource protection
 * Validates that Hiding Place protection works end-to-end
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/PlunderCalculator.php';
require_once __DIR__ . '/../lib/managers/ViewRenderer.php';
require_once __DIR__ . '/../lib/managers/ResourceManager.php';

echo "=== Hiding Place Integration Test ===\n\n";

// Initialize managers
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$resourceManager = new ResourceManager($conn, $buildingManager);

// Load unit data
$unitDataPath = __DIR__ . '/../data/units.json';
if (!file_exists($unitDataPath)) {
    die("Error: Unit data file not found\n");
}
$unitData = json_decode(file_get_contents($unitDataPath), true);
$plunderCalculator = new PlunderCalculator($unitData);

$viewRenderer = new ViewRenderer($conn, $buildingManager, $resourceManager);

// Test 1: Hiding Place capacity calculation
echo "Test 1: Hiding Place capacity calculation\n";
$testLevels = [0, 1, 5, 10];
foreach ($testLevels as $level) {
    $capacity = $buildingConfigManager->calculateHidingPlaceCapacity($level);
    $expected = $level > 0 ? (int)floor(150 * pow(1.233, $level)) : 0;
    if ($capacity === $expected) {
        echo "  ✓ Level $level: $capacity (expected $expected)\n";
    } else {
        echo "  ✗ Level $level: $capacity (expected $expected)\n";
        exit(1);
    }
}
echo "\n";

// Test 2: Plunder calculation with Hiding Place protection
echo "Test 2: Plunder calculation with Hiding Place protection\n";
$hidingPlaceLevel = 5;
$hidingPlaceCapacity = $buildingConfigManager->calculateHidingPlaceCapacity($hidingPlaceLevel);
echo "  Hiding Place Level: $hidingPlaceLevel\n";
echo "  Protection per resource: $hidingPlaceCapacity\n";

$resources = ['wood' => 5000, 'clay' => 3000, 'iron' => 4000];
$vaultPercent = 10.0;

$lootResult = $plunderCalculator->calculateAvailableLoot(
    $resources,
    $hidingPlaceCapacity,
    $vaultPercent,
    null,
    1.0
);

echo "  Resources: Wood={$resources['wood']}, Clay={$resources['clay']}, Iron={$resources['iron']}\n";
echo "  Vault: {$vaultPercent}%\n";
echo "  Protected: Wood={$lootResult['protected']['wood']}, Clay={$lootResult['protected']['clay']}, Iron={$lootResult['protected']['iron']}\n";
echo "  Lootable: Wood={$lootResult['lootable']['wood']}, Clay={$lootResult['lootable']['clay']}, Iron={$lootResult['lootable']['iron']}\n";

// Verify protection is working
foreach (['wood', 'clay', 'iron'] as $resource) {
    $vaultProtection = (int)ceil($resources[$resource] * ($vaultPercent / 100.0));
    $expectedProtection = max($hidingPlaceCapacity, $vaultProtection);
    if ($lootResult['protected'][$resource] === $expectedProtection) {
        echo "  ✓ $resource protection correct: {$lootResult['protected'][$resource]}\n";
    } else {
        echo "  ✗ $resource protection incorrect: {$lootResult['protected'][$resource]} (expected $expectedProtection)\n";
        exit(1);
    }
}
echo "\n";

// Test 3: ViewRenderer displays Hiding Place protection
echo "Test 3: ViewRenderer displays Hiding Place protection\n";
$resourceBar = $viewRenderer->renderResourceBar(
    ['wood' => 5000, 'clay' => 3000, 'iron' => 4000],
    ['wood' => 50.0, 'clay' => 30.0, 'iron' => 40.0],
    10000,
    $hidingPlaceCapacity
);

if (strpos($resourceBar, 'Protected:') !== false && strpos($resourceBar, (string)$hidingPlaceCapacity) !== false) {
    echo "  ✓ Resource bar displays Hiding Place protection\n";
} else {
    echo "  ✗ Resource bar does not display Hiding Place protection\n";
    echo "  Output: $resourceBar\n";
    exit(1);
}
echo "\n";

// Test 4: Zero-level Hiding Place
echo "Test 4: Zero-level Hiding Place (no protection)\n";
$zeroCapacity = $buildingConfigManager->calculateHidingPlaceCapacity(0);
if ($zeroCapacity === 0) {
    echo "  ✓ Level 0 Hiding Place has 0 capacity\n";
} else {
    echo "  ✗ Level 0 Hiding Place should have 0 capacity, got $zeroCapacity\n";
    exit(1);
}

$lootResultZero = $plunderCalculator->calculateAvailableLoot(
    $resources,
    $zeroCapacity,
    $vaultPercent,
    null,
    1.0
);

// With no Hiding Place, only vault protection applies
foreach (['wood', 'clay', 'iron'] as $resource) {
    $vaultProtection = (int)ceil($resources[$resource] * ($vaultPercent / 100.0));
    if ($lootResultZero['protected'][$resource] === $vaultProtection) {
        echo "  ✓ $resource uses only vault protection: {$lootResultZero['protected'][$resource]}\n";
    } else {
        echo "  ✗ $resource protection incorrect: {$lootResultZero['protected'][$resource]} (expected $vaultProtection)\n";
        exit(1);
    }
}
echo "\n";

echo "✓ All integration tests passed!\n";
exit(0);
