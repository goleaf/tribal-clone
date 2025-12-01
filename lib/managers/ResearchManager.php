<?php

class ResearchManager {
    private $conn;
    private $research_types_cache = [];

    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->loadResearchTypes();
    }

    /**
     * Loads all research types into cache for faster access.
     */
    private function loadResearchTypes() {
        $stmt = $this->conn->prepare("SELECT * FROM research_types");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $this->research_types_cache[$row['internal_name']] = $row;
        }
        $stmt->close();
    }

    /**
     * Returns research type info.
     *
     * @param string $internal_name Internal research name
     * @return array|null Research info or null when not found
     */
    public function getResearchType($internal_name) {
        return $this->research_types_cache[$internal_name] ?? null;
    }

    /**
     * Returns all research types for a given building type.
     *
     * @param string $buildingType Building type (e.g., 'smithy', 'academy')
     * @return array Research type list
     */
    public function getResearchTypesForBuilding($buildingType) {
        $result = [];
        foreach ($this->research_types_cache as $research) {
            if ($research['building_type'] === $buildingType && $research['is_active'] == 1) {
                $result[$research['internal_name']] = $research;
            }
        }
        return $result;
    }

    /**
     * Gets all research levels for a village.
     *
     * @param int $villageId Village ID
     * @return array Levels keyed by internal_name
     */
    public function getVillageResearchLevels($villageId) {
        $result = [];
        
        // Seed all research with default level 0
        foreach ($this->research_types_cache as $internal_name => $research) {
            $result[$internal_name] = 0;
        }
        
        // Fetch actual levels from the database
        $stmt = $this->conn->prepare("
            SELECT rt.internal_name, vr.level
            FROM village_research vr
            JOIN research_types rt ON vr.research_type_id = rt.id
            WHERE vr.village_id = ?
        ");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $db_result = $stmt->get_result();
        
        while ($row = $db_result->fetch_assoc()) {
            $result[$row['internal_name']] = (int)$row['level'];
        }
        $stmt->close();
        
        return $result;
    }

    /**
     * Checks whether research requirements are satisfied.
     *
     * @param int $researchTypeId Research type ID
     * @param int $villageId Village ID
     * @param int $targetLevel Target research level
     * @return array Status and requirement details
     */
    public function checkResearchRequirements($researchTypeId, $villageId, $targetLevel = null) {
        $response = [
            'can_research' => false,
            'reason' => 'unknown',
            'required_building_level' => 0,
            'current_building_level' => 0,
            'prerequisite_name' => null,
            'prerequisite_required_level' => 0,
            'prerequisite_current_level' => 0
        ];

        // Fetch research type info
        $stmt = $this->conn->prepare("SELECT * FROM research_types WHERE id = ?");
        $stmt->bind_param("i", $researchTypeId);
        $stmt->execute();
        $research = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$research) {
            $response['reason'] = 'research_not_found';
            return $response;
        }

        // If no target level provided, assume the next level
        if ($targetLevel === null) {
            // Fetch current research level
            $stmt = $this->conn->prepare("
                SELECT level FROM village_research 
                WHERE village_id = ? AND research_type_id = ?
            ");
            $stmt->bind_param("ii", $villageId, $researchTypeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_level = 0;
            
            if ($row = $result->fetch_assoc()) {
                $current_level = (int)$row['level'];
            }
            $stmt->close();
            
            $targetLevel = $current_level + 1;
        }

        // Ensure we are not exceeding the max level
        if ($targetLevel > $research['max_level']) {
            $response['reason'] = 'max_level_reached';
            return $response;
        }

        // Check building level requirement
        $buildingType = $research['building_type'];
        $requiredLevel = $research['required_building_level'];
        $response['required_building_level'] = $requiredLevel;

        $stmt = $this->conn->prepare("
            SELECT vb.level 
            FROM village_buildings vb 
            JOIN building_types bt ON vb.building_type_id = bt.id 
            WHERE bt.internal_name = ? AND vb.village_id = ?
        ");
        $stmt->bind_param("si", $buildingType, $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $building = $result->fetch_assoc();
        $stmt->close();

        if (!$building) {
            $response['reason'] = 'building_not_found';
            return $response;
        }

        $currentLevel = $building['level'];
        $response['current_building_level'] = $currentLevel;

        if ($currentLevel < $requiredLevel) {
            $response['reason'] = 'building_level_too_low';
            return $response;
        }

        // Check prerequisite research if defined
        if ($research['prerequisite_research_id']) {
            $prereq_id = $research['prerequisite_research_id'];
            $prereq_level = $research['prerequisite_research_level'];
            
            // Fetch info about the prerequisite research
            $stmt = $this->conn->prepare("SELECT name, internal_name FROM research_types WHERE id = ?");
            $stmt->bind_param("i", $prereq_id);
            $stmt->execute();
            $prereq_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($prereq_info) {
                $response['prerequisite_name'] = $prereq_info['name'];
                $response['prerequisite_required_level'] = $prereq_level;
                
                // Check current level of the prerequisite research
                $stmt = $this->conn->prepare("
                    SELECT level FROM village_research 
                    WHERE village_id = ? AND research_type_id = ?
                ");
                $stmt->bind_param("ii", $villageId, $prereq_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $prereq_current_level = 0;
                
                if ($row = $result->fetch_assoc()) {
                    $prereq_current_level = (int)$row['level'];
                }
                $stmt->close();
                
                $response['prerequisite_current_level'] = $prereq_current_level;
                
                if ($prereq_current_level < $prereq_level) {
                    $response['reason'] = 'prerequisite_not_met';
                    return $response;
                }
            }
        }

        $response['can_research'] = true;
        $response['reason'] = 'ok';
        return $response;
    }

    /**
     * Calculates research cost for a given type and level.
     *
     * @param int $researchTypeId Research type ID
     * @param int $targetLevel Target research level
     * @return array|null Cost [wood, clay, iron] or null on failure
     */
    public function getResearchCost($researchTypeId, $targetLevel) {
        if ($targetLevel <= 0) return null;
        
        $stmt = $this->conn->prepare("SELECT * FROM research_types WHERE id = ?");
        $stmt->bind_param("i", $researchTypeId);
        $stmt->execute();
        $research = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$research || $targetLevel > $research['max_level']) {
            return null;
        }

        // Cost formula: base_cost * (cost_factor ^ (level - 1))
        // Uses a dedicated research factor
        $cost_factor = 1.2; // Default multiplier; could be stored per research
        
        $cost_wood = floor($research['cost_wood'] * pow($cost_factor, $targetLevel - 1));
        $cost_clay = floor($research['cost_clay'] * pow($cost_factor, $targetLevel - 1));
        $cost_iron = floor($research['cost_iron'] * pow($cost_factor, $targetLevel - 1));
        
        return [
            'wood' => $cost_wood,
            'clay' => $cost_clay,
            'iron' => $cost_iron
        ];
    }

    /**
     * Calculates the time needed to perform research.
     *
     * @param int $researchTypeId Research type ID
     * @param int $targetLevel Target research level
     * @param int $buildingLevel Level of the research building
     * @return int|null Research time in seconds or null on failure
     */
    public function calculateResearchTime($researchTypeId, $targetLevel, $buildingLevel) {
        if ($targetLevel <= 0) return null;
        
        $stmt = $this->conn->prepare("SELECT * FROM research_types WHERE id = ?");
        $stmt->bind_param("i", $researchTypeId);
        $stmt->execute();
        $research = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$research || $targetLevel > $research['max_level']) {
            return null;
        }

        // Base research time
        $base_time = $research['research_time_base'];
        
        // Calculate time for the target level
        $time_for_level = floor($base_time * pow($research['research_time_factor'], $targetLevel - 1));
        
        // Reduce time based on building level
        // Example: time / (1 + (building_level * 0.05))
        // This factor can vary by building type
        $time_reduction_factor = 0.05;
        $time_with_building = floor($time_for_level / (1 + ($buildingLevel * $time_reduction_factor)));
        
        return max(10, $time_with_building); // Minimum research time is 10 seconds
    }

    /**
     * Starts research in a village.
     *
     * @param int $villageId Village ID
     * @param int $researchTypeId Research type ID
     * @param int $targetLevel Target research level
     * @param array $resources Available village resources [wood, clay, iron]
     * @return array Operation status and any errors
     */
    public function startResearch($villageId, $researchTypeId, $targetLevel, $resources) {
        $response = [
            'success' => false,
            'message' => '',
            'error' => '',
            'research_id' => 0,
            'ends_at' => null
        ];
        
        // Ensure this research type is not already queued
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM research_queue 
            WHERE village_id = ? AND research_type_id = ?
        ");
        $stmt->bind_param("ii", $villageId, $researchTypeId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['count'] > 0) {
            $response['error'] = 'This research type is already in progress.';
            return $response;
        }
        
        // Validate requirements
        $req_check = $this->checkResearchRequirements($researchTypeId, $villageId, $targetLevel);
        if (!$req_check['can_research']) {
            $response['error'] = 'Research requirements are not met.';
            $response['reason'] = $req_check['reason'];
            return $response;
        }
        
        // Fetch research cost
        $cost = $this->getResearchCost($researchTypeId, $targetLevel);
        if (!$cost) {
            $response['error'] = 'Cannot calculate research cost.';
            return $response;
        }
        
        // Ensure the player has enough resources
        if ($resources['wood'] < $cost['wood'] || 
            $resources['clay'] < $cost['clay'] || 
            $resources['iron'] < $cost['iron']) {
            $response['error'] = 'Not enough resources to conduct the research.';
            return $response;
        }
        
        // Get the research building level
        $research_type = $this->getResearchTypeById($researchTypeId);
        if (!$research_type) {
            $response['error'] = 'Invalid research type.';
            return $response;
        }
        
        $building_type = $research_type['building_type'];
        $stmt = $this->conn->prepare("
            SELECT vb.level 
            FROM village_buildings vb 
            JOIN building_types bt ON vb.building_type_id = bt.id 
            WHERE bt.internal_name = ? AND vb.village_id = ?
        ");
        $stmt->bind_param("si", $building_type, $villageId);
        $stmt->execute();
        $building = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$building) {
            $response['error'] = 'Required building not found.';
            return $response;
        }
        
        $building_level = (int)$building['level'];
        
        // Calculate research time
        $research_time = $this->calculateResearchTime($researchTypeId, $targetLevel, $building_level);
        if (!$research_time) {
            $response['error'] = 'Cannot calculate research time.';
            return $response;
        }
        
        // Begin transaction
        $this->conn->begin_transaction();
        
        try {
            // Deduct resources
            $stmt = $this->conn->prepare("
                UPDATE villages 
                SET wood = wood - ?, clay = clay - ?, iron = iron - ? 
                WHERE id = ?
            ");
            $stmt->bind_param("dddi", $cost['wood'], $cost['clay'], $cost['iron'], $villageId);
            if (!$stmt->execute()) {
                throw new Exception("Resource update failed.");
            }
            $stmt->close();
            
            // Calculate finish time
            $end_time = time() + $research_time;
            $end_time_sql = date('Y-m-d H:i:s', $end_time);
            
            // Add research to the queue
            $stmt = $this->conn->prepare("
                INSERT INTO research_queue 
                (village_id, research_type_id, level_after, ends_at) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiis", $villageId, $researchTypeId, $targetLevel, $end_time_sql);
            if (!$stmt->execute()) {
                throw new Exception("Failed to add research to the queue.");
            }
            $queue_id = $stmt->insert_id;
            $stmt->close();
            
            $this->conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Research started successfully.';
            $response['research_id'] = $queue_id;
            $response['ends_at'] = $end_time_sql;
            
            return $response;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            $response['error'] = 'An error occurred while starting the research: ' . $e->getMessage();
            return $response;
        }
    }

    /**
     * Cancel a running research task.
     * 
     * @param int $queueId Research queue ID
     * @param int $userId User ID (for verification)
     * @return array Operation status
     */
    public function cancelResearch($queueId, $userId) {
        $response = [
            'success' => false,
            'message' => '',
            'error' => '',
            'refunded' => [
                'wood' => 0,
                'clay' => 0,
                'iron' => 0
            ]
        ];
        
        // Get queued research info
        $stmt = $this->conn->prepare("
            SELECT rq.*, v.user_id, rt.cost_wood, rt.cost_clay, rt.cost_iron, rt.research_time_factor
            FROM research_queue rq
            JOIN villages v ON rq.village_id = v.id
            JOIN research_types rt ON rq.research_type_id = rt.id
            WHERE rq.id = ?
        ");
        $stmt->bind_param("i", $queueId);
        $stmt->execute();
        $queue_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$queue_item) {
            $response['error'] = 'Research to cancel was not found.';
            return $response;
        }
        
        // Verify research belongs to the user
        if ($queue_item['user_id'] != $userId) {
            $response['error'] = 'You do not have permission to cancel this research.';
            return $response;
        }
        
        // Calculate refund
        $current_time = time();
        $start_time = strtotime($queue_item['started_at']);
        $end_time = strtotime($queue_item['ends_at']);
        $total_time = $end_time - $start_time;
        $elapsed_time = $current_time - $start_time;
        
        // If research barely started, refund 100%
        if ($elapsed_time <= 10) {
            $refund_percentage = 1.0;
        } else {
            // Proportional refund based on remaining time (minimum 50%)
            $remaining_percentage = max(0, 1 - ($elapsed_time / $total_time));
            $refund_percentage = 0.5 + ($remaining_percentage * 0.5);
        }
        
        // Compute resource refunds
        $refund_wood = floor($queue_item['cost_wood'] * $refund_percentage);
        $refund_clay = floor($queue_item['cost_clay'] * $refund_percentage);
        $refund_iron = floor($queue_item['cost_iron'] * $refund_percentage);
        
        $response['refunded'] = [
            'wood' => $refund_wood,
            'clay' => $refund_clay,
            'iron' => $refund_iron
        ];
        
        $village_id = $queue_item['village_id'];
        
        // Begin transaction
        $this->conn->begin_transaction();
        
        try {
            // Refund resources
            $stmt = $this->conn->prepare("
                UPDATE villages 
                SET wood = wood + ?, clay = clay + ?, iron = iron + ? 
                WHERE id = ?
            ");
            $stmt->bind_param("dddi", $refund_wood, $refund_clay, $refund_iron, $village_id);
            if (!$stmt->execute()) {
                throw new Exception("Resource update failed.");
            }
            $stmt->close();
            
            // Remove task from queue
            $stmt = $this->conn->prepare("DELETE FROM research_queue WHERE id = ?");
            $stmt->bind_param("i", $queueId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to remove research from the queue.");
            }
            $stmt->close();
            
            $this->conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Research cancelled successfully. Some resources were refunded.';
            
            return $response;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            $response['error'] = 'An error occurred while cancelling the research: ' . $e->getMessage();
            return $response;
        }
    }

    /**
     * Processes the research queue for a village.
     * 
     * @param int $villageId Village ID
     * @return array Status and info about completed research
     */
    public function processResearchQueue($villageId) {
        $response = [
            'completed_research' => [],
            'updated_queue' => []
        ];
        
        // Fetch all completed/ready research for the village
        $stmt = $this->conn->prepare("
            SELECT rq.*, rt.name, rt.internal_name
            FROM research_queue rq
            JOIN research_types rt ON rq.research_type_id = rt.id
            WHERE rq.village_id = ? AND rq.ends_at <= NOW()
        ");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $completed = [];
        
        while ($queue_item = $result->fetch_assoc()) {
            $completed[] = $queue_item;
        }
        $stmt->close();
        
        if (empty($completed)) {
            return $response;
        }
        
        // Begin transaction
        $this->conn->begin_transaction();
        
        try {
            foreach ($completed as $item) {
                $research_type_id = $item['research_type_id'];
                $level_after = $item['level_after'];
                
                // Check if an entry already exists in village_research
                $stmt = $this->conn->prepare("
                    SELECT id, level FROM village_research 
                    WHERE village_id = ? AND research_type_id = ?
                ");
                $stmt->bind_param("ii", $villageId, $research_type_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing = $result->fetch_assoc();
                $stmt->close();
                
                if ($existing) {
                    // Update existing entry
                    $stmt = $this->conn->prepare("
                        UPDATE village_research 
                        SET level = ? 
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ii", $level_after, $existing['id']);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update research level.");
                    }
                    $stmt->close();
                } else {
                    // Create new entry
                    $stmt = $this->conn->prepare("
                        INSERT INTO village_research 
                        (village_id, research_type_id, level) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->bind_param("iii", $villageId, $research_type_id, $level_after);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to add new research entry.");
                    }
                    $stmt->close();
                }
                
                // Remove research from queue
                $stmt = $this->conn->prepare("DELETE FROM research_queue WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to remove research from queue.");
                }
                $stmt->close();
                
                // Add completion info to response
                $response['completed_research'][] = [
                    'research_name' => $item['name'],
                    'research_internal_name' => $item['internal_name'],
                    'level' => $level_after
                ];
            }
            
            $this->conn->commit();
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Research queue processing failed: " . $e->getMessage());
        }
        
        return $response;
    }

    /**
     * Fetches research currently queued for a village.
     * 
     * @param int $villageId Village ID
     * @return array List of queued research
     */
    public function getResearchQueue($villageId) {
        $queue = [];
        
        $stmt = $this->conn->prepare("
            SELECT rq.*, rt.name, rt.internal_name, rt.building_type
            FROM research_queue rq
            JOIN research_types rt ON rq.research_type_id = rt.id
            WHERE rq.village_id = ?
            ORDER BY rq.ends_at ASC
        ");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Calculate remaining time
            $end_time = strtotime($row['ends_at']);
            $current_time = time();
            $remaining_time = max(0, $end_time - $current_time);
            
            $queue[] = [
                'id' => $row['id'],
                'research_type_id' => $row['research_type_id'],
                'research_name' => $row['name'],
                'research_internal_name' => $row['internal_name'],
                'building_type' => $row['building_type'],
                'level_after' => $row['level_after'],
                'ends_at' => $row['ends_at'],
                'remaining_time' => $remaining_time
            ];
        }
        $stmt->close();
        
        return $queue;
    }

    /**
     * Gets research details by ID.
     * 
     * @param int $researchTypeId Research type ID
     * @return array|null Research details or null
     */
    public function getResearchTypeById($researchTypeId) {
        foreach ($this->research_types_cache as $research) {
            if ($research['id'] == $researchTypeId) {
                return $research;
            }
        }
        return null;
    }

    /**
     * Checks whether research is available for a given building level.
     * 
     * @param string $internalName Research internal name
     * @param int $buildingLevel Building level
     * @return bool True if available, false otherwise
     */
    public function isResearchAvailable($internalName, $buildingLevel) {
        $research = $this->getResearchType($internalName);
        if (!$research) {
            return false;
        }

        return $buildingLevel >= $research['required_building_level'];
    }
}
?> 
