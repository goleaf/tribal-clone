# Requirements Document

## Introduction

The Village & Building System is the foundation of player progression in a medieval tribal war browser MMO. Villages are player-controlled settlements that produce resources, train troops, and serve as strategic nodes on the world map. The system encompasses 20+ building types with upgrade paths, construction queues, specialization strategies, and integration with resource production, military training, and conquest mechanics. Buildings must support multiple village archetypes (offensive, defensive, production, scouting, conquest) while maintaining balance across different world configurations.

## Glossary

- **Village**: A player-controlled settlement that produces resources, trains troops, and occupies a tile on the world map
- **Building**: A structure within a village that provides specific functionality such as resource production, military training, or defensive capabilities
- **Building Level**: The upgrade tier of a building, ranging from 0 (not built) to a maximum level (typically 20)
- **Construction Queue**: An ordered list of building upgrades waiting to be processed
- **Queue Slot**: A concurrent construction slot allowing parallel building upgrades
- **Town Hall**: The core building that controls construction speed and unlocks other buildings
- **Resource Building**: A building that produces wood, clay, or iron over time (Lumber Yard, Clay Pit, Iron Mine)
- **Military Building**: A building that trains combat units (Barracks, Stable, Workshop, Siege Foundry)
- **Storage Building**: A building that increases resource capacity (Storage, Warehouse, Vault)
- **Defensive Building**: A building that enhances village defense (Wall, Watchtower, Garrison)
- **Support Building**: A building that provides utility functions (Market, Hospital, Library)
- **Prerequisite**: A required building level or research node that must be completed before constructing or upgrading another building
- **Village Archetype**: A strategic specialization pattern (Offensive Hub, Defensive Bastion, Production Village, Scouting Post, Conquest Capital)
- **Build Time**: The duration required to complete a building upgrade
- **Build Cost**: The resource amounts required to start a building upgrade
- **Population Cost**: The farm capacity consumed by a building level
- **Parallel Construction**: The ability to build multiple buildings simultaneously using multiple queue slots
- **Wall Level**: The defensive structure level that multiplies defender defense values in combat
- **Wall Damage**: Reduction in wall level caused by successful siege attacks
- **Wall Repair**: The process of restoring wall levels through the construction queue
- **Vault Protection**: The percentage of resources protected from plunder by the Vault building
- **Watchtower Detection**: The ability to detect incoming attacks and reveal noble-bearing commands
- **Detection Radius**: The map tile distance within which the Watchtower can detect incoming commands
- **Hospital Recovery**: The percentage of wounded troops recovered after battles
- **Hall of Banners**: The building that mints conquest resources (coins, standards) and trains conquest units
- **Minting**: The process of creating noble coins or standards required for conquest unit training
- **Market Caravan**: A transport unit that moves resources between villages or players
- **Research**: Technology unlocks that enable advanced buildings, units, or mechanics
- **Building Cap**: The maximum level allowed for a specific building type on a world
- **World Archetype**: Server configuration preset (casual, speed, hardcore) that modifies building costs and times
- **Outpost**: A temporary forward base with limited building slots and expiration
- **Building Decay**: Optional mechanic where inactive villages experience wall degradation over time
- **Emergency Shield**: A protection status that prevents attacks on a village for a limited time

## Requirements

### Requirement 1

**User Story:** As a player, I want to construct and upgrade buildings in my village, so that I can improve resource production, unlock military capabilities, and strengthen defenses.

#### Acceptance Criteria

1. WHEN a player selects a building to upgrade THEN the System SHALL verify that all prerequisite buildings and research are completed
2. WHEN a player submits a building upgrade THEN the System SHALL verify that sufficient resources are available and deduct the build cost
3. WHEN a player submits a building upgrade THEN the System SHALL verify that population capacity is sufficient for the new building level
4. WHEN a building upgrade is queued THEN the System SHALL calculate completion time based on building level, Town Hall level, and world multipliers
5. WHEN a building upgrade completes THEN the System SHALL increment the building level and apply new production rates or capabilities

### Requirement 2

**User Story:** As a player, I want to manage a construction queue with multiple slots, so that I can plan my village development efficiently.

#### Acceptance Criteria

1. WHEN a player starts with a new village THEN the System SHALL provide one base construction queue slot
2. WHEN the Town Hall reaches milestone levels THEN the System SHALL unlock additional construction queue slots based on configured thresholds
3. WHEN a player attempts to queue a building upgrade and all slots are occupied THEN the System SHALL reject the request and return error code ERR_QUEUE_FULL
4. WHEN parallel construction is enabled for the world THEN the System SHALL allow one resource building and one military building to be constructed simultaneously
5. WHEN a player cancels a queued building upgrade THEN the System SHALL refund a configured percentage of the build cost and remove the item from the queue

