# Full Gameplay Design Document — Medieval Tribal War Browser MMO

**Document owner:** Design Team  
**Audience:** Design, Product, Engineering, Live Ops, Community  
**Purpose:** Cohesive reference for vision, systems, and world-configuration knobs.

## Executive Summary
- Browser-based, persistent medieval tribal war MMO focused on collaborative empire building and tactical PvP at continent scale.
- Players grow villages into regional strongholds, form tribes, and coordinate wars across a shared grid map using scouting, siege, and loyalty-based conquest.
- Distinctive features: asymmetric world rules, loyalty-based village capture, tribe-driven diplomacy, event-driven pacing, and fair-play monetization with strong anti-cheat.
- Core loop blends resource management, building progression, troop production, scouting/intel wars, and synchronized tribal offensives.
- Multiple world types (classic, speed, seasonal, hardcore) with tunable parameters enable varied competitive experiences and seasonal resets.

## Core Fantasy & Pillars
### Core Fantasy
Lead a fledgling medieval tribe from a lone village to a continental power by mastering economy, military strategy, and diplomacy. Every decision on production, movement, and alliances shapes the living war on the map.

### Design Pillars
- **Tribal Cohesion:** The most powerful tool is your tribe. Shared intel, coordinated timings, and role specialization beat raw stats.
- **Strategic Clarity:** Players always understand what matters now (resources, build queues, targets) and why (clear reports, forecasts, and UI cues).
- **Persistent Consequence:** Villages captured, treaties signed, and events completed reshape the map; losses matter but are recoverable with tribe support.
- **Counterplay Everywhere:** Scouting vs counter-scout, siege vs walls, speed vs supply lines, nobles vs loyalty recovery; no unstoppable tactic.
- **Fair but Ferocious:** Competitive integrity (anti-botting, anti-pushing, transparent rules) plus monetization that respects fairness and caps advantages.

## Core Systems
### Economy
- **Resources:** Wood, Clay, Iron (production + building + recruitment), Food/Population (soft cap), Gold/Silver Coins (premium/world currency for nobles/events), Reputation/Season Points (meta progression), Event Tokens (limited-time exchange).
- **Production:** Resource buildings (e.g., Lumber Camp, Clay Pit, Iron Mine) produce per tick; capped by Storage/Granary. Night/peace bonuses can reduce plunder or increase regen on specific worlds.
- **Upkeep:** Units consume population (Food). Over-cap slows production or inflicts desertion on hardcore worlds.
- **Flow:** Gather (production) → Store (Storage/Granary) → Spend (buildings, troops, research, nobles) → Lose (plunder, event sinks) → Recover (tribe aid, production boosts).
- **Trade & Transport:** Market caravans move resources between owned villages or tribe members; taxed by distance/speed settings. Convoys can be intercepted on hardcore worlds.
- **Protection:** Beginner’s protection shields resources from plunder; Vault/Hidden Storage hides a % from raids. Night bonus can reduce attacker strength during defined hours.
- **Inflation Control:** Dynamic event shop prices, coin sinks (nobles, tribe tech), and maintenance costs tuned per world.

### Buildings
- **Construction Model:** Each village has building slots plus upgradeable levels. Build queue limits configurable; parallel queues unlocked by premium or research on some worlds.
- **Dependencies:** Town Hall gates build/upgrade speed; Barracks/Stable/Workshop require certain Hall levels; Academy required for noble training; Wall requires minimum Town Hall; Market requires Storage level N; Watchtower requires Wall level N.
- **Core Roles:**
  - **Town Hall:** Unlocks higher building caps, reduces build time.
  - **Barracks / Stable / Workshop:** Train infantry, cavalry, siege; speed scales with building level.
  - **Academy:** Mints Coins and trains Noblemen; core to conquest (see Conquest).
  - **Rally Point:** Send attacks/support, stack timing, manage presets.
  - **Wall:** Flat defensive bonus + casualty reduction; requires siege to breach.
  - **Storage & Granary:** Caps stock; interacts with plunder capacity.
  - **Market:** Trade resources; supports buy/sell orders or direct sends.
  - **Watchtower/Lookout:** Boosts scout defense, early-warning radius on map.
  - **Hospital/Healer (optional world rule):** Recovers wounded % after battles.
  - **Hidden Storage/Vault:** Protects resources from plunder.
  - **Resource Fields:** Lumber Camp, Clay Pit, Iron Mine; main production scaling.

