# Village Conquest System - Implementation Progress

## Completed Tasks (1-4)

### ✅ Task 1: Database Schema and Migrations

**Files Created:**
- `migrations/add_conquest_system.php` - Adds control/uptime columns and conquest_attempts table
- `migrations/add_conquest_world_config.php` - Adds conquest configuration to worlds table

**Database Changes:**
- Added to `villages` table:
  - `control_meter` (INTEGER) - Control meter for control-uptime mode (0-100)
  - `uptime_started_at` (DATETIME) - When control reached 100 and uptime began
  - `allegiance` (INTEGER) - Allegiance value (0-100)
  - `allegiance_last_update` (DATETIME) - Last regeneration update
  - `capture_cooldown_until` (DATETIME) - Capture cooldown expiry
  - `anti_snipe_until` (DATETIME) - Anti-snipe protection expiry
  - `allegiance_floor` (INTEGER) - Minimum allegiance during anti-snipe

- Created `conquest_attempts` audit log table:
  - Tracks all conquest attempts with full context
  - Includes attacker, defender, village, surviving Envoys
  - Records allegiance changes, drop amounts, capture status
  - Stores reason codes for blocked attempts
  - Includes wall level and modifiers (JSON)
  - Indexed for performance

- Added performance indexes:
  - `idx_allegiance_update` on villages(allegiance_last_update)
  - `idx_capture_cooldown` on villages(capture_cooldown_until)
  - `idx_anti_snipe` on villages(anti_snipe_until)
  - `idx_control_meter` on villages(control_meter)

**Migrations Run:**
```bash
php migrations/add_allegiance_columns.php  # ✅ Complete
php migrations/add_conquest_system.php     # ✅ Complete
php migrations/add_conquest_world_config.php # ✅ Complete
```

---

### ✅ Task 2: World Configuration System

**Files Modified:**
- `lib/managers/WorldManager.php` - Added conquest configuration support

**New Configuration Fields (added to worlds table):**
- `conquest_enabled` - Enable/disable conquest system
- `conquest_mode` - 'allegiance' or 'control' mode
- `alleg_regen_per_hour` - Allegiance regeneration rate (default: 2.0)
- `alleg_wall_reduction_per_level` - Wall damage reduction (default: 0.02)
- `alleg_drop_min` - Min allegiance drop per Envoy (default: 18)
- `alleg_drop_max` - Max allegiance drop per Envoy (default: 28)
- `anti_snipe_floor` - Minimum allegiance during anti-snipe (default: 10)
- `anti_snipe_seconds` - Anti-snipe duration (default: 900 = 15 min)
- `post_capture_start` - Starting allegiance after capture (default: 25)
- `capture_cooldown_seconds` - Cooldown before re-capture (default: 900)
- `uptime_duration_seconds` - Required uptime at 100% control (default: 900)
- `control_gain_rate_per_min` - Control gain per minute (default: 5)
- `control_decay_rate_per_min` - Control decay per minute (default: 3)
- `wave_spacing_ms` - Minimum wave spacing (default: 300ms)
- `max_envoys_per_command` - Max Envoys per attack (default: 1)
- `conquest_daily_mint_cap` - Max crests minted per day (default: 5)
- `conquest_daily_train_cap` - Max training sessions per day (default: 3)
- `conquest_min_defender_points` - Min defender points (default: 1000)
- `conquest_building_loss_enabled` - Enable building loss on capture
- `conquest_building_loss_chance` - Chance of building loss (default: 10%)
- `conquest_resource_transfer_pct` - Resource transfer percentage (default: 100%)
- `conquest_abandonment_decay_enabled` - Enable abandonment decay
- `conquest_abandonment_threshold_hours` - Hours before abandonment (default: 168 = 7 days)
- `conquest_abandonment_decay_rate` - Decay rate for abandoned villages (default: 1.0)

**New WorldManager Methods:**
- `isConquestEnabled(int $worldId)` - Check if conquest is enabled
- `getConquestSettings(int $worldId)` - Get all conquest configuration
- `getConquestMode(int $worldId)` - Get conquest mode (allegiance/control)

**Feature Flags Supported:**
- `FEATURE_CONQUEST_ENABLED` - Global conquest enable/disable
- `CONQUEST_MODE` - Global mode override
- `CONQUEST_ANTI_SNIPE_ENABLED` - Anti-snipe feature flag
- `CONQUEST_WALL_MOD_ENABLED` - Wall modifier feature flag

---

### ✅ Task 3: AllegianceService Core Calculations

**Files Created:**
- `lib/services/AllegianceService.php` - Core calculation engine

