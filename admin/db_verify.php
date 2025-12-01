<?php
require_once '../config/config.php';
require_once '../lib/Database.php';
require_once '../lib/functions.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$isSqlite = defined('DB_DRIVER') && DB_DRIVER === 'sqlite';

echo "<h1>Database table verification</h1>";

$database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $database->getConnection();

// List of tables that should exist
$expected_tables = [
    'users',
    'villages',
    'building_types',
    'village_buildings',
    'building_queue',
    'unit_types',
    'village_units',
    'unit_queue',
    'research_types',
    'village_research',
    'research_queue'
];

// Check whether tables exist
echo "<h2>Table existence check:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Table</th><th>Status</th><th>Record count</th></tr>";

foreach ($expected_tables as $table) {
    $exists = dbTableExists($conn, $table);
    
    if ($exists) {
        // Table exists - count rows
        $count_query = $isSqlite
            ? "SELECT COUNT(*) as count FROM \"$table\""
            : "SELECT COUNT(*) as count FROM `$table`";
        $count_result = $conn->query($count_query);
        $count = $count_result && method_exists($count_result, 'fetch_assoc')
            ? ($count_result->fetch_assoc()['count'] ?? 0)
            : 0;
        
        echo "<tr>";
        echo "<td>$table</td>";
        echo "<td style='color:green;'>Exists</td>";
        echo "<td>$count</td>";
        echo "</tr>";
    } else {
        echo "<tr>";
        echo "<td>$table</td>";
        echo "<td style='color:red;'>Missing!</td>";
        echo "<td>-</td>";
        echo "</tr>";
    }
}

echo "</table>";

// Check unit_types table structure
echo "<h2>unit_types table structure:</h2>";
$unit_types_columns = $isSqlite
    ? $conn->query("PRAGMA table_info('unit_types')")
    : $conn->query("SHOW COLUMNS FROM unit_types");

if ($unit_types_columns) {
    echo "<table border='1' cellpadding='5'>";
    echo $isSqlite
        ? "<tr><th>Column name</th><th>Type</th><th>NULL</th><th>Default</th><th>PK</th></tr>"
        : "<tr><th>Column name</th><th>Type</th><th>NULL</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($column = $unit_types_columns->fetch_assoc()) {
        if ($isSqlite) {
            echo "<tr>";
            echo "<td>" . $column['name'] . "</td>";
            echo "<td>" . $column['type'] . "</td>";
            echo "<td>" . $column['notnull'] . "</td>";
            echo "<td>" . $column['dflt_value'] . "</td>";
            echo "<td>" . $column['pk'] . "</td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
} else {
    echo "<p style='color:red;'>Could not fetch unit_types structure: " . ($conn->error ?? 'no details') . "</p>";
}

// Check research_queue table structure
echo "<h2>research_queue table structure:</h2>";
$research_queue_columns = $isSqlite
    ? $conn->query("PRAGMA table_info('research_queue')")
    : $conn->query("SHOW COLUMNS FROM research_queue");

if ($research_queue_columns) {
    echo "<table border='1' cellpadding='5'>";
    echo $isSqlite
        ? "<tr><th>Column name</th><th>Type</th><th>NULL</th><th>Default</th><th>PK</th></tr>"
        : "<tr><th>Column name</th><th>Type</th><th>NULL</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($column = $research_queue_columns->fetch_assoc()) {
        if ($isSqlite) {
            echo "<tr>";
            echo "<td>" . $column['name'] . "</td>";
            echo "<td>" . $column['type'] . "</td>";
            echo "<td>" . $column['notnull'] . "</td>";
            echo "<td>" . $column['dflt_value'] . "</td>";
            echo "<td>" . $column['pk'] . "</td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
} else {
    echo "<p style='color:red;'>Could not fetch research_queue structure: " . ($conn->error ?? 'no details') . "</p>";
}

$database->closeConnection();

echo "<h2>Verification finished.</h2>";
echo "<p><a href='../install.php'>Back to installer</a></p>";
?>
