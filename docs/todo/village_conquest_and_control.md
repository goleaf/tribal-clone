# Village Conquest & Control

## Overview

Village conquest is the ultimate strategic objective in the game, allowing players to expand their territory, increase resource production, and establish dominance over regions. Unlike simple raiding, conquest involves a multi-stage process that requires sustained military pressure, specialized units, and careful timing.

Conquest is not instantaneous. Villages have inherent **loyalty** to their current owner, representing the population's allegiance, administrative control, and cultural ties. Attackers must systematically break down this loyalty through military action, psychological warfare, and the deployment of specialized conquest units before a village can change hands.

---

## Core Conquest Mechanics

### Loyalty System

Every village has a **Loyalty** value ranging from 0 to 100:

- **100 Loyalty**: Village is fully loyal to its owner; cannot be conquered
- **50-99 Loyalty**: Village is stable but vulnerable to conquest attempts
- **25-49 Loyalty**: Village population is wavering; increased unrest
- **1-24 Loyalty**: Village is on the brink of revolt; severe penalties
- **0 Loyalty**: Village can be claimed by the next successful conquest action

#### Loyalty Effects on Village Performance

| Loyalty Range | Resource Production | Troop Training Speed | Building Construction | Unrest Events |
|---------------|---------------------|----------------------|-----------------------|---------------|
| 90-100 | 100% | 100% | 100% | None |
| 70-89 | 95% | 95% | 95% | Rare |
| 50-69 | 85% | 85% | 90% | Occasional |
| 30-49 | 70% | 70% | 75% | Common |
| 10-29 | 50% | 50% | 50% | Frequent |
| 0-9 | 25% | 25% | 25% | Constant |

#### Natural Loyalty Recovery

Villages naturally recover loyalty over time when not under attack:

- **Base Recovery**: +1 loyalty per hour
- **Palace Bonus**: +0.5 loyalty per palace level per hour
- **Tribe Bonus**: +0.25 loyalty per hour if owner is in a tribe
- **Governor Bonus**: +1 loyalty per hour if a hero is stationed as governor
- **Cultural Building Bonus**: +0.5 loyalty per hour from temples, monuments, etc.

Maximum natural recovery rate: +5 loyalty per hour with all bonuses

---

## Special Conquest Unit: The Chieftain

### Lore & Role

The **Chieftain** (or **Noble**, **Senator**, **Chief**, depending on faction) is a specialized political-military unit representing a powerful leader capable of swaying village populations and claiming territory. These are not mere soldiers but charismatic warlords, cunning diplomats, or religious figures who can break the will of a settlement and establish new rule.

Chieftains are rare, expensive, and vulnerable, making them high-value targets that must be carefully protected during conquest operations.

### Chieftain Statistics

| Attribute | Value | Notes |
|-----------|-------|-------|
| Training Cost | 30,000 wood, 25,000 clay, 40,000 iron, 50,000 food | Extremely expensive |
| Training Time | 48 hours | Requires Academy level 20+ |
| Population Cost | 10 | Significant population drain |
| Speed | 4 tiles/hour | Slow; requires escort |
| Attack Power | 50 | Weak in direct combat |
| Defense | 100 | Moderate defensive capability |
| Carry Capacity | 0 | Cannot carry resources |
| Loyalty Reduction | 20-30 per successful attack | Variable based on conditions |

### Chieftain Requirements

To train a Chieftain, a village must have:

- **Academy**: Level 20 or higher
- **Palace or Residence**: Level 15 or higher (only one Chieftain can be trained per Palace/Residence)
- **Rally Point**: Level 10 or higher
- **Stable**: Level 10 or higher
- **No existing Chieftain**: Only one Chieftain can exist per Palace/Residence at a time

### Chieftain Mechanics

#### Loyalty Reduction

When a Chieftain participates in a successful attack (where all defending troops are eliminated or the attacker wins decisively):

