<?php
declare(strict_types=1);

require_once '../../init.php';
require_once __DIR__ . '/../../lib/managers/VillageManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../../lib/managers/UnitManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$villageId = isset($_GET['village_id']) ? (int)$_GET['village_id'] : 0;

if ($villageId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid village']);
    exit();
}

$vm = new VillageManager($conn);
$bcm = new BuildingConfigManager($conn);
$bm = new BuildingManager($conn, $bcm);
$um = new UnitManager($conn);

$village = $vm->getVillageInfo($villageId);
if (!$village || (int)$village['user_id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not control this village.']);
    exit();
}

$levels = [
    'statue' => $bm->getBuildingLevel($villageId, 'statue'),
    'academy' => $bm->getBuildingLevel($villageId, 'academy'),
    'smithy' => $bm->getBuildingLevel($villageId, 'smithy'),
    'market' => $bm->getBuildingLevel($villageId, 'market'),
];

$unit = $um->getAllUnitTypes();
$nobleType = null;
foreach ($unit as $row) {
    if (in_array($row['internal_name'], ['noble', 'nobleman', 'nobleman_unit'], true)) {
        $nobleType = $row;
        break;
    }
}

$maxNobles = $um->getMaxNoblesForUser($userId);
$currentNobles = $um->countUserNobles($userId);

$costs = $nobleType ? [
    'wood' => (int)$nobleType['cost_wood'],
    'clay' => (int)$nobleType['cost_clay'],
    'iron' => (int)$nobleType['cost_iron'],
] : ['wood' => 0, 'clay' => 0, 'iron' => 0];

echo json_encode([
    'success' => true,
    'data' => [
        'village' => [
            'id' => $villageId,
            'name' => $village['name'],
            'coins' => (int)($village['coins'] ?? 0),
            'loyalty' => (int)($village['loyalty'] ?? 100),
            'resources' => [
                'wood' => (int)$village['wood'],
                'clay' => (int)$village['clay'],
                'iron' => (int)$village['iron'],
            ]
        ],
        'buildings' => $levels,
        'noble' => [
            'unit_id' => $nobleType['id'] ?? null,
            'costs' => $costs,
            'training_time' => $nobleType ? (int)$nobleType['training_time_base'] : 0,
        ],
        'caps' => [
            'max_nobles' => $maxNobles,
            'current_nobles' => $currentNobles,
        ],
        'coin_costs' => [
            'wood' => defined('COIN_COST_WOOD') ? (int)COIN_COST_WOOD : 20000,
            'clay' => defined('COIN_COST_CLAY') ? (int)COIN_COST_CLAY : 20000,
            'iron' => defined('COIN_COST_IRON') ? (int)COIN_COST_IRON : 20000,
        ]
    ]
]);
