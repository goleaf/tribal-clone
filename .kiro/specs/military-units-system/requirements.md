# Requirements Document

## Introduction

The Military Units System defines the complete roster of combat units available to players in a medieval tribal war browser MMO. The system encompasses 16+ distinct unit types across seven categories (Infantry, Cavalry, Ranged, Siege, Scouts, Support, and Elite/Special units), each with unique roles, stats, and strategic purposes. Units must follow rock-paper-scissors combat dynamics, support village specialization strategies, and integrate with the battle resolution system while maintaining balance across different world archetypes.

## Glossary

- **Unit**: A trainable military entity with specific combat stats, costs, population requirements, and strategic role
- **Unit Category**: A classification grouping units by primary function (Infantry, Cavalry, Ranged, Siege, Scout, Support, Elite)
- **Rock-Paper-Scissors (RPS)**: Combat balance system where unit types have advantages and disadvantages against other types
- **Attack Value**: The offensive power a unit contributes in combat
- **Defense Values**: Three defense stats (vs Infantry, vs Cavalry, vs Ranged) that determine how well a unit defends against each damage type
- **Speed**: Movement rate measured in minutes per map field
- **Carry Capacity**: Amount of resources a unit can transport when plundering or trading
- **Population Cost**: Farm capacity consumed by training and maintaining one unit
- **Training Time**: Duration required to produce one unit in a recruitment building
- **Prerequisites**: Building levels and research nodes required to unlock unit training
- **Village Specialization**: Strategic focus of a village toward specific unit types (offensive, defensive, siege, etc.)
- **World Archetype**: Server configuration preset (casual, speed, hardcore) that modifies unit costs, speeds, and availability
- **Seasonal Unit**: Limited-time unit available during special events with sunset handling
- **Conquest Unit**: Special unit (Noble, Standard Bearer) that reduces village allegiance for capture
- **Support Unit**: Unit that provides buffs or recovery (Banner Guard, War Healer) rather than direct combat
- **Siege Unit**: Unit specialized for destroying walls and buildings (Ram, Catapult, Mantlet)
- **Aura**: Area buff effect provided by support units to nearby troops
- **Unit Cap**: Maximum number of specific unit types allowed per village or account
- **Mantlet**: Siege cover unit that reduces ranged damage to escorted siege equipment

## Requirements

### Requirement 1

**User Story:** As a player, I want to train infantry units with different roles, so that I can build defensive garrisons and offensive forces suited to my strategy.

#### Acceptance Criteria

1. WHEN a player has Barracks level 1 or higher THEN the system SHALL allow training of Pikeneer units with anti-cavalry specialization
2. WHEN a player has Barracks level 2 or higher THEN the system SHALL allow training of Shieldbearer units with balanced defense
3. WHEN a player has Barracks level 1 or higher THEN the system SHALL allow training of Raider units with high attack and low defense
4. WHEN calculating Pikeneer defense THEN the system SHALL apply higher defense values against cavalry attacks than against infantry or ranged attacks
5. WHEN calculating Shieldbearer defense THEN the system SHALL apply balanced defense values across all three damage types

### Requirement 2

**User Story:** As a player, I want to train ranged units that excel behind walls, so that I can defend my villages effectively against infantry assaults.

#### Acceptance Criteria

1. WHEN a player has Barracks level 1 or higher THEN the system SHALL allow training of Militia Bowman units with basic ranged defense
2. WHEN a player has Barracks level 3 and Research level 2 THEN the system SHALL allow training of Longbow Scout units with improved offense
3. WHEN ranged units defend behind walls THEN the system SHALL apply bonus defense multipliers against infantry attacks
4. WHEN ranged units fight in open field THEN the system SHALL reduce their defensive effectiveness
5. WHEN calculating ranged unit carry capacity THEN the system SHALL assign low carry values reflecting their support role

### Requirement 3

**User Story:** As a player, I want to train cavalry units for fast raids and strikes, so that I can quickly attack distant targets and plunder resources.

