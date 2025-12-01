<?php
require '../init.php';
// Redirect if already admin
$redirectTarget = isset($_GET['redirect']) ? $_GET['redirect'] : 'admin.php';
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: ' . $redirectTarget);
    exit();
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $redirectTarget = $_POST['redirect'] ?? $redirectTarget;
    if (empty($username) || empty($password)) {
        $message = '<p class="error-message">All fields are required!</p>';
    } else {
        $stmt = $conn->prepare('SELECT id, password, is_admin FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($id, $hash, $is_admin);
        if ($stmt->fetch() && password_verify($password, $hash) && $is_admin) {
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = true;
            header('Location: ' . $redirectTarget);
            exit();
        } else {
            $message = '<p class="error-message">Invalid credentials or insufficient permissions.</p>';
        }
        $stmt->close();
    }
}
$pageTitle = 'Admin Panel - Login';
require '../header.php';
?>
<main>
    <div class="form-container">
        <h1>Admin Panel - Login</h1>
        <?= $message ?>
        <form method="POST" action="admin_login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget) ?>">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <input type="submit" value="Log in" class="btn btn-primary">
        </form>
    </div>
</main>
<?php require '../footer.php'; ?> 
