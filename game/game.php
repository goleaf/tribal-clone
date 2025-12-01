<?php
require '../init.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php'; // Updated path
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php'; // Include BuildingConfigManager
require_once __DIR__ . '/../lib/managers/VillageManager.php'; // Updated path
require_once __DIR__ . '/../lib/managers/ResourceManager.php'; // Updated path
// Require other managers if they are initialized and used here directly (e.g., UnitManager, BattleManager, ResearchManager)
require_once __DIR__ . '/../lib/managers/UnitManager.php'; // Updated path
require_once __DIR__ . '/../lib/managers/BattleManager.php'; // Updated path
require_once __DIR__ . '/../lib/managers/ResearchManager.php'; // Updated path

require_once __DIR__ . '/../lib/functions.php'; // Helper functions


// Instantiate managers
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$villageManager = new VillageManager($conn);
$resourceManager = new ResourceManager($conn, $buildingManager);
$unitManager = new UnitManager($conn); // Initialize UnitManager
$battleManager = new BattleManager($conn, $villageManager, $buildingManager); // Initialize BattleManager
$researchManager = new ResearchManager($conn); // Initialize ResearchManager


if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = ''; // For user-facing messages

// --- FETCH VILLAGE DATA ---
$village = $villageManager->getFirstVillage($user_id);

if (!$village) {
    // Should not happen after create_village.php, but keep as a guard
    header("Location: /player/create_village.php");
    exit();
}
$village_id = $village['id'];

// Main building level (used for time reductions)
$main_building_level = $buildingManager->getBuildingLevel($village_id, 'main_building');

// --- PROCESS COMPLETED TASKS ---
$messages = $villageManager->processCompletedTasksForVillage($village_id);
$message = implode('', $messages);

// Refresh village after updates
$village = $villageManager->getVillageInfo($village_id);

// --- PROCESS COMPLETED ATTACKS ---
$attackMessages = $battleManager->processCompletedAttacks($user_id);
if (!empty($attackMessages)) {
    $message .= implode('', $attackMessages);
}

// --- BUILDING DATA FOR VIEW ---
$buildings_data = $buildingManager->getVillageBuildingsViewData($village_id, $main_building_level);

// --- PAGE META ---
$pageTitle = htmlspecialchars($village['name']) . ' - Village Overview';

// Determine time of day and set background image path
date_default_timezone_set('Europe/Warsaw'); // Set appropriate timezone
$current_hour = (int)date('H'); // Get current hour (0-23)

$day_start_hour = 8;
$night_start_hour = 22;

// Assuming the background image files are named 'background.jpg' in their respective folders
$day_background_path = '/img/ds_graphic/visual/back_none.jpg';
$night_background_path = '/img/ds_graphic/visual_night/back_none.jpg';

if ($current_hour >= $day_start_hour && $current_hour < $night_start_hour) {
    // Day time (8:00 to 21:59)
    $village_background_image = $day_background_path;
} else {
    // Night time (22:00 to 7:59)
    $village_background_image = $night_background_path;
}

require '../header.php';
?>

