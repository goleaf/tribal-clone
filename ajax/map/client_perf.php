<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Login required']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$fetchMs = isset($data['fetch_ms']) ? (int)$data['fetch_ms'] : 0;
$renderMs = isset($data['render_ms']) ? (int)$data['render_ms'] : 0;
$size = isset($data['size']) ? (int)$data['size'] : 0;
$truncated = !empty($data['truncated']) ? 1 : 0;
$centerX = isset($data['center_x']) ? (int)$data['center_x'] : 0;
$centerY = isset($data['center_y']) ? (int)$data['center_y'] : 0;
$ts = isset($data['ts']) ? (int)$data['ts'] : time() * 1000;

// Basic validation to avoid log spam/overflow
if ($fetchMs < 0 || $renderMs < 0 || $fetchMs > 60000 || $renderMs > 60000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Timing out of range']);
    exit;
}

$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/map_client_perf.log';

$line = sprintf(
    "[%s] user=%d center=%d|%d size=%d fetch_ms=%d render_ms=%d truncated=%d ts=%d\n",
    date('Y-m-d H:i:s'),
    $userId,
    $centerX,
    $centerY,
    $size,
    $fetchMs,
    $renderMs,
    $truncated,
    $ts
);
@file_put_contents($logFile, $line, FILE_APPEND);

echo json_encode(['status' => 'ok']);
