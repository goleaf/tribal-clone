<?php
require_once '../config/config.php';
require_once '../lib/Database.php';
require_once '../lib/functions.php';

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$isSqlite = defined('DB_DRIVER') && DB_DRIVER === 'sqlite';

$db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $db->getConnection();

echo "<h2>village_buildings table structure</h2>";

if ($isSqlite) {
    $result = $conn->query("PRAGMA table_info('village_buildings')");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>name</th><th>type</th><th>notnull</th><th>default</th><th>pk</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . $row['type'] . "</td>";
            echo "<td>" . $row['notnull'] . "</td>";
            echo "<td>" . $row['dflt_value'] . "</td>";
            echo "<td>" . $row['pk'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Error: " . ($conn->error ?? 'unknown') . "</p>";
    }
    echo "<p>Schema changes for SQLite are managed via docs/sql/sqlite_schema.sql.</p>";
} else {
    $result = $conn->query('DESCRIBE village_buildings');

    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "Error: " . $conn->error;
    }

    echo "<h2>SQL script to add missing columns</h2>";
    echo "<pre>";
    echo "village_buildings schema is up-to-date; no upgrade_level_to / upgrade_ends_at columns required.";
    echo "</pre>";

    echo "<h2>Execute SQL script</h2>";
    echo "<form method='post'>";
    echo "<input type='submit' name='execute_sql' value='Add missing columns'>";
    echo "</form>";

    // Execute the SQL if the form is submitted
    if (isset($_POST['execute_sql'])) {
        // Add upgrade_level_to column if it doesn't exist
        $checkColumn = $conn->query("SHOW COLUMNS FROM village_buildings LIKE 'upgrade_level_to'");
        if ($checkColumn->num_rows == 0) {
            if ($conn->query("ALTER TABLE village_buildings ADD COLUMN upgrade_level_to INT DEFAULT NULL")) {
                echo "<p style='color: green;'>upgrade_level_to added successfully.</p>";
            } else {
                echo "<p style='color: red;'>Error adding upgrade_level_to: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>upgrade_level_to already exists.</p>";
        }
        
        // Add upgrade_ends_at column if it doesn't exist
        $checkColumn = $conn->query("SHOW COLUMNS FROM village_buildings LIKE 'upgrade_ends_at'");
        if ($checkColumn->num_rows == 0) {
            if ($conn->query("ALTER TABLE village_buildings ADD COLUMN upgrade_ends_at DATETIME DEFAULT NULL")) {
                echo "<p style='color: green;'>upgrade_ends_at added successfully.</p>";
            } else {
                echo "<p style='color: red;'>Error adding upgrade_ends_at: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>upgrade_ends_at already exists.</p>";
        }
    }

    // Show table structure again after changes
    if (isset($_POST['execute_sql'])) {
        echo "<h2>village_buildings structure after changes</h2>";
        $result = $conn->query('DESCRIBE village_buildings');
        
        if ($result) {
            echo "<table border='1'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['Field'] . "</td>";
                echo "<td>" . $row['Type'] . "</td>";
                echo "<td>" . $row['Null'] . "</td>";
                echo "<td>" . $row['Key'] . "</td>";
                echo "<td>" . $row['Default'] . "</td>";
                echo "<td>" . $row['Extra'] . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
    }
}

$db->closeConnection();
?>
