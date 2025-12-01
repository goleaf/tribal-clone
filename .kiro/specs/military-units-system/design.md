# Design Document

## Overview

The Military Units System provides a comprehensive roster of 16+ distinct military units across seven categories, each with unique combat roles, stats, and strategic purposes. The system integrates with the existing battle resolution engine, recruitment queue system, and world configuration framework to deliver balanced rock-paper-scissors combat dynamics while supporting diverse player strategies through village specialization.

The design builds upon the existing `UnitManager`, `BattleManager`, and recruitment infrastructure, extending them with:
- Complete unit stat definitions for all 16+ unit types
- Rock-paper-scissors combat modifiers and matchup bonuses
- Support unit mechanics (auras, healing, siege cover)
- Conquest unit mechanics with allegiance reduction
- Per-village and per-account unit caps
- Seasonal/event unit lifecycle management
- World-specific multipliers for different server archetypes
- Comprehensive validation and telemetry

## Architecture

### Component Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Game Client (UI)                         │
│  - Unit recruitment panels                                   │
│  - Unit information tooltips                                 │
│  - Queue management interface                                │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              Recruitment API (ajax/units/)                   │
│  - recruit.php: Handle training requests                     │
│  - get_recruitment_panel.php: Display available units        │
│  - cancel_recruitment.php: Cancel queued training            │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                    UnitManager                               │
│  - Load unit definitions from data/units.json + DB           │
│  - Apply world-specific multipliers                          │
│  - Validate prerequisites and caps                           │
│  - Manage recruitment queues                                 │
│  - Process unit completion                                   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                   BattleManager                              │
│  - Resolve combat with RPS modifiers                         │
│  - Apply support unit effects (auras, mantlets, healing)     │
│  - Calculate siege and conquest outcomes                     │
│  - Generate battle reports                                   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                  WorldManager                                │
│  - Provide world archetype settings                          │
│  - Enable/disable seasonal units                             │
│  - Configure feature flags (conquest, healers, etc.)         │
│  - Supply training time multipliers                          │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

**Unit Training Flow:**
1. Player selects unit and quantity in recruitment UI
2. Client sends POST to `ajax/units/recruit.php`
3. Recruitment API validates session and village ownership
4. UnitManager checks prerequisites (building levels, research, caps)
5. UnitManager validates resources and population capacity
6. ResourceManager deducts costs atomically
7. UnitManager adds entry to `unit_queue` table
8. Background job processes queue and adds completed units to `village_units`

**Combat Resolution Flow:**
1. Attack command arrives at target village
2. BattleManager loads attacker and defender unit compositions
3. BattleManager applies RPS modifiers based on unit matchups
4. BattleManager applies support effects (Banner aura, Mantlet protection)
5. BattleManager calculates casualties and distributes losses
6. BattleManager processes siege effects (wall damage, building damage)
7. BattleManager processes conquest effects (allegiance reduction, capture)
8. BattleManager applies healer recovery to wounded troops
9. BattleManager generates battle reports for both parties

## Components and Interfaces

### UnitManager Extensions

**New Methods:**

```php
/**
 * Get effective unit stats after world multipliers
 * @param int $unitTypeId Unit type ID
 * @param int $worldId World ID
 * @return array Effective stats with multipliers applied
 */
public function getEffectiveUnitStats(int $unitTypeId, int $worldId): array

/**
 * Check if unit is available based on world features
 * @param string $unitInternal Internal unit name
 * @param int $worldId World ID
 * @return bool True if unit is enabled
 */
public function isUnitAvailable(string $unitInternal, int $worldId): bool

/**
 * Get unit category/archetype for RPS calculations
 * @param int $unitTypeId Unit type ID
 * @return string Category: 'infantry', 'cavalry', 'ranged', 'siege', 'scout', 'support', 'conquest'
 */
public function getUnitCategory(int $unitTypeId): string

/**
 * Enforce per-account elite unit caps
 * @param int $userId User ID
 * @param string $unitInternal Internal unit name
 * @param int $count Requested count
 * @return array ['can_train' => bool, 'current' => int, 'max' => int]
 */
public function checkEliteUnitCap(int $userId, string $unitInternal, int $count): array

/**
 * Check seasonal unit availability window
 * @param string $unitInternal Internal unit name
 * @param int $timestamp Current timestamp
 * @return array ['available' => bool, 'start' => int|null, 'end' => int|null]
 */
public function checkSeasonalWindow(string $unitInternal, int $timestamp): array
```

