# World Map & Navigation

## Overview

The world map is the strategic canvas where all player interactions, territorial control, and tribal warfare unfold. It serves as both a tactical planning tool and a living representation of the game's political landscape. Players spend significant time analyzing the map, identifying targets, coordinating attacks, and monitoring threats. The map must be intuitive, information-rich, and performant even with thousands of villages displayed simultaneously.

## Map Structure

### Grid-Based Coordinate System

The world uses a **square grid** with Cartesian coordinates (X, Y) where:
- Center of the world is (0, 0)
- X-axis runs horizontally (West to East)
- Y-axis runs vertically (South to North)
- Each coordinate represents one village location
- Typical world size: -500 to +500 on both axes (1,000 × 1,000 grid = 1,000,000 possible village locations)

**Why Grid-Based:**
- Simple, intuitive coordinate system familiar to players
- Easy distance calculations using Euclidean or Manhattan distance
- Clean visual representation with clear boundaries
- Straightforward database queries for range-based searches
- Compatible with tile-based rendering for performance

### World Configuration Options

#### Option 1: Single Continuous World
**Description:** One massive grid where all players coexist.

**Characteristics:**
- Size: 1,000 × 1,000 to 2,000 × 2,000 grid
- All players on same map regardless of join date
- Natural clustering around center (0,0) which becomes most contested
- Edges are safer but more isolated

**Pros:**
- True MMO experience with everyone interacting
- Emergent political geography
- Simple to understand and implement

**Cons:**
- Performance challenges with thousands of villages
- New players may struggle against established powers
- Center becomes overcrowded, edges become ghost towns

**Best For:** Games prioritizing single-server community and long-term political dynamics.


#### Option 2: Ring-Based Expansion World
**Description:** World starts small and expands in concentric rings over time.

**Characteristics:**
- Initial ring: -100 to +100 (200 × 200)
- New rings unlock every 2-4 weeks
- Each ring adds 100 units in each direction
- Ring 1: Core (-100 to +100)
- Ring 2: Inner (-200 to +200)
- Ring 3: Middle (-300 to +300)
- Ring 4: Outer (-400 to +400)
- Ring 5: Frontier (-500 to +500)

**Pros:**
- Controlled growth prevents early overcrowding
- Creates natural progression and expansion goals
- New rings can have special resources or bonuses
- Easier server load management

**Cons:**
- Artificial boundaries feel less organic
- Timing of expansions must be carefully balanced
- Players may feel constrained early on

**Best For:** Games with staged progression and controlled population growth.

#### Option 3: Multi-Continent World
**Description:** World divided into 4-9 distinct continents separated by impassable ocean.

**Characteristics:**
- Each continent: 400 × 400 grid
- 4-continent layout: NW, NE, SW, SE quadrants
- Ocean gaps of 50-100 tiles between continents
- Special harbor villages enable cross-continent travel
- Each continent has unique resource distribution

**Pros:**
- Natural regional identities and politics
- Reduces direct competition in early game
- Cross-continent warfare becomes epic late-game content
- Can balance populations across continents

**Cons:**
- Splits player base into isolated groups
- Requires special mechanics for ocean travel
- More complex to implement and balance

**Best For:** Games emphasizing regional identity and late-game expansion wars.


#### Option 4: Sector-Based World
**Description:** World divided into 100 sectors (10 × 10 grid of sectors), each sector is 100 × 100 tiles.

**Characteristics:**
- Total world: 1,000 × 1,000 tiles
- Each sector: 100 × 100 tiles (10,000 villages)
- Sectors labeled K1-K100 or by coordinates (Sector 5,5)
- Sector boundaries visible on map
- Sector-specific bonuses, events, or rules

**Pros:**
- Easy to reference locations ("I'm in K45")
- Can implement sector-specific mechanics
- Natural organizational unit for tribes
- Performance optimization by loading sectors

**Cons:**
- Arbitrary divisions may feel artificial
- Sector boundaries can create strategic oddities
- Requires UI to show sector information

**Best For:** Games with strong tribal organization and sector-based objectives.

#### Option 5: Dynamic Worlds (Seasons/Rounds)
**Description:** Worlds reset every 6-12 months, starting fresh with new maps.

**Characteristics:**
- Each world lasts one "season"
- Winners declared at end based on points/territory
- New season starts with fresh map
- Can vary map size/layout each season
- Veteran and new player worlds separate

**Pros:**
- Prevents permanent dominance by old players
- Fresh starts attract returning players
- Can experiment with different map layouts
- Clear victory conditions and endings

**Cons:**
- Loss of long-term investment feeling
- Requires migration/reward systems between seasons
- Not true persistent MMO

**Best For:** Competitive games with clear win conditions and seasonal content.


### Recommended Configuration

**Hybrid: Ring-Based Expansion with Sectors**

Combine the controlled growth of rings with the organizational clarity of sectors:
- Start with Core Ring (Sectors K45-K56, approximately -250 to +250)
- Expand rings every 3 weeks
- Each ring adds 2-3 new sector rows
- Final world: 1,000 × 1,000 with 100 sectors
- Sectors provide organizational framework
- Rings control growth and create progression

This balances controlled growth, clear organization, and long-term scalability.

## Coordinates & Distance

### Coordinate System Details

**Format:** (X, Y) where both are integers
- X increases moving East (right)
- Y increases moving North (up)
- Example: Village at (123, -456) is 123 tiles East and 456 tiles South of center

**Coordinate Display:**
- Full format: "123|-456" (using pipe separator)
- Sector format: "K67 (123|-456)"
- Relative format: "45 tiles NE" (from current village)

### Distance Calculation

