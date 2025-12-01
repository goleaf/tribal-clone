# Design Document

## Overview

The Village & Building System is the foundational progression mechanic for a medieval tribal war browser MMO. It provides players with the ability to construct and upgrade buildings that produce resources, train military units, enhance defenses, and unlock strategic capabilities. The system supports 20+ building types organized into categories (resource production, military training, defensive structures, support facilities, and special buildings), each with unique upgrade paths, prerequisites, and strategic roles.

The design integrates with existing systems including resource production, military unit training, combat resolution, conquest mechanics, and world configuration. It implements a queue-based construction system with milestone-based slot unlocking, immediate resource deduction, and sequential processing to ensure fair progression pacing across different world archetypes (casual, speed, hardcore).

## Architecture

### High-Level Components

```
┌─────────────────────────────────────────────────────────────┐
│                     Client Layer                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Building UI  │  │  Queue UI    │  │  Village     │      │
│  │              │  │              │  │  Overview    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                     API Layer                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Building     │  │  Queue       │  │  Village     │      │
│  │ Endpoints    │  │  Endpoints   │  │  Endpoints   │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   Manager Layer                              │
│  ┌──────────────────┐  ┌──────────────────┐                │
│  │ BuildingManager  │  │ BuildingQueue    │                │
│  │                  │  │ Manager          │                │
│  │ - Validation     │  │                  │                │
│  │ - Level queries  │  │ - Enqueue        │                │
│  │ - Prerequisites  │  │ - Process        │                │
│  │ - Wall decay     │  │ - Cancel         │                │
│  └──────────────────┘  └──────────────────┘                │
│           │                      │                           │
│           ▼                      ▼                           │
│  ┌──────────────────┐  ┌──────────────────┐                │
│  │ BuildingConfig   │  │ Population       │                │
│  │ Manager          │  │ Manager          │                │
│  │                  │  │                  │                │
│  │ - Cost curves    │  │ - Capacity       │                │
│  │ - Time curves    │  │ - Validation     │                │
│  │ - Production     │  │                  │                │
│  └──────────────────┘  └──────────────────┘                │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   Data Layer                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ building_    │  │ village_     │  │ building_    │      │
│  │ types        │  │ buildings    │  │ queue        │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│  ┌──────────────┐  ┌──────────────┐                        │
│  │ building_    │  │ villages     │                        │
│  │ requirements │  │              │                        │
│  └──────────────┘  └──────────────┘                        │
└─────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

**BuildingManager**
- Validates building upgrade eligibility (prerequisites, resources, population, caps)
- Queries current building levels for villages
- Calculates production rates and capacities
- Applies wall decay for inactive villages (when enabled)
- Provides building information for UI display

**BuildingQueueManager**
- Enqueues building upgrades with immediate resource deduction
- Processes completed builds and applies level changes
- Cancels queued builds with partial refunds
- Rebalances queue timing after insertions/cancellations
- Enforces queue slot limits based on Town Hall milestones
- Logs queue events and metrics for telemetry

**BuildingConfigManager**
- Loads and caches building configurations from database
- Calculates upgrade costs using exponential curves
- Calculates upgrade times with Town Hall and world speed modifiers
- Calculates resource production rates
- Manages configuration versioning for client cache invalidation
- Provides prerequisite lookups

**PopulationManager**
- Validates population capacity for building upgrades
- Calculates farm capacity by level
- Tracks population consumption across buildings and units

## Components and Interfaces

### BuildingManager Interface

```php
class BuildingManager {
    // Validation
    public function canUpgradeBuilding(int $villageId, string $internalName, ?int $userId = null): array
    public function checkBuildingRequirements(string $internalName, int $villageId): array
    
    // Queries
    public function getBuildingLevel(int $villageId, string $internalName): int
    public function getVillageBuilding(int $villageId, string $internalName): ?array
    public function getVillageBuildingsViewData(int $villageId, int $mainBuildingLevel): array
    
    // Calculations
    public function getHourlyProduction(string $buildingInternalName, int $level): float
    public function getWarehouseCapacityByLevel(int $warehouseLevel): int
    public function getWallDefenseBonus(int $wallLevel): float
    
