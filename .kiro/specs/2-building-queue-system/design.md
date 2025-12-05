# Design Document

## Overview

The Building Queue System is a foundational game mechanic that enables players to queue multiple building construction and upgrade tasks within their villages. The system implements sequential processing where only one build is active at a time, with pending builds automatically promoted when the active build completes. Resources are deducted immediately upon queueing to prevent resource duplication exploits. The system must handle concurrent requests safely across both SQLite and MySQL databases while maintaining strict data integrity guarantees.

The design follows a manager-based architecture with clear separation between queue management (`BuildingQueueManager`), building configuration (`BuildingConfigManager`), and building operations (`BuildingManager`). A cron-based processor handles automatic completion of finished builds every minute.

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                     Client Layer                             │
│  (AJAX endpoints: upgrade_building.php, cancel_upgrade.php) │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│                BuildingQueueManager                          │
│  - enqueueBuild()                                            │
│  - onBuildComplete()                                         │
│  - cancelBuild()                                             │
│  - getVillageQueue()                                         │
│  - processCompletedBuilds()                                  │
└────────────────────┬────────────────────────────────────────┘
                     │
        ┌────────────┼────────────┐
        │            │            │
┌───────▼──────┐ ┌──▼──────────┐ ┌▼────────────────┐
│BuildingConfig│ │BuildingMgr  │ │NotificationMgr  │
│Manager       │ │             │ │                 │
└──────────────┘ └─────────────┘ └─────────────────┘
        │
┌───────▼──────────────────────────────────────────────────────┐
│                     Database Layer                            │
│  Tables: building_queue, village_buildings, building_types,   │
│          villages, building_requirements                      │
└───────────────────────────────────────────────────────────────┘
        │
┌───────▼──────────────────────────────────────────────────────┐
│                  Cron Processor                               │
│  jobs/process_building_queue.php (runs every minute)          │
└───────────────────────────────────────────────────────────────┘
```

### Data Flow

**Enqueue Flow:**
1. Player initiates upgrade via AJAX endpoint
2. BuildingQueueManager validates prerequisites and resources
3. Resources deducted immediately from village
4. Queue item inserted with status 'active' (if queue empty) or 'pending'
5. Response returned to client with queue item details

**Completion Flow:**
1. Cron processor identifies active builds with finish_time <= NOW()
2. For each completed build:
   - Increment building level in village_buildings
   - Mark queue item as 'completed'
   - Promote next pending item to 'active' (rebalance queue)
   - Create notification for village owner
3. Process continues idempotently if run multiple times

**Cancellation Flow:**
1. Player cancels queue item via AJAX endpoint
2. BuildingQueueManager calculates 90% refund
3. Resources added back to village
4. Queue item deleted (or marked 'canceled')
5. If canceled item was active, promote next pending to active
6. Rebalance remaining queue items

## Components and Interfaces

### BuildingQueueManager

Primary class responsible for all queue operations.

**Public Methods:**

```php
public function enqueueBuild(int $villageId, string $buildingInternalName, int $userId): array
```
- Validates prerequisites, resources, and queue capacity
- Deducts resources immediately
- Inserts queue item with appropriate status
- Returns: `['success' => bool, 'queue_item_id' => int, 'status' => string, 'finish_at' => int]`

```php
public function onBuildComplete(int $queueItemId): array
```
- Idempotent completion handler
- Increments building level
- Marks item as completed
- Promotes next pending item
- Returns: `['success' => bool, 'next_item_id' => ?int]`

```php
public function cancelBuild(int $queueItemId, int $userId): array
```
- Validates ownership
- Calculates and applies 90% refund
- Deletes queue item
- Promotes next pending if active was canceled
- Returns: `['success' => bool, 'refund' => array]`

```php
public function getVillageQueue(int $villageId): array
```
- Returns all active and pending queue items for display
- Ordered by start_time ascending

```php
public function processCompletedBuilds(): array
```
- Batch processes all completed builds (for cron)
- Returns array of processing results

**Private Helper Methods:**

- `rebalanceQueue(int $villageId)`: Recalculates timing for all pending items after active completion/cancellation
- `getQueueCount(int $villageId)`: Returns count of active + pending items
- `getQueueSlotLimit(int $hqLevel)`: Calculates allowed queue slots based on HQ level
- `deductResources(int $villageId, array $costs)`: Removes resources from village
- `refundResources(int $villageId, array $refund)`: Adds resources back to village

### BuildingConfigManager

Handles building configuration, cost calculations, and time calculations.

**Key Methods:**

```php
public function calculateUpgradeCost(string $internalName, int $currentLevel): ?array
```
- Formula: `cost = base_cost * (cost_factor ^ currentLevel)`
- Returns: `['wood' => int, 'clay' => int, 'iron' => int]`

```php
public function calculateUpgradeTime(string $internalName, int $currentLevel, int $mainBuildingLevel): ?int
```
- Formula: `time = base_time * (BUILD_TIME_LEVEL_FACTOR ^ level) / (WORLD_SPEED * (1 + HQ_level * 0.02))`
- Returns: time in seconds

```php
public function getBuildingRequirements(string $internalName): array
```
- Returns array of prerequisite buildings and required levels

### BuildingManager

Handles building-level operations and validation.

**Key Methods:**

```php
public function canUpgradeBuilding(int $villageId, string $internalName, ?int $userId): array
```
- Comprehensive validation chain
- Returns: `['success' => bool, 'message' => string, 'code' => string]`

```php
public function getBuildingLevel(int $villageId, string $internalName): int
```
- Returns current building level

## Data Models

### building_queue Table

```sql
CREATE TABLE building_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    village_building_id INTEGER NOT NULL,
    building_type_id INTEGER NOT NULL,
    level INTEGER NOT NULL,
    starts_at DATETIME NOT NULL,
    finish_time DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'active',  -- 'active', 'pending', 'completed', 'canceled'
    is_demolition BOOLEAN DEFAULT 0,
    refund_wood INTEGER DEFAULT 0,
    refund_clay INTEGER DEFAULT 0,
    refund_iron INTEGER DEFAULT 0,
    FOREIGN KEY (village_id) REFERENCES villages(id),
    FOREIGN KEY (village_building_id) REFERENCES village_buildings(id),
    FOREIGN KEY (building_type_id) REFERENCES building_types(id)
);

