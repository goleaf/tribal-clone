# Map Cache Quick Reference

## Overview
Server-side caching infrastructure for map data with ETag support and cache invalidation.

## Quick Start

```php
require_once 'Database.php';
require_once 'lib/MapCacheManager.php';

$db = Database::getInstance();
$cacheManager = new MapCacheManager($db);
```

## Common Operations

### Generate Cache Key
```php
$viewport = [
    'centerX' => 250,
    'centerY' => 250,
    'zoomLevel' => 1,
    'width' => 800,
    'height' => 600
];

$cacheKey = $cacheManager->generateCacheKey($worldId, $viewport, $userId);
// Returns: "map:1:9a222104462f:1000:0"
```

### Generate ETag
```php
$etag = $cacheManager->generateETag($worldId, $viewport, $userId);
// Returns: "6a211a88bf3cdba01eae34dee3d8d07f"
```

### Check Client Cache
```php
$clientETag = isset($_SERVER['HTTP_IF_NONE_MATCH']) 
    ? trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') 
    : null;

$serverETag = $cacheManager->generateETag($worldId, $viewport, $userId);

header('ETag: "' . $serverETag . '"');
header('Cache-Control: private, must-revalidate');

if ($clientETag === $serverETag) {
    http_response_code(304);
    exit;
}

// Return fresh data
echo json_encode($mapData);
```

### Invalidate Cache

**When commands change:**
```php
// After creating a command
$cacheManager->invalidateCommandCache($worldId);

// After completing a command
$cacheManager->invalidateCommandCache($worldId);

// After cancelling a command
$cacheManager->invalidateCommandCache($worldId);
```

**When villages change:**
```php
// After village conquest
$cacheManager->invalidateVillageCache($worldId, $villageId);

// After ownership transfer
$cacheManager->invalidateVillageCache($worldId, $villageId);
```

**When diplomacy changes:**
```php
// After alliance creation/dissolution
$cacheManager->invalidateDiplomacyCache($worldId);

// After war declaration/peace treaty
$cacheManager->invalidateDiplomacyCache($worldId);
```

## Cache Key Format

```
map:{worldId}:{viewportHash}:{diplomacyVersion}:{tribeId}
```

Example: `map:1:9a222104462f:1764623830:0`

- `worldId`: World identifier (1)
- `viewportHash`: 12-character MD5 hash of viewport (9a222104462f)
- `diplomacyVersion`: Unix timestamp of last diplomacy change (1764623830)
- `tribeId`: User's tribe ID or 0 if not in tribe (0)

## ETag Format

32-character MD5 hash of:
- Data version (Unix timestamp)
- Diplomacy version (Unix timestamp)
- Viewport hash
- Tribe ID

Example: `6a211a88bf3cdba01eae34dee3d8d07f`

## Viewport Rounding

To reduce cache fragmentation:
- Coordinates rounded to nearest 10 units
- Dimensions rounded to nearest 100 pixels

```php
// These viewports produce the same cache key:
$viewport1 = ['centerX' => 250, 'centerY' => 250, 'width' => 800, 'height' => 600];
$viewport2 = ['centerX' => 251, 'centerY' => 249, 'width' => 805, 'height' => 595];
```

## HTTP Headers

### Request Headers
```
If-None-Match: "6a211a88bf3cdba01eae34dee3d8d07f"
```

### Response Headers (Fresh Data)
```
HTTP/1.1 200 OK
ETag: "6a211a88bf3cdba01eae34dee3d8d07f"
Cache-Control: private, must-revalidate
Content-Type: application/json
```

### Response Headers (Cached Data)
```
HTTP/1.1 304 Not Modified
ETag: "6a211a88bf3cdba01eae34dee3d8d07f"
Cache-Control: private, must-revalidate
```

## Database Schema

```sql
CREATE TABLE cache_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    world_id INTEGER NOT NULL UNIQUE,
    data_version INTEGER NOT NULL DEFAULT 0,
    diplomacy_version INTEGER NOT NULL DEFAULT 0,
    updated_at INTEGER NOT NULL
);
```

## Testing

```bash
# Run simple tests
php tests/test_map_cache_simple.php

# Run integration tests
php tests/test_map_cache_integration.php
```

## Troubleshooting

### ETag not changing after invalidation
```bash
# Check cache versions
php -r "require_once 'Database.php'; \$db = Database::getInstance(); \$result = \$db->fetchAll('SELECT * FROM cache_versions'); var_dump(\$result);"
```

### Cache key generation fails
```bash
# Check if MapCacheManager loads
php -r "require_once 'Database.php'; require_once 'lib/MapCacheManager.php'; echo 'OK';"
```

### HTTP 304 not working
```bash
# Test ETag generation
php -r "require_once 'Database.php'; require_once 'lib/MapCacheManager.php'; \$db = Database::getInstance(); \$cm = new MapCacheManager(\$db); \$viewport = ['centerX' => 250, 'centerY' => 250, 'zoomLevel' => 1, 'width' => 800, 'height' => 600]; echo \$cm->generateETag(1, \$viewport, 1);"
```

## Performance

- Cache key generation: ~1ms
- ETag generation: ~1ms
- Cache invalidation: ~1ms
- Viewport rounding reduces cache entries by ~90%

## Best Practices

1. **Always invalidate cache** when data changes
2. **Use viewport rounding** to reduce cache fragmentation
3. **Check If-None-Match** header before generating response
4. **Set Cache-Control** headers appropriately
5. **Log cache hit rates** for monitoring

## Integration Example

```php
// ajax/map/fetch.php
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/MapCacheManager.php';

$userId = (int)$_SESSION['user_id'];
$worldId = isset($_GET['worldId']) ? (int)$_GET['worldId'] : 1;

$viewport = [
    'centerX' => isset($_GET['centerX']) ? (int)$_GET['centerX'] : 250,
    'centerY' => isset($_GET['centerY']) ? (int)$_GET['centerY'] : 250,
    'zoomLevel' => isset($_GET['zoomLevel']) ? (int)$_GET['zoomLevel'] : 1,
    'width' => isset($_GET['width']) ? (int)$_GET['width'] : 800,
    'height' => isset($_GET['height']) ? (int)$_GET['height'] : 600
];

$db = Database::getInstance();
$cacheManager = new MapCacheManager($db);

$etag = $cacheManager->generateETag($worldId, $viewport, $userId);
$clientETag = isset($_SERVER['HTTP_IF_NONE_MATCH']) 
    ? trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') 
    : null;

header('ETag: "' . $etag . '"');
header('Cache-Control: private, must-revalidate');

if ($clientETag === $etag) {
    http_response_code(304);
    exit;
}

// Generate map data...
$mapData = generateMapData($worldId, $viewport, $userId);

header('Content-Type: application/json');
echo json_encode($mapData);
```

## See Also

- Design Document: `.kiro/specs/map-performance-ux/design.md`
- Requirements: `.kiro/specs/map-performance-ux/requirements.md`
- Implementation Notes: `.kiro/specs/map-performance-ux/IMPLEMENTATION_NOTES.md`
