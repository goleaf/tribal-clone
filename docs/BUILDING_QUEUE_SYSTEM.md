# Building Construction Queue System

## Overview

This implementation provides a robust building construction queue system that allows multiple buildings to be queued per village, with sequential processing and automatic resource management.

## Key Features

- **Multiple Queue Items**: Players can queue multiple building upgrades
- **Immediate Resource Deduction**: Resources are deducted when queued (not when completed)
- **Sequential Processing**: Only one building is actively constructing at a time
- **Automatic Promotion**: When an active build completes, the next pending item automatically becomes active
- **90% Refund on Cancel**: Canceling a queued build refunds 90% of resources
- **HQ Level Scaling**: Build times scale with Headquarters level

## Architecture

### Database Schema

The `building_queue` table includes a `status` column with these values:

- `pending`: Queued but not yet started
- `active`: Currently under construction
- `completed`: Finished (historical record)
- `canceled`: Canceled by player

### Core Components

#### 1. BuildingQueueManager (`lib/managers/BuildingQueueManager.php`)

Main class handling all queue operations:

- `enqueueBuild()`: Add a building to the queue
- `onBuildComplete()`: Process completed builds and promote next item
- `cancelBuild()`: Cancel a queued item with refund
- `getVillageQueue()`: Get all queue items for a village
- `processCompletedBuilds()`: Batch process all completed builds (for cron)

#### 2. Queue Processor (`jobs/process_building_queue.php`)

Cron job that runs every minute to complete finished builds:

```bash
* * * * * php /path/to/jobs/process_building_queue.php >> /path/to/logs/queue_processor.log 2>&1
```

#### 3. API Endpoints

- `buildings/upgrade_building.php`: Enqueue a new build
- `buildings/cancel_upgrade.php`: Cancel a queued build
- `buildings/get_queue.php`: Get current queue for a village

## Installation

### 1. Run Migration

Add the `status` column to the `building_queue` table:

```bash
php admin/migrations/add_queue_status.php
```

### 2. Set Up Cron Job

Add to your crontab:

```bash
crontab -e
```

Add this line:

```
* * * * * php /path/to/your/game/jobs/process_building_queue.php >> /path/to/your/game/logs/queue_processor.log 2>&1
```

### 3. Update Frontend (Optional)

Update your JavaScript to:
- Display multiple queue items
- Show status (active vs pending)
- Handle queue responses from API

## Usage Examples

### Enqueue a Build

```php
$queueManager = new BuildingQueueManager($conn, $configManager);

$result = $queueManager->enqueueBuild(
    villageId: 123,
    buildingInternalName: 'barracks',
    userId: 456
);

if ($result['success']) {
    echo "Build queued! Status: " . $result['status'];
    echo "Finish at: " . date('Y-m-d H:i:s', $result['finish_at']);
}
```

### Get Village Queue

```php
$queue = $queueManager->getVillageQueue(123);

foreach ($queue as $item) {
    echo "{$item['building_name']} level {$item['level']} - {$item['status']}\n";
}
```

### Cancel a Build

```php
$result = $queueManager->cancelBuild(
    queueItemId: 789,
    userId: 456
);

if ($result['success']) {
    echo "Refunded: " . json_encode($result['refund']);
}
```

## Flow Diagram

```
Player clicks "Upgrade"
    ↓
Resources deducted immediately
    ↓
Is there an active build?
    ↓ No                    ↓ Yes
Status: active         Status: pending
Start time: now        Start time: after last item
    ↓                       ↓
Build completes (cron job)
    ↓
Increment building level
    ↓
Mark as completed
    ↓
Promote next pending → active
```

## Key Rules

1. **Resource Deduction**: Resources are deducted when the build is queued, not when it completes
2. **One Active Build**: Only one build can be "active" per village at a time
3. **Sequential Processing**: Builds complete in the order they were queued
4. **Build Time Calculation**: Based on current HQ level at time of queueing
5. **Cancellation**: 
   - If canceling active build: next pending becomes active immediately
   - If canceling pending build: no effect on other items
   - 90% resource refund in both cases

## Transaction Safety

All operations use database transactions with row-level locking:

```php
$conn->begin_transaction();
// Lock village row
$village = $this->getVillageForUpdate($villageId, $userId);
// Perform operations
$conn->commit();
```

This prevents race conditions when multiple requests occur simultaneously.

## Idempotency

The `onBuildComplete()` method is idempotent:

```php
if ($item['status'] !== 'active' || strtotime($item['finish_time']) > time()) {
    return; // Already processed or not ready
}
```

This ensures that if the cron job runs multiple times or late, builds won't be double-processed.

## Testing

Run the queue processor manually:

```bash
php jobs/process_building_queue.php
```

Check logs:

```bash
tail -f logs/queue_processor.log
```

## Future Enhancements

- **Premium Queue Slots**: Allow multiple simultaneous builds with premium currency
- **Queue Reordering**: Let players reorder pending builds
- **Instant Complete**: Premium feature to finish builds immediately
- **Queue Notifications**: Alert players when builds complete
- **Build Speed Bonuses**: Items or research that reduce build times

## Troubleshooting

### Builds not completing

Check cron job is running:
```bash
grep CRON /var/log/syslog
```

### Resources not refunding

Check migration ran successfully:
```bash
sqlite3 game.db "PRAGMA table_info(building_queue);"
```

Should show `status` column.

### Queue stuck

Manually process:
```bash
php jobs/process_building_queue.php
```

Check logs for errors.
