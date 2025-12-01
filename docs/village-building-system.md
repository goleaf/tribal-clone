# Village & Building System

## Overview

This document describes the complete village and building system for our medieval tribal war browser MMO. The building system is the foundation of player progression, strategic depth, and long-term engagement.

---

## 1. Village Concept

### What is a Village?

A **village** is the player's primary base of operations—a settlement that produces resources, trains units, researches technologies, and serves as the staging ground for military campaigns. Each village represents a fortified settlement with walls, buildings, and population that the player develops over time.

### Village Ownership Progression

- **Starting State**: Every player begins with 1 village (their capital)
- **Early Game (0-30 days)**: 1 village only
- **Mid Game (30-90 days)**: Can conquer or settle 2-5 villages depending on research and achievements
- **Late Game (90+ days)**: Maximum of 10-15 villages for most players
- **Elite Players**: Top-tier players with special achievements can manage up to 20 villages
- **Capital Village**: The first village is always the capital and cannot be lost (only relocated with special items)

### Village Slots & Expansion

Players unlock additional village slots through:
- **Research**: Academy technologies unlock village slots
- **Achievements**: Conquering X enemies, reaching population milestones
- **Premium Items**: Rare scrolls or tokens that grant +1 village slot
- **Tribal Bonuses**: Being in a large, active tribe grants bonus slots


### Village Archetypes

Players can specialize villages into different roles:

#### 1. **Offensive Hub (Hammer Village)**
- Focus: Maximum troop production and training speed
- Key Buildings: Multiple barracks, stables, siege workshop, rally point
- Resource Focus: Iron and food for unit upkeep
- Strategy: Minimal defense, all resources go to offensive units
- Typical Location: Behind front lines, protected by tribe

#### 2. **Defensive Bastion (Wall Village)**
- Focus: Impenetrable defense and troop preservation
- Key Buildings: Max-level wall, watchtower, hospital, granary, warehouse
- Resource Focus: Stone and wood for fortifications
- Strategy: Positioned on borders, designed to absorb attacks
- Special Feature: High hospital capacity to revive defending troops

#### 3. **Economic Powerhouse (Farm Village)**
- Focus: Maximum resource production
- Key Buildings: All resource buildings at high levels, market, warehouse
- Resource Focus: Balanced production of all resources
- Strategy: Located in safe interior, feeds other villages
- Trade Hub: Sends resources to offensive/defensive villages

#### 4. **Support Village (Utility Hub)**
- Focus: Research, scouting, and special operations
- Key Buildings: Academy, watchtower, market, shrine
- Resource Focus: Gold and knowledge points
- Strategy: Provides tribe-wide bonuses and intelligence

#### 5. **Hybrid Village (Balanced)**
- Focus: Self-sufficient, can adapt to any role
- Key Buildings: Balanced mix of all building types
- Resource Focus: Even distribution
- Strategy: Good for beginners or flexible players

#### 6. **Special Villages**
- **Wonder Village**: Late-game village that can build a Wonder (tribe victory condition)
- **Trade Hub**: Maximum market levels, positioned centrally for tribe logistics
- **Scouting Outpost**: Forward base with watchtower, minimal buildings, used for intelligence
- **Noble Village**: Specialized for training nobles/chieftains to conquer new villages


---

## 2. Complete Building List

### Core Buildings (Required)

1. **Headquarters (HQ)** - Central command building, unlocks other buildings
2. **Timber Camp** - Produces wood
3. **Clay Pit** - Produces clay/brick
4. **Iron Mine** - Produces iron ore
5. **Grain Farm** - Produces food
6. **Warehouse** - Stores wood, clay, and iron
7. **Granary** - Stores food
8. **Rally Point** - Coordinates troop movements
9. **Wall** - Defensive fortification

### Military Buildings

10. **Barracks** - Trains infantry units
11. **Stable** - Trains cavalry units
12. **Siege Workshop** - Builds siege engines
13. **Archery Range** - Trains ranged units
14. **Naval Dock** - Trains naval units (if map has water)
15. **War Camp** - Trains elite/special units
16. **Smithy** - Upgrades unit weapons and armor
17. **Hospital** - Heals wounded troops after battle

### Economic & Support Buildings

18. **Market** - Trades resources with other players
19. **Merchant Guild** - Increases trade capacity and reduces fees
20. **Watchtower** - Provides scouting and early warning
21. **Academy** - Researches technologies
22. **Library** - Stores knowledge, increases research speed
23. **Shrine/Temple** - Provides faith bonuses and special abilities
24. **Town Hall** - Increases population capacity and loyalty
25. **Tavern** - Recruits heroes and mercenaries

### Advanced Buildings

26. **Embassy** - Allows tribe membership and diplomacy
27. **Treasury** - Stores gold and valuable items
28. **Alchemist Lab** - Crafts potions and special items
29. **Engineer's Guild** - Reduces construction time
30. **Spy Network** - Enables espionage missions
31. **Monument** - Provides prestige and tribe bonuses
32. **Wonder** - Late-game victory condition building

### Decorative & Special Buildings

33. **Statue Garden** - Cosmetic, provides small morale bonus
34. **Festival Grounds** - Seasonal events and bonuses
35. **Hero's Hall** - Displays achievements and trophies
36. **Training Grounds** - Increases unit experience gain
37. **Blacksmith Quarter** - Crafts legendary equipment
38. **Mystic Circle** - Rare building for magical effects


---

## 3. Building Details Table

### Core Buildings

| Building | Max Level | Purpose | Unlock Conditions | Upgrade Effects | Synergies | Strategic Uses |
|----------|-----------|---------|-------------------|-----------------|-----------|----------------|
| **Headquarters** | 30 | Central command, unlocks buildings | None (starter) | +5% construction speed per level, unlocks new buildings at levels 3/5/10/15/20 | Engineer's Guild, Town Hall | Must be upgraded to unlock advanced buildings; prioritize early |
| **Timber Camp** | 30 | Wood production | HQ level 1 | +50 wood/hour per level (exponential scaling) | Warehouse, Merchant Guild | Essential for all construction; build multiple if possible |
| **Clay Pit** | 30 | Clay/brick production | HQ level 1 | +45 clay/hour per level | Warehouse, Smithy | Critical for defensive buildings and unit armor |
| **Iron Mine** | 30 | Iron ore production | HQ level 2 | +40 iron/hour per level | Warehouse, Smithy | Required for weapons and advanced units |
| **Grain Farm** | 30 | Food production | HQ level 1 | +60 food/hour per level | Granary, Market | Supports army upkeep; negative food = troop starvation |
| **Warehouse** | 30 | Resource storage | HQ level 1 | +2,000 capacity per level | All resource buildings | Prevents resource overflow; higher level = harder to plunder |
| **Granary** | 30 | Food storage | HQ level 1 | +2,500 capacity per level | Grain Farm, Market | Separate from warehouse; critical for large armies |
| **Rally Point** | 20 | Troop coordination | HQ level 3 | +1 simultaneous attack per 5 levels | All military buildings | Level 10 unlocks reinforcement requests; level 20 = mass attacks |
| **Wall** | 30 | Base defense | HQ level 2 | +50 defense points per level, +2% defender bonus | Watchtower, Hospital | Exponential defense scaling; level 20+ makes village very hard to conquer |

### Military Buildings

