# Battle Resolution System Design

## Overview

The Battle Resolution System is the deterministic combat engine for a real-time medieval tribal war MMO. When an attack command arrives at a target village, the system resolves combat by calculating casualties, siege effects, resource plunder, and allegiance changes based on troop compositions, defensive structures, and world-specific modifiers.

The system is designed to be:
- **Deterministic**: Identical inputs produce identical outputs across all servers
- **Fair**: Balanced through morale, luck, and anti-stacking mechanics
- **Strategic**: Multiple modifiers create tactical depth
- **Performant**: Capable of processing hundreds of battles per minute
- **Observable**: Comprehensive telemetry and detailed battle reports

## Architecture

### High-Level Components

```
┌─────────────────────────────────────────────────────────────┐
│                     Command Processor                        │
│  (Validates, sorts, and queues incoming attack commands)    │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                   Battle Resolver Core                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Combat     │  │    Siege     │  │   Plunder    │     │
│  │  Calculator  │  │   Handler    │  │  Calculator  │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  Conquest    │  │   Modifier   │  │    Report    │     │
│  │   Handler    │  │   Applier    │  │  Generator   │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                  Persistence Layer                           │
│  (Updates village state, creates reports, logs metrics)     │
└─────────────────────────────────────────────────────────────┘
```

### Design Rationale

**Separation of Concerns**: The system is divided into distinct components (combat, siege, plunder, conquest) to maintain clarity and testability.

**Stateless Core**: The Battle Resolver Core is stateless and performs pure calculations, making it easy to test and reason about.

**Modifier Pipeline**: All modifiers (morale, luck, wall, environment) are applied in a consistent order to ensure deterministic outcomes.


## Components and Interfaces

### 1. Command Processor

**Responsibility**: Validate, sort, and queue incoming attack commands.

**Interface**:
```php
class CommandProcessor {
    public function validateCommand(Command $cmd): ValidationResult;
    public function sortCommands(array $commands): array;
    public function enforceRateLimits(int $playerId, int $targetId): bool;
    public function checkMinimumPopulation(Command $cmd): bool;
}
```

**Key Operations**:
- Validate command structure and troop counts
- Sort commands by: arrival timestamp → sequence number → type priority → command ID
- Enforce per-player and per-target rate limits
- Check minimum population requirements
- Detect and tag fake attacks

### 2. Combat Calculator

**Responsibility**: Calculate battle outcomes based on troop compositions and modifiers.

**Interface**:
```php
class CombatCalculator {
    public function calculateOffensivePower(array $units, array $defenderClassShares): float;
    public function calculateDefensivePower(array $units, array $attackerClassShares): float;
    public function calculateCasualties(float $ratio, array $attackerUnits, array $defenderUnits): CasualtyResult;
    public function determineWinner(float $ratio): string;
}
```

**Key Formulas**:

**Offensive Power**:
```
totalOff = Σ(unit.off × count × classMultiplier) × morale × luck
```

**Defensive Power**:
```
effectiveDef = Σ(unit.defVsClass × count) × wall × environment
```

**Battle Ratio**:
```
ratio = totalOff / effectiveDef
```

**Casualties**:
- If ratio ≥ 1 (attacker wins):
  - Defender loss factor: 1.0 (total loss)
  - Attacker loss factor: 1 / (ratio^1.5)
- If ratio < 1 (defender holds):
  - Attacker loss factor: 1.0 (total loss)
  - Defender loss factor: ratio^1.5

The exponent of 1.5 creates a "square-root mechanic" that softens casualty edges and prevents extreme outcomes.


### 3. Modifier Applier

**Responsibility**: Apply all combat modifiers in the correct order.

**Interface**:
```php
class ModifierApplier {
    public function calculateMorale(int $defenderPoints, int $attackerPoints): float;
    public function generateLuck(array $worldConfig): float;
    public function calculateWallMultiplier(int $wallLevel): float;
    public function applyEnvironmentModifiers(float $off, float $def, array $worldConfig, DateTime $battleTime): array;
    public function calculateOverstackPenalty(int $defendingPopulation, array $worldConfig): float;
}
```

**Modifier Application Order**:
1. Overstack penalty (if enabled)
2. Wall multiplier
3. Environment modifiers (night, terrain, weather)
4. Morale (applied to attacker offense)
5. Luck (applied to attacker offense)

**Key Formulas**:

**Morale**:
```
morale = clamp(0.5, 1.5, 0.3 + defenderPoints / attackerPoints)
```

**Luck**:
```
luck = random(0.75, 1.25)  // ±25% variance
```

**Wall**:
```
// Levels 1-10
wallMult = 1.037^wallLevel

// Levels 11+
wallMult = 1.037^10 × 1.05^(wallLevel - 10)
```

**Overstack Penalty**:
```
if defendingPop > threshold:
    penalty = max(minMult, 1 - penaltyRate × max(0, (defendingPop - threshold) / threshold))
    effectiveDef *= penalty
```

**Night Bonus**:
```
if nightBonusEnabled and isNightTime:
    effectiveDef *= 1.5
```

### 4. Siege Handler

**Responsibility**: Calculate and apply siege effects (rams and catapults).

**Interface**:
```php
class SiegeHandler {
    public function applyRamDamage(int $wallLevel, int $survivingRams, array $worldConfig): int;
    public function applyCatapultDamage(string $building, int $level, int $survivingCatapults, array $worldConfig): int;
    public function selectRandomBuilding(int $villageId): string;
}
```

