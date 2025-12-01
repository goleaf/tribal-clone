<?php
require_once '../../init.php';
require_once '../../lib/managers/BuildingManager.php';
require_once '../../lib/managers/VillageManager.php'; // Needed to verify permissions
require_once '../../lib/functions.php'; // For validateCSRF

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You are not logged in.']);
    exit();
}

validateCSRF(); // CSRF token validation

$user_id = $_SESSION['user_id'];
$queue_id = isset($_POST['queue_id']) ? (int)$_POST['queue_id'] : null;
$village_id = isset($_POST['village_id']) ? (int)$_POST['village_id'] : null;

if (!$queue_id || !$village_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters (queue_id, village_id).']);
    exit();
}

$villageManager = new VillageManager($conn);
// Ensure the village belongs to the logged-in user
$village = $villageManager->getVillageInfo($village_id);
if (!$village || $village['user_id'] !== $user_id) {
    echo json_encode(['status' => 'error', 'message' => 'No access to this village.']);
    exit();
}

// Ensure the queue item belongs to this village
$stmt = $conn->prepare("SELECT COUNT(*) FROM building_queue WHERE id = ? AND village_id = ?");
if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Server error (prepare).']);
    exit();
}
$stmt->bind_param("ii", $queue_id, $village_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_row();
$stmt->close();

if ((int)$row[0] === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Queue item does not exist or does not belong to this village.']);
    exit();
}

// Delete the queue item
$stmt = $conn->prepare("DELETE FROM building_queue WHERE id = ?");
if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Server error (prepare delete).']);
    exit();
}
$stmt->bind_param("i", $queue_id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Construction was canceled. Resources were not refunded.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Could not cancel construction.']);
}
$stmt->close();

$conn->close();
?>
