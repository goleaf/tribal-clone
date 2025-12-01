# Task 2.9 Summary: Extend checkRecruitRequirements() to include seasonal and elite checks

## Implementation Status: ✅ COMPLETE

## Changes Made

### 1. Extended `UnitManager::checkRecruitRequirements()` Method

**File:** `lib/managers/UnitManager.php`

**Changes:**
- Added optional `$count` parameter (defaults to 1) to support cap validation
- Added user ID lookup to enable elite unit cap checks
- Integrated `checkEliteUnitCap()` call to validate elite unit caps
- Returns `ERR_CAP` error code when elite unit cap is exceeded
- Returns detailed error information including `current_count` and `max_cap`
- Maintained existing seasonal window validation (already present)
- Maintained existing feature flag validation (already present)

**Method Signature:**
```php
public function checkRecruitRequirements($unit_type_id, $village_id, $count = 1)
```

**Error Codes Returned:**
- `ERR_PREREQ`: Prerequisites not met (building level, research, village not found)
- `ERR_FEATURE_DISABLED`: Unit type disabled by world feature flags
- `ERR_SEASONAL_EXPIRED`: Seasonal unit outside availability window
- `ERR_CAP`: Elite unit cap exceeded
- `ERR_SERVER`: Database error

### 2. Updated Recruitment Endpoint

**File:** `ajax/units/recruit.php`

**Changes:**
- Updated `checkRecruitRequirements()` call to pass `$count` parameter
- Enhanced error response handling to include additional details for specific error types
- Added `current_count` and `max_cap` to response for `ERR_CAP` errors
- Added `window_start` and `window_end` to response for `ERR_SEASONAL_EXPIRED` errors
- Improved telemetry logging to use correct error codes

### 3. Created Comprehensive Tests

**Files:**
- `tests/check_recruit_requirements_test.php` - Unit tests for specific scenarios
- `tests/check_recruit_requirements_integration_test.php` - Integration tests

**Test Coverage:**
- ✅ Method signature with count parameter (default and explicit)
- ✅ Elite unit cap validation
- ✅ Seasonal window validation
- ✅ Feature flag validation
- ✅ Error code structure and consistency
- ✅ Integration with `checkSeasonalWindow()`
- ✅ Integration with `checkEliteUnitCap()`

**Test Results:** All 14 integration tests passed

## Requirements Validated

### Requirement 10.4 (Seasonal Window Validation)
✅ When a player attempts to train expired seasonal units, the system rejects the request and returns error code `ERR_SEASONAL_EXPIRED` with window information.

### Requirement 9.2 (Elite Unit Cap Validation)
✅ When a player attempts to train elite units, the system enforces per-account caps and rejects requests that would exceed the cap with error code `ERR_CAP`.

### Requirement 15.4 (Prerequisite Error Handling)
✅ When prerequisites are not met, the system returns error code `ERR_PREREQ` with missing requirements.

### Requirement 15.5 (Feature Flag Enforcement)
✅ When world features are disabled, the system rejects training of disabled unit types and returns error code `ERR_FEATURE_DISABLED`.

## Implementation Details

### Elite Unit Cap Check Flow
1. Get user ID from village
2. Call `checkEliteUnitCap($userId, $unitInternal, $count)`
3. If cap would be exceeded, return error with current count and max cap
4. Elite units checked: warden, ranger, tempest_knight, event_knight

### Seasonal Window Check Flow
1. Call `checkSeasonalWindow($unitInternal, time())`
2. If unit is seasonal and window is expired, return error with window timestamps
3. Window check includes `start_timestamp`, `end_timestamp`, and `is_active` flag

### Error Response Structure
```json
{
  "can_recruit": false,
  "reason": "elite_cap_reached",
  "code": "ERR_CAP",
  "unit": "warden",
  "current_count": 50,
  "max_cap": 100
}
```

## Backward Compatibility

✅ The `$count` parameter is optional with a default value of 1, maintaining backward compatibility with existing code that doesn't pass the count parameter.

## Testing Notes

- Integration tests verify the method works with and without the count parameter
- Tests verify proper error codes are returned for each validation type
- Tests verify error responses include all required fields
- Existing integration tests continue to pass

## Next Steps

This task is complete. The next tasks in the implementation plan are:
- Task 3.1: Implement getVillageUnitCountWithQueue() helper
- Task 3.2: Add per-village siege cap check to recruitUnits()
- Task 3.3: Add per-account elite cap check to recruitUnits()
