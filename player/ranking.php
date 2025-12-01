<?php
declare(strict_types=1);
require '../init.php';
require_once __DIR__ . '/../lib/managers/RankingManager.php'; // Updated path
require_once __DIR__ . '/../lib/managers/VillageManager.php'; // Updated path

// Access control - only for logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Initialize the village manager (needed for header.php)
$villageManager = new VillageManager($conn);
$village_id = $villageManager->getFirstVillage($user_id);
$village = $villageManager->getVillageInfo($village_id);

// Ranking type (players or tribes)
$ranking_type = isset($_GET['type']) ? $_GET['type'] : 'players';
$valid_ranking_types = ['players', 'tribes']; // Add 'tribes'
if (!in_array($ranking_type, $valid_ranking_types)) {
    $ranking_type = 'players'; // Default to players if invalid type
}

// Current page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Initialize RankingManager
$rankingManager = new RankingManager($conn);

$total_records = 0;
$ranking_data = [];
$totalPages = 1;
$unreadCount = 0; // Placeholder for future tribe ranking states

// Fetch ranking data and total record count depending on the type
if ($ranking_type === 'players') {
    $total_records = $rankingManager->getTotalPlayersCount();
    if ($total_records > 0) {
        $ranking_data = $rankingManager->getPlayersRanking($per_page, $offset);
    }
    $pageTitle = 'Player Rankings';
} elseif ($ranking_type === 'tribes') {
    $total_records = $rankingManager->getTotalTribesCount(); // This will currently return 0
    if ($total_records > 0) {
         $ranking_data = $rankingManager->getTribesRanking($per_page, $offset); // This will currently return []
    }
    $pageTitle = 'Tribe Rankings';
}

// Calculate total pages after fetching total_records
if ($total_records > 0) {
     $totalPages = ceil($total_records / $per_page);
     // Adjust current page and offset if it exceeds total pages (can happen after data changes)
     if ($page > $totalPages) {
          $page = $totalPages;
          $offset = ($page - 1) * $per_page;
          // Re-fetch data for the corrected page if needed (RankingManager handles offset)
           if ($ranking_type === 'players') {
                $ranking_data = $rankingManager->getPlayersRanking($per_page, $offset);
           } elseif ($ranking_type === 'tribes') {
                $ranking_data = $rankingManager->getTribesRanking($per_page, $offset);
           }
     }
} else {
     // If no records, ensure ranking_data is empty and totalPages is 1
     $ranking_data = [];
     $totalPages = 1;
     $page = 1;
     $offset = 0;
}

// Calculate the starting rank number for the current page
$start_rank = $offset + 1;

// Add rank number to each row
if ($ranking_type === 'players') {
    $current_rank = $start_rank;
    foreach ($ranking_data as &$player) {
        $player['rank'] = $current_rank++;
    }
    unset($player); // Unset the reference
} elseif ($ranking_type === 'tribes') {
    $current_rank = $start_rank;
    foreach ($ranking_data as &$tribeRow) {
        $tribeRow['rank'] = $current_rank++;
    }
    unset($tribeRow);
}


require '../header.php';
?>

