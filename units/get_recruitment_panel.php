<?php
require '../init.php';
require_once '../lib/managers/UnitManager.php';
require_once '../lib/managers/BuildingManager.php'; // Need BuildingManager to get building level

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error">Access denied</div>';
    exit;
}
$user_id = $_SESSION['user_id'];

// Fetch the player's village
$stmt = $conn->prepare("SELECT id FROM villages WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$village = $res->fetch_assoc();
$stmt->close();
if (!$village) {
    echo '<div class="error">No village found</div>';
    exit;
}
$village_id = $village['id'];

// Get building type from the request
$building_type = $_GET['building_type'] ?? '';

if (empty($building_type)) {
    echo '<div class="error">Building type not provided.</div>';
    exit;
}

// Fetch building level in the village
$buildingManager = new BuildingManager($conn);
$building_level = 0;
$stmt_level = $conn->prepare("
    SELECT vb.level 
    FROM village_buildings vb
    JOIN building_types bt ON vb.building_type_id = bt.id
    WHERE vb.village_id = ? AND bt.internal_name = ? LIMIT 1
");
$stmt_level->bind_param("is", $village_id, $building_type);
$stmt_level->execute();
$level_result = $stmt_level->get_result()->fetch_assoc();
$stmt_level->close();

if ($level_result) {
    $building_level = (int)$level_result['level'];
}

// If the building does not exist or is level 0, recruitment is not available
if ($building_level == 0) {
     echo '<div class="info">This building does not exist in your village or its level is 0. Unit recruitment is unavailable.</div>';
     exit;
}

$unitManager = new UnitManager($conn);
$availableUnits = $unitManager->getAvailableUnitsByBuilding($building_type, $building_level);

if (empty($availableUnits)) {
    echo '<div>No units are available for recruitment in this building at the current level.</div>';
    exit;
}
echo '<form method="post" id="recruitment-form">';
echo '<input type="hidden" name="building_type" value="' . htmlspecialchars($building_type) . '">'; // Include building type in the form
foreach ($availableUnits as $unit_data) {
    $icon = isset($unit_data['icon']) ? $unit_data['icon'] : '';
    $unit_internal_name = $unit_data['internal_name'];
    $unit_name = $unit_data['name'];
    $unit_id = $unit_data['id'];
    $cost_wood = $unit_data['cost_wood'];
    $cost_clay = $unit_data['cost_clay'];
    $cost_iron = $unit_data['cost_iron'];

    echo '<div class="unit-info">';
    if ($icon) {
        echo '<img src="../img/unit/' . htmlspecialchars($icon) . '" class="unit-icon" alt="' . htmlspecialchars($unit_name) . '">';
    }
    echo '<label>' . htmlspecialchars($unit_name) . ':</label> ';
    // Use unit_id for the input name and internal_name for the data attribute
    echo '<input type="number" name="units[' . $unit_id . ']" data-unit-internal-name="' . htmlspecialchars($unit_internal_name) . '" min="0" value="0">';
    // Updated cost display
    echo ' <span class="resource-cost">Cost: <img src="../img/wood.png" title="Wood" alt="Wood" class="resource-icon-small">' . $cost_wood . ' <img src="../img/stone.png" title="Clay" alt="Clay" class="resource-icon-small">' . $cost_clay . ' <img src="../img/iron.png" title="Iron" alt="Iron" class="resource-icon-small">' . $cost_iron . '</span>';
    echo '</div>';
}
echo '<button type="submit" class="btn btn-primary">Recruit selected units</button>';
echo '</form>';
?>
