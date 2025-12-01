<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../lib/functions.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';

header('Content-Type: application/json; charset=utf-8');
// Encourage client-side caching; precise values finalized below after we compute freshness.
header('Cache-Control: public, max-age=15, must-revalidate');
$metricsStart = microtime(true);
$mapMetricLog = __DIR__ . '/../logs/map_metrics.log';
$mapMetricAlertLog = __DIR__ . '/../logs/map_metric_alerts.log';
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

function logMapMetric(int $status, int $bytes, string $etag, int $userId, int $centerX, int $centerY, int $size, float $durationMs, string $cacheStatus, string $logFile): void
{
    $line = sprintf(
        "[%s] status=%d cache=%s bytes=%d dur_ms=%.2f etag=%s user=%d center=(%d,%d) size=%d\n",
        date('Y-m-d H:i:s'),
        $status,
        $cacheStatus,
        $bytes,
        $durationMs,
        $etag,
        $userId,
        $centerX,
        $centerY,
        $size
    );
    @file_put_contents($logFile, $line, FILE_APPEND);
}

function maybeAlertMapMetric(int $status, int $bytes, float $durationMs, string $cacheStatus, int $userId, int $centerX, int $centerY, int $size, string $alertLog): void
{
    $durationThreshold = defined('MAP_LATENCY_ALERT_MS') ? (int)MAP_LATENCY_ALERT_MS : 500;
    $payloadThreshold = defined('MAP_PAYLOAD_ALERT_BYTES') ? (int)MAP_PAYLOAD_ALERT_BYTES : 450000;
    if ($durationMs < $durationThreshold && $bytes < $payloadThreshold) {
        return;
    }
    $line = sprintf(
        "[%s] ALERT status=%d cache=%s bytes=%d dur_ms=%.2f user=%d center=(%d,%d) size=%d\n",
        date('Y-m-d H:i:s'),
        $status,
        $cacheStatus,
        $bytes,
        $durationMs,
        $userId,
        $centerX,
        $centerY,
        $size
    );
    @file_put_contents($alertLog, $line, FILE_APPEND);
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$userAllyId = null;
$allyStmt = $conn->prepare("SELECT ally_id FROM users WHERE id = ? LIMIT 1");
if ($allyStmt) {
    $allyStmt->bind_param("i", $user_id);
    $allyStmt->execute();
    $allyRow = $allyStmt->get_result()->fetch_assoc();
    $allyStmt->close();
    $userAllyId = isset($allyRow['ally_id']) ? (int)$allyRow['ally_id'] : null;
}
$rateWindow = 10; // seconds
$rateMax = 15; // max requests per window
$now = microtime(true);
$_SESSION['map_rate'] = isset($_SESSION['map_rate']) && is_array($_SESSION['map_rate']) ? $_SESSION['map_rate'] : [];
$_SESSION['map_rate'] = array_values(array_filter($_SESSION['map_rate'], function ($ts) use ($now, $rateWindow) {
    return ($ts + $rateWindow) > $now;
}));
if (count($_SESSION['map_rate']) >= $rateMax) {
    $retryAfter = max(1, (int)ceil(($_SESSION['map_rate'][0] + $rateWindow) - $now));
    header('Retry-After: ' . $retryAfter);
    header('Cache-Control: no-store');
    http_response_code(429);
    echo json_encode([
        'error' => 'Rate limited',
        'code' => 'ERR_RATE_LIMITED',
        'retry_after' => $retryAfter
    ]);
    $durationMs = (microtime(true) - $metricsStart) * 1000;
    logMapMetric(429, 0, 'n/a', (int)($_SESSION['user_id'] ?? 0), 0, 0, 0, $durationMs, 'rate_limit', $mapMetricLog);
    maybeAlertMapMetric(429, 0, $durationMs, 'rate_limit', (int)($_SESSION['user_id'] ?? 0), 0, 0, 0, $mapMetricAlertLog);
    exit;
}
$_SESSION['map_rate'][] = $now;

$worldSize = defined('WORLD_SIZE') ? (int)WORLD_SIZE : 1000;
$wm = new WorldManager($conn);
$worldSettings = $wm->getSettings(CURRENT_WORLD_ID);
$mapFeatures = [
    'batching' => $wm->isMapBatchingEnabled(CURRENT_WORLD_ID),
    'clustering' => $wm->isMapClusteringEnabled(CURRENT_WORLD_ID),
    'delta' => $wm->isMapDeltaEnabled(CURRENT_WORLD_ID),
    'fallback' => $wm->isMapFallbackEnabled(CURRENT_WORLD_ID),
    'pagination' => $wm->isMapPaginationEnabled(CURRENT_WORLD_ID),
];

// Handle conditional requests for data freshness without excessive payloads.
$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
$ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
$etagBaseParts = [$user_id, $worldSize, json_encode($mapFeatures)];
$freshnessHeaders = [];

$centerX = isset($_GET['x']) ? (int)$_GET['x'] : 0;
$centerY = isset($_GET['y']) ? (int)$_GET['y'] : 0;
$size = isset($_GET['size']) ? max(7, min(31, (int)$_GET['size'])) : 15;
$lowPerfMode = !empty($_GET['lowperf']);
$suppressCommands = !empty($_GET['suppress_commands']);

$radius = (int)floor(($size - 1) / 2);
$centerX = max(0, min($worldSize - 1, $centerX));
$centerY = max(0, min($worldSize - 1, $centerY));

$minX = max(0, $centerX - $radius);
$maxX = min($worldSize - 1, $centerX + $radius);
$minY = max(0, $centerY - $radius);
$maxY = min($worldSize - 1, $centerY + $radius);
$worldId = CURRENT_WORLD_ID;
$movementsLimit = defined('MAP_MOVEMENTS_LIMIT') ? (int)MAP_MOVEMENTS_LIMIT : 500;
$movementsTruncated = false;

$lastModifiedTs = 0;
function isUnderBeginnerProtection(array $userRow): bool
{
    $maxDays = defined('NEWBIE_PROTECTION_DAYS_MAX') ? NEWBIE_PROTECTION_DAYS_MAX : 7;
    $pointsCap = defined('NEWBIE_PROTECTION_POINTS_CAP') ? NEWBIE_PROTECTION_POINTS_CAP : 200;

    if (isset($userRow['is_protected']) && (int)$userRow['is_protected'] === 0) {
        return false;
    }
    if (($userRow['points'] ?? 0) > $pointsCap) {
        return false;
    }
    if (empty($userRow['created_at'])) {
        return false;
    }
    $createdAt = new DateTimeImmutable($userRow['created_at']);
    $days = (int)$createdAt->diff(new DateTimeImmutable('now'))->format('%a');
    return $days < $maxDays;
}

function computeActivityBucket(?int $lastActivityTs): string
{
    if (!$lastActivityTs) return 'unknown';
    $diff = time() - $lastActivityTs;
    if ($diff <= 3600) return '1h';
    if ($diff <= 6 * 3600) return '6h';
    if ($diff <= 24 * 3600) return '24h';
    if ($diff <= 72 * 3600) return '72h';
    return 'stale';
}

/**
 * Batch movements into 1-second buckets per village/direction to reduce payload size.
 */
function addMovementBatch(array &$batches, array &$attackMap, int $attackId, int $villageId, string $direction, int $arrivalTs, array $meta = []): void
{
    $bucket = $arrivalTs > 0 ? $arrivalTs : time();
    if (!isset($batches[$villageId])) {
        $batches[$villageId] = [];
    }
    $key = $bucket . ':' . $direction;
    if (!isset($batches[$villageId][$key])) {
        $batches[$villageId][$key] = [
            'village_id' => $villageId,
            'direction' => $direction,
            'bucket' => $bucket,
            'count' => 0,
            'has_noble' => false,
            'sample_coords' => []
        ];
    }
    $batches[$villageId][$key]['count']++;
    if (!empty($meta['coord']) && is_array($meta['coord'])) {
        $coordKey = ($meta['coord']['x'] ?? 0) . ':' . ($meta['coord']['y'] ?? 0);
        if (!isset($batches[$villageId][$key]['sample_coords'][$coordKey]) && count($batches[$villageId][$key]['sample_coords']) < 3) {
            $batches[$villageId][$key]['sample_coords'][$coordKey] = [
                'x' => (int)($meta['coord']['x'] ?? 0),
                'y' => (int)($meta['coord']['y'] ?? 0)
            ];
        }
    }
    $attackMap[$attackId][] = [$villageId, $key];
}

// Fetch villages with owner info inside the viewport
$stmt = $conn->prepare("
    SELECT v.id, v.x_coord, v.y_coord, v.name, v.user_id, v.points, u.username, u.ally_id, u.is_protected, u.created_at, u.last_activity_at
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
    $createdAtTs = isset($row['created_at']) ? strtotime($row['created_at']) : 0;
    $lastActivityTs = isset($row['last_activity_at']) ? strtotime($row['last_activity_at']) : null;
    if ($createdAtTs > $lastModifiedTs) {
        $lastModifiedTs = $createdAtTs;
    }

    $userRow = [
        'id' => $villageOwnerId,
        'username' => $row['username'] ?? null,
        'points' => (int)$row['points'],
        'ally_id' => $row['ally_id'] ?? null,
        'is_protected' => isset($row['is_protected']) ? (int)$row['is_protected'] : 0,
        'created_at' => $row['created_at'] ?? null,
        'last_activity_at' => $row['last_activity_at'] ?? null,
    ];
    $activityBucket = computeActivityBucket($lastActivityTs);

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
        'is_protected' => $villageOwnerId > 0 ? isUnderBeginnerProtection($userRow) : false,
        'continent' => getContinent((int)$row['x_coord'], (int)$row['y_coord']),
        'activity_bucket' => $activityBucket,
        'last_activity_at' => $lastActivityTs,
        // Reserved and movement flags can be filled once those systems exist.
        'reserved_by' => null,
        'reserved_team' => null,
        'movements' => [],
        'movement_summary' => [
            'incoming' => 0,
            'outgoing' => 0,
            'support' => 0,
            'earliest' => null,
            'has_noble' => false
        ]
    ];

    if ($villageOwnerId && !isset($players[$villageOwnerId])) {
        $players[$villageOwnerId] = array_merge($userRow, [
            'protected' => $villageOwnerId > 0 ? isUnderBeginnerProtection($userRow) : false
        ]);
    }
}
$stmt->close();

