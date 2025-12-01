# Resource & Economy Design

## Overview

This document defines the complete resource and economic systems for our medieval tribal war browser MMO. The economy is designed to support multiple play styles, create meaningful strategic choices, and maintain long-term engagement through balanced resource flows, sinks, and anti-inflation mechanics.

---

## Resource Types

### Core Resources

#### 1. **Wood** 🌲
- **Theme**: Basic construction material, abundant in forests
- **Primary Uses**: Building construction, defensive structures, basic units
- **Generation Rate**: Fast (renewable)
- **Storage**: Medium capacity
- **Pros**: 
  - Easy to obtain for new players
  - Required for early-game expansion
  - Abundant on map
- **Cons**:
  - Less valuable in late game
  - Takes significant storage space
  - Low trade value

#### 2. **Clay** 🧱
- **Theme**: Defensive material, pottery, bricks
- **Primary Uses**: Defensive buildings, walls, siege equipment
- **Generation Rate**: Medium (renewable)
- **Storage**: Medium capacity
- **Pros**:
  - Essential for defense
  - Maintains value throughout game
  - Good for defensive players
- **Cons**:
  - Slower production than wood
  - Heavy (transport costs)
  - Limited offensive applications

#### 3. **Iron** ⚔️
- **Theme**: Weapons, armor, advanced tools
- **Primary Uses**: Military units, weapons, advanced buildings
- **Generation Rate**: Slow (limited deposits)
- **Storage**: High value per unit
- **Pros**:
  - High trade value
  - Essential for military power
  - Required for advanced tech
- **Cons**:
  - Scarce resource
  - Requires advanced buildings
  - Highly contested on map

#### 4. **Food** 🌾
- **Theme**: Population sustenance, army supplies
- **Primary Uses**: Army upkeep, population growth, trading caravans
- **Generation Rate**: Fast but consumed continuously
- **Storage**: Large capacity needed
- **Pros**:
  - Enables large armies
  - Supports population growth
  - Always in demand
- **Cons**:
  - Constant drain (upkeep)
  - Spoils over time (decay mechanic)
  - Cannot stockpile indefinitely

#### 5. **Gold** 💰
- **Theme**: Currency, luxury, prestige
- **Primary Uses**: Instant upgrades, premium trades, mercenaries, diplomacy
- **Generation Rate**: Very slow (special sources)
- **Storage**: Unlimited (abstract currency)
- **Pros**:
  - Universal trade medium
  - Enables instant actions
  - No decay
- **Cons**:
  - Extremely scarce
  - Highly targeted by raiders
  - Can create pay-to-win concerns

### Advanced Resources

#### 6. **Stone** 🗿
- **Theme**: Monumental construction, fortifications
- **Primary Uses**: Castles, monuments, wonder buildings, advanced walls
- **Generation**: Quarries (limited map locations), mountain villages
- **Unlock**: Mid-game technology
- **Special Properties**: Required for prestige buildings, tribe wonders

#### 7. **Horses** 🐴
- **Theme**: Mobility, cavalry, trade caravans
- **Primary Uses**: Cavalry units, faster trade routes, scout units
- **Generation**: Stables, capturing wild herds, breeding
- **Unlock**: Early-mid game
- **Special Properties**: Living resource (breeding mechanics), enables fast units

### Rare Resources

#### 8. **Gems/Crystals** 💎
- **Theme**: Mystical power, ancient artifacts
- **Primary Uses**: Legendary units, powerful rituals, tribe relics
- **Generation**: Rare map nodes, defeating legendary barbarians, events
- **Rarity**: Very limited supply
- **Special Properties**: Non-renewable, prestige value, enables unique abilities

#### 9. **Ancient Scrolls** 📜
- **Theme**: Knowledge, research acceleration
- **Primary Uses**: Instant research completion, unlock secret techs
- **Generation**: Ruins exploration, quest rewards, defeating scholars
- **Rarity**: Limited per server
- **Special Properties**: One-time use, tradeable, highly valuable

### Event-Only Resources

#### 10. **Festival Tokens** 🎭
- **Theme**: Seasonal celebrations
- **Primary Uses**: Cosmetic items, temporary buffs, special units
- **Generation**: Seasonal events only
- **Duration**: Event-specific, may expire
- **Special Properties**: Cannot be raided, account-bound options

#### 11. **War Spoils** ⚡
- **Theme**: Conquest rewards
- **Primary Uses**: Prestige items, tribe bonuses, leaderboard rewards
- **Generation**: Server-wide conflicts, territory control
- **Duration**: Season-based
- **Special Properties**: Tribe-shared benefits, competitive rewards

#### 12. **Relics** 🏺
- **Theme**: Ancient artifacts of power
- **Primary Uses**: Permanent village bonuses, collection achievements
- **Generation**: World boss events, archaeology expeditions
- **Rarity**: Extremely limited
- **Special Properties**: Unique effects, displayable, tradeable at high cost

---

## Resource Generation

### Building-Based Production

#### Passive Production Buildings

**Wood Production:**
- **Lumber Camp** (Level 1-20): Base production 30-500/hour
- **Forestry** (Level 1-15): Enhanced production 50-800/hour, requires forest tiles
- **Sawmill** (Level 1-10): +20% efficiency to all wood production
- **Ancient Grove** (Special): Rare building, +50% wood, requires quest completion

**Clay Production:**
- **Clay Pit** (Level 1-20): Base production 25-450/hour
- **Brick Kiln** (Level 1-15): Enhanced production 40-700/hour
- **Potter's Workshop** (Level 1-10): +15% efficiency, enables clay trading

**Iron Production:**
- **Iron Mine** (Level 1-20): Base production 15-300/hour
- **Smeltery** (Level 1-15): Enhanced production 25-500/hour
- **Blacksmith** (Level 1-10): +25% efficiency, enables weapon crafting
- **Deep Mine** (Special): Requires mountain terrain, +100% iron

**Food Production:**
- **Farm** (Level 1-25): Base production 50-1000/hour
- **Granary** (Level 1-20): Storage + 5% production bonus per level
- **Fishing Hut** (Level 1-15): Requires water, 40-600/hour
- **Hunting Lodge** (Level 1-12): Requires forest, 30-400/hour
- **Windmill** (Level 1-10): +30% to all food production

**Gold Production:**
- **Market** (Level 1-15): Passive gold from trade taxes, 5-100/day
- **Treasury** (Level 1-10): +10% gold generation, secure storage
- **Merchant Guild** (Special): Requires tribe membership, +50% gold from trades
- **Tax Office** (Level 1-8): Gold from population, 10-150/day

**Advanced Resource Production:**
- **Quarry** (Stone): Level 1-15, 10-200/hour, requires stone deposit
- **Stable** (Horses): Level 1-12, 2-20/hour, requires pasture land
- **Gem Mine** (Gems): Special building, 0.1-1/hour, extremely rare locations
- **Scriptorium** (Scrolls): Level 1-5, 0.5-3/week, requires library

#### Production Modifiers

**Terrain Bonuses:**
- Forest tiles: +20% wood production
- Mountain tiles: +30% iron, +50% stone
- River/lake tiles: +25% food (fishing)
- Plains tiles: +15% food (farming)
- Hills: +10% clay

**Technology Bonuses:**
- Improved Tools: +10% all production
- Advanced Metallurgy: +25% iron
- Crop Rotation: +20% food
- Forestry Management: +15% wood
- Mining Techniques: +20% iron and stone

**Tribe Bonuses:**
- Tribe Research: +5-15% specific resources
- Tribe Buildings: Shared production facilities
- Territory Control: +10% in controlled regions
- Alliance Pacts: Trade route bonuses +5-20%


### Farming Barbarian & Neutral Villages

#### Barbarian Village Types

**Small Barbarian Camp** (Level 1-3):
- Loot: 200-500 each resource
- Respawn: 4 hours
- Difficulty: Easy
- Special: May drop horses (5% chance)

**Medium Barbarian Village** (Level 4-6):
- Loot: 500-1500 each resource
- Respawn: 8 hours
- Difficulty: Medium
- Special: May drop ancient scrolls (2% chance)