**Modified Methods:**

- `loadUnitTypes()`: Filter units based on world feature flags (conquest, seasonal, healer)
- `checkRecruitRequirements()`: Add seasonal window checks and elite caps
- `recruitUnits()`: Add conquest unit coin/standard deduction and per-command caps
- `calculateRecruitmentTime()`: Apply world archetype training multipliers

### BattleManager Extensions

**New Methods:**

```php
/**
 * Apply rock-paper-scissors combat modifiers
 * @param array $attackerUnits Attacker unit composition
 * @param array $defenderUnits Defender unit composition
 * @param array $context Battle context (wall level, terrain, etc.)
 * @return array Modified attack/defense values
 */
private function applyRPSModifiers(array $attackerUnits, array $defenderUnits, array $context): array

/**
 * Calculate Banner Guard aura effects
 * @param array $defenderUnits Defender unit composition
 * @return array ['def_multiplier' => float, 'resolve_bonus' => int, 'tier' => int]
 */
private function calculateBannerAura(array $defenderUnits): array

/**
 * Calculate Mantlet protection for siege units
 * @param array $attackerUnits Attacker unit composition
 * @return float Ranged damage reduction multiplier (0.0 to 1.0)
 */
private function calculateMantletProtection(array $attackerUnits): float

/**
 * Apply War Healer recovery after battle
 * @param array $losses Unit losses from battle
 * @param array $survivors Surviving units
 * @param int $worldId World ID
 * @return array Recovered units by type
 */
private function applyHealerRecovery(array $losses, array $survivors, int $worldId): array

/**
 * Process conquest unit allegiance reduction
 * @param int $targetVillageId Target village ID
 * @param array $survivingConquestUnits Surviving nobles/standard bearers
 * @param bool $attackerWon Whether attacker won the battle
 * @return array ['allegiance_reduced' => int, 'new_allegiance' => int, 'captured' => bool]
 */
private function processConquestAllegiance(int $targetVillageId, array $survivingConquestUnits, bool $attackerWon): array
```

**Modified Methods:**

- `resolveBattle()`: Integrate RPS modifiers, support effects, and conquest mechanics
- `calculateCasualties()`: Apply mantlet protection to siege units
- `generateBattleReport()`: Include RPS modifiers, aura effects, and mantlet reductions

### WorldManager Extensions

**New Methods:**

```php
/**
 * Check if conquest units are enabled for world
 * @param int $worldId World ID
 * @return bool True if conquest units enabled
 */
public function isConquestUnitEnabled(int $worldId): bool

/**
 * Check if seasonal units are enabled for world
 * @param int $worldId World ID
 * @return bool True if seasonal units enabled
 */
public function isSeasonalUnitsEnabled(int $worldId): bool

/**
 * Check if healer units are enabled for world
 * @param int $worldId World ID
 * @return bool True if healer units enabled
 */
public function isHealerEnabled(int $worldId): bool

/**
 * Get training speed multiplier for unit archetype
 * @param string $archetype Unit archetype ('inf', 'cav', 'rng', 'siege')
 * @return float Training speed multiplier
 */
public function getTrainSpeedForArchetype(string $archetype): float

/**
 * Get healer recovery cap for world
 * @param int $worldId World ID
 * @return float Recovery percentage cap (0.0 to 1.0)
 */
public function getHealerRecoveryCap(int $worldId): float
```

## Data Models

### Unit Type Definition (data/units.json + unit_types table)

