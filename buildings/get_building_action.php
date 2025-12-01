<?php
declare(strict_types=1);

require '../init.php';
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/ResearchManager.php';

/**
 * Send a JSON error response and exit.
 */
function sendJsonError(string $message): void
{
    ob_clean();
    echo json_encode(['status' => 'error', 'error' => $message]);
    exit();
}

/**
 * Build payload for the Main Building action panel.
 */
function buildMainBuildingPayload(mysqli|SQLiteAdapter $conn, int $villageId, int $userId, int $mainBuildingLevel, array $villageData): array
{
    // Village basics
    $population = (int)($villageData['population'] ?? 0);
    $villageName = $villageData['name'] ?? 'Village';

    // Player village count
    $villagesCount = 1;
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM villages WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $villagesCount = (int)($result['cnt'] ?? 1);
        $stmt->close();
    }

    // Buildings list for quick overview
    $buildingsData = [];
    $stmtBuildings = $conn->prepare("
        SELECT vb.id, vb.level, bt.name, bt.internal_name, bt.max_level
        FROM village_buildings vb
        JOIN building_types bt ON vb.building_type_id = bt.id
        WHERE vb.village_id = ?
        ORDER BY bt.id
    ");
    if ($stmtBuildings) {
        $stmtBuildings->bind_param("i", $villageId);
        $stmtBuildings->execute();
        $result = $stmtBuildings->get_result();
        while ($row = $result->fetch_assoc()) {
            $buildingsData[] = [
                'id' => (int)$row['id'],
                'level' => (int)$row['level'],
                'name' => $row['name'],
                'internal_name' => $row['internal_name'],
                'max_level' => (int)$row['max_level'],
            ];
        }
        $stmtBuildings->close();
    }

    return [
        'village_name' => $villageName,
        'main_building_level' => $mainBuildingLevel,
        'population' => $population,
        'villages_count' => $villagesCount,
        'buildings_list' => $buildingsData,
        'resources_capacity' => [
            'wood' => (int)($villageData['wood'] ?? 0),
            'clay' => (int)($villageData['clay'] ?? 0),
            'iron' => (int)($villageData['iron'] ?? 0),
            'population' => $population,
            'warehouse_capacity' => (int)($villageData['warehouse_capacity'] ?? 0),
            'farm_capacity' => (int)($villageData['farm_capacity'] ?? 0),
        ],
    ];
}

/**
 * Build payload for research buildings (smithy / academy).
 */
function buildResearchPayload(
    mysqli|SQLiteAdapter $conn,
    ResearchManager $researchManager,
    string $buildingInternalName,
    array $buildingDetails,
    int $villageId,
    array $villageData
): array {
    $buildingLevel = (int)($buildingDetails['level'] ?? 0);
    $buildingName = $buildingDetails['name'] ?? ucfirst(str_replace('_', ' ', $buildingInternalName));

    $researchTypes = $researchManager->getResearchTypesForBuilding($buildingInternalName);
    $villageResearchLevels = $researchManager->getVillageResearchLevels($villageId);
    $researchQueue = $researchManager->getResearchQueue($villageId);

    $currentResearchIds = [];
    foreach ($researchQueue as $queueItem) {
        $currentResearchIds[$queueItem['research_type_id']] = true;
    }

    $availableResearch = [];
    foreach ($researchTypes as $research) {
        $researchId = (int)$research['id'];
        $internal = $research['internal_name'];
        $currentLevel = $villageResearchLevels[$internal] ?? 0;
        $nextLevel = $currentLevel + 1;

        $isAtMax = $currentLevel >= (int)$research['max_level'];
        $isInProgress = isset($currentResearchIds[$researchId]);
        $isAvailable = $buildingLevel >= (int)$research['required_building_level'];
        $disableReason = '';

        // Check prerequisite research, if any
        if ($research['prerequisite_research_id']) {
            $prereq = $researchManager->getResearchTypeById((int)$research['prerequisite_research_id']);
            if ($prereq) {
                $requiredLevel = (int)$research['prerequisite_research_level'];
                $currentPrereqLevel = $villageResearchLevels[$prereq['internal_name']] ?? 0;
                if ($currentPrereqLevel < $requiredLevel) {
                    $isAvailable = false;
                    $disableReason = 'Requires ' . $prereq['name'] . ' level ' . $requiredLevel;
                }
            }
        }

        if (!$isAvailable && !$disableReason) {
            $disableReason = 'Required ' . $buildingName . ' level ' . $research['required_building_level'];
        }

        $cost = null;
        $timeSeconds = null;
        if (!$isAtMax && !$isInProgress && $isAvailable) {
            $cost = $researchManager->getResearchCost($researchId, $nextLevel);
            $timeSeconds = $researchManager->calculateResearchTime($researchId, $nextLevel, $buildingLevel);
        }

        $availableResearch[] = [
            'id' => $researchId,
            'internal_name' => $internal,
            'name' => $research['name'],
            'description' => $research['description'],
            'current_level' => $currentLevel,
            'max_level' => (int)$research['max_level'],
            'required_level' => (int)$research['required_building_level'],
            'is_available' => $isAvailable,
            'is_in_progress' => $isInProgress,
            'is_at_max_level' => $isAtMax,
            'disable_reason' => $disableReason,
            'cost' => $cost,
            'time_seconds' => $timeSeconds,
        ];
    }

    $researchQueueData = [];
    foreach ($researchQueue as $queue) {
        $finishTs = strtotime($queue['ends_at']);
        $remaining = $queue['remaining_time'] ?? max(0, $finishTs - time());
        $startTs = $queue['started_at'] ?? ($finishTs - $remaining);

        $researchQueueData[] = [
            'id' => (int)$queue['id'],
            'research_type_id' => (int)$queue['research_type_id'],
            'research_name' => $queue['research_name'],
            'research_internal_name' => $queue['research_internal_name'],
            'building_type' => $queue['building_type'],
            'level_after' => (int)$queue['level_after'],
            'finish_at' => $finishTs,
            'started_at' => $startTs,
            'time_remaining' => $remaining,
        ];
    }

    return [
        'building_name' => $buildingName,
        'building_level' => $buildingLevel,
        'available_research' => $availableResearch,
        'research_queue' => $researchQueueData,
        'current_village_resources' => [
            'wood' => (int)($villageData['wood'] ?? 0),
            'clay' => (int)($villageData['clay'] ?? 0),
            'iron' => (int)($villageData['iron'] ?? 0),
        ],
    ];
}