    // Mutations
    public function setBuildingLevel(int $villageId, string $internalName, int $newLevel): bool
    public function addBuildingToQueue(int $villageId, string $internalName): array
    
    // Demolition
    public function canDemolishBuilding(int $villageId, string $internalName): array
    public function queueDemolition(int $villageId, string $internalName): array
    
    // Wall Decay
    public function applyWallDecayIfNeeded(array $village, ?array $worldConfig = null): ?array
}
```

### BuildingQueueManager Interface

```php
class BuildingQueueManager {
    // Queue Operations
    public function enqueueBuild(int $villageId, string $buildingInternalName, int $userId): array
    public function cancelBuild(int $queueItemId, int $userId): array
    public function onBuildComplete(int $queueItemId): array
    
    // Queries
    public function getVillageQueue(int $villageId): array
    
    // Processing
    public function processCompletedBuilds(): array
}
```

### BuildingConfigManager Interface

```php
class BuildingConfigManager {
    // Configuration
    public function getBuildingConfig(string $internalName): ?array
    public function getAllBuildingConfigs(): array
    public function getBuildingRequirements(string $internalName): array
    public function getMaxLevel(string $internalName): ?int
    
    // Calculations
    public function calculateUpgradeCost(string $internalName, int $currentLevel): ?array
    public function calculateUpgradeTime(string $internalName, int $currentLevel, int $mainBuildingLevel = 0): ?int
    public function calculateProduction(string $internalName, int $level): ?float
    public function calculateWarehouseCapacity(int $level): ?int
    public function calculateFarmCapacity(int $level): ?int
    public function calculatePopulationCost(string $internalName, int $currentLevel): ?int
    
    // Cache Management
    public function getConfigVersion(): string
    public function invalidateCache(): void
}
```

## Data Models

### Database Schema

**building_types**
```sql
CREATE TABLE building_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_name TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    max_level INTEGER NOT NULL DEFAULT 20,
    cost_wood_initial INTEGER NOT NULL,
    cost_clay_initial INTEGER NOT NULL,
    cost_iron_initial INTEGER NOT NULL,
    cost_factor REAL NOT NULL DEFAULT 1.26,
    base_build_time_initial INTEGER NOT NULL,
    production_type TEXT,  -- 'wood', 'clay', 'iron', NULL
    production_initial REAL,
    production_factor REAL,
    population_cost INTEGER DEFAULT 0
);
```

**village_buildings**
```sql
CREATE TABLE village_buildings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    building_type_id INTEGER NOT NULL,
    level INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (village_id) REFERENCES villages(id),
    FOREIGN KEY (building_type_id) REFERENCES building_types(id),
    UNIQUE(village_id, building_type_id)
);
```

**building_queue**
```sql
CREATE TABLE building_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    village_building_id INTEGER NOT NULL,
    building_type_id INTEGER NOT NULL,
    level INTEGER NOT NULL,
    starts_at DATETIME NOT NULL,
    finish_time DATETIME NOT NULL,
    status TEXT DEFAULT 'active',  -- 'active', 'pending', 'completed'
    is_demolition INTEGER DEFAULT 0,
    refund_wood INTEGER DEFAULT 0,
    refund_clay INTEGER DEFAULT 0,
    refund_iron INTEGER DEFAULT 0,
    FOREIGN KEY (village_id) REFERENCES villages(id),
    FOREIGN KEY (village_building_id) REFERENCES village_buildings(id),
    FOREIGN KEY (building_type_id) REFERENCES building_types(id)
);
```

**building_requirements**
```sql
CREATE TABLE building_requirements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    building_type_id INTEGER NOT NULL,
    required_building TEXT NOT NULL,  -- internal_name of required building
    required_level INTEGER NOT NULL,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id)
);
```

### Configuration Constants

```php
// Queue Configuration
define('BUILDING_QUEUE_MAX_ITEMS', 10);      // Max total queue items
define('BUILDING_BASE_QUEUE_SLOTS', 1);      // Base concurrent slots
define('BUILDING_HQ_MILESTONE_STEP', 5);     // HQ levels per slot unlock
define('BUILDING_MAX_QUEUE_SLOTS', 3);       // Maximum concurrent slots

