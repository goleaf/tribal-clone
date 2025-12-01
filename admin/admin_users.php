<h2>User management</h2>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_admin'])) {
    validateCSRF();
    $uid = (int)$_POST['toggle_admin'];
    $stmt = $conn->prepare('UPDATE users SET is_admin = NOT is_admin WHERE id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->close();
    echo '<p class="success-message">Privileges updated.</p>';
}
$stmt = $conn->prepare('SELECT id, username, email, is_admin FROM users');
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();
?>
<form method="POST" action="admin.php?screen=users">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <table class="users-table">
        <thead>
            <tr><th>ID</th><th>User</th><th>Email</th><th>Admin</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php while ($user = $users->fetch_assoc()): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= $user['is_admin'] ? 'Yes' : 'No' ?></td>
                <td>
                    <button type="submit" name="toggle_admin" value="<?= $user['id'] ?>">
                        <?= $user['is_admin'] ? 'Remove admin' : 'Grant admin' ?>
                    </button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</form>
