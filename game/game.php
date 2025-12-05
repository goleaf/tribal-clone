<?php
require '../init.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/ResourceManager.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';
require_once __DIR__ . '/../lib/managers/ResearchManager.php';
require_once __DIR__ . '/../lib/managers/NotificationManager.php';
require_once __DIR__ . '/../lib/managers/EndgameManager.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';
require_once __DIR__ . '/../lib/managers/IntelManager.php';
require_once __DIR__ . '/../lib/managers/TaskManager.php';
require_once __DIR__ . '/../lib/managers/ViewRenderer.php';
require_once __DIR__ . '/../lib/functions.php';

// Instantiate managers
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$villageManager = new VillageManager($conn);
$resourceManager = new ResourceManager($conn, $buildingManager);
$unitManager = new UnitManager($conn);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);
$researchManager = new ResearchManager($conn);
$notificationManager = new NotificationManager($conn);
$endgameManager = new EndgameManager($conn);
$worldManager = new WorldManager($conn);
$intelManager = new IntelManager($conn);
$taskManager = new TaskManager($conn);
$viewRenderer = new ViewRenderer($conn, $buildingManager, $resourceManager);

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Commander';

// --- FETCH VILLAGE DATA ---
$village = $villageManager->getFirstVillage($user_id);

if (!$village) {
    // Should not happen after create_village.php, but keep as a guard
    header("Location: /player/create_village.php");
    exit();
}
$village_id = $village['id'];
$worldId = isset($village['world_id']) ? (int)$village['world_id'] : (defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1);

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
    if ($worldManager->areNotificationsEnabled($worldId)) {
        $notificationManager->addNotification(
            $user_id,
            sprintf('%d attack(s) resolved.', count($attackMessages)),
            'info',
            '/messages/reports.php'
        );
    }
}

// --- BUILDING DATA FOR VIEW ---
$buildings_data = $buildingManager->getVillageBuildingsViewData($village_id, $main_building_level);
$production_rates = $resourceManager->getProductionRates($village_id);
$active_upgrades = array_filter($buildings_data, static fn($b) => !empty($b['is_upgrading']));
$build_queue_count = $buildingManager->getActivePendingQueueCount($village_id) ?? 0;
$recruit_queue_count = count($unitManager->getRecruitmentQueues($village_id) ?? []);
$storage_capacity = $village['warehouse_capacity'] ?? 0;
$worldSettings = $worldManager->getSettings($worldId);
$enableNudges = $worldManager->areNudgesEnabled($worldId);
$enableTasks = $worldManager->areTasksEnabled($worldId);
$enableNotifications = $worldManager->areNotificationsEnabled($worldId);
$latestIntelAgeSeconds = $intelManager->getLatestReportAgeSecondsForUser($user_id);
$nearCapResources = [];
foreach (['wood', 'clay', 'iron'] as $resType) {
    $amount = (float)($village[$resType] ?? 0);
    if ($storage_capacity > 0 && $amount >= 0.9 * $storage_capacity) {
        $nearCapResources[] = ucfirst($resType);
    }
}

// --- PAGE META ---
$pageTitle = htmlspecialchars($village['name']) . ' - Village Overview';

// Determine time of day and set background image path
date_default_timezone_set('Europe/Warsaw');
$current_hour = (int)date('H'); // Get current hour (0-23)

$day_start_hour = 8;
$night_start_hour = 22;

// Assuming the background image files are named 'background.jpg' in their respective folders
$day_background_path = '/img/ds_graphic/visual/back_none.jpg';
$night_background_path = '/img/ds_graphic/visual_night/back_none.jpg';

$village_background_image = ($current_hour >= $day_start_hour && $current_hour < $night_start_hour)
    ? $day_background_path
    : $night_background_path;

$free_population = max(0, ($village['farm_capacity'] ?? 0) - ($village['population'] ?? 0));
$dominanceSnapshot = $endgameManager->getTribeDominanceSnapshot();
$showDominanceBanner = $endgameManager->shouldShowDominanceWarning($dominanceSnapshot);

