<?php
/**
 * BattleManager handles battles between villages.
 */
class BattleManager
{
    private $conn;
    private $villageManager;
    private $buildingManager; // BuildingManager dependency
    private $reportManager; // Generic report log

    private const RANDOM_VARIANCE = 0.25; // +/- 25% luck
    private const FAITH_DEFENSE_PER_LEVEL = 0.05; // 5% per church level
    private const FIRST_CHURCH_DEFENSE_BONUS = 0.1; // Flat 10% if first church exists
    private const MIN_MORALE = 0.30;
    private const WINNER_MINIMUM_LOSS = 0.05; // winner always loses at least 5% of troops
    private const RAID_CASUALTY_FACTOR = 0.65; // raids inflict/take fewer losses
    private const RAID_LOOT_FACTOR = 0.6; // raids cap loot to 60% of stored resources
    private const WALL_BONUS_PER_LEVEL = 0.08;
    private const WORLD_UNIT_SPEED = 1.0; // fields per hour baseline
    private const PHASE_ORDER = ['infantry', 'cavalry', 'archer'];
    private const RESEARCH_BONUS_PER_LEVEL = 0.10; // +10% per smithy level
    private const LOYALTY_MIN = 0;
    private const LOYALTY_MAX = 100;
    private const LOYALTY_DROP_MIN = 20;
    private const LOYALTY_DROP_MAX = 35;

    /**
     * @param mysqli $conn Database connection
     * @param VillageManager $villageManager Village manager instance
     * @param BuildingManager $buildingManager Building manager instance
     */
    public function __construct($conn, VillageManager $villageManager, BuildingManager $buildingManager)
    {
        $this->conn = $conn;
        $this->villageManager = $villageManager;
        $this->buildingManager = $buildingManager;
        // Lazy-load ReportManager if available
        if (!class_exists('ReportManager')) {
            require_once __DIR__ . '/ReportManager.php';
        }
        $this->reportManager = new ReportManager($conn);
    }
    
    /**
     * Sends an attack from one village to another.
     * 
     * @param int $source_village_id Attacker village ID
     * @param int $target_village_id Target village ID
     * @param array $units_sent Map of unit type IDs to counts
     * @param string $attack_type Attack type ('attack', 'raid', 'support', 'spy')
     * @param string|null $target_building Target building for catapults
     * @return array Operation status
     */
    public function sendAttack($source_village_id, $target_village_id, $units_sent, $attack_type = 'attack', $target_building = null)
    {
        $attack_type = in_array($attack_type, ['attack', 'raid', 'support', 'spy', 'fake'], true) ? $attack_type : 'attack';

        // Ensure both villages exist
        $stmt_check_villages = $this->conn->prepare("
            SELECT 
                v1.id as source_id, v1.name as source_name, v1.x_coord as source_x, v1.y_coord as source_y, v1.user_id as source_user_id,
                v2.id as target_id, v2.name as target_name, v2.x_coord as target_x, v2.y_coord as target_y, v2.user_id as target_user_id
            FROM villages v1, villages v2
            WHERE v1.id = ? AND v2.id = ?
        ");
        $stmt_check_villages->bind_param("ii", $source_village_id, $target_village_id);
        $stmt_check_villages->execute();
        $result = $stmt_check_villages->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'One or both villages do not exist.'
            ];
        }
        
        $villages = $result->fetch_assoc();
        $stmt_check_villages->close();
        
        // Prevent attacking your own villages
        if ($villages['source_user_id'] === $villages['target_user_id'] && $attack_type !== 'support') {
            return [
                'success' => false,
                'error' => 'You cannot attack your own villages.'
            ];
        }

        // Require Rally Point to send any troops
        $rallyLevel = $this->buildingManager->getBuildingLevel((int)$source_village_id, 'rally_point');
        if ($rallyLevel <= 0) {
            return [
                'success' => false,
                'error' => 'You need a Rally Point to send troops from this village.'
            ];
        }
        
