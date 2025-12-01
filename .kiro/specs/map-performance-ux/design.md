# Map Performance & UX Design Document

## Overview

This design addresses performance and user experience improvements for the world map system in a browser-based strategy game. The system must handle high-density scenarios (500+ concurrent commands), reduce server load through intelligent caching, maintain smooth interactions across devices, and provide comprehensive accessibility features.

The design focuses on three core pillars:
1. **Client-side performance optimization** through progressive rendering, clustering, and fallback modes
2. **Server-side efficiency** via HTTP caching, delta updates, and rate limiting
3. **Accessibility and offline support** for diverse user needs and network conditions

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Client Layer                          │
├─────────────────────────────────────────────────────────────┤
│  Map Renderer  │  Cache Manager  │  Offline Queue  │  A11y  │
│  - Clustering  │  - LRU Eviction │  - Sync Engine  │  Mode  │
│  - Fallback    │  - Pre-warming  │  - Conflict Res │        │
│  - Skeleton UI │  - Delta Merge  │                 │        │
└─────────────────────────────────────────────────────────────┘
                            ▲ │
                    HTTP    │ │ WebSocket (future)
                    Caching │ │ Delta Updates
                            │ ▼
┌─────────────────────────────────────────────────────────────┐
│                        Server Layer                          │
├─────────────────────────────────────────────────────────────┤
│  Map API       │  Cache Layer    │  Rate Limiter  │  Metrics│
│  - ETag/304    │  - Redis/Memcache│ - Token Bucket│  Logger │
│  - Delta Calc  │  - Invalidation │  - Per-user    │         │
│  - Pagination  │                 │                │         │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                      Data Layer                              │
│  Villages │ Commands │ Markers │ Diplomacy │ Telemetry      │
└─────────────────────────────────────────────────────────────┘
```

### Key Design Decisions

**Decision 1: Client-side clustering over server-side**
- Rationale: Reduces server CPU load and allows dynamic cluster recalculation during zoom without round-trips
- Trade-off: Increases initial payload size but improves interactivity

**Decision 2: HTTP caching with ETags over custom versioning**
- Rationale: Leverages browser cache infrastructure and CDN compatibility
- Trade-off: Requires careful cache key design for multi-tenant scenarios

**Decision 3: Cursor-based pagination over offset-based**
- Rationale: Handles real-time insertions/deletions without page drift
- Trade-off: Cannot jump to arbitrary pages (acceptable for command lists)

**Decision 4: Server-wins conflict resolution for offline sync**
- Rationale: Simplifies implementation and prevents data corruption in critical game state
- Trade-off: User edits may be overwritten (mitigated by conflict surfacing)

## Components and Interfaces

### Client Components

#### MapRenderer
Responsible for rendering the visible map viewport with commands, markers, and overlays.

```typescript
interface MapRenderer {
  render(viewport: Viewport, data: MapData): void;
  enableFallbackMode(): void;
  disableFallbackMode(): void;
  setAccessibilityMode(mode: AccessibilityMode): void;
  measurePerformance(): RenderMetrics;
}

interface Viewport {
  centerX: number;
  centerY: number;
  zoomLevel: number;
  width: number;
  height: number;
}

interface MapData {
  villages: Village[];
  commands: Command[];
  markers: Marker[];
  clusters?: Cluster[];
}

interface RenderMetrics {
  renderDuration: number;
  droppedFrames: number;
  elementCount: number;
  timestamp: number;
}
```

#### ClusterManager
Handles grouping of nearby markers and commands at far zoom levels.

```typescript
interface ClusterManager {
  cluster(items: Marker[], zoomLevel: number): Cluster[];
  shouldCluster(zoomLevel: number): boolean;
  expandCluster(cluster: Cluster): Marker[];
}

interface Cluster {
  centroid: { x: number; y: number };
  count: number;
  bounds: BoundingBox;
  items?: Marker[]; // Only populated when expanded
}
```

#### CacheManager
Manages client-side caching with LRU eviction and pre-warming.

```typescript
interface CacheManager {
  get(key: string): MapData | null;
  set(key: string, data: MapData, etag: string): void;
  prewarm(continent: number, budget: number): Promise<void>;
  evict(): void;
  getStats(): CacheStats;
}

