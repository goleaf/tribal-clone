<?php
require '../init.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php?redirect=help.php');
    exit();
}

$pageTitle = 'Admin - Help';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        body { background: #f5e9d7; }
        .admin-page { max-width: 900px; margin: 40px auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
        .admin-nav { margin-bottom: 20px; }
        .admin-nav a { margin-right: 12px; color: #8d5c2c; text-decoration: none; font-weight: 600; }
        .admin-nav a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="admin-page">
        <div class="admin-nav">
            <a href="admin.php">Back to admin dashboard</a>
            <a href="terms.php">Terms</a>
            <a href="../help.php" target="_blank">Public Help</a>
        </div>
        <h1>Help (Admin)</h1>
        <p>Use this page to document internal admin procedures, troubleshooting steps, and FAQs for moderators and administrators.</p>
        <h3>Common tasks</h3>
        <ul>
            <li>Reset a user password via the users tab.</li>
            <li>Verify world settings in the world/admin panel.</li>
            <li>Review logs in <code>admin/logs/</code> for recent errors.</li>
        </ul>
        <h3>Support process</h3>
        <p>When responding to player issues, gather the user ID, world ID, and timestamps. Note any reproduction steps and attach to the issue tracker.</p>
    </div>
</body>
</html>
