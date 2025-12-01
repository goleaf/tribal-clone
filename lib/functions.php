<?php

/**
 * Helper functions used across the system.
 * Inspired by the legacy VeryOldTemplate codebase.
 */

/**
 * Formats seconds to HH:MM:SS.
 */
function formatTime($seconds) {
    return gmdate("H:i:s", $seconds);
}

/**
 * Formats a timestamp into a human-readable date string.
 */
function formatDate($timestamp) {
    return date("Y-m-d H:i:s", $timestamp);
}

/**
 * Filters and sanitizes input data.
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitizeInput($value);
        }
        return $input;
    }

    $input = trim($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

    return $input;
}

/**
 * Sanitizes input for SQL queries.
 */
function sanitizeSql($conn, $input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitizeSql($conn, $value);
        }
        return $input;
    }

    if (is_object($conn) && method_exists($conn, 'real_escape_string')) {
        return $conn->real_escape_string($input);
    }

    return addslashes($input);
}

/**
 * Checks if a table exists (works for SQLite and MySQL).
 */
function dbTableExists($conn, string $tableName): bool {
    // SQLiteAdapter exposes getPdo(); mysqli does not.
    if (is_object($conn) && method_exists($conn, 'getPdo')) {
        $stmt = $conn->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        if ($stmt) {
            $stmt->bind_param("s", $tableName);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result && $result->num_rows > 0;
            $stmt->close();
            return $exists;
        }
        return false;
    }

    $safe = addslashes($tableName);
    $result = $conn->query("SHOW TABLES LIKE '$safe'");
    return $result && $result->num_rows > 0;
}

/**
 * Generates a unique token for the session.
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Calculates distance between two map points.
 */
function calculateDistance($x1, $y1, $x2, $y2) {
    return sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
}

/**
 * Calculates unit travel time between villages (seconds).
 */
function calculateTravelTime($distance, $speed) {
    // Speed is in fields per hour; output is seconds.
    return ($distance / $speed) * 3600;
}

/**
 * Calculates task end time (build, recruit, etc.).
 */
function calculateEndTime($duration_seconds) {
    return time() + $duration_seconds;
}

/**
 * Calculates player points from buildings and units.
 */
function calculatePlayerPoints($conn, $user_id) {
    // Building points
    $stmt = $conn->prepare("
        SELECT SUM(bt.base_points * vb.level) AS building_points
        FROM village_buildings vb
        JOIN building_types bt ON vb.building_type_id = bt.id
        JOIN villages v ON vb.village_id = v.id
        WHERE v.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $building_points = $row['building_points'] ?? 0;
    $stmt->close();

    // Unit points
    $stmt = $conn->prepare("
        SELECT SUM(ut.points * vu.count) AS unit_points
        FROM village_units vu
        JOIN unit_types ut ON vu.unit_type_id = ut.id
        JOIN villages v ON vu.village_id = v.id
        WHERE v.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $unit_points = $row['unit_points'] ?? 0;
    $stmt->close();

    return $building_points + $unit_points;
}

/**
 * Returns coordinates as "X|Y".
 */
function formatCoordinates($x, $y) {
    return $x . "|" . $y;
}

/**
 * Calculates position index based on map coordinates.
 */
function calculateMapPosition($x, $y, $map_size) {
    return ($y * $map_size) + $x;
}

/**
 * Rounds a number to the given decimals.
 */
function roundNumber($number, $decimals = 0) {
    return round($number, $decimals);
}

/**
 * Formats number with thousand separators.
 */
function formatNumber($number) {
    // English formatting: comma as thousands separator
    return number_format($number, 0, '.', ',');
}

/**
 * Converts seconds to HH:MM:SS.
 */
function secondsToTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;

    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}

/**
 * Validates username for allowed characters.
 */
function isValidUsername($username) {
    // 3-20 chars, letters, digits, underscore.
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

/**
 * Validates village name for allowed characters.
 */
function isValidVillageName($name) {
    // 2-30 chars; letters, digits, spaces, and basic punctuation.
    return preg_match('/^[a-zA-Z0-9 \.\,\-\_]{2,30}$/', $name);
}

/**
 * Hashes a password using modern PHP hashing.
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifies a password against its hash.
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generates a random player color.
 */
function generatePlayerColor() {
    $colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ff8000', '#8000ff'];
    return $colors[array_rand($colors)];
}

/**
 * Retrieves client IP address.
 */
function getClientIP() {
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } else if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if(isset($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    } else if(isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    } else if(isset($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    } else if(isset($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }
    return '0.0.0.0';
}

/**
 * Generates random coordinates for a new village.
 */
function generateRandomCoordinates($conn, $map_size = 100) {
    $max_attempts = 50; // Max attempts to find free coordinates.
    $attempt = 0;

    do {
        $x = rand(0, $map_size - 1);
        $y = rand(0, $map_size - 1);

        $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM villages WHERE x_coord = ? AND y_coord = ?");
        $stmt->bind_param("ii", $x, $y);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $is_occupied = $row['count'] > 0;
        $attempt++;

    } while ($is_occupied && $attempt < $max_attempts);

    if ($attempt >= $max_attempts) {
        return ['x' => 0, 'y' => 0];
    }

    return ['x' => $x, 'y' => $y];
}

/**
 * Returns terrain type for coordinates (pseudo-random but deterministic).
 */
function getTerrainType($x, $y) {
    $hash = $x * 1000 + $y;
    $types = ['plain', 'forest', 'hill', 'mountain', 'water'];

    srand($hash);
    $type = $types[rand(0, count($types) - 1)];
    srand(time()); // Restore randomness

    return $type;
}

/**
 * Generates or returns existing CSRF token stored in session.
 */
function getCSRFToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Adds a toast message to the session queue.
 */
function setGameMessage($message, $type = 'info') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['game_messages'])) {
        $_SESSION['game_messages'] = [];
    }
    $_SESSION['game_messages'][] = ['message' => $message, 'type' => $type];
}

/**
 * Validates CSRF token in POST requests.
 * Terminates script with 403 if invalid.
 */
function validateCSRF() {
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF validation failed. Session token: " . ($_SESSION['csrf_token'] ?? 'none') . ", Post token: " . ($_POST['csrf_token'] ?? 'none'));

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF validation failed. Refresh the page and try again.']);
        } else {
            setGameMessage('CSRF validation failed. Refresh the page and try again.', 'error');
            header("Location: index.php");
        }
        exit();
    }
}

