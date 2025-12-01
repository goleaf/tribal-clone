<?php
/**
 * Quick verification test for ModifierApplier and CombatCalculator components
 */

require_once __DIR__ . '/../lib/managers/ModifierApplier.php';
require_once __DIR__ . '/../lib/managers/CombatCalculator.php';

echo "Testing ModifierApplier...\n";

$modifierApplier = new ModifierApplier();

// Test morale calculation
$morale = $modifierApplier->calculateMorale(10000, 20000);
echo "Morale (10k vs 20k): " . $morale . " (expected ~0.8)\n";
assert($morale >= 0.5 && $morale <= 1.5, "Morale out of bounds");

// Test luck generation
$luck = $modifierApplier->generateLuck(['luck_min' => 0.75, 'luck_max' => 1.25]);
echo "Luck: " . $luck . " (expected 0.75-1.25)\n";
assert($luck >= 0.75 && $luck <= 1.25, "Luck out of bounds");

// Test wall multiplier
$wallMult = $modifierApplier->calculateWallMultiplier(10);
echo "Wall multiplier (level 10): " . $wallMult . " (expected ~1.44)\n";
assert($wallMult > 1.0, "Wall multiplier should be > 1.0");

$wallMult20 = $modifierApplier->calculateWallMultiplier(20);
echo "Wall multiplier (level 20): " . $wallMult20 . " (expected ~2.34)\n";
assert($wallMult20 > $wallMult, "Level 20 wall should be stronger than level 10");

// Test overstack penalty
$overstackPenalty = $modifierApplier->calculateOverstackPenalty(40000, [
    'overstack_enabled' => true,
    'overstack_threshold' => 30000,
    'overstack_penalty_rate' => 0.3,
    'overstack_min_multiplier' => 0.5
]);
echo "Overstack penalty (40k pop, 30k threshold): " . $overstackPenalty . " (expected ~0.9)\n";
assert($overstackPenalty < 1.0, "Overstack should reduce defense");

// Test environment modifiers
$envResult = $modifierApplier->applyEnvironmentModifiers(
    1000, // offense
    1000, // defense
    [
        'night_bonus_enabled' => true,
        'night_start_hour' => 0,
        'night_end_hour' => 23, // Always night for test
        'night_defense_multiplier' => 1.5
    ],
    new DateTime()
);
echo "Night bonus applied - Defense: " . $envResult['defense'] . " (expected 1500)\n";
assert($envResult['defense'] == 1500, "Night bonus should multiply defense by 1.5");

echo "\nTesting CombatCalculator...\n";

// Load unit data
$unitData = [
    'axe' => ['off' => 40, 'def' => ['gen' => 10, 'cav' => 5, 'arc' => 10]],
    'spear' => ['off' => 10, 'def' => ['gen' => 15, 'cav' => 45, 'arc' => 20]],
    'light' => ['off' => 130, 'def' => ['gen' => 30, 'cav' => 40, 'arc' => 30]],
];

$combatCalc = new CombatCalculator($unitData);

// Test offensive power calculation
$attackerUnits = ['axe' => 100, 'light' => 50];
$defenderShares = ['infantry' => 0.5, 'cavalry' => 0.3, 'archer' => 0.2];
$offPower = $combatCalc->calculateOffensivePower($attackerUnits, $defenderShares);
echo "Offensive power (100 axe, 50 light): " . $offPower . "\n";
assert($offPower > 0, "Offensive power should be positive");

// Test defensive power calculation
$defenderUnits = ['spear' => 100];
$defPower = $combatCalc->calculateDefensivePower($defenderUnits, $attackerUnits);
echo "Defensive power (100 spear): " . $defPower . "\n";
assert($defPower > 0, "Defensive power should be positive");

// Test casualty calculation
$ratio = $offPower / $defPower;
echo "Battle ratio: " . $ratio . "\n";
$casualties = $combatCalc->calculateCasualties($ratio, $attackerUnits, $defenderUnits);
echo "Attacker survivors: " . json_encode($casualties['attacker_survivors']) . "\n";
echo "Defender survivors: " . json_encode($casualties['defender_survivors']) . "\n";

// Test winner determination
$winner = $combatCalc->determineWinner($ratio);
echo "Winner: " . $winner . "\n";
assert(in_array($winner, ['attacker_win', 'defender_hold']), "Invalid winner");

// Test force merging
$garrison = ['spear' => 50, 'axe' => 30];
$support = ['spear' => 20, 'light' => 10];
$merged = $combatCalc->mergeDefendingForces($garrison, $support);
echo "Merged forces: " . json_encode($merged) . "\n";
assert($merged['spear'] == 70, "Spears should be merged correctly");
assert($merged['axe'] == 30, "Axes should be preserved");
assert($merged['light'] == 10, "Light cavalry should be added");

// Test class shares
$classShares = $combatCalc->getClassShares(['axe' => 50, 'spear' => 50, 'light' => 50]);
echo "Class shares: " . json_encode($classShares) . "\n";
assert(abs($classShares['infantry'] - 0.667) < 0.01, "Infantry share should be ~0.667");
assert(abs($classShares['cavalry'] - 0.333) < 0.01, "Cavalry share should be ~0.333");

echo "\n✓ All tests passed!\n";
