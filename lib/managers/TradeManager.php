<?php

declare(strict_types=1);

require_once __DIR__ . '/../utils/EconomyError.php';

class TradeManager {
    private $conn;
    private bool $tradeTablesEnsured = false;
    private ?bool $tradeOffersTableExists = null;
    private array $userColumnExistsCache = [];
    private ?string $altFlagColumn = null;
    private ?array $identityColumns = null;
    private array $userProfileCache = [];
    private const MIN_FAIR_RATE = 0.25; // offered/requested lower bound (25%)
    private const MAX_FAIR_RATE = 4.0;  // offered/requested upper bound (400%)
    private const PUSH_POINTS_RATIO = 5; // block aid when sender points exceed target by 5x and target is protected/low points

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
            return [
                'success' => false,
                'message' => 'Select at least one resource to send.',
                'code' => EconomyError::ERR_VALIDATION,
                'details' => ['field' => 'resources', 'message' => 'positive_required']
            ];
        }

        if (!preg_match('/^(\d+)\|(\d+)$/', $targetCoords, $matches)) {
            return ['success' => false, 'message' => 'Invalid coordinates format. Use X|Y.', 'code' => 'ERR_INPUT'];
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
            return ['success' => false, 'message' => 'You do not control this village.', 'code' => 'ERR_ALT_BLOCK'];
        }

        if ($village['wood'] < $resources['wood'] || $village['clay'] < $resources['clay'] || $village['iron'] < $resources['iron']) {
            return [
                'success' => false,
                'message' => 'Not enough resources in this village.',
                'code' => EconomyError::ERR_CAP,
                'details' => [
                    'cap_type' => 'storage',
                    'required' => $resources,
                    'available' => [
                        'wood' => (int)$village['wood'],
                        'clay' => (int)$village['clay'],
                        'iron' => (int)$village['iron']
                    ]
                ]
            ];
        }

        $targetVillage = $this->getVillageByCoords($targetX, $targetY);
        if (!$targetVillage) {
            return ['success' => false, 'message' => 'Target village not found.', 'code' => 'ERR_INPUT'];
        }
        if ((int)$targetVillage['id'] === $villageId) {
            return ['success' => false, 'message' => 'Cannot send resources to the same village.', 'code' => 'ERR_INPUT'];
        }

        // Anti-push: block sending to heavily protected/low-point targets when sender is much stronger
        $pushCheck = $this->enforceAntiPush((int)$village['user_id'], (int)$targetVillage['user_id']);
        if ($pushCheck !== true) {
            return $pushCheck;
        }

        // Enforce storage headroom at target to avoid overflow abuse
        $headroomCheck = $this->checkStorageHeadroom($targetVillage, $resources);
        if ($headroomCheck !== true) {
            return $headroomCheck;
        }

        $marketLevel = $this->getMarketLevel($villageId);
        if ($marketLevel <= 0) {
            return ['success' => false, 'message' => 'Build a market to send resources.', 'code' => EconomyError::ERR_CAP];
        }

        $tradersNeeded = $this->calculateTradersNeeded($resources);
        $availability = $this->getTraderAvailability($villageId);
        if ($availability['available'] < $tradersNeeded) {
            return ['success' => false, 'message' => 'No traders available for this shipment.', 'code' => EconomyError::ERR_CAP];
        }

        $distance = calculateDistance((float)$village['x_coord'], (float)$village['y_coord'], (float)$targetVillage['x_coord'], (float)$targetVillage['y_coord']);
        $speed = defined('TRADER_SPEED') ? TRADER_SPEED : 100;
        $timeSec = calculateTravelTime($distance, $speed);
        $departure = date('Y-m-d H:i:s');
        $arrival = date('Y-m-d H:i:s', time() + (int)$timeSec);

        // Enforce target storage caps to prevent overflow pushing.
        $capacityCheck = $this->checkStorageHeadroom($targetVillage, $resources);
        if ($capacityCheck !== true) {
            return $capacityCheck;
        }

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

            $this->logTradeAudit('send_resources', [
                'actor_user_id' => $userId,
                'source_village_id' => $villageId,
                'target_village_id' => (int)$targetVillage['id'],
                'target_coords' => [$targetX, $targetY],
                'resources' => $resources,
                'traders_used' => $tradersNeeded
            ]);
            $this->logEconomyMetric('send_resources', [
                'actor_user_id' => $userId,
                'source_village_id' => $villageId,
                'target_village_id' => (int)$targetVillage['id'],
                'wood' => $resources['wood'],
                'clay' => $resources['clay'],
                'iron' => $resources['iron'],
                'total' => array_sum($resources),
                'traders_used' => $tradersNeeded
            ]);

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
            return ['success' => false, 'message' => 'Trading offers are not supported by this world yet.', 'code' => EconomyError::ERR_CAP];
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
            return ['success' => false, 'message' => 'Offered resources must be greater than zero.', 'code' => 'ERR_INPUT'];
        }
        if ($requestResources['wood'] + $requestResources['clay'] + $requestResources['iron'] <= 0) {
            return ['success' => false, 'message' => 'Requested resources must be greater than zero.', 'code' => 'ERR_INPUT'];
        }

        // Validate exchange ratio within allowed band
        $offeredTotal = $offerResources['wood'] + $offerResources['clay'] + $offerResources['iron'];
        $requestedTotal = $requestResources['wood'] + $requestResources['clay'] + $requestResources['iron'];
        $minRatio = defined('TRADE_MIN_RATIO') ? (float)TRADE_MIN_RATIO : 0.5;
        $maxRatio = defined('TRADE_MAX_RATIO') ? (float)TRADE_MAX_RATIO : 2.0;
        if ($requestedTotal > 0) {
            $ratio = $offeredTotal / $requestedTotal;
            if ($ratio < $minRatio || $ratio > $maxRatio) {
                return [
                    'success' => false,
                    'message' => 'Exchange ratio outside allowed range.',
                    'code' => EconomyError::ERR_RATIO,
                    'details' => [
                        'offered_ratio' => $ratio,
                        'min_ratio' => $minRatio,
                        'max_ratio' => $maxRatio
                    ]
                ];
            }
        }

        // Enforce fair-market bounds to reduce pushing/abuse
        $totalOffered = $offerResources['wood'] + $offerResources['clay'] + $offerResources['iron'];
        $totalRequested = $requestResources['wood'] + $requestResources['clay'] + $requestResources['iron'];
        $ratio = $totalRequested > 0 ? ($totalOffered / $totalRequested) : 0;
        if ($ratio < self::MIN_FAIR_RATE || $ratio > self::MAX_FAIR_RATE) {
            return [
                'success' => false,
                'message' => 'Trade offer ratio outside allowed range. Adjust amounts to be fair.',
                'code' => EconomyError::ERR_RATIO,
                'details' => [
                    'offered_ratio' => round($ratio, 3),
                    'min_ratio' => self::MIN_FAIR_RATE,
                    'max_ratio' => self::MAX_FAIR_RATE
                ]
            ];
        }

        $village = $this->getVillageWithOwner($villageId);
        if (!$village || (int)$village['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'You do not control this village.', 'code' => 'ERR_ALT_BLOCK'];
        }

        $marketLevel = $this->getMarketLevel($villageId);
        if ($marketLevel <= 0) {
            return ['success' => false, 'message' => 'Build a market before creating offers.', 'code' => EconomyError::ERR_CAP];
        }

        $tradersNeeded = $this->calculateTradersNeeded($offerResources);
        $availability = $this->getTraderAvailability($villageId);
        if ($availability['available'] < $tradersNeeded) {
            return ['success' => false, 'message' => 'Not enough free traders to post this offer.', 'code' => EconomyError::ERR_CAP];
        }

        if ($village['wood'] < $offerResources['wood'] || $village['clay'] < $offerResources['clay'] || $village['iron'] < $offerResources['iron']) {
            return ['success' => false, 'message' => 'Not enough resources to place this offer.', 'code' => EconomyError::ERR_CAP];
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

            $this->logTradeAudit('create_offer', [
                'actor_user_id' => $userId,
                'source_village_id' => $villageId,
                'offer_id' => $offerId,
                'offered' => $offerResources,
                'requested' => $requestResources,
                'traders_reserved' => $tradersNeeded
            ]);
            $this->logEconomyMetric('create_offer', [
                'actor_user_id' => $userId,
                'source_village_id' => $villageId,
                'offer_id' => $offerId,
                'offered_total' => array_sum($offerResources),
                'requested_total' => array_sum($requestResources),
                'traders_reserved' => $tradersNeeded
            ]);

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

            $this->logTradeAudit('cancel_offer', [
                'actor_user_id' => $userId,
                'source_village_id' => $villageId,
                'offer_id' => $offerId,
                'refunded' => [
                    'wood' => (int)$offer['offered_wood'],
                    'clay' => (int)$offer['offered_clay'],
                    'iron' => (int)$offer['offered_iron']
                ]
            ]);

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
            return ['success' => false, 'message' => 'Trading offers are not supported by this world yet.', 'code' => EconomyError::ERR_CAP];
        }

        $offerStmt = $this->conn->prepare("
            SELECT o.*, sv.x_coord AS source_x, sv.y_coord AS source_y, sv.user_id AS source_user_id,
                   sv.name AS source_name
            FROM trade_offers o
            JOIN villages sv ON o.source_village_id = sv.id
            WHERE o.id = ? AND o.status = 'open'
            LIMIT 1
        ");
        $offerStmt->bind_param("i", $offerId);
        $offerStmt->execute();
        $offer = $offerStmt->get_result()->fetch_assoc();
        $offerStmt->close();

        if (!$offer) {
            return ['success' => false, 'message' => 'Offer not available anymore.', 'code' => EconomyError::ERR_CAP];
        }

        // Enforce fair-market bounds even on older offers
        $totalOffered = (int)$offer['offered_wood'] + (int)$offer['offered_clay'] + (int)$offer['offered_iron'];
        $totalRequested = (int)$offer['requested_wood'] + (int)$offer['requested_clay'] + (int)$offer['requested_iron'];
        if ($totalOffered <= 0 || $totalRequested <= 0) {
            return ['success' => false, 'message' => 'Invalid offer payload.', 'code' => EconomyError::ERR_VALIDATION];
        }
        $ratio = $totalOffered / $totalRequested;
        if ($ratio < self::MIN_FAIR_RATE || $ratio > self::MAX_FAIR_RATE) {
            return [
                'success' => false,
                'message' => 'Trade offer ratio outside allowed range.',
                'code' => EconomyError::ERR_RATIO,
                'details' => [
                    'offered_ratio' => round($ratio, 3),
                    'min_ratio' => self::MIN_FAIR_RATE,
                    'max_ratio' => self::MAX_FAIR_RATE
                ]
            ];
        }

        $sourceVillage = $this->getVillageWithOwner((int)$offer['source_village_id']);
        if (!$sourceVillage) {
            return ['success' => false, 'message' => 'Source village not found for this offer.', 'code' => EconomyError::ERR_CAP];
        }

        $acceptingVillage = $this->getVillageWithOwner($acceptingVillageId);
        if (!$acceptingVillage || (int)$acceptingVillage['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'You do not control this village.', 'code' => 'ERR_ALT_BLOCK'];
        }
        if ((int)$acceptingVillage['id'] === (int)$offer['source_village_id']) {
            return ['success' => false, 'message' => 'You cannot accept your own offer.', 'code' => 'ERR_ALT_BLOCK'];
        }
        if ((int)$offer['source_user_id'] === $userId) {
            return ['success' => false, 'message' => 'You cannot accept your own offer from another village.', 'code' => 'ERR_ALT_BLOCK'];
        }

        // Anti-push: prevent lopsided sends to protected/low-point accounts
        $pushCheck = $this->enforceAntiPush($userId, (int)$offer['source_user_id']);
        if ($pushCheck !== true) {
            return $pushCheck;
        }

        // Market level check for the accepting village
        $marketLevel = $this->getMarketLevel($acceptingVillageId);
        if ($marketLevel <= 0) {
            return ['success' => false, 'message' => 'Build a market before accepting offers.', 'code' => EconomyError::ERR_CAP];
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
            return ['success' => false, 'message' => 'Not enough traders available to accept this offer.', 'code' => EconomyError::ERR_CAP];
        }

        if ($acceptingVillage['wood'] < $requiredFromAcceptor['wood'] ||
            $acceptingVillage['clay'] < $requiredFromAcceptor['clay'] ||
            $acceptingVillage['iron'] < $requiredFromAcceptor['iron']) {
            return [
                'success' => false,
                'message' => 'Not enough resources to accept this offer.',
                'code' => EconomyError::ERR_CAP,
                'details' => [
                    'cap_type' => 'storage',
                    'required' => $requiredFromAcceptor,
                    'available' => [
                        'wood' => (int)$acceptingVillage['wood'],
                        'clay' => (int)$acceptingVillage['clay'],
                        'iron' => (int)$acceptingVillage['iron']
                    ]
                ]
            ];
        }

        // Prevent overflow at the receiving ends before creating routes
        $headroomAcceptor = $this->checkStorageHeadroom($acceptingVillage, [
            'wood' => (int)$offer['offered_wood'],
            'clay' => (int)$offer['offered_clay'],
            'iron' => (int)$offer['offered_iron'],
        ]);
        if ($headroomAcceptor !== true) {
            return $headroomAcceptor;
        }

        $headroomSource = $this->checkStorageHeadroom($sourceVillage, [
            'wood' => (int)$offer['requested_wood'],
            'clay' => (int)$offer['requested_clay'],
            'iron' => (int)$offer['requested_iron'],
        ]);
        if ($headroomSource !== true) {
            return $headroomSource;
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

            $this->logTradeAudit('accept_offer', [
                'actor_user_id' => $userId,
                'offer_id' => $offerId,
                'source_village_id' => $acceptingVillageId,
                'offer_village_id' => $offer['source_village_id'],
                'requested' => [
                    'wood' => $offer['requested_wood'],
                    'clay' => $offer['requested_clay'],
                    'iron' => $offer['requested_iron']
                ],
                'offered' => [
                    'wood' => $offer['offered_wood'],
                    'clay' => $offer['offered_clay'],
                    'iron' => $offer['offered_iron']
                ],
                'traders_used' => [
                    'offer_side' => $offer['merchants_required'],
                    'acceptor_side' => $tradersNeededAcceptor
                ]
            ]);
            $this->logEconomyMetric('accept_offer', [
                'actor_user_id' => $userId,
                'offer_id' => $offerId,
                'source_village_id' => $acceptingVillageId,
                'offer_village_id' => $offer['source_village_id'],
                'offered_total' => $offer['offered_wood'] + $offer['offered_clay'] + $offer['offered_iron'],
                'requested_total' => $offer['requested_wood'] + $offer['requested_clay'] + $offer['requested_iron'],
                'traders_used_offer' => $offer['merchants_required'],
                'traders_used_acceptor' => $tradersNeededAcceptor
            ]);

            return [
                'success' => true,
                'arrival_time' => strtotime($arrival),
                'traders_used' => $tradersNeededAcceptor
            ];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Could not accept offer: ' . $e->getMessage(), 'code' => EconomyError::ERR_CAP];
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

    /**
     * Block resource pushes from strong to protected/low-point players.
     */
    private function enforceAntiPush(int $senderUserId, int $targetUserId)
    {
        if ($senderUserId <= 0 || $targetUserId <= 0 || $senderUserId === $targetUserId) {
            return true;
        }

        $senderProfile = $this->getUserProfile($senderUserId);
        $targetProfile = $this->getUserProfile($targetUserId);
        if (!$senderProfile || !$targetProfile) {
            return true; // fail open if we cannot load profiles
        }

        // Hard block if accounts are flagged or share the same fingerprint/IP hash
        $flaggedUserId = $this->getAltFlaggedUserId($senderProfile, $targetProfile);
        if ($flaggedUserId !== null) {
            return [
                'success' => false,
                'message' => 'Trade blocked by account protection.',
                'code' => EconomyError::ERR_ALT_BLOCK,
                'details' => ['reason' => 'alt_flag', 'user_id' => $flaggedUserId]
            ];
        }

        if ($this->isAltLinkSuspicious($senderProfile, $targetProfile)) {
            return [
                'success' => false,
                'message' => 'Trade blocked: accounts appear linked (same fingerprint/IP).',
                'code' => EconomyError::ERR_ALT_BLOCK,
                'details' => ['reason' => 'alt_link']
            ];
        }

        $senderPts = (int)($senderProfile['points'] ?? 0);
        $targetPts = (int)($targetProfile['points'] ?? 0);

        $protectedThreshold = defined('TRADE_POWER_DELTA_PROTECTED_POINTS')
            ? (int)TRADE_POWER_DELTA_PROTECTED_POINTS
            : (defined('NEWBIE_PROTECTION_POINTS_CAP') ? (int)NEWBIE_PROTECTION_POINTS_CAP : 200);
        $ratioThreshold = defined('TRADE_POWER_DELTA_BLOCK_RATIO')
            ? (float)TRADE_POWER_DELTA_BLOCK_RATIO
            : self::PUSH_POINTS_RATIO;

        $minPoints = min($senderPts, $targetPts);
        $maxPoints = max($senderPts, $targetPts);
        $ratio = $minPoints > 0 ? ($maxPoints / $minPoints) : ($maxPoints > 0 ? INF : 1);

        $senderProtected = $this->isUserProtectedForTrades($senderProfile, $protectedThreshold);
        $targetProtected = $this->isUserProtectedForTrades($targetProfile, $protectedThreshold);

        if (($targetProtected || $senderProtected || $minPoints < $protectedThreshold) && $ratio >= $ratioThreshold) {
            return [
                'success' => false,
                'message' => 'Trade blocked: power gap too high for protected account.',
                'code' => EconomyError::ERR_ALT_BLOCK,
                'details' => [
                    'reason' => 'power_delta',
                    'source_points' => $senderPts,
                    'target_points' => $targetPts,
                    'ratio' => is_infinite($ratio) ? 'inf' : round($ratio, 2),
                    'threshold' => $ratioThreshold
                ]
            ];
        }

        return true;
    }

    private function isUserProtectedForTrades(array $profile, int $protectedThreshold): bool
    {
        $points = (int)($profile['points'] ?? 0);
        $isFlagProtected = $this->userColumnExists('is_protected') && !empty($profile['is_protected']);
        $timeProtected = false;
        $hours = defined('BEGINNER_PROTECTION_HOURS') ? (int)BEGINNER_PROTECTION_HOURS : 72;
        if ($hours > 0 && isset($profile['created_at'])) {
            $raw = $profile['created_at'];
            $createdTs = is_numeric($raw) ? (int)$raw : strtotime((string)$raw);
            if ($createdTs) {
                $timeProtected = (time() - $createdTs) < ($hours * 3600);
            }
        }

        return $isFlagProtected || $points < $protectedThreshold || $timeProtected;
    }

    private function getAltFlaggedUserId(array $sourceProfile, array $targetProfile): ?int
    {
        $col = $this->getAltFlagColumn();
        if ($col === null) {
            return null;
        }
        if (!empty($sourceProfile[$col])) {
            return isset($sourceProfile['id']) ? (int)$sourceProfile['id'] : null;
        }
        if (!empty($targetProfile[$col])) {
            return isset($targetProfile['id']) ? (int)$targetProfile['id'] : null;
        }
        return null;
    }

    private function isAltLinkSuspicious(array $sourceProfile, array $targetProfile): bool
    {
        if (!defined('TRADE_ALT_IP_BLOCK_ENABLED') || !TRADE_ALT_IP_BLOCK_ENABLED) {
            return false;
        }
        $sourceFingerprint = $this->getProfileFingerprint($sourceProfile);
        $targetFingerprint = $this->getProfileFingerprint($targetProfile);
        return $sourceFingerprint !== null
            && $sourceFingerprint !== ''
            && $sourceFingerprint === $targetFingerprint;
    }

    private function getProfileFingerprint(array $profile): ?string
    {
        foreach ($this->getIdentityColumns() as $col) {
            if (!empty($profile[$col])) {
                return (string)$profile[$col];
            }
        }
        return null;
    }

    private function getUserProfile(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        if (array_key_exists($userId, $this->userProfileCache)) {
            return $this->userProfileCache[$userId];
        }

        $columns = ['id'];
        if ($this->userColumnExists('points')) {
            $columns[] = 'points';
        }
        if ($this->userColumnExists('created_at')) {
            $columns[] = 'created_at';
        }
        $altCol = $this->getAltFlagColumn();
        if ($altCol !== null) {
            $columns[] = $altCol;
        }
        foreach ($this->getIdentityColumns() as $col) {
            $columns[] = $col;
        }
        if ($this->userColumnExists('is_protected')) {
            $columns[] = 'is_protected';
        }
        $columns = array_unique($columns);

        $select = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
        $stmt = $this->conn->prepare("SELECT {$select} FROM users WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return $this->userProfileCache[$userId] = null;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $this->userProfileCache[$userId] = $row ?: null;
        return $this->userProfileCache[$userId];
    }

    private function getIdentityColumns(): array
    {
        if ($this->identityColumns !== null) {
            return $this->identityColumns;
        }
        $candidates = ['ip_hash', 'last_ip_hash', 'last_ip', 'fingerprint'];
        $cols = [];
        foreach ($candidates as $col) {
            if ($this->userColumnExists($col)) {
                $cols[] = $col;
            }
        }
        $this->identityColumns = $cols;
        return $cols;
    }

    private function getAltFlagColumn(): ?string
    {
        if ($this->altFlagColumn === '') {
            return null;
        }
        if ($this->altFlagColumn !== null) {
            return $this->altFlagColumn;
        }
        foreach (['is_flagged_alt', 'alt_flag', 'is_alt'] as $candidate) {
            if ($this->userColumnExists($candidate)) {
                $this->altFlagColumn = $candidate;
                return $this->altFlagColumn;
            }
        }
        $this->altFlagColumn = '';
        return null;
    }

    private function userColumnExists(string $column): bool
    {
        if (isset($this->userColumnExistsCache[$column])) {
            return $this->userColumnExistsCache[$column];
        }

        $exists = false;
        try {
            if ($this->isSqlite()) {
                $stmt = $this->conn->prepare("PRAGMA table_info(users)");
                if ($stmt && $stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res) {
                        while ($row = $res->fetch_assoc()) {
                            if (isset($row['name']) && $row['name'] === $column) {
                                $exists = true;
                                break;
                            }
                        }
                    }
                }
                if ($stmt) {
                    $stmt->close();
                }
            } else {
                $stmt = $this->conn->prepare("SHOW COLUMNS FROM users LIKE ?");
                if ($stmt) {
                    $stmt->bind_param("s", $column);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $exists = $res && $res->num_rows > 0;
                    $stmt->close();
                }
            }
        } catch (Throwable $e) {
            $exists = false;
        }

        $this->userColumnExistsCache[$column] = $exists;
        return $exists;
    }

    private function quoteIdentifier(string $identifier): string
    {
        $safe = str_replace('`', '``', $identifier);
        return "`{$safe}`";
    }

    /**
     * Validate that incoming resources will not exceed target storage capacity.
     */
    private function checkStorageHeadroom(array $village, array $incoming)
    {
        $capacity = isset($village['warehouse_capacity']) ? (int)$village['warehouse_capacity'] : 0;
        if ($capacity <= 0) {
            return true; // no cap data; fail open
        }
        $projected = [
            'wood' => (int)($village['wood'] ?? 0) + ($incoming['wood'] ?? 0),
            'clay' => (int)($village['clay'] ?? 0) + ($incoming['clay'] ?? 0),
            'iron' => (int)($village['iron'] ?? 0) + ($incoming['iron'] ?? 0),
        ];
        foreach ($projected as $resKey => $amt) {
            if ($amt > $capacity) {
                return [
                    'success' => false,
                    'message' => 'Target storage capacity exceeded for ' . $resKey . '.',
                    'code' => EconomyError::ERR_CAP,
                    'details' => [
                        'cap_type' => 'storage',
                        'resource' => $resKey,
                        'capacity' => $capacity,
                        'projected' => $projected[$resKey]
                    ]
                ];
            }
        }
        return true;
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

    /**
     * Append trade/a id audit log (append-only).
     */
    private function logTradeAudit(string $action, array $payload): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir . '/trade_audit.log';
        $entry = [
            'ts' => date('c'),
            'action' => $action,
            'world_id' => defined('CURRENT_WORLD_ID') ? CURRENT_WORLD_ID : null,
            'ip_hash' => $this->hashValue($_SERVER['REMOTE_ADDR'] ?? ''),
            'ua_hash' => $this->hashValue($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'data' => $payload
        ];
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES);
        if ($line !== false) {
            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    private function hashValue(string $value): string
    {
        return $value !== '' ? hash('sha256', $value) : '';
    }

    private function logEconomyMetric(string $action, array $payload): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir . '/economy_metrics.log';
        $entry = [
            'ts' => date('c'),
            'action' => $action,
            'world_id' => defined('CURRENT_WORLD_ID') ? CURRENT_WORLD_ID : null,
            'ip_hash' => $this->hashValue($_SERVER['REMOTE_ADDR'] ?? ''),
            'ua_hash' => $this->hashValue($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'data' => $payload
        ];
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES);
        if ($line !== false) {
            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
