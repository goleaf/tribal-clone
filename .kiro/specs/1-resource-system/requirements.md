# Requirements Document

## Introduction

This document specifies the requirements for a comprehensive resource and village management system for a browser-based strategy game. The system encompasses resource production and storage, building construction, troop recruitment and combat, and village conquest mechanics. The design prioritizes extreme minimalism with text-based interfaces suitable for WAP-era constraints, using server-side rendering and minimal JavaScript dependencies.

## Glossary

- **Resource**: One of three primary materials (Wood, Clay, Iron) used for construction and recruitment
- **Production Rate**: The hourly rate at which a resource is generated, calculated from building levels and population
- **Storage Capacity**: Maximum amount of a resource that can be held, determined by Warehouse level
- **Building Level**: Current upgrade tier of a building (1-30 typically)
- **Population**: Farm capacity consumed by troops and buildings
- **Loyalty**: Village allegiance value (0-100) that determines ownership
- **Nobleman**: Prestige unit capable of reducing village loyalty for conquest
- **WAP-style**: Web interface design optimized for minimal bandwidth and simple HTML rendering

## Requirements

### Requirement 1: Resource Display and Production

**User Story:** As a player, I want to view my current resources and production rates, so that I can plan my village development and military operations.

#### Acceptance Criteria

1. WHEN a player views the village overview THEN the System SHALL display each resource (Wood, Clay, Iron) as text counters in the format "[Resource]: [Amount] (+[Rate]/hr)"
2. WHEN resource production rates are calculated THEN the System SHALL compute rates server-side based on production building levels and village population
3. WHILE resources accumulate THEN the System SHALL prevent resource amounts from exceeding the storage capacity determined by Warehouse level
4. WHEN resources reach storage capacity THEN the System SHALL stop accumulating that resource until capacity increases or resources are spent
5. WHEN displaying resources THEN the System SHALL render resources as text labels with numbers only, without icons or graphical elements

### Requirement 2: Building Construction and Upgrades

**User Story:** As a player, I want to construct and upgrade buildings in my village, so that I can improve resource production, unlock units, and strengthen defenses.

#### Acceptance Criteria

1. WHEN a player views the building list THEN the System SHALL display each building as a table row showing name, current level, upgrade cost, upgrade time, and an "Upgrade" hyperlink
2. WHEN a player initiates a building upgrade THEN the System SHALL deduct the required resources and add the upgrade to the construction queue
3. WHILE a building upgrade is in progress THEN the System SHALL display the queue as timestamped text entries with countdown timers
4. WHEN countdown timers are displayed THEN the System SHALL render timers server-side and update via meta-refresh tags or manual page reload
5. WHEN the Headquarters building is not constructed THEN the System SHALL prevent construction of other buildings
6. WHEN a building upgrade completes THEN the System SHALL increment the building level and apply new production rates or bonuses immediately

### Requirement 3: Village Overview Interface

**User Story:** As a player, I want a compact village overview screen, so that I can access all critical information without scrolling on limited-screen devices.

#### Acceptance Criteria

1. WHEN a player views the village overview THEN the System SHALL display a compact HTML table with buildings/levels in the left column, resources/rates in the center column, and troop movements in the right column
2. WHEN displaying the navigation header THEN the System SHALL render hyperlinks for [Village] [Troops] [Market] [Research] [Reports] [Messages] [Alliance] [Profile]
3. WHEN rendering the village interface THEN the System SHALL present buildings as menu entries without village map graphics
4. WHEN displaying troop movements THEN the System SHALL show incoming and outgoing movements as text entries with timestamps
5. WHEN rendering the interface THEN the System SHALL ensure all critical information appears above the fold for zero-scroll access

### Requirement 4: Troop Recruitment

**User Story:** As a player, I want to recruit military units, so that I can attack other villages and defend my own.

#### Acceptance Criteria

1. WHEN a player views the recruitment interface THEN the System SHALL display unit production queues as text in the format "[Unit] ([Completed]/[Total] complete, [Time] remaining)"
2. WHEN displaying recruitment costs THEN the System SHALL show costs as "Cost: [Wood]W, [Clay]C, [Iron]I, [Pop] Pop, Time: [Duration]"
3. WHEN a player views unit statistics THEN the System SHALL display comparison tables with columns for Attack, Defense (Infantry), Defense (Cavalry), Defense (Archer), Speed, and Carry Capacity
4. WHEN a player recruits units THEN the System SHALL provide quantity input boxes and "Recruit" buttons without drag-and-drop interfaces
5. WHEN recruitment is initiated THEN the System SHALL deduct resources and population capacity, then add units to the training queue

