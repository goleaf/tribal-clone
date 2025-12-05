<?php
/**
 * ViewRenderer Demo
 * 
 * Demonstrates how to use the ViewRenderer component for WAP-style interfaces.
 * This example shows all the main rendering methods.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/ViewRenderer.php';
require_once __DIR__ . '/../lib/managers/ResourceManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';

// Initialize managers
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$resourceManager = new ResourceManager($conn, $buildingManager);
$renderer = new ViewRenderer($conn, $buildingManager, $resourceManager);

// Example 1: Village Overview
echo "=== Example 1: Village Overview ===\n\n";

$village = [
    'id' => 1,
    'name' => 'Capital',
    'x_coord' => 500,
    'y_coord' => 500,
    'wood' => 5000,
    'clay' => 4000,
    'iron' => 3000,
    'warehouse_capacity' => 10000,
    'population' => 150,
    'farm_capacity' => 200,
    'wood_rate' => 50.0,
    'clay_rate' => 40.0,
    'iron_rate' => 30.0
];

$buildings = [
    ['name' => 'Main Building', 'internal_name' => 'main_building', 'level' => 10],
    ['name' => 'Barracks', 'internal_name' => 'barracks', 'level' => 5],
    ['name' => 'Warehouse', 'internal_name' => 'warehouse', 'level' => 8]
];

$movements = [
    'incoming' => [
        ['origin' => 'Enemy (501|501)', 'arrival_time' => date('Y-m-d H:i:s', time() + 3600), 'attack_type' => 'Attack']
    ],
    'outgoing' => [
        ['destination' => 'Ally (499|499)', 'arrival_time' => date('Y-m-d H:i:s', time() + 1800), 'attack_type' => 'Support']
    ]
];

echo $renderer->renderVillageOverview($village, $buildings, $movements);
echo "\n\n";

// Example 2: Navigation Menu
echo "=== Example 2: Navigation Menu ===\n\n";
echo $renderer->renderNavigation();
echo "\n\n";

// Example 3: Resource Bar
echo "=== Example 3: Resource Bar ===\n\n";
echo $renderer->renderResourceBar(
    ['wood' => 5000, 'clay' => 4000, 'iron' => 3000],
    ['wood' => 50.0, 'clay' => 40.0, 'iron' => 30.0],
    10000
);
echo "\n\n";

// Example 4: Building List
echo "=== Example 4: Building List ===\n\n";

$buildingsWithCosts = [
    [
        'name' => 'Main Building',
        'internal_name' => 'main_building',
        'level' => 10,
        'max_level' => 30,
        'upgrade_costs' => ['wood' => 1000, 'clay' => 800, 'iron' => 600],
        'upgrade_time_seconds' => 1800,
        'can_upgrade' => true
    ],
    [
        'name' => 'Barracks',
        'internal_name' => 'barracks',
        'level' => 5,
        'max_level' => 25,
        'upgrade_costs' => ['wood' => 500, 'clay' => 400, 'iron' => 300],
        'upgrade_time_seconds' => 900,
        'can_upgrade' => false,
        'upgrade_not_available_reason' => 'Queue is full'
    ]
];

echo $renderer->renderBuildingList($buildingsWithCosts, $village);
echo "\n\n";

// Example 5: Battle Report
echo "=== Example 5: Battle Report ===\n\n";

$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'outcome' => 'victory',
    'attacker_village' => [
        'name' => 'Capital',
        'x_coord' => 500,
        'y_coord' => 500
    ],
    'defender_village' => [
        'name' => 'Enemy Village',
        'x_coord' => 501,
        'y_coord' => 501
    ],
    'modifiers' => [
        'luck' => 0.08,
        'morale' => 95,
        'wall_multiplier' => 1.15
    ],
    'troops' => [
        'attacker_sent' => ['spearman' => 200, 'swordsman' => 100, 'axeman' => 50],
        'attacker_lost' => ['spearman' => 40, 'swordsman' => 20, 'axeman' => 10],
        'attacker_survivors' => ['spearman' => 160, 'swordsman' => 80, 'axeman' => 40],
        'defender_present' => ['spearman' => 100, 'archer' => 50],
        'defender_lost' => ['spearman' => 100, 'archer' => 50],
        'defender_survivors' => ['spearman' => 0, 'archer' => 0]
    ],
    'plunder' => [
        'wood' => 1000,
        'clay' => 800,
        'iron' => 600
    ],
    'allegiance' => [
        'before' => 100,
        'after' => 72,
        'drop' => 28
    ]
];

echo $renderer->renderBattleReport($report);
echo "\n\n";

// Example 6: Queue Display
echo "=== Example 6: Queue Display ===\n\n";

$queueItems = [
    [
        'name' => 'Main Building',
        'level' => 11,
        'finish_time' => date('Y-m-d H:i:s', time() + 1800)
    ],
    [
        'name' => 'Spearman',
        'quantity' => 50,
        'finish_time' => date('Y-m-d H:i:s', time() + 3600)
    ]
];

echo $renderer->renderQueueDisplay($queueItems);
echo "\n\n";

echo "=== Demo Complete ===\n";
echo "All ViewRenderer methods demonstrated successfully.\n";
echo "The output is WAP-compatible: minimal HTML, text-based, suitable for low-bandwidth.\n";

