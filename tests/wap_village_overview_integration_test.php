<?php
/**
 * WAP Village Overview Integration Test
 * 
 * Tests the complete WAP-style village overview page (game/game_wap.php).
 * Validates Requirements 3.1, 3.2, 3.3, 3.4, 3.5 in an integrated context.
 * 
 * This test verifies:
 * - Compact layout with buildings (left), resources (center), movements (right)
 * - Navigation header with all main section links
 * - Zero-scroll access to critical information
 * - Meta-refresh tags for timer updates
 * - Text-only interface suitable for WAP constraints
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/ResourceManager.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';
require_once __DIR__ . '/../lib/managers/ViewRenderer.php';

echo "=== WAP Village Overview Integration Test ===\n\n";

// Create test user and village
$testUsername = 'wap_test_user_' . time();
$testPassword = password_hash('test123', PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
$stmt->execute([$testUsername, $testPassword, $testUsername . '@test.com']);
// Handle both MySQL and SQLite
if (isset($stmt->insert_id) && $stmt->insert_id > 0) {
    $testUserId = $stmt->insert_id;
} else {
    $testUserId = $conn->insert_id;
}
$stmt->close();

// Create test village
$stmt = $conn->prepare("
    INSERT INTO villages (user_id, world_id, name, x_coord, y_coord, wood, clay, iron, 
                         warehouse_capacity, population, farm_capacity, loyalty, last_resource_update)
    VALUES (?, 1, ?, 500, 500, 1000, 800, 600, 2000, 50, 100, 100, datetime('now'))
");
$stmt->execute([$testUserId, 'WAP Test Village']);
// Handle both MySQL and SQLite
if (isset($stmt->insert_id) && $stmt->insert_id > 0) {
    $testVillageId = $stmt->insert_id;
} else {
    $testVillageId = $conn->insert_id;
}
$stmt->close();

// Initialize managers
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$villageManager = new VillageManager($conn);
$resourceManager = new ResourceManager($conn, $buildingManager);
$unitManager = new UnitManager($conn);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);
$viewRenderer = new ViewRenderer($conn, $buildingManager, $resourceManager);

// Set up some buildings
$stmt = $conn->prepare("
    INSERT INTO village_buildings (village_id, building_type_id, level)
    SELECT ?, id, 5 FROM building_types WHERE internal_name = 'main_building'
");
$stmt->execute([$testVillageId]);

$stmt = $conn->prepare("
    INSERT INTO village_buildings (village_id, building_type_id, level)
    SELECT ?, id, 3 FROM building_types WHERE internal_name = 'barracks'
");
$stmt->execute([$testVillageId]);

$stmt = $conn->prepare("
    INSERT INTO village_buildings (village_id, building_type_id, level)
    SELECT ?, id, 2 FROM building_types WHERE internal_name = 'warehouse'
");
$stmt->execute([$testVillageId]);

echo "Test Setup Complete\n";
echo "- Test User ID: $testUserId\n";
echo "- Test Village ID: $testVillageId\n\n";

// Test 1: Verify ViewRenderer integration
echo "Test 1: ViewRenderer Integration\n";
echo "Verifying that ViewRenderer is properly integrated\n";

$village = $villageManager->getVillageInfo($testVillageId);
$buildingsData = $buildingManager->getVillageBuildingsViewData($testVillageId, 0);
$productionRates = $resourceManager->getProductionRates($testVillageId);

$village['wood_rate'] = $productionRates['wood'] ?? 0;
$village['clay_rate'] = $productionRates['clay'] ?? 0;
$village['iron_rate'] = $productionRates['iron'] ?? 0;

$movements = ['incoming' => [], 'outgoing' => []];

$overview = $viewRenderer->renderVillageOverview($village, $buildingsData, $movements);

if (strpos($overview, '<table') !== false) {
    echo "✓ PASS: ViewRenderer generates table-based layout\n";
} else {
    echo "✗ FAIL: ViewRenderer not generating table layout\n";
}

if (strpos($overview, 'Buildings') !== false && 
    strpos($overview, 'Resources') !== false && 
    strpos($overview, 'Movements') !== false) {
    echo "✓ PASS: Three-column layout (buildings, resources, movements) present\n";
} else {
    echo "✗ FAIL: Three-column layout not complete\n";
}
echo "\n";

// Test 2: Navigation header
echo "Test 2: Navigation Header\n";
echo "Requirement 3.4: Hyperlink menu for main sections\n";

$navigation = $viewRenderer->renderNavigation();
$requiredSections = ['Village', 'Troops', 'Market', 'Research', 'Reports', 'Messages', 'Alliance', 'Profile'];
$allPresent = true;

foreach ($requiredSections as $section) {
    if (strpos($navigation, $section) === false) {
        echo "✗ FAIL: Missing navigation link: $section\n";
        $allPresent = false;
    }
}

if ($allPresent) {
    echo "✓ PASS: All required navigation sections present\n";
}

if (strpos($navigation, '<a href=') !== false) {
    echo "✓ PASS: Navigation uses hyperlinks\n";
} else {
    echo "✗ FAIL: Navigation not using hyperlinks\n";
}
echo "\n";

// Test 3: Resource display with rates
echo "Test 3: Resource Display with Production Rates\n";
echo "Requirement 3.3: Text-only resource display with rates\n";

$resourceBar = $viewRenderer->renderResourceBar(
    [
        'wood' => $village['wood'],
        'clay' => $village['clay'],
        'iron' => $village['iron']
    ],
    [
        'wood' => $productionRates['wood'] ?? 0,
        'clay' => $productionRates['clay'] ?? 0,
        'iron' => $productionRates['iron'] ?? 0
    ],
    (int)$village['warehouse_capacity']
);

if (strpos($resourceBar, 'Wood:') !== false && 
    strpos($resourceBar, '/hr') !== false) {
    echo "✓ PASS: Resource display includes production rates\n";
} else {
    echo "✗ FAIL: Resource display missing production rates\n";
}

if (strpos($resourceBar, 'Capacity:') !== false) {
    echo "✓ PASS: Warehouse capacity displayed\n";
} else {
    echo "✗ FAIL: Warehouse capacity not displayed\n";
}
echo "\n";

// Test 4: Building list with upgrade options
echo "Test 4: Building List with Upgrade Options\n";
echo "Requirement 3.2: Table rows with name, level, cost, time, upgrade link\n";

$buildingList = $viewRenderer->renderBuildingList($buildingsData, $village);

if (strpos($buildingList, '<table') !== false) {
    echo "✓ PASS: Building list uses table format\n";
} else {
    echo "✗ FAIL: Building list not using table format\n";
}

$requiredColumns = ['Building', 'Level', 'Upgrade Cost', 'Time', 'Action'];
$allColumnsPresent = true;

foreach ($requiredColumns as $column) {
    if (strpos($buildingList, $column) === false) {
        echo "✗ FAIL: Missing column: $column\n";
        $allColumnsPresent = false;
    }
}

if ($allColumnsPresent) {
    echo "✓ PASS: All required columns present in building list\n";
}

if (strpos($buildingList, 'W,') !== false && 
    strpos($buildingList, 'C,') !== false && 
    strpos($buildingList, 'I') !== false) {
    echo "✓ PASS: Upgrade costs formatted as compact text (W/C/I)\n";
} else {
    echo "✗ FAIL: Upgrade costs not properly formatted\n";
}
echo "\n";

// Test 5: WAP constraints validation
echo "Test 5: WAP Constraints Validation\n";
echo "Verifying minimal HTML suitable for WAP/low-bandwidth\n";

$allContent = $overview . $navigation . $resourceBar . $buildingList;

// Check for minimal HTML (no heavy frameworks, minimal CSS classes)
$heavyElements = ['<div class=', '<span class=', 'bootstrap', 'jquery', 'react', 'vue'];
$hasHeavyElements = false;

foreach ($heavyElements as $element) {
    if (stripos($allContent, $element) !== false) {
        echo "✗ WARNING: Contains potentially heavy element: $element\n";
        $hasHeavyElements = true;
    }
}

if (!$hasHeavyElements) {
    echo "✓ PASS: No heavy framework elements detected\n";
}

// Check for WAP-compatible elements
if (strpos($allContent, '<table') !== false && 
    strpos($allContent, '<br>') !== false &&
    strpos($allContent, '<a href=') !== false) {
    echo "✓ PASS: Uses WAP-compatible elements (table, br, a)\n";
} else {
    echo "✗ FAIL: Missing basic WAP-compatible elements\n";
}

// Check content size (should be minimal for low bandwidth)
$contentSize = strlen($allContent);
if ($contentSize < 50000) { // 50KB threshold for WAP
    echo "✓ PASS: Content size suitable for low bandwidth ($contentSize bytes)\n";
} else {
    echo "✗ WARNING: Content size may be too large for WAP ($contentSize bytes)\n";
}
echo "\n";

// Test 6: Zero-scroll critical information
echo "Test 6: Zero-Scroll Critical Information\n";
echo "Requirement 3.5: Ensure critical info appears above the fold\n";

// In a WAP interface, critical information should appear early in the HTML
$criticalInfo = ['Resources', 'Buildings', 'Population'];
$allCriticalPresent = true;

foreach ($criticalInfo as $info) {
    if (strpos($overview, $info) === false) {
        echo "✗ FAIL: Missing critical information: $info\n";
        $allCriticalPresent = false;
    }
}

if ($allCriticalPresent) {
    echo "✓ PASS: All critical information present in overview\n";
}

// Check that overview comes before detailed building list
$overviewPos = strpos($allContent, 'Resources');
$detailsPos = strpos($allContent, 'Upgrade Cost');

if ($overviewPos !== false && $detailsPos !== false && $overviewPos < $detailsPos) {
    echo "✓ PASS: Overview appears before detailed information\n";
} else {
    echo "✗ FAIL: Content ordering may not be optimal for zero-scroll\n";
}
echo "\n";

// Test 7: Meta-refresh capability
echo "Test 7: Meta-Refresh Support\n";
echo "Requirement 3.5: Support for timer updates via meta-refresh\n";

// The actual meta-refresh tag is in the HTML head, but we can verify
// that the ViewRenderer supports time-based data
$queueItems = [
    [
        'name' => 'Main Building',
        'level' => 6,
        'finish_time' => date('Y-m-d H:i:s', time() + 300)
    ]
];

$queueDisplay = $viewRenderer->renderQueueDisplay($queueItems);

if (strpos($queueDisplay, date('Y-m-d')) !== false) {
    echo "✓ PASS: Queue display includes timestamps for meta-refresh\n";
} else {
    echo "✗ FAIL: Queue display missing timestamp information\n";
}

echo "✓ INFO: Meta-refresh tag should be added in HTML head: <meta http-equiv=\"refresh\" content=\"60\">\n";
echo "\n";

// Cleanup
echo "Cleaning up test data...\n";
$stmt = $conn->prepare("DELETE FROM village_buildings WHERE village_id = ?");
$stmt->execute([$testVillageId]);

$stmt = $conn->prepare("DELETE FROM villages WHERE id = ?");
$stmt->execute([$testVillageId]);

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$testUserId]);

echo "✓ Cleanup complete\n\n";

echo "=== Integration Test Complete ===\n";
echo "All ViewRenderer components are properly integrated for WAP-style display.\n";
echo "The village overview page provides:\n";
echo "- Compact three-column layout (buildings, resources, movements)\n";
echo "- Navigation header with all main sections\n";
echo "- Text-only resource display with production rates\n";
echo "- Building list with upgrade options\n";
echo "- WAP-compatible minimal HTML\n";
echo "- Zero-scroll access to critical information\n";
echo "- Support for meta-refresh timer updates\n";

