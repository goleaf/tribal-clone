<?php
declare(strict_types=1);
// Start the session only if it is not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$IS_CLI = (php_sapi_name() === 'cli');

// Load configuration
require_once 'config/config.php';

// Load the autoloader
require_once 'lib/Autoloader.php';

// Load common helpers (contains getCSRFToken and validateCSRF)
require_once 'lib/functions.php';

// Initialize error handling
require_once 'lib/utils/ErrorHandler.php';
ErrorHandler::initialize();

// Global sanitization of GET/POST was removed.
// Validation and sanitization should happen at the point of use.
// $_GET = sanitizeInput($_GET);
// $_POST = sanitizeInput($_POST);

// Initialize CSRF token (generated in getCSRFToken from functions.php)
// getCSRFToken(); // Called in header.php so the token is available in a META tag

// Explicitly include Database class
require_once 'lib/Database.php';

// Connect to database
$database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $database->getConnection();

// Determine current world from session or default
if (!isset($_SESSION['world_id'])) {
    $_SESSION['world_id'] = INITIAL_WORLD_ID;
}
define('CURRENT_WORLD_ID', (int)$_SESSION['world_id']);

// Initialize the logs folder if it does not exist
if (!file_exists('logs')) {
    mkdir('logs', 0777, true);
}

// Check if the user is logged in and fetch their data
$user = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Ensure the autoloader works and UserManager is available
    // require_once 'lib/managers/UserManager.php'; 
    // $userManager = new UserManager($conn);
    // $user = $userManager->getUserById($user_id);

    // Temporarily use a direct prepared statement query
    $stmt = $conn->prepare("SELECT id, username, is_admin, is_banned FROM users WHERE id = ? LIMIT 1");
     if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
     } else {
         error_log("Database prepare failed in init.php for fetching user: " . $conn->error);
         // Error handling: log out or critical failure depending on security policy
         $user = null; // Set user to null on prepare failure
     }

    // If the session user does not exist or is banned, log them out
    if (!$user || $user['is_banned']) {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit();
    }

    // Refresh session data in case it changed in the database
    $_SESSION['username'] = $user['username'];
    $_SESSION['is_admin'] = $user['is_admin'];

    // Track last activity for inactivity-based systems
    if (dbColumnExists($conn, 'users', 'last_activity_at')) {
        $stmtUpdateActivity = $conn->prepare("UPDATE users SET last_activity_at = NOW() WHERE id = ?");
        if ($stmtUpdateActivity) {
            $stmtUpdateActivity->bind_param("i", $user_id);
            $stmtUpdateActivity->execute();
            $stmtUpdateActivity->close();
        }
    }

    // Check and process completed tasks for the user's active village
    if (isset($_SESSION['village_id'])) {
        // Ensure VillageManager is loaded (Autoloader should handle it)
        // require_once 'lib/VillageManager.php';
        // $villageManager = new VillageManager($conn);
        // $villageManager->processCompletedTasksForVillage($_SESSION['village_id']);
        // Left for when ResourceManager and full processing in game.php are implemented
    }

} elseif (!$IS_CLI) {
    // If the user is not logged in, redirect to login unless this is a public page
    $public_pages = ['index.php', 'auth/login.php', 'auth/register.php', 'install.php', 'admin/', 'admin/admin_login.php', 'admin/db_verify.php', 'favicon.ico', 'css/', 'js/', 'img/', 'ajax/', 'help.php', 'terms.php', 'guides.php']; // Add other public paths (folders and ajax)
    $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $current_path = trim($current_path, '/');
    
    $is_public = false;
    
    // Check whether the path matches public files/directories
    foreach ($public_pages as $public_item) {
        $public_item_trimmed = trim($public_item, '/');
        // If the URL path is exactly the public file OR starts with the public directory/
        if ($current_path === $public_item_trimmed || (strpos($current_path, $public_item_trimmed . '/') === 0 && $public_item_trimmed !== '')) {
            $is_public = true;
            break;
        }
    }
    

    if (!$is_public) {
        // If this is not a public page and not an AJAX request (which might target a public ajax script),
        // redirect to index.php (login/home page)
        if (!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
             header("Location: index.php");
             exit();
        }
         // If this is an AJAX request for a non-public resource and the user is not logged in, end it as well
         // (CSRF check should also catch this, but this is additional protection)
         // You can return JSON or 401/403 status
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Login required.']);
        exit();
   }
} else {
    // CLI context with no active user: allow execution without redirects.
}

// Setting an active village (if not set and user is logged in)
// Moved to game.php or similar where we actually need village_id
/*
if (isset($user) && !isset($_SESSION['village_id'])) {
    // Ensure VillageManager is loaded (Autoloader should handle it)
    $villageManager = new VillageManager($conn);
    $firstVillageId = $villageManager->getFirstVillage($user['id']);
    if ($firstVillageId) {
        $_SESSION['village_id'] = $firstVillageId;
    }
}
*/

// CSRF protection for POST requests
// Generate token per session (if not created yet) - done in getCSRFToken in functions.php, called in header.php
// if (empty($_SESSION['csrf_token'])) {
//     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// }

// CSRF validation for POST requests - handled by validateCSRF() from functions.php
// validateCSRF(); // This function is now in functions.php

// Removed calculation helpers - they should live in dedicated classes (BuildingManager, ResourceManager)
// function calculateHourlyProduction(...) { ... }
// function calculateWarehouseCapacity(...) { ... }

?> 
