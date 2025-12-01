<?php
require '../init.php';
// CSRF validation for POST requests is handled automatically in validateCSRF() from functions.php

$message = '';

// Generate unique village coordinates using prepared statements
function findUniqueCoordinates($conn, $max_coord = 100) {
    $attempts = 0;
    $max_attempts = 1000; // Prevent infinite loop

    do {
        $x = rand(1, $max_coord);
        $y = rand(1, $max_coord);

        $stmt_check = $conn->prepare("SELECT id FROM villages WHERE x_coord = ? AND y_coord = ?");
        // Ensure prepare succeeded
        if ($stmt_check === false) {
            error_log("Database prepare failed: " . $conn->error);
            return false; // Database error
        }
        $stmt_check->bind_param("ii", $x, $y);
        $stmt_check->execute();
        $stmt_check->store_result();
        $is_taken = $stmt_check->num_rows > 0;
        $stmt_check->close();
        $attempts++;
    } while ($is_taken && $attempts < $max_attempts);

    if ($is_taken) {
        // Could not find unique coordinates after many attempts - log an error
        error_log("Failed to find unique coordinates after {$max_attempts} attempts.");
        return false;
    }
    return ['x' => $x, 'y' => $y];
}

// --- DATA PROCESSING (REGISTRATION) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../lib/managers/VillageManager.php';
    // validateCSRF(); // Removed here because validateCSRF() is called globally for POST in init.php

    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Input validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = '<p class="error-message">All fields are required!</p>';
    } elseif ($password !== $confirm_password) {
        $message = '<p class="error-message">Passwords do not match!</p>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<p class="error-message">Invalid email format!</p>';
    } elseif (!isValidUsername($username)) { // Username validation
         $message = '<p class="error-message">Username can only contain letters, numbers, and underscores (3-20 characters).</p>';
    } else {
        // Check whether the username or email already exists
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
                $stmt->close(); // Close stmt_check_user_exists before new statements

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $conn->begin_transaction(); // Begin transaction

                try {
                    // 1. Add the user
                    $stmt_user = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                    if ($stmt_user === false) throw new Exception("Database prepare failed for user insert: " . $conn->error);
                    $stmt_user->bind_param("sss", $username, $email, $hashed_password);

                    if (!$stmt_user->execute()) {
                         throw new Exception("Failed to execute user insert query: " . $stmt_user->error);
                    }
                    $user_id = $stmt_user->insert_id; // Get newly created user ID
                    $stmt_user->close(); 

                    // 2. Find unique coordinates and create the village
                    $coordinates = findUniqueCoordinates($conn);
                    if ($coordinates === false) {
                         throw new Exception("Failed to find unique coordinates for the village.");
                    }
                    
                    $village_name = "Village " . htmlspecialchars($username); // Sanitize village name
                    // Use constants from config.php for initial values
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
                    $village_id = $stmt_village->insert_id; // Get the new village ID
                    $stmt_village->close(); 

                    // 3. Add basic buildings
                    $initial_buildings = [
                         'main', 'sawmill', 'clay_pit', 'iron_mine', 'warehouse', 'farm' // Include the farm
                    ];
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
                    
                    // 4. Update village population after adding buildings
                    $villageManager = new VillageManager($conn);
                    $villageManager->updateVillagePopulation($village_id); // Recalculate population

                    $conn->commit(); // Commit transaction if everything went well
                    $message = '<p class="success-message">Registration completed! Your first village has been created with basic buildings. You can now <a href="login.php">log in</a>.</p>';

                } catch (Exception $e) {
                    $conn->rollback(); // Roll back on error
                    error_log("Registration failed for user {$username}: " . $e->getMessage());
                    $message = '<p class="error-message">An error occurred while registering or creating the village. Try again or contact an administrator.</p>';
                    // If the user was added but the village failed, remove the user
                    if (isset($user_id)) {
                         $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
                         if ($stmt_delete_user) {
                            $stmt_delete_user->bind_param("i", $user_id);
                            $stmt_delete_user->execute();
                            $stmt_delete_user->close();
                         }
                    }
                     // Close any open statements on failure
                     if (isset($stmt_user) && $stmt_user) $stmt_user->close();
                     if (isset($stmt_village) && $stmt_village) $stmt_village->close();
                     if (isset($stmt_get_building_type) && $stmt_get_building_type) $stmt_get_building_type->close();
                     if (isset($stmt_add_building) && $stmt_add_building) $stmt_add_building->close();
                }
            }
             // Do not close stmt check user/email exists here; handle after use or in catch
        }
         if (isset($stmt) && $stmt) $stmt->close();
    }
}

// --- PRESENTATION (HTML) ---
$pageTitle = 'Register';
require '../header.php';
?>
<main>
    <div class="form-container">
        <h1>Register</h1>
        <?= $message ?>
        <form action="register.php" method="POST">
            <?php if (isset($_SESSION['csrf_token'])): ?>
                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <?php endif; ?>
            
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <input type="submit" value="Register" class="btn btn-primary">
        </form>
        <p class="mt-2">Already have an account? <a href="login.php">Log in</a>.</p>
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
