# Requirements Document

## Introduction

The Battle Resolution System is the core combat engine for a real-time server-tick medieval tribal war MMO. When an attack command arrives at a target village, the system must resolve the battle deterministically based on troop compositions, defensive structures, environmental modifiers, and world-specific rules. The system calculates casualties, siege effects, resource plunder, and allegiance changes, then generates detailed battle reports for both parties.

## Glossary

- **Battle Resolver**: The core engine that processes combat when an attack command arrives at a target village
- **Command**: A player-issued order to move troops (attack, support, scout, raid, siege)
- **Attacker**: The player or entity sending troops to engage a target village
- **Defender**: The player or entity whose village is being attacked, including any stationed support troops
- **Garrison**: Troops stationed in a village by the village owner
- **Support**: Allied troops stationed in a village to assist in defense
- **Siege Units**: Specialized units (rams, catapults) that damage walls and buildings
- **Conquest Units**: Special units (nobles, standard bearers) that reduce village allegiance
- **Allegiance**: A village's loyalty value; when reduced to zero or below, the village can be captured
- **Wall**: A defensive structure that multiplies defender defense values
- **Morale**: A modifier that reduces attacker strength when attacking weaker players
- **Luck**: A random modifier applied to combat outcomes within a configured range
- **Plunder**: Resources stolen by surviving attackers after a successful battle
- **Vault**: A structure that protects a percentage of resources from plunder
- **Battle Report**: A detailed record of combat outcomes sent to both attacker and defender
- **Server Tick**: The exact server timestamp when a command arrives and is processed
- **Overstack**: When total defending population exceeds a threshold, triggering defense penalties
- **Environment Modifiers**: Time-of-day, terrain, and weather effects that modify combat outcomes
- **Fake Attack**: A low-population attack intended to deceive defenders
- **Clear**: A high-attack force sent to eliminate defenders before a conquest wave
- **Snipe**: Defender support timed to arrive between attacker waves to kill conquest units

## Requirements

### Requirement 1

**User Story:** As an attacker, I want my troops to engage the defender's forces when they arrive at the target village, so that I can eliminate their army and achieve my objectives.

#### Acceptance Criteria

1. WHEN an attack command arrives at a target village THEN the Battle Resolver SHALL merge all defending forces including garrison and stationed support
2. WHEN calculating combat power THEN the Battle Resolver SHALL sum attacker power by damage type and defender defense by damage type
3. WHEN both sides have troops THEN the Battle Resolver SHALL calculate casualties proportionally based on the power ratio
4. WHEN the battle resolves THEN the Battle Resolver SHALL apply casualties to both attacker and defender unit counts
5. WHEN the battle completes THEN the Battle Resolver SHALL return surviving units to their origin or keep them stationed based on command type

### Requirement 2

**User Story:** As a defender, I want my wall and defensive structures to strengthen my troops, so that I can better protect my village from attacks.

#### Acceptance Criteria

1. WHEN calculating defender defense THEN the Battle Resolver SHALL apply the wall level multiplier to defender defense values
2. WHEN rams survive on the attacker side THEN the Battle Resolver SHALL reduce the wall level based on surviving ram count and battle outcome
3. WHEN catapults survive on the attacker side and the attacker wins THEN the Battle Resolver SHALL damage the targeted building
4. WHEN no building is targeted THEN the Battle Resolver SHALL select a random building for catapult damage
5. WHEN wall level changes THEN the Battle Resolver SHALL persist the new wall level to the village record

### Requirement 3

**User Story:** As a game administrator, I want morale and luck modifiers to balance combat, so that battles are fair and include appropriate randomness.

#### Acceptance Criteria

1. WHEN the attacker is significantly stronger than the defender THEN the Battle Resolver SHALL apply morale reduction to attacker strength based on the configured morale curve
2. WHEN luck is enabled for the world THEN the Battle Resolver SHALL apply a random modifier within the configured luck band to the attacker strength
3. WHEN calculating final combat power THEN the Battle Resolver SHALL apply morale before luck
4. WHEN morale is disabled for the world THEN the Battle Resolver SHALL skip morale calculations
5. WHEN luck is disabled for the world THEN the Battle Resolver SHALL skip luck calculations

### Requirement 4

**User Story:** As an attacker, I want to plunder resources from villages I successfully attack, so that I can gain economic advantage.

#### Acceptance Criteria

1. WHEN the attacker wins and has surviving plunder-capable units THEN the Battle Resolver SHALL calculate available loot after vault protection
2. WHEN calculating loot THEN the Battle Resolver SHALL subtract vault-protected amounts from available resources
3. WHEN loot exceeds attacker carry capacity THEN the Battle Resolver SHALL limit plunder to the total carry capacity of surviving units
4. WHEN distributing loot THEN the Battle Resolver SHALL split resources deterministically across surviving plunder-capable units
5. WHEN siege units or conquest units survive THEN the Battle Resolver SHALL assign zero carry capacity to those unit types

### Requirement 5

**User Story:** As an attacker with conquest units, I want to reduce village allegiance when I win battles, so that I can eventually capture the village.

#### Acceptance Criteria

1. WHEN the attacker wins and conquest units survive THEN the Battle Resolver SHALL reduce the target village allegiance by the configured amount per surviving conquest unit
2. WHEN allegiance reaches zero or below THEN the Battle Resolver SHALL transfer village ownership to the attacker
3. WHEN a village is captured THEN the Battle Resolver SHALL apply the post-capture allegiance floor to prevent immediate re-conquest
4. WHEN conquest units are present but the attacker loses THEN the Battle Resolver SHALL not reduce allegiance
5. WHEN conquest cooldowns are active on the target THEN the Battle Resolver SHALL block allegiance reduction and return a reason code

