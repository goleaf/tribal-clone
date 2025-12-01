# Worlds & Progression Configuration — Medieval Tribal War Browser MMO

## Configurable World Parameters
- **World Speed Multipliers:** Global modifier for build time, recruitment time, research, and resource production (e.g., 0.75x/1x/2x/4x). Can decouple build vs recruit vs research speeds.
- **Unit Speed Modifier:** Travel time scaling; can diverge from world speed (e.g., fast builds with normal movement for tactical depth).
- **Beginner Protection:** Duration (time- or points-based), early-exit toggle, attack restrictions (outgoing PvP blocked), and relocation window. Optional decay (protection weakens over time).
- **Morale Settings:** On/off; curve variants (linear, logarithmic, floor caps); distance-based morale (frontline fatigue); defender-only or bidirectional.
- **Night Bonus / Peace Windows:** On/off; configurable hours; effect type (defense multiplier, attacker penalty, scout penalty); seasonal/time-zone balanced schedules.
- **Tribe Size Cap:** Max members; alliance limits; invite rules; cooldowns on joining/leaving; contribution minimums for endgame credit.
- **Building Caps:** Max levels per building; soft caps (diminishing returns past level N); world-specific caps on Wall/Watchtower to change siege meta.
- **Unit Caps:** Per-village population cap; hard caps on siege or nobles per village; support cap per target to prevent overstacking.
- **Conquest Rules:** Loyalty drop ranges; noble/enovy costs; anti-snipe timers; minimum clear requirement; grace period after capture; abandon rules.
- **Barbarian/Neutral Growth:** Spawn density; growth rate of barb resources/defense; decay of abandoned player villages; POI spawn (relics, caravans).
- **Scout/Intel Rules:** Report fidelity curves; watchtower radius; sabotage on/off; fog of war density.
- **Luck Range:** Combat randomness band (e.g., ±0%, ±8%, ±15%); can be disabled for competitive worlds.
- **Nightmare/Hardcore Flags:** Higher casualty rates, convoy interception, disabled premium convenience, no luck/morale caps.
- **Economy Tuning:** Resource production rates; storage/vault protection; trade taxes by distance; inflation controls for event shops.
- **Event Cadence:** Frequency/availability of live events and modifiers per world type.
- **Win Condition Settings:** Dominance thresholds, relic/wonder requirements, season length, VP formulas.
- **Premium Rules:** Cosmetic-only, capped convenience, or disabled; spending caps per day; battle pass availability.

## World Archetypes (Suggested Settings)
| Archetype | Audience | Speed | Protection | Morale/Night | Tribe Cap | Conquest | Premium | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Casual | Newer/low-commitment players | 0.75x build/0.75x recruit, 1x move | 5–7 days, points-based exit | Morale on, night bonus on | 25–30 | Loyalty drop low (15–25), regen high | Cosmetics only | Safe pacing, generous vaults, slower barb growth |
| Classic | Veterans seeking legacy feel | 1x/1x/1x | 72h or points | Morale on, night bonus on | 40–50 | Standard nobles (20–35), normal regen | Cosmetics + capped QoL | Balanced barb growth, standard luck ±15% |
| Blitz/Speed | Time-poor but intensity-seeking | 3–5x build/recruit, 2–3x move | 24–36h | Morale off or softened; night off | 30–40 | Lower noble cost; faster regen | Cosmetics only | Short seasons (30–45d), low wall caps |
| Hardcore | High-skill PvP, no crutches | 1–1.5x build, 1x move | 24–48h | Morale off; night off; luck low | 30–35 | High noble cost; low regen; anti-snipe short | Cosmetics only | Higher casualties, convoy interception, limited vault |
| Experimental | Test new rules/modifiers | Variable | Variable | Variable | 30–40 | Novel conquest (influence uptime) | Cosmetics + limited QoL | Rotating mods (fog, weather, beacon play) |
| Seasonal/Ranked | Competitive ladder, fixed end date | 1–2x build/recruit, 1–1.5x move | 48–72h | Morale tuned; night on/off per region | 35–45 | Standard nobles; relic VP | Cosmetics + battle pass (no power) | 60–90d seasons, clear endgame timeline |
| Training | Tutorial-focused | 0.5–0.75x | 7–10d | Morale on; night on | 20–25 | Nobles delayed unlock | Cosmetics only | Heavy guidance, PvP throttles, low plunder |