// Index villages by id for movement enrichment
$villagesById = [];
foreach ($villages as $idx => $v) {
    $villagesById[$v['id']] = $idx;
}

// Track the most recent update timestamp for ETag/Last-Modified
$lastModifiedTs = max($lastModifiedTs, time());
$etagBaseParts[] = $minX . ':' . $maxX . ':' . $minY . ':' . $maxY;
$etagBaseParts[] = $lastModifiedTs;
$etag = '"' . sha1(implode('|', $etagBaseParts)) . '"';
$lastModifiedHeader = gmdate('D, d M Y H:i:s', $lastModifiedTs) . ' GMT';
$freshnessHeaders = [
    'ETag' => $etag,
    'Last-Modified' => $lastModifiedHeader,
    'Cache-Control' => 'private, max-age=15'
];

$ifModifiedSinceTs = $ifModifiedSince ? strtotime($ifModifiedSince) : null;
if (($ifNoneMatch && trim($ifNoneMatch) === $etag) || ($ifModifiedSinceTs && $ifModifiedSinceTs >= $lastModifiedTs)) {
    foreach ($freshnessHeaders as $key => $value) {
        header($key . ': ' . $value);
    }
    http_response_code(304);
    exit;
}

$movementAttackIds = [];
$movementLimitPerVillage = 50;
$movementBatches = [];
$movementBatchAttackMap = [];
if (!$lowPerfMode && !$suppressCommands) {
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
        ORDER BY a.arrival_time ASC
        LIMIT ?
    ");
    if ($movementsStmt) {
        $movementsStmt->bind_param(
            'iiiiiiiii',
            $minX, $maxX, $minY, $maxY,
            $minX, $maxX, $minY, $maxY,
            $movementsLimit + 1
        );
        $movementsStmt->execute();
        $movementsRes = $movementsStmt->get_result();
        $fetchedMoves = [];
        while ($move = $movementsRes->fetch_assoc()) {
            $fetchedMoves[] = $move;
        }
        if (count($fetchedMoves) > $movementsLimit) {
            $movementsTruncated = true;
            $fetchedMoves = array_slice($fetchedMoves, 0, $movementsLimit);
        }
        foreach ($fetchedMoves as $move) {
            $sourceId = (int)$move['source_village_id'];
            $targetId = (int)$move['target_village_id'];
            $arrivalTs = strtotime($move['arrival_time']);
            $startTs = isset($move['start_time']) ? strtotime($move['start_time']) : 0;
            if ($arrivalTs > $lastModifiedTs) {
                $lastModifiedTs = $arrivalTs;
            }
            if ($startTs > $lastModifiedTs) {
                $lastModifiedTs = $startTs;
            }

            $movementAttackIds[] = (int)$move['id'];

            if (isset($villagesById[$sourceId])) {
                $villages[$villagesById[$sourceId]]['movements'][] = [
                    'attack_id' => (int)$move['id'],
                    'type' => $move['attack_type'] === 'support' ? 'support' : 'attack',
                    'arrival' => $arrivalTs,
                    'target' => ['x' => (int)$move['target_x'], 'y' => (int)$move['target_y']]
                ];
                addMovementBatch(
                    $movementBatches,
                    $movementBatchAttackMap,
                    (int)$move['id'],
                    $sourceId,
                    $move['attack_type'] === 'support' ? 'support_out' : 'outgoing',
                    $arrivalTs,
                    ['coord' => ['x' => (int)$move['target_x'], 'y' => (int)$move['target_y']]]
                );
                $summary =& $villages[$villagesById[$sourceId]]['movement_summary'];
                if ($move['attack_type'] === 'support') {
                    $summary['support']++;
                } else {
                    $summary['outgoing']++;
                }
                if ($summary['earliest'] === null || $arrivalTs < $summary['earliest']) {
                    $summary['earliest'] = $arrivalTs;
                }
            }

            if (isset($villagesById[$targetId])) {
                $villages[$villagesById[$targetId]]['movements'][] = [
                    'attack_id' => (int)$move['id'],
                    'type' => $move['attack_type'] === 'support' ? 'support_in' : 'incoming',
                    'arrival' => $arrivalTs,
                    'source' => ['x' => (int)$move['source_x'], 'y' => (int)$move['source_y']]
                ];
                addMovementBatch(
                    $movementBatches,
                    $movementBatchAttackMap,
                    (int)$move['id'],
                    $targetId,
                    $move['attack_type'] === 'support' ? 'support_in' : 'incoming',
                    $arrivalTs,
                    ['coord' => ['x' => (int)$move['source_x'], 'y' => (int)$move['source_y']]]
                );
                $summary =& $villages[$villagesById[$targetId]]['movement_summary'];
                if ($move['attack_type'] === 'support') {
                    $summary['support']++;
                } else {
                    $summary['incoming']++;
                }
                if ($summary['earliest'] === null || $arrivalTs < $summary['earliest']) {
                    $summary['earliest'] = $arrivalTs;
                }
            }
        }
        $movementsStmt->close();
    }
}