| Building | Max Level | Purpose | Unlock Conditions | Upgrade Effects | Synergies | Strategic Uses |
|----------|-----------|---------|-------------------|-----------------|-----------|----------------|
| **Barracks** | 25 | Infantry training | HQ level 3, Rally Point 1 | -2% training time per level, +1 queue slot every 5 levels | Smithy, War Camp | Can build multiple barracks for parallel training |
| **Stable** | 25 | Cavalry training | HQ level 5, Barracks 5 | -2% training time per level, +1 queue slot every 5 levels | Smithy, Grain Farm | Cavalry is expensive but powerful; requires high food production |
| **Siege Workshop** | 20 | Siege engine construction | HQ level 10, Smithy 5 | -3% build time per level, +1 siege capacity every 4 levels | Engineer's Guild, Iron Mine | Slow production but essential for breaking walls |
| **Archery Range** | 25 | Ranged unit training | HQ level 4, Barracks 3 | -2% training time per level, +5% range damage every 5 levels | Watchtower, Smithy | Archers are cost-effective defenders |
| **Naval Dock** | 20 | Naval unit construction | HQ level 8, adjacent to water | -2% build time per level, +1 fleet capacity every 5 levels | Market (trade ships), Watchtower | Only available on coastal villages |
| **War Camp** | 15 | Elite unit training | HQ level 15, Barracks 15, Stable 15 | -3% training time, +5% elite unit stats per level | All military buildings | Trains champions, berserkers, and legendary units |
| **Smithy** | 20 | Unit upgrades | HQ level 5, Barracks 3 | Unlocks weapon/armor tiers, +2% unit stats per level | All military buildings | Each level unlocks new equipment tiers |
| **Hospital** | 20 | Troop healing | HQ level 8, Wall 5 | +100 healing capacity per level, -5% healing time | Wall, Shrine | Saves troops from death; only heals defenders |


### Economic & Support Buildings

| Building | Max Level | Purpose | Unlock Conditions | Upgrade Effects | Synergies | Strategic Uses |
|----------|-----------|---------|-------------------|-----------------|-----------|----------------|
| **Market** | 25 | Resource trading | HQ level 5, Warehouse 3, Granary 3 | +1 merchant per level, -2% trade fees every 5 levels | Merchant Guild, all resource buildings | Essential for resource balance; higher level = faster trades |
| **Merchant Guild** | 15 | Trade enhancement | HQ level 10, Market 10 | +2 merchants per level, -5% fees, +10% trade speed | Market, Treasury | Unlocks bulk trading and trade routes |
| **Watchtower** | 20 | Scouting & intelligence | HQ level 6, Rally Point 3 | +50km vision per level, -10% scout time | Wall, Spy Network | See incoming attacks earlier; level 15+ reveals attacker composition |
| **Academy** | 25 | Technology research | HQ level 10, Library 1 | -3% research time per level, unlocks new techs every 5 levels | Library, all buildings | Core progression system; expensive but essential |
| **Library** | 20 | Knowledge storage | HQ level 8, Academy 1 | +5% research speed per level, +1 simultaneous research every 10 levels | Academy, Shrine | Allows multiple research projects at high levels |
| **Shrine/Temple** | 20 | Faith & blessings | HQ level 7 | +1 faith point/day per level, unlocks blessings | Hospital, Hero's Hall | Provides buffs like +10% production or +5% combat stats |
| **Town Hall** | 20 | Population & loyalty | HQ level 6 | +50 population per level, +2% loyalty | All buildings | Higher population = more building slots; loyalty prevents conquest |
| **Tavern** | 15 | Hero recruitment | HQ level 9, Town Hall 5 | +1 hero slot every 5 levels, better hero quality | Hero's Hall, Shrine | Heroes provide powerful bonuses and lead armies |

### Advanced Buildings

| Building | Max Level | Purpose | Unlock Conditions | Upgrade Effects | Synergies | Strategic Uses |
|----------|-----------|---------|-------------------|-----------------|-----------|----------------|
| **Embassy** | 10 | Tribe membership | HQ level 5 | +5 tribe member capacity per level (for leaders) | Monument, Rally Point | Required to join/create tribes; leaders need high level |
| **Treasury** | 20 | Gold & item storage | HQ level 12, Warehouse 10 | +1,000 gold capacity per level, +2 item slots every 5 levels | Merchant Guild, Alchemist Lab | Stores premium currency and rare items safely |
| **Alchemist Lab** | 15 | Potion crafting | HQ level 14, Academy 10 | -5% crafting time per level, unlocks new recipes | Shrine, Library | Craft speed boosts, healing potions, combat buffs |
| **Engineer's Guild** | 15 | Construction speed | HQ level 11, HQ 10 | -3% construction time per level (all buildings) | All buildings | Passive bonus; essential for late-game building |
| **Spy Network** | 15 | Espionage | HQ level 13, Watchtower 10 | +1 spy per level, +5% success rate | Watchtower, Academy | Gather intelligence, sabotage enemies, steal resources |
| **Monument** | 10 | Prestige & bonuses | HQ level 15, Town Hall 10 | +100 prestige per level, +2% tribe-wide bonus | Embassy, Hero's Hall | Provides tribe-wide buffs; expensive but powerful |
| **Wonder** | 100 | Victory condition | HQ level 20, all buildings 15+, tribe decision | +1% tribe victory progress per level | Monument, Embassy | Requires massive tribe effort; reaching level 100 = server win |


### Decorative & Special Buildings

| Building | Max Level | Purpose | Unlock Conditions | Upgrade Effects | Synergies | Strategic Uses |
|----------|-----------|---------|-------------------|-----------------|-----------|----------------|
| **Statue Garden** | 10 | Cosmetic & morale | HQ level 8, Town Hall 5 | +1% morale per level, cosmetic variety | Town Hall, Monument | Small bonus but shows prestige; purely optional |
| **Festival Grounds** | 5 | Seasonal events | HQ level 10, Tavern 5 | +10% event rewards per level | Tavern, Shrine | Active during holidays; provides limited-time bonuses |
| **Hero's Hall** | 15 | Achievement display | HQ level 12, Tavern 10 | +1 hero slot every 5 levels, +5% hero XP | Tavern, Monument | Shows off accomplishments; functional hero benefits |
| **Training Grounds** | 15 | Unit experience | HQ level 10, Barracks 10 | +3% unit XP gain per level | All military buildings | Units trained here gain experience faster |
| **Blacksmith Quarter** | 10 | Legendary crafting | HQ level 18, Smithy 15 | Unlocks legendary equipment tiers | Smithy, Alchemist Lab | Craft unique items for heroes and elite units |
| **Mystic Circle** | 5 | Magical effects | Special quest reward | +5% to all production, +3% combat stats | Shrine, Academy | Extremely rare; only 1 per village; game-changing |

---

## 4. Upgrade System

### Level Ranges & Progression

- **Levels 1-5**: Tutorial phase, fast upgrades (minutes to hours)
- **Levels 6-10**: Early game, moderate speed (hours to 1 day)
- **Levels 11-15**: Mid game, significant investment (1-3 days)
- **Levels 16-20**: Late game, major commitment (3-7 days)
- **Levels 21-25**: End game, extreme dedication (7-14 days)
- **Levels 26-30**: Max level, legendary status (14-30 days)

### Cost Scaling

Costs increase exponentially with each level:

```
Base Cost × (1.5 ^ level)
```

**Example: Timber Camp**
- Level 1: 100 wood, 80 clay, 60 iron, 40 food → 5 minutes
- Level 5: 500 wood, 400 clay, 300 iron, 200 food → 2 hours
- Level 10: 2,500 wood, 2,000 clay, 1,500 iron, 1,000 food → 12 hours
- Level 15: 12,000 wood, 10,000 clay, 7,500 iron, 5,000 food → 3 days
- Level 20: 60,000 wood, 50,000 clay, 37,500 iron, 25,000 food → 10 days
- Level 25: 300,000 wood, 250,000 clay, 187,500 iron, 125,000 food → 25 days
- Level 30: 1,500,000 wood, 1,250,000 clay, 937,500 iron, 625,000 food → 60 days


### Construction Queue System

