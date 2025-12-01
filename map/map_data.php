<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../lib/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$centerX = isset($_GET['x']) ? (int)$_GET['x'] : 0;
$centerY = isset($_GET['y']) ? (int)$_GET['y'] : 0;
$size = isset($_GET['size']) ? max(7, min(31, (int)$_GET['size'])) : 15;

$radius = (int)floor(($size - 1) / 2);
$minX = $centerX - $radius;
$maxX = $centerX + $radius;
$minY = $centerY - $radius;
$maxY = $centerY + $radius;
$worldId = CURRENT_WORLD_ID;

// Fetch villages with owner info inside the viewport
$stmt = $conn->prepare("
    SELECT v.id, v.x_coord, v.y_coord, v.name, v.user_id, v.points, u.username, u.ally_id
    FROM villages v
    LEFT JOIN users u ON v.user_id = u.id
    WHERE v.world_id = ? 
      AND v.x_coord BETWEEN ? AND ?
      AND v.y_coord BETWEEN ? AND ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare query for villages.']);
    exit;
}

$stmt->bind_param('iiiii', $worldId, $minX, $maxX, $minY, $maxY);
$stmt->execute();
$result = $stmt->get_result();

$villages = [];
$players = [];
while ($row = $result->fetch_assoc()) {
    $villageOwnerId = isset($row['user_id']) ? (int)$row['user_id'] : null;
    $ownerType = ($villageOwnerId === null || $villageOwnerId === -1) ? 'barbarian' : 'player';
    $isOwn = $villageOwnerId === $user_id;

    $villages[] = [
        'id' => (int)$row['id'],
        'x' => (int)$row['x_coord'],
        'y' => (int)$row['y_coord'],
        'name' => $row['name'],
        'user_id' => $villageOwnerId,
        'owner' => $row['username'] ?? null,
        'ally_id' => $row['ally_id'] ?? null,
        'points' => (int)$row['points'],
        'type' => $ownerType,
        'is_own' => $isOwn,
        'continent' => getContinent((int)$row['x_coord'], (int)$row['y_coord']),
        // Reserved and movement flags can be filled once those systems exist.
        'reserved_by' => null,
        'reserved_team' => null,
        'movements' => []
    ];

    if ($villageOwnerId && !isset($players[$villageOwnerId])) {
        $players[$villageOwnerId] = [
            'id' => $villageOwnerId,
            'username' => $row['username'],
            'points' => (int)$row['points'],
            'ally_id' => $row['ally_id'] ?? null
        ];
    }
}
$stmt->close();

// Index villages by id for movement enrichment
$villagesById = [];
foreach ($villages as $idx => $v) {
    $villagesById[$v['id']] = $idx;
}

// Fetch active movements (attacks/support/return) intersecting the viewport
$movementsStmt = $conn->prepare("
    SELECT 
        a.id,
        a.source_village_id,
        a.target_village_id,
        a.attack_type,
        a.arrival_time,
        a.start_time,
        sv.x_coord AS source_x,
        sv.y_coord AS source_y,
        tv.x_coord AS target_x,
        tv.y_coord AS target_y
    FROM attacks a
    JOIN villages sv ON sv.id = a.source_village_id
    JOIN villages tv ON tv.id = a.target_village_id
    WHERE a.is_completed = 0 
      AND a.is_canceled = 0 
      AND a.arrival_time > NOW()
      AND (
        (sv.x_coord BETWEEN ? AND ? AND sv.y_coord BETWEEN ? AND ?) OR
        (tv.x_coord BETWEEN ? AND ? AND tv.y_coord BETWEEN ? AND ?)
      )
");
if ($movementsStmt) {
    $movementsStmt->bind_param(
        'iiiiiiii',
        $minX, $maxX, $minY, $maxY,
        $minX, $maxX, $minY, $maxY
    );
    $movementsStmt->execute();
    $movementsRes = $movementsStmt->get_result();
    while ($move = $movementsRes->fetch_assoc()) {
        $sourceId = (int)$move['source_village_id'];
        $targetId = (int)$move['target_village_id'];
        $arrivalTs = strtotime($move['arrival_time']);

        if (isset($villagesById[$sourceId])) {
            $villages[$villagesById[$sourceId]]['movements'][] = [
                'type' => $move['attack_type'] === 'support' ? 'support' : 'attack',
                'arrival' => $arrivalTs,
                'target' => ['x' => (int)$move['target_x'], 'y' => (int)$move['target_y']]
            ];
        }

        if (isset($villagesById[$targetId])) {
            $villages[$villagesById[$targetId]]['movements'][] = [
                'type' => $move['attack_type'] === 'support' ? 'support_in' : 'incoming',
                'arrival' => $arrivalTs,
                'source' => ['x' => (int)$move['source_x'], 'y' => (int)$move['source_y']]
            ];
        }
    }
    $movementsStmt->close();
}

// Fetch tribes/alliances if the table exists (optional)
$allies = [];
if (dbTableExists($conn, 'tribes')) {
    $allyQuery = 'SELECT id, name, points, tag FROM tribes';
    if (dbColumnExists($conn, 'tribes', 'world_id')) {
        $allyQuery .= ' WHERE world_id = ' . (int)$worldId;
    }

$allyResult = $conn->query($allyQuery);
if ($allyResult) {
    while ($allyRow = $allyResult->fetch_assoc()) {
        $allies[] = [
            'id' => (int)$allyRow['id'],
                'name' => $allyRow['name'],
                'points' => (int)($allyRow['points'] ?? 0),
                'short' => $allyRow['tag'] ?? ''
            ];
        }
        $allyResult->close();
    }
}

$boundsStmt = $conn->prepare("SELECT MIN(x_coord) AS min_x, MAX(x_coord) AS max_x, MIN(y_coord) AS min_y, MAX(y_coord) AS max_y FROM villages WHERE world_id = ?");
$worldBounds = null;
if ($boundsStmt) {
    $boundsStmt->bind_param('i', $worldId);
    $boundsStmt->execute();
    $boundsRow = $boundsStmt->get_result()->fetch_assoc();
    if ($boundsRow && $boundsRow['min_x'] !== null && $boundsRow['max_x'] !== null) {
        $worldBounds = [
            'min_x' => (int)$boundsRow['min_x'],
            'max_x' => (int)$boundsRow['max_x'],
            'min_y' => (int)$boundsRow['min_y'],
            'max_y' => (int)$boundsRow['max_y']
        ];
    }
    $boundsStmt->close();
}

echo json_encode([
    'center' => ['x' => $centerX, 'y' => $centerY],
    'size' => $size,
    'villages' => $villages,
    'players' => array_values($players),
    'allies' => $allies,
    'tribes' => $allies,
    'world_bounds' => $worldBounds
]);