### Units
- **Archetypes:**
  - **Light Infantry:** Fast build, low pop, good vs un-walled raids.
  - **Heavy Infantry:** High defense vs infantry/cav; slower.
  - **Spears/Pikes:** Counter cavalry; cheap defense.
  - **Swords:** Balanced defense; moderate attack.
  - **Archers (if enabled):** Ranged defense/attack; good wall synergy.
  - **Light Cavalry:** Fast scouts/raiders; high speed, low carry on hardcore.
  - **Heavy Cavalry:** Strong attack/defense; higher pop cost.
  - **Scouts:** Provide intel; minimal combat value; duel other scouts.
  - **Rams:** Reduce Wall level during battle.
  - **Catapults/Trebuchets:** Target buildings; long build time.
  - **Nobleman:** Reduces loyalty; core to village capture.
- **Stats:** Attack, Defense (inf/cav/arch), Speed (time per tile), Carry Capacity, Population Cost, Build Time, Travel Type (infantry/cav/siege), Visibility (for scouting reports).
- **Recruitment:** Queued per building (Barracks/Stable/Workshop/Academy); speed affected by building level, world speed, and premium queue boosts if enabled.

### Combat
- **Flow:** Select troops at Rally Point → travel time based on slowest unit and terrain/roads → battle resolves on arrival → survivors return with loot or stay as support.
- **Resolution:**
  - Compare total attack vs defense per type (inf/cav/arch) after Wall bonus and morale (attacker morale scales vs defender points to protect smaller players).
  - Random **Luck** factor within configurable band (e.g., -15% to +15%).
  - **Wall:** Provides multiplicative defense; Rams can reduce Wall during battle. If Rams fail, Wall persists.
  - **Formation & Targeting:** Siege fires first on Wall/target building; casualties allocated by proportion of defense types.
  - **Support:** Stationed allied troops join defender; returning home post-battle unless set to hold.
  - **Night Bonus (optional):** Defender multiplier during defined hours.
  - **Traps (if enabled):** Pre-battle casualty to attackers; cleared after triggered.
- **Casualties & Recovery:** Surviving troops return; wounded % may go to Hospital (if present) for delayed recovery; loyalty damage persists (see Conquest).
- **Looting:** Carry capacity determines plunder; capped by defender resources minus Vault protection. Partial plunder if insufficient capacity.
- **Reports:** Show troop loss, Wall change, loyalty shift, loot, and scout intel. Fogged values if no scouts survived.

### Map
- **Grid:** Square tile grid with coordinates (x,y); grouped into **Sectors → Provinces → Continents** for spawn, events, and endgame scoring.
- **Visibility:** Fog of war; only own/tribe villages and scouted tiles show details. Watchtowers provide early alerts in radius.
- **Movement:** Travel time = distance × unit speed modifier; roads/rivers adjust speed per tile; weather/events can modify.
- **Spawning:** New players placed in low-conflict sectors near peers; relocation tokens allow limited moves during protection.
- **Points of Interest:** Barbarian villages (farmable), event camps, relic shrines, tribe capitals.
- **Map Tools:** Bookmarks, tribe markers, K-codes (continent notation), heatmaps for activity, and live command traces during ops.

