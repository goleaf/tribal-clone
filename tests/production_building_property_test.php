<?php
/**
 * Property-Based Test for Production Building Effects
 * Feature: resource-system
 * Task: 8.1 Write property test for production building effects
 * 
 * Property 14: Production Building Effects
 * Validates: Requirements 8.1
 * 
 * For any production building (Timber Camp, Clay Pit, Iron Mine) at level L > 0,
 * the corresponding resource production rate SHALL be greater than the rate at level L-1.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
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

// Initialize managers
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);

echo "=== Production Building Effects Property Test ===\n\n";

/**
 * Property 14: Production Building Effects
 * Feature: resource-system, Property 14: Production Building Effects
 * Validates: Requirements 8.1
 * 
 * For any production building (Timber Camp, Clay Pit, Iron Mine) at level L > 0,
 * the corresponding resource production rate SHALL be greater than the rate at level L-1.
 * 
 * This property ensures that upgrading production buildings always increases output,
 * which is a fundamental game mechanic expectation.
 */
PropertyTest::forAll(
    function() {
        // Generate random production building and level
        $productionBuildings = [
            'sawmill',    // Timber Camp (produces wood)
            'clay_pit',   // Clay Pit (produces clay)
            'iron_mine'   // Iron Mine (produces iron)
        ];
        
        $building = $productionBuildings[array_rand($productionBuildings)];
        
        // Random level from 1 to 30 (we need L > 0 to compare with L-1)
        $level = rand(1, 30);
        
        return [$building, $level];
    },
    function($buildingInternalName, $level) use ($buildingManager) {
        // Get production rate at current level
        $currentProduction = $buildingManager->getHourlyProduction($buildingInternalName, $level);
        
        // Get production rate at previous level
        $previousProduction = $buildingManager->getHourlyProduction($buildingInternalName, $level - 1);
        
        // Verify both values are numeric
        if (!is_numeric($currentProduction)) {
            return "Current production is not numeric for $buildingInternalName at level $level";
        }
        
        if (!is_numeric($previousProduction)) {
            return "Previous production is not numeric for $buildingInternalName at level " . ($level - 1);
        }
        
        // Verify both values are non-negative
        if ($currentProduction < 0) {
            return "Current production is negative: $currentProduction for $buildingInternalName at level $level";
        }
        
        if ($previousProduction < 0) {
            return "Previous production is negative: $previousProduction for $buildingInternalName at level " . ($level - 1);
        }
        
        // Core property: production at level L MUST be greater than production at level L-1
        if ($currentProduction <= $previousProduction) {
            return "Production did not increase: $buildingInternalName level " . ($level - 1) . 
                   " produces $previousProduction/hr, level $level produces $currentProduction/hr " .
                   "(expected $currentProduction > $previousProduction)";
        }
        
        // Additional sanity checks for growth rate (skip if previous level was 0)
        if ($previousProduction > 0) {
            // For exponential growth with factor ~1.163, we expect at least 10% increase
            $minExpectedIncrease = $previousProduction * 1.10;
            if ($currentProduction < $minExpectedIncrease) {
                return "Production increase too small: $buildingInternalName from level " . ($level - 1) . 
                       " to $level increased from $previousProduction to $currentProduction " .
                       "(expected at least $minExpectedIncrease for ~10% growth)";
            }
            
            // Verify the increase is not unreasonably large (sanity check)
            // With growth factor 1.163, max increase should be ~16.3%
            $maxExpectedIncrease = $previousProduction * 1.20; // Allow 20% for rounding/world speed
            if ($currentProduction > $maxExpectedIncrease) {
                return "Production increase too large: $buildingInternalName from level " . ($level - 1) . 
                       " to $level increased from $previousProduction to $currentProduction " .
                       "(expected at most $maxExpectedIncrease for ~16.3% growth with margin)";
            }
        } else {
            // Special case: level 0 to level 1 transition
            // Level 1 should produce the base amount (30 for wood/clay, 25 for iron)
            $expectedBase = ($buildingInternalName === 'iron_mine') ? 25 : 30;
            // Allow for world speed multipliers (typically 1-10x)
            $minBase = $expectedBase * 0.5; // Allow 0.5x for slow worlds
            $maxBase = $expectedBase * 20;  // Allow 20x for fast worlds
            
            if ($currentProduction < $minBase || $currentProduction > $maxBase) {
                return "Level 1 production out of expected range: $buildingInternalName produces $currentProduction/hr " .
                       "(expected between $minBase and $maxBase for base $expectedBase with world speed)";
            }
        }
        
        return true;
    },
    "Property 14: Production Building Effects"
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
