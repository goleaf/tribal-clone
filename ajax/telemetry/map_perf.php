<?php
declare(strict_types=1);
require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$worldId = 1;
$metrics = [
    'request_rate' => isset($payload['request_rate']) ? (float)$payload['request_rate'] : null,
    'cache_hit_pct' => isset($payload['cache_hit_pct']) ? (float)$payload['cache_hit_pct'] : null,
    'payload_bytes' => isset($payload['payload_bytes']) ? (int)$payload['payload_bytes'] : null,
    'render_ms' => isset($payload['render_ms']) ? (float)$payload['render_ms'] : null,
    'dropped_frames' => isset($payload['dropped_frames']) ? (int)$payload['dropped_frames'] : null,
];

$stmt = $conn->prepare("INSERT INTO map_perf_telemetry (user_id, world_id, request_rate, cache_hit_pct, payload_bytes, render_ms, dropped_frames, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}
$stmt->bind_param(
    "iidiiid",
    $userId,
    $worldId,
    $metrics['request_rate'],
    $metrics['cache_hit_pct'],
    $metrics['payload_bytes'],
    $metrics['render_ms'],
    $metrics['dropped_frames']
);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success']);
