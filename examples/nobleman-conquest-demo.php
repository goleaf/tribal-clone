<?php
/**
 * Nobleman Conquest System Demo
 * 
 * This script demonstrates the nobleman conquest mechanics:
 * - Loyalty drops on successful noble attacks
 * - Village conquest when loyalty reaches 0
 * - Point-range gates
 * - Special items (Vasco's Scepter)
 */

require_once __DIR__ . '/../init.php';

echo "=== Nobleman Conquest System Demo ===\n\n";

// Simulate loyalty drop calculation
function simulateLoyaltyDrop($currentLoyalty, $minDrop = 20, $maxDrop = 35) {
    $drop = random_int($minDrop, $maxDrop);
    $newLoyalty = max(0, $currentLoyalty - $drop);
    return [
        'drop' => $drop,
        'before' => $currentLoyalty,
        'after' => $newLoyalty,
        'conquered' => $newLoyalty <= 0
    ];
}

// Demo 1: Standard conquest sequence
echo "Demo 1: Standard Conquest Sequence\n";
echo "-----------------------------------\n";
$loyalty = 100;
$attackNumber = 1;

while ($loyalty > 0) {
    $result = simulateLoyaltyDrop($loyalty);
    echo "Attack #{$attackNumber}: Loyalty {$result['before']} → {$result['after']} (dropped {$result['drop']})\n";
    
    if ($result['conquered']) {
        echo "✓ Village CONQUERED! Loyalty reset to 100 under new owner.\n";
        break;
    }
    
    $loyalty = $result['after'];
    $attackNumber++;
}

echo "\n";

// Demo 2: Point-range gate
echo "Demo 2: Point-Range Gate (50%-150%)\n";
echo "------------------------------------\n";

function checkConquestAllowed($attackerPoints, $defenderPoints) {
    if ($attackerPoints <= 0 || $defenderPoints <= 0) {
        return ['allowed' => true, 'reason' => 'Barbarian village'];
    }
    
    $ratio = $defenderPoints / $attackerPoints;
    
    if ($ratio < 0.5) {
        return ['allowed' => false, 'reason' => 'Target too weak (< 50% of your points)'];
    }
    
    if ($ratio > 1.5) {
        return ['allowed' => false, 'reason' => 'Target too strong (> 150% of your points)'];
    }
    
    return ['allowed' => true, 'reason' => 'Within valid range'];
}

$scenarios = [
    ['attacker' => 1000, 'defender' => 400],  // Too weak (40%)
    ['attacker' => 1000, 'defender' => 600],  // Valid (60%)
    ['attacker' => 1000, 'defender' => 1200], // Valid (120%)
    ['attacker' => 1000, 'defender' => 1600], // Too strong (160%)
    ['attacker' => 1000, 'defender' => -1],   // Barbarian
];

foreach ($scenarios as $scenario) {
    $check = checkConquestAllowed($scenario['attacker'], $scenario['defender']);
    $status = $check['allowed'] ? '✓ ALLOWED' : '✗ BLOCKED';
    $defenderLabel = $scenario['defender'] === -1 ? 'Barbarian' : $scenario['defender'] . ' pts';
    echo "Attacker: {$scenario['attacker']} pts vs Defender: {$defenderLabel}\n";
    echo "  {$status}: {$check['reason']}\n\n";
}

// Demo 3: Vasco's Scepter (loyalty floor)
echo "Demo 3: Vasco's Scepter (Loyalty Floor = 30)\n";
echo "---------------------------------------------\n";
$loyalty = 100;
$attackNumber = 1;
$loyaltyFloor = 30;

while ($loyalty > $loyaltyFloor) {
    $result = simulateLoyaltyDrop($loyalty);
    $actualAfter = max($loyaltyFloor, $result['after']);
    
    echo "Attack #{$attackNumber}: Loyalty {$result['before']} → {$actualAfter} (dropped {$result['drop']})";
    
    if ($actualAfter === $loyaltyFloor && $result['after'] < $loyaltyFloor) {
        echo " [FLOOR REACHED]";
    }
    
    echo "\n";
    
    if ($actualAfter <= $loyaltyFloor) {
        echo "✗ Cannot conquer: Vasco's Scepter prevents loyalty from dropping below {$loyaltyFloor}.\n";
        break;
    }
    
    $loyalty = $actualAfter;
    $attackNumber++;
}

