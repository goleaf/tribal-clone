<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../lib/Database.php';
require_once '../lib/managers/BuildingConfigManager.php';
require_once '../lib/managers/BuildingManager.php';

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$isSqlite = defined('DB_DRIVER') && DB_DRIVER === 'sqlite';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Building System Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2, h3 { color: #5a3921; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
        pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
        section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Building System Test - Diagnostics</h1>";

$database = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn = $database->getConnection();
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);

echo "<section>
    <h2>1. Struktura tabeli village_buildings</h2>";

// Verify table structure
$result = $isSqlite
    ? $conn->query("PRAGMA table_info('village_buildings')")
    : $conn->query('DESCRIBE village_buildings');
if ($result) {
    echo "<table>";
    echo $isSqlite
        ? "<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>PK</th></tr>"
        : "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $fields = [];
    while ($row = $result->fetch_assoc()) {
        if ($isSqlite) {
            $fields[$row['name']] = true;
            echo "<tr>
                <td>" . $row['name'] . "</td>
                <td>" . $row['type'] . "</td>
                <td>" . $row['notnull'] . "</td>
                <td>" . $row['dflt_value'] . "</td>
                <td>" . $row['pk'] . "</td>
            </tr>";
        } else {
            $fields[$row['Field']] = true;
            echo "<tr>
                <td>" . $row['Field'] . "</td>
                <td>" . $row['Type'] . "</td>
                <td>" . $row['Null'] . "</td>
                <td>" . $row['Key'] . "</td>
                <td>" . $row['Default'] . "</td>
                <td>" . $row['Extra'] . "</td>
            </tr>";
        }
    }
    
    echo "</table>";
    
    echo "<p>Required columns:</p>
    <ul>";
    // Required columns in the current schema
    $required_columns = ['id', 'village_id', 'building_type_id', 'level'];
    foreach ($required_columns as $column) {
        if (isset($fields[$column])) {
            echo "<li class='success'>$column - OK</li>";
        } else {
            echo "<li class='error'>$column - MISSING!</li>";
        }
    }
    echo "</ul>";
    
} else {
    echo "<p class='error'>Unable to fetch village_buildings structure: " . ($conn->error ?? 'no details') . "</p>";
}

echo "</section>";

echo "<section>
    <h2>2. Building types in the database</h2>";

// Check building types
$result = $conn->query('SELECT * FROM building_types ORDER BY id');
if ($result) {
    echo "<table>
        <tr>
            <th>ID</th>
            <th>Internal Name</th>
            <th>Name (PL)</th>
            <th>Max Level</th>
            <th>Build time</th>
            <th>Time factor</th>
            <th>Wood (base)</th>
            <th>Clay (base)</th>
            <th>Iron (base)</th>
            <th>Cost factor</th>
        </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>" . $row['id'] . "</td>
            <td>" . $row['internal_name'] . "</td>
            <td>" . $row['name'] . "</td>
            <td>" . $row['max_level'] . "</td>
            <td>" . $row['base_build_time_initial'] . "</td>
            <td>" . $row['build_time_factor'] . "</td>
            <td>" . $row['cost_wood_initial'] . "</td>
            <td>" . $row['cost_clay_initial'] . "</td>
            <td>" . $row['cost_iron_initial'] . "</td>
            <td>" . $row['cost_factor'] . "</td>
        </tr>";
    }
    
    echo "</table>";
    echo "<p>Found " . $result->num_rows . " building types in the database.</p>";
} else {
    echo "<p class='error'>Error: " . ($conn->error ?? 'no details') . "</p>";
}

echo "</section>";

echo "<section>
    <h2>3. BuildingManager verification</h2>";

// Test BuildingManager functions
echo "<h3>3.1. Calculating upgrade costs</h3>";
$test_buildings = ['main_building', 'sawmill', 'clay_pit', 'iron_mine', 'warehouse'];
$test_levels = [1, 2, 3, 5, 10];

echo "<table>
    <tr>
        <th>Building</th>
        <th>To level</th>
        <th>Wood</th>
        <th>Clay</th>
        <th>Iron</th>
    </tr>";

foreach ($test_buildings as $building) {
    foreach ($test_levels as $level) {
        $cost = $buildingManager->getBuildingUpgradeCost($building, $level);
        if ($cost) {
            echo "<tr>
                <td>" . $buildingManager->getBuildingDisplayName($building) . "</td>
                <td>" . $level . "</td>
                <td>" . $cost['wood'] . "</td>
                <td>" . $cost['clay'] . "</td>
                <td>" . $cost['iron'] . "</td>
            </tr>";
        } else {
            echo "<tr>
                <td>" . $building . "</td>
                <td>" . $level . "</td>
                <td colspan='3' class='error'>Cost calculation error</td>
            </tr>";
        }
    }
}

echo "</table>";

echo "<h3>3.2. Calculating upgrade times (with various town hall levels)</h3>";
$test_main_building_levels = [1, 5, 10, 20];

echo "<table>
    <tr>
        <th>Building</th>
        <th>To level</th>
        <th>Town Hall level</th>
        <th>Build time (seconds)</th>
        <th>Build time (formatted)</th>
    </tr>";

foreach ($test_buildings as $building) {
    foreach ($test_levels as $level) {
        foreach ($test_main_building_levels as $main_level) {
            $time = $buildingManager->getBuildingUpgradeTime($building, $level, $main_level);
            if ($time !== null) {
                echo "<tr>
                    <td>" . $buildingManager->getBuildingDisplayName($building) . "</td>
                    <td>" . $level . "</td>
                    <td>" . $main_level . "</td>
                    <td>" . $time . "</td>
                    <td>" . gmdate('H:i:s', $time) . "</td>
                </tr>";
            } else {
                echo "<tr>
                    <td>" . $building . "</td>
                    <td>" . $level . "</td>
                    <td>" . $main_level . "</td>
                    <td colspan='2' class='error'>Time calculation error</td>
                </tr>";
            }
        }
    }
}

echo "</table>";

echo "<h3>3.3. Calculating resource production</h3>";
$production_buildings = ['sawmill', 'clay_pit', 'iron_mine'];
$production_test_levels = [1, 5, 10, 15, 20, 30];

echo "<table>
    <tr>
        <th>Building</th>
        <th>Level</th>
        <th>Per hour</th>
        <th>Per day</th>
    </tr>";

foreach ($production_buildings as $building) {
    foreach ($production_test_levels as $level) {
        $production = $buildingManager->getHourlyProduction($building, $level);
        echo "<tr>
            <td>" . $buildingManager->getBuildingDisplayName($building) . "</td>
            <td>" . $level . "</td>
            <td>" . $production . "</td>
            <td>" . ($production * 24) . "</td>
        </tr>";
    }
}

echo "</table>";

echo "</section>";

echo "<section>
    <h2>4. Debug information</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
if ($isSqlite) {
    echo "<p>Database engine: SQLite</p>";
} else {
    echo "<p>MySQL Client Info: " . $conn->client_info . "</p>";
    echo "<p>MySQL Server Info: " . $conn->server_info . "</p>";
}
echo "</section>";

echo "<p><a href='../game/game.php'>Back to the game</a></p>";
echo "</body></html>";

$database->closeConnection();
?> 
