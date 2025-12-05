<?php
/**
 * Unit tests for Building Queue Logging Functionality
 * Task 10.1: Write unit tests for logging functionality
 * 
 * Tests:
 * - Log file creation
 * - JSONL format
 * - Error logging
 * 
 * Requirements: 4.4, 8.5
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingQueueManager.php';

// Test helper functions
function clearLogFile(string $logFile): void {
    if (file_exists($logFile)) {
        unlink($logFile);
    }
}

function getLogEntries(string $logFile): array {
    if (!file_exists($logFile)) {
        return [];
    }
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_map(function($line) {
        $decoded = json_decode($line, true);
        return $decoded !== null ? $decoded : [];
    }, $lines);
}

function assertLogFileExists(string $logFile, string $testName): bool {
    if (file_exists($logFile)) {
        echo "  ✓ PASS: {$testName} - Log file exists\n";
        return true;
    } else {
        echo "  ✗ FAIL: {$testName} - Log file does not exist\n";
        return false;
    }
}

function assertJsonlFormat(array $entries, string $testName): bool {
    $allValid = true;
    foreach ($entries as $idx => $entry) {
        if (empty($entry)) {
            echo "  ✗ FAIL: {$testName} - Entry {$idx} is not valid JSON\n";
            $allValid = false;
        }
    }
    if ($allValid && count($entries) > 0) {
        echo "  ✓ PASS: {$testName} - All entries are valid JSONL format\n";
    }
    return $allValid;
}

function assertFieldExists(array $entry, string $field, string $testName): bool {
    if (isset($entry[$field])) {
        echo "  ✓ PASS: {$testName} - Field '{$field}' exists\n";
        return true;
    } else {
        echo "  ✗ FAIL: {$testName} - Field '{$field}' missing\n";
        return false;
    }
}

// Setup
$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    die("No database connection available.\n");
}

$configManager = new BuildingConfigManager($conn);
$queueManager = new BuildingQueueManager($conn, $configManager);

$logDir = __DIR__ . '/../logs';
$auditLog = $logDir . '/build_queue.log';
$metricsLog = $logDir . '/build_queue_metrics.log';

echo "=== Building Queue Logging Tests ===\n\n";

// Test 1: Log file creation on enqueue
echo "Test 1: Log file creation on enqueue\n";
clearLogFile($auditLog);
clearLogFile($metricsLog);

// Create test user and village (reuse if exists)
$stmt = $conn->prepare("SELECT id FROM users WHERE username = 'test_log_user' LIMIT 1");
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userRow) {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $username = 'test_log_user';
    $email = 'test_log@test.com';
    $password = password_hash('test123', PASSWORD_DEFAULT);
    $stmt->bind_param("sss", $username, $email, $password);
    $stmt->execute();
    $userId = $conn->insert_id;
    $stmt->close();
} else {
    $userId = (int)$userRow['id'];
}

$stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$villageRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$villageRow) {
    // Find unique coordinates
    $x = rand(100, 999);
    $y = rand(100, 999);
    $stmt = $conn->prepare("INSERT INTO villages (user_id, name, x_coord, y_coord, wood, clay, iron, world_id) VALUES (?, 'Test Log Village', ?, ?, 10000, 10000, 10000, 1)");
    $stmt->bind_param("iii", $userId, $x, $y);
    if (!$stmt->execute()) {
        echo "  Debug: Failed to insert village: " . $stmt->error . "\n";
    }
    $villageId = $stmt->insert_id;
    if ($villageId == 0) {
        $villageId = $conn->insert_id;
    }
    $stmt->close();
} else {
    $villageId = (int)$villageRow['id'];
    // Ensure resources
    $conn->query("UPDATE villages SET wood = 10000, clay = 10000, iron = 10000 WHERE id = {$villageId}");
}

// Initialize village buildings
$stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = 'main_building'");
$stmt->execute();
$mainBuildingResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($mainBuildingResult) {
    $mainBuildingTypeId = $mainBuildingResult['id'];
    $conn->query("DELETE FROM village_buildings WHERE village_id = {$villageId} AND building_type_id = {$mainBuildingTypeId}");
    $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 1)");
    $stmt->bind_param("ii", $villageId, $mainBuildingTypeId);
    $stmt->execute();
    $stmt->close();
}

$stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = 'barracks'");
$stmt->execute();
$barracksResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($barracksResult) {
    $barracksTypeId = $barracksResult['id'];
    $conn->query("DELETE FROM village_buildings WHERE village_id = {$villageId} AND building_type_id = {$barracksTypeId}");
    $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, 0)");
    $stmt->bind_param("ii", $villageId, $barracksTypeId);
    $stmt->execute();
    $stmt->close();
}

// Clear any existing queue items
$conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");

// Enqueue a build
$result = $queueManager->enqueueBuild($villageId, 'barracks', $userId);

if ($result['success']) {
    assertLogFileExists($auditLog, "Audit log");
    assertLogFileExists($metricsLog, "Metrics log");
} else {
    echo "  ✗ FAIL: Failed to enqueue build: {$result['message']}\n";
}

// Test 2: JSONL format validation for audit log
echo "\nTest 2: JSONL format validation for audit log\n";
$auditEntries = getLogEntries($auditLog);
if (count($auditEntries) > 0) {
    echo "  ✓ PASS: Audit log has " . count($auditEntries) . " entries\n";
    assertJsonlFormat($auditEntries, "Audit log");
    
    // Check required fields in enqueue event
    $enqueueEntry = null;
    foreach ($auditEntries as $entry) {
        if (isset($entry['type']) && $entry['type'] === 'enqueue') {
            $enqueueEntry = $entry;
            break;
        }
    }
    
    if ($enqueueEntry) {
        echo "  ✓ PASS: Found 'enqueue' event in audit log\n";
        assertFieldExists($enqueueEntry, 'type', "Enqueue event");
        assertFieldExists($enqueueEntry, 'ts', "Enqueue event");
        assertFieldExists($enqueueEntry, 'date', "Enqueue event");
        assertFieldExists($enqueueEntry, 'queue_item_id', "Enqueue event");
        assertFieldExists($enqueueEntry, 'village_id', "Enqueue event");
        assertFieldExists($enqueueEntry, 'user_id', "Enqueue event");
        assertFieldExists($enqueueEntry, 'building', "Enqueue event");
        assertFieldExists($enqueueEntry, 'level', "Enqueue event");
        assertFieldExists($enqueueEntry, 'costs', "Enqueue event");
        assertFieldExists($enqueueEntry, 'status', "Enqueue event");
    } else {
        echo "  ✗ FAIL: No 'enqueue' event found in audit log\n";
    }
} else {
    echo "  ✗ FAIL: No entries in audit log\n";
}

// Test 3: JSONL format validation for metrics log
echo "\nTest 3: JSONL format validation for metrics log\n";
$metricsEntries = getLogEntries($metricsLog);
if (count($metricsEntries) > 0) {
    echo "  ✓ PASS: Metrics log has " . count($metricsEntries) . " entries\n";
    assertJsonlFormat($metricsEntries, "Metrics log");
    
    // Check required fields in enqueue metric
    $enqueueMetric = null;
    foreach ($metricsEntries as $entry) {
        if (isset($entry['metric']) && $entry['metric'] === 'enqueue') {
            $enqueueMetric = $entry;
            break;
        }
    }
    
    if ($enqueueMetric) {
        echo "  ✓ PASS: Found 'enqueue' metric in metrics log\n";
        assertFieldExists($enqueueMetric, 'metric', "Enqueue metric");
        assertFieldExists($enqueueMetric, 'ts', "Enqueue metric");
        assertFieldExists($enqueueMetric, 'date', "Enqueue metric");
        assertFieldExists($enqueueMetric, 'village_id', "Enqueue metric");
        assertFieldExists($enqueueMetric, 'user_id', "Enqueue metric");
        assertFieldExists($enqueueMetric, 'building', "Enqueue metric");
        assertFieldExists($enqueueMetric, 'level', "Enqueue metric");
        assertFieldExists($enqueueMetric, 'hq_level', "Enqueue metric");
        assertFieldExists($enqueueMetric, 'queue_count', "Enqueue metric");
        assertFieldExists($enqueueMetric, 'status', "Enqueue metric");
    } else {
        echo "  ✗ FAIL: No 'enqueue' metric found in metrics log\n";
    }
} else {
    echo "  ✗ FAIL: No entries in metrics log\n";
}

// Test 4: Error logging on failed enqueue
echo "\nTest 4: Error logging on failed enqueue\n";
clearLogFile($auditLog);
clearLogFile($metricsLog);

// Clear queue first
$conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");

// Try to enqueue with insufficient resources
$conn->query("UPDATE villages SET wood = 0, clay = 0, iron = 0 WHERE id = {$villageId}");

$result = $queueManager->enqueueBuild($villageId, 'barracks', $userId);

if (!$result['success']) {
    echo "  ✓ PASS: Enqueue failed as expected (insufficient resources)\n";
    
    $auditEntries = getLogEntries($auditLog);
    $failedEntry = null;
    foreach ($auditEntries as $entry) {
        if (isset($entry['type']) && $entry['type'] === 'enqueue_failed') {
            $failedEntry = $entry;
            break;
        }
    }
    
    if ($failedEntry) {
        echo "  ✓ PASS: Found 'enqueue_failed' event in audit log\n";
        assertFieldExists($failedEntry, 'type', "Failed enqueue event");
        assertFieldExists($failedEntry, 'error', "Failed enqueue event");
        assertFieldExists($failedEntry, 'code', "Failed enqueue event");
        
        if (isset($failedEntry['code'])) {
            if ($failedEntry['code'] === 'ERR_RES') {
                echo "  ✓ PASS: Error code is 'ERR_RES'\n";
            } else {
                echo "  ✗ FAIL: Error code is '{$failedEntry['code']}', expected 'ERR_RES'\n";
            }
        } else {
            echo "  ✗ FAIL: Error code field is missing\n";
        }
    } else {
        echo "  ✗ FAIL: No 'enqueue_failed' event found in audit log\n";
    }
    
    $metricsEntries = getLogEntries($metricsLog);
    $failedMetric = null;
    foreach ($metricsEntries as $entry) {
        if (isset($entry['metric']) && $entry['metric'] === 'enqueue_failed') {
            $failedMetric = $entry;
            break;
        }
    }
    
    if ($failedMetric) {
        echo "  ✓ PASS: Found 'enqueue_failed' metric in metrics log\n";
        assertFieldExists($failedMetric, 'error_code', "Failed enqueue metric");
    } else {
        echo "  ✗ FAIL: No 'enqueue_failed' metric found in metrics log\n";
    }
} else {
    echo "  ✗ FAIL: Enqueue should have failed but succeeded\n";
}

// Test 5: Completion logging
echo "\nTest 5: Completion logging\n";
clearLogFile($auditLog);
clearLogFile($metricsLog);

// Clear queue first
$conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");

// Setup: Create a completed build scenario
$conn->query("UPDATE villages SET wood = 10000, clay = 10000, iron = 10000 WHERE id = {$villageId}");

$result = $queueManager->enqueueBuild($villageId, 'barracks', $userId);
if ($result['success']) {
    $queueItemId = $result['queue_item_id'];
    
    // Force completion time to past
    $conn->query("UPDATE building_queue SET finish_time = datetime('now', '-1 minute') WHERE id = {$queueItemId}");
    
    clearLogFile($auditLog);
    clearLogFile($metricsLog);
    
    // Complete the build
    $completeResult = $queueManager->onBuildComplete($queueItemId);
    
    if ($completeResult['success']) {
        echo "  ✓ PASS: Build completed successfully\n";
        
        $auditEntries = getLogEntries($auditLog);
        $completeEntry = null;
        foreach ($auditEntries as $entry) {
            if (isset($entry['type']) && $entry['type'] === 'complete') {
                $completeEntry = $entry;
                break;
            }
        }
        
        if ($completeEntry) {
            echo "  ✓ PASS: Found 'complete' event in audit log\n";
            assertFieldExists($completeEntry, 'queue_item_id', "Complete event");
            assertFieldExists($completeEntry, 'village_id', "Complete event");
            assertFieldExists($completeEntry, 'level', "Complete event");
        } else {
            echo "  ✗ FAIL: No 'complete' event found in audit log\n";
        }
        
        $metricsEntries = getLogEntries($metricsLog);
        $completeMetric = null;
        foreach ($metricsEntries as $entry) {
            if (isset($entry['metric']) && $entry['metric'] === 'complete') {
                $completeMetric = $entry;
                break;
            }
        }
        
        if ($completeMetric) {
            echo "  ✓ PASS: Found 'complete' metric in metrics log\n";
        } else {
            echo "  ✗ FAIL: No 'complete' metric found in metrics log\n";
        }
    } else {
        echo "  ✗ FAIL: Build completion failed: {$completeResult['message']}\n";
    }
} else {
    echo "  ✗ FAIL: Failed to setup completion test: {$result['message']}\n";
}

// Test 6: Cancellation logging
echo "\nTest 6: Cancellation logging\n";
clearLogFile($auditLog);
clearLogFile($metricsLog);

// Clear queue first
$conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");

$result = $queueManager->enqueueBuild($villageId, 'barracks', $userId);
if ($result['success']) {
    $queueItemId = $result['queue_item_id'];
    
    clearLogFile($auditLog);
    clearLogFile($metricsLog);
    
    // Cancel the build
    $cancelResult = $queueManager->cancelBuild($queueItemId, $userId);
    
    if ($cancelResult['success']) {
        echo "  ✓ PASS: Build canceled successfully\n";
        
        $auditEntries = getLogEntries($auditLog);
        $cancelEntry = null;
        foreach ($auditEntries as $entry) {
            if (isset($entry['type']) && $entry['type'] === 'cancel') {
                $cancelEntry = $entry;
                break;
            }
        }
        
        if ($cancelEntry) {
            echo "  ✓ PASS: Found 'cancel' event in audit log\n";
            assertFieldExists($cancelEntry, 'queue_item_id', "Cancel event");
            assertFieldExists($cancelEntry, 'village_id', "Cancel event");
            assertFieldExists($cancelEntry, 'refund', "Cancel event");
            assertFieldExists($cancelEntry, 'was_active', "Cancel event");
        } else {
            echo "  ✗ FAIL: No 'cancel' event found in audit log\n";
        }
        
        $metricsEntries = getLogEntries($metricsLog);
        $cancelMetric = null;
        foreach ($metricsEntries as $entry) {
            if (isset($entry['metric']) && $entry['metric'] === 'cancel') {
                $cancelMetric = $entry;
                break;
            }
        }
        
        if ($cancelMetric) {
            echo "  ✓ PASS: Found 'cancel' metric in metrics log\n";
        } else {
            echo "  ✗ FAIL: No 'cancel' metric found in metrics log\n";
        }
    } else {
        echo "  ✗ FAIL: Build cancellation failed: {$cancelResult['message']}\n";
    }
} else {
    echo "  ✗ FAIL: Failed to setup cancellation test: {$result['message']}\n";
}

// Cleanup
$conn->query("DELETE FROM building_queue WHERE village_id = {$villageId}");

echo "\n=== All Building Queue Logging Tests Completed ===\n";