// Build Time Configuration
define('BUILD_TIME_LEVEL_FACTOR', 1.18);     // Exponential time scaling
define('MAIN_BUILDING_TIME_REDUCTION_PER_LEVEL', 0.02);  // 2% per HQ level

// Wall Configuration
define('WALL_MAX_LEVEL', 20);
define('WALL_DECAY_ENABLED', false);
define('WALL_DECAY_INACTIVE_HOURS', 72);
define('WALL_DECAY_INTERVAL_HOURS', 24);

// Watchtower Configuration
define('WATCHTOWER_MAX_LEVEL', 20);
define('WATCHTOWER_ENABLED', false);

// World Speed Multipliers
define('WORLD_SPEED', 1.0);
define('BUILD_SPEED_MULTIPLIER', 1.0);
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Prerequisite Validation Completeness
*For any* building upgrade request and village state, when prerequisites are not met, the system should reject the upgrade and identify all missing requirements.
**Validates: Requirements 1.1, 14.2**

### Property 2: Resource Deduction Atomicity
*For any* building upgrade that is successfully queued, the exact resource costs should be deducted from the village immediately, and no partial deductions should occur on failures.
**Validates: Requirements 1.2, 19.3**

### Property 3: Population Capacity Enforcement
*For any* building upgrade request, when the upgrade would exceed farm capacity, the system should reject the request with the current and required population values.
**Validates: Requirements 1.3, 19.2**

### Property 4: Build Time Calculation Consistency
*For any* building type, current level, Town Hall level, and world configuration, the calculated build time should be deterministic and apply all configured multipliers in the correct order.
**Validates: Requirements 1.4, 15.3**

### Property 5: Level Increment on Completion
*For any* completed building upgrade, the building level should increment by exactly one and any associated production rates or capacities should update to match the new level.
**Validates: Requirements 1.5**

### Property 6: Queue Slot Milestone Unlocking
*For any* Town Hall level, the number of available queue slots should equal the base slots plus one additional slot for every milestone step completed.
**Validates: Requirements 2.2, 3.5**

### Property 7: Queue Capacity Rejection
*For any* village with queue count at or above the slot limit, attempting to queue another building should be rejected with error code ERR_QUEUE_FULL or ERR_QUEUE_CAP.
**Validates: Requirements 2.3**

### Property 8: Parallel Queue Rules
*For any* world with parallel construction enabled, the system should allow at most one resource building and one military building to be queued simultaneously, rejecting additional builds that violate this rule.
**Validates: Requirements 2.4**

### Property 9: Cancellation Refund Accuracy
*For any* queued building that is cancelled, the refund should equal exactly 90% of the original upgrade cost for each resource type, rounded down.
**Validates: Requirements 2.5**

### Property 10: Town Hall Time Reduction
*For any* building upgrade, the effective build time should be reduced by 2% for each Town Hall level, applied as a divisor to the base time.
**Validates: Requirements 3.1**

### Property 11: Resource Production Scaling
*For any* resource building (Lumber Yard, Clay Pit, Iron Mine) at a given level, the production rate should follow the formula: base × growth^(level-1) × world_speed × build_speed.
**Validates: Requirements 4.1, 4.2, 4.3**

### Property 12: Storage Capacity Capping
*For any* village, when resource production would cause resources to exceed storage capacity, the resources should be capped at exactly the storage capacity value.
**Validates: Requirements 4.4, 4.5**

### Property 13: Storage Capacity Scaling
*For any* Storage or Warehouse building at a given level, the capacity should follow the configured exponential curve and apply to all three resource types.
**Validates: Requirements 5.1, 5.2**

### Property 14: Vault Protection Calculation
*For any* village with a Vault, the percentage of resources protected from plunder should increase with vault level, and plunder calculations should subtract exactly this protected amount.
**Validates: Requirements 5.3, 5.4**

