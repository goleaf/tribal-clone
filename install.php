<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tribal Wars Installer - New Edition</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* Styles for the main installer container */
        .install-container {
            max-width: 800px;
            margin: 20px auto;
            padding: var(--spacing-lg);
            background-color: var(--beige-light);
            border: 1px solid var(--beige-dark);
            border-radius: var(--border-radius-medium);
            box-shadow: var(--box-shadow-default);
            font-family: var(--font-main);
        }

        /* Styles for individual installation stages */
        .install-stage {
            margin-bottom: var(--spacing-xl);
            padding: var(--spacing-md);
            border: 1px solid var(--beige-dark);
            border-radius: var(--border-radius-medium);
            background-color: var(--beige-medium);
            box-shadow: var(--box-shadow-inset);
        }

        .install-stage h3 {
            color: var(--brown-secondary);
            margin-top: 0;
            margin-bottom: var(--spacing-sm);
            border-bottom: 2px solid var(--beige-dark);
            padding-bottom: var(--spacing-xs);
            font-size: var(--font-size-medium);
        }

        /* Styles for lists within stages (e.g., list of tables, files) */
        .install-stage ul {
            list-style: none;
            padding: 0;
            margin: var(--spacing-sm) 0;
        }

        .install-stage li {
            padding: var(--spacing-xs) 0;
            border-bottom: 1px dashed var(--beige-darker);
            margin-bottom: var(--spacing-xs);
        }

        .install-stage li:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        /* Styles for success messages */
        .success {
            color: var(--green-success);
            font-weight: bold;
        }

        /* Styles for error messages */
        .error {
            color: var(--red-error);
            font-weight: bold;
        }

        /* Style for clickable error message to show details */
        .error.clickable {
            cursor: pointer;
            text-decoration: underline;
        }

        .error.clickable:hover {
            color: var(--red-error-bg); /* Lighter red on hover */
        }

        /* Style for the text indicating show/hide details */
        .show-details {
            font-weight: normal;
            font-size: var(--font-size-small);
            margin-left: var(--spacing-xs);
        }

        /* Container for detailed SQL errors - hidden by default */
        .sql-errors {
            margin-top: var(--spacing-sm);
            padding: var(--spacing-sm);
            background-color: var(--red-error-bg);
            border: 1px dashed var(--red-error);
            border-radius: var(--border-radius-small);
            display: none; /* Hidden by default */
        }

        /* Styles for individual error details within the container */
        .error-detail {
            margin-bottom: var(--spacing-sm);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px dashed var(--red-error);
        }

        .error-detail:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .error-detail strong {
            color: var(--red-error);
        }

        .error-detail pre {
            background-color: #fff;
            padding: var(--spacing-xs);
            border: 1px solid var(--red-error-bg);
            border-radius: var(--border-radius-small);
            overflow-x: auto;
            font-size: var(--font-size-small);
            margin-top: var(--spacing-xs);
        }

        /* Style for horizontal rule separators */
        hr {
            border: none;
            height: 1px;
            background-color: var(--beige-dark);
            margin: var(--spacing-lg) 0;
        }

        /* Styles for the admin form container */
        .form-container {
            max-width: 400px;
            margin: var(--spacing-xl) auto;
            background-color: var(--beige-medium);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius-medium);
            box-shadow: var(--box-shadow-default);
            border: 1px solid var(--beige-dark);
        }

        .form-container label {
            display: block;
            margin-bottom: var(--spacing-xs);
            font-weight: bold;
            color: var(--brown-secondary);
        }

        .form-container input[type="text"],
        .form-container input[type="password"] {
            width: 100%;
            padding: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
            border: 1px solid var(--beige-dark);
            border-radius: var(--border-radius-small);
            background-color: var(--beige-light);
            color: #333;
        }

        .form-container button {
            width: 100%;
            /* Using general button styles from main.css */
        }

        /* Specific success message for admin creation */
        .admin-success-message {
             color: var(--green-success);
            background-color: var(--green-success-bg);
            border-left: 4px solid var(--green-success);
            padding: var(--spacing-sm);
            border-radius: var(--border-radius-small);
            margin: var(--spacing-md) 0;
             box-shadow: var(--box-shadow-default);
             text-align: center;
        }

        .admin-success-message a {
            color: var(--green-success);
            text-decoration: underline;
             font-weight: bold;
        }

        /* Specific error message for admin creation */
         .admin-error-message {
            color: var(--red-error);
            background-color: var(--red-error-bg);
            border-left: 4px solid var(--red-error);
            padding: var(--spacing-sm);
            border-radius: var(--border-radius-small);
            margin: var(--spacing-md) 0;
             box-shadow: var(--box-shadow-default);
             text-align: center;
        }


    </style>
