<?php
/**
 * Test for getEffectiveUnitStats() method
 * 
 * Validates: Requirements 11.1, 11.2, 11.3, 11.4
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Testing getEffectiveUnitStats() method...\n\n";

$unitManager = new UnitManager($conn);
$worldManager = new WorldManager($conn);

// Get a test unit (pikeneer)
$allUnits = $unitManager->getAllUnitTypes();
$testUnit = null;
$testUnitId = null;

foreach ($allUnits as $id => $unit) {
    if (($unit['internal_name'] ?? '') === 'spearguard') {
        $testUnit = $unit;
        $testUnitId = $id;
        break;
    }
}

if (!$testUnit) {
    echo "ERROR: Could not find spearguard unit for testing.\n";
    exit(1);
}

echo "Test Unit: {$testUnit['name']} (ID: $testUnitId)\n";
echo "Base Stats:\n";
echo "  - Training Time: {$testUnit['training_time_base']}s\n";
echo "  - Cost: {$testUnit['cost_wood']} wood, {$testUnit['cost_clay']} clay, {$testUnit['cost_iron']} iron\n";
echo "\n";

// Test with default world (ID 1)
$worldId = 1;
echo "Testing with World ID: $worldId\n";

$effectiveStats = $unitManager->getEffectiveUnitStats($testUnitId, $worldId);

if (empty($effectiveStats)) {
    echo "ERROR: getEffectiveUnitStats returned empty array.\n";
    exit(1);
}

echo "Effective Stats:\n";
echo "  - Unit Type ID: {$effectiveStats['unit_type_id']}\n";
echo "  - Name: {$effectiveStats['name']}\n";
echo "  - Internal Name: {$effectiveStats['internal_name']}\n";
echo "  - Category: {$effectiveStats['category']}\n";
echo "  - Attack: {$effectiveStats['attack']}\n";
echo "  - Defense (Infantry): {$effectiveStats['defense_infantry']}\n";
echo "  - Defense (Cavalry): {$effectiveStats['defense_cavalry']}\n";
echo "  - Defense (Ranged): {$effectiveStats['defense_ranged']}\n";
echo "  - Speed: {$effectiveStats['speed_min_per_field']} min/field\n";
echo "  - Carry: {$effectiveStats['carry_capacity']}\n";
echo "  - Population: {$effectiveStats['population']}\n";
echo "\n";

echo "Training Time:\n";
echo "  - Base: {$effectiveStats['training_time_base']}s\n";
echo "  - Effective: {$effectiveStats['training_time_effective']}s\n";
echo "  - World Speed Multiplier: {$effectiveStats['world_speed_multiplier']}\n";
echo "  - Archetype Train Multiplier: {$effectiveStats['archetype_train_multiplier']}\n";
echo "\n";

echo "Costs:\n";
echo "  - Base Wood: {$effectiveStats['cost_wood_base']}\n";
echo "  - Base Clay: {$effectiveStats['cost_clay_base']}\n";
echo "  - Base Iron: {$effectiveStats['cost_iron_base']}\n";
echo "  - Effective Wood: {$effectiveStats['cost_wood']}\n";
echo "  - Effective Clay: {$effectiveStats['cost_clay']}\n";
echo "  - Effective Iron: {$effectiveStats['cost_iron']}\n";
echo "  - Archetype Cost Multiplier: {$effectiveStats['archetype_cost_multiplier']}\n";
echo "\n";

// Verify multipliers are applied correctly
$expectedEffectiveTime = (int)floor(
    $testUnit['training_time_base'] / 
    ($effectiveStats['world_speed_multiplier'] * $effectiveStats['archetype_train_multiplier'])
);

$expectedEffectiveCostWood = (int)floor(
    $effectiveStats['cost_wood_base'] * $effectiveStats['archetype_cost_multiplier']
);

echo "Validation:\n";

if ($effectiveStats['training_time_effective'] === $expectedEffectiveTime) {
    echo "  ✓ Training time multiplier applied correctly\n";
} else {
    echo "  ✗ Training time multiplier FAILED\n";
    echo "    Expected: $expectedEffectiveTime, Got: {$effectiveStats['training_time_effective']}\n";
}

if ($effectiveStats['cost_wood'] === $expectedEffectiveCostWood) {
    echo "  ✓ Cost multiplier applied correctly\n";
} else {
    echo "  ✗ Cost multiplier FAILED\n";
    echo "    Expected: $expectedEffectiveCostWood, Got: {$effectiveStats['cost_wood']}\n";
}

// Test with modified world settings
echo "\n";
echo "Testing with modified world multipliers...\n";

// Update world settings to test multipliers
$conn->query("UPDATE worlds SET 
    train_multiplier_inf = 2.0,
    cost_multiplier_inf = 1.5
    WHERE id = 1");

// Clear cache by creating new manager
$unitManager2 = new UnitManager($conn);
$effectiveStats2 = $unitManager2->getEffectiveUnitStats($testUnitId, $worldId);

echo "Modified Multipliers:\n";
echo "  - Train Multiplier: {$effectiveStats2['archetype_train_multiplier']}\n";
echo "  - Cost Multiplier: {$effectiveStats2['archetype_cost_multiplier']}\n";
echo "  - Effective Training Time: {$effectiveStats2['training_time_effective']}s\n";
echo "  - Effective Wood Cost: {$effectiveStats2['cost_wood']}\n";
echo "\n";

// Verify the multipliers changed
if ($effectiveStats2['archetype_train_multiplier'] == 2.0) {
    echo "  ✓ Train multiplier updated correctly\n";
} else {
    echo "  ✗ Train multiplier update FAILED\n";
}

if ($effectiveStats2['archetype_cost_multiplier'] == 1.5) {
    echo "  ✓ Cost multiplier updated correctly\n";
} else {
    echo "  ✗ Cost multiplier update FAILED\n";
}

// Restore original settings
$conn->query("UPDATE worlds SET 
    train_multiplier_inf = 1.0,
    cost_multiplier_inf = 1.0
    WHERE id = 1");

echo "\n";
echo "All tests completed successfully!\n";
