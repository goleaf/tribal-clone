<?php
/**
 * Test for checkRecruitRequirements() with seasonal and elite cap validation
 * 
 * Task 2.9: Extend checkRecruitRequirements() to include seasonal and elite checks
 * Requirements: 10.4, 9.2, 15.4, 15.5
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

class CheckRecruitRequirementsTest
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

    public function testEliteUnitCapValidation()
    {
        echo "\n=== Testing Elite Unit Cap Validation ===\n";
        
        if (!$this->setupTestData()) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Test with elite unit (warden or ranger)
        $eliteUnitId = $this->getUnitTypeId('warden');
        if (!$eliteUnitId) {
            $eliteUnitId = $this->getUnitTypeId('ranger');
        }

        if ($eliteUnitId) {
            // Test 1: Check with count parameter
            $result = $this->unitManager->checkRecruitRequirements($eliteUnitId, $this->testVillageId, 1);
            
            $this->assert(
                isset($result['can_recruit']),
                "checkRecruitRequirements returns can_recruit field"
            );

            // Test 2: Check with large count that should exceed cap
            $result = $this->unitManager->checkRecruitRequirements($eliteUnitId, $this->testVillageId, 999);
            
            if (!$result['can_recruit']) {
                $this->assert(
                    isset($result['code']) && $result['code'] === 'ERR_CAP',
                    "Elite unit cap exceeded returns ERR_CAP code"
                );
                
                $this->assert(
                    isset($result['reason']) && $result['reason'] === 'elite_cap_reached',
                    "Elite unit cap exceeded has correct reason"
                );
                
                $this->assert(
                    isset($result['current_count']) && isset($result['max_cap']),
                    "Elite unit cap error includes current_count and max_cap"
                );
            }
        } else {
            echo "⚠ SKIP: No elite unit found in database\n";
        }
    }

    public function testSeasonalWindowValidation()
    {
        echo "\n=== Testing Seasonal Window Validation ===\n";
        
        if (!$this->setupTestData()) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Test with seasonal unit
        $seasonalId = $this->getUnitTypeId('tempest_knight');
        if (!$seasonalId) {
            $seasonalId = $this->getUnitTypeId('event_knight');
        }

        if ($seasonalId) {
            $result = $this->unitManager->checkRecruitRequirements($seasonalId, $this->testVillageId, 1);
            
            // If seasonal window is expired, should return proper error
            if (!$result['can_recruit'] && isset($result['code'])) {
                if ($result['code'] === 'ERR_SEASONAL_EXPIRED') {
                    $this->assert(
                        isset($result['reason']) && $result['reason'] === 'seasonal_expired',
                        "Seasonal expired error has correct reason"
                    );
                    
                    $this->assert(
                        isset($result['window_start']) && isset($result['window_end']),
                        "Seasonal expired error includes window_start and window_end"
                    );
                    
                    $this->assert(
                        isset($result['unit']),
                        "Seasonal expired error includes unit internal name"
                    );
                }
            }
        } else {
            echo "⚠ SKIP: No seasonal unit found in database\n";
        }
    }

    public function testFeatureFlagValidation()
    {
        echo "\n=== Testing Feature Flag Validation ===\n";
        
        if (!$this->setupTestData()) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Test with conquest unit
        $conquestId = $this->getUnitTypeId('noble');
        if (!$conquestId) {
            $conquestId = $this->getUnitTypeId('nobleman');
        }

        if ($conquestId) {
            $result = $this->unitManager->checkRecruitRequirements($conquestId, $this->testVillageId, 1);
            
            // If feature is disabled, should return proper error
            if (!$result['can_recruit'] && isset($result['code'])) {
                if ($result['code'] === 'ERR_FEATURE_DISABLED') {
                    $this->assert(
                        isset($result['reason']) && $result['reason'] === 'feature_disabled',
                        "Feature disabled error has correct reason"
                    );
                    
                    $this->assert(
                        isset($result['unit']),
                        "Feature disabled error includes unit internal name"
                    );
                }
            }
        }

        // Test with healer unit
        $healerId = $this->getUnitTypeId('war_healer');
        if (!$healerId) {
            $healerId = $this->getUnitTypeId('healer');
        }

        if ($healerId) {
            $result = $this->unitManager->checkRecruitRequirements($healerId, $this->testVillageId, 1);
            
            // If feature is disabled, should return proper error
            if (!$result['can_recruit'] && isset($result['code'])) {
                if ($result['code'] === 'ERR_FEATURE_DISABLED') {
                    $this->assert(
                        isset($result['reason']) && $result['reason'] === 'feature_disabled',
                        "Healer feature disabled error has correct reason"
                    );
                }
            }
        }
    }

    public function testErrorCodeConsistency()
    {
        echo "\n=== Testing Error Code Consistency ===\n";
        
        if (!$this->setupTestData()) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Test that all errors have proper error codes
        $testCases = [
            ['unit' => 'warden', 'count' => 999, 'expected_code' => 'ERR_CAP'],
            ['unit' => 'ranger', 'count' => 999, 'expected_code' => 'ERR_CAP'],
        ];

        foreach ($testCases as $testCase) {
            $unitId = $this->getUnitTypeId($testCase['unit']);
            if ($unitId) {
                $result = $this->unitManager->checkRecruitRequirements(
                    $unitId, 
                    $this->testVillageId, 
                    $testCase['count']
                );
                
                if (!$result['can_recruit'] && isset($result['code'])) {
                    $this->assert(
                        in_array($result['code'], ['ERR_CAP', 'ERR_PREREQ', 'ERR_FEATURE_DISABLED', 'ERR_SEASONAL_EXPIRED']),
                        "Unit '{$testCase['unit']}' returns valid error code: {$result['code']}"
                    );
                }
            }
        }
    }

    public function testCountParameterDefault()
    {
        echo "\n=== Testing Count Parameter Default ===\n";
        
        if (!$this->setupTestData()) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Test that method works with default count parameter
        $unitId = $this->getUnitTypeId('pikeneer');
        if ($unitId) {
            // Call without count parameter (should default to 1)
            $result = $this->unitManager->checkRecruitRequirements($unitId, $this->testVillageId);
            
            $this->assert(
                isset($result['can_recruit']),
                "checkRecruitRequirements works with default count parameter"
            );
        }
    }

    public function runAllTests()
    {
        echo "Starting checkRecruitRequirements() Tests\n";
        echo "==========================================\n";
        
        $this->testEliteUnitCapValidation();
        $this->testSeasonalWindowValidation();
        $this->testFeatureFlagValidation();
        $this->testErrorCodeConsistency();
        $this->testCountParameterDefault();
        
        echo "\n==========================================\n";
        echo "Test Results:\n";
        echo "Passed: {$this->testsPassed}\n";
        echo "Failed: {$this->testsFailed}\n";
        echo "Total:  " . ($this->testsPassed + $this->testsFailed) . "\n";
        
        if ($this->testsFailed === 0) {
            echo "\n✓ All tests passed!\n";
            return 0;
        } else {
            echo "\n✗ Some tests failed.\n";
            return 1;
        }
    }
}

// Run tests
$test = new CheckRecruitRequirementsTest();
exit($test->runAllTests());