/**
 * Build payload for the market panel (trade).
 */
function buildMarketPayload(mysqli|SQLiteAdapter $conn, array $buildingDetails, int $villageId, array $villageData): array
{
    $buildingLevel = (int)($buildingDetails['level'] ?? 0);
    $tradersCapacity = 3 + (int)floor($buildingLevel * 0.7);

    // Active transports
    $activeTrades = [];
    $stmt = $conn->prepare("
        SELECT * FROM trade_routes
        WHERE (source_village_id = ? OR target_village_id = ?)
        AND arrival_time > NOW()
        ORDER BY arrival_time ASC
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $villageId, $villageId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $isOutgoing = ((int)$row['source_village_id'] === $villageId);
            $direction = $isOutgoing ? 'outgoing' : 'incoming';

            // Fetch other village info
            $otherVillageId = $isOutgoing ? $row['target_village_id'] : $row['source_village_id'];
            $otherVillageStmt = $conn->prepare("
                SELECT v.name, v.x_coord, v.y_coord, u.username
                FROM villages v
                JOIN users u ON v.user_id = u.id
                WHERE v.id = ?
            ");
            if ($otherVillageStmt) {
                $otherVillageStmt->bind_param("i", $otherVillageId);
                $otherVillageStmt->execute();
                $otherVillage = $otherVillageStmt->get_result()->fetch_assoc();
                $otherVillageStmt->close();
            } else {
                $otherVillage = null;
            }

            $villageName = $otherVillage['name'] ?? 'Unknown village';
            $coords = ($otherVillage['x_coord'] ?? '?') . '|' . ($otherVillage['y_coord'] ?? '?');
            $playerName = $otherVillage['username'] ?? 'Unknown player';

            $arrivalTime = strtotime($row['arrival_time']);
            $remainingTime = max(0, $arrivalTime - time());

            $activeTrades[] = [
                'id' => (int)$row['id'],
                'direction' => $direction,
                'wood' => (int)$row['wood'],
                'clay' => (int)$row['clay'],
                'iron' => (int)$row['iron'],
                'village_name' => $villageName,
                'coords' => $coords,
                'player_name' => $playerName,
                'arrival_time' => $row['arrival_time'],
                'remaining_time' => $remainingTime,
                'traders_count' => (int)$row['traders_count'],
            ];
        }
        $stmt->close();
    }

    $tradersInUse = 0;
    foreach ($activeTrades as $trade) {
        if ($trade['direction'] === 'outgoing') {
            $tradersInUse += $trade['traders_count'];
        }
    }
    $availableTraders = max(0, $tradersCapacity - $tradersInUse);

    // Render HTML for the panel
    ob_start();
    ?>
    <div class="building-actions">
        <h3><?= htmlspecialchars($buildingDetails['name'] ?? 'Market') ?> - trading</h3>
        <p>Trade resources with other players here.</p>

        <div class="market-info">
            <p>Available traders: <strong><?= $availableTraders ?>/<?= $tradersCapacity ?></strong></p>
        </div>

        <?php if ($availableTraders > 0): ?>
            <div class="send-resources">
                <h4>Send resources</h4>
                <form action="send_resources.php" method="post" id="send-resources-form">
                    <input type="hidden" name="village_id" value="<?= $villageId ?>">

                    <div class="form-group">
                        <label for="target_coords">Target (coordinates x|y):</label>
                        <input type="text" id="target_coords" name="target_coords" placeholder="500|500" pattern="\d+\|\d+" required>
                    </div>

                    <div class="resource-inputs">
                        <div class="resource-input">
                            <label for="wood">Wood:</label>
                            <input type="number" id="wood" name="wood" min="0" value="0" required>
                        </div>

                        <div class="resource-input">
                            <label for="clay">Clay:</label>
                            <input type="number" id="clay" name="clay" min="0" value="0" required>
                        </div>

                        <div class="resource-input">
                            <label for="iron">Iron:</label>
                            <input type="number" id="iron" name="iron" min="0" value="0" required>
                        </div>
                    </div>

                    <div class="current-resources">
                        <p>Available resources:
                            Wood: <strong><?= floor($villageData['wood'] ?? 0) ?></strong>,
                            Clay: <strong><?= floor($villageData['clay'] ?? 0) ?></strong>,
                            Iron: <strong><?= floor($villageData['iron'] ?? 0) ?></strong>
                        </p>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="send-button">Send resources</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="no-traders">
                <p>You have no traders available to send resources. Please wait for them to return.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($activeTrades)): ?>
            <div class="active-trades">
                <h4>Active transports</h4>
                <table class="trades-table">
                    <tr><th>Direction</th><th>Resources</th><th>Target/Source</th><th>Arrival time</th></tr>
                    <?php foreach ($activeTrades as $trade): ?>
                        <tr>
                            <td><?= $trade['direction'] === 'outgoing' ? 'Outgoing' : 'Incoming' ?></td>
                            <td>
                                Wood: <?= $trade['wood'] ?><br>
                                Clay: <?= $trade['clay'] ?><br>
                                Iron: <?= $trade['iron'] ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($trade['village_name']) ?> (<?= $trade['coords'] ?>)<br>
                                Player: <?= htmlspecialchars($trade['player_name']) ?>
                            </td>
                            <td class="trade-timer" data-ends-at="<?= htmlspecialchars($trade['arrival_time']) ?>">
                                <?= gmdate("H:i:s", $trade['remaining_time']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php else: ?>
            <div class="no-trades">
                <p>You have no active transports.</p>
            </div>
        <?php endif; ?>

        <div class="market-offers">
            <h4>Trade offers</h4>
            <p>Trade offers functionality will be added in a future update.</p>
        </div>
    </div>
    <?php
    $content = ob_get_clean();

    return [
        'building_name' => $buildingDetails['name'] ?? 'Market',
        'building_level' => $buildingLevel,
        'available_traders' => $availableTraders,
        'traders_capacity' => $tradersCapacity,
        'active_trades' => $activeTrades,
        'additional_info_html' => $content,
    ];
}

