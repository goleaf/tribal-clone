# Scouting, Fog of War & Intelligence Systems

## Overview

Information is power in tribal warfare. This document defines the complete intelligence gathering, fog of war, and counter-intelligence systems that govern what players know about enemies, allies, and the world around them.

## Fog of War Concept

### Default Visibility

**What Players Can Always See:**
- Own villages: complete information (all buildings, resources, units, research)
- Own tribe villages: configurable by tribe permissions (default: location + basic info)
- Map terrain: basic geography, forests, mountains, water features
- Village locations: all villages appear as dots/markers on the map
- Village names: visible for all villages
- Player names: visible for village owners
- Tribe tags: visible for villages belonging to tribes
- Public rankings: top players/tribes in leaderboards
- Alliance/NAP declarations: publicly announced diplomatic relations

**What is Hidden by Default:**
- Troop counts (garrison and traveling)
- Building levels and types
- Resource stockpiles
- Wall/defense strength
- Research progress and completed techs
- Production rates
- Recent battle history
- Online/offline status
- Incoming attacks (until they arrive or are scouted)
- Outgoing attacks from enemy villages
- Village loyalty/morale levels
- Support troops stationed in villages
- Resource transfers in transit


### Proximity-Based Visibility

**Close Range Intel (Adjacent Tiles):**
- Village population estimate (small/medium/large)
- Recent troop movements (units seen passing through)
- Active construction (smoke, activity indicators)
- Major battles (visible explosions, fires)

**Regional Awareness:**
- Large army movements through your territory
- Tribe members' villages provide extended vision
- Watchtowers extend vision range
- Allied villages may share vision (configurable)

### Fog of War Mechanics

**Information Freshness:**
- Intel has timestamps showing when it was gathered
- Stale intel is marked with warning indicators
- Very old intel (7+ days) appears faded/grayed out
- Players must actively refresh intel through scouting

**Uncertainty Indicators:**
- "Last updated: 3 hours ago"
- "Estimated troop count: 500-800 (low confidence)"
- "Building levels unknown - last scouted 5 days ago"
- "Player may be offline - last seen 12 hours ago"

---

## Scout & Spy Units

### Unit Types

#### 1. Light Scout
**Role:** Fast reconnaissance, basic intel gathering
- **Speed:** Very fast (fastest unit in game)
- **Cost:** Low (cheap to produce in bulk)
- **Carrying Capacity:** 0 (cannot loot)
- **Combat Strength:** Very weak
- **Detection Risk:** Medium
- **Intel Quality:** Basic (troop counts, building presence)
- **Survival Rate:** Low if caught
- **Best Use:** Quick checks, monitoring enemy movements, screening for traps


#### 2. Deep Spy
**Role:** Detailed intelligence, infiltration
- **Speed:** Medium
- **Cost:** High (requires advanced training)
- **Carrying Capacity:** 0
- **Combat Strength:** Weak
- **Detection Risk:** Low (trained in stealth)
- **Intel Quality:** Detailed (building levels, resource counts, tech hints)
- **Survival Rate:** Medium (can escape if detected)
- **Best Use:** Pre-attack planning, identifying weak targets, tech espionage

#### 3. Scout Cavalry
**Role:** Mobile reconnaissance, pursuit
- **Speed:** Fast
- **Cost:** Medium
- **Carrying Capacity:** 5
- **Combat Strength:** Light
- **Detection Risk:** Medium-High
- **Intel Quality:** Basic-Medium
- **Survival Rate:** Medium (can fight or flee)
- **Best Use:** Chasing enemy scouts, rapid intel sweeps, harassment

#### 4. Counter-Scout (Ranger)
**Role:** Defensive scouting, scout hunting
- **Speed:** Medium-Fast
- **Cost:** Medium
- **Carrying Capacity:** 0
- **Combat Strength:** Strong vs scouts
- **Detection Risk:** Low (operates in home territory)
- **Intel Quality:** N/A (defensive unit)
- **Survival Rate:** High (home advantage)
- **Best Use:** Patrolling borders, intercepting enemy scouts, protecting intel


#### 5. Infiltrator
**Role:** Long-term embedded intelligence
- **Speed:** Slow
- **Cost:** Very high (elite training)
- **Carrying Capacity:** 0
- **Combat Strength:** Minimal
- **Detection Risk:** Very low (deep cover)
- **Intel Quality:** Excellent (real-time updates for duration)
- **Survival Rate:** Low if discovered (executed)
- **Best Use:** Monitoring key enemy villages, tracking troop movements, sabotage prep
- **Special:** Can remain embedded for days, providing periodic reports

#### 6. Scout Ship
**Role:** Naval reconnaissance
- **Speed:** Medium (water only)
- **Cost:** Medium-High
- **Carrying Capacity:** 10
- **Combat Strength:** Weak
- **Detection Risk:** High (visible on water)
- **Intel Quality:** Basic (coastal villages, naval forces)
- **Survival Rate:** Low (vulnerable to naval combat)
- **Best Use:** Island scouting, naval fleet tracking, coastal raids

#### 7. Merchant Scout
**Role:** Covert intelligence through trade
- **Speed:** Slow
- **Cost:** Medium
- **Carrying Capacity:** 50 (appears as trader)
- **Detection Risk:** Very low (disguised as merchant)
- **Intel Quality:** Medium (economic data, market prices, resource levels)
- **Survival Rate:** Very high (rarely attacked)
- **Best Use:** Economic espionage, resource tracking, covert operations
- **Special:** Can actually trade to maintain cover


### Scout Unit Comparison Table

| Unit Type | Speed | Cost | Detection Risk | Intel Quality | Best For |
|-----------|-------|------|----------------|---------------|----------|
| Light Scout | Very Fast | Low | Medium | Basic | Quick checks, screening |
| Deep Spy | Medium | High | Low | Detailed | Pre-attack planning |
| Scout Cavalry | Fast | Medium | Medium-High | Basic-Medium | Mobile ops, pursuit |
| Counter-Scout | Medium-Fast | Medium | Low | N/A | Defense, interception |
| Infiltrator | Slow | Very High | Very Low | Excellent | Long-term monitoring |
| Scout Ship | Medium | Medium-High | High | Basic | Naval reconnaissance |
| Merchant Scout | Slow | Medium | Very Low | Medium | Economic espionage |

