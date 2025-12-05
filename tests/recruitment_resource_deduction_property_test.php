<?php
/**
 * Property-Based Test for Recruitment Resource Deduction
 * Feature: resource-system, Property 7: Recruitment Resource Deduction
 * Validates: Requirements 4.5
 * 
 * For any valid unit recruitment, the village resources SHALL decrease by exactly 
 * (unit_cost × quantity), AND population used SHALL increase by (unit_pop × quantity), 
 * AND the unit queue SHALL contain the recruitment entry.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
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
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
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
                    if (isset($firstFailure['trace'])) {
                        echo "  Trace: {$firstFailure['trace']}\n";
                    }
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
$unitManager = new UnitManager($conn);
$villageManager = new VillageManager($conn);

echo "=== Recruitment Resource Deduction Property Test ===\n\n";

/**
 * Property 7: Recruitment Resource Deduction
 * Feature: resource-system, Property 7: Recruitment Resource Deduction
 * Validates: Requirements 4.5
 * 
 * For any valid unit recruitment, the village resources SHALL decrease by exactly 
 * (unit_cost × quantity), AND population used SHALL increase by (unit_pop × quantity), 
 * AND the unit queue SHALL contain the recruitment entry.
 */
PropertyTest::forAll(
    function() use ($conn, $unitManager) {
        // Get or create a test user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = 'test_recruit_user' LIMIT 1");
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$userRow) {
            $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES ('test_recruit_user', 'test', 'test_recruit@example.com')");
            $stmt->execute();
            $userId = $stmt->insert_id;
            $stmt->close();
        } else {
            $userId = (int)$userRow['id'];
        }
        
        // Get or create a test village with sufficient resources
        $stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $villageRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$villageRow) {
            $stmt = $conn->prepare("INSERT INTO villages (name, user_id, world_id, x_coord, y_coord, wood, clay, iron, population, farm_capacity) VALUES (?, ?, 1, 0, 0, 50000, 50000, 50000, 0, 1000)");
            $name = 'Test Recruit Village ' . rand(1000, 9999);
            $stmt->bind_param("si", $name, $userId);
            $stmt->execute();
            $villageId = $stmt->insert_id;
            $stmt->close();
        } else {
            $villageId = (int)$villageRow['id'];
            // Ensure village has sufficient resources
            $stmt = $conn->prepare("UPDATE villages SET wood = 50000, clay = 50000, iron = 50000, population = 0, farm_capacity = 1000 WHERE id = ?");
            $stmt->bind_param("i", $villageId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Get all available unit types (non-conquest, non-seasonal, non-elite)
        $allUnits = $unitManager->getAllUnitTypes();
        $availableUnits = [];
        
        foreach ($allUnits as $unit) {
            $internal = strtolower($unit['internal_name'] ?? '');
            // Skip conquest units, seasonal units, and elite units
            if (in_array($internal, ['noble', 'nobleman', 'standard_bearer', 'envoy', 'tempest_knight', 'event_knight', 'warden', 'ranger'], true)) {
                continue;
            }
            // Skip units with very high costs or population
            if ($unit['cost_wood'] > 10000 || $unit['cost_clay'] > 10000 || $unit['cost_iron'] > 10000 || $unit['population'] > 50) {
                continue;
            }
            $availableUnits[] = $unit;
        }
        
        if (empty($availableUnits)) {
            throw new Exception("No available units for testing");
        }
        
        // Pick a random unit
        $unit = $availableUnits[array_rand($availableUnits)];
        $unitTypeId = (int)$unit['id'];
        $buildingType = $unit['building_type'];
        
        // Ensure the required building exists at sufficient level
        $requiredLevel = max(1, (int)($unit['required_building_level'] ?? 1));
        $buildingLevel = rand($requiredLevel, min($requiredLevel + 10, 20));
        
        // Get building type ID
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ? LIMIT 1");
        $stmt->bind_param("s", $buildingType);
        $stmt->execute();
        $buildingTypeRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$buildingTypeRow) {
            throw new Exception("Building type $buildingType not found");
        }
        $buildingTypeId = (int)$buildingTypeRow['id'];
        
        // Delete existing building
        $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $villageId, $buildingTypeId);
        $stmt->execute();
        $stmt->close();
        
        // Insert building at required level
        $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $villageId, $buildingTypeId, $buildingLevel);
        $stmt->execute();
        $stmt->close();
        
        // Random quantity (1-10 units)
        $quantity = rand(1, 10);
        
        // Clear any existing recruitment queues for this village
        $stmt = $conn->prepare("DELETE FROM unit_queue WHERE village_id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $stmt->close();
        
        return [$villageId, $unitTypeId, $quantity, $buildingLevel, $unit];
    },
    function($villageId, $unitTypeId, $quantity, $buildingLevel, $unit) use ($conn, $unitManager, $villageManager) {
        // Get initial village state
        $initialVillage = $villageManager->getVillageInfo($villageId);
        if (!$initialVillage) {
            return "Failed to get initial village info";
        }
        
        $initialWood = (int)$initialVillage['wood'];
        $initialClay = (int)$initialVillage['clay'];
        $initialIron = (int)$initialVillage['iron'];
        $initialPopulation = (int)$initialVillage['population'];
        
        // Calculate expected costs
        $expectedWoodCost = (int)$unit['cost_wood'] * $quantity;
        $expectedClayCost = (int)$unit['cost_clay'] * $quantity;
        $expectedIronCost = (int)$unit['cost_iron'] * $quantity;
        $expectedPopulationCost = (int)$unit['population'] * $quantity;
        
        // Verify we have enough resources
        if ($initialWood < $expectedWoodCost || $initialClay < $expectedClayCost || $initialIron < $expectedIronCost) {
            return "Insufficient resources for test (this should not happen with test setup)";
        }
        
        // Verify we have enough farm capacity
        if (($initialPopulation + $expectedPopulationCost) > (int)$initialVillage['farm_capacity']) {
            return "Insufficient farm capacity for test (this should not happen with test setup)";
        }
        
        // Attempt recruitment
        $result = $unitManager->recruitUnits($villageId, $unitTypeId, $quantity, $buildingLevel);
        
        if (!$result['success']) {
            return "Recruitment failed: " . ($result['error'] ?? 'Unknown error') . " (code: " . ($result['code'] ?? 'N/A') . ")";
        }
        
        // Get final village state
        $finalVillage = $villageManager->getVillageInfo($villageId);
        if (!$finalVillage) {
            return "Failed to get final village info";
        }
        
        $finalWood = (int)$finalVillage['wood'];
        $finalClay = (int)$finalVillage['clay'];
        $finalIron = (int)$finalVillage['iron'];
        $finalPopulation = (int)$finalVillage['population'];
        
        // Verify resource deduction
        $actualWoodDeducted = $initialWood - $finalWood;
        $actualClayDeducted = $initialClay - $finalClay;
        $actualIronDeducted = $initialIron - $finalIron;
        
        if ($actualWoodDeducted != $expectedWoodCost) {
            return "Wood deduction mismatch: expected $expectedWoodCost, got $actualWoodDeducted (initial: $initialWood, final: $finalWood)";
        }
        
        if ($actualClayDeducted != $expectedClayCost) {
            return "Clay deduction mismatch: expected $expectedClayCost, got $actualClayDeducted (initial: $initialClay, final: $finalClay)";
        }
        
        if ($actualIronDeducted != $expectedIronCost) {
            return "Iron deduction mismatch: expected $expectedIronCost, got $actualIronDeducted (initial: $initialIron, final: $finalIron)";
        }
        
        // Verify population increase
        $actualPopulationIncrease = $finalPopulation - $initialPopulation;
        if ($actualPopulationIncrease != $expectedPopulationCost) {
            return "Population increase mismatch: expected $expectedPopulationCost, got $actualPopulationIncrease (initial: $initialPopulation, final: $finalPopulation)";
        }
        
        // Verify queue entry exists
        $stmt = $conn->prepare("SELECT id, unit_type_id, count, building_type FROM unit_queue WHERE village_id = ? AND unit_type_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("ii", $villageId, $unitTypeId);
        $stmt->execute();
        $queueEntry = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$queueEntry) {
            return "Queue entry not found after recruitment";
        }
        
        if ((int)$queueEntry['unit_type_id'] != $unitTypeId) {
            return "Queue entry has wrong unit type: expected $unitTypeId, got {$queueEntry['unit_type_id']}";
        }
        
        if ((int)$queueEntry['count'] != $quantity) {
            return "Queue entry has wrong count: expected $quantity, got {$queueEntry['count']}";
        }
        
        if ($queueEntry['building_type'] != $unit['building_type']) {
            return "Queue entry has wrong building type: expected {$unit['building_type']}, got {$queueEntry['building_type']}";
        }
        
        return true;
    },
    "Property 7: Recruitment Resource Deduction"
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
