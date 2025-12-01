<?php

declare(strict_types=1);

class TradeManager {
    private $conn;
    private bool $tradeTablesEnsured = false;
    private ?bool $tradeOffersTableExists = null;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->ensureTradeTables();
    }

    /**
     * Default carry capacity per trader.
     */
    public function getTraderCarryCapacity(): int
    {
        if (defined('TRADER_CAPACITY')) {
            return (int)TRADER_CAPACITY;
        }

        return 1000; // Fallback so trading still works without the constant.
    }

    /**
     * Number of traders unlocked by market level.
     */
    public function getTraderSlots(int $marketLevel): int
    {
        // Requirement: merchants equal to market level (0 when no market)
        return max(0, $marketLevel);
    }

    /**
     * Return market level for a village.
     */
    public function getMarketLevel(int $villageId): int
    {
        $stmt = $this->conn->prepare("
            SELECT vb.level 
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = 'market'
            LIMIT 1
        ");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['level'] : 0;
    }

    /**
     * Calculate traders needed for given resources.
     */
    public function calculateTradersNeeded(array $resources): int
    {
        $total = max(0, (int)($resources['wood'] ?? 0)) +
                 max(0, (int)($resources['clay'] ?? 0)) +
                 max(0, (int)($resources['iron'] ?? 0));

        if ($total <= 0) {
            return 0;
        }

        $capacity = $this->getTraderCarryCapacity();
        return (int)max(1, ceil($total / $capacity));
    }

    /**
     * Current trader availability for a village.
     */
    public function getTraderAvailability(int $villageId): array
    {
        $this->ensureTradeTables();

        $marketLevel = $this->getMarketLevel($villageId);
        $totalTraders = $this->getTraderSlots($marketLevel);
        $carryCapacity = $this->getTraderCarryCapacity();

        // Traders currently on the road
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(traders_count), 0) AS used
            FROM trade_routes
            WHERE source_village_id = ? AND arrival_time > NOW()
        ");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $routesInUse = (int)($row['used'] ?? 0);

        // Traders reserved by open offers
        $offersReserved = 0;
        if ($this->hasTradeOffersTable()) {
            $stmt = $this->conn->prepare("
                SELECT COALESCE(SUM(merchants_required), 0) AS reserved
                FROM trade_offers
                WHERE source_village_id = ? AND status = 'open'
            ");
            $stmt->bind_param("i", $villageId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $offersReserved = (int)($row['reserved'] ?? 0);
        }

        $inUse = $routesInUse + $offersReserved;
        $available = max(0, $totalTraders - $inUse);

        return [
            'market_level' => $marketLevel,
            'total' => $totalTraders,
            'in_use' => $inUse,
            'available' => $available,
            'carry_capacity' => $carryCapacity,
            'reserved_offers' => $offersReserved,
            'in_routes' => $routesInUse
        ];
    }

    /**
     * Create a direct resource transport.
     */
    public function sendResources(
        int $userId,
        int $villageId,
        string $targetCoords,
        array $resources
    ): array {
        $resources = [
            'wood' => max(0, (int)($resources['wood'] ?? 0)),
            'clay' => max(0, (int)($resources['clay'] ?? 0)),
            'iron' => max(0, (int)($resources['iron'] ?? 0)),
        ];

        if ($resources['wood'] + $resources['clay'] + $resources['iron'] <= 0) {
            return ['success' => false, 'message' => 'Select at least one resource to send.'];
        }

        if (!preg_match('/^(\d+)\|(\d+)$/', $targetCoords, $matches)) {
            return ['success' => false, 'message' => 'Invalid coordinates format. Use X|Y.'];
        }

        [$full, $targetX, $targetY] = $matches;
        $targetX = (int)$targetX;
        $targetY = (int)$targetY;

        // Verify ownership of the source village
        $stmt = $this->conn->prepare("SELECT id, user_id, x_coord, y_coord, wood, clay, iron FROM villages WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $village = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$village || (int)$village['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'You do not control this village.'];
        }

        if ($village['wood'] < $resources['wood'] || $village['clay'] < $resources['clay'] || $village['iron'] < $resources['iron']) {
            return ['success' => false, 'message' => 'Not enough resources in this village.'];
        }

        $targetVillage = $this->getVillageByCoords($targetX, $targetY);
        if (!$targetVillage) {
            return ['success' => false, 'message' => 'Target village not found.'];
        }
        if ((int)$targetVillage['id'] === $villageId) {
            return ['success' => false, 'message' => 'Cannot send resources to the same village.'];
        }

        $marketLevel = $this->getMarketLevel($villageId);
        if ($marketLevel <= 0) {
            return ['success' => false, 'message' => 'Build a market to send resources.'];
        }

        $tradersNeeded = $this->calculateTradersNeeded($resources);
        $availability = $this->getTraderAvailability($villageId);
        if ($availability['available'] < $tradersNeeded) {
            return ['success' => false, 'message' => 'No traders available for this shipment.'];
        }

        $distance = calculateDistance((float)$village['x_coord'], (float)$village['y_coord'], (float)$targetVillage['x_coord'], (float)$targetVillage['y_coord']);
        $speed = defined('TRADER_SPEED') ? TRADER_SPEED : 100;
        $timeSec = calculateTravelTime($distance, $speed);
        $departure = date('Y-m-d H:i:s');
        $arrival = date('Y-m-d H:i:s', time() + (int)$timeSec);

        $this->conn->begin_transaction();
        try {
            // Deduct resources now
            $stmt = $this->conn->prepare("UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ? WHERE id = ?");
            $stmt->bind_param("iiii", $resources['wood'], $resources['clay'], $resources['iron'], $villageId);
            $stmt->execute();
            $stmt->close();

            // Insert the outgoing route
            $stmt = $this->conn->prepare("
                INSERT INTO trade_routes 
                (source_village_id, target_village_id, target_x, target_y, wood, clay, iron, traders_count, departure_time, arrival_time, offer_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
            ");
            $stmt->bind_param(
                "iiiiiiiiss",
                $villageId,
                $targetVillage['id'],
                $targetX,
                $targetY,
                $resources['wood'],
                $resources['clay'],
                $resources['iron'],
                $tradersNeeded,
                $departure,
                $arrival
            );
            $stmt->execute();
            $routeId = $stmt->insert_id;
            $stmt->close();

            $this->conn->commit();
            return [
                'success' => true,
                'route_id' => $routeId,
                'arrival_time' => strtotime($arrival),
                'departure_time' => strtotime($departure),
                'traders_used' => $tradersNeeded,
                'target_village_id' => (int)$targetVillage['id']
            ];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Could not start the transport: ' . $e->getMessage()];
        }
    }

    /**
     * Process finished trade routes touching this village.
     */
    public function processArrivedTradesForVillage(int $villageId): array
    {
        $messages = [];

        $stmt = $this->conn->prepare("
            SELECT tr.*, 
                   sv.x_coord AS source_x, sv.y_coord AS source_y
            FROM trade_routes tr
            JOIN villages sv ON tr.source_village_id = sv.id
            WHERE arrival_time <= NOW()
              AND (source_village_id = ? OR target_village_id = ?)
        ");
        $stmt->bind_param("ii", $villageId, $villageId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($route = $result->fetch_assoc()) {
            $this->conn->begin_transaction();
            try {
                $hasPayload = ((int)$route['wood'] + (int)$route['clay'] + (int)$route['iron']) > 0;
                $targetId = $route['target_village_id'] ?: $route['source_village_id'];

                if ($hasPayload) {
                    // Deliver resources
                    $stmtAdd = $this->conn->prepare("UPDATE villages SET wood = wood + ?, clay = clay + ?, iron = iron + ? WHERE id = ?");
                    $stmtAdd->bind_param("iiii", $route['wood'], $route['clay'], $route['iron'], $targetId);
                    $stmtAdd->execute();
                    $stmtAdd->close();

                    // Mark offers as completed (only on delivery)
                    if (!empty($route['offer_id'])) {
                        $this->markOfferIfCompleted((int)$route['offer_id']);
                    }

                    // Start the return trip: reuse the same route row with zero payload
                    $distance = calculateDistance(
                        (float)$route['source_x'],
                        (float)$route['source_y'],
                        (float)$route['target_x'],
                        (float)$route['target_y']
                    );
                    $speed = defined('TRADER_SPEED') ? TRADER_SPEED : 100;
                    $timeSec = calculateTravelTime($distance, $speed);
                    $nowTime = date('Y-m-d H:i:s');
                    $returnArrival = date('Y-m-d H:i:s', time() + (int)$timeSec);

                    $update = $this->conn->prepare("
                        UPDATE trade_routes
                           SET wood = 0,
                               clay = 0,
                               iron = 0,
                               target_village_id = ?,
                               target_x = ?,
                               target_y = ?,
                               departure_time = ?,
                               arrival_time = ?
                         WHERE id = ?
                    ");
                    $update->bind_param(
                        "iiissi",
                        $route['source_village_id'],
                        $route['source_x'],
                        $route['source_y'],
                        $nowTime,
                        $returnArrival,
                        $route['id']
                    );
                    $update->execute();
                    $update->close();

                    $messages[] = sprintf(
                        "Trade delivered: +%d wood, +%d clay, +%d iron. Merchants returning.",
                        $route['wood'],
                        $route['clay'],
                        $route['iron']
                    );
                } else {
                    // Return leg finished: free merchants by deleting the route
                    $stmtDel = $this->conn->prepare("DELETE FROM trade_routes WHERE id = ?");
                    $stmtDel->bind_param("i", $route['id']);
                    $stmtDel->execute();
                    $stmtDel->close();
                }

                $this->conn->commit();
            } catch (Exception $e) {
                $this->conn->rollback();
            }
        }

        $stmt->close();
        return $messages;
    }

    /**
     * Active transports for a village (incoming + outgoing).
     */
    public function getActiveTrades(int $villageId): array
    {
        $stmt = $this->conn->prepare("
            SELECT tr.*, 
                   sv.name AS source_name, sv.x_coord AS source_x, sv.y_coord AS source_y, su.username AS source_player,
                   tv.name AS target_name, tv.x_coord AS target_x, tv.y_coord AS target_y, tu.username AS target_player
            FROM trade_routes tr
            JOIN villages sv ON tr.source_village_id = sv.id
            JOIN users su ON sv.user_id = su.id
            LEFT JOIN villages tv ON tr.target_village_id = tv.id
            LEFT JOIN users tu ON tv.user_id = tu.id
            WHERE (tr.source_village_id = ? OR tr.target_village_id = ?)
              AND tr.arrival_time > NOW()
              AND sv.world_id = ?
            ORDER BY tr.arrival_time ASC
        ");
        $currentWorld = defined('CURRENT_WORLD_ID') ? CURRENT_WORLD_ID : 1;
        $stmt->bind_param("iii", $villageId, $villageId, $currentWorld);
        $stmt->execute();
        $result = $stmt->get_result();

        $active = [];
        $now = time();

        while ($row = $result->fetch_assoc()) {
            $arrivalTs = strtotime($row['arrival_time']);
            $remaining = max(0, $arrivalTs - $now);
            $isOutgoing = (int)$row['source_village_id'] === $villageId;
            $isReturning = ((int)$row['wood'] + (int)$row['clay'] + (int)$row['iron']) === 0;

            $active[] = [
                'id' => (int)$row['id'],
                'direction' => $isReturning ? 'returning' : ($isOutgoing ? 'outgoing' : 'incoming'),
                'wood' => (int)$row['wood'],
                'clay' => (int)$row['clay'],
                'iron' => (int)$row['iron'],
                'traders_count' => (int)$row['traders_count'],
                'arrival_time' => $arrivalTs,
                'remaining_time' => $remaining,
                'other_village' => $isOutgoing ? $row['target_name'] : $row['source_name'],
                'other_coords' => $isOutgoing
                    ? $this->formatCoords($row['target_x'], $row['target_y'])
                    : $this->formatCoords($row['source_x'], $row['source_y']),
                'other_player' => $isOutgoing ? $row['target_player'] : $row['source_player']
            ];
        }

        $stmt->close();
        return $active;
    }

    /**
     * Open offers from other villages.
     */
    public function getOpenOffers(int $villageId, int $limit = 25): array
    {
        if (!$this->hasTradeOffersTable()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT o.*, v.name AS village_name, v.x_coord, v.y_coord, v.world_id, u.username
            FROM trade_offers o
            JOIN villages v ON o.source_village_id = v.id
            JOIN users u ON v.user_id = u.id
            WHERE o.status = 'open' 
              AND o.source_village_id <> ?
              AND v.world_id = ?
            ORDER BY o.created_at DESC
            LIMIT ?
        ");
        $currentWorld = defined('CURRENT_WORLD_ID') ? CURRENT_WORLD_ID : 1;
        $stmt->bind_param("iii", $villageId, $currentWorld, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $offers = [];
        while ($row = $result->fetch_assoc()) {
            $offers[] = $this->hydrateOffer($row);
        }
        $stmt->close();

        return $offers;
    }

    /**
     * Offers created by this village.
     */
    public function getVillageOffers(int $villageId): array
    {
        if (!$this->hasTradeOffersTable()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT o.*, v.name AS village_name, v.x_coord, v.y_coord
            FROM trade_offers o
            JOIN villages v ON o.source_village_id = v.id
            WHERE o.source_village_id = ? AND v.world_id = ?
            ORDER BY o.created_at DESC
        ");
        $currentWorld = defined('CURRENT_WORLD_ID') ? CURRENT_WORLD_ID : 1;
        $stmt->bind_param("ii", $villageId, $currentWorld);
        $stmt->execute();
        $result = $stmt->get_result();

        $offers = [];
        while ($row = $result->fetch_assoc()) {
            $offers[] = $this->hydrateOffer($row);
        }
        $stmt->close();

        return $offers;
    }

    /**
     * Create a new trade offer.
     */
    public function createOffer(int $userId, int $villageId, array $offerResources, array $requestResources): array
    {
        if (!$this->hasTradeOffersTable()) {
            return ['success' => false, 'message' => 'Trading offers are not supported by this world yet.'];
        }

        $offerResources = [
            'wood' => max(0, (int)($offerResources['wood'] ?? 0)),
            'clay' => max(0, (int)($offerResources['clay'] ?? 0)),
            'iron' => max(0, (int)($offerResources['iron'] ?? 0)),
        ];
        $requestResources = [
            'wood' => max(0, (int)($requestResources['wood'] ?? 0)),
            'clay' => max(0, (int)($requestResources['clay'] ?? 0)),
            'iron' => max(0, (int)($requestResources['iron'] ?? 0)),
        ];

        if ($offerResources['wood'] + $offerResources['clay'] + $offerResources['iron'] <= 0) {
            return ['success' => false, 'message' => 'Offered resources must be greater than zero.'];
        }
        if ($requestResources['wood'] + $requestResources['clay'] + $requestResources['iron'] <= 0) {
            return ['success' => false, 'message' => 'Requested resources must be greater than zero.'];
        }

        $village = $this->getVillageWithOwner($villageId);
        if (!$village || (int)$village['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'You do not control this village.'];
        }

        $marketLevel = $this->getMarketLevel($villageId);
        if ($marketLevel <= 0) {
            return ['success' => false, 'message' => 'Build a market before creating offers.'];
        }

        $tradersNeeded = $this->calculateTradersNeeded($offerResources);
        $availability = $this->getTraderAvailability($villageId);
        if ($availability['available'] < $tradersNeeded) {
            return ['success' => false, 'message' => 'Not enough free traders to post this offer.'];
        }

        if ($village['wood'] < $offerResources['wood'] || $village['clay'] < $offerResources['clay'] || $village['iron'] < $offerResources['iron']) {
            return ['success' => false, 'message' => 'Not enough resources to place this offer.'];
        }

        $this->conn->begin_transaction();
        try {
            // Reserve the offered resources
            $stmt = $this->conn->prepare("UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ? WHERE id = ?");
            $stmt->bind_param("iiii", $offerResources['wood'], $offerResources['clay'], $offerResources['iron'], $villageId);
            $stmt->execute();
            $stmt->close();

            // Insert the offer
            $stmt = $this->conn->prepare("
                INSERT INTO trade_offers (
                    source_village_id, offered_wood, offered_clay, offered_iron,
                    requested_wood, requested_clay, requested_iron,
                    merchants_required, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
            ");
            $stmt->bind_param(
                "iiiiiiii",
                $villageId,
                $offerResources['wood'],
                $offerResources['clay'],
                $offerResources['iron'],
                $requestResources['wood'],
                $requestResources['clay'],
                $requestResources['iron'],
                $tradersNeeded
            );
            $stmt->execute();
            $offerId = $stmt->insert_id;
            $stmt->close();

            $this->conn->commit();

            // Optional report entry for the offer owner
            if (!class_exists('ReportManager')) {
                require_once __DIR__ . '/ReportManager.php';
            }
            if (class_exists('ReportManager')) {
                $reportManager = new ReportManager($this->conn);
                $reportManager->addReport(
                    (int)$village['user_id'],
                    'trade',
                    'Trade offer posted',
                    [
                        'offer' => $offerResources,
                        'request' => $requestResources,
                        'village_id' => $villageId
                    ],
                    $offerId
                );
            }
            return ['success' => true, 'offer_id' => $offerId];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Could not create offer: ' . $e->getMessage()];
        }
    }

    /**
     * Cancel an open offer and refund resources.
     */
    public function cancelOffer(int $userId, int $villageId, int $offerId): array
    {
        if (!$this->hasTradeOffersTable()) {
            return ['success' => false, 'message' => 'Trading offers are not supported by this world yet.'];
        }

        $stmt = $this->conn->prepare("
            SELECT * FROM trade_offers
            WHERE id = ? AND source_village_id = ? AND status = 'open'
            LIMIT 1
        ");
        $stmt->bind_param("ii", $offerId, $villageId);
        $stmt->execute();
        $offer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$offer) {
            return ['success' => false, 'message' => 'Offer not found or already handled.'];
        }

        $village = $this->getVillageWithOwner($villageId);
        if (!$village || (int)$village['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'You do not control this village.'];
        }

        $this->conn->begin_transaction();
        try {
            // Refund resources
            $stmt = $this->conn->prepare("UPDATE villages SET wood = wood + ?, clay = clay + ?, iron = iron + ? WHERE id = ?");
            $stmt->bind_param("iiii", $offer['offered_wood'], $offer['offered_clay'], $offer['offered_iron'], $villageId);
            $stmt->execute();
            $stmt->close();

            // Update status
            $stmt = $this->conn->prepare("UPDATE trade_offers SET status = 'canceled', completed_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $offerId);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Could not cancel offer: ' . $e->getMessage()];
        }
    }

    /**
     * Accept an open offer.
     */
    public function acceptOffer(int $userId, int $acceptingVillageId, int $offerId): array
    {
        if (!$this->hasTradeOffersTable()) {
            return ['success' => false, 'message' => 'Trading offers are not supported by this world yet.'];
        }

        $offerStmt = $this->conn->prepare("
            SELECT o.*, sv.x_coord AS source_x, sv.y_coord AS source_y, sv.user_id AS source_user_id,
                   tv.name AS source_name
            FROM trade_offers o
            JOIN villages sv ON o.source_village_id = sv.id
            LEFT JOIN villages tv ON o.source_village_id = tv.id
            WHERE o.id = ? AND o.status = 'open'
            LIMIT 1
        ");
        $offerStmt->bind_param("i", $offerId);
        $offerStmt->execute();
        $offer = $offerStmt->get_result()->fetch_assoc();
        $offerStmt->close();

        if (!$offer) {
            return ['success' => false, 'message' => 'Offer not available anymore.'];
        }

        $acceptingVillage = $this->getVillageWithOwner($acceptingVillageId);
        if (!$acceptingVillage || (int)$acceptingVillage['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'You do not control this village.'];
        }
        if ((int)$acceptingVillage['id'] === (int)$offer['source_village_id']) {
            return ['success' => false, 'message' => 'You cannot accept your own offer.'];
        }
        if ((int)$offer['source_user_id'] === $userId) {
            return ['success' => false, 'message' => 'You cannot accept your own offer from another village.'];
        }

        // Market level check for the accepting village
        $marketLevel = $this->getMarketLevel($acceptingVillageId);
        if ($marketLevel <= 0) {
            return ['success' => false, 'message' => 'Build a market before accepting offers.'];
        }

        // Resources + trader availability for the accepting village (to send requested goods)
        $requiredFromAcceptor = [
            'wood' => (int)$offer['requested_wood'],
            'clay' => (int)$offer['requested_clay'],
            'iron' => (int)$offer['requested_iron'],
        ];
        $tradersNeededAcceptor = $this->calculateTradersNeeded($requiredFromAcceptor);

        $availability = $this->getTraderAvailability($acceptingVillageId);
        if ($availability['available'] < $tradersNeededAcceptor) {
            return ['success' => false, 'message' => 'Not enough traders available to accept this offer.'];
        }

        if ($acceptingVillage['wood'] < $requiredFromAcceptor['wood'] ||
            $acceptingVillage['clay'] < $requiredFromAcceptor['clay'] ||
            $acceptingVillage['iron'] < $requiredFromAcceptor['iron']) {
            return ['success' => false, 'message' => 'Not enough resources to accept this offer.'];
        }

        // Distance calculations
        $distance = calculateDistance(
            (float)$offer['source_x'],
            (float)$offer['source_y'],
            (float)$acceptingVillage['x_coord'],
            (float)$acceptingVillage['y_coord']
        );
        $speed = defined('TRADER_SPEED') ? TRADER_SPEED : 100;
        $timeSec = calculateTravelTime($distance, $speed);
        $departure = date('Y-m-d H:i:s');
        $arrival = date('Y-m-d H:i:s', time() + (int)$timeSec);

        $this->conn->begin_transaction();
        try {
            // Deduct requested resources from the accepting village
            $stmt = $this->conn->prepare("UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ? WHERE id = ?");
            $stmt->bind_param("iiii", $requiredFromAcceptor['wood'], $requiredFromAcceptor['clay'], $requiredFromAcceptor['iron'], $acceptingVillageId);
            $stmt->execute();
            $stmt->close();

            // Create route from seller -> buyer (offered resources)
            $stmt = $this->conn->prepare("
                INSERT INTO trade_routes 
                (source_village_id, target_village_id, target_x, target_y, wood, clay, iron, traders_count, departure_time, arrival_time, offer_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iiiiiiiissi",
                $offer['source_village_id'],
                $acceptingVillageId,
                $acceptingVillage['x_coord'],
                $acceptingVillage['y_coord'],
                $offer['offered_wood'],
                $offer['offered_clay'],
                $offer['offered_iron'],
                $offer['merchants_required'],
                $departure,
                $arrival,
                $offerId
            );
            $stmt->execute();
            $stmt->close();

            // Create route from buyer -> seller (requested resources)
            $stmt = $this->conn->prepare("
                INSERT INTO trade_routes 
                (source_village_id, target_village_id, target_x, target_y, wood, clay, iron, traders_count, departure_time, arrival_time, offer_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iiiiiiiissi",
                $acceptingVillageId,
                $offer['source_village_id'],
                $offer['source_x'],
                $offer['source_y'],
                $offer['requested_wood'],
                $offer['requested_clay'],
                $offer['requested_iron'],
                $tradersNeededAcceptor,
                $departure,
                $arrival,
                $offerId
            );
            $stmt->execute();
            $stmt->close();

            // Update offer status
            $stmt = $this->conn->prepare("
                UPDATE trade_offers 
                SET status = 'accepted', accepted_village_id = ?, accepted_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $acceptingVillageId, $offerId);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();

            return [
                'success' => true,
                'arrival_time' => strtotime($arrival),
                'traders_used' => $tradersNeededAcceptor
            ];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Could not accept offer: ' . $e->getMessage()];
        }
    }

    /**
     * Check whether the trade_offers table exists (graceful fallback for older worlds).
     */
    private function hasTradeOffersTable(): bool
    {
        $this->ensureTradeTables();

        if ($this->tradeOffersTableExists !== null) {
            return $this->tradeOffersTableExists;
        }

        try {
            if ($this->isSqlite()) {
                $stmt = $this->conn->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='trade_offers'");
            } else {
                $stmt = $this->conn->prepare("SHOW TABLES LIKE 'trade_offers'");
            }
        } catch (Throwable $e) {
            error_log("TradeManager::hasTradeOffersTable prepare failed: " . $e->getMessage());
            return false;
        }

        if ($stmt === false) {
            return false;
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $stmt->close();

        $this->tradeOffersTableExists = $exists;
        return $exists;
    }

    private function ensureTradeTables(): void
    {
        if ($this->tradeTablesEnsured) {
            return;
        }

        $this->tradeTablesEnsured = true;

        try {
            if ($this->isSqlite()) {
                $this->conn->query("
                    CREATE TABLE IF NOT EXISTS trade_offers (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        source_village_id INTEGER NOT NULL,
                        offered_wood INTEGER NOT NULL DEFAULT 0,
                        offered_clay INTEGER NOT NULL DEFAULT 0,
                        offered_iron INTEGER NOT NULL DEFAULT 0,
                        requested_wood INTEGER NOT NULL DEFAULT 0,
                        requested_clay INTEGER NOT NULL DEFAULT 0,
                        requested_iron INTEGER NOT NULL DEFAULT 0,
                        merchants_required INTEGER NOT NULL DEFAULT 1,
                        status TEXT NOT NULL DEFAULT 'open',
                        accepted_village_id INTEGER NULL,
                        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        accepted_at TEXT NULL,
                        completed_at TEXT NULL,
                        FOREIGN KEY (source_village_id) REFERENCES villages(id) ON DELETE CASCADE,
                        FOREIGN KEY (accepted_village_id) REFERENCES villages(id) ON DELETE SET NULL
                    )
                ");

                $this->conn->query("
                    CREATE TABLE IF NOT EXISTS trade_routes (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        source_village_id INTEGER NOT NULL,
                        target_village_id INTEGER NULL,
                        target_x INTEGER NOT NULL,
                        target_y INTEGER NOT NULL,
                        wood INTEGER NOT NULL,
                        clay INTEGER NOT NULL,
                        iron INTEGER NOT NULL,
                        traders_count INTEGER NOT NULL DEFAULT 1,
                        departure_time TEXT NOT NULL,
                        arrival_time TEXT NOT NULL,
                        offer_id INTEGER NULL,
                        FOREIGN KEY (source_village_id) REFERENCES villages(id) ON DELETE CASCADE,
                        FOREIGN KEY (target_village_id) REFERENCES villages(id) ON DELETE CASCADE,
                        FOREIGN KEY (offer_id) REFERENCES trade_offers(id) ON DELETE SET NULL
                    )
                ");
            } else {
                $this->conn->query("
                    CREATE TABLE IF NOT EXISTS `trade_offers` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `source_village_id` INT NOT NULL,
                        `offered_wood` INT NOT NULL DEFAULT 0,
                        `offered_clay` INT NOT NULL DEFAULT 0,
                        `offered_iron` INT NOT NULL DEFAULT 0,
                        `requested_wood` INT NOT NULL DEFAULT 0,
                        `requested_clay` INT NOT NULL DEFAULT 0,
                        `requested_iron` INT NOT NULL DEFAULT 0,
                        `merchants_required` INT NOT NULL DEFAULT 1,
                        `status` VARCHAR(20) NOT NULL DEFAULT 'open',
                        `accepted_village_id` INT NULL,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `accepted_at` DATETIME NULL,
                        `completed_at` DATETIME NULL,
                        FOREIGN KEY (`source_village_id`) REFERENCES villages(id) ON DELETE CASCADE,
                        FOREIGN KEY (`accepted_village_id`) REFERENCES villages(id) ON DELETE SET NULL
                    )
                ");

                $this->conn->query("
                    CREATE TABLE IF NOT EXISTS `trade_routes` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `source_village_id` INT NOT NULL,
                        `target_village_id` INT NULL,
                        `target_x` INT NOT NULL,
                        `target_y` INT NOT NULL,
                        `wood` INT NOT NULL,
                        `clay` INT NOT NULL,
                        `iron` INT NOT NULL,
                        `traders_count` INT NOT NULL DEFAULT 1,
                        `departure_time` DATETIME NOT NULL,
                        `arrival_time` DATETIME NOT NULL,
                        `offer_id` INT NULL,
                        FOREIGN KEY (`source_village_id`) REFERENCES villages(id) ON DELETE CASCADE,
                        FOREIGN KEY (`target_village_id`) REFERENCES villages(id) ON DELETE CASCADE,
                        FOREIGN KEY (`offer_id`) REFERENCES trade_offers(id) ON DELETE SET NULL
                    )
                ");
            }

            // Reset cache so the next check reflects current state.
            $this->tradeOffersTableExists = null;
        } catch (Throwable $e) {
            error_log("TradeManager::ensureTradeTables failed: " . $e->getMessage());
        }
    }

    private function isSqlite(): bool
    {
        return is_object($this->conn) && method_exists($this->conn, 'getPdo');
    }

    private function getVillageByCoords(int $x, int $y): ?array
    {
        $world = defined('CURRENT_WORLD_ID') ? CURRENT_WORLD_ID : 1;
        $stmt = $this->conn->prepare("SELECT * FROM villages WHERE x_coord = ? AND y_coord = ? AND world_id = ? LIMIT 1");
        $stmt->bind_param("iii", $x, $y, $world);
        $stmt->execute();
        $village = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $village ?: null;
    }

    private function getVillageWithOwner(int $villageId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM villages WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $village = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $village ?: null;
    }

    private function formatCoords(?int $x, ?int $y): string
    {
        if ($x === null || $y === null) {
            return '?|?';
        }
        return $x . '|' . $y;
    }

    private function hydrateOffer(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'source_village_id' => (int)$row['source_village_id'],
            'village_name' => $row['village_name'] ?? null,
            'coords' => isset($row['x_coord'], $row['y_coord']) ? $this->formatCoords((int)$row['x_coord'], (int)$row['y_coord']) : null,
            'username' => $row['username'] ?? null,
            'offered' => [
                'wood' => (int)$row['offered_wood'],
                'clay' => (int)$row['offered_clay'],
                'iron' => (int)$row['offered_iron'],
            ],
            'requested' => [
                'wood' => (int)$row['requested_wood'],
                'clay' => (int)$row['requested_clay'],
                'iron' => (int)$row['requested_iron'],
            ],
            'merchants_required' => (int)$row['merchants_required'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'accepted_at' => $row['accepted_at'] ?? null,
            'accepted_village_id' => isset($row['accepted_village_id']) ? (int)$row['accepted_village_id'] : null,
            'completed_at' => $row['completed_at'] ?? null
        ];
    }

    private function markOfferIfCompleted(int $offerId): void
    {
        if (!$this->hasTradeOffersTable()) {
            return;
        }

        // If there are no more routes tied to this offer, mark it completed.
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM trade_routes WHERE offer_id = ?");
        $stmt->bind_param("i", $offerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ((int)$row['cnt'] === 0) {
            $stmt = $this->conn->prepare("
                UPDATE trade_offers
                SET status = 'completed', completed_at = NOW()
                WHERE id = ? AND status <> 'completed'
            ");
            $stmt->bind_param("i", $offerId);
            $stmt->execute();
            $stmt->close();
        }
    }
}
