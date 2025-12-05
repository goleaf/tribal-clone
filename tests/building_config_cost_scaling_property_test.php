<?php
/**
 * Property-Based Test for Cost Scaling
 * Feature: building-queue-system
 * Task: 2.2 Write property test for cost calculation
 * 
 * Property: Cost Scaling
 * Validates: Requirements 1.1
 * 
 * For any building at any level, the calculated upgrade cost should follow
 * exponential scaling: cost = base_cost × (cost_factor ^ level).
 * This ensures consistent resource requirements across all buildings.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';

// Simple property-based testing helper
class PropertyTest {
    private static $iterations = 100;
    private static $failedTests = [];
    
    public static function forAll(callable $generator, callable $property, string $testName): bool {
        echo "Running property test: $testName\n";
        $passed = 0;
        $failed = 0;
        $firstFailure = null;
        
        for ($i = 0; $i < self::$iterations; $i++) {
            $inputs = $generator();
            try {
                $result = $property(...$inputs);
                if ($result === true) {
                    $passed++;
                } else {
                    $failed++;
                    if ($firstFailure === null) {
                        $firstFailure = [
                            'iteration' => $i,
                            'inputs' => $inputs,
                            'result' => $result
                        ];
                    }
                }
            } catch (Exception $e) {
                $failed++;
                if ($firstFailure === null) {
                    $firstFailure = [
                        'iteration' => $i,
                        'inputs' => $inputs,
                        'exception' => $e->getMessage()
                    ];
                }
            }
        }
        
        $success = $failed === 0;
        if ($success) {
            echo "✓ PASS: $testName ($passed/" . self::$iterations . " iterations)\n\n";
        } else {
            echo "✗ FAIL: $testName ($passed passed, $failed failed out of " . self::$iterations . " iterations)\n";
            if ($firstFailure) {
                echo "First failure at iteration {$firstFailure['iteration']}:\n";
                echo "  Inputs: " . json_encode($firstFailure['inputs']) . "\n";
                if (isset($firstFailure['exception'])) {
                    echo "  Exception: {$firstFailure['exception']}\n";
                } else {
                    echo "  Result: " . json_encode($firstFailure['result']) . "\n";
                }
            }
            echo "\n";
            self::$failedTests[] = $testName;
        }
        
        return $success;
    }
    
    public static function getFailedTests(): array {
        return self::$failedTests;
    }
}

// Initialize manager
$buildingConfigManager = new BuildingConfigManager($conn);

echo "=== Cost Scaling Property Test ===\n\n";

/**
 * Property: Cost Scaling
 * Feature: building-queue-system, Property: Cost Scaling
 * Validates: Requirements 1.1
 * 
 * For any building at any level, the calculated upgrade cost should follow
 * exponential scaling: cost = base_cost × (cost_factor ^ level).
 * 
 * This property ensures that:
 * 1. Costs scale exponentially as expected
 * 2. All resource types (wood, clay, iron) follow the same scaling
 * 3. Costs are always positive integers
 * 4. Costs increase monotonically with level
 */