**Large Barbarian Fortress** (Level 7-10):
- Loot: 1500-5000 each resource
- Respawn: 24 hours
- Difficulty: Hard
- Special: Guaranteed rare resource drop, possible relic (0.5%)

**Legendary Barbarian Stronghold** (Level 11+):
- Loot: 5000-20000 each resource
- Respawn: 72 hours
- Difficulty: Very Hard (requires coordinated attack)
- Special: Gems, relics, legendary unit blueprints

#### Neutral Villages

**Abandoned Villages:**
- Can be captured or farmed
- Loot: 1000-3000 mixed resources
- Respawn: 12 hours if not captured
- Risk: Other players may contest

**Trading Outposts:**
- Peaceful farming (no combat)
- Loot: 500-1000 gold, trade goods
- Respawn: 6 hours
- Bonus: Improves trade route efficiency

**Resource Villages:**
- Specialized in one resource type
- Loot: 3000-8000 of specific resource
- Respawn: 16 hours
- Strategy: Target based on needs

**Oasis/Special Terrain:**
- Bonus resources based on terrain
- Loot: Variable, includes rare resources
- Respawn: 24-48 hours
- Competition: Highly contested

### PvP Raiding

#### Raid Mechanics

**Successful Raid Rewards:**
- Carry capacity based on unit types
- Light cavalry: 100 resources per unit
- Heavy cavalry: 150 resources per unit
- Wagons: 500 resources per unit
- Siege units: 50 resources per unit (combat-focused)


**Raid Efficiency Factors:**
- Distance: Longer raids = more food consumption
- Defenses: Strong walls reduce loot by 10-50%
- Warehouse level: Protects 100-5000 of each resource
- Online defenders: +20% defense bonus
- Tribe support: Reinforcements can arrive

**Raid Types:**
- **Quick Raid**: Fast units, low capacity, hit-and-run
- **Heavy Raid**: Slow units, high capacity, vulnerable to counter
- **Siege Raid**: Destroy buildings, lower loot priority
- **Scout Raid**: Intelligence gathering, minimal loot
- **Fake Raid**: Deception, no loot, tactical advantage

**Loot Distribution:**
- Attacker chooses resource priority
- Automatic: Fill by highest value
- Balanced: Equal distribution
- Targeted: Specific resource focus

### Quests & Achievements

#### Daily Quests

**Beginner Quests:**
- "Gather 1000 wood": Reward 500 clay, 200 iron
- "Train 10 spearmen": Reward 1000 food, 50 gold
- "Upgrade any building": Reward 500 each resource
- "Farm 3 barbarian camps": Reward 1500 mixed resources, 100 gold

**Advanced Quests:**
- "Raid 5 player villages": Reward 5000 mixed resources, 500 gold, 1 scroll
- "Complete a trade route": Reward 2000 gold, trade bonus
- "Defend successfully": Reward 3000 mixed resources, defensive bonus
- "Research new technology": Reward 1000 gold, 2 scrolls

**Weekly Quests:**
- "Contribute 50k resources to tribe": Reward 10k mixed, 1000 gold, 1 gem
- "Control 3 map objectives": Reward 20k mixed, 2000 gold, rare unit
- "Win 10 battles": Reward 15k mixed, 1500 gold, 3 scrolls
- "Complete 20 trades": Reward 5000 gold, merchant reputation

#### Achievement Rewards

**Economic Achievements:**
- "First Million": Accumulate 1M total resources → 5000 gold, +5% production
- "Master Trader": Complete 100 trades → 10000 gold, market level bonus
- "Resource Baron": Max all production buildings → +10% all production permanently
- "Hoarder": Fill all warehouses → 20000 mixed resources, storage expansion


**Military Achievements:**
- "Conqueror": Capture 10 villages → 50000 mixed, 5000 gold, 2 gems
- "Raider": Loot 1M resources → +10% raid capacity, 10000 gold
- "Defender": Defend 50 attacks → +15% wall strength, 5000 gold
- "Warlord": Win 100 battles → Legendary unit unlock, 10 gems

**Exploration Achievements:**
- "Cartographer": Explore 1000 tiles → 5000 gold, map bonuses
- "Treasure Hunter": Find 10 relics → 20000 gold, 5 gems, special title
- "Barbarian Slayer": Defeat 100 barbarian villages → +20% vs barbarians, 15000 mixed
- "World Explorer": Visit all terrain types → 10000 gold, terrain bonuses

### Events & Seasonal Content

#### Seasonal Events

**Harvest Festival** (Autumn):
- Duration: 2 weeks
- Bonus: +50% food production
- Special quests: Deliver food for festival tokens
- Rewards: Cosmetics, temporary buffs, 2x food storage

**Winter Raids** (Winter):
- Duration: 3 weeks
- Bonus: +30% raid loot
- Special: Frozen barbarian camps (extra loot)
- Rewards: Winter-themed units, gold bonuses

**Spring Awakening** (Spring):
- Duration: 2 weeks
- Bonus: +40% all production
- Special: Rare resource nodes spawn
- Rewards: Gems, scrolls, production boosts

**Summer Conquest** (Summer):
- Duration: 4 weeks
- Bonus: Territory control rewards doubled
- Special: Server-wide faction wars
- Rewards: War spoils, relics, prestige titles

#### Limited-Time Events

**World Boss Events:**
- Frequency: Monthly
- Participation: Server-wide cooperation
- Rewards: Scaled by contribution (top 100 get relics)
- Loot pool: 1M+ mixed resources, 50+ gems, unique items

**Double Resource Weekends:**
- Frequency: Bi-weekly
- Effect: 2x production from all buildings
- Strategy: Optimal time for expansion
- Bonus: Stacks with other bonuses


**Tribe Wars:**
- Frequency: Quarterly
- Duration: 1 month
- Rewards: Based on tribe ranking
  - 1st place: 500k mixed, 50k gold, 50 gems, legendary relic
  - 2nd-5th: 250k mixed, 25k gold, 25 gems
  - 6th-20th: 100k mixed, 10k gold, 10 gems
  - Participation: 10k mixed, 1k gold

**Archaeology Expeditions:**
- Frequency: Random (1-2 per month)
- Duration: 3 days
- Mechanic: Dig sites appear on map
- Rewards: Ancient scrolls, relics, historical cosmetics

### Tribe Bonuses

#### Tribe Research Benefits

**Economic Research Tree:**
- **Shared Prosperity**: +5% production for all members
- **Trade Network**: -10% market fees
- **Resource Pooling**: Tribe warehouse access
- **Bulk Discounts**: -5% building costs
- **Collective Bargaining**: Better NPC trade rates

#### Tribe Buildings

**Tribe Warehouse:**
- Shared storage: 100k-1M capacity
- Contribution tracking
- Emergency withdrawals for defense
- Tribe leader controls

**Tribe Market:**
- Internal trading (no fees)
- Bulk trade orders
- Resource requests
- Priority fulfillment for members

**Tribe Monument:**
- Prestige building
- Requires massive resources (10M+ mixed)
- Provides server-wide bonuses
- Displays tribe achievements

#### Territory Control Bonuses

**Controlled Regions:**
- Small region (10x10): +5% production
- Medium region (20x20): +10% production, +5% raid loot
- Large region (30x30): +15% production, +10% raid loot, special building unlocks
- Capital region: +25% all bonuses, tribe monument location

**Border Forts:**
- Cost: 50k wood, 50k clay, 25k iron, 10k stone
- Benefit: +10% defense in region, spawn point for tribe
- Upkeep: 5k food/day
- Can be contested by other tribes


### Map Objectives

#### Resource Nodes

**Rare Resource Deposits:**
- Gem Mine: 1 gem/hour, highly contested
- Ancient Quarry: 100 stone/hour, requires control
- Gold Vein: 50 gold/hour, attracts raiders
- Horse Pasture: 5 horses/hour, enables cavalry focus

**Control Mechanics:**
- Capture: Defeat guardian, hold for 24 hours
- Maintenance: Must defend against challengers
- Sharing: Tribe members get 50% benefit
- Loss: Lose all benefits if captured

