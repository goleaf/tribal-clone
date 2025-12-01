# Building Queue System - Quick Setup Guide

## Installation Steps

### 1. Run the Migration

Add the `status` column to your `building_queue` table:

```bash
php admin/migrations/add_queue_status.php
```

Expected output:
```
Adding status column to building_queue table...
✓ Status column added successfully
✓ Index created successfully

Migration completed successfully!
```

### 2. Test the System

Run the test script to verify everything works:

```bash
php tests/test_building_queue.php
```

This will:
- Queue a barracks upgrade (should be "active")
- Queue a stable upgrade (should be "pending")
- Show the current queue
- Try to process completed builds

### 3. Set Up the Cron Job

Add this to your crontab to process completed builds every minute:

```bash
crontab -e
```

Add this line (replace `/path/to/your/game` with your actual path):

```
* * * * * php /path/to/your/game/jobs/process_building_queue.php >> /path/to/your/game/logs/queue_processor.log 2>&1
```

To verify the cron job is running:

```bash
tail -f logs/queue_processor.log
```

### 4. Update Your Frontend (Optional)

The existing endpoints have been updated to support the queue:

- `buildings/upgrade_building.php` - Now queues builds instead of requiring empty queue
- `buildings/cancel_upgrade.php` - Handles cancellation with automatic promotion
- `buildings/get_queue.php` - NEW: Get all queue items for a village

Example AJAX call to get queue:

```javascript
fetch(`buildings/get_queue.php?village_id=${villageId}`)
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      data.queue.forEach(item => {
        console.log(`${item.building_name} level ${item.level} - ${item.status}`);
      });
    }
  });
```

## Key Changes from Old System

### Before (Single Build Only)
- ❌ Only one building could be queued
- ❌ Had to wait for completion before queuing next
- ❌ Resources deducted at completion

### After (Multiple Queue)
- ✅ Multiple buildings can be queued
- ✅ Queue automatically processes in order
- ✅ Resources deducted immediately when queued
- ✅ 90% refund on cancellation
- ✅ Automatic promotion of pending builds

## API Response Changes

### upgrade_building.php Response

```json
{
  "status": "success",
  "message": "Upgrade of Barracks to level 2 started.",
  "building_queue_item": {
    "queue_item_id": 123,
    "building_internal_name": "barracks",
    "level": 2,
    "status": "active",
    "finish_time": 1638360000
  },
  "village_info": {
    "wood": 450,
    "clay": 380,
    "iron": 290,
    "population": 15,
    "warehouse_capacity": 1000,
    "farm_capacity": 24
  }
}
```

Note the new `status` field:
- `"active"` - Build started immediately
- `"pending"` - Build queued, will start when previous completes

### get_queue.php Response

```json
{
  "success": true,
  "queue": [
    {
      "id": 123,
      "building_name": "Barracks",
      "building_internal_name": "barracks",
      "level": 2,
      "status": "active",
      "starts_at": 1638356400,
      "finish_time": 1638360000,
      "time_remaining": 3600
    },
    {
      "id": 124,
      "building_name": "Stable",
      "building_internal_name": "stable",
      "level": 1,
      "status": "pending",
      "starts_at": 1638360000,
      "finish_time": 1638367200,
      "time_remaining": 10800
    }
  ]
}
```

## Troubleshooting

### Migration fails with "column already exists"
This is fine - it means the migration was already run. The system will work correctly.

### Cron job not running
Check if cron service is running:
```bash
sudo service cron status
```

Check cron logs:
```bash
grep CRON /var/log/syslog
```

### Builds not completing
Manually run the processor:
```bash
php jobs/process_building_queue.php
```

Check the output for errors.

### Resources not refunding on cancel
Ensure the migration ran successfully. Check that the `status` column exists:
```bash
sqlite3 game.db "PRAGMA table_info(building_queue);"
```

## Next Steps

1. Update your frontend to display multiple queue items
2. Add visual indicators for "active" vs "pending" status
3. Consider adding a "queue full" indicator (e.g., max 5 items)
4. Add notifications when builds complete

For more details, see [BUILDING_QUEUE_SYSTEM.md](BUILDING_QUEUE_SYSTEM.md)