---

## Intel Types

### Military Intelligence

**Troop Counts:**
- Garrison troops (by unit type)
- Support troops from allies
- Traveling reinforcements (incoming)
- Outgoing attacks (if intercepted)
- Unit production queue (deep intel only)
- Recent losses (from battle reports)
- Estimated training capacity

**Defensive Strength:**
- Wall level and condition
- Gate status (open/closed/damaged)
- Defensive buildings (towers, traps, barricades)
- Garrison commander (if applicable)
- Defensive bonuses from research
- Morale/loyalty level


**Unit Movement Intelligence:**
- Outgoing attacks (destination, estimated size)
- Returning troops (arrival time)
- Support sent to allies
- Troop transfers between own villages
- Rally point activity
- Recent scout missions sent

### Economic Intelligence

**Resource Stockpiles:**
- Current wood, clay, iron, food levels
- Storage capacity
- Production rates (estimated from buildings)
- Recent resource transfers
- Market activity (trades, purchases)
- Resource shortage indicators

**Building Information:**
- Building levels (HQ, barracks, walls, etc.)
- Construction queue
- Recently completed buildings
- Damaged/destroyed buildings
- Special buildings (academy, workshop, temple)
- Building upgrade timers (if ongoing)

**Economic Capacity:**
- Farm population limit
- Warehouse/granary capacity
- Market availability
- Production efficiency
- Resource generation per hour


### Technological Intelligence

**Research Progress:**
- Completed technologies
- Current research (if deep spy succeeds)
- Academy level
- Tech tree advancement stage
- Special unit unlocks
- Unique tribal technologies

**Strategic Capabilities:**
- Noble/chief availability (conquest capability)
- Catapult/siege weapon access
- Advanced unit types unlocked
- Special abilities researched
- Defensive tech bonuses

### Player Activity Intelligence

**Online Status Hints:**
- "Recently active" (within 1 hour)
- "Possibly offline" (1-6 hours)
- "Likely offline" (6-24 hours)
- "Inactive" (24+ hours)
- Last command timestamp (if infiltrator present)
- Response time to attacks (historical)

**Behavioral Patterns:**
- Typical online hours
- Response speed to threats
- Attack frequency and timing
- Resource spending patterns
- Defensive posture (aggressive/passive)
- Coordination with tribe


### Diplomatic Intelligence

**Tribe Relations:**
- Tribe membership and rank
- Tribe size and power
- Alliance partners (NAPs, formal alliances)
- Enemy tribes (wars, feuds)
- Diplomatic reputation
- Tribe activity level

**Support Network:**
- Villages providing support troops
- Villages receiving support
- Support troop composition
- Response time for reinforcements
- Defensive pacts
- Coordinated attack patterns

### Battle Intelligence

**Recent Combat History:**
- Attacks received (last 7 days)
- Attacks sent (if intercepted)
- Win/loss ratio
- Casualties taken
- Loot gained/lost
- Revenge targets
- Battle report summaries

**Combat Patterns:**
- Preferred attack times
- Typical army compositions
- Target selection (farms vs warriors)
- Retaliation likelihood
- Defensive response patterns


### Geographic Intelligence

**Territory Control:**
- Villages owned by player
- Village locations and spacing
- Strategic positioning
- Resource field distribution
- Expansion direction
- Border vulnerabilities

**Regional Threats:**
- Nearby hostile players
- Barbarian village locations
- Contested territories
- Strategic chokepoints
- Safe zones and danger zones

### Special Intelligence

**Conquest Status:**
- Village loyalty level
- Recent loyalty changes
- Noble attacks received
- Conquest vulnerability
- Time since last noble attack

**Rare Intel:**
- Secret tribe plans (from infiltrators)
- Coordinated attack timing
- Mass recruitment drives
- Diplomatic negotiations
- Internal tribe conflicts
- Player vacation mode status

---

## Scouting Outcomes

### Successful Scout

**Full Success (90-100% intel quality):**


**Example Report 1:**
```
Scout Report - Village: Ironhold (543|287)
Status: SUCCESS - Undetected
Scout: Light Scout x3
Time: 2025-12-01 14:23:45

GARRISON FORCES:
- Spearmen: 245
- Swordsmen: 180
- Archers: 95
- Light Cavalry: 42
- Heavy Cavalry: 18
- Catapults: 3

DEFENSES:
- Wall: Level 15 (Good condition)
- Watchtower: Level 8
- Traps: Estimated 50-75

RESOURCES (estimated):
- Wood: 12,400
- Clay: 8,900
- Iron: 6,200
- Food: 15,600

BUILDINGS (visible):
- Headquarters: Level 18
- Barracks: Level 20
- Stable: Level 15
- Workshop: Level 10
- Academy: Level 12

ACTIVITY:
- Player last seen: 2 hours ago
- Recent construction: Warehouse (completed 6h ago)
- No incoming attacks detected

ASSESSMENT: Medium-strength village. Moderate defenses. Player semi-active.
```


