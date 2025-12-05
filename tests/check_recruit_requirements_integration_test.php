<?php
/**
 * Integration test for checkRecruitRequirements() with count parameter
 * 
 * Task 2.9: Extend checkRecruitRequirements() to include seasonal and elite checks
 * Requirements: 10.4, 9.2, 15.4, 15.5
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

class CheckRecruitRequirementsIntegrationTest
{
    private $conn;
    private $unitManager;
    private $testsPassed = 0;
    private $testsFailed = 0;
    private $testVillageId;
    private $testUserId;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
        $this->unitManager = new UnitManager($conn);
    }

    private function assert($condition, $message)
    {
        if ($condition) {
            echo "✓ PASS: $message\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: $message\n";
            $this->testsFailed++;
        }
    }

    private function setupTestData()
    {
        // Get or create a test village
        $result = $this->conn->query("SELECT id, user_id FROM villages LIMIT 1");
        if ($result && $row = $result->fetch_assoc()) {
            $this->testVillageId = (int)$row['id'];
            $this->testUserId = (int)$row['user_id'];
            return true;
        }
        return false;
    }

    private function getUnitTypeId($internalName)
    {
        $stmt = $this->conn->prepare("SELECT id FROM unit_types WHERE internal_name = ? LIMIT 1");
        $stmt->bind_param("s", $internalName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return (int)$row['id'];
        }
        $stmt->close();
        return null;
    }

    public function testMethodSignatureWithCount()
    {
        echo "\n=== Testing Method Signature with Count Parameter ===\n";
        
        if (!$this->setupTestData()) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Get any available unit
        $unitId = $this->getUnitTypeId('axe');
        if (!$unitId) {
            $unitId = $this->getUnitTypeId('scout');
        }

        if ($unitId) {
            // Test 1: Call with count parameter
            $result = $this->unitManager->checkRecruitRequirements($unitId, $this->testVillageId, 5);
            
            $this->assert(
                isset($result['can_recruit']),
                "checkRecruitRequirements accepts count parameter and returns result"
            );

            // Test 2: Call without count parameter (default to 1)
            $result = $this->unitManager->checkRecruitRequirements($unitId, $this->testVillageId);
            
            $this->assert(
                isset($result['can_recruit']),
                "checkRecruitRequirements works with default count parameter"
            );

            // Test 3: Call with count = 1 explicitly
            $result = $this->unitManager->checkRecruitRequirements($unitId, $this->testVillageId, 1);
            
            $this->assert(
                isset($result['can_recruit']),
                "checkRecruitRequirements works with explicit count = 1"
            );
        }
    }

    public function testErrorCodeStructure()
    {
        echo "\n=== Testing Error Code Structure ===\n";
        
        if (!$this->setupTestData()) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Test with a unit that might fail prerequisites
        $unitId = $this->getUnitTypeId('noble');
        if (!$unitId) {
            $unitId = $this->getUnitTypeId('paladin');
        }

        if ($unitId) {
            $result = $this->unitManager->checkRecruitRequirements($unitId, $this->testVillageId, 1);
            
            // Check that result has proper structure
            $this->assert(
                isset($result['can_recruit']),
                "Result contains can_recruit field"
            );

            if (!$result['can_recruit']) {
                $this->assert(
                    isset($result['code']),
                    "Failed recruitment has error code"
                );

                $this->assert(
                    isset($result['reason']),
                    "Failed recruitment has reason"
                );

                // Verify error code is one of the expected values
                $validCodes = ['ERR_PREREQ', 'ERR_CAP', 'ERR_FEATURE_DISABLED', 'ERR_SEASONAL_EXPIRED', 'ERR_SERVER'];
                $this->assert(
                    in_array($result['code'], $validCodes),
                    "Error code is one of the valid codes: " . ($result['code'] ?? 'none')
                );
            }
        }
    }

    public function testSeasonalWindowCheck()
    {
        echo "\n=== Testing Seasonal Window Check Integration ===\n";
        
        if (!$this->setupTestData()) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Insert a test seasonal unit entry
        $testUnitInternal = 'test_seasonal_unit_' . time();
        $pastTimestamp = time() - 86400; // Yesterday
        
        $stmt = $this->conn->prepare("
            INSERT INTO seasonal_units (unit_internal_name, event_name, start_timestamp, end_timestamp, is_active)
            VALUES (?, 'Test Event', ?, ?, 1)
        ");
        
        if ($stmt) {
            $stmt->bind_param("sii", $testUnitInternal, $pastTimestamp, $pastTimestamp);
            $stmt->execute();
            $stmt->close();

            // Now test checkSeasonalWindow directly
            $window = $this->unitManager->checkSeasonalWindow($testUnitInternal, time());
            
            $this->assert(
                isset($window['available']),
                "checkSeasonalWindow returns available field"
            );

            $this->assert(
                $window['available'] === false,
                "Expired seasonal window returns available = false"
            );

            $this->assert(
                isset($window['start']) && isset($window['end']),
                "checkSeasonalWindow returns start and end timestamps"
            );

            // Clean up
            $this->conn->query("DELETE FROM seasonal_units WHERE unit_internal_name = '$testUnitInternal'");
        }
    }

    public function testEliteUnitCapCheck()
    {
        echo "\n=== Testing Elite Unit Cap Check Integration ===\n";
        
        if (!$this->setupTestData()) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Test checkEliteUnitCap directly with a known elite unit
        $capCheck = $this->unitManager->checkEliteUnitCap($this->testUserId, 'warden', 1);
        
        $this->assert(
            isset($capCheck['can_train']),
            "checkEliteUnitCap returns can_train field"
        );

        $this->assert(
            isset($capCheck['current']) && isset($capCheck['max']),
            "checkEliteUnitCap returns current and max fields"
        );

        // Test with excessive count
        $capCheck = $this->unitManager->checkEliteUnitCap($this->testUserId, 'warden', 999);
        
        $this->assert(
            $capCheck['can_train'] === false,
            "checkEliteUnitCap returns false for excessive count"
        );

        // Test with non-elite unit (should always return can_train = true)
        $capCheck = $this->unitManager->checkEliteUnitCap($this->testUserId, 'axe', 999);
        
        $this->assert(
            $capCheck['can_train'] === true,
            "checkEliteUnitCap returns true for non-elite units"
        );
    }

    public function runAllTests()
    {
        echo "Starting checkRecruitRequirements() Integration Tests\n";
        echo "======================================================\n";
        
        $this->testMethodSignatureWithCount();
        $this->testErrorCodeStructure();
        $this->testSeasonalWindowCheck();
        $this->testEliteUnitCapCheck();
        
        echo "\n======================================================\n";
        echo "Test Results:\n";
        echo "Passed: {$this->testsPassed}\n";
        echo "Failed: {$this->testsFailed}\n";
        echo "Total:  " . ($this->testsPassed + $this->testsFailed) . "\n";
        
        if ($this->testsFailed === 0) {
            echo "\n✓ All integration tests passed!\n";
            return 0;
        } else {
            echo "\n✗ Some integration tests failed.\n";
            return 1;
        }
    }
}

// Run tests
$test = new CheckRecruitRequirementsIntegrationTest();
exit($test->runAllTests());