### Requirement 3

**User Story:** As a player, I want the Town Hall to control my construction speed and unlock advanced buildings, so that it serves as the core progression building.

#### Acceptance Criteria

1. WHEN calculating building construction time THEN the System SHALL apply a time reduction multiplier based on Town Hall level
2. WHEN the Town Hall reaches level 3 THEN the System SHALL unlock Barracks construction
3. WHEN the Town Hall reaches level 5 THEN the System SHALL unlock Stable construction
4. WHEN the Town Hall reaches level 10 THEN the System SHALL unlock advanced buildings including Workshop and Siege Foundry
5. WHEN the Town Hall reaches configured milestone levels THEN the System SHALL unlock additional construction queue slots

### Requirement 4

**User Story:** As a player, I want resource buildings to produce wood, clay, and iron, so that I can fuel my village economy and military production.

#### Acceptance Criteria

1. WHEN a Lumber Yard is upgraded THEN the System SHALL increase wood production per hour based on the level-specific production curve
2. WHEN a Clay Pit is upgraded THEN the System SHALL increase clay production per hour based on the level-specific production curve
3. WHEN an Iron Mine is upgraded THEN the System SHALL increase iron production per hour based on the level-specific production curve
4. WHEN resource production ticks THEN the System SHALL add produced resources to village storage up to the storage capacity limit
5. WHEN storage capacity is reached THEN the System SHALL stop accumulating resources and display a capacity warning to the player

### Requirement 5

**User Story:** As a player, I want storage buildings to increase my resource capacity, so that I can accumulate resources for expensive upgrades and military production.

#### Acceptance Criteria

1. WHEN a Storage building is upgraded THEN the System SHALL increase the capacity for all three resource types based on the level-specific capacity curve
2. WHEN a Warehouse building is upgraded THEN the System SHALL provide additional capacity beyond the base Storage building
3. WHEN a Vault building is upgraded THEN the System SHALL increase the percentage of resources protected from plunder
4. WHEN calculating available loot after a raid THEN the System SHALL subtract vault-protected amounts from total available resources
5. WHEN a player attempts to upgrade a building requiring more resources than storage capacity THEN the System SHALL reject the upgrade and return error code ERR_STORAGE_CAP

### Requirement 6

**User Story:** As a player, I want military buildings to train combat units, so that I can build armies for offense and defense.

#### Acceptance Criteria

1. WHEN a Barracks is constructed THEN the System SHALL enable training of basic infantry units including Pikeneers, Shieldbearers, and Raiders
2. WHEN a Stable is constructed THEN the System SHALL enable training of cavalry units including Skirmisher Cavalry and Lancers
3. WHEN a Workshop is constructed THEN the System SHALL enable training of siege units including Battering Rams and Mantlets
4. WHEN a Siege Foundry is constructed THEN the System SHALL enable training of catapult units including Stone Hurlers
5. WHEN military buildings are upgraded THEN the System SHALL reduce unit training time based on the building level

### Requirement 7

**User Story:** As a player, I want a Wall to protect my village, so that I can multiply my defensive strength and slow down attackers.

#### Acceptance Criteria

1. WHEN a Wall is constructed THEN the System SHALL apply a defense multiplier to all defending troops based on the wall level
2. WHEN siege units successfully attack a village THEN the System SHALL reduce the wall level based on surviving ram count and battle outcome
3. WHEN a player queues a wall repair THEN the System SHALL restore wall levels through the construction queue consuming resources and time
4. WHEN hostile commands are inbound with ETA less than the configured repair block window THEN the System SHALL prevent wall repair queueing and return error code ERR_REPAIR_BLOCKED
5. WHEN wall decay is enabled for the world and a village is inactive THEN the System SHALL reduce wall level by a small amount daily after the inactivity threshold

### Requirement 8

**User Story:** As a player, I want a Watchtower to detect incoming attacks, so that I can prepare defenses and identify noble-bearing conquest attempts.

#### Acceptance Criteria

1. WHEN a Watchtower is constructed THEN the System SHALL enable detection of incoming commands within a radius based on watchtower level
2. WHEN an attack command enters the detection radius THEN the System SHALL create a warning notification with command type and estimated arrival time
3. WHEN noble detection is enabled and a command contains conquest units THEN the System SHALL flag the command with a noble indicator if detected
4. WHEN calculating detection probability THEN the System SHALL apply modifiers based on Scout Hall level, terrain, and weather conditions
5. WHEN a Watchtower is upgraded THEN the System SHALL increase the detection radius according to the level-specific radius curve

