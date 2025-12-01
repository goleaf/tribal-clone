<?php
require '../init.php';

// Simple admin guard: require admin session; if not present, send to admin login
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header("Location: admin_login.php?redirect={$redirect}");
    exit();
}

$villageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($villageId <= 0) {
    die('Invalid village id.');
}

// Fetch village with owner
$stmt = $conn->prepare("
    SELECT v.*, u.username AS owner_name
    FROM villages v
    JOIN users u ON v.user_id = u.id
    WHERE v.id = ?
");
$stmt->bind_param('i', $villageId);
$stmt->execute();
$village = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$village) {
    die('Village not found.');
}

// Basic lists for display
$resourceFields = ['wood' => 'Wood', 'clay' => 'Clay', 'iron' => 'Iron', 'population' => 'Population'];
$buildingFields = [
    'main' => 'Town hall',
    'barracks' => 'Barracks',
    'stable' => 'Stable',
    'garage' => 'Workshop',
    'smithy' => 'Smithy',
    'market' => 'Market',
    'wood' => 'Timber camp',
    'clay' => 'Clay pit',
    'iron' => 'Iron mine',
    'farm' => 'Farm',
    'storage' => 'Warehouse',
    'wall' => 'Wall'
];

// Pull unit counts if present in village_units table
$unitCounts = [];
$unitStmt = $conn->prepare("
    SELECT ut.internal_name, ut.name, vu.count
    FROM village_units vu
    JOIN unit_types ut ON vu.unit_type_id = ut.id
    WHERE vu.village_id = ?
");
if ($unitStmt) {
    $unitStmt->bind_param('i', $villageId);
    $unitStmt->execute();
    $unitRes = $unitStmt->get_result();
    while ($row = $unitRes->fetch_assoc()) {
        $unitCounts[] = $row;
    }
    $unitStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Village details</title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        body { background: #f5e9d7; font-family: var(--font-main, Arial, sans-serif); }
        .container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px #ccc; padding: 24px; }
        h1 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { padding: 10px 8px; border: 1px solid #e0c9a6; text-align: left; }
        th { background: #f5e9d7; }
        .back-link { display: inline-block; margin-bottom: 10px; color: #8d5c2c; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <a href="admin.php?tab=villages" class="back-link">← Back to villages</a>
    <h1><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</h1>
    <p><strong>Owner:</strong> <?= htmlspecialchars($village['owner_name']) ?> (ID: <?= (int)$village['user_id'] ?>)</p>
    <p><strong>Village ID:</strong> <?= (int)$village['id'] ?></p>

    <h3>Resources</h3>
    <table>
        <thead><tr><th>Type</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach ($resourceFields as $key => $label): ?>
            <tr>
                <td><?= $label ?></td>
                <td><?= isset($village[$key]) ? (int)$village[$key] : 0 ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Building levels</h3>
    <table>
        <thead><tr><th>Building</th><th>Level</th></tr></thead>
        <tbody>
        <?php foreach ($buildingFields as $key => $label): ?>
            <tr>
                <td><?= $label ?></td>
                <td><?= isset($village[$key]) ? (int)$village[$key] : 0 ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Units</h3>
    <?php if (!empty($unitCounts)): ?>
        <table>
            <thead><tr><th>Unit</th><th>Count</th></tr></thead>
            <tbody>
            <?php foreach ($unitCounts as $unit): ?>
                <tr>
                    <td><?= htmlspecialchars($unit['name'] ?? $unit['internal_name']) ?></td>
                    <td><?= (int)$unit['count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No units recorded for this village.</p>
    <?php endif; ?>
</div>
</body>
</html>