### Property 15: Storage Capacity Prerequisite
*For any* building upgrade with a cost exceeding current storage capacity, the system should reject the upgrade with error code ERR_STORAGE_CAP.
**Validates: Requirements 5.5**

### Property 16: Military Building Training Speed
*For any* military building at a given level, unit training time should be reduced according to the building level multiplier.
**Validates: Requirements 6.5**

### Property 17: Wall Defense Multiplier
*For any* wall level, the defense multiplier should equal 1 + (0.08 × wall_level), applied to all defending troops.
**Validates: Requirements 7.1**

### Property 18: Wall Damage from Siege
*For any* successful siege attack with surviving rams, the wall level should decrease based on ram count, with a minimum reduction of 0.25 levels per wave.
**Validates: Requirements 7.2**

### Property 19: Wall Repair Queue Processing
*For any* wall repair queued and completed, the wall level should be restored to the target level, consuming the calculated resources and time.
**Validates: Requirements 7.3**

### Property 20: Repair Blocking with Incoming Attacks
*For any* village with hostile commands arriving within the repair block window, wall repair queueing should be rejected with error code ERR_REPAIR_BLOCKED.
**Validates: Requirements 7.4**

### Property 21: Wall Decay Application
*For any* village that is inactive beyond the threshold with wall decay enabled, the wall level should decrease by 1 per decay interval, with decay timestamps recorded.
**Validates: Requirements 7.5**

### Property 22: Watchtower Detection Radius
*For any* watchtower level, the detection radius should follow the configured level-to-radius curve, and commands entering this radius should trigger warnings.
**Validates: Requirements 8.1, 8.5**

### Property 23: Noble Detection Flagging
*For any* incoming command containing conquest units within watchtower range, when noble detection is enabled, the command should be flagged with a noble indicator.
**Validates: Requirements 8.3**

### Property 24: Detection Probability Modifiers
*For any* detection calculation, the probability should incorporate modifiers from Scout Hall level, terrain type, and weather conditions according to configured formulas.
**Validates: Requirements 8.4**

### Property 25: Hospital Recovery Rate
*For any* hospital level, the recovery percentage should follow the configured level-to-rate curve, capped at the maximum recovery rate.
**Validates: Requirements 9.1, 9.2**

### Property 26: Hospital Recovery Application
*For any* defensive battle with a hospital, recovered troops should be added to the garrison and recovery costs should be deducted from village resources.
**Validates: Requirements 9.3**

### Property 27: Market Caravan Count
*For any* market level, the number of available merchant caravans should follow the configured level-to-count formula.
**Validates: Requirements 10.2**

### Property 28: Market Speed Scaling
*For any* market level, merchant travel time should be reduced according to the configured level-to-speed curve.
**Validates: Requirements 10.3**

### Property 29: Hall of Banners Minting Caps
*For any* village or account (depending on configuration), daily minting attempts should be rejected when the cap is reached, enforcing the configured limit.
**Validates: Requirements 11.4**

### Property 30: Hall of Banners Time Reduction
*For any* Hall of Banners level, minting time should be reduced and daily caps should potentially increase according to configured formulas.
**Validates: Requirements 11.5**

### Property 31: Library Research Time Scaling
*For any* library level, research time should be reduced according to the configured level-to-time curve.
**Validates: Requirements 12.4**

### Property 32: Maximum Level Cap Enforcement
*For any* building at its world-configured maximum level, upgrade attempts should be rejected with error code ERR_CAP.
**Validates: Requirements 14.1**

### Property 33: Research Prerequisite Validation
*For any* building requiring research prerequisites, construction attempts without completed research should be rejected with error code ERR_RESEARCH.
**Validates: Requirements 14.3**

### Property 34: Protection Mode Military Blocking
*For any* village under emergency shield protection, when military building blocking is configured, military building upgrades should be rejected while other buildings are allowed.
**Validates: Requirements 14.4**

