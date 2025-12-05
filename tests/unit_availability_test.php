<?php
/**
 * Test for UnitManager::isUnitAvailable() method
 * 
 * Validates Requirements: 10.1, 10.2, 15.5
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';

class UnitAvailabilityTest
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

    public function testConquestUnits()
    {
        echo "\n=== Testing Conquest Units ===\n";
        
        $worldId = 1; // Assuming world 1 exists
        
        // Test noble unit
        $isAvailable = $this->unitManager->isUnitAvailable('noble', $worldId);
        $this->assert(
            is_bool($isAvailable),
            "isUnitAvailable returns boolean for conquest unit 'noble'"
        );
        
        // Test nobleman unit
        $isAvailable = $this->unitManager->isUnitAvailable('nobleman', $worldId);
        $this->assert(
            is_bool($isAvailable),
            "isUnitAvailable returns boolean for conquest unit 'nobleman'"
        );
        
        // Test standard_bearer unit
        $isAvailable = $this->unitManager->isUnitAvailable('standard_bearer', $worldId);
        $this->assert(
            is_bool($isAvailable),
            "isUnitAvailable returns boolean for conquest unit 'standard_bearer'"
        );
    }

    public function testSeasonalUnits()
    {
        echo "\n=== Testing Seasonal Units ===\n";
        
        $worldId = 1;
        
        // Test seasonal unit
        $isAvailable = $this->unitManager->isUnitAvailable('tempest_knight', $worldId);
        $this->assert(
            is_bool($isAvailable),
            "isUnitAvailable returns boolean for seasonal unit 'tempest_knight'"
        );
        
        // Test event unit
        $isAvailable = $this->unitManager->isUnitAvailable('event_knight', $worldId);
        $this->assert(
            is_bool($isAvailable),
            "isUnitAvailable returns boolean for seasonal unit 'event_knight'"
        );
    }

    public function testHealerUnits()
    {
        echo "\n=== Testing Healer Units ===\n";
        
        $worldId = 1;
        
        // Test war_healer unit
        $isAvailable = $this->unitManager->isUnitAvailable('war_healer', $worldId);
        $this->assert(
            is_bool($isAvailable),
            "isUnitAvailable returns boolean for healer unit 'war_healer'"
        );
        
        // Test healer unit
        $isAvailable = $this->unitManager->isUnitAvailable('healer', $worldId);
        $this->assert(
            is_bool($isAvailable),
            "isUnitAvailable returns boolean for healer unit 'healer'"
        );
    }

    public function testRegularUnits()
    {
        echo "\n=== Testing Regular Units ===\n";
        
        $worldId = 1;
        
        // Test regular infantry unit
        $isAvailable = $this->unitManager->isUnitAvailable('pikeneer', $worldId);
        $this->assert(
            $isAvailable === true,
            "Regular unit 'pikeneer' is available by default"
        );
        
        // Test regular cavalry unit
        $isAvailable = $this->unitManager->isUnitAvailable('skirmisher_cavalry', $worldId);
        $this->assert(
            $isAvailable === true,
            "Regular unit 'skirmisher_cavalry' is available by default"
        );
        
        // Test regular ranged unit
        $isAvailable = $this->unitManager->isUnitAvailable('militia_bowman', $worldId);
        $this->assert(
            $isAvailable === true,
            "Regular unit 'militia_bowman' is available by default"
        );
    }

    public function testCaseInsensitivity()
    {
        echo "\n=== Testing Case Insensitivity ===\n";
        
        $worldId = 1;
        
        // Test with uppercase
        $upper = $this->unitManager->isUnitAvailable('NOBLE', $worldId);
        $lower = $this->unitManager->isUnitAvailable('noble', $worldId);
        $mixed = $this->unitManager->isUnitAvailable('NoBLe', $worldId);
        
        $this->assert(
            $upper === $lower && $lower === $mixed,
            "Unit availability is case-insensitive"
        );
    }

    public function testWhitespaceHandling()
    {
        echo "\n=== Testing Whitespace Handling ===\n";
        
        $worldId = 1;
        
        // Test with whitespace
        $trimmed = $this->unitManager->isUnitAvailable('noble', $worldId);
        $withSpaces = $this->unitManager->isUnitAvailable('  noble  ', $worldId);
        
        $this->assert(
            $trimmed === $withSpaces,
            "Unit availability handles whitespace correctly"
        );
    }

    public function runAllTests()
    {
        echo "Starting Unit Availability Tests\n";
        echo "================================\n";
        
        $this->testConquestUnits();
        $this->testSeasonalUnits();
        $this->testHealerUnits();
        $this->testRegularUnits();
        $this->testCaseInsensitivity();
        $this->testWhitespaceHandling();
        
        echo "\n================================\n";
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
$test = new UnitAvailabilityTest();
exit($test->runAllTests());
