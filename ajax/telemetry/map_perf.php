<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');

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

$entry = [
    'ts' => date('c'),
    'user_id' => (int)$_SESSION['user_id'],
    'render_ms' => (float)$payload['render_ms'],
    'payload_bytes' => isset($payload['payload_bytes']) ? (int)$payload['payload_bytes'] : null,
    'cache_hit_pct' => $payload['cache_hit_pct'] ?? null,
    'request_rate' => $payload['request_rate'] ?? null,
    'dropped_frames' => $payload['dropped_frames'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
];

// Avoid log bloat; skip obviously bad values.
if ($entry['render_ms'] < 0 || $entry['render_ms'] > 60000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Render time out of range']);
    exit;
}

$line = json_encode($entry) . PHP_EOL;
@file_put_contents(__DIR__ . '/../../logs/map_client_perf.log', $line, FILE_APPEND);

echo json_encode(['status' => 'success']);