### Property 35: World Multiplier Application
*For any* building cost or time calculation, world archetype multipliers should be applied to the base values, producing consistent effective costs and times.
**Validates: Requirements 14.5, 15.1, 15.2, 15.4**

### Property 36: Complete Time Formula
*For any* building upgrade, the effective build time should equal: (base_time × level_factor^level) / (world_speed × build_speed × (1 + 0.02 × hq_level)).
**Validates: Requirements 15.3**

### Property 37: Invalid Building ID Rejection
*For any* building upgrade request with an invalid or non-existent building ID, the system should reject the request with error code ERR_INPUT.
**Validates: Requirements 19.1**

### Property 38: Concurrent Queue Atomicity
*For any* set of concurrent building queue requests that would exceed slot limits, the system should process them atomically such that only valid requests succeed and violating requests are rejected.
**Validates: Requirements 19.4**

## Error Handling

### Error Codes

The system uses standardized error codes for all validation failures:

- **ERR_INPUT**: Invalid input parameters (building ID, level, etc.)
- **ERR_PREREQ**: Prerequisites not met (buildings, research, special conditions)
- **ERR_RES**: Insufficient resources
- **ERR_POP**: Insufficient population capacity
- **ERR_CAP**: Maximum level or queue capacity reached
- **ERR_QUEUE_CAP**: No available queue slots (increase Town Hall)
- **ERR_QUEUE_FULL**: Absolute queue limit reached
- **ERR_STORAGE_CAP**: Build cost exceeds storage capacity
- **ERR_PROTECTED**: Action blocked by protection status
- **ERR_REPAIR_BLOCKED**: Wall repair blocked by incoming attacks
- **ERR_RESEARCH**: Research prerequisites not met
- **ERR_SERVER**: Internal server error

### Error Response Format

```php
[
    'success' => false,
    'message' => 'Human-readable error message',
    'code' => 'ERR_CODE',
    'details' => [
        // Optional additional context
        'missing_requirements' => [...],
        'current_value' => X,
        'required_value' => Y
    ]
]
```

### Validation Order

To provide clear error messages, validations are performed in this order:

1. Input validation (building ID, village ownership)
2. Protection status checks
3. Maximum level caps
4. Prerequisites (buildings, research)
5. Queue capacity
6. Resource availability
7. Population capacity
8. Storage capacity (for large builds)

### Transaction Safety

All building operations that modify state use database transactions:

```php
try {
    $this->conn->begin_transaction();
    
    // Validate
    // Deduct resources
    // Insert queue item
    // Update state
    
    $this->conn->commit();
    return ['success' => true, ...];
    
} catch (Exception $e) {
    $this->conn->rollback();
    return ['success' => false, 'message' => $e->getMessage()];
}
```

### Idempotency

Queue processing operations are idempotent:

- Completing an already-completed build returns success without side effects
- Processing checks current status before applying changes
- Timestamps are validated to prevent premature completion

## Testing Strategy

### Unit Testing

Unit tests will cover:

- **Cost Calculation**: Verify exponential cost curves for all building types across levels 0-20
- **Time Calculation**: Verify time formulas with various TH levels and world multipliers
- **Production Calculation**: Verify resource production formulas for all resource buildings
- **Capacity Calculation**: Verify storage and farm capacity formulas
- **Prerequisite Validation**: Verify prerequisite checking logic with various building states
- **Queue Slot Calculation**: Verify slot unlocking at TH milestones
- **Refund Calculation**: Verify 90% refund math for cancellations
- **Wall Defense Bonus**: Verify 8% per level multiplier
- **Error Code Assignment**: Verify correct error codes for each validation failure

### Property-Based Testing

Property-based tests will use **fast-check** (JavaScript/TypeScript) or **PHPUnit with Faker** (PHP) to generate random inputs and verify universal properties. Each property test should run a minimum of 100 iterations.

The testing framework will be **PHPUnit** with **Faker** for property-based testing in PHP.

Property tests will cover:

- **Property 1-38**: Each correctness property listed above will have a dedicated property-based test
- Tests will generate random building types, levels, resource amounts, TH levels, and world configurations
- Tests will verify formulas, validation logic, and state transitions across the input space
- Tests will check error codes, refund calculations, and atomic operations

