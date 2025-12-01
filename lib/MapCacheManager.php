<?php
declare(strict_types=1);

/**
 * MapCacheManager
 * 
 * Handles server-side caching infrastructure for map data including:
 * - Cache key generation for map data
 * - ETag generation based on data version and viewport
 * - Cache invalidation logic for command/village/diplomacy changes
 * 
 * Requirements: 2.1, 2.2
 */
class MapCacheManager
{
    private Database $db;
    private string $cachePrefix = 'map:';
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    /**
     * Generate a cache key for map data based on world, viewport, and user permissions
     * 
     * @param int $worldId World identifier
     * @param array $viewport Viewport bounds [centerX, centerY, zoomLevel, width, height]
     * @param int $userId User identifier for permission-based filtering
     * @return string Cache key
     */
    public function generateCacheKey(int $worldId, array $viewport, int $userId): string
    {
        // Get user's tribe for diplomacy-based filtering
        $tribeId = $this->getUserTribeId($userId);
        
        // Get current diplomacy version for cache invalidation
        $diplomacyVersion = $this->getDiplomacyVersion($worldId);
        
        // Create viewport hash to reduce key length
        $viewportHash = $this->hashViewport($viewport);
        
        // Format: map:{worldId}:{viewportHash}:{diplomacyVersion}:{tribeId}
        return sprintf(
            '%s%d:%s:%s:%d',
            $this->cachePrefix,
            $worldId,
            $viewportHash,
            $diplomacyVersion,
            $tribeId ?? 0
        );
    }
    
    /**
     * Generate an ETag for map data based on data version and viewport
     * 
     * @param int $worldId World identifier
     * @param array $viewport Viewport bounds
     * @param int $userId User identifier
     * @return string ETag value
     */
    public function generateETag(int $worldId, array $viewport, int $userId): string
    {
        $dataVersion = $this->getDataVersion($worldId);
        $diplomacyVersion = $this->getDiplomacyVersion($worldId);
        $tribeId = $this->getUserTribeId($userId);
        $viewportHash = $this->hashViewport($viewport);
        
        $etagData = [
            'data_version' => $dataVersion,
            'diplomacy_version' => $diplomacyVersion,
            'viewport' => $viewportHash,
            'tribe_id' => $tribeId ?? 0
        ];
        
        return md5(json_encode($etagData));
    }
    
    /**
     * Invalidate cache for a specific world when commands change
     * 
     * @param int $worldId World identifier
     * @return void
     */
    public function invalidateCommandCache(int $worldId): void
    {
        $this->incrementDataVersion($worldId);
    }
    
    /**
     * Invalidate cache when village ownership changes
     * 
     * @param int $worldId World identifier
     * @param int $villageId Village that changed ownership
     * @return void
     */
    public function invalidateVillageCache(int $worldId, int $villageId): void
    {
        $this->incrementDataVersion($worldId);
    }
    
    /**
     * Invalidate cache when diplomacy state changes
     * 
     * @param int $worldId World identifier
     * @return void
     */
    public function invalidateDiplomacyCache(int $worldId): void
    {
        $this->incrementDiplomacyVersion($worldId);
        $this->incrementDataVersion($worldId);
    }
    
    /**
     * Get the current data version for a world
     * 
     * @param int $worldId World identifier
     * @return string Data version timestamp
     */
    private function getDataVersion(int $worldId): string
    {
        $result = $this->db->fetchOne(
            "SELECT data_version FROM cache_versions WHERE world_id = ?",
            [$worldId]
        );
        
        if (!$result) {
            // Initialize if not exists
            $this->initializeCacheVersions($worldId);
            return (string)time();
        }
        
        return (string)$result['data_version'];
    }
    
    /**
     * Get the current diplomacy version for a world
     * 
     * @param int $worldId World identifier
     * @return string Diplomacy version timestamp
     */
    private function getDiplomacyVersion(int $worldId): string
    {
        $result = $this->db->fetchOne(
            "SELECT diplomacy_version FROM cache_versions WHERE world_id = ?",
            [$worldId]
        );
        
        if (!$result) {
            $this->initializeCacheVersions($worldId);
            return (string)time();
        }
        
        return (string)$result['diplomacy_version'];
    }
    
