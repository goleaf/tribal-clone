<?php
/**
 * Property-style tests for ModifierApplier.
 * Each property runs 100 iterations as per requirements doc.
 */

require_once __DIR__ . '/../lib/managers/ModifierApplier.php';

$applier = new ModifierApplier();

/** Feature: battle-resolution, Property 10: Morale Calculation */
for ($i = 0; $i < 100; $i++) {
    $attackerPoints = max(1, mt_rand(1, 1_000_000));
    $defenderPoints = mt_rand(0, 1_000_000);
    $morale = $applier->calculateMorale($defenderPoints, $attackerPoints);

    $expected = 0.3 + ($defenderPoints / $attackerPoints);
    $expected = max(0.5, min(1.5, $expected));
    assert(abs($morale - $expected) < 1e-6, "Morale mismatch");
    assert($morale >= 0.5 && $morale <= 1.5, "Morale out of bounds");
}

/** Feature: battle-resolution, Property 11: Luck Bounds */
for ($i = 0; $i < 100; $i++) {
    $min = mt_rand(50, 90) / 100;
    $max = mt_rand(110, 150) / 100;
    if ($min > $max) {
        [$min, $max] = [$max, $min];
    }
    $luck = $applier->generateLuck(['luck_min' => $min, 'luck_max' => $max]);
    assert($luck >= $min - 1e-9 && $luck <= $max + 1e-9, "Luck out of bounds");
}

/** Feature: battle-resolution, Property 12: Modifier Application Order */
for ($i = 0; $i < 100; $i++) {
    $baseOff = 1000;
    $baseDef = 800;
    $wallLevel = 5;
    $defPop = 40_000;
    $attPts = 10_000;
    $defPts = 8_000;
    $world = [
        'overstack_enabled' => true,
        'overstack_threshold' => 30_000,
        'overstack_penalty_rate' => 0.2,
        'overstack_min_multiplier' => 0.6,
        'night_bonus_enabled' => true,
        'night_start_hour' => 0,
        'night_end_hour' => 23,
        'night_defense_multiplier' => 1.4,
        'terrain_enabled' => true,
        'terrain_attack_multiplier' => 1.1,
        'terrain_defense_multiplier' => 1.2,
        'weather_enabled' => true,
        'weather_attack_multiplier' => 0.9,
        'weather_defense_multiplier' => 1.05,
        'luck_min' => 1.0,
        'luck_max' => 1.0,
    ];
    $battleTime = new DateTime('2023-01-01 12:00:00');
    $result = $applier->applyAllModifiers(
        $baseOff,
        $baseDef,
        $wallLevel,
        $defPop,
        $defPts,
        $attPts,
        $world,
        $battleTime
    );

    $overstack = $applier->calculateOverstackPenalty($defPop, $world);
    $expectedDef = $baseDef * $overstack;
    $expectedDef *= $applier->calculateWallMultiplier($wallLevel);
    $expectedOff = $baseOff;

    $env = $applier->applyEnvironmentModifiers($expectedOff, $expectedDef, $world, $battleTime);
    $expectedOff = $env['offense'];
    $expectedDef = $env['defense'];

    $morale = $applier->calculateMorale($defPts, $attPts);
    $expectedOff *= $morale;

    $luck = 1.0; // locked by config
    $expectedOff *= $luck;

    assert(abs($result['offense'] - $expectedOff) < 1e-6, "Offense order mismatch");
    assert(abs($result['defense'] - $expectedDef) < 1e-6, "Defense order mismatch");
}

/** Feature: battle-resolution, Property 13: Night Bonus Application */
for ($i = 0; $i < 100; $i++) {
    $world = [
        'night_bonus_enabled' => true,
        'night_start_hour' => 22,
        'night_end_hour' => 6,
        'night_defense_multiplier' => 1.5,
    ];
    $time = new DateTime('2023-01-01 23:00:00');
    $env = $applier->applyEnvironmentModifiers(100, 200, $world, $time);
    assert(abs($env['defense'] - 300.0) < 1e-6, "Night bonus not applied");
}

/** Feature: battle-resolution, Property 17: Overstack Penalty Formula */
for ($i = 0; $i < 100; $i++) {
    $threshold = mt_rand(10_000, 50_000);
    $pop = $threshold + mt_rand(0, 50_000);
    $penaltyRate = mt_rand(5, 30) / 100;
    $minMult = mt_rand(30, 80) / 100;
    $world = [
        'overstack_enabled' => true,
        'overstack_threshold' => $threshold,
        'overstack_penalty_rate' => $penaltyRate,
        'overstack_min_multiplier' => $minMult,
    ];
    $penalty = $applier->calculateOverstackPenalty($pop, $world);
    $excessRatio = max(0, ($pop - $threshold) / $threshold);
    $expected = max($minMult, 1.0 - ($penaltyRate * $excessRatio));
    assert(abs($penalty - $expected) < 1e-6, "Overstack penalty mismatch");
    assert($penalty <= 1.0 + 1e-9, "Overstack penalty should not amplify");
}

/** Feature: battle-resolution, Property 18: Overstack Modifier Ordering */
for ($i = 0; $i < 100; $i++) {
    $world = [
        'overstack_enabled' => true,
        'overstack_threshold' => 10_000,
        'overstack_penalty_rate' => 0.5,
        'overstack_min_multiplier' => 0.5,
        'luck_min' => 1.0,
        'luck_max' => 1.0,
    ];
    $res = $applier->applyAllModifiers(
        1000,
        1000,
        0,
        20_000,
        10_000,
        10_000,
        $world,
        new DateTime('2023-01-01 12:00:00')
    );
    $overstackMult = $applier->calculateOverstackPenalty(20_000, $world);
    assert(abs($res['modifiers']['overstack_penalty'] - $overstackMult) < 1e-9, "Overstack modifier missing");
    // Wall is zero, so defense should be base * overstack.
    assert(abs($res['defense'] - (1000 * $overstackMult)) < 1e-6, "Overstack should precede other defense modifiers");
}

echo "ModifierApplier property tests passed.\n";