- **Base Loyalty Reduction**: 20 points
- **Bonus from Escort Size**: +1 point per 500 attacking troops (max +10)
- **Penalty from Defender Morale**: -5 points if defender has high morale buildings
- **Bonus from Repeated Attacks**: +2 points per previous Chieftain attack in last 24 hours (max +10)
- **Penalty from Distance**: -1 point per 20 tiles distance (max -5)

**Example**: A Chieftain with 2,000 escort troops attacking a village 15 tiles away with no morale buildings, on the third attack in 24 hours:
- Base: 20
- Escort: +4 (2,000 / 500)
- Repeated: +4 (2 previous attacks × 2)
- Distance: -1 (15 / 20, rounded)
- **Total**: 27 loyalty reduction

#### Chieftain Vulnerability

- If a Chieftain is present in a losing battle, there is a **50% chance the Chieftain is killed**
- If the attacker retreats or is defeated before eliminating defenders, the Chieftain survives but loyalty is not reduced
- Chieftains cannot be used in raids; only in full conquest attacks
- If a Chieftain dies, the Palace/Residence can train a new one after a 24-hour cooldown

### Tactical Use of Chieftains

**Wave Strategy**: Players typically send multiple waves:
1. **Clearing Wave**: Large army to eliminate defenders
2. **Chieftain Wave**: Chieftain with moderate escort to reduce loyalty
3. **Follow-up Waves**: Additional Chieftain attacks if loyalty remains

**Escort Composition**: Chieftains are usually escorted by:
- Fast cavalry for quick strikes
- Heavy infantry for protection
- Rams to destroy walls (if not already destroyed)

**Timing**: Chieftain attacks are often coordinated during:
- Defender's offline hours
- After major defensive armies are destroyed elsewhere
- In rapid succession to prevent loyalty recovery

---

## Capture Conditions

A village can only be captured when **all** of the following conditions are met:

### 1. Loyalty Threshold

- Village loyalty must be reduced to **0**
- Loyalty cannot go below 0 (excess reduction is wasted)

### 2. Military Dominance

At least one of the following must be true:

- **All defending troops eliminated**: No military units remain in the village
- **Walls destroyed**: Wall level reduced to 0
- **Overwhelming victory**: Attacker wins with 90%+ of their army surviving

### 3. Conquest Action

- A **Chieftain must be present** in the final conquering attack
- The attack must be flagged as a "Conquest" attack (not a raid or support)
- The Chieftain must survive the battle

### 4. Attacker Eligibility

The attacking player must:

- Have a **Palace** (not just a Residence) in the village sending the Chieftain
- Have available village slots (not at maximum village limit)
- Not be in beginner protection
- Not have conquered another village in the last 24 hours (cooldown)

### 5. Defender Eligibility

The defending village:

- Cannot be the defender's last village (last village cannot be conquered)
- Cannot be under beginner protection
- Cannot have been conquered in the last 7 days (conquest immunity)
- Cannot be in a "safe zone" (if such zones exist on the map)

### 6. Timing Constraints

- The conquest attack must land during the **conquest window**: typically 00:00 - 23:59 server time (all day), but some servers may restrict to specific hours
- If the defender has a **Palace**, they can delay conquest by 12 hours after loyalty reaches 0 (emergency defense period)

---

## Number & Timing of Attacks

### Typical Conquest Timeline

Conquering a village typically requires **3-8 Chieftain attacks** over **1-3 days**, depending on:

- Defender's loyalty recovery rate
- Attacker's Chieftain effectiveness
- Defensive resistance and counter-attacks
- Coordination and timing

### Attack Patterns

#### Pattern 1: Blitz Conquest (Fast & Risky)

- **Goal**: Conquer in under 12 hours
- **Method**: 4-6 Chieftain attacks in rapid succession
- **Requirements**: Multiple Chieftains from different villages, overwhelming military superiority
- **Risk**: High Chieftain loss rate, vulnerable to counter-attacks
- **Best For**: Weak or inactive defenders

#### Pattern 2: Siege Conquest (Slow & Safe)

- **Goal**: Conquer over 2-3 days with minimal losses
- **Method**: 1-2 Chieftain attacks per day, maintaining military pressure
- **Requirements**: Strong defensive position, ability to repel counter-attacks
- **Risk**: Defender has time to call for reinforcements
- **Best For**: Strong defenders, contested territory