#### Strategic Locations

**Trade Hubs:**
- Crossroads: -20% trade travel time
- Port Cities: +30% trade capacity
- Mountain Passes: Control = toll collection (10% of passing trades)
- River Fords: +15% food production in region

**Wonder Locations:**
- Ancient Temple: +10% research speed (server-wide for controller)
- Great Library: +5 scroll generation/week
- Colosseum: +20% unit training speed
- Marketplace of Nations: +50% trade volume

### Trading System

#### Player-to-Player Trading

**Market Offers:**
- Post offers: Specify resource, amount, exchange rate
- Browse offers: Filter by resource, rate, distance
- Accept trades: Instant or caravan delivery
- Reputation: Rating system for reliable traders

**Trade Mechanics:**
- Market fee: 5% (reduced to 2% for premium, 0% for tribe)
- Distance cost: Food consumption for caravans
- Travel time: 1 minute per tile (faster with horses)
- Capacity: Based on market level and caravan size
- Security: Can be raided en route (risk vs reward)

**Fair Trade Rules:**
- Maximum exchange rate: 1:3 ratio (prevents exploitation)
- Minimum trade value: 100 resources
- Maximum trade value: 100k resources (or market level × 10k)
- Cooldown: 5 minutes between trades with same player
- Anti-bot: CAPTCHA for trades over 10k value


#### Premium vs Non-Premium Trading

**Free Players:**
- 10 active trade offers maximum
- 5% market fee
- Standard caravan speed
- 3 trades per day with same player
- Cannot trade gems or relics

**Premium Players:**
- 50 active trade offers maximum
- 2% market fee
- +50% caravan speed
- Unlimited trades with same player
- Can trade all resources including gems/relics
- Priority in trade queue
- Trade history and analytics

**Tribe Trading (All Players):**
- Unlimited internal trades
- 0% fees
- Instant delivery within tribe territory
- Shared warehouse access
- Trade requests and fulfillment system

#### Caravan Mechanics

**Caravan Types:**
- **Small Caravan**: 5k capacity, fast (2 tiles/min), cheap (100 food)
- **Medium Caravan**: 20k capacity, medium (1 tile/min), moderate (500 food)
- **Large Caravan**: 100k capacity, slow (0.5 tiles/min), expensive (2000 food)
- **Merchant Convoy**: 50k capacity, fast (3 tiles/min), very expensive (5000 food + 100 gold)

**Caravan Protection:**
- Unescorted: Vulnerable to raids (50% chance if spotted)
- Light escort: 10 cavalry, 25% raid chance
- Heavy escort: 50 mixed units, 10% raid chance
- Tribe escort: Tribe members can join, 5% raid chance
- Safe routes: Controlled territory, 0% raid chance

**Caravan Raiding:**
- Scout caravans: Requires scout units
- Intercept: Calculate arrival time, position raiders
- Loot: 50-100% of caravan contents
- Reputation: Raiding caravans reduces trade reputation
- Consequences: Trade bans, tribe wars, bounties

#### NPC Trading

**Merchant NPCs:**
- Fixed exchange rates (worse than player trades)
- Always available (no waiting)
- Unlimited capacity
- No raid risk
- Rates: 1:2 for basic resources, 1:5 for advanced

**Black Market:**
- Better rates than merchants (1:1.5)
- Limited stock (refreshes daily)
- Requires reputation or gold
- Can buy rare resources (expensive)
- Risky: 10% chance of "bad deal" (lose resources)


**Seasonal Merchants:**
- Event-specific traders
- Unique items and resources
- Festival tokens accepted
- Limited-time offers
- Cosmetic and functional items

---

## Resource Sinks

### Building Costs

#### Basic Buildings (Level 1 → Level 20)

**Headquarters:**
- Level 1: 500 wood, 400 clay, 200 iron
- Level 10: 15k wood, 12k clay, 8k iron, 2k stone
- Level 20: 150k wood, 120k clay, 80k iron, 30k stone, 10k gold
- Enables: Village expansion, building unlocks

**Barracks:**
- Level 1: 400 wood, 300 clay, 100 iron
- Level 10: 12k wood, 9k clay, 6k iron
- Level 20: 100k wood, 80k clay, 50k iron, 5k gold
- Enables: Unit training speed, queue size

**Warehouse:**
- Level 1: 600 wood, 400 clay
- Level 10: 20k wood, 15k clay, 5k iron
- Level 20: 180k wood, 140k clay, 60k iron, 20k stone
- Benefit: Storage capacity 1k → 100k per resource

**Wall:**
- Level 1: 300 wood, 500 clay
- Level 10: 10k wood, 20k clay, 8k iron
- Level 20: 80k wood, 150k clay, 100k iron, 50k stone
- Benefit: Defense bonus 5% → 100%

**Market:**
- Level 1: 800 wood, 600 clay, 400 iron, 100 gold
- Level 10: 25k wood, 20k clay, 15k iron, 5k gold
- Level 20: 200k wood, 180k clay, 150k iron, 50k stone, 20k gold
- Benefit: Trade capacity, merchant slots

#### Advanced Buildings

**Academy:**
- Level 1: 5k wood, 4k clay, 3k iron, 1k gold
- Level 10: 80k wood, 70k clay, 60k iron, 20k stone, 10k gold
- Benefit: Research speed, tech unlocks

**Smithy:**
- Level 1: 3k wood, 2k clay, 2k iron
- Level 10: 50k wood, 40k clay, 50k iron, 10k stone
- Benefit: Unit upgrades, weapon quality


**Stable:**
- Level 1: 4k wood, 3k clay, 2k iron, 10 horses
- Level 10: 60k wood, 50k clay, 40k iron, 15k stone, 100 horses
- Benefit: Cavalry training, horse breeding

**Workshop:**
- Level 1: 5k wood, 4k clay, 4k iron, 1k stone
- Level 10: 70k wood, 60k clay, 70k iron, 30k stone, 5k gold
- Benefit: Siege weapons, special units

**Temple/Shrine:**
- Level 1: 6k wood, 5k clay, 3k iron, 2k stone, 2k gold
- Level 10: 100k wood, 90k clay, 60k iron, 50k stone, 20k gold, 5 gems
- Benefit: Rituals, blessings, morale bonuses

#### Prestige Buildings

**Castle:**
- Cost: 500k wood, 400k clay, 300k iron, 200k stone, 50k gold
- Benefit: +50% defense, prestige, noble units
- Requirement: Level 20 HQ, controlled territory

**Wonder:**
- Cost: 2M wood, 1.5M clay, 1M iron, 500k stone, 100k gold, 50 gems
- Benefit: Server-wide bonuses, tribe prestige, leaderboard
- Requirement: Tribe project, specific location

**Monument:**
- Cost: 1M wood, 800k clay, 600k iron, 400k stone, 50k gold, 20 gems
- Benefit: Permanent production bonus, display achievements
- Requirement: Level 20 HQ, 100 achievements

### Unit Costs

#### Infantry Units

**Spearman:**
- Cost: 50 wood, 30 clay, 10 iron, 1 food/hour upkeep
- Training time: 5 minutes
- Role: Basic defense

**Swordsman:**
- Cost: 30 wood, 20 clay, 40 iron, 2 food/hour upkeep
- Training time: 10 minutes
- Role: Offensive infantry

**Axeman:**
- Cost: 60 wood, 20 clay, 30 iron, 2 food/hour upkeep
- Training time: 8 minutes
- Role: Balanced infantry

**Pikeman:**
- Cost: 40 wood, 30 clay, 50 iron, 3 food/hour upkeep
- Training time: 15 minutes
- Role: Anti-cavalry


#### Cavalry Units

**Scout:**
- Cost: 50 wood, 20 clay, 20 iron, 2 horses, 3 food/hour upkeep
- Training time: 12 minutes
- Role: Reconnaissance, speed

**Light Cavalry:**
- Cost: 80 wood, 40 clay, 50 iron, 5 horses, 5 food/hour upkeep
- Training time: 20 minutes
- Role: Raiding, mobility