</head>
<body>
<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">&#9881;</span>
            <span class="game-name">Installer</span>
        </div>
    </header>
    <main class="install-container">
<?php
// Installation handling: GET runs SQL and shows the admin form, POST creates the admin
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once 'config/config.php';
    require_once 'lib/Database.php';

    // Enable full error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Fast path for SQLite: use the ready schema and skip MySQL-specific steps
    if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
        $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn = $database->getConnection();

        $schemaFile = 'docs/sql/sqlite_schema.sql';
        $schemaSql = file_get_contents($schemaFile);
        $errors = [];

        echo "<h2>Installation (SQLite)</h2>";
        echo "<div class='install-stage'>";
        echo "<h3>Loading database schema</h3>";
        if ($schemaSql === false) {
            echo "<div class='error'>&#10060; Failed to read the schema file.</div>";
        } else {
            $queries = array_filter(array_map('trim', explode(';', $schemaSql)));
            $ok = true;
            $counter = 0;
            foreach ($queries as $q) {
                $counter++;
                try {
                    $result = $conn->query($q);
                    if ($result === false) {
                        $ok = false;
                        $errors[] = "Error in query #$counter: " . ($conn->error ?? 'unknown error');
                    }
                } catch (Throwable $e) {
                    $ok = false;
                    $errors[] = "Exception in query #$counter: " . $e->getMessage();
                }
            }

            if ($ok) {
                echo "<div class='success'>&#9989; SQLite schema loaded.</div>";
            } else {
                echo "<div class='error'>&#10060; Errors occurred while loading the schema.</div>";
                if ($errors) {
                    echo "<div class='sql-errors'>";
                    foreach ($errors as $err) {
                        echo "<div class='error-detail'>" . htmlspecialchars($err) . "</div>";
                    }
                    echo "</div>";
                }
            }
        }
        echo "</div>";

        echo "<div class='success'>&#9989; Database ready. Create an administrator account:</div>";
        echo '<form method="POST" class="form-container">';
        echo '<label for="admin_username">Admin username</label>';
        echo '<input type="text" id="admin_username" name="admin_username" required>';
        echo '<label for="admin_password">Admin password</label>';
        echo '<input type="password" id="admin_password" name="admin_password" required>';
        echo '<label for="admin_password_confirm">Confirm password</label>';
        echo '<input type="password" id="admin_password_confirm" name="admin_password_confirm" required>';
        echo '<button type="submit">Create administrator</button>';
        echo '</form>';
        exit();
    }

    // Helper to execute SQL queries from a file
    function executeSqlFile($conn, $filePath, &$errorMessages) {
        $sql = file_get_contents($filePath);
        if ($sql === false) {
            $errorMessages[] = "Error: Unable to read SQL file: " . htmlspecialchars($filePath);
            return false;
        }

        // Split into individual statements
        $queries = explode(';', $sql);
        $success = true;
        $queryCount = 0;
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $queryCount++;
                try {
                    if ($conn->query($query) !== TRUE) {
                        $errorMsg = "&nbsp;&nbsp;<strong>Error in query #$queryCount:</strong> " . $conn->error . "<pre>" . htmlspecialchars($query) . "</pre>";
                        $errorMessages[] = $errorMsg;
                        $success = false;
                    }
                } catch (Throwable $e) {
                    $errorMsg = "&nbsp;&nbsp;<strong>SQL exception in query #$queryCount:</strong> " . $e->getMessage() . "<pre>" . htmlspecialchars($query) . "</pre>";
                    $errorMessages[] = $errorMsg;
                    $success = false;
                }
            }
        }
        
        return $success;
    }

    echo "<h2>Database table installation:</h2>";

    // --- Step 1/4: Connect to the database server and create the database ---
    echo "<div class='install-stage'>"; // Container for step
    echo "<h3>Step 1/4: Connecting to the database and creating the schema...</h3>";
    $databaseNoDb = new Database(DB_HOST, DB_USER, DB_PASS, null);
    $conn_no_db = $databaseNoDb->getConnection();

    if (!$conn_no_db) {
        echo "<div class='error'>&#10060; Database connection error.</div>";
        echo "</div>"; // Close step container
        die(); // Stop installation on connection failure
    }

    // Create the database
    $sql_create_db = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    if ($conn_no_db->query($sql_create_db) === TRUE) {
        echo "<div class='success'>&#9989; Database '" . DB_NAME . "' created successfully or already exists.</div>";
    } else {
        echo "<div class='error'>&#10060; Error creating database: " . ($conn_no_db->error ?? 'unknown error') . "</div>";
    }

    $databaseNoDb->closeConnection();
    echo "</div>"; // Close step container
    echo "<hr>"; // Step separator

    // Connect to the newly created database
    $database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $database->getConnection();

    // --- Step 2/4: Remove existing tables ---
    echo "<div class='install-stage'>"; // Container for step
    echo "<h3>Step 2/4: Dropping existing tables...</h3>";
    echo "<ul>";
    try {
        // Temporarily disable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
        
        // Tables to drop (reverse dependency order)
        $tables_to_drop = [
            'ai_logs',
            'notifications',
            'trade_offers',
            'trade_routes',
            'battle_report_units',
            'battle_reports',
            'attack_units',
            'attacks',
            'research_queue',
            'village_research',
            'research_types',
            'unit_queue',
            'village_units',
            'unit_types',
            'building_queue',
            'village_buildings',
            'building_requirements',
            'building_types',
            'messages',
            'reports',
            'tribe_invitations',
            'tribe_members',
            'villages',
            'tribes',
            'user_achievements',
            'achievements',
            'users',
            'worlds'
        ];
        
        foreach ($tables_to_drop as $table) {
            try {
                $conn->query("DROP TABLE IF EXISTS $table");
                echo "<li>Table <strong>$table</strong> has been dropped (if it existed).</li>";
            } catch (Exception $e) {
                echo "<li><div class='error'>Error dropping table $table: " . $e->getMessage() . "</div></li>";
            }
        }
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        echo "</ul>";
        echo "<div class='success'>&#9989; Completed dropping tables.</div>";
    } catch (Exception $e) {
        echo "</ul>";
        echo "<div class='error'>&#10060; Error while dropping tables: " . $e->getMessage() . "</div>";
    }
    echo "</div>"; // Close step 2 container
    echo "<hr>"; // Step separator

    // Run SQL scripts to create tables
    echo "<div class='install-stage'>"; // Container for step
    echo "<h3>Step 3/4: Creating tables and inserting data...</h3>";
    echo "<ul>";
    $sql_files = [
        'docs/sql/sql_create_users_table.sql',
        'docs/sql/sql_create_tribes_tables.sql',
        'docs/sql/sql_create_worlds_table.sql',
        'docs/sql/sql_create_villages_table.sql',
        'docs/sql/sql_create_buildings_tables.sql',
        'docs/sql/sql_create_building_queue_table.sql',
        'docs/sql/sql_create_unit_types.sql',
        'docs/sql/sql_create_units_table.sql',
        'docs/sql/sql_create_reports_table.sql',
        'docs/sql/sql_create_battle_tables.sql',
        'docs/sql/sql_create_research_tables.sql',
        'docs/sql/sql_create_messages_table.sql',
        'docs/sql/sql_create_trade_offers_table.sql',
        'docs/sql/sql_create_trade_routes_table.sql',
        'docs/sql/sql_create_notifications_table.sql'
    ];
    
    foreach ($sql_files as $sql_file) {
        echo "<li>Executing file <strong>$sql_file</strong>: ";
        if (file_exists($sql_file)) {
            $errorMessages = [];
            $result = executeSqlFile($conn, $sql_file, $errorMessages);
            if ($result) {
                echo "<span class='success'>Completed successfully.</span></li>";
            } else {
                echo "<span class='error clickable'>&#10060; Errors occurred. <span class='show-details'>(Show details)</span></span></li>";
                echo "<div class='sql-errors'>"; // Container for errors
                foreach ($errorMessages as $msg) {
                    echo "<div class='error-detail'>$msg</div>";
                }
                echo "</div>"; // Close container
            }
            
            // After sql_create_buildings_tables.sql, add the missing population_cost column
            if ($sql_file === 'docs/sql/sql_create_buildings_tables.sql') {
                echo "<li>Adding `population_cost` column to `building_types`: ";
                $alter_sql = "ALTER IGNORE TABLE `building_types` ADD COLUMN `population_cost` INT(11) DEFAULT 0 COMMENT 'Population cost per level';";
                if ($conn->query($alter_sql) === TRUE) {
                    echo "<span class='success'>Successful or column already existed.</span></li>";
                } else {
                    echo "<span class='error'>&#10060; Error: " . $conn->error . "</span></li>";
                }
            }

            // Ensure users table has last_activity_at for inactivity tracking
            if ($sql_file === 'docs/sql/sql_create_users_table.sql') {
                echo "<li>Ensuring `last_activity_at` column exists on `users`: ";
                $alter_sql = "ALTER TABLE `users` ADD COLUMN `last_activity_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP";
                if ($conn->query($alter_sql) === TRUE) {
                    echo "<span class='success'>Added.</span></li>";
                } else {
                    // Ignore duplicate column errors gracefully
                    if (strpos(strtolower($conn->error), 'duplicate') !== false || strpos(strtolower($conn->error), 'exists') !== false) {
                        echo "<span class='success'>Already existed.</span></li>";
                    } else {
                        echo "<span class='error'>&#10060; Error: " . $conn->error . "</span></li>";
                    }
                }
            }

            // After unit_types script, verify the unit_types table structure
            if ($sql_file === 'docs/sql/sql_create_unit_types.sql') {
                echo "<li>Checking `unit_types` table structure: ";
                $describe_result = $conn->query("DESCRIBE `unit_types`");
                if ($describe_result) {
                    $fields = [];
                    while ($row = $describe_result->fetch_assoc()) {
                        $fields[] = $row['Field'];
                    }
                    $describe_result->free();
                    // Verify that required columns exist
                    if (in_array('internal_name', $fields) && in_array('wood_cost', $fields) && in_array('clay_cost', $fields) && in_array('iron_cost', $fields)) {
                         echo "<span class='success'>Structure is correct.</span></li>";
                    } else {
                         echo "<span class='error'>Required columns are missing!</span></li>";
                    }
                } else {
                    echo "<span class='error'>Error describing table structure: " . $conn->error . "</span></li>";
                }
            }
        } else {
            echo "<span class='error'>&#10060; File does not exist!</span></li>";
        }
    }
    echo "</ul>";
    echo "<div class='success'>&#9989; Finished creating tables and inserting data.</div>";
    echo "</div>"; // Close step 3 container
    echo "<hr>"; // Step separator

    // --- Step 4/4: Create the default world and administrator ---
    echo "<div class='install-stage'>"; // Container for step
    echo "<h3>Step 4/4: Creating the default world and administrator...</h3>";
    echo "<ul>"; // List for step 4
    echo "<li>Creating default world: ";
    if ($conn->query("INSERT INTO worlds (name) VALUES ('World 1')") === TRUE) {
        echo "<span class='success'>Successful.</span></li>";
    } else {
        echo "<span class='error'>&#10060; Error: " . $conn->error . "</span></li>";
    }

    // No need to clear tables after installation because they were freshly created
    $database->closeConnection();

    // After the tables are installed show the administrator creation form
    echo "</ul>"; // Close list for step 4
    echo "<div class='success'>&#9989; Database installation finished. Please create an administrator account below:</div>";
    echo "</div>"; // Close step 4 container
    echo '<form method="POST" class="form-container">';
    echo '<label for="admin_username">Admin username</label>';
    echo '<input type="text" id="admin_username" name="admin_username" required>';
    echo '<label for="admin_password">Admin password</label>';
    echo '<input type="password" id="admin_password" name="admin_password" required>';
    echo '<label for="admin_password_confirm">Confirm password</label>';
    echo '<input type="password" id="admin_password_confirm" name="admin_password_confirm" required>';
    echo '<button type="submit">Create administrator</button>';
    echo '</form>';
    exit();
}
// POST: administrator account creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_username'])) {
    require_once 'config/config.php';
    require_once 'lib/Database.php';
    require_once 'lib/functions.php';
    require_once 'lib/managers/VillageManager.php';
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $db->getConnection();
    $username = trim($_POST['admin_username']);
    $password = $_POST['admin_password'];
    $confirm  = $_POST['admin_password_confirm'];
    $error = '';
    if (empty($username) || empty($password) || empty($confirm)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!isValidUsername($username)) {
        $error = 'Invalid username.';
    }
    if (!$error) {
        $email = $username . '@localhost';
        $hash = hashPassword($password);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, 1)");
        $stmt->bind_param('sss', $username, $email, $hash);
        if ($stmt->execute()) {
            $admin_id = $stmt->insert_id;
            $stmt->close();
            $vm = new VillageManager($conn);
            $coords = generateRandomCoordinates($conn, 100);
            $vm->createVillage($admin_id, 'Village ' . $username, $coords['x'], $coords['y']);
            echo '<h2>Administrator created successfully!</h2>';
            echo '<p><a href="admin/admin_login.php">Log in to the admin panel</a> | <a href="auth/login.php">Start the game</a>.</p>';
        } else {
            $error = 'Error: ' . $stmt->error;
        }
    }
    if ($error) {
        echo '<p class="error-message">' . htmlspecialchars($error) . '</p>';
    }
    exit();
}
?>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const errorSpans = document.querySelectorAll('.error.clickable');

        errorSpans.forEach(span => {
            span.addEventListener('click', function() {
                // Find the <li> that contains the clicked span
                const listItem = this.closest('li');
                if (!listItem) return;

                // Find the error container inside that list item
                const sqlErrorsDiv = listItem.querySelector('.sql-errors');
                
                if (sqlErrorsDiv) {
                    // Toggle visibility of the error container
                    if (sqlErrorsDiv.style.display === 'none' || sqlErrorsDiv.style.display === '') {
                        sqlErrorsDiv.style.display = 'block';
                        this.querySelector('.show-details').textContent = '(Hide details)';
                    } else {
                        sqlErrorsDiv.style.display = 'none';
                        this.querySelector('.show-details').textContent = '(Show details)';
                    }
                }
            });
        });

        // Hide all SQL error containers at start
        document.querySelectorAll('.sql-errors').forEach(div => {
            div.style.display = 'none';
        });

    });
</script>
</body>
</html>