#### Basic Rules
- **Default Queue**: 1 building at a time per village
- **Queue Expansion**: 
  - HQ level 10: +1 queue slot (2 simultaneous builds)
  - HQ level 20: +1 queue slot (3 simultaneous builds)
  - Engineer's Guild level 10: +1 queue slot
  - Premium item: +1 temporary queue slot (7 days)

#### Queue Management
- Players can queue up to 10 buildings in advance
- Queued buildings start automatically when a slot opens
- Players can reorder the queue at any cost
- Canceling a building refunds 80% of resources
- Pausing construction is not allowed (prevents gaming the system)

### Parallel Construction Options

Players can build multiple buildings simultaneously through:

1. **Multiple Villages**: Each village has independent construction queues
2. **Queue Slots**: Unlock additional slots within a single village
3. **Resource Buildings**: Can build multiple timber camps, clay pits, etc. in the same village
4. **Military Buildings**: Can construct multiple barracks, stables, etc. for faster training

**Strategic Note**: Building multiple resource buildings is often more efficient than upgrading one to max level.

### Speed-Up Mechanics (Fair Play)

#### Time Reduction Methods
1. **Engineer's Guild**: Passive -3% per level (max -45% at level 15)
2. **Tribe Bonuses**: Active tribe members provide +1% speed per 10 members (max +10%)
3. **Hero Abilities**: Certain heroes provide construction speed buffs
4. **Blessings**: Shrine blessings can grant +20% speed for 24 hours
5. **Premium Speed-Up**: Instant completion or time reduction using gold

#### Premium Speed-Up Costs
- **Instant Completion**: 1 gold per hour remaining (max 1,000 gold)
- **50% Time Reduction**: 0.5 gold per hour remaining
- **Finish Last Hour Free**: If <1 hour remains, complete for free (prevents micro-transactions)

#### Fair Play Principles
- No "pay to win" instant armies
- Speed-ups are expensive and limited
- Free players can compete through smart planning
- Premium mainly saves time, not power


---

## 5. Village Specialization System

### How Specialization Works

Players can specialize villages by:
1. **Building Selection**: Choose which buildings to construct
2. **Upgrade Priority**: Focus resources on specific building types
3. **Resource Allocation**: Send resources from economic villages to military villages
4. **Location Strategy**: Place villages based on map position and tribe needs

### Complete Build Orders by Archetype

#### Offensive Hub (Hammer Village)

**Goal**: Maximum offensive troop production

**Phase 1: Foundation (Days 1-7)**
1. HQ → Level 5
2. All resource buildings → Level 5
3. Warehouse → Level 5
4. Granary → Level 5
5. Rally Point → Level 3
6. Barracks → Level 5

**Phase 2: Military Expansion (Days 8-30)**
1. HQ → Level 10
2. Stable → Level 10
3. Barracks → Level 15
4. Build 2nd Barracks → Level 10
5. Smithy → Level 10
6. Grain Farm → Level 15 (army upkeep)
7. Iron Mine → Level 15 (weapons)

**Phase 3: Elite Production (Days 31-90)**
1. HQ → Level 15
2. War Camp → Level 10
3. Siege Workshop → Level 10
4. Build 3rd Barracks → Level 10
5. Build 2nd Stable → Level 10
6. Smithy → Level 15
7. All resource buildings → Level 20

**Phase 4: Maximum Output (Days 91+)**
1. HQ → Level 20
2. All military buildings → Level 20+
3. Build 4th Barracks
4. Engineer's Guild → Level 15
5. Academy → Research offensive technologies
6. Minimal wall (level 5-10 only)

**Final Layout**:
- 4 Barracks (level 20+)
- 2 Stables (level 20+)
- 1 Siege Workshop (level 15+)
- 1 War Camp (level 15+)
- 1 Smithy (level 20)
- Minimal defense buildings
- Maximum resource production for unit creation


#### Defensive Bastion (Wall Village)

**Goal**: Impenetrable defense and troop preservation

**Phase 1: Foundation (Days 1-7)**
1. HQ → Level 5
2. All resource buildings → Level 5
3. Warehouse → Level 5
4. Granary → Level 5
5. Wall → Level 5
6. Rally Point → Level 3

**Phase 2: Fortification (Days 8-30)**
1. HQ → Level 10
2. Wall → Level 15
3. Watchtower → Level 10
4. Hospital → Level 10
5. Archery Range → Level 10 (defenders)
6. Clay Pit → Level 15 (wall materials)
7. Warehouse → Level 15 (protect resources)

**Phase 3: Maximum Defense (Days 31-90)**
1. HQ → Level 15
2. Wall → Level 25
3. Hospital → Level 15
4. Watchtower → Level 15
5. Barracks → Level 10 (defensive troops)
6. Town Hall → Level 15 (loyalty)
7. Granary → Level 20 (siege resistance)

**Phase 4: Fortress (Days 91+)**
1. HQ → Level 20
2. Wall → Level 30
3. Hospital → Level 20
4. Watchtower → Level 20
5. Multiple Archery Ranges
6. Shrine → Level 15 (defensive blessings)
7. Minimal offensive buildings

**Final Layout**:
- Wall (level 30)
- Hospital (level 20)
- Watchtower (level 20)
- 2-3 Archery Ranges (level 15+)
- 1 Barracks (level 10-15)
- Maximum storage buildings
- Town Hall (level 20 for loyalty)


#### Economic Powerhouse (Farm Village)

**Goal**: Maximum resource production and trade capacity

**Phase 1: Foundation (Days 1-7)**
1. HQ → Level 5
2. All resource buildings → Level 7
3. Warehouse → Level 5
4. Granary → Level 5
5. Build 2nd Timber Camp → Level 5
6. Build 2nd Clay Pit → Level 5

**Phase 2: Production Scaling (Days 8-30)**
1. HQ → Level 10
2. All resource buildings → Level 15
3. Build 3rd Timber Camp → Level 10
4. Build 3rd Clay Pit → Level 10
5. Build 2nd Iron Mine → Level 10
6. Build 2nd Grain Farm → Level 10
7. Market → Level 10

**Phase 3: Trade Hub (Days 31-90)**
1. HQ → Level 15
2. All resource buildings → Level 20
3. Market → Level 20
4. Merchant Guild → Level 10
5. Warehouse → Level 20
6. Granary → Level 20
7. Engineer's Guild → Level 10

**Phase 4: Maximum Output (Days 91+)**
1. HQ → Level 20
2. All resource buildings → Level 25-30
3. Market → Level 25
4. Merchant Guild → Level 15
5. Build 4th of each resource building
6. Treasury → Level 15
7. Minimal military (1 barracks for defense only)

**Final Layout**:
- 4 Timber Camps (level 25+)
- 4 Clay Pits (level 25+)
- 3 Iron Mines (level 25+)
- 3 Grain Farms (level 25+)
- Market (level 25)
- Merchant Guild (level 15)
- Maximum storage
- Minimal defense (wall level 10)


#### Support Village (Utility Hub)

**Goal**: Research, scouting, and tribe support

**Phase 1: Foundation (Days 1-7)**
1. HQ → Level 5
2. All resource buildings → Level 5
3. Warehouse → Level 5
4. Granary → Level 5
5. Rally Point → Level 3
6. Watchtower → Level 5

**Phase 2: Intelligence Network (Days 8-30)**
1. HQ → Level 10
2. Watchtower → Level 15
3. Academy → Level 10
4. Library → Level 5
5. Embassy → Level 5
6. Market → Level 10
7. All resource buildings → Level 10

**Phase 3: Research Center (Days 31-90)**
1. HQ → Level 15
2. Academy → Level 20
3. Library → Level 15
4. Watchtower → Level 20
5. Spy Network → Level 10
6. Shrine → Level 15
7. Tavern → Level 10 (heroes)