#### Pattern 3: Coordinated Tribal Conquest

- **Goal**: Conquer a heavily defended village with tribe support
- **Method**: Multiple tribe members send clearing waves, one member sends Chieftains
- **Requirements**: Tribe coordination, multiple attack timings
- **Risk**: Requires trust and coordination; can be disrupted
- **Best For**: High-value targets, enemy tribe members

#### Pattern 4: Fake Conquest (Psychological Warfare)

- **Goal**: Force defender to waste resources on defense
- **Method**: Send fake Chieftain attacks (without Chieftain) to simulate conquest attempts
- **Requirements**: Ability to mimic real attack patterns
- **Risk**: Defender may call bluff and ignore
- **Best For**: Distracting defenders, creating openings elsewhere

### Defending Against Conquest

Defenders have multiple options to prevent conquest:

#### Active Defense

- **Troop Reinforcement**: Station large defensive armies in the village
- **Counter-Attacks**: Attack the aggressor's villages to force troop recalls
- **Chieftain Sniping**: Time defensive troops to kill attacking Chieftains
- **Wall Rebuilding**: Continuously repair walls between attacks

#### Passive Defense

- **Loyalty Boosting**: Build Palace, temples, and cultural buildings for faster recovery
- **Hero Governor**: Station hero in village for loyalty bonus
- **Resource Denial**: Spend or hide resources so attacker gains nothing
- **Tribe Support**: Request defensive reinforcements from tribe members

#### Strategic Defense

- **Fake Troops**: Show large defensive numbers to deter attacks
- **Evacuation**: Move valuable troops and resources to other villages
- **Scorched Earth**: Demolish buildings before conquest to reduce village value
- **Diplomatic Negotiation**: Offer tribute or alliance to avoid conquest

---

## Post-Capture Rules

When a village is successfully conquered, several changes occur immediately:

### Ownership Transfer

- **Immediate**: Village ownership transfers to the conquering player
- **Loyalty Reset**: Village loyalty is set to **25** (low but stable)
- **Conquest Immunity**: Village cannot be conquered again for **7 days**

### Buildings

Multiple variants can be implemented:

#### Variant A: Partial Destruction (Recommended)

- **Palace/Residence**: Destroyed completely (level 0)
- **Walls**: Reduced to 50% of current level (rounded down)
- **Military Buildings**: Reduced to 75% of current level
- **Resource Buildings**: Reduced to 90% of current level
- **Special Buildings**: Reduced to 80% of current level (Academy, Smithy, etc.)
- **Warehouse/Granary**: Remain at current level

#### Variant B: Minimal Destruction

- Only Palace/Residence destroyed
- All other buildings remain intact
- Encourages conquest of developed villages

#### Variant C: Severe Destruction

- Palace/Residence destroyed
- All buildings reduced to 50% of current level
- Walls destroyed completely
- Represents brutal conquest; discourages conquest of developed villages

#### Variant D: Conditional Destruction

- Destruction level depends on final loyalty (lower loyalty = more destruction)
- If loyalty was 0 for more than 24 hours, additional destruction occurs
- Rewards quick conquest, punishes prolonged sieges

### Units & Troops

#### Defending Troops

- **All defending troops are eliminated** in the final conquest battle
- **Reinforcements from other players** are sent back to their home villages (if they survive)
- **Troops in training** are cancelled; resources are lost

#### Attacking Troops

- **Conquering Chieftain** remains in the village as the new governor (optional rule)
- **Escort troops** can be stationed or sent home at attacker's discretion
- **Troops in transit** to the old owner are automatically recalled

### Resources

#### Variant A: Partial Plunder (Recommended)

- Attacker plunders **50% of available resources** (up to carry capacity)
- Remaining resources stay in the village for new owner
- Encourages conquest for economic gain

#### Variant B: Full Plunder

- Attacker plunders **100% of available resources** (up to carry capacity)
- Village starts with minimal resources
- Represents thorough looting

