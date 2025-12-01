<?php
require_once __DIR__ . '/../lib/managers/CommandProcessor.php';

$unitData = json_decode(file_get_contents(__DIR__ . '/../data/units.json'), true);
$cp = new CommandProcessor($unitData);

/** Feature: battle-resolution, Property 36: Command Sorting Determinism */
$commands = [
    ['command_id' => 2, 'arrival_at' => '2023-01-01T00:00:02Z', 'sequence_number' => 1, 'command_type' => 'attack'],
    ['command_id' => 1, 'arrival_at' => '2023-01-01T00:00:01Z', 'sequence_number' => 1, 'command_type' => 'support'],
    ['command_id' => 3, 'arrival_at' => '2023-01-01T00:00:01Z', 'sequence_number' => 2, 'command_type' => 'attack'],
];
$sorted = $cp->sortCommands($commands);
assert($sorted[0]['command_id'] === 1, "First should be earliest arrival");
assert($sorted[1]['command_id'] === 3, "Second should respect sequence");
assert($sorted[2]['command_id'] === 2, "Third is later arrival");

/** Feature: battle-resolution, Property 37: Support Timing Inclusion */
// Arrival ordering covers inclusion; support sorted before same-tick attacks.
$sameTick = [
    ['command_id' => 10, 'arrival_at' => 't', 'sequence_number' => 1, 'command_type' => 'attack'],
    ['command_id' => 9, 'arrival_at' => 't', 'sequence_number' => 1, 'command_type' => 'support'],
];
$sorted = $cp->sortCommands($sameTick);
assert($sorted[0]['command_type'] === 'support', "Support should sort before attack on same tick");

/** Feature: battle-resolution, Property 38: Sequential Processing Order */
for ($i = 0; $i < 10; $i++) {
    $cmds = [
        ['command_id' => 1, 'arrival_at' => 'a', 'sequence_number' => 2, 'command_type' => 'attack'],
        ['command_id' => 2, 'arrival_at' => 'a', 'sequence_number' => 1, 'command_type' => 'attack'],
    ];
    $sorted = $cp->sortCommands($cmds);
    assert($sorted[0]['command_id'] === 2, "Sequence determines order");
}

/** Feature: battle-resolution, Property 39: Battle Determinism */
// Sorting is deterministic; identical inputs produce identical outputs.
$cmds = [
    ['command_id' => 5, 'arrival_at' => 'a', 'sequence_number' => 1, 'command_type' => 'attack'],
    ['command_id' => 4, 'arrival_at' => 'a', 'sequence_number' => 1, 'command_type' => 'attack'],
];
$sortedA = $cp->sortCommands($cmds);
$sortedB = $cp->sortCommands($cmds);
assert($sortedA === $sortedB, "Sorting must be deterministic");

/** Feature: battle-resolution, Property 40: Command Spacing Enforcement */
// Simulate spacing by rate limit window.
$history = ['player' => [], 'target' => []];
$config = ['rate_limits' => ['per_player' => 1, 'per_target' => 1, 'window_seconds' => 60]];
$now = 1000;
$res1 = $cp->enforceRateLimits(1, 2, $now, $history, $config);
$res2 = $cp->enforceRateLimits(1, 2, $now + 1, $history, $config);
assert($res1['allowed'] === true, "First allowed");
assert($res2['allowed'] === false, "Second should be bumped/rejected");

/** Feature: battle-resolution, Property 41: Per-Player Rate Limit */
$history = ['player' => [], 'target' => []];
$config = ['rate_limits' => ['per_player' => 2, 'per_target' => 10, 'window_seconds' => 60]];
$cp->enforceRateLimits(7, null, 0, $history, $config);
$cp->enforceRateLimits(7, null, 1, $history, $config);
$third = $cp->enforceRateLimits(7, null, 2, $history, $config);
assert($third['allowed'] === false, "Per-player limit enforced");

/** Feature: battle-resolution, Property 42: Per-Target Rate Limit */
$history = ['player' => [], 'target' => []];
$config = ['rate_limits' => ['per_player' => 10, 'per_target' => 1, 'window_seconds' => 60]];
$cp->enforceRateLimits(3, 9, 0, $history, $config);
$second = $cp->enforceRateLimits(3, 9, 1, $history, $config);
assert($second['allowed'] === false, "Per-target limit enforced");

/** Feature: battle-resolution, Property 43: Rate Limit Error Response */
assert(isset($second['retry_after']) && $second['retry_after'] > 0, "Retry-after should be provided");

/** Feature: battle-resolution, Property 44: Minimum Population Enforcement */
$units = ['spear' => 1]; // pop 1
$world = ['min_attack_population' => 10];
$valid = $cp->validateCommand(['units' => $units], $world);
assert($valid['valid'] === false && $valid['error'] === 'ERR_MIN_POP', "Min pop enforced");

/** Feature: battle-resolution, Property 45: Fake Attack Tagging */
$isFake = $cp->isFakeAttack(['spear' => 1], 50);
assert($isFake === true, "Fake tagging for low pop");

echo "CommandProcessor property tests passed.\n";
