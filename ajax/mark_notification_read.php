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
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : null;

if (!$notification_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing notification ID.']);
    exit();
}

$notificationManager = new NotificationManager($conn);
$result = $notificationManager->markAsRead($notification_id, $user_id);

if ($result) {
    echo json_encode(['status' => 'success', 'message' => 'Notification marked as read.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Could not mark the notification as read.']);
}

$conn->close();
?>
