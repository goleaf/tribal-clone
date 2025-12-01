<?php
/**
 * Travel time calculator for map interactions.
 * Inputs: source_village_id (optional), target_x, target_y, unit_speed (fields/hour), grid_type (square|hex), terrain_multiplier.
 * Output: distance (tiles) and travel_time_seconds with formatted hh:mm:ss.
 */
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/utils/AjaxResponse.php';
require_once __DIR__ . '/../../lib/managers/VillageManager.php';
require_once __DIR__ . '/../../lib/managers/WorldManager.php';
require_once __DIR__ . '/../../lib/RateLimiter.php';
require_once __DIR__ . '/../../lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('User is not logged in', null, 401);
}

$userId = (int)$_SESSION['user_id'];
$limiter = new RateLimiter($conn);
$limiterKey = "travel_time_user_{$userId}";
$windowSeconds = 10;
$maxRequests = 30; // per user per window

if (!$limiter->allow($limiterKey, $maxRequests, $windowSeconds)) {
    http_response_code(429);
    header('Retry-After: ' . $windowSeconds);
    echo json_encode(['error' => 'ERR_RATE_LIMITED', 'retry_after' => $windowSeconds]);
    exit;
}

$targetX = isset($_GET['target_x']) ? (float)$_GET['target_x'] : null;
$targetY = isset($_GET['target_y']) ? (float)$_GET['target_y'] : null;
$unitSpeed = isset($_GET['unit_speed']) ? (float)$_GET['unit_speed'] : null; // fields/hour
$gridType = isset($_GET['grid_type']) ? strtolower(trim((string)$_GET['grid_type'])) : 'square';
$terrainMultiplier = isset($_GET['terrain_multiplier']) ? (float)$_GET['terrain_multiplier'] : 1.0;
$sourceVillageId = isset($_GET['source_village_id']) ? (int)$_GET['source_village_id'] : 0;

if ($targetX === null || $targetY === null || $unitSpeed === null || $unitSpeed <= 0) {
    AjaxResponse::error('Missing or invalid parameters.');
}

if (!in_array($gridType, ['square', 'hex'], true)) {
    $gridType = 'square';
}

if ($terrainMultiplier <= 0) {
    $terrainMultiplier = 1.0;
}

try {
    $villageManager = new VillageManager($conn);
    $worldManager = new WorldManager($conn);

    if ($sourceVillageId <= 0) {
        $firstVillage = $villageManager->getFirstVillage($userId);
        if (!$firstVillage) {
            AjaxResponse::error('No village found.', null, 404);
        }
        $sourceVillageId = (int)$firstVillage['id'];
    }

    // Ownership check
    $owner = $villageManager->getVillageInfo($sourceVillageId);
    if (!$owner || (int)($owner['user_id'] ?? 0) !== $userId) {
        AjaxResponse::error('No permission for this village.', null, 403);
    }

    $sourceX = (float)($owner['x_coord'] ?? 0);
    $sourceY = (float)($owner['y_coord'] ?? 0);

    $dx = $targetX - $sourceX;
    $dy = $targetY - $sourceY;

    $distance = 0.0;
    if ($gridType === 'hex') {
        $distance = (abs($dx) + abs($dy) + abs($dx + $dy)) / 2.0;
    } else {
        // Square grid uses Chebyshev distance for travel timing
        $distance = max(abs($dx), abs($dy));
    }

    $baseUnitSpeed = defined('WORLD_UNIT_SPEED') ? max(0.1, (float)WORLD_UNIT_SPEED) : 1.0;
    $worldSpeed = $worldManager->getWorldSpeed();
    $troopSpeed = $worldManager->getTroopSpeed();
    $unitSpeedMultiplier = defined('UNIT_SPEED_MULTIPLIER') ? max(0.1, (float)UNIT_SPEED_MULTIPLIER) : 1.0;
    $effectiveSpeed = $baseUnitSpeed * $worldSpeed * $troopSpeed * $unitSpeedMultiplier; // fields per hour

    $travelSeconds = (int)ceil(($distance * $terrainMultiplier * $unitSpeed / max(0.1, $effectiveSpeed)) * 3600);

    AjaxResponse::success([
        'distance' => $distance,
        'grid_type' => $gridType,
        'travel_time_seconds' => $travelSeconds,
        'travel_time_formatted' => gmdate('H:i:s', $travelSeconds),
        'source' => ['x' => $sourceX, 'y' => $sourceY],
        'target' => ['x' => $targetX, 'y' => $targetY],
        'dx' => $dx,
        'dy' => $dy,
        'terrain_multiplier' => $terrainMultiplier,
        'effective_speed' => $effectiveSpeed,
        'base_unit_speed' => $baseUnitSpeed,
        'world_speed' => $worldSpeed,
        'troop_speed' => $troopSpeed,
        'unit_speed_multiplier' => $unitSpeedMultiplier
    ]);
} catch (Throwable $e) {
    AjaxResponse::handleException($e);
}
