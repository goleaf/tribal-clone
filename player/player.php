<?php
require '../init.php';

// require_once 'config/config.php'; // Remove manual DB connection
// require_once 'lib/Database.php'; // Remove manual DB connection
require_once __DIR__ . '/../lib/managers/UserManager.php'; // Include UserManager
require_once __DIR__ . '/../lib/managers/VillageManager.php'; // Include VillageManager
require_once __DIR__ . '/../lib/managers/RankingManager.php'; // Include RankingManager
require_once __DIR__ . '/../lib/functions.php'; // For formatNumber

// Check whether a user ID or username was provided in the URL
$player_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$username_get = isset($_GET['user']) ? trim($_GET['user']) : '';

// If neither ID nor username is provided, redirect to player rankings
if ($player_id <= 0 && empty($username_get)) {
    header("Location: ranking.php?type=players");
    exit();
}

// Use global $conn from init.php
if (!$conn) {
    // Handle database connection error (though init.php should handle this)
    // For now, a simple error message
    die('Failed to connect to the database.'); // Consider better error handling
}

// Initialize managers
$userManager = new UserManager($conn);
$villageManager = new VillageManager($conn);
$rankingManager = new RankingManager($conn);

$user = null;

// Fetch player data by ID or username
if ($player_id > 0) {
    $user = $userManager->getUserById($player_id); // Assuming getUserById method exists
} elseif (!empty($username_get)) {
    $user = $userManager->getUserByUsername($username_get); // Assuming getUserByUsername method exists
}

// Verify that the player was found
if (!$user) {
    $pageTitle = 'Player does not exist';
    require '../header.php';
    // Use standard game layout divs
    echo '<div id="game-container"><div id="main-content"><main><h2>Player does not exist</h2><p>The player profile was not found.</p><a href="ranking.php?type=players" class="btn btn-secondary mt-3">Back to rankings</a></main></div></div>';
    require '../footer.php';
    exit;
}

$user_id = $user['id'];
$username = $user['username'];

// Get the player's villages via VillageManager
$villages = $villageManager->getUserVillages($user_id);

// Get the player's rank via RankingManager
// RankingManager::getPlayerRank and getTotalPlayersCount are assumed to exist
$playerRank = $rankingManager->getPlayerRank($user_id); // Assuming this method returns rank number
$totalPlayers = $rankingManager->getTotalPlayersCount(); // Assuming this method returns total count

// General statistics (add methods to managers if needed)
// $total_users = $rankingManager->getTotalUsersCount(); // Example
// $total_villages = $villageManager->getTotalVillagesCount(); // Example

// For now, keep direct queries for totals if managers don't have them
$res = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $res ? $res->fetch_assoc()['total'] : 0;
$res = $conn->query("SELECT COUNT(*) as total FROM villages");
$total_villages = $res ? $res->fetch_assoc()['total'] : 0;

// $database->closeConnection(); // Remove manual DB close

$pageTitle = 'Player profile: ' . htmlspecialchars($username);
require '../header.php';
?>

<div id="game-container">
    <?php // Add the header section similar to other pages if needed, or rely on header.php ?>
    <!-- Example header structure -->
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">&#128100;</span>
            <span>Player profile</span>
        </div>
        <?php /* User section will be included by header.php if logic is there */ ?>
        <?php // Manual user section if header.php doesn't handle it based on context ?>
        <?php if (isset($_SESSION['user_id']) && ($currentUserVillage = $villageManager->getFirstVillage($_SESSION['user_id']))): ?>
         <div class="header-user">
             Player: <?= htmlspecialchars($_SESSION['username']) ?><br>
             <span class="village-name-display" data-village-id="<?= $currentUserVillage['id'] ?>"><?= htmlspecialchars($currentUserVillage['name']) ?> (<?= $currentUserVillage['x_coord'] ?>|<?= $currentUserVillage['y_coord'] ?>)</span>
         </div>
        <?php endif; ?>
    </header>

    <div id="main-content">
        <main>
            <h2>Player profile: <?php echo htmlspecialchars($username); ?></h2>
            <div class="summary-box">
                <b>Player registration date:</b> <?php echo isset($user['registration_date']) ? htmlspecialchars($user['registration_date']) : 'no data'; ?><br>
                <b>Total players:</b> <?php echo $total_users; ?><br>
                <b>Total villages in the game:</b> <?php echo $total_villages; ?>
            </div>
            <p><b>Number of villages:</b> <?php echo count($villages); ?></p>
            <p><b>Ranking position:</b> <?php echo $playerRank; ?> / <?php echo $totalPlayers; ?></p>
            <div class="villages-list">
                <h3>Village list</h3>
                <?php if (count($villages) === 0): ?>
                    <p class="no-villages">The player does not own any villages.</p>
                <?php else: ?>
                <table>
                    <tr><th>Village name</th><th>Coordinates</th><th></th></tr>
                    <?php foreach ($villages as $v): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($v['name']); ?></td>
                            <td>(<?php echo $v['x_coord']; ?>|<?php echo $v['y_coord']; ?>)</td>
                            <td><a href="../map/map.php?center_x=<?php echo $v['x_coord']; ?>&center_y=<?php echo $v['y_coord']; ?>" class="btn btn-secondary" style="padding:4px 10px; font-size:0.95em; margin:0;">Show on map</a></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
            <a href="ranking.php?type=players" class="btn btn-secondary mt-3">Back to rankings</a>
        </main>
    </div>
</div>

<?php require '../footer.php'; ?> 