**Phase 4: Tribe Powerhouse (Days 91+)**
1. HQ → Level 20
2. Academy → Level 25
3. Library → Level 20
4. Monument → Level 10
5. Embassy → Level 10
6. Spy Network → Level 15
7. Alchemist Lab → Level 10

**Final Layout**:
- Academy (level 25)
- Library (level 20)
- Watchtower (level 20)
- Spy Network (level 15)
- Monument (level 10)
- Embassy (level 10)
- Shrine (level 15)
- Minimal military buildings


#### Hybrid Village (Balanced)

**Goal**: Self-sufficient, adaptable to any situation

**Phase 1: Foundation (Days 1-7)**
1. HQ → Level 5
2. All resource buildings → Level 5
3. Warehouse → Level 5
4. Granary → Level 5
5. Rally Point → Level 3
6. Barracks → Level 3

**Phase 2: Balanced Growth (Days 8-30)**
1. HQ → Level 10
2. All resource buildings → Level 10
3. Barracks → Level 10
4. Stable → Level 8
5. Wall → Level 10
6. Market → Level 8
7. Academy → Level 8

**Phase 3: Versatile Development (Days 31-90)**
1. HQ → Level 15
2. All resource buildings → Level 15
3. Barracks → Level 15
4. Stable → Level 12
5. Wall → Level 15
6. Academy → Level 15
7. Smithy → Level 12
8. Watchtower → Level 10

**Phase 4: Complete Village (Days 91+)**
1. HQ → Level 20
2. All buildings → Level 15-20
3. Can pivot to any specialization
4. Good for beginners learning the game

**Final Layout**:
- Balanced mix of all building types
- No extreme specialization
- Can adapt to changing needs
- Good for solo players or small tribes


---

## 6. Early/Mid/Late-Game Priorities

### Early Game (Days 1-14): Foundation Building

#### Casual Player Path (1-2 hours/day)
**Week 1 Priorities**:
1. HQ → Level 5
2. All resource buildings → Level 5
3. Warehouse & Granary → Level 5
4. Rally Point → Level 3
5. Barracks → Level 3
6. Complete tutorial quests

**Week 2 Priorities**:
1. HQ → Level 8
2. All resource buildings → Level 8
3. Barracks → Level 8
4. Wall → Level 5
5. Market → Level 5
6. Join a tribe (Embassy level 1)

**Key Tips**:
- Focus on resource production first
- Don't rush military until resources are stable
- Complete daily quests for bonuses
- Join an active tribe for protection

#### Hardcore Player Path (4-6 hours/day)
**Week 1 Priorities**:
1. HQ → Level 10
2. All resource buildings → Level 10
3. Barracks → Level 10
4. Stable → Level 8
5. Wall → Level 8
6. Market → Level 8
7. Academy → Level 5

**Week 2 Priorities**:
1. HQ → Level 12
2. All resource buildings → Level 12
3. Build 2nd Barracks → Level 8
4. Smithy → Level 10
5. Academy → Level 10
6. Start aggressive expansion

**Key Tips**:
- Use premium speed-ups strategically
- Farm inactive players for resources
- Build offensive army quickly
- Coordinate with tribe for early conquests


### Mid Game (Days 15-60): Specialization & Expansion

#### Casual Player Path
**Weeks 3-4 Priorities**:
1. HQ → Level 12
2. All resource buildings → Level 12
3. Decide on village specialization
4. Academy → Level 12 (research village slots)
5. Wall → Level 12 (if defensive)
6. Barracks → Level 12 (if offensive)

**Weeks 5-8 Priorities**:
1. HQ → Level 15
2. Complete specialization build order
3. Conquer or settle 2nd village
4. Build 2nd village as complementary type
5. Establish resource trade routes
6. Participate in tribe wars

**Key Tips**:
- Specialize your capital village
- 2nd village should complement 1st (e.g., farm + hammer)
- Focus on tribe cooperation
- Don't overextend with too many villages

#### Hardcore Player Path
**Weeks 3-4 Priorities**:
1. HQ → Level 15
2. All resource buildings → Level 15
3. Complete offensive village build
4. Conquer 2nd and 3rd villages
5. Academy → Level 15
6. War Camp → Level 10

**Weeks 5-8 Priorities**:
1. HQ → Level 18
2. Manage 4-5 villages with different roles
3. Build dedicated farm villages
4. Create offensive hammer village
5. Establish defensive border villages
6. Lead tribe attacks

**Key Tips**:
- Aggressive expansion is key
- Coordinate with tribe for mass conquests
- Build specialized village network
- Farm resources from weaker players
- Invest in premium for speed advantages


### Late Game (Days 61+): Dominance & Victory

#### Casual Player Path
**Months 3-4 Priorities**:
1. HQ → Level 18 in all villages
2. Complete all village specializations
3. Manage 3-5 villages efficiently
4. Academy → Level 20 (all techs)
5. Participate in Wonder construction
6. Maintain strong defense

**Months 5-6 Priorities**:
1. HQ → Level 20 in capital
2. Max out key buildings (level 25+)
3. Support tribe Wonder efforts
4. Collect legendary items
5. Mentor new players
6. Enjoy end-game content

**Key Tips**:
- Focus on quality over quantity
- Support tribe victory conditions
- Collect achievements and cosmetics
- Maintain sustainable village network
- Enjoy social aspects of game

#### Hardcore Player Path
**Months 3-4 Priorities**:
1. HQ → Level 20 in all villages
2. Manage 10-15 villages
3. Build Wonder village
4. Academy → Level 25 (all villages)
5. Dominate server rankings
6. Lead tribe to victory

**Months 5-6 Priorities**:
1. Max out all buildings (level 30)
2. Complete Wonder construction
3. Achieve server victory
4. Collect all achievements
5. Prepare for next server/season
6. Compete in global rankings

**Key Tips**:
- Coordinate massive tribe operations
- Optimize village network for maximum efficiency
- Invest heavily in Wonder construction
- Crush rival tribes
- Aim for server domination


---

## 7. Visual & UX Design

### Building Screen Layout

#### Main Village View
- **Isometric 2D Map**: Top-down view of village with all buildings visible
- **Building Slots**: 30-40 available building plots
- **Visual Progression**: Buildings change appearance as they level up
- **Construction Animation**: Scaffolding and workers visible during upgrades
- **Resource Display**: Always visible at top of screen
- **Quick Actions**: Buttons for common tasks (train troops, research, trade)

#### Building Detail Panel
When clicking a building, show:
- **Building Name & Level**: Large, clear display
- **Current Stats**: Production rate, capacity, bonuses
- **Upgrade Button**: Prominent, shows cost and time
- **Next Level Preview**: What stats will improve
- **Requirements**: What's needed to unlock upgrade (red if not met)
- **Queue Position**: If building is queued
- **Cancel/Speed-Up Options**: For active construction

#### Construction Queue Interface
- **Visual Timeline**: Horizontal bar showing all queued buildings
- **Drag-and-Drop Reordering**: Easy queue management
- **Time Estimates**: Countdown timers for each building
- **Resource Projection**: Shows when you'll have enough resources for queued items
- **Queue Capacity**: Shows available slots (e.g., "2/3 slots used")
- **Batch Actions**: "Cancel All", "Speed Up All" buttons

### Building Categories & Filters
- **All Buildings**: Default view
- **Resource**: Production and storage buildings
- **Military**: Barracks, stables, workshops
- **Economic**: Market, trade, storage
- **Research**: Academy, library
- **Defense**: Wall, watchtower, hospital
- **Special**: Unique and rare buildings

