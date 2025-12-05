<?php
declare(strict_types=1);
/**
 * WAP-style Battle Reports Archive
 * Minimalist text-based interface for viewing battle reports
 * Requirements: 6.4, 6.5, 6.6
 */
require '../init.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';
require_once __DIR__ . '/../lib/managers/ReportStateManager.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);
$reportStateManager = new ReportStateManager($conn);

// Process completed attacks
$battleManager->processCompletedAttacks($user_id);

// Pagination
$reportsPerPage = 20;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $reportsPerPage;

// Get total reports
$totalReports = $battleManager->getTotalBattleReportsForUser($user_id);
$totalPages = $totalReports > 0 ? ceil($totalReports / $reportsPerPage) : 1;

// Ensure current page is valid
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $reportsPerPage;
}

// Fetch reports
$reports = $battleManager->getBattleReportsForUser($user_id, $reportsPerPage, $offset);
foreach ($reports as &$report) {
    $state = $reportStateManager->getState((int)$report['report_id'], $user_id);
    $report['is_starred'] = $state['is_starred'] ?? 0;
    $report['is_read'] = $state['is_read'] ?? 0;
}
unset($report);

$unreadCount = $reportStateManager->countUnreadForUser($user_id);

// Get report details if requested
$report_details = null;
if (isset($_GET['report_id'])) {
    $report_id = (int)$_GET['report_id'];
    $result = $battleManager->getBattleReport($report_id, $user_id);
    
    if ($result['success']) {
        $reportStateManager->markRead($report_id, $user_id);
        $state = $reportStateManager->getState($report_id, $user_id);
        $report_details = $result['report'];
        $report_details['is_starred'] = $state['is_starred'];
        $report_details['is_read'] = $state['is_read'];
    }
}

$username = $_SESSION['username'];
$pageTitle = 'Battle Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 8px;
            background: #f5f5f5;
        }
        .header {
            background: #8b4513;
            color: white;
            padding: 4px 8px;
            margin-bottom: 8px;
        }
        .nav {
            background: #d2b48c;
            padding: 4px;
            margin-bottom: 8px;
        }
        .nav a {
            color: #000;
            text-decoration: none;
            margin-right: 8px;
        }
        .stats {
            background: #fff;
            border: 1px solid #ccc;
            padding: 4px 8px;
            margin-bottom: 8px;
        }
        .report-list {
            background: #fff;
            border: 1px solid #ccc;
            margin-bottom: 8px;
        }
        .report-item {
            border-bottom: 1px solid #eee;
            padding: 4px 8px;
        }
        .report-item.unread {
            background: #ffffcc;
            font-weight: bold;
        }
        .report-item a {
            color: #00f;
            text-decoration: none;
        }
        .report-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-right: 4px;
        }
        .report-details {
            background: #fff;
            border: 1px solid #ccc;
            padding: 8px;
            margin-bottom: 8px;
        }
        .report-details h3 {
            margin: 0 0 8px 0;
            font-size: 14px;
        }
        .report-details table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }
        .report-details th,
        .report-details td {
            border: 1px solid #ccc;
            padding: 4px;
            text-align: left;
        }
        .report-details th {
            background: #e0e0e0;
            font-weight: bold;
        }
        .winner {
            background: #d4edda;
        }
        .loser {
            background: #f8d7da;
        }
        .pagination {
            text-align: center;
            padding: 8px;
        }
        .pagination a {
            color: #00f;
            text-decoration: none;
            margin: 0 4px;
        }
        .pagination .current {
            font-weight: bold;
            color: #000;
        }
    </style>
</head>
<body>

<div class="header">
    <strong><?= htmlspecialchars($username) ?></strong> | Battle Reports
</div>

<div class="nav">
    <a href="../game/game.php">Village</a> |
    <a href="reports_wap.php">Reports</a> |
    <a href="messages.php">Messages</a> |
    <a href="../player/player.php">Profile</a>
</div>

<div class="stats">
    Total: <?= $totalReports ?> | Unread: <?= $unreadCount ?> | Page: <?= $currentPage ?>/<?= $totalPages ?>
</div>

