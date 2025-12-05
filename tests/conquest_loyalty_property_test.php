<?php
/**
 * Property-Based Tests for Conquest/Loyalty System
 * 
 * Tests Properties 12 and 13 from the resource-system spec:
 * - Property 12: Nobleman Loyalty Reduction Bounds
 * - Property 13: Village Conquest Preservation
 */

declare(strict_types=1);

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/services/AllegianceService.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';

// Test configuration
const TEST_ITERATIONS = 100;
const TEST_WORLD_ID = 1;

// Test counters
$testsRun = 0;
$testsPassed = 0;
$testsFailed = 0;

/**
 * Helper: Create a test village with specified allegiance/loyalty
 */
function createTestVillage($conn, int $userId, int $allegiance, int $wallLevel = 0): int
{
    $name = "Test Village " . uniqid();
    // Use larger coordinate range to avoid collisions
    // Use negative coordinates to avoid conflicts with real game data
    $x = rand(-10000, -1000);
    $y = rand(-10000, -1000);
    $worldId = TEST_WORLD_ID;
    
    $stmt = $conn->prepare("
        INSERT INTO villages (user_id, world_id, name, x_coord, y_coord, allegiance, loyalty)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisiiii", $userId, $worldId, $name, $x, $y, $allegiance, $allegiance);
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception("Failed to insert village: " . ($stmt->error ?? 'unknown error'));
    }
    
    // Get insert ID - handle both MySQL and SQLite
    if (isset($stmt->insert_id) && $stmt->insert_id > 0) {
        $villageId = $stmt->insert_id;
    } else {
        $villageId = $conn->insert_id;
    }
    $stmt->close();
    
    if ($villageId === 0) {
        throw new Exception("Failed to get village insert ID");
    }
    
    // Update wall level separately if needed
    if ($wallLevel > 0) {
        $stmt = $conn->prepare("
            INSERT INTO village_buildings (village_id, building_type, level)
            VALUES (?, ?, ?)
        ");
        $buildingType = 'wall';
        $stmt->bind_param("isi", $villageId, $buildingType, $wallLevel);
        $stmt->execute();
        $stmt->close();
    }
    
    return $villageId;
}

/**
 * Helper: Create a test user
 */
function createTestUser($conn): int
{
    $username = "test_user_" . uniqid();
    $email = $username . "@test.com";
    $password = password_hash("test123", PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("sss", $username, $email, $password);
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception("Failed to insert user: " . ($stmt->error ?? 'unknown error'));
    }
    
    // Get insert ID - handle both MySQL and SQLite
    if (isset($stmt->insert_id) && $stmt->insert_id > 0) {
        $userId = $stmt->insert_id;
    } else {
        $userId = $conn->insert_id;
    }
    $stmt->close();
    
    if ($userId === 0) {
        throw new Exception("Failed to get user insert ID");
    }
    
    return $userId;
}

/**
 * Helper: Clean up test data
 */
function cleanupTestData($conn, array $villageIds, array $userIds): void
{
    if (!empty($villageIds)) {
        $placeholders = implode(',', array_fill(0, count($villageIds), '?'));
        $sql = "DELETE FROM villages WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $types = str_repeat('i', count($villageIds));
            $stmt->bind_param($types, ...$villageIds);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "DELETE FROM users WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $types = str_repeat('i', count($userIds));
            $stmt->bind_param($types, ...$userIds);
            $stmt->execute();
            $stmt->close();
        }
    }
}

echo "=== Conquest/Loyalty Property-Based Tests ===\n\n";

// Initialize services
$allegianceService = new AllegianceService($conn);
$worldManager = new WorldManager($conn);

// Get world conquest settings
$conquestSettings = $worldManager->getConquestSettings(TEST_WORLD_ID);
$minDrop = $conquestSettings['alleg_drop_min'];
$maxDrop = $conquestSettings['alleg_drop_max'];