// Flag movements that carry nobles
if (!$lowPerfMode && !empty($movementAttackIds)) {
    $placeholders = implode(',', array_fill(0, count($movementAttackIds), '?'));
    $types = str_repeat('i', count($movementAttackIds));
    $nobleSql = "
        SELECT DISTINCT au.attack_id
        FROM attack_units au
        JOIN unit_types ut ON ut.id = au.unit_type_id
        WHERE au.attack_id IN ($placeholders)
          AND LOWER(ut.internal_name) IN ('noble', 'nobleman', 'nobleman_unit')
          AND au.count > 0
    ";
    $nobleStmt = $conn->prepare($nobleSql);
    if ($nobleStmt) {
        $nobleStmt->bind_param($types, ...$movementAttackIds);
        $nobleStmt->execute();
        $res = $nobleStmt->get_result();
        $nobleAttackIds = [];
        while ($row = $res->fetch_assoc()) {
            $nobleAttackIds[(int)$row['attack_id']] = true;
        }
        $nobleStmt->close();

        if (!empty($nobleAttackIds)) {
            foreach ($villages as &$v) {
                if (empty($v['movements'])) continue;
                foreach ($v['movements'] as &$move) {
                    if (!empty($move['attack_id']) && isset($nobleAttackIds[$move['attack_id']])) {
                        $move['has_noble'] = true;
                        $v['movement_summary']['has_noble'] = true;
                    }
                }
                unset($move);
            }
            unset($v);

            // Mark batches that include nobles to help clients highlight critical lines
            foreach ($nobleAttackIds as $attackId => $flag) {
                if (empty($movementBatchAttackMap[$attackId])) {
                    continue;
                }
                foreach ($movementBatchAttackMap[$attackId] as [$villageId, $bucketKey]) {
                    if (isset($movementBatches[$villageId][$bucketKey])) {
                        $movementBatches[$villageId][$bucketKey]['has_noble'] = true;
                    }
                }
            }
        }
    }
}