### Requirement 6

**User Story:** As a player, I want detailed battle reports showing what happened in combat, so that I can understand the outcome and plan future actions.

#### Acceptance Criteria

1. WHEN a battle resolves THEN the Battle Resolver SHALL generate a battle report for both attacker and defender
2. WHEN generating reports THEN the Battle Resolver SHALL include troops sent, lost, and survived for both sides
3. WHEN generating reports THEN the Battle Resolver SHALL include all applied modifiers including morale, luck, wall, and environment effects
4. WHEN generating reports THEN the Battle Resolver SHALL include siege effects showing wall and building changes
5. WHEN generating reports THEN the Battle Resolver SHALL include plunder amounts and vault protection details
6. WHEN generating reports THEN the Battle Resolver SHALL include allegiance changes if conquest units were present
7. WHEN attacker scouts survive THEN the Battle Resolver SHALL include defender intelligence in the attacker report
8. WHEN attacker scouts die THEN the Battle Resolver SHALL redact defender intelligence from the attacker report

### Requirement 7

**User Story:** As a game administrator, I want environment modifiers like night, terrain, and weather to affect battles, so that combat has strategic depth.

#### Acceptance Criteria

1. WHEN night bonus is enabled and the battle occurs during night hours THEN the Battle Resolver SHALL apply the night defense multiplier to defender values
2. WHEN terrain modifiers are enabled THEN the Battle Resolver SHALL apply terrain-specific multipliers based on the village tile type
3. WHEN weather modifiers are enabled THEN the Battle Resolver SHALL apply weather effects to combat calculations
4. WHEN multiple environment modifiers are active THEN the Battle Resolver SHALL apply them in the configured order
5. WHEN environment modifiers are disabled for the world THEN the Battle Resolver SHALL skip those calculations

### Requirement 8

**User Story:** As a game administrator, I want to prevent defense overstacking, so that players cannot make villages invulnerable by concentrating unlimited troops.

#### Acceptance Criteria

1. WHEN overstack penalties are enabled and total defending population exceeds the threshold THEN the Battle Resolver SHALL apply a defense reduction multiplier
2. WHEN calculating the overstack multiplier THEN the Battle Resolver SHALL use the formula: max(min_multiplier, 1 - penalty_rate × max(0, (def_pop - threshold) / threshold))
3. WHEN applying combat modifiers THEN the Battle Resolver SHALL apply overstack penalties before wall and environment modifiers
4. WHEN overstack penalties are disabled for the world THEN the Battle Resolver SHALL skip overstack calculations
5. WHEN overstack penalties apply THEN the Battle Resolver SHALL include the penalty percentage in the battle report

### Requirement 9

**User Story:** As a player, I want simultaneous attacks to be resolved in a deterministic order, so that combat outcomes are fair and predictable.

#### Acceptance Criteria

1. WHEN multiple commands arrive at the same server tick THEN the Battle Resolver SHALL sort them by arrival timestamp, then sequence number, then type priority, then command ID
2. WHEN support commands arrive at the same tick as an attack THEN the Battle Resolver SHALL include support with arrival_at less than or equal to attack arrival_at
3. WHEN resolving battles in sequence THEN the Battle Resolver SHALL process each command in the sorted order
4. WHEN identical inputs are provided THEN the Battle Resolver SHALL produce identical outputs across different servers
5. WHEN commands violate minimum spacing rules THEN the Battle Resolver SHALL bump the later command to the next tick and return a spacing error code

### Requirement 10

**User Story:** As a game administrator, I want to enforce rate limits on attack commands, so that players cannot spam attacks and degrade server performance.

#### Acceptance Criteria

1. WHEN a player sends commands THEN the Battle Resolver SHALL enforce per-player rate limits based on sliding time windows
2. WHEN a player targets the same village repeatedly THEN the Battle Resolver SHALL enforce per-target rate limits
3. WHEN rate limits are exceeded THEN the Battle Resolver SHALL reject the command and return an error code with retry-after time
4. WHEN minimum population rules are enabled THEN the Battle Resolver SHALL reject attacks below the minimum troop count
5. WHEN fake attack detection is enabled THEN the Battle Resolver SHALL tag low-population attacks for intelligence filtering

### Requirement 11

**User Story:** As a developer, I want the battle resolver to handle edge cases gracefully, so that the system remains stable under all conditions.

#### Acceptance Criteria

1. WHEN a village has an active emergency shield THEN the Battle Resolver SHALL bounce the attack without combat and return a protection error code
2. WHEN troop counts are negative or invalid THEN the Battle Resolver SHALL reject the command and return a validation error code
3. WHEN modifiers produce values outside valid ranges THEN the Battle Resolver SHALL clamp them to configured minimum and maximum values
4. WHEN calculation errors occur THEN the Battle Resolver SHALL log the error with correlation ID and return a safe default outcome
5. WHEN the defender is offline THEN the Battle Resolver SHALL process combat normally without special handling

### Requirement 12

**User Story:** As a system operator, I want comprehensive telemetry and logging for battles, so that I can monitor performance and debug issues.

#### Acceptance Criteria

1. WHEN a battle resolves THEN the Battle Resolver SHALL emit metrics including resolver latency, battle outcome, and modifier flags
2. WHEN a battle resolves THEN the Battle Resolver SHALL log a correlation ID linking all related operations
3. WHEN errors occur THEN the Battle Resolver SHALL log the error with context including command IDs, player IDs, and reason codes
4. WHEN rate limits are hit THEN the Battle Resolver SHALL increment rate limit error counters
5. WHEN battles complete THEN the Battle Resolver SHALL record timing data for performance analysis