#### Acceptance Criteria

1. WHEN a player has Stable level 1 or higher THEN the system SHALL allow training of Skirmisher Cavalry units with very fast speed
2. WHEN a player has Stable level 3 or higher THEN the system SHALL allow training of Lancer units with high attack and heavy population cost
3. WHEN cavalry attacks ranged units in open field THEN the system SHALL apply cavalry bonus multipliers to attack values
4. WHEN cavalry attacks defended walls with pike units THEN the system SHALL apply pike anti-cavalry defense multipliers
5. WHEN calculating cavalry speed THEN the system SHALL assign movement rates faster than infantry and siege units

### Requirement 4

**User Story:** As a player, I want to train scout units to gather intelligence, so that I can see enemy troop compositions and plan my attacks.

#### Acceptance Criteria

1. WHEN a player has Barracks level 1 or higher THEN the system SHALL allow training of Pathfinder scout units with very fast speed and minimal combat power
2. WHEN a player has Stable level 5 and Research level 4 THEN the system SHALL allow training of Shadow Rider units with deep intel capabilities
3. WHEN Pathfinder scouts survive a scouting mission THEN the system SHALL reveal defender troop counts and resource levels
4. WHEN Shadow Rider scouts survive a scouting mission THEN the system SHALL reveal defender building levels and recruitment queues
5. WHEN defending scouts outnumber attacking scouts THEN the system SHALL kill attacking scouts and prevent intelligence gathering

### Requirement 5

**User Story:** As a player, I want to train siege units to destroy walls and buildings, so that I can breach defenses and weaken enemy infrastructure.

#### Acceptance Criteria

1. WHEN a player has Workshop level 1 or higher THEN the system SHALL allow training of Battering Ram units with wall reduction capability
2. WHEN a player has Workshop level 3 and Research level 3 THEN the system SHALL allow training of Stone Hurler catapult units with building damage capability
3. WHEN Battering Rams survive a successful attack THEN the system SHALL reduce the target village wall level based on surviving ram count
4. WHEN Stone Hurlers survive a successful attack THEN the system SHALL damage the targeted building or select a random building if none specified
5. WHEN siege units are attacked by ranged defenders THEN the system SHALL apply low siege defense values making them vulnerable

### Requirement 6

**User Story:** As a player, I want to train support units that buff my armies, so that I can improve combat effectiveness and recover wounded troops.

#### Acceptance Criteria

1. WHEN a player has Rally Point level 5 and Research level 4 THEN the system SHALL allow training of Banner Guard units with defensive aura capability
2. WHEN a player has Hospital building and world setting enables healers THEN the system SHALL allow training of War Healer units with wounded recovery capability
3. WHEN Banner Guard units are present in a defending force THEN the system SHALL apply the highest-tier aura buff to all defending troops
4. WHEN War Healer units survive a battle THEN the system SHALL recover a percentage of lost troops up to the configured per-battle cap
5. WHEN multiple Banner Guards are present THEN the system SHALL apply only the highest aura level without stacking

### Requirement 7

**User Story:** As a player, I want to train conquest units to capture enemy villages, so that I can expand my territory.

#### Acceptance Criteria

1. WHEN a player has Academy level 3 and Smithy level 20 and has minted noble coins THEN the system SHALL allow training of Noble conquest units
2. WHEN a player has Hall of Banners and has crafted standards THEN the system SHALL allow training of Standard Bearer conquest units
3. WHEN Noble units survive a successful attack THEN the system SHALL reduce target village allegiance by the configured amount per surviving noble
4. WHEN allegiance reaches zero or below THEN the system SHALL transfer village ownership to the attacker
5. WHEN conquest units are present in a losing attack THEN the system SHALL not reduce allegiance

### Requirement 8

**User Story:** As a player, I want to train elite units with superior stats, so that I can anchor critical defenses and execute high-value operations.