#### Variant C: No Plunder

- All resources remain in the village
- Attacker gains no immediate economic benefit
- Focuses conquest on territorial expansion only

#### Variant D: Resource Destruction

- 50% of resources are plundered
- 25% of resources are destroyed (lost)
- 25% remain in village
- Represents chaos of conquest

### Village Name & Appearance

- **Village Name**: Remains unchanged by default; new owner can rename immediately
- **Village Flag**: Changes to new owner's flag/banner
- **Map Appearance**: Updates to show new owner's color
- **Village History**: Records conquest in village history log (visible to all players)

### Research & Technology

- **Smithy Upgrades**: Remain at current level; new owner benefits from existing research
- **Academy Research**: Remains intact; new owner gains access to unlocked technologies
- **Tribe Bonuses**: Village immediately benefits from new owner's tribe bonuses (if any)

### Ongoing Effects

- **Troop Training**: Any troops in training are cancelled; resources lost
- **Building Upgrades**: Any buildings under construction continue at current progress
- **Research**: Any ongoing research is cancelled; resources lost
- **Marketplace Trades**: Incoming trades are cancelled and returned to sender
- **Outgoing Attacks**: All outgoing attacks from the village are cancelled and troops return

---

## Cool-downs & Limits

### Conquest Cool-downs

To prevent rapid conquest chains and abuse:

#### Attacker Cool-downs

- **Conquest Cool-down**: Player cannot conquer another village for **24 hours** after a successful conquest
- **Chieftain Training Cool-down**: After a Chieftain dies, the Palace/Residence cannot train a new one for **24 hours**
- **Same Target Cool-down**: Player cannot attempt to re-conquer the same village for **7 days** after losing it

#### Defender Cool-downs

- **Conquest Immunity**: Newly conquered village cannot be conquered again for **7 days**
- **Loyalty Grace Period**: If a village reaches 0 loyalty but is not conquered within 12 hours, loyalty resets to 25
- **Last Village Protection**: A player's last remaining village cannot be conquered (permanent protection)

### Village Limits

Players are limited in the total number of villages they can control:

#### Base Village Limits

| Player Points | Maximum Villages | Notes |
|---------------|------------------|-------|
| 0 - 1,000 | 1 | Starting village only |
| 1,001 - 5,000 | 2 | First conquest available |
| 5,001 - 15,000 | 3 | Early expansion |
| 15,001 - 40,000 | 4 | Mid-game |
| 40,001 - 80,000 | 5 | Advanced player |
| 80,001 - 150,000 | 6 | Late game |
| 150,001+ | 7+ | +1 village per 50,000 points |

#### Palace/Residence Levels

Village limits are also tied to Palace/Residence levels:

- **Residence**: Allows training 1 Chieftain; supports up to 3 villages total
- **Palace**: Allows training 3 Chieftains (one at a time); supports unlimited villages (within point limits)

To conquer a 2nd village: Residence level 10 or Palace level 10
To conquer a 3rd village: Residence level 15 or Palace level 15
To conquer a 4th+ village: Palace level 20+

### Soft Caps & Penalties

To discourage excessive expansion:

#### Loyalty Penalty

- **2-3 villages**: No penalty
- **4-5 villages**: -10% loyalty recovery rate in all villages
- **6-7 villages**: -20% loyalty recovery rate in all villages
- **8+ villages**: -30% loyalty recovery rate in all villages

#### Administrative Overhead

- **2-3 villages**: No penalty
- **4-5 villages**: +5% building costs in all villages
- **6-7 villages**: +10% building costs in all villages
- **8+ villages**: +15% building costs and +10% troop training costs

#### Distance Penalty

- Villages more than **50 tiles** from capital suffer -10% resource production
- Villages more than **100 tiles** from capital suffer -20% resource production
- Encourages regional consolidation rather than scattered expansion

---

## Anti-Abuse Measures

### Protection for Small Players

#### Beginner Protection

- **New players** (under 7 days old or under 1,000 points) cannot be conquered
- **Beginner villages** cannot be attacked by players with 5x their points
- **Beginner protection** is removed if player attacks another player outside protection

