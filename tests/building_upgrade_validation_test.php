<?php
/**
 * Integration test for BuildingManager::canUpgradeBuilding validation chain
 * Tests the complete validation flow as per task 2.1
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/PopulationManager.php';

class BuildingUpgradeValidationTest {
    private $db;
    private $buildingManager;
    private $testVillageId;
    private $testUserId;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
        
        $configManager = new BuildingConfigManager($this->db);
        $this->buildingManager = new BuildingManager($this->db, $configManager);
    }
    
    public function run() {
        echo "Running Building Upgrade Validation Tests...\n\n";
        
        $this->setupTestData();
        
        $passed = 0;
        $failed = 0;
        
        // Test 1: Invalid building ID
        if ($this->testInvalidBuildingId()) {
            echo "✓ Test 1: Invalid building ID validation\n";
            $passed++;
        } else {
            echo "✗ Test 1: Invalid building ID validation FAILED\n";
            $failed++;
        }
        
        // Test 2: Maximum level cap
        if ($this->testMaxLevelCap()) {
            echo "✓ Test 2: Maximum level cap enforcement\n";
            $passed++;
        } else {
            echo "✗ Test 2: Maximum level cap enforcement FAILED\n";
            $failed++;
        }
        
        // Test 3: Insufficient resources
        if ($this->testInsufficientResources()) {
            echo "✓ Test 3: Insufficient resources validation\n";
            $passed++;
        } else {
            echo "✗ Test 3: Insufficient resources validation FAILED\n";
            $failed++;
        }
        
        // Test 4: Missing prerequisites
        if ($this->testMissingPrerequisites()) {
            echo "✓ Test 4: Missing prerequisites validation\n";
            $passed++;
        } else {
            echo "✗ Test 4: Missing prerequisites validation FAILED\n";
            $failed++;
        }
        
        // Test 5: Valid upgrade
        if ($this->testValidUpgrade()) {
            echo "✓ Test 5: Valid upgrade passes all checks\n";
            $passed++;
        } else {
            echo "✗ Test 5: Valid upgrade passes all checks FAILED\n";
            $failed++;
        }
        
        $this->cleanupTestData();
        
        echo "\n";
        echo "Results: {$passed} passed, {$failed} failed\n";
        
        return $failed === 0;
    }
    
    private function setupTestData() {
        // Create test user
        $stmt = $this->db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $username = 'test_user_' . time();
        $email = $username . '@test.com';
        $password = password_hash('test', PASSWORD_DEFAULT);
        $stmt->bind_param("sss", $username, $email, $password);
        $stmt->execute();
        $this->testUserId = $this->db->insert_id;
        $stmt->close();
        
        // Create test village
        $worldId = 1; // Default world
        $stmt = $this->db->prepare("INSERT INTO villages (user_id, world_id, name, x, y, wood, clay, iron) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $villageName = 'Test Village';
        $x = 500;
        $y = 500;
        $wood = 10000;
        $clay = 10000;
        $iron = 10000;
        $stmt->bind_param("iisiiiii", $this->testUserId, $worldId, $villageName, $x, $y, $wood, $clay, $iron);
        $stmt->execute();
        $this->testVillageId = $this->db->insert_id;
        $stmt->close();
        
        // Initialize village buildings - use a simpler approach
        $result = $this->db->query("SELECT id, internal_name FROM building_types");
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $buildingTypeId = $row['id'];
            $level = ($row['internal_name'] === 'main_building') ? 10 : 0;
            
            $stmt = $this->db->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $this->testVillageId, $buildingTypeId, $level);
            if (!$stmt->execute()) {
                echo "Setup ERROR: Failed to insert building {$row['internal_name']}: " . $stmt->error . "\n";
            }
            $stmt->close();
            $count++;
        }
        
        echo "Setup: Created $count building entries for village {$this->testVillageId}\n";
        
        // Verify the setup
        $verifyStmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM village_buildings WHERE village_id = ?");
        $verifyStmt->bind_param("i", $this->testVillageId);
        $verifyStmt->execute();
        $actualCount = $verifyStmt->get_result()->fetch_assoc()['cnt'];
        $verifyStmt->close();
        echo "Setup: Verified $actualCount rows in database\n";
    }
    
    private function cleanupTestData() {
        if ($this->testVillageId) {
            $stmt = $this->db->prepare("DELETE FROM village_buildings WHERE village_id = ?");
            $stmt->bind_param("i", $this->testVillageId);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $this->db->prepare("DELETE FROM villages WHERE id = ?");
            $stmt->bind_param("i", $this->testVillageId);
            $stmt->execute();
            $stmt->close();
        }
        
        if ($this->testUserId) {
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $this->testUserId);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    private function testInvalidBuildingId() {
        $result = $this->buildingManager->canUpgradeBuilding(
            $this->testVillageId, 
            'nonexistent_building',
            $this->testUserId
        );
        
        return !$result['success'] && $result['code'] === 'ERR_INPUT';
    }
    
    private function testMaxLevelCap() {
        // Get building type IDs
        $mainBuildingTypeId = $this->db->query("SELECT id FROM building_types WHERE internal_name = 'main_building'")->fetch_assoc()['id'];
        $barracksTypeId = $this->db->query("SELECT id FROM building_types WHERE internal_name = 'barracks'")->fetch_assoc()['id'];
        $barracksMaxLevel = $this->db->query("SELECT max_level FROM building_types WHERE internal_name = 'barracks'")->fetch_assoc()['max_level'];
        
        // Check if rows exist
        $checkStmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM village_buildings WHERE village_id = ? AND building_type_id = ?");
        $checkStmt->bind_param("ii", $this->testVillageId, $mainBuildingTypeId);
        $checkStmt->execute();
        $count = $checkStmt->get_result()->fetch_assoc()['cnt'];
        $checkStmt->close();
        
        if ($count == 0) {
            echo "  Debug: No village_buildings row found for village {$this->testVillageId} and building_type_id {$mainBuildingTypeId}\n";
            return false;
        }
        
        // Ensure main_building is at sufficient level
        $stmt = $this->db->prepare("UPDATE village_buildings SET level = 10 WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $this->testVillageId, $mainBuildingTypeId);
        $stmt->execute();
        $affected1 = $stmt->affected_rows;
        $stmt->close();
        
        // Set barracks to max level
        $stmt = $this->db->prepare("UPDATE village_buildings SET level = ? WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("iii", $barracksMaxLevel, $this->testVillageId, $barracksTypeId);
        $stmt->execute();
        $affected2 = $stmt->affected_rows;
        $stmt->close();
        
        // Verify levels
        $mainLevel = $this->buildingManager->getBuildingLevel($this->testVillageId, 'main_building');
        $barracksLevel = $this->buildingManager->getBuildingLevel($this->testVillageId, 'barracks');
        
        if ($mainLevel != 10 || $barracksLevel != $barracksMaxLevel) {
            echo "  Debug: main_building level: $mainLevel (expected 10, affected: $affected1), barracks level: $barracksLevel (expected $barracksMaxLevel, affected: $affected2)\n";
        }
        
        $result = $this->buildingManager->canUpgradeBuilding(
            $this->testVillageId, 
            'barracks',
            $this->testUserId
        );
        
        if (!(!$result['success'] && $result['code'] === 'ERR_CAP')) {
            echo "  Debug: Expected ERR_CAP, got: " . json_encode($result) . "\n";
        }
        
        return !$result['success'] && $result['code'] === 'ERR_CAP';
    }
    
    private function testInsufficientResources() {
        // Get building type IDs
        $mainBuildingTypeId = $this->db->query("SELECT id FROM building_types WHERE internal_name = 'main_building'")->fetch_assoc()['id'];
        $barracksTypeId = $this->db->query("SELECT id FROM building_types WHERE internal_name = 'barracks'")->fetch_assoc()['id'];
        
        // Ensure main_building is at sufficient level
        $stmt = $this->db->prepare("UPDATE village_buildings SET level = 10 WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $this->testVillageId, $mainBuildingTypeId);
        $stmt->execute();
        $stmt->close();
        
        // Set barracks to level 0
        $stmt = $this->db->prepare("UPDATE village_buildings SET level = 0 WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $this->testVillageId, $barracksTypeId);
        $stmt->execute();
        $stmt->close();
        
        // Set village resources to 0
        $stmt = $this->db->prepare("UPDATE villages SET wood = 0, clay = 0, iron = 0 WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $stmt->close();
        
        $result = $this->buildingManager->canUpgradeBuilding(
            $this->testVillageId, 
            'barracks',
            $this->testUserId
        );
        
        // Restore resources
        $stmt = $this->db->prepare("UPDATE villages SET wood = 10000, clay = 10000, iron = 10000 WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $stmt->close();
        
        if (!(!$result['success'] && $result['code'] === 'ERR_RES')) {
            echo "  Debug: Expected ERR_RES, got: " . json_encode($result) . "\n";
        }
        
        return !$result['success'] && $result['code'] === 'ERR_RES';
    }
    
    private function testMissingPrerequisites() {
        // Get building type ID
        $mainBuildingTypeId = $this->db->query("SELECT id FROM building_types WHERE internal_name = 'main_building'")->fetch_assoc()['id'];
        
        // Try to upgrade stable without meeting main_building requirement
        $stmt = $this->db->prepare("UPDATE village_buildings SET level = 0 WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $this->testVillageId, $mainBuildingTypeId);
        $stmt->execute();
        $stmt->close();
        
        $result = $this->buildingManager->canUpgradeBuilding(
            $this->testVillageId, 
            'stable',
            $this->testUserId
        );
        
        // Restore main_building level
        $stmt = $this->db->prepare("UPDATE village_buildings SET level = 10 WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $this->testVillageId, $mainBuildingTypeId);
        $stmt->execute();
        $stmt->close();
        
        return !$result['success'] && $result['code'] === 'ERR_PREREQ';
    }
    
    private function testValidUpgrade() {
        // Get building type IDs
        $mainBuildingTypeId = $this->db->query("SELECT id FROM building_types WHERE internal_name = 'main_building'")->fetch_assoc()['id'];
        $barracksTypeId = $this->db->query("SELECT id FROM building_types WHERE internal_name = 'barracks'")->fetch_assoc()['id'];
        
        // Ensure main_building is at level 10 (meets barracks requirement of level 3)
        $stmt = $this->db->prepare("UPDATE village_buildings SET level = 10 WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $this->testVillageId, $mainBuildingTypeId);
        $stmt->execute();
        $stmt->close();
        
        // Ensure barracks is at level 0
        $stmt = $this->db->prepare("UPDATE village_buildings SET level = 0 WHERE village_id = ? AND building_type_id = ?");
        $stmt->bind_param("ii", $this->testVillageId, $barracksTypeId);
        $stmt->execute();
        $stmt->close();
        
        // Ensure resources are sufficient
        $stmt = $this->db->prepare("UPDATE villages SET wood = 10000, clay = 10000, iron = 10000 WHERE id = ?");
        $stmt->bind_param("i", $this->testVillageId);
        $stmt->execute();
        $stmt->close();
        
        $result = $this->buildingManager->canUpgradeBuilding(
            $this->testVillageId, 
            'barracks',
            $this->testUserId
        );
        
        if ($result['success'] !== true) {
            echo "  Debug: Expected success, got: " . json_encode($result) . "\n";
        }
        
        return $result['success'] === true;
    }
}

// Run the test
$test = new BuildingUpgradeValidationTest();
$success = $test->run();

exit($success ? 0 : 1);