#### Acceptance Criteria

1. WHEN a player has Barracks level 10 and Research level 8 THEN the system SHALL allow training of Warden elite infantry with very high defense
2. WHEN a player has Barracks level 8 and Research level 7 THEN the system SHALL allow training of Ranger elite ranged units with anti-siege bonus
3. WHEN Warden units defend THEN the system SHALL apply very high defense values across all damage types
4. WHEN Ranger units engage siege units THEN the system SHALL apply bonus damage multipliers against rams and catapults
5. WHEN calculating elite unit costs THEN the system SHALL assign high resource costs and long training times reflecting their power

### Requirement 9

**User Story:** As a game administrator, I want to configure unit caps per village and account, so that I can prevent unit stacking exploits and maintain balance.

#### Acceptance Criteria

1. WHEN a player attempts to train siege units THEN the system SHALL enforce per-village caps on total ram and catapult counts
2. WHEN a player attempts to train elite or seasonal units THEN the system SHALL enforce per-account caps on total counts
3. WHEN a player attempts to train conquest units THEN the system SHALL enforce per-command caps on nobles and standard bearers
4. WHEN unit caps are reached THEN the system SHALL reject training requests and return error code ERR_CAP with current count and limit
5. WHEN calculating current counts THEN the system SHALL include both stationed units and units in transit

### Requirement 10

**User Story:** As a game administrator, I want to configure seasonal and event units with time windows, so that I can offer limited-time content without permanent power creep.

#### Acceptance Criteria

1. WHEN a seasonal unit event is active THEN the system SHALL allow training of event units within the configured start and end timestamps
2. WHEN a seasonal unit event expires THEN the system SHALL disable training of event units and hide them from recruitment UI
3. WHEN seasonal units expire THEN the system SHALL convert existing units to resources or disable their use based on world configuration
4. WHEN a player attempts to train expired seasonal units THEN the system SHALL reject the request and return error code ERR_SEASONAL_EXPIRED
5. WHEN seasonal units are enabled THEN the system SHALL enforce per-account caps to prevent hoarding

### Requirement 11

**User Story:** As a game administrator, I want to apply world-specific multipliers to unit costs and training times, so that I can balance different server archetypes.

#### Acceptance Criteria

1. WHEN a world has speed archetype THEN the system SHALL apply training time multipliers reducing build duration for all units
2. WHEN a world has hardcore archetype THEN the system SHALL apply cost multipliers increasing resource requirements for elite and seasonal units
3. WHEN calculating effective training time THEN the system SHALL multiply base training time by world archetype multiplier
4. WHEN calculating effective costs THEN the system SHALL multiply base resource costs by world archetype multiplier
5. WHEN displaying unit information THEN the system SHALL show effective costs and times after world multipliers are applied

### Requirement 12

**User Story:** As a player, I want to see clear unit information including strengths and weaknesses, so that I can make informed training decisions.

#### Acceptance Criteria

1. WHEN viewing unit details THEN the system SHALL display attack value, defense values by type, speed, carry capacity, and population cost
2. WHEN viewing unit details THEN the system SHALL display resource costs, training time, and prerequisite buildings
3. WHEN viewing unit details THEN the system SHALL display rock-paper-scissors matchup information showing strengths and weaknesses
4. WHEN viewing unit details THEN the system SHALL display special abilities such as aura effects, siege capabilities, or conquest mechanics
5. WHEN world-specific modifiers apply THEN the system SHALL display effective values after multipliers with notation indicating modifications

### Requirement 13

**User Story:** As a developer, I want unit stats and configurations stored in a central data file, so that I can maintain balance and version control.

#### Acceptance Criteria

1. WHEN the system initializes THEN the system SHALL load unit definitions from data/units.json including all stats and costs
2. WHEN unit stats are modified THEN the system SHALL validate that rock-paper-scissors relationships are maintained
3. WHEN unit stats are modified THEN the system SHALL validate that no unit has negative or zero values for required fields
4. WHEN unit stats are modified THEN the system SHALL generate a human-readable diff showing changes for changelog documentation
5. WHEN deploying unit changes THEN the system SHALL validate that world-specific overrides do not break balance constraints

