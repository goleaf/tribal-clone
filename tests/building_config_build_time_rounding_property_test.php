<?php
/**
 * Property-Based Test for Build Time Integer Rounding
 * Feature: building-queue-system
 * Task: 14.1 Write property test for build time integer rounding
 * 
 * Property 19: Build Time Integer Rounding
 * Validates: Requirements 5.4
 * 
 * For any calculated build time, the result should be an integer (no fractional seconds).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';

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

echo "=== Build Time Integer Rounding Property Test ===\n\n";

/**
 * Property 19: Build Time Integer Rounding
 * Feature: building-queue-system, Property 19: Build Time Integer Rounding
 * Validates: Requirements 5.4
 * 
 * For any calculated build time, the result should be an integer (no fractional seconds).
 * 
 * This property ensures that all build times are whole seconds, which is critical for:
 * 1. Database storage (integer columns)
 * 2. UI display (no fractional seconds shown to players)
 * 3. Cron processing (finish_time comparisons work correctly)
 * 4. Game balance (consistent timing without floating point errors)
 */
PropertyTest::forAll(
    function() {
        // Generate random building, level, and HQ level
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
        
        // Random level from 0 to 29 (we're calculating time to upgrade TO level+1)
        $currentLevel = rand(0, 29);
        
        // Random HQ level from 0 to 30
        $hqLevel = rand(0, 30);
        
        return [$building, $currentLevel, $hqLevel];
    },
    function($buildingInternalName, $currentLevel, $hqLevel) use ($buildingConfigManager) {
        // Get calculated time from the manager
        $calculatedTime = $buildingConfigManager->calculateUpgradeTime($buildingInternalName, $currentLevel, $hqLevel);
        
        // If at max level, calculateUpgradeTime returns null - this is valid
        if ($calculatedTime === null) {
            $maxLevel = $buildingConfigManager->getMaxLevel($buildingInternalName);
            if ($maxLevel !== null && $currentLevel >= $maxLevel) {
                return true; // Correctly returns null at max level
            }
            return "calculateUpgradeTime returned null for $buildingInternalName at level $currentLevel with HQ $hqLevel (not at max level)";
        }
        
        // Property: The result MUST be an integer (no fractional seconds)
        if (!is_int($calculatedTime)) {
            return "Build time is not an integer: " . var_export($calculatedTime, true) . 
                   " (type: " . gettype($calculatedTime) . ") for $buildingInternalName level $currentLevel with HQ $hqLevel";
        }
        
        // Additional validation: The result should be positive
        if ($calculatedTime <= 0) {
            return "Build time is not positive: $calculatedTime for $buildingInternalName level $currentLevel with HQ $hqLevel";
        }
        
        // Additional validation: The result should be reasonable (not astronomically large)
        // Max reasonable build time: ~30 days = 2,592,000 seconds
        if ($calculatedTime > 2592000) {
            return "Build time is unreasonably large: $calculatedTime seconds (" . 
                   round($calculatedTime / 86400, 1) . " days) for $buildingInternalName level $currentLevel with HQ $hqLevel";
        }
        
        return true;
    },
    "Property 19: Build Time Integer Rounding"
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
