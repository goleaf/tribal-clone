# Building Upgrade System - Complete Guide

## Overview

The building upgrade system has been completely refactored to fix critical bugs and improve reliability. This guide covers everything you need to know about the new system.

## What Was Fixed

1. **Infinite Reprocessing Bug** - Completed buildings were being processed repeatedly
2. **Missing Status Checks** - Queue items weren't properly filtered by status
3. **Dual Processing Systems** - Two conflicting systems have been unified
4. **Private Method Access** - Fixed method visibility issues

## How It Works

### Queue States

Buildings in the queue can have three states:

- **`pending`** - Waiting for previous build to complete
- **`active`** - Currently building (only 1 per village)
- **`completed`** - Finished (kept for history)

### Upgrade Flow

```
1. User clicks upgrade button
   ↓
2. System validates (resources, requirements, queue space)
   ↓
3. Resources deducted immediately
   ↓
4. Queue item created (active or pending)
   ↓
5. On page load, completed items are processed
   ↓
6. Building level updated, item marked completed
   ↓
7. Next pending item promoted to active
```

## Files Modified

### Core System Files

1. **lib/managers/BuildingManager.php**
   - Made `getActivePendingQueueCount()` public
   - Handles upgrade validation

2. **lib/managers/BuildingQueueManager.php**
   - Manages queue operations (enqueue, complete, cancel)
   - Handles queue promotion and rebalancing
   - Idempotent completion processing

3. **lib/managers/VillageManager.php**
   - Processes completed tasks
   - Delegates to BuildingQueueManager
   - Integrates with achievement system

4. **lib/managers/CatchupManager.php**
   - Fixed WorldManager loading issue

### Endpoint Files

- **buildings/upgrade_building.php** - Handles upgrade requests
- **game/game.php** - Processes completed tasks on page load

## Tools & Scripts

### 1. Test Script
```bash
php tests/test_building_upgrade.php
```
Tests the entire upgrade system without making changes.

### 2. Migration Script
```bash
php migrations/add_queue_status_field.php
```
Ensures database has the correct schema with status field.

### 3. Debug Tool
```bash
# Debug all villages
php tools/debug_building_queue.php

# Debug specific village
php tools/debug_building_queue.php 1
```
Shows detailed queue information and detects issues.

### 4. Cleanup Tool
```bash
# Dry run (no changes)
php tools/cleanup_completed_builds.php --dry-run

# Delete completed items older than 30 days
php tools/cleanup_completed_builds.php --days=30

# Delete completed items older than 7 days
php tools/cleanup_completed_builds.php --days=7
```
Removes old completed items to keep database clean.

## Testing Checklist

### Basic Functionality
- [ ] Start a building upgrade
- [ ] Verify resources are deducted immediately
- [ ] Check building appears in queue
- [ ] Wait for completion (or manipulate finish_time in DB)
- [ ] Refresh page and verify building level increased
- [ ] Confirm success message appears

### Queue Management
- [ ] Queue multiple buildings (2-3)
- [ ] Verify first is 'active', rest are 'pending'
- [ ] Complete first build
- [ ] Verify second promotes to 'active'
- [ ] Check timing is recalculated correctly

### Edge Cases
- [ ] Try to upgrade when queue is full
- [ ] Try to upgrade without enough resources
- [ ] Try to upgrade without meeting requirements
- [ ] Cancel a queued build
- [ ] Verify refund is correct (90% of cost)

### Idempotency
- [ ] Complete a build
- [ ] Refresh page multiple times
- [ ] Verify build is not processed again
- [ ] Check status stays 'completed'

## Database Schema

The `building_queue` table should have these columns:

```sql
CREATE TABLE building_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    village_building_id INTEGER NOT NULL,
    building_type_id INTEGER NOT NULL,
    level INTEGER NOT NULL,
    starts_at TEXT NOT NULL,
    finish_time TEXT NOT NULL,
    status TEXT DEFAULT 'active',
    is_demolition INTEGER DEFAULT 0,
    refund_wood INTEGER DEFAULT 0,
    refund_clay INTEGER DEFAULT 0,
    refund_iron INTEGER DEFAULT 0,
    FOREIGN KEY (village_id) REFERENCES villages(id),
    FOREIGN KEY (village_building_id) REFERENCES village_buildings(id),
    FOREIGN KEY (building_type_id) REFERENCES building_types(id)
);
```

## Monitoring & Logs

### Log Files

1. **logs/build_queue.log** - Detailed queue events
   - Enqueue operations
   - Completion events
   - Cancellations
   - Errors

2. **logs/build_queue_metrics.log** - Performance metrics
   - Queue counts
   - Build times
   - Success/failure rates