<?php if ($report_details): ?>
<div class="report-details">
    <h3>
        <?php
        $type = $report_details['type'] ?? $report_details['attack_type'] ?? 'battle';
        $icon = 'scout.svg';
        if ($type !== 'spy') {
            $icon = $report_details['attacker_won'] ? 'victory.svg' : 'defeat.svg';
        }
        ?>
        <img src="../img/reports/<?= htmlspecialchars($icon) ?>" alt="<?= htmlspecialchars($type) ?>" class="report-icon">
        <?= $type === 'spy' ? 'Spy Report' : 'Battle Report' ?> #<?= $report_details['id'] ?>
    </h3>
    
    <p>
        <strong>From:</strong> <?= htmlspecialchars($report_details['attacker_name']) ?> 
        (<?= htmlspecialchars($report_details['source_village_name']) ?> 
        <?= $report_details['source_x'] ?>|<?= $report_details['source_y'] ?>)<br>
        <strong>To:</strong> <?= htmlspecialchars($report_details['defender_name']) ?> 
        (<?= htmlspecialchars($report_details['target_village_name']) ?> 
        <?= $report_details['target_x'] ?>|<?= $report_details['target_y'] ?>)<br>
        <strong>Time:</strong> <?= htmlspecialchars($report_details['battle_time']) ?>
    </p>

    <?php if ($type === 'spy'): ?>
        <?php
        $details = $report_details['details'] ?? [];
        $intel = $details['intel'] ?? [];
        ?>
        <p><strong>Mission:</strong> <?= $details['success'] ? 'Success' : 'Failed' ?></p>
        <p>Scouts sent: <?= $details['attacker_spies_sent'] ?? 0 ?>, lost: <?= $details['attacker_spies_lost'] ?? 0 ?></p>
        <p>Defender scouts: <?= $details['defender_spies'] ?? 0 ?>, lost: <?= $details['defender_spies_lost'] ?? 0 ?></p>
        
        <?php if (!empty($intel['resources'])): ?>
        <p><strong>Resources:</strong> 
            Wood: <?= $intel['resources']['wood'] ?? 0 ?>, 
            Clay: <?= $intel['resources']['clay'] ?? 0 ?>, 
            Iron: <?= $intel['resources']['iron'] ?? 0 ?>
        </p>
        <?php endif; ?>
        
        <?php if (!empty($intel['units'])): ?>
        <table>
            <tr><th>Unit</th><th>Count</th></tr>
            <?php foreach ($intel['units'] as $unit): ?>
            <tr>
                <td><?= htmlspecialchars($unit['name'] ?? $unit['internal_name'] ?? 'Unit') ?></td>
                <td><?= $unit['count'] ?? 0 ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
        
    <?php else: ?>
        <?php
        $details = $report_details['details'] ?? [];
        ?>
        
        <table>
            <tr>
                <th colspan="4" class="<?= $report_details['attacker_won'] ? 'winner' : 'loser' ?>">
                    Attacker <?= $report_details['attacker_won'] ? '(Victory)' : '(Defeat)' ?>
                </th>
            </tr>
            <tr>
                <th>Unit</th>
                <th>Sent</th>
                <th>Lost</th>
                <th>Remaining</th>
            </tr>
            <?php if (!empty($report_details['attacker_units'])): ?>
                <?php foreach ($report_details['attacker_units'] as $unit): ?>
                <tr>
                    <td><?= htmlspecialchars($unit['name'] ?? 'Unit') ?></td>
                    <td><?= $unit['initial_count'] ?? 0 ?></td>
                    <td><?= $unit['lost_count'] ?? 0 ?></td>
                    <td><?= $unit['remaining_count'] ?? 0 ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">No units</td></tr>
            <?php endif; ?>
        </table>

        <table>
            <tr>
                <th colspan="4" class="<?= $report_details['attacker_won'] ? 'loser' : 'winner' ?>">
                    Defender <?= $report_details['attacker_won'] ? '(Defeat)' : '(Victory)' ?>
                </th>
            </tr>
            <tr>
                <th>Unit</th>
                <th>Present</th>
                <th>Lost</th>
                <th>Remaining</th>
            </tr>
            <?php if (!empty($report_details['defender_units'])): ?>
                <?php foreach ($report_details['defender_units'] as $unit): ?>
                <tr>
                    <td><?= htmlspecialchars($unit['name'] ?? 'Unit') ?></td>
                    <td><?= $unit['initial_count'] ?? 0 ?></td>
                    <td><?= $unit['lost_count'] ?? 0 ?></td>
                    <td><?= $unit['remaining_count'] ?? 0 ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">No units</td></tr>
            <?php endif; ?>
        </table>

        <?php if (!empty($details['loot'])): ?>
        <p><strong>Loot:</strong> 
            Wood: <?= $details['loot']['wood'] ?? 0 ?>, 
            Clay: <?= $details['loot']['clay'] ?? 0 ?>, 
            Iron: <?= $details['loot']['iron'] ?? 0 ?>
        </p>
        <?php endif; ?>

        <?php if (!empty($details['loyalty'])): ?>
        <p><strong>Loyalty:</strong> 
            <?= $details['loyalty']['before'] ?? '?' ?> → <?= $details['loyalty']['after'] ?? '?' ?>
            <?php if ($details['loyalty']['drop'] ?? 0): ?>
                (<?= $details['loyalty']['drop'] > 0 ? '-' : '' ?><?= $details['loyalty']['drop'] ?>)
            <?php endif; ?>
            <?php if ($details['loyalty']['conquered'] ?? false): ?>
                <strong style="color: #c00;">Village conquered!</strong>
            <?php endif; ?>
        </p>
        <?php endif; ?>

        <?php if (!empty($details['wall_damage'])): ?>
        <p><strong>Wall:</strong> 
            Level <?= $details['wall_damage']['initial_level'] ?? '?' ?> → 
            <?= $details['wall_damage']['final_level'] ?? '?' ?>
        </p>
        <?php endif; ?>

        <?php if (!empty($details['building_damage'])): ?>
        <p><strong>Building Damage:</strong> 
            <?= htmlspecialchars($details['building_damage']['building_name'] ?? 'Target') ?>: 
            Level <?= $details['building_damage']['initial_level'] ?? '?' ?> → 
            <?= $details['building_damage']['final_level'] ?? '?' ?>
        </p>
        <?php endif; ?>

        <?php if (isset($details['morale']) || isset($details['attack_luck']) || isset($details['wall_level'])): ?>
        <p><strong>Battle Modifiers:</strong><br>
            <?php if (isset($details['morale'])): ?>
                Morale: <?= round($details['morale'] * 100) ?>%<br>
            <?php endif; ?>
            <?php if (isset($details['attack_luck'])): ?>
                Luck: <?= round(($details['attack_luck'] - 1) * 100) ?>%<br>
            <?php endif; ?>
            <?php if (isset($details['wall_level'])): ?>
                Wall: Level <?= $details['wall_level'] ?>
                <?php if (isset($details['effective_wall_level'])): ?>
                    (Effective: <?= $details['effective_wall_level'] ?>)
                <?php endif; ?>
                <br>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    <?php endif; ?>

    <p><a href="reports_wap.php">← Back to list</a></p>
