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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tribeId = isset($_GET['tribe_id']) ? (int)$_GET['tribe_id'] : 0;
    if ($tribeId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid tribe id']);
        exit();
    }
    $relations = $tribeManager->getDiplomacyRelations($tribeId);
    echo json_encode(['success' => true, 'relations' => $relations]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $tribeId = isset($_POST['tribe_id']) ? (int)$_POST['tribe_id'] : 0;
    $targetTribeId = isset($_POST['target_tribe_id']) ? (int)$_POST['target_tribe_id'] : 0;
    $status = isset($_POST['status']) ? trim((string)$_POST['status']) : '';
    if ($tribeId <= 0 || $targetTribeId <= 0 || $tribeId === $targetTribeId) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid tribe selection']);
        exit();
    }
    $result = $tribeManager->setDiplomacyStatus($tribeId, $userId, $targetTribeId, $status);
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode(['error' => $result['message'] ?? 'Unable to update diplomacy']);
        exit();
    }
    echo json_encode(['success' => true, 'message' => $result['message']]);
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
