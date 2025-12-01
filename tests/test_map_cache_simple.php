<?php
declare(strict_types=1);

/**
 * Simple test for MapCacheManager basic functionality
 */

echo "Testing MapCacheManager...\n\n";

// Test 1: Check if files exist
echo "Test 1: Check if MapCacheManager file exists...\n";
if (file_exists(__DIR__ . '/../lib/MapCacheManager.php')) {
    echo "  ✓ MapCacheManager.php exists\n";
} else {
    echo "  ✗ MapCacheManager.php not found\n";
    exit(1);
}

// Test 2: Check if migration file exists
echo "\nTest 2: Check if migration file exists...\n";
if (file_exists(__DIR__ . '/../migrations/add_cache_versions_table.php')) {
    echo "  ✓ Migration file exists\n";
} else {
    echo "  ✗ Migration file not found\n";
    exit(1);
}

// Test 3: Check if cache_versions table exists in database
echo "\nTest 3: Check if cache_versions table exists...\n";
require_once __DIR__ . '/../Database.php';

try {
    $db = Database::getInstance();
    $result = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='cache_versions'");
    
    if ($result) {
        echo "  ✓ cache_versions table exists\n";
    } else {
        echo "  ✗ cache_versions table not found\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  ✗ Error checking table: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Test MapCacheManager instantiation
echo "\nTest 4: Test MapCacheManager instantiation...\n";
require_once __DIR__ . '/../lib/MapCacheManager.php';

try {
    $cacheManager = new MapCacheManager($db);
    echo "  ✓ MapCacheManager instantiated successfully\n";
} catch (Exception $e) {
    echo "  ✗ Error instantiating MapCacheManager: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Test cache key generation
echo "\nTest 5: Test cache key generation...\n";
try {
    $viewport = [
        'centerX' => 250,
        'centerY' => 250,
        'zoomLevel' => 1,
        'width' => 800,
        'height' => 600
    ];
    
    $cacheKey = $cacheManager->generateCacheKey(1, $viewport, 1);
    
    if (is_string($cacheKey) && strlen($cacheKey) > 0) {
        echo "  ✓ Cache key generated: {$cacheKey}\n";
    } else {
        echo "  ✗ Invalid cache key generated\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  ✗ Error generating cache key: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: Test ETag generation
echo "\nTest 6: Test ETag generation...\n";
try {
    $etag = $cacheManager->generateETag(1, $viewport, 1);
    
    if (is_string($etag) && strlen($etag) === 32 && ctype_xdigit($etag)) {
        echo "  ✓ ETag generated: {$etag}\n";
    } else {
        echo "  ✗ Invalid ETag generated\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  ✗ Error generating ETag: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 7: Test cache invalidation
echo "\nTest 7: Test cache invalidation...\n";
try {
    $etagBefore = $cacheManager->generateETag(1, $viewport, 1);
    
    // Wait a moment to ensure timestamp changes
    sleep(1);
    
    // Invalidate cache
    $cacheManager->invalidateCommandCache(1);
    
    // Wait a moment
    sleep(1);
    
    $etagAfter = $cacheManager->generateETag(1, $viewport, 1);
    
    if ($etagBefore !== $etagAfter) {
        echo "  ✓ Cache invalidation works (ETag changed)\n";
        echo "    Before: {$etagBefore}\n";
        echo "    After:  {$etagAfter}\n";
    } else {
        echo "  ✗ Cache invalidation failed (ETag unchanged)\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  ✗ Error testing cache invalidation: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ All tests passed!\n";