### Requirement 5: Troop Movement

**User Story:** As a player, I want to send troops between villages, so that I can attack enemies, support allies, and relocate forces.

#### Acceptance Criteria

1. WHEN a player initiates troop movement THEN the System SHALL generate timestamped movement entries via hyperlink commands
2. WHEN troops are in transit THEN the System SHALL display movement entries visible to the sending player showing destination, arrival time, and unit composition
3. WHEN troops approach a village THEN the System SHALL display incoming movement entries to the defending player showing origin, arrival time, and attack type
4. WHEN troops arrive at destination THEN the System SHALL resolve the movement action (attack, support, or return) and update both villages accordingly

### Requirement 6: Combat Resolution

**User Story:** As a player, I want battles to resolve fairly with detailed reports, so that I can understand combat outcomes and improve my strategies.

#### Acceptance Criteria

1. WHEN a battle occurs THEN the System SHALL calculate combat server-side using sequential rounds where attackers strike first, then defenders counter-attack
2. WHEN calculating damage THEN the System SHALL use the formula: Attack Value × Quantity × Random(0.8-1.2) vs Defense Value × Quantity × Wall Bonus × Random(0.8-1.2)
3. WHEN resolving combat THEN the System SHALL apply unit type advantages in the cycle: cavalry > archers > infantry > spears > cavalry
4. WHEN a battle completes THEN the System SHALL generate a combat report as formatted text tables showing initial forces, wall bonus, casualties per unit type, resources plundered, and loyalty damage
5. WHEN displaying combat reports THEN the System SHALL archive reports as hyperlinked entries ordered by timestamp, with detail pages showing full statistics in tabular format
6. IF a battle report includes visual indicators THEN the System SHALL limit graphics to a single 16×16 icon indicating victory, defeat, or scout report

### Requirement 7: Village Conquest

**User Story:** As a player, I want to capture enemy villages using noblemen, so that I can expand my empire and eliminate rivals.

#### Acceptance Criteria

1. WHEN a player trains noblemen THEN the System SHALL require Academy research completion and special coins minted at the Rally Point
2. WHEN a nobleman attacks a village THEN the System SHALL reduce target village loyalty by a random value between 20 and 35 points
3. WHEN village loyalty reaches zero THEN the System SHALL transfer village ownership to the attacker while preserving all buildings and troops
4. WHEN displaying village loyalty THEN the System SHALL show loyalty as text in the format "Loyalty: [Current]/100" on village overview pages
5. WHEN loyalty attacks occur THEN the System SHALL record historical attacks as text entries showing attacker name, timestamp, and loyalty change
6. WHEN a player owns multiple villages THEN the System SHALL provide a dropdown menu for quick navigation between owned villages

### Requirement 8: Core Buildings

**User Story:** As a player, I want access to 15 specialized buildings, so that I can develop my village across production, military, and strategic dimensions.

#### Acceptance Criteria

1. WHEN constructing production buildings THEN the System SHALL provide Timber Camp (wood), Clay Pit (clay), and Iron Mine (iron) that increase respective resource production rates per level
2. WHEN constructing military buildings THEN the System SHALL provide Barracks (infantry), Stable (cavalry), and Workshop (siege weapons) that unlock and train respective unit types
3. WHEN constructing support buildings THEN the System SHALL provide Headquarters (unlocks construction), Academy (technology research), Smithy (unit upgrades), and Rally Point (troop coordination and coin minting)
4. WHEN constructing economic buildings THEN the System SHALL provide Market (resource trading), Warehouse (storage capacity), and Farm (population capacity)
5. WHEN constructing defensive buildings THEN the System SHALL provide Wall (defensive bonus) and Hiding Place (resource protection from plunder)

### Requirement 9: Storage and Resource Protection

**User Story:** As a player, I want to protect some resources from plunder, so that I can recover after enemy attacks.

#### Acceptance Criteria

1. WHEN a village is plundered THEN the System SHALL protect resources up to the Hiding Place capacity from being taken
2. WHEN calculating plunder THEN the System SHALL only allow attackers to take resources exceeding the Hiding Place protection limit
3. WHEN displaying storage information THEN the System SHALL show both Warehouse capacity and Hiding Place protection limits