<div id="game-container">
    <!-- Game header with resources -->
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">&#127968;</span>
            <span>Overview</span>
        </div>
        <div class="header-user">
            Player: <?= htmlspecialchars($username) ?><br>
            <span class="village-name-display" data-village-id="<?= $village_id ?>"><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</span>
        </div>
        <!-- Resource Bar -->
        <div id="resource-bar">
            <div class="resource-item">
                <img src="/img/ds_graphic/wood.png" alt="Wood">
                <span id="current-wood"><?= formatNumber($village['wood']) ?></span> / <span id="warehouse-wood-capacity"><?= formatNumber($village['warehouse_capacity']) ?></span>
            </div>
            <div class="resource-item">
                <img src="/img/ds_graphic/stone.png" alt="Clay">
                <span id="current-clay"><?= formatNumber($village['clay']) ?></span> / <span id="warehouse-clay-capacity"><?= formatNumber($village['warehouse_capacity']) ?></span>
            </div>
            <div class="resource-item">
                <img src="/img/ds_graphic/iron.png" alt="Iron">
                <span id="current-iron"><?= formatNumber($village['iron']) ?></span> / <span id="warehouse-iron-capacity"><?= formatNumber($village['warehouse_capacity']) ?></span>
            </div>
            <div class="resource-item">
                <img src="/img/ds_graphic/resources/population.png" alt="Population">
                <span id="current-population"><?= $village['population'] ?></span> / <span id="farm-population-capacity"><?= $village['farm_capacity'] ?></span>
            </div>
        </div>
    </header>

    <main id="main-content">
        <?php if (!empty($message)): ?>
            <div class="game-message">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <section class="main-game-content">
            <div id="village-view-graphic">
                <!-- Village graphic with positioned building placeholders -->
                <img src="<?= $village_background_image ?>" alt="Village view" style="width: 100%; height: auto; border-radius: var(--border-radius-medium);">
                <!-- Example building placeholder positions (ideally in JS/CSS) -->
                <?php
                // Map internal_name to graphic base filenames
                $building_graphic_map = [
                    'main_building' => 'main',
                    'barracks' => 'barracks',
                    'stable' => 'stable',
                    'workshop' => 'garage',
                    'academy' => 'academy',
                    'market' => 'market',
                    'smithy' => 'smith',
                    'wood_production' => 'wood', // Sawmill graphic base
                    'clay_pit' => 'stone', // Clay pit graphic base
                    'iron_mine' => 'iron', // Iron mine graphic base
                    'farm' => 'farm',
                    'warehouse' => 'storage', // Warehouse graphic base
                    'wall' => 'wall',
                    'statue' => 'statue',
                    'church' => 'church',
                    'first_church' => 'church_f', // First church graphic base
                    'watchtower' => 'watchtower',
                    // Add other buildings if graphics exist
                ];

                // Example positions for building placeholders
                $building_positions = [
                    // Positions adapted from original Tribal Wars layout
                    'main_building' => ['left' => '45%', 'top' => '35%', 'width' => '18%', 'height' => '28%'], // Town hall
                    'barracks' => ['left' => '25%', 'top' => '58%', 'width' => '12%', 'height' => '18%'], // Barracks
                    'stable' => ['left' => '60%', 'top' => '52%', 'width' => '12%', 'height' => '18%'], // Stable
                    'workshop' => ['left' => '34%', 'top' => '28%', 'width' => '12%', 'height' => '18%'], // Workshop
                    'academy' => ['left' => '69%', 'top' => '64%', 'width' => '12%', 'height' => '18%'], // Academy
                    'market' => ['left' => '24%', 'top' => '24%', 'width' => '12%', 'height' => '18%'], // Market
                    'smithy' => ['left' => '17%', 'top' => '34%', 'width' => '12%', 'height' => '18%'], // Smithy
                    'wood_production' => ['left' => '7%', 'top' => '55%', 'width' => '12%', 'height' => '18%'], // Sawmill
                    'clay_pit' => ['left' => '12%', 'top' => '75%', 'width' => '12%', 'height' => '18%'], // Clay pit
                    'iron_mine' => ['left' => '77%', 'top' => '70%', 'width' => '12%', 'height' => '18%'], // Iron mine
                    'farm' => ['left' => '32%', 'top' => '60%', 'width' => '12%', 'height' => '18%'], // Farm
                    'warehouse' => ['left' => '46%', 'top' => '50%', 'width' => '12%', 'height' => '18%'], // Warehouse
                    'wall' => ['left' => '38%', 'top' => '88%', 'width' => '30%', 'height' => '10%'], // Wall
                    'statue' => ['left' => '72%', 'top' => '15%', 'width' => '10%', 'height' => '15%'], // Statue
                    'church' => ['left' => '82%', 'top' => '30%', 'width' => '12%', 'height' => '18%'], // Church
                    'first_church' => ['left' => '82%', 'top' => '30%', 'width' => '12%', 'height' => '18%'], // First church
                    'watchtower' => ['left' => '2%', 'top' => '42%', 'width' => '10%', 'height' => '15%'], // Watchtower
                ];
                // Sort positions by internal name for consistent rendering
                ksort($building_positions);

                foreach ($buildings_data as $building) {
                    $pos = $building_positions[$building['internal_name']] ?? null;
                    if ($pos) {
                        // Determine the image variant based on building level
                        $internalName = $building['internal_name'];
                        $buildingLevel = $building['level'];
                        $isUpgrading = $building['is_upgrading'] ?? false;

                        $image_variant = null; // Default to no image variant
                        $image_extension = '.png'; // Default to PNG

                        // Logic for production buildings (wood_production, clay_pit, iron_mine, farm)
                        $production_buildings = ['wood_production', 'clay_pit', 'iron_mine', 'farm'];
                        if (in_array($internalName, $production_buildings)) {
                            // Specific logic for Farm image variant based on level
                            if ($internalName === 'farm') {
                                 if ($buildingLevel >= 15) {
                                     $image_variant = 3;
                                 } elseif ($buildingLevel >= 5) { // Farm image starts from level 5 (variant 2)
                                     $image_variant = 2;
                                 }
                                 // No image variant for farm levels 0-4 (image_variant remains null)
                            } else { // Logic for other production buildings (wood_production, clay_pit, iron_mine)
                                 if ($buildingLevel >= 15) {
                                     $image_variant = 3;
                                 } elseif ($buildingLevel >= 10) {
                                     $image_variant = 2;
                                 } elseif ($buildingLevel >= 1) { // Variant 1 for levels 1-9
                                      $image_variant = 1;
                                 }
                            }
                            
                            // If building from level 0, use variant 0 and GIF (applies to all production buildings)
                            if ($buildingLevel === 0 && $isUpgrading) {
                                 $image_variant = 0;
                                 $image_extension = '.gif';
                            }
                            
                            // TODO: Implement logic to switch between GIF (working) and PNG (idle/full) based on resource levels/warehouse capacity.
                            // This requires access to current resource levels and warehouse capacity for the village.
                            // For now, defaulting to PNG unless building from level 0.

                        } else {
                            // Default logic for other buildings (non-production or level 0 not building)
                            if ($buildingLevel >= 15) {
                                $image_variant = 3;
                            } elseif ($buildingLevel >= 10) {
                                $image_variant = 2;
                            } elseif ($buildingLevel >= 1) { // For levels 1-9, use variant 1
                                 $image_variant = 1;
                            } elseif ($buildingLevel === 0 && $isUpgrading) {
                                 // Special case for building from level 0 (e.g., barracks, church) - use variant 0
                                 $image_variant = 0;
                                 $image_extension = '.gif'; // Assume building from 0 is animated
                            } elseif ($buildingLevel === 0 && !$isUpgrading) {
                                 // Level 0 and not building - no image
                                 $image_variant = null;
                            }
                        }

                        // Resolve graphic base name from the map (fallback to internal_name)
                        $graphic_base_name = $building_graphic_map[$internalName] ?? $internalName;

                        // Determine the base path based on time of day
                        $base_building_image_path = ($current_hour >= $day_start_hour && $current_hour < $night_start_hour) ? '/img/ds_graphic/visual/' : '/img/ds_graphic/visual_night/'; // Corrected path

                        // Construct the image path
                        $building_image_path = null;
                        if ($image_variant !== null) {
                            $building_image_path = $base_building_image_path . htmlspecialchars($graphic_base_name) . $image_variant . $image_extension;
                        }

                        // JS should handle any special cases/animations; assume assets exist.


                        $is_upgrading_class = $isUpgrading ? 'building-upgrading' : '';
                        ?>
                        <div class="building-placeholder <?= $is_upgrading_class ?>" 
                             style="left: <?= $pos['left'] ?>; top: <?= $pos['top'] ?>; width: <?= $pos['width'] ?>; height: <?= $pos['height'] ?>;"
                             data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>"
                             data-village-id="<?= $village_id ?>"
                             title="<?= htmlspecialchars($building['name']) ?> (Level <?= (int)$building['level'] ?>)">
                            <?php if ($building_image_path): ?>
                                <img src="<?= $building_image_path ?>" alt="<?= htmlspecialchars($building['name']) ?>" class="building-icon building-graphic" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>" data-building-level="<?= $building['level'] ?>">
                            <?php else: ?>
                                <!-- No image for this building at this level -->
                                <div class="building-icon building-graphic no-image" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>" data-building-level="<?= $building['level'] ?>"></div>
                            <?php endif; ?>
                            <span class="placeholder-text"><?= htmlspecialchars($building['name']) ?> (<?= $building['level'] ?>)</span>
                        </div>
                        <?php
                    }
                }

                 // Add a placeholder for the town hall flag if the hall exists
                 if (isset($buildings_data['main_building']) && $buildings_data['main_building']['level'] > 0) {
                     $main_building_level = $buildings_data['main_building']['level'];
                     // Determine flag variant based on hall level
                     $flag_variant = 1;
                     if ($main_building_level >= 15) {
                         $flag_variant = 3;
                     } elseif ($main_building_level >= 10) {
                         $flag_variant = 2;
                     }
                     $flag_image_path = '/img/ds_graphic/visual/' . 'mainflag' . $flag_variant . '.gif';

                     // Position for the town hall flag (may need tuning)
                     $flag_position = ['left' => '48%', 'top' => '25%', 'width' => '4%', 'height' => '5%']; // Example position
                      // Check if the hall is upgrading to decide on GIF
                     $main_building_is_upgrading = $buildings_data['main_building']['is_upgrading'] ?? false;

                     // Use GIF when hall is upgrading, PNG otherwise
                     $flag_image_name = 'mainflag' . $flag_variant;
                     $flag_image_path = '/img/ds_graphic/visual/' . $flag_image_name . ($main_building_is_upgrading ? '.gif' : '.png');

                     ?>
                     <div class="building-placeholder main-flag" 
                          style="left: <?= $flag_position['left'] ?>; top: <?= $flag_position['top'] ?>; width: <?= $flag_position['width'] ?>; height: <?= $flag_position['height'] ?>;"
                          data-building-internal-name="main_building_flag" data-village-id="<?= $village_id ?>">
                         <img src="<?= $flag_image_path ?>" alt="Town hall flag" class="building-icon building-graphic" data-building-internal-name="main_building_flag" data-building-level="<?= $main_building_level ?>">
                     </div>
                     <?php
                 }

                ?>
            </div>

            <section class="building-list-section">
                <?php
                // Separate buildings into resource and other categories
                $resource_buildings_data = [];
                $other_buildings_data = [];
                $resource_building_keys = ['sawmill', 'clay_pit', 'iron_mine', 'farm', 'warehouse'];

                foreach ($buildings_data as $internal_name => $building) {
                    if (in_array($internal_name, $resource_building_keys)) {
                        $resource_buildings_data[$internal_name] = $building;
                    } else {
                        $other_buildings_data[$internal_name] = $building;
                    }
                }

                // Function to render a single building item to avoid code duplication
                function render_building_item($building, $village, $buildingManager, $village_id) {
                    $current_level = $building['level'];
                    $next_level = $building['next_level'];
                    $is_upgrading = $building['is_upgrading'];
                    $queue_finish_time = $building['queue_finish_time'];
                    $queue_level_after = $building['queue_level_after'];
                    $max_level = $building['max_level'];
                    $upgrade_costs = $building['upgrade_costs'];
                    $upgrade_time_seconds = $building['upgrade_time_seconds'];
                    $can_upgrade = $building['can_upgrade'];
                    $upgrade_not_available_reason = $building['upgrade_not_available_reason'];
                    $production_type = $building['production_type'];
                    $population_cost = $building['population_cost'];
                    $next_level_population_cost = $building['next_level_population_cost'];
                    $production_info = '';
                    if ($production_type) {
                         $hourly_production = $buildingManager->getHourlyProduction($building['internal_name'], $current_level);
                         $production_info = "<p>Production: " . formatNumber($hourly_production) . "/h</p>";
                    }
                    $population_info = '';
                    if ($population_cost !== null) {
                         $population_info = "<p>Population: " . formatNumber($population_cost) . "</p>";
                    }
                    $upgrade_time_formatted = ($upgrade_time_seconds !== null) ? formatDuration($upgrade_time_seconds) : '';
                    ?>
                    <div class="building-item" data-internal-name="<?= htmlspecialchars($building['internal_name']) ?>">
                        <h3><?= htmlspecialchars($building['name']) ?> (Level <?= $building['level'] ?>)</h3>
                        <p><?= htmlspecialchars($building['description']) ?></p>
                        <?= $production_info ?>
                        <?= $population_info ?>

                        <?php if ($is_upgrading): ?>
                            <p class="upgrade-status">Upgrading to level <?= $queue_level_after ?>.</p>
                            <p class="upgrade-timer" data-finish-time="<?= $queue_finish_time ?>"><?= getRemainingTimeText($queue_finish_time) ?></p>
                            <div class="progress-bar-container" data-finish-time="<?= $queue_finish_time ?>"><div class="progress-bar"></div><span class="progress-text"></span></div>
                            <button class="btn btn-secondary cancel-upgrade-button" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>">Cancel</button>
                        <?php elseif ($current_level >= $max_level): ?>
                            <p class="upgrade-status">Maximum level reached (<?= $max_level ?>).</p>
                             <button class="btn btn-secondary" disabled>Max level</button>
                        <?php else: ?>
                            <p class="upgrade-status">Upgrade to level <?= $next_level ?>:</p>
                            <?php if ($upgrade_costs): ?>
                                 <p>Cost:
                                    <span class="resource wood <?= ($village['wood'] < $upgrade_costs['wood']) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/wood.png" alt="Wood"><?= formatNumber($upgrade_costs['wood']) ?></span>
                                    <span class="resource clay <?= ($village['clay'] < $upgrade_costs['clay']) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/stone.png" alt="Clay"><?= formatNumber($upgrade_costs['clay']) ?></span>
                                   <span class="resource iron <?= ($village['iron'] < $upgrade_costs['iron']) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/iron.png" alt="Iron"><?= formatNumber($upgrade_costs['iron']) ?></span>
                                 </p>
                                 <?php if ($next_level_population_cost > 0): ?>
                                     <p>Required free population: <span class="resource population <?= (($village['farm_capacity'] - $village['population']) < $next_level_population_cost) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/resources/population.png" alt="Population"><?= formatNumber($next_level_population_cost) ?></span></p>
                                 <?php endif; ?>
                                 <p>Build time: <span class="upgrade-time-formatted"><?= $upgrade_time_formatted ?></span></p>

                                 <?php if ($can_upgrade): ?>
                                     <button class="btn btn-primary upgrade-building-button" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>" data-village-id="<?= $village_id ?>">Upgrade to level <?= $next_level ?></button>
                                 <?php else: ?>
                                     <p class="error-message"><?= htmlspecialchars($upgrade_not_available_reason) ?></p>
                                     <button class="btn btn-primary" disabled>Upgrade to level <?= $next_level ?></button>
                                 <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php
                             $buildings_with_panels = ['main_building', 'barracks', 'stable', 'workshop', 'smithy', 'academy', 'market', 'statue', 'church', 'first_church', 'mint'];
                             if (in_array($building['internal_name'], $buildings_with_panels) && $building['level'] > 0):
                                 $actionText = getBuildingActionText($building['internal_name']);
                        ?>
                                <button class="btn btn-secondary building-action-button" data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>" data-village-id="<?= $village_id ?>"><?= htmlspecialchars($actionText) ?></button>
                            <?php endif; ?>
                    </div>
                    <?php
                }
               ?>

                <h2>Resource Buildings</h2>
                <div class="buildings-list">
                    <?php foreach ($resource_buildings_data as $building) render_building_item($building, $village, $buildingManager, $village_id); ?>
                </div>

                <h2 style="margin-top: 30px;">Town and Military Buildings</h2>
                <div class="buildings-list">
                    <?php foreach ($other_buildings_data as $building) render_building_item($building, $village, $buildingManager, $village_id); ?>
                </div>
            </section>
        </section>

        <!-- Building Details/Action Popup -->
        <div id="building-action-popup" class="popup-container">
            <div class="popup-content">
                <span class="close-button">&times;</span>
                <div id="popup-details">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>



    </main>
