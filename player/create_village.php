<?php
require '../init.php';
validateCSRF();
require_once __DIR__ . '/../lib/managers/VillageManager.php'; // Updated path
// BuildingManager is not needed directly here because VillageManager handles initial creation

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = '';

$villageManager = new VillageManager($conn);

// Check if the user already has a village (defensive check for direct access)
$existingVillage = $villageManager->getFirstVillage($user_id);

if ($existingVillage) {
    header('Location: ../game/game.php'); // Already has a village, go to the game
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_village'])) {
    // Create the village in a free spot on the current world
    $creationResult = $villageManager->createVillage($user_id);

    if ($creationResult['success']) {
        $message = '<p class="success-message">' . htmlspecialchars($creationResult['message']) . ' Redirecting to the game in a moment...</p>';
        // Store new village ID in session if needed elsewhere
        if (isset($creationResult['village_id'])) {
            $_SESSION['village_id'] = $creationResult['village_id'];
        }
        header('Refresh: 3; url=../game/game.php'); // Redirect to the game after 3 seconds
        // Do not exit immediately so the message stays visible
    } else {
        $message = '<p class="error-message">An error occurred while creating your village: ' . htmlspecialchars($creationResult['message']) . '</p>';
    }
}

// Render the page using the shared template
$pageTitle = 'Create Village';
require '../header.php';
?>
<div class="container">
    <h1>Welcome, <?= htmlspecialchars($username) ?>!</h1>
    <?= $message ?>
    <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST' || strpos($message, 'error-message') !== false): ?>
        <p>It looks like you do not have a village yet.</p>
        <p>Click the button below to found your first settlement and start your adventure!</p>
        <form action="create_village.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="submit" name="create_village" value="Create my first village" class="btn btn-primary">
        </form>
    <?php endif; ?>
</div>
<?php require '../footer.php'; ?>
<?php
// Database connection is handled in init.php
?>
