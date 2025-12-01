<?php
/**
 * Comprehensive test for getEffectiveUnitStats() method
 * 
 * Validates: Requirements 11.1, 11.2, 11.3, 11.4
 * 
 * Tests:
 * - Load base stats from unit_types
 * - Apply world training time multipliers by archetype
 * - Apply world cost multipliers by archetype
 * - Return effective stats
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "=== Comprehensive Test for getEffectiveUnitStats() ===\n\n";

$unitManager = new UnitManager($conn);
$worldManager = new WorldManager($conn);

// Test different unit archetypes
$testUnits = [
    'infantry' => 'spearguard',
    'cavalry' => 'lancer',
    'ranged' => 'bowman',
    'siege' => 'battering_ram'
];

$worldId = 1;
$allPassed = true;

// Test 1: Verify base stats are loaded correctly
echo "Test 1: Load base stats from unit_types\n";
echo str_repeat("-", 50) . "\n";

foreach ($testUnits as $archetype => $internalName) {
    $allUnits = $unitManager->getAllUnitTypes();
    $unitId = null;
    
    foreach ($allUnits as $id => $unit) {
        if (($unit['internal_name'] ?? '') === $internalName) {
            $unitId = $id;
            break;
        }
    }
    
    if (!$unitId) {
        echo "  ✗ Could not find $archetype unit ($internalName)\n";
        continue;
    }
    
    $stats = $unitManager->getEffectiveUnitStats($unitId, $worldId);
    
    if (empty($stats)) {
        echo "  ✗ $archetype: getEffectiveUnitStats returned empty\n";
        $allPassed = false;
        continue;
    }
    
    // Verify all required fields are present
    $requiredFields = [
        'unit_type_id', 'name', 'internal_name', 'category',
        'attack', 'defense_infantry', 'defense_cavalry', 'defense_ranged',
        'speed_min_per_field', 'carry_capacity', 'population',
        'training_time_base', 'training_time_effective',
        'cost_wood_base', 'cost_clay_base', 'cost_iron_base',
        'cost_wood', 'cost_clay', 'cost_iron',
        'world_speed_multiplier', 'archetype_train_multiplier', 'archetype_cost_multiplier'
    ];
    
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $stats)) {
            $missingFields[] = $field;
        }
    }
    
    if (empty($missingFields)) {
        echo "  ✓ $archetype: All required fields present\n";
    } else {
        echo "  ✗ $archetype: Missing fields: " . implode(', ', $missingFields) . "\n";
        $allPassed = false;
    }
}

echo "\n";

// Test 2: Verify training time multipliers are applied correctly
echo "Test 2: Apply world training time multipliers by archetype\n";
echo str_repeat("-", 50) . "\n";

// Set different multipliers for each archetype
$conn->query("UPDATE worlds SET 
    train_multiplier_inf = 1.5,
    train_multiplier_cav = 2.0,
    train_multiplier_rng = 1.2,
    train_multiplier_siege = 0.8
    WHERE id = 1");

// Create new manager to clear cache
$unitManager2 = new UnitManager($conn);

foreach ($testUnits as $archetype => $internalName) {
    $allUnits = $unitManager2->getAllUnitTypes();
    $unitId = null;
    
    foreach ($allUnits as $id => $unit) {
        if (($unit['internal_name'] ?? '') === $internalName) {
            $unitId = $id;
            break;
        }
    }
    
    if (!$unitId) {
        continue;
    }
    
    $stats = $unitManager2->getEffectiveUnitStats($unitId, $worldId);
    
    // Verify the multiplier matches the archetype
    $expectedMultipliers = [
        'infantry' => 1.5,
        'cavalry' => 2.0,
        'ranged' => 1.2,
        'siege' => 0.8
    ];
    
    $expected = $expectedMultipliers[$archetype];
    $actual = $stats['archetype_train_multiplier'];
    
    if (abs($actual - $expected) < 0.01) {
        echo "  ✓ $archetype: Train multiplier = $actual (expected $expected)\n";
    } else {
        echo "  ✗ $archetype: Train multiplier = $actual (expected $expected)\n";
        $allPassed = false;
    }
    
    // Verify effective time is calculated correctly
    $expectedTime = (int)floor($stats['training_time_base'] / ($stats['world_speed_multiplier'] * $actual));
    if ($stats['training_time_effective'] === $expectedTime) {
        echo "  ✓ $archetype: Effective time calculated correctly\n";
    } else {
        echo "  ✗ $archetype: Effective time = {$stats['training_time_effective']} (expected $expectedTime)\n";
        $allPassed = false;
    }
}

echo "\n";

// Test 3: Verify cost multipliers are applied correctly
echo "Test 3: Apply world cost multipliers by archetype\n";
echo str_repeat("-", 50) . "\n";

// Set different cost multipliers for each archetype
$conn->query("UPDATE worlds SET 
    cost_multiplier_inf = 1.3,
    cost_multiplier_cav = 1.8,
    cost_multiplier_rng = 1.1,
    cost_multiplier_siege = 2.5
    WHERE id = 1");

// Create new manager to clear cache
$unitManager3 = new UnitManager($conn);

foreach ($testUnits as $archetype => $internalName) {
    $allUnits = $unitManager3->getAllUnitTypes();
    $unitId = null;
    
    foreach ($allUnits as $id => $unit) {
        if (($unit['internal_name'] ?? '') === $internalName) {
            $unitId = $id;
            break;
        }
    }
    
    if (!$unitId) {
        continue;
    }
    
    $stats = $unitManager3->getEffectiveUnitStats($unitId, $worldId);
    
    // Verify the cost multiplier matches the archetype
    $expectedMultipliers = [
        'infantry' => 1.3,
        'cavalry' => 1.8,
        'ranged' => 1.1,
        'siege' => 2.5
    ];
    
    $expected = $expectedMultipliers[$archetype];
    $actual = $stats['archetype_cost_multiplier'];
    
    if (abs($actual - $expected) < 0.01) {
        echo "  ✓ $archetype: Cost multiplier = $actual (expected $expected)\n";
    } else {
        echo "  ✗ $archetype: Cost multiplier = $actual (expected $expected)\n";
        $allPassed = false;
    }
    
    // Verify effective costs are calculated correctly
    $expectedWood = (int)floor($stats['cost_wood_base'] * $actual);
    if ($stats['cost_wood'] === $expectedWood) {
        echo "  ✓ $archetype: Effective wood cost calculated correctly\n";
    } else {
        echo "  ✗ $archetype: Effective wood cost = {$stats['cost_wood']} (expected $expectedWood)\n";
        $allPassed = false;
    }
}

echo "\n";

// Test 4: Verify return structure
echo "Test 4: Return effective stats with all required information\n";
echo str_repeat("-", 50) . "\n";

$allUnits = $unitManager3->getAllUnitTypes();
$testUnitId = array_key_first($allUnits);
$stats = $unitManager3->getEffectiveUnitStats($testUnitId, $worldId);

// Verify base costs are included
if (isset($stats['cost_wood_base']) && isset($stats['cost_clay_base']) && isset($stats['cost_iron_base'])) {
    echo "  ✓ Base costs included in return value\n";
} else {
    echo "  ✗ Base costs missing from return value\n";
    $allPassed = false;
}

// Verify effective costs are included
if (isset($stats['cost_wood']) && isset($stats['cost_clay']) && isset($stats['cost_iron'])) {
    echo "  ✓ Effective costs included in return value\n";
} else {
    echo "  ✗ Effective costs missing from return value\n";
    $allPassed = false;
}

// Verify multipliers are included
if (isset($stats['world_speed_multiplier']) && isset($stats['archetype_train_multiplier']) && isset($stats['archetype_cost_multiplier'])) {
    echo "  ✓ All multipliers included in return value\n";
} else {
    echo "  ✗ Some multipliers missing from return value\n";
    $allPassed = false;
}

echo "\n";

// Restore original settings
$conn->query("UPDATE worlds SET 
    train_multiplier_inf = 1.0,
    train_multiplier_cav = 1.0,
    train_multiplier_rng = 1.0,
    train_multiplier_siege = 1.0,
    cost_multiplier_inf = 1.0,
    cost_multiplier_cav = 1.0,
    cost_multiplier_rng = 1.0,
    cost_multiplier_siege = 1.0
    WHERE id = 1");

echo "=== Test Summary ===\n";
if ($allPassed) {
    echo "✓ All tests PASSED\n";
    echo "\nRequirements validated:\n";
    echo "  - 11.1: Training time multipliers applied\n";
    echo "  - 11.2: Cost multipliers applied\n";
    echo "  - 11.3: Effective training time calculated\n";
    echo "  - 11.4: Effective costs calculated\n";
    exit(0);
} else {
    echo "✗ Some tests FAILED\n";
    exit(1);
}
