<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Unit tables installation</h1>";

// Load database configuration
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Database.php';

try {
    // Connect to the database (SQLite/MySQL through the wrapper)
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = $db->getConnection();
    
    echo "<p>Connected to the database.</p>";
    
    // Load and execute the SQL script
    $sql = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'sql_create_units_table.sql');
    
    if (!$sql) {
        die("Could not read the SQL file.");
    }
    
    echo "<p>SQL file loaded.</p>";
    
    // Execute individual queries
    $queries = explode(';', $sql);
    $success_count = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        if ($conn->query($query)) {
            $success_count++;
            echo "<p style='color:green'>Query executed: " . htmlspecialchars(substr($query, 0, 80)) . "...</p>";
        } else {
            echo "<p style='color:red'>Query error: " . htmlspecialchars(substr($query, 0, 80)) . "...</p>";
            echo "<p>Error: " . $conn->error . "</p>";
        }
    }
    
    echo "<h2>Installation finished</h2>";
    echo "<p>Successfully executed $success_count queries.</p>";
    echo "<p><a href='../game/game.php'>Return to the game</a></p>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color:red'>An error occurred: " . $e->getMessage() . "</p>";
}
?>
