<?php
/**
 * Property-Based Tests for BuildingManager
 * Feature: resource-system
 * 
 * These tests validate correctness properties across many random inputs.
 * Each property test runs minimum 100 iterations.
 * 
 * SECURITY: This test creates isolated test data and cleans up on completion.
 */

// Define test database path before loading config
if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/../data/test_tribal_wars.sqlite');
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingQueueManager.php';
require_once __DIR__ . '/../lib/managers/ResourceManager.php';

// SECURITY: Prevent running against production database
if (strpos(DB_PATH, 'test') === false) {
    die("ERROR: This test must run against a test database only. DB_PATH must include 'test' in the path.\n");
}

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
$queueManager = new BuildingQueueManager($conn, $buildingConfigManager);
$resourceManager = new ResourceManager($conn, $buildingManager);

// SECURITY: Track test data for cleanup
$testCleanup = [
    'user_ids' => [],
    'village_ids' => [],
    'building_queue_ids' => []
];

// SECURITY: Register shutdown function for cleanup
register_shutdown_function(function() use ($conn, &$testCleanup) {
    echo "\n=== Cleaning up test data ===\n";
    
    // Clean building queue entries
    if (!empty($testCleanup['building_queue_ids'])) {
        $placeholders = implode(',', array_fill(0, count($testCleanup['building_queue_ids']), '?'));
        $stmt = $conn->prepare("DELETE FROM building_queue WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($testCleanup['building_queue_ids']));
        $stmt->bind_param($types, ...$testCleanup['building_queue_ids']);
        $stmt->execute();
        $stmt->close();
        echo "Cleaned " . count($testCleanup['building_queue_ids']) . " queue entries\n";
    }
    
    // Clean village buildings
    if (!empty($testCleanup['village_ids'])) {
        $placeholders = implode(',', array_fill(0, count($testCleanup['village_ids']), '?'));
        $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id IN ($placeholders)");
        $types = str_repeat('i', count($testCleanup['village_ids']));
        $stmt->bind_param($types, ...$testCleanup['village_ids']);
        $stmt->execute();
        $stmt->close();
    }
    
    // Clean villages
    if (!empty($testCleanup['village_ids'])) {
        $placeholders = implode(',', array_fill(0, count($testCleanup['village_ids']), '?'));
        $stmt = $conn->prepare("DELETE FROM villages WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($testCleanup['village_ids']));
        $stmt->bind_param($types, ...$testCleanup['village_ids']);
        $stmt->execute();
        $stmt->close();
        echo "Cleaned " . count($testCleanup['village_ids']) . " villages\n";
    }
    
    // Clean users
    if (!empty($testCleanup['user_ids'])) {
        $placeholders = implode(',', array_fill(0, count($testCleanup['user_ids']), '?'));
        $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($testCleanup['user_ids']));
        $stmt->bind_param($types, ...$testCleanup['user_ids']);
        $stmt->execute();
        $stmt->close();
        echo "Cleaned " . count($testCleanup['user_ids']) . " users\n";
    }
});

echo "=== BuildingManager Property-Based Tests ===\n\n";

/**
 * Property 4: Building Upgrade State Transition
 * Feature: resource-system, Property 4: Building Upgrade State Transition
 * Validates: Requirements 2.2
 * 
 * For any valid building upgrade initiation, the village resources SHALL decrease 
 * by exactly the upgrade cost, AND the building queue SHALL contain exactly one 
 * new entry for that building.
 */
PropertyTest::forAll(
    function() use ($conn, $buildingConfigManager, &$testCleanup) {
        // Get a random upgradeable building (exclude special buildings)
        $excludeBuildings = ['first_church', 'church', 'wall'];
        $placeholders = implode(',', array_fill(0, count($excludeBuildings), '?'));
        $stmt = $conn->prepare("SELECT internal_name, max_level FROM building_types WHERE max_level > 1 AND internal_name NOT IN ($placeholders) ORDER BY RANDOM() LIMIT 1");
        $types = str_repeat('s', count($excludeBuildings));
        $stmt->bind_param($types, ...$excludeBuildings);
        $stmt->execute();
        $buildingRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$buildingRow) {
            throw new Exception("No upgradeable buildings found");
        }
        $buildingInternalName = $buildingRow['internal_name'];
        $maxLevel = (int)$buildingRow['max_level'];
        
        // SECURITY: Always create isolated test data with unique identifiers
        $uniqueId = uniqid('prop4_', true);
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, is_protected) VALUES (?, ?, ?, 0)");
        $username = 'test_' . $uniqueId;
        // SECURITY: Use cryptographically secure random password
        $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $email = $username . '@test.local';
        $stmt->bind_param("sss", $username, $password, $email);
        $stmt->execute();
        $userId = $stmt->insert_id;
        $stmt->close();
        $testCleanup['user_ids'][] = $userId;
        
        // SECURITY: Create isolated test village with negative coords to avoid collision
        $stmt = $conn->prepare("INSERT INTO villages (name, user_id, world_id, x_coord, y_coord, wood, clay, iron) VALUES (?, ?, 1, ?, ?, 100000, 100000, 100000)");
        $villageName = 'TestVillage_' . $uniqueId;
        $xCoord = -1000 - rand(0, 9999);
        $yCoord = -1000 - rand(0, 9999);
        $stmt->bind_param("siii", $villageName, $userId, $xCoord, $yCoord);
        $stmt->execute();
        $villageId = $stmt->insert_id;
        $stmt->close();
        $testCleanup['village_ids'][] = $villageId;
        
        // Setup building at upgradeable level
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ? LIMIT 1");
        $stmt->bind_param("s", $buildingInternalName);
        $stmt->execute();
        $buildingTypeRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$buildingTypeRow) {
            throw new Exception("Building type not found: $buildingInternalName");
        }
        $buildingTypeId = (int)$buildingTypeRow['id'];
        
        $currentLevel = rand(0, max(0, $maxLevel - 2));
        $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $villageId, $buildingTypeId, $currentLevel);
        $stmt->execute();
        $stmt->close();
        
        // Ensure main_building exists at level 1+ (required for all buildings)
        $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = 'main_building' LIMIT 1");
        $stmt->execute();
        $mainBuildingTypeRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($mainBuildingTypeRow) {
            $mainBuildingTypeId = (int)$mainBuildingTypeRow['id'];
            $mainBuildingLevel = rand(1, 10);
            $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $villageId, $mainBuildingTypeId, $mainBuildingLevel);
            $stmt->execute();
            $stmt->close();
        }
        
        // Ensure prerequisites are met
        $prereqs = $buildingConfigManager->getBuildingRequirements($buildingInternalName);
        if (!empty($prereqs)) {
            foreach ($prereqs as $prereq) {
                $reqBuildingName = $prereq['required_building'];
                $reqLevel = (int)$prereq['required_level'];
                
                $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ? LIMIT 1");
                $stmt->bind_param("s", $reqBuildingName);
                $stmt->execute();
                $reqBuildingTypeRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($reqBuildingTypeRow) {
                    $reqBuildingTypeId = (int)$reqBuildingTypeRow['id'];
                    
                    // Check if building already exists (e.g., main_building)
                    $stmt = $conn->prepare("SELECT id FROM village_buildings WHERE village_id = ? AND building_type_id = ? LIMIT 1");
                    $stmt->bind_param("ii", $villageId, $reqBuildingTypeId);
                    $stmt->execute();
                    $existingBuilding = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$existingBuilding) {
                        $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
                        $stmt->bind_param("iii", $villageId, $reqBuildingTypeId, $reqLevel);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        // Update level if current level is lower than required
                        $stmt = $conn->prepare("UPDATE village_buildings SET level = GREATEST(level, ?) WHERE village_id = ? AND building_type_id = ?");
                        $stmt->bind_param("iii", $reqLevel, $villageId, $reqBuildingTypeId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }
        
        return [$villageId, $userId, $buildingInternalName, $currentLevel];
    },
    function($villageId, $userId, $buildingInternalName, $currentLevel) use ($conn, $queueManager, $buildingConfigManager, &$testCleanup) {
        // SECURITY: Verify ownership before operations (simulating real-world checks)
        $stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $ownerCheck = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$ownerCheck || $ownerCheck['user_id'] != $userId) {
            return "SECURITY: Ownership validation failed";
        }
        
        // Get resources before upgrade
        $stmt = $conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $resourcesBefore = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$resourcesBefore) {
            return "Failed to get village resources";
        }
        
        // Get expected upgrade cost
        $upgradeCost = $buildingConfigManager->calculateUpgradeCost($buildingInternalName, $currentLevel);
        if (!$upgradeCost) {
            return "Failed to calculate upgrade cost";
        }
        
        // Attempt to enqueue the upgrade
        $result = $queueManager->enqueueBuild($villageId, $buildingInternalName, $userId);
        
        if (!$result['success']) {
            $errorMsg = $result['message'] ?? 'unknown error';
            $errorCode = $result['error_code'] ?? 'no code';
            return "Failed to enqueue build: $errorMsg (code: $errorCode)";
        }
        
        // Track queue item for cleanup
        if (isset($result['queue_item_id'])) {
            $testCleanup['building_queue_ids'][] = $result['queue_item_id'];
        }
        
        // Get resources after upgrade
        $stmt = $conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $resourcesAfter = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Verify resources decreased by exactly the upgrade cost
        $expectedWood = $resourcesBefore['wood'] - $upgradeCost['wood'];
        $expectedClay = $resourcesBefore['clay'] - $upgradeCost['clay'];
        $expectedIron = $resourcesBefore['iron'] - $upgradeCost['iron'];
        
        if ($resourcesAfter['wood'] != $expectedWood) {
            return "Wood mismatch: expected $expectedWood, got {$resourcesAfter['wood']}";
        }
        if ($resourcesAfter['clay'] != $expectedClay) {
            return "Clay mismatch: expected $expectedClay, got {$resourcesAfter['clay']}";
        }
        if ($resourcesAfter['iron'] != $expectedIron) {
            return "Iron mismatch: expected $expectedIron, got {$resourcesAfter['iron']}";
        }
        
        // Verify queue entry was created
        if (!isset($result['queue_item_id']) || $result['queue_item_id'] <= 0) {
            return "No valid queue_item_id in result";
        }
        
        if ($result['building_internal_name'] != $buildingInternalName) {
            return "Queue entry building mismatch: expected $buildingInternalName, got {$result['building_internal_name']}";
        }
        
        if ($result['level'] != $currentLevel + 1) {
            return "Queue entry level mismatch: expected " . ($currentLevel + 1) . ", got {$result['level']}";
        }
        
        return true;
    },
    "Property 4: Building Upgrade State Transition"
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
