<?php
declare(strict_types=1);

require_once '../../init.php';
require_once __DIR__ . '/../../lib/managers/TribeManager.php';
require_once __DIR__ . '/../../lib/managers/TribeIntelManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$worldId = defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : (int)($_SESSION['world_id'] ?? 1);

$tribeManager = new TribeManager($conn);
$membership = $tribeManager->getMembershipPublic($userId);
if (!$membership || !isset($membership['tribe_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'You are not in a tribe.']);
    exit();
}
$tribeId = (int)$membership['tribe_id'];
$role = $membership['role'] ?? 'member';

$intel = new TribeIntelManager($conn, $tribeManager);

// GET: list markers/operations
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $includeExpired = isset($_GET['include_expired']) && $_GET['include_expired'] === '1';
    $view = isset($_GET['view']) ? strtolower((string)$_GET['view']) : 'all';

    $response = ['success' => true];
    if ($view === 'markers' || $view === 'all') {
        $response['markers'] = $intel->listMarkers($tribeId, $worldId, $includeExpired);
    }
    if ($view === 'operations' || $view === 'all') {
        $response['operations'] = $intel->listOperations($tribeId, $worldId, $includeExpired);
    }

    echo json_encode($response);
    exit();
}

// POST actions
validateCSRF();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create_marker':
        $type = (string)($_POST['type'] ?? '');
        $title = (string)($_POST['title'] ?? '');
        $x = isset($_POST['x']) ? (int)$_POST['x'] : 0;
        $y = isset($_POST['y']) ? (int)$_POST['y'] : 0;
        $freshness = isset($_POST['freshness_minutes']) ? (int)$_POST['freshness_minutes'] : 60;
        $confidence = isset($_POST['confidence']) ? (int)$_POST['confidence'] : 3;
        $expiresAt = $_POST['expires_at'] ?? null;
        $tags = $_POST['tags'] ?? null;
        $notes = (string)($_POST['notes'] ?? '');

        $result = $intel->createMarker($tribeId, $worldId, $userId, $role, $type, $title, $x, $y, $expiresAt, $freshness, $confidence, $tags, $notes);
        if (!$result['success']) {
            http_response_code(400);
            echo json_encode(['error' => $result['message'] ?? 'Unable to create marker']);
            exit();
        }
        echo json_encode(['success' => true, 'marker' => $result['marker'] ?? null]);
        exit();

    case 'delete_marker':
        $markerId = isset($_POST['marker_id']) ? (int)$_POST['marker_id'] : 0;
        if ($markerId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid marker id']);
            exit();
        }
        $result = $intel->deleteMarker($tribeId, $userId, $role, $markerId);
        if (!$result['success']) {
            http_response_code(400);
            echo json_encode(['error' => $result['message'] ?? 'Unable to delete marker']);
            exit();
        }
        echo json_encode(['success' => true]);
        exit();

    case 'create_operation':
        $type = (string)($_POST['type'] ?? '');
        $title = (string)($_POST['title'] ?? '');
        $targetX = isset($_POST['target_x']) ? (int)$_POST['target_x'] : 0;
        $targetY = isset($_POST['target_y']) ? (int)$_POST['target_y'] : 0;
        $launchAt = $_POST['launch_at'] ?? null;
        $markerId = isset($_POST['marker_id']) && $_POST['marker_id'] !== '' ? (int)$_POST['marker_id'] : null;
        $requiredRoles = [];
        if (!empty($_POST['required_roles'])) {
            if (is_array($_POST['required_roles'])) {
                $requiredRoles = array_filter(array_map('trim', $_POST['required_roles']));
            } else {
                $parts = explode(',', (string)$_POST['required_roles']);
                $requiredRoles = array_filter(array_map('trim', $parts));
            }
        }
        $expiresAt = $_POST['expires_at'] ?? null;
        $notes = (string)($_POST['notes'] ?? '');

        $result = $intel->createOperation($tribeId, $worldId, $userId, $role, $type, $title, $targetX, $targetY, $launchAt, $markerId, $requiredRoles ?: null, $expiresAt, $notes);
        if (!$result['success']) {
            http_response_code(400);
            echo json_encode(['error' => $result['message'] ?? 'Unable to create operation']);
            exit();
        }
        echo json_encode(['success' => true, 'operation' => $result['operation'] ?? null]);
        exit();

    case 'claim_operation':
        $operationId = isset($_POST['operation_id']) ? (int)$_POST['operation_id'] : 0;
        $claimRole = (string)($_POST['claim_role'] ?? '');
        $note = $_POST['note'] ?? null;
        $ttlMinutes = isset($_POST['ttl_minutes']) && $_POST['ttl_minutes'] !== '' ? (int)$_POST['ttl_minutes'] : null;

        if ($operationId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid operation id']);
            exit();
        }
        $result = $intel->claimOperation($tribeId, $worldId, $userId, $role, $operationId, $claimRole, $note, $ttlMinutes);
        if (!$result['success']) {
            http_response_code(400);
            echo json_encode(['error' => $result['message'] ?? 'Unable to claim slot']);
            exit();
        }
        echo json_encode(['success' => true, 'claim' => $result['claim'] ?? null]);
        exit();

    case 'release_claim':
        $claimId = isset($_POST['claim_id']) ? (int)$_POST['claim_id'] : 0;
        if ($claimId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid claim id']);
            exit();
        }
        $result = $intel->releaseClaim($tribeId, $userId, $role, $claimId);
        if (!$result['success']) {
            http_response_code(400);
            echo json_encode(['error' => $result['message'] ?? 'Unable to release claim']);
            exit();
        }
        echo json_encode(['success' => true]);
        exit();

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported action']);
        exit();
}
