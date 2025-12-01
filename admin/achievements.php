<?php
require '../init.php';
require_once __DIR__ . '/../lib/managers/AchievementManager.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: admin_login.php?redirect=achievements.php');
    exit();
}

$achievementManager = new AchievementManager($conn); // ensures schema/seed

$message = '';
$error = '';

$conditionTypes = [
    'building_level' => 'Building level',
    'resource_stock' => 'Resource stock',
    'units_trained' => 'Units trained',
    'points_total' => 'Points total',
    'enemies_defeated' => 'Enemies defeated',
    'successful_attack' => 'Successful attacks',
    'successful_defense' => 'Successful defenses',
    'conquest' => 'Conquests',
    'all_buildings_max' => 'All buildings at max',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (in_array($action, ['create', 'update'], true)) {
        $internal = trim($_POST['internal_name'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'general');
        $condition_type = $_POST['condition_type'] ?? '';
        $condition_target = trim($_POST['condition_target'] ?? '');
        $condition_value = (int)($_POST['condition_value'] ?? 0);
        $reward_wood = (int)($_POST['reward_wood'] ?? 0);
        $reward_clay = (int)($_POST['reward_clay'] ?? 0);
        $reward_iron = (int)($_POST['reward_iron'] ?? 0);
        $reward_points = (int)($_POST['reward_points'] ?? 0);

        if ($internal === '' || $name === '' || $description === '' || $condition_type === '' || $condition_value <= 0) {
            $error = 'Please fill all required fields (internal name, name, description, condition type, value).';
        } elseif (!array_key_exists($condition_type, $conditionTypes)) {
            $error = 'Invalid condition type.';
        } else {
            if ($action === 'create') {
                $stmt = $conn->prepare("
                    INSERT INTO achievements
                    (internal_name, name, description, category, condition_type, condition_target, condition_value, reward_wood, reward_clay, reward_iron, reward_points)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt) {
                    $stmt->bind_param(
                        "ssssssiiiii",
                        $internal,
                        $name,
                        $description,
                        $category,
                        $condition_type,
                        $condition_target,
                        $condition_value,
                        $reward_wood,
                        $reward_clay,
                        $reward_iron,
                        $reward_points
                    );
                    if ($stmt->execute()) {
                        $message = 'Achievement created.';
                    } else {
                        $error = 'Insert failed: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            } elseif ($action === 'update' && $id > 0) {
                $stmt = $conn->prepare("
                    UPDATE achievements
                    SET internal_name = ?, name = ?, description = ?, category = ?, condition_type = ?, condition_target = ?, condition_value = ?, reward_wood = ?, reward_clay = ?, reward_iron = ?, reward_points = ?
                    WHERE id = ?
                ");
                if ($stmt) {
                    $stmt->bind_param(
                        "ssssssiiiiii",
                        $internal,
                        $name,
                        $description,
                        $category,
                        $condition_type,
                        $condition_target,
                        $condition_value,
                        $reward_wood,
                        $reward_clay,
                        $reward_iron,
                        $reward_points,
                        $id
                    );
                    if ($stmt->execute()) {
                        $message = 'Achievement updated.';
                    } else {
                        $error = 'Update failed: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        // Clean user links then delete achievement
        $conn->query("DELETE FROM user_achievements WHERE achievement_id = {$id}");
        $stmt = $conn->prepare("DELETE FROM achievements WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = 'Achievement deleted.';
            } else {
                $error = 'Delete failed: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    header('Location: achievements.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit();
}

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
    $stmt = $conn->prepare("SELECT * FROM achievements WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $editRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$achievements = [];
$res = $conn->query("SELECT * FROM achievements ORDER BY category ASC, name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $achievements[] = $row;
    }
    $res->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Achievements</title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        body { background: #f5e9d7; }
        .admin-wrap { max-width: 1200px; margin: 30px auto; background:#fff; padding:24px; border-radius:12px; box-shadow:0 10px 18px rgba(0,0,0,0.08); }
        h1 { margin-top:0; }
        table { width:100%; border-collapse:collapse; margin-top:16px; }
        th, td { border:1px solid #e0c9a6; padding:8px; }
        th { background:#f5e9d7; }
        .msg { padding:10px 12px; margin-bottom:10px; border-radius:8px; }
        .msg.ok { background:#e8f7ef; color:#256d3a; border:1px solid #a9e1b9; }
        .msg.err { background:#fdecea; color:#b43728; border:1px solid #f3c1bb; }
        .form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px; }
        input[type=text], input[type=number], textarea, select { width:100%; padding:8px; border:1px solid #d2b17a; border-radius:6px; background:#fffdfa; }
        textarea { min-height:80px; resize:vertical; }
        .actions { display:flex; gap:8px; }
        .btn-small { padding:6px 10px; border-radius:6px; border:1px solid #8d5c2c; background:#fff8ec; color:#3b2410; text-decoration:none; }
        .btn-small.danger { border-color:#c0392b; color:#c0392b; }
        .top-bar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
    </style>
</head>
<body>
<div class="admin-wrap">
    <div class="top-bar">
        <div>
            <h1>Achievements (Admin)</h1>
            <a href="admin.php" class="btn-small">Back to admin</a>
        </div>
        <div>
            <span style="font-weight:600;">Tip:</span> Players see these on <code>/player/achievements.php</code>. Rewards apply instantly when unlocked.
        </div>
    </div>

    <?php if ($message): ?><div class="msg ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <h2><?= $editRow ? 'Edit achievement' : 'Create achievement' ?></h2>
    <form method="post" style="margin-bottom:20px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
        <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div>
                <label>Internal name</label>
                <input type="text" name="internal_name" required value="<?= htmlspecialchars($editRow['internal_name'] ?? '') ?>">
            </div>
            <div>
                <label>Display name</label>
                <input type="text" name="name" required value="<?= htmlspecialchars($editRow['name'] ?? '') ?>">
            </div>
            <div>
                <label>Category</label>
                <input type="text" name="category" value="<?= htmlspecialchars($editRow['category'] ?? 'general') ?>">
            </div>
            <div>
                <label>Condition type</label>
                <select name="condition_type" required>
                    <option value="">Select...</option>
                    <?php foreach ($conditionTypes as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= ($editRow && $editRow['condition_type'] === $key) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Condition target (optional)</label>
                <input type="text" name="condition_target" value="<?= htmlspecialchars($editRow['condition_target'] ?? '') ?>">
            </div>
            <div>
                <label>Condition value</label>
                <input type="number" name="condition_value" min="1" required value="<?= htmlspecialchars($editRow['condition_value'] ?? 1) ?>">
            </div>
        </div>
        <div class="form-grid" style="margin-top:10px;">
            <div><label>Reward wood</label><input type="number" name="reward_wood" min="0" value="<?= htmlspecialchars($editRow['reward_wood'] ?? 0) ?>"></div>
            <div><label>Reward clay</label><input type="number" name="reward_clay" min="0" value="<?= htmlspecialchars($editRow['reward_clay'] ?? 0) ?>"></div>
            <div><label>Reward iron</label><input type="number" name="reward_iron" min="0" value="<?= htmlspecialchars($editRow['reward_iron'] ?? 0) ?>"></div>
            <div><label>Reward points</label><input type="number" name="reward_points" min="0" value="<?= htmlspecialchars($editRow['reward_points'] ?? 0) ?>"></div>
        </div>
        <div style="margin-top:10px;">
            <label>Description</label>
            <textarea name="description" required><?= htmlspecialchars($editRow['description'] ?? '') ?></textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit" class="btn-small"><?= $editRow ? 'Update' : 'Create' ?></button>
            <?php if ($editRow): ?>
                <a href="achievements.php" class="btn-small">Cancel edit</a>
            <?php endif; ?>
        </div>
    </form>

    <h2>Existing achievements</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Internal</th>
                <th>Name</th>
                <th>Category</th>
                <th>Condition</th>
                <th>Value</th>
                <th>Rewards</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($achievements as $row): ?>
            <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= htmlspecialchars($row['internal_name']) ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['condition_type']) ?><?php if (!empty($row['condition_target'])): ?> (<?= htmlspecialchars($row['condition_target']) ?>)<?php endif; ?></td>
                <td><?= (int)$row['condition_value'] ?></td>
                <td>
                    <?php
                        $parts = [];
                        if ((int)$row['reward_wood'] > 0) $parts[] = 'W+'.(int)$row['reward_wood'];
                        if ((int)$row['reward_clay'] > 0) $parts[] = 'C+'.(int)$row['reward_clay'];
                        if ((int)$row['reward_iron'] > 0) $parts[] = 'I+'.(int)$row['reward_iron'];
                        if ((int)$row['reward_points'] > 0) $parts[] = 'Pts+'.(int)$row['reward_points'];
                        echo $parts ? implode(', ', $parts) : 'None';
                    ?>
                </td>
                <td class="actions">
                    <a class="btn-small" href="achievements.php?edit=<?= (int)$row['id'] ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this achievement?');" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <button type="submit" class="btn-small danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