**Key Formulas**:

**Ram Damage**:
```
ramsPerLevel = max(1, ceil((2 + wallLevel × 0.5) / worldSpeed))
wallDrop = floor(survivingRams / ramsPerLevel)
newWallLevel = max(0, wallLevel - wallDrop)
```

**Catapult Damage** (only if attacker wins):
```
catapultsPerLevel = max(1, ceil((8 + buildingLevel × 2) / worldSpeed))
levelsDrop = floor(survivingCatapults / catapultsPerLevel)
newBuildingLevel = max(0, buildingLevel - levelsDrop)
```


### 5. Plunder Calculator

**Responsibility**: Calculate resource plunder based on surviving units and vault protection.

**Interface**:
```php
class PlunderCalculator {
    public function calculateAvailableLoot(int $villageId, int $vaultLevel): array;
    public function calculateCarryCapacity(array $survivingUnits): int;
    public function distributePlunder(array $resources, int $capacity): array;
}
```

**Key Formulas**:

**Vault Protection**:
```
protectedAmount = totalResources × vaultProtectionRate
availableLoot = max(0, totalResources - protectedAmount)
```

**Carry Capacity**:
```
totalCapacity = Σ(unit.carry × count)
// Note: Siege units (rams, catapults) and conquest units (nobles) have carry = 0
```

**Plunder Distribution**:
```
if totalLoot > capacity:
    plunder = capacity
else:
    plunder = totalLoot

// Distribute proportionally across resource types
woodPlundered = floor(plunder × (wood / totalLoot))
clayPlundered = floor(plunder × (clay / totalLoot))
ironPlundered = plunder - woodPlundered - clayPlundered
```

### 6. Conquest Handler

**Responsibility**: Manage village allegiance reduction and ownership transfer.

**Interface**:
```php
class ConquestHandler {
    public function reduceAllegiance(int $villageId, int $survivingConquestUnits, array $worldConfig): int;
    public function checkCaptureConditions(int $villageId, int $newAllegiance): bool;
    public function transferOwnership(int $villageId, int $newOwnerId): void;
    public function applyPostCaptureAllegiance(int $villageId, array $worldConfig): void;
}
```

**Key Logic**:

**Allegiance Reduction**:
```
if attackerWins and conquestUnits > 0:
    allegianceDrop = conquestUnits × allegianceDropPerUnit
    newAllegiance = max(0, currentAllegiance - allegianceDrop)
```

**Capture Conditions**:
```
if newAllegiance <= 0:
    transferOwnership()
    applyPostCaptureAllegiance()  // Prevents immediate re-conquest
```

**Anti-Snipe Protection**:
- If conquest cooldown is active, block allegiance reduction
- Return reason code: ERR_CONQUEST_COOLDOWN


### 7. Report Generator

**Responsibility**: Generate detailed battle reports for both attacker and defender.

**Interface**:
```php
class ReportGenerator {
    public function generateReport(BattleResult $result, string $perspective): Report;
    public function includeIntelligence(Report $report, bool $scoutsSurvived): Report;
    public function redactIntelligence(Report $report): Report;
}
```

**Report Structure**:
```php
class Report {
    public string $battleId;
    public DateTime $timestamp;
    public string $outcome;  // 'attacker_win' | 'defender_hold'
    
    // Participants
    public int $attackerId;
    public int $defenderId;
    public array $attackerVillage;
    public array $defenderVillage;
    
    // Modifiers
    public float $luck;
    public float $morale;
    public float $wallMultiplier;
    public ?float $nightBonus;
    public ?float $overstackPenalty;
    public ?array $environmentModifiers;
    
    // Troops
    public array $attackerSent;
    public array $attackerLost;
    public array $attackerSurvivors;
    public array $defenderPresent;
    public array $defenderLost;
    public array $defenderSurvivors;
    
    // Siege Effects
    public ?array $wallChange;  // ['start' => int, 'end' => int]
    public ?array $buildingDamage;  // ['target' => string, 'start' => int, 'end' => int]
    
    // Plunder
    public ?array $plunder;  // ['wood' => int, 'clay' => int, 'iron' => int]
    public ?array $vaultProtection;
    
    // Conquest
    public ?array $allegianceChange;  // ['start' => int, 'end' => int, 'captured' => bool]
    
    // Intelligence (only if scouts survive)
    public ?array $defenderIntel;
}
```

**Intelligence Redaction Rules**:
- If attacker scouts survive: Include full defender intelligence
- If attacker scouts die: Redact defender troop counts and building levels
- Defender always sees full attacker information


## Data Models

### Command

```php
class Command {
    public int $commandId;
    public int $attackerId;
    public int $defenderId;
    public int $sourceVillageId;
    public int $targetVillageId;
    public string $commandType;  // 'attack', 'support', 'raid', 'siege'
    public array $units;  // ['unit_type' => count]
    public DateTime $sentAt;
    public DateTime $arrivalAt;
    public int $sequenceNumber;
    public ?string $targetBuilding;
}
```

### BattleResult

```php
class BattleResult {
    public string $outcome;  // 'attacker_win' | 'defender_hold'
    public float $luck;
    public float $morale;
    public float $ratio;
    
    public array $attackerUnits;  // ['sent' => [], 'lost' => [], 'survivors' => []]
    public array $defenderUnits;  // ['present' => [], 'lost' => [], 'survivors' => []]
    
    public array $siegeEffects;  // ['wall' => [], 'building' => []]
    public ?array $plunder;
    public ?array $allegianceChange;
    
    public array $modifiers;  // All applied modifiers for reporting
}
```