### Requirement 9

**User Story:** As a player, I want a Hospital to recover wounded troops after battles, so that I can sustain my military forces without complete losses.

#### Acceptance Criteria

1. WHEN a Hospital is constructed and enabled for the world THEN the System SHALL recover a percentage of lost troops after defensive battles
2. WHEN calculating recovery percentage THEN the System SHALL apply the hospital level-specific recovery rate up to the configured maximum
3. WHEN troops are recovered THEN the System SHALL add them to the village garrison and consume resources based on recovery costs
4. WHEN generating battle reports THEN the System SHALL include the count of troops recovered by the Hospital
5. WHEN the Hospital is disabled for the world THEN the System SHALL hide hospital construction options and skip recovery calculations

### Requirement 10

**User Story:** As a player, I want a Market to trade resources with other players, so that I can balance my economy and support allies.

#### Acceptance Criteria

1. WHEN a Market is constructed THEN the System SHALL enable sending resources to other villages using merchant caravans
2. WHEN a Market is upgraded THEN the System SHALL increase the number of available merchant caravans based on the market level
3. WHEN a Market is upgraded THEN the System SHALL reduce the merchant travel time based on the level-specific speed curve
4. WHEN a player sends resources THEN the System SHALL deduct resources from the sending village and create a caravan command with calculated travel time
5. WHEN a caravan arrives at the destination THEN the System SHALL add resources to the receiving village up to storage capacity

### Requirement 11

**User Story:** As a player, I want a Hall of Banners to mint conquest resources and train conquest units, so that I can capture enemy villages.

#### Acceptance Criteria

1. WHEN a Hall of Banners is constructed THEN the System SHALL enable minting of noble coins or standards based on world configuration
2. WHEN a player mints a coin or standard THEN the System SHALL consume the configured resource costs and apply the minting duration
3. WHEN a player trains a conquest unit THEN the System SHALL verify that a minted coin or standard is available and deduct it
4. WHEN calculating daily minting limits THEN the System SHALL enforce per-village or per-account caps based on world configuration
5. WHEN a Hall of Banners is upgraded THEN the System SHALL reduce minting time and potentially increase daily minting caps

### Requirement 12

**User Story:** As a player, I want a Library to unlock research technologies, so that I can access advanced units, buildings, and strategic capabilities.

#### Acceptance Criteria

1. WHEN a Library is constructed THEN the System SHALL enable research of technology nodes including unit unlocks and building enhancements
2. WHEN a player starts research THEN the System SHALL verify prerequisites are met and deduct research costs
3. WHEN research completes THEN the System SHALL unlock the associated capabilities including advanced units or building levels
4. WHEN a Library is upgraded THEN the System SHALL reduce research time and potentially unlock additional research slots
5. WHEN research is cancelled THEN the System SHALL refund a configured percentage of research costs

### Requirement 13

**User Story:** As a player, I want to specialize my villages for different strategic roles, so that I can optimize my empire across multiple settlements.

#### Acceptance Criteria

1. WHEN a player focuses on offensive specialization THEN the System SHALL support high-level Barracks, Stable, Workshop, and Siege Foundry with minimal Wall investment
2. WHEN a player focuses on defensive specialization THEN the System SHALL support high-level Wall, Watchtower, Garrison, and Hospital with strong resource production
3. WHEN a player focuses on production specialization THEN the System SHALL support high-level resource buildings, Storage, Market, and Vault with minimal military buildings
4. WHEN a player focuses on scouting specialization THEN the System SHALL support high-level Watchtower, Scout Hall, and Stable with moderate defenses
5. WHEN a player focuses on conquest specialization THEN the System SHALL support high-level Hall of Banners, Wall, Hospital, and defensive troops

### Requirement 14

**User Story:** As a game administrator, I want to enforce building caps and prerequisites, so that players cannot bypass progression or create imbalanced villages.

#### Acceptance Criteria

1. WHEN a player attempts to upgrade a building beyond the world-configured maximum level THEN the System SHALL reject the upgrade and return error code ERR_CAP
2. WHEN a player attempts to construct a building without meeting prerequisite building levels THEN the System SHALL reject the construction and return error code ERR_PREREQ with missing requirements
3. WHEN a player attempts to construct a building without meeting prerequisite research THEN the System SHALL reject the construction and return error code ERR_RESEARCH
4. WHEN a player attempts to queue a building during emergency shield protection THEN the System SHALL allow construction but prevent military building upgrades if configured
5. WHEN calculating building costs THEN the System SHALL apply world archetype multipliers to base costs and times