CREATE INDEX idx_building_queue_village ON building_queue(village_id);
CREATE INDEX idx_building_queue_status ON building_queue(status);
CREATE INDEX idx_building_queue_finish ON building_queue(finish_time);
```

**Status Values:**
- `active`: Currently under construction (only one per village)
- `pending`: Queued, waiting for active build to complete
- `completed`: Finished (historical record)
- `canceled`: Canceled by player

### building_types Table

```sql
CREATE TABLE building_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_name VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    cost_wood_initial INTEGER NOT NULL,
    cost_clay_initial INTEGER NOT NULL,
    cost_iron_initial INTEGER NOT NULL,
    cost_factor REAL DEFAULT 1.18,
    base_build_time_initial INTEGER NOT NULL,
    max_level INTEGER DEFAULT 30,
    production_type VARCHAR(20),  -- 'wood', 'clay', 'iron', NULL
    production_initial REAL,
    production_factor REAL,
    population_cost INTEGER DEFAULT 0
);
```

### village_buildings Table

```sql
CREATE TABLE village_buildings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    building_type_id INTEGER NOT NULL,
    level INTEGER DEFAULT 0,
    FOREIGN KEY (village_id) REFERENCES villages(id),
    FOREIGN KEY (building_type_id) REFERENCES building_types(id),
    UNIQUE(village_id, building_type_id)
);
```

### building_requirements Table

```sql
CREATE TABLE building_requirements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    building_type_id INTEGER NOT NULL,
    required_building VARCHAR(50) NOT NULL,  -- internal_name of required building
    required_level INTEGER NOT NULL,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id)
);
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Immediate Resource Deduction
*For any* village and valid building upgrade, when a build is queued, the village's resources should decrease by exactly the upgrade cost amount.
**Validates: Requirements 1.1**

### Property 2: Active Status for Empty Queue
*For any* village with an empty queue, when a build is queued, the queue item status should be 'active' and finish_time should equal current_time + build_time.
**Validates: Requirements 1.2**

### Property 3: Pending Status for Non-Empty Queue
*For any* village with an active build, when another build is queued, the new item status should be 'pending' and finish_time should equal last_item_finish_time + new_build_time.
**Validates: Requirements 1.3**

### Property 4: Insufficient Resources Rejection
*For any* village with resources less than upgrade cost, attempting to queue a build should fail and leave village resources unchanged.
**Validates: Requirements 1.5**

