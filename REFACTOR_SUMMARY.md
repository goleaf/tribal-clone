# Building Upgrade System - Refactor Summary

## Issues Fixed

### 1. WorldManager Not Found Error
**Problem:** `CatchupManager` tried to use `WorldManager` without loading it first.

**Solution:** Added explicit require statement with class existence check:
```php
if (!class_exists('WorldManager')) {
    require_once __DIR__ . '/WorldManager.php';
}
```

### 2. Private Method Called from Global Scope
**Problem:** `BuildingManager::getActivePendingQueueCount()` was private but called from `game/game.php`.

**Solution:** Changed method visibility from `private` to `public`.

### 3. Building Upgrades Not Working
**Root Cause:** The queue processing system had multiple critical bugs:

#### Bug A: Missing Status Check
The old `VillageManager::processBuildingQueue()` query didn't filter by status:
```sql
-- OLD (BROKEN):
WHERE bq.village_id = ? AND bq.finish_time <= NOW()

-- NEW (FIXED):
WHERE bq.village_id = ? 
  AND bq.status = 'active' 
  AND bq.finish_time <= NOW()
```

This caused completed items to be reprocessed infinitely.

#### Bug B: Dual Processing Systems
Two different systems existed:
- **Old:** `VillageManager::processBuildingQueue()` - deleted queue items
- **New:** `BuildingQueueManager::onBuildComplete()` - marked as completed

They weren't integrated, causing conflicts.

#### Bug C: Level Update Method
The queue manager incremented levels (`level = level + 1`) instead of setting them to the target level, causing issues when items were reprocessed.

## Complete Solution

### 1. Unified Queue Processing
`VillageManager::processBuildingQueue()` now:
- Queries only `status = 'active'` items
- Delegates to `BuildingQueueManager::onBuildComplete()`
- Maintains legacy demolition support

### 2. Idempotent Completion
`BuildingQueueManager::onBuildComplete()` now:
- Checks if already completed (returns success, skips processing)
- Validates status is 'active'
- Sets building level to target (not increment)
- Marks as 'completed' (doesn't delete)
- Promotes next pending item

### 3. Proper Queue Flow

```
User Action → Validation → Enqueue → Processing → Completion
     ↓             ↓           ↓          ↓           ↓
upgrade_     canUpgrade  enqueueBuild  process   onBuildComplete
building.php  Building                 Completed
                                      Tasks
```

**States:**
- `pending` - Waiting in queue
- `active` - Currently building  
- `completed` - Finished (kept for history)

## Files Modified

1. **lib/managers/CatchupManager.php**
   - Added WorldManager require statement

2. **lib/managers/BuildingManager.php**
   - Changed `getActivePendingQueueCount()` to public

3. **lib/managers/VillageManager.php**
   - Refactored `processBuildingQueue()` to use BuildingQueueManager
   - Added status check in SQL query
   - Integrated with new queue system

4. **lib/managers/BuildingQueueManager.php**
   - Enhanced `onBuildComplete()` with idempotent guards
   - Fixed level update to set target level
   - Improved error handling

## How to Test

1. **Start a building upgrade:**
   ```
   - Go to game.php
   - Click on a building
   - Click upgrade
   - Verify resources are deducted immediately
   ```

2. **Wait for completion:**
   ```
   - Refresh the page after finish_time
   - Verify building level increased
   - Check that success message appears
   - Confirm item is marked 'completed' in database
   ```

3. **Queue multiple buildings:**
   ```
   - Queue 2-3 buildings
   - Verify first is 'active', rest are 'pending'
   - Wait for first to complete
   - Verify second promotes to 'active'
   ```

4. **Check logs:**
   ```
   - logs/build_queue.log - Queue events
   - logs/build_queue_metrics.log - Metrics
   - game/logs/errors.log - Any errors
   ```

## Database Schema

The `building_queue` table should have:
```sql
- id (primary key)
- village_id
- village_building_id
- building_type_id
- level (target level)
- starts_at (timestamp)
- finish_time (timestamp)
- status ('pending', 'active', 'completed')
- is_demolition (boolean, optional)
- refund_wood, refund_clay, refund_iron (for demolitions)
```

## Success Criteria

✅ Buildings upgrade successfully
✅ Resources deducted immediately
✅ Queue processes correctly
✅ No infinite reprocessing
✅ Multiple items queue properly
✅ Completed items stay completed
✅ Next item promotes automatically

## Monitoring

Watch these logs for issues:
- `logs/build_queue.log` - Queue operations
- `logs/build_queue_metrics.log` - Performance metrics
- `game/logs/errors.log` - PHP errors

Look for:
- `complete_failed` events
- `already_completed` skips (should be rare)
- Any SQL errors
- Resource deduction issues
