<?php
/**
 * Property-Based Test for Prerequisite Validation
 * Feature: building-queue-system, Property 25: Prerequisite Validation
 * Validates: Requirements 9.1
 * 
 * Property 25: Prerequisite Validation
 * For any building with prerequisites, attempting to queue an upgrade when 
 * prerequisites are not met should fail.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/PopulationManager.php';

// Simple property-based testing helper
class PropertyTest {
    private static $iterations = 100;
    private static $failedTests = [];
    
    public static function forAll(callable $generator, callable $property, string $testName): bool {
        echo "Running property test: $testName\n";
        
        $passed = 0;
        $failed = 0;
        
        for ($i = 0; $i < self::$iterations; $i++) {
            try {
                $testData = $generator();
                $result = $property($testData);
                
                if ($result === true) {
                    $passed++;
                } else {
                    $failed++;
                    self::$failedTests[] = [
                        'test' => $testName,
                        'iteration' => $i + 1,
                        'data' => $testData,
                        'reason' => is_string($result) ? $result : 'Property violated'
                    ];
                    
                    // Show first failure details
                    if ($failed === 1) {
                        echo "  First failure at iteration " . ($i + 1) . "\n";
                        echo "  Data: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n";
                        echo "  Reason: " . (is_string($result) ? $result : 'Property violated') . "\n";
                    }
                }
            } catch (Exception $e) {
                $failed++;
                self::$failedTests[] = [
                    'test' => $testName,
                    'iteration' => $i + 1,
                    'exception' => $e->getMessage()
                ];
                
                if ($failed === 1) {
                    echo "  Exception at iteration " . ($i + 1) . ": " . $e->getMessage() . "\n";
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

// Initialize managers
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);

echo "=== Prerequisite Validation Property Test ===\n\n";

/**
 * Property 25: Prerequisite Validation
 * 
 * For any building with prerequisites, attempting to queue an upgrade when 
 * prerequisites are not met should fail.
 * 
 * This property ensures that the validation system correctly enforces building
 * dependencies, preventing players from upgrading buildings before meeting
 * the required prerequisite levels.
 */
