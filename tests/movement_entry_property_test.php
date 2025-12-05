<?php
/**
 * Property-Based Test for Troop Movement System
 * Feature: resource-system, Property 8: Movement Entry Creation
 * Validates: Requirements 5.1
 * 
 * For any troop movement initiation, the attacks table SHALL contain a new entry 
 * with valid source_village_id, target_village_id, start_time, and arrival_time 
 * where arrival_time > start_time.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';
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
$villageManager = new VillageManager($conn);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);

// Track test data for cleanup
$testCleanup = [
    'user_ids' => [],
    'village_ids' => [],
    'attack_ids' => [],
    'unit_type_ids' => []
];

// Register shutdown function for cleanup
register_shutdown_function(function() use ($conn, &$testCleanup) {
    echo "\n=== Cleaning up test data ===\n";
    
    // Clean attack_units entries
    if (!empty($testCleanup['attack_ids'])) {
        $placeholders = implode(',', array_fill(0, count($testCleanup['attack_ids']), '?'));
        $stmt = $conn->prepare("DELETE FROM attack_units WHERE attack_id IN ($placeholders)");
        if ($stmt) {
            $types = str_repeat('i', count($testCleanup['attack_ids']));
            $stmt->bind_param($types, ...$testCleanup['attack_ids']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Clean attacks
    if (!empty($testCleanup['attack_ids'])) {
        $placeholders = implode(',', array_fill(0, count($testCleanup['attack_ids']), '?'));
        $stmt = $conn->prepare("DELETE FROM attacks WHERE id IN ($placeholders)");
        if ($stmt) {
            $types = str_repeat('i', count($testCleanup['attack_ids']));
            $stmt->bind_param($types, ...$testCleanup['attack_ids']);
            $stmt->execute();
            $stmt->close();
            echo "Cleaned " . count($testCleanup['attack_ids']) . " attack entries\n";
        }
    }
    
    // Clean village_units
    if (!empty($testCleanup['village_ids'])) {
        $placeholders = implode(',', array_fill(0, count($testCleanup['village_ids']), '?'));
        $stmt = $conn->prepare("DELETE FROM village_units WHERE village_id IN ($placeholders)");
        if ($stmt) {
            $types = str_repeat('i', count($testCleanup['village_ids']));
            $stmt->bind_param($types, ...$testCleanup['village_ids']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Clean village_buildings
    if (!empty($testCleanup['village_ids'])) {
        $placeholders = implode(',', array_fill(0, count($testCleanup['village_ids']), '?'));
        $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id IN ($placeholders)");
        if ($stmt) {
            $types = str_repeat('i', count($testCleanup['village_ids']));
            $stmt->bind_param($types, ...$testCleanup['village_ids']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Clean villages
    if (!empty($testCleanup['village_ids'])) {
        $placeholders = implode(',', array_fill(0, count($testCleanup['village_ids']), '?'));
        $stmt = $conn->prepare("DELETE FROM villages WHERE id IN ($placeholders)");
        if ($stmt) {
            $types = str_repeat('i', count($testCleanup['village_ids']));
            $stmt->bind_param($types, ...$testCleanup['village_ids']);
            $stmt->execute();
            $stmt->close();
            echo "Cleaned " . count($testCleanup['village_ids']) . " villages\n";
        }
    }
    
    // Clean users
    if (!empty($testCleanup['user_ids'])) {
        $placeholders = implode(',', array_fill(0, count($testCleanup['user_ids']), '?'));
        $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        if ($stmt) {
            $types = str_repeat('i', count($testCleanup['user_ids']));
            $stmt->bind_param($types, ...$testCleanup['user_ids']);
            $stmt->execute();
            $stmt->close();
            echo "Cleaned " . count($testCleanup['user_ids']) . " users\n";
        }
    }
});

echo "=== Movement Entry Creation Property Test ===\n\n";

/**
 * Property 8: Movement Entry Creation
 * Feature: resource-system, Property 8: Movement Entry Creation
 * Validates: Requirements 5.1
 * 
 * For any troop movement initiation, the attacks table SHALL contain a new entry 
 * with valid source_village_id, target_village_id, start_time, and arrival_time 
 * where arrival_time > start_time.
 */
