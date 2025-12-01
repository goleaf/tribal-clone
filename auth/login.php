<?php
require '../init.php';
// CSRF validation for POST requests is handled automatically in validateCSRF() from functions.php

$message = '';

// --- DATA PROCESSING (LOGIN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validateCSRF(); // Removed here because validateCSRF() is called globally for POST in init.php

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Basic input validation
    if (empty($username) || empty($password)) {
        $message = '<p class="error-message">All fields are required!</p>';
    } else {
        // Use a prepared statement to fetch user data
        $stmt = $conn->prepare("SELECT id, username, password, is_banned FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $db_username, $hashed_password, $is_banned);
        $stmt->fetch();

        if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) {
            $stmt->close(); // Close the first query

            if ($is_banned) {
                 $message = '<p class="error-message">Your account has been banned.</p>';
            } else {
                // Set session
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $db_username;

                // Check whether the user already has a village
                $stmt_check_village = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
                $stmt_check_village->bind_param("i", $id);
                $stmt_check_village->execute();
                $stmt_check_village->store_result();

                // Touch last_activity_at for inactivity tracking
                if (dbColumnExists($conn, 'users', 'last_activity_at')) {
                    $touch = $conn->prepare("UPDATE users SET last_activity_at = NOW() WHERE id = ?");
                    if ($touch) {
                        $touch->bind_param("i", $id);
                        $touch->execute();
                        $touch->close();
                    }
                }

                if ($stmt_check_village->num_rows > 0) {
                    $stmt_check_village->close();
                    // Redirect to world selection or the game
                    header("Location: ../game/world_select.php?redirect=game/game.php");
                } else {
                     $stmt_check_village->close();
                    // Redirect to village creation
                    header("Location: ../game/world_select.php?redirect=player/create_village.php");
                }
                exit();
            }
        } else {
             if ($stmt) $stmt->close(); // Close the query even if no user was found
            $message = '<p class="error-message">Invalid username or password.</p>';
        }
    }
}

// --- PRESENTATION (HTML) ---
$pageTitle = 'Login';
require '../header.php';
?>
<main>
    <div class="form-container">
        <h1>Login</h1>
        <?= $message ?>
        <form action="login.php" method="POST">
            <?php if (isset($_SESSION['csrf_token'])): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <?php endif; ?>
            
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <input type="submit" value="Log in" class="btn btn-primary">
        </form>
        <p class="mt-2">Don't have an account? <a href="register.php">Register</a>.</p>
        <p><a href="../index.php">Back to homepage</a>.</p>
    </div>
</main>
<?php
require '../footer.php';
// Close the database connection after rendering the page
if (isset($database)) {
    $database->closeConnection();
}
?>
