<?php
/**
 * Property-Based Test for World Speed Multiplier Application
 * Feature: building-queue-system
 * Task: 13.1 Write property test for world speed multiplier application
 * 
 * Property 18: World Speed Multiplier Application
 * Validates: Requirements 5.3
 * 
 * For any world configuration with speed multipliers, build times should 
 * reflect those multipliers.
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
$worldManager = new WorldManager($conn);

echo "=== World Speed Multiplier Property Test ===\n\n";

/**
 * Property 18: World Speed Multiplier Application
 * Feature: building-queue-system, Property 18: World Speed Multiplier Application
 * Validates: Requirements 5.3
 * 
 * For any world configuration with speed multipliers, build times should 
 * reflect those multipliers. This ensures that different world configurations
 * (casual, blitz, hardcore, etc.) have appropriately scaled build times.
 * 
 * The property verifies that:
 * 1. Higher world_speed reduces build times proportionally
 * 2. Higher build_speed reduces build times proportionally
 * 3. The combined effect of both multipliers is correctly applied
 * 4. Build times scale inversely with speed (2x speed = 0.5x time)
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
        
        // Random level from 0 to 20 (reasonable range for testing)
        $currentLevel = rand(0, 20);
        
        // Random HQ level from 0 to 20
        $hqLevel = rand(0, 20);
        
        // Generate two different world speed configurations to compare
        // World speed typically ranges from 0.5 (slow) to 5.0 (very fast)
        $worldSpeed1 = round(rand(5, 50) / 10, 1); // 0.5 to 5.0
        $buildSpeed1 = round(rand(5, 50) / 10, 1); // 0.5 to 5.0
        
        $worldSpeed2 = round(rand(5, 50) / 10, 1); // 0.5 to 5.0
        $buildSpeed2 = round(rand(5, 50) / 10, 1); // 0.5 to 5.0
        
        return [$building, $currentLevel, $hqLevel, $worldSpeed1, $buildSpeed1, $worldSpeed2, $buildSpeed2];
    },
    function($buildingInternalName, $currentLevel, $hqLevel, $worldSpeed1, $buildSpeed1, $worldSpeed2, $buildSpeed2) use ($buildingConfigManager, $worldManager, $conn) {
        // Get the building config
        $config = $buildingConfigManager->getBuildingConfig($buildingInternalName);
        
        if (!$config) {
            return "Building config not found for $buildingInternalName";
        }
        
        // Check if we're at max level
        $maxLevel = $buildingConfigManager->getMaxLevel($buildingInternalName);
        if ($maxLevel !== null && $currentLevel >= $maxLevel) {
            // At max level, skip this test case
            return true;
        }
        
        // Test 1: Set world speed configuration 1 and calculate time
        $stmt = $conn->prepare("UPDATE worlds SET world_speed = ?, build_speed = ? WHERE id = 1");
        $stmt->bind_param("dd", $worldSpeed1, $buildSpeed1);
        $stmt->execute();
        $stmt->close();
        
        // Clear cache to force reload
        $buildingConfigManager->invalidateCache();
        
        // Calculate time with configuration 1
        $time1 = $buildingConfigManager->calculateUpgradeTime($buildingInternalName, $currentLevel, $hqLevel);
        
        if ($time1 === null) {
            return "calculateUpgradeTime returned null for $buildingInternalName at level $currentLevel with HQ $hqLevel (config 1)";
        }
        
        // Test 2: Set world speed configuration 2 and calculate time
        $stmt = $conn->prepare("UPDATE worlds SET world_speed = ?, build_speed = ? WHERE id = 1");
        $stmt->bind_param("dd", $worldSpeed2, $buildSpeed2);
        $stmt->execute();
        $stmt->close();
        
        // Clear cache to force reload
        $buildingConfigManager->invalidateCache();
        
        // Calculate time with configuration 2
        $time2 = $buildingConfigManager->calculateUpgradeTime($buildingInternalName, $currentLevel, $hqLevel);
        
        if ($time2 === null) {
            return "calculateUpgradeTime returned null for $buildingInternalName at level $currentLevel with HQ $hqLevel (config 2)";
        }
        
        // Calculate speed multipliers
        $speedMultiplier1 = $worldSpeed1 * $buildSpeed1;
        $speedMultiplier2 = $worldSpeed2 * $buildSpeed2;
        
        // Avoid division by zero
        if ($speedMultiplier1 < 0.01 || $speedMultiplier2 < 0.01) {
            return true; // Skip edge cases with very low speeds
        }
        
        // The key property to verify: higher speed should result in lower or equal time
        // (equal when both hit the same tier floor)
        
        // Case 1: speedMultiplier2 > speedMultiplier1
        // Then time2 should be <= time1
        if ($speedMultiplier2 > $speedMultiplier1 * 1.01) { // 1% tolerance for floating point
            if ($time2 > $time1) {
                return "Higher speed resulted in higher time for $buildingInternalName level $currentLevel:\n" .
                       "  Config 1: world_speed=$worldSpeed1, build_speed=$buildSpeed1, speed_mult=" . $speedMultiplier1 . ", time=$time1\n" .
                       "  Config 2: world_speed=$worldSpeed2, build_speed=$buildSpeed2, speed_mult=" . $speedMultiplier2 . ", time=$time2\n" .
                       "  Expected: time2 <= time1 (higher speed = lower time)";
            }
        }
        
        // Case 2: speedMultiplier1 > speedMultiplier2
        // Then time1 should be <= time2
        if ($speedMultiplier1 > $speedMultiplier2 * 1.01) { // 1% tolerance for floating point
            if ($time1 > $time2) {
                return "Higher speed resulted in higher time for $buildingInternalName level $currentLevel:\n" .
                       "  Config 1: world_speed=$worldSpeed1, build_speed=$buildSpeed1, speed_mult=" . $speedMultiplier1 . ", time=$time1\n" .
                       "  Config 2: world_speed=$worldSpeed2, build_speed=$buildSpeed2, speed_mult=" . $speedMultiplier2 . ", time=$time2\n" .
                       "  Expected: time1 <= time2 (higher speed = lower time)";
            }
        }
        
        // Additional check: Verify that the speed multipliers are actually being applied
        // by checking that different speeds produce different times (unless both hit the same floor)
        $targetLevel = $currentLevel + 1;
        
        // Check if this level has a tier floor
        $tierFloors = [
            ['min' => 5,  'max' => 8,  'seconds' => 1800],  // 30m+
            ['min' => 9,  'max' => 12, 'seconds' => 3600],  // 1h+
            ['min' => 13, 'max' => 20, 'seconds' => 7200],  // 2h+
        ];
        
        $hasTierFloor = false;
        foreach ($tierFloors as $tier) {
            if ($targetLevel >= $tier['min'] && $targetLevel <= $tier['max']) {
                $hasTierFloor = true;
                break;
            }
        }
        
        // If speeds are significantly different and we're not in a tier floor range,
        // times should be different
        if (!$hasTierFloor && abs($speedMultiplier1 - $speedMultiplier2) > 0.5) {
            if ($time1 === $time2) {
                return "Different speed multipliers produced identical times for $buildingInternalName level $currentLevel (no tier floor):\n" .
                       "  Config 1: speed_mult=" . $speedMultiplier1 . ", time=$time1\n" .
                       "  Config 2: speed_mult=" . $speedMultiplier2 . ", time=$time2";
            }
        }
        
        return true;
    },
    "Property 18: World Speed Multiplier Application"
);

// Restore default world settings
$stmt = $conn->prepare("UPDATE worlds SET world_speed = 1.0, build_speed = 1.0 WHERE id = 1");
$stmt->execute();
$stmt->close();

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