### Scouting & Intel
- **Scout Actions:** Recon (troops + resources), Structure Scan (building levels), Sabotage (event/world-specific), Path Watch (track movement through watchtowers).
- **Counter-Intel:** Watchtower level adds defensive scouts; defending scouts duel attackers. Anti-scout bonus from tribe tech and items.
- **Signal Levels:** Report fidelity depends on surviving scout ratio; partial info reveals ranges instead of exact counts.
- **Intel Sharing:** Tribe intel board aggregates reports and renders map overlays; permissions control who can see enemy coords.

### Conquest (Loyalty System)
- **Loyalty:** Each village has loyalty (0–100). Nobleman attacks reduce loyalty by a random range (configurable, e.g., 20–35). At 0 or below, attacker captures the village if at least one noble survives and defender is cleared.
- **Requirements:** Academy level N, minted Coins (resource sink), and population capacity. Noble travel is slow and high value.
- **Defensive Recovery:** Loyalty regenerates per hour up to 100; rate tunable and modified by morale/tribe tech.
- **Anti-Snipe:** After capture, grace timer prevents immediate re-take or grants temporary defender morale bonus.
- **Abandon/Relocate:** Players may abandon low-value villages (with timer) to reduce frontline burden; not allowed during active siege.

### Tribes & Diplomacy
- **Formation:** Players create or join tribes; size cap configurable. Founders set banner, name, tagline, and recruitment rules.
- **Roles/Permissions:** Leader, Diplomat, War Chief, Treasurer, Recruiter, Member. Permissions gate ops planning, tribe funds, NAP/ally offers, and forum moderation.
- **Diplomacy States:** Ally, NAP, Neutral, War; optional “Ceasefire (timed)” and “Protected Vassal” states on some worlds. States affect support/attacks, vision sharing, and friendly-fire rules.
- **Tribe Features:**
  - Shared intel, coordinated command planner with timed send calculators.
  - Tribe quests (collective goals) that grant boosts or cosmetics.
  - Resource aid pool; request board with caps to prevent pushing.
  - Tribe tech tree: small % bonuses (build speed, scout defense, loyalty regen) funded by Coins and tribe quests; capped for fairness.
  - Internal forums, announcements, ping-able map markers, and alarm settings for attacks.

### Worlds & Rulesets
- **World Types:**
  - **Classic:** Standard speed, night bonus, gradual tech unlocks.
  - **Speed:** Higher build/unit speeds, shorter world duration, softer death penalties.
  - **Casual/Training:** Longer protection, lower attack caps, reduced plunder; ideal for NPE.
  - **Hardcore:** Higher casualty rates, convoy interception, limited premium, harsher morale against bullies removed.
  - **Seasonal/Ranked:** Fixed duration, battle pass, rotating modifiers (fogged reports, random events), leaderboard rewards.
- **Sharding & Merge:** Worlds are isolated; optional late-game merge event for top tribes into a Finals world.
- **Seasonality:** Worlds have start/end dates; relic distribution and event calendar published at launch.

### Endgame
- **Win Conditions (configurable per world):**
  - **Dominance:** Tribe controls X% of villages in Y continents for Z days.
  - **Wonder:** Construct and defend Great Wonder; requires relics and escalating resource costs.
  - **Relic Control:** Hold scattered relic shrines; accrue victory points per tick.
  - **Season Points:** Highest season score at end of fixed duration; points from villages, relics, events, and tribe quests.
- **Post-Victory:** Lock world to read-only or mop-up phase; grant cosmetic badges/titles and optional transfer tokens to next season.
- **Catch-Up:** Late joiners get boosted production and fast-start bundles on seasonal worlds to stay relevant.

### New Player Experience (NPE)
- **Tutorial:** Step-by-step missions (build resource fields, train scouts, send first raid) with UI highlights and instant completions for first tasks.
- **Beginner Protection:** Time- and point-based; ends early if player attacks. Limits on outgoing attacks to prevent smurf harassment.
- **Guided Tribe Join:** Prompt to auto-apply to nearby aligned tribes; incentives for tribes to adopt rookies (tribe quest credit).
- **Safety Nets:** Starter relocation, rebuild boosts after first wipe, daily login gifts focused on production not troops.
- **Education:** Inline tips on reports, wall importance, and loyalty; mini-sim for timing attacks vs support.

