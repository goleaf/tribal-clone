<?php
require '../init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') validateCSRF();
require_once '../lib/managers/ResearchManager.php';

// Database connection provided by init.php context
$researchManager = new ResearchManager($conn);

// Verify that the user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = [
        'success' => false,
        'error' => 'You are not logged in.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

// Ensure we received the research queue ID to cancel
if (!isset($_POST['research_queue_id'])) {
    $response = [
        'success' => false,
        'error' => 'Missing research ID to cancel.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$research_queue_id = (int) $_POST['research_queue_id'];

// Confirm the research exists and belongs to the player
$stmt = $conn->prepare("
    SELECT rq.id, rq.village_id, v.user_id, rt.name 
    FROM research_queue rq
    JOIN villages v ON rq.village_id = v.id
    JOIN research_types rt ON rq.research_type_id = rt.id
    WHERE rq.id = ?
");
$stmt->bind_param("i", $research_queue_id);
$stmt->execute();
$result = $stmt->get_result();
$queue_item = $result->fetch_assoc();
$stmt->close();

if (!$queue_item) {
    $response = [
        'success' => false,
        'error' => 'No research found for the provided ID.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Ensure the research belongs to the current user
if ($queue_item['user_id'] != $user_id) {
    $response = [
        'success' => false,
        'error' => 'You do not have permission to cancel this research.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Cancel the research and refund part of the resources
$cancel_result = $researchManager->cancelResearch($research_queue_id, $user_id);

if ($cancel_result['success']) {
    // Pull updated resource values
    $stmt = $conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
    $stmt->bind_param("i", $queue_item['village_id']);
    $stmt->execute();
    $updated_resources = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $response = [
        'success' => true,
        'message' => 'Research "' . $queue_item['name'] . '" has been cancelled.',
        'refunded' => $cancel_result['refunded'],
        'updated_resources' => $updated_resources
    ];
} else {
    $response = [
        'success' => false,
        'error' => $cancel_result['error']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 
