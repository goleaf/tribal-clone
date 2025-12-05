<?php
/**
 * WAP-style Recruitment Interface
 * Implements Requirements 4.1, 4.2, 4.3, 4.4, 4.5
 * 
 * Displays:
 * - Unit queues as text: "[Unit] ([Completed]/[Total] complete, [Time] remaining)"
 * - Recruitment costs as "Cost: [Wood]W, [Clay]C, [Iron]I, [Pop] Pop, Time: [Duration]"
 * - Unit statistics comparison table with Attack/Defense columns
 * - Quantity input boxes and "Recruit" buttons (no drag-and-drop)
 * - Ensures recruitment deducts resources and population, adds to training queue
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/ViewRenderer.php';
require_once __DIR__ . '/../lib/managers/ResourceManager.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Initialize managers
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$resourceManager = new ResourceManager($conn, $buildingManager);
$unitManager = new UnitManager($conn);
$villageManager = new VillageManager($conn);
$viewRenderer = new ViewRenderer($conn, $buildingManager, $resourceManager);

// Get village ID from request or use first village
$villageId = isset($_GET['village_id']) ? (int)$_GET['village_id'] : null;

if (!$villageId) {
    $village = $villageManager->getFirstVillage($userId);
    if (!$village) {
        die('No village found');
    }
    $villageId = (int)$village['id'];
} else {
    $village = $villageManager->getVillageInfo($villageId);
    if (!$village || (int)$village['user_id'] !== $userId) {
        die('Access denied');
    }
}

// Get building type from request (default to barracks)
$buildingType = isset($_GET['building']) ? trim($_GET['building']) : 'barracks';

// Validate building type
$validBuildings = ['barracks', 'stable', 'workshop', 'garage'];
if (!in_array($buildingType, $validBuildings, true)) {
    $buildingType = 'barracks';
}

// Get building level
$buildingLevel = $buildingManager->getBuildingLevel($villageId, $buildingType);

if ($buildingLevel === 0) {
    $buildingName = ucfirst($buildingType);
    die("$buildingName not built in this village");
}

// Get available units for this building
$availableUnits = $unitManager->getAvailableUnitsByBuilding($buildingType, $buildingLevel);

// Get recruitment queues for this building
$queues = $unitManager->getRecruitmentQueues($villageId, $buildingType);

// Update village resources (offline gains)
$village = $resourceManager->updateVillageResources($village);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruit Units - <?php echo htmlspecialchars($village['name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 10px;
            background: #f5f5f5;
        }
        table {
            background: white;
            border-collapse: collapse;
        }
        th {
            background: #d9c4a7;
            font-weight: bold;
            padding: 4px;
        }
        td {
            padding: 4px;
        }
        input[type="number"] {
            width: 60px;
        }
        input[type="submit"] {
            padding: 6px 12px;
            background: #8d5c2c;
            color: white;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background: #6d4c1c;
        }
        .message {
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ccc;
            background: #ffffcc;
        }
        .error {
            background: #ffcccc;
            border-color: #cc0000;
        }
        .success {
            background: #ccffcc;
            border-color: #00cc00;
        }
    </style>
</head>
<body>

<?php echo $viewRenderer->renderNavigation(); ?>

<h2><?php echo htmlspecialchars($village['name']); ?> - Recruit Units</h2>

<?php
// Display messages
if (isset($_SESSION['game_message'])) {
    echo $_SESSION['game_message'];
    unset($_SESSION['game_message']);
}
?>

<!-- Resource Display -->
<p><b>Resources:</b></p>
<?php
echo $viewRenderer->renderResourceBar(
    [
        'wood' => $village['wood'],
        'clay' => $village['clay'],
        'iron' => $village['iron']
    ],
    [
        'wood' => $resourceManager->getProductionRates($villageId)['wood'] ?? 0,
        'clay' => $resourceManager->getProductionRates($villageId)['clay'] ?? 0,
        'iron' => $resourceManager->getProductionRates($villageId)['iron'] ?? 0
    ],
    (int)$village['warehouse_capacity']
);
?>

<p><b>Population:</b> <?php echo (int)$village['population']; ?> / <?php echo (int)$village['farm_capacity']; ?></p>

<!-- Building Selection -->
<p><b>Select Building:</b></p>
<p>
<?php
$buildings = [
    'barracks' => 'Barracks',
    'stable' => 'Stable',
    'workshop' => 'Workshop'
];

$links = [];
foreach ($buildings as $type => $name) {
    $level = $buildingManager->getBuildingLevel($villageId, $type);
    if ($level > 0) {
        if ($type === $buildingType) {
            $links[] = sprintf('<b>%s (Lvl %d)</b>', htmlspecialchars($name), $level);
        } else {
            $links[] = sprintf(
                '<a href="?village_id=%d&building=%s">%s (Lvl %d)</a>',
                $villageId,
                $type,
                htmlspecialchars($name),
                $level
            );
        }
    }
}
echo implode(' | ', $links);
?>
</p>

<!-- Recruitment Queues -->
<?php
if (!empty($queues)) {
    echo $viewRenderer->renderRecruitmentQueues($queues);
}
?>

<!-- Unit Statistics Table -->
<h3>Available Units</h3>
<?php
if (!empty($availableUnits)) {
    echo $viewRenderer->renderUnitStatsTable($availableUnits);
} else {
    echo '<p>No units available at this building level</p>';
}
?>

<!-- Recruitment Form -->
<h3>Recruit Units</h3>
<?php
if (!empty($availableUnits)) {
    echo $viewRenderer->renderRecruitmentForm($availableUnits, $villageId, $buildingType, $buildingLevel);
} else {
    echo '<p>No units available for recruitment</p>';
}
?>

<p><a href="/game/game.php">Back to Village</a></p>

</body>
</html>
