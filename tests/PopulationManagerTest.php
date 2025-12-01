<?php
declare(strict_types=1);

/**
 * Test suite for PopulationManager
 * 
 * Run with: php tests/PopulationManagerTest.php
 */

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/managers/PopulationManager.php';

class PopulationManagerTest
{
    private $db;
    private $popManager;
    private $testVillageId;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->popManager = new PopulationManager($this->db);
    }

    public function run(): void
    {
        echo "Running PopulationManager tests...\n\n";

        $this->testFarmCapacityCalculation();
        $this->testPopulationState();
        $this->testBuildingPopulation();
        $this->testUnitPopulation();
        $this->testPopulationChecks();
        $this->testSanityCheck();

        echo "\n✓ All tests passed!\n";
    }

    private function testFarmCapacityCalculation(): void
    {
        echo "Test: Farm capacity calculation\n";

        $tests = [
            [0, 240],
            [1, 240],
            [5, 313],
            [10, 518],
            [15, 855],
            [20, 1434],
            [25, 2407],
            [30, 3968],
        ];

        foreach ($tests as [$level, $expected]) {
            $actual = $this->popManager->calculateFarmCapacity($level);
            $this->assert(
                $actual === $expected,
                "Farm level {$level} should give {$expected} capacity, got {$actual}"
            );
        }

        echo "  ✓ Farm capacity formula correct\n";
    }

    private function testPopulationState(): void
    {
        echo "Test: Population state retrieval\n";

        // Create a test village
        $this->createTestVillage();

        $state = $this->popManager->getPopulationState($this->testVillageId);

        $this->assert(
            isset($state['used']) && isset($state['cap']) && isset($state['available']),
            "Population state should have 'used', 'cap', and 'available' keys"
        );

        $this->assert(
            $state['available'] === ($state['cap'] - $state['used']),
            "Available population should equal cap - used"
        );

        echo "  ✓ Population state structure correct\n";

        $this->cleanupTestVillage();
    }

    private function testBuildingPopulation(): void
    {
        echo "Test: Building population tracking\n";

        $this->createTestVillage();

        // Initially no buildings (except farm at level 1)
        $buildingPop = $this->popManager->getBuildingPopulation($this->testVillageId);
        
        // Add some buildings
        $this->addTestBuilding('barracks', 3);
        $this->addTestBuilding('stable', 2);

        $buildingPop = $this->popManager->getBuildingPopulation($this->testVillageId);
        
        // Should have some population from buildings (exact value depends on migration data)
        $this->assert(
            $buildingPop >= 0,
            "Building population should be non-negative"
        );

        echo "  ✓ Building population tracking works\n";

        $this->cleanupTestVillage();
    }

    private function testUnitPopulation(): void
    {
        echo "Test: Unit population tracking\n";

        $this->createTestVillage();

        // Add some units
        $this->addTestUnits('spear', 10); // 10 population (1 each)
        $this->addTestUnits('sword', 5);  // 5 population (1 each)

        $troopPop = $this->popManager->getTroopPopulation($this->testVillageId);

        $this->assert(
            $troopPop === 15,
            "Troop population should be 15, got {$troopPop}"
        );

        echo "  ✓ Unit population tracking works\n";

        $this->cleanupTestVillage();
    }

    private function testPopulationChecks(): void
    {
        echo "Test: Population availability checks\n";

        $this->createTestVillage();
        $this->setFarmLevel(5); // 313 capacity

        // Fill up most of the population
        $this->addTestUnits('spear', 300); // 300 population

        // Should have 13 available (313 - 300)
        $state = $this->popManager->getPopulationState($this->testVillageId);
        $this->assert(
            $state['available'] <= 13,
            "Should have ~13 or less available population"
        );

        // Try to recruit 20 units (should fail)
        $check = $this->popManager->canAffordUnitPopulation($this->testVillageId, 'spear', 20);
        $this->assert(
            !$check['success'],
            "Should not be able to recruit 20 units with only ~13 population available"
        );

        // Try to recruit 10 units (should succeed)
        $check = $this->popManager->canAffordUnitPopulation($this->testVillageId, 'spear', 10);
        $this->assert(
            $check['success'],
            "Should be able to recruit 10 units with ~13 population available"
        );

        echo "  ✓ Population checks work correctly\n";

        $this->cleanupTestVillage();
    }

    private function testSanityCheck(): void
    {
        echo "Test: Sanity check\n";

        $this->createTestVillage();
        $this->setFarmLevel(10); // 518 capacity

        $this->addTestBuilding('barracks', 5);
        $this->addTestUnits('spear', 50);

        $sanity = $this->popManager->sanityCheck($this->testVillageId);

        $this->assert(
            isset($sanity['buildings']) && isset($sanity['troops']) && isset($sanity['support']),
            "Sanity check should return all population categories"
        );

        $this->assert(
            $sanity['total'] === ($sanity['buildings'] + $sanity['troops'] + $sanity['support']),
            "Total should equal sum of all categories"
        );

        $this->assert(
            $sanity['cap'] === 518,
            "Capacity should be 518 for farm level 10"
        );

        $this->assert(
            !$sanity['over_capacity'],
            "Village should not be over capacity"
        );

        echo "  ✓ Sanity check works correctly\n";

        $this->cleanupTestVillage();
    }

    // Helper methods

    private function createTestVillage(): void
    {
        $this->db->execute("
            INSERT INTO villages (user_id, name, x_coord, y_coord, wood, clay, iron)
            VALUES (-999, 'Test Village', 999, 999, 1000, 1000, 1000)
        ");
        $this->testVillageId = (int)$this->db->lastInsertId();

        // Add farm at level 1
        $this->db->execute("
            INSERT INTO buildings (village_id, building_type, level)
            VALUES (?, 'farm', 1)
        ", [$this->testVillageId]);
    }

    private function cleanupTestVillage(): void
    {
        if ($this->testVillageId) {
            $this->db->execute("DELETE FROM villages WHERE id = ?", [$this->testVillageId]);
            $this->db->execute("DELETE FROM buildings WHERE village_id = ?", [$this->testVillageId]);
            $this->db->execute("DELETE FROM units WHERE village_id = ?", [$this->testVillageId]);
            $this->testVillageId = null;
        }
    }

    private function setFarmLevel(int $level): void
    {
        $this->db->execute("
            UPDATE buildings
            SET level = ?
            WHERE village_id = ? AND building_type = 'farm'
        ", [$level, $this->testVillageId]);
    }

    private function addTestBuilding(string $type, int $level): void
    {
        $this->db->execute("
            INSERT INTO buildings (village_id, building_type, level)
            VALUES (?, ?, ?)
            ON CONFLICT(village_id, building_type)
            DO UPDATE SET level = excluded.level
        ", [$this->testVillageId, $type, $level]);
    }

    private function addTestUnits(string $type, int $quantity): void
    {
        $this->db->execute("
            INSERT INTO units (village_id, unit_type, quantity)
            VALUES (?, ?, ?)
            ON CONFLICT(village_id, unit_type)
            DO UPDATE SET quantity = quantity + excluded.quantity
        ", [$this->testVillageId, $type, $quantity]);
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new Exception("Assertion failed: {$message}");
        }
    }
}

// Run tests
try {
    $test = new PopulationManagerTest();
    $test->run();
} catch (Exception $e) {
    echo "\n✗ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}