### Events & Live Ops
- **Cadence:** Weekly micro-events (boosted barb loot, scout mini-game), monthly arcs (relic storms), seasonal arcs (harvest festival with resource modifiers).
- **Event Types:**
  - **PvE Camps:** Spawn NPC camps with loot and unique tactics.
  - **Modifiers:** Temporary world rules (faster scouts, ram bonus, fogged reports).
  - **Competitive Races:** First tribe to achieve milestones (X relics, Y captures) gets buffs.
  - **Crafting/Token Shops:** Earn tokens to exchange for cosmetics, limited boosters, or tribe tech contributions.
- **Integration:** Events appear on map, inject alternative progression, and act as sinks for resources and Coins without breaking fairness caps.

### UI/UX Foundations
- **Views:** Village (build/train), Map (movement and intel), Tribe (diplomacy and ops), Reports, Events, Shop.
- **Quality-of-Life:** Build and recruit queues with timers, presets for attack/support, bookmark folders, attack timers with server sync, filters in reports.
- **Clarity:** Inline tooltips for morale, loyalty, wall effects; color-coded diplomacy states on map; resource delta per hour surfaces.
- **Accessibility:** Mobile-friendly layout, scalable fonts, high-contrast mode, keyboard shortcuts on desktop.

### Fair Play & Trust
- **Anti-Abuse:** Bot detection (behavioral + captcha), alt-account detection (IP/device clustering), push-protection (resource send caps by score), and ban escalation.
- **Rate Limits:** Trade/aid throttles; command-per-minute caps during early game to limit scripts.
- **Transparency:** Public ruleset per world, visible ban waves, and clear report data without hidden modifiers beyond luck/morale bands.
- **Rollback Policy:** Logged actions allow targeted rollbacks for exploits without punishing unaffected players.

### Monetization (Fairness-First)
- **Primary:** Cosmetics (skins for UI, banners, troop visuals), profile/tribe flair, emotes.
- **Secondary (Capped Convenience):** Queue extenders, name changes, extra bookmarks, mild build speed boosts with daily caps and disabled on hardcore worlds.
- **Season Pass:** Track of cosmetics, currencies, and small time-saving items; no direct combat buffs. Pass XP from play, not purchase.
- **Earning vs Buying:** Most convenience items earnable through events/quests; spending accelerates but capped to avoid pay-to-win.
- **No-Go:** Direct troop sales, uncapped production boosts, attack buffs, or pay-only nobles.
- **Compliance:** Clear refund and parental controls; spending limits per day per account.

## Player Journey
- **First Login (Day 0):** Guided tutorial builds first resource fields, introduces Rally Point, sends scripted scout to barb; beginner protection on. Player chooses preferred world speed and auto-joins starter continent.
- **Day 1–2 (Shelter & Learning):** Focus on economy (resource fields to level 6–8), Wall level 1–2, first Barracks units. Joins tribe via guided prompt. Receives daily gift and event tokens.
- **Day 3–5 (First Blood):** Starts raiding barbarians, learns report reading, upgrades Wall/Storage, researches Rams unlock path. Tribe assigns defense/attack roles; first coordinated support call.
- **Day 5–10 (Expansion):** Academy built; coins minted; first Noble attack on nearby barb/abandoned village. Map exploration and watchtower placement. Engages in first event (PvE camp race).
- **Week 3 (War Footing):** Multi-village management; resource trading and tribe tech contributions. Participates in synchronized tribe offensive (fake waves + main). Learns timing to dodge/stack.
- **Midgame (Territorial Play):** Loyalist villages captured; relic shrines contested. Night bonus influences attack windows. Player optimizes multiple queues, uses presets, and manages support pools.
- **Late Game (Endgame Mode):** Tribe pursues win condition (dominance/relic/wonder). Large-scale ops with multiple fronts, heavy scouting wars, and attrition management. Player rotates between offense, support, and rebuild.
- **Post-Victory/Season End:** Receives titles, cosmetics, and transfer tokens. Optional migration to next season with soft benefits; social graph preserved via tribe carry-over.

