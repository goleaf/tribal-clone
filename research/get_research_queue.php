<?php
require '../init.php';
require_once '../lib/managers/ResearchManager.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error">Access denied</div>';
    exit;
}
$user_id = $_SESSION['user_id'];

// Fetch the player\'s village
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
$queue = $researchManager->getResearchQueue($village_id);

// Research icon mapping (placeholder: using unit icons for now)
$research_icons = [
    'spear_research' => 'unit_spear.png', // Example: spearman research
    'sword_research' => 'unit_sword.png', // Example: swordsman research
    // Add mappings for other research items using their internal_name
];

if (empty($queue)) {
    echo '<div class="queue-empty">No research is queued.</div>';
    exit;
}
echo '<div class="research-queue-list">'; // Separate class name from building/recruitment queues

foreach ($queue as $item) {
    $end_time = strtotime($item['ends_at']);
    $start_time = strtotime($item['starts_at']); // Assuming starts_at is present in queue data
    $total_time = $end_time - $start_time;
    $remaining_time = $end_time - time();
    $progress_percent = ($total_time > 0) ? 100 - (($remaining_time / $total_time) * 100) : 0;
     // Use the research internal_name to fetch the icon
    $icon_url = isset($research_icons[$item['research_internal_name']]) ? '../img/research/' . $research_icons[$item['research_internal_name']] : '';

    echo '<div class="queue-item research" data-starts-at="' . $start_time . '" data-duration="' . $total_time . '">';
    echo '  <div class="item-header">';
    echo '    <div class="item-title">';
    if ($icon_url) {
         // Research icon
        echo '      <img src="' . $icon_url . '" class="research-icon" alt="' . htmlspecialchars($item['research_name']) . '">';
    }
    echo '      <span class="research-name">' . htmlspecialchars($item['research_name']) . '</span>';
    echo '      <span class="research-level">(level ' . (int)$item['level_after'] . ')</span>';
    echo '    </div>';
    echo '    <div class="item-actions">';
    // Cancel button with data-id for AJAX handling
    echo '      <button class="cancel-button research" data-id="' . $item['id'] . '">Cancel</button>';
    echo '    </div>';
    echo '  </div>';
    echo '  <div class="item-progress">';
    echo '    <div class="progress-bar">';
    // Using the progress-fill class
    echo '      <div class="progress-fill" style="width: ' . max(0, min(100, $progress_percent)) . '%" data-ends-at="' . $end_time . '"></div>';
    echo '    </div>';
    echo '    <div class="progress-time">';
    // Using the time-remaining class
    echo '      <span class="time-remaining" data-remaining="' . $remaining_time . '">' . formatTime($remaining_time) . '</span>'; // Assuming formatTime JS helper is globally available
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
}

echo '</div>'; 
