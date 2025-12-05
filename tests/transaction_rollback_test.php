<?php
/**
 * Unit Tests for Transaction Rollback
 * Task: 15.1 Write unit tests for transaction rollback
 * Requirements: 7.4
 * 
 * Tests:
 * - Test rollback on resource deduction failure
 * - Test rollback on queue insert failure
 * - Test rollback on completion failure
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingQueueManager.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';

class TransactionRollbackTest {
    private $conn;
    private $buildingConfigManager;
    private $buildingQueueManager;
    private $testUserId;
    private $testVillageId;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->buildingConfigManager = new BuildingConfigManager($conn);
        $this->buildingQueueManager = new BuildingQueueManager($conn, $this->buildingConfigManager);
    }
    
    public function setUp(): void {
        // Create test user
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = 'test_rollback_user' LIMIT 1");
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$userRow) {
            $stmt = $this->conn->prepare("INSERT INTO users (username, email, password, is_protected) VALUES (?, ?, ?, 0)");
            $username = 'test_rollback_user';
            $email = 'test_rollback@example.com';
            $password = password_hash('test', PASSWORD_DEFAULT);
            $stmt->bind_param("sss", $username, $email, $password);
            $stmt->execute();
            $this->testUserId = $this->conn->insert_id;
            $stmt->close();
        } else {
            $this->testUserId = (int)$userRow['id'];
        }
        
        // Create test village with resources
        $stmt = $this->conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $this->testUserId);
        $stmt->execute();
        $villageRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$villageRow) {
            $stmt = $this->conn->prepare("INSERT INTO villages (user_id, name, x_coord, y_coord, wood, clay, iron, world_id) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $villageName = 'Test Rollback Village';
            $x = rand(1, 100);
            $y = rand(1, 100);
            $wood = 10000;
            $clay = 10000;
            $iron = 10000;
            $stmt->bind_param("isiiii", $this->testUserId, $villageName, $x, $y, $wood, $clay, $iron);
            $stmt->execute();
            $this->testVillageId = $this->conn->insert_id;
            $stmt->close();
        } else {
            $this->testVillageId = (int)$villageRow['id'];
            // Reset resources
            $stmt = $this->conn->prepare("UPDATE villages SET wood = 10000, clay = 10000, iron = 10000 WHERE id = ?");
            $stmt->bind_param("i", $this->testVillageId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Set up main_building at level 10
        $stmt = $this->conn->prepare("SELECT id FROM building_types WHERE internal_name = 'main_building'");
        $stmt->execute();
        $mainBuildingResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($mainBuildingResult) {
            $mainBuildingTypeId = $mainBuildingResult['id'];
            
            $stmt = $this->conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
            $stmt->bind_param("ii", $this->testVillageId, $mainBuildingTypeId);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $this->conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 10)");
            $stmt->bind_param("ii", $this->testVillageId, $mainBuildingTypeId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Set up barracks at level 0
        $stmt = $this->conn->prepare("SELECT id FROM building_types WHERE internal_name = 'barracks'");
        $stmt->execute();
        $barracksResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($barracksResult) {
            $barracksTypeId = $barracksResult['id'];
            
            $stmt = $this->conn->prepare("DELETE FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
            $stmt->bind_param("ii", $this->testVillageId, $barracksTypeId);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $this->conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 0)");
            $stmt->bind_param("ii", $this->testVillageId, $barracksTypeId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Clean up any existing queue items
        $stmt = $this->conn->prepare("DELETE FROM building_queue WHERE village_id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $stmt->close();
    }
    
    public function tearDown(): void {
        // Clean up queue items
        if ($this->testVillageId) {
            $stmt = $this->conn->prepare("DELETE FROM building_queue WHERE village_id = ?");
            $stmt->bind_param("i", $this->testVillageId);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Test: Rollback on resource deduction failure
     * 
     * Scenario: Attempt to queue a build with insufficient resources
     * Expected: Transaction rolls back, no queue item created, resources unchanged
     */
    public function testRollbackOnInsufficientResources(): bool {
        echo "Test: Rollback on insufficient resources\n";
        
        // Set village to have insufficient resources
        $stmt = $this->conn->prepare("UPDATE villages SET wood = 10, clay = 10, iron = 10 WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $stmt->close();
        
        // Get initial resources
        $stmt = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $initialResources = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get initial queue count
        $stmt = $this->conn->prepare("SELECT COUNT(*) as cnt FROM building_queue WHERE village_id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $initialQueueCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        
        // Attempt to enqueue build (should fail due to insufficient resources)
        $result = $this->buildingQueueManager->enqueueBuild($this->testVillageId, 'barracks', $this->testUserId);
        
        // Get final resources
        $stmt = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $finalResources = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get final queue count
        $stmt = $this->conn->prepare("SELECT COUNT(*) as cnt FROM building_queue WHERE village_id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $finalQueueCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        
        // Verify rollback occurred
        $success = true;
        
        if ($result['success'] !== false) {
            echo "  ✗ FAIL: Expected enqueue to fail, but it succeeded\n";
            $success = false;
        }
        
        if ($result['error_code'] !== 'ERR_RES') {
            echo "  ✗ FAIL: Expected error code ERR_RES, got " . ($result['error_code'] ?? 'none') . "\n";
            $success = false;
        }
        
        if ($finalResources['wood'] != $initialResources['wood'] ||
            $finalResources['clay'] != $initialResources['clay'] ||
            $finalResources['iron'] != $initialResources['iron']) {
            echo "  ✗ FAIL: Resources changed after failed enqueue\n";
            echo "    Initial: wood={$initialResources['wood']}, clay={$initialResources['clay']}, iron={$initialResources['iron']}\n";
            echo "    Final: wood={$finalResources['wood']}, clay={$finalResources['clay']}, iron={$finalResources['iron']}\n";
            $success = false;
        }
        
        if ($finalQueueCount != $initialQueueCount) {
            echo "  ✗ FAIL: Queue count changed after failed enqueue (initial: {$initialQueueCount}, final: {$finalQueueCount})\n";
            $success = false;
        }
        
        if ($success) {
            echo "  ✓ PASS: Transaction rolled back correctly on insufficient resources\n";
        }
        
        echo "\n";
        return $success;
    }
    
    /**
     * Test: Rollback on queue insert failure
     * 
     * Scenario: Simulate a queue insert failure by attempting to queue when queue is full
     * Expected: Transaction rolls back, resources are not deducted
     */
    public function testRollbackOnQueueFull(): bool {
        echo "Test: Rollback on queue full\n";
        
        // Reset resources to sufficient amount
        $stmt = $this->conn->prepare("UPDATE villages SET wood = 10000, clay = 10000, iron = 10000 WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $stmt->close();
        
        // Fill the queue to max capacity
        $maxQueueItems = defined('BUILDING_QUEUE_MAX_ITEMS') ? (int)BUILDING_QUEUE_MAX_ITEMS : 10;
        
        for ($i = 0; $i < $maxQueueItems; $i++) {
            $this->buildingQueueManager->enqueueBuild($this->testVillageId, 'barracks', $this->testUserId);
        }
        
        // Get resources before attempting to add to full queue
        $stmt = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $initialResources = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Attempt to enqueue another build (should fail due to full queue)
        $result = $this->buildingQueueManager->enqueueBuild($this->testVillageId, 'barracks', $this->testUserId);
        
        // Get resources after failed enqueue
        $stmt = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $finalResources = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Verify rollback occurred
        $success = true;
        
        if ($result['success'] !== false) {
            echo "  ✗ FAIL: Expected enqueue to fail on full queue, but it succeeded\n";
            $success = false;
        }
        
        if ($result['error_code'] !== 'ERR_CAP' && $result['error_code'] !== 'ERR_QUEUE_CAP') {
            echo "  ✗ FAIL: Expected error code ERR_CAP or ERR_QUEUE_CAP, got " . ($result['error_code'] ?? 'none') . "\n";
            $success = false;
        }
        
        if ($finalResources['wood'] != $initialResources['wood'] ||
            $finalResources['clay'] != $initialResources['clay'] ||
            $finalResources['iron'] != $initialResources['iron']) {
            echo "  ✗ FAIL: Resources changed after failed enqueue on full queue\n";
            echo "    Initial: wood={$initialResources['wood']}, clay={$initialResources['clay']}, iron={$initialResources['iron']}\n";
            echo "    Final: wood={$finalResources['wood']}, clay={$finalResources['clay']}, iron={$finalResources['iron']}\n";
            $success = false;
        }
        
        if ($success) {
            echo "  ✓ PASS: Transaction rolled back correctly on queue full\n";
        }
        
        echo "\n";
        return $success;
    }
    
    /**
     * Test: Rollback on completion failure
     * 
     * Scenario: Attempt to complete a build that doesn't exist or is invalid
     * Expected: Transaction rolls back, no changes to database
     */
    public function testRollbackOnCompletionFailure(): bool {
        echo "Test: Rollback on completion failure\n";
        
        // Clean up queue
        $stmt = $this->conn->prepare("DELETE FROM building_queue WHERE village_id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $stmt->close();
        
        // Reset resources
        $stmt = $this->conn->prepare("UPDATE villages SET wood = 10000, clay = 10000, iron = 10000 WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $stmt->close();
        
        // Get initial building level
        $stmt = $this->conn->prepare("
            SELECT vb.level FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = 'barracks'
        ");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $initialLevel = $stmt->get_result()->fetch_assoc()['level'];
        $stmt->close();
        
        // Attempt to complete a non-existent queue item
        $nonExistentQueueId = 999999;
        $result = $this->buildingQueueManager->onBuildComplete($nonExistentQueueId);
        
        // Get final building level
        $stmt = $this->conn->prepare("
            SELECT vb.level FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = 'barracks'
        ");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $finalLevel = $stmt->get_result()->fetch_assoc()['level'];
        $stmt->close();
        
        // Verify rollback occurred
        $success = true;
        
        if ($result['success'] !== false) {
            echo "  ✗ FAIL: Expected completion to fail on non-existent queue item, but it succeeded\n";
            $success = false;
        }
        
        if ($finalLevel != $initialLevel) {
            echo "  ✗ FAIL: Building level changed after failed completion (initial: {$initialLevel}, final: {$finalLevel})\n";
            $success = false;
        }
        
        if ($success) {
            echo "  ✓ PASS: Transaction rolled back correctly on completion failure\n";
        }
        
        echo "\n";
        return $success;
    }
    
    /**
     * Test: Rollback on completion of already completed build
     * 
     * Scenario: Attempt to complete a build that's already marked as completed
     * Expected: Idempotent behavior, no changes, no errors
     */
    public function testIdempotentCompletion(): bool {
        echo "Test: Idempotent completion (already completed)\n";
        
        // Clean up and reset
        $stmt = $this->conn->prepare("DELETE FROM building_queue WHERE village_id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $this->conn->prepare("UPDATE villages SET wood = 10000, clay = 10000, iron = 10000 WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $stmt->close();
        
        // Enqueue a build
        $enqueueResult = $this->buildingQueueManager->enqueueBuild($this->testVillageId, 'barracks', $this->testUserId);
        
        if (!$enqueueResult['success']) {
            echo "  ✗ FAIL: Could not enqueue build for test\n\n";
            return false;
        }
        
        $queueItemId = $enqueueResult['queue_item_id'];
        
        // Mark it as completed manually
        $stmt = $this->conn->prepare("UPDATE building_queue SET status = 'completed', finish_time = ? WHERE id = ?");
        $pastTime = date('Y-m-d H:i:s', time() - 3600);
        $stmt->bind_param("si", $pastTime, $queueItemId);
        $stmt->execute();
        $stmt->close();
        
        // Get building level before
        $stmt = $this->conn->prepare("
            SELECT vb.level FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = 'barracks'
        ");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $initialLevel = $stmt->get_result()->fetch_assoc()['level'];
        $stmt->close();
        
        // Attempt to complete it again
        $result = $this->buildingQueueManager->onBuildComplete($queueItemId);
        
        // Get building level after
        $stmt = $this->conn->prepare("
            SELECT vb.level FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = 'barracks'
        ");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $finalLevel = $stmt->get_result()->fetch_assoc()['level'];
        $stmt->close();
        
        // Verify idempotent behavior
        $success = true;
        
        if ($result['success'] !== true) {
            echo "  ✗ FAIL: Expected success=true for idempotent completion\n";
            $success = false;
        }
        
        if (!isset($result['skipped']) || $result['skipped'] !== true) {
            echo "  ✗ FAIL: Expected skipped=true for already completed build\n";
            $success = false;
        }
        
        if ($finalLevel != $initialLevel) {
            echo "  ✗ FAIL: Building level changed on idempotent completion (initial: {$initialLevel}, final: {$finalLevel})\n";
            $success = false;
        }
        
        if ($success) {
            echo "  ✓ PASS: Idempotent completion handled correctly\n";
        }
        
        echo "\n";
        return $success;
    }
    
    public function runAll(): bool {
        echo "=== Transaction Rollback Tests ===\n\n";
        
        $allPassed = true;
        
        $this->setUp();
        $allPassed = $this->testRollbackOnInsufficientResources() && $allPassed;
        $this->tearDown();
        
        $this->setUp();
        $allPassed = $this->testRollbackOnQueueFull() && $allPassed;
        $this->tearDown();
        
        $this->setUp();
        $allPassed = $this->testRollbackOnCompletionFailure() && $allPassed;
        $this->tearDown();
        
        $this->setUp();
        $allPassed = $this->testIdempotentCompletion() && $allPassed;
        $this->tearDown();
        
        return $allPassed;
    }
}

// Run tests
$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    die("No database connection available.\n");
}

$test = new TransactionRollbackTest($conn);
$allPassed = $test->runAll();

echo "=== Test Summary ===\n";
if ($allPassed) {
    echo "All transaction rollback tests passed!\n";
    exit(0);
} else {
    echo "Some transaction rollback tests failed.\n";
    exit(1);
}

