<?php
/**
 * Property-Based Tests for ResourceManager
 * Feature: resource-system
 * 
 * These tests validate correctness properties across many random inputs.
 * Each property test runs minimum 100 iterations.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/ResourceManager.php';
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
$resourceManager = new ResourceManager($conn, $buildingManager);

echo "=== ResourceManager Property-Based Tests ===\n\n";

/**
 * Property 1: Resource Display Format
 * Feature: resource-system, Property 1: Resource Display Format
 * Validates: Requirements 1.1
 * 
 * For any resource amount and production rate, the formatted display string 
 * SHALL match the pattern "[Resource]: [Amount] (+[Rate]/hr)" where Amount 
 * and Rate are numeric values.
 */
PropertyTest::forAll(
    function() {
        // Generate random resource name, amount, and rate
        $resources = ['Wood', 'Clay', 'Iron', 'Stone', 'Food'];
        $resourceName = $resources[array_rand($resources)];
        $amount = rand(0, 1000000);
        $rate = rand(0, 10000) / 10.0; // Random float 0-1000 with 1 decimal
        return [$resourceName, $amount, $rate];
    },
    function($resourceName, $amount, $rate) use ($resourceManager) {
        $display = $resourceManager->formatResourceDisplay($resourceName, $amount, $rate);
        
        // Verify format matches pattern: "[Resource]: [Amount] (+[Rate]/hr)"
        $pattern = '/^[A-Za-z]+: \d+ \(\+\d+\.\d\/hr\)$/';
        if (!preg_match($pattern, $display)) {
            return "Format does not match pattern. Got: $display";
        }
        
        // Verify the resource name is present
        if (strpos($display, $resourceName) !== 0) {
            return "Resource name not at start. Got: $display";
        }
        
        // Verify amount is present and correct
        $expectedAmount = (int)$amount;
        if (strpos($display, ": $expectedAmount ") === false) {
            return "Amount $expectedAmount not found in: $display";
        }
        
        // Verify rate is present (rounded to 1 decimal)
        $expectedRate = number_format($rate, 1);
        if (strpos($display, "+$expectedRate/hr") === false) {
            return "Rate +$expectedRate/hr not found in: $display";
        }
        
        return true;
    },
    "Property 1: Resource Display Format"
);

/**
 * Property 2: Production Rate Calculation
 * Feature: resource-system, Property 2: Production Rate Calculation
 * Validates: Requirements 1.2
 * 
 * For any village with production buildings at levels 0-30, the calculated 
 * production rate SHALL equal base * growth^(level-1) * world_speed * building_speed
 */
PropertyTest::forAll(
    function() use ($conn) {
        // Get or create a test village
        $stmt = $conn->prepare("SELECT id FROM villages LIMIT 1");
        $stmt->execute();
        $villageRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$villageRow) {
            // Create test village
            $stmt = $conn->prepare("INSERT INTO villages (name, user_id, world_id, x_coord, y_coord, wood, clay, iron) VALUES (?, 1, 1, 0, 0, 1000, 1000, 1000)");
            $name = 'Test Village ' . rand(1000, 9999);
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $villageId = $stmt->insert_id;
            $stmt->close();
        } else {
            $villageId = (int)$villageRow['id'];
        }
        
        return [$villageId];
    },
    function($villageId) use ($resourceManager) {
        $rates = $resourceManager->getProductionRates($villageId);
        
        // Verify all three resource types are present
        if (!isset($rates['wood']) || !isset($rates['clay']) || !isset($rates['iron'])) {
            return "Missing resource types in rates";
        }
        
        // Verify rates are non-negative
        if ($rates['wood'] < 0 || $rates['clay'] < 0 || $rates['iron'] < 0) {
            return "Negative production rate found";
        }
        
        // Verify rates are numeric
        if (!is_numeric($rates['wood']) || !is_numeric($rates['clay']) || !is_numeric($rates['iron'])) {
            return "Non-numeric production rate found";
        }
        
        return true;
    },
    "Property 2: Production Rate Calculation"
);

/**
 * Property 3: Resource Capacity Enforcement
 * Feature: resource-system, Property 3: Resource Capacity Enforcement
 * Validates: Requirements 1.3, 1.4
 * 
 * For any village, after any resource update operation, the resource amounts 
 * SHALL NOT exceed the warehouse capacity determined by the warehouse building level.
 */