interface CacheStats {
  hitRate: number;
  size: number;
  budget: number;
}
```

#### OfflineQueue
Queues user actions when offline and syncs when reconnected.

```typescript
interface OfflineQueue {
  enqueue(action: UserAction): void;
  sync(): Promise<SyncResult>;
  getConflicts(): Conflict[];
  resolveConflict(conflict: Conflict, resolution: Resolution): void;
}

interface UserAction {
  type: 'marker_add' | 'marker_edit' | 'marker_delete' | 'bookmark_add';
  payload: any;
  timestamp: number;
  clientId: string;
}

interface SyncResult {
  synced: number;
  conflicts: Conflict[];
  errors: Error[];
}

interface Conflict {
  action: UserAction;
  serverState: any;
  reason: string;
}
```

### Server Components

#### MapAPI
REST endpoints for map data with caching and delta support.

```php
interface MapAPI {
  public function fetchMapData(
    int $worldId,
    Viewport $viewport,
    ?string $cursor = null,
    ?string $ifNoneMatch = null
  ): Response;
  
  public function fetchCommandList(
    int $villageId,
    ?string $cursor = null
  ): Response;
  
  public function syncOfflineActions(
    int $userId,
    array $actions
  ): Response;
}

class Response {
  public int $statusCode;
  public array $headers;
  public mixed $body;
}
```

#### DeltaCalculator
Computes incremental updates between map states.

```php
interface DeltaCalculator {
  public function calculateDelta(
    string $cursor,
    MapData $currentState
  ): Delta;
  
  public function applyDelta(
    MapData $baseState,
    Delta $delta
  ): MapData;
}

class Delta {
  public array $added;
  public array $modified;
  public array $removed;
  public string $nextCursor;
}
```

#### RateLimiter
Token bucket rate limiter per user.

```php
interface RateLimiter {
  public function checkLimit(int $userId, string $endpoint): bool;
  public function getRetryAfter(int $userId, string $endpoint): int;
}
```

#### MetricsLogger
Collects performance and usage metrics.

```php
interface MetricsLogger {
  public function logRequest(
    string $endpoint,
    float $duration,
    int $payloadSize,
    bool $cacheHit
  ): void;
  
  public function logClientMetrics(
    int $userId,
    RenderMetrics $metrics
  ): void;
  
