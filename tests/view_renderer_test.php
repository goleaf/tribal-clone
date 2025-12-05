<?php
/**
 * ViewRenderer Test
 * 
 * Tests the ViewRenderer component for WAP-style interface rendering.
 * Validates Requirements 3.1, 3.2, 3.3, 3.4, 3.5.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/ViewRenderer.php';
require_once __DIR__ . '/../lib/managers/ResourceManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';

// Test data
$testVillage = [
    'id' => 1,
    'name' => 'Test Village',
    'x_coord' => 500,
    'y_coord' => 500,
    'wood' => 1000,
    'clay' => 800,
    'iron' => 600,
    'warehouse_capacity' => 2000,
    'population' => 50,
    'farm_capacity' => 100,
    'wood_rate' => 30.5,
    'clay_rate' => 25.0,
    'iron_rate' => 20.0
];

$testBuildings = [
    [
        'name' => 'Main Building',
        'internal_name' => 'main_building',
        'level' => 5,
        'max_level' => 30,
        'upgrade_costs' => ['wood' => 100, 'clay' => 80, 'iron' => 60],
        'upgrade_time_seconds' => 300,
        'can_upgrade' => true
    ],
    [
        'name' => 'Barracks',
        'internal_name' => 'barracks',
        'level' => 3,
        'max_level' => 25,
        'upgrade_costs' => ['wood' => 200, 'clay' => 150, 'iron' => 100],
        'upgrade_time_seconds' => 600,
        'can_upgrade' => false,
        'upgrade_not_available_reason' => 'Not enough resources'
    ]
];

$testMovements = [
    'incoming' => [
        [
            'origin' => 'Enemy Village (501|501)',
            'arrival_time' => date('Y-m-d H:i:s', time() + 3600),
            'attack_type' => 'Attack'
        ]
    ],
    'outgoing' => [
        [
            'destination' => 'Target Village (499|499)',
            'arrival_time' => date('Y-m-d H:i:s', time() + 1800),
            'attack_type' => 'Support'
        ]
    ]
];

$testReport = [
    'timestamp' => date('Y-m-d H:i:s'),
    'outcome' => 'victory',
    'attacker_village' => [
        'name' => 'Attacker Village',
        'x_coord' => 500,
        'y_coord' => 500
    ],
    'defender_village' => [
        'name' => 'Defender Village',
        'x_coord' => 501,
        'y_coord' => 501
    ],
    'modifiers' => [
        'luck' => 0.05,
        'morale' => 100,
        'wall_multiplier' => 1.2
    ],
    'troops' => [
        'attacker_sent' => ['spearman' => 100, 'swordsman' => 50],
        'attacker_lost' => ['spearman' => 20, 'swordsman' => 10],
        'attacker_survivors' => ['spearman' => 80, 'swordsman' => 40],
        'defender_present' => ['spearman' => 50],
        'defender_lost' => ['spearman' => 50],
        'defender_survivors' => ['spearman' => 0]
    ],
    'plunder' => [
        'wood' => 500,
        'clay' => 400,
        'iron' => 300
    ],
    'allegiance' => [
        'before' => 100,
        'after' => 75,
        'drop' => 25
    ]
];

// Initialize renderer
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$resourceManager = new ResourceManager($conn, $buildingManager);
$renderer = new ViewRenderer($conn, $buildingManager, $resourceManager);

echo "=== ViewRenderer Test Suite ===\n\n";

// Test 1: renderVillageOverview
echo "Test 1: renderVillageOverview\n";
echo "Requirement 3.1: Compact HTML table with buildings/resources/movements\n";
$overview = $renderer->renderVillageOverview($testVillage, $testBuildings, $testMovements);
if (strpos($overview, '<table') !== false && 
    strpos($overview, 'Buildings') !== false && 
    strpos($overview, 'Resources') !== false && 
    strpos($overview, 'Movements') !== false) {
    echo "✓ PASS: Village overview contains all required sections\n";
} else {
    echo "✗ FAIL: Village overview missing required sections\n";
}
if (strpos($overview, 'Main Building') !== false && strpos($overview, 'Lvl 5') !== false) {
    echo "✓ PASS: Building data rendered correctly\n";
} else {
    echo "✗ FAIL: Building data not rendered correctly\n";
}
echo "\n";

// Test 2: renderBuildingList
echo "Test 2: renderBuildingList\n";
echo "Requirement 3.2: Table rows with name, level, cost, time, upgrade link\n";
$buildingList = $renderer->renderBuildingList($testBuildings, $testVillage);
if (strpos($buildingList, '<table') !== false && 
    strpos($buildingList, '<th>Building</th>') !== false &&
    strpos($buildingList, '<th>Level</th>') !== false &&
    strpos($buildingList, '<th>Upgrade Cost</th>') !== false &&
    strpos($buildingList, '<th>Time</th>') !== false &&
    strpos($buildingList, '<th>Action</th>') !== false) {
    echo "✓ PASS: Building list has all required columns\n";
} else {
    echo "✗ FAIL: Building list missing required columns\n";
}
if (strpos($buildingList, '100W, 80C, 60I') !== false) {
    echo "✓ PASS: Upgrade costs formatted correctly\n";
} else {
    echo "✗ FAIL: Upgrade costs not formatted correctly\n";
}
if (strpos($buildingList, 'Upgrade to 6') !== false) {
    echo "✓ PASS: Upgrade link present for upgradeable building\n";
} else {
    echo "✗ FAIL: Upgrade link missing\n";
}
if (strpos($buildingList, 'Not enough resources') !== false) {
    echo "✓ PASS: Upgrade restriction reason shown\n";
} else {
    echo "✗ FAIL: Upgrade restriction reason not shown\n";
}
echo "\n";

// Test 3: renderResourceBar
echo "Test 3: renderResourceBar\n";
echo "Requirement 3.3: Text-only resource display with rates\n";
$resourceBar = $renderer->renderResourceBar(
    ['wood' => 1000, 'clay' => 800, 'iron' => 600],
    ['wood' => 30.5, 'clay' => 25.0, 'iron' => 20.0],
    2000
);
if (strpos($resourceBar, 'Wood: 1000') !== false && 
    strpos($resourceBar, '+30.5/hr') !== false) {
    echo "✓ PASS: Wood resource formatted correctly with rate\n";
} else {
    echo "✗ FAIL: Wood resource format incorrect\n";
}
if (strpos($resourceBar, 'Clay: 800') !== false && 
    strpos($resourceBar, '+25.0/hr') !== false) {
    echo "✓ PASS: Clay resource formatted correctly with rate\n";
} else {
    echo "✗ FAIL: Clay resource format incorrect\n";
}
if (strpos($resourceBar, 'Iron: 600') !== false && 
    strpos($resourceBar, '+20.0/hr') !== false) {
    echo "✓ PASS: Iron resource formatted correctly with rate\n";
} else {
    echo "✗ FAIL: Iron resource format incorrect\n";
}
if (strpos($resourceBar, 'Capacity: 2000') !== false) {
    echo "✓ PASS: Capacity displayed\n";
} else {
    echo "✗ FAIL: Capacity not displayed\n";
}
echo "\n";

// Test 4: renderNavigation
echo "Test 4: renderNavigation\n";
echo "Requirement 3.4: Hyperlink menu for main sections\n";
$navigation = $renderer->renderNavigation();
$requiredLinks = ['Village', 'Troops', 'Market', 'Research', 'Reports', 'Messages', 'Alliance', 'Profile'];
$allLinksPresent = true;
foreach ($requiredLinks as $link) {
    if (strpos($navigation, $link) === false) {
        echo "✗ FAIL: Missing link: $link\n";
        $allLinksPresent = false;
    }
}
if ($allLinksPresent) {
    echo "✓ PASS: All required navigation links present\n";
}
if (strpos($navigation, '<a href=') !== false && strpos($navigation, '|') !== false) {
    echo "✓ PASS: Navigation formatted as hyperlinks with separators\n";
} else {
    echo "✗ FAIL: Navigation format incorrect\n";
}
echo "\n";

// Test 5: renderBattleReport
echo "Test 5: renderBattleReport\n";
echo "Requirement 3.5: Formatted text tables for combat results\n";
$battleReport = $renderer->renderBattleReport($testReport);
if (strpos($battleReport, 'Battle Report') !== false) {
    echo "✓ PASS: Report has title\n";
} else {
    echo "✗ FAIL: Report missing title\n";
}
if (strpos($battleReport, 'Outcome:') !== false && 
    (strpos($battleReport, 'victory') !== false || strpos($battleReport, 'Victory') !== false)) {
    echo "✓ PASS: Outcome displayed\n";
} else {
    echo "✗ FAIL: Outcome not displayed\n";
}
if (strpos($battleReport, 'Attacker Village') !== false && 
    strpos($battleReport, 'Defender Village') !== false) {
    echo "✓ PASS: Village information displayed\n";
} else {
    echo "✗ FAIL: Village information missing\n";
}
if (strpos($battleReport, 'Luck:') !== false && 
    strpos($battleReport, 'Morale:') !== false &&
    strpos($battleReport, 'Wall Bonus:') !== false) {
    echo "✓ PASS: Modifiers displayed\n";
} else {
    echo "✗ FAIL: Modifiers missing\n";
}
if (strpos($battleReport, '<table') !== false && 
    strpos($battleReport, 'Attacker Sent') !== false &&
    strpos($battleReport, 'Defender Lost') !== false) {
    echo "✓ PASS: Troops table present with required columns\n";
} else {
    echo "✗ FAIL: Troops table missing or incomplete\n";
}
if (strpos($battleReport, 'Plunder:') !== false && 
    strpos($battleReport, 'Wood: 500') !== false) {
    echo "✓ PASS: Plunder information displayed\n";
} else {
    echo "✗ FAIL: Plunder information missing\n";
}
if (strpos($battleReport, 'Loyalty:') !== false && 
    strpos($battleReport, 'Before: 100') !== false &&
    strpos($battleReport, 'After: 75') !== false) {
    echo "✓ PASS: Loyalty/allegiance information displayed\n";
} else {
    echo "✗ FAIL: Loyalty information missing\n";
}
echo "\n";

// Test 6: WAP constraints validation
echo "Test 6: WAP Constraints Validation\n";
echo "Verifying minimal HTML suitable for WAP constraints\n";
$allOutputs = $overview . $buildingList . $resourceBar . $navigation . $battleReport;
// Check for absence of heavy elements
if (strpos($allOutputs, '<div') === false && 
    strpos($allOutputs, '<span') === false &&
    strpos($allOutputs, 'class=') === false) {
    echo "✓ PASS: No heavy HTML elements (div, span, classes)\n";
} else {
    echo "✗ FAIL: Contains heavy HTML elements not suitable for WAP\n";
}
// Check for simple table-based layout
if (strpos($allOutputs, '<table') !== false && 
    strpos($allOutputs, '<br>') !== false) {
    echo "✓ PASS: Uses simple table and line break elements\n";
} else {
    echo "✗ FAIL: Missing basic WAP-compatible elements\n";
}
echo "\n";

echo "=== Test Suite Complete ===\n";

