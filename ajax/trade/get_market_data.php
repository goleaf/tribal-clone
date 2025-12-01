<?php
/**
 * Fetches market data (traders, routes, offers) and returns rendered HTML.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/utils/AjaxResponse.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/VillageManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/managers/TradeManager.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('User is not logged in', null, 401);
}

try {
    $userId = (int)$_SESSION['user_id'];
    $villageManager = new VillageManager($conn);
    $tradeManager = new TradeManager($conn);

    $villageId = isset($_GET['village_id']) ? (int)$_GET['village_id'] : 0;
    if ($villageId <= 0) {
        $firstVillage = $villageManager->getFirstVillage($userId);
        if (!$firstVillage) {
            AjaxResponse::error('Village not found', null, 404);
        }
        $villageId = (int)$firstVillage['id'];
    }

    // Validate ownership
    $stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
    $stmt->bind_param("i", $villageId);
    $stmt->execute();
    $owner = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$owner || (int)$owner['user_id'] !== $userId) {
        AjaxResponse::error('No permission for this village', null, 403);
    }

    // Refresh resource production before showing values
    $villageManager->updateResources($villageId);
    $village = $villageManager->getVillageInfo($villageId);

    // Process completed trades for this village
    $tradeMessages = $tradeManager->processArrivedTradesForVillage($villageId);

    $availability = $tradeManager->getTraderAvailability($villageId);
    $activeTrades = $tradeManager->getActiveTrades($villageId);
    $openOffers = $tradeManager->getOpenOffers($villageId);
    $myOffers = $tradeManager->getVillageOffers($villageId);

    $html = renderMarketHtml($village, $availability, $activeTrades, $openOffers, $myOffers);

    AjaxResponse::success([
        'html' => $html,
        'market' => [
            'availability' => $availability,
            'active_trades' => $activeTrades,
            'open_offers' => $openOffers,
            'my_offers' => $myOffers
        ],
        'village_id' => $villageId,
        'messages' => $tradeMessages
    ]);
} catch (Throwable $e) {
    AjaxResponse::handleException($e);
}

/**
 * Build the market HTML so the frontend can drop it into the popup.
 */