```json
{
  "pikeneer": {
    "name": "Pikeneer",
    "internal_name": "pikeneer",
    "category": "infantry",
    "building_type": "barracks",
    "required_building_level": 1,
    "required_tech": null,
    "required_tech_level": 0,
    "cost": {
      "wood": 50,
      "clay": 30,
      "iron": 10
    },
    "population": 1,
    "attack": 25,
    "defense": {
      "infantry": 65,
      "cavalry": 20,
      "ranged": 15
    },
    "speed_min_per_field": 18,
    "carry_capacity": 10,
    "training_time_base": 2700,
    "rps_bonuses": {
      "vs_cavalry": 1.4
    },
    "special_abilities": []
  },
  "banner_guard": {
    "name": "Banner Guard",
    "internal_name": "banner_guard",
    "category": "support",
    "building_type": "barracks",
    "required_building_level": 5,
    "required_tech": "banner_tactics",
    "required_tech_level": 4,
    "cost": {
      "wood": 150,
      "clay": 120,
      "iron": 80
    },
    "population": 2,
    "attack": 25,
    "defense": {
      "infantry": 45,
      "cavalry": 45,
      "ranged": 45
    },
    "speed_min_per_field": 24,
    "carry_capacity": 10,
    "training_time_base": 6600,
    "special_abilities": ["aura_defense_tier_1"],
    "aura_config": {
      "def_multiplier": 1.15,
      "resolve_bonus": 5
    }
  }
}
```

### Unit Queue Entry (unit_queue table)

```sql
CREATE TABLE unit_queue (
  id INT PRIMARY KEY AUTO_INCREMENT,
  village_id INT NOT NULL,
  unit_type_id INT NOT NULL,
  count INT NOT NULL,
  count_finished INT DEFAULT 0,
  started_at INT NOT NULL,
  finish_at INT NOT NULL,
  building_type VARCHAR(50) NOT NULL,
  FOREIGN KEY (village_id) REFERENCES villages(id),
  FOREIGN KEY (unit_type_id) REFERENCES unit_types(id),
  INDEX idx_village_finish (village_id, finish_at)
);
```

### Seasonal Unit Configuration (seasonal_units table)

```sql
CREATE TABLE seasonal_units (
  id INT PRIMARY KEY AUTO_INCREMENT,
  unit_internal_name VARCHAR(50) NOT NULL UNIQUE,
  event_name VARCHAR(100) NOT NULL,
  start_timestamp INT NOT NULL,
  end_timestamp INT NOT NULL,
  per_account_cap INT DEFAULT 50,
  is_active BOOLEAN DEFAULT TRUE,
  INDEX idx_active_window (is_active, start_timestamp, end_timestamp)
);
```

### Elite Unit Caps (elite_unit_caps table)

```sql
CREATE TABLE elite_unit_caps (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  unit_internal_name VARCHAR(50) NOT NULL,
  current_count INT DEFAULT 0,
  last_updated INT NOT NULL,
  UNIQUE KEY unique_user_unit (user_id, unit_internal_name),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### World Feature Flags (worlds table extensions)

```sql
ALTER TABLE worlds ADD COLUMN conquest_units_enabled BOOLEAN DEFAULT TRUE;
ALTER TABLE worlds ADD COLUMN seasonal_units_enabled BOOLEAN DEFAULT TRUE;
ALTER TABLE worlds ADD COLUMN healer_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE worlds ADD COLUMN train_multiplier_inf FLOAT DEFAULT 1.0;
ALTER TABLE worlds ADD COLUMN train_multiplier_cav FLOAT DEFAULT 1.0;
ALTER TABLE worlds ADD COLUMN train_multiplier_rng FLOAT DEFAULT 1.0;
ALTER TABLE worlds ADD COLUMN train_multiplier_siege FLOAT DEFAULT 1.0;
ALTER TABLE worlds ADD COLUMN healer_recovery_cap FLOAT DEFAULT 0.15;
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*


### Property Reflection

After analyzing all acceptance criteria, several properties can be consolidated to reduce redundancy:

- Unit unlock properties (1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 4.1, 4.2, 5.1, 5.2, 6.1, 6.2, 7.1, 7.2, 8.1, 8.2, 14.1) all test the same pattern: "unit available when prerequisites met". These can be combined into a single comprehensive property.
- Defense value properties (1.4, 1.5, 8.3) test stat relationships and can be combined into RPS validation properties.
- Cap enforcement properties (9.1, 9.2, 9.3, 9.5, 16.3) all test cap enforcement and can be unified.
- Error handling properties (9.4, 10.4, 15.4, 15.5, 17.1, 17.2, 17.3) test error codes and can be consolidated.
- Telemetry properties (18.1, 18.2, 18.3) test metric emission and can be combined.

### Correctness Properties

