<?php
/**
 * Property-Based Test for Notification Creation on Completion
 * Feature: building-queue-system, Property 27: Notification Creation on Completion
 * Validates: Requirements 10.1
 * 
 * Property: For any build that completes, a notification should be created for the village owner.
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

echo "=== Notification Creation on Completion Property Test ===\n\n";

/**
 * Property 27: Notification Creation on Completion
 * 
 * For any build that completes, a notification should be created for the village owner.
 */
PropertyTest::forAll(
    // Generator: Create random test scenario with a completed build
    function() use ($conn) {
        // Get available buildings
        $buildings = ['barracks', 'stable', 'smithy', 'farm', 'warehouse'];
        $building = $buildings[array_rand($buildings)];
        
        // Random level (1-5)
        $level = rand(1, 5);
        
        return [
            'building' => $building,
            'level' => $level
        ];
    },
    // Property: Notification is created for village owner
    function($input) use ($conn, $buildingQueueManager) {
        // Get or create test user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = 'test_notif_user' LIMIT 1");
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$userRow) {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_protected) VALUES (?, ?, ?, 0)");
            $username = 'test_notif_user';
            $email = 'test_notif@example.com';
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
            $villageName = 'Test Notif Village';
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
        
        // Setup test building
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
        $stmt->bind_param("s", $input['building']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) {
            return "Building type not found";
        }
        
        $buildingTypeId = $result['id'];
        
        // Set building to level - 1
        $currentLevel = $input['level'] - 1;
        $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $villageId, $buildingTypeId);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $villageId, $buildingTypeId, $currentLevel);
        $stmt->execute();
        $villageBuildingId = $conn->insert_id;
        $stmt->close();
        
        // Count notifications before
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $beforeCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        
        // Create a completed queue item (simulate a build that just finished)
        $finishTime = date('Y-m-d H:i:s', time() - 1); // 1 second ago
        $stmt = $conn->prepare("
            INSERT INTO building_queue 
            (village_id, village_building_id, building_type_id, level, starts_at, finish_time, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param("iiiiss", $villageId, $villageBuildingId, $buildingTypeId, $input['level'], $finishTime, $finishTime);
        $stmt->execute();
        $queueItemId = $conn->insert_id;
        $stmt->close();
        
        // Complete the build
        $result = $buildingQueueManager->onBuildComplete($queueItemId);
        
        // Count notifications after
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $afterCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        
        // Cleanup
        $conn->query("DELETE FROM building_queue WHERE id = {$queueItemId}");
        $conn->query("DELETE FROM notifications WHERE user_id = {$userId}");
        
        // Verify property
        if (!$result['success']) {
            return "Build completion failed: " . ($result['message'] ?? 'Unknown error');
        }
        
        if ($afterCount !== $beforeCount + 1) {
            return "Expected 1 new notification, got " . ($afterCount - $beforeCount);
        }
        
        return true;
    },
    "Property 27: Notification Creation on Completion"
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
