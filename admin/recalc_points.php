<?php
require '../init.php';
require_once __DIR__ . '/../lib/managers/PointsManager.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: admin_login.php?redirect=recalc_points.php');
    exit();
}

$pointsManager = new PointsManager($conn);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $villageCount = 0;
    $userIds = [];

    $res = $conn->query("SELECT id, user_id FROM villages");
    while ($row = $res->fetch_assoc()) {
        $pointsManager->updateVillagePoints((int)$row['id'], false);
        $userIds[(int)$row['user_id']] = true;
        $villageCount++;
    }
    $res->close();

    $playerCount = 0;
    foreach (array_keys($userIds) as $uid) {
        $pointsManager->updatePlayerPoints($uid, true);
        $playerCount++;
    }

    $message = "Recalculated {$villageCount} villages and {$playerCount} players/tribes.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Recalculate Points</title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        body { background:#f5e9d7; }
        .wrap { max-width: 700px; margin: 40px auto; background:#fff; padding:24px; border-radius:12px; box-shadow:0 10px 18px rgba(0,0,0,0.08); }
        .msg { padding:10px 12px; border-radius:8px; margin-top:12px; }
        .ok { background:#e8f7ef; color:#256d3a; border:1px solid #a9e1b9; }
        .note { color:#4d341a; margin-top:6px; }
        .btn { padding:10px 14px; border:1px solid #8d5c2c; border-radius:8px; background:#fff8ec; cursor:pointer; font-weight:700; color:#3b2410; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Recalculate Points</h1>
    <p>Force-recompute village, player, and tribe points using the current formula (Σ(level^1.2)) and store a snapshot for growth stats.</p>
    <form method="post" onsubmit="return confirm('Recalculate all points now?');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <button type="submit" class="btn">Recalculate now</button>
    </form>
    <?php if ($message): ?>
        <div class="msg ok"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <p class="note">You can link this page from a cron if desired, but a dedicated cron script is recommended for production.</p>
    <p><a href="admin.php" class="btn">Back to admin</a></p>
</div>
</body>
</html>
