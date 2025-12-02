# Task 5: Implement Telemetry and Logging - Summary

## Completed Subtasks

### 5.1 Extend logRecruitTelemetry() in recruit.php ✓
**Requirements: 17.5, 18.1**

Enhanced the `logRecruitTelemetry()` function to include all required fields:
- Added `world_id` parameter (optional, defaults to CURRENT_WORLD_ID or 1)
- Updated all 13 call sites to pass world_id explicitly
- Log entries now include: timestamp, user_id, village_id, world_id, unit_id, count, status, code, message
- Logs written to `logs/recruit_telemetry.log` in JSON format

**Changes:**
- Modified function signature to accept optional `?int $worldId = null`
- Moved world_id retrieval to top of POST handler for consistency
- All telemetry calls now include world context

### 5.4 Add cap hit counter incrementation ✓
**Requirements: 18.2**

Implemented `incrementCapHitCounter()` function to track when ERR_CAP is returned:
- Logs to `logs/cap_hit_counters.log`
- Tracks by unit_id, world_id, and unit_internal name
- Called whenever ERR_CAP error is returned (2 locations):
  1. When checkRecruitRequirements returns ERR_CAP
  2. When noble cap is reached

**Log format:**
```json
{
  "ts": "2025-12-02T09:50:10+00:00",
  "world_id": 1,
  "unit_id": 5,
  "unit_internal": "noble",
  "event": "cap_hit"
}
```

### 5.5 Add error counter incrementation ✓
**Requirements: 18.3**

Implemented `incrementErrorCounter()` function to track all error codes:
- Logs to `logs/error_counters.log`
- Tracks by error code (reason code)
- Called for all error types:
  - ERR_INPUT (3 locations)
  - ERR_FEATURE_DISABLED (1 location)
  - ERR_PREREQ (2 locations)
  - ERR_CAP (2 locations)
  - ERR_RES (3 locations)
  - ERR_SERVER (1 location)

**Log format:**
```json
{
  "ts": "2025-12-02T09:50:10+00:00",
  "error_code": "ERR_CAP",
  "event": "error"
}
```

## Implementation Details

### Files Modified
- `ajax/units/recruit.php`: Added 3 telemetry functions and updated all error paths

### New Functions
1. `logRecruitTelemetry()` - Enhanced with world_id tracking
2. `incrementCapHitCounter()` - Tracks cap hit events
3. `incrementErrorCounter()` - Tracks all error events

### Log Files Created
- `logs/recruit_telemetry.log` - All recruitment attempts (success and failure)
- `logs/cap_hit_counters.log` - Cap hit events by unit type and world
- `logs/error_counters.log` - Error events by reason code

## Testing

Created `tests/telemetry_logging_test.php` with 4 test cases:
1. ✓ Verify logRecruitTelemetry includes all required fields
2. ✓ Verify incrementCapHitCounter logs cap hits correctly
3. ✓ Verify incrementErrorCounter logs all error codes
4. ✓ Verify world_id defaults when not provided

All tests pass successfully.

## Validation Against Requirements

### Requirement 17.5 ✓
"WHEN training requests fail THEN the system SHALL log the failure with correlation ID, player ID, unit type, and reason code for telemetry"
- Implemented: All failures logged with timestamp (correlation), user_id (player), unit_id (unit type), and error code (reason)

### Requirement 18.1 ✓
"WHEN a player trains units THEN the system SHALL emit metrics including unit type, count, world ID, and player ID"
- Implemented: All training attempts logged with unit_id, count, world_id, and user_id

### Requirement 18.2 ✓
"WHEN training requests hit caps THEN the system SHALL increment cap-hit counters by unit type and world"
- Implemented: Cap hits tracked with unit_id, unit_internal, and world_id

### Requirement 18.3 ✓
"WHEN training requests fail validation THEN the system SHALL increment error counters by reason code"
- Implemented: All error codes tracked in error_counters.log

## Notes

- All telemetry functions use file locking (LOCK_EX) to prevent race conditions
- JSON format allows easy parsing for analytics
- Timestamp in ISO 8601 format for consistency
- Error suppression (@) on file_put_contents prevents telemetry failures from breaking recruitment
- World ID defaults to 1 if CURRENT_WORLD_ID is not defined
