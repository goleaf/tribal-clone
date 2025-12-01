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

echo "<p>village_buildings schema is up-to-date; no missing columns to add.</p>";

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
