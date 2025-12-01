<?php
require '../init.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php?redirect=terms.php');
    exit();
}

$pageTitle = 'Admin - Terms';
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
            <a href="help.php">Help</a>
            <a href="../terms.php" target="_blank">Public Terms</a>
        </div>
        <h1>Terms (Admin)</h1>
        <p>Use this page to keep an internal reference of the current Terms and Conditions and revision notes.</p>
        <h3>Current version</h3>
        <p>Review the public-facing terms at <a href="../terms.php" target="_blank">terms.php</a>. Update this page whenever the public terms change.</p>
        <h3>Revision log</h3>
        <ul>
            <li>v1.0 – Initial draft.</li>
        </ul>
    </div>
</body>
</html>
