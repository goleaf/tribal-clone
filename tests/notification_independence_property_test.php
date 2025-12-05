<?php
/**
 * Property-Based Test for Multiple Build Notification Independence
 * Feature: building-queue-system, Property 29: Multiple Build Notification Independence
 * Validates: Requirements 10.4
 * 
 * Property: For any sequence of builds that complete, each should generate a separate notification.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingQueueManager.php';

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

echo "=== Multiple Build Notification Independence Property Test ===\n\n";

/**
 * Property 29: Multiple Build Notification Independence
 * 
 * For any sequence of builds that complete, each should generate a separate notification.
 */
PropertyTest::forAll(
    // Generator: Create random number of builds to complete
    function() use ($conn) {
        // Random number of builds (2-5)
        $numBuilds = rand(2, 5);
        
        // Get available buildings
        $availableBuildings = ['barracks', 'stable', 'smithy', 'farm', 'warehouse'];
        
        $builds = [];
        for ($i = 0; $i < $numBuilds; $i++) {
            $builds[] = [
                'building' => $availableBuildings[array_rand($availableBuildings)],
                'level' => rand(1, 5)
            ];
        }
        
        return [
            'builds' => $builds,
            'count' => $numBuilds
        ];
    },
    // Property: Each build generates exactly one notification
    function($input) use ($conn, $buildingQueueManager) {
        // Get or create test user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = 'test_indep_user' LIMIT 1");
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$userRow) {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_protected) VALUES (?, ?, ?, 0)");
            $username = 'test_indep_user';
            $email = 'test_indep@example.com';
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
            $stmt = $conn->prepare("INSERT INTO villages (user_id, name, x_coord, y_coord, wood, clay, iron, world_id) VALUES (?, ?, ?, ?, 10000, 10000, 10000, 1)");
            $villageName = 'Test Indep Village';
            $x = rand(1, 100);
            $y = rand(1, 100);
            $stmt->bind_param("isii", $userId, $villageName, $x, $y);
            $stmt->execute();
            $villageId = $conn->insert_id;
            $stmt->close();
        } else {
            $villageId = (int)$villageRow['id'];
        }
        
        // Setup main_building
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = 'main_building'");
        $stmt->execute();
        $mainBuildingResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($mainBuildingResult) {
            $mainBuildingTypeId = $mainBuildingResult['id'];
            $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
            $stmt->bind_param("ii", $villageId, $mainBuildingTypeId);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 10)");
            $stmt->bind_param("ii", $villageId, $mainBuildingTypeId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Clear previous notifications
        $conn->query("DELETE FROM notifications WHERE user_id = {$userId}");
        
        // Count notifications before
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $beforeCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        
        // Complete each build
        $queueItemIds = [];
        foreach ($input['builds'] as $build) {
            // Get building type
            $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
            $stmt->bind_param("s", $build['building']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$result) {
                continue;
            }
            
            $buildingTypeId = $result['id'];
            
            // Set building to level - 1
            $currentLevel = $build['level'] - 1;
            $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
            $stmt->bind_param("ii", $villageId, $buildingTypeId);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $villageId, $buildingTypeId, $currentLevel);
            $stmt->execute();
            $villageBuildingId = $conn->insert_id;
            $stmt->close();
            
            // Create a completed queue item
            $finishTime = date('Y-m-d H:i:s', time() - 1);
            $stmt = $conn->prepare("
                INSERT INTO building_queue 
                (village_id, village_building_id, building_type_id, level, starts_at, finish_time, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->bind_param("iiiiss", $villageId, $villageBuildingId, $buildingTypeId, $build['level'], $finishTime, $finishTime);
            $stmt->execute();
            $queueItemId = $conn->insert_id;
            $stmt->close();
            
            $queueItemIds[] = $queueItemId;
            
            // Complete the build
            $result = $buildingQueueManager->onBuildComplete($queueItemId);
            
            if (!$result['success']) {
                // Cleanup and return error
                foreach ($queueItemIds as $id) {
                    $conn->query("DELETE FROM building_queue WHERE id = {$id}");
                }
                $conn->query("DELETE FROM notifications WHERE user_id = {$userId}");
                return "Build completion failed: " . ($result['message'] ?? 'Unknown error');
            }
        }
        
        // Count notifications after
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $afterCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        
        // Cleanup
        foreach ($queueItemIds as $id) {
            $conn->query("DELETE FROM building_queue WHERE id = {$id}");
        }
        $conn->query("DELETE FROM notifications WHERE user_id = {$userId}");
        
        // Verify property: each build should create exactly one notification
        $expectedNotifications = $input['count'];
        $actualNotifications = $afterCount - $beforeCount;
        
        if ($actualNotifications !== $expectedNotifications) {
            return "Expected {$expectedNotifications} notifications, got {$actualNotifications}";
        }
        
        return true;
    },
    "Property 29: Multiple Build Notification Independence"
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
