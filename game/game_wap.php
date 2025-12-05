<?php
/**
 * WAP-style Village Overview
 * Implements Requirements 3.1, 3.2, 3.3, 3.4, 3.5
 * 
 * Displays:
 * - Compact HTML table with buildings (left), resources (center), movements (right)
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
require_once __DIR__ . '/../lib/functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Commander';

// Initialize managers
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$villageManager = new VillageManager($conn);
$resourceManager = new ResourceManager($conn, $buildingManager);
$unitManager = new UnitManager($conn);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);
$viewRenderer = new ViewRenderer($conn, $buildingManager, $resourceManager);

// Get village
$village = $villageManager->getFirstVillage($userId);

if (!$village) {
    header('Location: /player/create_village.php');
    exit();
}

$villageId = (int)$village['id'];

// Process completed tasks
$messages = $villageManager->processCompletedTasksForVillage($villageId);
$message = implode('', $messages);

// Process completed attacks
$attackMessages = $battleManager->processCompletedAttacks($userId);
if (!empty($attackMessages)) {
    $message .= implode('', $attackMessages);
}

// Refresh village data after processing
$village = $villageManager->getVillageInfo($villageId);

// Update resources (offline gains)
$village = $resourceManager->updateVillageResources($village);

// Get building data
$buildingsData = $buildingManager->getVillageBuildingsViewData($villageId, 0);

// Get production rates
$productionRates = $resourceManager->getProductionRates($villageId);

// Add production rates to village data for ViewRenderer
$village['wood_rate'] = $productionRates['wood'] ?? 0;
$village['clay_rate'] = $productionRates['clay'] ?? 0;
$village['iron_rate'] = $productionRates['iron'] ?? 0;

// Get movements (incoming and outgoing attacks)
$movements = [
    'incoming' => [],
    'outgoing' => []
];

// Query incoming attacks
$stmtIncoming = $conn->prepare("
    SELECT a.*, 
           sv.name as source_name, sv.x_coord as source_x, sv.y_coord as source_y,
           tv.name as target_name, tv.x_coord as target_x, tv.y_coord as target_y
    FROM attacks a
    LEFT JOIN villages sv ON a.source_village_id = sv.id
    LEFT JOIN villages tv ON a.target_village_id = tv.id
    WHERE a.target_village_id = ? 
      AND a.arrival_time > datetime('now')
      AND a.status = 'active'
    ORDER BY a.arrival_time ASC
    LIMIT 10
");
$stmtIncoming->execute([$villageId]);
$incomingAttacks = $stmtIncoming->fetchAll(PDO::FETCH_ASSOC);

foreach ($incomingAttacks as $attack) {
    $movements['incoming'][] = [
        'origin' => sprintf(
            '%s (%d|%d)',
            $attack['source_name'] ?? 'Unknown',
            (int)($attack['source_x'] ?? 0),
            (int)($attack['source_y'] ?? 0)
        ),
        'arrival_time' => $attack['arrival_time'],
        'attack_type' => ucfirst($attack['attack_type'] ?? 'Attack')
    ];
}

// Query outgoing attacks
$stmtOutgoing = $conn->prepare("
    SELECT a.*, 
           sv.name as source_name, sv.x_coord as source_x, sv.y_coord as source_y,
           tv.name as target_name, tv.x_coord as target_x, tv.y_coord as target_y
    FROM attacks a
    LEFT JOIN villages sv ON a.source_village_id = sv.id
    LEFT JOIN villages tv ON a.target_village_id = tv.id
    WHERE a.source_village_id = ? 
      AND a.arrival_time > datetime('now')
      AND a.status = 'active'
    ORDER BY a.arrival_time ASC
    LIMIT 10
");
$stmtOutgoing->execute([$villageId]);
$outgoingAttacks = $stmtOutgoing->fetchAll(PDO::FETCH_ASSOC);

foreach ($outgoingAttacks as $attack) {
    $movements['outgoing'][] = [
        'destination' => sprintf(
            '%s (%d|%d)',
            $attack['target_name'] ?? 'Unknown',
            (int)($attack['target_x'] ?? 0),
            (int)($attack['target_y'] ?? 0)
        ),
        'arrival_time' => $attack['arrival_time'],
        'attack_type' => ucfirst($attack['attack_type'] ?? 'Attack')
    ];
}

// Get building queue
$activeUpgrades = array_filter($buildingsData, static fn($b) => !empty($b['is_upgrading']));
$queueItems = [];
foreach ($activeUpgrades as $upgrade) {
    $queueItems[] = [
        'name' => $upgrade['name'],
        'level' => $upgrade['queue_level_after'],
        'finish_time' => $upgrade['queue_finish_time']
    ];
}

// Get recruitment queues
$recruitQueues = $unitManager->getRecruitmentQueues($villageId);

// Page title
$pageTitle = htmlspecialchars($village['name']) . ' - Village Overview (WAP)';

// Meta refresh for auto-update (every 60 seconds)
$metaRefresh = '<meta http-equiv="refresh" content="60">';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php echo $metaRefresh; ?>
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
            width: 100%;
        }
        th {
            background: #d9c4a7;
            font-weight: bold;
            padding: 4px;
            text-align: left;
        }
        td {
            padding: 4px;
            vertical-align: top;
        }
        a {
            color: #0066cc;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
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
        .header {
            background: #8d5c2c;
            color: white;
            padding: 10px;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 16px;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 11px;
        }
        .footer {
            margin-top: 20px;
            padding: 10px;
            background: #e5e5e5;
            font-size: 10px;
            text-align: center;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <h1><?php echo htmlspecialchars($village['name']); ?> (<?php echo (int)$village['x_coord']; ?>|<?php echo (int)$village['y_coord']; ?>)</h1>
    <p>Commander: <?php echo htmlspecialchars($username); ?> | Server Time: <?php echo date('Y-m-d H:i:s'); ?></p>
</div>

<!-- Navigation -->
<?php echo $viewRenderer->renderNavigation(); ?>

<!-- Messages -->
<?php if (!empty($message)): ?>
<div class="message success">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Village Overview Table (Requirement 3.1) -->
<h2>Village Overview</h2>
<?php echo $viewRenderer->renderVillageOverview($village, $buildingsData, $movements); ?>

<!-- Building Queue -->
<?php if (!empty($queueItems)): ?>
<h3>Construction Queue</h3>
<?php echo $viewRenderer->renderQueueDisplay($queueItems); ?>
<?php endif; ?>

<!-- Recruitment Queue -->
<?php if (!empty($recruitQueues)): ?>
<h3>Recruitment Queue</h3>
<?php echo $viewRenderer->renderRecruitmentQueues($recruitQueues); ?>
<?php endif; ?>

<!-- Building List (Requirement 3.2) -->
<h2>Buildings</h2>
<?php echo $viewRenderer->renderBuildingList($buildingsData, $village); ?>

<!-- Quick Links -->
<h3>Quick Actions</h3>
<p>
    <a href="/map/map.php">Map</a> |
    <a href="/units/recruit_wap.php?village_id=<?php echo $villageId; ?>">Recruit Units</a> |
    <a href="/ajax/trade/get_market_data.php">Market</a> |
    <a href="/research/research.php">Research</a> |
    <a href="/messages/reports.php">Reports</a> |
    <a href="/messages/messages.php">Messages</a> |
    <a href="/player/player.php">Profile</a>
</p>

<!-- Switch to Modern View -->
<p>
    <a href="/game/game.php">Switch to Modern View</a>
</p>

<!-- Footer -->
<div class="footer">
    <p>WAP-style interface | Auto-refresh: 60 seconds | <a href="?">Manual Reload</a></p>
    <p>Minimal bandwidth mode - suitable for low-end devices and slow connections</p>
</div>

</body>
</html>