**Euclidean Distance (Recommended):**
```
distance = √[(x₂ - x₁)² + (y₂ - y₁)²]
```

**Example:**
- Village A: (100, 100)
- Village B: (150, 130)
- Distance: √[(150-100)² + (130-100)²] = √[2500 + 900] = √3400 ≈ 58.3 tiles

**Manhattan Distance (Alternative):**
```
distance = |x₂ - x₁| + |y₂ - y₁|
```
- Same example: |150-100| + |130-100| = 50 + 30 = 80 tiles
- Simpler calculation but less realistic for diagonal movement

**Recommendation:** Use Euclidean distance for realism, but display rounded to whole numbers.


### Travel Time & Distance

**Base Travel Speed:**
- Infantry: 10 tiles per hour
- Cavalry: 20 tiles per hour
- Siege: 5 tiles per hour
- Speed of slowest unit determines army speed

**Travel Time Formula:**
```
travel_time = distance / unit_speed
```

**Example Distances & Travel Times:**

| Distance | Infantry | Cavalry | Siege | Strategic Context |
|----------|----------|---------|-------|-------------------|
| 5 tiles | 30 min | 15 min | 1 hour | Immediate neighbors, quick raids |
| 20 tiles | 2 hours | 1 hour | 4 hours | Local area, same sector |
| 50 tiles | 5 hours | 2.5 hours | 10 hours | Cross-sector, requires planning |
| 100 tiles | 10 hours | 5 hours | 20 hours | Long-distance, overnight attacks |
| 200 tiles | 20 hours | 10 hours | 40 hours | Cross-continent, major operations |
| 500 tiles | 50 hours | 25 hours | 100 hours | World-spanning, rare |

**Strategic Implications:**

**Close Range (0-20 tiles):**
- Constant threat from neighbors
- Quick raids and support possible
- Difficult to defend against coordinated attacks
- Ideal for tribal clustering

**Medium Range (20-100 tiles):**
- Requires planning and coordination
- Time for scouts to return with intel
- Defenders have warning time
- Sweet spot for tribal warfare

**Long Range (100+ tiles):**
- Major operations only
- Significant resource investment
- High risk of interception
- Used for conquests and relocations

### Distance-Based Strategies

**Defensive Depth:**
- Keep support villages 10-30 tiles from frontline
- Can respond to attacks within 1-3 hours
- Too close: vulnerable to same attacks
- Too far: support arrives too late

**Offensive Positioning:**
- Forward bases 20-50 tiles from enemy territory
- Launch attacks from multiple angles
- Coordinate arrival times across distances

**Tribal Territory:**
- Core territory: 50-100 tile radius
- Members can support each other within 2-5 hours
- Larger territories harder to defend
- Compact territories easier to coordinate


## Player Placement

### New Player Spawning

#### Initial Placement Algorithm

**Goal:** Place new players in areas with:
- Other new players nearby (community building)
- Available barbarian villages to conquer
- Not too close to established powers
- Balanced resource distribution

**Placement Zones:**

1. **Newbie Zones (Recommended)**
   - Designated sectors for players under 7 days old
   - Protected from attacks by players over 7 days
   - Zones rotate as world expands
   - Example: Sectors K23, K24, K33, K34 in Ring 2

2. **Random Placement with Clustering**
   - Random location within available rings
   - Bias toward areas with 2-5 other new players within 20 tiles
   - Avoid areas with 10+ villages within 10 tiles (overcrowded)
   - Avoid areas within 50 tiles of top 100 players

3. **Tribal Invitation Spawning**
   - If invited by tribe, spawn within 50 tiles of tribe's territory center
   - Allows immediate integration into tribe
   - Must still meet minimum spacing requirements

**Spacing Requirements:**
- Minimum 3 tiles from any other village
- Preferred 5-7 tiles from nearest village
- Maximum 20 tiles from nearest player village (prevent isolation)

#### Spawn Location Selection Process

1. Identify eligible rings (currently open rings)
2. Filter out overcrowded sectors (>70% occupied)
3. Filter out high-conflict zones (>10 attacks/day per sector)
4. If tribal invite: prioritize tribe's region
5. Find clusters of 2-5 new players (joined within 48 hours)
6. Select random location meeting spacing requirements
7. Verify barbarian villages available within 15 tiles
8. Assign location and create starting village


### Beginner Protection

**Protection Period:** 7 days from account creation

**Protection Rules:**
- Cannot be attacked by players over 7 days old
- Can attack barbarians and other protected players
- Can be attacked by players within protection period
- Protection ends early if player attacks non-protected player
- Protection ends early if player reaches 1,000 points

**Visual Indicators:**
- Protected villages show shield icon
- Hover shows "Protected until [date]"
- Map filter to show/hide protected villages

### Relocation & Respawn Options

#### Beginner Relocation (First 72 Hours)
- Free relocation item in inventory
- Can move starting village to any valid spawn location
- One-time use within first 3 days
- Useful if spawned in bad location or want to join friends

#### Restart Option (First 7 Days)
- Delete account and restart with new spawn
- Loses all progress
- Can restart up to 3 times per email/IP
- Prevents abuse while allowing genuine restarts

#### Late-Game Relocation
- Rare item: "Relocation Scroll" (premium or event reward)
- Moves one village to new location
- Restrictions:
  - Cannot move within 100 tiles of top 50 players
  - Cannot move into protected zones
  - 30-day cooldown between uses
  - Village is vulnerable during 24-hour relocation period

#### Tribal Relocation Programs
- Tribes can invite members to relocate near core territory
- Tribe provides resources and protection during move
- Coordinated relocations strengthen tribal cohesion
- Game mechanics don't enforce, but UI can facilitate