<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">&#127942;</span>
            <span>Rankings</span>
        </div>
        <div class="header-user">
            Player: <?= htmlspecialchars($username) ?><br>
            <?php if (isset($village) && $village): // Check if village data is available ?>
                <span class="village-name-display" data-village-id="<?= $village['id'] ?>"><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</span>
            <?php endif; ?>
        </div>
    </header>

    <div id="main-content">

        <main>
            <h2>Rankings</h2>

            <div class="ranking-tabs">
                <a href="?type=players" class="ranking-tab <?= $ranking_type == 'players' ? 'active' : '' ?>">Players</a>
                <a href="?type=tribes" class="ranking-tab <?= $ranking_type == 'tribes' ? 'active' : '' ?>">Tribes</a>
            </div>

            <div class="ranking-stats" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
                <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                    <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Total</div>
                    <div style="font-size:22px;font-weight:700;"><?= (int)$total_records ?></div>
                </div>
                <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                    <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Page</div>
                    <div style="font-size:22px;font-weight:700;"><?= $page ?> / <?= max(1, $totalPages) ?></div>
                </div>
                <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                    <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Per page</div>
                    <div style="font-size:22px;font-weight:700;"><?= $per_page ?></div>
                </div>
            </div>

            <div class="ranking-container">
                <?php if ($ranking_type === 'players'): ?>
                    <h3>Player Rankings</h3>

                    <?php if (count($ranking_data) > 0): ?>
                        <div class="ranking-filters" style="margin-bottom:12px;">
                            <a href="?type=players&sort=points" class="btn btn-secondary">Sort by points</a>
                            <a href="?type=players&sort=villages" class="btn btn-secondary">Sort by villages</a>
                        </div>
                        <table class="ranking-table">
                            <thead>
                                <tr>
                                    <th class="rank-column">Rank</th>
                                    <th>Player</th>
                                    <th>Villages</th>
                                    <th>Population</th>
                                    <th>Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ranking_data as $player): ?>
                                    <tr class="<?= $player['id'] == $user_id ? 'current-user' : '' ?>">
                                        <td class="rank-column"><?= $player['rank'] ?></td>
                                        <td><?= htmlspecialchars($player['username']) ?></td>
                                        <td><?= $player['village_count'] ?></td>
                                        <td><?= formatNumber($player['total_population']) ?></td>
                                        <td><?= formatNumber($player['points']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=1" class="page-link">&laquo;</a>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $page - 1 ?>" class="page-link">&lsaquo;</a>
                                <?php endif; ?>

                                <?php
                                // Pages to display
                                $start_page = max(1, $page - 2);
                                $end_page = min($start_page + 4, $totalPages);
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                if ($start_page > 1) {
                                    echo '<span class="page-ellipsis">...</span>';
                                }
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor;
                                if ($end_page < $totalPages) {
                                    echo '<span class="page-ellipsis">...</span>';
                                }
                                ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $page + 1 ?>" class="page-link">&rsaquo;</a>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $totalPages ?>" class="page-link">&raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="no-data">No player data to display</div>
                    <?php endif; ?>

                <?php elseif ($ranking_type === 'tribes'): ?>
                    <h3>Tribe Rankings</h3>
                    <?php if (count($ranking_data) > 0): ?>
                         <!-- TODO: Add tribes ranking table structure here -->
                         <table class="ranking-table">
                             <thead>
                                 <tr>
                                     <th class="rank-column">Rank</th>
                                     <th>Tribe</th>
                                     <th>Members</th>
                                     <th>Villages</th>
                                     <th>Points</th>
                                 </tr>
                             </thead>
                             <tbody>
                                  <?php // Example loop for tribes (currently $ranking_data is empty) ?>
<?php foreach ($ranking_data as $tribe): ?>
    <tr>
         <td class="rank-column"><?= $tribe['rank'] ?? '-' ?></td>
         <td>
             <span class="tribe-tag">[<?= htmlspecialchars($tribe['tag'] ?? '') ?>]</span>
             <?= htmlspecialchars($tribe['name']) ?>
         </td>
         <td><?= $tribe['member_count'] ?? 0 ?></td>
         <td><?= $tribe['village_count'] ?? 0 ?></td>
         <td><?= formatNumber($tribe['points'] ?? 0) ?></td>
    </tr>
<?php endforeach; ?>
                             </tbody>
                         </table>
                          <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=1" class="page-link">&laquo;</a>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $page - 1 ?>" class="page-link">&lsaquo;</a>
                                <?php endif; ?>

                                <?php
                                // Pages to display
                                $start_page = max(1, $page - 2);
                                $end_page = min($start_page + 4, $totalPages);

                                // Adjust range if near the start or end
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                }

                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $page + 1 ?>" class="page-link">&rsaquo;</a>
                                    <a href="?type=<?= $ranking_type ?>&page=<?= $totalPages ?>" class="page-link">&raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                         <div class="no-data">
                             The tribes system is under development or no data is available.
                         </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php require '../footer.php'; ?> 