### Property 5: Build Completion Increments Level
*For any* active build that completes, the building level in the village should increase by exactly 1.
**Validates: Requirements 2.1**

### Property 6: Completion Status Transition
*For any* active build that completes, the queue item status should change from 'active' to 'completed'.
**Validates: Requirements 2.2**

### Property 7: Pending Promotion on Completion
*For any* village with active + pending builds, when the active build completes, the first pending item should become active.
**Validates: Requirements 2.3**

### Property 8: Finish Time Recalculation on Promotion
*For any* pending item promoted to active, the finish_time should equal current_time + build_duration (not the originally calculated time).
**Validates: Requirements 2.4**

### Property 9: Cancellation Refund Calculation
*For any* queue item that is canceled, the village resources should increase by exactly 90% of the original upgrade cost.
**Validates: Requirements 3.1**

### Property 10: Active Cancellation Promotes Pending
*For any* village where an active build is canceled and pending items exist, the next pending item should immediately become active.
**Validates: Requirements 3.2**

### Property 11: Pending Cancellation Isolation
*For any* village where a pending build is canceled, all other queue items should remain unchanged (same status, same timing).
**Validates: Requirements 3.3**

### Property 12: Cancellation Status Transition
*For any* queue item that is canceled, the item status should change to 'canceled' (or be deleted).
**Validates: Requirements 3.4**

### Property 13: Cron Processor Selection
*For any* database state, the cron processor should select only active builds with finish_time <= current_time.
**Validates: Requirements 4.1**

### Property 14: Active Status Precondition
*For any* queue item, the completion handler should only process items with status 'active' (skip others).
**Validates: Requirements 4.2**

### Property 15: Idempotent Completion
*For any* completed build, processing it multiple times should only increment the building level once.
**Validates: Requirements 4.5**

### Property 16: Build Time Formula Application
*For any* building at any level with any HQ level, the calculated build time should match the formula: base_time × (BUILD_TIME_LEVEL_FACTOR ^ level) / (WORLD_SPEED × (1 + HQ_level × 0.02)).
**Validates: Requirements 5.1**

### Property 17: HQ Level Capture at Queue Time
*For any* queued build, changing the HQ level after queueing should not affect the queued build's finish time.
**Validates: Requirements 5.2**

### Property 18: World Speed Multiplier Application
*For any* world configuration with speed multipliers, build times should reflect those multipliers.
**Validates: Requirements 5.3**

### Property 19: Build Time Integer Rounding
*For any* calculated build time, the result should be an integer (no fractional seconds).
**Validates: Requirements 5.4**

### Property 20: HQ Level Recalculation on Promotion
*For any* pending build promoted to active after HQ upgrade, the finish time should use the new HQ level for calculation.
**Validates: Requirements 5.5**

### Property 21: Queue Query Returns Active and Pending Only
*For any* village queue query, the results should include only items with status 'active' or 'pending' (exclude 'completed' and 'canceled').
**Validates: Requirements 6.1**

### Property 22: Queue Response Completeness
*For any* queue item returned, the response should include building_name, target_level, status, and finish_time fields.
**Validates: Requirements 6.2**

### Property 23: Queue Ordering by Start Time
*For any* village queue, items should be ordered by starts_at ascending.
**Validates: Requirements 6.3**

### Property 24: Queue Position Calculation
*For any* pending build in a queue, its position should equal the count of items with earlier start times + 1.
**Validates: Requirements 6.5**

### Property 25: Prerequisite Validation
*For any* building with prerequisites, attempting to queue an upgrade when prerequisites are not met should fail.
**Validates: Requirements 9.1**

### Property 26: Level Jump Validation
*For any* building, attempting to queue an upgrade to a level other than current_level + 1 should fail.
**Validates: Requirements 9.3**

### Property 27: Notification Creation on Completion
*For any* build that completes, a notification should be created for the village owner.
**Validates: Requirements 10.1**

### Property 28: Notification Content Completeness
*For any* build completion notification, it should include the building name and new level.
**Validates: Requirements 10.2**

### Property 29: Multiple Build Notification Independence
*For any* sequence of builds that complete, each should generate a separate notification.
**Validates: Requirements 10.4**

## Error Handling

### Error Codes

The system uses structured error codes for client-side handling:

