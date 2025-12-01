<?php
declare(strict_types=1);

require_once '../../init.php';
require_once __DIR__ . '/../../lib/managers/TaskManager.php';
require_once __DIR__ . '/../../lib/managers/VillageManager.php';
require_once __DIR__ . '/../../lib/managers/WorldManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$villageManager = new VillageManager($conn);
$firstVillage = $villageManager->getFirstVillage($userId);
$worldId = isset($firstVillage['world_id']) ? (int)$firstVillage['world_id'] : (defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1);
$worldManager = new WorldManager($conn);
if (!defined('FEATURE_TASKS_ENABLED') || FEATURE_TASKS_ENABLED !== true || !$worldManager->areTasksEnabled($worldId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Tasks are disabled on this world.']);
    exit();
}
$taskManager = new TaskManager($conn);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$type = isset($_GET['type']) ? strtolower((string)$_GET['type']) : 'daily';
$type = in_array($type, ['daily', 'weekly'], true) ? $type : 'daily';
$ttl = $type === 'weekly' ? 168 : 24;

// Simple seed tasks if none exist (placeholder definitions)
$seedDefs = [
    ['key' => 'train_50_units', 'target' => 50, 'reward' => ['wood' => 500, 'clay' => 500, 'iron' => 500]],
    ['key' => 'send_3_raids', 'target' => 3, 'reward' => ['premium' => 10]],
    ['key' => 'collect_resources', 'target' => 5, 'reward' => ['speed_token_5m' => 1]],
];

if ($method === 'GET') {
    $tasks = $taskManager->refreshTasks($userId, $type, $seedDefs, $ttl, 3);
    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'expires_at' => $tasks ? $tasks[0]['expires_at'] ?? null : null,
        'type' => $type
    ]);
    exit();
}

validateCSRF();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'claim':
        $taskKey = (string)($_POST['task_key'] ?? '');
        $res = $taskManager->claimTask($userId, $taskKey, $type);
        if (!$res['success']) {
            http_response_code(400);
            echo json_encode(['error' => $res['message'] ?? 'Unable to claim task.']);
            exit();
        }
        echo json_encode(['success' => true, 'reward' => $res['reward'] ?? []]);
        exit();
    case 'reroll':
        $taskKey = (string)($_POST['task_key'] ?? '');
        $newDef = $seedDefs[array_rand($seedDefs)];
        $res = $taskManager->rerollTask($userId, $taskKey, $type, $newDef, 1);
        if (!$res['success']) {
            http_response_code(400);
            echo json_encode(['error' => $res['message'] ?? 'Unable to reroll task.']);
            exit();
        }
        echo json_encode(['success' => true]);
        exit();
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported action']);
        exit();
}