### Population Rebalancing

**Challenge:** Over time, certain areas become overcrowded while others become ghost towns.

**Rebalancing Strategies:**

1. **Dynamic Barbarian Spawning**
   - Spawn more barbarians in underpopulated sectors
   - Incentivizes expansion into empty areas
   - Barbarians in empty sectors have bonus resources

2. **Ring Expansion Incentives**
   - New rings offer bonus resources for first settlers
   - "Pioneer" achievement for settling new rings
   - Temporary production bonuses in frontier sectors

3. **Abandoned Village Cleanup**
   - Villages inactive for 30+ days become barbarian
   - Prevents ghost towns from cluttering map
   - Reclaimed villages have reduced defenses

4. **Sector Bonuses**
   - Underpopulated sectors get temporary bonuses
   - "Frontier Bonus": +20% resource production
   - "Pioneer Spirit": +10% troop training speed
   - Bonuses removed when sector reaches 50% capacity

5. **World Merge Events (Extreme)**
   - If world population drops below threshold
   - Merge two worlds into one
   - Complex but prevents dead worlds

## Village Types on Map

### Player Villages

**Standard Player Village:**
- Owned by active player
- Color-coded by tribe affiliation
- Size indicator based on points/population
- Shows player name and village name on hover

**Capital Village:**
- Player's first/main village
- Special icon (crown or star)
- Usually most developed
- Losing capital is significant setback

**Conquered Villages:**
- Villages taken from other players or advanced barbarians
- May show "recently conquered" indicator for 24 hours
- Typically less developed than capitals
- Strategic expansion points


### Barbarian Villages

**Basic Barbarian Village:**
- Neutral NPC villages
- Spawn naturally across map
- Weak defenses (50-200 troops)
- Basic resources available
- Can be conquered by players
- Respawn in empty areas over time

**Fortified Barbarian Village:**
- Stronger defenses (500-1,000 troops)
- Better resource production
- Requires coordinated attack
- Found in frontier areas
- Higher rewards for conquest

**Barbarian Stronghold:**
- Very strong defenses (2,000-5,000 troops)
- Excellent resource production
- Rare special buildings
- Requires tribal coordination
- Major strategic objectives
- Only 1-2 per sector

**Abandoned Villages:**
- Former player villages now barbarian
- Retain some buildings/walls
- Defenses based on previous owner
- May have resources left behind
- Show "Abandoned" tag

### Special Objective Villages

**Tribal Capitals:**
- Designated by tribe leader
- Special building: Tribal Hall
- Tribe-wide bonuses if held
- Major target for enemies
- Extra defensive bonuses
- Shows tribe banner/flag

**Wonder Villages:**
- Late-game objectives
- Can build World Wonder (massive project)
- Requires tribe to defend during construction
- First tribe to complete Wonder wins world
- Extremely high-value targets
- Limited locations (1 per continent)


**Resource Villages:**
- Special villages with bonus resource production
- Types:
  - Iron Mine Village: +50% iron production
  - Lumber Camp Village: +50% wood production
  - Quarry Village: +50% stone production
  - Farmland Village: +50% food production
- Highly contested
- 5-10 per sector
- Show resource icon on map

**Artifact Villages:**
- Contain powerful artifacts
- Must conquer and hold to use artifact
- Artifacts provide tribe-wide bonuses
- Examples:
  - Great Forge: -20% unit training time
  - Ancient Library: -15% research time
  - War Horn: +10% troop attack
  - Stone Tablets: +15% resource production
- 1-3 per continent
- Rotate locations every 2 weeks

**Trading Posts:**
- NPC villages that facilitate trade
- Cannot be conquered
- Offer market with better rates
- Safe zones (no attacks within 5 tiles)
- 1 per 4 sectors
- Strategic meeting points

**Mercenary Camps:**
- NPC villages offering mercenary troops
- Pay resources to hire temporary troops
- Cannot be conquered
- Troops available for 24 hours
- Expensive but powerful
- 1 per 9 sectors

### Event Structures

**Seasonal Event Villages:**
- Appear during special events
- Themed (Halloween, Winter, Summer)
- Special rewards for conquest
- Temporary (1-2 weeks)
- Unique mechanics or challenges

**Raid Camps:**
- Temporary barbarian camps
- Spawn randomly for 48 hours
- Contain loot and resources
- Weak defenses but time-limited
- First to raid gets best rewards


**Monster Lairs:**
- PvE challenge locations
- Powerful NPC defenders
- Require multiple coordinated attacks
- Drop rare items and resources
- Respawn weekly
- Difficulty scales with world age

**Ruins:**
- Ancient structures to explore
- Send scouts to discover secrets
- May contain treasures or traps
- One-time rewards
- Spawn in remote areas
- Encourage exploration

## Map UI & Controls

### Desktop Interface

#### Main Map View

**Canvas Rendering:**
- HTML5 Canvas for performance
- Renders 50,000+ villages smoothly
- Tile-based rendering with viewport culling
- Only render visible tiles + buffer zone

**Zoom Levels:**

| Level | Tiles Visible | Use Case | Details Shown |
|-------|---------------|----------|---------------|
| 1 (Max Out) | 500 × 500 | World overview | Sectors only, major tribes |
| 2 | 250 × 250 | Continental view | Tribe territories, major villages |
| 3 | 100 × 100 | Regional view | All villages as dots, colors |
| 4 | 50 × 50 | Tactical view | Village icons, names on hover |
| 5 (Max In) | 20 × 20 | Detail view | Full village info, troop movements |