#### Point Difference Limits

- Players cannot conquer villages from players with **less than 25% of their points**
- Exception: If the smaller player attacked first, they lose this protection for 7 days
- Exception: If players are in the same tribe, no point restrictions apply

#### Last Village Protection

- A player's **last remaining village** cannot be conquered under any circumstances
- This prevents complete elimination from the game
- Players can still be raided and weakened, but not removed entirely

### Tribe-Internal Transfers

To allow legitimate village transfers within tribes while preventing abuse:

#### Friendly Conquest Rules

- **Tribe members** can conquer each other's villages with **mutual consent**
- **Consent Mechanism**: Defender sets village to "Transfer Mode" in Palace
- **Transfer Mode Effects**:
  - Village starts at 0 loyalty (no Chieftain attacks needed)
  - No troops required to defend
  - Buildings are not destroyed
  - Resources are not plundered
  - No conquest cool-down applied
  - Transfer must complete within 24 hours or mode expires

#### Transfer Restrictions

- Both players must be in the same tribe for **at least 7 days**
- Transferring player must have at least 2 villages (cannot transfer last village)
- Receiving player must have available village slots
- Village cannot be transferred again for **14 days**
- Maximum **1 transfer per player per week**

### Multi-Account Abuse Prevention

#### Account Linking Detection

- **IP Tracking**: Accounts from the same IP are flagged for review
- **Behavioral Analysis**: Accounts that only interact with each other are flagged
- **Resource Flow**: Unusual one-way resource transfers trigger alerts
- **Conquest Patterns**: Accounts that repeatedly conquer each other are flagged

#### Automated Restrictions

- **Flagged accounts** cannot conquer each other
- **Flagged accounts** have reduced resource transfer limits
- **Persistent flags** result in manual admin review

#### Reporting System

- Players can report suspected multi-accounting
- Tribe leaders can report suspicious internal transfers
- Admins can review conquest history and resource flows

#### Penalties

- **First Offense**: Warning and temporary conquest ban (7 days)
- **Second Offense**: Village confiscation and point reduction
- **Third Offense**: Permanent account ban

---

## Advanced & Optional Features

### Vassalage System

A diplomatic alternative to full conquest:

#### Vassal Mechanics

- Instead of conquering, a player can offer **vassalage** to a defeated opponent
- **Vassal Benefits**: Keeps village ownership, reduced tribute, military protection
- **Overlord Benefits**: Receives 20% of vassal's resource production, can call vassal troops for defense
- **Vassal Obligations**: Must send tribute, cannot attack overlord, must provide military support when requested

#### Establishing Vassalage

- Attacker reduces village loyalty to **25 or below**
- Attacker sends **Vassalage Offer** instead of final conquest
- Defender can **accept** (becomes vassal) or **refuse** (conquest continues)
- Vassalage lasts **30 days** or until broken by either party

#### Breaking Vassalage

- **Vassal Revolt**: Vassal can revolt after 7 days; overlord can attempt to re-conquer
- **Overlord Release**: Overlord can release vassal at any time
- **Conquest by Third Party**: If vassal is conquered by another player, vassalage ends

### Shared Control & Co-Ownership

Allows multiple players to jointly control a village:

#### Co-Ownership Mechanics

- **Primary Owner**: Has full control; can add/remove co-owners
- **Co-Owners**: Can train troops, build, and send attacks from the village
- **Resource Sharing**: All owners contribute to and benefit from village resources
- **Loyalty Sharing**: All owners contribute to loyalty recovery

#### Use Cases

- **Tribe Fortresses**: Strategic villages controlled by multiple tribe leaders
- **Border Defenses**: Shared defensive positions on tribe borders
- **Economic Hubs**: Joint resource production centers

#### Restrictions

- Maximum **3 co-owners** per village
- Co-owners must be in the same tribe
- Primary owner can revoke co-ownership at any time
- If primary owner loses the village, all co-ownership ends

### Temporary Occupation vs Permanent Conquest

Introduces a distinction between temporary military occupation and permanent territorial conquest:

#### Occupation Mechanics

- **Occupation**: Village loyalty reduced to 0, but not conquered
- **Occupier Benefits**: Plunders resources, prevents troop training, reduces production
- **Occupier Costs**: Must maintain garrison; village can be liberated
- **Duration**: Occupation lasts until garrison is defeated or withdrawn

#### Occupation vs Conquest

| Aspect | Occupation | Conquest |
|--------|-----------|----------|
| Ownership | Remains with original owner | Transfers to conqueror |
| Duration | Temporary (until liberated) | Permanent (until re-conquered) |
| Resource Benefit | Continuous plunder | One-time plunder + production |
| Military Requirement | Garrison must remain | No garrison required |
| Building Control | No building/training | Full building/training |
| Loyalty | Frozen at 0 | Resets to 25 |

#### Strategic Use

- **Occupation**: Deny enemy resources without committing to full conquest
- **Conquest**: Permanent territorial expansion
- **Hybrid Strategy**: Occupy multiple villages, conquer the most valuable

### Revolts & Uprisings

Adds dynamic challenges to maintaining conquered territory:

#### Revolt Triggers

Villages can revolt if:

- **Loyalty drops below 10** for more than 48 hours
- **Owner is inactive** for more than 7 days
- **Village is far from capital** (100+ tiles) and under-garrisoned
- **Random Event**: 1% chance per day if loyalty is below 50

#### Revolt Mechanics

When a revolt occurs:

- **Rebel Army Spawns**: NPC army appears in the village (strength based on village size)
- **Loyalty Frozen**: Loyalty cannot recover during revolt
- **Production Halted**: No resource production or troop training
- **Owner Must Suppress**: Owner must defeat rebel army to regain control

#### Rebel Army Composition

- **Small Village** (under 1,000 points): 500 infantry, 200 archers
- **Medium Village** (1,000-5,000 points): 1,500 infantry, 500 archers, 200 cavalry
- **Large Village** (5,000+ points): 3,000 infantry, 1,000 archers, 500 cavalry, 100 siege

#### Revolt Outcomes

- **Suppressed**: Owner defeats rebels; loyalty resets to 50; village returns to normal
- **Failed Suppression**: Rebels win; village becomes independent (neutral NPC village that can be re-conquered)
- **Third-Party Conquest**: Another player conquers the revolting village during the chaos

#### Preventing Revolts

- Maintain loyalty above 25
- Station garrison troops in distant villages
- Build loyalty-boosting buildings (Palace, temples)
- Assign hero governors to high-risk villages
- Stay active (login regularly)

### Cultural Conversion

A long-term alternative to military conquest:

#### Cultural Influence

- Villages can be **culturally converted** through sustained non-military pressure
- **Cultural Buildings**: Temples, monuments, theaters spread influence to nearby villages
- **Influence Radius**: 10 tiles for basic buildings, 20 tiles for advanced
- **Conversion Rate**: 1 loyalty per day per influence source

#### Conversion Mechanics

- Target village must be within influence radius for **30 consecutive days**
- Target village loyalty gradually decreases (1 point per day per influence source)
- When loyalty reaches 0 through cultural influence, village **peacefully joins** the influencer
- No military action required; no building destruction; no cool-downs

#### Defending Against Cultural Conversion

- Build own cultural buildings to counter influence
- Increase loyalty recovery rate
- Attack influencer's cultural buildings
- Move capital closer to threatened villages

#### Strategic Use

- **Long-term Expansion**: Slowly expand influence over border regions
- **Peaceful Growth**: Gain villages without military conflict
- **Defensive Buffer**: Create cultural barriers against enemy expansion

---

## Implementation Variants & Server Options

Different server types can implement conquest differently:

### Speed Server Variants

- **Faster Loyalty Reduction**: Chieftains reduce 40-50 loyalty per attack
- **Shorter Cool-downs**: 12-hour conquest cool-down instead of 24
- **Reduced Immunity**: 3-day conquest immunity instead of 7
- **Higher Village Limits**: Players can control more villages earlier

### Casual Server Variants

