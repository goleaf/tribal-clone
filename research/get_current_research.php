<?php
require '../init.php';
require_once '../lib/managers/ResearchManager.php';

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

$researchManager = new ResearchManager($conn);
$researches = $researchManager->getVillageResearchLevels($village_id);

if (empty(array_filter($researches))) {
    echo '<div class="research-empty">No research completed yet (level > 0).</div>';
    exit;
}
echo '<div class="current-research-list">';

// Research icon mapping (placeholder until real icons are provided)
$research_icons = [
    'axe' => 'unit_axe.png', // Example: axe fighter research icon
    'spear' => 'unit_spear.png', // Example: spearman research icon
    // Add more mappings for other research types
];

// Pull research names from ResearchManager (if not returned by getVillageResearchLevels)
// Alternatively, adjust getVillageResearchLevels to return full data
$all_research_types = $researchManager->getAllResearchTypes(); // Assumes this method exists
$research_names = [];
foreach ($all_research_types as $type) {
    $research_names[$type['internal_name']] = $type['name'];
    // Pull icon if present in the research_types table
    $research_icons[$type['internal_name']] = $type['icon'] ?? ''; // Assumes an 'icon' column
}

// Filter only research entries at level > 0
$active_researches = array_filter($researches, function($level) { return $level > 0; });

if (empty($active_researches)) {
     echo '<div class="research-empty">No research completed yet (level > 0).</div>';
     exit;
}

foreach ($active_researches as $internal_name => $level) {
    // Use internal_name for mapping and point icons to img/research/
    $icon_url = isset($research_icons[$internal_name]) && !empty($research_icons[$internal_name]) ? '../img/research/' . $research_icons[$internal_name] : '';
    $research_name = $research_names[$internal_name] ?? $internal_name;

    echo '<div class="research-item">';
    if ($icon_url) {
        echo '<img src="' . htmlspecialchars($icon_url) . '" class="research-icon" alt="' . htmlspecialchars($research_name) . '">';
    }
    echo '<span class="research-name">' . htmlspecialchars($research_name) . '</span>';
    echo '<span class="research-level">level <b>' . (int)$level . '</b></span>';
    echo '</div>';
}
echo '</div>';