### WorldConfig

```php
class WorldConfig {
    public float $speed;
    public bool $moraleEnabled;
    public bool $luckEnabled;
    public float $luckMin;
    public float $luckMax;
    
    public bool $nightBonusEnabled;
    public int $nightStartHour;
    public int $nightEndHour;
    public float $nightDefenseMultiplier;
    
    public bool $overstackEnabled;
    public int $overstackThreshold;
    public float $overstackPenaltyRate;
    public float $overstackMinMultiplier;
    
    public bool $terrainEnabled;
    public bool $weatherEnabled;
    
    public int $allegianceDropPerNoble;
    public int $postCaptureAllegiance;
    
    public int $minAttackPopulation;
    public int $fakeAttackThreshold;
    
    public array $rateLimits;  // ['per_player' => int, 'per_target' => int, 'window_seconds' => int]
}
```

### UnitData

```php
class UnitData {
    public string $internalName;
    public int $offense;
    public array $defense;  // ['gen' => int, 'cav' => int, 'arc' => int]
    public int $carry;
    public int $population;
    public string $class;  // 'infantry' | 'cavalry' | 'archer'
    public bool $isSiege;
    public bool $isConquest;
}
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Combat Mechanics Properties

**Property 1: Force Merging Completeness**
*For any* garrison and support arrays, merging them should result in a combined array containing all units from both sources with correct counts.
**Validates: Requirements 1.1**

**Property 2: Power Calculation Correctness**
*For any* unit composition, the calculated offensive and defensive power should match the sum of individual unit contributions multiplied by applicable modifiers.
**Validates: Requirements 1.2**

**Property 3: Casualty Proportionality**
*For any* battle with non-zero forces on both sides, casualties should follow the ratio^1.5 formula where ratio = totalOff / effectiveDef.
**Validates: Requirements 1.3**

**Property 4: Unit Conservation**
*For any* battle, for each unit type: sent - lost = survivors.
**Validates: Requirements 1.4**

**Property 5: Post-Battle Unit Movement**
*For any* attack command, surviving units should return to origin; for any support command, surviving units should remain stationed.
**Validates: Requirements 1.5**

### Defensive Structure Properties

**Property 6: Wall Multiplier Application**
*For any* wall level, the defense multiplier should be calculated as 1.037^level for levels 1-10, and 1.037^10 × 1.05^(level-10) for levels 11+.
**Validates: Requirements 2.1**

**Property 7: Ram Damage Determinism**
*For any* number of surviving rams and wall level, the wall reduction should be floor(survivingRams / ramsPerLevel) where ramsPerLevel increases with wall level.
**Validates: Requirements 2.2**

**Property 8: Catapult Damage on Victory**
*For any* attacker victory with surviving catapults, the targeted building should be damaged by floor(survivingCatapults / catapultsPerLevel) levels.
**Validates: Requirements 2.3**

**Property 9: Wall Persistence**
*For any* battle that changes wall level, the new wall level should be persisted to the village record.
**Validates: Requirements 2.5**


### Modifier Properties

**Property 10: Morale Calculation**
*For any* attacker and defender point values, morale should be clamp(0.5, 1.5, 0.3 + defenderPoints / attackerPoints).
**Validates: Requirements 3.1**

**Property 11: Luck Bounds**
*For any* battle with luck enabled, the luck value should be within the configured range [luckMin, luckMax].
**Validates: Requirements 3.2**

**Property 12: Modifier Application Order**
*For any* battle, the final combat power should be calculated as: base × morale × luck.
**Validates: Requirements 3.3**

**Property 13: Night Bonus Application**
*For any* battle during night hours with night bonus enabled, defender defense should be multiplied by the configured night multiplier.
**Validates: Requirements 7.1**

**Property 14: Terrain Modifier Application**
*For any* battle with terrain modifiers enabled, the terrain-specific multipliers should be applied based on village tile type.
**Validates: Requirements 7.2**

**Property 15: Weather Modifier Application**
*For any* battle with weather modifiers enabled, weather effects should be applied to combat calculations.
**Validates: Requirements 7.3**

**Property 16: Environment Modifier Ordering**
*For any* battle with multiple environment modifiers, they should be applied in the configured order.
**Validates: Requirements 7.4**

**Property 17: Overstack Penalty Formula**
*For any* defending population exceeding the threshold, the penalty should be max(minMult, 1 - penaltyRate × max(0, (defPop - threshold) / threshold)).
**Validates: Requirements 8.1, 8.2**

**Property 18: Overstack Modifier Ordering**
*For any* battle with overstack penalties, they should be applied before wall and environment modifiers.
**Validates: Requirements 8.3**

### Plunder Properties

**Property 19: Vault Protection**
*For any* village resources and vault level, the protected amount should be totalResources × vaultProtectionRate.
**Validates: Requirements 4.1, 4.2**

**Property 20: Carry Capacity Limit**
*For any* attacker victory, the plundered amount should never exceed the total carry capacity of surviving plunder-capable units.
**Validates: Requirements 4.3**

**Property 21: Plunder Determinism**
*For any* identical battle inputs, the plunder distribution should be identical across all executions.
**Validates: Requirements 4.4**

**Property 22: Siege Unit Carry Capacity**
*For any* siege units (rams, catapults) or conquest units (nobles), the carry capacity should be zero.
**Validates: Requirements 4.5**


### Conquest Properties

**Property 23: Allegiance Reduction on Victory**
*For any* attacker victory with surviving conquest units, allegiance should be reduced by conquestUnits × allegianceDropPerUnit.
**Validates: Requirements 5.1**

**Property 24: Ownership Transfer Threshold**
*For any* village with allegiance <= 0, ownership should transfer to the attacker.
**Validates: Requirements 5.2**

**Property 25: Post-Capture Allegiance Floor**
*For any* captured village, the allegiance should be set to the configured post-capture floor.
**Validates: Requirements 5.3**

**Property 26: No Allegiance Drop on Loss**
*For any* attacker loss with conquest units, allegiance should not be reduced.
**Validates: Requirements 5.4**

**Property 27: Conquest Cooldown Enforcement**
*For any* village with active conquest cooldown, allegiance reduction should be blocked and return ERR_CONQUEST_COOLDOWN.
**Validates: Requirements 5.5**

### Report Properties

**Property 28: Report Generation**
*For any* battle, exactly two reports should be generated (one for attacker, one for defender).
**Validates: Requirements 6.1**

**Property 29: Report Troop Completeness**
*For any* battle report, it should include sent, lost, and survived counts for all unit types on both sides.
**Validates: Requirements 6.2**

**Property 30: Report Modifier Completeness**
*For any* battle report, it should include all applied modifiers (morale, luck, wall, environment).
**Validates: Requirements 6.3**

**Property 31: Report Siege Tracking**
*For any* battle with siege effects, the report should include wall and building changes.
**Validates: Requirements 6.4**

**Property 32: Report Plunder Tracking**
*For any* battle with plunder, the report should include plunder amounts and vault protection details.
**Validates: Requirements 6.5**

**Property 33: Report Conquest Tracking**
*For any* battle with conquest units, the report should include allegiance changes.
**Validates: Requirements 6.6**

**Property 34: Scout Intelligence Inclusion**
*For any* attacker victory with surviving scouts, the attacker report should include full defender intelligence.
**Validates: Requirements 6.7**

**Property 35: Scout Intelligence Redaction**
*For any* battle where attacker scouts die, the attacker report should have defender intelligence redacted.
**Validates: Requirements 6.8**


### Command Processing Properties

**Property 36: Command Sorting Determinism**
*For any* set of commands arriving at the same tick, they should be sorted by: arrival timestamp → sequence number → type priority → command ID.
**Validates: Requirements 9.1**

**Property 37: Support Timing Inclusion**
*For any* support command with arrival_at <= attack arrival_at, it should be included in the defending forces.
**Validates: Requirements 9.2**

**Property 38: Sequential Processing Order**
*For any* sorted command list, battles should be processed in the exact sorted order.
**Validates: Requirements 9.3**

**Property 39: Battle Determinism**
*For any* identical battle inputs, the outputs should be identical across all executions and servers.
**Validates: Requirements 9.4**

**Property 40: Command Spacing Enforcement**
*For any* commands violating minimum spacing rules, the later command should be bumped to the next tick with ERR_SPACING returned.
**Validates: Requirements 9.5**

### Rate Limiting Properties

**Property 41: Per-Player Rate Limit**
*For any* player sending more commands than the per-player limit within the time window, subsequent commands should be rejected.
**Validates: Requirements 10.1**

**Property 42: Per-Target Rate Limit**
*For any* player targeting the same village more than the per-target limit within the time window, subsequent commands should be rejected.
**Validates: Requirements 10.2**

**Property 43: Rate Limit Error Response**
*For any* rate limit violation, the system should return an error code with retry-after time.
**Validates: Requirements 10.3**

**Property 44: Minimum Population Enforcement**
*For any* attack with total population below the minimum threshold, the command should be rejected.
**Validates: Requirements 10.4**

**Property 45: Fake Attack Tagging**
*For any* attack with population below the fake attack threshold, it should be tagged for intelligence filtering.
**Validates: Requirements 10.5**

### Edge Case Properties

**Property 46: Shield Protection**
*For any* village with active emergency shield, incoming attacks should be bounced without combat and return ERR_PROTECTED.
**Validates: Requirements 11.1**

**Property 47: Input Validation**
*For any* command with negative or invalid troop counts, it should be rejected with ERR_VALIDATION.
**Validates: Requirements 11.2**

**Property 48: Modifier Clamping**
*For any* calculated modifier, if it falls outside valid ranges, it should be clamped to configured min/max values.
**Validates: Requirements 11.3**

**Property 49: Offline Defender Handling**
*For any* defender who is offline, combat should process normally without special handling.
**Validates: Requirements 11.5**


### Telemetry Properties

**Property 50: Metrics Emission**
*For any* battle, metrics should be emitted including resolver latency, battle outcome, and modifier flags.
**Validates: Requirements 12.1**

**Property 51: Correlation ID Logging**
*For any* battle, a correlation ID should be logged linking all related operations.
**Validates: Requirements 12.2**

**Property 52: Error Context Logging**
*For any* error, the log should include context with command IDs, player IDs, and reason codes.
**Validates: Requirements 12.3**

**Property 53: Rate Limit Counter Increment**
*For any* rate limit violation, the rate limit error counter should be incremented.
**Validates: Requirements 12.4**

**Property 54: Timing Data Recording**
*For any* completed battle, timing data should be recorded for performance analysis.
**Validates: Requirements 12.5**

## Error Handling

### Error Codes

The system uses structured error codes for all failure scenarios:

```php
class BattleErrorCode {
    const ERR_PROTECTED = 'ERR_PROTECTED';           // Village has active shield
    const ERR_SAFE_ZONE = 'ERR_SAFE_ZONE';           // Village in safe zone
    const ERR_MIN_POP = 'ERR_MIN_POP';               // Below minimum population
    const ERR_RATE_LIMIT = 'ERR_RATE_LIMIT';         // Rate limit exceeded
    const ERR_SPACING = 'ERR_SPACING';               // Commands too close
    const ERR_VALIDATION = 'ERR_VALIDATION';         // Invalid input
    const ERR_CONQUEST_COOLDOWN = 'ERR_CONQUEST_COOLDOWN';  // Conquest on cooldown
    const ERR_CALCULATION = 'ERR_CALCULATION';       // Calculation error
}
```

### Error Handling Strategy

**Pre-Battle Validation**:
- Validate all inputs before processing
- Return early with error codes for invalid states
- Log validation failures with context

**During Battle**:
- Clamp all modifiers to valid ranges
- Handle division by zero gracefully
- Use safe defaults for missing data

**Post-Battle**:
- Verify all state changes before persisting
- Rollback on persistence failures
- Emit error metrics for monitoring

**Graceful Degradation**:
- If calculation errors occur, log with correlation ID
- Return safe default outcome (defender holds)
- Alert operators for investigation


## Testing Strategy

### Dual Testing Approach

The battle resolution system requires both unit testing and property-based testing to ensure correctness:

**Unit Tests** verify:
- Specific battle scenarios (attacker wins, defender holds)
- Edge cases (zero troops, maximum values)
- Error conditions (invalid inputs, shields)
- Integration points (database persistence, report generation)

**Property-Based Tests** verify:
- Universal properties hold across all inputs
- Formulas are correctly implemented
- Determinism across executions
- Bounds and constraints are enforced

Together, unit tests catch concrete bugs while property tests verify general correctness.

### Property-Based Testing Framework

**Framework**: PHPUnit with Eris (PHP property-based testing library)

**Configuration**: Each property test should run a minimum of 100 iterations to ensure adequate coverage of the input space.

**Test Tagging**: Each property-based test must include a comment explicitly referencing the correctness property from this design document using the format:
```php
/**
 * Feature: battle-resolution, Property 1: Force Merging Completeness
 */