## Progression Curves & Tuning Philosophies
- **Village Growth Curves:**
  - **Classic S-Curve:** Early acceleration (cheap resource fields), mid slowdown (storage/academy costs), late plateau (diminishing returns).
  - **Steady Climb:** Linear-ish cost scaling; suited for training worlds to reduce frustration.
  - **Front-Loaded Economy:** Fast early economy to get to PvP sooner; higher late-game upkeep to prevent runaway.
- **Troop Growth:**
  - **Population-Gated:** Pop/Food caps drive army size; upkeep penalties over cap on hardcore.
  - **Time-Gated:** Recruit speed is limiting factor; build/recruit speed decoupled enables economy boom without instant armies.
  - **Soft Caps on Siege/Nobles:** Limit per-village siege to avoid one-stack dominance.
- **Map Saturation:**
  - **Slow Fill (Casual/Training):** Lower barb decay, slower spawn of new players to maintain space.
  - **Fast Fill (Blitz/Seasonal):** High barb spawn and decay; aggressive merging of abandoned villages into barb targets.
- **Tribe Consolidation:**
  - **Loose Early:** Lower tribe caps encourage multiple small tribes; later merge via cap increases or alliance slots.
  - **Tight Early:** High tribe cap but strict alliance limit to form strong blocs quickly; suits competitive worlds.
- **Tuning Philosophies:**
  - Encourage multi-village management without endless grind (queue length caps, resource balancers).
  - Preserve scouting and timing skill by keeping movement speeds meaningful even on fast worlds.
  - Keep comeback potential through morale/resolve or objective-based VP (relics) on long worlds.

## World Lifecycle & Phase Design
- **Opening Phase (Week 1):**
  - Spawn rules: fill by sectors, avoid hot drops near top clans; relocation allowed during protection.
  - Bonuses: starter resource boosts, tutorial skips, limited-time build tokens.
  - Events: none or gentle (harvest mini-boost) to not distort starts.
- **Early Rush (Week 2–3):**
  - Protection ends; barb growth tuned for farming; first nobles unlock (academy reachable).
  - Events: scouting mini-challenges; small relic/POI teasers.
  - Spawn: late joiners get catch-up production bonus (seasonal worlds).
- **Mid Stabilization (Week 4–6):**
  - Tribe consolidation; diplomacy settles; first real wars; relic shrines/ancient villages spawn.
  - Adjustments: slight resource curve softening to avoid stall; barb strength increases to keep farming relevant but risky.
  - Events: trade/market events, raider incursions to create pressure.
- **World War Phase (Late Midgame):**
  - Major conflicts over relics/beacons/wonders; map mostly filled.
  - Adjustments: reduced luck range on competitive worlds; enable siege trials; tweak support caps to prevent infinite stacks.
- **Endgame Phase:**
  - Win condition activates (dominance thresholds, wonder/beacons, timed season countdown HUD).
  - Spawns closed; abandoned villages decay faster; event cadence supports endgame objectives (e.g., relic migrations).
  - Communication: persistent endgame bar, daily standings mail.
- **Closure:**
  - Mop-up window 48–72h or immediate shutdown (speed worlds). Hall of Fame entry; reward distribution; optional museum mode.

## Cross-World Features & Fairness
- **Shared Account & Identity:** Unified account, profile, friends, tribe history; cosmetics and titles persist across worlds.
- **Meta Progression:**
  - **Cosmetics/Stats:** Profile titles, frames, report skins; public Hall of Fame entries with world tags.
  - **No Power Carryover:** No resources, troops, or stat bonuses transfer between worlds.