$nudges = [];
if ($enableNudges) {
    if ($build_queue_count === 0) {
        $nudges[] = [
            'code' => 'build_queue_idle',
            'severity' => 'info',
            'message' => 'Construction queue is idle.',
            'action' => [
                'type' => 'building',
                'label' => 'Open town hall',
                'internal' => 'main_building',
                'name' => $buildings_data['main_building']['name'] ?? 'Town hall',
                'description' => $buildings_data['main_building']['description'] ?? '',
                'level' => (int)($buildings_data['main_building']['level'] ?? 0),
            ],
        ];
    }
    if ($recruit_queue_count === 0 && ($buildings_data['barracks']['level'] ?? 0) > 0) {
        $nudges[] = [
            'code' => 'recruit_queue_idle',
            'severity' => 'info',
            'message' => 'Recruitment queue is empty.',
            'action' => [
                'type' => 'building',
                'label' => 'Open barracks',
                'internal' => 'barracks',
                'name' => $buildings_data['barracks']['name'] ?? 'Barracks',
                'description' => $buildings_data['barracks']['description'] ?? '',
                'level' => (int)($buildings_data['barracks']['level'] ?? 0),
            ],
        ];
    }
    if (!empty($nearCapResources)) {
        $nudges[] = [
            'code' => 'resource_cap',
            'severity' => 'warning',
            'message' => implode(', ', $nearCapResources) . ' near storage cap — spend or trade to avoid overflow.',
            'action' => [
                'type' => 'building',
                'label' => 'Upgrade warehouse',
                'internal' => 'warehouse',
                'name' => $buildings_data['warehouse']['name'] ?? 'Warehouse',
                'description' => $buildings_data['warehouse']['description'] ?? '',
                'level' => (int)($buildings_data['warehouse']['level'] ?? 0),
            ],
        ];
    }
    if ($latestIntelAgeSeconds === null || $latestIntelAgeSeconds > 72 * 3600) {
        $ageLabel = $latestIntelAgeSeconds === null ? 'No intel yet' : 'Last intel is stale';
        $nudges[] = [
            'code' => 'stale_intel',
            'severity' => 'info',
            'message' => $ageLabel . ' — send scouts to refresh enemy data.',
            'action' => [
                'type' => 'link',
                'label' => 'Open Intel',
                'href' => '/game/intel.php'
            ],
        ];
    }
    if ($enableTasks) {
        $soonestExpiry = null;
        $soonestType = null;
        foreach (['daily', 'weekly'] as $taskType) {
            $tasks = $taskManager->getTasks($user_id, $taskType);
            foreach ($tasks as $task) {
                if (!in_array($task['status'], ['active', 'completed'], true)) {
                    continue;
                }
                $remaining = strtotime($task['expires_at']) - time();
                if ($remaining > 0 && $remaining <= 3600 && ($soonestExpiry === null || $remaining < $soonestExpiry)) {
                    $soonestExpiry = $remaining;
                    $soonestType = $taskType;
                }
            }
        }
        if ($soonestExpiry !== null) {
            $minutesLeft = max(1, (int)ceil($soonestExpiry / 60));
            $nudges[] = [
                'code' => 'task_expiring',
                'severity' => 'warning',
                'message' => ucfirst($soonestType) . " tasks expire in {$minutesLeft} min — claim or reroll now.",
                'action' => [
                    'type' => 'link',
                    'label' => 'Open tasks',
                    'href' => '/ajax/tasks/tasks.php?type=' . $soonestType
                ],
            ];
        }
    }
}

require '../header.php';
?>

