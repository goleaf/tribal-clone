<?php
require_once __DIR__ . '/../lib/managers/ConquestHandler.php';

$handler = new ConquestHandler();
$world = [
    'allegiance_drop_per_noble' => 25,
    'post_capture_allegiance' => 25,
];

/** Feature: battle-resolution, Property 23: Allegiance Reduction on Victory */
$res = $handler->reduceAllegiance(100, 2, $world, true, false);
assert($res['new_allegiance'] === 50 && $res['dropped'] === 50, "Allegiance drop mismatch");

/** Feature: battle-resolution, Property 24: Ownership Transfer Threshold */
assert($handler->checkCaptureConditions(0) === true, "Capture should trigger at <=0");

/** Feature: battle-resolution, Property 25: Post-Capture Allegiance Floor */
assert($handler->applyPostCaptureAllegiance($world) === 25, "Post-capture floor mismatch");

/** Feature: battle-resolution, Property 26: No Allegiance Drop on Loss */
$loss = $handler->reduceAllegiance(100, 2, $world, false, false);
assert($loss['new_allegiance'] === 100 && $loss['blocked'] === true, "No drop on loss");

/** Feature: battle-resolution, Property 27: Conquest Cooldown Enforcement */
$cool = $handler->reduceAllegiance(100, 2, $world, true, true);
assert($cool['blocked'] === true && $cool['reason'] === 'ERR_CONQUEST_COOLDOWN', "Cooldown should block");

echo "ConquestHandler property tests passed.\n";