    /**
     * Increment the data version for cache invalidation
     * 
     * @param int $worldId World identifier
     * @return void
     */
    private function incrementDataVersion(int $worldId): void
    {
        $newVersion = time();
        
        // Check if record exists
        $existing = $this->db->fetchOne(
            "SELECT world_id FROM cache_versions WHERE world_id = ?",
            [$worldId]
        );
        
        if ($existing) {
            // Update existing record
            $this->db->execute(
                "UPDATE cache_versions SET data_version = ?, updated_at = ? WHERE world_id = ?",
                [$newVersion, $newVersion, $worldId]
            );
        } else {
            // Insert new record
            $this->db->execute(
                "INSERT INTO cache_versions (world_id, data_version, diplomacy_version, updated_at) 
                 VALUES (?, ?, ?, ?)",
                [$worldId, $newVersion, $newVersion, $newVersion]
            );
        }
    }
    
    /**
     * Increment the diplomacy version for cache invalidation
     * 
     * @param int $worldId World identifier
     * @return void
     */
    private function incrementDiplomacyVersion(int $worldId): void
    {
        $newVersion = time();
        
        // Check if record exists
        $existing = $this->db->fetchOne(
            "SELECT world_id FROM cache_versions WHERE world_id = ?",
            [$worldId]
        );
        
        if ($existing) {
            // Update existing record
            $this->db->execute(
                "UPDATE cache_versions SET diplomacy_version = ?, updated_at = ? WHERE world_id = ?",
                [$newVersion, $newVersion, $worldId]
            );
        } else {
            // Insert new record
            $this->db->execute(
                "INSERT INTO cache_versions (world_id, data_version, diplomacy_version, updated_at) 
                 VALUES (?, ?, ?, ?)",
                [$worldId, $newVersion, $newVersion, $newVersion]
            );
        }
    }
    
    /**
     * Initialize cache versions for a world
     * 
     * @param int $worldId World identifier
     * @return void
     */
    private function initializeCacheVersions(int $worldId): void
    {
        $timestamp = time();
        
        $this->db->execute(
            "INSERT OR IGNORE INTO cache_versions (world_id, data_version, diplomacy_version, updated_at) 
             VALUES (?, ?, ?, ?)",
            [$worldId, $timestamp, $timestamp, $timestamp]
        );
    }
    
    /**
     * Get user's tribe ID for permission-based filtering
     * 
     * @param int $userId User identifier
     * @return int|null Tribe ID or null if not in a tribe
     */
    private function getUserTribeId(int $userId): ?int
    {
        $result = $this->db->fetchOne(
            "SELECT tribe_id FROM tribe_members WHERE user_id = ?",
            [$userId]
        );
        
        return $result ? (int)$result['tribe_id'] : null;
    }
    
    /**
     * Create a hash of viewport parameters to reduce key length
     * 
     * @param array $viewport Viewport bounds [centerX, centerY, zoomLevel, width, height]
     * @return string Viewport hash
     */
    private function hashViewport(array $viewport): string
    {
        // Round coordinates to reduce cache fragmentation
        $centerX = isset($viewport['centerX']) ? round($viewport['centerX'] / 10) * 10 : 0;
        $centerY = isset($viewport['centerY']) ? round($viewport['centerY'] / 10) * 10 : 0;
        $zoomLevel = $viewport['zoomLevel'] ?? 1;
        $width = isset($viewport['width']) ? round($viewport['width'] / 100) * 100 : 0;
        $height = isset($viewport['height']) ? round($viewport['height'] / 100) * 100 : 0;
        
        $viewportString = sprintf('%d:%d:%d:%d:%d', $centerX, $centerY, $zoomLevel, $width, $height);
        
        return substr(md5($viewportString), 0, 12);
    }
}
