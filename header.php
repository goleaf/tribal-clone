<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/lib/functions.php';
// If user is logged in, prepare resource display
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/lib/managers/VillageManager.php';
    require_once __DIR__ . '/lib/managers/ResourceManager.php'; // Needed for production calculations
    require_once __DIR__ . '/lib/managers/BuildingManager.php'; // Correct path
    require_once __DIR__ . '/lib/managers/BuildingConfigManager.php'; // Correct path
    require_once __DIR__ . '/lib/managers/NotificationManager.php'; // For notifications

    $vm = new VillageManager($conn);
    
    // Instantiate building managers
    $bcm = new BuildingConfigManager($conn);
    $bm = new BuildingManager($conn, $bcm); // BuildingManager needs the connection and config manager

    // Instantiate ResourceManager
    $rm = new ResourceManager($conn, $bm); // ResourceManager needs the connection and BuildingManager

    // Ensure resources are up to date
    $firstVidData = $vm->getFirstVillage($_SESSION['user_id']);
    if ($firstVidData) {
        $village_id = $firstVidData['id'];
        $vm->updateResources($village_id); // Updates resources in the database
        
        // Fetch the latest village data after resource update
        $currentRes = $vm->getVillageInfo($village_id); // Basic village info

        // Get hourly production rates and attach to $currentRes
        if ($currentRes) {
            $productionRates = $rm->getProductionRates($village_id); // Use ResourceManager::getProductionRates
            $currentRes['wood_production_per_hour'] = $productionRates['wood'] ?? 0;
            $currentRes['clay_production_per_hour'] = $productionRates['clay'] ?? 0;
            $currentRes['iron_production_per_hour'] = $productionRates['iron'] ?? 0;
        }

    } else {
        // User logged in but has no village - should not happen if registration works,
        // but handle defensively
        // Maybe redirect to create_village.php if not already there?
         $currentRes = null;
         $firstVidData = null;
    }

    // Fetch unread notifications for the user
    $unread_notifications = [];
    if (isset($_SESSION['user_id'])) {
        // Ensure the autoloader works and NotificationManager is available
        $notificationManager = new NotificationManager($conn);
        $unread_notifications = $notificationManager->getNotifications($_SESSION['user_id'], true, 5);
    }
    $unread_count = count($unread_notifications);

}
// Ensure CSRF token is available
getCSRFToken();

if (!isset($pageTitle)) {
    $pageTitle = 'Tribal Wars';
}

// Set a permissive-but-explicit Content Security Policy and allow eval for legacy scripts
$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com",
    "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com",
    "img-src 'self' data:",
    "font-src 'self' https://cdnjs.cloudflare.com data:",
    "connect-src 'self'",
    "frame-ancestors 'self'"
];
header('Content-Security-Policy: ' . implode('; ', $csp));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="/css/main.css">
    <?php if (basename($_SERVER['PHP_SELF']) === 'index.php'): ?>
    <link rel="stylesheet" href="/css/home.css">
    <?php endif; ?>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    
    <!-- JavaScript files -->
    <?php if (isset($_SESSION['user_id'])): ?>
    <script>
        // Set a global JavaScript variable with the village ID
        window.currentVillageId = <?= json_encode($firstVidData['id'] ?? null) ?>;
    </script>
    <?php endif; ?>
    <script src="/js/utils.js" defer></script>

    <?php
    // Get messages from the session and pass them to JavaScript
    $gameMessages = [];
    if (isset($_SESSION['game_messages'])) {
        $gameMessages = $_SESSION['game_messages'];
        unset($_SESSION['game_messages']); // Clear after reading
    }
    ?>
    <script>
        window.gameMessages = <?= json_encode($gameMessages) ?>;
    </script>
    <?php if (isset($_SESSION['user_id'])): ?>
<?php $assetVersion = 'v3'; ?>
<script src="/js/resources.js?<?= $assetVersion ?>" defer></script>
<script src="/js/notifications.js?<?= $assetVersion ?>" defer></script>
    <?php endif; ?>
    <script src="/js/main.js" defer></script>