## System Interactions
- **Economy ↔ Combat:** Resource scarcity drives raiding; plunder feeds build queues; Wall and Vault mitigate losses; upkeep/population caps gate army size.
- **Economy ↔ Conquest:** Coins and population are sinks for noble production; loyalty regen competes with resource allocation for offense vs defense.
- **Economy ↔ Events:** Events provide alternative sinks and bursts, preventing hoarding and smoothing progression curves.
- **Buildings ↔ Units:** Production buildings and Storage set throughput; military buildings gate unit types and speed; Wall and Watchtower shape defensive meta.
- **Combat ↔ Scouting:** Scouting informs composition; counter-scout determines battle report fidelity. Fogged info increases risk and encourages mixed armies.
- **Combat ↔ Map:** Travel times create timing gameplay; roads/events change viable compositions; distance defines operational theaters.
- **Tribes ↔ Diplomacy:** States control support rules, shared vision, and target markers; treaties modify morale or war penalties on some worlds.
- **Tribes ↔ Events:** Tribe quests and relic events create shared objectives; reward tribe tech and cosmetics.
- **Worlds ↔ Endgame:** Rule tweaks (speed, night bonus, morale) change pacing and win-condition viability; seasonal worlds align event cadence with end-date.
- **Monetization ↔ Fair Play:** Convenience caps, no direct power sales, and public rules preserve competitive integrity; anti-abuse protects spenders and non-spenders alike.

## Tuning Levers (Per World)
- World speed multipliers (build, recruit, travel, research).
- Resource production rates and storage caps; vault protection %; plunder cap.
- Population cap effects (over-cap penalties, desertion toggles).
- Morale formula (floor, curve) and luck range in combat.
- Night bonus timings and strength; peace windows.
- Wall max level and ram effectiveness; trap availability.
- Scout intel granularity and survival thresholds; watchtower radius.
- Noble coin cost, loyalty hit range, loyalty regen rate, and anti-snipe timers.
- Tribe size cap, support rules (ally/NAP support), tribe tech bonus ceilings.
- Event frequency, token drop rates, and event shop pricing.
- Win condition thresholds (dominance %, wonder stages, relic point rates, season duration).
- Monetization toggles (queue boosts allowed, battle pass availability, cosmetic only), daily spending caps.
- Anti-abuse settings (aid caps, attack caps during protection, captcha triggers).

## Appendices
### Summary Tables
**Resources**

| Resource | Primary Uses | Key Sources | Protection |
| --- | --- | --- | --- |
| Wood | Buildings, infantry, rams | Lumber Camp, tribe aid, plunder | Vault % hidden |
| Clay | Buildings, defense units | Clay Pit, aid, plunder | Vault % hidden |
| Iron | Elite units, siege, academy | Iron Mine, aid, plunder | Vault % hidden |
| Food/Population | Soft cap for units/buildings | Farms/Granary, events | Over-cap penalties |
| Coins (Gold/Silver) | Mint nobles, tribe tech, event sinks | Academy minting, events, shop (capped) | Not plunderable |
| Event Tokens | Limited-time exchanges | Events, quests | Time-limited |
| Season Points | Leaderboards, rewards | Play performance | Not spendable |

**Buildings (core examples)**