3. **game/logs/errors.log** - PHP errors
   - SQL errors
   - Exception traces

### What to Watch For

**Good Signs:**
- `complete` events in logs
- `enqueue` events with correct timing
- Status transitions: pending → active → completed

**Warning Signs:**
- `complete_failed` events
- Multiple `already_completed` skips for same item
- SQL errors in error log
- Items stuck in 'active' status

**Critical Issues:**
- Infinite reprocessing (same item completed repeatedly)
- Resources not deducted
- Building levels not updating
- Queue not promoting

## Troubleshooting

### Issue: Buildings not upgrading

**Check:**
1. Run debug tool: `php tools/debug_building_queue.php [village_id]`
2. Look for items with status='active' and finish_time in the past
3. Check error logs for SQL errors
4. Verify `processCompletedTasksForVillage()` is being called

**Fix:**
- Manually process: Refresh game.php page
- Check database connection
- Verify BuildingQueueManager is loaded

### Issue: Resources not deducted

**Check:**
1. Verify `BuildingQueueManager::enqueueBuild()` is being called
2. Check if transaction is rolling back
3. Look for errors in logs

**Fix:**
- Check resource validation logic
- Verify database transaction handling

### Issue: Queue not promoting

**Check:**
1. Run: `php tools/debug_building_queue.php [village_id]`
2. Look for multiple 'active' items in same village
3. Check if `rebalanceQueue()` is being called

**Fix:**
- Manually mark old active items as completed
- Run migration script to fix status field

### Issue: Completed items reprocessing

**Check:**
1. Verify status field exists: `PRAGMA table_info(building_queue)`
2. Check if query includes `status = 'active'` filter
3. Look for NULL status values

**Fix:**
- Run migration: `php migrations/add_queue_status_field.php`
- Update VillageManager to use new query

## Configuration

### Queue Settings

In `config/config.php`:

```php
// Maximum items in queue (hard limit)
define('BUILDING_QUEUE_MAX_ITEMS', 10);

// Base queue slots (without HQ bonuses)
define('BUILDING_BASE_QUEUE_SLOTS', 1);

// HQ level milestone for additional slot
define('BUILDING_HQ_MILESTONE_STEP', 5);

// Maximum queue slots (with HQ bonuses)
define('BUILDING_MAX_QUEUE_SLOTS', 3);
```

### Example Slot Calculation

- HQ Level 0-4: 1 slot
- HQ Level 5-9: 2 slots
- HQ Level 10+: 3 slots

## API Reference

### BuildingManager

```php
// Check if upgrade is possible
$result = $buildingManager->canUpgradeBuilding($villageId, $buildingName, $userId);
// Returns: ['success' => bool, 'message' => string, 'code' => string]

// Get current building level
$level = $buildingManager->getBuildingLevel($villageId, $buildingName);

// Get queue usage
$usage = $buildingManager->getQueueUsage($villageId);
// Returns: ['count' => int, 'limit' => int, 'is_full' => bool]
```

### BuildingQueueManager

```php
// Enqueue a build
$result = $queueManager->enqueueBuild($villageId, $buildingName, $userId);
// Returns: ['success' => bool, 'queue_item_id' => int, 'status' => string, ...]

// Process completion
$result = $queueManager->onBuildComplete($queueItemId);
// Returns: ['success' => bool, 'next_item_id' => int|null]

// Cancel a build
$result = $queueManager->cancelBuild($queueItemId, $userId);
// Returns: ['success' => bool, 'refund' => array]
```

### VillageManager

```php
// Process all completed tasks
$completed = $villageManager->processCompletedTasksForVillage($villageId);
// Returns: array of completed items
```

## Best Practices

1. **Always check `canUpgradeBuilding()` before enqueueing**
2. **Process completed tasks on every page load** (already done in game.php)
3. **Use BuildingQueueManager for all queue operations**
4. **Monitor logs regularly** for errors
5. **Run cleanup script monthly** to remove old completed items
6. **Use debug tool** when investigating issues

## Support

If you encounter issues:

1. Run the debug tool
2. Check the logs
3. Review this guide
4. Check REFACTOR_SUMMARY.md for technical details

## Changelog

### v2.0 (Current)
- ✅ Fixed infinite reprocessing bug
- ✅ Added status field to queue
- ✅ Unified dual processing systems
- ✅ Implemented idempotent completion
- ✅ Added comprehensive logging
- ✅ Created debugging tools

### v1.0 (Legacy)
- ❌ Had reprocessing issues
- ❌ Missing status checks
- ❌ Conflicting systems