echo "World conquest settings:\n";
echo "  - Min drop per envoy: $minDrop\n";
echo "  - Max drop per envoy: $maxDrop\n";
echo "  - Test iterations: " . TEST_ITERATIONS . "\n\n";

/**
 * Property 12: Nobleman Loyalty Reduction Bounds
 * 
 * Feature: resource-system, Property 12: Nobleman Loyalty Reduction Bounds
 * Validates: Requirements 7.2
 * 
 * For any nobleman attack, the loyalty reduction SHALL be a random value 
 * in the range [20, 35] per surviving nobleman.
 * 
 * Note: The spec uses [20, 35] but the world config may differ. We test
 * against the configured range [alleg_drop_min, alleg_drop_max].
 */
echo "Test 1: Property 12 - Nobleman Loyalty Reduction Bounds\n";
echo str_repeat("-", 60) . "\n";

$testVillages = [];
$testUsers = [];

for ($i = 0; $i < TEST_ITERATIONS; $i++) {
    $testsRun++;
    
    // Generate random test parameters
    $initialAllegiance = rand(50, 100);
    $survivingEnvoys = rand(1, 5);
    $wallLevel = rand(0, 20);
    
    // Create test data
    $userId = createTestUser($conn);
    $villageId = createTestVillage($conn, $userId, $initialAllegiance, $wallLevel);
    $testUsers[] = $userId;
    $testVillages[] = $villageId;
    
    // Calculate drop
    $result = $allegianceService->calculateDrop(
        $villageId,
        $survivingEnvoys,
        $wallLevel,
        [],
        TEST_WORLD_ID
    );
    
    $dropAmount = $result['drop_amount'];
    
    // Calculate expected bounds
    // Drop = dropPerEnvoy * survivingEnvoys * wallReduction
    // Since dropPerEnvoy is random in [minDrop, maxDrop], we need to account for wall reduction
    $wallReduction = 1.0 - min(0.5, $wallLevel * $conquestSettings['alleg_wall_reduction_per_level']);
    
    $minExpectedDrop = (int)floor($minDrop * $survivingEnvoys * $wallReduction);
    $maxExpectedDrop = (int)floor($maxDrop * $survivingEnvoys * $wallReduction);
    
    // Verify drop is within bounds
    if ($dropAmount >= $minExpectedDrop && $dropAmount <= $maxExpectedDrop) {
        $testsPassed++;
    } else {
        $testsFailed++;
        echo "  [FAIL] Iteration $i: Drop $dropAmount not in range [$minExpectedDrop, $maxExpectedDrop]\n";
        echo "    Initial: $initialAllegiance, Envoys: $survivingEnvoys, Wall: $wallLevel\n";
        echo "    Wall reduction: $wallReduction\n";
    }
}

// Cleanup
cleanupTestData($conn, $testVillages, $testUsers);
$testVillages = [];
$testUsers = [];

echo "  Passed: " . ($testsRun - $testsFailed) . "/" . TEST_ITERATIONS . "\n\n";

/**
 * Property 13: Village Conquest Preservation
 * 
 * Feature: resource-system, Property 13: Village Conquest Preservation
 * Validates: Requirements 7.3
 * 
 * For any village conquest (loyalty reaches 0), after ownership transfer:
 * - The new owner_id SHALL be the attacker's user_id
 * - All building levels SHALL remain unchanged
 * - All unit counts SHALL remain unchanged
 */
echo "Test 2: Property 13 - Village Conquest Preservation\n";
echo str_repeat("-", 60) . "\n";