| Building | Role | Key Unlocks/Effects | Primary Costs |
| --- | --- | --- | --- |
| Town Hall | Core progression, build speed | Higher building caps | Wood/Clay/Iron |
| Barracks | Infantry training | Spears, Swords | Wood/Clay/Iron/Pop |
| Stable | Cavalry training | Light/Heavy Cav, Scouts | Wood/Clay/Iron/Pop |
| Workshop | Siege production | Rams, Catapults | Wood/Clay/Iron/Pop |
| Academy | Noble training, coin minting | Nobleman | Wood/Clay/Iron/Coins/Pop |
| Wall | Defensive bonus | Defense multiplier | Wood/Clay |
| Storage/Granary | Resource cap | Enables Market/Warehouse levels | Wood/Clay |
| Market | Trade resources | Caravans, market orders | Wood/Clay/Iron |
| Watchtower | Counter-scout, alerts | Early warnings | Wood/Clay/Iron |
| Hospital (optional) | Wounded recovery | Unit refunds over time | Wood/Clay/Iron |

**Units (archetype summary)**

| Unit | Role | Strengths | Weaknesses | Pop | Carry |
| --- | --- | --- | --- | --- | --- |
| Spearman | Cheap defense | Anti-cav, low cost | Low attack | Low | Low |
| Swordsman | Core defense | Balanced defense | Slow, moderate cost | Low | Low |
| Archer (opt) | Ranged defense | Wall synergy | Vulnerable to cav if unprotected | Low | Low |
| Light Cavalry | Fast raid | Speed, good attack | Low defense vs pikes | Med | Med |
| Heavy Cavalry | Premium frontline | High attack/defense | High pop/cost | High | Med |
| Scout | Intel | Reveals info | Dies to counter-scout | Low | Very low |
| Ram | Wall breach | Lowers wall | Weak without escort | High | None |
| Catapult | Building damage | Targets structures | Slow, expensive | High | None |
| Nobleman | Conquest | Lowers loyalty | Very fragile, costly | Very high | None |

**World Types (example presets)**

| World | Speed | Protection | Monetization | Endgame |
| --- | --- | --- | --- | --- |
| Classic | 1x | Standard + night bonus | Cosmetics + capped convenience | Dominance or Wonder |
| Speed | 3x–5x | Shortened | Cosmetic only or light | Season points |
| Casual | 0.75x | Long, safe aid rules | Cosmetic only | Timeboxed peaceful win |
| Hardcore | 1.5x | Minimal | Cosmetics only | Relic control |
| Seasonal | 1x–2x | Standard | Cosmetics + battle pass (no power) | Season leaderboard |

### Glossary
- **K-code:** Continent notation for map coordinates (e.g., K55).
- **Fake:** Low-population attack to draw defenses or burn watchtower charges.
- **Stacking:** Sending large defensive support into a target before an incoming hit.
- **Dodging:** Withdrawing troops before impact to preserve army and counter-attack.
- **Sniping:** Timing support to land between nobles in a noble train.
- **Train:** Multiple noble waves timed seconds apart to drop loyalty quickly.
- **OP (Operation):** Coordinated tribe attack plan with synchronized send times.
- **Barb:** Barbarian village; NPC target for farm/loot.
- **Vault/Hidden:** Building protecting a % of resources from plunder.

### Text Diagrams
**Map Hierarchy Example**
```
[World]
  └─ Continent (e.g., K55)
       └─ Province/Sector
            └─ Tile (x,y) with Village/POI
```

**Conquest Flow (Attacker vs Defender)**
```
Scout -> Read report -> Send fakes + main
  -> Main stack with Rams + Cats + Nobles
    -> Resolve battle (wall/morale/luck)
      -> Loyalty drop (if noble survives)
        -> Capture at loyalty <= 0
          -> Anti-snipe timer + loyalty regen starts
```

**Tribe Ops Timing (simplified)**
```
Plan targets -> Assign waves -> Sync send timers
  -> Launch fakes -> Launch main -> Monitor reports
    -> Stack defenses where needed -> Counter-attack routes
```