</head>
<body>
    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
    
    <header class="site-header">
        <div class="logo">
            <h1>Tribal Wars</h1>
        </div>
        <button class="mobile-nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
            <span class="sr-only">Toggle navigation</span>
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>
        <nav id="primary-nav" class="main-nav">
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Navigation for logged-in users -->
                <a href="/game/game.php" class="<?= $current_page === 'game.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Overview</a>
                <a href="/map/map.php" class="<?= $current_page === 'map.php' ? 'active' : '' ?>"><i class="fas fa-map"></i> Map</a>
                <a href="/messages/reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>"><i class="fas fa-scroll"></i> Reports</a>
                <a href="/messages/messages.php" class="<?= $current_page === 'messages.php' ? 'active' : '' ?>"><i class="fas fa-envelope"></i> Messages</a>
                <a href="/player/tribe.php" class="<?= $current_page === 'tribe.php' ? 'active' : '' ?>"><i class="fas fa-users"></i> Tribe</a>
                <a href="/player/ranking.php" class="<?= $current_page === 'ranking.php' ? 'active' : '' ?>"><i class="fas fa-trophy"></i> Rankings</a>
                <a href="/guides.php" class="<?= $current_page === 'guides.php' ? 'active' : '' ?>"><i class="fas fa-book-open"></i> Guides</a>
                <a href="/player/achievements.php" class="<?= $current_page === 'achievements.php' ? 'active' : '' ?>"><i class="fas fa-medal"></i> Achievements</a>
                <a href="/player/settings.php" class="<?= $current_page === 'settings.php' ? 'active' : '' ?>"><i class="fas fa-cog"></i> Settings</a>
                <a href="/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Log out</a>
                
                <div class="notifications-icon">
                    <a href="#" id="notifications-toggle">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge" id="notification-count"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </a>
                    <div id="notifications-dropdown" class="dropdown-content">
                        <h3>Notifications</h3>
                        <div id="notifications-list">
                        <?php if (empty($unread_notifications)): ?>
                            <div class="no-notifications">No new notifications</div>
                        <?php else: ?>
                            <ul class="notifications-list-items">
                                <?php foreach ($unread_notifications as $notification): ?>
                                    <li class="notification-item notification-<?= htmlspecialchars($notification['type']) ?>" data-id="<?= $notification['id'] ?>">
                                        <div class="notification-icon">
                                            <i class="fas fa-<?= $notification['type'] === 'success' ? 'check-circle' : ($notification['type'] === 'error' ? 'exclamation-circle' : ($notification['type'] === 'info' ? 'info-circle' : 'bell')) ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                                            <div class="notification-time"><?= relativeTime(strtotime($notification['created_at'])) ?></div>
                                        </div>
                                        <button class="mark-read-btn" data-id="<?= $notification['id'] ?>" title="Mark as read">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        </div>
                        <div class="notifications-footer">
                            <a href="#" id="mark-all-read">Mark all as read</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Navigation for guests (homepage) -->
                <a href="/index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a>
                <a href="/guides.php" class="<?= $current_page === 'guides.php' ? 'active' : '' ?>">Guides</a>
                <a href="/auth/register.php" class="<?= $current_page === 'register.php' ? 'active' : '' ?>">Register</a>
                <a href="/auth/login.php" class="<?= $current_page === 'login.php' ? 'active' : '' ?>">Log in</a>
            <?php endif; ?>
        </nav>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="header-world">
                World: <?= htmlspecialchars(getCurrentWorldName($conn)) ?>
            </div>
        <?php endif; ?>
    </header>
    
    <?php if (isset($_SESSION['user_id']) && isset($currentRes) && $currentRes !== null): ?>
    <div id="resource-bar" class="resource-bar" data-village-id="<?= $firstVidData['id'] ?>">
        <ul>
            <li class="resource-wood">
                <?= displayResource('wood', $currentRes['wood'], true, $currentRes['warehouse_capacity']) ?>
                <span class="resource-production" id="prod-wood">+<?= formatNumber($currentRes['wood_production_per_hour']) ?>/h</span>
            </li>
            <li class="resource-clay">
                <?= displayResource('clay', $currentRes['clay'], true, $currentRes['warehouse_capacity']) ?>
                 <span class="resource-production" id="prod-clay">+<?= formatNumber($currentRes['clay_production_per_hour']) ?>/h</span>
            </li>
            <li class="resource-iron">
                <?= displayResource('iron', $currentRes['iron'], true, $currentRes['warehouse_capacity']) ?>
                 <span class="resource-production" id="prod-iron">+<?= formatNumber($currentRes['iron_production_per_hour']) ?>/h</span>
            </li>
            <li class="resource-population">
                <?= displayResource('population', $currentRes['population'], true, $currentRes['farm_capacity']) ?>
            </li>
        </ul>
    </div>
<?php endif; ?>
