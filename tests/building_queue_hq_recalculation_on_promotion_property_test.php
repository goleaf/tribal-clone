<?php
/**
 * Property-Based Test for HQ Level Recalculation on Promotion
 * Feature: building-queue-system, Property 20: HQ Level Recalculation on Promotion
 * Validates: Requirements 5.5
 * 
 * Property: For any pending build promoted to active after HQ upgrade,
 * the finish time should use the new HQ level for calculation.
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

echo "=== HQ Level Recalculation on Promotion Property Test ===\n\n";

/**
 * Property 20: HQ Level Recalculation on Promotion
 * 
 * For any pending build promoted to active after HQ upgrade,
 * the finish time should use the new HQ level for calculation.
 */
PropertyTest::forAll(
    // Generator: Create random test scenario
    function() use ($conn, $buildingConfigManager) {
        // Get available buildings
        $buildings = ['barracks', 'stable', 'smithy', 'farm', 'warehouse', 'storage'];
        $building1 = $buildings[array_rand($buildings)];
        $building2 = $buildings[array_rand($buildings)];
        
        // Random initial HQ level (5-10 to ensure we have 2+ queue slots)
        // With base=1, milestone=5: HQ 5 gives 1 + floor((5-1)/5) = 1 + 0 = 1 slot
        // HQ 6 gives 1 + floor((6-1)/5) = 1 + 1 = 2 slots
        $initialHqLevel = rand(6, 10);
        
        // Random new HQ level (higher than initial)
        $newHqLevel = rand($initialHqLevel + 1, 15);
        
        // Random building levels (0-3)
        $currentLevel1 = rand(0, 3);
        $currentLevel2 = rand(0, 3);
        
        // Calculate costs for both upgrades
        $costs1 = $buildingConfigManager->calculateUpgradeCost($building1, $currentLevel1);
        $costs2 = $buildingConfigManager->calculateUpgradeCost($building2, $currentLevel2);
        
        if (!$costs1 || !$costs2) {
            return null;
        }
        
        // Calculate total costs
        $totalWood = $costs1['wood'] + $costs2['wood'];
        $totalClay = $costs1['clay'] + $costs2['clay'];
        $totalIron = $costs1['iron'] + $costs2['iron'];
        
        // Generate resources with enough to cover both builds
        $woodBuffer = rand(5000, 10000);
        $clayBuffer = rand(5000, 10000);
        $ironBuffer = rand(5000, 10000);
        
        return [
            'building1' => $building1,
            'building2' => $building2,
            'current_level1' => $currentLevel1,
            'current_level2' => $currentLevel2,
            'initial_hq_level' => $initialHqLevel,
            'new_hq_level' => $newHqLevel,
            'costs1' => $costs1,
            'costs2' => $costs2,
            'initial_wood' => $totalWood + $woodBuffer,
            'initial_clay' => $totalClay + $clayBuffer,
            'initial_iron' => $totalIron + $ironBuffer
        ];
    },
    // Property: Pending build uses new HQ level when promoted
    function($input) use ($conn, $buildingQueueManager, $buildingConfigManager) {
        if ($input === null) {
            return true; // Skip invalid inputs
        }
        
        // Get or create test user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = 'test_hq_promo_user' LIMIT 1");
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$userRow) {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_protected) VALUES (?, ?, ?, 0)");
            $username = 'test_hq_promo_user';
            $email = 'test_hq_promo@example.com';
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
            $villageName = 'Test HQ Promo Village';
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
        
        // Set main_building to initial HQ level
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = 'main_building'");
        $stmt->execute();
        $mainBuildingResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$mainBuildingResult) {
            return "main_building not found in building_types";
        }
        
        $mainBuildingTypeId = $mainBuildingResult['id'];
        
        // Delete existing main_building
        $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $villageId, $mainBuildingTypeId);
        $stmt->execute();
        $stmt->close();
        
        // Insert main_building at initial HQ level
        $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $villageId, $mainBuildingTypeId, $input['initial_hq_level']);
        $stmt->execute();
        $stmt->close();
        
        // Set building1 to current level
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
        $stmt->bind_param("s", $input['building1']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result) {
            $buildingTypeId1 = $result['id'];
            
            // Delete existing building
            $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
            $stmt->bind_param("ii", $villageId, $buildingTypeId1);
            $stmt->execute();
            $stmt->close();
            
            // Insert building at current level
            $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $villageId, $buildingTypeId1, $input['current_level1']);
            $stmt->execute();
            $stmt->close();
        }
        
        // Set building2 to current level
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
        $stmt->bind_param("s", $input['building2']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result) {
            $buildingTypeId2 = $result['id'];
            
            // Delete existing building
            $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
            $stmt->bind_param("ii", $villageId, $buildingTypeId2);
            $stmt->execute();
            $stmt->close();
            
            // Insert building at current level
            $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $villageId, $buildingTypeId2, $input['current_level2']);
            $stmt->execute();
            $stmt->close();
        }
        
        // Enqueue first build (will be active)
        $result1 = $buildingQueueManager->enqueueBuild($villageId, $input['building1'], $userId);
        
        if (!$result1['success']) {
            // Cleanup
            $conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");
            return "First enqueue failed: " . ($result1['message'] ?? 'Unknown error');
        }
        
        // Enqueue second build (will be pending)
        $result2 = $buildingQueueManager->enqueueBuild($villageId, $input['building2'], $userId);
        
        if (!$result2['success']) {
            // Cleanup
            $conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");
            return "Second enqueue failed: " . ($result2['message'] ?? 'Unknown error');
        }
        
        $queueItemId2 = $result2['queue_item_id'];
        
        // Verify second build is pending
        $stmt = $conn->prepare("SELECT status FROM building_queue WHERE id = ?");
        $stmt->bind_param("i", $queueItemId2);
        $stmt->execute();
        $queueItem = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($queueItem['status'] !== 'pending') {
            // Cleanup
            $conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");
            return "Second build is not pending (status: {$queueItem['status']})";
        }
        
        // Upgrade HQ level
        $stmt = $conn->prepare("UPDATE village_buildings SET level = ? WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("iii", $input['new_hq_level'], $villageId, $mainBuildingTypeId);
        $stmt->execute();
        $stmt->close();
        
        // Set first build's finish time to the past so it can be completed
        $pastTime = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago
        $stmt = $conn->prepare("UPDATE building_queue SET finish_time = ? WHERE id = ?");
        $stmt->bind_param("si", $pastTime, $result1['queue_item_id']);
        $stmt->execute();
        $stmt->close();
        
        // Complete first build to promote second build
        $completeResult = $buildingQueueManager->onBuildComplete($result1['queue_item_id']);
        
        if (!$completeResult['success']) {
            // Cleanup
            $conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");
            return "Build completion failed: " . ($completeResult['message'] ?? 'Unknown error');
        }
        
        // Get the promoted build's timing
        $stmt = $conn->prepare("SELECT starts_at, finish_time, status FROM building_queue WHERE id = ?");
        $stmt->bind_param("i", $queueItemId2);
        $stmt->execute();
        $promotedItem = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$promotedItem) {
            // Cleanup
            $conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");
            return "Promoted build not found";
        }
        
        if ($promotedItem['status'] !== 'active') {
            // Cleanup
            $conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");
            return "Promoted build is not active (status: {$promotedItem['status']})";
        }
        
        // Calculate expected build time with new HQ level
        $expectedBuildTime = $buildingConfigManager->calculateUpgradeTime(
            $input['building2'], 
            $input['current_level2'], 
            $input['new_hq_level']
        );
        
        // Calculate actual build time from queue
        $startsAt = strtotime($promotedItem['starts_at']);
        $finishTime = strtotime($promotedItem['finish_time']);
        $actualBuildTime = $finishTime - $startsAt;
        
        // Cleanup
        $conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");
        
        // Verify property: build time should match calculation with new HQ level
        // Allow 1 second tolerance for rounding
        if (abs($actualBuildTime - $expectedBuildTime) > 1) {
            return "Build time mismatch: expected {$expectedBuildTime}s (with HQ {$input['new_hq_level']}), got {$actualBuildTime}s";
        }
        
        return true;
    },
    "Property 20: HQ Level Recalculation on Promotion"
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
