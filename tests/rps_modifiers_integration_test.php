<?php
/**
 * Integration test for RPS combat modifiers in BattleManager
 * Tests cavalry vs ranged, pike vs cavalry, ranger vs siege, and ranged wall bonuses
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';

class RPSModifiersIntegrationTest
{
    private $conn;
    private $battleManager;
    private $testResults = [];

    public function __construct($conn)
    {
        $this->conn = $conn;
        $villageManager = new VillageManager($conn);
        $buildingManager = new BuildingManager($conn);
        $this->battleManager = new BattleManager($conn, $villageManager, $buildingManager);
    }

    public function runAllTests()
    {
        echo "=== RPS Modifiers Integration Test ===\n\n";

        $this->testCavalryVsRangedOpenField();
        $this->testPikeVsCavalry();
        $this->testRangerVsSiege();
        $this->testRangedWallBonus();

        $this->printResults();
    }

    private function testCavalryVsRangedOpenField()
    {
        echo "Test 1: Cavalry vs Ranged in Open Field (wall = 0)\n";
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->battleManager);
        $method = $reflection->getMethod('applyRPSModifiers');
        $method->setAccessible(true);

        $attackerUnits = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'skirmisher_cavalry',
                'name' => 'Skirmisher Cavalry',
                'category' => 'cavalry',
                'attack' => 60,
                'defense' => 20,
                'count' => 10
            ]
        ];

        $defenderUnits = [
            2 => [
                'unit_type_id' => 2,
                'internal_name' => 'militia_bowman',
                'name' => 'Militia Bowman',
                'category' => 'ranged',
                'attack' => 25,
                'defense' => 10,
                'count' => 10
            ]
        ];

        $context = ['wall_level' => 0];

        $modifiers = $method->invokeArgs($this->battleManager, [&$attackerUnits, &$defenderUnits, $context]);

        // Check if cavalry attack was boosted
        $cavalryAttackBoosted = $attackerUnits[1]['attack'] > 60;
        $modifierApplied = !empty($modifiers) && isset($modifiers[0]['type']) && 
                          $modifiers[0]['type'] === 'cavalry_vs_ranged_open_field';

        $this->testResults[] = [
            'name' => 'Cavalry vs Ranged Open Field',
            'passed' => $cavalryAttackBoosted && $modifierApplied,
            'details' => sprintf(
                "Cavalry attack: %d (expected > 60), Modifier applied: %s",
                $attackerUnits[1]['attack'],
                $modifierApplied ? 'yes' : 'no'
            )
        ];

        echo $cavalryAttackBoosted && $modifierApplied ? "✓ PASS\n" : "✗ FAIL\n";
        echo "  Cavalry attack boosted from 60 to {$attackerUnits[1]['attack']}\n\n";
    }

    private function testPikeVsCavalry()
    {
        echo "Test 2: Pike vs Cavalry Defense Bonus\n";
        
        $reflection = new ReflectionClass($this->battleManager);
        $method = $reflection->getMethod('applyRPSModifiers');
        $method->setAccessible(true);

        $attackerUnits = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'skirmisher_cavalry',
                'name' => 'Skirmisher Cavalry',
                'category' => 'cavalry',
                'attack' => 60,
                'defense' => 20,
                'count' => 10
            ]
        ];

        $defenderUnits = [
            2 => [
                'unit_type_id' => 2,
                'internal_name' => 'pikeneer',
                'name' => 'Pikeneer',
                'category' => 'infantry',
                'attack' => 25,
                'defense' => 65,
                'count' => 10
            ]
        ];

        $context = ['wall_level' => 5];

        $modifiers = $method->invokeArgs($this->battleManager, [&$attackerUnits, &$defenderUnits, $context]);

        // Check if pike defense was boosted
        $pikeDefenseBoosted = $defenderUnits[2]['defense'] > 65;
        $modifierApplied = false;
        foreach ($modifiers as $mod) {
            if (isset($mod['type']) && $mod['type'] === 'pike_vs_cavalry') {
                $modifierApplied = true;
                break;
            }
        }

        $this->testResults[] = [
            'name' => 'Pike vs Cavalry',
            'passed' => $pikeDefenseBoosted && $modifierApplied,
            'details' => sprintf(
                "Pike defense: %d (expected > 65), Modifier applied: %s",
                $defenderUnits[2]['defense'],
                $modifierApplied ? 'yes' : 'no'
            )
        ];

        echo $pikeDefenseBoosted && $modifierApplied ? "✓ PASS\n" : "✗ FAIL\n";
        echo "  Pike defense boosted from 65 to {$defenderUnits[2]['defense']}\n\n";
    }

    private function testRangerVsSiege()
    {
        echo "Test 3: Ranger vs Siege Bonus\n";
        
        $reflection = new ReflectionClass($this->battleManager);
        $method = $reflection->getMethod('applyRPSModifiers');
        $method->setAccessible(true);

        $attackerUnits = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'ranger',
                'name' => 'Ranger',
                'category' => 'ranged',
                'attack' => 90,
                'defense' => 40,
                'count' => 10
            ]
        ];

        $defenderUnits = [
            2 => [
                'unit_type_id' => 2,
                'internal_name' => 'battering_ram',
                'name' => 'Battering Ram',
                'category' => 'siege',
                'attack' => 2,
                'defense' => 20,
                'count' => 5
            ]
        ];

        $context = ['wall_level' => 10];

        $modifiers = $method->invokeArgs($this->battleManager, [&$attackerUnits, &$defenderUnits, $context]);

        // Check if ranger attack was boosted against siege
        $rangerAttackBoosted = $attackerUnits[1]['attack'] > 90;
        $modifierApplied = false;
        foreach ($modifiers as $mod) {
            if (isset($mod['type']) && $mod['type'] === 'ranger_vs_siege') {
                $modifierApplied = true;
                break;
            }
        }

        $this->testResults[] = [
            'name' => 'Ranger vs Siege',
            'passed' => $rangerAttackBoosted && $modifierApplied,
            'details' => sprintf(
                "Ranger attack: %d (expected > 90), Modifier applied: %s",
                $attackerUnits[1]['attack'],
                $modifierApplied ? 'yes' : 'no'
            )
        ];

        echo $rangerAttackBoosted && $modifierApplied ? "✓ PASS\n" : "✗ FAIL\n";
        echo "  Ranger attack boosted from 90 to {$attackerUnits[1]['attack']}\n\n";
    }

    private function testRangedWallBonus()
    {
        echo "Test 4: Ranged Wall Bonus vs Infantry\n";
        
        $reflection = new ReflectionClass($this->battleManager);
        $method = $reflection->getMethod('applyRPSModifiers');
        $method->setAccessible(true);

        $attackerUnits = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'raider',
                'name' => 'Raider',
                'category' => 'infantry',
                'attack' => 60,
                'defense' => 20,
                'count' => 10
            ]
        ];

        $defenderUnits = [
            2 => [
                'unit_type_id' => 2,
                'internal_name' => 'militia_bowman',
                'name' => 'Militia Bowman',
                'category' => 'ranged',
                'attack' => 25,
                'defense' => 10,
                'count' => 10
            ]
        ];

        $context = ['wall_level' => 10];

        $modifiers = $method->invokeArgs($this->battleManager, [&$attackerUnits, &$defenderUnits, $context]);

        // Check if ranged defense was boosted with wall
        $rangedDefenseBoosted = $defenderUnits[2]['defense'] > 10;
        $modifierApplied = false;
        foreach ($modifiers as $mod) {
            if (isset($mod['type']) && $mod['type'] === 'ranged_wall_bonus_vs_infantry') {
                $modifierApplied = true;
                break;
            }
        }

        $this->testResults[] = [
            'name' => 'Ranged Wall Bonus',
            'passed' => $rangedDefenseBoosted && $modifierApplied,
            'details' => sprintf(
                "Ranged defense: %d (expected > 10), Modifier applied: %s, Wall level: %d",
                $defenderUnits[2]['defense'],
                $modifierApplied ? 'yes' : 'no',
                $context['wall_level']
            )
        ];

        echo $rangedDefenseBoosted && $modifierApplied ? "✓ PASS\n" : "✗ FAIL\n";
        echo "  Ranged defense boosted from 10 to {$defenderUnits[2]['defense']} with wall level {$context['wall_level']}\n\n";
    }

    private function printResults()
    {
        echo "\n=== Test Summary ===\n";
        $passed = 0;
        $failed = 0;

        foreach ($this->testResults as $result) {
            if ($result['passed']) {
                $passed++;
                echo "✓ {$result['name']}: PASS\n";
            } else {
                $failed++;
                echo "✗ {$result['name']}: FAIL\n";
                echo "  {$result['details']}\n";
            }
        }

        echo "\nTotal: " . count($this->testResults) . " tests\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";

        if ($failed === 0) {
            echo "\n✓ All RPS modifier tests passed!\n";
        } else {
            echo "\n✗ Some tests failed. Please review the implementation.\n";
        }
    }
}

// Run tests
try {
    $test = new RPSModifiersIntegrationTest($conn);
    $test->runAllTests();
} catch (Exception $e) {
    echo "Error running tests: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