### Integration Testing

Integration tests will verify:

- Queue processing with database persistence
- Resource deduction and refund flows
- Building level updates on completion
- Wall damage and repair integration with combat system
- Watchtower detection integration with command system
- Hospital recovery integration with battle system
- Market, Hall of Banners, and Library integration with respective systems

### Edge Cases

Specific edge cases to test:

- Building at level 0 (not yet constructed)
- Building at maximum level (cap enforcement)
- Town Hall at level 0 (no time reduction)
- Town Hall at milestone levels (slot unlocking)
- Queue at capacity (rejection)
- Resources exactly at cost (boundary)
- Resources at storage capacity (capping)
- Wall at level 0 (no defense bonus)
- Inactive village at decay threshold (decay triggering)
- Concurrent queue requests (atomicity)
- Cancelling active vs pending builds (refund and rebalancing)

### Performance Testing

Performance tests will measure:

- Queue processing latency (p50, p95, p99)
- Cost/time calculation performance (cached vs uncached)
- Concurrent queue request handling
- Database query performance for building lookups
- Configuration cache hit rates

## Implementation Notes

### Cost and Time Curves

Building costs and times scale exponentially to create meaningful progression:

**Cost Formula**: `cost = initial_cost × cost_factor^level`
- Typical cost_factor: 1.26 (26% increase per level)
- Clamped between 1.01 and 1.6 to prevent runaway costs

**Time Formula**: `time = base_time × time_factor^level / (world_speed × build_speed × hq_bonus)`
- Typical time_factor: 1.18 (18% increase per level)
- HQ bonus: `1 + (0.02 × hq_level)` (2% per level)
- Tier floors ensure mid-game builds take 30-90 minutes, late-game 2-4 hours

### Queue Processing

The queue system uses a sequential processing model:

1. **Active Build**: One build is marked 'active' and processes first
2. **Pending Builds**: Additional builds are marked 'pending' and wait in sequence
3. **Rebalancing**: When active completes or is cancelled, next pending is promoted to active
4. **Timing**: Each pending build's start time is set to the previous build's finish time

This ensures:
- Predictable completion times
- No race conditions
- Fair processing order
- Efficient database queries (single active build per village)

### Caching Strategy

**BuildingConfigManager** caches:
- Building configurations (loaded once, invalidated on config changes)
- Cost curves (memoized per building/level)
- Time curves (memoized per building/level/hq/world)
- Configuration version hash (for client cache busting)

Cache invalidation:
- Manual via `invalidateCache()` after config updates
- Automatic on world speed changes
- Version hash changes trigger client refetch

### Wall Decay Implementation

Wall decay is optional and controlled by world configuration:

```php
if ($worldConfig['wall_decay_enabled']) {
    $decayInfo = $buildingManager->applyWallDecayIfNeeded($village, $worldConfig);
    if ($decayInfo) {
        // Log decay event
        // Update village record
        // Notify player (optional)
    }
}
```

Decay logic:
- Checks last decay timestamp (minimum interval: 24 hours)
- Checks player last activity (minimum inactivity: 72 hours)
- Reduces wall by 1 level (minimum 0)
- Updates `last_wall_decay_at` timestamp
- Logs to `logs/wall_decay.log`

### Watchtower Integration

Watchtower detection is handled by the command system but configured by building levels:

```php
$watchtowerLevel = $buildingManager->getBuildingLevel($villageId, 'watchtower');
$detectionRadius = calculateDetectionRadius($watchtowerLevel);

// In command processing:
if (distanceBetween($command, $village) <= $detectionRadius) {
    createWarning($command, $village);
    if ($command->hasNobles() && $worldConfig['noble_detection_enabled']) {
        flagAsNobleCommand($command);
    }
}
```

### Hospital Integration

Hospital recovery is applied post-battle by the battle resolver:

```php
$hospitalLevel = $buildingManager->getBuildingLevel($defenderVillageId, 'hospital');
if ($hospitalLevel > 0 && $worldConfig['hospital_enabled']) {
    $recoveryRate = calculateRecoveryRate($hospitalLevel);
    $recovered = applyRecovery($lostTroops, $recoveryRate);
    addToGarrison($defenderVillageId, $recovered);
    deductRecoveryCosts($defenderVillageId, $recovered);
}
```

### Parallel Queue Rules

When parallel construction is enabled:

```php
$activeBuilds = getActiveBuilds($villageId);
$resourceBuildActive = hasResourceBuild($activeBuilds);
$militaryBuildActive = hasMilitaryBuild($activeBuilds);

if ($buildingType === 'resource' && $resourceBuildActive) {
    return ['success' => false, 'code' => 'ERR_QUEUE_CAP'];
}
if ($buildingType === 'military' && $militaryBuildActive) {
    return ['success' => false, 'code' => 'ERR_QUEUE_CAP'];
}
```

Building categories:
- **Resource**: sawmill, clay_pit, iron_mine, farm, warehouse, storage
- **Military**: barracks, stable, workshop, siege_foundry, garrison
- **Other**: All other buildings (share slots with either category)

### World Archetype Multipliers

World archetypes modify costs and times:

```php
$worldArchetype = $worldConfig['archetype']; // 'casual', 'speed', 'hardcore'

$multipliers = [
    'casual' => ['cost' => 0.8, 'time' => 0.7],
    'speed' => ['cost' => 1.0, 'time' => 0.3],
    'hardcore' => ['cost' => 1.5, 'time' => 1.2]
];

$effectiveCost = $baseCost * $multipliers[$worldArchetype]['cost'];
$effectiveTime = $baseTime * $multipliers[$worldArchetype]['time'];
```

### Logging and Telemetry

All queue operations are logged to `logs/build_queue.log` (JSONL format):

```json
{"type":"enqueue","queue_item_id":123,"village_id":456,"building":"barracks","level":5,"costs":{"wood":1000,"clay":800,"iron":600},"ts":1638360000}
{"type":"complete","queue_item_id":123,"village_id":456,"building_type_id":2,"level":5,"ts":1638363600}
{"type":"cancel","queue_item_id":124,"village_id":456,"refund":{"wood":900,"clay":720,"iron":540},"ts":1638361800}
```

Metrics are logged to `logs/build_queue_metrics.log`:

```json
{"metric":"enqueue","village_id":456,"building":"barracks","level":5,"hq_level":10,"queue_count":2,"slot_limit":2,"build_time":3600,"ts":1638360000}
{"metric":"complete","queue_item_id":123,"village_id":456,"level":5,"ts":1638363600}
```

## Deployment Considerations

### Database Migrations

Required migrations:
1. Add `last_wall_decay_at` column to `villages` table
2. Add `status` column to `building_queue` table (if not exists)
3. Add `is_demolition` and refund columns to `building_queue` table
4. Create indexes on `building_queue(village_id, status)` and `building_queue(finish_time)`

### Feature Flags

World-level feature flags:
- `wall_decay_enabled`: Enable/disable wall decay
- `watchtower_enabled`: Enable/disable watchtower detection
- `hospital_enabled`: Enable/disable hospital recovery
- `parallel_queues_enabled`: Enable/disable parallel construction
- `outpost_enabled`: Enable/disable temporary outposts

### Configuration Validation

On deployment, validate:
- All building types have valid cost/time curves
- Prerequisites don't create circular dependencies
- World multipliers are within reasonable ranges (0.1 to 5.0)
- Queue slot limits are sensible (1-10)
- Decay intervals are positive

### Rollback Plan

If issues arise:
1. Disable feature flags via world configuration
2. Pause queue processing cron job
3. Rollback database migrations (restore from backup)
4. Clear building config cache
5. Restart application servers

### Monitoring

Monitor:
- Queue processing latency (alert if p95 > 1s)
- Queue depth per village (alert if > 50)
- Failed enqueue rate (alert if > 5%)
- Wall decay application rate (alert on anomalies)
- Configuration cache hit rate (alert if < 95%)