**Example Report 2:**
```
Deep Spy Report - Village: Shadowfen (621|445)
Status: SUCCESS - Deep Infiltration
Agent: Deep Spy x1
Time: 2025-12-01 09:15:22

COMPLETE GARRISON:
- Spearmen: 0
- Swordsmen: 450
- Archers: 320
- Light Cavalry: 125
- Heavy Cavalry: 85
- Rams: 8
- Catapults: 12
- Nobles: 1

SUPPORT TROOPS (from allies):
- From "Stormwind" (634|441): 200 Spearmen, 100 Archers
- From "Ravencrest" (618|438): 150 Swordsmen, 50 Heavy Cavalry

EXACT RESOURCES:
- Wood: 24,567
- Clay: 18,923
- Iron: 31,445
- Food: 42,108

COMPLETE BUILDING LIST:
- Headquarters: Level 22
- Barracks: Level 25 (MAX)
- Stable: Level 20
- Workshop: Level 15
- Academy: Level 15
- Smithy: Level 18
- Rally Point: Level 10
- Market: Level 20
- Warehouse: Level 25
- Granary: Level 24
- Wall: Level 18
- Farm: Level 30

RESEARCH COMPLETED:
- Infantry Attack: Level 3
- Cavalry Defense: Level 2
- Siege Weapons: Level 2
- Scout Efficiency: Level 1

CURRENT PRODUCTION:
- Training: 50 Swordsmen (complete in 2h 15m)
- Research: Cavalry Attack Level 3 (complete in 8h 42m)
- Construction: Wall Level 19 (complete in 5h 30m)

OUTGOING ORDERS:
- Attack on "Millbrook" (598|452) - 300 Swordsmen, 100 Cavalry, 5 Rams
  Arrival: 2025-12-01 16:45:00

PLAYER ACTIVITY:
- Currently ONLINE
- Last command: 8 minutes ago
- Typical online hours: 08:00-23:00 UTC
- Response time: Fast (avg 15 minutes)

TRIBE: [Shadow Legion] - Rank: Elder
- Tribe size: 45 members
- Tribe rank: #8
- Active war with: [Iron Brotherhood]

ASSESSMENT: Strong offensive village. Well-defended. Active player. High threat level.
```


### Partially Successful Scout

**Partial Success (40-89% intel quality):**

**Example Report 3:**
```
Scout Report - Village: Thornkeep (712|334)
Status: PARTIAL SUCCESS - Detected but escaped
Scout: Scout Cavalry x5 (2 lost)
Time: 2025-12-01 11:47:33

GARRISON FORCES (estimated):
- Infantry: 400-600 (mixed types)
- Archers: Unknown
- Cavalry: 50-100 (light and heavy)
- Siege: Possibly present

DEFENSES:
- Wall: Level 12-15 (uncertain)
- Watchtower: Level 10+ (detected our scouts)
- Counter-scouts: ACTIVE (killed 2 of our scouts)

RESOURCES:
- Unable to determine (scouts fled before gathering economic intel)

BUILDINGS:
- Headquarters: Level 15+
- Barracks: Present (level unknown)
- Stable: Present (level unknown)
- Watchtower: Level 10+ (confirmed)

ACTIVITY:
- Player status: Unknown
- ALERT: Village has active counter-scout patrols
- High defensive readiness

ASSESSMENT: Hostile detection. Village is well-defended and alert. 
Recommend deep spy or wait for defenses to relax.
```


**Example Report 4:**
```
Scout Report - Village: Mistwood (445|556)
Status: PARTIAL - Incomplete Data
Scout: Light Scout x8 (all returned)
Time: 2025-12-01 13:22:18

GARRISON FORCES (partial):
- Spearmen: 180
- Swordsmen: [DATA CORRUPTED]
- Archers: 65
- Cavalry: Unable to count (moving during observation)
- Siege: None visible

DEFENSES:
- Wall: Level 8 (damaged - recent attack?)
- Watchtower: Not present or Level 0
- Traps: Unknown

RESOURCES (rough estimate):
- Wood: 5,000-10,000
- Clay: 3,000-8,000
- Iron: Unknown
- Food: Low (granary appeared nearly empty)

BUILDINGS (partial view):
- Headquarters: Level 14
- Barracks: Level 16
- Other buildings: Obscured by fog/weather

ACTIVITY:
- Player last seen: Unknown
- Recent battle: Evidence of recent combat (damaged wall, troop movements)
- Possible incoming support detected

NOTES: 
- Weather conditions hampered observation
- Village appears to be recovering from recent attack
- Recommend follow-up scout in 6-12 hours

ASSESSMENT: Weakened target. Incomplete data. Proceed with caution.
```


### Failed Scout

**Complete Failure (0-39% intel quality):**

**Example Report 5:**
```
Scout Report - Village: Dragonspire (823|267)
Status: FAILED - All scouts lost
Scout: Light Scout x10 (ALL KILLED)
Time: 2025-12-01 15:08:41

GARRISON FORCES:
- Unknown - scouts eliminated before gathering intel

DEFENSES:
- EXTREMELY STRONG
- Counter-scout forces: ACTIVE AND DEADLY
- Advanced detection systems present

CASUALTIES:
- Light Scout x10: KILLED

INTELLIGENCE GATHERED:
- None

ENEMY AWARENESS:
- Village owner is now AWARE of your scouting attempt
- Expect possible retaliation
- Your village location may be compromised

ASSESSMENT: DO NOT SCOUT THIS VILLAGE AGAIN WITHOUT SUPERIOR FORCES.
High-level defensive infrastructure. Possible trap or ambush setup.
```

**Example Report 6:**
```
Scout Report - Village: Blackwater (534|689)
Status: FAILED - Scouts never returned
Scout: Deep Spy x2 (MISSING - presumed captured)
Time: 2025-12-01 08:45:00
Last Contact: 2025-12-01 10:12:33

GARRISON FORCES:
- Unknown

DEFENSES:
- Unknown - likely includes counter-intelligence capabilities

CASUALTIES:
- Deep Spy x2: MISSING (presumed captured or killed)

INTELLIGENCE GATHERED:
- Minimal - scouts reported entering village, then went silent
- Last transmission: "Heavy security... multiple checkpoints... [SIGNAL LOST]"

ENEMY AWARENESS:
- Village owner DEFINITELY KNOWS you attempted espionage
- Your spies may be interrogated (risk of intel leak about YOUR village)
- Captured spies may reveal your attack plans if any were discussed

ASSESSMENT: CRITICAL FAILURE. Counter-intelligence operation detected our spies.
Village has sophisticated security. Your own security may be compromised.
Recommend defensive preparations.
```


### Trapped Scout

**Scout Captured/Interrogated:**