**Heavy Cavalry:**
- Cost: 100 wood, 60 clay, 100 iron, 8 horses, 8 food/hour upkeep
- Training time: 30 minutes
- Role: Shock troops, high damage

**Mounted Archer:**
- Cost: 120 wood, 30 clay, 60 iron, 6 horses, 6 food/hour upkeep
- Training time: 25 minutes
- Role: Ranged cavalry, harassment

#### Siege Units

**Battering Ram:**
- Cost: 300 wood, 200 clay, 100 iron, 10 food/hour upkeep
- Training time: 1 hour
- Role: Wall destruction

**Catapult:**
- Cost: 400 wood, 300 clay, 200 iron, 50 stone, 15 food/hour upkeep
- Training time: 2 hours
- Role: Building destruction

**Trebuchet:**
- Cost: 600 wood, 400 clay, 300 iron, 100 stone, 20 food/hour upkeep
- Training time: 3 hours
- Role: Heavy siege, long range

**Siege Tower:**
- Cost: 500 wood, 300 clay, 150 iron, 80 stone, 12 food/hour upkeep
- Training time: 2.5 hours
- Role: Wall bypass, troop transport

#### Special Units

**Berserker:**
- Cost: 200 wood, 100 clay, 150 iron, 50 gold, 10 food/hour upkeep
- Training time: 1 hour
- Role: Elite infantry, high damage
- Requirement: Special building or quest

**Paladin:**
- Cost: 300 wood, 200 clay, 250 iron, 15 horses, 100 gold, 1 gem, 20 food/hour upkeep
- Training time: 4 hours
- Role: Hero unit, leadership bonuses
- Requirement: Castle, noble status


**War Elephant:**
- Cost: 1k wood, 800 clay, 600 iron, 500 gold, 5 gems, 50 food/hour upkeep
- Training time: 8 hours
- Role: Legendary unit, massive power
- Requirement: Special event or relic

**Assassin:**
- Cost: 100 wood, 50 clay, 200 iron, 200 gold, 15 food/hour upkeep
- Training time: 2 hours
- Role: Sabotage, espionage
- Requirement: Special building

### Technology Research

#### Economic Technologies

**Improved Tools:**
- Cost: 2k wood, 1k clay, 1k iron, 500 gold
- Time: 6 hours
- Effect: +10% all production

**Advanced Metallurgy:**
- Cost: 5k wood, 4k clay, 8k iron, 2k gold
- Time: 12 hours
- Effect: +25% iron production
- Requirement: Improved Tools

**Crop Rotation:**
- Cost: 4k wood, 3k clay, 2k iron, 1k gold
- Time: 8 hours
- Effect: +20% food production

**Forestry Management:**
- Cost: 6k wood, 2k clay, 1k iron, 1k gold
- Time: 10 hours
- Effect: +15% wood production

**Mining Techniques:**
- Cost: 8k wood, 6k clay, 10k iron, 5k stone, 3k gold
- Time: 18 hours
- Effect: +20% iron and stone production

**Trade Routes:**
- Cost: 10k wood, 8k clay, 5k iron, 5k gold
- Time: 24 hours
- Effect: -20% trade travel time, +10% trade capacity

#### Military Technologies

**Bronze Weapons:**
- Cost: 3k wood, 2k clay, 4k iron, 1k gold
- Time: 8 hours
- Effect: +10% infantry attack

**Iron Weapons:**
- Cost: 6k wood, 4k clay, 10k iron, 3k gold
- Time: 16 hours
- Effect: +20% infantry attack
- Requirement: Bronze Weapons

**Steel Weapons:**
- Cost: 12k wood, 8k clay, 25k iron, 10k stone, 10k gold
- Time: 36 hours
- Effect: +35% infantry attack
- Requirement: Iron Weapons


**Cavalry Tactics:**
- Cost: 8k wood, 5k clay, 8k iron, 50 horses, 5k gold
- Time: 20 hours
- Effect: +15% cavalry speed and attack

**Siege Engineering:**
- Cost: 15k wood, 12k clay, 10k iron, 8k stone, 8k gold
- Time: 30 hours
- Effect: +30% siege weapon damage

**Defensive Architecture:**
- Cost: 10k wood, 20k clay, 15k iron, 12k stone, 6k gold
- Time: 24 hours
- Effect: +25% wall defense

#### Advanced Technologies

**Gunpowder:**
- Cost: 50k wood, 40k clay, 60k iron, 30k stone, 50k gold, 10 scrolls
- Time: 72 hours
- Effect: Unlock gunpowder units
- Requirement: Multiple prerequisites

**Alchemy:**
- Cost: 30k wood, 25k clay, 40k iron, 20k stone, 30k gold, 5 gems
- Time: 48 hours
- Effect: Unlock potions and rituals
- Requirement: Temple level 10

**Legendary Smithing:**
- Cost: 100k wood, 80k clay, 150k iron, 50k stone, 100k gold, 20 gems, 10 scrolls
- Time: 120 hours
- Effect: Unlock legendary equipment
- Requirement: Smithy level 20, multiple techs

### Spells & Rituals

#### Basic Rituals (Temple Level 1-5)

**Blessing of Harvest:**
- Cost: 1k food, 500 gold
- Duration: 24 hours
- Effect: +20% food production
- Cooldown: 48 hours

**Shield of Faith:**
- Cost: 2k wood, 2k clay, 1k gold
- Duration: 12 hours
- Effect: +15% defense
- Cooldown: 24 hours

**War Drums:**
- Cost: 1k wood, 1k iron, 1k gold
- Duration: 6 hours
- Effect: +10% attack
- Cooldown: 12 hours

#### Advanced Rituals (Temple Level 6-10)

**Divine Intervention:**
- Cost: 5k each resource, 5k gold, 1 gem
- Duration: Instant
- Effect: Cancel incoming attack (once)
- Cooldown: 7 days


**Mass Teleport:**
- Cost: 10k each resource, 10k gold, 3 gems
- Duration: Instant
- Effect: Relocate village to new location
- Cooldown: 30 days

**Summon Reinforcements:**
- Cost: 8k each resource, 5k gold, 2 gems
- Duration: Instant
- Effect: Spawn 100 random units
- Cooldown: 14 days

**Plague:**
- Cost: 15k food, 10k gold, 5 gems, 1 scroll
- Duration: 48 hours
- Effect: Target enemy village -50% production
- Cooldown: 30 days
- Restriction: Can be countered with rituals

#### Legendary Rituals (Temple Level 10+)

**Meteor Strike:**
- Cost: 50k each resource, 50k gold, 20 gems, 5 scrolls
- Duration: Instant
- Effect: Destroy random buildings in target village
- Cooldown: 90 days
- Restriction: Requires tribe vote, massive reputation cost

**Time Warp:**
- Cost: 100k each resource, 100k gold, 50 gems, 10 scrolls, 1 relic
- Duration: Instant
- Effect: Complete all current construction/research instantly
- Cooldown: 180 days
- Restriction: One-time use per server season

### Tribe Projects

#### Small Tribe Projects

**Tribe Warehouse:**
- Cost: 50k wood, 40k clay, 30k iron, 10k stone (shared contribution)
- Benefit: 500k shared storage
- Requirement: 10 tribe members

**Tribe Market:**
- Cost: 80k wood, 60k clay, 50k iron, 20k stone, 10k gold
- Benefit: Internal trading, 0% fees
- Requirement: 15 tribe members

**Tribe Academy:**
- Cost: 100k wood, 80k clay, 70k iron, 30k stone, 20k gold
- Benefit: +10% research speed for all members
- Requirement: 20 tribe members

#### Medium Tribe Projects

**Border Fort:**
- Cost: 200k wood, 180k clay, 150k iron, 80k stone, 30k gold
- Benefit: Territory control, spawn point, +10% defense in region
- Requirement: Controlled territory, 30 tribe members
- Upkeep: 10k food/day (shared)


