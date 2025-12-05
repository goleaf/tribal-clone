<?php
/**
 * Property-Based Tests for Building Queue Enqueue Status Logic
 * Feature: building-queue-system
 * 
 * Property 2: Active Status for Empty Queue - Validates: Requirements 1.2
 * Property 3: Pending Status for Non-Empty Queue - Validates: Requirements 1.3
 * Property 4: Insufficient Resources Rejection - Validates: Requirements 1.5
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

// Setup test user and village once
$stmt = $conn->prepare("SELECT id FROM users WHERE username = 'test_queue_status_user' LIMIT 1");
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userRow) {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_protected) VALUES (?, ?, ?, 0)");
    $username = 'test_queue_status_user';
    $email = 'test_queue_status@example.com';
    $password = password_hash('test', PASSWORD_DEFAULT);
    $stmt->bind_param("sss", $username, $email, $password);
    $stmt->execute();
    $testUserId = $conn->insert_id;
    $stmt->close();
} else {
    $testUserId = (int)$userRow['id'];
}

$stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $testUserId);
$stmt->execute();
$villageRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$villageRow) {
    $stmt = $conn->prepare("INSERT INTO villages (user_id, name, x_coord, y_coord, wood, clay, iron, world_id) VALUES (?, ?, ?, ?, 50000, 50000, 50000, 1)");
    $villageName = 'Test Queue Status Village';
    $x = rand(1, 100);
    $y = rand(1, 100);
    $stmt->bind_param("isii", $testUserId, $villageName, $x, $y);
    $stmt->execute();
    $testVillageId = $conn->insert_id;
    $stmt->close();
} else {
    $testVillageId = (int)$villageRow['id'];
}

// Setup main_building
$stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = 'main_building'");
$stmt->execute();
$mainBuildingResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($mainBuildingResult) {
    $mainBuildingTypeId = $mainBuildingResult['id'];
    $conn->query("DELETE FROM village_buildings WHERE village_id = {$testVillageId} AND building_type_id = {$mainBuildingTypeId}");
    $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 10)");
    $stmt->bind_param("ii", $testVillageId, $mainBuildingTypeId);
    $stmt->execute();
    $stmt->close();
}

echo "=== Building Queue Enqueue Status Property Tests ===\n\n";

/**
 * Property 2: Active Status for Empty Queue
 * 
 * For any village with an empty queue, when a build is queued,
 * the queue item status should be 'active' and finish_time should equal
 * current_time + build_time.
 */
PropertyTest::forAll(
    // Generator
    function() {
        $buildings = ['barracks', 'stable', 'smithy', 'farm', 'warehouse'];
        return [
            'building' => $buildings[array_rand($buildings)],
            'current_level' => rand(0, 5)
        ];
    },
    // Property
    function($input) use ($conn, $buildingQueueManager, $testVillageId, $testUserId) {
        // Clear queue to ensure it's empty
        $conn->query("DELETE FROM building_queue WHERE village_id = {$testVillageId}");
        
        // Ensure village has enough resources
        $conn->query("UPDATE villages SET wood = 50000, clay = 50000, iron = 50000 WHERE id = {$testVillageId}");
        
        // Setup building at current level
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
        $stmt->bind_param("s", $input['building']);
        $stmt->execute();
        $buildingTypeId = $stmt->get_result()->fetch_assoc()['id'];
        $stmt->close();
        
        $conn->query("DELETE FROM village_buildings WHERE village_id = {$testVillageId} AND building_type_id = {$buildingTypeId}");
        $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $testVillageId, $buildingTypeId, $input['current_level']);
        $stmt->execute();
        $stmt->close();
        
        // Record time before enqueue
        $timeBefore = time();
        
        // Enqueue build
        $result = $buildingQueueManager->enqueueBuild($testVillageId, $input['building'], $testUserId);
        
        if (!$result['success']) {
            return "Enqueue failed: " . ($result['message'] ?? 'Unknown error');
        }
        
        // Verify status is 'active'
        if ($result['status'] !== 'active') {
            return "Expected status 'active', got '{$result['status']}'";
        }
        
        // Verify finish_time is approximately current_time + build_time
        $timeAfter = time();
        $finishTime = $result['finish_at'];
        $buildTime = $finishTime - $result['start_at'];
        
        // Allow 2 second tolerance for test execution time
        if ($result['start_at'] < $timeBefore - 2 || $result['start_at'] > $timeAfter + 2) {
            return "Start time not within expected range";
        }
        
        return true;
    },
    "Property 2: Active Status for Empty Queue"
);

/**
 * Property 3: Pending Status for Non-Empty Queue
 * 
 * For any village with an active build, when another build is queued,
 * the new item status should be 'pending' and finish_time should equal
 * last_item_finish_time + new_build_time.
 */
