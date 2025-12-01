<?php
require_once __DIR__ . '/../lib/managers/SiegeHandler.php';

$handler = new SiegeHandler();

/** Feature: battle-resolution, Property 6: Wall Multiplier Application */
for ($i = 1; $i <= 15; $i++) {
    $mult = $handler->calculateWallMultiplier($i);
    if ($i <= 10) {
        $expected = pow(1.037, $i);
    } else {
        $expected = pow(1.037, 10) * pow(1.05, $i - 10);
    }
    assert(abs($mult - $expected) < 1e-9, "Wall multiplier mismatch for level {$i}");
}

/** Feature: battle-resolution, Property 7: Ram Damage Determinism */
$world = ['speed' => 1.0];
$newWall = $handler->applyRamDamage(10, 20, $world);
$ramsPerLevel = max(1, (int)ceil((2 + 10 * 0.5) / 1.0));
$expectedDrop = (int)floor(20 / $ramsPerLevel);
assert($newWall === max(0, 10 - $expectedDrop), "Ram damage mismatch");

/** Feature: battle-resolution, Property 8: Catapult Damage on Victory */
$newBuilding = $handler->applyCatapultDamage(10, 30, $world, true);
$catsPerLevel = max(1, (int)ceil((8 + 10 * 2) / 1.0));
$expectedDrop = (int)floor(30 / $catsPerLevel);
assert($newBuilding === max(0, 10 - $expectedDrop), "Catapult damage mismatch");

/** Feature: battle-resolution, Property 9: Wall Persistence */
// Ensure not going below zero and does not change when no rams.
$noChange = $handler->applyRamDamage(5, 0, $world);
assert($noChange === 5, "Wall should persist without rams");
$nonNegative = $handler->applyRamDamage(1, 1000, $world);
assert($nonNegative >= 0, "Wall should not go negative");

echo "SiegeHandler property tests passed.\n";
