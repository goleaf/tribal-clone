<?php
declare(strict_types=1);

require_once '../../init.php';
require_once __DIR__ . '/../../lib/managers/VillageManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$villageId = isset($_POST['village_id']) ? (int)$_POST['village_id'] : 0;

if ($villageId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid village']);
    exit();
}

$vm = new VillageManager($conn);
$village = $vm->getVillageInfo($villageId);

if (!$village || (int)$village['user_id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not control this village.']);
    exit();
}

$costWood = defined('COIN_COST_WOOD') ? (int)COIN_COST_WOOD : 20000;
$costClay = defined('COIN_COST_CLAY') ? (int)COIN_COST_CLAY : 20000;
$costIron = defined('COIN_COST_IRON') ? (int)COIN_COST_IRON : 20000;

if ($village['wood'] < $costWood || $village['clay'] < $costClay || $village['iron'] < $costIron) {
    http_response_code(400);
    echo json_encode(['error' => 'Not enough resources to mint a coin.']);
    exit();
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ?, coins = coins + 1 WHERE id = ?");
    $stmt->bind_param("iiii", $costWood, $costClay, $costIron, $villageId);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Coin minted.',
        'coins' => (int)$village['coins'] + 1,
        'resources' => [
            'wood' => (int)$village['wood'] - $costWood,
            'clay' => (int)$village['clay'] - $costClay,
            'iron' => (int)$village['iron'] - $costIron,
        ]
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mint coin: ' . $e->getMessage()]);
}
