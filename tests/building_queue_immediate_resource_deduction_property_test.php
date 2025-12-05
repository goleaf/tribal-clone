<?php
/**
 * Property-Based Test for Immediate Resource Deduction
 * Feature: building-queue-system, Property 1: Immediate Resource Deduction
 * Validates: Requirements 1.1
 * 
 * Property: For any village and valid building upgrade, when a build is queued,
 * the village's resources should decrease by exactly the upgrade cost amount.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingQueueManager.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';

// Simple property-based testing helper
class PropertyTest {
    private static $iterations = 100;
    private static $failedTests = [];
    
    public static function forAll(callable $generator, callable $property, string $testName): bool {
        echo "Running property test: $testName\n";
        
        $passed = 0;
        $failed = 0;
        
        for ($i = 0; $i < self::$iterations; $i++) {
            $input = $generator();
            
            try {
                $result = $property($input);
                
                if ($result === true) {
                    $passed++;
                } else {
                    $failed++;
                    self::$failedTests[] = [
                        'test' => $testName,
                        'iteration' => $i + 1,
                        'input' => $input,
                        'reason' => is_string($result) ? $result : 'Property violated'
                    ];
                    
                    // Show first failure
                    if ($failed === 1) {
                        echo "  ✗ Failed on iteration " . ($i + 1) . "\n";
                        echo "    Input: " . json_encode($input) . "\n";
                        echo "    Reason: " . (is_string($result) ? $result : 'Property violated') . "\n";
                    }
                }
            } catch (Exception $e) {
                $failed++;
                self::$failedTests[] = [
                    'test' => $testName,
                    'iteration' => $i + 1,
                    'input' => $input,
                    'exception' => $e->getMessage()
                ];
                
                if ($failed === 1) {
                    echo "  ✗ Exception on iteration " . ($i + 1) . ": " . $e->getMessage() . "\n";
                }
            }
        }
        
        $passRate = ($passed / self::$iterations) * 100;
        echo "  Result: {$passed}/{" . self::$iterations . "} passed ({$passRate}%)\n\n";
        
        return $failed === 0;
    }
    
    public static function getFailedTests(): array {
        return self::$failedTests;
    }
}

// Database setup
$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    die("No database connection available.\n");
}

$buildingConfigManager = new BuildingConfigManager($conn);
$buildingQueueManager = new BuildingQueueManager($conn, $buildingConfigManager);

echo "=== Immediate Resource Deduction Property Test ===\n\n";

/**
 * Property 1: Immediate Resource Deduction
 * 
 * For any village and valid building upgrade, when a build is queued,
 * the village's resources should decrease by exactly the upgrade cost amount.
 */