  public function logAlert(
    string $type,
    string $message,
    array $context
  ): void;
}
```

## Data Models

### Command
Represents a military action in transit.

```typescript
interface Command {
  id: string;
  type: 'attack' | 'support' | 'trade' | 'scout';
  sourceVillageId: number;
  targetVillageId: number;
  sourceCoords: { x: number; y: number };
  targetCoords: { x: number; y: number };
  arrivalTime: number;
  units: Record<string, number>;
  playerId: number;
  tribeId?: number;
}
```

### Marker
User-placed visual indicator on the map.

```typescript
interface Marker {
  id: string;
  type: 'bookmark' | 'note' | 'flag';
  coords: { x: number; y: number };
  label?: string;
  color?: string;
  playerId: number;
  createdAt: number;
  updatedAt: number;
}
```

### Village
Map tile with village data.

```typescript
interface Village {
  id: number;
  coords: { x: number; y: number };
  name: string;
  playerId?: number;
  tribeId?: number;
  points: number;
  isBarbarian: boolean;
}
```

### AccessibilityMode
User accessibility preferences.

```typescript
interface AccessibilityMode {
  highContrast: boolean;
  reducedMotion: boolean;
  keyboardNav: boolean;
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Render performance under load
*For any* viewport containing 500 or more commands on a mid-tier mobile device, the p95 render latency should be under 200ms
**Validates: Requirements 1.1**

### Property 2: Cache validation correctness
*For any* map data request with a matching ETag, the server should return HTTP 304 without a payload body
**Validates: Requirements 2.2**

### Property 3: Delta idempotence
*For any* delta update applied multiple times to the same base state, the result should be identical to applying it once
**Validates: Requirements 7.4**

### Property 4: Cluster count accuracy
*For any* set of markers and zoom level, the sum of cluster counts should equal the total number of markers
**Validates: Requirements 5.1, 5.3**

### Property 5: Offline sync conflict detection
*For any* offline action that conflicts with server state, the sync operation should surface the conflict without data loss
**Validates: Requirements 6.5**

### Property 6: Rate limit enforcement
*For any* user exceeding the configured rate limit, subsequent requests should return ERR_RATE_LIMITED with appropriate retry-after header
**Validates: Requirements 2.5**

### Property 7: Pagination continuity
*For any* paginated command list, fetching all pages using cursor tokens should return all commands exactly once without duplicates or omissions
**Validates: Requirements 7.1, 7.2**

### Property 8: Accessibility mode preservation
*For any* accessibility mode enabled, all performance targets should be maintained without degradation
**Validates: Requirements 3.5**

### Property 9: Pre-warming budget compliance
*For any* pre-warming operation, the total cached data size should not exceed the configured memory budget
**Validates: Requirements 8.3**

### Property 10: Viewport culling correctness
*For any* server response, all commands and markers outside the viewport plus padding should be excluded
**Validates: Requirements 8.4**

## Error Handling

### Client-Side Errors

**Network Failures**
- Retry with exponential backoff (100ms, 200ms, 400ms, 800ms, 1600ms)
- After 5 retries, enable offline mode and queue actions
- Display toast notification: "Connection lost. Working offline."

**Render Performance Degradation**
- Monitor frame drops using `requestAnimationFrame` timing
- If 3 consecutive frames exceed 200ms, enable fallback mode
- Fallback mode: disable animations, simplify geometry, reduce marker density
- Display subtle indicator: "Performance mode active"

**Cache Corruption**
- Detect via checksum validation on cache reads
- Clear corrupted entries and re-fetch from server
- Log to telemetry with cache key and error details

**Offline Sync Conflicts**
- Surface conflicts in a dedicated UI panel
- Show server state vs. user action side-by-side
- Allow user to retry, discard, or modify action
- Log all conflicts to telemetry for analysis

### Server-Side Errors

**Rate Limit Exceeded**
- Return HTTP 429 with `Retry-After` header
- Include error code: `ERR_RATE_LIMITED`
- Log user ID, endpoint, and current rate for monitoring

**Delta Calculation Failure**
- Fall back to full state response
- Log error with cursor value and state snapshot
- Increment `delta_failure` metric

**Database Timeout**
- Return HTTP 503 with `Retry-After: 5`
- Log slow query details for optimization
- Alert if timeout rate exceeds 1% of requests

**Invalid Cursor Token**
- Return HTTP 400 with error: `ERR_INVALID_CURSOR`
- Client should discard cursor and request from beginning
- Log occurrence to detect potential tampering

## Testing Strategy

### Unit Tests

**Client-Side**
- ClusterManager: Test clustering algorithm with various marker distributions
- CacheManager: Test LRU eviction with different access patterns
- DeltaApplicator: Test merge logic with add/modify/remove operations
- OfflineQueue: Test conflict detection with overlapping edits

**Server-Side**
- DeltaCalculator: Test delta generation with various state changes
- RateLimiter: Test token bucket algorithm with burst scenarios
- ETagGenerator: Test cache key generation for multi-tenant data
- ViewportCuller: Test boundary conditions for culling logic

### Property-Based Tests

Property-based tests will use the appropriate testing library for each language (e.g., fast-check for TypeScript, PHPUnit with random data generators for PHP). Each test should run a minimum of 100 iterations.

**Property 1: Render performance under load**
- Generate random viewports with 500-1000 commands
- Measure p95 render latency across 100 iterations
- Assert p95 < 200ms on target device profile

**Property 2: Cache validation correctness**
- Generate random map data and ETags
- Make requests with matching ETags
- Assert all responses are HTTP 304 with empty body

**Property 3: Delta idempotence**
- Generate random base states and deltas
- Apply delta once, then apply same delta to result
- Assert both results are identical

**Property 4: Cluster count accuracy**
- Generate random marker sets (10-1000 markers)
- Cluster at various zoom levels
- Assert sum of cluster counts equals total markers

**Property 5: Offline sync conflict detection**
- Generate random offline actions and conflicting server states
- Run sync operation
- Assert all conflicts are detected and surfaced

**Property 6: Rate limit enforcement**
- Generate request bursts exceeding rate limit
- Assert excess requests return 429 with retry-after

**Property 7: Pagination continuity**
- Generate random command lists (50-500 commands)
- Paginate with various page sizes
- Assert all commands appear exactly once

**Property 8: Accessibility mode preservation**
- Generate random viewports with accessibility modes enabled
- Measure render performance
- Assert performance targets are maintained

**Property 9: Pre-warming budget compliance**
- Generate random continent data
- Run pre-warming with various budgets
- Assert cache size never exceeds budget

**Property 10: Viewport culling correctness**
- Generate random viewports and map data
- Apply culling logic
- Assert no items outside viewport + padding remain

### Integration Tests

- End-to-end map load with 500+ commands
- Offline mode: queue actions, disconnect, reconnect, verify sync
- Rate limiting: burst requests, verify 429 responses
- Cache invalidation: update server data, verify stale cache detection
- Accessibility: enable modes, verify UI changes and performance

### Performance Tests

- Load test: 1000 concurrent users fetching map data
- Stress test: Single user with 2000 commands in viewport
- Soak test: 24-hour test with realistic usage patterns
- Mobile device testing: Test on low-end Android and iOS devices

## Implementation Notes

### Client-Side Rendering Optimization

**Batching Strategy**
- Collect all updates within 1-second windows per village
- Use `requestAnimationFrame` to schedule batch renders
- Deduplicate updates by command/marker ID

**Fallback Mode Triggers**
- Frame time > 200ms for 3 consecutive frames
- Total element count > 2000 in viewport
- Device memory < 2GB (via `navigator.deviceMemory`)

**Skeleton UI**
- Show during initial load and pagination
- Use CSS animations for shimmer effect
- Match layout of actual content to prevent layout shift

### Server-Side Caching Strategy

**Cache Key Design**
```
map:{worldId}:{viewport_hash}:{diplomacy_version}
```

**Invalidation Strategy**
- Invalidate on command completion/cancellation
- Invalidate on village ownership change
- Invalidate on diplomacy state change
- Use Redis pub/sub for multi-server invalidation

**ETag Generation**
```php
$etag = md5(json_encode([
  'data_version' => $dataVersion,
  'viewport' => $viewport,
  'user_permissions' => $userPermissions
]));
```

### Delta Update Protocol

**Cursor Format**
```
base64(json_encode([
  'timestamp' => $lastFetchTime,
  'version' => $dataVersion,
  'checksum' => $stateChecksum
]))
```

**Delta Response**
```json
{
  "delta": {
    "added": [...],
    "modified": [...],
    "removed": [...]
  },
  "cursor": "...",
  "has_more": false
}
```

### Accessibility Implementation

**High Contrast Mode**
- Override CSS custom properties for colors
- Ensure 7:1 contrast ratio for all text
- Use distinct patterns for colorblind users

**Reduced Motion**
- Disable CSS transitions and animations
- Use instant state changes instead of tweens
- Respect `prefers-reduced-motion` media query

**Keyboard Navigation**
- Arrow keys: pan map (50px per press)
- +/- keys: zoom in/out
- Tab: cycle through filter controls
- Enter/Space: toggle filters
- Escape: close modals/panels

### Monitoring and Alerting

**Key Metrics**
- Map fetch p50/p95/p99 latency
- Cache hit rate (target: >80%)
- Average payload size (target: <100KB)
- Client render p95 (target: <200ms)
- Rate limit hit rate (alert if >5%)

**Alerts**
- Payload size >500KB (investigate data bloat)
- Render time p95 >300ms (performance regression)
- Cache hit rate <60% (cache configuration issue)
- Delta failure rate >1% (investigate cursor issues)

## Security Considerations

**Rate Limiting**
- Prevent map data scraping via aggressive rate limits
- Use sliding window algorithm for fairness
- Whitelist admin/monitoring tools

**Data Visibility**
- Respect fog-of-war: only return visible villages/commands
- Filter commands by diplomacy state (ally/enemy visibility)
- Validate viewport bounds to prevent out-of-world requests

**Offline Sync**
- Validate all queued actions server-side
- Check permissions before applying marker edits
- Prevent timestamp manipulation attacks

## Future Enhancements

- WebSocket support for real-time command updates
- Service Worker for true offline PWA experience
- WebGL rendering for >1000 commands
- Predictive pre-fetching based on user behavior
- Collaborative markers with real-time sync
- Advanced clustering with density-based algorithms (DBSCAN)