**Tribe Monument:**
- Cost: 500k wood, 400k clay, 350k iron, 200k stone, 100k gold, 10 gems
- Benefit: +15% all production, prestige, tribe hall
- Requirement: 50 tribe members, controlled capital region

**War Camp:**
- Cost: 300k wood, 250k clay, 400k iron, 150k stone, 50k gold
- Benefit: +20% unit training speed, shared barracks
- Requirement: 40 tribe members, active war status

#### Large Tribe Projects (Megaprojects)

**Wonder of the World:**
- Cost: 5M wood, 4M clay, 3M iron, 2M stone, 500k gold, 100 gems, 50 scrolls
- Benefit: Server victory condition, permanent bonuses, legendary status
- Requirement: 100+ tribe members, controlled wonder location
- Duration: 30+ days to complete
- Competition: Other tribes can sabotage or compete

**Great Library:**
- Cost: 3M wood, 2.5M clay, 2M iron, 1.5M stone, 300k gold, 50 gems, 100 scrolls
- Benefit: +25% research speed (server-wide), +10 scroll generation/week
- Requirement: 80+ tribe members, specific location
- Duration: 20+ days to complete

**Colosseum:**
- Cost: 4M wood, 3.5M clay, 2.5M iron, 2M stone, 400k gold, 80 gems
- Benefit: +30% unit training speed, arena events, gladiator units
- Requirement: 90+ tribe members, capital city
- Duration: 25+ days to complete

**Trade Empire:**
- Cost: 2M wood, 1.8M clay, 1.5M iron, 1M stone, 1M gold, 30 gems
- Benefit: -50% trade fees (server-wide), +50% trade capacity, merchant bonuses
- Requirement: 70+ tribe members, control 5 trade hubs
- Duration: 15+ days to complete

### Map Structures

#### Defensive Structures

**Watchtower:**
- Cost: 10k wood, 8k clay, 5k iron, 3k stone
- Benefit: Vision radius +5 tiles, early warning system
- Placement: Anywhere in controlled territory
- Upkeep: 500 food/day

**Palisade:**
- Cost: 20k wood, 15k clay, 10k iron
- Benefit: +20% defense in tile
- Placement: Border tiles
- Upkeep: 1k food/day


**Stone Wall:**
- Cost: 50k wood, 80k clay, 40k iron, 30k stone
- Benefit: +50% defense, blocks passage
- Placement: Strategic chokepoints
- Upkeep: 3k food/day

#### Economic Structures

**Trade Post:**
- Cost: 15k wood, 12k clay, 8k iron, 5k gold
- Benefit: -10% trade travel time in region
- Placement: Along trade routes
- Upkeep: 800 food/day

**Resource Camp:**
- Cost: 25k wood, 20k clay, 15k iron, 10k stone
- Benefit: +10% production from nearby resource nodes
- Placement: Near resource deposits
- Upkeep: 1.5k food/day

**Toll Gate:**
- Cost: 30k wood, 25k clay, 20k iron, 15k stone, 10k gold
- Benefit: Collect 5% toll from passing caravans
- Placement: Major crossroads
- Upkeep: 2k food/day
- Risk: Attracts raiders and diplomatic issues

### Cosmetic Unlocks

#### Village Cosmetics

**Building Skins:**
- Medieval Theme: 5k gold
- Nordic Theme: 8k gold
- Oriental Theme: 10k gold
- Fantasy Theme: 15k gold, 5 gems
- Legendary Theme: 50k gold, 20 gems, 5 scrolls

**Village Banners:**
- Basic Banners: 1k gold each
- Animated Banners: 5k gold each
- Legendary Banners: 10k gold, 3 gems each
- Custom Banners: 20k gold, 10 gems (upload image)

**Village Effects:**
- Smoke Effects: 3k gold
- Magical Auras: 8k gold, 2 gems
- Seasonal Effects: 5k gold (limited time)
- Legendary Effects: 25k gold, 10 gems

#### Unit Cosmetics

**Unit Skins:**
- Basic Recolors: 2k gold per unit type
- Themed Armor: 5k gold per unit type
- Elite Skins: 10k gold, 3 gems per unit type
- Legendary Skins: 30k gold, 15 gems per unit type

**Unit Banners/Flags:**
- Standard Flags: 1k gold
- Custom Flags: 5k gold, 2 gems
- Animated Flags: 10k gold, 5 gems


#### Profile Cosmetics

**Titles:**
- Common Titles: 2k gold (e.g., "The Builder", "The Farmer")
- Rare Titles: 10k gold, 5 gems (e.g., "The Conqueror", "Master Trader")
- Legendary Titles: 50k gold, 25 gems (e.g., "Emperor", "Legend")
- Achievement Titles: Earned through gameplay (free)

**Avatars/Portraits:**
- Basic Avatars: 1k gold
- Animated Avatars: 5k gold, 2 gems
- Custom Avatars: 15k gold, 8 gems
- Legendary Avatars: 40k gold, 20 gems

**Profile Frames:**
- Bronze Frame: 3k gold
- Silver Frame: 8k gold, 3 gems
- Gold Frame: 20k gold, 10 gems
- Legendary Frame: 60k gold, 30 gems

#### Map Cosmetics

**Custom Map Markers:**
- Basic Markers: 500 gold each
- Animated Markers: 2k gold, 1 gem each
- Custom Icons: 5k gold, 3 gems each

**Territory Borders:**
- Standard Borders: Free
- Glowing Borders: 10k gold
- Animated Borders: 25k gold, 10 gems
- Legendary Borders: 75k gold, 40 gems

---

## Player Archetypes & Economy

### The Farmer (PvE Focus)

**Playstyle:**
- Focuses on resource production and growth
- Avoids PvP conflict when possible
- Farms barbarian villages extensively
- Trades surplus resources

**Economic Interaction:**
- **Primary Income**: Building production, barbarian farming
- **Resource Priority**: Balanced growth, food for army
- **Spending**: Buildings, defensive units, storage
- **Trading**: Sells surplus, buys iron for defense
- **Tribe Role**: Resource supplier, quest completer

**Progression Path:**
- Early: Max production buildings
- Mid: Expand villages, increase farming efficiency
- Late: Contribute to tribe projects, prestige buildings

**Challenges:**
- Vulnerable to raiders
- Slower military progression
- Requires active farming


### The Raider (PvP Aggressive)

**Playstyle:**
- Focuses on attacking other players
- Builds large raiding armies
- Targets wealthy villages
- Reputation as aggressive player

**Economic Interaction:**
- **Primary Income**: Raiding players, PvP loot
- **Resource Priority**: Iron for units, food for upkeep
- **Spending**: Military units, cavalry, siege weapons
- **Trading**: Sells looted resources, buys horses
- **Tribe Role**: Military power, territory expansion

**Progression Path:**
- Early: Build raiding army quickly
- Mid: Maximize raid efficiency, multiple villages
- Late: Elite units, legendary equipment, dominance

**Challenges:**
- High food consumption
- Enemies and retaliation
- Requires constant activity
- Reputation management

### The Trader (Economic Focus)

**Playstyle:**
- Focuses on market manipulation
- Buys low, sells high
- Controls trade routes
- Builds merchant empire

**Economic Interaction:**
- **Primary Income**: Trading profits, market fees
- **Resource Priority**: Gold, diverse resources for trading
- **Spending**: Market upgrades, caravans, trade posts
- **Trading**: Constant activity, multiple offers
- **Tribe Role**: Economic support, resource distribution

**Progression Path:**
- Early: Build market, establish trade reputation
- Mid: Control trade routes, multiple markets
- Late: Trade empire, merchant guild, massive wealth

**Challenges:**
- Caravan raids
- Market competition
- Requires market knowledge
- Vulnerable to economic changes

### The Diplomat (Social Focus)

**Playstyle:**
- Focuses on alliances and tribe politics
- Mediates conflicts
- Organizes tribe activities
- Builds social networks


**Economic Interaction:**
- **Primary Income**: Tribe contributions, diplomatic gifts
- **Resource Priority**: Balanced, gold for diplomacy
- **Spending**: Tribe projects, gifts, rituals
- **Trading**: Internal tribe trades, alliance support
- **Tribe Role**: Leadership, coordination, morale

