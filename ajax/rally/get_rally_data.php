<?php
/**
 * Rally Point data: outgoing/incoming commands for a village.
 */
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/utils/AjaxResponse.php';
require_once __DIR__ . '/../../lib/managers/VillageManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../../lib/managers/BattleManager.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('User is not logged in', null, 401);
}

try {
    $userId = (int)$_SESSION['user_id'];
    $villageManager = new VillageManager($conn);
    $buildingConfig = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfig);
    $battleManager = new BattleManager($conn, $villageManager, $buildingManager);

    $villageId = isset($_GET['village_id']) ? (int)$_GET['village_id'] : 0;
    if ($villageId <= 0) {
        $firstVillage = $villageManager->getFirstVillage($userId);
        if (!$firstVillage) {
            AjaxResponse::error('Village not found', null, 404);
        }
        $villageId = (int)$firstVillage['id'];
    }

    // Ownership check
    $stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
    $stmt->bind_param("i", $villageId);
    $stmt->execute();
    $owner = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$owner || (int)$owner['user_id'] !== $userId) {
        AjaxResponse::error('No permission for this village', null, 403);
    }

    $outgoing = $battleManager->getOutgoingAttacks($villageId);
    $incoming = $battleManager->getIncomingAttacks($villageId);

    ob_start();
    ?>
    <div class="rally-panel">
        <h3>Rally Point</h3>
        <p>Coordinate your troop movements.</p>

        <div class="rally-section">
            <h4>Outgoing commands</h4>
            <?php if (empty($outgoing)): ?>
                <p class="muted">No outgoing commands.</p>
            <?php else: ?>
                <table class="trades-table">
                    <tr>
                        <th>Type</th>
                        <th>Target</th>
                        <th>Arrival</th>
                        <th>Return</th>
                        <th></th>
                    </tr>
                    <?php foreach ($outgoing as $cmd): ?>
                        <tr>
                            <td><?= htmlspecialchars(ucfirst($cmd['attack_type'])) ?></td>
                            <td><?= htmlspecialchars($cmd['target_village_name'] ?? 'Village') ?> (<?= (int)$cmd['target_x'] ?>|<?= (int)$cmd['target_y'] ?>)</td>
                            <td><?= htmlspecialchars($cmd['formatted_arrival_time'] ?? $cmd['formatted_remaining_time']) ?> (ETA <?= htmlspecialchars($cmd['formatted_remaining_time']) ?>)</td>
                            <td><?= isset($cmd['formatted_return_time']) ? htmlspecialchars($cmd['formatted_return_time']) . ' (' . htmlspecialchars($cmd['formatted_return_remaining']) . ')' : '-' ?></td>
                            <td>
                                <?php if ($cmd['attack_type'] !== 'return'): ?>
                                    <button class="btn btn-secondary cancel-command-btn" data-attack-id="<?= (int)$cmd['id'] ?>">Cancel</button>
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="rally-section">
            <h4>Incoming commands</h4>
            <?php if (empty($incoming)): ?>
                <p class="muted">No incoming commands.</p>
            <?php else: ?>
                <table class="trades-table">
                    <tr>
                        <th>Type</th>
                        <th>From</th>
                        <th>Arrival</th>
                    </tr>
                    <?php foreach ($incoming as $cmd): ?>
                        <tr>
                            <td><?= htmlspecialchars(ucfirst($cmd['attack_type'])) ?></td>
                            <td><?= htmlspecialchars($cmd['source_village_name'] ?? 'Village') ?> (<?= (int)$cmd['source_x'] ?>|<?= (int)$cmd['source_y'] ?>) · <?= htmlspecialchars($cmd['attacker_name'] ?? 'Player') ?></td>
                            <td><?= htmlspecialchars($cmd['formatted_arrival_time'] ?? $cmd['formatted_remaining_time']) ?> (ETA <?= htmlspecialchars($cmd['formatted_remaining_time']) ?>)</td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    AjaxResponse::success(['html' => $html, 'outgoing' => $outgoing, 'incoming' => $incoming, 'village_id' => $villageId]);
} catch (Throwable $e) {
    AjaxResponse::handleException($e);
}