**Zoom Controls:**
- Mouse wheel: zoom in/out
- +/- buttons: zoom in/out
- Double-click: zoom in centered on click
- Minimap: click to jump to location

**Pan Controls:**
- Click and drag to pan
- Arrow keys: pan in direction
- WASD keys: alternative pan controls
- Minimap: click to center view


#### Village Icons & Colors

**Icon Sizes (based on village points):**
- Tiny: 0-100 points (4×4 pixels)
- Small: 100-500 points (6×6 pixels)
- Medium: 500-2,000 points (8×8 pixels)
- Large: 2,000-10,000 points (10×10 pixels)
- Huge: 10,000+ points (12×12 pixels)

**Color Coding:**
- Own villages: Blue
- Tribe members: Green
- Allied tribes: Light Green
- Neutral players: Gray
- Enemy tribes: Red
- NAP (Non-Aggression Pact) tribes: Yellow
- Barbarians: Brown
- Special objectives: Purple
- Protected players: White with shield

**Special Icons:**
- Capital: Crown overlay
- Tribal capital: Banner overlay
- Under attack: Crossed swords (pulsing)
- Incoming support: Shield icon
- Recently conquered: Flag icon
- Artifact village: Gem icon
- Resource village: Resource icon
- Protected: Shield icon

#### Hover Information Panel

**Quick Info (on hover):**
```
Village Name
Player Name (Tribe Tag)
Coordinates: 123|456
Points: 1,234
Distance: 45 tiles (4.5 hours cavalry)
```

**Extended Info (click for popup):**
- Full village details
- Recent battle reports (if scouted)
- Troop estimates (if scouted)
- Building levels (if scouted)
- Player profile link
- Tribe profile link
- Attack/Support buttons


#### Quick Actions

**Right-Click Context Menu:**
- Send Attack
- Send Support
- Send Scout
- Send Resources
- Open Village Details
- View Player Profile
- View Tribe Profile
- Add to Favorites
- Set as Rally Point
- Measure Distance
- Share Location (copy coordinates)

**Keyboard Shortcuts:**
- A: Send Attack to selected village
- S: Send Support to selected village
- D: Send Scout to selected village
- R: Send Resources to selected village
- V: Open village details
- F: Add to favorites
- C: Center map on selected village
- H: Return to home village
- Space: Toggle troop movement overlay

#### Filters & Toggles

**Village Type Filters (sticky pill toggles, saved per player):**
- Own villages: always on, bright outline and glow; double-click isolates only own villages
- Tribe villages: on by default, tribe-color fill; hides sitter villages only if player opts out
- Allied villages: on by default, tinted with ally color; hover shows alliance name
- Enemy villages: on by default, red markers; quick-toggle button "Only enemies" filters out all others
- Neutral villages: on by default, muted gray markers to reduce clutter
- Barbarian villages: on by default on desktop, off by default on mobile to declutter
- Protected villages: off by default; shield icon indicates beginner protection or truce state
- Special objectives: on by default; shows wonders, event nodes, and map objectives with unique icons

**Information Overlays (independent toggles, stackable):**
- Village names: label overlay that auto-hides when zoomed far out; tap to force-show for 5 seconds
- Player names: shows `Player (Tribe Tag)` under the village label; off by default on mobile
- Coordinates: small `123|456` badge; always visible in "War Room" preset
- Points: shows current village points with tiny trend arrow if scouted recently
- Troop movements: animated arrows with ETA badges; throttled when >500 movements on screen
- Sector boundaries: gridlines every 20×20 tiles; highlight current sector with bold border
- Tribe territories: shaded polygons with opacity slider; overlapping alliances use striped fill
- Distance circles: configurable radii (5/10/20/50 tiles) from selected village; color-coded per radius

**Point Range Filter:**
- Slider: 0 to 50,000 points
- Show only villages within range
- Useful for finding targets of similar strength


#### Minimap

**Location:** Bottom-right corner
**Size:** 150×150 pixels
**Features:**
- Shows entire world at once
- Current viewport highlighted
- Click to jump to location
- Drag viewport rectangle to pan
- Color-coded by tribe density
- Toggle on/off

#### Search & Navigation

**Coordinate Search:**
- Input: "123|456" or "123,456"
- Instantly centers map on coordinates
- Highlights target village
- Shows distance from current village

**Player Search:**
- Autocomplete player names
- Shows all player's villages
- Click to center on village
- Shows player statistics

**Tribe Search:**
- Autocomplete tribe names
- Shows tribe territory overview
- Lists all tribe villages
- Shows tribe statistics

**Favorites System:**
- Save up to 50 favorite locations
- Organize into folders (Targets, Allies, Resources, etc.)
- Quick jump buttons
- Notes for each favorite

### Mobile Interface

**Challenges:**
- Smaller screen (320-428px width)
- Touch controls instead of mouse
- Limited hover states
- Performance constraints


**Mobile Adaptations:**

**Touch Controls:**
- Pinch to zoom in/out
- Drag with one finger to pan
- Tap village for quick info
- Long-press for context menu
- Double-tap to zoom in on location

**Simplified UI:**
- Fewer zoom levels (3 instead of 5)
- Larger touch targets (minimum 44×44px)
- Bottom sheet for village details
- Floating action button for quick actions
- Collapsible filter menu

**Performance Optimizations:**
- Render fewer villages at once
- Simplified icons (no gradients)
- Reduced animation
- Lazy load village details
- Cache rendered tiles

**Mobile-Specific Features:**
- Swipe gestures for common actions
- Quick action toolbar at bottom
- Notification badges on map for incoming attacks
- GPS-style navigation to targets
- Voice commands for search (optional)