**Example Report 7:**
```
Scout Report - Village: Grimhold (667|423)
Status: TRAPPED - Scout captured alive
Scout: Infiltrator x1 (CAPTURED)
Time: 2025-12-01 12:30:15

GARRISON FORCES:
- Partial data gathered before capture

DEFENSES:
- Counter-intelligence: EXPERT LEVEL
- Interrogation facilities: Present

CASUALTIES:
- Infiltrator x1: CAPTURED ALIVE

INTELLIGENCE GATHERED:
- Limited data before capture (see attached partial report)

CRITICAL WARNING:
- Your infiltrator is being interrogated
- Risk of intel leak: HIGH
- Enemy may learn:
  * Your village location and strength
  * Your tribe's plans
  * Other ongoing scout missions
  * Your attack intentions

ENEMY ACTIONS:
- Village owner sent you a message: "We have your spy. Surrender or face consequences."
- Possible ransom demand incoming
- Retaliation attack likely

RECOMMENDATIONS:
- Prepare defenses immediately
- Warn tribe members of potential intel breach
- Consider rescue mission (high risk)
- Consider ransom negotiation
- Abort any planned attacks on this target

ASSESSMENT: WORST CASE SCENARIO. Your operational security is compromised.
```


### Ambushed Scout

**Scout Walked Into Trap:**

**Example Report 8:**
```
Scout Report - Village: Deathwatch (789|512)
Status: AMBUSHED - Trap sprung
Scout: Scout Cavalry x15 (12 killed, 3 escaped)
Time: 2025-12-01 16:55:27

GARRISON FORCES:
- AMBUSH FORCE ENCOUNTERED:
  * Heavy Cavalry: 80+ (emerged from hiding)
  * Archers: 150+ (fired from walls)
  * Counter-scouts: 30+ (pursuit units)

DEFENSES:
- Trap: DELIBERATE AMBUSH SETUP
- False weakness displayed to lure scouts
- Hidden forces revealed only after scouts entered kill zone

CASUALTIES:
- Scout Cavalry x12: KILLED IN AMBUSH
- Scout Cavalry x3: Escaped with wounds

INTELLIGENCE GATHERED:
- DECEPTIVE - Initial observations showed weak garrison
- ACTUAL STRENGTH: Much higher than displayed
- Village was BAITING scout attempts

ENEMY TACTICS:
- Sophisticated trap using false intel
- Coordinated ambush with multiple unit types
- Pursuit forces to prevent escape
- Likely monitoring for follow-up attacks

ASSESSMENT: DELIBERATE COUNTER-INTELLIGENCE OPERATION.
Village owner is experienced and dangerous. All previous intel on this village is SUSPECT.
Do not trust any "easy target" indicators. Assume heavy defenses.
```


**Example Report 9:**
```
Scout Report - Village: Serpent's Nest (456|678)
Status: AMBUSHED - Counter-scout interception
Scout: Light Scout x20 (18 killed, 2 returned)
Time: 2025-12-01 10:15:44

GARRISON FORCES:
- Unable to observe - intercepted before reaching village

DEFENSES:
- Perimeter patrol: ACTIVE
- Counter-scout network: EXTENSIVE
- Early warning system: EFFECTIVE

AMBUSH DETAILS:
- Intercepted 2km from village by patrol
- Counter-scout force: 40+ Rangers
- Coordinated pursuit across multiple tiles
- No escape route provided

CASUALTIES:
- Light Scout x18: KILLED BY COUNTER-SCOUTS
- Light Scout x2: Barely escaped

INTELLIGENCE GATHERED:
- None (never reached target)

ENEMY CAPABILITIES:
- Wide defensive perimeter
- Mobile counter-scout patrols
- Excellent coordination
- Likely part of larger defensive network

TRIBAL INTEL:
- Village appears to be part of coordinated tribal defense
- Multiple villages may share counter-scout forces
- Entire region may be under surveillance

ASSESSMENT: Target is part of sophisticated defensive network.
Standard scouting methods ineffective. Recommend alternative approaches
(merchant scouts, long-range infiltrators, or diplomatic intel gathering).
```

---

## Counter-Scouting Mechanics

### Detection Systems

**Watchtower:**
- Increases scout detection chance by 5% per level
- Provides early warning of approaching scouts
- Range: 2-5 tiles depending on level
- Can spot large scout groups automatically


**Counter-Scout Units (Rangers/Hunters):**
- Specialized anti-scout units
- Patrol village perimeter automatically
- High detection rate vs all scout types
- Can pursue and eliminate fleeing scouts
- Bonus combat strength vs scout units

**Spy Network (Building):**
- Advanced counter-intelligence facility
- Detects infiltrators and deep spies
- Can feed false information to enemy scouts
- Provides alerts when village is being scouted
- Enables interrogation of captured spies

**Research: Counter-Intelligence:**
- Level 1: +10% scout detection
- Level 2: +20% detection, can identify scout origin
- Level 3: +30% detection, can capture scouts alive
- Level 4: +40% detection, can feed false intel
- Level 5: +50% detection, automatic counter-scout deployment

### Defensive Responses

**Passive Detection:**
- Scout spotted but allowed to gather intel
- Defender receives notification: "Enemy scouts detected from [Village Name]"
- Defender can track who is scouting them
- No immediate action taken

**Active Interception:**
- Counter-scouts deployed to intercept
- Scout combat resolution
- Survivors flee with partial/no intel
- Defender learns attacker's identity


**Scout Capture:**
- Scouts captured alive (requires tech)
- Interrogation mini-game or timer
- Captured scouts may reveal:
  * Sender's village location
  * Sender's military strength
  * Planned attacks
  * Tribe intelligence
- Ransom demands possible
- Execution as warning to others

**Ambush Setup:**
- Defender deliberately shows false weakness
- Hides majority of troops
- Lures scouts into kill zone
- Eliminates scouts with overwhelming force
- Psychological warfare effect

**Retaliation Strike:**
- Immediate counter-attack on scout sender
- "You scouted me, now I attack you"
- Can be automated response (policy setting)
- Escalation risk

### Deception Tactics

**False Information:**
- Spy Network can feed fake intel to enemy scouts
- Shows inflated/deflated troop counts
- Displays fake building levels
- Shows false resource amounts
- Indicates fake player activity status


**Decoy Villages:**
- Appear weak to attract attacks
- Actually heavily defended
- Trap for greedy attackers
- Coordinated with tribe for support

**Hidden Troops:**
- Troops hidden in special buildings
- Not visible to standard scouts
- Revealed only during combat
- Requires deep spy to detect