echo "\n";

// Demo 4: Attack conditions
echo "Demo 4: Attack Conditions for Loyalty Drop\n";
echo "-------------------------------------------\n";

$conditions = [
    ['attacker_wins' => true, 'noble_survives' => true, 'defenders_alive' => false, 'expected' => true],
    ['attacker_wins' => false, 'noble_survives' => true, 'defenders_alive' => true, 'expected' => false],
    ['attacker_wins' => true, 'noble_survives' => false, 'defenders_alive' => false, 'expected' => false],
    ['attacker_wins' => true, 'noble_survives' => true, 'defenders_alive' => true, 'expected' => false],
];

foreach ($conditions as $i => $cond) {
    echo "Scenario " . ($i + 1) . ":\n";
    echo "  Attacker wins: " . ($cond['attacker_wins'] ? 'Yes' : 'No') . "\n";
    echo "  Noble survives: " . ($cond['noble_survives'] ? 'Yes' : 'No') . "\n";
    echo "  Defenders alive: " . ($cond['defenders_alive'] ? 'Yes' : 'No') . "\n";
    
    $loyaltyDrops = $cond['attacker_wins'] && $cond['noble_survives'] && !$cond['defenders_alive'];
    $status = $loyaltyDrops ? '✓ Loyalty DROPS' : '✗ No loyalty change';
    
    echo "  Result: {$status}\n";
    
    if ($loyaltyDrops !== $cond['expected']) {
        echo "  ⚠ WARNING: Unexpected result!\n";
    }
    
    echo "\n";
}

// Demo 5: Statistics
echo "Demo 5: Conquest Statistics (1000 simulations)\n";
echo "-----------------------------------------------\n";

$simulations = 1000;
$totalAttacks = 0;
$minAttacks = PHP_INT_MAX;
$maxAttacks = 0;

for ($i = 0; $i < $simulations; $i++) {
    $loyalty = 100;
    $attacks = 0;
    
    while ($loyalty > 0) {
        $result = simulateLoyaltyDrop($loyalty);
        $loyalty = $result['after'];
        $attacks++;
        
        if ($attacks > 100) break; // Safety limit
    }
    
    $totalAttacks += $attacks;
    $minAttacks = min($minAttacks, $attacks);
    $maxAttacks = max($maxAttacks, $attacks);
}

$avgAttacks = round($totalAttacks / $simulations, 2);

echo "Average attacks to conquer: {$avgAttacks}\n";
echo "Minimum attacks: {$minAttacks}\n";
echo "Maximum attacks: {$maxAttacks}\n";
echo "\n";

// Demo 6: Loyalty regeneration (future feature)
echo "Demo 6: Loyalty Regeneration (Future Feature)\n";
echo "----------------------------------------------\n";

function simulateLoyaltyRegeneration($currentLoyalty, $hoursPassed, $regenPerHour = 1) {
    $newLoyalty = min(100, $currentLoyalty + ($hoursPassed * $regenPerHour));
    return [
        'before' => $currentLoyalty,
        'after' => $newLoyalty,
        'regenerated' => $newLoyalty - $currentLoyalty
    ];
}

$loyalty = 65;
$timeScenarios = [1, 6, 12, 24, 48];

echo "Starting loyalty: {$loyalty}\n\n";

foreach ($timeScenarios as $hours) {
    $result = simulateLoyaltyRegeneration($loyalty, $hours);
    echo "After {$hours} hours: {$result['before']} → {$result['after']} (+{$result['regenerated']})\n";
}

echo "\n";
echo "Note: Loyalty regeneration is not yet implemented in the game.\n";
echo "This is a demonstration of how it could work.\n";

echo "\n=== Demo Complete ===\n";