**Responsive Breakpoints:**
- Mobile: < 768px (simplified interface)
- Tablet: 768-1024px (hybrid interface)
- Desktop: > 1024px (full interface)

## Information Layers

### Diplomacy Layer

**Purpose:** Visualize political relationships across the map.

**Display:**
- Color-code villages by diplomatic status
- Own tribe: Blue
- Allied tribes: Green
- NAP tribes: Yellow
- Enemy tribes: Red
- Neutral: Gray

**Features:**
- Toggle individual tribes on/off
- Highlight specific tribe's territory
- Show diplomatic borders
- Display recent diplomatic changes
- War declaration indicators


### Activity Layer

**Purpose:** Show where active players and conflicts are occurring.

**Heatmap Display:**
- Red zones: High activity (10+ attacks/day)
- Orange zones: Medium activity (5-10 attacks/day)
- Yellow zones: Low activity (1-5 attacks/day)
- Green zones: Peaceful (0-1 attacks/day)

**Activity Indicators:**
- Recent attacks (last 24 hours)
- Active players (logged in within 1 hour)
- Troop movements in progress
- Recent conquests
- Battle intensity

**Use Cases:**
- Find active war zones
- Identify safe expansion areas
- Locate active players to recruit
- Avoid high-conflict areas as new player

### Tribe Territory Layer

**Purpose:** Visualize tribal control and influence.

**Territory Calculation:**
- Convex hull around tribe's villages
- Influence radius around each village (10-20 tiles)
- Blended influence for overlapping territories
- Color intensity based on village density

**Display:**
- Semi-transparent colored overlay
- Tribe name/tag in territory center
- Border lines between territories
- Contested areas (multiple tribes)
- Territory size ranking

**Features:**
- Toggle specific tribes
- Show only top 10 tribes
- Historical territory view (time-lapse)
- Territory growth/shrinkage indicators


### Danger Zone Layer

**Purpose:** Help players assess risk levels across the map.

**Risk Factors:**
- Proximity to enemy tribes
- Recent attack frequency
- Presence of large players
- Distance from friendly support
- Defensive strength of area

**Risk Levels:**
- Safe (Green): Protected zones, friendly territory
- Low Risk (Yellow): Neutral areas, some activity
- Medium Risk (Orange): Border areas, occasional attacks
- High Risk (Red): Active war zones, frequent attacks
- Extreme Risk (Dark Red): Frontlines, constant combat

**Personal Danger Assessment:**
- Calculates risk specific to player's tribe
- Shows safe expansion corridors
- Highlights vulnerable villages
- Suggests defensive priorities

### Resource Richness Layer

**Purpose:** Show resource distribution and valuable targets.

**Display:**
- Color gradient based on resource production
- Special icons for resource villages
- Barbarian village resource levels
- Bonus resource areas

**Resource Types:**
- All resources combined (overall richness)
- Individual resource filters (wood, stone, iron, food)
- Special resources (artifacts, bonuses)

**Strategic Use:**
- Identify high-value conquest targets
- Plan expansion into resource-rich areas
- Locate resource villages to capture
- Find barbarian villages worth farming


### Event Hotspot Layer

**Purpose:** Highlight active events and special locations.

**Event Types:**
- Seasonal events (Halloween, Winter, etc.)
- Raid camps (temporary loot opportunities)
- Monster lairs (PvE challenges)
- Artifact spawns (powerful items)
- World wonders (end-game objectives)
- Tribal wars (major conflicts)

**Display:**
- Pulsing icons for active events
- Countdown timers for temporary events
- Event difficulty indicators
- Reward previews
- Participation statistics

**Notifications:**
- Alert when new event spawns nearby
- Remind when event ending soon
- Notify when tribe members engage event
- Show event completion status

### Troop Movement Layer

**Purpose:** Visualize all troop movements in real-time.

**Display:**
- Animated arrows showing troop paths
- Color-coded by movement type:
  - Red: Attacks
  - Blue: Support
  - Green: Returning troops
  - Yellow: Scouts
  - Purple: Resource transfers
- Arrow thickness based on army size
- ETA displayed on hover

**Filters:**
- Own movements only
- Tribe movements
- Incoming attacks
- Outgoing attacks
- Support movements
- All movements

**Strategic Value:**
- Coordinate tribal attacks
- Spot incoming threats
- Monitor support coverage
- Identify enemy patterns


## World Expansion & Contraction

### Ring Expansion System

**Expansion Schedule:**

| Week | Ring | Coordinates | New Tiles | Total Tiles | Trigger |
|------|------|-------------|-----------|-------------|---------|
| 0 | Core | -100 to +100 | 40,000 | 40,000 | World launch |
| 3 | Inner | -200 to +200 | 120,000 | 160,000 | 60% core occupied |
| 6 | Middle | -300 to +300 | 200,000 | 360,000 | 60% inner occupied |
| 10 | Outer | -400 to +400 | 280,000 | 640,000 | 60% middle occupied |
| 15 | Frontier | -500 to +500 | 360,000 | 1,000,000 | 60% outer occupied |

**Expansion Triggers:**
- Time-based: Minimum weeks before expansion
- Occupation-based: Percentage of ring occupied
- Population-based: Active player count threshold
- Manual: Admin can trigger early if needed

**Expansion Announcement:**
- 7 days advance notice
- In-game notifications
- Email alerts to active players
- Map preview of new areas
- Bonus incentives for early settlers

**New Ring Features:**
- Higher barbarian density
- Bonus resource villages
- Special event structures
- Pioneer achievements
- Temporary production bonuses (+20% for first 2 weeks)

### World Contraction (Inactive Worlds)