**Fake Activity:**
- Automated scripts to simulate player presence
- Random troop movements
- Scheduled building upgrades
- Market activity
- Makes village appear active when player offline

### Counter-Intelligence Buildings

**Watchtower:**
- Level 1-5: Basic detection (+5-25% per level)
- Level 6-10: Extended range (+1 tile per 2 levels)
- Level 11-15: Scout identification (shows origin village)
- Level 16-20: Automatic counter-scout deployment

**Spy Network:**
- Level 1-5: Detect infiltrators
- Level 6-10: Feed false information
- Level 11-15: Capture scouts alive
- Level 16-20: Double agent operations (turn enemy spies)

**Guard House:**
- Trains counter-scout units
- Provides patrol automation
- Increases capture chance
- Reduces enemy intel quality


### Counter-Intelligence Policies

**Defensive Postures:**

1. **Open Village:**
   - No counter-scout measures
   - Scouts gather intel freely
   - Useful for diplomatic relations
   - Shows trust/weakness

2. **Alert Status:**
   - Active detection systems
   - Scouts detected and reported
   - No aggressive action
   - Monitoring only

3. **Hostile Response:**
   - Scouts intercepted and killed
   - Immediate retaliation possible
   - Aggressive counter-intelligence
   - War footing

4. **Deception Mode:**
   - False intel fed to scouts
   - Ambush traps set
   - Psychological warfare
   - Advanced tactics

---

## Intel Decay & Freshness

### Time-Based Decay

**Intel Freshness Categories:**

| Age | Status | Reliability | Visual Indicator |
|-----|--------|-------------|------------------|
| 0-1 hour | Fresh | 95-100% | Green, bright |
| 1-6 hours | Recent | 80-95% | Green, normal |
| 6-24 hours | Aging | 60-80% | Yellow, faded |
| 1-3 days | Stale | 40-60% | Orange, warning |
| 3-7 days | Old | 20-40% | Red, unreliable |
| 7+ days | Ancient | 0-20% | Gray, nearly useless |


### Decay Mechanics

**Troop Count Decay:**
- Decays fastest (troops constantly training/dying)
- After 6 hours: ±20% uncertainty
- After 24 hours: ±50% uncertainty
- After 3 days: Completely unreliable

**Building Level Decay:**
- Decays slowly (buildings upgrade infrequently)
- After 24 hours: Still mostly accurate
- After 7 days: May be 1-2 levels outdated
- After 30 days: Could be significantly different

**Resource Decay:**
- Decays very fast (resources constantly changing)
- After 1 hour: ±30% uncertainty
- After 6 hours: ±100% uncertainty
- After 24 hours: Useless

**Activity Status Decay:**
- Decays immediately
- Real-time data only
- Historical patterns remain useful
- "Last seen" timestamp always accurate

### Decay Indicators

**Visual Cues:**
- Faded colors for old intel
- Warning icons next to stale data
- Confidence percentage displayed
- "Last updated" timestamp prominent
- Uncertainty ranges shown (e.g., "500-800 troops")


**Example Stale Intel Display:**
```
Scout Report - Village: Oldfort (445|223)
⚠️ WARNING: This intel is 4 days old - reliability: LOW

GARRISON FORCES (OUTDATED):
- Spearmen: 150 ± 100 (could be 50-250)
- Swordsmen: 200 ± 150 (could be 50-350)
- Cavalry: Unknown (data too old)

DEFENSES (POSSIBLY OUTDATED):
- Wall: Level 12 (may have been upgraded)
- Last updated: 4 days ago

RESOURCES (USELESS):
- Data too old to display

RECOMMENDATION: Re-scout this village before attacking.
Current intel is unreliable and may lead to failed attack.
```

### Refresh Mechanics

**Automatic Refresh:**
- Infiltrators provide periodic updates
- Watchtowers update nearby village intel
- Battle reports refresh combat data
- Market trades update economic data

**Manual Refresh:**
- Send new scouts
- Request tribe intel sharing
- Diplomatic information exchange
- Merchant scout visits

**Partial Refresh:**
- Some data types update independently
- Activity status refreshes from any interaction
- Troop movements visible in real-time
- Building construction visible from distance

---

## Tribe Intel Sharing

### Shared Intelligence Systems

**Tribe Intel Pool:**
- All tribe members can contribute scout reports
- Shared database of enemy village intel
- Automatic deduplication (newest intel kept)
- Permission-based access control


**Intel Sharing Permissions:**

| Rank | View Intel | Share Intel | Request Intel | Delete Intel | Manage Tags |
|------|------------|-------------|---------------|--------------|-------------|
| Recruit | Own only | No | No | No | No |
| Member | All tribe | Yes | Yes | Own only | No |
| Officer | All tribe | Yes | Yes | Any | Yes |
| Elder | All tribe | Yes | Yes | Any | Yes |
| Leader | All tribe | Yes | Yes | Any | Yes |

### Map Markers & Tags

**Village Tags:**
- "Easy Target" - weak defenses, low activity
- "Dangerous" - strong defenses, active player
- "Farm Village" - good for resource raids
- "Offline" - player inactive
- "Trap" - suspected ambush setup
- "Priority Target" - strategic importance
- "Allied" - friendly, do not attack
- "NAP" - non-aggression pact
- "Enemy HQ" - high-value target
- Custom tags (tribe-specific)

**Map Markers:**
- Color-coded pins on map
- Shared visibility with tribe
- Notes attached to markers
- Expiration dates for temporary markers
- Alert markers for threats

**Intel Channels:**
- Dedicated chat channel for intel sharing
- Automated scout report posting
- Alert notifications for critical intel
- Intel request system
- Bounty system for specific intel needs


### Spy Ranks & Specialization

**Tribe Spy Roles:**

1. **Scout Master:**
   - Coordinates all tribe scouting operations
   - Assigns scout missions to members
   - Maintains intel database
   - Analyzes enemy patterns
   - Plans reconnaissance campaigns

2. **Deep Cover Operative:**
   - Specializes in infiltration missions
   - Long-term embedded intelligence
   - High-risk, high-reward operations
   - Reports directly to leadership

