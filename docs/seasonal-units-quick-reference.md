# Seasonal Units Quick Reference

## Quick Start

### 1. Add a Seasonal Unit

```sql
INSERT INTO seasonal_units 
(unit_internal_name, event_name, start_timestamp, end_timestamp, is_active, per_account_cap)
VALUES 
('winter_knight', 'Winter Festival 2025', 1735689600, 1738368000, 0, 50);
```

### 2. Schedule the Job

```bash
# Add to crontab (runs every hour)
0 * * * * php /path/to/jobs/process_seasonal_units.php >> /path/to/logs/seasonal_units.log 2>&1
```

### 3. Monitor Logs

```bash
tail -f logs/seasonal_units.log
```

## Common Tasks

### Check Seasonal Unit Status

```sql
SELECT 
    unit_internal_name,
    event_name,
    is_active,
    datetime(start_timestamp, 'unixepoch') as start_time,
    datetime(end_timestamp, 'unixepoch') as end_time
FROM seasonal_units
ORDER BY start_timestamp DESC;
```

### Manually Activate a Unit

```sql
UPDATE seasonal_units 
SET is_active = 1 
WHERE unit_internal_name = 'winter_knight';
```

### Manually Sunset a Unit

```sql
UPDATE seasonal_units 
SET is_active = 0 
WHERE unit_internal_name = 'winter_knight';
```

### Count Existing Seasonal Units

```sql
SELECT 
    ut.internal_name,
    COUNT(DISTINCT vu.village_id) as villages,
    SUM(vu.count) as total_units
FROM village_units vu
JOIN unit_types ut ON vu.unit_type_id = ut.id
JOIN seasonal_units su ON ut.internal_name = su.unit_internal_name
GROUP BY ut.internal_name;
```

### Check Queued Seasonal Units

```sql
SELECT 
    ut.internal_name,
    COUNT(*) as queue_entries,
    SUM(uq.count - uq.count_finished) as pending_units
FROM unit_queue uq
JOIN unit_types ut ON uq.unit_type_id = ut.id
JOIN seasonal_units su ON ut.internal_name = su.unit_internal_name
WHERE uq.count_finished < uq.count
GROUP BY ut.internal_name;
```

## Lifecycle States

| State | is_active | Time Condition | Can Train? |
|-------|-----------|----------------|------------|
| Future | 0 | now < start | ❌ No |
| Active | 1 | start ≤ now ≤ end | ✅ Yes |
| Expired | 0 | now > end | ❌ No |

## Error Codes

| Code | Meaning | When |
|------|---------|------|
| ERR_SEASONAL_EXPIRED | Unit not available | Outside time window or is_active = 0 |
| ERR_CAP | Cap exceeded | Reached per_account_cap |
| ERR_PREREQ | Prerequisites not met | Missing building/research |

## Timestamps

Convert dates to Unix timestamps:

```bash
# Linux/Mac
date -j -f "%Y-%m-%d %H:%M:%S" "2025-01-01 00:00:00" +%s

# Online
# Use: https://www.unixtimestamp.com/
```

Convert Unix timestamps to dates:

```sql
SELECT datetime(1735689600, 'unixepoch');
-- Result: 2025-01-01 00:00:00
```

## Testing

```bash
# Run lifecycle test
php tests/seasonal_unit_lifecycle_test.php

# Run integration test
php tests/seasonal_unit_integration_test.php

# Run job manually
php jobs/process_seasonal_units.php
```

## Troubleshooting

### Unit not activating

1. Check is_active flag: `SELECT is_active FROM seasonal_units WHERE unit_internal_name = 'winter_knight'`
2. Check timestamps: `SELECT start_timestamp, end_timestamp FROM seasonal_units WHERE unit_internal_name = 'winter_knight'`
3. Run job manually: `php jobs/process_seasonal_units.php`
4. Check logs: `tail logs/seasonal_units.log`

### Unit still trainable after expiry

1. Check is_active flag (should be 0)
2. Check end_timestamp (should be < current time)
3. Run job manually to force sunset
4. Check UnitManager cache (may need to clear)

### Job not running

1. Check crontab: `crontab -l`
2. Check cron logs: `grep CRON /var/log/syslog`
3. Check file permissions: `ls -la jobs/process_seasonal_units.php`
4. Run manually to test: `php jobs/process_seasonal_units.php`

## Best Practices

✅ **DO:**
- Set generous time windows (1-2 weeks minimum)
- Test activation before event starts
- Monitor logs during events
- Set reasonable per_account_cap values
- Announce events to players in advance

❌ **DON'T:**
- Overlap similar seasonal units
- Set very short windows (< 24 hours)
- Forget to schedule the cron job
- Make seasonal units too powerful
- Change timestamps while event is active

## Example Event Timeline

```
Day -7:  Add seasonal unit to database (is_active = 0)
Day -3:  Announce event to players
Day -1:  Verify job is scheduled and working
Day 0:   Event starts (job activates unit)
Day 1-13: Event runs (monitor logs)
Day 14:  Event ends (job sunsets unit)
Day 15:  Review metrics and player feedback
```

## Support

- Documentation: `docs/seasonal-units-lifecycle.md`
- Tests: `tests/seasonal_unit_*_test.php`
- Logs: `logs/seasonal_units.log`
- Job: `jobs/process_seasonal_units.php`