**Implemented Methods:**

1. **`calculateDrop()`** - Calculate allegiance drop from conquest wave
   - Random drop per Envoy (configurable min/max range)
   - Wall level reduction factor
   - World multipliers
   - Anti-snipe floor enforcement
   - Clamping to valid range [0, 100]
   - Capture detection
   - Returns: new_allegiance, drop_amount, captured, clamped, wall_reduction, floor_active

2. **`applyRegeneration()`** - Apply time-based regeneration
   - Base rate per hour (configurable)
   - Building and tech bonuses with multiplier cap
   - Pause logic during anti-snipe periods
   - Clamp to maximum of 100
   - Returns: new allegiance value

3. **`enforceFloor()`** - Enforce anti-snipe floor
   - Check if anti-snipe period is active
   - Enforce minimum allegiance floor
   - Handle floor expiry logic
   - Returns: clamped allegiance value

4. **`checkCapture()`** - Detect if capture conditions are met
   - Allegiance mode: capture when allegiance <= 0
   - Control mode: capture when control >= 100 with uptime complete
   - Respect anti-snipe and cooldown states
   - Returns: boolean capture flag

5. **`updateAllegiance()`** - Update village allegiance in database
   - Updates allegiance value
   - Updates last_update timestamp
   - Returns: success boolean

6. **`initializePostCapture()`** - Initialize post-capture state
   - Set post-capture allegiance start value
   - Activate anti-snipe floor
   - Set capture cooldown timestamp
   - Reset control meter and uptime
   - Returns: success boolean

**Algorithms Implemented:**
- Drop calculation: `base_drop = random(min, max) * envoy_count * wall_reduction * world_multiplier`
- Wall reduction: `1.0 - min(0.5, wall_level * reduction_factor)`
- Regeneration: `(base_rate / 3600) * elapsed_seconds * multiplier`
- Multiplier cap: `min(3.0, building_mult * tech_mult)`

---

### ✅ Task 4: ConquestStateMachine Validation

**Files Created:**
- `lib/services/ConquestStateMachine.php` - Prerequisite validation and business rules

**Error Codes Defined:**
- `ERR_PREREQ` - Prerequisites not met
- `ERR_CAP` - Capacity limit exceeded
- `ERR_RES` - Insufficient resources
- `ERR_POP` - Insufficient population
- `ERR_PROTECTED` - Target is protected
- `ERR_SAFE_ZONE` - Target in safe zone
- `ERR_COOLDOWN` - Capture cooldown active
- `ERR_SPACING` - Wave spacing violation
- `ERR_VILLAGE_CAP` - Village limit exceeded
- `ERR_HANDOVER_OFF` - Tribe handover not enabled
- `ERR_COMBAT_LOSS` - Attacker lost battle
- `ERR_NO_BEARER` - No Envoys survived
- `ERR_FEATURE_OFF` - Conquest feature disabled
- `ERR_MIN_POINTS` - Defender below minimum points

**Implemented Methods:**

1. **`validateAttempt()`** - Validate all prerequisites for conquest attempt
   - Check if conquest is enabled
   - Validate combat win requirement
   - Validate Envoy survival
   - Check protection status
   - Check cooldown
   - Check village cap
   - Check tribe handover rules
   - Returns: ['allowed' => bool, 'reason_code' => string, 'message' => string]

2. **`checkProtection()`** - Check if target is protected
   - Beginner protection check
   - Minimum points requirement
   - Safe zone check (placeholder)
   - Returns: validation result

3. **`checkCooldown()`** - Check capture cooldown status
   - Query village cooldown expiry
   - Calculate remaining time
   - Returns: validation result with remaining minutes

4. **`checkWaveSpacing()`** - Check wave spacing requirements
   - Query last wave arrival time
   - Calculate time difference in milliseconds
   - Enforce minimum spacing per world config
   - Returns: validation result

5. **`checkVillageCap()`** - Check village cap enforcement
   - Count player's current villages
   - Compare against maximum limit
   - Returns: validation result

6. **`checkHandover()`** - Check tribe handover opt-in
   - Get tribe memberships for attacker and defender
   - Check if same tribe
   - Validate handover opt-in (placeholder)
   - Returns: validation result

7. **`isProtected()`** - Helper to check if village is protected
   - Returns: boolean

8. **`isInCooldown()`** - Helper to check if village is in cooldown
   - Returns: boolean

---

## Next Steps (Tasks 5-8)

### Task 5: TrainingPipeline for Envoys
- Implement Hall of Banners building
- Training prerequisite validation
- Training cap enforcement
- Resource consumption
- Integration with training queue system