### Upgrade Information Display
- **Cost Breakdown**: Wood, clay, iron, food with icons
- **Time Required**: Large countdown timer
- **Prerequisites**: Clear list of requirements
- **Benefits**: "+50 wood/hour", "+100 storage capacity"
- **Comparison**: Current vs. next level stats side-by-side
- **Efficiency Rating**: Shows if upgrade is cost-effective


### Interactive Features

#### Building Placement
- **Drag-and-Drop**: Move buildings to different plots
- **Rotation**: Rotate buildings for aesthetic appeal
- **Snap-to-Grid**: Buildings automatically align
- **Collision Detection**: Can't overlap buildings
- **Undo/Redo**: Easy to fix mistakes

#### Visual Feedback
- **Hover Effects**: Buildings highlight on mouseover
- **Construction Progress**: Visual progress bar on building
- **Completion Notification**: Pop-up when building finishes
- **Sound Effects**: Satisfying audio for upgrades
- **Particle Effects**: Sparkles or glow for completed buildings

#### Mobile Optimization
- **Touch-Friendly**: Large buttons and tap targets
- **Swipe Navigation**: Easy to move around village
- **Pinch-to-Zoom**: Zoom in/out of village view
- **Simplified UI**: Streamlined for smaller screens
- **Offline Queue**: Queue buildings even without connection

### Accessibility Features
- **Colorblind Mode**: Alternative color schemes
- **Screen Reader Support**: Text descriptions for all elements
- **Keyboard Navigation**: Full keyboard control
- **High Contrast Mode**: For visibility
- **Text Scaling**: Adjustable font sizes

---

## 8. Optional & Experimental Features

### Prestige Buildings

**Concept**: Ultra-rare buildings that provide massive bonuses but require extreme investment.

#### Examples:
1. **Grand Colosseum** (Max Level 5)
   - Unlock: HQ 25, 1 million of each resource
   - Effect: +10% to all troop stats per level
   - Limit: 1 per player (any village)

2. **Ancient Library** (Max Level 5)
   - Unlock: Academy 25, Library 20
   - Effect: -20% research time per level
   - Limit: 1 per player

3. **Dragon's Lair** (Max Level 3)
   - Unlock: Special quest chain
   - Effect: Unlocks dragon units
   - Limit: 1 per server (tribe must control)

4. **Eternal Forge** (Max Level 5)
   - Unlock: Smithy 20, Blacksmith Quarter 10
   - Effect: +15% unit production speed per level
   - Limit: 1 per player


### Building Skins & Cosmetics

**Concept**: Visual customization without gameplay impact.

#### Skin Types:
1. **Architectural Styles**:
   - Roman (stone columns, red roofs)
   - Viking (wooden longhouses, thatch roofs)
   - Oriental (pagodas, curved roofs)
   - Fantasy (magical towers, glowing crystals)

2. **Seasonal Themes**:
   - Winter (snow-covered buildings)
   - Spring (flowers and greenery)
   - Autumn (orange leaves)
   - Halloween (spooky decorations)

3. **Prestige Skins**:
   - Golden buildings (for top players)
   - Legendary skins (achievement rewards)
   - Animated buildings (premium)

#### Acquisition Methods:
- **Purchase**: Premium currency
- **Achievements**: Unlock through gameplay
- **Events**: Limited-time seasonal events
- **Tribe Rewards**: Tribe victory bonuses

### Seasonal Buildings

**Concept**: Limited-time buildings available during special events.

#### Examples:
1. **Winter Festival Hall** (December)
   - Duration: 30 days
   - Effect: +25% resource production during event
   - Converts to Statue Garden after event

2. **Harvest Shrine** (September-October)
   - Duration: 60 days
   - Effect: +50% food production
   - Provides special harvest units

3. **War Memorial** (Server Anniversary)
   - Duration: Permanent if built during event
   - Effect: +5% to all stats
   - Commemorates server history

4. **Lunar Temple** (Lunar New Year)
   - Duration: 14 days
   - Effect: +100% research speed
   - Unlocks special technologies


### Regional Buildings

**Concept**: Buildings unique to specific map regions or biomes.

#### Desert Region:
1. **Oasis Well** (Replaces standard well)
   - Effect: +30% water production (special resource)
   - Bonus: +10% troop morale in desert

2. **Sand Fortress** (Enhanced wall)
   - Effect: +20% defense against cavalry
   - Appearance: Sandstone walls

#### Mountain Region:
1. **Quarry** (Enhanced clay pit)
   - Effect: +50% stone production
   - Bonus: -20% wall construction time

2. **Mountain Fortress** (Enhanced wall)
   - Effect: +30% defense bonus
   - Appearance: Carved into mountainside

#### Forest Region:
1. **Lumber Mill** (Enhanced timber camp)
   - Effect: +50% wood production
   - Bonus: -20% building construction time

2. **Treehouse Watchtower** (Enhanced watchtower)
   - Effect: +50% vision range
   - Appearance: Built in giant trees

#### Coastal Region:
1. **Harbor** (Enhanced naval dock)
   - Effect: +50% naval production
   - Bonus: +20% trade capacity

2. **Lighthouse** (Enhanced watchtower)
   - Effect: +100% naval vision
   - Appearance: Tall stone lighthouse

#### Plains Region:
1. **Mega Farm** (Enhanced grain farm)
   - Effect: +50% food production
   - Bonus: +20% cavalry training speed

2. **Trading Post** (Enhanced market)
   - Effect: +50% merchant capacity
   - Appearance: Large caravan hub


### Building Synergy System

**Concept**: Buildings provide bonus effects when placed near each other.

#### Synergy Examples:

1. **Military District**:
   - Barracks + Stable + Smithy within 3 tiles
   - Bonus: -10% training time for all units
   - Visual: Connected by training grounds

2. **Economic Quarter**:
   - Market + Warehouse + Merchant Guild adjacent
   - Bonus: -15% trade fees, +20% merchant speed
   - Visual: Shared marketplace area

3. **Research Campus**:
   - Academy + Library + Shrine within 3 tiles
   - Bonus: -15% research time, +10% tech effectiveness
   - Visual: Connected by scholar paths

4. **Defensive Perimeter**:
   - Wall + Watchtower + Hospital adjacent
   - Bonus: +20% defense, +30% healing capacity
   - Visual: Integrated fortification system

5. **Resource Hub**:
   - 3+ resource buildings of same type adjacent
   - Bonus: +15% production for that resource
   - Visual: Shared worker housing

### Building Evolution System

**Concept**: Buildings can evolve into advanced versions at high levels.

#### Evolution Examples:

1. **Barracks → Elite Barracks** (Level 20)
   - Unlocks: Champion infantry units
   - Appearance: Larger, more impressive structure
   - Bonus: +25% infantry stats

2. **Market → Grand Bazaar** (Level 20)
   - Unlocks: International trade routes
   - Appearance: Massive marketplace with multiple stalls
   - Bonus: +50% merchant capacity

3. **Academy → University** (Level 25)
   - Unlocks: Advanced technologies
   - Appearance: Multiple buildings, campus-like
   - Bonus: +2 simultaneous research projects

4. **Wall → Fortress Wall** (Level 25)
   - Unlocks: Defensive towers and gates
   - Appearance: Massive stone fortification
   - Bonus: +100% defense, +50% garrison capacity

5. **Shrine → Grand Cathedral** (Level 20)
   - Unlocks: Powerful blessings and miracles
   - Appearance: Towering religious structure
   - Bonus: +3 active blessings simultaneously


### Building Destruction & Reconstruction

**Concept**: Buildings can be destroyed in combat and must be rebuilt.

#### Destruction Mechanics:
- **Siege Damage**: Siege weapons can target specific buildings
- **Conquest Damage**: Conquered villages have 20-50% building damage
- **Sabotage**: Spy missions can damage buildings
- **Natural Disasters**: Random events (rare) can damage buildings

