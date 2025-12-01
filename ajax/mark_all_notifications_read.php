<?php
require_once '../init.php';
require_once '../lib/managers/NotificationManager.php';
require_once '../lib/functions.php'; // For validateCSRF

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You are not logged in.']);
    exit();
}

validateCSRF(); // CSRF token validation

$user_id = $_SESSION['user_id'];

$notificationManager = new NotificationManager($conn);
$result = $notificationManager->markAllAsRead($user_id);

if ($result) {
    echo json_encode(['status' => 'success', 'message' => 'All notifications marked as read.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Could not mark all notifications as read.']);
}

$conn->close();
?>