function renderMarketHtml(array $village, array $availability, array $activeTrades, array $openOffers, array $myOffers): string
{
    ob_start();
    ?>
    <div class="building-actions market-panel">
        <h3>Market</h3>
        <p>Trade resources with other players. Traders carry <?= number_format($availability['carry_capacity']) ?> resources each.</p>

        <div class="market-info">
            <p>Market level: <strong><?= (int)$availability['market_level'] ?></strong></p>
            <p>Traders: <strong><?= (int)$availability['available'] ?>/<?= (int)$availability['total'] ?></strong> available (<?= (int)$availability['reserved_offers'] ?> reserved for offers)</p>
            <p>
                Resources:
                <span class="resource wood">Wood <?= floor($village['wood']) ?></span> |
                <span class="resource clay">Clay <?= floor($village['clay']) ?></span> |
                <span class="resource iron">Iron <?= floor($village['iron']) ?></span>
            </p>
        </div>

        <?php if ($availability['market_level'] <= 0): ?>
            <div class="market-warning">
                <p>Build a Market to unlock trading.</p>
            </div>
        <?php else: ?>
            <div class="market-grid">
                <div class="market-card">
                    <h4>Send resources</h4>
                    <form id="send-resources-form" action="/ajax/trade/send_resources.php" method="post">
                        <input type="hidden" name="village_id" value="<?= (int)$village['id'] ?>">
                        <div class="form-group">
                            <label for="target_coords">Target (X|Y)</label>
                            <input type="text" id="target_coords" name="target_coords" placeholder="<?= (int)$village['x_coord'] ?>|<?= (int)$village['y_coord'] ?>" pattern="\d+\|\d+" required>
                        </div>
                        <div class="resource-inputs">
                            <div class="resource-input">
                                <label for="wood">Wood</label>
                                <input type="number" id="wood" name="wood" min="0" value="0">
                            </div>
                            <div class="resource-input">
                                <label for="clay">Clay</label>
                                <input type="number" id="clay" name="clay" min="0" value="0">
                            </div>
                            <div class="resource-input">
                                <label for="iron">Iron</label>
                                <input type="number" id="iron" name="iron" min="0" value="0">
                            </div>
                        </div>
                        <p class="helper-text">Traders needed are based on total resources / carry capacity.</p>
                        <div class="form-actions">
                            <button type="submit" class="send-button btn btn-primary">Send resources</button>
                        </div>
                    </form>
                </div>

                <div class="market-card">
                    <h4>Create trade offer</h4>
                    <form id="create-offer-form" action="/ajax/trade/create_offer.php" method="post">
                        <input type="hidden" name="village_id" value="<?= (int)$village['id'] ?>">
                        <div class="resource-inputs">
                            <div class="resource-input">
                                <label for="offer_wood">Offer wood</label>
                                <input type="number" id="offer_wood" name="offer_wood" min="0" value="0">
                            </div>
                            <div class="resource-input">
                                <label for="offer_clay">Offer clay</label>
                                <input type="number" id="offer_clay" name="offer_clay" min="0" value="0">
                            </div>
                            <div class="resource-input">
                                <label for="offer_iron">Offer iron</label>
                                <input type="number" id="offer_iron" name="offer_iron" min="0" value="0">
                            </div>
                        </div>
                        <div class="resource-inputs">
                            <div class="resource-input">
                                <label for="request_wood">Request wood</label>
                                <input type="number" id="request_wood" name="request_wood" min="0" value="0">
                            </div>
                            <div class="resource-input">
                                <label for="request_clay">Request clay</label>
                                <input type="number" id="request_clay" name="request_clay" min="0" value="0">
                            </div>
                            <div class="resource-input">
                                <label for="request_iron">Request iron</label>
                                <input type="number" id="request_iron" name="request_iron" min="0" value="0">
                            </div>
                        </div>
                        <p class="helper-text">Merchants for your offer are reserved immediately.</p>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-secondary">Create offer</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="market-subsection active-trades">
                <h4>Active transports</h4>
                <?php if (empty($activeTrades)): ?>
                    <p class="muted">No transports in progress.</p>
                <?php else: ?>
                    <table class="trades-table">
                        <tr>
                            <th>Direction</th>
                            <th>Resources</th>
                            <th>Village</th>
                            <th>Traders</th>
                            <th>Arrival</th>
                        </tr>
                        <?php foreach ($activeTrades as $trade): ?>
                            <tr>
                                <td>
                                    <?php
                                    if ($trade['direction'] === 'returning') {
                                        echo 'Returning';
                                    } else {
                                        echo $trade['direction'] === 'outgoing' ? 'Outgoing' : 'Incoming';
                                    }
                                    ?>
                                </td>
                                <td>W: <?= (int)$trade['wood'] ?> / C: <?= (int)$trade['clay'] ?> / I: <?= (int)$trade['iron'] ?></td>
                                <td><?= htmlspecialchars($trade['other_village'] ?? 'Unknown') ?> (<?= htmlspecialchars($trade['other_coords'] ?? '?|?') ?>)<br><small><?= htmlspecialchars($trade['other_player'] ?? 'Unknown') ?></small></td>
                                <td><?= (int)$trade['traders_count'] ?></td>
                                <td class="trade-timer" data-ends-at="<?= (int)$trade['arrival_time'] ?>"><?= gmdate("H:i:s", $trade['remaining_time']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <div class="market-subsection market-offers">
                <h4>Available offers</h4>
                <?php if (empty($openOffers)): ?>
                    <p class="muted">No open offers right now.</p>
                <?php else: ?>
                    <table class="offers-table">
                        <tr>
                            <th>Player</th>
                            <th>Village</th>
                            <th>Offers</th>
                            <th>Requests</th>
                            <th>Traders</th>
                            <th></th>
                        </tr>
                        <?php foreach ($openOffers as $offer): ?>
                            <tr>
                                <td><?= htmlspecialchars($offer['username'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($offer['village_name'] ?? 'Village') ?> (<?= htmlspecialchars($offer['coords'] ?? '?|?') ?>)</td>
                                <td>W: <?= (int)$offer['offered']['wood'] ?> / C: <?= (int)$offer['offered']['clay'] ?> / I: <?= (int)$offer['offered']['iron'] ?></td>
                                <td>W: <?= (int)$offer['requested']['wood'] ?> / C: <?= (int)$offer['requested']['clay'] ?> / I: <?= (int)$offer['requested']['iron'] ?></td>
                                <td><?= (int)$offer['merchants_required'] ?></td>
                                <td><button class="btn btn-primary accept-offer-btn" data-offer-id="<?= (int)$offer['id'] ?>">Accept</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <div class="market-subsection my-offers">
                <h4>My offers</h4>
                <?php if (empty($myOffers)): ?>
                    <p class="muted">You have not posted any offers.</p>
                <?php else: ?>
                    <table class="offers-table">
                        <tr>
                            <th>Offers</th>
                            <th>Requests</th>
                            <th>Traders</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        <?php foreach ($myOffers as $offer): ?>
                            <tr>
                                <td>W: <?= (int)$offer['offered']['wood'] ?> / C: <?= (int)$offer['offered']['clay'] ?> / I: <?= (int)$offer['offered']['iron'] ?></td>
                                <td>W: <?= (int)$offer['requested']['wood'] ?> / C: <?= (int)$offer['requested']['clay'] ?> / I: <?= (int)$offer['requested']['iron'] ?></td>
                                <td><?= (int)$offer['merchants_required'] ?></td>
                                <td class="offer-status"><?= htmlspecialchars(ucfirst($offer['status'])) ?></td>
                                <td>
                                    <?php if ($offer['status'] === 'open'): ?>
                                        <button class="btn btn-secondary cancel-offer-btn" data-offer-id="<?= (int)$offer['id'] ?>">Cancel</button>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