try {
    // Optional compatibility: quick message for deprecated queue action
    if (isset($_GET['action']) && $_GET['action'] === 'queue') {
        header('Content-Type: text/html');
        ob_clean();
        echo '<p class="queue-empty">Build queue endpoint moved to /ajax/buildings/get_queue.php.</p>';
        exit();
    }

    if (!isset($_SESSION['user_id'])) {
        sendJsonError('User not logged in.');
    }
    $userId = (int)$_SESSION['user_id'];

    $villageId = isset($_GET['village_id']) ? (int)$_GET['village_id'] : 0;
    $internalName = trim($_GET['building_internal_name'] ?? $_GET['building_type'] ?? '');
    $buildingId = isset($_GET['building_id']) ? (int)$_GET['building_id'] : 0;

    $villageManager = new VillageManager($conn);
    if ($villageId <= 0) {
        $firstVillage = $villageManager->getFirstVillage($userId);
        if (!$firstVillage) {
            sendJsonError('No village found for this user.');
        }
        $villageId = (int)$firstVillage['id'];
    }

    $villageData = $villageManager->getVillageInfo($villageId);
    if (!$villageData || (int)$villageData['user_id'] !== $userId) {
        sendJsonError('No access to this village.');
    }

    // Derive internal name from building ID when needed
    if ($internalName === '' && $buildingId > 0) {
        $stmt = $conn->prepare("
            SELECT bt.internal_name
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.id = ? AND vb.village_id = ?
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $buildingId, $villageId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $internalName = $row['internal_name'] ?? '';
            $stmt->close();
        }
    }

    // Normalise known aliases
    $aliasMap = [
        'wood_production' => 'sawmill',
        'forge' => 'smithy',
    ];
    if (isset($aliasMap[$internalName])) {
        $internalName = $aliasMap[$internalName];
    }

    if ($internalName === '') {
        sendJsonError('Missing building type.');
    }

    $buildingConfigManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfigManager);

    // Fetch building details; allow level 0 (not yet built)
    $buildingDetails = $buildingManager->getVillageBuilding($villageId, $internalName);
    if (!$buildingDetails) {
        $config = $buildingConfigManager->getBuildingConfig($internalName);
        if (!$config) {
            sendJsonError('Building not found in your village.');
        }
        $buildingDetails = [
            'internal_name' => $internalName,
            'name' => $config['name'] ?? $internalName,
            'level' => 0,
            'building_type_id' => $config['id'] ?? null,
        ];
    }

    $buildingLevel = (int)($buildingDetails['level'] ?? 0);
    $buildingName = $buildingDetails['name'] ?? ($buildingConfigManager->getBuildingConfig($internalName)['name'] ?? $internalName);

    $actionType = 'info';
    $data = [
        'building_internal_name' => $internalName,
        'building_name' => $buildingName,
        'building_level' => $buildingLevel,
    ];

    switch ($internalName) {
        case 'main_building':
            $actionType = 'manage_village';
            $data = buildMainBuildingPayload($conn, $villageId, $userId, $buildingLevel, $villageData);
            break;

        case 'smithy':
        case 'academy':
            $actionType = ($internalName === 'academy') ? 'research_advanced' : 'research';
            $researchManager = new ResearchManager($conn);
            $data = buildResearchPayload($conn, $researchManager, $internalName, $buildingDetails, $villageId, $villageData);
            break;

        case 'market':
            $actionType = 'trade';
            $data = buildMarketPayload($conn, $buildingDetails, $villageId, $villageData);
            break;

        case 'warehouse':
            $actionType = 'info';
            $capacity = $buildingManager->getWarehouseCapacityByLevel($buildingLevel);
            $config = $buildingConfigManager->getBuildingConfig('warehouse');
            $description = $config['description'] ?? 'Stores resources.';
            $data['additional_info_html'] = '<p>Description: ' . htmlspecialchars($description) . '</p>'
                . '<p>Warehouse capacity: ' . $capacity . '</p>';
            break;

        case 'sawmill':
        case 'clay_pit':
        case 'iron_mine':
            $actionType = 'info_production';
            $productionPerHour = $buildingManager->getHourlyProduction($internalName, $buildingLevel);
            $config = $buildingConfigManager->getBuildingConfig($internalName);
            $description = $config['description'] ?? 'Produces resources.';
            $resourceName = ($internalName === 'sawmill') ? 'Wood' : ($internalName === 'clay_pit' ? 'Clay' : 'Iron');
            $data['additional_info_html'] = '<p>Description: ' . htmlspecialchars($description) . '</p>'
                . '<p>Production: ' . $productionPerHour . ' per hour of ' . $resourceName . '</p>';
            break;

        default:
            $config = $buildingConfigManager->getBuildingConfig($internalName);
            $description = $config['description'] ?? 'No additional actions available for this building yet.';
            $data['additional_info_html'] = '<p>Description: ' . htmlspecialchars($description) . '</p>';
            $actionType = 'info';
            break;
    }

    $response = [
        'status' => 'success',
        'action_type' => $actionType,
        'data' => $data,
    ];
    if (isset($data['additional_info_html'])) {
        $response['html'] = $data['additional_info_html'];
    }

    ob_clean();
    echo json_encode($response);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'error' => 'A server error occurred: ' . $e->getMessage()]);
    error_log("Error in get_building_action.php: " . $e->getMessage());
}
