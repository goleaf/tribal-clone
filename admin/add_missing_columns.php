<?php
require_once '../config/config.php';
require_once '../lib/Database.php';
require_once '../lib/functions.php';

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$isSqlite = defined('DB_DRIVER') && DB_DRIVER === 'sqlite';

echo "<h1>Adding missing columns</h1>";

if ($isSqlite) {
    echo "<p>SQLite mode: schema is managed via docs/sql/sqlite_schema.sql. No changes applied.</p>";
    exit;
}

$db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $db->getConnection();

// Add upgrade_level_to column
$checkColumn1 = $conn->query("SHOW COLUMNS FROM village_buildings LIKE 'upgrade_level_to'");
if ($checkColumn1 && $checkColumn1->num_rows == 0) {
    if ($conn->query("ALTER TABLE village_buildings ADD COLUMN upgrade_level_to INT DEFAULT NULL")) {
        echo "<p style='color: green;'>Column upgrade_level_to added successfully.</p>";
    } else {
        echo "<p style='color: red;'>Error while adding upgrade_level_to: " . ($conn->error ?? 'unknown error') . "</p>";
    }
} else {
    echo "<p>Column upgrade_level_to already exists.</p>";
}

// Add upgrade_ends_at column
$checkColumn2 = $conn->query("SHOW COLUMNS FROM village_buildings LIKE 'upgrade_ends_at'");
if ($checkColumn2 && $checkColumn2->num_rows == 0) {
    if ($conn->query("ALTER TABLE village_buildings ADD COLUMN upgrade_ends_at DATETIME DEFAULT NULL")) {
        echo "<p style='color: green;'>Column upgrade_ends_at added successfully.</p>";
    } else {
        echo "<p style='color: red;'>Error while adding upgrade_ends_at: " . ($conn->error ?? 'unknown error') . "</p>";
    }
} else {
    echo "<p>Column upgrade_ends_at already exists.</p>";
}

// Show table structure after changes
echo "<h2>village_buildings table structure after changes</h2>";
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
    echo "Error: " . ($conn->error ?? 'unknown error');
}

echo "<p><a href='../game/game.php'>Return to the main game page</a></p>";

$db->closeConnection();
?> 