### Requirement 14

**User Story:** As a player, I want mantlet units to protect my siege equipment from ranged fire, so that I can successfully breach defended walls.

#### Acceptance Criteria

1. WHEN a player has Workshop level 2 and Research level 3 THEN the system SHALL allow training of Mantlet Crew units with siege cover capability
2. WHEN Mantlet units escort siege units in an attack THEN the system SHALL reduce incoming ranged damage to siege units by the configured percentage
3. WHEN calculating mantlet protection THEN the system SHALL apply reduction before distributing casualties to siege units
4. WHEN mantlet units are killed THEN the system SHALL remove protection and allow full ranged damage to siege units
5. WHEN battle reports are generated THEN the system SHALL include mantlet reduction percentage in applied modifiers section

### Requirement 15

**User Story:** As a game administrator, I want to enforce prerequisite checks for unit training, so that players cannot train units before unlocking required buildings and research.

#### Acceptance Criteria

1. WHEN a player attempts to train a unit THEN the system SHALL verify all required building levels are met
2. WHEN a player attempts to train a unit THEN the system SHALL verify all required research nodes are completed
3. WHEN a player attempts to train conquest units THEN the system SHALL verify noble coins or standards are available and deduct them
4. WHEN prerequisites are not met THEN the system SHALL reject training and return error code ERR_PREREQ with missing requirements
5. WHEN world features are disabled THEN the system SHALL reject training of disabled unit types and return error code ERR_FEATURE_DISABLED

### Requirement 16

**User Story:** As a player, I want to specialize my villages for different unit production, so that I can optimize my military strategy across my territory.

#### Acceptance Criteria

1. WHEN a village focuses on cavalry production THEN the system SHALL allow training high volumes of skirmisher cavalry and lancers with appropriate stable levels
2. WHEN a village focuses on defensive production THEN the system SHALL allow training high volumes of pikeneers, shieldbearers, and ranged units
3. WHEN a village focuses on siege production THEN the system SHALL allow training rams, catapults, and mantlets up to per-village caps
4. WHEN a village focuses on conquest production THEN the system SHALL allow training nobles or standard bearers with required coin/standard consumption
5. WHEN calculating village specialization efficiency THEN the system SHALL consider building levels, population capacity, and resource production rates

### Requirement 17

**User Story:** As a developer, I want comprehensive validation and error handling for unit training, so that the system remains stable under all conditions.

#### Acceptance Criteria

1. WHEN a player submits a training request with zero or negative unit counts THEN the system SHALL reject the request and return error code ERR_INPUT
2. WHEN a player submits a training request exceeding farm capacity THEN the system SHALL reject the request and return error code ERR_POP with current and required population
3. WHEN a player submits a training request with insufficient resources THEN the system SHALL reject the request and return error code ERR_RES with missing amounts
4. WHEN concurrent training requests could exceed caps THEN the system SHALL enforce caps atomically and reject violating requests
5. WHEN training requests fail THEN the system SHALL log the failure with correlation ID, player ID, unit type, and reason code for telemetry

### Requirement 18

**User Story:** As a system operator, I want telemetry and monitoring for unit training patterns, so that I can detect balance issues and abuse.

#### Acceptance Criteria

1. WHEN a player trains units THEN the system SHALL emit metrics including unit type, count, world ID, and player ID
2. WHEN training requests hit caps THEN the system SHALL increment cap-hit counters by unit type and world
3. WHEN training requests fail validation THEN the system SHALL increment error counters by reason code
4. WHEN seasonal units are trained THEN the system SHALL track adoption rates and alert on anomalies
5. WHEN elite units are trained THEN the system SHALL track per-account totals and alert on hoarding patterns exceeding thresholds
