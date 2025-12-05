# Requirements Document

## Introduction

The Building Queue System enables players to queue multiple building construction and upgrade tasks in their villages. The system manages sequential processing of queued items, immediate resource deduction, automatic promotion of pending builds, and cancellation with partial refunds. This system is foundational to the game's progression mechanics and must handle concurrent requests safely while maintaining data integrity across SQLite and MySQL databases.

## Glossary

- **Building Queue**: An ordered list of construction/upgrade tasks for a village
- **Queue Item**: A single building construction or upgrade task in the queue
- **Active Build**: The currently executing construction task (only one per village)
- **Pending Build**: A queued construction task waiting for the active build to complete
- **Queue Status**: The state of a queue item (pending, active, completed, canceled)
- **HQ**: Headquarters (main building) that reduces construction time
- **Cron Processor**: Background job that completes finished builds every minute
- **Resource Deduction**: Immediate removal of resources when a build is queued
- **Build Promotion**: Automatic transition of a pending build to active status
- **Refund**: Return of 90% of resources when a build is canceled
- **World Speed**: Global multiplier affecting construction times
- **Build Time Factor**: Exponential scaling factor for construction duration

## Requirements

### Requirement 1

**User Story:** As a player, I want to queue multiple building upgrades in my village, so that I can plan my development strategy without waiting for each build to complete before queuing the next.

#### Acceptance Criteria

1. WHEN a player queues a building upgrade THEN the system SHALL deduct the required resources immediately from the village
2. WHEN a player queues a building upgrade and no build is active THEN the system SHALL set the queue item status to active and calculate finish time based on current time
3. WHEN a player queues a building upgrade and an active build exists THEN the system SHALL set the queue item status to pending and calculate finish time based on the last queued item's finish time
4. WHEN the building queue reaches the configured maximum items THEN the system SHALL prevent additional builds from being queued
5. WHERE the village has insufficient resources THEN the system SHALL reject the queue request and maintain the current state

### Requirement 2

**User Story:** As a player, I want my queued buildings to automatically start construction when the previous build completes, so that I don't need to manually monitor and start each build.

#### Acceptance Criteria

1. WHEN an active build completes THEN the system SHALL increment the building level in the village
2. WHEN an active build completes THEN the system SHALL mark the queue item status as completed
3. WHEN an active build completes and pending items exist THEN the system SHALL promote the next pending item to active status
4. WHEN promoting a pending item to active THEN the system SHALL recalculate the finish time based on current time
5. WHEN no pending items exist after completion THEN the system SHALL leave the queue empty with no active builds

### Requirement 3

**User Story:** As a player, I want to cancel queued building upgrades, so that I can adapt my strategy and recover most of my invested resources.

#### Acceptance Criteria

1. WHEN a player cancels a queue item THEN the system SHALL refund 90% of the original resource costs
2. WHEN a player cancels an active build THEN the system SHALL promote the next pending item to active status immediately
3. WHEN a player cancels a pending build THEN the system SHALL not affect other queue items
4. WHEN a player cancels a queue item THEN the system SHALL mark the item status as canceled
5. WHEN a player attempts to cancel a completed or already canceled item THEN the system SHALL reject the request

### Requirement 4

**User Story:** As a system administrator, I want a cron processor to automatically complete finished builds, so that construction progresses without manual intervention.

#### Acceptance Criteria

1. WHEN the cron processor runs THEN the system SHALL identify all active builds with finish times in the past
2. WHEN processing a completed build THEN the system SHALL verify the item status is active before processing
3. WHEN processing a completed build THEN the system SHALL use database transactions with row-level locking
4. WHEN the cron processor encounters an error THEN the system SHALL log the error and continue processing other items
5. WHEN the cron processor runs multiple times for the same completed build THEN the system SHALL process it only once (idempotency)

### Requirement 5

**User Story:** As a player, I want building construction times to scale based on building level and my Headquarters level, so that progression feels balanced and strategic upgrades matter.

#### Acceptance Criteria

1. WHEN calculating build time THEN the system SHALL apply the formula: base_time × (BUILD_TIME_LEVEL_FACTOR ^ level) / (WORLD_SPEED × (1 + HQ_level × 0.02))
2. WHEN calculating build time THEN the system SHALL use the current HQ level at the time of queueing
3. WHEN calculating build time THEN the system SHALL apply world-specific speed multipliers if configured
4. WHEN calculating build time THEN the system SHALL round the result to the nearest second
5. WHEN a pending build is promoted to active THEN the system SHALL recalculate build time using the current HQ level

### Requirement 6

**User Story:** As a player, I want to view my village's building queue, so that I can see what is currently building and what is pending.

#### Acceptance Criteria

1. WHEN a player requests the queue THEN the system SHALL return all active and pending items for the village
2. WHEN displaying queue items THEN the system SHALL show building name, target level, status, and finish time
3. WHEN displaying queue items THEN the system SHALL order them by start time ascending
4. WHEN displaying an active build THEN the system SHALL indicate it is currently under construction
5. WHEN displaying a pending build THEN the system SHALL show its position in the queue

### Requirement 7

**User Story:** As a developer, I want the queue system to prevent race conditions, so that concurrent requests don't corrupt the queue state or duplicate resource deductions.

#### Acceptance Criteria

1. WHEN enqueueing a build THEN the system SHALL use database transactions with row-level locking on the village
2. WHEN canceling a build THEN the system SHALL use database transactions with row-level locking on the village
3. WHEN processing completed builds THEN the system SHALL use database transactions with row-level locking on queue items
4. WHEN a transaction fails THEN the system SHALL roll back all changes and return an error
5. WHEN multiple requests target the same village simultaneously THEN the system SHALL serialize access through database locks

### Requirement 8

**User Story:** As a system administrator, I want the queue system to work with both SQLite and MySQL databases, so that the game can run in different hosting environments.

#### Acceptance Criteria

1. WHEN using SQLite THEN the system SHALL use BEGIN IMMEDIATE for transactions to prevent lock escalation
2. WHEN using MySQL THEN the system SHALL use SELECT FOR UPDATE for row-level locking
3. WHEN detecting the database type THEN the system SHALL adapt locking strategies accordingly
4. WHEN executing queries THEN the system SHALL use prepared statements to prevent SQL injection
5. WHEN handling database errors THEN the system SHALL log errors without exposing sensitive information to players

### Requirement 9

**User Story:** As a player, I want building requirements to be validated before queueing, so that I cannot queue buildings that don't meet prerequisites.

#### Acceptance Criteria

1. WHEN queueing a building upgrade THEN the system SHALL verify all prerequisite buildings exist at required levels
2. WHEN queueing a building upgrade THEN the system SHALL verify the building exists in the village
3. WHEN queueing a building upgrade THEN the system SHALL verify the target level is exactly one higher than current level
4. WHEN queueing a building upgrade THEN the system SHALL verify the building has not reached maximum level
5. WHEN prerequisite validation fails THEN the system SHALL reject the queue request with a descriptive error message

### Requirement 10

**User Story:** As a player, I want to receive notifications when my buildings complete, so that I know when to queue new construction or train units.

#### Acceptance Criteria

1. WHEN a build completes THEN the system SHALL create a notification for the village owner
2. WHEN a build completes THEN the notification SHALL include the building name and new level
3. WHEN a build completes THEN the notification SHALL link to the village overview
4. WHEN multiple builds complete in quick succession THEN the system SHALL create separate notifications for each
5. WHEN a player has notifications disabled THEN the system SHALL not create notifications for completed builds