// Trim movement lists per village to limit payload size
foreach ($villages as &$v) {
    if (empty($v['movements'])) {
        $v['movements_truncated'] = false;
        $v['movement_summary']['omitted'] = 0;
        continue;
    }
    usort($v['movements'], static function ($a, $b) {
        return ($a['arrival'] ?? 0) <=> ($b['arrival'] ?? 0);
    });
    $total = count($v['movements']);
    if ($total > $movementLimitPerVillage) {
        $v['movements'] = array_slice($v['movements'], 0, $movementLimitPerVillage);
        $omitted = $total - $movementLimitPerVillage;
        $v['movements_truncated'] = true;
        $v['movement_summary']['omitted'] = $omitted;
    } else {
        $v['movements_truncated'] = false;
        $v['movement_summary']['omitted'] = 0;
    }
}
unset($v);

// Normalize movement batch arrays for JSON output
foreach ($movementBatches as $vid => $batches) {
    foreach ($batches as $bucketKey => &$batch) {
        if (isset($batch['sample_coords']) && is_array($batch['sample_coords'])) {
            $batch['sample_coords'] = array_values($batch['sample_coords']);
        }
    }
    unset($batch);
    $movementBatches[$vid] = array_values($batches);
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
            'short' => $allyRow['tag'] ?? '',
            'tag' => $allyRow['tag'] ?? ''
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
        $boundsUpdatedTs = time();
        if ($boundsUpdatedTs > $lastModifiedTs) {
            $lastModifiedTs = $boundsUpdatedTs;
        }
    }
    $boundsStmt->close();
}

