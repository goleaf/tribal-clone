<?php
require '../init.php';
validateCSRF();

// ini_set('display_errors', 1); // Remove development-specific settings
// ini_set('display_startup_errors', 1); // Remove development-specific settings
// error_reporting(E_ALL); // Remove development-specific settings

// Remove duplicate session_start() and header()
// session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../lib/managers/VillageManager.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User is not logged in.', 'redirect' => 'auth/login.php']);
    exit();
}

// Ensure the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

// Validate the presence of the new village name
if (!isset($_POST['new_village_name']) || empty($_POST['new_village_name'])) {
    echo json_encode(['success' => false, 'error' => 'No village name provided.']);
    exit();
}

// Extract request data
$new_village_name = trim($_POST['new_village_name']);
$village_id = isset($_POST['village_id']) ? (int)$_POST['village_id'] : 0;
$user_id = $_SESSION['user_id'];

// Validate the new village name (consider moving to VillageManager)
if (strlen($new_village_name) < 3) {
    echo json_encode(['success' => false, 'error' => 'Village name must be at least 3 characters long.']);
    exit();
}

if (strlen($new_village_name) > 50) {
    echo json_encode(['success' => false, 'error' => 'Village name can be at most 50 characters long.']);
    exit();
}

// Ensure the name contains only allowed characters
// Use the same pattern as lib/functions.php -> isValidVillageName
if (!preg_match('/^[a-zA-Z0-9\\s\\-\\._]+$/', $new_village_name)) { // Added \. to allowed chars based on isValidVillageName
     echo json_encode(['success' => false, 'error' => 'Village name contains invalid characters.']);
     exit();
}

// Remove manual DB connection
// require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
// require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Database.php';

try {
    // Use global $conn from init.php
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Failed to connect to the database.']);
        exit();
    }

    // If no specific village ID is provided, use the player's first village
    // Use VillageManager to retrieve the first village when village_id <= 0
    if ($village_id <= 0) {
        $villageManager = new VillageManager($conn);
        $firstVillage = $villageManager->getFirstVillage($user_id);
        if ($firstVillage && isset($firstVillage['id'])) {
            $village_id = $firstVillage['id'];
        } else {
            echo json_encode(['success' => false, 'error' => 'No village found for this user.']);
            exit(); // Exit directly, no manual connection to close
        }
    }

    // Check ownership in VillageManager::renameVillage, so no explicit check here
    /*
    $stmt = $conn->prepare("SELECT id FROM villages WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $village_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $village = $result->fetch_assoc();
    $stmt->close();

    if (!$village) {
        echo json_encode(['success' => false, 'error' => 'You do not have permission to rename this village.']);
        // $database->closeConnection(); // Remove
        exit();
    }
    */

    // Use VillageManager to rename the village
    $villageManager = $villageManager ?? new VillageManager($conn); // Instantiate if not already
    $renameResult = $villageManager->renameVillage($village_id, $user_id, $new_village_name);

    if ($renameResult['success']) {
        // Renaming succeeded
        echo json_encode(['success' => true, 'message' => $renameResult['message']]);

        // Save the new village name in the session if needed (e.g., for header display)
        // This logic can be moved elsewhere later
        $_SESSION['village_name'] = $new_village_name;
    } else {
        // Renaming failed
        echo json_encode(['success' => false, 'error' => $renameResult['message']]);
    }

    // Remove manual DB connection close
    // $database->closeConnection();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
}

// No need for $conn->close(); - handled by init.php if persistent, or closes automatically
?> 
