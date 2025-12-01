# Building Queue Implementation Summary

## What Was Implemented

A complete building construction queue system based on your TypeScript pseudo-code, adapted for PHP with MySQLi/SQLite compatibility.

## Files Created

### Core System
1. **lib/managers/BuildingQueueManager.php** - Main queue management class
   - `enqueueBuild()` - Add buildings to queue with immediate resource deduction
   - `onBuildComplete()` - Process completions and promote next item
   - `cancelBuild()` - Cancel with 90% refund and automatic promotion
   - `getVillageQueue()` - Get all queue items for display
   - `processCompletedBuilds()` - Batch process for cron job

### Database
2. **admin/migrations/add_queue_status.php** - Adds `status` column to `building_queue` table
   - Adds: `status TEXT NOT NULL DEFAULT 'active'`
   - Creates index on `(village_id, status, starts_at)`

### Background Processing
3. **jobs/process_building_queue.php** - Cron job to complete builds
   - Runs every minute
   - Processes all completed builds
   - Logs to `logs/queue_processor.log`

### API Endpoints
4. **buildings/get_queue.php** - NEW endpoint to fetch village queue
5. **buildings/upgrade_building.php** - UPDATED to use queue manager
6. **buildings/cancel_upgrade.php** - UPDATED to use queue manager

### Documentation
7. **docs/BUILDING_QUEUE_SYSTEM.md** - Complete technical documentation
8. **docs/QUEUE_SETUP_GUIDE.md** - Quick setup instructions
9. **docs/QUEUE_IMPLEMENTATION_SUMMARY.md** - This file

### Testing
10. **tests/test_building_queue.php** - Test script to verify functionality

## Key Features Implemented

### ✅ Multiple Queue Items
- Players can queue multiple building upgrades
- No limit on queue size (can be added later)

### ✅ Sequential Processing
- Only one build is "active" at a time
- Others are "pending" and wait their turn
- Automatic promotion when active completes

### ✅ Immediate Resource Deduction
- Resources deducted when queued (not when completed)
- Prevents resource exploitation
- Matches your pseudo-code specification

### ✅ Build Time Calculation
- Based on Headquarters level at time of queueing
- Uses existing `BuildingConfigManager::calculateUpgradeTime()`
- Formula: `time * 0.94^(hqLevel-1)`

### ✅ Cancellation with Refund
- 90% resource refund
- If canceling active: next pending becomes active immediately
- If canceling pending: no effect on other items

### ✅ Transaction Safety
- All operations use database transactions
- Row-level locking prevents race conditions
- Rollback on any error

### ✅ Idempotency
- `onBuildComplete()` can be called multiple times safely
- Guards against double-processing
- Safe for cron job retries

## Database Schema Changes

```sql
ALTER TABLE building_queue 
ADD COLUMN status TEXT NOT NULL DEFAULT 'active';

CREATE INDEX idx_building_queue_status 
ON building_queue(village_id, status, starts_at);
```

### Status Values
- `active` - Currently building (only one per village)
- `pending` - Queued, waiting for active to complete
- `completed` - Finished (historical record)
- `canceled` - Canceled by player

## Flow Diagram

```
Player clicks "Upgrade Building"
         ↓
Check resources & requirements
         ↓
Deduct resources immediately
         ↓
Is there an active build?
    ↓ No              ↓ Yes
Status: active    Status: pending
Start: now        Start: after last item
         ↓                ↓
    [Queue Item Created]
         ↓
Cron job runs every minute
         ↓
Active build finished?
         ↓ Yes
Increment building level
         ↓
Mark as completed
         ↓
Get next pending item
         ↓
Promote to active
         ↓
Update start/finish times
```

## Comparison to Pseudo-Code

Your TypeScript pseudo-code → PHP implementation:

| Pseudo-Code | PHP Implementation |
|-------------|-------------------|
| `type Building` | `string $buildingInternalName` |
| `interface QueueItem` | Database row in `building_queue` |
| `async function enqueueBuild()` | `BuildingQueueManager::enqueueBuild()` |
| `db.transaction()` | `$conn->begin_transaction()` |
| `getVillageForUpdate()` | `SELECT ... WHERE id = ? AND user_id = ?` |
| `getBuildCost()` | `BuildingConfigManager::calculateUpgradeCost()` |
| `getBuildTime()` | `BuildingConfigManager::calculateUpgradeTime()` |
| `hasResources()` | `hasResources()` private method |
| `subtractResources()` | `deductResources()` private method |
| `getBuildQueue()` | `getBuildQueue()` private method |
| `scheduleBuildCompletion()` | Cron job: `process_building_queue.php` |
| `onBuildComplete()` | `BuildingQueueManager::onBuildComplete()` |
| `incrementBuildingLevel()` | `UPDATE village_buildings SET level = level + 1` |
| `getNextPending()` | `SELECT ... WHERE status = 'pending' ORDER BY starts_at` |

## Installation

```bash
# 1. Run migration
php admin/migrations/add_queue_status.php

# 2. Test the system
php tests/test_building_queue.php

# 3. Set up cron job
crontab -e
# Add: * * * * * php /path/to/jobs/process_building_queue.php >> /path/to/logs/queue_processor.log 2>&1
```

## Usage Example

```php
// Enqueue a build
$queueManager = new BuildingQueueManager($conn, $configManager);
$result = $queueManager->enqueueBuild(
    villageId: 123,
    buildingInternalName: 'barracks',
    userId: 456
);

if ($result['success']) {
    echo "Queued! Status: {$result['status']}"; // "active" or "pending"
}

// Get village queue
$queue = $queueManager->getVillageQueue(123);
foreach ($queue as $item) {
    echo "{$item['building_type_id']} level {$item['level']} - {$item['status']}\n";
}

// Cancel a build
$result = $queueManager->cancelBuild(queueItemId: 789, userId: 456);
if ($result['success']) {
    echo "Refunded: {$result['refund']['wood']} wood";
}
```

## Testing

Run the test script:
```bash
php tests/test_building_queue.php
```

Expected output:
```
=== Building Queue System Test ===

✓ Using user ID: 1
✓ Using village ID: 1

Test 1: Get current queue
Current queue items: 0

Test 2: Enqueue barracks upgrade
✓ Build queued successfully!
  Queue Item ID: 1
  Status: active
  Level: 2
  Finish at: 2025-12-01 15:30:00

Test 3: Enqueue stable upgrade (should be pending)
✓ Build queued successfully!
  Queue Item ID: 2
  Status: pending
  Level: 1
  Finish at: 2025-12-01 17:30:00

Test 4: Get updated queue
Current queue items: 2
  - Building type ID 2, Level 2, Status: active
  - Building type ID 3, Level 1, Status: pending

Test 5: Process completed builds
Processed 0 builds

=== All tests completed ===
```

## Future Enhancements

Potential additions (not implemented):

1. **Premium Queue Slots** - Allow multiple simultaneous builds with premium currency
2. **Queue Reordering** - Let players reorder pending builds
3. **Instant Complete** - Premium feature to finish immediately
4. **Queue Limit** - Max 5 items per village
5. **Build Speed Bonuses** - Items/research that reduce build times
6. **Notifications** - Alert when builds complete
7. **Queue Visualization** - Timeline showing all builds

## Notes

- The system is fully backward compatible
- Existing builds in the queue will default to `status = 'active'`
- No frontend changes are required (but recommended for better UX)
- The cron job is essential for builds to complete
- All operations are logged to `logs/queue_processor.log`

## Support

For issues or questions:
1. Check `logs/queue_processor.log` for cron job errors
2. Check `logs/database.log` for database errors
3. Run `php tests/test_building_queue.php` to verify setup
4. See [QUEUE_SETUP_GUIDE.md](QUEUE_SETUP_GUIDE.md) for troubleshooting