// Unit speed lookup (minutes per field -> fields per hour)
$unitSpeeds = [];
    $speedStmt = $conn->prepare("SELECT internal_name, speed, is_active FROM unit_types");
    if ($speedStmt) {
        $speedStmt->execute();
        $speedRes = $speedStmt->get_result();
        while ($u = $speedRes->fetch_assoc()) {
            $internal = strtolower($u['internal_name'] ?? '');
            $minutesPerField = isset($u['speed']) ? (float)$u['speed'] : null;
            if ($minutesPerField && $minutesPerField > 0) {
                $unitSpeeds[$internal] = [
                    'minutes_per_field' => $minutesPerField,
                    'fields_per_hour' => 60 / $minutesPerField,
                    'active' => (bool)($u['is_active'] ?? 1)
                ];
            }
        }
        $speedStmt->close();
    }

$movementBatchCursor = time();

$payload = [
    'map_version' => defined('MAP_API_VERSION') ? (int)MAP_API_VERSION : 1,
    'center' => ['x' => $centerX, 'y' => $centerY],
    'size' => $size,
    'villages' => $villages,
    'players' => array_values($players),
    'allies' => $allies,
    'tribes' => $allies,
    'world_bounds' => $worldBounds,
    'my_tribe_id' => $userTribeId,
    'diplomacy' => fetchTribeDiplomacy($conn, $userTribeId),
    'unit_speeds' => $unitSpeeds,
    'movements_truncated' => $movementsTruncated,
    'movements_limit' => $movementsLimit,
    'movement_batches' => $movementBatches,
    'movement_batch_cursor' => $movementBatchCursor,
    'low_perf' => $lowPerfMode,
    'map_features' => $mapFeatures
];

