<?php
require '../init.php';

$message = '';
$activeTab = 'login'; // Default to login tab

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $activeTab = 'login';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = '<p class="error-message">All fields are required!</p>';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, is_banned FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $db_username, $hashed_password, $is_banned);
        $stmt->fetch();

        if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) {
            $stmt->close();

            if ($is_banned) {
                $message = '<p class="error-message">Your account has been banned.</p>';
            } else {
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $db_username;

                $stmt_check_village = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
                $stmt_check_village->bind_param("i", $id);
                $stmt_check_village->execute();
                $stmt_check_village->store_result();

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
                    header("Location: ../game/world_select.php?redirect=game/game.php");
                } else {
                    $stmt_check_village->close();
                    header("Location: ../game/world_select.php?redirect=player/create_village.php");
                }
                exit();
            }
        } else {
            if ($stmt) $stmt->close();
            $message = '<p class="error-message">Invalid username or password.</p>';
        }
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    require_once '../lib/managers/VillageManager.php';
    $activeTab = 'register';
    
    $username = $_POST['reg_username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['reg_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = '<p class="error-message">All fields are required!</p>';
    } elseif ($password !== $confirm_password) {
        $message = '<p class="error-message">Passwords do not match!</p>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<p class="error-message">Invalid email format!</p>';
    } elseif (!isValidUsername($username)) {
        $message = '<p class="error-message">Username can only contain letters, numbers, and underscores (3-20 characters).</p>';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if ($stmt === false) {
            error_log("Database prepare failed: " . $conn->error);
            $message = '<p class="error-message">A database error occurred.</p>';
        } else {
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $message = '<p class="error-message">Username or email is already taken!</p>';
            } else {
                $stmt->close();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $conn->begin_transaction();

                try {
                    $stmt_user = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                    if ($stmt_user === false) throw new Exception("Database prepare failed for user insert: " . $conn->error);
                    $stmt_user->bind_param("sss", $username, $email, $hashed_password);

                    if (!$stmt_user->execute()) {
                        throw new Exception("Failed to execute user insert query: " . $stmt_user->error);
                    }
                    $user_id = $stmt_user->insert_id;
                    $stmt_user->close();

                    $coordinates = findUniqueCoordinates($conn);
                    if ($coordinates === false) {
                        throw new Exception("Failed to find unique coordinates for the village.");
                    }
                    
                    $village_name = "Village " . htmlspecialchars($username);
                    $initial_wood = INITIAL_WOOD;
                    $initial_clay = INITIAL_CLAY;
                    $initial_iron = INITIAL_IRON;
                    $initial_warehouse_capacity = INITIAL_WAREHOUSE_CAPACITY;
                    $initial_population = INITIAL_POPULATION;
                    
                    $stmt_village = $conn->prepare("INSERT INTO villages (user_id, name, x_coord, y_coord, wood, clay, iron, warehouse_capacity, population, last_resource_update) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    if ($stmt_village === false) throw new Exception("Database prepare failed for village insert: " . $conn->error);
                    $stmt_village->bind_param(
                        "isiiiiiii",
                        $user_id,
                        $village_name,
                        $coordinates['x'],
                        $coordinates['y'],
                        $initial_wood,
                        $initial_clay,
                        $initial_iron,
                        $initial_warehouse_capacity,
                        $initial_population
                    );

                    if (!$stmt_village->execute()) {
                        throw new Exception("Failed to execute village insert query: " . $stmt_village->error);
                    }
                    $village_id = $stmt_village->insert_id;
                    $stmt_village->close();

                    $initial_buildings = ['main', 'sawmill', 'clay_pit', 'iron_mine', 'warehouse', 'farm'];
                    $base_level = 1;

                    foreach ($initial_buildings as $internal_name) {
                        $stmt_get_building_type = $conn->prepare("SELECT id FROM building_types WHERE internal_name = ? LIMIT 1");
                        if ($stmt_get_building_type === false) throw new Exception("Database prepare failed for building type select: " . $conn->error);
                        $stmt_get_building_type->bind_param("s", $internal_name);
                        $stmt_get_building_type->execute();
                        $result_building_type = $stmt_get_building_type->get_result();
                        $building_type = $result_building_type->fetch_assoc();
                        $stmt_get_building_type->close();

                        if (!$building_type) {
                            throw new Exception("Building type {$internal_name} not found for initial setup.");
                        }
                        
                        $building_type_id = $building_type['id'];
                        $stmt_add_building = $conn->prepare("INSERT INTO village_buildings (village_id, building_type_id, level) VALUES (?, ?, ?)");
                        if ($stmt_add_building === false) throw new Exception("Database prepare failed for village building insert: " . $conn->error);
                        $stmt_add_building->bind_param("iii", $village_id, $building_type_id, $base_level);
                        if (!$stmt_add_building->execute()) {
                            throw new Exception("Failed to add building {$internal_name} for village {$village_id}: " . $stmt_add_building->error);
                        }
                        $stmt_add_building->close();
                    }
                    
                    $villageManager = new VillageManager($conn);
                    $villageManager->updateVillagePopulation($village_id);

                    $conn->commit();
                    $message = '<p class="success-message">Registration completed! You can now log in.</p>';
                    $activeTab = 'login';

                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Registration failed for user {$username}: " . $e->getMessage());
                    $message = '<p class="error-message">An error occurred while registering. Try again or contact an administrator.</p>';
                    if (isset($user_id)) {
                        $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
                        if ($stmt_delete_user) {
                            $stmt_delete_user->bind_param("i", $user_id);
                            $stmt_delete_user->execute();
                            $stmt_delete_user->close();
                        }
                    }
                }
            }
            if (isset($stmt) && $stmt) $stmt->close();
        }
    }
}