PropertyTest::forAll(
    // Generator: Create test scenarios with buildings that have prerequisites
    function() use ($conn, $buildingConfigManager) {
        // Get all buildings with prerequisites
        $buildingsWithPrereqs = [];
        $allBuildings = $buildingConfigManager->getAllBuildingConfigs();
        
        foreach ($allBuildings as $building) {
            $prereqs = $buildingConfigManager->getBuildingRequirements($building['internal_name']);
            if (!empty($prereqs)) {
                $buildingsWithPrereqs[] = [
                    'building' => $building,
                    'prerequisites' => $prereqs
                ];
            }
        }
        
        if (empty($buildingsWithPrereqs)) {
            // Skip if no buildings have prerequisites
            return null;
        }
        
        // Select a random building with prerequisites
        $selected = $buildingsWithPrereqs[array_rand($buildingsWithPrereqs)];
        $building = $selected['building'];
        $prerequisites = $selected['prerequisites'];
        
        // Create a test village with random resources
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $username = 'proptest_prereq_' . uniqid();
        $email = $username . '@test.com';
        $password = password_hash('test', PASSWORD_DEFAULT);
        $stmt->bind_param("sss", $username, $email, $password);
        $stmt->execute();
        $userId = $conn->insert_id;
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO villages (user_id, world_id, name, x_coord, y_coord, wood, clay, iron) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $worldId = 1;
        $villageName = 'Test Village';
        $x = rand(100, 900);
        $y = rand(100, 900);
        $wood = rand(50000, 100000); // Plenty of resources
        $clay = rand(50000, 100000);
        $iron = rand(50000, 100000);
        $stmt->bind_param("iisiiiii", $userId, $worldId, $villageName, $x, $y, $wood, $clay, $iron);
        $stmt->execute();
        $villageId = $conn->insert_id;
        $stmt->close();
        
        // Randomly decide whether to meet prerequisites or not
        $meetPrerequisites = (rand(0, 1) === 1);
        
        // Initialize all buildings at level 0, except main_building
        $result = $conn->query("SELECT id, internal_name FROM building_types");
        while ($row = $result->fetch_assoc()) {
            $buildingTypeId = $row['id'];
            
            // Set main_building level based on whether we're meeting prerequisites
            if ($row['internal_name'] === 'main_building') {
                if ($building['internal_name'] === 'main_building') {
                    // If testing main_building itself, start at 0
                    $level = 0;
                } elseif ($meetPrerequisites) {
                    // If meeting prerequisites, set main_building to a good level
                    $level = rand(5, 15);
                } else {
                    // If not meeting prerequisites, set to 0
                    $level = 0;
                }
            } else {
                $level = 0;
            }
            
            $stmt = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $villageId, $buildingTypeId, $level);
            $stmt->execute();
            $stmt->close();
        }
        
        // Set prerequisite building levels
        foreach ($prerequisites as $prereq) {
            $prereqBuildingName = $prereq['required_building'];
            $requiredLevel = (int)$prereq['required_level'];
            
            // Get building type ID
            $stmt = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ?");
            $stmt->bind_param("s", $prereqBuildingName);
            $stmt->execute();
            $prereqResult = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$prereqResult) {
                continue; // Skip if building type not found
            }
            
            $prereqTypeId = $prereqResult['id'];
            
            if ($meetPrerequisites) {
                // Set to required level or higher
                $level = rand($requiredLevel, $requiredLevel + 5);
            } else {
                // Set to below required level (but not always 0)
                $level = rand(0, max(0, $requiredLevel - 1));
            }
            
            $stmt = $conn->prepare("UPDATE village_buildings SET level = ? WHERE village_id = ? AND building_type_id = ?");
            $stmt->bind_param("iii", $level, $villageId, $prereqTypeId);
            $stmt->execute();
            $stmt->close();
        }
        
        return [
            'village_id' => $villageId,
            'user_id' => $userId,
            'building_name' => $building['internal_name'],
            'prerequisites' => $prerequisites,
            'should_meet_prereqs' => $meetPrerequisites
        ];
    },
    
    // Property: Validation should fail when prerequisites are not met
    function($testData) use ($conn, $buildingManager, $buildingConfigManager) {
        if ($testData === null) {
            return true; // Skip if no buildings with prerequisites
        }
        
        $villageId = $testData['village_id'];
        $userId = $testData['user_id'];
        $buildingName = $testData['building_name'];
        $shouldMeetPrereqs = $testData['should_meet_prereqs'];
        
        try {
            // Test the validation
            $result = $buildingManager->canUpgradeBuilding($villageId, $buildingName, $userId);
            
            // Debug: Check actual prerequisite levels
            $actualPrereqs = [];
            foreach ($testData['prerequisites'] as $prereq) {
                $prereqName = $prereq['required_building'];
                $requiredLevel = $prereq['required_level'];
                $actualLevel = $buildingManager->getBuildingLevel($villageId, $prereqName);
                $actualPrereqs[$prereqName] = [
                    'required' => $requiredLevel,
                    'actual' => $actualLevel,
                    'met' => $actualLevel >= $requiredLevel
                ];
            }
            
            // Check main_building level
            $mainLevel = $buildingManager->getBuildingLevel($villageId, 'main_building');
            
            // Verify the result matches expectations
            if ($shouldMeetPrereqs) {
                // Verify all prerequisites are actually met
                $allMet = true;
                foreach ($actualPrereqs as $name => $data) {
                    if (!$data['met']) {
                        $allMet = false;
                        break;
                    }
                }
                
                // Also check main_building for non-main_building buildings
                if ($buildingName !== 'main_building' && $mainLevel < 1) {
                    $allMet = false;
                }
                
                if (!$allMet) {
                    // Test setup error - prerequisites weren't actually met
                    return "Test setup error: prerequisites not actually met";
                }
                
                // If prerequisites are met, validation might still fail for other reasons
                // (resources, population, etc.), but it should NOT fail with ERR_PREREQ
                if (!$result['success'] && $result['code'] === 'ERR_PREREQ') {
                    return "Prerequisites were met but validation failed with ERR_PREREQ. Main level: {$mainLevel}, Message: " . ($result['message'] ?? 'none');
                }
            } else {
                // If prerequisites are NOT met, validation MUST fail with ERR_PREREQ
                if ($result['success']) {
                    return "Prerequisites were not met but validation succeeded";
                }
                if ($result['code'] !== 'ERR_PREREQ') {
                    return "Prerequisites were not met but validation failed with wrong code: " . ($result['code'] ?? 'none');
                }
            }
            
            return true;
            
        } finally {
            // Cleanup
            $stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ?");
            $stmt->bind_param("i", $villageId);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("DELETE FROM villages WHERE id = ?");
            $stmt->bind_param("i", $villageId);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }
    },
    
    "Property 25: Prerequisite Validation"
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
        if (isset($failure['reason'])) {
            echo "    Reason: {$failure['reason']}\n";
        }
        if (isset($failure['exception'])) {
            echo "    Exception: {$failure['exception']}\n";
        }
    }
    exit(1);
}
