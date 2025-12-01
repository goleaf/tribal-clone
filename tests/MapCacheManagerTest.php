<?php
declare(strict_types=1);

/**
 * Unit tests for MapCacheManager
 * 
 * Tests cache key generation, ETag generation, and cache invalidation logic
 */

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/MapCacheManager.php';

class MapCacheManagerTest
{
    private Database $db;
    private MapCacheManager $cacheManager;
    private int $testWorldId = 1;
    private int $testUserId = 1;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cacheManager = new MapCacheManager($this->db);
    }
    
    public function run(): void
    {
        echo "Running MapCacheManager tests...\n\n";
        
        $this->testCacheKeyGeneration();
        $this->testETagGeneration();
        $this->testCommandCacheInvalidation();
        $this->testVillageCacheInvalidation();
        $this->testDiplomacyCacheInvalidation();
        $this->testETagChangesAfterInvalidation();
        
        echo "\nAll tests passed!\n";
    }
    
    private function testCacheKeyGeneration(): void
    {
        echo "Test: Cache key generation...\n";
        
        $viewport = [
            'centerX' => 250,
            'centerY' => 250,
            'zoomLevel' => 1,
            'width' => 800,
            'height' => 600
        ];
        
        $cacheKey1 = $this->cacheManager->generateCacheKey($this->testWorldId, $viewport, $this->testUserId);
        $cacheKey2 = $this->cacheManager->generateCacheKey($this->testWorldId, $viewport, $this->testUserId);
        
        // Same viewport should generate same cache key
        $this->assert($cacheKey1 === $cacheKey2, "Same viewport should generate same cache key");
        
        // Different viewport should generate different cache key
        $viewport2 = $viewport;
        $viewport2['centerX'] = 300;
        $cacheKey3 = $this->cacheManager->generateCacheKey($this->testWorldId, $viewport2, $this->testUserId);
        $this->assert($cacheKey1 !== $cacheKey3, "Different viewport should generate different cache key");
        
        // Cache key should contain world ID
        $this->assert(strpos($cacheKey1, 'map:' . $this->testWorldId) === 0, "Cache key should start with map:{worldId}");
        
        echo "  ✓ Cache key generation works correctly\n";
    }
    
    private function testETagGeneration(): void
    {
        echo "Test: ETag generation...\n";
        
        $viewport = [
            'centerX' => 250,
            'centerY' => 250,
            'zoomLevel' => 1,
            'width' => 800,
            'height' => 600
        ];
        
        $etag1 = $this->cacheManager->generateETag($this->testWorldId, $viewport, $this->testUserId);
        $etag2 = $this->cacheManager->generateETag($this->testWorldId, $viewport, $this->testUserId);
        
        // Same viewport should generate same ETag
        $this->assert($etag1 === $etag2, "Same viewport should generate same ETag");
        
        // ETag should be a valid MD5 hash (32 characters)
        $this->assert(strlen($etag1) === 32, "ETag should be 32 characters (MD5 hash)");
        $this->assert(ctype_xdigit($etag1), "ETag should be hexadecimal");
        
        echo "  ✓ ETag generation works correctly\n";
    }
    
    private function testCommandCacheInvalidation(): void
    {
        echo "Test: Command cache invalidation...\n";
        
        $viewport = [
            'centerX' => 250,
            'centerY' => 250,
            'zoomLevel' => 1,
            'width' => 800,
            'height' => 600
        ];
        
        $etagBefore = $this->cacheManager->generateETag($this->testWorldId, $viewport, $this->testUserId);
        
        // Invalidate command cache
        $this->cacheManager->invalidateCommandCache($this->testWorldId);
        
        // Small delay to ensure timestamp changes
        usleep(1000);
        
        $etagAfter = $this->cacheManager->generateETag($this->testWorldId, $viewport, $this->testUserId);
        
        // ETag should change after invalidation
        $this->assert($etagBefore !== $etagAfter, "ETag should change after command cache invalidation");
        
        echo "  ✓ Command cache invalidation works correctly\n";
    }
    
    private function testVillageCacheInvalidation(): void
    {
        echo "Test: Village cache invalidation...\n";
        
        $viewport = [
            'centerX' => 250,
            'centerY' => 250,
            'zoomLevel' => 1,
            'width' => 800,
            'height' => 600
        ];
        
        $etagBefore = $this->cacheManager->generateETag($this->testWorldId, $viewport, $this->testUserId);
        
        // Invalidate village cache
        $this->cacheManager->invalidateVillageCache($this->testWorldId, 1);
        
        // Small delay to ensure timestamp changes
        usleep(1000);
        
        $etagAfter = $this->cacheManager->generateETag($this->testWorldId, $viewport, $this->testUserId);
        
        // ETag should change after invalidation
        $this->assert($etagBefore !== $etagAfter, "ETag should change after village cache invalidation");
        
        echo "  ✓ Village cache invalidation works correctly\n";
    }
    
    private function testDiplomacyCacheInvalidation(): void
    {
        echo "Test: Diplomacy cache invalidation...\n";
        
        $viewport = [
            'centerX' => 250,
            'centerY' => 250,
            'zoomLevel' => 1,
            'width' => 800,
            'height' => 600
        ];
        
        $etagBefore = $this->cacheManager->generateETag($this->testWorldId, $viewport, $this->testUserId);
        
        // Invalidate diplomacy cache
        $this->cacheManager->invalidateDiplomacyCache($this->testWorldId);
        
        // Small delay to ensure timestamp changes
        usleep(1000);
        
        $etagAfter = $this->cacheManager->generateETag($this->testWorldId, $viewport, $this->testUserId);
        
        // ETag should change after invalidation
        $this->assert($etagBefore !== $etagAfter, "ETag should change after diplomacy cache invalidation");
        
        echo "  ✓ Diplomacy cache invalidation works correctly\n";
    }
    
    private function testETagChangesAfterInvalidation(): void
    {
        echo "Test: ETag changes after multiple invalidations...\n";
        
        $viewport = [
            'centerX' => 250,
            'centerY' => 250,
            'zoomLevel' => 1,
            'width' => 800,
            'height' => 600
        ];
        
        $etags = [];
        
        // Generate initial ETag
        $etags[] = $this->cacheManager->generateETag($this->testWorldId, $viewport, $this->testUserId);
        
        // Invalidate and generate new ETags
        for ($i = 0; $i < 3; $i++) {
            usleep(1000);
            $this->cacheManager->invalidateCommandCache($this->testWorldId);
            usleep(1000);
            $etags[] = $this->cacheManager->generateETag($this->testWorldId, $viewport, $this->testUserId);
        }
        
        // All ETags should be different
        $uniqueEtags = array_unique($etags);
        $this->assert(count($uniqueEtags) === count($etags), "Each invalidation should produce a unique ETag");
        
        echo "  ✓ ETags change correctly after multiple invalidations\n";
    }
    
    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new Exception("Assertion failed: {$message}");
        }
    }
}

// Run tests
try {
    $test = new MapCacheManagerTest();
    $test->run();
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