/**
 * Returns the name of the current world.
 */
function getCurrentWorldName($conn) {
    $worldId = CURRENT_WORLD_ID;
    $stmt = $conn->prepare("SELECT name FROM worlds WHERE id = ?");
    $stmt->bind_param("i", $worldId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: [];
    $stmt->close();
    return $row['name'] ?? '';
}

/**
 * Formats time to a human-friendly "in X time" string.
 */
function formatTimeToHuman($timestamp) {
    $now = time();
    $diff = $timestamp - $now;

    if ($diff <= 0) {
        return "now";
    }

    if ($diff < 60) {
        return "in " . $diff . " " . ($diff == 1 ? "second" : "seconds");
    }

    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "in " . $minutes . " " . ($minutes == 1 ? "minute" : "minutes");
    }

    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "in " . $hours . " " . ($hours == 1 ? "hour" : "hours");
    }

    $days = floor($diff / 86400);
    return "in " . $days . " " . ($days == 1 ? "day" : "days");
}

/**
 * Renders a resource icon/value snippet.
 */
function displayResource($resource_type, $amount, $show_max = false, $max_amount = 0) {
    $icons = [
        'wood' => '../img/ds_graphic/wood.png',
        'clay' => '../img/ds_graphic/stone.png',
        'iron' => '../img/ds_graphic/iron.png',
        'population' => '../img/ds_graphic/resources/population.png'
    ];

    $names = [
        'wood' => 'Wood',
        'clay' => 'Clay',
        'iron' => 'Iron',
        'population' => 'Population'
    ];

    $icon = isset($icons[$resource_type]) ? $icons[$resource_type] : '';
    $name = isset($names[$resource_type]) ? $names[$resource_type] : $resource_type;

    $output = '<img src="' . $icon . '" alt="' . $name . '" title="' . $name . '"> ';
    $output .= '<span class="resource-value" id="current-' . $resource_type . '">' . formatNumber($amount) . '</span>';

    if ($show_max && $max_amount > 0) {
        $output .= '<span class="resource-capacity">/<span id="capacity-' . $resource_type . '">' . formatNumber($max_amount) . '</span></span>';

        $percentage = min(100, round(($amount / $max_amount) * 100));

        $output .= '<div class="resource-tooltip">
            <div class="resource-info">
                <span class="resource-info-label">' . $name . ':</span>
                <span><span id="tooltip-current-' . $resource_type . '">' . formatNumber($amount) . '</span>/<span id="tooltip-capacity-' . $resource_type . '">' . formatNumber($max_amount) . '</span></span>
            </div>
            <div class="resource-info">
                <span class="resource-info-label">Production:</span>
                <span id="tooltip-prod-' . $resource_type . '"></span>
            </div>
            <div class="resource-info">
                <span class="resource-info-label">Fill:</span>
                <span id="tooltip-percentage-' . $resource_type . '">' . $percentage . '%</span>
            </div>
            <div class="resource-bar-outer">
                <div class="resource-bar-inner" id="bar-' . $resource_type . '" style="width: ' . $percentage . '%"></div>
            </div>
        </div>';
    }

    return $output;
}

/**
 * Calculates hourly production for a building level.
 * @deprecated Use ResourceManager->getHourlyProductionRate()
 */