### Requirement 15

**User Story:** As a game administrator, I want to apply world-specific multipliers to building costs and times, so that I can balance different server archetypes.

#### Acceptance Criteria

1. WHEN a world has speed archetype THEN the System SHALL apply build time multipliers reducing construction duration for all buildings
2. WHEN a world has hardcore archetype THEN the System SHALL apply cost multipliers increasing resource requirements for military and advanced buildings
3. WHEN calculating effective build time THEN the System SHALL multiply base build time by Town Hall reduction multiplier and world archetype multiplier
4. WHEN calculating effective build cost THEN the System SHALL multiply base resource costs by world archetype multiplier
5. WHEN displaying building information THEN the System SHALL show effective costs and times after all multipliers are applied

### Requirement 16

**User Story:** As a player, I want clear building information including costs, times, and benefits, so that I can make informed upgrade decisions.

#### Acceptance Criteria

1. WHEN viewing building details THEN the System SHALL display current level, next level benefits, resource costs, build time, and population cost
2. WHEN viewing building details THEN the System SHALL display prerequisite buildings and research with completion status
3. WHEN viewing building details THEN the System SHALL display production rates for resource buildings or training speed for military buildings
4. WHEN viewing building details THEN the System SHALL display special capabilities such as vault protection percentage or watchtower detection radius
5. WHEN resources are insufficient THEN the System SHALL highlight missing resource amounts and estimated time to accumulate them

### Requirement 17

**User Story:** As a player, I want to create temporary outposts for forward staging, so that I can position troops closer to targets during operations.

#### Acceptance Criteria

1. WHEN a player uses an outpost item or tech THEN the System SHALL create a temporary village with limited building slots and expiration time
2. WHEN an outpost is active THEN the System SHALL allow limited unit training and resource storage based on configured restrictions
3. WHEN hostile commands are inbound to an outpost THEN the System SHALL block new outpost creation in that location
4. WHEN an outpost expires THEN the System SHALL return stationed troops to their origin villages and remove the outpost marker from the map
5. WHEN an outpost is destroyed by attack THEN the System SHALL apply the same expiration cleanup process

### Requirement 18

**User Story:** As a developer, I want building configurations stored in a central data structure, so that I can maintain balance and version control.

#### Acceptance Criteria

1. WHEN the System initializes THEN the System SHALL load building definitions including costs, times, prerequisites, and caps from configuration files
2. WHEN building configurations are modified THEN the System SHALL validate that prerequisite chains do not create circular dependencies
3. WHEN building configurations are modified THEN the System SHALL validate that cost and time curves scale appropriately without negative or zero values
4. WHEN building configurations are modified THEN the System SHALL generate a configuration version hash for client cache invalidation
5. WHEN deploying building changes THEN the System SHALL validate that world-specific overrides do not break progression paths

### Requirement 19

**User Story:** As a developer, I want comprehensive validation and error handling for building operations, so that the system remains stable under all conditions.

#### Acceptance Criteria

1. WHEN a player submits a building upgrade with invalid building ID THEN the System SHALL reject the request and return error code ERR_INPUT
2. WHEN a player submits a building upgrade exceeding farm capacity THEN the System SHALL reject the request and return error code ERR_POP with current and required population
3. WHEN a player submits a building upgrade with insufficient resources THEN the System SHALL reject the request and return error code ERR_RES with missing amounts
4. WHEN concurrent building requests could violate queue slot limits THEN the System SHALL enforce limits atomically and reject violating requests
5. WHEN building operations fail THEN the System SHALL log the failure with correlation ID, player ID, village ID, building type, and reason code for telemetry

### Requirement 20

**User Story:** As a system operator, I want telemetry and monitoring for building operations, so that I can detect performance issues and balance problems.

#### Acceptance Criteria

1. WHEN a player queues a building upgrade THEN the System SHALL emit metrics including building type, level, world ID, and player ID
2. WHEN building upgrades complete THEN the System SHALL emit completion metrics with actual duration and any processing delays
3. WHEN building operations hit caps or fail validation THEN the System SHALL increment error counters by reason code and building type
4. WHEN queue processing runs THEN the System SHALL emit queue depth metrics and processing latency percentiles
5. WHEN wall repairs are blocked or decay is applied THEN the System SHALL track occurrences and alert on anomalies

