<?php
declare(strict_types=1);
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/RateLimiter.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$limiter = new RateLimiter($conn);
$windowSeconds = 10;
$maxRequests = 10; // per user per window to curb fetch spam
$key = "map_fetch_user_{$userId}";

if (!$limiter->allow($key, $maxRequests, $windowSeconds)) {
    http_response_code(429);
    header('Retry-After: ' . $windowSeconds);
    echo json_encode(['error' => 'ERR_RATE_LIMITED', 'retry_after' => $windowSeconds]);
    exit;
}

// Placeholder: Actual map payload would be built here or delegated.
echo json_encode(['ok' => true, 'message' => 'Map fetch allowed (placeholder payload).']);