Property 1: Unit unlock prerequisites
*For any* unit type and village, when all required building levels and research nodes are met and world features enable the unit, then the unit should be available for training
**Validates: Requirements 1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 4.1, 4.2, 5.1, 5.2, 6.1, 6.2, 7.1, 7.2, 8.1, 8.2, 14.1, 15.1, 15.2**

Property 2: RPS defense bonuses
*For any* unit with RPS bonuses defined, when calculating defense against the specified damage type, the defense value should be higher than against other damage types by at least the configured multiplier
**Validates: Requirements 1.4, 3.4**

Property 3: Balanced unit stats
*For any* unit designated as "balanced" (like Shieldbearer), the variance between its three defense values should be within 20% of the mean defense value
**Validates: Requirements 1.5**

Property 4: Ranged wall bonus
*For any* defending force with ranged units, when wall level is greater than zero, the effective defense against infantry should be higher than when wall level is zero
**Validates: Requirements 2.3, 2.4**

Property 5: Unit category speed ordering
*For any* cavalry unit and any infantry unit, the cavalry unit's speed (minutes per field) should be less than the infantry unit's speed, and both should be less than any siege unit's speed
**Validates: Requirements 3.5**

Property 6: Scout intel revelation
*For any* scouting mission where attacking scouts survive, the battle report should include defender troop counts and resource levels (for Pathfinder) or building levels and queues (for Shadow Rider)
**Validates: Requirements 4.3, 4.4**

Property 7: Scout combat resolution
*For any* scouting mission where defending scouts outnumber attacking scouts, all attacking scouts should be killed and no intelligence should be revealed
**Validates: Requirements 4.5**

Property 8: Siege wall reduction
*For any* successful attack with surviving Battering Rams, the target village wall level should decrease by an amount proportional to the number of surviving rams
**Validates: Requirements 5.3**

Property 9: Catapult building damage
*For any* successful attack with surviving Stone Hurlers, at least one building in the target village should have reduced level or health
**Validates: Requirements 5.4**

Property 10: Banner aura application
*For any* defending force with Banner Guard units, the highest aura tier present should be applied to all defending troops, and multiple Banner Guards should not stack additively
**Validates: Requirements 6.3, 6.5**

Property 11: Healer recovery cap
*For any* battle with surviving War Healer units, the number of recovered troops should not exceed the configured per-battle recovery cap percentage of total losses
**Validates: Requirements 6.4**

Property 12: Conquest allegiance reduction
*For any* successful attack with surviving Noble or Standard Bearer units, the target village allegiance should decrease by the configured amount per surviving conquest unit
**Validates: Requirements 7.3**

Property 13: Village capture on zero allegiance
*For any* village where allegiance reaches zero or below, the village ownership should transfer to the attacker who reduced the allegiance
**Validates: Requirements 7.4**

Property 14: Conquest requires victory
*For any* attack with conquest units where the attacker loses, the target village allegiance should remain unchanged
**Validates: Requirements 7.5**

Property 15: Ranger anti-siege bonus
*For any* battle where Ranger units engage siege units, the Rangers should deal bonus damage to rams and catapults according to the configured multiplier
**Validates: Requirements 8.4**

Property 16: Unit cap enforcement
*For any* training request that would cause total unit count (stationed + in transit + queued) to exceed the configured cap, the request should be rejected with error code ERR_CAP
**Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5, 16.3**

Property 17: Seasonal window enforcement
*For any* seasonal unit, training requests should be accepted only when the current timestamp is between the configured start and end timestamps
**Validates: Requirements 10.1, 10.2, 10.4**

Property 18: World multiplier application
*For any* unit training request, the effective training time should equal base training time multiplied by world speed multiplier and archetype multiplier
**Validates: Requirements 11.1, 11.3**

Property 19: World cost multiplier application
*For any* unit training request on a world with cost multipliers, the effective resource costs should equal base costs multiplied by the world archetype multiplier
**Validates: Requirements 11.2, 11.4**

Property 20: Unit data completeness
*For any* unit loaded from data/units.json, all required fields (attack, defense values, speed, carry, population, costs, training time) should be present and positive
**Validates: Requirements 13.1, 13.3**

Property 21: RPS relationship validation
*For any* unit configuration change, units with anti-cavalry bonuses should have higher defense against cavalry than against other types, and similar relationships should hold for all RPS bonuses
**Validates: Requirements 13.2, 13.5**

