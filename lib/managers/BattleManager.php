<?php
/**
 * BattleManager handles battles between villages.
 */
class BattleManager
{
    private $conn;
    private $villageManager;
    private $buildingManager; // BuildingManager dependency

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
    }
    
    /**
     * Sends an attack from one village to another.
     * 
     * @param int $source_village_id Attacker village ID
     * @param int $target_village_id Target village ID
     * @param array $units_sent Map of unit type IDs to counts
     * @param string $attack_type Attack type ('attack', 'raid', 'support')
     * @param string|null $target_building Target building for catapults
     * @return array Operation status
     */
    public function sendAttack($source_village_id, $target_village_id, $units_sent, $attack_type = 'attack', $target_building = null)
    {
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
        
        // Calculate distance and travel time
        $distance = $this->calculateDistance(
            $villages['source_x'], $villages['source_y'],
            $villages['target_x'], $villages['target_y']
        );
        
        // Find the slowest unit
        $stmt_get_speed = $this->conn->prepare("
            SELECT unit_type_id, speed 
            FROM unit_types
            WHERE id IN (" . implode(',', array_keys($units_sent)) . ")
            ORDER BY speed ASC
            LIMIT 1
        ");
        $stmt_get_speed->execute();
        $speed_result = $stmt_get_speed->get_result();
        $slowest_unit = $speed_result->fetch_assoc();
        $stmt_get_speed->close();
        
        if (!$slowest_unit) {
            return [
                'success' => false,
                'error' => 'Unit information could not be found.'
            ];
        }
        
        // Calculate travel time in seconds (higher speed value means a slower unit)
        $travel_time = ceil($distance * $slowest_unit['speed'] * 60); // in seconds
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
            
            // Return units to the source village
            foreach ($units_to_return as $unit_type_id => $count) {
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
                    // Insert new unit rows
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
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'The attack was canceled and the units returned to the village.',
                'attack_id' => $attack_id,
                'returned_units' => $units_to_return
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
            // Process a single battle - this method generates a report and updates the DB
            $battle_result = $this->processBattle($attack['id']);

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
                     // processBattle creates the report, so fetch it immediately after.
                     $report = $this->getBattleReportForAttack($attack['id']); // Dedicated helper

                     if ($report) {
                         $source_name = htmlspecialchars($attack_details['source_name']);
                         $target_name = htmlspecialchars($attack_details['target_name']);
                         $winner = $report['winner']; // 'attacker' or 'defender'
                         $loot = json_decode($report['details_json'], true)['loot'] ?? ['wood' => 0, 'clay' => 0, 'iron' => 0];

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
            SELECT id, winner, details_json
            FROM battle_reports
            WHERE attack_id = ?
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
         return $report;
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
        // Fetch attacking units
        $stmt_get_attack_units = $this->conn->prepare("
            SELECT au.unit_type_id, au.count, ut.attack, ut.defense, ut.name, ut.carry_capacity, ut.internal_name
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
            SELECT vu.unit_type_id, vu.count, ut.attack, ut.defense, ut.name
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
        // --- RANDOMNESS: +/-10% ---
        $attack_random = mt_rand(90, 110) / 100;
        $defense_random = mt_rand(90, 110) / 100;
        // --- MORALE: simple factor (e.g., weaker attacker could get bonus) ---
        $morale = 1.0;
        // Placeholder morale calculation:
        // $morale = min(1.5, max(0.5, $attacker_points / max($defender_points,1)));
        // --- TOTAL STRENGTH ---
        $total_attack_strength = 0;
        foreach ($attacking_units as $unit) {
            $total_attack_strength += $unit['attack'] * $unit['count'];
        }
        $total_defense_strength = 0;
        foreach ($defending_units as $unit) {
            $total_defense_strength += $unit['defense'] * $unit['count'];
        }

        // --- WALL BONUS ---
        $wall_level = $this->buildingManager->getBuildingLevel($attack['target_village_id'], 'wall');
        $wall_bonus = $this->buildingManager->getWallDefenseBonus($wall_level);

        $total_attack_strength = round($total_attack_strength * $attack_random * $morale);
        $total_defense_strength = round($total_defense_strength * $wall_bonus * $defense_random);
        // --- LOSSES ---
        $attacker_win = $total_attack_strength > $total_defense_strength;

        $attacker_losses = [];
        $remaining_attacking_units = [];
        $defender_losses = [];
        $remaining_defending_units = [];

        if ($attacker_win) {
            // Attacker wins: defender loses all, attacker loses proportionally
            $loss_ratio = ($total_defense_strength / max(1, $total_attack_strength)) ** 0.5;

            foreach ($attacking_units as $unit_type_id => $unit) {
                $loss_count = round($unit['count'] * $loss_ratio);
                $remaining_count = $unit['count'] - $loss_count;
                $attacker_losses[$unit_type_id] = [
                    'unit_name' => $unit['name'], 'initial_count' => $unit['count'],
                    'lost_count' => $loss_count, 'remaining_count' => $remaining_count
                ];
                if ($remaining_count > 0) {
                    $remaining_attacking_units[$unit_type_id] = $remaining_count;
                }
            }

            foreach ($defending_units as $unit_type_id => $unit) {
                $defender_losses[$unit_type_id] = [
                    'unit_name' => $unit['name'], 'initial_count' => $unit['count'],
                    'lost_count' => $unit['count'], 'remaining_count' => 0
                ];
            }
        } else {
            // Defender wins or draw: attacker loses all units, defender loses proportionally
            $loss_ratio = ($total_attack_strength / max(1, $total_defense_strength)) ** 0.5;

            foreach ($attacking_units as $unit_type_id => $unit) {
                $attacker_losses[$unit_type_id] = [
                    'unit_name' => $unit['name'], 'initial_count' => $unit['count'],
                    'lost_count' => $unit['count'], 'remaining_count' => 0
                ];
            }

            foreach ($defending_units as $unit_type_id => $unit) {
                $loss_count = round($unit['count'] * $loss_ratio);
                $remaining_count = $unit['count'] - $loss_count;
                $defender_losses[$unit_type_id] = [
                    'unit_name' => $unit['name'], 'initial_count' => $unit['count'],
                    'lost_count' => $loss_count, 'remaining_count' => $remaining_count
                ];
                if ($remaining_count > 0) {
                    $remaining_defending_units[$unit_type_id] = $remaining_count;
                }
            }
        }
        // --- LOOT ---
        $loot = [ 'wood' => 0, 'clay' => 0, 'iron' => 0 ];
        if ($attacker_win && $attack_capacity > 0) {
            // Pull resources from the target village
            $stmt_res = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
            $stmt_res->bind_param("i", $attack['target_village_id']);
            $stmt_res->execute();
            $res = $stmt_res->get_result()->fetch_assoc();
            $stmt_res->close();
            $total_loot = min($attack_capacity, $res['wood'] + $res['clay'] + $res['iron']);
            // Distribute loot proportionally
            $sum = $res['wood'] + $res['clay'] + $res['iron'];
            if ($sum > 0) {
                $loot['wood'] = floor($total_loot * ($res['wood'] / $sum));
                $loot['clay'] = floor($total_loot * ($res['clay'] / $sum));
                $loot['iron'] = $total_loot - $loot['wood'] - $loot['clay'];
            }
            // Subtract resources from the village
            $stmt_update = $this->conn->prepare("UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ? WHERE id = ?");
            $stmt_update->bind_param("iiii", $loot['wood'], $loot['clay'], $loot['iron'], $attack['target_village_id']);
            $stmt_update->execute();
            $stmt_update->close();
        }

        // --- WALL DAMAGE (RAMS) ---
        $wall_damage_report = ['initial_level' => $wall_level, 'final_level' => $wall_level];
        if ($attacker_win) {
            $surviving_rams = 0;
            foreach ($remaining_attacking_units as $unit_type_id => $count) {
                if (isset($attacking_units[$unit_type_id]) && $attacking_units[$unit_type_id]['internal_name'] === 'ram') {
                    $surviving_rams += $count;
                }
            }

            if ($surviving_rams > 0 && $wall_level > 0) {
                $ram_effectiveness = (mt_rand(8, 12) / 10); // 0.8 to 1.2
                $levels_destroyed = floor(($surviving_rams / 4) * $ram_effectiveness);

                if ($levels_destroyed > 0) {
                    $new_wall_level = max(0, $wall_level - $levels_destroyed);
                    $wall_damage_report['final_level'] = $new_wall_level;
                }
            }
        }

        // --- BUILDING DAMAGE (CATAPULTS) ---
        $building_damage_report = null;
        if ($attacker_win) {
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
                        $catapult_effectiveness = (mt_rand(8, 12) / 10);
                        $levels_destroyed = floor(($surviving_catapults / 8) * $catapult_effectiveness);

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

            // Mark the attack as completed
            $stmt_complete_attack = $this->conn->prepare("
                UPDATE attacks 
                SET is_completed = 1 
                WHERE id = ?
            ");
            $stmt_complete_attack->bind_param("i", $attack_id);
            $stmt_complete_attack->execute();
            $stmt_complete_attack->close();
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
            // Get user IDs for the report
            $stmt_users = $this->conn->prepare("SELECT v1.user_id as attacker_user_id, v2.user_id as defender_user_id FROM villages v1, villages v2 WHERE v1.id = ? AND v2.id = ?");
            $stmt_users->bind_param("ii", $attack['source_village_id'], $attack['target_village_id']);
            $stmt_users->execute();
            $users = $stmt_users->get_result()->fetch_assoc();
            $stmt_users->close();
            $attacker_user_id = $users['attacker_user_id'];
            $defender_user_id = $users['defender_user_id'];

            // Add battle report (with JSON details)
            $details = [
                'attacker_losses' => $attacker_losses,
                'defender_losses' => $defender_losses,
                'loot' => $loot,
                'attack_random' => $attack_random,
                'defense_random' => $defense_random,
                'morale' => $morale,
                'wall_level' => $wall_level,
                'wall_bonus' => $wall_bonus,
                'wall_damage' => $wall_damage_report,
                'building_damage' => $building_damage_report,
                'total_attack_strength' => $total_attack_strength,
                'total_defense_strength' => $total_defense_strength
            ];
            $report_data_json = json_encode($details);
            $attacker_won_int = $attacker_win ? 1 : 0;

            $stmt_add_report = $this->conn->prepare("
                INSERT INTO battle_reports (
                    attack_id, source_village_id, target_village_id,
                    battle_time, attacker_user_id, defender_user_id,
                    attacker_won, report_data
                ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
            ");
            $stmt_add_report->bind_param(
                "iiiiis",
                $attack_id, $attack['source_village_id'], $attack['target_village_id'],
                $attacker_user_id, $defender_user_id,
                $attacker_won_int, $report_data_json
            );
            $stmt_add_report->execute();
            $report_id = $stmt_add_report->insert_id;
            $stmt_add_report->close();

            // Update the attack with the new report id
            $stmt_update_attack = $this->conn->prepare("UPDATE attacks SET report_id = ? WHERE id = ?");
            $stmt_update_attack->bind_param("ii", $report_id, $attack_id);
            $stmt_update_attack->execute();
            $stmt_update_attack->close();
            $this->conn->commit();
            return [ 'success' => true ];
        } catch (Exception $e) {
            $this->conn->rollback();
            return [ 'success' => false, 'error' => $e->getMessage() ];
        }
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
            $current_time = time();
            $remaining_time = max(0, $arrival_time - $current_time);
            
            $attack['remaining_time'] = $remaining_time;
            $attack['formatted_remaining_time'] = $this->formatTime($remaining_time);
            
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
        // Ensure the report exists and the user has access
        $stmt = $this->conn->prepare("
            SELECT br.id, br.attack_id, br.source_village_id, br.target_village_id, 
                   br.attack_type, br.winner, br.total_attack_strength, 
                   br.total_defense_strength, br.created_at,
                   sv.name as source_village_name, sv.x_coord as source_x, sv.y_coord as source_y,
                   tv.name as target_village_name, tv.x_coord as target_x, tv.y_coord as target_y,
                   attacker.username as attacker_name, defender.username as defender_name
            FROM battle_reports br
            JOIN villages sv ON br.source_village_id = sv.id
            JOIN villages tv ON br.target_village_id = tv.id
            JOIN users attacker ON sv.user_id = attacker.id
            JOIN users defender ON tv.user_id = defender.id
            WHERE br.id = ? AND (sv.user_id = ? OR tv.user_id = ?)
        ");
        $stmt->bind_param("iii", $report_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'Report does not exist or you do not have access.'
            ];
        }
        
        $report = $result->fetch_assoc();
        $stmt->close();
        
        // Fetch unit details
        $stmt_units = $this->conn->prepare("
            SELECT bru.unit_type_id, bru.side, bru.initial_count, 
                   bru.lost_count, bru.remaining_count,
                   ut.name, ut.internal_name, ut.attack, ut.defense
            FROM battle_report_units bru
            JOIN unit_types ut ON bru.unit_type_id = ut.id
            WHERE bru.battle_report_id = ?
        ");
        $stmt_units->bind_param("i", $report_id);
        $stmt_units->execute();
        $units_result = $stmt_units->get_result();
        
        $attacker_units = [];
        $defender_units = [];
        
        while ($unit = $units_result->fetch_assoc()) {
            if ($unit['side'] === 'attacker') {
                $attacker_units[] = $unit;
            } else {
                $defender_units[] = $unit;
            }
        }
        $stmt_units->close();
        
        $report['attacker_units'] = $attacker_units;
        $report['defender_units'] = $defender_units;
        
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
                br.report_id, br.attacker_won, br.battle_time as created_at,
                sv.name as source_village_name, sv.x_coord as source_x, sv.y_coord as source_y, sv.user_id as source_user_id,
                tv.name as target_village_name, tv.x_coord as target_x, tv.y_coord as target_y, tv.user_id as target_user_id,
                u_attacker.username as attacker_name, u_defender.username as defender_name,
                r.is_read -- Pobieramy status odczytania z tabeli reports
            FROM battle_reports br
            JOIN villages sv ON br.source_village_id = sv.id
            JOIN villages tv ON br.target_village_id = tv.id
            JOIN users u_attacker ON sv.user_id = u_attacker.id
            JOIN users u_defender ON tv.user_id = u_defender.id
            JOIN reports r ON br.report_id = r.id AND r.user_id = ? -- Join with reports table
            WHERE sv.user_id = ? OR tv.user_id = ?
            ORDER BY br.battle_time DESC
            LIMIT ? OFFSET ?
        ");
        // Bind parameters in order: r.user_id, sv.user_id, tv.user_id, limit, offset
        $stmt->bind_param("iiiii", $userId, $userId, $userId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            // Determine whether the user was attacker or defender
            $row['is_attacker'] = ($row['source_user_id'] == $userId);
            // Format the date (could also be done in the frontend)
            $row['formatted_date'] = date('d.m.Y H:i:s', strtotime($row['created_at']));
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
