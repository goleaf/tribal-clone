<?php
declare(strict_types=1);

/**
 * DeltaCalculator
 * 
 * Computes incremental updates between map states to reduce data transfer.
 * Handles cursor token generation/validation and delta response formatting.
 * 
 * Requirements: 2.3, 2.4
 */
class DeltaCalculator
{
    private Database $db;
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    /**
     * Calculate delta between a cursor state and current state
     * 
     * @param string $cursor Base64-encoded cursor token containing timestamp and version
     * @param array $currentState Current map data [villages, commands, markers]
     * @param int $worldId World identifier
     * @return array Delta with added/modified/removed arrays and next cursor
     */
    public function calculateDelta(string $cursor, array $currentState, int $worldId): array
    {
        // Decode and validate cursor
        $cursorData = $this->decodeCursor($cursor);
        
        if (!$cursorData) {
            throw new InvalidArgumentException('Invalid cursor token');
        }
        
        $lastTimestamp = $cursorData['timestamp'];
        $lastVersion = $cursorData['version'];
        
        // Initialize delta structure
        $delta = [
            'added' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ],
            'modified' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ],
            'removed' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ]
        ];
        
        // Calculate deltas for each entity type
        $delta['added']['villages'] = $this->getAddedVillages($worldId, $lastTimestamp, $currentState['villages'] ?? []);
        $delta['modified']['villages'] = $this->getModifiedVillages($worldId, $lastTimestamp, $currentState['villages'] ?? []);
        $delta['removed']['villages'] = $this->getRemovedVillages($worldId, $lastTimestamp, $currentState['villages'] ?? []);
        
        $delta['added']['commands'] = $this->getAddedCommands($worldId, $lastTimestamp, $currentState['commands'] ?? []);
        $delta['modified']['commands'] = $this->getModifiedCommands($worldId, $lastTimestamp, $currentState['commands'] ?? []);
        $delta['removed']['commands'] = $this->getRemovedCommands($worldId, $lastTimestamp, $currentState['commands'] ?? []);
        
        $delta['added']['markers'] = $this->getAddedMarkers($worldId, $lastTimestamp, $currentState['markers'] ?? []);
        $delta['modified']['markers'] = $this->getModifiedMarkers($worldId, $lastTimestamp, $currentState['markers'] ?? []);
        $delta['removed']['markers'] = $this->getRemovedMarkers($worldId, $lastTimestamp, $currentState['markers'] ?? []);
        
        // Generate next cursor
        $nextCursor = $this->generateCursor($worldId);
        
        return [
            'delta' => $delta,
            'cursor' => $nextCursor,
            'has_more' => false // Will be set to true if pagination is needed
        ];
    }
    
    /**
     * Apply a delta to a base state to produce a new state
     * 
     * @param array $baseState Base map state
     * @param array $delta Delta with added/modified/removed arrays
     * @return array New state after applying delta
     */
    public function applyDelta(array $baseState, array $delta): array
    {
        $newState = [
            'villages' => $baseState['villages'] ?? [],
            'commands' => $baseState['commands'] ?? [],
            'markers' => $baseState['markers'] ?? []
        ];
        
        // Apply changes for each entity type
        foreach (['villages', 'commands', 'markers'] as $entityType) {
            // Remove entities
            if (isset($delta['removed'][$entityType])) {
                $removeIds = array_column($delta['removed'][$entityType], 'id');
                $newState[$entityType] = array_filter(
                    $newState[$entityType],
                    fn($entity) => !in_array($entity['id'], $removeIds)
                );
            }
            
            // Add new entities
            if (isset($delta['added'][$entityType])) {
                foreach ($delta['added'][$entityType] as $entity) {
                    $newState[$entityType][] = $entity;
                }
            }
            
            // Modify existing entities
            if (isset($delta['modified'][$entityType])) {
                foreach ($delta['modified'][$entityType] as $modifiedEntity) {
                    $found = false;
                    foreach ($newState[$entityType] as $key => $entity) {
                        if ($entity['id'] === $modifiedEntity['id']) {
                            $newState[$entityType][$key] = $modifiedEntity;
                            $found = true;
                            break;
                        }
                    }
                    // If not found in base state, add it (handles race conditions)
                    if (!$found) {
                        $newState[$entityType][] = $modifiedEntity;
                    }
                }
            }
            
            // Re-index arrays to maintain clean structure
            $newState[$entityType] = array_values($newState[$entityType]);
        }
        
        return $newState;
    }
    
    /**
     * Generate a cursor token for the current state
     * 
     * @param int $worldId World identifier
     * @return string Base64-encoded cursor token
     */
    public function generateCursor(int $worldId): string
    {
        $timestamp = time();
        $version = $this->getDataVersion($worldId);
        $checksum = $this->calculateStateChecksum($worldId);
        
        $cursorData = [
            'timestamp' => $timestamp,
            'version' => $version,
            'checksum' => $checksum,
            'world_id' => $worldId
        ];
        
        return base64_encode(json_encode($cursorData));
    }
    
    /**
     * Decode and validate a cursor token
     * 
     * @param string $cursor Base64-encoded cursor token
     * @return array|null Decoded cursor data or null if invalid
     */
    private function decodeCursor(string $cursor): ?array
    {
        $decoded = base64_decode($cursor, true);
        
        if ($decoded === false) {
            return null;
        }
        
        $data = json_decode($decoded, true);
        
        if (!is_array($data) || !isset($data['timestamp'], $data['version'], $data['checksum'])) {
            return null;
        }
        
        // Validate timestamp is not too old (e.g., 24 hours)
        if ($data['timestamp'] < (time() - 86400)) {
            return null;
        }
        
        return $data;
    }
    
    /**
     * Get villages added since the last timestamp
     */
    private function getAddedVillages(int $worldId, int $lastTimestamp, array $currentVillages): array
    {
        try {
            $query = "SELECT v.id, v.x, v.y, v.name, v.user_id, v.points, 
                             CASE WHEN v.user_id IS NULL THEN 1 ELSE 0 END as is_barbarian,
                             u.tribe_id
                      FROM villages v
                      LEFT JOIN users u ON v.user_id = u.id
                      WHERE v.world_id = ? AND v.created_at > ?";
            
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                return [];
            }
            
            $stmt->bind_param("ii", $worldId, $lastTimestamp);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $results = [];
            while ($row = $result->fetch_assoc()) {
                $results[] = [
                    'id' => (int)$row['id'],
                    'coords' => ['x' => (int)$row['x'], 'y' => (int)$row['y']],
                    'name' => $row['name'],
                    'playerId' => $row['user_id'] ? (int)$row['user_id'] : null,
                    'tribeId' => $row['tribe_id'] ? (int)$row['tribe_id'] : null,
                    'points' => (int)$row['points'],
                    'isBarbarian' => (bool)$row['is_barbarian']
                ];
            }
            
            $stmt->close();
            return $results;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get villages modified since the last timestamp
     */
    private function getModifiedVillages(int $worldId, int $lastTimestamp, array $currentVillages): array
    {
        $query = "SELECT v.id, v.x, v.y, v.name, v.user_id, v.points,
                         CASE WHEN v.user_id IS NULL THEN 1 ELSE 0 END as is_barbarian,
                         u.tribe_id
                  FROM villages v
                  LEFT JOIN users u ON v.user_id = u.id
                  WHERE v.world_id = ? AND v.updated_at > ? AND v.created_at <= ?";
        
        $results = $this->db->fetchAll($query, [$worldId, $lastTimestamp, $lastTimestamp]);
        
        return array_map(function($row) {
            return [
                'id' => (int)$row['id'],
                'coords' => ['x' => (int)$row['x'], 'y' => (int)$row['y']],
                'name' => $row['name'],
                'playerId' => $row['user_id'] ? (int)$row['user_id'] : null,
                'tribeId' => $row['tribe_id'] ? (int)$row['tribe_id'] : null,
                'points' => (int)$row['points'],
                'isBarbarian' => (bool)$row['is_barbarian']
            ];
        }, $results);
    }
    
    /**
     * Get villages removed since the last timestamp
     */
    private function getRemovedVillages(int $worldId, int $lastTimestamp, array $currentVillages): array
    {
        // Villages are rarely deleted, but we check for soft deletes or world resets
        $query = "SELECT id FROM villages 
                  WHERE world_id = ? AND deleted_at > ? AND deleted_at IS NOT NULL";
        
        try {
            $results = $this->db->fetchAll($query, [$worldId, $lastTimestamp]);
            return array_map(fn($row) => ['id' => (int)$row['id']], $results);
        } catch (Exception $e) {
            // If deleted_at column doesn't exist, return empty array
            return [];
        }
    }
    
    /**
     * Get commands added since the last timestamp
     */
    private function getAddedCommands(int $worldId, int $lastTimestamp, array $currentCommands): array
    {
        $query = "SELECT c.id, c.type, c.source_village_id, c.target_village_id,
                         c.arrival_time, c.user_id,
                         sv.x as source_x, sv.y as source_y,
                         tv.x as target_x, tv.y as target_y,
                         u.tribe_id
                  FROM commands c
                  JOIN villages sv ON c.source_village_id = sv.id
                  JOIN villages tv ON c.target_village_id = tv.id
                  LEFT JOIN users u ON c.user_id = u.id
                  WHERE c.world_id = ? AND c.created_at > ? AND c.status = 'active'";
        
        try {
            $results = $this->db->fetchAll($query, [$worldId, $lastTimestamp]);
            
            return array_map(function($row) {
                return [
                    'id' => (int)$row['id'],
                    'type' => $row['type'],
                    'sourceVillageId' => (int)$row['source_village_id'],
                    'targetVillageId' => (int)$row['target_village_id'],
                    'sourceCoords' => ['x' => (int)$row['source_x'], 'y' => (int)$row['source_y']],
                    'targetCoords' => ['x' => (int)$row['target_x'], 'y' => (int)$row['target_y']],
                    'arrivalTime' => (int)$row['arrival_time'],
                    'playerId' => (int)$row['user_id'],
                    'tribeId' => $row['tribe_id'] ? (int)$row['tribe_id'] : null
                ];
            }, $results);
        } catch (Exception $e) {
            // If commands table doesn't exist or has different schema, return empty
            return [];
        }
    }
    
    /**
     * Get commands modified since the last timestamp
     */
    private function getModifiedCommands(int $worldId, int $lastTimestamp, array $currentCommands): array
    {
        // Commands are typically not modified, but we check for status changes
        try {
            $query = "SELECT c.id, c.type, c.source_village_id, c.target_village_id,
                             c.arrival_time, c.user_id,
                             sv.x as source_x, sv.y as source_y,
                             tv.x as target_x, tv.y as target_y,
                             u.tribe_id
                      FROM commands c
                      JOIN villages sv ON c.source_village_id = sv.id
                      JOIN villages tv ON c.target_village_id = tv.id
                      LEFT JOIN users u ON c.user_id = u.id
                      WHERE c.world_id = ? AND c.updated_at > ? AND c.created_at <= ? AND c.status = 'active'";
            
            $results = $this->db->fetchAll($query, [$worldId, $lastTimestamp, $lastTimestamp]);
            
            return array_map(function($row) {
                return [
                    'id' => (int)$row['id'],
                    'type' => $row['type'],
                    'sourceVillageId' => (int)$row['source_village_id'],
                    'targetVillageId' => (int)$row['target_village_id'],
                    'sourceCoords' => ['x' => (int)$row['source_x'], 'y' => (int)$row['source_y']],
                    'targetCoords' => ['x' => (int)$row['target_x'], 'y' => (int)$row['target_y']],
                    'arrivalTime' => (int)$row['arrival_time'],
                    'playerId' => (int)$row['user_id'],
                    'tribeId' => $row['tribe_id'] ? (int)$row['tribe_id'] : null
                ];
            }, $results);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get commands removed since the last timestamp
     */
    private function getRemovedCommands(int $worldId, int $lastTimestamp, array $currentCommands): array
    {
        // Commands that completed, were cancelled, or expired
        try {
            $query = "SELECT id FROM commands 
                      WHERE world_id = ? 
                      AND (
                          (status = 'completed' AND completed_at > ?)
                          OR (status = 'cancelled' AND updated_at > ?)
                          OR (arrival_time < ? AND arrival_time > ?)
                      )";
            
            $currentTime = time();
            $results = $this->db->fetchAll($query, [
                $worldId, 
                $lastTimestamp, 
                $lastTimestamp,
                $currentTime,
                $lastTimestamp
            ]);
            
            return array_map(fn($row) => ['id' => (int)$row['id']], $results);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get markers added since the last timestamp
     */
    private function getAddedMarkers(int $worldId, int $lastTimestamp, array $currentMarkers): array
    {
        // Markers might not exist in all implementations
        try {
            $query = "SELECT id, type, x, y, label, color, user_id, created_at
                      FROM map_markers
                      WHERE world_id = ? AND created_at > ?";
            
            $results = $this->db->fetchAll($query, [$worldId, $lastTimestamp]);
            
            return array_map(function($row) {
                return [
                    'id' => (int)$row['id'],
                    'type' => $row['type'],
                    'coords' => ['x' => (int)$row['x'], 'y' => (int)$row['y']],
                    'label' => $row['label'] ?? null,
                    'color' => $row['color'] ?? null,
                    'playerId' => (int)$row['user_id'],
                    'createdAt' => (int)$row['created_at']
                ];
            }, $results);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get markers modified since the last timestamp
     */
    private function getModifiedMarkers(int $worldId, int $lastTimestamp, array $currentMarkers): array
    {
        try {
            $query = "SELECT id, type, x, y, label, color, user_id, created_at, updated_at
                      FROM map_markers
                      WHERE world_id = ? AND updated_at > ? AND created_at <= ?";
            
            $results = $this->db->fetchAll($query, [$worldId, $lastTimestamp, $lastTimestamp]);
            
            return array_map(function($row) {
                return [
                    'id' => (int)$row['id'],
                    'type' => $row['type'],
                    'coords' => ['x' => (int)$row['x'], 'y' => (int)$row['y']],
                    'label' => $row['label'] ?? null,
                    'color' => $row['color'] ?? null,
                    'playerId' => (int)$row['user_id'],
                    'createdAt' => (int)$row['created_at'],
                    'updatedAt' => (int)$row['updated_at']
                ];
            }, $results);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get markers removed since the last timestamp
     */
    private function getRemovedMarkers(int $worldId, int $lastTimestamp, array $currentMarkers): array
    {
        try {
            $query = "SELECT id FROM map_markers 
                      WHERE world_id = ? AND deleted_at > ? AND deleted_at IS NOT NULL";
            
            $results = $this->db->fetchAll($query, [$worldId, $lastTimestamp]);
            return array_map(fn($row) => ['id' => (int)$row['id']], $results);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get the current data version for a world
     */
    private function getDataVersion(int $worldId): string
    {
        try {
            $result = $this->db->fetchOne(
                "SELECT data_version FROM cache_versions WHERE world_id = ?",
                [$worldId]
            );
            
            return $result ? (string)$result['data_version'] : (string)time();
        } catch (Exception $e) {
            return (string)time();
        }
    }
    
    /**
     * Calculate a checksum of the current state for validation
     */
    private function calculateStateChecksum(int $worldId): string
    {
        // Simple checksum based on counts and latest timestamps
        try {
            $villageCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM villages WHERE world_id = ?",
                [$worldId]
            )['count'] ?? 0;
            
            $commandCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM commands WHERE world_id = ? AND status = 'active'",
                [$worldId]
            )['count'] ?? 0;
            
            $checksumData = [
                'villages' => $villageCount,
                'commands' => $commandCount,
                'timestamp' => time()
            ];
            
            return md5(json_encode($checksumData));
        } catch (Exception $e) {
            return md5((string)time());
        }
    }
}