**Progression Path:**
- Early: Join active tribe, build relationships
- Mid: Leadership roles, organize projects
- Late: Tribe leader, server politics, megaprojects

**Challenges:**
- Requires social skills
- Time-intensive
- Dependent on tribe success
- Political drama

### The Whale (Premium Player)

**Playstyle:**
- Uses premium currency extensively
- Accelerates progression
- Competes at highest level
- Collects rare items

**Economic Interaction:**
- **Primary Income**: Premium purchases, all sources
- **Resource Priority**: Everything, no limitations
- **Spending**: Instant upgrades, premium trades, cosmetics
- **Trading**: Premium features, unlimited capacity
- **Tribe Role**: Major contributor, prestige

**Progression Path:**
- Early: Rapid expansion through premium
- Mid: Multiple villages, elite armies
- Late: Server dominance, legendary status

**Challenges:**
- Cost management
- Community perception
- Diminishing returns
- Balance concerns

### The Free Competitive Player

**Playstyle:**
- Competes without spending
- Highly efficient gameplay
- Strategic planning
- Skill-based progression

**Economic Interaction:**
- **Primary Income**: Optimized production, smart raiding
- **Resource Priority**: Efficiency, no waste
- **Spending**: Calculated investments, ROI focus
- **Trading**: Strategic trades, market timing
- **Tribe Role**: Skilled player, tactical advisor

**Progression Path:**
- Early: Optimal build order, efficient farming
- Mid: Strategic expansion, calculated risks
- Late: Competitive position through skill


**Challenges:**
- Time investment
- Premium player competition
- Requires deep knowledge
- Slower progression

---

## Anti-Hoarding & Anti-Inflation Systems

### Storage Limitations

#### Hard Caps

**Warehouse Capacity:**
- Level 1: 1,000 per resource
- Level 10: 50,000 per resource
- Level 20: 100,000 per resource
- Level 30 (max): 250,000 per resource

**Granary Capacity (Food Only):**
- Level 1: 2,000 food
- Level 10: 100,000 food
- Level 20: 200,000 food
- Level 30 (max): 500,000 food

**Treasury Capacity (Gold):**
- Level 1: 5,000 gold
- Level 10: 100,000 gold
- Level 20: 500,000 gold
- Level 30 (max): 2,000,000 gold

**Overflow Mechanics:**
- Production continues but excess is lost
- Warning at 90% capacity
- Auto-trade option (sells excess at NPC rates)
- Tribe warehouse can store overflow (if available)

### Soft Caps & Diminishing Returns

**Production Efficiency:**
- 0-50k stored: 100% production
- 50k-100k stored: 90% production
- 100k-200k stored: 75% production
- 200k+ stored: 50% production
- Rationale: Encourages spending, prevents infinite hoarding

**Raid Protection:**
- Warehouse protects base amount
- Beyond protection: 100% lootable
- Incentive: Spend resources before being raided
- Strategy: Keep resources in circulation

### Resource Decay

**Food Spoilage:**
- Fresh food: 0-7 days, no decay
- Aging food: 7-14 days, -5% per day
- Spoiling food: 14-21 days, -10% per day
- Rotten food: 21+ days, -20% per day
- Prevention: Granary upgrades reduce decay by 50%


**Gold Inflation Tax:**
- 0-10k gold: 0% tax
- 10k-50k gold: 1% per week
- 50k-200k gold: 2% per week
- 200k+ gold: 5% per week
- Rationale: Prevents gold hoarding, encourages investment
- Exemption: Gold in active trades or tribe projects

**Resource Maintenance:**
- Large stockpiles require upkeep
- 100k+ resources: 1% food cost per day
- 500k+ resources: 3% food cost per day
- 1M+ resources: 5% food cost per day
- Rationale: Realistic storage costs, anti-hoarding

### Expensive Late-Game Sinks

#### Prestige Buildings

**Palace:**
- Cost: 1M wood, 800k clay, 600k iron, 400k stone, 200k gold
- Benefit: +100% production, prestige, noble units
- Upkeep: 50k food/day
- Purpose: Massive resource sink for endgame

**World Wonder:**
- Cost: 10M wood, 8M clay, 6M iron, 4M stone, 2M gold, 500 gems
- Benefit: Server victory, legendary status
- Upkeep: 200k food/day
- Purpose: Ultimate resource sink, tribe effort

**Legendary Monument:**
- Cost: 5M wood, 4M clay, 3M iron, 2M stone, 1M gold, 200 gems, 100 scrolls
- Benefit: Permanent server-wide bonuses
- Upkeep: 100k food/day
- Purpose: Collaborative megaproject

#### Research Sinks

**Ultimate Technologies:**
- Cost: 500k+ each resource, 100k+ gold, 50+ gems, 20+ scrolls
- Time: 7+ days
- Effect: Game-changing bonuses
- Purpose: Long-term progression goal

**Legendary Equipment:**
- Cost: 1M+ resources, 500k+ gold, 100+ gems
- Effect: Unique unit abilities
- Purpose: Endgame customization

### Tribe-Wide Megaprojects

**Collaborative Wonders:**
- Require 100+ members contributing
- Total cost: 50M+ resources
- Duration: 60+ days
- Benefit: Server-wide effects
- Purpose: Community engagement, resource drain

**Territory Expansion:**
- Cost scales exponentially
- 1st region: 100k resources
- 5th region: 5M resources
- 10th region: 50M resources
- Purpose: Competitive resource sink


### Seasonal Resets & Wipes

**Server Seasons:**
- Duration: 6-12 months
- Reset: All progress wiped
- Rewards: Carry over cosmetics, titles, premium currency
- Purpose: Fresh economy, prevent stagnation

**Partial Resets:**
- Keep: Villages, buildings (reduced levels)
- Reset: Resources, units, technologies
- Frequency: Quarterly
- Purpose: Economic refresh without full wipe

### Dynamic Market Adjustments

**Supply & Demand:**
- High supply: Prices drop 20-50%
- Low supply: Prices rise 20-50%
- Updates: Hourly based on market activity
- Purpose: Self-regulating economy

**NPC Price Adjustments:**
- Abundant resources: NPC pays less
- Scarce resources: NPC pays more
- Range: 50-200% of base value
- Purpose: Market stabilization

**Event-Based Inflation Control:**
- Double resource events: Temporary price drops
- Scarcity events: Temporary price increases
- War events: Military resource demand increases
- Purpose: Dynamic economic challenges

### Alternative Sinks

**Gambling/Lottery:**
- Cost: 10k gold per ticket
- Prize: Rare resources, gems, relics
- Odds: 1% legendary, 10% rare, 30% common
- Purpose: Gold sink, excitement

**Cosmetic Gacha:**
- Cost: 5k gold per roll
- Rewards: Random cosmetics
- Duplicate protection after 10 rolls
- Purpose: Gold sink, collection

**Donation System:**
- Donate to server development
- Rewards: Exclusive cosmetics, titles
- No gameplay advantage
- Purpose: Ethical monetization, resource sink

**Ritual Offerings:**
- Sacrifice resources for RNG blessings
- Cost: 50k+ resources
- Rewards: Random buffs, rare items
- Purpose: High-risk resource sink

---

## Risk vs Reward

### Transporting Resources

#### Caravan Risk Levels

**Safe Routes (Controlled Territory):**
- Risk: 0-5% raid chance
- Reward: Standard delivery
- Strategy: Routine trades, bulk transport


**Neutral Routes (Uncontrolled Territory):**
- Risk: 20-40% raid chance
- Reward: Faster routes, better trade rates
- Strategy: Medium-value trades, calculated risk

**Dangerous Routes (Enemy Territory):**
- Risk: 60-80% raid chance
- Reward: Exceptional trade rates, rare resources
- Strategy: High-value trades, heavy escort

**Legendary Routes (Cross-Server):**
- Risk: 90%+ raid chance
- Reward: Unique items, massive profits
- Strategy: Coordinated tribe efforts, legendary rewards