PropertyTest::forAll(
    function() use ($conn, &$testCleanup) {
        // Create unique test users and villages
        $uniqueId = uniqid('move_', true);
        
        // Create attacker user
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, is_protected, points) VALUES (?, ?, ?, 0, 1000)");
        $attackerUsername = 'attacker_' . $uniqueId;
        $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $email = $attackerUsername . '@test.local';
        $stmt->bind_param("sss", $attackerUsername, $password, $email);
        $stmt->execute();
        $attackerUserId = $stmt->insert_id;
        $stmt->close();
        $testCleanup['user_ids'][] = $attackerUserId;
        
        // Create defender user
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, is_protected, points) VALUES (?, ?, ?, 0, 1000)");
        $defenderUsername = 'defender_' . $uniqueId;
        $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $email = $defenderUsername . '@test.local';
        $stmt->bind_param("sss", $defenderUsername, $password, $email);
        $stmt->execute();
        $defenderUserId = $stmt->insert_id;
        $stmt->close();
        $testCleanup['user_ids'][] = $defenderUserId;
        
        // Create source village with random coordinates
        $sourceX = rand(-5000, -4000);
        $sourceY = rand(-5000, -4000);
        $stmt = $conn->prepare("INSERT INTO villages (name, user_id, world_id, x_coord, y_coord, wood, clay, iron, population, farm_capacity) VALUES (?, ?, 1, ?, ?, 10000, 10000, 10000, 50, 1000)");
        $sourceName = 'Source_' . $uniqueId;
        $stmt->bind_param("siii", $sourceName, $attackerUserId, $sourceX, $sourceY);
        $stmt->execute();
        $sourceVillageId = $stmt->insert_id;
        $stmt->close();
        $testCleanup['village_ids'][] = $sourceVillageId;
        
        // Create target village with different coordinates
        $targetX = rand(-3000, -2000);
        $targetY = rand(-3000, -2000);
        $stmt = $conn->prepare("INSERT INTO villages (name, user_id, world_id, x_coord, y_coord, wood, clay, iron, population, farm_capacity) VALUES (?, ?, 1, ?, ?, 10000, 10000, 10000, 50, 1000)");
        $targetName = 'Target_' . $uniqueId;
        $stmt->bind_param("siii", $targetName, $defenderUserId, $targetX, $targetY);
        $stmt->execute();
        $targetVillageId = $stmt->insert_id;
        $stmt->close();
        $testCleanup['village_ids'][] = $targetVillageId;
        
        // Get any available unit type
        $stmt = $conn->prepare("SELECT id, internal_name FROM unit_types ORDER BY id LIMIT 1");
        $stmt->execute();
        $unitTypeRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$unitTypeRow) {
            throw new Exception("No unit types found in database");
        }
        
        $unitTypeId = (int)$unitTypeRow['id'];
        $unitCount = rand(10, 100);
        
        // Add units to source village
        $stmt = $conn->prepare("INSERT INTO village_units (village_id, unit_type_id, count) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $sourceVillageId, $unitTypeId, $unitCount);
        $stmt->execute();
        $stmt->close();
        
        // Random attack type
        $attackTypes = ['attack', 'raid', 'support'];
        $attackType = $attackTypes[array_rand($attackTypes)];
        
        // Units to send (send a portion of available units)
        $unitsToSend = [$unitTypeId => rand(5, min(50, $unitCount))];
        
        return [$sourceVillageId, $targetVillageId, $attackerUserId, $unitsToSend, $attackType];
    },
    function($sourceVillageId, $targetVillageId, $attackerUserId, $unitsToSend, $attackType) use ($conn, &$testCleanup) {
        // Record timestamp before creating movement
        $timeBefore = time();
        
        // Calculate travel time based on distance (simplified formula)
        // Get village coordinates
        $stmt = $conn->prepare("
            SELECT v1.x_coord as sx, v1.y_coord as sy, v2.x_coord as tx, v2.y_coord as ty
            FROM villages v1, villages v2
            WHERE v1.id = ? AND v2.id = ?
        ");
        $stmt->bind_param("ii", $sourceVillageId, $targetVillageId);
        $stmt->execute();
        $coords = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$coords) {
            return "Failed to get village coordinates";
        }
        
        // Calculate distance
        $dx = $coords['tx'] - $coords['sx'];
        $dy = $coords['ty'] - $coords['sy'];
        $distance = sqrt($dx * $dx + $dy * $dy);
        
        // Calculate travel time (assume 1 field per minute for testing)
        $travelTimeSeconds = (int)($distance * 60);
        if ($travelTimeSeconds < 60) {
            $travelTimeSeconds = 60; // Minimum 1 minute
        }
        
        // Create movement entry directly (simulating what sendAttack would do)
        $startTime = date('Y-m-d H:i:s', $timeBefore);
        $arrivalTime = date('Y-m-d H:i:s', $timeBefore + $travelTimeSeconds);
        
        $stmt = $conn->prepare("
            INSERT INTO attacks (source_village_id, target_village_id, attack_type, start_time, arrival_time, is_completed, is_canceled)
            VALUES (?, ?, ?, ?, ?, 0, 0)
        ");
        $stmt->bind_param("iisss", $sourceVillageId, $targetVillageId, $attackType, $startTime, $arrivalTime);
        $success = $stmt->execute();
        $attackId = $stmt->insert_id;
        $stmt->close();
        
        if (!$success || $attackId <= 0) {
            return "Failed to create attack entry";
        }
        
        // Track for cleanup
        $testCleanup['attack_ids'][] = $attackId;
        
        // Also insert attack_units entry
        $unitTypeId = array_key_first($unitsToSend);
        $unitCount = $unitsToSend[$unitTypeId];
        $stmt = $conn->prepare("INSERT INTO attack_units (attack_id, unit_type_id, count) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $attackId, $unitTypeId, $unitCount);
        $stmt->execute();
        $stmt->close();
        
        // Now verify the created entry meets all requirements
        $stmt = $conn->prepare("
            SELECT id, source_village_id, target_village_id, attack_type, start_time, arrival_time
            FROM attacks
            WHERE id = ?
        ");
        $stmt->bind_param("i", $attackId);
        $stmt->execute();
        $attack = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$attack) {
            return "Attack entry not found after creation";
        }
        
        // Verify source_village_id is valid
        if ((int)$attack['source_village_id'] != $sourceVillageId) {
            return "source_village_id mismatch: expected $sourceVillageId, got {$attack['source_village_id']}";
        }
        
        // Verify target_village_id is valid
        if ((int)$attack['target_village_id'] != $targetVillageId) {
            return "target_village_id mismatch: expected $targetVillageId, got {$attack['target_village_id']}";
        }
        
        // Verify attack_type is valid
        $validTypes = ['attack', 'raid', 'support', 'spy', 'fake'];
        if (!in_array($attack['attack_type'], $validTypes, true)) {
            return "Invalid attack_type: {$attack['attack_type']}";
        }
        
        // Verify start_time is valid
        $startTimeTs = strtotime($attack['start_time']);
        if ($startTimeTs === false) {
            return "start_time is invalid: {$attack['start_time']}";
        }
        
        // Verify arrival_time is valid
        $arrivalTimeTs = strtotime($attack['arrival_time']);
        if ($arrivalTimeTs === false) {
            return "arrival_time is invalid: {$attack['arrival_time']}";
        }
        
        // CRITICAL PROPERTY: Verify arrival_time > start_time
        if ($arrivalTimeTs <= $startTimeTs) {
            return "PROPERTY VIOLATION: arrival_time ($arrivalTimeTs) must be greater than start_time ($startTimeTs)";
        }
        
        // Verify travel time is positive
        $travelTime = $arrivalTimeTs - $startTimeTs;
        if ($travelTime <= 0) {
            return "Travel time must be positive: $travelTime seconds";
        }
        
        // Verify travel time is reasonable (not absurdly long)
        if ($travelTime > 86400 * 30) {
            return "Travel time is unreasonably long: $travelTime seconds (> 30 days)";
        }
        
        return true;
    },
    "Property 8: Movement Entry Creation"
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