function calculateHourlyProduction($building_type, $level) {
    $base_production = 100; // Base hourly production for level 1
    $growth_factor = 1.2; // Growth factor per level

    if ($level <= 0) {
        return 0;
    }

    return round($base_production * pow($growth_factor, $level - 1));
}

/**
 * Adds a notification for a user.
 */
function addNotification($conn, $user_id, $type, $message, $link = '', $expires_at = 0) {
    // Default expiration: 7 days
    if ($expires_at <= 0) {
        $expires_at = time() + (7 * 24 * 60 * 60);
    }

    $table_exists = dbTableExists($conn, 'notifications');

    // Create the table if missing (SQLite compatible)
    if (!$table_exists) {
        $create_table = "CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            link TEXT DEFAULT '',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            is_read INTEGER DEFAULT 0,
            expires_at INTEGER NOT NULL
        );";

        if (!$conn->query($create_table)) {
            error_log("Unable to create notifications table: " . $conn->error);
            return false;
        }
    }

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, link, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $user_id, $type, $message, $link, $expires_at);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Retrieves notifications for a user.
 */
function getNotifications($conn, $user_id, $only_unread = false, $limit = 10) {
    $table_exists = dbTableExists($conn, 'notifications');

    if (!$table_exists) {
        return [];
    }

    // Remove expired notifications
    $current_time = time();
    $stmt_delete = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND expires_at < ?");
    $stmt_delete->bind_param("ii", $user_id, $current_time);
    $stmt_delete->execute();
    $stmt_delete->close();

    // Fetch notifications
    $query = "SELECT id, type, message, link, created_at, is_read FROM notifications 
              WHERE user_id = ? AND expires_at > ?";

    if ($only_unread) {
        $query .= " AND is_read = 0";
    }

    $query .= " ORDER BY created_at DESC LIMIT ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $current_time, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();
    return $notifications;
}

/**
 * Marks a notification as read.
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Checks if the player has enough resources.
 */
function hasEnoughResources($available, $required) {
    return $available['wood'] >= $required['wood'] &&
           $available['clay'] >= $required['clay'] &&
           $available['iron'] >= $required['iron'];
}

/**
 * Calculates remaining time text for build/recruit tasks.
 */
function getRemainingTimeText($ends_at) {
    $now = time();
    $remaining = $ends_at - $now;

    if ($remaining <= 0) {
        return 'Completed';
    }

    $hours = floor($remaining / 3600);
    $minutes = floor(($remaining % 3600) / 60);
    $seconds = $remaining % 60;

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

/**
 * Generates a link to a player profile.
 */
function generatePlayerLink($user_id, $username = '') {
    if (empty($username)) {
        $username = 'Player #' . $user_id;
    }

    return '<a href="player.php?id=' . $user_id . '" class="player-link">' . htmlspecialchars($username) . '</a>';
}

/**
 * Generates a link to a village.
 */
function generateVillageLink($village_id, $village_name = '', $x = null, $y = null) {
    $html = '<a href="game.php?village_id=' . $village_id . '" class="village-link">';

    if (!empty($village_name)) {
        $html .= htmlspecialchars($village_name);
    } else {
        $html .= 'Village #' . $village_id;
    }

    if ($x !== null && $y !== null) {
        $html .= ' <span class="coordinates">(' . $x . '|' . $y . ')</span>';
    }

    $html .= '</a>';
    return $html;
}

/**
 * Converts duration in seconds to a human-friendly string.
 */
function formatDuration($seconds, $long_format = false) {
    if ($seconds < 60) {
        return $seconds . ($long_format ? " seconds" : "s");
    }

    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;

    if ($minutes < 60) {
        if ($long_format) {
            $min_text = $minutes . " " . ($minutes == 1 ? "minute" : "minutes");
            if ($seconds > 0) {
                $min_text .= " " . $seconds . " " . ($seconds == 1 ? "second" : "seconds");
            }
            return $min_text;
        } else {
            return $minutes . "m " . $seconds . "s";
        }
    }

    $hours = floor($minutes / 60);
    $minutes = $minutes % 60;

    if ($long_format) {
        $hour_text = $hours . " " . ($hours == 1 ? "hour" : "hours");
        if ($minutes > 0) {
            $hour_text .= " " . $minutes . " " . ($minutes == 1 ? "minute" : "minutes");
        }
        return $hour_text;
    } else {
        return $hours . "h " . $minutes . "m";
    }
}

/**
 * Returns button text for a building in village view.
 */
function getBuildingActionText($building_internal_name) {
    switch ($building_internal_name) {
        case 'main_building': return 'Village overview';
        case 'barracks': return 'Recruit infantry';
        case 'stable': return 'Recruit cavalry';
        case 'workshop': return 'Produce siege';
        case 'smithy': return 'Research technology';
        case 'academy': return 'Advanced research';
        case 'market': return 'Trade';
        case 'statue': return 'Statue';
        case 'church':
        case 'first_church': return 'Church';
        case 'mint': return 'Mint';
        default: return 'Action';
    }
}
