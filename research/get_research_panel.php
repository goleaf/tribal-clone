<?php
require '../init.php';
require_once '../lib/managers/ResearchManager.php';
require_once '../lib/managers/BuildingManager.php'; // Needed to check requirements and calculate time
require_once '../lib/managers/ResourceManager.php'; // Needed to show current resources

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error">Access denied. Please log in again.</div>';
    exit;
}
$user_id = $_SESSION['user_id'];

// Fetch the player\'s village
$stmt = $conn->prepare("SELECT id, wood, clay, iron FROM villages WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$village = $res->fetch_assoc();
$stmt->close();
if (!$village) {
    echo '<div class="error">No village found.</div>';
    exit;
}
$village_id = $village['id'];
$current_wood = $village['wood'];
$current_clay = $village['clay'];
$current_iron = $village['iron'];

$researchManager = new ResearchManager($conn);
$buildingManager = new BuildingManager($conn);

// Check the Academy level in the village using a direct query
$academy_level = 0;
$stmt_academy = $conn->prepare("
    SELECT vb.level 
    FROM village_buildings vb
    JOIN building_types bt ON vb.building_type_id = bt.id
    WHERE vb.village_id = ? AND bt.internal_name = 'academy' LIMIT 1
");
$stmt_academy->bind_param("i", $village_id);
$stmt_academy->execute();
$academy_result = $stmt_academy->get_result()->fetch_assoc();
$stmt_academy->close();

if ($academy_result) {
    $academy_level = (int)$academy_result['level'];
}

if ($academy_level === 0) {
    echo '<div class="info-message">You need to build an Academy to start research.</div>';
    exit;
}

// Fetch available research items for the Academy
$availableResearches = $researchManager->getResearchTypesForBuilding('academy');

// Fetch current research levels for the village
$village_research_levels = $researchManager->getVillageResearchLevels($village_id);

// Fetch current research queue
$research_queue = $researchManager->getResearchQueue($village_id);
$current_research_in_queue = [];
foreach ($research_queue as $item) {
    $current_research_in_queue[$item['research_type_id']] = true;
}

$researches_to_display = [];

foreach ($availableResearches as $research) {
     $research_id = $research['id'];
     $internal_name = $research['internal_name'];
     $name = $research['name'];
     $max_level = $research['max_level'];
     $required_building_level = $research['required_building_level'];
     
     $current_level = $village_research_levels[$internal_name] ?? 0;
     $next_level = $current_level + 1;

    // Check whether the research can continue (not at max level and building requirements met)
    if ($current_level < $max_level && $academy_level >= $required_building_level) {
        // Check additional requirements (e.g., other research)
        $requirements_met = true; // Default to met
        if ($research['prerequisite_research_id']) {
            $prereq_id = $research['prerequisite_research_id'];
            $prereq_level = $research['prerequisite_research_level'];
            // Get information about the required research
            $prereq_info = $researchManager->getResearchTypeById($prereq_id); // Assumes getResearchTypeById exists and works
            $prereq_internal_name = $prereq_info['internal_name'] ?? null;
            $prereq_current_level = $village_research_levels[$prereq_internal_name] ?? 0;
            
            if ($prereq_current_level < $prereq_level) {
                $requirements_met = false;
            }
        }

        // Ensure the research is not already queued
        $is_in_queue = isset($current_research_in_queue[$research_id]);

        if ($requirements_met && !$is_in_queue) {
             // Calculate cost and time for the next level
             $cost = $researchManager->getResearchCost($research_id, $next_level);
             $time_seconds = $researchManager->calculateResearchTime($research_id, $next_level, $academy_level); // Use Academy level to calculate time

            $researches_to_display[] = [
                'id' => $research_id,
                'internal_name' => $internal_name,
                'name' => $name,
                'current_level' => $current_level,
                'next_level' => $next_level,
                'wood_cost' => $cost['wood'],
                'clay_cost' => $cost['clay'],
                'iron_cost' => $cost['iron'],
                'duration_seconds' => $time_seconds,
                'icon' => $research['icon'] ?? null, // Assuming an icon is provided in the research data
            ];
        }
    }
}

if (empty($researches_to_display)) {
    echo '<div class="info-message">No research is available to start right now.</div>';
    exit;
}

echo '<div class="available-research-list">';

foreach ($researches_to_display as $research) {
    $icon_url = isset($research['icon']) && !empty($research['icon']) ? '../img/research/' . $research['icon'] : ''; // Use icon from research data
    $can_afford = (
        $current_wood >= $research['wood_cost'] &&
        $current_clay >= $research['clay_cost'] &&
        $current_iron >= $research['iron_cost']
    );

    echo '<div class="research-available-item ' . ($can_afford ? 'can-afford' : 'cannot-afford') . '" data-research-id="' . $research['id'] . '">';
    echo '  <div class="item-header">';
    echo '    <div class="item-title">';
    if ($icon_url) {
        echo '<img src="' . htmlspecialchars($icon_url) . '" class="research-icon" alt="' . htmlspecialchars($research['name']) . '">';
    }
    echo '      <span class="research-name">' . htmlspecialchars($research['name']) . ' (level ' . $research['next_level'] . ')</span>';
    echo '    </div>';
    echo '    <div class="item-actions">';
    if ($can_afford) {
        // Start research button
        echo '      <button class="btn btn-primary btn-small start-research-button" data-research-id="' . $research['id'] . '">Research</button>';
    } else {
        echo '      <button class="btn btn-primary btn-small" disabled>Research</button>';
    }
    echo '    </div>';
    echo '  </div>';
    echo '  <div class="research-details">';
    echo '    <div class="costs-info">';
    echo '      Cost: ';
    // Use formatNumber from functions.php
    echo '      <span class="cost-item ' . ($current_wood >= $research['wood_cost'] ? 'enough' : 'not-enough') . '"><img src="../img/ds_graphic/wood.png" alt="Wood"> ' . formatNumber($research['wood_cost']) . '</span> ';
    echo '      <span class="cost-item ' . ($current_clay >= $research['clay_cost'] ? 'enough' : 'not-enough') . '"><img src="../img/ds_graphic/stone.png" alt="Clay"> ' . formatNumber($research['clay_cost']) . '</span> ';
    echo '      <span class="cost-item ' . ($current_iron >= $research['iron_cost'] ? 'enough' : 'not-enough') . '"><img src="../img/ds_graphic/iron.png" alt="Iron"> ' . formatNumber($research['iron_cost']) . '</span>';
    echo '    </div>';
     // Research duration - assume the time is in seconds and we can use formatTime from functions.php
    echo '    <div class="time-info">Research time: <span class="research-time" data-time-seconds="' . $research['duration_seconds'] . '">' . formatTime($research['duration_seconds']) . '</span></div>';
    echo '  </div>';
    echo '</div>';
}

echo '</div>'; 