- `ERR_INPUT`: Invalid input (unknown building, invalid level)
- `ERR_PREREQ`: Prerequisites not met (buildings, research, special conditions)
- `ERR_CAP`: Capacity limit reached (max level, queue full)
- `ERR_QUEUE_CAP`: Queue slot limit reached (need higher HQ)
- `ERR_RES`: Insufficient resources
- `ERR_POP`: Insufficient population capacity
- `ERR_STORAGE_CAP`: Storage capacity too low for upgrade cost
- `ERR_PROTECTED`: Action blocked by protection status
- `ERR_RESEARCH`: Required research not completed
- `ERR_SERVER`: Internal server error

### Transaction Rollback

All operations use database transactions with automatic rollback on failure:

```php
try {
    $this->conn->begin_transaction();
    // ... operations ...
    $this->conn->commit();
    return ['success' => true, ...];
} catch (Exception $e) {
    $this->conn->rollback();
    return ['success' => false, 'message' => $e->getMessage(), 'error_code' => $code];
}
```

### Idempotency Guards

The completion handler includes idempotency checks:

```php
if ($item['status'] === 'completed') {
    return ['success' => true, 'message' => 'Build already completed.', 'skipped' => true];
}

if ($item['status'] !== 'active') {
    return ['success' => false, 'message' => "Build is not active."];
}

if (strtotime($item['finish_time']) > time()) {
    return ['success' => false, 'message' => 'Build not ready yet.'];
}
```

### Logging

The system maintains two log files:

1. **Audit Log** (`logs/build_queue.log`): JSONL format with all queue events
   - Events: enqueue, enqueue_failed, complete, complete_failed, cancel, cancel_failed
   - Fields: timestamp, event type, village_id, user_id, building, level, costs, etc.

2. **Metrics Log** (`logs/build_queue_metrics.log`): JSONL format for analytics
   - Metrics: enqueue, enqueue_failed, complete, complete_failed, cancel, cancel_failed
   - Fields: timestamp, metric name, village_id, user_id, building, level, hq_level, queue_count, etc.

## Testing Strategy

### Unit Testing

Unit tests cover specific scenarios and edge cases:

- Empty queue behavior
- Full queue rejection
- Max level enforcement
- Insufficient resources handling
- Prerequisite validation
- Cancellation of last item in queue
- Cron processor with no completed builds
- Error message formatting

### Property-Based Testing

Property-based tests verify universal properties across all inputs using a PHP property testing library (e.g., Eris or php-quickcheck). Each test runs a minimum of 100 iterations with randomly generated inputs.

**Test Configuration:**
```php
// Use Eris for property-based testing
composer require --dev giorgiosironi/eris

// Configure minimum iterations
$this->forAll(Generator::int(1, 30), Generator::int(0, 20))
    ->withMaxSize(100)  // Run 100 iterations minimum
    ->then(function($level, $hqLevel) {
        // Property test implementation
    });
```

**Property Test Structure:**

Each property test must:
1. Generate random valid inputs (villages, buildings, levels, resources)
2. Execute the operation
3. Assert the property holds
4. Include a comment tag referencing the design document property

**Example:**
```php
/**
 * Feature: building-queue-system, Property 1: Immediate Resource Deduction
 * For any village and valid building upgrade, when a build is queued,
 * the village's resources should decrease by exactly the upgrade cost amount.
 */
public function testImmediateResourceDeduction() {
    $this->forAll(
        Generator::associative([
            'village_id' => Generator::int(1, 1000),
            'building' => Generator::elements(['barracks', 'stable', 'smithy']),
            'wood' => Generator::int(1000, 10000),
            'clay' => Generator::int(1000, 10000),
            'iron' => Generator::int(1000, 10000)
        ])
    )->then(function($data) {
        // Setup: Create village with resources
        // Action: Queue build
        // Assert: Resources decreased by exact cost
    });
}
```

**Property Test Coverage:**

- Property 1-29: Each correctness property has a dedicated property-based test
- Generators create random but valid game states
- Tests verify properties hold across diverse inputs
- Failures report the specific input that violated the property

### Integration Testing

Integration tests verify end-to-end flows:

- Complete workflow: enqueue → cron process → completion → notification
- Cancellation workflow: enqueue → cancel → refund → promotion
- Multi-build workflow: enqueue multiple → sequential completion
- Concurrent request handling (if feasible in test environment)

### Manual Testing Checklist

- Verify UI displays queue correctly
- Test queue with different HQ levels (slot limits)
- Test with different world speed settings
- Verify notifications appear in-game
- Test with both SQLite and MySQL databases
- Verify cron job runs and completes builds
- Test protection mode blocking (if applicable)