3. **Counter-Intelligence Officer:**
   - Protects tribe from enemy spies
   - Coordinates counter-scout operations
   - Identifies enemy intel gathering
   - Manages deception operations

4. **Intel Analyst:**
   - Processes raw scout reports
   - Identifies patterns and trends
   - Produces strategic assessments
   - Maintains target priority lists

5. **Field Scout:**
   - Conducts routine reconnaissance
   - Quick response scout missions
   - Monitors enemy movements
   - Provides real-time updates

### Coordinated Intel Operations

**Tribe-Wide Scouting Campaigns:**
- Coordinated mass scouting of enemy tribe
- Distributed targets to avoid detection patterns
- Synchronized timing for snapshot of enemy state
- Compiled reports for strategic planning


**Intel Request System:**
```
Example Intel Request:

FROM: WarChief_Khan
TO: Tribe Intel Channel
PRIORITY: HIGH

REQUEST: Need current intel on enemy village "Ironforge" (678|445)

REASON: Planning coordinated attack in 24 hours

REQUIREMENTS:
- Troop counts (detailed)
- Wall level
- Support troops present
- Player activity status
- Incoming support (if any)

DEADLINE: 18 hours from now

REWARD: 5,000 resources to scout who provides intel

STATUS: OPEN
```

**Intel Bounty System:**
- Tribe offers rewards for specific intelligence
- Resources, favor points, or rank advancement
- Encourages active reconnaissance
- Prioritizes critical intel needs

### Shared Intel Features

**Real-Time Updates:**
- Live feed of scout reports
- Instant notifications for critical intel
- Shared map view with all tribe markers
- Coordinated attack planning tools

**Intel Archive:**
- Historical scout reports stored
- Pattern analysis over time
- Enemy behavior tracking
- Seasonal activity trends

**Collaborative Analysis:**
- Multiple members can annotate reports
- Discussion threads on intel items
- Voting on intel reliability
- Crowdsourced threat assessment

---

## Advanced Features

### False Report Generation

**Deception Operations:**


**Planted False Intel:**
- Spy Network building enables false report generation
- Defender can craft fake scout reports
- Enemy receives believable but false information
- Used to lure enemies into traps

**Example False Report:**
```
Scout Report - Village: Baitville (555|444)
Status: SUCCESS - Undetected
Scout: Light Scout x5
Time: 2025-12-01 14:30:00

[THIS REPORT IS FAKE - PLANTED BY DEFENDER]

GARRISON FORCES:
- Spearmen: 50 (FAKE - actually 500)
- Swordsmen: 30 (FAKE - actually 400)
- Archers: 20 (FAKE - actually 200)
- Cavalry: 0 (FAKE - actually 150)

DEFENSES:
- Wall: Level 5 (FAKE - actually Level 18)
- Watchtower: Not present (FAKE - Level 15)

RESOURCES:
- Wood: 25,000 (REAL - bait)
- Clay: 20,000 (REAL - bait)
- Iron: 15,000 (REAL - bait)

ACTIVITY:
- Player last seen: 3 days ago (FAKE - player is online)

ASSESSMENT: Easy target. Weak defenses. Offline player. High loot potential.
[This assessment is designed to lure you into an ambush]
```

**Double Agent Operations:**
- Captured enemy spies "turned" to work for you
- Feed false intel back to enemy
- Long-term deception campaigns
- Requires high-level Spy Network


### Fog of War Events

**Dynamic Map Events:**

**1. Smoke Signals:**
- Large battles create visible smoke on map
- All players in region can see
- Indicates major combat occurring
- No details, just location and scale

**2. Refugee Reports:**
- Fleeing civilians from conquered villages
- Provide rumors and partial intel
- Low accuracy but free information
- Random event trigger

**3. Merchant Gossip:**
- Traders passing through share news
- Economic intel and market trends
- Player activity rumors
- Regional threat warnings

**4. Spy Network Intercepts:**
- High-level Spy Network can intercept enemy communications
- Chance to read enemy scout reports
- Discover enemy attack plans
- Counter-intelligence gold mine

**5. Deserter Intel:**
- Enemy troops desert and join you
- Bring intel about their former village
- Medium accuracy, slightly outdated
- Rare but valuable

**6. Captured Messenger:**
- Intercept enemy message carriers
- Read communications between enemy players
- Discover diplomatic negotiations
- Reveals strategic plans


### Special Map Objectives

**Intel-Revealing Locations:**

**1. Observation Posts:**
- Neutral map locations
- Can be captured and held
- Provide vision of surrounding area
- Reveal troop movements in region
- Contested by multiple players

**2. Spy Guilds:**
- Neutral NPC buildings
- Can be bribed for information
- Sell intel on nearby players
- Quality varies by payment
- Limited uses per day

**3. Ancient Watchtowers:**
- Ruins that can be restored
- Provide permanent vision when controlled
- Strategic value for tribes
- Require resources to maintain

**4. Information Brokers:**
- NPC characters on map
- Trade resources for intel
- Specialize in different intel types
- Prices vary by demand
- Can be unreliable (chance of false info)

**5. Sacred Sites:**
- Religious/mystical locations
- Provide prophetic visions (random intel)
- Controlled through religious buildings
- Tribe-wide benefits
- Heavily contested

### Anti-Abuse Considerations

**Preventing Intel Exploitation:**


**1. Scout Spam Prevention:**
- Cooldown timers between scout missions to same target
- Diminishing returns on repeated scouting
- Increased detection chance for frequent scouts
- Resource cost scaling for excessive scouting

**2. Multi-Account Intel Sharing:**
- Tribe intel sharing requires minimum account age
- New members have limited intel access
- Suspicious intel sharing patterns flagged
- Cross-tribe intel trading monitored

**3. Bot Detection:**
- Automated scouting patterns detected
- CAPTCHA for suspicious activity
- Rate limiting on scout missions
- Behavioral analysis

**4. Intel Market Abuse:**
- Limits on intel trading between non-allied players
- Verification of intel authenticity
- Reputation system for intel sellers
- Penalties for selling false intel

**5. Spy Cycling:**
- Captured spies have respawn timers
- Limit on simultaneous infiltrators
- Escalating costs for repeated spy losses
- Diplomatic penalties for excessive espionage