**Problem:** Worlds with declining populations become too large and empty.

**Contraction Strategies:**

**1. Soft Contraction (Incentivized Migration)**
- Offer relocation items to outer ring players
- Bonus resources for moving to inner rings
- Tribal relocation programs
- Gradually reduce barbarian spawns in outer rings
- No forced moves, but strong incentives


**2. Hard Contraction (Ring Closure)**
- Announce ring closure 30 days in advance
- Outer ring becomes uninhabitable
- Players must relocate or lose villages
- Free relocation items provided
- Compensation for lost villages
- Only used in extreme population decline

**3. Sector Consolidation**
- Merge low-population sectors
- Relocate all villages to consolidated sector
- Maintain relative positions
- Preserve diplomatic relationships
- Less disruptive than ring closure

### World Merging

**When to Merge:**
- World population below 500 active players
- Multiple worlds with <1,000 players each
- End of season in seasonal worlds
- Community vote supports merge

**Merge Process:**

**Phase 1: Announcement (30 days before)**
- Notify all players of upcoming merge
- Explain merge mechanics
- Allow players to prepare
- Offer opt-out (account transfer to different world)

**Phase 2: Preparation (14 days before)**
- Freeze world expansion
- Resolve ongoing wars
- Distribute merge bonuses
- Technical preparation

**Phase 3: Merge Day**
- Worlds go offline for 4-8 hours
- Villages relocated to merged world
- Coordinate systems adjusted
- Diplomatic relationships reset or preserved (configurable)
- Tribal memberships maintained

**Phase 4: Post-Merge (7 days after)**
- Grace period: no attacks between former worlds
- Diplomatic negotiation period
- Bonus resources to help adjustment
- Monitor for issues and rebalance


**Merge Strategies:**

**Strategy A: Side-by-Side**
- World 1 occupies West half (-500 to 0, -500 to +500)
- World 2 occupies East half (0 to +500, -500 to +500)
- Natural border at X=0
- Gradual integration over time

**Strategy B: Interleaved**
- Alternate sectors from each world
- More immediate integration
- Higher initial conflict
- Faster community blending

**Strategy C: Concentric**
- Larger world in center
- Smaller world in outer rings
- Preserves core territories
- Clear hierarchy

**Recommendation:** Strategy A (Side-by-Side) for smoothest transition.

### World Splitting (Overpopulation)

**When to Split:**
- World exceeds 10,000 active players
- Server performance issues
- Community requests separate competitive environments

**Split Process:**

**Option 1: Voluntary Migration**
- Open new world
- Offer free transfers
- Incentivize migration with bonuses
- No forced moves

**Option 2: Geographic Split**
- Divide world by coordinates
- North/South or East/West split
- Maintain tribal integrity where possible
- Compensate split tribes

**Option 3: Competitive Split**
- Top 50% by points to "Elite World"
- Bottom 50% to "Rising World"
- Allows more balanced competition
- Controversial but effective


### Seasonal World Resets

**For Seasonal/Round-Based Worlds:**

**End of Season:**
- Declare winners (most points, territory, wonders)
- Award seasonal rewards
- Archive world state
- Allow players to view historical map

**New Season Start:**
- Fresh map (possibly different layout)
- All players start equal
- Carry over cosmetic rewards only
- New seasonal objectives
- Lessons learned from previous season

**Season Length:** 6-12 months depending on game pace

## Examples & Scenarios

### Example 1: New Player Exploration

**Scenario:** Sarah just joined the game and wants to understand her surroundings.

**Actions:**

1. **Initial Spawn**
   - Sarah spawns at coordinates (234|156) in Sector K45
   - Map opens centered on her village
   - Tutorial highlights zoom controls and minimap
   - Protected player shield icon visible on her village

2. **Local Scan (Zoom Level 4)**
   - Zooms in to see 50×50 tile area
   - Identifies 3 barbarian villages within 10 tiles
   - Sees 2 other protected players nearby (white shields)
   - Notes 1 established player 15 tiles away (gray, 500 points)

3. **Hover Information**
   - Hovers over nearest barbarian at (238|159)
   - Sees: "Barbarian Village, 4 tiles away (24 min infantry)"
   - Right-clicks and selects "Send Scout"

4. **Regional View (Zoom Level 3)**
   - Zooms out to see 100×100 tile area
   - Activates "Tribe Territory" layer
   - Sees she's on edge of "Wolves" tribe territory (green)
   - Notes "Bears" tribe territory 50 tiles North (red - enemy of Wolves)


5. **Search for Tribe**
   - Uses search box to find "Wolves" tribe
   - Views tribe profile
   - Sees they're recruiting
   - Applies to join

6. **Planning Expansion**
   - Activates "Resource Richness" layer
   - Identifies high-iron area 8 tiles East
   - Marks location as favorite: "Future Expansion"
   - Plans to conquer barbarian there once strong enough

**Outcome:** Sarah understands her local area, identified targets, and found a tribe to join.

---

### Example 2: Coordinated Tribal Attack

**Scenario:** The "Wolves" tribe is planning a coordinated attack on "Bears" tribe's capital.

**Actions:**

1. **Target Identification**
   - Tribe leader Marcus opens map
   - Searches for "BearKing" (enemy leader)
   - Identifies capital at (312|278)
   - Clicks village, sees 5,000 points, likely well-defended

2. **Intelligence Gathering**
   - Reviews recent scout reports (from tribe members)
   - Estimates 2,000 defensive troops
   - Notes wall level 15
   - Identifies 3 support villages within 20 tiles