Property 22: Mantlet protection application
*For any* attack with Mantlet units escorting siege units, the incoming ranged damage to siege units should be reduced by the configured percentage while mantlets survive
**Validates: Requirements 14.2, 14.3, 14.4**

Property 23: Conquest resource deduction
*For any* successful training request for Noble or Standard Bearer units, the village's coin/standard count should decrease by the number of units trained
**Validates: Requirements 15.3, 16.4**

Property 24: Feature flag enforcement
*For any* unit type that requires a world feature flag, training requests should be rejected with ERR_FEATURE_DISABLED when the feature is disabled for that world
**Validates: Requirements 15.5**

Property 25: Input validation
*For any* training request with zero, negative, or non-numeric unit counts, the request should be rejected with error code ERR_INPUT
**Validates: Requirements 17.1**

Property 26: Population capacity enforcement
*For any* training request where total population (current + queued + requested) exceeds farm capacity, the request should be rejected with error code ERR_POP
**Validates: Requirements 17.2**

Property 27: Resource availability enforcement
*For any* training request where village resources are insufficient for the total cost, the request should be rejected with error code ERR_RES
**Validates: Requirements 17.3**

Property 28: Concurrent cap enforcement
*For any* set of concurrent training requests that would collectively exceed unit caps, at least one request should be rejected to maintain the cap
**Validates: Requirements 17.4**

Property 29: Failure logging
*For any* failed training request, a log entry should be created containing correlation ID, player ID, unit type, and reason code
**Validates: Requirements 17.5**

Property 30: Telemetry emission
*For any* training request (successful or failed), metrics should be emitted including unit type, count, world ID, player ID, and outcome
**Validates: Requirements 18.1, 18.2, 18.3**

## Error Handling

### Error Codes and Responses

The system uses standardized error codes for all validation failures:

- **ERR_INPUT**: Invalid input (zero/negative counts, malformed data)
- **ERR_PREREQ**: Prerequisites not met (building level, research, coins/standards)
- **ERR_CAP**: Unit cap exceeded (per-village, per-account, or per-command)
- **ERR_RES**: Insufficient resources (wood, clay, iron)
- **ERR_POP**: Insufficient farm capacity
- **ERR_FEATURE_DISABLED**: Unit type disabled by world feature flags
- **ERR_SEASONAL_EXPIRED**: Seasonal unit outside availability window
- **ERR_SERVER**: Internal server error during processing

Each error response includes:
```json
{
  "success": false,
  "error": "Human-readable error message",
  "code": "ERR_CODE",
  "details": {
    // Context-specific details (e.g., current cap, missing resources)
  }
}
```

### Validation Order

To provide clear error messages and avoid unnecessary processing, validations are performed in this order:

1. **Session and ownership validation**: Verify user is logged in and owns the village
2. **Input validation**: Check for zero/negative counts, malformed data
3. **Feature flag validation**: Verify unit type is enabled for the world
4. **Seasonal window validation**: Check if seasonal units are within availability window
5. **Prerequisite validation**: Verify building levels and research requirements
6. **Resource validation**: Check coin/standard availability for conquest units
7. **Cap validation**: Enforce per-village, per-account, and per-command caps
8. **Population validation**: Verify farm capacity is sufficient
9. **Resource validation**: Check wood/clay/iron availability
10. **Atomic transaction**: Deduct resources and add to queue

### Graceful Degradation

- If unit data loading fails, the system falls back to a minimal unit set (basic infantry, cavalry, siege)
- If world settings are unavailable, the system uses default multipliers (1.0 for all archetypes)
- If telemetry logging fails, the system continues processing but logs the telemetry failure
- If battle report generation fails, the system still processes combat outcomes and logs the error

### Concurrency Handling

- Unit cap checks use database-level locking to prevent race conditions
- Resource deductions are performed in transactions to ensure atomicity
- Duplicate command detection uses a 10-second window to prevent replay attacks
- Queue processing uses row-level locks to prevent double-completion

## Testing Strategy

### Unit Testing

Unit tests verify individual components and methods:

**UnitManager Tests:**
- `testLoadUnitTypes()`: Verify all units load with complete data
- `testApplyWorldMultipliers()`: Verify training time and cost multipliers
- `testCheckPrerequisites()`: Verify building and research checks
- `testEnforceUnitCaps()`: Verify per-village and per-account caps
- `testSeasonalWindowCheck()`: Verify seasonal availability logic
- `testFeatureFlagFiltering()`: Verify disabled units are filtered

**BattleManager Tests:**
- `testApplyRPSModifiers()`: Verify cavalry vs ranged, pike vs cavalry bonuses
- `testCalculateBannerAura()`: Verify highest aura is selected, no stacking
- `testCalculateMantletProtection()`: Verify ranged damage reduction
- `testApplyHealerRecovery()`: Verify recovery cap enforcement
- `testProcessConquestAllegiance()`: Verify allegiance reduction and capture

**Validation Tests:**
- `testInputValidation()`: Verify zero/negative counts are rejected
- `testResourceValidation()`: Verify insufficient resources are detected
- `testPopulationValidation()`: Verify farm capacity is enforced
- `testConcurrentCapEnforcement()`: Verify caps hold under concurrent requests

### Property-Based Testing

Property-based tests verify universal properties across all inputs using a PHP property testing library (e.g., Eris or php-quickcheck):

**Property Test 1: Unit unlock prerequisites**
- **Feature: military-units-system, Property 1: Unit unlock prerequisites**
- Generate random building levels, research levels, and world feature flags
- For each unit type, verify availability matches prerequisite requirements
- Run 100 iterations with varied inputs

**Property Test 2: RPS defense bonuses**
- **Feature: military-units-system, Property 2: RPS defense bonuses**
- Generate random unit compositions with RPS bonuses
- Verify bonus defense values exceed base defense by configured multiplier
- Run 100 iterations with varied unit types

**Property Test 3: Unit cap enforcement**
- **Feature: military-units-system, Property 16: Unit cap enforcement**
- Generate random training requests with varying counts
- Verify requests exceeding caps are rejected with ERR_CAP
- Run 100 iterations with different cap scenarios

**Property Test 4: World multiplier application**
- **Feature: military-units-system, Property 18: World multiplier application**
- Generate random world multipliers and base training times
- Verify effective time = base time × world speed × archetype multiplier
- Run 100 iterations with varied multipliers

**Property Test 5: Conquest allegiance reduction**
- **Feature: military-units-system, Property 12: Conquest allegiance reduction**
- Generate random successful attacks with conquest units
- Verify allegiance decreases by configured amount per unit
- Run 100 iterations with varied conquest unit counts

**Property Test 6: Mantlet protection**
- **Feature: military-units-system, Property 22: Mantlet protection application**
- Generate random attacks with mantlets and ranged defenders
- Verify ranged damage to siege is reduced by configured percentage
- Run 100 iterations with varied mantlet counts

**Property Test 7: Healer recovery cap**
- **Feature: military-units-system, Property 11: Healer recovery cap**
- Generate random battles with healers and casualties
- Verify recovered troops ≤ cap percentage of losses
- Run 100 iterations with varied healer counts and loss amounts

**Property Test 8: Concurrent cap enforcement**
- **Feature: military-units-system, Property 28: Concurrent cap enforcement**
- Generate concurrent training requests that collectively exceed caps
- Verify at least one request is rejected to maintain cap
- Run 100 iterations with varied concurrency patterns

**Property Test 9: Input validation**
- **Feature: military-units-system, Property 25: Input validation**
- Generate random invalid inputs (zero, negative, non-numeric)
- Verify all invalid inputs are rejected with ERR_INPUT
- Run 100 iterations with varied invalid inputs

**Property Test 10: Telemetry emission**
- **Feature: military-units-system, Property 30: Telemetry emission**
- Generate random training requests (successful and failed)
- Verify telemetry log contains required fields for each request
- Run 100 iterations with varied outcomes

### Integration Testing

Integration tests verify end-to-end workflows:

- **Training workflow**: Submit training request → verify queue entry → process queue → verify units added
- **Combat workflow**: Send attack with RPS matchups → verify modifiers applied → verify battle report accuracy
- **Conquest workflow**: Train nobles → attack with nobles → verify allegiance reduction → verify capture
- **Support workflow**: Train Banner Guards → defend with aura → verify defense boost in report
- **Seasonal workflow**: Enable seasonal unit → train within window → expire unit → verify training blocked