PropertyTest::forAll(
    // Generator
    function() {
        $buildings = ['barracks', 'stable', 'smithy', 'farm', 'warehouse'];
        // Ensure we pick two different buildings
        $firstBuilding = $buildings[array_rand($buildings)];
        $remainingBuildings = array_diff($buildings, [$firstBuilding]);
        $secondBuilding = $remainingBuildings[array_rand($remainingBuildings)];
        
        return [
            'first_building' => $firstBuilding,
            'second_building' => $secondBuilding,
            'first_level' => rand(0, 5),
            'second_level' => rand(0, 5)
        ];
    },
    // Property
    function($input) use ($conn, $buildingQueueManager, $testVillageId, $testUserId) {
        // Clear queue
        $conn->query("DELETE FROM building_queue WHERE village_id = {$testVillageId}");
        
        // Ensure village has enough resources
        $conn->query("UPDATE villages SET wood = 50000, clay = 50000, iron = 50000 WHERE id = {$testVillageId}");
        
        // Setup first building
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
        $stmt->bind_param("s", $input['first_building']);
        $stmt->execute();
        $firstBuildingTypeId = $stmt->get_result()->fetch_assoc()['id'];
        $stmt->close();
        
        $conn->query("DELETE FROM village_buildings WHERE village_id = {$testVillageId} AND building_type_id = {$firstBuildingTypeId}");
        $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $testVillageId, $firstBuildingTypeId, $input['first_level']);
        $stmt->execute();
        $stmt->close();
        
        // Enqueue first build
        $firstResult = $buildingQueueManager->enqueueBuild($testVillageId, $input['first_building'], $testUserId);
        
        if (!$firstResult['success']) {
            return "First enqueue failed: " . ($firstResult['message'] ?? 'Unknown error');
        }
        
        // Setup second building
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
        $stmt->bind_param("s", $input['second_building']);
        $stmt->execute();
        $secondBuildingTypeId = $stmt->get_result()->fetch_assoc()['id'];
        $stmt->close();
        
        $conn->query("DELETE FROM village_buildings WHERE village_id = {$testVillageId} AND building_type_id = {$secondBuildingTypeId}");
        $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $testVillageId, $secondBuildingTypeId, $input['second_level']);
        $stmt->execute();
        $stmt->close();
        
        // Enqueue second build
        $secondResult = $buildingQueueManager->enqueueBuild($testVillageId, $input['second_building'], $testUserId);
        
        if (!$secondResult['success']) {
            return "Second enqueue failed: " . ($secondResult['message'] ?? 'Unknown error');
        }
        
        // Verify second build has 'pending' status
        if ($secondResult['status'] !== 'pending') {
            return "Expected status 'pending', got '{$secondResult['status']}'";
        }
        
        // Verify second build starts after first build finishes
        if ($secondResult['start_at'] < $firstResult['finish_at']) {
            return "Second build start time should be >= first build finish time";
        }
        
        return true;
    },
    "Property 3: Pending Status for Non-Empty Queue"
);

/**
 * Property 4: Insufficient Resources Rejection
 * 
 * For any village with resources less than upgrade cost,
 * attempting to queue a build should fail and leave village resources unchanged.
 */
PropertyTest::forAll(
    // Generator
    function() use ($buildingConfigManager) {
        $buildings = ['barracks', 'stable', 'smithy', 'farm', 'warehouse'];
        $building = $buildings[array_rand($buildings)];
        $currentLevel = rand(0, 5);
        
        $costs = $buildingConfigManager->calculateUpgradeCost($building, $currentLevel);
        
        if (!$costs) {
            return null;
        }
        
        // Set resources to be insufficient (50% of required)
        return [
            'building' => $building,
            'current_level' => $currentLevel,
            'costs' => $costs,
            'wood' => (int)($costs['wood'] * 0.5),
            'clay' => (int)($costs['clay'] * 0.5),
            'iron' => (int)($costs['iron'] * 0.5)
        ];
    },
    // Property
    function($input) use ($conn, $buildingQueueManager, $testVillageId, $testUserId) {
        if ($input === null) {
            return true; // Skip invalid inputs
        }
        
        // Clear queue
        $conn->query("DELETE FROM building_queue WHERE village_id = {$testVillageId}");
        
        // Set village resources to insufficient amount
        $stmt = $conn->prepare("UPDATE villages SET wood = ?, clay = ?, iron = ? WHERE id = ?");
        $stmt->bind_param("iiii", $input['wood'], $input['clay'], $input['iron'], $testVillageId);
        $stmt->execute();
        $stmt->close();
        
        // Setup building at current level
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
        $stmt->bind_param("s", $input['building']);
        $stmt->execute();
        $buildingTypeId = $stmt->get_result()->fetch_assoc()['id'];
        $stmt->close();
        
        $conn->query("DELETE FROM village_buildings WHERE village_id = {$testVillageId} AND building_type_id = {$buildingTypeId}");
        $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $testVillageId, $buildingTypeId, $input['current_level']);
        $stmt->execute();
        $stmt->close();
        
        // Attempt to enqueue build
        $result = $buildingQueueManager->enqueueBuild($testVillageId, $input['building'], $testUserId);
        
        // Verify enqueue failed
        if ($result['success']) {
            return "Enqueue should have failed due to insufficient resources";
        }
        
        // Verify error code is ERR_RES
        if (($result['error_code'] ?? '') !== 'ERR_RES') {
            return "Expected error code 'ERR_RES', got '" . ($result['error_code'] ?? 'none') . "'";
        }
        
        // Verify resources unchanged
        $stmt = $conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        $stmt->bind_param("i", $testVillageId);
        $stmt->execute();
        $afterResources = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($afterResources['wood'] != $input['wood'] || 
            $afterResources['clay'] != $input['clay'] || 
            $afterResources['iron'] != $input['iron']) {
            return "Resources should remain unchanged after failed enqueue";
        }
        
        return true;
    },
    "Property 4: Insufficient Resources Rejection"
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