$json = json_encode($payload);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to encode map data']);
    exit;
}

function fetchTribeDiplomacy($conn, ?int $tribeId): array
{
    if (!$tribeId) return [];
    $relations = [];
    $stmt = $conn->prepare("SELECT target_tribe_id, status FROM tribe_diplomacy WHERE tribe_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $relations[(int)$row['target_tribe_id']] = $row['status'];
        }
        $stmt->close();
    }
    return $relations;
}

$etag = '"' . sha1($json) . '"';
$lastModifiedTs = $lastModifiedTs ?: time();
$lastModifiedHeader = gmdate('D, d M Y H:i:s', $lastModifiedTs) . ' GMT';
$ttlSeconds = 15; // keep small for active map polling
$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
$ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
$ifModifiedSinceTs = $ifModifiedSince ? strtotime($ifModifiedSince) : 0;
$durationMs = (microtime(true) - $metricsStart) * 1000;
$cacheStatus = 'miss';

foreach ([
    'Cache-Control' => 'public, max-age=' . $ttlSeconds . ', must-revalidate',
    'ETag' => $etag,
    'Last-Modified' => $lastModifiedHeader
] as $k => $v) {
    header($k . ': ' . $v);
}

if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
    $cacheStatus = 'etag';
    http_response_code(304);
    logMapMetric(304, 0, $etag, $user_id, $centerX, $centerY, $size, $durationMs, $cacheStatus, $mapMetricLog);
    maybeAlertMapMetric(304, 0, $durationMs, $cacheStatus, $user_id, $centerX, $centerY, $size, $mapMetricAlertLog);
    exit;
}

if ($ifModifiedSinceTs && $ifModifiedSinceTs >= $lastModifiedTs) {
    $cacheStatus = 'last-modified';
    http_response_code(304);
    logMapMetric(304, 0, $etag, $user_id, $centerX, $centerY, $size, $durationMs, $cacheStatus, $mapMetricLog);
    maybeAlertMapMetric(304, 0, $durationMs, $cacheStatus, $user_id, $centerX, $centerY, $size, $mapMetricAlertLog);
    exit;
}

echo $json;
logMapMetric(200, strlen($json), $etag, $user_id, $centerX, $centerY, $size, $durationMs, $cacheStatus, $mapMetricLog);
maybeAlertMapMetric(200, strlen($json), $durationMs, $cacheStatus, $user_id, $centerX, $centerY, $size, $mapMetricAlertLog);
