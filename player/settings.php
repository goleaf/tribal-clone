<?php
declare(strict_types=1);
require '../init.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php'; // Updated path
require_once __DIR__ . '/../lib/managers/UserManager.php'; // Updated path

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = '';

$villageManager = new VillageManager($conn); // Instantiate VillageManager
$village_id = $villageManager->getFirstVillage($user_id); // Get the user's first village ID
$village = null;
if ($village_id) {
    $village = $villageManager->getVillageInfo($village_id); // Get village details if an ID exists
}

// Initialize UserManager
$userManager = new UserManager($conn); // Instantiate UserManager

// Handle email change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {
    $new_email = $_POST['new_email'] ?? '';
    $result = $userManager->changeEmail($user_id, $new_email);
    if ($result['success']) {
        $message = '<p class="success-message">' . htmlspecialchars($result['message']) . '</p>';
    } else {
        $message = '<p class="error-message">' . htmlspecialchars($result['message']) . '</p>';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $result = $userManager->changePassword($user_id, $current_password, $new_password, $confirm_password);
    if ($result['success']) {
        $message = '<p class="success-message">' . htmlspecialchars($result['message']) . '</p>';
    } else {
        $message = '<p class="error-message">' . htmlspecialchars($result['message']) . '</p>';
    }
}
?>
<?php require '../header.php'; ?>

<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">&#9881;</span>
            <span>Settings</span>
        </div>
        <div class="header-user">
            Player: <?= htmlspecialchars($username) ?><br>
            <?php if (isset($village) && $village): // Check if village data is available ?>
                <span class="village-name-display" data-village-id="<?= $village['id'] ?>"><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</span>
            <?php endif; ?>
        </div>
    </header>
    <div id="main-content">

        <main>
            <h2>Account settings</h2>
            <div class="settings-stats" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
                <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                    <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Username</div>
                    <div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($username) ?></div>
                </div>
                <?php if ($village): ?>
                <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                    <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Home village</div>
                    <div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($village['name']) ?></div>
                    <div style="font-size:12px;color:#555;">(<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</div>
                </div>
                <?php endif; ?>
            </div>
            <?php echo $message; ?>
            <section class="form-container">
                <h3>Change email address</h3>
                <form action="settings.php" method="POST">
                    <label for="new_email">New email</label>
                    <input type="email" id="new_email" name="new_email" required>
                    <input type="submit" name="change_email" value="Change email" class="btn btn-primary mt-2">
                </form>
            </section>
            <section class="form-container mt-3">
                <h3>Change password</h3>
                <form action="settings.php" method="POST">
                    <label for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" required>

                    <label for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" required>

                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>

                    <input type="submit" name="change_password" value="Change password" class="btn btn-primary mt-2">
                </form>
            </section>
        </main>
    </div>
</div>
<?php require '../footer.php'; ?> 