```

### Unit Testing Approach

**Test Organization**:
- Group tests by component (CombatCalculator, SiegeHandler, etc.)
- Use descriptive test names that explain the scenario
- Include both success and failure cases

**Test Coverage**:
- All public methods in each component
- All error codes and edge cases
- Integration with database and external systems

**Example Unit Tests**:
- `testAttackerWinsWithHighOffense()`
- `testDefenderHoldsWithStrongWall()`
- `testRamsDamageWall()`
- `testCatapultsDamageBuilding()`
- `testShieldBlocksAttack()`
- `testRateLimitRejectsCommand()`

### Test Data Generation

**For Property Tests**:
- Generate random unit compositions within valid ranges
- Generate random modifier values within configured bounds
- Generate random world configurations
- Ensure generated data represents realistic game scenarios

**For Unit Tests**:
- Use fixed, reproducible test data
- Include boundary values (0, 1, max)
- Include typical game scenarios
- Include edge cases and error conditions

### Performance Testing

**Load Testing**:
- Simulate 1000+ concurrent battles
- Measure resolver latency (target: <50ms p99)
- Verify no memory leaks over extended runs

**Stress Testing**:
- Test with maximum unit counts
- Test with extreme modifier values
- Test with many simultaneous commands


## Implementation Considerations

### Performance Optimizations

**Unit Data Caching**:
- Load unit data once at startup
- Cache in memory for fast access
- No database queries during battle resolution

**Batch Processing**:
- Process multiple battles in a single transaction
- Minimize database round-trips
- Use prepared statements for persistence

**Calculation Efficiency**:
- Pre-calculate common values (wall multipliers, morale curves)
- Use integer math where possible
- Avoid unnecessary floating-point operations

### Determinism Requirements

**Random Number Generation**:
- Use seeded RNG for reproducible luck values
- Store seed in battle record for debugging
- Ensure same seed produces same luck across platforms

**Floating-Point Precision**:
- Use consistent rounding modes
- Document precision requirements
- Test cross-platform consistency

**Sorting Stability**:
- Use stable sort for command ordering
- Break ties deterministically
- Document tie-breaking rules

### Scalability Considerations

**Horizontal Scaling**:
- Battle resolver is stateless and can run on multiple servers
- Use distributed locking for village state updates
- Partition battles by world or region

**Database Optimization**:
- Index on village_id, player_id, arrival_at
- Use connection pooling
- Consider read replicas for report queries

**Monitoring and Alerting**:
- Track resolver latency (p50, p95, p99)
- Alert on error rate spikes
- Monitor rate limit hit rates

### Security Considerations

**Input Validation**:
- Validate all command inputs before processing
- Reject commands with impossible values
- Check player ownership of source village

**Rate Limiting**:
- Implement sliding window rate limits
- Use distributed rate limiting (Redis)
- Return retry-after headers

**Anti-Cheat**:
- Log all battles with correlation IDs
- Detect suspicious patterns (impossible timing, coordinated attacks)
- Flag anomalies for review

### Observability

**Metrics to Track**:
- Battles processed per second
- Average resolver latency
- Error rate by error code
- Modifier distribution (luck, morale)
- Siege success rate
- Conquest success rate

**Logging Strategy**:
- Log all battles with correlation ID
- Log all errors with full context
- Log rate limit violations
- Use structured logging (JSON)

**Tracing**:
- Trace command flow from arrival to report generation
- Include timing for each phase
- Link related operations with correlation ID


## Database Schema

### Tables

**commands**
```sql
CREATE TABLE commands (
    command_id INTEGER PRIMARY KEY,
    attacker_id INTEGER NOT NULL,
    defender_id INTEGER NOT NULL,
    source_village_id INTEGER NOT NULL,
    target_village_id INTEGER NOT NULL,
    command_type TEXT NOT NULL,  -- 'attack', 'support', 'raid', 'siege'
    units TEXT NOT NULL,  -- JSON: {"axe": 100, "light": 50}
    sent_at DATETIME NOT NULL,
    arrival_at DATETIME NOT NULL,
    sequence_number INTEGER NOT NULL,
    target_building TEXT,
    status TEXT DEFAULT 'pending',  -- 'pending', 'processing', 'completed', 'failed'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attacker_id) REFERENCES users(user_id),
    FOREIGN KEY (defender_id) REFERENCES users(user_id),
    FOREIGN KEY (source_village_id) REFERENCES villages(village_id),
    FOREIGN KEY (target_village_id) REFERENCES villages(village_id)
);