#### Reconstruction System:
- **Repair Cost**: 50% of original construction cost
- **Repair Time**: 25% of original construction time
- **Partial Functionality**: Buildings at 50%+ health still work at reduced capacity
- **Priority Repair**: Players can choose which buildings to repair first
- **Auto-Repair**: Option to automatically repair damaged buildings

#### Strategic Implications:
- Attackers can cripple economy by destroying resource buildings
- Defenders must protect key buildings
- Reconstruction creates resource sink for conquered villages
- Adds depth to siege warfare

### Building Automation Features

**Concept**: Quality-of-life features for managing multiple villages.

#### Auto-Build Templates:
1. **Save Layout**: Save current village layout as template
2. **Apply Template**: Apply saved layout to new village
3. **Smart Build**: AI suggests optimal build order
4. **Copy Village**: Duplicate another village's layout

#### Auto-Queue System:
1. **Recurring Upgrades**: Automatically queue next level when building finishes
2. **Resource Threshold**: Only build when resources reach X amount
3. **Priority System**: Set building priorities (high/medium/low)
4. **Smart Queue**: AI fills queue based on village specialization

**Smart Queue Details (per-village toggle):**
- **Inputs**: Village specialization tag (e.g., "econ", "defense", "offense"), current building levels, storage caps, worker availability, and resource/hour.
- **Logic**: Maintains a rolling 3-item queue; always includes one production/storage item, one military/research item, and a filler upgrade with the best ROI for the specialization. Skips items that block due to prereqs or storage caps.
- **Constraints**: Respects player-set max level per building, resource thresholds, and queue slot limits; pauses when building slot is occupied by player manual build.
- **Resource Safety**: Auto-trims upgrade choices to keep at least X hours of idle resources as buffer (configurable).
- **Transparency**: Shows "Why picked" tooltip (e.g., "+180 wood/hr, pays back in 26m; unlocks Stables req").
- **Overrides**: Player can pin specific buildings to always prioritize or ban items entirely (e.g., "never queue Wall").
- **Failover**: If specialization unknown, defaults to balanced template; if resources insufficient for 10 minutes, auto-downgrades to cheaper picks.

#### Batch Operations:
1. **Upgrade All**: Upgrade all buildings of same type across villages
2. **Sync Villages**: Keep multiple villages at same building levels
3. **Resource Distribution**: Automatically send resources where needed
4. **Mass Construction**: Start same building in multiple villages


### Building Achievements & Milestones

**Concept**: Reward players for building accomplishments.

#### Achievement Examples:

1. **Master Builder**:
   - Requirement: Upgrade any building to level 30
   - Reward: -5% construction time (permanent)
   - Badge: Golden hammer icon

2. **Economic Genius**:
   - Requirement: All resource buildings level 25+ in one village
   - Reward: +10% resource production (permanent)
   - Badge: Golden coin icon

3. **Fortress Lord**:
   - Requirement: Wall level 30, Hospital level 20, Watchtower level 20
   - Reward: +15% defense bonus (permanent)
   - Badge: Castle icon

4. **Research Pioneer**:
   - Requirement: Academy level 25, Library level 20
   - Reward: -10% research time (permanent)
   - Badge: Scroll icon

5. **Military Mastermind**:
   - Requirement: 4+ Barracks level 20+, 2+ Stables level 20+
   - Reward: -10% training time (permanent)
   - Badge: Sword icon

6. **Village Collector**:
   - Requirement: Own 10 villages simultaneously
   - Reward: +1 village slot
   - Badge: Crown icon

7. **Wonder Builder**:
   - Requirement: Contribute to Wonder construction
   - Reward: Unique cosmetic skin
   - Badge: Wonder icon

8. **Perfectionist**:
   - Requirement: All buildings level 20+ in one village
   - Reward: +5% to all stats in that village
   - Badge: Star icon


---

## 9. Advanced Building Mechanics

### Building Slots & Expansion

**Base Slots**: Every village starts with 30 building slots

**Expansion Methods**:
1. **Town Hall Upgrades**: +1 slot every 5 levels (max +4)
2. **HQ Upgrades**: +1 slot at levels 15, 20, 25, 30 (max +4)
3. **Premium Expansion**: Purchase +5 slots (max 2 purchases)
4. **Achievement Rewards**: Unlock +1 slot for specific achievements
5. **Maximum Slots**: 50 slots per village

**Slot Types**:
- **Standard Slots**: Can build any building
- **Resource Slots**: Only for resource buildings (unlimited)
- **Military Slots**: Only for military buildings (max 10)
- **Special Slots**: Only for unique buildings (max 5)

### Building Dependencies & Tech Trees

**Concept**: Buildings unlock in a logical progression tree.

#### Tier 1 (HQ 1-5):
- Timber Camp, Clay Pit, Iron Mine, Grain Farm
- Warehouse, Granary
- Rally Point

#### Tier 2 (HQ 6-10):
- Barracks, Archery Range
- Wall, Watchtower
- Market, Town Hall
- Embassy

#### Tier 3 (HQ 11-15):
- Stable, Siege Workshop
- Academy, Library
- Hospital, Smithy
- Tavern, Shrine

#### Tier 4 (HQ 16-20):
- War Camp, Naval Dock
- Merchant Guild, Treasury
- Spy Network, Alchemist Lab
- Engineer's Guild, Monument

#### Tier 5 (HQ 21-25):
- Blacksmith Quarter, Training Grounds
- Hero's Hall, Mystic Circle
- Wonder (special conditions)


### Building Maintenance & Upkeep

**Concept**: High-level buildings require ongoing maintenance.

#### Maintenance System:
- **Buildings Level 1-10**: No maintenance cost
- **Buildings Level 11-20**: 1% of production as maintenance
- **Buildings Level 21-30**: 2% of production as maintenance
- **Military Buildings**: Additional food upkeep for facilities

#### Maintenance Resources:
- **Wood**: For building repairs
- **Food**: For worker wages
- **Gold**: For advanced building upkeep

#### Neglect Consequences:
- **0-7 Days Unpaid**: Building operates at 100%
- **8-14 Days Unpaid**: Building operates at 75%
- **15-30 Days Unpaid**: Building operates at 50%
- **31+ Days Unpaid**: Building operates at 25%
- **Never Destroyed**: Buildings never fully stop working

#### Auto-Maintenance:
- **Option**: Automatically pay maintenance from resources
- **Priority**: Set which resources to use first
- **Alerts**: Notification when maintenance is due

### Building Specialization Paths

**Concept**: Buildings can be specialized for different bonuses at level 15+.

#### Specialization Examples:

**Barracks Specializations** (Choose at level 15):
1. **Speed Training**: -30% training time, -10% unit stats
2. **Elite Training**: +20% unit stats, +30% training time
3. **Mass Production**: +50% queue capacity, +10% cost
4. **Efficient Training**: -20% resource cost, normal time

**Market Specializations** (Choose at level 15):
1. **Trade Hub**: +50% merchant capacity, +20% fees
2. **Fast Trading**: +50% merchant speed, -20% capacity
3. **Efficient Trading**: -50% fees, normal speed/capacity
4. **Bulk Trading**: +100% capacity, +50% time

**Academy Specializations** (Choose at level 20):
1. **Rapid Research**: -40% research time, +30% cost
2. **Efficient Research**: -30% research cost, +20% time
3. **Advanced Research**: Unlock unique technologies, normal time/cost
4. **Parallel Research**: +1 simultaneous research, +20% time


---

## 10. Building Balance & Game Economy

### Resource Production Balance

**Goal**: Ensure no single resource is too scarce or abundant.

