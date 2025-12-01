<?php
/**
 * Property-style tests for CombatCalculator.
 */

require_once __DIR__ . '/../lib/managers/CombatCalculator.php';

$unitDataPath = __DIR__ . '/../data/units.json';
$unitData = json_decode(file_get_contents($unitDataPath), true);

// Reduce to the units we actually reference.
$subset = [
    'spear' => $unitData['spear'],
    'sword' => $unitData['sword'],
    'axe' => $unitData['axe'],
    'light' => $unitData['light'],
    'archer' => $unitData['archer'],
];

$calc = new CombatCalculator($subset);

/** Feature: battle-resolution, Property 1: Force Merging Completeness */
for ($i = 0; $i < 100; $i++) {
    $garrison = ['spear' => mt_rand(0, 500), 'axe' => mt_rand(0, 500)];
    $support = ['spear' => mt_rand(0, 500), 'light' => mt_rand(0, 200)];
    $merged = $calc->mergeDefendingForces($garrison, $support);
    assert($merged['spear'] === ($garrison['spear'] + $support['spear']), "Spears not merged correctly");
    assert($merged['axe'] === ($garrison['axe']), "Axes changed unexpectedly");
    assert($merged['light'] === ($support['light']), "Light cav missing");
}

/** Feature: battle-resolution, Property 2: Power Calculation Correctness */
for ($i = 0; $i < 100; $i++) {
    $att = ['axe' => mt_rand(0, 200), 'light' => mt_rand(0, 200)];
    $def = ['spear' => mt_rand(0, 400), 'sword' => mt_rand(0, 300), 'archer' => mt_rand(0, 300)];
    $defShares = $calc->getClassShares($def);
    $off = $calc->calculateOffensivePower($att, $defShares);
    $defPow = $calc->calculateDefensivePower($def, $att);
    assert($off >= 0 && $defPow >= 0, "Power should be non-negative");
}

/** Feature: battle-resolution, Property 3: Casualty Proportionality */
for ($i = 0; $i < 100; $i++) {
    $att = ['axe' => 100];
    $def = ['spear' => 100];
    $off = $calc->calculateOffensivePower($att, $calc->getClassShares($def));
    $defPow = $calc->calculateDefensivePower($def, $att);
    $ratio = $off / max(1e-6, $defPow);
    $res = $calc->calculateCasualties($ratio, $att, $def);

    if ($ratio >= 1) {
        $expectedLossFactor = 1 / pow($ratio, 1.5);
        $expectedSurvivors = max(0, $att['axe'] - (int)ceil($att['axe'] * $expectedLossFactor));
        assert($res['attacker_survivors']['axe'] === $expectedSurvivors, "Attacker casualties mismatch");
    } else {
        $expectedLossFactor = pow($ratio, 1.5);
        $expectedDefSurvivors = max(0, $def['spear'] - (int)ceil($def['spear'] * $expectedLossFactor));
        assert($res['defender_survivors']['spear'] === $expectedDefSurvivors, "Defender casualties mismatch");
    }
}

/** Feature: battle-resolution, Property 4: Unit Conservation */
for ($i = 0; $i < 100; $i++) {
    $att = ['axe' => mt_rand(0, 300), 'light' => mt_rand(0, 150)];
    $def = ['spear' => mt_rand(0, 300), 'sword' => mt_rand(0, 200)];
    $off = $calc->calculateOffensivePower($att, $calc->getClassShares($def));
    $defPow = $calc->calculateDefensivePower($def, $att);
    $ratio = $off / max(1e-6, $defPow);
    $res = $calc->calculateCasualties($ratio, $att, $def);

    foreach ($att as $unit => $count) {
        $lost = $res['attacker_losses'][$unit];
        $surv = $res['attacker_survivors'][$unit];
        assert($count === ($lost + $surv), "Attacker conservation failed for {$unit}");
    }
    foreach ($def as $unit => $count) {
        $lost = $res['defender_losses'][$unit];
        $surv = $res['defender_survivors'][$unit];
        assert($count === ($lost + $surv), "Defender conservation failed for {$unit}");
    }
}

echo "CombatCalculator property tests passed.\n";