**6. Metagaming Prevention:**
- Out-of-game intel sharing discouraged
- In-game intel systems provide advantages
- External tools detection
- Fair play enforcement


### Advanced Intel Mechanics

**Predictive Intelligence:**
- AI analysis of historical patterns
- Predicts enemy behavior
- Suggests optimal scout timing
- Identifies vulnerable windows
- Forecasts enemy attacks

**Intel Confidence Scoring:**
- Each piece of intel has confidence rating
- Based on source quality, age, and corroboration
- Multiple scouts increase confidence
- Conflicting reports lower confidence
- Visual indicators (stars, percentages)

**Corroboration System:**
- Multiple scouts to same target
- Cross-reference reports
- Identify discrepancies (possible deception)
- Increase intel reliability
- Tribe-wide verification

**Intel Fusion:**
- Combine multiple intel sources
- Scout reports + battle reports + market data
- Create comprehensive target profile
- Automated analysis tools
- Strategic recommendations

**Counter-Intel Warfare:**
- Detect enemy scouting patterns
- Predict enemy attack timing
- Identify enemy intel priorities
- Proactive deception campaigns
- Information warfare doctrine


---

## Intel UI & Presentation

### Intel Dashboard

**Main Intel Screen:**
- List of all scouted villages
- Sortable by: freshness, threat level, distance, loot potential
- Filter by: tribe, region, intel quality, tags
- Quick actions: re-scout, attack, share with tribe
- Visual threat indicators

**Village Intel Card:**
```
╔══════════════════════════════════════════════════════╗
║ IRONHOLD (543|287) - [Shadow Legion]                ║
║ Owner: DarkKnight | Distance: 12.4 tiles             ║
╠══════════════════════════════════════════════════════╣
║ THREAT LEVEL: ████████░░ HIGH (8/10)                ║
║ INTEL FRESHNESS: ████████░░ 2 hours ago             ║
║ CONFIDENCE: ████████░░ 85%                          ║
╠══════════════════════════════════════════════════════╣
║ GARRISON: ~1,200 troops (mixed)                      ║
║ DEFENSES: Wall Lv15, Watchtower Lv8                 ║
║ RESOURCES: ~43,000 total (medium)                    ║
║ ACTIVITY: Online 2h ago (semi-active)                ║
╠══════════════════════════════════════════════════════╣
║ TAGS: [Dangerous] [Active Player] [Well-Defended]   ║
║                                                      ║
║ [View Full Report] [Re-Scout] [Plan Attack] [Share] ║
╚══════════════════════════════════════════════════════╝
```

### Map Integration

**Intel Overlay:**
- Color-coded villages by threat level
- Intel freshness indicators
- Tribe tags visible
- Click village for quick intel popup
- Filter map by intel criteria


**Threat Heatmap:**
- Visual representation of danger zones
- Red = high threat, Green = safe/weak targets
- Based on aggregated intel
- Updates as intel refreshes
- Tribe-wide shared view

### Notification System

**Intel Alerts:**
- "New scout report available"
- "Intel on [Village] is now stale - consider re-scouting"
- "Your scouts were detected by [Village]"
- "Enemy scouts detected near your village"
- "Tribe member shared intel on [Village]"
- "High-value target identified: [Village]"
- "Warning: [Village] may be a trap"

**Alert Priorities:**
- Critical: Captured spies, incoming retaliation
- High: Scout losses, trap detection
- Medium: Successful scouts, intel sharing
- Low: Routine updates, stale intel warnings

---

## Strategic Intel Doctrine

### Offensive Intelligence

**Pre-Attack Reconnaissance:**
1. Initial light scout (basic intel, low risk)
2. Evaluate threat level
3. Deep spy if target appears valuable
4. Corroborate with tribe intel
5. Monitor for changes until attack
6. Final scout 1-2 hours before attack

**Target Selection:**
- Prioritize offline players
- Identify weak defenses
- Confirm high resources
- Check for support troops
- Verify no incoming reinforcements
- Assess retaliation risk


### Defensive Intelligence

**Counter-Reconnaissance:**
- Monitor who scouts you
- Track scouting patterns
- Identify potential attackers
- Pre-emptive strikes on scouts
- Deception operations

**Threat Assessment:**
- Identify nearby hostile players
- Monitor enemy troop buildups
- Track enemy expansion
- Predict attack timing
- Coordinate tribal defense

### Economic Intelligence

**Market Intelligence:**
- Track resource prices
- Identify trading opportunities
- Monitor competitor economies
- Predict resource shortages
- Optimize trade routes

**Production Espionage:**
- Enemy production capacity
- Resource generation rates
- Economic vulnerabilities
- Blockade opportunities
- Trade disruption targets

### Diplomatic Intelligence

**Alliance Monitoring:**
- Track enemy alliances
- Identify diplomatic weaknesses
- Monitor NAP violations
- Detect internal tribe conflicts
- Exploit diplomatic rifts

**Tribe Strength Assessment:**
- Total tribe military power
- Active vs inactive members
- Coordination effectiveness
- Leadership quality
- Expansion patterns


---

## Intel Gameplay Loop

### Daily Intel Operations

**Morning Routine:**
1. Check overnight scout reports
2. Review tribe intel updates
3. Refresh stale intel on key targets
4. Update threat assessments
5. Plan day's reconnaissance missions

**Active Play:**
1. Send scouts to new targets
2. Monitor scout returns
3. Share intel with tribe
4. Respond to counter-scout alerts
5. Adjust defensive posture

**Evening Review:**
1. Analyze day's intel gathering
2. Identify intel gaps
3. Plan overnight infiltrations
4. Set defensive alerts
5. Coordinate with tribe for next day

### Long-Term Intel Strategy

**Week 1-2 (Early Game):**
- Scout immediate neighbors
- Identify easy farming targets
- Map local threats
- Establish basic intel network

**Week 3-4 (Mid Game):**
- Deep reconnaissance of enemies
- Coordinate tribe intel sharing
- Develop counter-intelligence
- Track enemy expansion

**Week 5+ (Late Game):**
- Strategic intelligence operations
- Long-term infiltrators
- Deception campaigns
- Information warfare dominance


