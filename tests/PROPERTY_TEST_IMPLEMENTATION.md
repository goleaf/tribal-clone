# Property Test Implementation Summary

## Overview

Implemented Property 4: Building Upgrade State Transition test for the BuildingManager component. This property-based test validates that building upgrades correctly deduct resources and create queue entries.

## Test Details

**File:** `tests/building_manager_property_test.php`

**Property Validated:** Property 4 - Building Upgrade State Transition  
**Requirements:** 2.2 from resource-system spec  
**Iterations:** 100 per test run

### Property Statement

> For any valid building upgrade initiation, the village resources SHALL decrease by exactly the upgrade cost, AND the building queue SHALL contain exactly one new entry for that building.

## Security Hardening

The test implements comprehensive security measures per `tests/SECURITY_TESTING_GUIDE.md`:

### 1. Database Protection (CRITICAL)
```php
if (strpos(DB_PATH, 'test') === false) {
    die("ERROR: This test must run against a test database only...");
}
```

### 2. Test Data Isolation (HIGH)
- Creates unique test users with cryptographically secure passwords
- Uses negative coordinates (-1000 to -10999) to avoid collision with production data
- Tracks all created entities for cleanup
- Registers shutdown function to clean up on exit

### 3. Ownership Validation (MEDIUM)
```php
$stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
// Verify ownership before operations
if (!$ownerCheck || $ownerCheck['user_id'] != $userId) {
    return "SECURITY: Ownership validation failed";
}
```

### 4. Cryptographic Security (LOW)
```php
$password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
```

## Test Implementation

### Generator Function

Creates isolated test environment for each iteration:

1. **Random Building Selection** - Excludes special buildings (first_church, church, wall)
2. **User Creation** - Unique username with secure password
3. **Village Creation** - Negative coordinates for isolation, 100k resources
4. **Building Setup** - Random level (0 to max-2) to ensure upgradeable
5. **Main Building** - Level 1-10 (required for all buildings)
6. **Prerequisites** - Automatically fulfills all building requirements

### Property Function

Validates the upgrade state transition:

1. **Ownership Check** - Security validation
2. **Resource Snapshot** - Captures before state
3. **Cost Calculation** - Gets expected upgrade cost
4. **Enqueue Build** - Calls BuildingQueueManager.enqueueBuild()
5. **Resource Verification** - Exact deduction validation
6. **Queue Verification** - Confirms queue entry created with correct data

## Test Results

```
=== BuildingManager Property-Based Tests ===

Running property test: Property 4: Building Upgrade State Transition
✓ PASS: Property 4: Building Upgrade State Transition (100/100 iterations)

=== Test Summary ===
All property tests passed!

=== Cleaning up test data ===
Cleaned 100 queue entries
Cleaned 100 villages
Cleaned 100 users
```

## Key Fixes Applied

### 1. Prerequisite Handling
- Checks for existing buildings before inserting
- Updates level if current level is lower than required
- Prevents duplicate building entries

```php
$stmt = $conn->prepare("SELECT id FROM village_buildings WHERE village_id = ? AND building_type_id = ? LIMIT 1");
// ... check if exists
if (!$existingBuilding) {
    // Insert new
} else {
    // Update to required level
    $stmt = $conn->prepare("UPDATE village_buildings SET level = GREATEST(level, ?) WHERE village_id = ? AND building_type_id = ?");
}
```

### 2. Main Building Requirement
- Ensures main_building exists at level 1+ for all tests
- Required by BuildingQueueManager validation

### 3. Cleanup Robustness
- Tracks all created entities in arrays
- Uses prepared statements with proper parameter binding
- Cleans in correct order: queue → buildings → villages → users

## Integration with Existing Code

### BuildingQueueManager Integration

The test validates the complete enqueueBuild() flow:

- Transaction handling (BEGIN IMMEDIATE for SQLite, begin_transaction for MySQL)
- Village row locking (SELECT FOR UPDATE)
- Resource deduction (immediate, atomic)
- Queue status logic (active vs pending)
- Finish time calculation
- Queue capacity validation

### Return Structure Validation

Verifies BuildingQueueManager returns:
```php
[
    'success' => true,
    'queue_item_id' => int,
    'status' => 'active'|'pending',
    'start_at' => timestamp,
    'finish_at' => timestamp,
    'level' => int,
    'building_internal_name' => string
]
```

## Next Steps

### Additional Property Tests Needed

From `.kiro/specs/2-building-queue-system/tasks.md`:

1. **Property 5** - Build Completion Increments Level (Requirement 2.1)
2. **Property 6** - Completion Status Transition (Requirement 2.2)
3. **Property 7** - Pending Promotion on Completion (Requirement 2.3)
4. **Property 8** - Finish Time Recalculation on Promotion (Requirement 2.4)
5. **Property 9** - Cancellation Refund Calculation (Requirement 3.1)
6. **Property 10** - Active Cancellation Promotes Pending (Requirement 3.2)

### Manual Testing Checklist

- [ ] Test with SQLite database
- [ ] Test with MySQL database
- [ ] Verify cleanup on abnormal termination
- [ ] Test with concurrent requests (stress test)
- [ ] Verify no orphaned data after 1000+ iterations

## References

- **Spec:** `.kiro/specs/1-resource-system/tasks.md` (Task 2.1)
- **Security Guide:** `tests/SECURITY_TESTING_GUIDE.md`
- **BuildingQueueManager:** `lib/managers/BuildingQueueManager.php`
- **BuildingManager:** `lib/managers/BuildingManager.php`
- **DevDocs PHP:** https://devdocs.io/php/ (prepared statements, random_bytes, password_hash)

## Verification Commands

```bash
# Run the property test
php tests/building_manager_property_test.php

# Verify no orphaned data
sqlite3 data/test_tribal_wars.sqlite "SELECT COUNT(*) FROM users WHERE username LIKE 'test_%';"
# Should return 0

# Check for orphaned villages
sqlite3 data/test_tribal_wars.sqlite "SELECT COUNT(*) FROM villages WHERE x_coord < -1000;"
# Should return 0
```

## Conclusion

Property 4 test successfully validates the Building Upgrade State Transition property with 100% pass rate across 100 iterations. The test implements comprehensive security measures, proper cleanup, and validates the complete enqueueBuild() flow including resource deduction and queue entry creation.

All test data is properly isolated and cleaned up, with zero orphaned records after execution.