- **Slower Loyalty Reduction**: Chieftains reduce 10-15 loyalty per attack
- **Longer Cool-downs**: 48-hour conquest cool-down
- **Extended Immunity**: 14-day conquest immunity
- **Stronger Beginner Protection**: Protection lasts 14 days or 5,000 points

### Hardcore Server Variants

- **No Last Village Protection**: Players can be completely eliminated
- **No Conquest Immunity**: Villages can be immediately re-conquered
- **No Point Restrictions**: Any player can conquer any other player
- **Permanent Destruction**: Conquered villages have severe building destruction

### Diplomatic Server Variants

- **Vassalage Encouraged**: Reduced tribute rates, stronger vassal protections
- **Cultural Conversion Available**: Non-military expansion option
- **Shared Control Enabled**: Tribe fortresses and co-ownership
- **Peaceful Transfers**: Easy tribe-internal village transfers

---

## Summary & Design Philosophy

Village conquest is designed to be:

1. **Challenging**: Requires sustained effort, resources, and coordination
2. **Strategic**: Multiple approaches (blitz, siege, cultural) with different trade-offs
3. **Risky**: Chieftains are expensive and vulnerable; conquest can fail
4. **Rewarding**: Successful conquest provides significant territorial and economic benefits
5. **Balanced**: Anti-abuse measures prevent exploitation while allowing legitimate gameplay
6. **Dynamic**: Revolts, loyalty, and cultural influence create ongoing challenges
7. **Flexible**: Multiple variants allow servers to tailor conquest to their community

The conquest system should feel like a major achievement, not a routine action. Each conquered village represents a significant investment of time, resources, and strategic planning, making territorial expansion a meaningful progression path for players.
# Village Conquest & Control

## Overview

This document defines the complete village conquest and control systems for our medieval tribal war browser MMO. Village conquest represents the ultimate form of territorial expansion, allowing players to permanently claim enemy villages and expand their empire. The system is designed to be strategic, requiring multiple coordinated attacks, significant investment, and careful planning while preventing abuse and maintaining game balance.

---

## Conquest Overview

### Core Concept

Village conquest is the process by which one player can permanently take control of another player's village. Unlike simple raiding (which steals resources), conquest transfers complete ownership and control of the village, including all buildings, production capabilities, and strategic position.

### Ownership Transfer Mechanics

**Traditional Conquest:**
- Attacker must reduce village loyalty to 0%
- Requires specialized conquest units (Nobles/Chieftains)
- Multiple attacks typically needed (3-5 on average)
- Defender can rebuild loyalty between attacks
- Final conquest occurs when loyalty reaches 0% and conquest unit survives

**Influence-Based Conquest:**
- Attacker builds influence through repeated successful attacks
- Each victory increases attacker influence (10-25% per attack)
- Defender influence decreases with each loss
- Conquest occurs when attacker influence exceeds 100%
- Defender can reduce attacker influence through successful defenses

**Hybrid System (Recommended):**
- Village has loyalty (100% at start)
- Conquest units reduce loyalty (20-35% per successful attack)
- Loyalty regenerates slowly over time (5-10% per day)
- Buildings and defenses can increase regeneration rate
- Conquest occurs at 0% loyalty with conquest unit present
- Failed conquest attempts still reduce loyalty partially (5-10%)

### Loyalty System Details

**Base Loyalty:**
- New villages: 100% loyalty
- Recently conquered: 50% loyalty (vulnerable to reconquest)
- Established villages (30+ days): 100% loyalty + bonuses
- Capital village: 150% loyalty (harder to conquer)


**Loyalty Modifiers:**
- Palace/Headquarters level: +1% per level (max +30%)
- Wall level: +0.5% per level (max +15%)
- Garrison size: +0.1% per 10 units (max +20%)
- Population happiness: +0-25% based on morale
- Recent attacks: -5% per attack in last 24 hours
- Tribe support: +10% if tribe members nearby
- Distance from capital: -1% per 10 tiles (max -20%)