3. **Attack Planning**
   - Opens "Troop Movement" layer
   - Measures distances from tribe members' villages
   - Identifies 5 members within 50 tiles (5-hour cavalry range)
   - Uses "Distance Circles" tool centered on target

4. **Coordination**
   - Creates attack plan in tribe forum
   - Assigns roles:
     - 3 members send fake attacks (arrive 30 min before)
     - 2 members send real attacks (arrive simultaneously)
     - 2 members send support to attackers (in case of counter)
   - Sets attack time: Tomorrow 3:00 AM (when BearKing likely asleep)


5. **Execution**
   - Members send attacks at calculated times
   - Marcus monitors "Troop Movement" layer
   - Sees all attacks en route
   - Notices enemy support incoming from (325|290)
   - Quickly sends additional attack to intercept support

6. **Real-Time Monitoring**
   - Watches attacks arrive on map
   - First 3 fakes hit, trigger enemy support
   - Real attacks arrive 30 min later
   - Target village icon changes to "Under Attack" (pulsing swords)
   - Waits for battle reports

7. **Post-Attack Analysis**
   - Attack successful! Village conquered
   - Reviews battle reports
   - Shares victory on tribe chat
   - Sends support to newly conquered village
   - Marks nearby enemy villages as "Next Targets"

**Outcome:** Successful coordinated attack using map tools for planning, coordination, and execution.

---

### Example 3: Defensive Monitoring

**Scenario:** Elena notices suspicious enemy activity near her villages.

**Actions:**

1. **Threat Detection**
   - Elena logs in, sees notification: "Enemy scout detected"
   - Opens map, centers on her village at (445|223)
   - Activates "Activity Layer"
   - Sees orange zone (medium activity) around her area

2. **Enemy Analysis**
   - Activates "Diplomacy Layer"
   - Sees red villages (enemy tribe "Vipers") 30 tiles away
   - Searches for "Vipers" tribe
   - Reviews their recent conquests (3 villages in past week)
   - Identifies likely next targets (including her village)


3. **Support Network**
   - Activates "Tribe Territory" layer
   - Identifies 4 tribe members within 20 tiles
   - Right-clicks each, selects "Request Support"
   - Sends tribe message: "Under threat, need defensive support"
   - Watches "Troop Movement" layer as support arrives

4. **Defensive Preparation**
   - Reviews own defenses: 500 troops, wall level 10
   - Incoming support: 800 troops from tribe members
   - Total defense: 1,300 troops
   - Feels more confident but stays alert

5. **Attack Arrives**
   - 6 hours later, sees incoming attack on map
   - Red arrow pointing to her village
   - Hover shows: "1,200 troops, arrives in 2 hours"
   - Sends urgent tribe message
   - 2 more members send last-minute support

6. **Battle Resolution**
   - Attack arrives, battle occurs
   - With support, defense successful
   - Attacker loses 800 troops
   - Elena loses 300 troops
   - Tribe support saved her village

7. **Counter-Attack Planning**
   - Reviews attacker's village (now weakened)
   - Coordinates with tribe for counter-attack
   - Uses map to plan revenge strike

**Outcome:** Map tools enabled early threat detection, support coordination, and successful defense.

---

### Example 4: Resource Farming Route

**Scenario:** David wants to efficiently farm barbarian villages for resources.

**Actions:**

1. **Farming Area Selection**
   - Opens map, centers on his village (156|89)
   - Activates "Resource Richness" layer
   - Identifies cluster of 8 barbarian villages within 15 tiles
   - All show medium-high resource production


2. **Route Planning**
   - Adds all 8 barbarians to favorites folder "Farm Route"
   - Orders by distance (closest first)
   - Notes travel times:
     - Barbarian 1: 5 tiles (30 min)
     - Barbarian 2: 7 tiles (42 min)
     - Barbarian 3: 9 tiles (54 min)
     - ... and so on

3. **Farming Execution**
   - Sends 50 cavalry to each barbarian (light raids)
   - Uses quick action: Right-click → "Send Attack" → Preset "Farm"
   - Sends all 8 attacks in 2 minutes using favorites list
   - Watches "Troop Movement" layer to track raids

4. **Return Monitoring**
   - Troops return with resources over next 2 hours
   - Total haul: 5,000 wood, 4,000 stone, 3,000 iron, 2,000 food
   - Notes which barbarians had best resources
   - Marks top 3 as "Priority Farms"

5. **Optimization**
   - Repeats every 4 hours (when barbarian resources regenerate)
   - Creates farming schedule
   - Shares farming area with tribe (coordinates farming zones)
   - Avoids farming barbarians near tribe members

**Outcome:** Efficient resource farming using map tools and favorites system.

---

### Example 5: Artifact Hunt

**Scenario:** A new artifact has spawned, and multiple tribes are racing to claim it.

**Actions:**

1. **Artifact Announcement**
   - Server-wide notification: "Ancient Forge artifact spawned at (234|567)"
   - All players see pulsing purple icon on map
   - Artifact info: "+20% unit training speed for controlling tribe"


2. **Distance Assessment**
   - Multiple tribes check distance to artifact
   - "Wolves" tribe: Closest member 80 tiles away (8 hours cavalry)
   - "Bears" tribe: Closest member 60 tiles away (6 hours cavalry)
   - "Vipers" tribe: Closest member 100 tiles away (10 hours cavalry)

3. **Race Begins**
   - All tribes send attacks simultaneously
   - Map shows multiple colored arrows converging on artifact village
   - "Troop Movement" layer becomes very active
   - Players watch in real-time

4. **Strategic Complications**
   - "Bears" tribe's attack will arrive first (6 hours)
   - "Wolves" tribe sends faster cavalry, arrives 7 hours
   - "Vipers" tribe sends fake attacks to confuse
   - Some tribes send attacks to intercept other tribes' attacks

