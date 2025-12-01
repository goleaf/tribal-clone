<?php
declare(strict_types=1);

/**
 * Village manager inspired by the legacy VeryOldTemplate classes.
 */
class VillageManager
{
    private $conn;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    /**
     * Fetches basic village info.
     */
    public function getVillageInfo($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM villages 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $village_info = $result->fetch_assoc();
        $stmt->close();

        if ($village_info) {
            $this->regenVillageLoyalty($village_info);
        }

        return $village_info;
    }

    /**
     * Alias for getVillageInfo (backward compatibility).
     */
    public function getVillageDetails($village_id)
    {
        return $this->getVillageInfo($village_id);
    }

    /**
     * Regenerate loyalty over time (+1 per hour idle) up to 100.
     * Updates the passed village array by reference with refreshed loyalty.
     */
    private function regenVillageLoyalty(array &$village): void
    {
        if (!isset($village['loyalty'])) {
            return;
        }

        $loyaltyCap = $this->getEffectiveLoyaltyCap((int)$village['id'], $village);
        $loyalty = (float)$village['loyalty'];
        $lastUpdate = isset($village['last_loyalty_update']) ? strtotime((string)$village['last_loyalty_update']) : null;

        if ($loyalty >= $loyaltyCap - 0.01) {
            return;
        }

        $now = time();
        if (!$lastUpdate || $lastUpdate <= 0) {
            $lastUpdate = $now;
        }
        $elapsedSeconds = $now - $lastUpdate;
        if ($elapsedSeconds <= 0) {
            return;
        }

        $regenPerHour = $this->getLoyaltyRegenPerHour((int)$village['id'], $village);
        $gain = ($regenPerHour / 3600) * $elapsedSeconds;

        if ($gain <= 0.01) {
            // Still update the timestamp to avoid reprocessing tiny deltas repeatedly
            $village['last_loyalty_update'] = date('Y-m-d H:i:s', $now);
            return;
        }

        $newLoyalty = min($loyaltyCap, $loyalty + $gain);
        $village['loyalty'] = (int)round($newLoyalty);
        $village['last_loyalty_update'] = date('Y-m-d H:i:s', $now);

        $stmt = $this->conn->prepare("UPDATE villages SET loyalty = ?, last_loyalty_update = ? WHERE id = ?");
        if ($stmt) {
            $loyaltyToStore = (int)round($newLoyalty);
            $stmt->bind_param("isi", $loyaltyToStore, $village['last_loyalty_update'], $village['id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
    * Effective loyalty cap (capital + bonuses/penalties).
    */
    public function getEffectiveLoyaltyCap(int $villageId, ?array $village = null): float
    {
        $context = $this->getLoyaltyContext($villageId, $village);
        return $context['cap'];
    }

    /**
     * Loyalty regen per hour (uses hybrid rules).
     */
    public function getLoyaltyRegenPerHour(int $villageId, ?array $village = null): float
    {
        $context = $this->getLoyaltyContext($villageId, $village);
        $cap = $context['cap'];
        $hqLevel = $context['hq_level'];
        $churchLevel = $context['church_level'];

        // Base: 5% of cap per day
        $perDay = $cap * 0.05;

        // HQ/Palace bonus: +1% per 5 levels per day (max via cap already bounded)
        $perDay += floor($hqLevel / 5) * 0.01 * $cap;

        // Tribe loyalty building proxy: any church gives +5% per day
        if ($churchLevel > 0) {
            $perDay += 0.05 * $cap;
        }

        // Active defense: +10% per successful defense in last 24h
        if ($this->hasRecentSuccessfulDefense($villageId, 24)) {
            $perDay += 0.10 * $cap;
        }

        return $perDay / 24.0;
    }

    /**
     * How much loyalty drop is resisted or amplified (multiplier).
     */
    public function getLoyaltyDropMultiplier(int $villageId, ?array $village = null): float
    {
        $context = $this->getLoyaltyContext($villageId, $village);
        return $context['drop_multiplier'];
    }

    /**
     * Gather loyalty-related modifiers in one place.
     */
    private function getLoyaltyContext(int $villageId, ?array $village = null): array
    {
        $stub = $village ?: $this->getMinimalVillageRow($villageId);
        $baseCap = (!empty($stub['is_capital'])) ? 150.0 : 100.0;

        $buildingLevels = $this->getBuildingLevels($villageId, ['main_building', 'wall', 'church', 'first_church']);
        $hqLevel = $buildingLevels['main_building'] ?? 0;
        $wallLevel = $buildingLevels['wall'] ?? 0;
        $churchLevel = $buildingLevels['church'] ?? ($buildingLevels['first_church'] ?? 0);

        // Positive bonuses
        $bonusPct = 0.0;
        $bonusPct += min(0.30, $hqLevel * 0.01);           // +1% per HQ level, max 30%
        $bonusPct += min(0.15, $wallLevel * 0.005);        // +0.5% per wall level, max 15%

        $garrisonUnits = $this->countVillageUnits($villageId);
        $bonusPct += min(0.20, ($garrisonUnits / 10) * 0.001); // +0.1% per 10 units, max 20%

        // Penalties
        $penaltyPct = 0.0;
        $recentAttackPenalty = $this->countRecentAttacks($villageId, 24) * 0.05; // -5% per attack in last 24h
        $penaltyPct += min(0.25, $recentAttackPenalty);

        $distancePenalty = 0.0;
        if (empty($stub['is_capital'])) {
            $distancePenalty = $this->getDistancePenalty($stub);
            $penaltyPct += $distancePenalty;
        }

        $netPct = $bonusPct - $penaltyPct;
        $cap = max(50.0, round($baseCap * (1 + $netPct), 2));

        // Multiplier applied to loyalty drop: <1 resists, >1 amplifies
        $dropMultiplier = 1 - $bonusPct + $penaltyPct;
        $dropMultiplier = max(0.5, min(1.3, $dropMultiplier));

        return [
            'cap' => $cap,
            'base_cap' => $baseCap,
            'bonus_pct' => $bonusPct,
            'penalty_pct' => $penaltyPct,
            'hq_level' => $hqLevel,
            'wall_level' => $wallLevel,
            'church_level' => $churchLevel,
            'garrison_units' => $garrisonUnits,
            'drop_multiplier' => $dropMultiplier,
            'distance_penalty' => $distancePenalty,
            'recent_attack_penalty' => $recentAttackPenalty,
        ];
    }

    /**
     * Minimal village row for loyalty calculations.
     */
    private function getMinimalVillageRow(int $villageId): array
    {
        $columns = ['id', 'user_id', 'x_coord', 'y_coord', 'loyalty'];
        if ($this->villageColumnExists('is_capital')) {
            $columns[] = 'is_capital';
        }
        if ($this->villageColumnExists('conquered_at')) {
            $columns[] = 'conquered_at';
        }
        if ($this->villageColumnExists('last_loyalty_update')) {
            $columns[] = 'last_loyalty_update';
        }
        $sql = "SELECT " . implode(', ', $columns) . " FROM villages WHERE id = ? LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return ['id' => $villageId, 'user_id' => null, 'x_coord' => 0, 'y_coord' => 0, 'loyalty' => 100, 'is_capital' => 0];
        }

        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $row['is_capital'] = $row['is_capital'] ?? 0;
        return $row;
    }

    /**
     * Check village column existence (cached).
     */
    private function villageColumnExists(string $column): bool
    {
        static $cache = [];
        if (isset($cache[$column])) {
            return $cache[$column];
        }
        if (function_exists('dbColumnExists')) {
            return $cache[$column] = dbColumnExists($this->conn, 'villages', $column);
        }
        return $cache[$column] = false;
    }

    /**
     * Bulk fetch specific building levels.
     */
    private function getBuildingLevels(int $villageId, array $internalNames): array
    {
        if (empty($internalNames)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($internalNames), '?'));
        $types = str_repeat('s', count($internalNames));

        $sql = "
            SELECT bt.internal_name, vb.level
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name IN ($placeholders)
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        // Bind village id + internal names
        $types = 'i' . $types;
        $params = array_merge([$types, $villageId], $internalNames);
        $stmt->bind_param(...$this->refValues($params));
        $stmt->execute();
        $res = $stmt->get_result();

        $levels = [];
        while ($row = $res->fetch_assoc()) {
            $levels[$row['internal_name']] = (int)$row['level'];
        }
        $stmt->close();
        return $levels;
    }

    /**
     * Count total stationed units (for garrison bonus).
     */
    private function countVillageUnits(int $villageId): int
    {
        $stmt = $this->conn->prepare("SELECT SUM(count) AS total FROM village_units WHERE village_id = ?");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Count recent attacks hitting this village.
     */
    private function countRecentAttacks(int $villageId, int $hours): int
    {
        if (!function_exists('dbTableExists') || !dbTableExists($this->conn, 'battle_reports')) {
            return 0;
        }
        $since = date('Y-m-d H:i:s', time() - ($hours * 3600));
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM battle_reports WHERE target_village_id = ? AND battle_time >= ?");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("is", $villageId, $since);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Whether the village won a defense recently.
     */
    private function hasRecentSuccessfulDefense(int $villageId, int $hours): bool
    {
        if (!function_exists('dbTableExists') || !dbTableExists($this->conn, 'battle_reports')) {
            return false;
        }
        $since = date('Y-m-d H:i:s', time() - ($hours * 3600));
        $stmt = $this->conn->prepare("
            SELECT 1 FROM battle_reports 
            WHERE target_village_id = ? AND battle_time >= ? AND attacker_won = 0 
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("is", $villageId, $since);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return !empty($row);
    }

    /**
     * Capital distance penalty: -1% per 10 tiles, max -20%.
     */
    private function getDistancePenalty(array $villageStub): float
    {
        if (empty($villageStub['user_id']) || !$this->villageColumnExists('is_capital')) {
            return 0.0;
        }
        $capital = $this->getCapitalCoords((int)$villageStub['user_id']);
        if (!$capital) {
            return 0.0;
        }
        $dx = ($capital['x_coord'] ?? 0) - (int)($villageStub['x_coord'] ?? 0);
        $dy = ($capital['y_coord'] ?? 0) - (int)($villageStub['y_coord'] ?? 0);
        $distance = sqrt(($dx * $dx) + ($dy * $dy));
        return min(0.20, floor($distance / 10) * 0.01);
    }

    private function getCapitalCoords(int $userId): ?array
    {
        if (!$this->villageColumnExists('is_capital')) {
            return null;
        }
        $stmt = $this->conn->prepare("SELECT x_coord, y_coord FROM villages WHERE user_id = ? AND is_capital = 1 LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }

        // Fallback to first village
        $stmt = $this->conn->prepare("SELECT x_coord, y_coord FROM villages WHERE user_id = ? ORDER BY id ASC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Helper to bind params with variadics.
     */
    private function refValues(array $arr): array
    {
        // mysqli bind_param expects references
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }

    /**
     * Updates village resources based on production time.
     * Delegates logic to ResourceManager.
     */
    public function updateResources($village_id): bool
    {
        // Fetch village data
        $village = $this->getVillageInfo($village_id);
        if (!$village) {
            return false; // Village not found
        }
        
        // Check whether enough time has passed for production
        $last_update = strtotime($village['last_resource_update']);
        $elapsed_seconds = time() - $last_update;

        if ($elapsed_seconds <= 0) {
            return false; // Nothing changed
        }

        // ResourceManager needs BuildingManager to compute production
        require_once __DIR__ . '/BuildingManager.php';
        // BuildingManager potrzebuje BuildingConfigManager
        require_once __DIR__ . '/BuildingConfigManager.php';

        // Instantiating managers inline; ideally use dependency injection.
        $buildingConfigManager = new BuildingConfigManager($this->conn);
        $buildingManager = new BuildingManager($this->conn, $buildingConfigManager);

        require_once __DIR__ . '/ResourceManager.php';
        $resourceManager = new ResourceManager($this->conn, $buildingManager);

        // Delegate resource update
        // ResourceManager::updateVillageResources requires full village array
        $updated_village = $resourceManager->updateVillageResources($village);
        
        // updateVillageResources already persists changes; return status only.
        return true;
    }

    /**
     * Fetches all buildings in the village.
     */
    public function getVillageBuildings($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT vb.id, vb.level, 
                   bt.id AS building_type_id, bt.internal_name, bt.name
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ?
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $buildings = [];
        while ($row = $result->fetch_assoc()) {
            // Prepare building data, including upgrade queue status
            $building = $row;
            $building['is_upgrading'] = false; // Default: not upgrading
            $building['queue_finish_time'] = null;
            $building['queue_level_after'] = null;

            // Check build queue for this specific village_buildings.id
            $stmt_queue = $this->conn->prepare("
                SELECT level, finish_time
                FROM building_queue
                WHERE village_building_id = ?
                LIMIT 1
            ");
            $stmt_queue->bind_param("i", $building['id']);
            $stmt_queue->execute();
            $queue_result = $stmt_queue->get_result();
            $queue_item = $queue_result->fetch_assoc();
            $stmt_queue->close();

            if ($queue_item) {
                $building['is_upgrading'] = true;
                $building['queue_finish_time'] = strtotime($queue_item['finish_time']);
                $building['queue_level_after'] = (int)$queue_item['level'];
            }
            
            $buildings[$building['internal_name']] = $building; // Use internal_name as key
        }
        $stmt->close();

        return $buildings;
    }

    /**
     * Processes completed tasks for the village (build, recruit, research).
     * Should be called e.g., at the start of game.php.
     * Returns an array of messages to display.
     */
    public function processCompletedTasksForVillage(int $village_id): array
    {
        $messages = [];
        $village = $this->getVillageInfo($village_id);
        if (!$village) {
            return $messages;
        }
        $userId = (int)$village['user_id'];
        
        // 1. Update resources (covers offline production)
        $this->updateResources($village_id);
        // Refresh village after resource update
        $village = $this->getVillageInfo($village_id);

        // Required managers
        require_once __DIR__ . '/BuildingManager.php';
        require_once __DIR__ . '/UnitManager.php';
        require_once __DIR__ . '/ResearchManager.php';
        require_once __DIR__ . '/BuildingConfigManager.php';
        require_once __DIR__ . '/ResourceManager.php';
        require_once __DIR__ . '/AchievementManager.php';
        require_once __DIR__ . '/TradeManager.php';
        require_once __DIR__ . '/NotificationManager.php';

        // Instantiate managers (DI would be better)
        $buildingConfigManager = new BuildingConfigManager($this->conn);
        $buildingManager = new BuildingManager($this->conn, $buildingConfigManager);
        $unitManager = new UnitManager($this->conn);
        $researchManager = new ResearchManager($this->conn);
        $achievementManager = new AchievementManager($this->conn);
        $tradeManager = new TradeManager($this->conn);
        $notificationManager = new NotificationManager($this->conn);
        if (!class_exists('ReportManager')) {
            require_once __DIR__ . '/ReportManager.php';
        }
        $reportManager = class_exists('ReportManager') ? new ReportManager($this->conn) : null;
        // BattleManager could be added here when needed.

        // Evaluate snapshot-based achievements and resource milestones
        if ($userId) {
            $achievementManager->evaluateAutoUnlocks($userId);
            $achievementManager->checkResourceStock($userId, $village, $village_id);
        }

        // 2. Complete finished building tasks
        $completed_buildings = $this->processBuildingQueue($village_id);
        foreach ($completed_buildings as $item) {
            $messages[] = "<p class='success-message'>Upgrade completed: <b>" . htmlspecialchars($item['name']) . "</b> to level " . $item['level'] . ".</p>";
            if ($userId) {
                $achievementManager->checkBuildingLevel($userId, $item['internal_name'], (int)$item['level'], $village_id);
                $notificationManager->addNotification(
                    $userId,
                    sprintf('Upgrade completed: %s to level %d.', $item['name'], $item['level']),
                    'success',
                    '/game/game.php'
                );
                if ($reportManager) {
                    $reportManager->addReport(
                        $userId,
                        'system',
                        sprintf('Building completed: %s %s', $item['name'], $item['level']),
                        ['village_id' => $village_id, 'building' => $item['internal_name'], 'level' => (int)$item['level']]
                    );
                }
            }
        }

        // 3. Complete unit recruitment tasks
        $recruitmentUpdate = $unitManager->processRecruitmentQueue($village_id);
         if (!empty($recruitmentUpdate['completed_queues'])) {
            foreach ($recruitmentUpdate['completed_queues'] as $queue) {
                $messages[] = "<p class='success-message'>Recruitment completed: " . $queue['count'] . " units of '" . htmlspecialchars($queue['unit_name']) . "'.</p>";
                if ($userId) {
                    $achievementManager->addUnitsTrainedProgress($userId, (int)($queue['produced_now'] ?? $queue['count'] ?? 0));
                    $notificationManager->addNotification(
                        $userId,
                        sprintf('Recruitment completed: %d × %s.', $queue['count'], $queue['unit_name']),
                        'success',
                        '/game/game.php'
                    );
                    if ($reportManager) {
                        $reportManager->addReport(
                            $userId,
                            'system',
                            sprintf('Recruitment completed: %d × %s', $queue['count'], $queue['unit_name']),
                            ['village_id' => $village_id, 'unit_type_id' => $queue['unit_type_id'] ?? null, 'count' => $queue['count']]
                        );
                    }
                }
            }
        }
         if (!empty($recruitmentUpdate['updated_queues']) && empty($recruitmentUpdate['completed_queues'])) {
            foreach ($recruitmentUpdate['updated_queues'] as $update) {
                 $messages[] = "<p class='success-message'>Produced " . $update['units_finished'] . " units of '" . htmlspecialchars($update['unit_name']) . "'. Recruitment continues...</p>";
                 if ($userId) {
                     $achievementManager->addUnitsTrainedProgress($userId, (int)($update['produced_now'] ?? $update['units_finished'] ?? 0));
                 }
            }
        }

        // 4. Complete research tasks
        $researchUpdate = $researchManager->processResearchQueue($village_id);
         if (!empty($researchUpdate['completed_research'])) {
            foreach ($researchUpdate['completed_research'] as $research) {
                $messages[] = "<p class='success-message'>Research completed: <b>" . htmlspecialchars($research['research_name']) . "</b> to level " . $research['level'] . ".</p>";
                if ($userId) {
                    $notificationManager->addNotification(
                        $userId,
                        sprintf('Research completed: %s to level %d.', $research['research_name'], $research['level']),
                        'info',
                        '/game/game.php'
                    );
                    if ($reportManager) {
                        $reportManager->addReport(
                            $userId,
                            'system',
                            sprintf('Research completed: %s %d', $research['research_name'], $research['level']),
                            ['village_id' => $village_id, 'research' => $research['research_name'], 'level' => $research['level']]
                        );
                    }
                }
            }
        }

        // 5. Complete finished trade routes involving this village
        $tradeMessages = $tradeManager->processArrivedTradesForVillage($village_id);
        foreach ($tradeMessages as $tradeMessage) {
            $messages[] = "<p class='success-message'>" . htmlspecialchars($tradeMessage) . "</p>";
            if ($userId) {
                $notificationManager->addNotification(
                    $userId,
                    $tradeMessage,
                    'success',
                    '/game/game.php'
                );
            }
        }

        // 6. Attack processing is handled in BattleManager (see game.php usage).

        // 7. Refresh points after any building/resource changes
        require_once __DIR__ . '/PointsManager.php';
        $pointsManager = new PointsManager($this->conn);
        $pointsManager->updateVillagePoints($village_id, true);

        return $messages;
    }

    /**
     * Processes the building_queue, updating levels and clearing entries.
     * Returns a list of completed build tasks.
     */
    public function processBuildingQueue(int $village_id): array
    {
        $completed_queue_items = [];
        
        // Fetch completed builds from the queue
        $stmt_check_finished = $this->conn->prepare("SELECT bq.id, bq.village_building_id, bq.level, bq.is_demolition, bq.refund_wood, bq.refund_clay, bq.refund_iron, bt.name, bt.internal_name FROM building_queue bq JOIN building_types bt ON bq.building_type_id = bt.id WHERE bq.village_id = ? AND bq.finish_time <= NOW()");
        $stmt_check_finished->bind_param("i", $village_id);
        $stmt_check_finished->execute();
        $result_finished = $stmt_check_finished->get_result();
        
        // Begin transaction for processing completed builds
        $this->conn->begin_transaction();

        try {
            while ($finished_building_queue_item = $result_finished->fetch_assoc()) {
                // Update building level in village_buildings
                $stmt_update_vb_level = $this->conn->prepare("UPDATE village_buildings SET level = ? WHERE id = ?");
                $stmt_update_vb_level->bind_param("ii", $finished_building_queue_item['level'], $finished_building_queue_item['village_building_id']);
                if (!$stmt_update_vb_level->execute()) {
                    throw new Exception("Failed to update building level after completion for village_building_id " . $finished_building_queue_item['village_building_id'] . ".");
                }
                $stmt_update_vb_level->close();

                // Apply demolition refunds (if any)
                if (!empty($finished_building_queue_item['is_demolition'])) {
                    $refundWood = (int)$finished_building_queue_item['refund_wood'];
                    $refundClay = (int)$finished_building_queue_item['refund_clay'];
                    $refundIron = (int)$finished_building_queue_item['refund_iron'];
                    if ($refundWood || $refundClay || $refundIron) {
                        $stmt_refund = $this->conn->prepare("
                            UPDATE villages
                            SET wood = wood + ?, clay = clay + ?, iron = iron + ?
                            WHERE id = ?
                        ");
                        if ($stmt_refund) {
                            $stmt_refund->bind_param("iiii", $refundWood, $refundClay, $refundIron, $village_id);
                            $stmt_refund->execute();
                            $stmt_refund->close();
                        }
                    }
                }

                // Remove task from build queue
                $stmt_delete_queue_item = $this->conn->prepare("DELETE FROM building_queue WHERE id = ?");
                $stmt_delete_queue_item->bind_param("i", $finished_building_queue_item['id']);
                if (!$stmt_delete_queue_item->execute()) {
                     throw new Exception("Failed to remove queue task after build completion for id " . $finished_building_queue_item['id'] . ".");
                }
                $stmt_delete_queue_item->close();
                
                $completed_queue_items[] = $finished_building_queue_item; // Add to completed list
            }

            // Commit transaction
            $this->conn->commit();

        } catch (Exception $e) {
            // Roll back transaction on error
            $this->conn->rollback();
             error_log("Error in processBuildingQueue for village {$village_id}: " . $e->getMessage());
            throw $e; // Re-throw
        } finally {
             $result_finished->free();
            $stmt_check_finished->close();
        }

        return $completed_queue_items;
    }

    /**
     * Updates village population capacity based on building levels.
     * Called after completing a building (e.g., farm).
     */
    public function updateVillagePopulation(int $village_id): bool
    {
        // Requires BuildingConfigManager to calculate farm capacity
         require_once __DIR__ . '/BuildingConfigManager.php';
         $buildingConfigManager = new BuildingConfigManager($this->conn); // Create instance (should be injected)

        // Fetch farm level
        $farm_level = 0;
        $farm_stmt = $this->conn->prepare("
            SELECT vb.level 
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = 'farm'
        ");
        $farm_stmt->bind_param("i", $village_id);
        $farm_stmt->execute();
        $farm_result = $farm_stmt->get_result();
        if ($farm_row = $farm_result->fetch_assoc()) {
            $farm_level = $farm_row['level'];
        }
        $farm_stmt->close();

        // Calculate farm capacity
        $max_population = $buildingConfigManager->calculateFarmCapacity($farm_level);
        
        // Ideally population should sum building/unit usage; currently only farm capacity is updated.

        $update_stmt = $this->conn->prepare("
            UPDATE villages 
            SET farm_capacity = ?
            WHERE id = ?
        ");
        $update_stmt->bind_param("ii", $max_population, $village_id);
        $result = $update_stmt->execute();
        $update_stmt->close();

        return $result;
    }

    /**
     * Creates a new village with starter buildings (transactional).
     */
    public function createVillage($user_id, $name = '', $x = null, $y = null)
    {
        // Fetch username
        $stmt_user = $this->conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $user = $stmt_user->get_result()->fetch_assoc();
        $stmt_user->close();
        $username = $user ? $user['username'] : 'Player';
        
        // Unique village name
        $village_name = $name ?: ("Village " . $username);
        
        // Respect player village limit if configured (per world)
        $villageLimit = defined('PLAYER_VILLAGE_LIMIT') ? (int)PLAYER_VILLAGE_LIMIT : 0;
        if ($villageLimit > 0) {
            $countStmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM villages WHERE user_id = ? AND world_id = ?");
            $countStmt->bind_param("ii", $user_id, CURRENT_WORLD_ID);
            $countStmt->execute();
            $countRow = $countStmt->get_result()->fetch_assoc();
            $countStmt->close();
            if (($countRow['cnt'] ?? 0) >= $villageLimit) {
                return ['success' => false, 'message' => 'Village limit reached for this player.'];
            }
        }

        // Random free coordinates within world bounds
        $worldSize = defined('WORLD_SIZE') ? max(10, (int)WORLD_SIZE) : 1000;
        $found = false;
        $tries = 0;
        do {
            $x_try = $x ?? random_int(0, $worldSize - 1);
            $y_try = $y ?? random_int(0, $worldSize - 1);
            $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM villages WHERE x_coord = ? AND y_coord = ? AND world_id = ?");
            $stmt->bind_param("iii", $x_try, $y_try, CURRENT_WORLD_ID);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
            if ($cnt == 0) { $found = true; $x = $x_try; $y = $y_try; }
            $tries++;
        } while (!$found && $tries < 200);
        if (!$found) return ['success'=>false,'message'=>'No free tiles found in the world bounds.'];
        
        // Starting resources and buildings
        $worldId = defined('CURRENT_WORLD_ID') ? CURRENT_WORLD_ID : (defined('INITIAL_WORLD_ID') ? INITIAL_WORLD_ID : 1);
        $wood = defined('INITIAL_WOOD') ? INITIAL_WOOD : 500;
        $clay = defined('INITIAL_CLAY') ? INITIAL_CLAY : 500;
        $iron = defined('INITIAL_IRON') ? INITIAL_IRON : 500;
        $warehouse = defined('INITIAL_WAREHOUSE_CAPACITY') ? INITIAL_WAREHOUSE_CAPACITY : 1000;
        $population = defined('INITIAL_POPULATION') ? INITIAL_POPULATION : 1;
        
        $stmt = $this->conn->prepare("INSERT INTO villages (user_id, world_id, name, x_coord, y_coord, wood, clay, iron, warehouse_capacity, population, last_resource_update) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisiiiiiii", $user_id, $worldId, $village_name, $x, $y, $wood, $clay, $iron, $warehouse, $population);
        if (!$stmt->execute()) {
            return ['success' => false, 'message' => 'Error while creating village: ' . $stmt->error];
        }
        $village_id = $stmt->insert_id;
        $stmt->close();
        
        // Starter buildings: town hall, sawmill, clay pit, iron mine, warehouse, farm (all level 1)
        $basic_buildings = [
            'main_building' => 1,
            'sawmill' => 1,
            'clay_pit' => 1,
            'iron_mine' => 1,
            'warehouse' => 1,
            'farm' => 1
        ];
        foreach ($basic_buildings as $building_name => $level) {
            $this->createBuildingInVillage($village_id, $building_name, $level);
        }
        $this->updateVillagePopulation($village_id);
        // Recalculate player points
        require_once __DIR__ . '/PointsManager.php';
        $pointsManager = new PointsManager($this->conn);
        $pointsManager->updateVillagePoints((int)$village_id, true);

        // Evaluate starting achievements (e.g., first village, initial buildings)
        require_once __DIR__ . '/AchievementManager.php';
        $achievementManager = new AchievementManager($this->conn);
        $achievementManager->evaluateAutoUnlocks((int)$user_id);

        return [
            'success' => true, 
            'message' => 'Village created successfully!', 
            'village_id' => $village_id
        ];
    }

    /**
     * Creates default buildings for a new village.
     * @param int $village_id Newly created village ID.
     * @return bool Sukces operacji.
     */
    private function createInitialBuildings(int $village_id): bool
    {
        // Requires BuildingConfigManager to fetch building types
         require_once __DIR__ . '/BuildingConfigManager.php';
         $buildingConfigManager = new BuildingConfigManager($this->conn); // Create instance (should be injected)

        // Fetch all building types from DB
        $buildingTypes = $buildingConfigManager->getAllBuildingConfigs(); // Potrzebna publiczna metoda

        if (empty($buildingTypes)) {
            error_log("Error: No building types in database during village creation.");
            return false; // Cannot create buildings if types are missing
        }

        $insert_stmt = $this->conn->prepare("
            INSERT INTO village_buildings (village_id, building_type_id, level)
            VALUES (?, ?, ?)
        ");
        
        foreach ($buildingTypes as $type) {
             // For a new village, all buildings start at 0 or 1 (main building)
             $initial_level = ($type['internal_name'] === 'main_building') ? 1 : 0;
             // Resolve building_type_id from internal_name
             $building_type_id = $type['id']; // id expected in config

            $insert_stmt->bind_param("iii", $village_id, $building_type_id, $initial_level);
            if (!$insert_stmt->execute()) {
                error_log("Error adding building '{$type['internal_name']}' to village {$village_id}: " . $insert_stmt->error);
                $insert_stmt->close();
                return false; // Failed to add building
            }
        }
        
        $insert_stmt->close();

        return true;
    }


    /**
     * Creates a single building in a village with a given level.
     * Used mainly during village initialization.
     * @deprecated Preferuj createInitialBuildings.
     */
    private function createBuildingInVillage($village_id, $building_internal_name, $level = 0)
    {
        // Fetch building type ID
        $type_stmt = $this->conn->prepare("
            SELECT id 
            FROM building_types 
            WHERE internal_name = ?
        ");
        $type_stmt->bind_param("s", $building_internal_name);
        $type_stmt->execute();
        $type_result = $type_stmt->get_result();
        
        if ($type_result->num_rows === 0) {
            return false;
        }
        
        $building_type_id = $type_result->fetch_assoc()['id'];
        $type_stmt->close();
        
        // Add building to the village
        $stmt = $this->conn->prepare("
            INSERT INTO village_buildings (village_id, building_type_id, level) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iii", $village_id, $building_type_id, $level);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Renames a village.
     */
    public function renameVillage($village_id, $user_id, $new_name)
    {
        // Verify the village belongs to the user
        $stmt = $this->conn->prepare("
            SELECT id 
            FROM villages 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $village_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'You do not have access to this village.'];
        }
        $stmt->close();
        
        // Rename village
        $update_stmt = $this->conn->prepare("
            UPDATE villages 
            SET name = ? 
            WHERE id = ?
        ");
        $update_stmt->bind_param("si", $new_name, $village_id);
        
        if (!$update_stmt->execute()) {
            return ['success' => false, 'message' => 'Error while changing name: ' . $update_stmt->error];
        }
        $update_stmt->close();
        
        return ['success' => true, 'message' => 'Village name has been changed.'];
    }

    /**
     * Gets all villages for a user.
     * @return array List of villages (or empty array).
     */
    public function getUserVillages(int $user_id): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, name, x_coord, y_coord
            FROM villages
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $villages = [];
        while ($row = $result->fetch_assoc()) {
            $villages[] = $row;
        }
        $stmt->close();

        return $villages;
    }

     /**
     * Gets only IDs of all villages for a user (e.g., for attack checks).
     * @return array List of village IDs (or empty array).
     */
    public function getUserVillageIds(int $user_id): array
    {
        $stmt = $this->conn->prepare("
            SELECT id
            FROM villages
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $village_ids = [];
        while ($row = $result->fetch_assoc()) {
            $village_ids[] = (int)$row['id'];
        }
        $stmt->close();

        return $village_ids;
    }

    /**
     * Gets the first (default) village for a user (e.g., after login).
     * @return array|null Village data or null if none.
     */
    public function getFirstVillage(int $user_id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, name, x_coord, y_coord, wood, clay, iron, warehouse_capacity, population, farm_capacity, last_resource_update
            FROM villages
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $village = $result->fetch_assoc();
        $stmt->close();

        return $village;
    }

    /**
     * Fetches current hourly production for a resource in a village.
     * @deprecated Prefer ResourceManager->getHourlyProductionRate
     */
    public function getResourceProduction($village_id, $resource_type)
    {
        // Deprecated; should be handled by ResourceManager. Temporary fallback:
        
        require_once __DIR__ . '/BuildingConfigManager.php';
        require_once __DIR__ . '/BuildingManager.php';
        require_once __DIR__ . '/ResourceManager.php';

        // Temporary instantiation
        $buildingConfigManager = new BuildingConfigManager($this->conn);
        $buildingManager = new BuildingManager($this->conn, $buildingConfigManager);
        $resourceManager = new ResourceManager($this->conn, $buildingManager);

        // Use ResourceManager to fetch production
        return $resourceManager->getHourlyProductionRate($village_id, $resource_type);
    }

    /**
     * Fetches the current (first in queue) build task for a village.
     * Returns task data or null if queue is empty.
     */
    public function getBuildingQueueItem(int $village_id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT bq.id, bq.village_building_id, bq.building_type_id, bq.level, bq.starts_at, bq.finish_time, 
                   bt.internal_name, bt.name
            FROM building_queue bq
            JOIN building_types bt ON bq.building_type_id = bt.id
            WHERE bq.village_id = ?
            ORDER BY bq.starts_at ASC
            LIMIT 1
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();

        return $item;
    }

    /**
     * Convenience wrapper to recalculate points for a village and its owner.
     */
    public function recalculateVillagePoints(int $villageId): int
    {
        require_once __DIR__ . '/PointsManager.php';
        $pointsManager = new PointsManager($this->conn);
        return $pointsManager->updateVillagePoints($villageId, true);
    }

    /**
     * Convenience wrapper to recalculate points for a player (and their tribe).
     */
    public function recalculatePlayerPoints(int $userId): int
    {
        require_once __DIR__ . '/PointsManager.php';
        $pointsManager = new PointsManager($this->conn);
        return $pointsManager->updatePlayerPoints($userId, true);
    }
} 