#### Risk Mitigation

**Escort Options:**
- No escort: Fast, vulnerable
- Light escort: Moderate speed, some protection
- Heavy escort: Slow, high protection
- Tribe convoy: Very slow, maximum protection

**Decoy Caravans:**
- Send fake caravans to distract raiders
- Cost: Food and units
- Success: 50% chance to divert attention
- Strategy: Protect high-value shipments

**Insurance System:**
- Pay 10% of cargo value
- Guaranteed delivery or refund
- Premium feature or high-reputation traders
- Purpose: Risk management for valuable trades

**Stealth Mechanics:**
- Small caravans harder to detect
- Night travel: -20% detection chance
- Scout avoidance: Requires intelligence
- Trade-off: Lower capacity for safety

### Raiding Risks

#### Attacker Risks

**Failed Raids:**
- Unit losses: 50-100% of attacking force
- Reputation damage: -10 to -50 points
- Retaliation: Target may counter-attack
- Tribe consequences: Diplomatic issues

**Successful Raids:**
- Unit losses: 10-30% of attacking force
- Loot: 50-100% of target's unprotected resources
- Reputation: +5 to +20 points (raider reputation)
- Tribe benefits: Shared loot options

**Overextension:**
- Raiding too far: High food costs
- Multiple raids: Army spread thin
- Vulnerability: Home village exposed
- Consequence: Counter-raids while away


#### Defender Rewards

**Successful Defense:**
- Keep all resources
- Destroy attacker units (loot scrap resources)
- Reputation: +10 to +30 points
- Morale boost: +10% production for 24 hours

**Failed Defense:**
- Lose unprotected resources
- Unit losses: 30-70% of defending force
- Building damage: 10-30% efficiency loss
- Recovery time: 6-24 hours

**Trap Mechanics:**
- Hidden traps: Cost resources to build
- Surprise damage: 20-50% attacker casualties
- One-time use: Must rebuild after trigger
- Strategy: Deter raiders, protect resources

### Hoarding Risks

#### Storage Vulnerabilities

**Full Warehouses:**
- Maximum raid target
- Attracts attention from scouts
- Inefficient (production penalties)
- Opportunity cost: Resources not invested

**Visible Wealth:**
- High-level buildings signal wealth
- Leaderboard position attracts raiders
- Reputation as "rich target"
- Strategy: Balance growth with security

**Inactive Hoarding:**
- Offline players are prime targets
- No active defense
- Resources wasted (not generating value)
- Consequence: Rapid resource loss

#### Strategic Hoarding

**Pre-Upgrade Hoarding:**
- Save for major building/tech
- Risk: Vulnerable during accumulation
- Reward: Rapid progression when ready
- Strategy: Time upgrades, use protection

**Event Preparation:**
- Stockpile for events
- Risk: Resources idle for days/weeks
- Reward: Competitive advantage during event
- Strategy: Balance preparation with growth

**Market Manipulation:**
- Hoard scarce resources
- Risk: Market changes, storage limits
- Reward: Sell at inflated prices
- Strategy: Requires market knowledge

---

## Example Economic Flows

### Early Game: The New Farmer (Days 1-7)

**Day 1:**
- Start: 1000 each resource
- Build: Lumber camp (500 wood, 400 clay)
- Train: 5 spearmen (250 wood, 150 clay, 50 iron)
- Farm: 3 small barbarian camps (600-1500 mixed resources)
- End: ~2000 wood, 1500 clay, 800 iron, 1000 food


**Day 3:**
- Production: +5000 wood, +3000 clay, +1500 iron, +4000 food
- Build: Clay pit level 5 (2000 wood, 1500 clay)
- Build: Iron mine level 3 (1000 wood, 800 clay, 500 iron)
- Farm: 10 barbarian camps (2000-5000 mixed resources)
- Quest rewards: 1500 mixed, 100 gold
- End: ~8000 wood, 6000 clay, 4000 iron, 3000 food, 100 gold

**Day 7:**
- Production: +20k wood, +12k clay, +6k iron, +15k food
- Build: Warehouse level 10 (20k wood, 15k clay, 5k iron)
- Build: Barracks level 5 (5k wood, 4k clay, 2k iron)
- Train: 20 spearmen, 10 axemen (1500 wood, 1000 clay, 700 iron)
- Farm: 30 barbarian camps (6000-15000 mixed resources)
- Trade: Sell 5k wood for 2k iron (market)
- End: ~25k wood, 18k clay, 12k iron, 10k food, 300 gold
- Status: Established production, ready for expansion

### Mid Game: The Ambitious Raider (Weeks 2-8)

**Week 2:**
- Resources: 50k wood, 40k clay, 30k iron, 20k food, 1k gold
- Build: Stable level 5 (20k wood, 15k clay, 10k iron, 50 horses)
- Train: 30 light cavalry (2400 wood, 1200 clay, 1500 iron, 150 horses)
- Research: Cavalry tactics (8k wood, 5k clay, 8k iron, 50 horses, 5k gold)
- Raid: 10 player villages (50k-150k mixed loot)
- Farm: 50 barbarian camps (15k-40k mixed loot)
- End: ~120k wood, 90k clay, 80k iron, 40k food, 2k gold
- Status: Strong raiding force, positive resource flow

**Week 4:**
- Resources: 200k wood, 180k clay, 150k iron, 80k food, 10k gold
- Build: Second village (100k wood, 80k clay, 60k iron, 40k stone, 5k gold)
- Build: Academy level 5 (40k wood, 35k clay, 30k iron, 10k stone, 5k gold)
- Train: 50 heavy cavalry (5k wood, 3k clay, 5k iron, 400 horses)
- Raid: 30 player villages (150k-500k mixed loot)
- Tribe: Contribute 50k mixed to tribe warehouse
- End: ~400k wood, 350k clay, 300k iron, 150k food, 20k gold
- Status: Multi-village empire, tribe contributor


**Week 8:**
- Resources: 800k wood, 700k clay, 600k iron, 300k food, 50k gold
- Build: Castle (500k wood, 400k clay, 300k iron, 200k stone, 50k gold)
- Train: 10 paladins (3k wood, 2k clay, 2500 iron, 150 horses, 1k gold, 10 gems)
- Research: Steel weapons (12k wood, 8k clay, 25k iron, 10k stone, 10k gold)
- Raid: 100 player villages (500k-2M mixed loot)
- Tribe: Lead tribe war effort
- End: ~2M wood, 1.8M clay, 1.5M iron, 800k food, 100k gold
- Status: Server power player, tribe leader

### Mid Game: The Savvy Trader (Weeks 2-8)

**Week 2:**
- Resources: 40k wood, 35k clay, 25k iron, 30k food, 2k gold
- Build: Market level 10 (25k wood, 20k clay, 15k iron, 5k gold)
- Strategy: Buy low (wood/clay), sell high (iron/gold)
- Trades: 50 successful trades (10k profit per trade)
- Farm: 20 barbarian camps (supplement trading)
- End: ~60k wood, 50k clay, 40k iron, 35k food, 15k gold
- Status: Established trader, growing reputation

**Week 4:**
- Resources: 150k wood, 130k clay, 100k iron, 80k food, 50k gold
- Build: Trade posts on 3 routes (45k wood, 36k clay, 24k iron, 15k gold)
- Strategy: Control trade routes, collect tolls
- Trades: 200 successful trades (50k profit per week)
- Caravan: Run merchant convoys (high-risk, high-reward)
- End: ~300k wood, 250k clay, 200k iron, 150k food, 150k gold
- Status: Trade empire forming, multiple income streams

**Week 8:**
- Resources: 600k wood, 500k clay, 400k iron, 300k food, 500k gold
- Build: Merchant guild (200k wood, 180k clay, 150k iron, 50k stone, 100k gold)
- Strategy: Market manipulation, bulk trading
- Trades: 500+ successful trades (200k profit per week)
- Tribe: Provide economic support, resource distribution
- End: ~1.5M wood, 1.2M clay, 1M iron, 800k food, 1M gold
- Status: Economic powerhouse, tribe benefactor

