# Building Upgrade System Refactor - COMPLETED

## Problem Analysis

The building upgrade system had several critical issues:

1. **Dual Queue Systems**: Two different queue processing mechanisms existed
   - Old system in `VillageManager::processBuildingQueue()` 
   - New system in `BuildingQueueManager`
   - They weren't properly integrated

2. **Status Field Missing**: The old queue processor didn't check the `status` field
   - Queried `WHERE finish_time <= NOW()` without checking `status = 'active'`
   - This caused completed items to be reprocessed infinitely

3. **Resource Deduction**: Resources are deducted immediately in `BuildingQueueManager` but the old system expected them at completion time

4. **Queue Promotion**: The new system handles queue promotion, but the old system deleted items directly

## Solution Implemented

### 1. Updated VillageManager::processBuildingQueue()

**Changes:**
- Now uses `BuildingQueueManager` for processing completions
- Added `status = 'active'` check in the SQL query
- Delegates to `BuildingQueueManager::onBuildComplete()` for regular upgrades
- Maintains legacy support for demolitions

**Key improvements:**
```php
// Old query (BROKEN):
WHERE bq.village_id = ? AND bq.finish_time <= NOW()

// New query (FIXED):
WHERE bq.village_id = ? 
  AND bq.status = 'active' 
  AND bq.finish_time <= NOW()
```

### 2. Enhanced BuildingQueueManager::onBuildComplete()

**Changes:**
- Added idempotent guards to prevent double-processing
- Checks if item is already completed and skips gracefully
- Validates status is 'active' before processing
- Sets building level to target level (not increment)
- Improved error handling and logging

**Key improvements:**
```php
// Idempotent check
if ($currentStatus === 'completed') {
    return ['success' => true, 'message' => 'Build already completed.', 'skipped' => true];
}

// Status validation
if ($currentStatus !== 'active') {
    return ['success' => false, 'message' => "Build is not active."];
}

// Set to target level (not increment)
UPDATE village_buildings SET level = ? WHERE id = ?
```

### 3. Fixed BuildingManager::canUpgradeBuilding()

**Changes:**
- Made `getActivePendingQueueCount()` public (was private)
- This method is called from `game/game.php` so it needs to be accessible

## How It Works Now

### Upgrade Flow:

1. **User clicks upgrade** → `buildings/upgrade_building.php`
2. **Validation** → `BuildingManager::canUpgradeBuilding()`
   - Checks resources, requirements, queue capacity
3. **Enqueue** → `BuildingQueueManager::enqueueBuild()`
   - Deducts resources immediately
   - Creates queue item with `status = 'active'` or `'pending'`
   - Calculates start/finish times
4. **Processing** → `VillageManager::processCompletedTasksForVillage()`
   - Called on page load in `game/game.php`
   - Finds active items where `finish_time <= NOW()`
   - Delegates to `BuildingQueueManager::onBuildComplete()`
5. **Completion** → `BuildingQueueManager::onBuildComplete()`
   - Updates building level
   - Marks item as `status = 'completed'`
   - Promotes next pending item to active
   - Rebalances queue timing

### Queue States:

- **pending**: Waiting for previous build to complete
- **active**: Currently building
- **completed**: Finished (kept for history)

## Testing Checklist

- [x] Upgrade a building successfully
- [ ] Verify resources are deducted immediately
- [ ] Check building level updates after completion
- [ ] Confirm queue promotion works (queue multiple buildings)
- [ ] Test that completed items aren't reprocessed
- [ ] Verify demolitions still work
- [ ] Check error handling for invalid upgrades

## Files Modified

1. `lib/managers/VillageManager.php` - Updated `processBuildingQueue()`
2. `lib/managers/BuildingQueueManager.php` - Enhanced `onBuildComplete()`
3. `lib/managers/BuildingManager.php` - Made `getActivePendingQueueCount()` public
4. `lib/managers/CatchupManager.php` - Added WorldManager require statement

## Next Steps

1. Test the upgrade flow end-to-end
2. Monitor logs for any errors
3. Consider adding a cleanup job to archive old completed items
4. Add UI feedback for queue status