- **World Transfers:** Optional transfer tokens only for cosmetics and name changes; no village/troop transfers.
- **Season Points:** Earned across worlds, used for cosmetic shops; capped to avoid grind incentives.
- **Cross-World Tribes:** Social-only groups spanning worlds for chat and recruitment; no gameplay advantage.

## Implementation TODOs
- [x] Add `world_endpoints`/config schema for tunables (speed, morale/night, tribe caps, conquest rules, win condition model, premium rules). _(config/config.php already centralizes world tunables; add DB schema + JSON schema stub below)_
- [ ] Create archetype templates (Casual/Classic/Blitz/Hardcore/Seasonal/Experimental) to seed new worlds; allow per-field overrides with audit trail.
- [ ] Validation rules: reject incompatible combos (e.g., hardcore with uncapped premiums; conquest disabled but relic endgame enabled); require a win condition.
- [ ] Admin UI: create/edit world configs, schedule start/end dates, preview key derived values (build/recruit times at level 1/10/20, protection duration).
- [ ] Lifecycle service: open/close spawns, manage protection windows, hand off to endgame triggers, transition to cleanup/museum.
- [ ] Metrics: per-world dashboards for growth (villages/tribes), conquests per day, attack/aid caps hit, protection usage, and endgame pacing.
- [ ] Auditing: record every config change with actor, timestamp, before/after; expose read-only history and diff view to ops.
- [ ] Rollout safety: feature flags per archetype and per rule cluster (morale, night, conquest, premium rules, endgame model); default-off for experimental knobs with kill switches.
- [ ] Templates versioning: version archetypes and world configs; lock derived values after world start; allow hotfix to specific tunables with audit (e.g., morale curve).
- [ ] Sim tests: offline simulation to project build/recruit times, resource curves, and conquest pacing for given config; output recommended adjustments before launch.

## Example Player Journeys
- **Casual World (0.75x, Morale/Night On):**
  - Day 1: Player builds slowly, stays in protection; joins a friendly tribe by Day 2; farming barb villages is main loop.
  - Week 2: Protection ends; small skirmishes; nobles expensive, so focus on economy; events are light (harvest boosts).
  - Week 5: Tribe begins first conquest; relic shrines spawn; wars are paced with night bonus providing breathing room.
  - Endgame: Dominance threshold reached slowly; wonder is community-built over a week.
- **Blitz World (4x build/recruit, 2.5x move, Morale Off):**
  - First 24h: Economy ramps instantly; nobles by Day 2; protection brief (24–36h).
  - Week 1: Rapid village captures; map fills; barb growth high. Events minimal to avoid distortion.
  - Week 2: Endgame relic race begins; season lasts ~30–40 days. Constant movement due to faster speeds; no night bonus.
- **Hardcore World (1.5x build, 1x move, Morale/Luck Off, Night Off):**
  - Early: Protection short; high casualties and low vaults make raids punishing. Tribe cap lower to force elite squads.
  - Mid: Siege decisive; support caps prevent turtle stacks; conquest costly with low loyalty regen.
  - Endgame: Beacon network or relic VP to force movement; rewards purely cosmetic.
- **Seasonal Ranked World (1.5x build/recruit, 1x move, Timed 90d):**
  - Early: Standard protection; guided tribe matchmaking. Battle pass cosmetics available.
  - Mid: Crown Trials weekly objectives; relic shrines in central provinces; VP ladder visible.
  - Endgame: Final 10 days with score multipliers on relics; standings update hourly; world closes on timer and enters museum mode.
- **Experimental World (Influence Conquest, Weather Modifiers):**
  - Conquest requires channeling influence post-battle; weather fronts change speed/vision daily.
  - Players test new scouting fidelity rules; feedback gathered; rewards cosmetic for participation.
