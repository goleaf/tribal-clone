<?php
require 'init.php';
// If the user is logged in, move them to world selection or straight into the game
if (isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['world_id'])) {
        header('Location: world_select.php?redirect=game.php');
        exit();
    }
    header('Location: game.php');
    exit();
}

// --- DATA PROCESSING ---
// Fetch statistics for the homepage using prepared statements
$stats = [
    'worlds' => 0,
    'players' => 0,
    'villages' => 0
];

if ($conn) {
    // Number of worlds
    $stmt_worlds = $conn->prepare("SELECT COUNT(*) AS count FROM worlds");
    if ($stmt_worlds) {
        $stmt_worlds->execute();
        $result_worlds = $stmt_worlds->get_result();
        if ($row = $result_worlds->fetch_assoc()) {
            $stats['worlds'] = $row['count'];
        }
        $stmt_worlds->close();
    }
    
    // Number of players
    $stmt_players = $conn->prepare("SELECT COUNT(*) AS count FROM users");
     if ($stmt_players) {
        $stmt_players->execute();
        $result_players = $stmt_players->get_result();
        if ($row = $result_players->fetch_assoc()) {
            $stats['players'] = $row['count'];
        }
        $stmt_players->close();
    }
    
    // Number of villages
    $stmt_villages = $conn->prepare("SELECT COUNT(*) AS count FROM villages");
     if ($stmt_villages) {
        $stmt_villages->execute();
        $result_villages = $stmt_villages->get_result();
        if ($row = $result_villages->fetch_assoc()) {
            $stats['villages'] = $row['count'];
        }
        $stmt_villages->close();
    }
}

// --- PRESENTATION (HTML) ---
$pageTitle = 'Tribal Wars - New Version';
require 'header.php';
?>
<main class="homepage">
    <section class="hero" style="background-image: url('img/village_bg.jpg');">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h2>Welcome to the new version of Tribal Wars!</h2>
            <p>Discover a modern take on Tribal Wars with dynamic gameplay and strategic challenges.</p>
            <div class="hero-buttons">
                <a href="auth/register.php" class="btn btn-primary">Register</a>
                <a href="auth/login.php" class="btn btn-secondary">Log in</a>
            </div>
        </div>
    </section>
    
    <section class="stats-bar">
        <div class="stat-item">
            <span class="stat-number"><?= number_format($stats['worlds']) ?></span>
            <span class="stat-label">Worlds</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= number_format($stats['players']) ?></span>
            <span class="stat-label">Players</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= number_format($stats['villages']) ?></span>
            <span class="stat-label">Villages</span>
        </div>
    </section>
    
        <section class="features">
            <h2>Key Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="img/ds_graphic/resources.png" alt="Resources">
                    </div>
                    <h3>Resource Production</h3>
                    <p>Manage the extraction of wood, clay, and iron in real time. Expand your resource sites to boost production.</p>
                    <a href="auth/register.php" class="feature-link">Start Producing</a>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="img/ds_graphic/buildings/main.png" alt="Buildings">
                    </div>
                    <h3>Village Development</h3>
                    <p>Build and upgrade structures to strengthen your position. Each building unlocks new options and advantages.</p>
                    <a href="auth/register.php" class="feature-link">Build Your Empire</a>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="img/ds_graphic/map/map.png" alt="World Map">
                    </div>
                    <h3>Interactive Map</h3>
                    <p>Explore a draggable world map. Plan strategic attacks and conquer new territories.</p>
                    <a href="auth/register.php" class="feature-link">Explore the World</a>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="img/ds_graphic/unit/spear.png" alt="Units">
                    </div>
                    <h3>Combat Units</h3>
                    <p>Recruit different unit types and attack enemies. Build powerful armies and defend your territory.</p>
                    <a href="auth/register.php" class="feature-link">Build an Army</a>
                </div>
            </div>
        </section>
    
    <section class="game-description">
        <div class="description-container">
            <h2>Strategic Browser Game</h2>
            <p>Tribal Wars is a strategy game where, as the leader of a small village, you must grow your territory, gather resources, and build an army. Forge alliances with other players, conquer new lands, and become the most powerful ruler in the world of Tribes!</p>
            
            <h3>Core gameplay elements:</h3>
            <ul class="feature-list">
                <li><strong>Economic growth</strong> - build resource sites and increase production</li>
                <li><strong>Territorial expansion</strong> - establish new villages and grow your empire</li>
                <li><strong>Military</strong> - train varied units and wage wars</li>
                <li><strong>Diplomacy</strong> - forge alliances and cooperate with other players</li>
            </ul>
            
            <div class="cta-box">
                <h3>Join the game now!</h3>
                <p>Registration takes just a moment, and the game is completely free.</p>
                <a href="auth/register.php" class="btn btn-primary">Start Playing</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
    <div class="footer-content">
        <div class="footer-logo">
            <h3>Tribal Wars</h3>
            <p>Strategic browser game</p>
        </div>
        <div class="footer-links">
            <h4>Quick links</h4>
            <ul>
                <li><a href="auth/register.php">Register</a></li>
                <li><a href="auth/login.php">Login</a></li>
                <li><a href="#">Help</a></li>
                <li><a href="#">Terms</a></li>
            </ul>
        </div>
        <div class="footer-info">
            <h4>About the project</h4>
            <p>This version of Tribal Wars is a modern implementation of the classic strategy game. The project is built with PHP, MySQL, HTML5, and CSS3.</p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Tribal Wars. All rights reserved.</p>
    </div>
    </footer>

<?php
// Close the database connection after rendering the page (or at the end of the script)
// init.php opens the connection, so it can be closed here.
if (isset($database)) {
    $database->closeConnection();
}
?>