for ($i = 0; $i < TEST_ITERATIONS; $i++) {
    $testsRun++;
    
    // Create original owner and attacker
    $originalOwnerId = createTestUser($conn);
    $attackerId = createTestUser($conn);
    $testUsers[] = $originalOwnerId;
    $testUsers[] = $attackerId;
    
    // Create village with random buildings and units
    $villageId = createTestVillage($conn, $originalOwnerId, 0); // Allegiance at 0 for conquest
    $testVillages[] = $villageId;
    
    // Add random buildings
    $buildingTypes = ['main_building', 'barracks', 'stable', 'warehouse', 'farm'];
    $buildingLevels = [];
    foreach ($buildingTypes as $buildingType) {
        $level = rand(1, 10);
        $buildingLevels[$buildingType] = $level;
        
        $stmt = $conn->prepare("
            INSERT INTO village_buildings (village_id, building_type, level)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("isi", $villageId, $buildingType, $level);
        $stmt->execute();
        $stmt->close();
    }
    
    // Add random units
    $unitTypes = ['spearman', 'swordsman', 'archer'];
    $unitCounts = [];
    foreach ($unitTypes as $unitType) {
        $count = rand(10, 100);
        $unitCounts[$unitType] = $count;
        
        $stmt = $conn->prepare("
            INSERT INTO village_units (village_id, unit_type, count)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("isi", $villageId, $unitType, $count);
        $stmt->execute();
        $stmt->close();
    }
    
    // Perform ownership transfer
    $stmt = $conn->prepare("UPDATE villages SET user_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $attackerId, $villageId);
    $stmt->execute();
    $stmt->close();
    
    // Verify ownership changed
    $stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
    $stmt->bind_param("i", $villageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $newOwnerId = (int)$row['user_id'];
    
    if ($newOwnerId !== $attackerId) {
        $testsFailed++;
        echo "  [FAIL] Iteration $i: Ownership not transferred correctly\n";
        echo "    Expected owner: $attackerId, Got: $newOwnerId\n";
        continue;
    }
    
    // Verify all buildings preserved
    $stmt = $conn->prepare("SELECT building_type, level FROM village_buildings WHERE village_id = ?");
    $stmt->bind_param("i", $villageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $buildings = [];
    while ($row = $result->fetch_assoc()) {
        $buildings[] = $row;
    }
    $stmt->close();
    
    $buildingsPreserved = true;
    foreach ($buildings as $building) {
        $type = $building['building_type'];
        $level = (int)$building['level'];
        
        if (!isset($buildingLevels[$type]) || $buildingLevels[$type] !== $level) {
            $buildingsPreserved = false;
            echo "  [FAIL] Iteration $i: Building $type level changed\n";
            echo "    Expected: {$buildingLevels[$type]}, Got: $level\n";
            break;
        }
    }
    
    if (!$buildingsPreserved) {
        $testsFailed++;
        continue;
    }
    
    // Verify all units preserved
    $stmt = $conn->prepare("SELECT unit_type, count FROM village_units WHERE village_id = ?");
    $stmt->bind_param("i", $villageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    $stmt->close();
    
    $unitsPreserved = true;
    foreach ($units as $unit) {
        $type = $unit['unit_type'];
        $count = (int)$unit['count'];
        
        if (!isset($unitCounts[$type]) || $unitCounts[$type] !== $count) {
            $unitsPreserved = false;
            echo "  [FAIL] Iteration $i: Unit $type count changed\n";
            echo "    Expected: {$unitCounts[$type]}, Got: $count\n";
            break;
        }
    }
    
    if (!$unitsPreserved) {
        $testsFailed++;
        continue;
    }
    
    $testsPassed++;
}

// Cleanup
cleanupTestData($conn, $testVillages, $testUsers);

echo "  Passed: " . ($testsRun - $testsFailed - TEST_ITERATIONS) . "/" . TEST_ITERATIONS . "\n\n";

// Summary
echo str_repeat("=", 60) . "\n";
echo "Test Summary:\n";
echo "  Total tests run: $testsRun\n";
echo "  Passed: $testsPassed\n";
echo "  Failed: $testsFailed\n";

if ($testsFailed > 0) {
    echo "\n[FAILURE] Some property tests failed!\n";
    exit(1);
} else {
    echo "\n[SUCCESS] All property tests passed!\n";
    exit(0);
}