CREATE INDEX idx_commands_arrival ON commands(arrival_at, sequence_number);
CREATE INDEX idx_commands_target ON commands(target_village_id, arrival_at);
CREATE INDEX idx_commands_attacker ON commands(attacker_id, sent_at);
```

**battle_reports**
```sql
CREATE TABLE battle_reports (
    report_id INTEGER PRIMARY KEY,
    command_id INTEGER NOT NULL,
    battle_id TEXT NOT NULL,  -- Correlation ID
    recipient_id INTEGER NOT NULL,
    perspective TEXT NOT NULL,  -- 'attacker' | 'defender'
    outcome TEXT NOT NULL,  -- 'attacker_win' | 'defender_hold'
    
    -- Participants
    attacker_id INTEGER NOT NULL,
    defender_id INTEGER NOT NULL,
    attacker_village_id INTEGER NOT NULL,
    defender_village_id INTEGER NOT NULL,
    
    -- Modifiers
    luck REAL NOT NULL,
    morale REAL NOT NULL,
    wall_multiplier REAL NOT NULL,
    night_bonus REAL,
    overstack_penalty REAL,
    environment_modifiers TEXT,  -- JSON
    
    -- Troops
    attacker_sent TEXT NOT NULL,  -- JSON
    attacker_lost TEXT NOT NULL,  -- JSON
    attacker_survivors TEXT NOT NULL,  -- JSON
    defender_present TEXT NOT NULL,  -- JSON
    defender_lost TEXT NOT NULL,  -- JSON
    defender_survivors TEXT NOT NULL,  -- JSON
    
    -- Siege
    wall_start INTEGER,
    wall_end INTEGER,
    building_target TEXT,
    building_start INTEGER,
    building_end INTEGER,
    
    -- Plunder
    plunder_wood INTEGER,
    plunder_clay INTEGER,
    plunder_iron INTEGER,
    vault_protection TEXT,  -- JSON
    
    -- Conquest
    allegiance_start INTEGER,
    allegiance_end INTEGER,
    village_captured BOOLEAN DEFAULT 0,
    
    -- Intelligence
    defender_intel TEXT,  -- JSON (null if scouts died)
    
    -- Metadata
    is_read BOOLEAN DEFAULT 0,
    is_starred BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (command_id) REFERENCES commands(command_id),
    FOREIGN KEY (recipient_id) REFERENCES users(user_id),
    FOREIGN KEY (attacker_id) REFERENCES users(user_id),
    FOREIGN KEY (defender_id) REFERENCES users(user_id)
);