PropertyTest::forAll(
    function() {
        // Generate random building and level
        $buildings = [
            'main_building',
            'barracks',
            'stable',
            'smithy',
            'market',
            'farm',
            'warehouse',
            'wall',
            'sawmill',
            'clay_pit',
            'iron_mine'
        ];
        
        $building = $buildings[array_rand($buildings)];
        
        // Random level from 0 to 29 (we're calculating cost to upgrade TO level+1)
        $currentLevel = rand(0, 29);
        
        return [$building, $currentLevel];
    },
    function($buildingInternalName, $currentLevel) use ($buildingConfigManager) {
        // Get the building config
        $config = $buildingConfigManager->getBuildingConfig($buildingInternalName);
        
        if (!$config) {
            return "Building config not found for $buildingInternalName";
        }
        
        // Check if we're at max level
        $maxLevel = $buildingConfigManager->getMaxLevel($buildingInternalName);
        if ($maxLevel !== null && $currentLevel >= $maxLevel) {
            // At max level, calculateUpgradeCost should return null
            $calculatedCost = $buildingConfigManager->calculateUpgradeCost($buildingInternalName, $currentLevel);
            if ($calculatedCost !== null) {
                return "Expected null for building at max level ($buildingInternalName level $currentLevel, max $maxLevel), got cost array";
            }
            return true; // This is correct behavior
        }
        
        // Get calculated cost from the manager
        $calculatedCost = $buildingConfigManager->calculateUpgradeCost($buildingInternalName, $currentLevel);
        
        if ($calculatedCost === null) {
            return "calculateUpgradeCost returned null for $buildingInternalName at level $currentLevel";
        }
        
        // Verify the cost array has all required keys
        if (!isset($calculatedCost['wood']) || !isset($calculatedCost['clay']) || !isset($calculatedCost['iron'])) {
            return "Cost array missing required keys for $buildingInternalName at level $currentLevel: " . json_encode($calculatedCost);
        }
        
        // Manually calculate expected costs using the formula
        // Formula: cost = base_cost × (cost_factor ^ level)
        $costFactor = (float)$config['cost_factor'];
        
        // Clamp cost factor to guardrails (1.01 to 1.6)
        if ($costFactor <= 0) {
            $costFactor = 1.0;
        }
        $costFactor = max(1.01, min(1.6, $costFactor));
        
        $expectedWood = round($config['cost_wood_initial'] * ($costFactor ** $currentLevel));
        $expectedClay = round($config['cost_clay_initial'] * ($costFactor ** $currentLevel));
        $expectedIron = round($config['cost_iron_initial'] * ($costFactor ** $currentLevel));
        
        // Verify calculated costs match expected
        if ($calculatedCost['wood'] != $expectedWood) {
            return "Wood cost mismatch for $buildingInternalName level $currentLevel: " .
                   "calculated={$calculatedCost['wood']}, expected=$expectedWood " .
                   "(base={$config['cost_wood_initial']}, factor=$costFactor)";
        }
        
        if ($calculatedCost['clay'] != $expectedClay) {
            return "Clay cost mismatch for $buildingInternalName level $currentLevel: " .
                   "calculated={$calculatedCost['clay']}, expected=$expectedClay " .
                   "(base={$config['cost_clay_initial']}, factor=$costFactor)";
        }
        
        if ($calculatedCost['iron'] != $expectedIron) {
            return "Iron cost mismatch for $buildingInternalName level $currentLevel: " .
                   "calculated={$calculatedCost['iron']}, expected=$expectedIron " .
                   "(base={$config['cost_iron_initial']}, factor=$costFactor)";
        }
        
        // Verify all costs are positive integers
        foreach (['wood', 'clay', 'iron'] as $resource) {
            if (!is_numeric($calculatedCost[$resource]) || $calculatedCost[$resource] < 0) {
                return "Cost for $resource is not a positive number: {$calculatedCost[$resource]} " .
                       "for $buildingInternalName at level $currentLevel";
            }
        }
        
        // Verify monotonic increase: cost at level L should be greater than cost at level L-1
        if ($currentLevel > 0) {
            $previousCost = $buildingConfigManager->calculateUpgradeCost($buildingInternalName, $currentLevel - 1);
            
            if ($previousCost !== null) {
                foreach (['wood', 'clay', 'iron'] as $resource) {
                    if ($calculatedCost[$resource] <= $previousCost[$resource]) {
                        return "Cost did not increase monotonically for $resource: " .
                               "$buildingInternalName level " . ($currentLevel - 1) . " costs {$previousCost[$resource]}, " .
                               "level $currentLevel costs {$calculatedCost[$resource]} " .
                               "(expected {$calculatedCost[$resource]} > {$previousCost[$resource]})";
                    }
                }
            }
        }
        
        // Verify exponential growth rate is reasonable
        // With cost_factor between 1.01 and 1.6, costs should grow but not explode
        if ($currentLevel > 0) {
            $previousCost = $buildingConfigManager->calculateUpgradeCost($buildingInternalName, $currentLevel - 1);
            
            if ($previousCost !== null && $previousCost['wood'] > 0) {
                $growthRatio = $calculatedCost['wood'] / $previousCost['wood'];
                
                // Growth ratio should be approximately equal to cost_factor
                // Allow some tolerance for rounding
                $expectedGrowthRatio = $costFactor;
                $tolerance = 0.05; // 5% tolerance
                
                if (abs($growthRatio - $expectedGrowthRatio) > $tolerance) {
                    return "Growth ratio outside expected range for $buildingInternalName level $currentLevel: " .
                           "ratio=$growthRatio, expected=$expectedGrowthRatio (tolerance=$tolerance)";
                }
            }
        }
        
        return true;
    },
    "Property: Cost Scaling"
);

// Summary
echo "=== Test Summary ===\n";
$failedTests = PropertyTest::getFailedTests();
if (empty($failedTests)) {
    echo "All property tests passed!\n";
    exit(0);
} else {
    echo "Failed tests:\n";
    foreach ($failedTests as $test) {
        echo "  - $test\n";
    }
    exit(1);
}
