<?php
require '../init.php';
validateCSRF();
require_once '../lib/managers/ResearchManager.php';

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

// Ensure all required parameters are present
if (!isset($_POST['village_id']) || !isset($_POST['research_type_id'])) {
    $response = [
        'success' => false,
        'error' => 'Missing required parameters.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$village_id = (int) $_POST['village_id'];
$research_type_id = (int) $_POST['research_type_id'];

// Confirm that the village belongs to the current player
$stmt = $conn->prepare("SELECT id, wood, clay, iron FROM villages WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $village_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$village = $result->fetch_assoc();
$stmt->close();

if (!$village) {
    $response = [
        'success' => false,
        'error' => 'The village was not found or you do not have access to it.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fetch details about the research type
$researchManager = new ResearchManager($conn);
$research_type = $researchManager->getResearchTypeById($research_type_id);
if (!$research_type) {
    $response = [
        'success' => false,
        'error' => 'Invalid research type.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get the current research level (if it exists)
$current_level = 0;
$stmt = $conn->prepare("
    SELECT level FROM village_research 
    WHERE village_id = ? AND research_type_id = ?
");
$stmt->bind_param("ii", $village_id, $research_type_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $current_level = (int) $row['level'];
}
$stmt->close();

$target_level = $current_level + 1;

// Make sure the same research is not already queued
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM research_queue 
    WHERE village_id = ? AND research_type_id = ?
");
$stmt->bind_param("ii", $village_id, $research_type_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result['count'] > 0) {
    $response = [
        'success' => false,
        'error' => 'Research of this type is already in progress.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Verify that all requirements to conduct the research are met
$requirements_check = $researchManager->checkResearchRequirements($research_type_id, $village_id, $target_level);

if (!$requirements_check['can_research']) {
    $response = [
        'success' => false,
        'error' => 'Research requirements are not met.',
        'details' => $requirements_check
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Calculate research cost
$research_cost = $researchManager->getResearchCost($research_type_id, $target_level);

if (!$research_cost) {
    $response = [
        'success' => false,
        'error' => 'Unable to calculate research cost.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Confirm the player has enough resources
if ($village['wood'] < $research_cost['wood'] || 
    $village['clay'] < $research_cost['clay'] || 
    $village['iron'] < $research_cost['iron']) {
    $response = [
        'success' => false,
        'error' => 'Not enough resources to start the research.',
        'required' => $research_cost,
        'available' => [
            'wood' => $village['wood'],
            'clay' => $village['clay'],
            'iron' => $village['iron']
        ]
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Begin the research
$resources = [
    'wood' => $village['wood'],
    'clay' => $village['clay'],
    'iron' => $village['iron']
];

$result = $researchManager->startResearch($village_id, $research_type_id, $target_level, $resources);

if ($result['success']) {
    // Pull updated resource info
    $stmt = $conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $updated_resources = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $response = [
        'success' => true,
        'message' => 'Research started successfully.',
        'research_id' => $result['research_id'],
        'research_name' => $research_type['name'],
        'level_after' => $target_level,
        'ends_at' => $result['ends_at'],
        'updated_resources' => $updated_resources
    ];
} else {
    $response = [
        'success' => false,
        'error' => $result['error']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 
