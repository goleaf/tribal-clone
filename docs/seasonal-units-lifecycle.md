# Seasonal Units Lifecycle Management

## Overview

The seasonal units system allows game administrators to create time-limited event units that are only available during specific windows. This document describes how the lifecycle management works and how to configure seasonal units.

## Architecture

### Components

1. **seasonal_units table**: Stores seasonal unit configurations with time windows
2. **UnitManager::checkSeasonalWindow()**: Validates if a unit is within its availability window
3. **jobs/process_seasonal_units.php**: Background job that activates and sunsets seasonal units

### Lifecycle States

A seasonal unit can be in one of these states:

- **Inactive (Future)**: `is_active = 0`, current time < start_timestamp
- **Active**: `is_active = 1`, start_timestamp <= current time <= end_timestamp  
- **Inactive (Expired)**: `is_active = 0`, current time > end_timestamp

## Configuration

### Adding a Seasonal Unit

Insert a record into the `seasonal_units` table:

```sql
INSERT INTO seasonal_units 
(unit_internal_name, event_name, start_timestamp, end_timestamp, is_active, per_account_cap)
VALUES 
('winter_knight', 'Winter Festival 2025', 1735689600, 1738368000, 0, 50);
```

Fields:
- `unit_internal_name`: Must match a unit in the `unit_types` table
- `event_name`: Human-readable event name for logging
- `start_timestamp`: Unix timestamp when the unit becomes available
- `end_timestamp`: Unix timestamp when the unit expires
- `is_active`: Set to 0 initially; the job will activate it when the window starts
- `per_account_cap`: Maximum number of this unit per player account (default: 50)

### Scheduling the Lifecycle Job

Add to crontab to run every hour:

```bash
0 * * * * php /path/to/jobs/process_seasonal_units.php >> /path/to/logs/seasonal_units.log 2>&1
```

Or run manually:

```bash
php jobs/process_seasonal_units.php
```

## Lifecycle Behavior

### Activation (Task 13.2)

When the job runs, it:

1. Queries for seasonal units where:
   - `is_active = 0`
   - `start_timestamp <= current_time`
   - `end_timestamp >= current_time`

2. Sets `is_active = 1` for matching units

3. Logs activation events to `logs/seasonal_units.log`

**Example log output:**
```
[2025-12-02 12:00:00] ACTIVATED: Unit 'winter_knight' for event 'Winter Festival 2025' 
                      (window: 2025-01-01 00:00:00 to 2025-02-01 00:00:00)
```

### Sunset (Task 13.1)

When the job runs, it:

1. Queries for seasonal units where:
   - `is_active = 1`
   - `end_timestamp < current_time`

2. Sets `is_active = 0` for matching units

3. Logs sunset events and counts existing units

4. Existing units remain functional but cannot be trained

**Example log output:**
```
[2025-02-01 12:00:00] SUNSET: Unit 'winter_knight' for event 'Winter Festival 2025' 
                      expired at 2025-02-01 00:00:00
[2025-02-01 12:00:00]   → Found 1,234 existing 'winter_knight' units across 456 villages
[2025-02-01 12:00:00]   → Units remain functional but cannot be trained 
                          (world config determines conversion policy)
[2025-02-01 12:00:00]   → Found 89 'winter_knight' units still in training queues
[2025-02-01 12:00:00]   → Queued units will complete normally but no new training allowed
```

### Handling Existing Units After Sunset

The current implementation logs existing units but does not automatically convert or remove them. This allows world administrators to decide the policy:

**Option 1: Leave units functional**
- Units remain in villages and can be used in combat
- No new training is allowed
- This is the default behavior

**Option 2: Convert to resources (future enhancement)**
- Calculate resource value based on unit costs
- Return resources to village storage
- Remove units from villages

**Option 3: Disable units (future enhancement)**
- Mark units as "disabled" in database
- Prevent use in combat
- Allow conversion to resources later

To implement conversion policies, modify the `handleExistingUnits()` function in `jobs/process_seasonal_units.php`.

## Integration with UnitManager

### Training Validation

When a player attempts to train a seasonal unit, the system:

1. Calls `UnitManager::checkSeasonalWindow($unitInternal, time())`

2. Returns availability status:
   ```php
   [
       'available' => bool,  // true if within window and active
       'start' => int|null,  // start timestamp
       'end' => int|null,    // end timestamp
       'is_active' => bool   // is_active flag from database
   ]
   ```

3. Rejects training with `ERR_SEASONAL_EXPIRED` if not available

### Example Usage

```php
$unitManager = new UnitManager($conn);
$window = $unitManager->checkSeasonalWindow('winter_knight', time());

if (!$window['available']) {
    return [
        'success' => false,
        'error' => 'This seasonal unit is not currently available',
        'code' => 'ERR_SEASONAL_EXPIRED',
        'window_start' => $window['start'],
        'window_end' => $window['end']
    ];
}
```

## Testing

### Unit Tests

Run the lifecycle test:
```bash
php tests/seasonal_unit_lifecycle_test.php
```

This tests:
- Activation of pending units
- Sunset of expired units
- Preservation of active units
- Ignoring future units

### Integration Tests

Run the integration test:
```bash
php tests/seasonal_unit_integration_test.php
```

This tests:
- UnitManager::checkSeasonalWindow() behavior
- Active units within window
- Inactive units (expired)
- Units outside window
- Non-seasonal units

## Monitoring

### Log Files

- **logs/seasonal_units.log**: Lifecycle events (activation, sunset)
- **logs/recruit_telemetry.log**: Training attempts for seasonal units

### Metrics to Monitor

1. **Activation rate**: How many seasonal units are activated per run
2. **Sunset rate**: How many seasonal units expire per run
3. **Training attempts**: Track ERR_SEASONAL_EXPIRED errors
4. **Existing units**: Monitor unit counts after sunset

### Alerts

Consider alerting on:
- Seasonal units training after expiry (indicates job failure)
- High ERR_SEASONAL_EXPIRED rates (indicates player confusion)
- Job execution failures

## Best Practices

### Event Planning

1. **Set windows generously**: Allow 1-2 week windows for events
2. **Announce in advance**: Give players notice before events start
3. **Overlap prevention**: Avoid overlapping similar seasonal units
4. **Cap appropriately**: Set per_account_cap based on unit power

### Operational

1. **Run job hourly**: More frequent runs ensure timely activation/sunset
2. **Monitor logs**: Check for errors or unexpected behavior
3. **Test before events**: Verify units activate correctly before event start
4. **Backup data**: Keep backups before major seasonal events

### Unit Design

1. **Balanced power**: Seasonal units should be interesting but not overpowered
2. **Clear theme**: Unit should match the event theme
3. **Unique abilities**: Give seasonal units special abilities to make them memorable
4. **Limited availability**: Use scarcity to create excitement

## Requirements Validation

This implementation satisfies:

- **Requirement 10.1**: Seasonal units are available within configured time windows
- **Requirement 10.2**: Training is disabled when events expire
- **Requirement 10.3**: Existing units are handled based on world configuration
- **Requirement 10.4**: Training requests are rejected with ERR_SEASONAL_EXPIRED

## Future Enhancements

1. **Resource conversion**: Automatically convert expired units to resources
2. **Grace period**: Allow a grace period after expiry before sunset
3. **Recurring events**: Support yearly recurring seasonal units
4. **Dynamic caps**: Adjust per_account_cap based on player activity
5. **Event notifications**: Notify players when seasonal units activate/expire
6. **Admin UI**: Web interface for managing seasonal units
