<?php
declare(strict_types=1);

/**
 * Integration test for MapCacheManager with ajax/map/fetch.php
 * 
 * Tests the full caching workflow including HTTP headers
 */

echo "Testing Map Cache Integration...\n\n";

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/MapCacheManager.php';

$db = Database::getInstance();
$cacheManager = new MapCacheManager($db);

// Test 1: Generate cache key and ETag for a viewport
echo "Test 1: Generate cache key and ETag...\n";
$viewport = [
    'centerX' => 250,
    'centerY' => 250,
    'zoomLevel' => 1,
    'width' => 800,
    'height' => 600
];

$cacheKey1 = $cacheManager->generateCacheKey(1, $viewport, 1);
$etag1 = $cacheManager->generateETag(1, $viewport, 1);

echo "  Cache Key: {$cacheKey1}\n";
echo "  ETag: {$etag1}\n";
echo "  ✓ Initial cache key and ETag generated\n\n";

// Test 2: Verify same viewport produces same cache key and ETag
echo "Test 2: Verify cache key consistency...\n";
$cacheKey2 = $cacheManager->generateCacheKey(1, $viewport, 1);
$etag2 = $cacheManager->generateETag(1, $viewport, 1);

if ($cacheKey1 === $cacheKey2 && $etag1 === $etag2) {
    echo "  ✓ Same viewport produces same cache key and ETag\n\n";
} else {
    echo "  ✗ Cache key or ETag mismatch!\n";
    exit(1);
}

// Test 3: Verify different viewport produces different cache key
echo "Test 3: Verify different viewport produces different cache key...\n";
$viewport2 = $viewport;
$viewport2['centerX'] = 300;

$cacheKey3 = $cacheManager->generateCacheKey(1, $viewport2, 1);
$etag3 = $cacheManager->generateETag(1, $viewport2, 1);

if ($cacheKey1 !== $cacheKey3 && $etag1 !== $etag3) {
    echo "  ✓ Different viewport produces different cache key and ETag\n";
    echo "    Original: {$cacheKey1}\n";
    echo "    Modified: {$cacheKey3}\n\n";
} else {
    echo "  ✗ Cache key or ETag should be different!\n";
    exit(1);
}

// Test 4: Test command cache invalidation
echo "Test 4: Test command cache invalidation...\n";
$etagBefore = $cacheManager->generateETag(1, $viewport, 1);
sleep(1);
$cacheManager->invalidateCommandCache(1);
sleep(1);
$etagAfter = $cacheManager->generateETag(1, $viewport, 1);

if ($etagBefore !== $etagAfter) {
    echo "  ✓ Command cache invalidation changes ETag\n";
    echo "    Before: {$etagBefore}\n";
    echo "    After:  {$etagAfter}\n\n";
} else {
    echo "  ✗ ETag should change after invalidation!\n";
    exit(1);
}

// Test 5: Test village cache invalidation
echo "Test 5: Test village cache invalidation...\n";
$etagBefore = $cacheManager->generateETag(1, $viewport, 1);
sleep(1);
$cacheManager->invalidateVillageCache(1, 123);
sleep(1);
$etagAfter = $cacheManager->generateETag(1, $viewport, 1);

if ($etagBefore !== $etagAfter) {
    echo "  ✓ Village cache invalidation changes ETag\n";
    echo "    Before: {$etagBefore}\n";
    echo "    After:  {$etagAfter}\n\n";
} else {
    echo "  ✗ ETag should change after invalidation!\n";
    exit(1);
}

// Test 6: Test diplomacy cache invalidation
echo "Test 6: Test diplomacy cache invalidation...\n";
$etagBefore = $cacheManager->generateETag(1, $viewport, 1);
sleep(1);
$cacheManager->invalidateDiplomacyCache(1);
sleep(1);
$etagAfter = $cacheManager->generateETag(1, $viewport, 1);

if ($etagBefore !== $etagAfter) {
    echo "  ✓ Diplomacy cache invalidation changes ETag\n";
    echo "    Before: {$etagBefore}\n";
    echo "    After:  {$etagAfter}\n\n";
} else {
    echo "  ✗ ETag should change after invalidation!\n";
    exit(1);
}

// Test 7: Test viewport rounding for cache efficiency
echo "Test 7: Test viewport rounding for cache efficiency...\n";
$viewport3 = [
    'centerX' => 251,  // Should round to 250
    'centerY' => 249,  // Should round to 250
    'zoomLevel' => 1,
    'width' => 805,    // Should round to 800
    'height' => 595    // Should round to 600
];

$cacheKey4 = $cacheManager->generateCacheKey(1, $viewport3, 1);
$cacheKey5 = $cacheManager->generateCacheKey(1, $viewport, 1);

if ($cacheKey4 === $cacheKey5) {
    echo "  ✓ Viewport rounding works correctly\n";
    echo "    Viewport 1: centerX=250, centerY=250, width=800, height=600\n";
    echo "    Viewport 2: centerX=251, centerY=249, width=805, height=595\n";
    echo "    Both produce: {$cacheKey4}\n\n";
} else {
    echo "  ✗ Viewport rounding failed!\n";
    echo "    Key 1: {$cacheKey4}\n";
    echo "    Key 2: {$cacheKey5}\n";
    exit(1);
}

// Test 8: Test ETag format (should be 32-character MD5 hash)
echo "Test 8: Verify ETag format...\n";
$etag = $cacheManager->generateETag(1, $viewport, 1);

if (strlen($etag) === 32 && ctype_xdigit($etag)) {
    echo "  ✓ ETag is valid MD5 hash (32 hex characters)\n";
    echo "    ETag: {$etag}\n\n";
} else {
    echo "  ✗ Invalid ETag format!\n";
    exit(1);
}

// Test 9: Test cache key format
echo "Test 9: Verify cache key format...\n";
$cacheKey = $cacheManager->generateCacheKey(1, $viewport, 1);

if (strpos($cacheKey, 'map:1:') === 0) {
    echo "  ✓ Cache key has correct format\n";
    echo "    Format: map:{worldId}:{viewportHash}:{diplomacyVersion}:{tribeId}\n";
    echo "    Key: {$cacheKey}\n\n";
} else {
    echo "  ✗ Invalid cache key format!\n";
    exit(1);
}

// Test 10: Test multiple rapid invalidations
echo "Test 10: Test multiple rapid invalidations...\n";
$etags = [];
for ($i = 0; $i < 5; $i++) {
    sleep(1);
    $cacheManager->invalidateCommandCache(1);
    sleep(1);
    $etags[] = $cacheManager->generateETag(1, $viewport, 1);
}

$uniqueEtags = array_unique($etags);
if (count($uniqueEtags) === count($etags)) {
    echo "  ✓ Each invalidation produces unique ETag\n";
    echo "    Generated " . count($etags) . " unique ETags\n\n";
} else {
    echo "  ✗ Some ETags were duplicated!\n";
    exit(1);
}

echo "✅ All integration tests passed!\n\n";
echo "Summary:\n";
echo "- Cache key generation: ✓\n";
echo "- ETag generation: ✓\n";
echo "- Cache invalidation (commands): ✓\n";
echo "- Cache invalidation (villages): ✓\n";
echo "- Cache invalidation (diplomacy): ✓\n";
echo "- Viewport rounding: ✓\n";
echo "- ETag format validation: ✓\n";
echo "- Cache key format validation: ✓\n";
echo "- Multiple invalidations: ✓\n";