</div>

<?php require '../footer.php'; ?>

<!-- Scripts -->
<script src="/js/resources.js" defer></script>
<script src="/js/notifications.js" defer></script>
<script src="/js/buildings.js" defer></script>
<script src="/js/units.js" defer></script>
<script src="/js/research.js" defer></script>
<script src="/js/market.js" defer></script>
<script src="/js/main_building.js" defer></script>
<script src="/js/noble.js" defer></script>
<script src="/js/mint.js" defer></script>
<script src="/js/info_panel.js" defer></script> <!-- Generic panel for info -->
<script src="/js/main.js" defer></script> // Add main.js with absolute path



<script>
// Helper functions (should be in a common.js file)
// Duplicate PHP/JS functions removed; see lib/functions.php and js/resources.js/etc.
// function formatDuration(seconds) { ... }
// function formatNumber(number) { ... }


document.addEventListener('DOMContentLoaded', function() {
    // Initialize resource timers
    // Assuming fetchUpdate from resources.js is called here or via setInterval
    // fetchUpdate(); // Initial fetch
    // setInterval(fetchUpdate, 1000); // Update every second

    // Initialize building queue timers
    updateTimers(); // from js/buildings.js
    setInterval(updateTimers, 1000); // Update every second

    // Initialize recruitment queue timers
    // updateRecruitmentTimers(); // from js/units.js
    // setInterval(updateRecruitmentTimers, 1000); // Update every second

     // Initialize research queue timers
    // updateResearchTimers(); // from js/research.js
    // setInterval(updateResearchTimers, 1000); // Update every second

});

</script>