</div>
<?php endif; ?>

<?php if (!empty($reports)): ?>
<div class="report-list">
    <?php foreach ($reports as $report): ?>
    <div class="report-item <?= empty($report['is_read']) ? 'unread' : '' ?>">
        <?php
        $type = $report['type'] ?? $report['attack_type'] ?? 'battle';
        $icon = 'scout.svg';
        if ($type !== 'spy') {
            $icon = $report['attacker_won'] ? 'victory.svg' : 'defeat.svg';
        }
        ?>
        <img src="../img/reports/<?= htmlspecialchars($icon) ?>" alt="<?= htmlspecialchars($type) ?>" class="report-icon">
        <a href="reports_wap.php?report_id=<?= $report['report_id'] ?>">
            <?= htmlspecialchars($report['source_village_name']) ?> (<?= $report['source_x'] ?>|<?= $report['source_y'] ?>) 
            → 
            <?= htmlspecialchars($report['target_village_name']) ?> (<?= $report['target_x'] ?>|<?= $report['target_y'] ?>)
        </a>
        <br>
        <small><?= htmlspecialchars($report['formatted_date']) ?></small>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($currentPage > 1): ?>
        <a href="reports_wap.php?page=<?= $currentPage - 1 ?>">« Prev</a>
    <?php endif; ?>
    
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $currentPage): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="reports_wap.php?page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($currentPage < $totalPages): ?>
        <a href="reports_wap.php?page=<?= $currentPage + 1 ?>">Next »</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="report-list">
    <div class="report-item">
        <p>No battle reports yet. Launch an attack to see reports here.</p>
    </div>
</div>
<?php endif; ?>

</body>
</html>
