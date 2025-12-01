<?php
declare(strict_types=1);
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/RateLimiter.php';
require_once __DIR__ . '/../../lib/MapCacheManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$limiter = new RateLimiter($conn);
$windowSeconds = 10;
$maxRequests = 8; // tighter per-user window to curb fetch spam
$key = "map_fetch_user_{$userId}";

if (!$limiter->allow($key, $maxRequests, $windowSeconds)) {
    http_response_code(429);
    header('Retry-After: ' . $windowSeconds);
    echo json_encode(['error' => 'ERR_RATE_LIMITED', 'retry_after' => $windowSeconds]);
    exit;
}

// Get viewport parameters
$centerX = isset($_GET['centerX']) ? (int)$_GET['centerX'] : 250;
$centerY = isset($_GET['centerY']) ? (int)$_GET['centerY'] : 250;
$zoomLevel = isset($_GET['zoomLevel']) ? (int)$_GET['zoomLevel'] : 1;
$width = isset($_GET['width']) ? (int)$_GET['width'] : 800;
$height = isset($_GET['height']) ? (int)$_GET['height'] : 600;

$viewport = [
    'centerX' => $centerX,
    'centerY' => $centerY,
    'zoomLevel' => $zoomLevel,
    'width' => $width,
    'height' => $height
];

// Get world ID (default to 1)
$worldId = isset($_GET['worldId']) ? (int)$_GET['worldId'] : 1;

// Initialize cache manager
$db = Database::getInstance();
$cacheManager = new MapCacheManager($db);

// Generate ETag for current data
$etag = $cacheManager->generateETag($worldId, $viewport, $userId);

// Check if client sent If-None-Match header
$clientETag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') : null;

// Set ETag header
header('ETag: "' . $etag . '"');
header('Cache-Control: private, must-revalidate');

// If ETags match, return 304 Not Modified
if ($clientETag === $etag) {
    http_response_code(304);
    exit;
}

// Generate cache key
$cacheKey = $cacheManager->generateCacheKey($worldId, $viewport, $userId);

// Placeholder: Actual map payload would be built here or delegated.
// For now, return a simple response with cache information
$response = [
    'ok' => true,
    'message' => 'Map fetch allowed with caching.',
    'cache_key' => $cacheKey,
    'etag' => $etag,
    'viewport' => $viewport
];

echo json_encode($response);
