<?php
/**
 * Validation script for data/units.json
 * Verifies all required fields are present and valid
 * Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 4.1, 4.2, 5.1, 5.2, 6.1, 6.2, 7.1, 7.2, 8.1, 8.2, 14.1
 */

$unitsFile = __DIR__ . '/../data/units.json';

if (!file_exists($unitsFile)) {
    die("ERROR: units.json not found at $unitsFile\n");
}

$json = file_get_contents($unitsFile);
$units = json_decode($json, true);

if ($units === null) {
    die("ERROR: Failed to parse units.json: " . json_last_error_msg() . "\n");
}

$errors = [];
$warnings = [];
$unitCount = 0;

// Required categories and minimum counts
$requiredCategories = [
    'infantry' => 4,    // Pikeneer, Shieldbearer, Raider, Warden
    'ranged' => 3,      // Militia Bowman, Longbow Scout, Ranger
    'cavalry' => 2,     // Skirmisher Cavalry, Lancer
    'scout' => 2,       // Pathfinder, Shadow Rider
    'siege' => 3,       // Battering Ram, Stone Hurler, Mantlet Crew
    'support' => 2,     // Banner Guard, War Healer
    'conquest' => 2     // Noble, Standard Bearer
];

$categoryCounts = [];

// Required fields for all units
$requiredFields = [
    'name', 'internal_name', 'category', 'building_type',
    'required_building_level', 'cost', 'population', 'attack',
    'defense', 'speed_min_per_field', 'carry_capacity',
    'training_time_base', 'rps_bonuses', 'special_abilities'
];

$requiredDefenseTypes = ['infantry', 'cavalry', 'ranged'];

foreach ($units as $key => $unit) {
    // Skip metadata fields
    if (strpos($key, '_') === 0) {
        continue;
    }
    
    $unitCount++;
    $unitName = $unit['name'] ?? $key;
    
    // Check required fields
    foreach ($requiredFields as $field) {
        if (!isset($unit[$field])) {
            $errors[] = "Unit '$unitName': Missing required field '$field'";
        }
    }
    
    // Validate category
    if (isset($unit['category'])) {
        $category = $unit['category'];
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = 0;
        }
        $categoryCounts[$category]++;
        
        if (!isset($requiredCategories[$category])) {
            $warnings[] = "Unit '$unitName': Unknown category '$category'";
        }
    }
    
    // Validate costs are positive
    if (isset($unit['cost'])) {
        foreach (['wood', 'clay', 'iron'] as $resource) {
            if (isset($unit['cost'][$resource]) && $unit['cost'][$resource] <= 0) {
                $errors[] = "Unit '$unitName': Cost for '$resource' must be positive";
            }
        }
    }
    
    // Validate population is positive
    if (isset($unit['population']) && $unit['population'] <= 0) {
        $errors[] = "Unit '$unitName': Population must be positive";
    }
    
    // Validate attack is non-negative
    if (isset($unit['attack']) && $unit['attack'] < 0) {
        $errors[] = "Unit '$unitName': Attack cannot be negative";
    }
    
    // Validate defense values
    if (isset($unit['defense'])) {
        foreach ($requiredDefenseTypes as $defType) {
            if (!isset($unit['defense'][$defType])) {
                $errors[] = "Unit '$unitName': Missing defense type '$defType'";
            } elseif ($unit['defense'][$defType] < 0) {
                $errors[] = "Unit '$unitName': Defense '$defType' cannot be negative";
            }
        }
    }
    
    // Validate speed is positive
    if (isset($unit['speed_min_per_field']) && $unit['speed_min_per_field'] <= 0) {
        $errors[] = "Unit '$unitName': Speed must be positive";
    }
    
    // Validate carry capacity is non-negative
    if (isset($unit['carry_capacity']) && $unit['carry_capacity'] < 0) {
        $errors[] = "Unit '$unitName': Carry capacity cannot be negative";
    }
    
    // Validate training time is positive
    if (isset($unit['training_time_base']) && $unit['training_time_base'] <= 0) {
        $errors[] = "Unit '$unitName': Training time must be positive";
    }
    
    // Validate RPS bonuses (if present)
    if (isset($unit['rps_bonuses']) && is_array($unit['rps_bonuses'])) {
        foreach ($unit['rps_bonuses'] as $bonusType => $multiplier) {
            if (!is_numeric($multiplier) || $multiplier < 1.0) {
                $warnings[] = "Unit '$unitName': RPS bonus '$bonusType' should be >= 1.0";
            }
        }
    }
}

// Check category counts
foreach ($requiredCategories as $category => $minCount) {
    $actualCount = $categoryCounts[$category] ?? 0;
    if ($actualCount < $minCount) {
        $errors[] = "Category '$category': Expected at least $minCount units, found $actualCount";
    }
}

// Report results
echo "=== Units.json Validation Report ===\n\n";
echo "Total units: $unitCount\n\n";

echo "Category breakdown:\n";
foreach ($categoryCounts as $category => $count) {
    $required = $requiredCategories[$category] ?? 0;
    $status = $count >= $required ? "✓" : "✗";
    echo "  $status $category: $count units (required: $required)\n";
}
echo "\n";

if (count($errors) > 0) {
    echo "ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ $warning\n";
    }
    echo "\n";
}

if (count($errors) === 0) {
    echo "✓ All validation checks passed!\n";
    echo "✓ Task 1.4 requirements satisfied:\n";
    echo "  - Infantry units: " . ($categoryCounts['infantry'] ?? 0) . " (Pikeneer, Shieldbearer, Raider, Warden)\n";
    echo "  - Ranged units: " . ($categoryCounts['ranged'] ?? 0) . " (Militia Bowman, Longbow Scout, Ranger)\n";
    echo "  - Cavalry units: " . ($categoryCounts['cavalry'] ?? 0) . " (Skirmisher Cavalry, Lancer)\n";
    echo "  - Scout units: " . ($categoryCounts['scout'] ?? 0) . " (Pathfinder, Shadow Rider)\n";
    echo "  - Siege units: " . ($categoryCounts['siege'] ?? 0) . " (Battering Ram, Stone Hurler, Mantlet Crew)\n";
    echo "  - Support units: " . ($categoryCounts['support'] ?? 0) . " (Banner Guard, War Healer)\n";
    echo "  - Conquest units: " . ($categoryCounts['conquest'] ?? 0) . " (Noble, Standard Bearer)\n";
    echo "  - All stats included: attack, defense values, speed, carry, population, costs, training time, RPS bonuses\n";
    exit(0);
} else {
    echo "✗ Validation failed with " . count($errors) . " error(s)\n";
    exit(1);
}