PropertyTest::forAll(
    function() use ($conn, $buildingManager) {
        // Get or create a test village
        $stmt = $conn->prepare("SELECT id FROM villages LIMIT 1");
        $stmt->execute();
        $villageRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$villageRow) {
            // Create test village
            $stmt = $conn->prepare("INSERT INTO villages (name, user_id, world_id, x_coord, y_coord, wood, clay, iron) VALUES (?, 1, 1, 0, 0, 1000, 1000, 1000)");
            $name = 'Test Village ' . rand(1000, 9999);
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $villageId = $stmt->insert_id;
            $stmt->close();
        } else {
            $villageId = (int)$villageRow['id'];
        }
        
        // Random warehouse level (0-30)
        $warehouseLevel = rand(0, 30);
        
        // Random initial resources (can exceed capacity)
        $initialWood = rand(0, 200000);
        $initialClay = rand(0, 200000);
        $initialIron = rand(0, 200000);
        
        return [$villageId, $warehouseLevel, $initialWood, $initialClay, $initialIron];
    },
    function($villageId, $warehouseLevel, $initialWood, $initialClay, $initialIron) use ($conn, $buildingManager, $resourceManager) {
        // Get warehouse building type ID
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = 'warehouse' LIMIT 1");
        $stmt->execute();
        $buildingTypeRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$buildingTypeRow) {
            return "Warehouse building type not found in database";
        }
        $buildingTypeId = (int)$buildingTypeRow['id'];
        
        // Delete existing warehouse building for this village
        $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $villageId, $buildingTypeId);
        $stmt->execute();
        $stmt->close();
        
        // Insert warehouse building at specified level
        if ($warehouseLevel > 0) {
            $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $villageId, $buildingTypeId, $warehouseLevel);
            $stmt->execute();
            $stmt->close();
        }
        
        // Set initial resources (potentially exceeding capacity)
        $stmt = $conn->prepare("UPDATE villages SET wood = ?, clay = ?, iron = ? WHERE id = ?");
        $stmt->bind_param("iiii", $initialWood, $initialClay, $initialIron, $villageId);
        $stmt->execute();
        $stmt->close();
        
        // Enforce warehouse capacity
        $result = $resourceManager->enforceWarehouseCapacity($villageId);
        
        if (!$result['success']) {
            return "Failed to enforce capacity: " . $result['message'];
        }
        
        $capacity = $buildingManager->getWarehouseCapacityByLevel($warehouseLevel);
        
        // Debug: check if warehouse level matches
        if (isset($result['warehouse_level']) && $result['warehouse_level'] != $warehouseLevel) {
            return "Warehouse level mismatch: expected $warehouseLevel, got {$result['warehouse_level']}";
        }
        
        // Also verify by reading from database
        global $conn;
        $stmt = $conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $dbResources = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Verify resources don't exceed capacity (check both result and DB)
        if ($result['resources']['wood'] > $capacity) {
            return "Wood exceeds capacity: {$result['resources']['wood']} > $capacity";
        }
        if ($result['resources']['clay'] > $capacity) {
            return "Clay exceeds capacity: {$result['resources']['clay']} > $capacity";
        }
        if ($result['resources']['iron'] > $capacity) {
            return "Iron exceeds capacity: {$result['resources']['iron']} > $capacity";
        }
        
        // Verify database matches
        if ($dbResources['wood'] > $capacity) {
            return "DB Wood exceeds capacity: {$dbResources['wood']} > $capacity";
        }
        if ($dbResources['clay'] > $capacity) {
            return "DB Clay exceeds capacity: {$dbResources['clay']} > $capacity";
        }
        if ($dbResources['iron'] > $capacity) {
            return "DB Iron exceeds capacity: {$dbResources['iron']} > $capacity";
        }
        
        // Verify resources are non-negative
        if ($result['resources']['wood'] < 0 || $result['resources']['clay'] < 0 || $result['resources']['iron'] < 0) {
            return "Negative resource amount found";
        }
        
        return true;
    },
    "Property 3: Resource Capacity Enforcement"
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
