# Task 2.8: Implement checkEliteUnitCap() Method - Summary

## Status: ✅ Complete

## Implementation Details

### Method: `checkEliteUnitCap()`
**Location:** `lib/managers/UnitManager.php` (lines 1267-1329)

### Functionality
The method enforces per-account caps on elite units by:

1. **Defining Elite Units**: Maintains a list of elite units with their default caps:
   - `warden`: 100 units per account
   - `ranger`: 100 units per account
   - `tempest_knight`: 50 units per account (seasonal)
   - `event_knight`: 50 units per account (seasonal)

2. **Counting Existing Units**: Queries across all villages owned by the user:
   - Counts stationed units from `village_units` table
   - Counts queued units from `unit_queue` table
   - Sums both to get total current count

3. **Cap Enforcement**: Compares requested count against available capacity:
   - Checks if `(currentCount + requestedCount) <= maxCap`
   - Returns detailed status including current count and max cap

4. **Non-Elite Units**: Returns no cap (`max: -1`) for regular units

### Method Signature
```php
public function checkEliteUnitCap(int $userId, string $unitInternal, int $count): array
```

### Return Value
```php
[
    'can_train' => bool,    // Whether training is allowed
    'current' => int,       // Current unit count (existing + queued)
    'max' => int           // Maximum allowed (-1 for non-elite units)
]
```

### Requirements Validated
- ✅ **Requirement 9.2**: Enforces per-account caps on elite and seasonal units
- ✅ Counts units across all villages for the user
- ✅ Includes both stationed and queued units in count
- ✅ Compares against configured per-account caps
- ✅ Returns detailed cap status

## Testing

### Test File: `tests/elite_unit_cap_test.php`

### Test Coverage
1. ✅ Non-elite units have no cap
2. ✅ Elite units start with 0 count
3. ✅ Counts units across multiple villages
4. ✅ Prevents exceeding cap
5. ✅ Allows training exactly to cap
6. ✅ Includes queued units in count
7. ✅ Different elite units have separate caps

### Test Results
```
All tests passed! ✓
- Test 1: Non-elite units have no cap ✓
- Test 2: Created warden unit ✓
- Test 3: Check cap with no existing warden units ✓
- Test 4: Add 30 wardens to village 1 ✓
- Test 5: Add 40 wardens to village 2 ✓
- Test 6: Try to train 40 wardens (would exceed cap) ✓
- Test 7: Train exactly 30 wardens (to reach cap) ✓
- Test 8: Add 20 wardens to queue ✓
- Test 9: Creating test elite unit (ranger) ✓
```

## Integration

### Used By
- `recruitUnits()` method in UnitManager (line 524)
- Called before adding units to recruitment queue
- Prevents training if cap would be exceeded

### Error Handling
- Returns `can_train: false` when cap is exceeded
- Provides current count and max cap for error messages
- Handles database errors gracefully

## Design Notes

### Elite Unit Caps Table
The `elite_unit_caps` table exists in the database but is currently used for tracking purposes. The implementation uses:
- **Default caps**: Hardcoded in the method for each elite unit type
- **Real-time counting**: Queries `village_units` and `unit_queue` directly for accuracy
- **Future enhancement**: Could be extended to support per-user cap overrides from the table

### Performance Considerations
- Uses indexed queries on `user_id` and `unit_internal_name`
- Two separate queries (existing + queued) for clarity
- Minimal overhead as caps are only checked for elite units

## Files Modified
- ✅ `lib/managers/UnitManager.php` - Method already implemented
- ✅ `tests/elite_unit_cap_test.php` - Comprehensive test suite created

## Validation
- ✅ Method signature matches design document
- ✅ Counts units across all villages
- ✅ Includes queued units in count
- ✅ Enforces per-account caps correctly
- ✅ Returns proper status structure
- ✅ All tests pass

## Next Steps
Task 2.8 is complete. The next task in the sequence is:
- **Task 2.9**: Extend checkRecruitRequirements() to include seasonal and elite checks