function findUniqueCoordinates($conn, int $max_coord = 100): array|false {
    $attempts = 0;
    $max_attempts = 1000;

    do {
        $x = random_int(1, $max_coord);
        $y = random_int(1, $max_coord);

        $stmt_check = $conn->prepare("SELECT id FROM villages WHERE x_coord = ? AND y_coord = ?");
        if ($stmt_check === false) {
            error_log("Database prepare failed: " . $conn->error);
            return false;
        }
        $stmt_check->bind_param("ii", $x, $y);
        $stmt_check->execute();
        $stmt_check->store_result();
        $is_taken = $stmt_check->num_rows > 0;
        $stmt_check->close();
        $attempts++;
    } while ($is_taken && $attempts < $max_attempts);

    if ($is_taken) {
        error_log("Failed to find unique coordinates after {$max_attempts} attempts.");
        return false;
    }
    return ['x' => $x, 'y' => $y];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tribal Conquest - Enter the Realm of Strategy</title>
    <link rel="stylesheet" href="../css/tribal_conquest_login.css">
</head>
<body>
    <div class="login-background">
        <div class="login-container">
            <div class="parchment-frame">
                <div class="frame-corner frame-corner-tl"></div>
                <div class="frame-corner frame-corner-tr"></div>
                <div class="frame-corner frame-corner-bl"></div>
                <div class="frame-corner frame-corner-br"></div>
                
                <h1 class="game-title">TRIBAL CONQUEST</h1>
                <p class="game-subtitle">Enter the Realm of Strategy</p>
                
                <div class="tabs">
                    <button class="tab-btn <?= $activeTab === 'login' ? 'active' : '' ?>" data-tab="login">LOGIN</button>
                    <button class="tab-btn <?= $activeTab === 'register' ? 'active' : '' ?>" data-tab="register">REGISTER</button>
                </div>
                
                <?= $message ?>
                
                <!-- Login Form -->
                <div class="tab-content <?= $activeTab === 'login' ? 'active' : '' ?>" id="login-tab">
                    <form action="tribal_conquest_login.php" method="POST" class="auth-form">
                        <input type="hidden" name="action" value="login">
                        <?php if (isset($_SESSION['csrf_token'])): ?>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" placeholder="Username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Password" required>
                        </div>
                        
                        <button type="submit" class="btn-medieval btn-primary">
                            <span class="btn-icon">⚔</span> ENTER BATTLE
                        </button>
                        
                        <div class="form-footer">
                            <a href="#" class="link-medieval">Forgot Password?</a>
                        </div>
                    </form>
                </div>
                
                <!-- Register Form -->
                <div class="tab-content <?= $activeTab === 'register' ? 'active' : '' ?>" id="register-tab">
                    <form action="tribal_conquest_login.php" method="POST" class="auth-form">
                        <input type="hidden" name="action" value="register">
                        <?php if (isset($_SESSION['csrf_token'])): ?>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="reg_username">Username</label>
                            <input type="text" id="reg_username" name="reg_username" placeholder="Username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="Email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_password">Password</label>
                            <input type="password" id="reg_password" name="reg_password" placeholder="Password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                        </div>
                        
                        <button type="submit" class="btn-medieval btn-primary">
                            <span class="btn-icon">🛡</span> CREATE ACCOUNT
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <footer class="login-footer">
            <a href="#" class="footer-link">📖 Game Guide</a>
            <a href="#" class="footer-link">💬 Forums</a>
            <a href="#" class="footer-link">❓ Support</a>
        </footer>
        
        <div class="copyright">
            Copyright © 2025 Tribal Conquest, Inc. All rights reserved.
        </div>
    </div>
    
    <script src="../js/tribal_conquest_login.js"></script>
</body>
</html>
