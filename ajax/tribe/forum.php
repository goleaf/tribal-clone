<?php
declare(strict_types=1);

require_once '../../init.php';
require_once __DIR__ . '/../../lib/managers/TribeManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$tribeManager = new TribeManager($conn);

// List threads
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tribeId = isset($_GET['tribe_id']) ? (int)$_GET['tribe_id'] : 0;
    if ($tribeId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid tribe id']);
        exit();
    }
    $membership = $tribeManager->getMembershipPublic($userId);
    if (!$membership || $membership['tribe_id'] !== $tribeId) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not in this tribe.']);
        exit();
    }
    $threads = $tribeManager->getForumThreads($tribeId);
    echo json_encode(['success' => true, 'threads' => $threads]);
    exit();
}

// Create thread or post depending on action
$action = $_POST['action'] ?? '';
$tribeId = isset($_POST['tribe_id']) ? (int)$_POST['tribe_id'] : 0;

if ($tribeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tribe id']);
    exit();
}

validateCSRF();

switch ($action) {
    case 'create_thread':
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $res = $tribeManager->createThread($tribeId, $userId, $title, $body);
        if (!$res['success']) {
            http_response_code(400);
            echo json_encode(['error' => $res['message'] ?? 'Unable to create thread']);
            exit();
        }
        echo json_encode(['success' => true, 'thread_id' => $res['thread_id'] ?? null]);
        break;
    case 'reply':
        $threadId = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
        $body = trim((string)($_POST['body'] ?? ''));
        $res = $tribeManager->addPost($tribeId, $threadId, $userId, $body);
        if (!$res['success']) {
            http_response_code(400);
            echo json_encode(['error' => $res['message'] ?? 'Unable to reply']);
            exit();
        }
        echo json_encode(['success' => true, 'post_id' => $res['post_id'] ?? null]);
        break;
    case 'delete_post':
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $res = $tribeManager->deletePost($tribeId, $userId, $postId);
        if (!$res['success']) {
            http_response_code(400);
            echo json_encode(['error' => $res['message'] ?? 'Unable to delete post']);
            exit();
        }
        echo json_encode(['success' => true]);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported action']);
}
?>