### Late Game: The Tribe Leader (Months 3-6)

**Month 3:**
- Tribe resources: 10M wood, 8M clay, 6M iron, 4M stone, 2M gold, 200 gems
- Project: Begin Wonder construction
- Contribution: 100 members × 100k each resource
- Defense: Protect Wonder site (24/7 tribe coordination)
- Raids: Sabotage enemy tribe Wonders
- Status: Server-wide competition, diplomatic maneuvering


**Month 4:**
- Tribe resources: 20M wood, 16M clay, 12M iron, 8M stone, 4M gold, 400 gems
- Project: Wonder 50% complete
- Territory: Control 30% of server map
- Economy: Tribe-wide production bonuses (+50% all resources)
- Warfare: Constant battles for territory and resources
- Status: Dominant tribe, nearing victory

**Month 6:**
- Tribe resources: 50M+ wood, 40M+ clay, 30M+ iron, 20M+ stone, 10M+ gold, 1000+ gems
- Project: Wonder complete (server victory)
- Rewards: Legendary titles, permanent bonuses, cosmetics
- Legacy: Tribe name in server history
- Status: Server champions, season complete

### Late Game: The Competitive Free Player (Months 3-6)

**Month 3:**
- Resources: 5M wood, 4M clay, 3M iron, 2M stone, 500k gold, 50 gems
- Strategy: Efficient gameplay, no premium shortcuts
- Villages: 5 optimized villages (max production)
- Army: 5000 mixed units (carefully managed)
- Tribe: Active contributor, tactical advisor
- Status: Competitive despite no spending

**Month 4:**
- Resources: 10M wood, 8M clay, 6M iron, 4M stone, 1M gold, 100 gems
- Strategy: Smart raiding, efficient trading
- Villages: 8 villages (strategic locations)
- Army: 10000 mixed units (quality over quantity)
- Tribe: Key player in tribe wars
- Status: Top 100 player, respected competitor

**Month 6:**
- Resources: 30M wood, 25M clay, 20M iron, 15M stone, 5M gold, 300 gems
- Strategy: Mastery of game mechanics
- Villages: 12 villages (empire)
- Army: 25000 mixed units (legendary equipment)
- Tribe: Co-leader, strategic mastermind
- Achievement: Prove free players can compete
- Status: Top 20 player, community legend

---

## Economic Balance Principles

### Core Design Goals

1. **Multiple Viable Paths**: Farming, raiding, trading all viable
2. **Risk vs Reward**: Higher risk = higher potential rewards
3. **Anti-Stagnation**: Constant resource flow, no infinite hoarding
4. **Social Interaction**: Tribe cooperation rewarded
5. **Skill Expression**: Knowledge and strategy matter
6. **Fair Competition**: Free players can compete with premium
7. **Long-Term Engagement**: Progression systems for months/years
8. **Dynamic Economy**: Supply/demand, events, seasonal changes


### Balancing Mechanisms

**Production vs Consumption:**
- Base production: 100 units/hour
- Army upkeep: 50 units/hour (50% drain)
- Building costs: 1000 units (10 hours production)
- Ratio: Sustainable growth with active play

**Raid vs Defense:**
- Raid loot: 10k resources (average)
- Raid cost: 2k resources (units + food)
- Defense cost: 5k resources (units + buildings)
- Balance: Raiding profitable but risky

**Trade Efficiency:**
- NPC trade: 1:2 ratio (50% loss)
- Player trade: 1:1.2 ratio (20% loss from fees)
- Tribe trade: 1:1 ratio (no loss)
- Incentive: Social trading preferred

**Time Investment:**
- Casual player: 1 hour/day = steady progress
- Active player: 3 hours/day = competitive
- Hardcore player: 6+ hours/day = top tier
- Premium player: Any time = accelerated

### Inflation Control

**Resource Generation Rate:**
- Early game: 1k/hour per village
- Mid game: 10k/hour per village
- Late game: 50k/hour per village
- Scaling: Linear with effort, exponential costs

**Resource Sink Scaling:**
- Early buildings: 1k resources
- Mid buildings: 100k resources
- Late buildings: 10M resources
- Ratio: Costs scale faster than production

**Gold Economy:**
- Generation: 100 gold/day (average)
- Sinks: 1k gold/day (buildings, trades, cosmetics)
- Premium: 10k gold/month (optional)
- Balance: Gold scarcity maintained

### Premium Balance

**Premium Advantages:**
- +50% production (time-saving)
- +50% trade capacity (convenience)
- Instant upgrades (time-saving)
- Cosmetics (no gameplay impact)

**Free Player Parity:**
- Can achieve same end-state
- Requires more time investment
- Skill can overcome premium advantages
- Tribe support equalizes differences

**Pay-to-Win Prevention:**
- No exclusive powerful units
- No unbeatable advantages
- Skill and strategy matter most
- Community balance feedback

---

## Implementation Notes

### Technical Considerations

**Database Design:**
- Resource tables: Player ID, resource type, amount, timestamp
- Transaction logs: All resource changes tracked
- Market tables: Offers, trades, prices, history
- Tribe tables: Shared resources, contributions, projects


**Performance Optimization:**
- Batch resource updates (every 5 minutes)
- Cache production rates
- Async trade processing
- Indexed market queries

**Anti-Cheat:**
- Server-side validation (all transactions)
- Rate limiting (prevent bots)
- Anomaly detection (suspicious trades)
- Transaction rollback (exploit recovery)

**Analytics:**
- Track resource flows
- Monitor inflation rates
- Identify economic exploits
- Balance adjustments based on data

### Tuning Parameters

**Adjustable Values:**
- Production rates (per building level)
- Resource costs (buildings, units, tech)
- Storage capacities (warehouse levels)
- Decay rates (food spoilage, gold tax)
- Market fees (trade costs)
- Raid loot percentages
- Event bonuses (seasonal multipliers)

**Balance Patches:**
- Weekly: Minor adjustments based on data
- Monthly: Major balance changes
- Quarterly: Economic overhauls
- Seasonal: New content and systems

**Community Feedback:**
- Player surveys (satisfaction, balance)
- Forum discussions (suggestions, complaints)
- Beta testing (new features)
- Data analysis (actual vs intended behavior)

### Future Expansions

**Potential Features:**
- Stock market system (resource futures)
- Banking system (loans, interest)
- Insurance companies (player-run)
- Auction house (rare items)
- Crafting system (combine resources)
- Resource refinement (upgrade quality)
- Trade caravans (automated routes)
- Economic warfare (embargoes, sanctions)
- Resource conversion (alchemy)
- Seasonal resources (limited availability)

**Advanced Mechanics:**
- Dynamic pricing algorithms
- Player-driven economy (minimal NPC intervention)
- Economic espionage (steal trade secrets)
- Resource speculation (buy low, sell high)
- Economic alliances (trade blocs)
- Currency exchange (multiple currencies)
- Resource quality tiers (common, rare, legendary)
- Economic achievements (millionaire, trade master)

---

## Conclusion

This resource and economy system is designed to create a rich, dynamic, and engaging economic experience that supports multiple play styles, encourages social interaction, and maintains long-term balance. The combination of diverse resource types, multiple generation methods, extensive sinks, and anti-inflation mechanics ensures a healthy economy that rewards skill, strategy, and cooperation while preventing stagnation and exploitation.


Key principles include:
- **Diversity**: Multiple resources with distinct roles and values
- **Flow**: Constant circulation through generation and consumption
- **Choice**: Meaningful decisions about resource allocation
- **Risk**: Interesting trade-offs between safety and reward
- **Social**: Tribe cooperation and trading encouraged
- **Balance**: Free and premium players can both succeed
- **Longevity**: Systems that support months or years of engagement
- **Fairness**: Skill and strategy matter more than spending

The economy should feel alive, responsive, and fair, creating emergent gameplay through player interactions while maintaining developer control over inflation and balance. Regular monitoring, community feedback, and data-driven adjustments will ensure the economy remains healthy and engaging throughout the game's lifecycle.