### Task 6: Combat Integration
- Hook into battle resolution system
- Envoy survival calculation
- Control/allegiance application
- Resistance calculation

### Task 7: Control/Uptime Mechanics
- Uptime timer system
- Control decay system

### Task 8: PostCaptureHandler
- Ownership transfer
- Diplomacy state updates
- Resource transfer
- Anti-snipe initialization
- Optional building loss
- Outgoing command handling

---

## Testing Status

### Unit Tests (To Be Written)
- [ ] Property test for allegiance drop calculation (Property 3, 7)
- [ ] Property test for regeneration calculation (Property 11, 12, 13)
- [ ] Property test for floor enforcement (Property 16, 17)
- [ ] Property test for capture detection (Property 5)
- [ ] Property test for prerequisite validation (Property 3)
- [ ] Property test for protection checks (Property 18)
- [ ] Property test for wave spacing (Property 23)
- [ ] Property test for cap enforcement (Property 19, 21)

### Integration Tests (To Be Written)
- [ ] End-to-end conquest flow
- [ ] Multi-wave conquest trains
- [ ] Concurrent attacks on same village
- [ ] Post-capture state transitions

---

## Configuration Summary

### Default World Settings
```php
[
    'conquest_enabled' => true,
    'conquest_mode' => 'allegiance',
    'alleg_regen_per_hour' => 2.0,
    'alleg_wall_reduction_per_level' => 0.02,
    'alleg_drop_min' => 18,
    'alleg_drop_max' => 28,
    'anti_snipe_floor' => 10,
    'anti_snipe_seconds' => 900,  // 15 minutes
    'post_capture_start' => 25,
    'capture_cooldown_seconds' => 900,  // 15 minutes
    'uptime_duration_seconds' => 900,  // 15 minutes
    'control_gain_rate_per_min' => 5,
    'control_decay_rate_per_min' => 3,
    'wave_spacing_ms' => 300,
    'max_envoys_per_command' => 1,
    'conquest_daily_mint_cap' => 5,
    'conquest_daily_train_cap' => 3,
    'conquest_min_defender_points' => 1000,
]
```

### Envoy Unit Configuration (already in units.json)
```json
{
  "envoy": {
    "buildTimeSec": 60000,
    "carry": 0,
    "cost": {
      "clay": 20000,
      "iron": 25000,
      "wood": 15000
    },
    "def": {
      "arc": 40,
      "cav": 40,
      "gen": 40
    },
    "off": 30,
    "pop": 80,
    "speedMinPerField": 35
  }
}
```

---

## Files Created/Modified

### New Files (6)
1. `migrations/add_conquest_system.php`
2. `migrations/add_conquest_world_config.php`
3. `lib/services/AllegianceService.php`
4. `lib/services/ConquestStateMachine.php`
5. `.kiro/specs/village-conquest-system/implementation-progress.md` (this file)

### Modified Files (1)
1. `lib/managers/WorldManager.php` - Added conquest configuration support

---

## Database Schema Changes

### Villages Table (7 new columns)
- allegiance (INTEGER, default 100)
- allegiance_last_update (DATETIME)
- capture_cooldown_until (DATETIME)
- anti_snipe_until (DATETIME)
- allegiance_floor (INTEGER, default 0)
- control_meter (INTEGER, default 0)
- uptime_started_at (DATETIME)

### Worlds Table (24 new columns)
- conquest_enabled, conquest_mode
- alleg_regen_per_hour, alleg_wall_reduction_per_level
- alleg_drop_min, alleg_drop_max
- anti_snipe_floor, anti_snipe_seconds
- post_capture_start, capture_cooldown_seconds
- uptime_duration_seconds, control_gain_rate_per_min, control_decay_rate_per_min
- wave_spacing_ms, max_envoys_per_command
- conquest_daily_mint_cap, conquest_daily_train_cap, conquest_min_defender_points
- conquest_building_loss_enabled, conquest_building_loss_chance
- conquest_resource_transfer_pct
- conquest_abandonment_decay_enabled, conquest_abandonment_threshold_hours
- conquest_abandonment_decay_rate

### New Tables (1)
- conquest_attempts (audit log with 14 columns + indexes)

---

## Progress: 4/18 Tasks Complete (22%)

**Completed:** Tasks 1-4 (Database, Configuration, Core Services)  
**Next:** Tasks 5-8 (Training, Combat, Control, Post-Capture)  
**Remaining:** Tasks 9-18 (Reporting, Regeneration, Anti-Abuse, UI, Testing)