#### Production Ratios (Per Hour at Level 20):
- **Wood**: 1,000/hour (most common, used for everything)
- **Clay**: 900/hour (common, used for defense and buildings)
- **Iron**: 800/hour (less common, used for weapons and advanced units)
- **Food**: 1,200/hour (most abundant, but consumed by armies)
- **Gold**: 10/hour (rare, premium resource)

#### Consumption Ratios:
- **Building Construction**: 40% wood, 30% clay, 20% iron, 10% food
- **Unit Training**: 20% wood, 10% clay, 40% iron, 30% food
- **Research**: 30% wood, 20% clay, 30% iron, 20% food
- **Army Upkeep**: 100% food (ongoing)

### Building Cost Progression

**Formula**: Base Cost × (1.5 ^ Level) × Building Multiplier

#### Building Multipliers:
- **Resource Buildings**: 1.0x (cheapest)
- **Storage Buildings**: 1.2x
- **Basic Military**: 1.5x
- **Economic Buildings**: 1.3x
- **Advanced Military**: 2.0x
- **Research Buildings**: 2.5x
- **Special Buildings**: 3.0x (most expensive)

### Time Investment Balance

**Goal**: Keep players engaged without excessive waiting.

#### Time Ranges by Level:
- **Levels 1-5**: 5 minutes - 2 hours (instant gratification)
- **Levels 6-10**: 2 hours - 12 hours (daily check-ins)
- **Levels 11-15**: 12 hours - 3 days (strategic planning)
- **Levels 16-20**: 3 days - 7 days (major commitment)
- **Levels 21-25**: 7 days - 14 days (hardcore dedication)
- **Levels 26-30**: 14 days - 30 days (legendary status)

#### Concurrent Progress:
- Multiple villages allow parallel progression
- Queue system enables planning ahead
- Speed-ups provide flexibility
- Tribe cooperation accelerates growth


### Building Power Scaling

**Goal**: Ensure late-game buildings feel powerful but not game-breaking.

#### Power Curve:
- **Levels 1-10**: Linear growth (+10% per level)
- **Levels 11-20**: Exponential growth (+15% per level)
- **Levels 21-30**: Diminishing returns (+8% per level)

**Reasoning**: Early levels feel impactful, mid-game provides strong growth, late-game requires massive investment for smaller gains.

#### Example: Timber Camp Production
- Level 1: 50 wood/hour
- Level 5: 90 wood/hour (+80% from level 1)
- Level 10: 200 wood/hour (+300% from level 1)
- Level 15: 600 wood/hour (+1,100% from level 1)
- Level 20: 1,500 wood/hour (+2,900% from level 1)
- Level 25: 3,000 wood/hour (+5,900% from level 1)
- Level 30: 5,000 wood/hour (+9,900% from level 1)

### Building Diversity Incentives

**Goal**: Encourage players to build variety, not just max one building type.

#### Diversity Bonuses:
1. **Balanced Village**: If all building types present, +5% to all production
2. **Complete Set**: If all core buildings level 10+, +10% construction speed
3. **Specialized Master**: If 5+ buildings of same type level 20+, +15% to that category
4. **Renaissance Village**: If 20+ different building types, +10% to all stats

#### Anti-Spam Mechanics:
- **Diminishing Returns**: Each additional building of same type produces 10% less
- **Slot Limits**: Maximum 5 of any single building type (except resource buildings)
- **Synergy Requirements**: Some bonuses require building diversity

---

## 11. Implementation Priorities

### Phase 1: Core Buildings (MVP)
**Timeline**: Months 1-2

**Essential Buildings**:
1. Headquarters
2. Timber Camp, Clay Pit, Iron Mine, Grain Farm
3. Warehouse, Granary
4. Barracks
5. Rally Point
6. Wall
7. Market

**Core Features**:
- Basic construction system
- Simple upgrade mechanics
- Resource production
- Single construction queue
- Basic UI


### Phase 2: Military & Economy (Months 3-4)

**Additional Buildings**:
1. Stable
2. Archery Range
3. Siege Workshop
4. Smithy
5. Watchtower
6. Hospital
7. Academy
8. Town Hall
9. Embassy

**New Features**:
- Multiple building queues
- Building dependencies
- Village specialization
- Basic synergies

### Phase 3: Advanced Systems (Months 5-6)

**Additional Buildings**:
1. War Camp
2. Library
3. Merchant Guild
4. Treasury
5. Shrine/Temple
6. Tavern
7. Engineer's Guild
8. Spy Network

**New Features**:
- Building specializations
- Advanced synergies
- Prestige buildings
- Building skins
- Automation features

### Phase 4: End-Game Content (Months 7-8)

**Additional Buildings**:
1. Monument
2. Wonder
3. Alchemist Lab
4. Blacksmith Quarter
5. Training Grounds
6. Hero's Hall
7. Mystic Circle
8. Regional buildings

**New Features**:
- Building evolution
- Seasonal buildings
- Advanced automation
- Achievement system
- Building destruction/repair

### Phase 5: Polish & Expansion (Months 9+)

**Focus Areas**:
- Balance adjustments
- Additional building variants
- More cosmetic options
- Community-requested features
- Seasonal content updates
- New building types based on player feedback


---

## 12. Technical Considerations

### Database Schema Requirements

**Buildings Table**:
- `building_id` (primary key)
- `village_id` (foreign key)
- `building_type` (enum)
- `level` (integer)
- `position_x`, `position_y` (coordinates)
- `construction_start_time` (timestamp)
- `construction_end_time` (timestamp)
- `health` (percentage)
- `specialization` (enum, nullable)

**Building Queue Table**:
- `queue_id` (primary key)
- `village_id` (foreign key)
- `building_type` (enum)
- `target_level` (integer)
- `queue_position` (integer)
- `resources_reserved` (JSON)

**Building Templates Table**:
- `template_id` (primary key)
- `player_id` (foreign key)
- `template_name` (string)
- `building_layout` (JSON)

### Performance Optimization

**Caching Strategy**:
- Cache building stats for each level
- Cache production rates per village
- Cache construction queue state
- Invalidate cache on building completion

**Database Queries**:
- Index on `village_id` for fast lookups
- Index on `construction_end_time` for queue processing
- Batch updates for multiple villages
- Lazy loading for building details

**Client-Side Optimization**:
- Preload building images
- Sprite sheets for building animations
- Minimize API calls with local state
- WebSocket for real-time updates


### API Endpoints

**Building Management**:
- `GET /api/village/{id}/buildings` - List all buildings
- `POST /api/village/{id}/building/construct` - Start construction
- `PUT /api/village/{id}/building/{building_id}/upgrade` - Upgrade building
- `DELETE /api/village/{id}/building/{building_id}` - Demolish building
- `POST /api/village/{id}/building/{building_id}/speedup` - Speed up construction
- `GET /api/village/{id}/construction-queue` - Get queue status
- `PUT /api/village/{id}/construction-queue/reorder` - Reorder queue

**Building Information**:
- `GET /api/building-types` - List all building types
- `GET /api/building-type/{type}/stats` - Get stats for all levels
- `GET /api/building-type/{type}/requirements` - Get unlock requirements

**Templates & Automation**:
- `POST /api/player/building-template` - Save template
- `GET /api/player/building-templates` - List templates
- `POST /api/village/{id}/apply-template` - Apply template
- `PUT /api/village/{id}/auto-build-settings` - Configure automation

### Real-Time Updates

**WebSocket Events**:
- `building.construction.started` - Building construction began
- `building.construction.completed` - Building finished
- `building.construction.cancelled` - Construction cancelled
- `building.damaged` - Building took damage
- `building.repaired` - Building repaired
- `queue.updated` - Construction queue changed

