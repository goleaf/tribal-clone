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

// Get world ID for effective stats
$worldId = defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1;

echo '<form method="post" id="recruitment-form">';
echo '<input type="hidden" name="building_type" value="' . htmlspecialchars($building_type) . '">'; // Include building type in the form

foreach ($availableUnits as $unit_data) {
    $icon = isset($unit_data['icon']) ? $unit_data['icon'] : '';
    $unit_internal_name = $unit_data['internal_name'];
    $unit_name = $unit_data['name'];
    $unit_id = $unit_data['id'];
    
    // Get effective stats with world multipliers applied
    $effectiveStats = $unitManager->getEffectiveUnitStats($unit_id, $worldId);
    
    $cost_wood = $effectiveStats['cost_wood'] ?? $unit_data['cost_wood'];
    $cost_clay = $effectiveStats['cost_clay'] ?? $unit_data['cost_clay'];
    $cost_iron = $effectiveStats['cost_iron'] ?? $unit_data['cost_iron'];
    $training_time = $effectiveStats['training_time_effective'] ?? $unitManager->calculateRecruitmentTime($unit_id, $building_level);
    
    // Get unit stats
    $attack = $effectiveStats['attack'] ?? $unit_data['attack'] ?? 0;
    $def_inf = $effectiveStats['defense_infantry'] ?? $unit_data['defense_infantry'] ?? 0;
    $def_cav = $effectiveStats['defense_cavalry'] ?? $unit_data['defense_cavalry'] ?? 0;
    $def_rng = $effectiveStats['defense_ranged'] ?? $unit_data['defense_ranged'] ?? 0;
    $speed = $effectiveStats['speed_min_per_field'] ?? $unit_data['speed'] ?? $unit_data['speed_min_per_field'] ?? 0;
    $carry = $effectiveStats['carry_capacity'] ?? $unit_data['carry'] ?? $unit_data['carry_capacity'] ?? 0;
    $population = $effectiveStats['population'] ?? $unit_data['population'] ?? 0;
    $category = $effectiveStats['category'] ?? $unitManager->getUnitCategory($unit_id);
    
    // Get RPS bonuses and special abilities
    $rps_bonuses = [];
    if (!empty($unit_data['rps_bonuses'])) {
        $rps_data = is_string($unit_data['rps_bonuses']) ? json_decode($unit_data['rps_bonuses'], true) : $unit_data['rps_bonuses'];
        if (is_array($rps_data)) {
            $rps_bonuses = $rps_data;
        }
    }
    
    $special_abilities = [];
    if (!empty($unit_data['special_abilities'])) {
        $abilities_data = is_string($unit_data['special_abilities']) ? json_decode($unit_data['special_abilities'], true) : $unit_data['special_abilities'];
        if (is_array($abilities_data)) {
            $special_abilities = $abilities_data;
        }
    }
    
    // Get prerequisites
    $required_building_level = $unit_data['required_building_level'] ?? 1;
    $required_tech = $unit_data['required_tech'] ?? null;
    $required_tech_level = $unit_data['required_tech_level'] ?? 0;
    
    echo '<div class="unit-info-card" style="border: 1px solid #d9c4a7; border-radius: 8px; padding: 16px; margin-bottom: 16px; background: #fff;">';
    
    // Unit header with icon and name
    echo '<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">';
    if ($icon) {
        echo '<img src="../img/unit/' . htmlspecialchars($icon) . '" class="unit-icon" alt="' . htmlspecialchars($unit_name) . '" style="width: 48px; height: 48px;">';
    }
    echo '<div style="flex: 1;">';
    echo '<h3 style="margin: 0; font-size: 18px; color: #8d5c2c;">' . htmlspecialchars($unit_name) . '</h3>';
    echo '<span style="font-size: 12px; color: #666; text-transform: capitalize;">' . htmlspecialchars($category) . '</span>';
    echo '</div>';
    echo '<input type="number" name="units[' . $unit_id . ']" data-unit-internal-name="' . htmlspecialchars($unit_internal_name) . '" min="0" value="0" style="width: 80px; padding: 8px; border: 1px solid #d9c4a7; border-radius: 4px;">';
    echo '</div>';
    
    // Combat stats
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; margin-bottom: 12px;">';
    echo '<div style="background: #f9f6f1; padding: 8px; border-radius: 4px;">';
    echo '<div style="font-size: 11px; color: #8d5c2c; text-transform: uppercase;">Attack</div>';
    echo '<div style="font-size: 16px; font-weight: 600;">' . $attack . '</div>';
    echo '</div>';
    echo '<div style="background: #f9f6f1; padding: 8px; border-radius: 4px;">';
    echo '<div style="font-size: 11px; color: #8d5c2c; text-transform: uppercase;">Def vs Inf</div>';
    echo '<div style="font-size: 16px; font-weight: 600;">' . $def_inf . '</div>';
    echo '</div>';
    echo '<div style="background: #f9f6f1; padding: 8px; border-radius: 4px;">';
    echo '<div style="font-size: 11px; color: #8d5c2c; text-transform: uppercase;">Def vs Cav</div>';
    echo '<div style="font-size: 16px; font-weight: 600;">' . $def_cav . '</div>';
    echo '</div>';
    echo '<div style="background: #f9f6f1; padding: 8px; border-radius: 4px;">';
    echo '<div style="font-size: 11px; color: #8d5c2c; text-transform: uppercase;">Def vs Rng</div>';
    echo '<div style="font-size: 16px; font-weight: 600;">' . $def_rng . '</div>';
    echo '</div>';
    echo '</div>';
    
    // Unit properties
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 8px; margin-bottom: 12px; font-size: 13px;">';
    echo '<div><strong>Speed:</strong> ' . $speed . ' min/field</div>';
    echo '<div><strong>Carry:</strong> ' . $carry . '</div>';
    echo '<div><strong>Population:</strong> ' . $population . '</div>';
    echo '<div><strong>Training:</strong> ' . gmdate('H:i:s', $training_time) . '</div>';
    echo '</div>';
    
    // Resource costs
    echo '<div style="margin-bottom: 12px;">';
    echo '<strong>Cost:</strong> ';
    echo '<img src="../img/wood.png" title="Wood" alt="Wood" style="width: 16px; height: 16px; vertical-align: middle;">' . $cost_wood . ' ';
    echo '<img src="../img/stone.png" title="Clay" alt="Clay" style="width: 16px; height: 16px; vertical-align: middle;">' . $cost_clay . ' ';
    echo '<img src="../img/iron.png" title="Iron" alt="Iron" style="width: 16px; height: 16px; vertical-align: middle;">' . $cost_iron;
    echo '</div>';
    
    // Prerequisites
    echo '<div style="margin-bottom: 12px; font-size: 12px; color: #666;">';
    echo '<strong>Prerequisites:</strong> ' . htmlspecialchars($building_type) . ' level ' . $required_building_level;
    if ($required_tech && $required_tech_level > 0) {
        echo ', ' . htmlspecialchars($required_tech) . ' level ' . $required_tech_level;
    }
    echo '</div>';
    
    // RPS matchups
    if (!empty($rps_bonuses)) {
        echo '<div style="margin-bottom: 12px;">';
        echo '<strong style="color: #2d7a2d;">Strengths:</strong> ';
        $strengths = [];
        foreach ($rps_bonuses as $key => $value) {
            if ($value > 1.0) {
                $bonus_pct = round(($value - 1) * 100);
                $strengths[] = str_replace('vs_', '', $key) . ' (+' . $bonus_pct . '%)';
            }
        }
        echo !empty($strengths) ? implode(', ', $strengths) : 'None';
        echo '</div>';
    }
    
    // Special abilities
    if (!empty($special_abilities)) {
        echo '<div style="margin-bottom: 8px;">';
        echo '<strong style="color: #7a5c2d;">Special Abilities:</strong> ';
        $ability_descriptions = [];
        foreach ($special_abilities as $ability) {
            if (is_string($ability)) {
                // Format ability name
                $formatted = str_replace('_', ' ', $ability);
                $formatted = ucwords($formatted);
                $ability_descriptions[] = $formatted;
            }
        }
        echo !empty($ability_descriptions) ? implode(', ', $ability_descriptions) : 'None';
        echo '</div>';
    }
    
    echo '</div>'; // Close unit-info-card
}

echo '<button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; background: #8d5c2c; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;">Recruit selected units</button>';
echo '</form>';
?>