### Performance Testing

- **Load test**: Simulate 1000 concurrent training requests to verify cap enforcement holds
- **Queue processing**: Verify queue processor handles 10,000 queued units within 1 second
- **Battle resolution**: Verify RPS modifier calculations add < 10ms to battle resolution time
- **Telemetry volume**: Verify telemetry logging handles 100 requests/second without degradation

## Implementation Notes

### Database Schema Changes

**New Tables:**
```sql
CREATE TABLE seasonal_units (
  id INT PRIMARY KEY AUTO_INCREMENT,
  unit_internal_name VARCHAR(50) NOT NULL UNIQUE,
  event_name VARCHAR(100) NOT NULL,
  start_timestamp INT NOT NULL,
  end_timestamp INT NOT NULL,
  per_account_cap INT DEFAULT 50,
  is_active BOOLEAN DEFAULT TRUE,
  INDEX idx_active_window (is_active, start_timestamp, end_timestamp)
);

CREATE TABLE elite_unit_caps (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  unit_internal_name VARCHAR(50) NOT NULL,
  current_count INT DEFAULT 0,
  last_updated INT NOT NULL,
  UNIQUE KEY unique_user_unit (user_id, unit_internal_name),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

**Table Modifications:**
```sql
ALTER TABLE worlds ADD COLUMN conquest_units_enabled BOOLEAN DEFAULT TRUE;
ALTER TABLE worlds ADD COLUMN seasonal_units_enabled BOOLEAN DEFAULT TRUE;
ALTER TABLE worlds ADD COLUMN healer_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE worlds ADD COLUMN train_multiplier_inf FLOAT DEFAULT 1.0;
ALTER TABLE worlds ADD COLUMN train_multiplier_cav FLOAT DEFAULT 1.0;
ALTER TABLE worlds ADD COLUMN train_multiplier_rng FLOAT DEFAULT 1.0;
ALTER TABLE worlds ADD COLUMN train_multiplier_siege FLOAT DEFAULT 1.0;
ALTER TABLE worlds ADD COLUMN healer_recovery_cap FLOAT DEFAULT 0.15;

ALTER TABLE unit_types ADD COLUMN category VARCHAR(20) DEFAULT 'infantry';
ALTER TABLE unit_types ADD COLUMN rps_bonuses JSON DEFAULT NULL;
ALTER TABLE unit_types ADD COLUMN special_abilities JSON DEFAULT NULL;
ALTER TABLE unit_types ADD COLUMN aura_config JSON DEFAULT NULL;
```

### Configuration Files

**data/units.json**: Complete unit definitions with all 16+ units
**config/rps_modifiers.php**: RPS multiplier constants
**config/unit_caps.php**: Default cap values per unit category

### Migration Strategy

1. **Phase 1**: Add new database columns and tables
2. **Phase 2**: Populate unit_types table with complete unit roster
3. **Phase 3**: Deploy UnitManager extensions with feature flags disabled
4. **Phase 4**: Deploy BattleManager extensions with RPS modifiers
5. **Phase 5**: Enable feature flags per world gradually
6. **Phase 6**: Monitor telemetry and adjust balance as needed

### Backward Compatibility

- Existing units continue to function with default RPS modifiers (1.0)
- Worlds without feature flags default to all units enabled
- Battle reports include RPS modifiers only when applied (no UI breakage)
- Unit data version tracking allows rollback if issues arise

### Performance Considerations

- Unit type cache loaded once per request, not per operation
- RPS modifier calculations use lookup tables, not dynamic computation
- Cap checks use indexed queries with row-level locking
- Telemetry uses async logging to avoid blocking gameplay paths

### Security Considerations

- All training requests validate session and village ownership
- Concurrent requests use database transactions to prevent race conditions
- Duplicate command detection prevents replay attacks
- Rate limiting prevents training spam and automation
- Input validation prevents SQL injection and XSS attacks

### Monitoring and Alerting

- Track training request volume by unit type and world
- Alert on cap hit rates exceeding 10% of requests
- Alert on seasonal unit training after expiry (indicates bug)
- Alert on elite unit hoarding (> 2x expected per-account totals)
- Track RPS modifier application frequency to validate balance
- Monitor battle resolution latency to detect performance regressions