5. **Map Monitoring**
   - All tribes watch map obsessively
   - Countdown timers on each attack
   - Diplomatic negotiations in real-time
   - Some tribes form temporary alliances

6. **Resolution**
   - "Bears" attack arrives first, conquers artifact village
   - "Wolves" attack arrives 1 hour later, attacks "Bears"
   - Epic battle, "Wolves" win and take artifact
   - "Vipers" attack arrives too late
   - Map shows new owner (green for "Wolves" tribe)

7. **Aftermath**
   - "Wolves" tribe sends massive support to defend artifact
   - Map shows dozens of support movements
   - Other tribes plan counter-attacks
   - Artifact village becomes most contested location on map

**Outcome:** Map tools enabled real-time strategic competition for high-value objective.


---

### Example 6: World Expansion Event

**Scenario:** The world is expanding from Ring 2 to Ring 3, opening new territory.

**Actions:**

1. **Expansion Announcement**
   - 7 days before: "Ring 3 will open on [date]"
   - Map preview shows new area (-300 to +300)
   - Announcement of pioneer bonuses

2. **Tribal Planning**
   - Tribes analyze new territory
   - Identify strategic locations
   - Plan coordinated expansion
   - Assign members to specific sectors

3. **Expansion Day**
   - Ring 3 opens at midnight
   - New barbarian villages spawn
   - Special resource villages appear
   - Map updates with new territory

4. **Land Rush**
   - Players send settlers to claim barbarians
   - Map shows hundreds of attacks heading to new ring
   - "Activity Layer" shows new ring glowing red
   - First conquests within 2 hours

5. **Territory Establishment**
   - Tribes establish footholds in new sectors
   - Diplomatic borders shift
   - New conflicts emerge over prime locations
   - "Tribe Territory" layer updates in real-time

6. **Pioneer Rewards**
   - First 100 players to conquer in Ring 3 get achievement
   - Bonus resources for early settlers
   - Special buildings available only in new ring
   - Map shows pioneer villages with special icon

**Outcome:** World expansion creates new strategic opportunities and conflicts, all visible on map.


## Technical Considerations

### Performance Optimization

**Challenge:** Rendering 100,000+ villages smoothly.

**Solutions:**

1. **Viewport Culling**
   - Only render villages within visible area + buffer
   - Typical viewport: 2,000-5,000 villages
   - Reduces rendering by 95%+

2. **Level of Detail (LOD)**
   - Zoom level 1-2: Render sectors only
   - Zoom level 3: Render villages as colored dots
   - Zoom level 4-5: Render detailed icons
   - Reduces complexity at distance

3. **Canvas Layering**
   - Static layer: Terrain, grid, sectors (rarely changes)
   - Village layer: Village icons (updates on changes)
   - Movement layer: Troop movements (updates frequently)
   - UI layer: Tooltips, selections (updates constantly)
   - Only redraw changed layers

4. **Data Pagination**
   - Load village data in chunks
   - Cache loaded data
   - Lazy load details on demand
   - Preload adjacent areas

5. **WebGL Rendering (Advanced)**
   - Use WebGL for massive performance boost
   - Can render 100,000+ villages at 60 FPS
   - More complex implementation
   - Fallback to Canvas for older browsers

### Database Optimization

**Spatial Queries:**
- Index on (x, y) coordinates
- Use spatial database extensions (PostGIS)
- Optimize range queries: "Find all villages within 50 tiles of (x, y)"
- Cache frequently accessed areas

**Query Examples:**
```sql
-- Find villages within radius
SELECT * FROM villages 
WHERE SQRT(POW(x - ?, 2) + POW(y - ?, 2)) <= ?

-- Find villages in rectangle (faster)
SELECT * FROM villages 
WHERE x BETWEEN ? AND ? 
AND y BETWEEN ? AND ?
```


### Real-Time Updates

**WebSocket Connection:**
- Maintain persistent connection for real-time updates
- Push updates for:
  - New attacks/support movements
  - Village conquests
  - Diplomatic changes
  - Troop arrivals/departures
- Update map without page refresh

**Update Frequency:**
- Critical updates: Immediate (attacks, conquests)
- High priority: Every 10 seconds (troop movements)
- Medium priority: Every 60 seconds (village changes)
- Low priority: Every 5 minutes (statistics, rankings)

### Mobile Performance

**Challenges:**
- Limited CPU/GPU
- Smaller screen
- Touch latency
- Battery consumption

**Optimizations:**
- Render fewer villages (reduce viewport)
- Simplify icons (no gradients, shadows)
- Reduce animation
- Lower update frequency
- Aggressive caching
- Lazy loading

### Accessibility

**Considerations:**
- Color-blind friendly color schemes
- High contrast mode
- Keyboard navigation
- Screen reader support for village info
- Text alternatives for icons
- Zoom without loss of functionality

## Summary

The world map is the heart of the game, where strategy, diplomacy, and warfare converge. A well-designed map system provides:

- **Intuitive Navigation:** Easy to understand coordinates, zoom, and pan
- **Rich Information:** Multiple layers showing different strategic aspects
- **Performance:** Smooth rendering even with massive scale
- **Flexibility:** Supports various world configurations and growth patterns
- **Engagement:** Real-time updates and interactive tools keep players engaged

The map should feel alive, constantly updating with troop movements, battles, and territorial changes. It should empower players to make informed strategic decisions while remaining accessible to newcomers. Whether planning a solo raid or coordinating a massive tribal war, the map is the essential tool that brings the medieval world to life.