**Loyalty Regeneration:**
- Base rate: 5% per day
- Palace bonus: +1% per day per 5 levels
- Tribe loyalty building: +5% per day
- Active defense: +10% per successful defense
- Governor presence: +3% per day (special unit)
- Rituals/blessings: +20% instant or +10% per day temporary

**Loyalty Decay:**
- Conquest unit attack: -20% to -35% per successful attack
- Failed defense: -5% per loss
- Siege damage: -1% per 10% building damage
- Starvation: -10% per day if food negative
- Plague/sabotage: -15% per day (temporary)
- Abandoned (offline 7+ days): -5% per day

---

## Special Conquest Unit: The Noble/Chieftain

### Lore & Background

**The Noble:**
In the feudal hierarchy of our medieval world, Nobles are the aristocratic elite who command respect and loyalty through birthright, wealth, and political power. When a Noble enters a conquered village, the population recognizes their authority and transfers allegiance. Nobles are rare, expensive to train, and represent the pinnacle of political power.

**Alternative: The Chieftain:**
For tribal-themed servers, Chieftains are legendary warriors who have proven themselves in countless battles. Their reputation and charisma inspire loyalty, and their presence in a village causes the population to accept new leadership. Chieftains are revered figures who unite tribes under their banner.

### Unit Statistics

**Noble/Chieftain Base Stats:**
- **Attack Power**: 30 (weak in direct combat)
- **Defense**: 100 (heavily armored/protected)
- **Speed**: 4 tiles/minute (slow, travels with heavy escort)
- **Carry Capacity**: 0 (cannot loot resources)
- **Population**: 100 (requires massive support)
- **Loyalty Reduction**: 25% per successful attack (base)

### Training Requirements

**Building Prerequisites:**
- Academy: Level 20 (max level)
- Palace/Headquarters: Level 20 (max level)
- Smithy: Level 15
- Market: Level 10
- Special: "Noble Estate" or "Chieftain's Hall" (unique building)


**Resource Costs:**
- Wood: 50,000
- Clay: 50,000
- Iron: 50,000
- Stone: 25,000
- Gold: 100,000
- Gems: 10 (rare resource)
- Ancient Scrolls: 5
- Total value: ~500,000 resource equivalent

**Training Time:**
- Base: 48 hours (2 days)
- Reduced by Academy level: -1 hour per level (min 24 hours)
- Premium acceleration: Can reduce to 12 hours with gold
- Queue limit: 1 Noble training at a time per village

**Training Limits:**
- Maximum 1 Noble per village at any time
- Maximum 3 Nobles total per player (soft cap)
- Maximum 5 Nobles total per player (hard cap, requires achievements)
- Tribe limit: No more than 20% of tribe can train Nobles simultaneously

### Conquest Mechanics

**Using the Noble:**
1. Train Noble in village with required buildings
2. Assemble escort army (Noble is vulnerable alone)
3. Send attack with Noble included in army
4. Noble must survive battle to reduce loyalty
5. If Noble survives and wins, loyalty reduced by 25%
6. If Noble dies, massive loss (must train new one)
7. Repeat until loyalty reaches 0%
8. Final attack with Noble claims village

**Loyalty Reduction Formula:**
```
Base Reduction: 25%
+ Noble Level Bonus: +0-10% (if Noble leveling system exists)
+ Technology Bonus: +0-10% (Diplomacy/Politics research)
+ Tribe Bonus: +0-5% (Tribe research)
- Defender Palace Bonus: -0-15% (Palace level resistance)
- Defender Wall Bonus: -0-10% (Wall level resistance)
= Final Reduction: 15-50% per attack
```

**Noble Survival:**
- Noble has high defense (100) but low attack (30)
- Requires strong escort to survive battle
- If battle is lost, Noble has 50% chance to escape
- If battle is won, Noble survives automatically
- Defender can specifically target Nobles (focus fire)
- Special defensive units: "Noble Hunters" (bonus vs Nobles)

### Risks & Vulnerabilities

**High Cost:**
- 500k+ resource investment per Noble
- Loss of Noble = complete resource loss
- No resource recovery if Noble dies
- Limits aggressive conquest attempts

