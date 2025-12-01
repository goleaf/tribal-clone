<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');
$clientPerfLog = __DIR__ . '/../../logs/map_client_perf.log';
$clientPerfAlertLog = __DIR__ . '/../../logs/map_client_perf_alerts.log';
if (!is_dir(__DIR__ . '/../../logs')) {
    mkdir(__DIR__ . '/../../logs', 0777, true);
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload) || !isset($payload['render_ms'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$renderMs = isset($payload['render_ms']) ? (float)$payload['render_ms'] : null;
$payloadBytes = isset($payload['payload_bytes']) ? (int)$payload['payload_bytes'] : null;
$cacheHitPct = isset($payload['cache_hit_pct']) ? (float)$payload['cache_hit_pct'] : null;
$requestRate = isset($payload['request_rate']) ? (float)$payload['request_rate'] : null;
$droppedFrames = isset($payload['dropped_frames']) && $payload['dropped_frames'] !== '' ? (int)$payload['dropped_frames'] : null;
$cacheStatus = $payload['cache_status'] ?? null;
$fetchMs = isset($payload['fetch_ms']) ? (int)$payload['fetch_ms'] : null;

$entry = [
    'ts' => date('c'),
    'user_id' => (int)$_SESSION['user_id'],
    'render_ms' => $renderMs,
    'payload_bytes' => $payloadBytes,
    'cache_hit_pct' => $cacheHitPct,
    'request_rate' => $requestRate,
    'dropped_frames' => $droppedFrames,
    'cache_status' => $cacheStatus,
    'fetch_ms' => $fetchMs,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
];

// Avoid log bloat; skip obviously bad values.
if ($renderMs === null || $renderMs < 0 || $renderMs > 60000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Render time out of range']);
    exit;
}

$line = json_encode($entry) . PHP_EOL;
@file_put_contents($clientPerfLog, $line, FILE_APPEND);

$renderAlertMs = defined('MAP_CLIENT_RENDER_ALERT_MS') ? (int)MAP_CLIENT_RENDER_ALERT_MS : 1200;
$droppedFrameAlert = defined('MAP_CLIENT_DROPPED_FRAME_ALERT') ? (int)MAP_CLIENT_DROPPED_FRAME_ALERT : 8;
if (($renderMs !== null && $renderMs >= $renderAlertMs) || ($droppedFrames !== null && $droppedFrames >= $droppedFrameAlert)) {
    $alertLine = sprintf(
        "[%s] ALERT render_ms=%.1f dropped_frames=%s payload_bytes=%s user=%d\n",
        date('Y-m-d H:i:s'),
        $renderMs,
        $droppedFrames === null ? 'null' : $droppedFrames,
        $payloadBytes === null ? 'null' : $payloadBytes,
        (int)$_SESSION['user_id']
    );
    @file_put_contents($clientPerfAlertLog, $alertLine, FILE_APPEND);
}

echo json_encode(['status' => 'success']);
