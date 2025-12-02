<?php
/**
 * Test support unit mechanics in BattleManager
 * Tests Banner Aura, Mantlet Protection, and Healer Recovery
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';

class SupportUnitMechanicsTest
{
    private $conn;
    private $battleManager;
    
    public function __construct($conn)
    {
        $this->conn = $conn;
        $villageManager = new VillageManager($conn);
        $buildingConfigManager = new BuildingConfigManager($conn);
        $buildingManager = new BuildingManager($conn, $buildingConfigManager);
        $this->battleManager = new BattleManager($conn, $villageManager, $buildingManager);
    }
    
    public function run()
    {
        echo "=== Support Unit Mechanics Test ===\n\n";
        
        $this->testBannerAuraCalculation();
        $this->testMantletProtectionCalculation();
        $this->testHealerRecoveryCalculation();
        
        echo "\n=== All Support Unit Mechanics Tests Passed ===\n";
    }
    
    private function testBannerAuraCalculation()
    {
        echo "Testing Banner Aura Calculation...\n";
        
        // Test with no banner guards
        $defenderUnits = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'pikeneer',
                'name' => 'Pikeneer',
                'category' => 'infantry',
                'count' => 100
            ]
        ];
        
        $reflection = new ReflectionClass($this->battleManager);
        $method = $reflection->getMethod('calculateBannerAura');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->battleManager, $defenderUnits);
        
        assert($result['applied'] === false, "Banner aura should not be applied without banner guards");
        assert($result['def_multiplier'] === 1.0, "Defense multiplier should be 1.0 without banner guards");
        assert($result['tier'] === 0, "Tier should be 0 without banner guards");
        
        echo "  ✓ No banner guards: aura not applied\n";
        
        // Test with banner guards
        $defenderUnitsWithBanner = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'pikeneer',
                'name' => 'Pikeneer',
                'category' => 'infantry',
                'count' => 100
            ],
            2 => [
                'unit_type_id' => 2,
                'internal_name' => 'banner_guard',
                'name' => 'Banner Guard',
                'category' => 'support',
                'count' => 5
            ]
        ];
        
        $result = $method->invoke($this->battleManager, $defenderUnitsWithBanner);
        
        assert($result['applied'] === true, "Banner aura should be applied with banner guards");
        assert($result['def_multiplier'] === 1.15, "Defense multiplier should be 1.15 with tier 1 banner");
        assert($result['tier'] === 1, "Tier should be 1 with banner guards");
        assert($result['banner_count'] === 5, "Banner count should be 5");
        
        echo "  ✓ With banner guards: aura applied correctly (1.15x defense)\n";
    }
    
    private function testMantletProtectionCalculation()
    {
        echo "Testing Mantlet Protection Calculation...\n";
        
        // Test with no mantlets
        $attackerUnits = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'battering_ram',
                'name' => 'Battering Ram',
                'category' => 'siege',
                'count' => 10
            ]
        ];
        
        $reflection = new ReflectionClass($this->battleManager);
        $method = $reflection->getMethod('calculateMantletProtection');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->battleManager, $attackerUnits);
        
        assert($result === 0.0, "Mantlet protection should be 0.0 without mantlets");
        
        echo "  ✓ No mantlets: no protection\n";
        
        // Test with mantlets and siege units
        $attackerUnitsWithMantlets = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'battering_ram',
                'name' => 'Battering Ram',
                'category' => 'siege',
                'count' => 10
            ],
            2 => [
                'unit_type_id' => 2,
                'internal_name' => 'mantlet_crew',
                'name' => 'Mantlet Crew',
                'category' => 'siege',
                'count' => 5
            ]
        ];
        
        $result = $method->invoke($this->battleManager, $attackerUnitsWithMantlets);
        
        assert($result === 0.4, "Mantlet protection should be 0.4 (40% reduction)");
        
        echo "  ✓ With mantlets: 40% ranged damage reduction\n";
        
        // Test with mantlets but no siege units
        $attackerUnitsOnlyMantlets = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'mantlet_crew',
                'name' => 'Mantlet Crew',
                'category' => 'siege',
                'count' => 5
            ]
        ];
        
        $result = $method->invoke($this->battleManager, $attackerUnitsOnlyMantlets);
        
        assert($result === 0.0, "Mantlet protection should be 0.0 without siege units to protect");
        
        echo "  ✓ Mantlets without siege: no protection (nothing to protect)\n";
    }
    
    private function testHealerRecoveryCalculation()
    {
        echo "Testing Healer Recovery Calculation...\n";
        
        // Test with no healers
        $losses = [
            1 => [
                'unit_name' => 'Pikeneer',
                'initial_count' => 100,
                'lost_count' => 50,
                'remaining_count' => 50
            ]
        ];
        
        $survivors = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'pikeneer',
                'name' => 'Pikeneer',
                'count' => 50
            ]
        ];
        
        $reflection = new ReflectionClass($this->battleManager);
        $method = $reflection->getMethod('applyHealerRecovery');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->battleManager, $losses, $survivors, 1);
        
        assert(empty($result), "No recovery should occur without healers");
        
        echo "  ✓ No healers: no recovery\n";
        
        // Test with healers
        $survivorsWithHealers = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'pikeneer',
                'name' => 'Pikeneer',
                'count' => 50
            ],
            2 => [
                'unit_type_id' => 2,
                'internal_name' => 'war_healer',
                'name' => 'War Healer',
                'count' => 2
            ]
        ];
        
        $result = $method->invoke($this->battleManager, $losses, $survivorsWithHealers, 1);
        
        assert(!empty($result), "Recovery should occur with healers");
        assert(isset($result[1]), "Recovery should be calculated for unit type 1");
        
        // With 2 healers, recovery rate = min(0.15, 0.05 * 2) = 0.10 (10%)
        // 50 lost * 0.10 = 5 recovered
        assert($result[1] === 5, "Should recover 5 units (10% of 50 losses with 2 healers)");
        
        echo "  ✓ With 2 healers: 10% recovery (5 units from 50 losses)\n";
        
        // Test with many healers (should cap at world setting)
        $survivorsWithManyHealers = [
            1 => [
                'unit_type_id' => 1,
                'internal_name' => 'pikeneer',
                'name' => 'Pikeneer',
                'count' => 50
            ],
            2 => [
                'unit_type_id' => 2,
                'internal_name' => 'war_healer',
                'name' => 'War Healer',
                'count' => 10
            ]
        ];
        
        $result = $method->invoke($this->battleManager, $losses, $survivorsWithManyHealers, 1);
        
        // With 10 healers, recovery rate = min(0.15, 0.05 * 10) = 0.15 (15% cap)
        // 50 lost * 0.15 = 7 recovered
        assert($result[1] === 7, "Should recover 7 units (15% cap of 50 losses with 10 healers)");
        
        echo "  ✓ With 10 healers: capped at 15% recovery (7 units from 50 losses)\n";
    }
}

// Run tests
try {
    $test = new SupportUnitMechanicsTest($conn);
    $test->run();
    exit(0);
} catch (Exception $e) {
    echo "Test failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