PropertyTest::forAll(
    // Generator: Create random test scenario
    function() use ($conn, $buildingConfigManager) {
        // Get available buildings
        $buildings = ['barracks', 'stable', 'smithy', 'farm', 'warehouse', 'storage'];
        $building = $buildings[array_rand($buildings)];
        
        // Random level (0-5 to keep costs reasonable)
        $currentLevel = rand(0, 5);
        
        // Calculate costs for this upgrade
        $costs = $buildingConfigManager->calculateUpgradeCost($building, $currentLevel);
        
        if (!$costs) {
            // Skip if we can't calculate costs
            return null;
        }
        
        // Generate resources with enough to cover costs plus some buffer
        $woodBuffer = rand(100, 1000);
        $clayBuffer = rand(100, 1000);
        $ironBuffer = rand(100, 1000);
        
        return [
            'building' => $building,
            'current_level' => $currentLevel,
            'costs' => $costs,
            'initial_wood' => $costs['wood'] + $woodBuffer,
            'initial_clay' => $costs['clay'] + $clayBuffer,
            'initial_iron' => $costs['iron'] + $ironBuffer,
            'wood_buffer' => $woodBuffer,
            'clay_buffer' => $clayBuffer,
            'iron_buffer' => $ironBuffer
        ];
    },
    // Property: Resources decrease by exact cost
    function($input) use ($conn, $buildingQueueManager, $buildingConfigManager) {
        if ($input === null) {
            return true; // Skip invalid inputs
        }
        
        // Get or create test user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = 'test_queue_user' LIMIT 1");
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$userRow) {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_protected) VALUES (?, ?, ?, 0)");
            $username = 'test_queue_user';
            $email = 'test_queue@example.com';
            $password = password_hash('test', PASSWORD_DEFAULT);
            $stmt->bind_param("sss", $username, $email, $password);
            $stmt->execute();
            $userId = $conn->insert_id;
            $stmt->close();
        } else {
            $userId = (int)$userRow['id'];
        }
        
        // Get or create test village
        $stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $villageRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$villageRow) {
            $stmt = $conn->prepare("INSERT INTO villages (user_id, name, x_coord, y_coord, wood, clay, iron, world_id) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $villageName = 'Test Queue Village';
            $x = rand(1, 100);
            $y = rand(1, 100);
            $stmt->bind_param("isiiii", $userId, $villageName, $x, $y, 
                $input['initial_wood'], $input['initial_clay'], $input['initial_iron']);
            $stmt->execute();
            $villageId = $conn->insert_id;
            $stmt->close();
        } else {
            $villageId = (int)$villageRow['id'];
            // Update resources for this test
            $stmt = $conn->prepare("UPDATE villages SET wood = ?, clay = ?, iron = ? WHERE id = ?");
            $stmt->bind_param("iiii", $input['initial_wood'], $input['initial_clay'], $input['initial_iron'], $villageId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Set main_building to level 10 to avoid prerequisite issues (must be done first)
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = 'main_building'");
        $stmt->execute();
        $mainBuildingResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($mainBuildingResult) {
            $mainBuildingTypeId = $mainBuildingResult['id'];
            
            // Delete existing main_building
            $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
            $stmt->bind_param("ii", $villageId, $mainBuildingTypeId);
            $stmt->execute();
            $stmt->close();
            
            // Insert main_building at level 10
            $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 10)");
            $stmt->bind_param("ii", $villageId, $mainBuildingTypeId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Set building to current level
        $buildingTypeId = null;
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
        $stmt->bind_param("s", $input['building']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result) {
            $buildingTypeId = $result['id'];
            
            // Delete existing building
            $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
            $stmt->bind_param("ii", $villageId, $buildingTypeId);
            $stmt->execute();
            $stmt->close();
            
            // Insert building at current level
            $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $villageId, $buildingTypeId, $input['current_level']);
            $stmt->execute();
            $stmt->close();
        }
        
        // Enqueue the build
        $result = $buildingQueueManager->enqueueBuild($villageId, $input['building'], $userId);
        
        // Get resources after enqueue
        $stmt = $conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $afterResources = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Cleanup - just delete the queue item, keep user and village for reuse
        $conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");
        
        // Verify property
        if (!$result['success']) {
            return "Enqueue failed: " . ($result['message'] ?? 'Unknown error');
        }
        
        $expectedWood = $input['initial_wood'] - $input['costs']['wood'];
        $expectedClay = $input['initial_clay'] - $input['costs']['clay'];
        $expectedIron = $input['initial_iron'] - $input['costs']['iron'];
        
        if ($afterResources['wood'] != $expectedWood) {
            return "Wood mismatch: expected {$expectedWood}, got {$afterResources['wood']}";
        }
        
        if ($afterResources['clay'] != $expectedClay) {
            return "Clay mismatch: expected {$expectedClay}, got {$afterResources['clay']}";
        }
        
        if ($afterResources['iron'] != $expectedIron) {
            return "Iron mismatch: expected {$expectedIron}, got {$afterResources['iron']}";
        }
        
        return true;
    },
    "Property 1: Immediate Resource Deduction"
);

// Summary
echo "=== Test Summary ===\n";
$failedTests = PropertyTest::getFailedTests();

if (empty($failedTests)) {
    echo "All property tests passed!\n";
    exit(0);
} else {
    echo "Failed tests:\n";
    foreach ($failedTests as $failure) {
        echo "  - {$failure['test']} (iteration {$failure['iteration']})\n";
        if (isset($failure['exception'])) {
            echo "    Exception: {$failure['exception']}\n";
        } elseif (isset($failure['reason'])) {
            echo "    Reason: {$failure['reason']}\n";
        }
    }
    exit(1);
}