---

## Implementation Priorities

### Phase 1: Core Systems
- Basic scout units (Light Scout, Deep Spy)
- Simple fog of war (hide troop counts, buildings)
- Scout report generation
- Intel freshness tracking
- Basic counter-scout mechanics (Watchtower)

### Phase 2: Advanced Features
- Additional scout types (Cavalry, Rangers)
- Counter-intelligence buildings (Spy Network)
- Intel decay mechanics
- Tribe intel sharing
- Map markers and tags

### Phase 3: Sophisticated Warfare
- False intel generation
- Infiltrator units
- Advanced counter-measures
- Intel confidence scoring
- Predictive analysis

### Phase 4: Polish & Balance
- UI/UX refinement
- Anti-abuse systems
- Balance adjustments
- Special map objectives
- Dynamic fog of war events

---

## Balance Considerations

**Scout Cost vs Value:**
- Scouts should be affordable for regular use
- Deep intel should require significant investment
- Risk/reward balanced for different scout types
- Counter-scouting should be viable defense strategy


**Information Advantage:**
- Good intel should provide clear advantage
- But not make attacks risk-free
- Defenders should have counter-play options
- Skill in intel gathering should be rewarded

**Time Investment:**
- Intel gathering should require active play
- But not be overwhelming time sink
- Automation options for routine tasks:
  - Saved scout routes with cooldowns and max-distance limits to prevent spam
  - Auto-refresh watchlist: resend light scouts to selected targets when intel is stale
  - One-tap presets from rally point (e.g., "Light probe", "Deep scout", "Fake siege")
  - Report digests that summarize changes since last scan instead of flooding inbox
  - Safety rails: daily cap and randomization to avoid automated abuse
- Strategic depth for dedicated players

**Tribe Coordination:**
- Encourage cooperative intel sharing
- But allow solo players to compete
- Reward organized tribes
- Prevent intel monopolies

**Deception Viability:**
- False intel should be detectable with effort
- But effective against careless players
- High-level play should involve mind games
- Counter-intelligence should be valuable skill

---

## Example Scenarios

### Scenario 1: The Perfect Raid

**Situation:** Player wants to raid enemy village for resources

**Intel Process:**
1. Send 5 Light Scouts (low cost, acceptable risk)
2. Report shows: 200 troops, Wall Lv8, 30k resources, player offline 6h
3. Confirm with tribe - no recent intel contradicts
4. Plan attack with 400 troops (2:1 advantage)
5. Send Deep Spy 1h before attack for final confirmation
6. Deep Spy confirms: still offline, no reinforcements incoming
7. Launch attack with confidence
8. Success: Minimal losses, high loot


### Scenario 2: The Trap

**Situation:** Player scouts what appears to be easy target

**Intel Process:**
1. Send 3 Light Scouts to "weak" village
2. Report shows: 50 troops, Wall Lv5, 40k resources, player offline 3 days
3. Looks too good to be true - send Deep Spy to verify
4. Deep Spy is CAPTURED - warning received
5. Realize it's a trap - village has Spy Network Lv15
6. Abort attack plans
7. Prepare defenses for likely retaliation
8. Enemy attacks but player is ready - successful defense

**Lesson:** Verify suspicious intel, trust your instincts

### Scenario 3: Counter-Intelligence Victory

**Situation:** Player being repeatedly scouted by enemy

**Counter-Intel Process:**
1. Watchtower detects 3 scout attempts in 2 days
2. Identify scout origin: enemy tribe member
3. Deploy Counter-Scouts (Rangers) on patrol
4. Next enemy scout attempt: 8 scouts killed, 2 captured
5. Interrogate captured scouts - learn enemy planning attack
6. Feed false intel through Spy Network: show weak garrison
7. Actually reinforce with tribe support troops
8. Enemy attacks based on false intel - walks into ambush
9. Devastating enemy defeat - war turns in your favor

**Lesson:** Active counter-intelligence can turn defense into offense


### Scenario 4: Tribe Intel Coordination

**Situation:** Tribe planning coordinated attack on enemy tribe

**Coordination Process:**
1. Scout Master assigns scout targets to 20 tribe members
2. Each member scouts 2-3 enemy villages
3. All reports shared to tribe intel pool within 6 hours
4. Intel Analyst compiles data: identifies 5 weak targets
5. Cross-reference with activity patterns: find 3 offline players
6. Deep Spies sent to top 3 targets for detailed intel
7. Final target selection: 2 villages with confirmed weak defenses
8. Coordinated attack launched by 10 tribe members
9. Both villages conquered simultaneously
10. Enemy tribe demoralized by coordinated strike

**Lesson:** Organized intel gathering enables strategic victories

### Scenario 5: The Long Game

**Situation:** Player embeds infiltrator in enemy capital

**Long-Term Intel:**
1. Send Infiltrator (expensive, high risk) to enemy leader's village
2. Infiltrator successfully embeds - provides daily reports for 2 weeks
3. Intel gathered:
   - Daily troop movements
   - Attack planning discussions
   - Resource stockpile patterns
   - Online/offline schedule
   - Diplomatic negotiations
4. Discover enemy planning major offensive in 10 days
5. Share intel with tribe - prepare counter-measures
6. When enemy attacks, tribe is fully prepared
7. Enemy offensive fails catastrophically
8. Infiltrator remains undetected - continues providing intel

**Lesson:** Patient, long-term intelligence operations pay huge dividends

---

## Conclusion

The intelligence and scouting system creates a deep layer of strategic gameplay where information becomes a valuable resource. Players must balance the cost and risk of gathering intel against the advantage it provides. Skilled players will master the art of reconnaissance, counter-intelligence, and deception, turning information warfare into a decisive factor in tribal conflicts.

Success requires:
- Active reconnaissance and intel gathering
- Careful analysis and verification of information
- Effective counter-intelligence measures
- Coordination with tribe members
- Strategic use of deception
- Adaptation to enemy intel operations

The fog of war ensures that no player has perfect information, creating uncertainty, risk, and opportunities for clever tactics. The best commanders will be those who can gather, analyze, and act on intelligence while denying the same advantages to their enemies.