CREATE INDEX idx_reports_recipient ON battle_reports(recipient_id, created_at);
CREATE INDEX idx_reports_battle ON battle_reports(battle_id);
```

**battle_metrics**
```sql
CREATE TABLE battle_metrics (
    metric_id INTEGER PRIMARY KEY,
    battle_id TEXT NOT NULL,
    resolver_latency_ms INTEGER NOT NULL,
    outcome TEXT NOT NULL,
    attacker_points INTEGER NOT NULL,
    defender_points INTEGER NOT NULL,
    total_attacker_pop INTEGER NOT NULL,
    total_defender_pop INTEGER NOT NULL,
    morale REAL NOT NULL,
    luck REAL NOT NULL,
    wall_level INTEGER NOT NULL,
    had_siege BOOLEAN NOT NULL,
    had_conquest BOOLEAN NOT NULL,
    village_captured BOOLEAN NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_metrics_created ON battle_metrics(created_at);
CREATE INDEX idx_metrics_outcome ON battle_metrics(outcome);
```

**rate_limit_tracking**
```sql
CREATE TABLE rate_limit_tracking (
    tracking_id INTEGER PRIMARY KEY,
    player_id INTEGER NOT NULL,
    target_village_id INTEGER,
    command_type TEXT NOT NULL,
    timestamp DATETIME NOT NULL,
    FOREIGN KEY (player_id) REFERENCES users(user_id),
    FOREIGN KEY (target_village_id) REFERENCES villages(village_id)
);

CREATE INDEX idx_rate_limit_player ON rate_limit_tracking(player_id, timestamp);
CREATE INDEX idx_rate_limit_target ON rate_limit_tracking(target_village_id, timestamp);
```

### Schema Design Rationale

**JSON Storage**: Unit compositions and modifiers are stored as JSON for flexibility and ease of querying.

**Denormalization**: Battle reports include denormalized data (attacker/defender names, village coords) to avoid joins when displaying reports.

**Indexing Strategy**: Indexes are optimized for common queries (reports by recipient, commands by arrival time, metrics by date).

**Correlation IDs**: The battle_id field links all related records for debugging and analysis.


## API Design

### Battle Resolver API

```php
interface BattleResolverInterface {
    /**
     * Resolve a single battle
     * 
     * @param Command $command The attack command to resolve
     * @param array $worldConfig World-specific configuration
     * @return BattleResult The battle outcome
     * @throws BattleException On validation or calculation errors
     */
    public function resolveBattle(Command $command, array $worldConfig): BattleResult;
    
    /**
     * Resolve multiple battles in sequence
     * 
     * @param array $commands Array of commands sorted by arrival
     * @param array $worldConfig World-specific configuration
     * @return array Array of BattleResult objects
     */
    public function resolveBattles(array $commands, array $worldConfig): array;
    
    /**
     * Validate a command before processing
     * 
     * @param Command $command The command to validate
     * @param array $worldConfig World-specific configuration
     * @return ValidationResult Validation outcome with errors if any
     */
    public function validateCommand(Command $command, array $worldConfig): ValidationResult;
}
```

### Command Processor API

```php
interface CommandProcessorInterface {
    /**
     * Queue a new attack command
     * 
     * @param int $attackerId Player sending the attack
     * @param int $sourceVillageId Source village
     * @param int $targetVillageId Target village
     * @param array $units Unit composition
     * @param string $commandType Command type
     * @param string|null $targetBuilding Target building for catapults
     * @return CommandResult Result with command ID or error
     */
    public function queueCommand(
        int $attackerId,
        int $sourceVillageId,
        int $targetVillageId,
        array $units,
        string $commandType,
        ?string $targetBuilding = null
    ): CommandResult;
    
    /**
     * Get pending commands for a tick
     * 
     * @param DateTime $tick The server tick to process
     * @return array Sorted array of commands
     */
    public function getCommandsForTick(DateTime $tick): array;
    
    /**
     * Check rate limits for a player
     * 
     * @param int $playerId Player to check
     * @param int|null $targetVillageId Optional target village
     * @return RateLimitResult Result with allowed status and retry-after
     */
    public function checkRateLimits(int $playerId, ?int $targetVillageId = null): RateLimitResult;
}
```

### Report API

```php
interface ReportServiceInterface {
    /**
     * Get reports for a player
     * 
     * @param int $playerId Player ID
     * @param string $type Report type filter ('all', 'attack', 'defense')
     * @param int $limit Number of reports to return
     * @param int $offset Pagination offset
     * @return array Array of Report objects
     */
    public function getReports(
        int $playerId,
        string $type = 'all',
        int $limit = 50,
        int $offset = 0
    ): array;
    
    /**
     * Mark a report as read
     * 
     * @param int $reportId Report ID
     * @param int $playerId Player ID (for authorization)
     * @return bool Success status
     */
    public function markAsRead(int $reportId, int $playerId): bool;
    
    /**
     * Toggle star status on a report
     * 
     * @param int $reportId Report ID
     * @param int $playerId Player ID (for authorization)
     * @return bool New star status
     */
    public function toggleStar(int $reportId, int $playerId): bool;
}
```

## Configuration

### World Configuration Schema

```php
$worldConfig = [
    // Basic settings
    'speed' => 1.0,  // World speed multiplier
    
    // Morale settings
    'morale_enabled' => true,
    'morale_min' => 0.5,
    'morale_max' => 1.5,
    'morale_base' => 0.3,
    
    // Luck settings
    'luck_enabled' => true,
    'luck_min' => 0.75,
    'luck_max' => 1.25,
    
    // Night bonus settings
    'night_bonus_enabled' => true,
    'night_start_hour' => 22,
    'night_end_hour' => 6,
    'night_defense_multiplier' => 1.5,
    
    // Overstack settings
    'overstack_enabled' => true,
    'overstack_threshold' => 30000,  // Population threshold
    'overstack_penalty_rate' => 0.3,  // 30% penalty per threshold exceeded
    'overstack_min_multiplier' => 0.5,  // Minimum 50% defense
    
    // Environment settings
    'terrain_enabled' => false,
    'weather_enabled' => false,
    
    // Conquest settings
    'allegiance_drop_per_noble' => 25,
    'post_capture_allegiance' => 25,
    'conquest_cooldown_seconds' => 300,  // 5 minutes
    
    // Rate limiting
    'rate_limits' => [
        'per_player' => 50,  // Commands per window
        'per_target' => 20,  // Commands to same target per window
        'window_seconds' => 3600,  // 1 hour window
    ],
    
    // Attack restrictions
    'min_attack_population' => 10,
    'fake_attack_threshold' => 50,
    
    // Vault settings
    'vault_protection_rate' => 0.2,  // 20% per vault level
];
```

### Configuration Validation

All world configurations should be validated on load:
- Numeric values within valid ranges
- Boolean flags are actual booleans
- Required fields are present
- Dependent settings are consistent (e.g., night_start < 24)


## Integration Points

### Integration with Existing Systems

**Village Manager**:
- Query village state (resources, buildings, troops)
- Update village state after battle (wall level, building damage, resources)
- Lock villages during battle resolution to prevent race conditions

**Unit Manager**:
- Query unit compositions for attacking and defending forces
- Update unit counts after battle (apply casualties)
- Handle unit movement (return survivors, station support)

**Notification System**:
- Send notifications for incoming attacks
- Send notifications for battle reports
- Send notifications for village capture

**Tribe System**:
- Check diplomatic relations (war, alliance, NAP)
- Share intelligence with tribe members
- Track tribe warfare statistics

**Achievement System**:
- Track battle statistics for achievements
- Award achievements for conquests, defenses, etc.

### Event Hooks

The battle resolver emits events at key points for integration:

```php
// Before battle resolution
Event::dispatch('battle.before_resolve', [
    'command' => $command,
    'attacker' => $attacker,
    'defender' => $defender
]);

// After battle resolution
Event::dispatch('battle.resolved', [
    'result' => $result,
    'reports' => $reports
]);

// On village capture
Event::dispatch('village.captured', [
    'village_id' => $villageId,
    'old_owner_id' => $oldOwnerId,
    'new_owner_id' => $newOwnerId,
    'allegiance' => $newAllegiance
]);

// On rate limit hit
Event::dispatch('rate_limit.exceeded', [
    'player_id' => $playerId,
    'limit_type' => $limitType,
    'retry_after' => $retryAfter
]);
```

## Migration Strategy

### Phase 1: Core Battle Resolution
- Implement stateless battle resolver core
- Add unit tests and property tests
- Deploy alongside existing system (no user impact)

### Phase 2: Command Processing
- Implement command validation and sorting
- Add rate limiting
- Deploy with feature flag (test with subset of users)

### Phase 3: Report Generation
- Implement report generator
- Migrate existing reports to new schema
- Deploy with gradual rollout

### Phase 4: Full Integration
- Replace old battle system with new resolver
- Monitor metrics and error rates
- Rollback capability if issues arise

### Rollback Plan

If critical issues are discovered:
1. Disable new battle resolver via feature flag
2. Route all battles through old system
3. Investigate and fix issues
4. Re-enable with additional monitoring

### Data Migration

**Battle Reports**:
- Old reports remain in legacy schema
- New reports use new schema
- UI handles both formats during transition
- Eventually archive old reports

**Commands**:
- New commands use new schema immediately
- In-flight commands complete in old system
- No migration needed


## Future Enhancements

### Potential Additions

**Research Bonuses**:
- Smithy upgrades affecting unit stats
- Research tree for combat bonuses
- Tribe-wide research benefits

**Hero System**:
- Heroes providing combat bonuses
- Hero skills and abilities
- Hero experience and leveling

**Formation System**:
- Tactical positioning bonuses
- Front-line vs back-line mechanics
- Formation counters

**Fatigue System**:
- Multiple battles reducing effectiveness
- Rest periods for recovery
- Strategic timing considerations

**Advanced Siege**:
- Siege camps for sustained attacks
- Siege equipment durability
- Counter-siege mechanics

**Weather System**:
- Dynamic weather affecting battles
- Seasonal modifiers
- Weather forecasting

**Terrain System**:
- Detailed terrain types (forest, hills, plains, water)
- Terrain-specific unit bonuses
- Terrain modification (fortifications)

**Battle Replays**:
- Visual replay of battle progression
- Turn-by-turn breakdown
- Shareable battle links

### Technical Debt

**Known Limitations**:
- Current implementation uses PHP; consider Go/Rust for performance
- Floating-point precision may vary across platforms
- Rate limiting is per-server; needs distributed solution

**Refactoring Opportunities**:
- Extract modifier pipeline into separate service
- Implement strategy pattern for different battle types
- Add caching layer for frequently accessed data

**Performance Improvements**:
- Pre-compute common modifier values
- Batch database operations more aggressively
- Consider event sourcing for battle history

## Glossary

**Battle Ratio**: The ratio of attacker offensive power to defender defensive power, used to determine battle outcome and casualties.

**Casualty Exponent**: The exponent (1.5) used in the casualty calculation formula to create a square-root mechanic.

**Conquest Unit**: A special unit (noble, envoy) that reduces village allegiance when surviving an attacker victory.

**Correlation ID**: A unique identifier linking all operations related to a single battle for debugging and tracing.

**Deterministic**: Producing identical outputs for identical inputs across all executions and servers.

**Fake Attack**: A low-population attack intended to deceive defenders about the attacker's true intentions.

**Overstack**: When total defending population exceeds a threshold, triggering defense penalties to prevent invulnerability.

**Property-Based Testing**: A testing approach that verifies properties hold across all inputs rather than testing specific examples.

**Rate Limiting**: Restricting the number of commands a player can send within a time window to prevent spam and server overload.

**Siege Unit**: A specialized unit (ram, catapult) that damages defensive structures and buildings.

**Square-Root Mechanic**: The use of ratio^1.5 in casualty calculations to soften edges and prevent extreme outcomes.

**Vault Protection**: A percentage of resources protected from plunder based on vault level.

## References

- Existing BattleEngine implementation: `lib/managers/BattleEngine.php`
- Unit data: `data/units.json`
- Combat guide: `docs/COMBAT_GUIDE.md`
- Battle engine documentation: `docs/battle-engine.md`
- Test suite: `tests/BattleEngine.test.php`