**Push Notifications**:
- Building completion alerts
- Queue slot available
- Resources full (can't build)
- Building under attack
- Maintenance due

---

## 13. Balancing & Testing

### Key Metrics to Monitor

**Player Engagement**:
- Average buildings per village
- Most/least popular buildings
- Average time to max level
- Queue utilization rate
- Speed-up purchase frequency

**Economic Balance**:
- Resource production vs. consumption
- Building cost vs. benefit
- Time investment vs. power gain
- Premium vs. free player progression

**Strategic Diversity**:
- Village archetype distribution
- Building specialization choices
- Multi-village strategies
- Tribe coordination patterns


### Testing Scenarios

**Functional Testing**:
1. Build every building type to max level
2. Test all upgrade paths and dependencies
3. Verify queue system with multiple buildings
4. Test speed-up mechanics and cancellations
5. Verify resource consumption and production
6. Test building placement and movement
7. Verify specialization choices and effects

**Balance Testing**:
1. Compare offensive vs. defensive village power
2. Test economic village resource output
3. Verify building cost scaling feels fair
4. Test time investment vs. reward
5. Compare free vs. premium progression
6. Test multi-village management complexity

**Stress Testing**:
1. Player with 20 villages, all building simultaneously
2. Massive construction queue (100+ buildings)
3. Rapid building/canceling cycles
4. Concurrent building operations
5. Database load with 10,000+ active players

**User Experience Testing**:
1. New player tutorial flow
2. Building UI clarity and usability
3. Queue management ease
4. Mobile vs. desktop experience
5. Accessibility features
6. Visual feedback and satisfaction

---

## 14. Future Expansion Ideas

### Building Variants

**Concept**: Alternative versions of buildings with different stats.

**Examples**:
1. **Barracks Variants**:
   - Infantry Barracks (standard)
   - Heavy Infantry Barracks (+defense, -speed)
   - Light Infantry Barracks (+speed, -defense)

2. **Resource Building Variants**:
   - Standard Timber Camp
   - Logging Camp (+production, +cost)
   - Sustainable Forest (-production, -upkeep)

3. **Wall Variants**:
   - Stone Wall (balanced)
   - Wooden Palisade (cheap, weak)
   - Iron Fortress (expensive, strong)


### Dynamic Buildings

**Concept**: Buildings that change based on game state.

**Examples**:
1. **Seasonal Transformation**:
   - Buildings change appearance with seasons
   - Winter: Snow-covered, reduced production
   - Summer: Lush, increased production

2. **War State Buildings**:
   - Buildings fortify during tribal wars
   - Peacetime: Normal appearance
   - Wartime: Defensive upgrades visible

3. **Population-Based Scaling**:
   - Buildings grow visually with population
   - Low population: Small, simple buildings
   - High population: Large, elaborate buildings

### Community Buildings

**Concept**: Buildings that benefit entire tribe.

**Examples**:
1. **Tribal Fortress**:
   - Built by entire tribe
   - Provides tribe-wide defense bonus
   - Requires contributions from all members

2. **Great Library**:
   - Shared research building
   - All tribe members benefit from research
   - Faster research with more contributors

3. **Trade Network**:
   - Connects all tribe villages
   - Free resource transfers within tribe
   - Shared merchant pool

4. **War Monument**:
   - Commemorates tribe victories
   - Provides morale bonuses
   - Displays tribe achievements

### Player-Designed Buildings

**Concept**: Allow players to customize building appearance.

**Features**:
1. **Building Editor**:
   - Choose colors, decorations, flags
   - Add custom text/names
   - Arrange building elements

2. **Sharing System**:
   - Share designs with tribe
   - Community building gallery
   - Vote on best designs

3. **Rewards**:
   - Featured designs get bonuses
   - Cosmetic rewards for creativity
   - Prestige for popular designs


---

## 15. Monetization Strategy

### Premium Building Features

**Fair Monetization Principles**:
- Never sell power directly
- Time-saving is acceptable
- Cosmetics are primary revenue
- Free players can compete

**Premium Options**:

1. **Construction Speed-Ups**:
   - Instant completion (expensive)
   - 50% time reduction (moderate)
   - Last hour free (encourages engagement)

2. **Additional Queue Slots**:
   - +1 permanent slot: $9.99
   - +1 temporary slot (30 days): $2.99
   - Maximum 2 additional slots

3. **Building Skins**:
   - Individual building skins: $1.99
   - Theme packs (all buildings): $14.99
   - Seasonal skins: $0.99
   - Legendary skins: $4.99

4. **Automation Features**:
   - Auto-build templates: Free
   - Advanced automation: $4.99/month
   - Multi-village sync: $2.99/month

5. **Building Slots**:
   - +5 building slots: $4.99
   - Maximum 2 purchases per village

6. **Premium Buildings**:
   - Cosmetic-only buildings: $2.99
   - Special effect buildings: $9.99
   - Never provide combat advantage

### Battle Pass / Season Pass

**Building-Themed Rewards**:
- Level 1-10: Building skins
- Level 11-20: Speed-up items
- Level 21-30: Exclusive buildings
- Level 31-40: Legendary skins
- Level 41-50: Prestige buildings

**Free Track**: Basic rewards for all players
**Premium Track**: $9.99, enhanced rewards


---

## 16. Summary & Design Philosophy

### Core Design Principles

1. **Depth Without Complexity**:
   - Many buildings and options
   - Clear progression paths
   - Intuitive UI/UX
   - Gradual learning curve

2. **Strategic Diversity**:
   - Multiple viable strategies
   - No single "best" build
   - Encourage experimentation
   - Reward creativity

3. **Long-Term Engagement**:
   - Progression takes months, not days
   - Always something to build
   - Regular content updates
   - Seasonal variety

4. **Fair Competition**:
   - Free players can compete
   - Premium saves time, not power
   - Skill matters more than spending
   - Tribe cooperation > individual spending

5. **Social Integration**:
   - Buildings support tribe gameplay
   - Shared objectives (Wonder)
   - Trade and cooperation
   - Competitive rankings

### Success Metrics

**Player Retention**:
- 70%+ Day 1 retention
- 40%+ Day 7 retention
- 20%+ Day 30 retention
- 10%+ Day 90 retention

**Engagement**:
- Average 3+ logins per day
- 30+ minutes daily playtime
- 80%+ of players in tribes
- 50%+ manage multiple villages

**Monetization**:
- 5-10% conversion to paying
- $10-20 average monthly spend
- 80%+ revenue from cosmetics/convenience
- <20% revenue from speed-ups

**Balance**:
- All village archetypes viable
- No building type ignored
- Free vs. premium power gap <20%
- Strategic diversity in top players


### Final Thoughts

The village and building system is the heart of the game. It must be:

- **Satisfying**: Every upgrade feels meaningful
- **Strategic**: Choices matter and create different playstyles
- **Social**: Supports tribe cooperation and competition
- **Balanced**: Fair for all players regardless of spending
- **Evolving**: Regular updates keep content fresh

**Key Differentiators**:
1. **38+ unique buildings** (more than most competitors)
2. **Deep specialization system** (offensive, defensive, economic, support)
3. **Building synergies** (placement matters)
4. **Regional variants** (map location affects buildings)
5. **Fair monetization** (cosmetics > power)
6. **Tribe integration** (Wonder, shared buildings)
7. **Long-term progression** (months to max out)
8. **Strategic depth** (multiple viable paths)

**Development Priorities**:
1. Core buildings and mechanics (Months 1-2)
2. Military and economic expansion (Months 3-4)
3. Advanced systems and features (Months 5-6)
4. End-game content and polish (Months 7-8)
5. Ongoing updates and balance (Months 9+)

This building system provides the foundation for a deep, engaging, and long-lasting MMO experience that rewards both casual and hardcore players while maintaining fair competition and strategic diversity.

---

**Document Version**: 1.0  
**Last Updated**: December 1, 2025  
**Status**: Design Complete - Ready for Implementation Planning