## Performance Considerations

### Database Indexes

Critical indexes for query performance:

```sql
CREATE INDEX idx_building_queue_village ON building_queue(village_id);
CREATE INDEX idx_building_queue_status ON building_queue(status);
CREATE INDEX idx_building_queue_finish ON building_queue(finish_time);
CREATE INDEX idx_village_buildings_lookup ON village_buildings(village_id, building_type_id);
```

### Caching

BuildingConfigManager caches building configurations in memory to avoid repeated database queries:

```php
private $buildingConfigs = [];  // Cache for building configurations
private $costCache = [];         // Cache for cost calculations
private $timeCache = [];         // Cache for time calculations
```

### Query Optimization

- Use prepared statements for all queries
- Fetch only required columns (avoid SELECT *)
- Use LIMIT 1 for single-row queries
- Batch process completed builds in cron (single query to identify all)

### Transaction Scope

Keep transactions as short as possible:
- Lock village row only during resource deduction
- Release locks immediately after commit
- Avoid long-running operations inside transactions

## Configuration

### Constants

```php
// Queue limits
define('BUILDING_QUEUE_MAX_ITEMS', 10);           // Absolute max items
define('BUILDING_BASE_QUEUE_SLOTS', 1);           // Base slots (HQ 0)
define('BUILDING_HQ_MILESTONE_STEP', 5);          // Slots per HQ milestone
define('BUILDING_MAX_QUEUE_SLOTS', 3);            // Max slots regardless of HQ

// Build time calculation
define('BUILD_TIME_LEVEL_FACTOR', 1.18);          // Exponential scaling
define('MAIN_BUILDING_TIME_REDUCTION_PER_LEVEL', 0.02);  // 2% per HQ level
define('WORLD_SPEED', 1.0);                       // Global speed multiplier
define('BUILD_SPEED_MULTIPLIER', 1.0);            // Additional build speed

// Refund rate
define('BUILDING_CANCEL_REFUND_RATE', 0.9);       // 90% refund on cancel
```

### World-Specific Configuration

World settings can override global constants:

```php
$worldSettings = $worldManager->getSettings($worldId);
$worldSpeed = $worldSettings['world_speed'] ?? WORLD_SPEED;
$buildSpeed = $worldSettings['build_speed_multiplier'] ?? BUILD_SPEED_MULTIPLIER;
```

## Security Considerations

### Ownership Validation

All operations validate that the user owns the village:

```php
$stmt = $this->conn->prepare("
    SELECT v.* FROM villages v 
    WHERE v.id = ? AND v.user_id = ?
");
```

### SQL Injection Prevention

All queries use prepared statements with parameter binding:

```php
$stmt->bind_param("iiiis", $villageId, $villageBuildingId, $buildingTypeId, $nextLevel, $finishTime);
```

### Race Condition Prevention

Database transactions with row-level locking prevent concurrent modification:

```php
// SQLite: BEGIN IMMEDIATE
// MySQL: SELECT ... FOR UPDATE
```

### Resource Duplication Prevention

Immediate resource deduction prevents exploits where players queue multiple builds before resources are deducted.

### Error Message Sanitization

Error messages to players never expose:
- Database structure or query details
- Internal IDs or technical implementation details
- Stack traces or file paths

## Migration Path

### Database Migration

```sql
-- Add status column if not exists
ALTER TABLE building_queue ADD COLUMN status VARCHAR(20) DEFAULT 'active';

-- Add indexes
CREATE INDEX IF NOT EXISTS idx_building_queue_village ON building_queue(village_id);
CREATE INDEX IF NOT EXISTS idx_building_queue_status ON building_queue(status);
CREATE INDEX IF NOT EXISTS idx_building_queue_finish ON building_queue(finish_time);

-- Backfill status for existing rows
UPDATE building_queue SET status = 'active' WHERE status IS NULL AND finish_time > datetime('now');
UPDATE building_queue SET status = 'completed' WHERE status IS NULL AND finish_time <= datetime('now');
```

### Cron Job Setup

```bash
# Add to crontab
* * * * * php /path/to/jobs/process_building_queue.php >> /path/to/logs/queue_processor.log 2>&1
```

### Backward Compatibility

The system maintains backward compatibility with legacy code:

- `addBuildingToQueue()` method preserved for old endpoints
- Status column defaults to 'active' for legacy inserts
- Queue queries work with or without status column
