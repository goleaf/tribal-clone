<?php
/**
 * Integration test for isUnitAvailable() with checkRecruitRequirements()
 * 
 * Validates that feature flags properly block recruitment
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';

class UnitAvailabilityIntegrationTest
{
    private $conn;
    private $unitManager;
    private $testsPassed = 0;
    private $testsFailed = 0;

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

    private function getTestVillageId()
    {
        // Get any village from the database for testing
        $result = $this->conn->query("SELECT id FROM villages LIMIT 1");
        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['id'];
        }
        return null;
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

    public function testFeatureFlagIntegration()
    {
        echo "\n=== Testing Feature Flag Integration ===\n";
        
        $villageId = $this->getTestVillageId();
        if (!$villageId) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Test with a regular unit (should always pass feature check)
        $unitId = $this->getUnitTypeId('pikeneer');
        if ($unitId) {
            $result = $this->unitManager->checkRecruitRequirements($unitId, $villageId);
            
            // Should not fail due to feature flags
            $this->assert(
                !isset($result['code']) || $result['code'] !== 'ERR_FEATURE_DISABLED',
                "Regular unit 'pikeneer' does not fail feature flag check"
            );
        }

        // Test with conquest unit (may fail if feature disabled)
        $nobleId = $this->getUnitTypeId('noble');
        if (!$nobleId) {
            $nobleId = $this->getUnitTypeId('nobleman');
        }
        
        if ($nobleId) {
            $result = $this->unitManager->checkRecruitRequirements($nobleId, $villageId);
            
            // If it fails, it should be with proper error code
            if (!$result['can_recruit']) {
                $this->assert(
                    isset($result['code']),
                    "Failed recruitment has error code"
                );
                
                if (isset($result['code']) && $result['code'] === 'ERR_FEATURE_DISABLED') {
                    $this->assert(
                        isset($result['reason']) && $result['reason'] === 'feature_disabled',
                        "Feature disabled error has correct reason"
                    );
                }
            }
        }
    }

    public function testSeasonalWindowIntegration()
    {
        echo "\n=== Testing Seasonal Window Integration ===\n";
        
        $villageId = $this->getTestVillageId();
        if (!$villageId) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Test with seasonal unit
        $seasonalId = $this->getUnitTypeId('tempest_knight');
        if (!$seasonalId) {
            $seasonalId = $this->getUnitTypeId('event_knight');
        }
        
        if ($seasonalId) {
            $result = $this->unitManager->checkRecruitRequirements($seasonalId, $villageId);
            
            // If it fails due to seasonal window, should have proper error
            if (!$result['can_recruit'] && isset($result['code'])) {
                if ($result['code'] === 'ERR_SEASONAL_EXPIRED') {
                    $this->assert(
                        isset($result['reason']) && $result['reason'] === 'seasonal_expired',
                        "Seasonal expired error has correct reason"
                    );
                    
                    $this->assert(
                        isset($result['window_start']) && isset($result['window_end']),
                        "Seasonal expired error includes window information"
                    );
                }
            }
        } else {
            echo "⚠ SKIP: No seasonal unit found in database\n";
        }
    }

    public function testErrorCodeConsistency()
    {
        echo "\n=== Testing Error Code Consistency ===\n";
        
        $villageId = $this->getTestVillageId();
        if (!$villageId) {
            echo "⚠ SKIP: No test village available\n";
            return;
        }

        // Test that all feature-disabled errors use ERR_FEATURE_DISABLED
        $testUnits = ['noble', 'nobleman', 'war_healer', 'healer'];
        
        foreach ($testUnits as $unitInternal) {
            $unitId = $this->getUnitTypeId($unitInternal);
            if ($unitId) {
                $result = $this->unitManager->checkRecruitRequirements($unitId, $villageId);
                
                if (!$result['can_recruit'] && isset($result['reason']) && $result['reason'] === 'feature_disabled') {
                    $this->assert(
                        $result['code'] === 'ERR_FEATURE_DISABLED',
                        "Unit '$unitInternal' uses correct error code ERR_FEATURE_DISABLED"
                    );
                }
            }
        }
    }

    public function runAllTests()
    {
        echo "Starting Unit Availability Integration Tests\n";
        echo "============================================\n";
        
        $this->testFeatureFlagIntegration();
        $this->testSeasonalWindowIntegration();
        $this->testErrorCodeConsistency();
        
        echo "\n============================================\n";
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
$test = new UnitAvailabilityIntegrationTest();
exit($test->runAllTests());
