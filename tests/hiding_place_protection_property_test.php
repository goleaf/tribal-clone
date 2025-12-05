<?php
/**
 * Property-Based Test for Hiding Place Protection
 * Feature: resource-system, Property 15: Hiding Place Protection
 * Validates: Requirements 9.1, 9.2
 * 
 * This test validates that the Hiding Place correctly protects resources from plunder.
 * Each property test runs minimum 100 iterations.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/PlunderCalculator.php';

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

// Load unit data for PlunderCalculator
$unitDataPath = __DIR__ . '/../data/units.json';
if (!file_exists($unitDataPath)) {
    die("Error: Unit data file not found at $unitDataPath\n");
}
$unitData = json_decode(file_get_contents($unitDataPath), true);
if (!$unitData) {
    die("Error: Failed to load unit data\n");
}

// Initialize managers
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$plunderCalculator = new PlunderCalculator($unitData);

echo "=== Hiding Place Protection Property-Based Tests ===\n\n";

/**
 * Property 15: Hiding Place Protection
 * Feature: resource-system, Property 15: Hiding Place Protection
 * Validates: Requirements 9.1, 9.2
 * 
 * For any plunder calculation where village has Hiding Place at level L, 
 * the protected amount SHALL equal the hiding capacity at level L, 
 * AND plundered resources SHALL NOT include any amount up to that capacity.
 */
PropertyTest::forAll(
    function() {
        // Generate random Hiding Place level (0-10)
        $hidingPlaceLevel = mt_rand(0, 10);
        
        // Generate random village resources (0-100000 per resource)
        $wood = mt_rand(0, 100000);
        $clay = mt_rand(0, 100000);
        $iron = mt_rand(0, 100000);
        
        // Generate random vault percentage (0-100)
        $vaultPercent = mt_rand(0, 100);
        
        return [$hidingPlaceLevel, $wood, $clay, $iron, $vaultPercent];
    },
    function($hidingPlaceLevel, $wood, $clay, $iron, $vaultPercent) use ($buildingConfigManager, $plunderCalculator) {
        // Calculate expected Hiding Place capacity
        $expectedCapacity = $buildingConfigManager->calculateHidingPlaceCapacity($hidingPlaceLevel);
        
        // Calculate lootable resources using PlunderCalculator
        $resources = [
            'wood' => $wood,
            'clay' => $clay,
            'iron' => $iron
        ];
        
        $lootResult = $plunderCalculator->calculateAvailableLoot(
            $resources,
            $expectedCapacity,  // hiddenPerResource
            $vaultPercent,      // vaultPercent
            null,               // no plunder cap
            1.0                 // no diminishing returns
        );
        
        // Property 1: Protected amount should equal Hiding Place capacity or vault protection (whichever is higher)
        $vaultProtection = [
            'wood' => (int)ceil($wood * ($vaultPercent / 100.0)),
            'clay' => (int)ceil($clay * ($vaultPercent / 100.0)),
            'iron' => (int)ceil($iron * ($vaultPercent / 100.0))
        ];
        
        foreach (['wood', 'clay', 'iron'] as $resource) {
            $expectedProtection = max($expectedCapacity, $vaultProtection[$resource]);
            $actualProtection = $lootResult['protected'][$resource];
            
            if ($actualProtection !== $expectedProtection) {
                return "Protected amount mismatch for $resource: expected $expectedProtection, got $actualProtection";
            }
        }
        
        // Property 2: Lootable resources should not include protected amounts
        foreach (['wood', 'clay', 'iron'] as $resource) {
            $totalResource = $resources[$resource];
            $protected = $lootResult['protected'][$resource];
            $lootable = $lootResult['lootable'][$resource];
            $available = $lootResult['available'][$resource];
            
            // Available should be total minus protected
            $expectedAvailable = max(0, $totalResource - $protected);
            if ($available !== $expectedAvailable) {
                return "Available amount mismatch for $resource: expected $expectedAvailable, got $available";
            }
            
            // Lootable should not exceed available
            if ($lootable > $available) {
                return "Lootable exceeds available for $resource: lootable=$lootable, available=$available";
            }
            
            // When protection exceeds total resources, all resources are protected
            // In this case, lootable should be 0
            if ($protected >= $totalResource && $lootable > 0) {
                return "When protection exceeds total, lootable should be 0 for $resource: lootable=$lootable, protected=$protected, total=$totalResource";
            }
            
            // When protection is less than total, lootable + protected should not exceed total
            if ($protected < $totalResource && $lootable + $protected > $totalResource) {
                return "Lootable + protected exceeds total for $resource: lootable=$lootable, protected=$protected, total=$totalResource";
            }
        }
        
        // Property 3: When Hiding Place level is 0, capacity should be 0
        if ($hidingPlaceLevel === 0 && $expectedCapacity !== 0) {
            return "Hiding Place capacity should be 0 when level is 0, got $expectedCapacity";
        }
        
        // Property 4: Hiding Place capacity should increase with level
        if ($hidingPlaceLevel > 0) {
            $lowerLevelCapacity = $buildingConfigManager->calculateHidingPlaceCapacity($hidingPlaceLevel - 1);
            if ($expectedCapacity <= $lowerLevelCapacity) {
                return "Hiding Place capacity should increase with level: level $hidingPlaceLevel capacity=$expectedCapacity, level " . ($hidingPlaceLevel - 1) . " capacity=$lowerLevelCapacity";
            }
        }
        
        return true;
    },
    "Property 15: Hiding Place Protection"
);

// Report results
$failedTests = PropertyTest::getFailedTests();
if (empty($failedTests)) {
    echo "✓ All property tests passed!\n";
    exit(0);
} else {
    echo "✗ " . count($failedTests) . " property test(s) failed:\n";
    foreach ($failedTests as $test) {
        echo "  - $test\n";
    }
    exit(1);
}
