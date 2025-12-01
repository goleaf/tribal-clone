<?php
declare(strict_types=1);
require '../init.php';
validateCSRF();

require_once __DIR__ . '/../lib/managers/SittingManager.php';
require_once __DIR__ . '/../lib/managers/UserManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$targetOwnerId = isset($_POST['owner_id']) ? (int)$_POST['owner_id'] : 0;

$sittingManager = new SittingManager($conn);
$userManager = new UserManager($conn);

if ($targetOwnerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid owner.']);
    exit();
}

$active = $sittingManager->isActive($targetOwnerId, $currentUserId);
if (!$active) {
    echo json_encode(['success' => false, 'message' => 'No active sitting permission for this account.']);
    exit();
}

// Switch session to act as owner
$_SESSION['sitter_original_user_id'] = $currentUserId;
$_SESSION['user_id'] = $targetOwnerId;
$_SESSION['sitter_expires_at'] = strtotime($active['ends_at']);
$_SESSION['sitter_owner_id'] = $targetOwnerId;

$owner = $userManager->getUserById($targetOwnerId);

echo json_encode([
    'success' => true,
    'message' => 'Now sitting for ' . ($owner['username'] ?? 'player') . ' until ' . $active['ends_at'],
    'redirect' => '/game/game.php'
]);
exit();
