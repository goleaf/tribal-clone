<?php
/**
 * Property-style tests for PlunderCalculator.
 */

require_once __DIR__ . '/../lib/managers/PlunderCalculator.php';

$unitDataPath = __DIR__ . '/../data/units.json';
$unitData = json_decode(file_get_contents($unitDataPath), true);
$calc = new PlunderCalculator($unitData);

echo "Running PlunderCalculator property tests...\n";

/** Feature: battle-resolution, Property 19: Vault Protection */
for ($i = 0; $i < 100; $i++) {
    $resources = [
        'wood' => mt_rand(0, 50000),
        'clay' => mt_rand(0, 50000),
        'iron' => mt_rand(0, 50000)
    ];
    $hidden = mt_rand(0, 5000);
    $vaultPercent = mt_rand(0, 100);

    $result = $calc->calculateAvailableLoot($resources, $hidden, $vaultPercent);

    foreach (['wood', 'clay', 'iron'] as $res) {
        $vaultProtected = (int)ceil($resources[$res] * ($vaultPercent / 100));
        $protected = max($hidden, $vaultProtected);
        $expectedAvailable = max(0, $resources[$res] - $protected);

        assert($result['protected'][$res] === $protected, "Protected mismatch for {$res}");
        assert($result['available'][$res] === $expectedAvailable, "Available mismatch for {$res}");
        assert($result['lootable'][$res] <= $result['available'][$res], "Lootable should not exceed available for {$res}");
    }
}
echo "✓ Property 19 passed\n";

/** Feature: battle-resolution, Property 20: Carry Capacity Limit */
for ($i = 0; $i < 100; $i++) {
    $lootable = [
        'wood' => mt_rand(0, 20000),
        'clay' => mt_rand(0, 20000),
        'iron' => mt_rand(0, 20000)
    ];
    $capacity = mt_rand(0, 50000);

    $distribution = $calc->distributePlunder($lootable, $capacity);
    $expectedTotal = min($capacity, array_sum($lootable));
    $actualTotal = array_sum($distribution['loot']);

    assert($actualTotal === $expectedTotal, "Carry capacity limit violated (expected {$expectedTotal}, got {$actualTotal})");
    foreach (['wood', 'clay', 'iron'] as $res) {
        assert($distribution['loot'][$res] <= $lootable[$res], "Distributed {$res} exceeds lootable");
    }
}
echo "✓ Property 20 passed\n";

/** Feature: battle-resolution, Property 21: Plunder Determinism */
for ($i = 0; $i < 100; $i++) {
    $lootable = [
        'wood' => mt_rand(0, 15000),
        'clay' => mt_rand(0, 15000),
        'iron' => mt_rand(0, 15000)
    ];
    $capacity = mt_rand(0, 30000);

    $first = $calc->distributePlunder($lootable, $capacity);
    $second = $calc->distributePlunder($lootable, $capacity);
    assert($first === $second, "DistributePlunder not deterministic on iteration {$i}");
}
echo "✓ Property 21 passed\n";

/** Feature: battle-resolution, Property 22: Siege Unit Carry Capacity */
// Include siege/conquest units with some plunder-capable units to verify exclusions.
$sampleUnits = [
    'ram' => 10,
    'catapult' => 5,
    'noble' => 2,
    'axe' => 100,
    'light' => 30,
    'paladin' => 1
];
$carry = $calc->calculateCarryCapacity($sampleUnits);
$expectedCarry = ($unitData['axe']['carry'] * 100)
    + ($unitData['light']['carry'] * 30)
    + ($unitData['paladin']['carry'] * 1);

assert($carry === $expectedCarry, "Siege/conquest units should not contribute carry");
echo "✓ Property 22 passed\n";

echo "All PlunderCalculator property tests passed.\n";
