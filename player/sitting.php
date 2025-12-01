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

$userId = (int)$_SESSION['user_id'];
$sittingManager = new SittingManager($conn);
$userManager = new UserManager($conn);

$message = '';
$messageType = '';

// Handle new sitter declaration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_sitter') {
    $sitterUsername = trim($_POST['sitter_username'] ?? '');
    $duration = isset($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : 24;
    $sitter = $userManager->getUserByUsername($sitterUsername);
    if (!$sitter) {
        $message = 'User not found.';
        $messageType = 'error';
    } elseif ($sitter['id'] === $userId) {
        $message = 'You cannot set yourself as a sitter.';
        $messageType = 'error';
    } else {
        $result = $sittingManager->createSitting($userId, (int)$sitter['id'], $duration);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// Handle revoke
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke') {
    $sitterId = (int)($_POST['sitter_id'] ?? 0);
    if ($sitterId > 0) {
        $sittingManager->revokeSitting($userId, $sitterId);
        $message = 'Sitter revoked.';
        $messageType = 'success';
    }
}

$activeSitters = $sittingManager->getActiveForOwner($userId);
$youSitFor = $sittingManager->getActiveForSitter($userId);

$pageTitle = 'Account Sitting';
require '../header.php';
?>

<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">&#128101;</span>
            <span>Account Sitting</span>
        </div>
        <div class="header-user">
            Player: <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
        </div>
    </header>

    <div id="main-content">
        <main>
            <h2>Manage sitters</h2>
            <?php if ($message): ?>
                <div class="<?= $messageType === 'success' ? 'success-message' : 'error-message' ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <section class="card">
                <h3>Authorize a sitter</h3>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add_sitter">
                    <label>Player username:</label>
                    <input type="text" name="sitter_username" required>
                    <label>Duration (hours, max 168):</label>
                    <input type="number" name="duration_hours" min="1" max="168" value="24" required>
                    <button class="btn btn-primary" type="submit">Add sitter</button>
                </form>
                <p class="hint">Sitters can access your account until expiry; you can revoke anytime.</p>
            </section>

            <section class="card">
                <h3>Active sitters on your account</h3>
                <?php if (empty($activeSitters)): ?>
                    <p>No active sitters.</p>
                <?php else: ?>
                    <table class="styled-table">
                        <tr><th>Sitter</th><th>Ends at</th><th>Action</th></tr>
                        <?php foreach ($activeSitters as $sitter): ?>
                            <tr>
                                <td><?= htmlspecialchars($sitter['sitter_username']) ?></td>
                                <td><?= htmlspecialchars($sitter['ends_at']) ?></td>
                                <td>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="revoke">
                                        <input type="hidden" name="sitter_id" value="<?= (int)$sitter['sitter_user_id'] ?>">
                                        <button class="btn btn-secondary" type="submit">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </section>

            <section class="card">
                <h3>You can sit for</h3>
                <?php if (empty($youSitFor)): ?>
                    <p>No active sitting assignments.</p>
                <?php else: ?>
                    <table class="styled-table">
                        <tr><th>Account</th><th>Ends at</th><th>Switch</th></tr>
                        <?php foreach ($youSitFor as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['owner_username']) ?></td>
                                <td><?= htmlspecialchars($row['ends_at']) ?></td>
                                <td>
                                    <form method="post" action="/auth/sit_as.php" class="sit-switch-form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="owner_id" value="<?= (int)$row['owner_user_id'] ?>">
                                        <button class="btn btn-primary" type="submit">Play as</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<?php require '../footer.php'; ?>