<div id="game-container" class="game-shell">
    <main id="main-content">
    <section class="village-hero">
        <div class="hero-copy">
            <p class="eyebrow">Village overview</p>
            <h1>
                <?= htmlspecialchars($village['name']) ?>
                <span class="coords">(<?= (int)$village['x_coord'] ?>|<?= (int)$village['y_coord'] ?>)</span>
            </h1>
            <p class="hero-meta">
                Commander <?= htmlspecialchars($username) ?> &middot;
                Main building lvl <?= (int)($buildings_data['main_building']['level'] ?? $main_building_level) ?>
            </p>
            <div class="hero-badges">
                <span class="badge">Population <?= formatNumber($village['population']) ?>/<?= formatNumber($village['farm_capacity']) ?></span>
                <span class="badge">Storage <?= formatNumber($storage_capacity) ?></span>
                <span class="badge"><?= ($current_hour >= $day_start_hour && $current_hour < $night_start_hour) ? 'Day cycle' : 'Night cycle' ?> · Server <?= date('H:i') ?></span>
            </div>
            <?php if (!empty($nudges)): ?>
                <div class="nudge-deck">
                    <?php foreach ($nudges as $nudge): ?>
                        <div class="nudge-card <?= htmlspecialchars($nudge['severity']) ?>" data-nudge-code="<?= htmlspecialchars($nudge['code']) ?>">
                            <div class="nudge-main">
                                <span class="nudge-code"><?= htmlspecialchars(strtoupper($nudge['code'])) ?></span>
                                <p><?= htmlspecialchars($nudge['message']) ?></p>
                            </div>
                            <?php if (!empty($nudge['action'])): ?>
                                <?php if ($nudge['action']['type'] === 'building'): ?>
                                    <button
                                        class="action-chip building-action-button"
                                        data-building-internal-name="<?= htmlspecialchars($nudge['action']['internal']) ?>"
                                        data-village-id="<?= $village_id ?>"
                                        data-building-name="<?= htmlspecialchars($nudge['action']['name'], ENT_QUOTES) ?>"
                                        data-building-level="<?= (int)$nudge['action']['level'] ?>"
                                        data-building-description="<?= htmlspecialchars($nudge['action']['description'], ENT_QUOTES) ?>"
                                    ><?= htmlspecialchars($nudge['action']['label']) ?></button>
                                <?php elseif ($nudge['action']['type'] === 'link'): ?>
                                    <a class="action-chip" href="<?= htmlspecialchars($nudge['action']['href']) ?>"><?= htmlspecialchars($nudge['action']['label']) ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($message)): ?>
                <div class="game-message accent">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            <?php if ($showDominanceBanner && isset($dominanceSnapshot['top'])): ?>
                <?php
                    $top = $dominanceSnapshot['top'];
                    $topSharePercent = round(($top['share'] ?? 0) * 100, 1);
                ?>
                <div class="game-message warning">
                    <strong>Endgame alert:</strong> Tribe <?= htmlspecialchars($top['name']) ?> controls <?= $topSharePercent ?>% of world tribe points.
                    Contest objectives now or the world may lock soon.
                    <?php if (!empty($dominanceSnapshot['leaders'])): ?>
                        <div class="mini-leaderboard">
                            <?php foreach ($dominanceSnapshot['leaders'] as $idx => $leader): ?>
                                <span class="badge">
                                    #<?= $idx + 1 ?> <?= htmlspecialchars($leader['name']) ?> (<?= formatNumber($leader['points']) ?> pts)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="hero-panels">
            <div id="hud-resources" class="hud-resources" data-village-id="<?= $village_id ?>">
                <?php
                $hud_resources = [
                    ['type' => 'wood', 'label' => 'Wood', 'icon' => '/img/ds_graphic/wood.png', 'amount' => $village['wood'], 'capacity' => $storage_capacity, 'production' => $production_rates['wood'] ?? 0],
                    ['type' => 'clay', 'label' => 'Clay', 'icon' => '/img/ds_graphic/stone.png', 'amount' => $village['clay'], 'capacity' => $storage_capacity, 'production' => $production_rates['clay'] ?? 0],
                    ['type' => 'iron', 'label' => 'Iron', 'icon' => '/img/ds_graphic/iron.png', 'amount' => $village['iron'], 'capacity' => $storage_capacity, 'production' => $production_rates['iron'] ?? 0],
                    ['type' => 'population', 'label' => 'Population', 'icon' => '/img/ds_graphic/resources/population.png', 'amount' => $village['population'], 'capacity' => $village['farm_capacity'], 'production' => null],
                ];
                foreach ($hud_resources as $res):
                ?>
                <div class="hud-pill" data-resource="<?= htmlspecialchars($res['type']) ?>">
                    <div class="pill-label">
                        <img src="<?= $res['icon'] ?>" alt="<?= htmlspecialchars($res['label']) ?>">
                        <span><?= htmlspecialchars($res['label']) ?></span>
                    </div>
                    <div class="pill-value">
                        <span class="value" data-field="amount"><?= formatNumber($res['amount']) ?></span>
                        <?php if ($res['capacity']): ?>
                            <span class="slash">/</span>
                            <span class="value capacity" data-field="capacity"><?= formatNumber($res['capacity']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($res['production'] !== null): ?>
                        <div class="pill-sub">+<?= formatNumber($res['production']) ?>/h</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="hero-actions">
                <a class="action-chip" href="/map/map.php"><i class="fas fa-map"></i> Map</a>
                <button
                    class="action-chip building-action-button"
                    data-building-internal-name="main_building"
                    data-village-id="<?= $village_id ?>"
                    data-building-name="<?= htmlspecialchars($buildings_data['main_building']['name'] ?? 'Town hall', ENT_QUOTES) ?>"
                    data-building-level="<?= (int)($buildings_data['main_building']['level'] ?? 0) ?>"
                    data-building-description="<?= htmlspecialchars($buildings_data['main_building']['description'] ?? '', ENT_QUOTES) ?>"
                ><i class="fas fa-city"></i> Town hall</button>
                <?php if (($buildings_data['barracks']['level'] ?? 0) > 0): ?>
                    <button
                        class="action-chip building-action-button"
                        data-building-internal-name="barracks"
                        data-village-id="<?= $village_id ?>"
                        data-building-name="<?= htmlspecialchars($buildings_data['barracks']['name'] ?? 'Barracks', ENT_QUOTES) ?>"
                        data-building-level="<?= (int)($buildings_data['barracks']['level'] ?? 0) ?>"
                        data-building-description="<?= htmlspecialchars($buildings_data['barracks']['description'] ?? '', ENT_QUOTES) ?>"
                    ><i class="fas fa-shield-alt"></i> Barracks</button>
                <?php endif; ?>
                <?php if (($buildings_data['market']['level'] ?? 0) > 0): ?>
                    <button
                        class="action-chip building-action-button"
                        data-building-internal-name="market"
                        data-village-id="<?= $village_id ?>"
                        data-building-name="<?= htmlspecialchars($buildings_data['market']['name'] ?? 'Market', ENT_QUOTES) ?>"
                        data-building-level="<?= (int)($buildings_data['market']['level'] ?? 0) ?>"
                        data-building-description="<?= htmlspecialchars($buildings_data['market']['description'] ?? '', ENT_QUOTES) ?>"
                    ><i class="fas fa-coins"></i> Market</button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php
    // Prepare building graphics and positions
    $building_graphic_map = [
        'main_building' => 'main',
        'barracks' => 'barracks',
        'stable' => 'stable',
        'workshop' => 'garage',
        'academy' => 'academy',
        'market' => 'market',
        'smithy' => 'smith',
        'sawmill' => 'wood',
        'clay_pit' => 'stone',
        'iron_mine' => 'iron',
        'farm' => 'farm',
        'warehouse' => 'storage',
        'wall' => 'wall',
        'statue' => 'statue',
        'church' => 'church',
        'first_church' => 'church_f',
        'watchtower' => 'watchtower',
    ];

    $building_positions = [
        'main_building' => ['left' => '45%', 'top' => '35%', 'width' => '18%', 'height' => '28%'],
        'barracks' => ['left' => '25%', 'top' => '58%', 'width' => '12%', 'height' => '18%'],
        'stable' => ['left' => '60%', 'top' => '52%', 'width' => '12%', 'height' => '18%'],
        'workshop' => ['left' => '34%', 'top' => '28%', 'width' => '12%', 'height' => '18%'],
        'academy' => ['left' => '69%', 'top' => '64%', 'width' => '12%', 'height' => '18%'],
        'market' => ['left' => '24%', 'top' => '24%', 'width' => '12%', 'height' => '18%'],
        'smithy' => ['left' => '17%', 'top' => '34%', 'width' => '12%', 'height' => '18%'],
        'sawmill' => ['left' => '7%', 'top' => '55%', 'width' => '12%', 'height' => '18%'],
        'clay_pit' => ['left' => '12%', 'top' => '75%', 'width' => '12%', 'height' => '18%'],
        'iron_mine' => ['left' => '77%', 'top' => '70%', 'width' => '12%', 'height' => '18%'],
        'farm' => ['left' => '32%', 'top' => '60%', 'width' => '12%', 'height' => '18%'],
        'warehouse' => ['left' => '46%', 'top' => '50%', 'width' => '12%', 'height' => '18%'],
        'wall' => ['left' => '38%', 'top' => '88%', 'width' => '30%', 'height' => '10%'],
        'statue' => ['left' => '72%', 'top' => '15%', 'width' => '10%', 'height' => '15%'],
        'church' => ['left' => '82%', 'top' => '30%', 'width' => '12%', 'height' => '18%'],
        'first_church' => ['left' => '82%', 'top' => '30%', 'width' => '12%', 'height' => '18%'],
        'watchtower' => ['left' => '2%', 'top' => '42%', 'width' => '10%', 'height' => '15%'],
    ];
    ksort($building_positions);
    ?>

    <div class="game-grid">
        <section class="panel village-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Village view</p>
                    <h2>Click buildings to inspect or upgrade</h2>
                    <p class="muted">Background updates automatically to <?= ($current_hour >= $day_start_hour && $current_hour < $night_start_hour) ? 'daylight' : 'night watch' ?>.</p>
                </div>
                <div class="status-chip"><?= ($current_hour >= $day_start_hour && $current_hour < $night_start_hour) ? 'Day phase' : 'Night phase' ?></div>
            </div>
            <div class="village-view-wrapper">
                <div id="village-view-graphic" class="village-view">
                    <img src="<?= $village_background_image ?>" alt="Village view" class="village-background">
                    <?php
                    foreach ($buildings_data as $building) {
                        $pos = $building_positions[$building['internal_name']] ?? null;
                        if ($pos) {
                            $internalName = $building['internal_name'];
                            $buildingLevel = $building['level'];
                            $isUpgrading = $building['is_upgrading'] ?? false;

                            $image_variant = null; // Default to no image variant
                            $image_extension = '.png'; // Default to PNG

                            $production_buildings = ['sawmill', 'clay_pit', 'iron_mine', 'farm'];
                            if (in_array($internalName, $production_buildings, true)) {
                                if ($internalName === 'farm') {
                                    if ($buildingLevel >= 15) {
                                        $image_variant = 3;
                                    } elseif ($buildingLevel >= 5) {
                                        $image_variant = 2;
                                    }
                                } else {
                                    if ($buildingLevel >= 15) {
                                        $image_variant = 3;
                                    } elseif ($buildingLevel >= 10) {
                                        $image_variant = 2;
                                    } elseif ($buildingLevel >= 1) {
                                        $image_variant = 1;
                                    }
                                }

                                if ($buildingLevel === 0 && $isUpgrading) {
                                    $image_variant = 0;
                                    $image_extension = '.gif';
                                }
                            } else {
                                if ($buildingLevel >= 15) {
                                    $image_variant = 3;
                                } elseif ($buildingLevel >= 10) {
                                    $image_variant = 2;
                                } elseif ($buildingLevel >= 1) {
                                    $image_variant = 1;
                                } elseif ($buildingLevel === 0 && $isUpgrading) {
                                    $image_variant = 0;
                                    $image_extension = '.gif';
                                } else {
                                    $image_variant = null;
                                }
                            }

                            $graphic_base_name = $building_graphic_map[$internalName] ?? $internalName;

                            $base_building_image_path = ($current_hour >= $day_start_hour && $current_hour < $night_start_hour) ? '/img/ds_graphic/visual/' : '/img/ds_graphic/visual_night/';

                            $building_image_path = null;
                            if ($image_variant !== null) {
                                $building_image_path = $base_building_image_path . htmlspecialchars($graphic_base_name) . $image_variant . $image_extension;
                            }

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
                        $flag_variant = 1;
                        if ($main_building_level >= 15) {
                            $flag_variant = 3;
                        } elseif ($main_building_level >= 10) {
                            $flag_variant = 2;
                        }
                        $flag_position = ['left' => '48%', 'top' => '25%', 'width' => '4%', 'height' => '5%'];
                        $flag_image_name = 'mainflag' . $flag_variant;
                        $flag_image_path = '/img/ds_graphic/visual/' . $flag_image_name . '.gif';

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
                <div class="village-view-footer">
                    <span>Tip: hover to read levels, click to open actions.</span>
                    <span class="legend-dot upgrading"></span><small>Upgrading</small>
                </div>
            </div>
        </section>

        <?php
        $resource_buildings_data = [];
        $other_buildings_data = [];
        $resource_building_keys = ['sawmill', 'clay_pit', 'iron_mine', 'farm', 'warehouse'];

        foreach ($buildings_data as $internal_name => $building) {
            if (in_array($internal_name, $resource_building_keys, true)) {
                $resource_buildings_data[$internal_name] = $building;
            } else {
                $other_buildings_data[$internal_name] = $building;
            }
        }

        function render_building_item($building, $village, $buildingManager, $village_id) {
            $current_level = $building['level'];
            $next_level = $building['next_level'];
            $is_upgrading = $building['is_upgrading'];
            $queue_finish_time = $building['queue_finish_time'];
            $queue_start_time = $building['queue_start_time'] ?? null;
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
                $production_info = '<p class="stat-line">Production: ' . formatNumber($hourly_production) . '/h</p>';
            }
            $population_info = '';
            if ($population_cost !== null) {
                $population_info = '<p class="stat-line">Population: ' . formatNumber($population_cost) . '</p>';
            }
            $upgrade_time_formatted = ($upgrade_time_seconds !== null) ? formatDuration((int)$upgrade_time_seconds) : '';
            $start_time_guess = $queue_start_time ?? (($queue_finish_time && $upgrade_time_seconds) ? $queue_finish_time - $upgrade_time_seconds : null);
            ?>
            <div class="building-item" data-internal-name="<?= htmlspecialchars($building['internal_name']) ?>" data-current-level="<?= (int)$current_level ?>">
                <div class="building-item__header">
                    <div>
                        <p class="eyebrow"><?= htmlspecialchars($building['internal_name']) ?></p>
                        <h3><?= htmlspecialchars($building['name']) ?></h3>
                    </div>
                    <span class="level-badge">Lvl <?= $building['level'] ?></span>
                </div>
                <p class="muted"><?= htmlspecialchars($building['description']) ?></p>
                <?= $production_info ?>
                <?= $population_info ?>

                <?php if ($is_upgrading): ?>
                    <div class="status-chip">Upgrading to level <?= $queue_level_after ?>.</div>
                    <div class="item-progress" <?php if ($queue_finish_time): ?>data-ends-at="<?= (int)$queue_finish_time ?>"<?php endif; ?> <?php if ($start_time_guess): ?>data-start-time="<?= (int)$start_time_guess ?>"<?php endif; ?>>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <?php if ($queue_finish_time): ?>
                            <div class="progress-time" data-ends-at="<?= (int)$queue_finish_time ?>" <?php if ($start_time_guess): ?>data-start-time="<?= (int)$start_time_guess ?>"<?php endif; ?>>
                                <?= getRemainingTimeText((int)$queue_finish_time) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($current_level >= $max_level): ?>
                    <p class="upgrade-status">Maximum level reached (<?= $max_level ?>).</p>
                    <button class="btn btn-secondary" disabled>Max level</button>
                <?php else: ?>
                    <p class="upgrade-status">Upgrade to level <?= $next_level ?>:</p>
                    <?php if ($upgrade_costs): ?>
                        <div class="cost-row">
                            <span class="resource wood <?= ($village['wood'] < $upgrade_costs['wood']) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/wood.png" alt="Wood"><?= formatNumber($upgrade_costs['wood']) ?></span>
                            <span class="resource clay <?= ($village['clay'] < $upgrade_costs['clay']) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/stone.png" alt="Clay"><?= formatNumber($upgrade_costs['clay']) ?></span>
                            <span class="resource iron <?= ($village['iron'] < $upgrade_costs['iron']) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/iron.png" alt="Iron"><?= formatNumber($upgrade_costs['iron']) ?></span>
                            <?php if ($next_level_population_cost > 0): ?>
                                <span class="resource population <?= (($village['farm_capacity'] - $village['population']) < $next_level_population_cost) ? 'not-enough' : '' ?>"><img src="/img/ds_graphic/resources/population.png" alt="Population"><?= formatNumber($next_level_population_cost) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="muted">Build time: <span class="upgrade-time-formatted"><?= $upgrade_time_formatted ?></span></p>

                        <?php if ($can_upgrade): ?>
                            <button
                                class="btn btn-primary upgrade-building-button"
                                data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>"
                                data-current-level="<?= (int)$building['level'] ?>"
                                data-village-id="<?= $village_id ?>"
                            >Upgrade to level <?= $next_level ?></button>
                        <?php else: ?>
                            <p class="error-message"><?= htmlspecialchars($upgrade_not_available_reason) ?></p>
                            <button class="btn btn-primary" disabled>Upgrade to level <?= $next_level ?></button>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php
                $buildings_with_panels = ['main_building', 'barracks', 'stable', 'workshop', 'smithy', 'academy', 'market', 'statue', 'church', 'first_church', 'mint'];
                if (in_array($building['internal_name'], $buildings_with_panels, true) && $building['level'] > 0):
                    $actionText = getBuildingActionText($building['internal_name']);
                ?>
                    <button
                        class="btn btn-secondary building-action-button"
                        data-building-internal-name="<?= htmlspecialchars($building['internal_name']) ?>"
                        data-village-id="<?= $village_id ?>"
                        data-building-name="<?= htmlspecialchars($building['name'], ENT_QUOTES) ?>"
                        data-building-level="<?= (int)$current_level ?>"
                        data-building-description="<?= htmlspecialchars($building['description'], ENT_QUOTES) ?>"
                    ><?= htmlspecialchars($actionText) ?></button>
                <?php endif; ?>
            </div>
            <?php
        }
        ?>
        <aside class="side-panels">
            <div class="panel queue-card" id="building-queue">
                <div class="panel-header">
                    <h3>Construction queue</h3>
                    <button
                        class="text-button building-action-button"
                        id="open-town-hall-button"
                        type="button"
                        data-building-internal-name="main_building"
                        data-village-id="<?= $village_id ?>"
                        data-building-name="<?= htmlspecialchars($buildings_data['main_building']['name'] ?? 'Town hall', ENT_QUOTES) ?>"
                        data-building-level="<?= (int)($buildings_data['main_building']['level'] ?? 0) ?>"
                        data-building-description="<?= htmlspecialchars($buildings_data['main_building']['description'] ?? '', ENT_QUOTES) ?>"
                    >Open town hall</button>
                </div>
                <div id="building-queue-list" class="queue-list">
                    <?php if (!empty($active_upgrades)): ?>
                        <?php foreach ($active_upgrades as $upgrade): ?>
                            <?php
                                $finish_time = $upgrade['queue_finish_time'] ?? null;
                                $start_time = $upgrade['queue_start_time'] ?? null;
                                $duration_guess = $upgrade['upgrade_time_seconds'] ?? null;
                                if (!$start_time && $finish_time && $duration_guess) {
                                    $start_time = $finish_time - $duration_guess;
                                }
                            ?>
                            <div class="queue-item current" data-building-internal-name="<?= htmlspecialchars($upgrade['internal_name']) ?>">
                                <div class="item-header">
                                    <div class="item-title"><?= htmlspecialchars($upgrade['name']) ?></div>
                                    <div class="item-meta">Level <?= (int)$upgrade['queue_level_after'] ?></div>
                                </div>
                                <div class="item-progress" <?php if ($finish_time): ?>data-ends-at="<?= (int)$finish_time ?>"<?php endif; ?> <?php if ($start_time): ?>data-start-time="<?= (int)$start_time ?>"<?php endif; ?>>
                                    <div class="progress-bar">
                                        <div class="progress-fill"></div>
                                    </div>
                                    <?php if ($finish_time): ?>
                                        <div class="progress-time" data-ends-at="<?= (int)$finish_time ?>" <?php if ($start_time): ?>data-start-time="<?= (int)$start_time ?>"<?php endif; ?>><?= getRemainingTimeText((int)$finish_time) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="queue-empty">No active construction. Queue a task in the town hall.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel activity-card">
                <div class="panel-header"><h3>Activity</h3></div>
                <div class="activity-feed">
                    <?php if (!empty($message)): ?>
                        <?= $message ?>
                    <?php else: ?>
                        <p class="muted">All clear. Build or attack to see updates.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel quick-links">
                <div class="panel-header"><h3>Shortcuts</h3></div>
                <div class="chip-row">
                    <a class="action-chip" href="/messages/reports.php"><i class="fas fa-scroll"></i> Reports</a>
                    <a class="action-chip" href="/messages/messages.php"><i class="fas fa-envelope"></i> Messages</a>
                    <a class="action-chip" href="/player/ranking.php"><i class="fas fa-trophy"></i> Rankings</a>
                    <a class="action-chip" href="/help.php"><i class="fas fa-info-circle"></i> Help</a>
                    <a class="action-chip" href="/game/game_wap.php"><i class="fas fa-mobile-alt"></i> WAP Mode</a>
                </div>
            </div>
        </aside>
    </div>

    <section class="buildings-section">
        <div class="section-header">
            <div>
                <p class="eyebrow">Infrastructure</p>
                <h2>Resource buildings</h2>
            </div>
        </div>
        <div class="buildings-list">
            <?php foreach ($resource_buildings_data as $building) render_building_item($building, $village, $buildingManager, $village_id); ?>
        </div>

        <div class="section-header">
            <div>
                <p class="eyebrow">City & military</p>
                <h2>Town and military buildings</h2>
            </div>
        </div>
        <div class="buildings-list">
            <?php foreach ($other_buildings_data as $building) render_building_item($building, $village, $buildingManager, $village_id); ?>
        </div>
    </section>
    </main>
</div>

<div id="popup-overlay" class="popup-overlay"></div>
<div id="building-action-popup" class="popup-container">
    <div class="popup-content">
        <span class="close-button">&times;</span>
        <header class="popup-header">
            <div>
                <p class="eyebrow">Building</p>
                <h3 id="popup-building-name">Loading...</h3>
                <p class="muted" id="popup-building-description"></p>
            </div>
            <div class="level-chip">Level <span id="popup-current-level">-</span></div>
        </header>

        <div id="building-details-content">
            <div class="popup-grid">
                <div class="stat-card" id="popup-production-info" style="display:none;"></div>
                <div class="stat-card" id="popup-capacity-info" style="display:none;"></div>
            </div>

            <div id="building-upgrade-section" class="upgrade-section">
                <div class="upgrade-row">
                    <div>
                        <p class="upgrade-label">Next level <span id="popup-next-level"></span></p>
                        <div id="popup-upgrade-costs" class="cost-row"></div>
                        <div id="popup-upgrade-time" class="muted"></div>
                    </div>
                    <button class="btn btn-primary" id="popup-upgrade-button" style="display:none;">Upgrade</button>
                </div>
                <div id="popup-requirements"></div>
                <p class="error-message" id="popup-upgrade-reason" style="display:none;"></p>
            </div>
        </div>

        <div id="popup-action-content" class="popup-details" style="display:none;"></div>
    </div>
</div>

<!-- Scripts -->
<script src="/js/buildings.js?v1" defer></script>
<script src="/js/units.js" defer></script>
<script src="/js/research.js" defer></script>
<script src="/js/market.js" defer></script>
<script src="/js/rally_point.js" defer></script>
<script src="/js/main_building.js" defer></script>
<script src="/js/noble.js" defer></script>
<script src="/js/mint.js" defer></script>
<script src="/js/info_panel.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const hud = document.getElementById('hud-resources');
    if (!hud) return;

    const updateHud = (resources) => {
        if (!resources) return;
        ['wood', 'clay', 'iron', 'population'].forEach((type) => {
            const pill = hud.querySelector(`[data-resource="${type}"]`);
            if (!pill) return;
            const amountEl = pill.querySelector('[data-field="amount"]');
            const capacityEl = pill.querySelector('[data-field="capacity"]');
            const productionEl = pill.querySelector('.pill-sub');
            const resData = resources[type];
            if (amountEl && resData && typeof resData.amount !== 'undefined') {
                amountEl.textContent = formatNumber(Math.floor(resData.amount));
            }
            if (capacityEl && resData && typeof resData.capacity !== 'undefined') {
                capacityEl.textContent = formatNumber(Math.floor(resData.capacity));
            }
            if (productionEl && resData && typeof resData.production !== 'undefined') {
                productionEl.textContent = `+${formatNumber(Math.floor(resData.production))}/h`;
            }
        });
    };

    document.addEventListener('resource:update', (event) => {
        updateHud(event.detail.resources);
    });

    const openTownHallBtn = document.getElementById('open-town-hall-button');
    if (openTownHallBtn) {
        openTownHallBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            const vid = openTownHallBtn.dataset.villageId || window.currentVillageId;
            if (typeof fetchAndRenderMainBuildingPanel === 'function') {
                fetchAndRenderMainBuildingPanel(vid, 'main_building');
            } else if (window.toastManager) {
                window.toastManager.showToast('Town hall panel unavailable.', 'error');
            }
        });
    }

    if (window.resourceUpdater && window.resourceUpdater.resources) {
        updateHud(window.resourceUpdater.resources);
    }
});
</script>

<?php require '../footer.php'; ?>
