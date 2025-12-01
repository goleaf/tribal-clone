# Building Upgrade System - Quick Reference

## 🚀 Quick Start

```bash
# 1. Run migration (REQUIRED FIRST)
php migrations/add_queue_status_field.php

# 2. Test the system
php tests/test_building_upgrade.php

# 3. Debug if needed
php tools/debug_building_queue.php
```

## 📋 Common Commands

```bash
# Test specific village
php tools/debug_building_queue.php 1

# Cleanup old items (dry run)
php tools/cleanup_completed_builds.php --dry-run

# Cleanup old items (live)
php tools/cleanup_completed_builds.php --days=30
```

## 🔍 Quick Diagnostics

### Check Queue Status
```sql
SELECT status, COUNT(*) 
FROM building_queue 
GROUP BY status;
```

### Find Ready Items
```sql
SELECT * FROM building_queue 
WHERE status = 'active' 
  AND finish_time <= datetime('now');
```

### Check Village Queue
```sql
SELECT bq.*, bt.name 
FROM building_queue bq
JOIN building_types bt ON bq.building_type_id = bt.id
WHERE bq.village_id = 1
ORDER BY bq.starts_at;
```

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| Buildings not upgrading | Run debug tool, check logs |
| Resources not deducted | Check BuildingQueueManager logs |
| Queue not promoting | Verify status field exists |
| Infinite reprocessing | Run migration script |

## 📊 Queue States

- **pending** → Waiting for previous build
- **active** → Currently building (1 per village)
- **completed** → Finished (kept for history)

## 📁 Important Files

### Core System
- `lib/managers/BuildingManager.php` - Validation
- `lib/managers/BuildingQueueManager.php` - Queue ops
- `lib/managers/VillageManager.php` - Processing

### Tools
- `tests/test_building_upgrade.php` - Test suite
- `tools/debug_building_queue.php` - Debugger
- `tools/cleanup_completed_builds.php` - Cleanup

### Docs
- `UPGRADE_SYSTEM_GUIDE.md` - Full guide
- `REFACTOR_SUMMARY.md` - Technical details
- `IMPLEMENTATION_COMPLETE.md` - Status

## 🔧 Configuration

```php
// config/config.php
define('BUILDING_QUEUE_MAX_ITEMS', 10);
define('BUILDING_BASE_QUEUE_SLOTS', 1);
define('BUILDING_HQ_MILESTONE_STEP', 5);
define('BUILDING_MAX_QUEUE_SLOTS', 3);
```

## 📝 Logs

- `logs/build_queue.log` - Queue events
- `logs/build_queue_metrics.log` - Metrics
- `game/logs/errors.log` - PHP errors

## ✅ Health Check

```bash
# Run all checks
php tests/test_building_upgrade.php
php tools/debug_building_queue.php

# Check for issues
grep "complete_failed" logs/build_queue.log
grep "ERROR" game/logs/errors.log
```

## 🎯 Success Indicators

- ✅ No `complete_failed` in logs
- ✅ Items transition: pending → active → completed
- ✅ Only 1 active item per village
- ✅ Resources deducted immediately
- ✅ Building levels update correctly

## 🆘 Emergency Fixes

### Fix Stuck Active Items
```sql
UPDATE building_queue 
SET status = 'completed' 
WHERE status = 'active' 
  AND finish_time < datetime('now', '-1 hour');
```

### Fix NULL Status
```sql
UPDATE building_queue 
SET status = 'active' 
WHERE status IS NULL;
```

### Clear Old Completed
```sql
DELETE FROM building_queue 
WHERE status = 'completed' 
  AND finish_time < datetime('now', '-30 days');
```

## 📞 Support

1. Check `UPGRADE_SYSTEM_GUIDE.md`
2. Run debug tool
3. Review logs
4. Check this reference

---

**Version:** 2.0 | **Status:** Production Ready ✅
