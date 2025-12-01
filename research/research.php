<?php
session_start();
require_once '../config/config.php';
require_once '../lib/Database.php';
require_once '../lib/managers/ResearchManager.php';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/functions.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Connect
$db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $db->getConnection();
$rm = new ResearchManager($conn);

// Determine default village
$stmt = $conn->prepare("SELECT id, name FROM villages WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$villageData = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$villageData) {
    header("Location: ../player/create_village.php");
    exit();
}
$village_id = $villageData['id'];
$village_name = $villageData['name'];

// Fetch research data
$levels = $rm->getVillageResearchLevels($village_id);
$queue = $rm->getResearchQueue($village_id);
$available = $rm->getResearchTypesForBuilding('academy');

$db->closeConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research - Tribal Wars New Edition</title>
    <link rel="stylesheet" href="../css/main.css?v=<?php echo time(); ?>">
    <script src="/js/main.js?v=<?php echo time(); ?>"></script>
</head>
<body>
<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">Research</span>
            <span class="game-name">Research</span>
        </div>
        <div class="header-user">Welcome, <b><?php echo htmlspecialchars($username); ?></b></div>
    </header>
    <div id="main-content">
        <nav id="sidebar">
            <ul>
                <li><a href="../game/game.php">Village</a></li>
                <li><a href="../map/map.php">Map</a></li>
                <li><a href="../combat/attack.php">Attack</a></li>
                <li><a href="../messages/reports.php">Reports</a></li>
                <li><a href="../messages/messages.php">Messages</a></li>
                <li><a href="../player/ranking.php">Rankings</a></li>
                <li><a href="../player/settings.php">Settings</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
        <main>
            <h2>Research in village <?php echo htmlspecialchars($village_name); ?></h2>
            <!-- Research queue -->
            <section class="form-container">
                <h3>Research queue</h3>
                <?php if (!empty($queue)): ?>
                    <table class="upgrade-buildings-table">
                        <thead>
                            <tr><th>Research</th><th>Target level</th><th>Time remaining</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($queue as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['research_name']); ?></td>
                                <td><?php echo $item['level_after']; ?></td>
                                <td><span class="build-timer" data-ends-at="<?php echo strtotime($item['ends_at']); ?>"></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No research in the queue.</p>
                <?php endif; ?>
            </section>
            <!-- Available research -->
            <section class="form-container mt-3">
                <h3>Available research</h3>
                <form id="research-form">
                    <table class="upgrade-buildings-table">
                        <thead>
                            <tr><th>Research</th><th>Current level</th><th>Cost</th><th>Time</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($available as $internal => $r): 
                            $current = $levels[$internal] ?? 0;
                            $next = $current + 1;
                            $cost = $rm->getResearchCost($r['id'], $next);
                            $time = $rm->calculateResearchTime($r['id'], $next, $levels['academy'] ?? 0);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['name']); ?></td>
                                <td><?php echo $current; ?></td>
                                <td>Wood: <?php echo $cost['wood']; ?> Clay: <?php echo $cost['clay']; ?> Iron: <?php echo $cost['iron']; ?></td>
                                <td><?php echo gmdate('H:i:s', $time); ?></td>
                                <td><button type="button" class="btn btn-primary start-research" data-research-id="<?php echo $r['id']; ?>" data-next-level="<?php echo $next; ?>">Start</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </section>
        </main>
    </div>
</div>
<script>
// Initialize research timers
initializeBuildTimers();
// Handle clicking the start research button
document.querySelectorAll('.start-research').forEach(btn => {
    btn.addEventListener('click', function() {
        const researchId = this.dataset.researchId;
        const level = this.dataset.nextLevel;
        fetch('start_research.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `village_id=<?php echo $village_id; ?>&research_type_id=${researchId}`
        })
        .then(r=>r.json())
        .then(data => {
            if (data.success) window.location.reload();
            else alert(data.error);
        });
    });
});
</script>
</body>
</html> 