        // Check available units
        $stmt_check_units = $this->conn->prepare("
            SELECT unit_type_id, count 
            FROM village_units 
            WHERE village_id = ?
        ");
        $stmt_check_units->bind_param("i", $source_village_id);
        $stmt_check_units->execute();
        $units_result = $stmt_check_units->get_result();
        
        $available_units = [];
        while ($unit = $units_result->fetch_assoc()) {
            $available_units[$unit['unit_type_id']] = $unit['count'];
        }
        $stmt_check_units->close();
        
        // Ensure the player is not sending more units than available
        foreach ($units_sent as $unit_type_id => $count) {
            if (!isset($available_units[$unit_type_id]) || $available_units[$unit_type_id] < $count) {
                return [
                    'success' => false,
                    'error' => 'You do not have enough units to perform this attack.'
                ];
            }
        }
        
        // Require at least one unit to be sent
        $total_units = 0;
        foreach ($units_sent as $count) {
            $total_units += $count;
        }
        
        if ($total_units === 0) {
            return [
                'success' => false,
                'error' => 'You must send at least one unit.'
            ];
        }

        // Load unit metadata for validation and speed calculation
        $unit_type_ids = array_keys($units_sent);
        $unit_meta = [];
        $placeholders = implode(',', array_map('intval', $unit_type_ids));

        $stmt_get_units = $this->conn->prepare("
            SELECT id, internal_name, speed
            FROM unit_types
            WHERE id IN ($placeholders)
        ");
        $stmt_get_units->execute();
        $meta_result = $stmt_get_units->get_result();
        while ($row = $meta_result->fetch_assoc()) {
            $unit_meta[$row['id']] = $row;
        }
        $stmt_get_units->close();

        // Validate spy-only missions
        if ($attack_type === 'spy') {
            foreach ($units_sent as $unit_type_id => $count) {
                if (!isset($unit_meta[$unit_type_id]) || $unit_meta[$unit_type_id]['internal_name'] !== 'spy') {
                    return [
                        'success' => false,
                        'error' => 'Spy missions can only include scouts.'
                    ];
                }
            }
        }

        // Calculate distance and travel time
        $distance = $this->calculateDistance(
            $villages['source_x'], $villages['source_y'],
            $villages['target_x'], $villages['target_y']
        );
        
        // Find the slowest unit
        $slowest_speed = null;
        foreach ($units_sent as $unit_type_id => $count) {
            if (!isset($unit_meta[$unit_type_id])) {
                return [
                    'success' => false,
                    'error' => 'Unit information could not be found.'
                ];
            }
            $speed = (int)$unit_meta[$unit_type_id]['speed'];
            if ($slowest_speed === null || $speed < $slowest_speed) {
                $slowest_speed = $speed;
            }
        }
        
        if ($slowest_speed === null) {
            return [
                'success' => false,
                'error' => 'Cannot determine unit speed.'
            ];
        }

        // Calculate travel time in seconds (distance in fields, speed in fields/hour -> seconds)
        $worldSpeed = defined('WORLD_UNIT_SPEED') ? max(0.1, (float)WORLD_UNIT_SPEED) : self::WORLD_UNIT_SPEED;
        $travel_time = (int)ceil(($distance * $slowest_speed / $worldSpeed) * 3600);
        $start_time = time();
        $arrival_time = $start_time + $travel_time;
        
        // Begin transaction
        $this->conn->begin_transaction();
        
        try {
            // Subtract units from the source village
            foreach ($units_sent as $unit_type_id => $count) {
                $stmt_update_units = $this->conn->prepare("
                    UPDATE village_units 
                    SET count = count - ? 
                    WHERE village_id = ? AND unit_type_id = ?
                ");
                $stmt_update_units->bind_param("iii", $count, $source_village_id, $unit_type_id);
                $stmt_update_units->execute();
                $stmt_update_units->close();
            }
            
            // Add the attack to the attacks table
            $stmt_add_attack = $this->conn->prepare("
                INSERT INTO attacks (
                    source_village_id, target_village_id,
                    attack_type, start_time, arrival_time,
                    is_completed, is_canceled, target_building
                ) VALUES (?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), 0, 0, ?)
            ");
            $stmt_add_attack->bind_param(
                "iisiss",
                $source_village_id, $target_village_id,
                $attack_type, $start_time, $arrival_time,
                $target_building
            );
            $stmt_add_attack->execute();
            $attack_id = $stmt_add_attack->insert_id;
            $stmt_add_attack->close();
            
            // Add unit records to attack_units
            foreach ($units_sent as $unit_type_id => $count) {
                $stmt_add_units = $this->conn->prepare("
                    INSERT INTO attack_units (
                        attack_id, unit_type_id, count
                    ) VALUES (?, ?, ?)
                ");
                $stmt_add_units->bind_param("iii", $attack_id, $unit_type_id, $count);
                $stmt_add_units->execute();
                $stmt_add_units->close();
            }
            
            // Commit transaction
            $this->conn->commit();
            
            // Build response payload
            $arrival_date = date('Y-m-d H:i:s', $arrival_time);
            
            return [
                'success' => true,
                'message' => "Attack sent successfully. Arrival time: $arrival_date",
                'attack_id' => $attack_id,
                'source_village_id' => $source_village_id,
                'target_village_id' => $target_village_id,
                'attack_type' => $attack_type,
                'units_sent' => $units_sent,
                'distance' => $distance,
                'travel_time' => $travel_time,
                'arrival_time' => $arrival_time,
                'arrival_date' => $arrival_date
            ];
        } catch (Exception $e) {
            // Roll back on failure
            $this->conn->rollback();
            
            return [
                'success' => false,
                'error' => 'An error occurred while sending the attack: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel an attack if it has not arrived yet.
     * 
     * @param int $attack_id Attack ID
     * @param int $user_id User ID (permission check)
     * @return array Operation status
     */
    public function cancelAttack($attack_id, $user_id)
    {
        // Ensure the attack exists and belongs to the user
        $stmt_check_attack = $this->conn->prepare("
            SELECT a.id, a.source_village_id, a.target_village_id, a.attack_type, 
                   a.start_time, a.arrival_time, a.is_completed, a.is_canceled
            FROM attacks a
            JOIN villages v ON a.source_village_id = v.id
            WHERE a.id = ? AND v.user_id = ? AND a.is_completed = 0 AND a.is_canceled = 0
        ");
        $stmt_check_attack->bind_param("ii", $attack_id, $user_id);
        $stmt_check_attack->execute();
        $result = $stmt_check_attack->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'The attack does not exist, is already finished or canceled, or you do not have access to it.'
            ];
        }
        
        $attack = $result->fetch_assoc();
        $stmt_check_attack->close();
        
        // Ensure the attack has not already arrived
        $current_time = time();
        $arrival_time = strtotime($attack['arrival_time']);
        
        if ($current_time >= $arrival_time) {
            return [
                'success' => false,
                'error' => 'Cannot cancel an attack that has already arrived.'
            ];
        }
        
        // Begin transaction
        $this->conn->begin_transaction();
        
        try {
            // Mark the attack as canceled
            $stmt_cancel_attack = $this->conn->prepare("
                UPDATE attacks 
                SET is_canceled = 1 
                WHERE id = ?
            ");
            $stmt_cancel_attack->bind_param("i", $attack_id);
            $stmt_cancel_attack->execute();
            $stmt_cancel_attack->close();
            
            // Fetch units from the attack
            $stmt_get_units = $this->conn->prepare("
                SELECT unit_type_id, count 
                FROM attack_units 
                WHERE attack_id = ?
            ");
            $stmt_get_units->bind_param("i", $attack_id);
            $stmt_get_units->execute();
            $units_result = $stmt_get_units->get_result();
            
            $units_to_return = [];
            while ($unit = $units_result->fetch_assoc()) {
                $units_to_return[$unit['unit_type_id']] = $unit['count'];
            }
            $stmt_get_units->close();

            // Create a return march from the current position back to the source village.
            $startTs = strtotime($attack['start_time']);
            $arrivalTs = strtotime($attack['arrival_time']);
            $now = time();
            $elapsed = max(1, $now - $startTs);
            $total = max(1, $arrivalTs - $startTs);
            $returnTravel = min($elapsed, $total); // approximate distance already covered
            $returnArrival = $now + $returnTravel;

            $returnSource = $attack['target_village_id'] ?: $attack['source_village_id'];
            $returnTarget = $attack['source_village_id'];

            $stmt_return = $this->conn->prepare("
                INSERT INTO attacks (
                    source_village_id, target_village_id,
                    attack_type, start_time, arrival_time,
                    is_completed, is_canceled, target_building
                ) VALUES (?, ?, 'return', FROM_UNIXTIME(?), FROM_UNIXTIME(?), 0, 0, NULL)
            ");
            $stmt_return->bind_param(
                "iiii",
                $returnSource,
                $returnTarget,
                $now,
                $returnArrival
            );
            $stmt_return->execute();
            $returnAttackId = $stmt_return->insert_id;
            $stmt_return->close();

            foreach ($units_to_return as $unit_type_id => $count) {
                $stmt_add_units = $this->conn->prepare("
                    INSERT INTO attack_units (attack_id, unit_type_id, count)
                    VALUES (?, ?, ?)
                ");
                $stmt_add_units->bind_param("iii", $returnAttackId, $unit_type_id, $count);
                $stmt_add_units->execute();
                $stmt_add_units->close();
            }

            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'The attack was canceled. Troops are returning.',
                'attack_id' => $attack_id,
                'returned_units' => $units_to_return,
                'return_attack_id' => $returnAttackId,
                'return_arrival' => $returnArrival
            ];
        } catch (Exception $e) {
            // Roll back on error
            $this->conn->rollback();
            
            return [
                'success' => false,
                'error' => 'An error occurred while canceling the attack: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Processes completed attacks and generates user-facing messages.
     * @param int $user_id User ID for which to process attacks.
     * @return array Messages to display.
     */
    public function processCompletedAttacks(int $user_id): array
    {
        $messages = [];
        $current_time = date('Y-m-d H:i:s');
        
        // Fetch user village IDs
        $user_village_ids = $this->villageManager->getUserVillageIds($user_id);
        
        if (empty($user_village_ids)) {
             return []; // User has no villages; no attacks to process
        }

        // Fetch unfinished, uncanceled attacks that should have arrived by now,
        // that involve the user's villages (attacker or defender)
        // Using FIND_IN_SET because we cannot bind arrays to IN with prepare()
        $village_ids_string = implode(',', $user_village_ids);

        $stmt_get_attacks = $this->conn->prepare("
            SELECT id, source_village_id, target_village_id, attack_type
            FROM attacks
            WHERE is_completed = 0 AND is_canceled = 0 AND arrival_time <= ?
              AND (FIND_IN_SET(source_village_id, ?) OR FIND_IN_SET(target_village_id, ?))
            ORDER BY arrival_time ASC
        ");
        
         if ($stmt_get_attacks === false) {
             error_log("Prepare failed for getCompletedAttacks (BattleManager): " . $this->conn->error);
             return ['<p class="error-message">An error occurred while fetching completed attacks.</p>'];
         }

        $stmt_get_attacks->bind_param("sss", $current_time, $village_ids_string, $village_ids_string);
        $stmt_get_attacks->execute();
        $attacks_result = $stmt_get_attacks->get_result();
        
        while ($attack = $attacks_result->fetch_assoc()) {
            // Process based on attack type
            if ($attack['attack_type'] === 'spy') {
                $battle_result = $this->processSpyMission($attack['id']);
            } elseif ($attack['attack_type'] === 'support') {
                $battle_result = $this->processSupportArrival($attack['id']);
            } elseif ($attack['attack_type'] === 'return') {
                $battle_result = $this->processReturnArrival($attack['id']);
            } else {
                $battle_result = $this->processBattle($attack['id']);
            }

            if ($battle_result && $battle_result['success']) {
                // Fetch attack and village details for messaging
                $stmt_details = $this->conn->prepare("
                    SELECT
                        a.id, a.source_village_id, a.target_village_id, a.attack_type,
                        sv.name as source_name, tv.name as target_name
                    FROM attacks a
                    JOIN villages sv ON a.source_village_id = sv.id
                    JOIN villages tv ON a.target_village_id = tv.id
                    WHERE a.id = ? LIMIT 1
                ");
                $stmt_details->bind_param("i", $attack['id']);
                $stmt_details->execute();
                $attack_details = $stmt_details->get_result()->fetch_assoc();
                $stmt_details->close();

                if ($attack_details) {
                    // Fetch the battle report to identify the winner and loot (if any)
                    // processBattle/processSpyMission creates the report, so fetch it immediately after.
                    $report = $this->getBattleReportForAttack($attack['id']); // Dedicated helper

                    if ($report) {
                        $source_name = htmlspecialchars($attack_details['source_name']);
                        $target_name = htmlspecialchars($attack_details['target_name']);
                        $report_type = $report['type'] ?? $attack_details['attack_type'];

                        if ($report_type === 'spy') {
                            $success = !empty($report['attacker_won']);
                            $intel = $report['details']['intel'] ?? [];
                            if (in_array($attack['source_village_id'], $user_village_ids)) {
                                $resourcesText = '';
                                if (!empty($intel['resources'])) {
                                    $res = $intel['resources'];
                                    $resourcesText = " Resources - Wood: {$res['wood']}, Clay: {$res['clay']}, Iron: {$res['iron']}.";
                                }
                                if ($success) {
                                    $messages[] = "<p class='success-message'>Your scouts from <b>{$source_name}</b> successfully scouted <b>{$target_name}</b>.{$resourcesText}</p>";
                                } else {
                                    $messages[] = "<p class='error-message'>Your scouts from <b>{$source_name}</b> were intercepted at <b>{$target_name}</b>.</p>";
                                }
                            }

                            if (in_array($attack['target_village_id'], $user_village_ids)) {
                                if ($success) {
                                    $messages[] = "<p class='error-message'>Enemy scouts from <b>{$source_name}</b> gathered intel on <b>{$target_name}</b>.</p>";
                                } else {
                                    $messages[] = "<p class='success-message'>Enemy scouts from <b>{$source_name}</b> were caught near <b>{$target_name}</b>.</p>";
                                }
                            }
                        } else {
                            $winner = $report['attacker_won'] ? 'attacker' : 'defender';
                            $loot = $report['details']['loot'] ?? ['wood' => 0, 'clay' => 0, 'iron' => 0];

                            // Message for the attacker (if the source village belongs to the user)
                            if (in_array($attack['source_village_id'], $user_village_ids)) {
                                if ($winner === 'attacker') {
                                    $messages[] = "<p class='success-message'>Your attack from village <b>{$source_name}</b> on <b>{$target_name}</b> ended in victory! Looted: Wood: {$loot['wood']}, Clay: {$loot['clay']}, Iron: {$loot['iron']}.</p>";
                                } else {
                                    $messages[] = "<p class='error-message'>Your attack from village <b>{$source_name}</b> on <b>{$target_name}</b> ended in defeat.</p>";
                                }
                            }

                            // Message for the defender (if the target village belongs to the user)
                            if (in_array($attack['target_village_id'], $user_village_ids)) {
                                if ($winner === 'defender') {
                                    $messages[] = "<p class='success-message'>Your village <b>{$target_name}</b> defended against an attack from village <b>{$source_name}</b>.</p>";
                                } else {
                                    $messages[] = "<p class='error-message'>Your village <b>{$target_name}</b> was defeated in an attack from village <b>{$source_name}</b>. Resources were lost.</p>";
                                }
                            }
                        }

                        // Add a link to the full battle report here if available
                    } else {
                        error_log("Error: No battle report found for completed attack ID: " . $attack['id']);
                        $messages[] = "<p class='error-message'>An error occurred while generating the battle report for attack ID: " . $attack['id'] . ".</p>";
                    }
                } else {
                    error_log("Error: Attack details not found for attack ID: " . $attack['id'] . " while generating messages.");
                    $messages[] = "<p class='error-message'>An error occurred while fetching attack details for attack ID: " . $attack['id'] . ".</p>";
                }
            } else {
                error_log("Battle processing error for attack ID: " . $attack['id'] . ". Result: " . json_encode($battle_result));
                $messages[] = "<p class='error-message'>An error occurred while processing the battle for attack ID: " . $attack['id'] . ".</p>";
            }
        }

        $attacks_result->free(); // Free memory
        $stmt_get_attacks->close();

        return $messages; // Return collected messages
    }
    
    /**
     * Fetches a battle report by attack ID for post-battle messaging.
     * @param int $attack_id Attack ID
     * @return array|null Battle report data or null if missing.
     */
    public function getBattleReportForAttack(int $attack_id): ?array
    {
         $stmt = $this->conn->prepare("
            SELECT br.id, br.attacker_won, br.report_data, br.battle_time, a.attack_type
            FROM battle_reports br
            JOIN attacks a ON a.id = br.attack_id
            WHERE br.attack_id = ?
            LIMIT 1
         ");
         if ($stmt === false) {
              error_log("Prepare failed for getBattleReportForAttack: " . $this->conn->error);
              return null;
         }
         $stmt->bind_param("i", $attack_id);
         $stmt->execute();
         $result = $stmt->get_result();
         $report = $result->fetch_assoc();
         $stmt->close();
         if (!$report) {
             return null;
         }

         $details = json_decode($report['report_data'], true);
         if (!is_array($details)) {
             $details = [];
         }

         return [
             'id' => $report['id'],
             'attack_type' => $report['attack_type'],
             'type' => $details['type'] ?? $report['attack_type'] ?? 'battle',
             'attacker_won' => (int)$report['attacker_won'],
             'details' => $details,
             'battle_time' => $report['battle_time']
         ];
    }

    /**
     * Handle support arrival (no battle, units join target garrison).
     */
    private function processSupportArrival(int $attack_id): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM attacks WHERE id = ? AND is_completed = 0 AND is_canceled = 0 LIMIT 1");
        $stmt->bind_param("i", $attack_id);
        $stmt->execute();
        $attack = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$attack) {
            return ['success' => false, 'error' => 'Support command missing.'];
        }

        $units = $this->getAttackUnits($attack_id);
        $unitsMap = [];
        foreach ($units as $unit) {
            $unitsMap[$unit['unit_type_id']] = $unit['count'];
        }

        $this->conn->begin_transaction();
        try {
            $this->addUnitsToVillage((int)$attack['target_village_id'], $unitsMap);

            $stmtUpdate = $this->conn->prepare("UPDATE attacks SET is_completed = 1 WHERE id = ?");
            $stmtUpdate->bind_param("i", $attack_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('Support arrival failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Support could not arrive.'];
        }

        return ['success' => true];
    }

    /**
     * Handle return march arrival (restore units to target village).
     */
    private function processReturnArrival(int $attack_id): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM attacks WHERE id = ? AND is_completed = 0 AND is_canceled = 0 LIMIT 1");
        $stmt->bind_param("i", $attack_id);
        $stmt->execute();
        $attack = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$attack) {
            return ['success' => false, 'error' => 'Return command missing.'];
        }

        $units = $this->getAttackUnits($attack_id);
        $unitsMap = [];
        foreach ($units as $unit) {
            $unitsMap[$unit['unit_type_id']] = $unit['count'];
        }

        $this->conn->begin_transaction();
        try {
            $this->addUnitsToVillage((int)$attack['target_village_id'], $unitsMap);

            $stmtUpdate = $this->conn->prepare("UPDATE attacks SET is_completed = 1 WHERE id = ?");
            $stmtUpdate->bind_param("i", $attack_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('Return arrival failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Return march failed.'];
        }

        return ['success' => true];
    }

    /**
     * Add units to a village (update or insert counts).
     */
    private function addUnitsToVillage(int $villageId, array $units): void
    {
        foreach ($units as $unitTypeId => $count) {
            $stmt_check_existing = $this->conn->prepare("
                SELECT id, count 
                FROM village_units 
                WHERE village_id = ? AND unit_type_id = ?
            ");
            $stmt_check_existing->bind_param("ii", $villageId, $unitTypeId);
            $stmt_check_existing->execute();
            $existing_result = $stmt_check_existing->get_result();

            if ($existing_result->num_rows > 0) {
                $existing = $existing_result->fetch_assoc();
                $new_count = $existing['count'] + $count;
                $stmt_update = $this->conn->prepare("
                    UPDATE village_units 
                    SET count = ? 
                    WHERE id = ?
                ");
                $stmt_update->bind_param("ii", $new_count, $existing['id']);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
                $stmt_insert = $this->conn->prepare("
                    INSERT INTO village_units (
                        village_id, unit_type_id, count
                    ) VALUES (?, ?, ?)
                ");
                $stmt_insert->bind_param("iii", $villageId, $unitTypeId, $count);
                $stmt_insert->execute();
                $stmt_insert->close();
            }

            $stmt_check_existing->close();
        }
    }
    /**
     * Processes a single battle: calculates losses/loot, updates DB, creates report.
     * @param int $attack_id Attack ID to process.
     * @return array Battle processing result (success/error).
     */
    private function processBattle(int $attack_id): array
    {
        // Fetch attack details
        $stmt_get_attack = $this->conn->prepare("
            SELECT id, source_village_id, target_village_id, attack_type, target_building
            FROM attacks
            WHERE id = ?
        ");
        $stmt_get_attack->bind_param("i", $attack_id);
        $stmt_get_attack->execute();
        $attack = $stmt_get_attack->get_result()->fetch_assoc();
        $stmt_get_attack->close();
        if (!$attack) {
            return [ 'success' => false, 'error' => 'Attack does not exist.' ];
        }
        $isRaid = $attack['attack_type'] === 'raid';
        // Fetch attacking units
        $stmt_get_attack_units = $this->conn->prepare("
            SELECT au.unit_type_id, au.count, ut.attack, ut.defense, ut.name, ut.carry_capacity, ut.internal_name,
                   ut.defense_cavalry, ut.defense_archer
            FROM attack_units au
            JOIN unit_types ut ON au.unit_type_id = ut.id
            WHERE au.attack_id = ?
        ");
        $stmt_get_attack_units->bind_param("i", $attack_id);
        $stmt_get_attack_units->execute();
        $attack_units_result = $stmt_get_attack_units->get_result();
        $attacking_units = [];
        $attack_capacity = 0;
        while ($unit = $attack_units_result->fetch_assoc()) {
            $attacking_units[$unit['unit_type_id']] = $unit;
            $attack_capacity += $unit['carry_capacity'] * $unit['count'];
        }
        $stmt_get_attack_units->close();
        // Fetch defending units
        $stmt_get_defense_units = $this->conn->prepare("
            SELECT vu.unit_type_id, vu.count, ut.attack, ut.defense, ut.name, ut.internal_name,
                   ut.defense_cavalry, ut.defense_archer
            FROM village_units vu
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE vu.village_id = ?
        ");
        $stmt_get_defense_units->bind_param("i", $attack['target_village_id']);
        $stmt_get_defense_units->execute();
        $defense_units_result = $stmt_get_defense_units->get_result();
        $defending_units = [];
        while ($unit = $defense_units_result->fetch_assoc()) {
            $defending_units[$unit['unit_type_id']] = $unit;
        }
        $stmt_get_defense_units->close();
        // --- RANDOMNESS & MORALE ---
        $attack_random = $this->rollRandomFactor(self::RANDOM_VARIANCE);
        $defense_random = $this->rollRandomFactor(self::RANDOM_VARIANCE);
        $attacker_points = $this->getVillagePointsWithFallback($attack['source_village_id']);
        $defender_points = $this->getVillagePointsWithFallback($attack['target_village_id']);
        $morale = $this->calculateMoraleFactor($attacker_points, $defender_points);
        // --- WALL BONUS & FAITH ---
        $wall_level = $this->buildingManager->getBuildingLevel($attack['target_village_id'], 'wall');
        $effective_wall_level = $wall_level; // Rams are applied later for permanent damage
        $wall_bonus = 1 + ($effective_wall_level * self::WALL_BONUS_PER_LEVEL);
        $faith_bonus = $this->calculateFaithDefenseBonus($attack['target_village_id']);
        // --- TOTAL STRENGTH ---
        $attackProfile = $this->calculateAttackProfile($attacking_units);
        $total_attack_strength = $attackProfile['total'];
        $total_defense_strength = $this->calculateDefensePower($defending_units, $wall_bonus * $faith_bonus, $defense_random, $attackProfile);

        $attackPower = max(0, $total_attack_strength * $morale * $attack_random);
        $defensePower = max(1, $total_defense_strength); // wall/defense luck already applied inside calculateDefensePower

        $battleOutcome = $this->calculateCasualties(
            $attacking_units,
            $defending_units,
            $attackPower,
            $defensePower,
            $isRaid
        );

        $attacker_win = $battleOutcome['attacker_win'];
        $attacker_losses = $battleOutcome['attacker_losses'];
        $defender_losses = $battleOutcome['defender_losses'];
        $remaining_attacking_units = $battleOutcome['remaining_attacking_units'];
        $remaining_defending_units = $battleOutcome['remaining_defending_units'];

        // --- LOYALTY / CONQUEST (NOBLES) ---
        $loyalty_report = null;
        $villageConquered = false;
        $attacker_user_id = null;
        $defender_user_id = null;
        $stmt_users = $this->conn->prepare("SELECT v1.user_id as attacker_user_id, v2.user_id as defender_user_id FROM villages v1, villages v2 WHERE v1.id = ? AND v2.id = ?");
        $stmt_users->bind_param("ii", $attack['source_village_id'], $attack['target_village_id']);
        $stmt_users->execute();
        $users = $stmt_users->get_result()->fetch_assoc();
        $stmt_users->close();
        if ($users) {
            $attacker_user_id = (int)$users['attacker_user_id'];
            $defender_user_id = (int)$users['defender_user_id'];
        }

        if ($attacker_win && $this->villageHasLoyalty()) {
            $survivingNobles = $this->countUnitsByInternalName($remaining_attacking_units, $attacking_units, ['noble', 'nobleman']);
            if ($survivingNobles > 0) {
                $currentLoyalty = $this->getVillageLoyalty($attack['target_village_id']);
                $drop = random_int(20, 35);
                $newLoyalty = max(0, $currentLoyalty - $drop);
                $loyalty_report = [
                    'before' => $currentLoyalty,
                    'after' => $newLoyalty,
                    'change' => -$drop,
                    'nobles' => $survivingNobles
                ];
                if ($newLoyalty <= 0 && $attacker_user_id !== null) {
                    $villageConquered = true;
                    $newLoyalty = 25; // standard post-conquest loyalty
                    $loyalty_report['after'] = $newLoyalty;
                    $loyalty_report['conquered'] = true;
                }
            }
        }
        // --- LOOT ---
        $loot = [ 'wood' => 0, 'clay' => 0, 'iron' => 0 ];
        if ($attacker_win && !empty($remaining_attacking_units)) {
            $attack_capacity = 0;
            foreach ($remaining_attacking_units as $unit_type_id => $count) {
                if (isset($attacking_units[$unit_type_id])) {
                    $attack_capacity += ($attacking_units[$unit_type_id]['carry_capacity'] ?? 0) * $count;
                }
            }
            if ($attack_capacity > 0) {
            // Pull resources from the target village
            $stmt_res = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
            $stmt_res->bind_param("i", $attack['target_village_id']);
            $stmt_res->execute();
            $res = $stmt_res->get_result()->fetch_assoc();
            $stmt_res->close();

            // Apply hiding place protection per resource
            $hiddenPerResource = $this->getHiddenResourcesPerType($attack['target_village_id']);
            $available = [
                'wood' => max(0, $res['wood'] - $hiddenPerResource),
                'clay' => max(0, $res['clay'] - $hiddenPerResource),
                'iron' => max(0, $res['iron'] - $hiddenPerResource),
            ];

            $max_available = $available['wood'] + $available['clay'] + $available['iron'];
            if ($isRaid) {
                $max_available = floor($max_available * self::RAID_LOOT_FACTOR);
            }
            $total_loot = min($attack_capacity, $max_available);
            if ($total_loot > 0) {
                // Even distribution across remaining resources
                $share = (int)floor($total_loot / 3);
                $loot['wood'] = min($available['wood'], $share);
                $loot['clay'] = min($available['clay'], $share);
                $loot['iron'] = min($available['iron'], $total_loot - $loot['wood'] - $loot['clay']);
            }
            // Subtract resources from the village
            $stmt_update = $this->conn->prepare("UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ? WHERE id = ?");
            $stmt_update->bind_param("iiii", $loot['wood'], $loot['clay'], $loot['iron'], $attack['target_village_id']);
            $stmt_update->execute();
            $stmt_update->close();
            }
        }

        // --- WALL DAMAGE (RAMS) ---
        $wall_damage_report = ['initial_level' => $wall_level, 'final_level' => $wall_level];
        if ($attacker_win && !$isRaid) {
            $surviving_rams = 0;
            foreach ($remaining_attacking_units as $unit_type_id => $count) {
                if (isset($attacking_units[$unit_type_id]) && $attacking_units[$unit_type_id]['internal_name'] === 'ram') {
                    $surviving_rams += $count;
                }
            }

            if ($surviving_rams > 0 && $wall_level > 0) {
                $damage_value = ($surviving_rams * 2) - ($wall_level * 0.5);
                $levels_destroyed = (int)floor(max(0, $damage_value));
                if ($levels_destroyed > 0) {
                    $new_wall_level = max(0, $wall_level - min($levels_destroyed, $wall_level));
                    $wall_damage_report['final_level'] = $new_wall_level;
                }
            }
        }

        // --- BUILDING DAMAGE (CATAPULTS) ---
        $building_damage_report = null;
        if ($attacker_win && !$isRaid) {
            $surviving_catapults = 0;
            foreach ($remaining_attacking_units as $unit_type_id => $count) {
                if (isset($attacking_units[$unit_type_id]) && $attacking_units[$unit_type_id]['internal_name'] === 'catapult') {
                    $surviving_catapults += $count;
                }
            }

            if ($surviving_catapults > 0) {
                $target_building_name = $attack['target_building'];

                // If no specific target, choose a random one
                if (empty($target_building_name)) {
                    $village_buildings = $this->buildingManager->getVillageBuildingsLevels($attack['target_village_id']);
                    $possible_targets = array_filter($village_buildings, function($level) {
                        return $level > 0;
                    });
                    if (!empty($possible_targets)) {
                        $target_building_name = array_rand($possible_targets);
                    }
                }

                if (!empty($target_building_name)) {
                    $initial_level = $this->buildingManager->getBuildingLevel($attack['target_village_id'], $target_building_name);
                    if ($initial_level > 0) {
                        $accuracy_factor = 0.25; // base
                        if (!empty($attack['target_building'])) {
                            $accuracy_factor += 0.25; // assume scout intel if a target was set
                        }
                        $accuracy_factor = min(1.0, $accuracy_factor);

                        // Roll to see if we miss the intended building
                        $hitRoll = $this->randomFloat(0, 1);
                        if ($hitRoll > $accuracy_factor) {
                            // Missed: hit a random building that exists
                            $village_buildings = $this->buildingManager->getVillageBuildingsLevels($attack['target_village_id']);
                            $possible_targets = array_filter($village_buildings, fn($level) => $level > 0);
                            if (!empty($possible_targets)) {
                                $target_building_name = array_rand($possible_targets);
                                $initial_level = $possible_targets[$target_building_name];
                            }
                        }

                        $damage_value = $surviving_catapults * 2 * $accuracy_factor;
                        $levels_destroyed = (int)floor($damage_value);

                        if ($levels_destroyed > 0) {
                            $final_level = max(0, $initial_level - $levels_destroyed);
                            $building_damage_report = [
                                'building_name' => $target_building_name,
                                'initial_level' => $initial_level,
                                'final_level' => $final_level
                            ];
                        }
                    }
                }
            }
        }

        // --- TRANSACTION ---
        $this->conn->begin_transaction();
        try {
            // Update wall level if damaged
            if ($wall_damage_report['initial_level'] !== $wall_damage_report['final_level']) {
                $this->buildingManager->setBuildingLevel(
                    $attack['target_village_id'],
                    'wall',
                    $wall_damage_report['final_level']
                );
            }

            // Update catapult target level if damaged
            if ($building_damage_report && $building_damage_report['initial_level'] !== $building_damage_report['final_level']) {
                $this->buildingManager->setBuildingLevel(
                    $attack['target_village_id'],
                    $building_damage_report['building_name'],
                    $building_damage_report['final_level']
                );
            }

            // Loyalty handling
            if ($this->villageHasLoyalty() && $loyalty_report !== null) {
                $this->updateVillageLoyalty($attack['target_village_id'], (int)$loyalty_report['after']);
            }

            // Conquest handling
            if ($villageConquered && $attacker_user_id !== null) {
                $stmt_update_owner = $this->conn->prepare("UPDATE villages SET user_id = ?, loyalty = ? WHERE id = ?");
                $newLoyaltyVal = $loyalty_report['after'] ?? 25;
                $stmt_update_owner->bind_param("iii", $attacker_user_id, $newLoyaltyVal, $attack['target_village_id']);
                $stmt_update_owner->execute();
                $stmt_update_owner->close();

                // Remove defender units and set attacker survivors as new garrison
                $stmt_delete_all = $this->conn->prepare("DELETE FROM village_units WHERE village_id = ?");
                $stmt_delete_all->bind_param("i", $attack['target_village_id']);
                $stmt_delete_all->execute();
                $stmt_delete_all->close();

                foreach ($remaining_attacking_units as $unit_type_id => $count) {
                    $stmt_insert = $this->conn->prepare("
                        INSERT INTO village_units (village_id, unit_type_id, count)
                        VALUES (?, ?, ?)
                    ");
                    $stmt_insert->bind_param("iii", $attack['target_village_id'], $unit_type_id, $count);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                // No return for conquering armies
                $remaining_attacking_units = [];
                $remaining_defending_units = [];
            } else {
                // Update defending units in the village
                foreach ($defending_units as $unit_type_id => $unit) {
                    $new_count = isset($remaining_defending_units[$unit_type_id]) ? $remaining_defending_units[$unit_type_id] : 0;
                    if ($new_count > 0) {
                        $stmt_update = $this->conn->prepare("
                            UPDATE village_units 
                            SET count = ? 
                            WHERE village_id = ? AND unit_type_id = ?
                        ");
                        $stmt_update->bind_param("iii", $new_count, $attack['target_village_id'], $unit_type_id);
                        $stmt_update->execute();
                        $stmt_update->close();
                    } else {
                        $stmt_delete = $this->conn->prepare("
                            DELETE FROM village_units 
                            WHERE village_id = ? AND unit_type_id = ?
                        ");
                        $stmt_delete->bind_param("ii", $attack['target_village_id'], $unit_type_id);
                        $stmt_delete->execute();
                        $stmt_delete->close();
                    }
                }

                // Return remaining attacking units to the source village
                foreach ($remaining_attacking_units as $unit_type_id => $count) {
                    $stmt_check_existing = $this->conn->prepare("
                        SELECT id, count 
                        FROM village_units 
                        WHERE village_id = ? AND unit_type_id = ?
                    ");
                    $stmt_check_existing->bind_param("ii", $attack['source_village_id'], $unit_type_id);
                    $stmt_check_existing->execute();
                    $existing_result = $stmt_check_existing->get_result();
                    if ($existing_result->num_rows > 0) {
                        // Update existing units
                        $existing = $existing_result->fetch_assoc();
                        $new_count = $existing['count'] + $count;
                        $stmt_update = $this->conn->prepare("
                            UPDATE village_units 
                            SET count = ? 
                            WHERE id = ?
                        ");
                        $stmt_update->bind_param("ii", $new_count, $existing['id']);
                        $stmt_update->execute();
                        $stmt_update->close();
                    } else {
                        // Insert new unit records
                        $stmt_insert = $this->conn->prepare("
                            INSERT INTO village_units (
                                village_id, unit_type_id, count
                            ) VALUES (?, ?, ?)
                        ");
                        $stmt_insert->bind_param("iii", $attack['source_village_id'], $unit_type_id, $count);
                        $stmt_insert->execute();
                        $stmt_insert->close();
                    }
                    $stmt_check_existing->close();
                }
            }

            // Mark the attack as completed
            $stmt_complete_attack = $this->conn->prepare("
                UPDATE attacks 
                SET is_completed = 1 
                WHERE id = ?
            ");
            $stmt_complete_attack->bind_param("i", $attack_id);
            $stmt_complete_attack->execute();
            $stmt_complete_attack->close();

            // Add battle report (with JSON details)
            $details = [
                'type' => 'battle',
                'attacker_losses' => $attacker_losses,
                'defender_losses' => $defender_losses,
                'loot' => $loot,
                'attack_luck' => $attack_random,
                'defense_luck' => $defense_random,
                'morale' => $morale,
                'attacker_points' => $attacker_points,
                'defender_points' => $defender_points,
                'attack_type' => $attack['attack_type'],
                'wall_level' => $wall_level,
                'effective_wall_level' => $effective_wall_level,
                'wall_bonus' => $wall_bonus,
                'faith_bonus' => $faith_bonus,
                'wall_damage' => $wall_damage_report,
                'building_damage' => $building_damage_report,
                'hiding_place_level' => $this->buildingManager->getBuildingLevel($attack['target_village_id'], 'hiding_place'),
                'hidden_per_resource' => $this->getHiddenResourcesPerType($attack['target_village_id']),
                'attack_power' => $attackPower,
                'defense_power' => $defensePower,
                'loyalty' => $loyalty_report,
                'conquered' => $villageConquered
            ];
            $report_data_json = json_encode($details);
            $attacker_won_int = $attacker_win ? 1 : 0;

            $stmt_add_report = $this->conn->prepare("
                INSERT INTO battle_reports (
                    attack_id, source_village_id, target_village_id,
                    battle_time, attacker_user_id, defender_user_id,
                    attacker_won, report_data
                ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)
            ");
            $stmt_add_report->bind_param(
                "iiiiis",
                $attack_id, $attack['source_village_id'], $attack['target_village_id'],
                $attacker_user_id, $defender_user_id,
                $attacker_won_int, $report_data_json
            );
            $stmt_add_report->execute();
            $stmt_add_report->close();

            // Generic reports for attacker/defender
            $attackerTitle = ucfirst($attack['attack_type'] === 'raid' ? 'Raid' : 'Attack') . " on " . $this->getVillageName($attack['target_village_id']);
            $defenderTitle = "Defense at " . $this->getVillageName($attack['target_village_id']);
            if ($this->reportManager) {
                $this->reportManager->addReport(
                    $attacker_user_id,
                    $attack['attack_type'] === 'support' ? 'support' : 'attack',
                    $attackerTitle,
                    $details,
                    $attack_id
                );
                $this->reportManager->addReport(
                    $defender_user_id,
                    $attack['attack_type'] === 'support' ? 'support' : 'defense',
                    $defenderTitle,
                    $details,
                    $attack_id
                );
            }

            $this->conn->commit();
            return [ 'success' => true ];
        } catch (Exception $e) {
            $this->conn->rollback();
            return [ 'success' => false, 'error' => $e->getMessage() ];
        }
    }

    /**
     * Processes a spy mission (attack_type = spy). Scouts attempt to gather intel and may be detected.
     *
     * @param int $attack_id Attack ID to process.
     * @return array Result of the spy resolution.
     */
    private function processSpyMission(int $attack_id): array
    {
        // Fetch attack details
        $stmt_get_attack = $this->conn->prepare("
            SELECT id, source_village_id, target_village_id
            FROM attacks
            WHERE id = ?
        ");
        $stmt_get_attack->bind_param("i", $attack_id);
        $stmt_get_attack->execute();
        $attack = $stmt_get_attack->get_result()->fetch_assoc();
        $stmt_get_attack->close();

        if (!$attack) {
            return ['success' => false, 'error' => 'Attack does not exist.'];
        }

        // Fetch attacking units
        $stmt_get_units = $this->conn->prepare("
            SELECT au.unit_type_id, au.count, ut.internal_name, ut.name
            FROM attack_units au
            JOIN unit_types ut ON au.unit_type_id = ut.id
            WHERE au.attack_id = ?
        ");
        $stmt_get_units->bind_param("i", $attack_id);
        $stmt_get_units->execute();
        $attack_units_result = $stmt_get_units->get_result();

        $attacker_spies = 0;
        $spy_unit_type_id = null;
        $other_units = [];

        while ($unit = $attack_units_result->fetch_assoc()) {
            if ($unit['internal_name'] === 'spy') {
                $attacker_spies += $unit['count'];
                $spy_unit_type_id = $unit['unit_type_id'];
            } else {
                // Safety: return any non-spy units untouched
                $other_units[$unit['unit_type_id']] = $unit['count'];
            }
        }
        $stmt_get_units->close();

        // Defender spies
        $defender_spies = 0;
        $defender_spy_row_id = null;
        $stmt_def_spies = $this->conn->prepare("
            SELECT vu.id, vu.count
            FROM village_units vu
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE vu.village_id = ? AND ut.internal_name = 'spy'
        ");
        $stmt_def_spies->bind_param("i", $attack['target_village_id']);
        $stmt_def_spies->execute();
        $def_res = $stmt_def_spies->get_result();
        if ($row = $def_res->fetch_assoc()) {
            $defender_spies = (int)$row['count'];
            $defender_spy_row_id = (int)$row['id'];
        }
        $stmt_def_spies->close();

        $attacker_spy_level = $this->getResearchLevelForVillage($attack['source_village_id'], 'spying');
        $defender_spy_level = $this->getResearchLevelForVillage($attack['target_village_id'], 'spying');
        $wall_level = $this->buildingManager->getBuildingLevel($attack['target_village_id'], 'wall');

        // Scores and outcome
        $attack_score = max(1, $attacker_spies) * (1 + 0.15 * $attacker_spy_level);
        $defense_score = max(0, $defender_spies * (1 + 0.15 * $defender_spy_level)) + ($wall_level * 0.6);
        $attack_score *= (random_int(90, 110) / 100);
        $defense_score *= (random_int(90, 110) / 100);

        $success = $attack_score >= max(1, $defense_score) && $attacker_spies > 0;

        // Casualties
        $attacker_losses = 0;
        if ($attacker_spies > 0) {
            if ($success) {
                $attacker_losses = min(
                    $attacker_spies,
                    (int)ceil(($defense_score / max(1, $attack_score)) * $attacker_spies * 0.6)
                );
            } else {
                $attacker_losses = $attacker_spies;
            }
        }
        $attacker_survivors = max(0, $attacker_spies - $attacker_losses);

        if ($success) {
            $defender_losses = min($defender_spies, max(0, (int)floor($attacker_spies / 2)));
        } else {
            $defender_losses = min($defender_spies, (int)ceil($attacker_spies * 0.3));
        }
        $defender_survivors = max(0, $defender_spies - $defender_losses);

        // Prepare intel if successful
        $intel = [];
        if ($success) {
            $stmt_res = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
            $stmt_res->bind_param("i", $attack['target_village_id']);
            $stmt_res->execute();
            $resources = $stmt_res->get_result()->fetch_assoc();
            $stmt_res->close();
            $intel['resources'] = $resources ?: ['wood' => 0, 'clay' => 0, 'iron' => 0];

            $intel_level = $attacker_spy_level + ($attacker_survivors >= 5 ? 2 : ($attacker_survivors >= 2 ? 1 : 0));

            if ($intel_level >= 2) {
                $intel['buildings'] = $this->getBuildingSnapshot($attack['target_village_id']);
            }
            if ($intel_level >= 3) {
                $intel['units'] = $this->getVillageUnitSnapshot($attack['target_village_id']);
            }
            if ($intel_level >= 4) {
                $intel['research'] = $this->getResearchSnapshot($attack['target_village_id']);
            }
        }

        // Units to return (include any non-spy units to avoid losing them)
        $units_to_return = $other_units;
        if ($spy_unit_type_id !== null && $attacker_survivors > 0) {
            $units_to_return[$spy_unit_type_id] = ($units_to_return[$spy_unit_type_id] ?? 0) + $attacker_survivors;
        }

        // Fetch user IDs
        $stmt_users = $this->conn->prepare("
            SELECT v1.user_id as attacker_user_id, v2.user_id as defender_user_id
            FROM villages v1, villages v2
            WHERE v1.id = ? AND v2.id = ?
        ");
        $stmt_users->bind_param("ii", $attack['source_village_id'], $attack['target_village_id']);
        $stmt_users->execute();
        $users = $stmt_users->get_result()->fetch_assoc();
        $stmt_users->close();

        $details = [
            'type' => 'spy',
            'success' => $success,
            'attacker_spies_sent' => $attacker_spies,
            'attacker_spies_lost' => $attacker_losses,
            'attacker_spies_returned' => $attacker_survivors,
            'defender_spies' => $defender_spies,
            'defender_spies_lost' => $defender_losses,
            'defender_spies_remaining' => $defender_survivors,
            'attacker_spy_level' => $attacker_spy_level,
            'defender_spy_level' => $defender_spy_level,
            'wall_level' => $wall_level,
            'scores' => [
                'attack' => round($attack_score, 2),
                'defense' => round($defense_score, 2)
            ],
            'intel' => $intel,
            'returned_units' => $units_to_return
        ];
        $report_data_json = json_encode($details);
        $attacker_won_int = $success ? 1 : 0;

        // Persist results
        $this->conn->begin_transaction();
        try {
            // Update defender spies
            if ($defender_spy_row_id !== null) {
                if ($defender_survivors > 0) {
                    $stmt_update = $this->conn->prepare("UPDATE village_units SET count = ? WHERE id = ?");
                    $stmt_update->bind_param("ii", $defender_survivors, $defender_spy_row_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    $stmt_delete = $this->conn->prepare("DELETE FROM village_units WHERE id = ?");
                    $stmt_delete->bind_param("i", $defender_spy_row_id);
                    $stmt_delete->execute();
                    $stmt_delete->close();
                }
            }

            // Return surviving units to the source village
            foreach ($units_to_return as $unit_type_id => $count) {
                if ($count <= 0) {
                    continue;
                }
                $stmt_check_existing = $this->conn->prepare("
                    SELECT id, count
                    FROM village_units
                    WHERE village_id = ? AND unit_type_id = ?
                ");
                $stmt_check_existing->bind_param("ii", $attack['source_village_id'], $unit_type_id);
                $stmt_check_existing->execute();
                $existing_result = $stmt_check_existing->get_result();
                if ($existing_result->num_rows > 0) {
                    $existing = $existing_result->fetch_assoc();
                    $new_count = $existing['count'] + $count;
                    $stmt_update = $this->conn->prepare("
                        UPDATE village_units
                        SET count = ?
                        WHERE id = ?
                    ");
                    $stmt_update->bind_param("ii", $new_count, $existing['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    $stmt_insert = $this->conn->prepare("
                        INSERT INTO village_units (village_id, unit_type_id, count)
                        VALUES (?, ?, ?)
                    ");
                    $stmt_insert->bind_param("iii", $attack['source_village_id'], $unit_type_id, $count);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                $stmt_check_existing->close();
            }

            // Mark attack as completed
            $stmt_complete_attack = $this->conn->prepare("
                UPDATE attacks
                SET is_completed = 1
                WHERE id = ?
            ");
            $stmt_complete_attack->bind_param("i", $attack_id);
            $stmt_complete_attack->execute();
            $stmt_complete_attack->close();

            // Add spy report
            $stmt_add_report = $this->conn->prepare("
                INSERT INTO battle_reports (
                    attack_id, source_village_id, target_village_id,
                    battle_time, attacker_user_id, defender_user_id,
                    attacker_won, report_data
                ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)
            ");
            $stmt_add_report->bind_param(
                "iiiiiis",
                $attack_id, $attack['source_village_id'], $attack['target_village_id'],
                $users['attacker_user_id'], $users['defender_user_id'],
                $attacker_won_int, $report_data_json
            );
            $stmt_add_report->execute();
            $stmt_add_report->close();

            // Generic reports: scout for attacker, defense for defender
            if ($this->reportManager) {
                $attackerTitle = "Scout report: " . $this->getVillageName($attack['target_village_id']);
                $defenderTitle = "Scouts near " . $this->getVillageName($attack['target_village_id']);
                $this->reportManager->addReport(
                    $users['attacker_user_id'],
                    'scout',
                    $attackerTitle,
                    $details,
                    $attack_id
                );
                $this->reportManager->addReport(
                    $users['defender_user_id'],
                    'defense',
                    $defenderTitle,
                    $details,
                    $attack_id
                );
            }

            $this->conn->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch village points; fallback to population when points are not yet calculated.
     */
    private function getVillagePointsWithFallback(int $villageId): int
    {
        $stmt = $this->conn->prepare("SELECT points, population FROM villages WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return 0;
        }

        $points = (int)$row['points'];
        if ($points > 0) {
            return $points;
        }

        return (int)$row['population'];
    }

    /**
     * Morale punishes oversized attackers; no penalty if attacker is weaker.
     * Returns multiplier between MIN_MORALE and 1.0.
     */
    private function calculateMoraleFactor(int $attackerPoints, int $defenderPoints): float
    {
        if ($attackerPoints <= 0 || $defenderPoints <= 0) {
            return 1.0;
        }

        if ($attackerPoints <= $defenderPoints) {
            return 1.0;
        }

        $ratio = $defenderPoints / max(1, $attackerPoints);
        $morale = sqrt($ratio);
        return max(self::MIN_MORALE, min(1.0, $morale));
    }

    /**
     * Defensive faith bonus from churches.
     */
    private function calculateFaithDefenseBonus(int $villageId): float
    {
        $bonus = 1.0;

        $churchLevel = $this->buildingManager->getBuildingLevel($villageId, 'church');
        if ($churchLevel > 0) {
            $bonus += $churchLevel * self::FAITH_DEFENSE_PER_LEVEL;
        }

        $firstChurchLevel = $this->buildingManager->getBuildingLevel($villageId, 'first_church');
        if ($firstChurchLevel > 0) {
            $bonus += self::FIRST_CHURCH_DEFENSE_BONUS;
        }

        return max(1.0, $bonus);
    }

    /**
     * Return a random factor centered at 1 with a configurable spread.
     */
    private function rollRandomFactor(float $spread = self::RANDOM_VARIANCE): float
    {
        $spread = max(0, $spread);
        $min = 1 - $spread;
        $max = 1 + $spread;
        return $this->randomFloat($min, $max);
    }

    /**
     * Inclusive-ish random float helper.
     */
    private function randomFloat(float $min, float $max): float
    {
        $min = min($min, $max);
        $max = max($min, $max);
        return $min + (random_int(0, 1000000) / 1000000) * ($max - $min);
    }

    /**
     * Hidden resources protected by the hiding place per resource type.
     */
    private function getHiddenResourcesPerType(int $villageId): int
    {
        $level = $this->buildingManager->getBuildingLevel($villageId, 'hiding_place');
        if ($level <= 0) {
            return 0;
        }

        return (int)floor(150 * pow(1.233, $level));
    }

    /**
     * Attack power: simple sum of attack stat * count (smithy bonuses can hook in later).
     */
    private function calculateAttackPower(array $units): float
    {
        $power = 0;
        foreach ($units as $unit) {
            $power += ($unit['attack'] ?? 0) * ($unit['count'] ?? 0);
        }
        return max(0, $power);
    }

    /**
     * Attack profile split by unit class.
     */
    private function calculateAttackProfile(array $units): array
    {
        $profile = [
            'by_class' => [
                'infantry' => 0,
                'cavalry' => 0,
                'archer' => 0,
                'siege' => 0
            ],
            'total' => 0
        ];

        foreach ($units as $unit) {
            $class = $this->getUnitClass($unit['internal_name'] ?? '');
            $attackValue = ($unit['attack'] ?? 0) * ($unit['count'] ?? 0);
            $profile['by_class'][$class] += $attackValue;
            $profile['total'] += $attackValue;
        }

        return $profile;
    }

    /**
     * Defense profile for a single unit (per class, fallback to general defense).
     */
    private function getDefenseProfile(array $unit): array
    {
        $base = (float)($unit['defense'] ?? 0);
        $defCav = (float)($unit['defense_cavalry'] ?? $base);
        $defArch = (float)($unit['defense_archer'] ?? $base);

        return [
            'infantry' => $base,
            'cavalry' => $defCav,
            'archer' => $defArch,
            'siege' => $base
        ];
    }

    /**
     * Map internal unit name to combat class.
     */
    private function getUnitClass(string $internalName): string
    {
        $internalName = strtolower($internalName);
        if (in_array($internalName, ['light', 'heavy', 'marcher', 'spy'])) {
            return 'cavalry';
        }
        if (in_array($internalName, ['archer', 'marcher'])) {
            return 'archer';
        }
        if (in_array($internalName, ['ram', 'catapult'])) {
            return 'siege';
        }
        return 'infantry';
    }

    /**
     * Defense power with wall bonus and luck already baked in.
     */
    private function calculateDefensePower(array $units, float $wallBonus, float $luck, array $attackProfile): float
    {
        $defenseProfile = [
            'infantry' => 0,
            'cavalry' => 0,
            'archer' => 0,
            'siege' => 0
        ];

        foreach ($units as $unit) {
            $profile = $this->getDefenseProfile($unit);
            $count = $unit['count'] ?? 0;
            $defenseProfile['infantry'] += $profile['infantry'] * $count;
            $defenseProfile['cavalry'] += $profile['cavalry'] * $count;
            $defenseProfile['archer'] += $profile['archer'] * $count;
            $defenseProfile['siege'] += $profile['siege'] * $count;
        }

        $defenseProfile = array_map(fn($v) => $v * $wallBonus * $luck, $defenseProfile);

        $effectiveDefense = 0;
        $attackTotal = max(1, $attackProfile['total']);
        foreach (['infantry', 'cavalry', 'archer', 'siege'] as $class) {
            $attackShare = ($attackProfile['by_class'][$class] ?? 0) / $attackTotal;
            $effectiveDefense += $attackShare * ($defenseProfile[$class] ?? 0);
        }

        return max(1, $effectiveDefense);
    }

    /**
     * Calculate casualties for both sides using exponential ratios.
     */
    private function calculateCasualties(array $attacking_units, array $defending_units, float $attackPower, float $defensePower, bool $isRaid): array
    {
        $attackPower = max(1, $attackPower);
        $defensePower = max(1, $defensePower);
        $attackerWins = $attackPower >= $defensePower;
        $ratio = $attackPower / $defensePower;

        $minLoss = 0.30; // floors for realism
        $lossScale = 0.9;

        if ($attackerWins) {
            $defenderLossFactor = min(1.0, max($minLoss, $lossScale * pow($ratio, 0.9)));
            $attackerLossFactor = min(1.0, max($minLoss, $lossScale * pow(1 / $ratio, 0.65)));
        } else {
            $attackerLossFactor = min(1.0, max($minLoss, $lossScale * pow(1 / max(0.0001, $ratio), 0.9)));
            $defenderLossFactor = min(1.0, max($minLoss, $lossScale * pow($ratio, 0.65)));
        }

        if ($isRaid) {
            $attackerLossFactor *= self::RAID_CASUALTY_FACTOR;
            $defenderLossFactor *= self::RAID_CASUALTY_FACTOR;
        }

        $attacker_losses = [];
        $remaining_attacking_units = [];
        foreach ($attacking_units as $unit_type_id => $unit) {
            $loss_count = (int)round($unit['count'] * $attackerLossFactor);
            $remaining = max(0, $unit['count'] - $loss_count);
            $attacker_losses[$unit_type_id] = [
                'unit_name' => $unit['name'],
                'initial_count' => $unit['count'],
                'lost_count' => $loss_count,
                'remaining_count' => $remaining
            ];
            if ($remaining > 0) {
                $remaining_attacking_units[$unit_type_id] = $remaining;
            }
        }

        $defender_losses = [];
        $remaining_defending_units = [];
        foreach ($defending_units as $unit_type_id => $unit) {
            $loss_count = (int)round($unit['count'] * $defenderLossFactor);
            $remaining = max(0, $unit['count'] - $loss_count);
            $defender_losses[$unit_type_id] = [
                'unit_name' => $unit['name'],
                'initial_count' => $unit['count'],
                'lost_count' => $loss_count,
                'remaining_count' => $remaining
            ];
            if ($remaining > 0) {
                $remaining_defending_units[$unit_type_id] = $remaining;
            }
        }

        return [
            'attacker_win' => $attackerWins,
            'attacker_losses' => $attacker_losses,
            'defender_losses' => $defender_losses,
            'remaining_attacking_units' => $remaining_attacking_units,
            'remaining_defending_units' => $remaining_defending_units
        ];
    }

    /**
     * Calculates how many troops the winning side loses.
     * Scales with power ratio and is softened for raids.
     */
    private function calculateWinnerLossRatio(float $winnerPower, float $loserPower, bool $isRaid): float
    {
        if ($winnerPower <= 0) {
            return 1.0;
        }

        $ratio = $loserPower / max(1, $winnerPower);
        $loss_ratio = max(self::WINNER_MINIMUM_LOSS, pow($ratio, 0.6));

        if ($isRaid) {
            $loss_ratio *= self::RAID_CASUALTY_FACTOR;
        }

        return min(1.0, $loss_ratio);
    }

    /**
     * Suppress wall bonus based on incoming rams before losses are applied.
     * Raids suppress less wall power and never inflict permanent damage.
     */
    private function calculateEffectiveWallLevel(int $wallLevel, array $attackingUnits, bool $isRaid): int
    {
        if ($wallLevel <= 0) {
            return 0;
        }

        $ramCount = 0;
        foreach ($attackingUnits as $unit) {
            if (!empty($unit['internal_name']) && $unit['internal_name'] === 'ram') {
                $ramCount += $unit['count'];
            }
        }

        if ($ramCount <= 0) {
            return $wallLevel;
        }

        $effectiveness = random_int(
            (int)round(self::RAM_EFFECTIVENESS_MIN * 100),
            (int)round(self::RAM_EFFECTIVENESS_MAX * 100)
        ) / 100;

        $levels_ignored = floor(($ramCount / self::RAMS_PER_WALL_LEVEL) * $effectiveness);

        if ($isRaid) {
            $levels_ignored = floor($levels_ignored * 0.6);
        }

        return max(0, $wallLevel - $levels_ignored);
    }

    /**
     * Calculates distance between two map points
     * 
     * @param int $x1 X coordinate of point 1
     * @param int $y1 Y coordinate of point 1
     * @param int $x2 X coordinate of point 2
     * @param int $y2 Y coordinate of point 2
     * @return float Distance between points
     */
    private function calculateDistance($x1, $y1, $x2, $y2)
    {
        return sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
    }

    /**
     * Helper: fetch research level for a given village and research internal name.
     */
    private function getResearchLevelForVillage(int $villageId, string $researchInternalName): int
    {
        $stmt = $this->conn->prepare("
            SELECT vr.level
            FROM village_research vr
            JOIN research_types rt ON vr.research_type_id = rt.id
            WHERE vr.village_id = ? AND rt.internal_name = ?
        ");
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param("is", $villageId, $researchInternalName);
        $stmt->execute();
        $result = $stmt->get_result();
        $level = 0;
        if ($row = $result->fetch_assoc()) {
            $level = (int)$row['level'];
        }
        $stmt->close();
        return $level;
    }

    /**
     * Helper: return building levels snapshot for a village.
     */
    private function getBuildingSnapshot(int $villageId): array
    {
        $stmt = $this->conn->prepare("
            SELECT bt.internal_name, bt.name, vb.level
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ?
        ");
        $snapshot = [];
        if ($stmt === false) {
            return $snapshot;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $snapshot[$row['internal_name']] = [
                'name' => $row['name'],
                'level' => (int)$row['level']
            ];
        }
        $stmt->close();
        ksort($snapshot);
        return $snapshot;
    }

    /**
     * Helper: return unit snapshot (counts + metadata) for a village.
     */
    private function getVillageUnitSnapshot(int $villageId): array
    {
        $stmt = $this->conn->prepare("
            SELECT ut.internal_name, ut.name, vu.count
            FROM village_units vu
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE vu.village_id = ? AND vu.count > 0
        ");
        $units = [];
        if ($stmt === false) {
            return $units;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $units[] = [
                'internal_name' => $row['internal_name'],
                'name' => $row['name'],
                'count' => (int)$row['count']
            ];
        }
        $stmt->close();
        return $units;
    }

    /**
     * Helper: village name by ID (empty string on failure).
     */
    private function getVillageName(int $villageId): string
    {
        $stmt = $this->conn->prepare("SELECT name FROM villages WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return '';
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res['name'] ?? '';
    }

    /**
     * Helper: return research snapshot (unlocked tech levels) for a village.
     */
    private function getResearchSnapshot(int $villageId): array
    {
        $stmt = $this->conn->prepare("
            SELECT rt.internal_name, rt.name, vr.level
            FROM village_research vr
            JOIN research_types rt ON vr.research_type_id = rt.id
            WHERE vr.village_id = ? AND vr.level > 0
        ");
        $research = [];
        if ($stmt === false) {
            return $research;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $research[$row['internal_name']] = [
                'name' => $row['name'],
                'level' => (int)$row['level']
            ];
        }
        $stmt->close();
        ksort($research);
        return $research;
    }
    
    /**
     * Fetch list of incoming attacks for a village
     * 
     * @param int $village_id Village ID
     * @return array Incoming attacks
     */
    public function getIncomingAttacks($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.source_village_id, a.attack_type, a.start_time, a.arrival_time,
                   v.name as source_village_name, v.x_coord as source_x, v.y_coord as source_y,
                   u.username as attacker_name
            FROM attacks a
            JOIN villages v ON a.source_village_id = v.id
            JOIN users u ON v.user_id = u.id
            WHERE a.target_village_id = ? AND a.is_completed = 0 AND a.is_canceled = 0
            ORDER BY a.arrival_time ASC
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $incoming_attacks = [];
        while ($attack = $result->fetch_assoc()) {
            // Calculate remaining time
            $arrival_time = strtotime($attack['arrival_time']);
            $current_time = time();
            $remaining_time = max(0, $arrival_time - $current_time);
            
            $attack['remaining_time'] = $remaining_time;
            $attack['formatted_remaining_time'] = $this->formatTime($remaining_time);
            $attack['formatted_start_time'] = date('Y-m-d H:i:s', strtotime($attack['start_time']));
            $attack['formatted_arrival_time'] = date('Y-m-d H:i:s', $arrival_time);
            
            $incoming_attacks[] = $attack;
        }
        $stmt->close();
        
        return $incoming_attacks;
    }
    
    /**
     * Fetch list of outgoing attacks for a village
     * 
     * @param int $village_id Village ID
     * @return array Outgoing attacks
     */
    public function getOutgoingAttacks($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.target_village_id, a.attack_type, a.start_time, a.arrival_time,
                   v.name as target_village_name, v.x_coord as target_x, v.y_coord as target_y,
                   u.username as defender_name
            FROM attacks a
            JOIN villages v ON a.target_village_id = v.id
            JOIN users u ON v.user_id = u.id
            WHERE a.source_village_id = ? AND a.is_completed = 0 AND a.is_canceled = 0
            ORDER BY a.arrival_time ASC
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $outgoing_attacks = [];
        while ($attack = $result->fetch_assoc()) {
            // Calculate remaining time
            $arrival_time = strtotime($attack['arrival_time']);
            $start_time = strtotime($attack['start_time']);
            $current_time = time();
            $remaining_time = max(0, $arrival_time - $current_time);
            $travel_time = max(1, $arrival_time - $start_time);
            $return_time = $arrival_time + $travel_time;
            $return_remaining = max(0, $return_time - $current_time);
            
            $attack['remaining_time'] = $remaining_time;
            $attack['formatted_remaining_time'] = $this->formatTime($remaining_time);
            $attack['formatted_start_time'] = date('Y-m-d H:i:s', $start_time);
            $attack['formatted_arrival_time'] = date('Y-m-d H:i:s', $arrival_time);
            $attack['formatted_return_time'] = date('Y-m-d H:i:s', $return_time);
            $attack['formatted_return_remaining'] = $this->formatTime($return_remaining);
            
            // Add info about sent units
            $attack['units'] = $this->getAttackUnits($attack['id']);
            
            $outgoing_attacks[] = $attack;
        }
        $stmt->close();
        
        return $outgoing_attacks;
    }
    
    /**
     * Fetch units involved in an attack
     * 
     * @param int $attack_id Attack ID
     * @return array Units list
     */
    public function getAttackUnits($attack_id)
    {
        $stmt = $this->conn->prepare("
            SELECT au.unit_type_id, au.count, ut.name, ut.internal_name
            FROM attack_units au
            JOIN unit_types ut ON au.unit_type_id = ut.id
            WHERE au.attack_id = ?
        ");
        $stmt->bind_param("i", $attack_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $units = [];
        while ($unit = $result->fetch_assoc()) {
            $units[] = $unit;
        }
        $stmt->close();
        
        return $units;
    }
    
    /**
     * Fetch a battle report
     * 
     * @param int $report_id Report ID
     * @param int $user_id User ID (permission check)
     * @return array Battle report data
     */
    public function getBattleReport($report_id, $user_id)
    {
        $stmt = $this->conn->prepare("
            SELECT
                br.id, br.attack_id, br.source_village_id, br.target_village_id,
                br.battle_time, br.attacker_won, br.report_data,
                a.attack_type,
                sv.name as source_village_name, sv.x_coord as source_x, sv.y_coord as source_y, sv.user_id as source_user_id,
                tv.name as target_village_name, tv.x_coord as target_x, tv.y_coord as target_y, tv.user_id as target_user_id,
                attacker.username as attacker_name, defender.username as defender_name
            FROM battle_reports br
            JOIN attacks a ON a.id = br.attack_id
            JOIN villages sv ON br.source_village_id = sv.id
            JOIN villages tv ON br.target_village_id = tv.id
            JOIN users attacker ON sv.user_id = attacker.id
            JOIN users defender ON tv.user_id = defender.id
            WHERE br.id = ? AND (sv.user_id = ? OR tv.user_id = ?)
            LIMIT 1
        ");
        if ($stmt === false) {
            return [
                'success' => false,
                'error' => 'Failed to load report.'
            ];
        }

        $stmt->bind_param("iii", $report_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'Report does not exist or you do not have access.'
            ];
        }

        $report_row = $result->fetch_assoc();
        $stmt->close();

        $details = json_decode($report_row['report_data'], true);
        if (!is_array($details)) {
            $details = [];
        }

        $type = $details['type'] ?? $report_row['attack_type'] ?? 'battle';

        // Build unit summaries from the stored details (if present)
        $attacker_units = [];
        $defender_units = [];

        if (!empty($details['attacker_losses']) && is_array($details['attacker_losses'])) {
            foreach ($details['attacker_losses'] as $unit_type_id => $unit) {
                $attacker_units[] = [
                    'unit_type_id' => (int)$unit_type_id,
                    'name' => $unit['unit_name'] ?? 'Unit',
                    'initial_count' => $unit['initial_count'] ?? 0,
                    'lost_count' => $unit['lost_count'] ?? 0,
                    'remaining_count' => $unit['remaining_count'] ?? 0
                ];
            }
        }

        if (!empty($details['defender_losses']) && is_array($details['defender_losses'])) {
            foreach ($details['defender_losses'] as $unit_type_id => $unit) {
                $defender_units[] = [
                    'unit_type_id' => (int)$unit_type_id,
                    'name' => $unit['unit_name'] ?? 'Unit',
                    'initial_count' => $unit['initial_count'] ?? 0,
                    'lost_count' => $unit['lost_count'] ?? 0,
                    'remaining_count' => $unit['remaining_count'] ?? 0
                ];
            }
        }

        // Spy reports store counts differently
        if ($type === 'spy' && empty($attacker_units) && isset($details['attacker_spies_sent'])) {
            $attacker_units[] = [
                'unit_type_id' => null,
                'name' => 'Scout',
                'initial_count' => $details['attacker_spies_sent'],
                'lost_count' => $details['attacker_spies_lost'] ?? 0,
                'remaining_count' => $details['attacker_spies_returned'] ?? 0
            ];
            $defender_units[] = [
                'unit_type_id' => null,
                'name' => 'Defender scouts',
                'initial_count' => $details['defender_spies'] ?? 0,
                'lost_count' => $details['defender_spies_lost'] ?? 0,
                'remaining_count' => $details['defender_spies_remaining'] ?? ($details['defender_spies'] ?? 0)
            ];
        }

        $report = [
            'id' => $report_row['id'],
            'attack_id' => $report_row['attack_id'],
            'attack_type' => $report_row['attack_type'],
            'type' => $type,
            'attacker_won' => (bool)$report_row['attacker_won'],
            'battle_time' => $report_row['battle_time'],
            'attacker_name' => $report_row['attacker_name'],
            'defender_name' => $report_row['defender_name'],
            'source_village_name' => $report_row['source_village_name'],
            'target_village_name' => $report_row['target_village_name'],
            'source_x' => $report_row['source_x'],
            'source_y' => $report_row['source_y'],
            'target_x' => $report_row['target_x'],
            'target_y' => $report_row['target_y'],
            'details' => $details,
            'attacker_units' => $attacker_units,
            'defender_units' => $defender_units
        ];

        return [
            'success' => true,
            'report' => $report
        ];
    }
    
    /**
     * Format seconds into a readable hh:mm:ss string.
     * 
     * @param int $seconds Time in seconds
     * @return string Formatted time
     */
    private function formatTime($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * Fetch paginated battle reports for a user.
     *
     * @param int $userId User ID.
     * @param int $limit Reports per page.
     * @param int $offset Offset dla paginacji.
     * @return array Battle reports.
     */
    public function getBattleReportsForUser(int $userId, int $limit, int $offset): array
    {
        $reports = [];
        $stmt = $this->conn->prepare("
            SELECT
                br.id, br.attacker_won, br.battle_time as created_at, br.report_data,
                a.attack_type,
                sv.name as source_village_name, sv.x_coord as source_x, sv.y_coord as source_y, sv.user_id as source_user_id,
                tv.name as target_village_name, tv.x_coord as target_x, tv.y_coord as target_y, tv.user_id as target_user_id,
                u_attacker.username as attacker_name, u_defender.username as defender_name
            FROM battle_reports br
            JOIN attacks a ON a.id = br.attack_id
            JOIN villages sv ON br.source_village_id = sv.id
            JOIN villages tv ON br.target_village_id = tv.id
            JOIN users u_attacker ON sv.user_id = u_attacker.id
            JOIN users u_defender ON tv.user_id = u_defender.id
            WHERE sv.user_id = ? OR tv.user_id = ?
            ORDER BY br.battle_time DESC
            LIMIT ? OFFSET ?
        ");
        // Bind parameters in order: sv.user_id, tv.user_id, limit, offset
        $stmt->bind_param("iiii", $userId, $userId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $details = json_decode($row['report_data'] ?? '', true);
            if (!is_array($details)) {
                $details = [];
            }
            // Determine whether the user was attacker or defender
            $row['is_attacker'] = ($row['source_user_id'] == $userId);
            $row['type'] = $details['type'] ?? $row['attack_type'] ?? 'battle';
            // Format the date (could also be done in the frontend)
            $row['formatted_date'] = date('d.m.Y H:i:s', strtotime($row['created_at']));
            $row['report_id'] = $row['id'];
            $reports[] = $row;
        }
        $stmt->close();

        return $reports;
    }

    /**
     * Fetch total number of battle reports for the user.
     *
     * @param int $userId User ID.
     * @return int Total number of reports.
     */
    public function getTotalBattleReportsForUser(int $userId): int
    {
        $countQuery = "SELECT COUNT(*) as total
                     FROM battle_reports br
                     JOIN villages sv ON br.source_village_id = sv.id
                     JOIN villages tv ON br.target_village_id = tv.id
                     WHERE sv.user_id = ? OR tv.user_id = ?";
        $countStmt = $this->conn->prepare($countQuery);
        $countStmt->bind_param("ii", $userId, $userId);
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();

        return $countResult['total'] ?? 0;
    }
} 
