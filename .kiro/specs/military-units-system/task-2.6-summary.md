# Task 2.6: Implement checkSeasonalWindow() Method - Summary

## Status: ✅ COMPLETED

## Overview
Implemented the `checkSeasonalWindow()` method in the UnitManager class to query the seasonal_units table and determine if a unit is available based on time windows.

## Implementation Details

### Method Signature
```php
public function checkSeasonalWindow(string $unitInternal, int $timestamp): array
```

### Return Value
```php
[
    'available' => bool,      // Whether unit can be trained at given timestamp
    'start' => int|null,      // Start timestamp (null if not seasonal)
    'end' => int|null,        // End timestamp (null if not seasonal)
    'is_active' => bool       // Whether event is active (only for seasonal units)
]
```

### Implementation Logic
1. **Query seasonal_units table** for the given unit internal name
2. **Non-seasonal units**: Return `available=true` with null start/end
3. **Seasonal units**: Check if:
   - Event is active (`is_active = 1`)
   - Current timestamp is within window (`timestamp >= start AND timestamp <= end`)
4. **Return availability status** with window details

### Integration Points
The method is integrated into:
- ✅ `checkRecruitRequirements()` - Validates seasonal window before allowing recruitment
- ✅ `isUnitAvailable()` - Checks seasonal availability for UI filtering

## Testing

### Test Coverage
Created comprehensive test suite in `tests/seasonal_window_test.php`:

1. ✅ Non-seasonal unit returns available=true with null window
2. ✅ Seasonal unit within active window is available
3. ✅ Seasonal unit before window starts is not available
4. ✅ Seasonal unit after window expires is not available
5. ✅ Inactive seasonal unit is not available even within window
6. ✅ Edge case: Unit at exact start timestamp is available
7. ✅ Edge case: Unit at exact end timestamp is available

### Test Results
```
Test Summary:
  Passed: 14
  Failed: 0
  Total:  14
```

All tests passed successfully! ✅

## Requirements Validated

### Requirement 10.1
✅ **WHEN a seasonal unit event is active THEN the system SHALL allow training of event units within the configured start and end timestamps**
- Implementation checks `is_active` flag and timestamp range
- Returns `available=true` only when all conditions met

### Requirement 10.2
✅ **WHEN a seasonal unit event expires THEN the system SHALL disable training of event units and hide them from recruitment UI**
- Returns `available=false` when timestamp is outside window
- Integration with `isUnitAvailable()` filters expired units from UI

### Requirement 10.4
✅ **WHEN a player attempts to train expired seasonal units THEN the system SHALL reject the request and return error code ERR_SEASONAL_EXPIRED**
- Integration with `checkRecruitRequirements()` returns ERR_SEASONAL_EXPIRED
- Includes window details in error response for user feedback

## Database Schema
The method queries the `seasonal_units` table:
```sql
CREATE TABLE seasonal_units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    unit_internal_name TEXT NOT NULL UNIQUE,
    event_name TEXT NOT NULL,
    start_timestamp INTEGER NOT NULL,
    end_timestamp INTEGER NOT NULL,
    per_account_cap INTEGER DEFAULT 50,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)
```

Index used for efficient queries:
```sql
CREATE INDEX idx_seasonal_active_window 
ON seasonal_units(is_active, start_timestamp, end_timestamp)
```

## Error Handling
- Returns safe defaults for non-seasonal units (available=true, null window)
- Handles database errors gracefully (returns available=false)
- Validates all timestamp comparisons using inclusive ranges (>= and <=)

## Performance Considerations
- Single database query with indexed lookup
- Cached in UnitManager for repeated calls within same request
- No complex calculations, just timestamp comparisons

## Next Steps
This method is now ready for use in:
- Task 2.9: Extend checkRecruitRequirements() to include seasonal checks ✅ (Already integrated)
- Task 13.1: Create seasonal unit sunset job (will use this method)
- Task 13.2: Create seasonal unit activation job (will use this method)

## Files Modified
- ✅ `lib/managers/UnitManager.php` - Added checkSeasonalWindow() method
- ✅ `tests/seasonal_window_test.php` - Created comprehensive test suite

## Verification
```bash
php tests/seasonal_window_test.php
# Result: All 14 tests passed ✅
```

---
**Task completed successfully on:** 2024-12-02
**Requirements validated:** 10.1, 10.2, 10.4
