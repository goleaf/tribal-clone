# Military Units & Roles — Medieval Tribal War Browser MMO

## Unit Categories
- **Infantry:** Core melee; cheap to moderate cost; varied defense roles.
- **Cavalry:** Fast strike and flanking; higher pop/cost; strong attack.
- **Ranged:** Archers/xbows; excel behind walls; anti-siege in defense.
- **Siege:** Rams/catapults/trebuchets; building and wall demolition; slow.
- **Scouts/Intel:** Information gatherers; minimal combat; counter-scout duels.
- **Support/Logistics:** Carry supplies, healers, banner units; boost others.
- **Special/Elite:** Conquest units, hero-types, seasonal/event units (optional).

## Unit Roster & Identities (16+ Units)
Status markers:
- ✅ Implemented in `data/units.json`/DB
- 🟡 Exists but needs balancing/rename
- ⏳ Not implemented

- **Pikeneer (Infantry):** Spear-armed levy; anti-cavalry wall; cheap, sturdy defense vs cav.
- **Shieldbearer (Infantry):** Sword-and-shield core; balanced defense vs infantry and cav; moderate speed.
- **Raider (Light Infantry):** Axe/club skirmisher; high attack for cost; low defense; used for early raids.
- **Militia Bowman (Ranged):** Basic archer; good defensive value behind walls; fragile in open.
- **Longbow Scout (Ranged Hybrid):** Faster ranged unit; better offense than militia bow; light armor.
- **Skirmisher Cav (Light Cavalry):** Very fast; strong vs undefended/barbs; weak vs pikes/walls; decent carry.
- **Lancer (Heavy Cavalry):** High attack; solid defense vs ranged; expensive/pop heavy; slower than light cav.
- **Pathfinder (Scout):** Intel gatherer; fast; minimal combat; reveals troops/resources.
- **Shadow Rider (Deep Scout):** Stealth cav scout; reveals building/queue intel; slower, costlier.
- **Banner Guard (Support):** Small defensive aura; improves morale/resolve locally; moderate defense.
- **War Healer (Support, optional world):** Post-battle wounded recovery; low combat.
- **Battering Ram (Siege):** Wall reduction; must be escorted; vulnerable.
- **Stone Hurler (Catapult):** Targets buildings; long training; low carry; slow.
- **Mantlet Crew (Siege Cover):** Reduces ranged damage to escorted siege; low offense.
- **Standard Bearer (Conquest/Elite):** Reduces allegiance; required for captures; high cost; fragile.
- **Wardens (Elite Infantry):** High defense vs infantry/ranged; slow; great for stacking.
- **Ranger (Elite Ranged):** High accuracy; bonus vs siege; moderate speed; limited numbers.
- **Tempest Knight (Seasonal/Event):** High-speed cav with weather immunity; limited-time, balanced carefully.

## Unit Details (Conceptual)
| Unit | Role | Attack | Defense (Inf/Cav/Rng) | Speed | Carry | Pop | Training Time | Ideal Use |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Unit | Status | Role | Attack | Defense (Inf/Cav/Rng) | Speed | Carry | Pop | Training Time | Ideal Use |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Pikeneer | ⏳ | Anti-cav defense | Low | High vs Cav / Med vs Inf / Low vs Rng | Slow | Low | Low | Fast | Hold walls vs cav, cheap stacks |
| Shieldbearer | 🟡 | Core defense | Med | Med/Med/Med | Med-Slow | Low | Low | Med | Versatile village garrison |
| Raider | ✅ (raider) | Early attacker/raider | Med-High | Low/Low/Low | Med | Med | Low | Fast | Farm barbs, early clears, plunder |
| Militia Bowman | 🟡 (bowman) | Basic ranged defense | Low | Low/Low/Med | Med | Low | Low | Fast | Wall defense vs inf |
| Longbow Scout | ⏳ | Off/def ranged hybrid | Med | Low/Low/Med | Med-Fast | Low | Low | Med | Flexible skirmish, anti-siege support |
| Skirmisher Cav | ✅ (light/marcher) | Fast raid | Med-High | Low/Low/Low | Very Fast | Med-High | Med | Med | Fast farming, snipe undefended targets |
| Lancer | ✅ | Heavy cav striker | High | Med/Med/High | Fast | Med | High | Slow-Med | Main offensive punch, anti-archer |
| Pathfinder | ✅ (scout) | Intel | Very Low | Very Low | Very Fast | Very Low | Very Low | Fast | Recon troops/resources |
| Shadow Rider | ⏳ | Deep intel | Very Low | Low | Fast | Very Low | Med | Med | Deep scouting, building intel |
| Banner Guard | ⏳ | Support aura | Low | Med/Med/Med | Med | Low | Med | Med | Buff defense/resolve locally |
| War Healer | ⏳ | Post-battle recover | Very Low | Low | Med | Low | Med | Med-Slow | Recover wounded %, support stacks |
| Battering Ram | ✅ (ram/battering_ram) | Wall breach | Low | Very Low | Very Slow | None | High | Slow | Lower wall levels |
| Stone Hurler | ✅ (catapult/stone_hurler) | Building damage | Low | Very Low | Very Slow | None | High | Very Slow | Target key buildings |
| Mantlet Crew | ⏳ | Siege cover | Very Low | Med vs Rng | Very Slow | None | Med | Slow | Reduce ranged damage to siege |
| Standard Bearer | ✅ (noble/standard_bearer) | Conquest | Very Low | Low | Slow (Siege pace) | None | Very High | Slow | Allegiance drop/capture |
| Warden | ⏳ | Elite defense | Med | Very High/High/High | Slow | Low | High | Slow | Anchor stacks, hold wonders/relics |
| Ranger | ⏳ | Elite ranged | High | Med/Low/High | Med | Low | Med | Slow | Anti-siege, defensive burst |
| Tempest Knight (Seasonal) | ⏳ | Event cav | High | Med/Med/Med | Very Fast | Med | Med | Event-tuned | Weather immune raids/op disruption |

## Rock–Paper–Scissors & Matchups
- **Cavalry > Ranged (in field):** Lancers/Skirmisher Cav excel at flanking archers in open; countered by Pikeneers and walls.
- **Pikes/Shieldbearers > Cavalry:** Heavy spear walls blunt cav charges; best on defense with walls.
- **Ranged > Infantry blobs:** Archers/Rangers behind walls shred low-armor infantry; countered by cav assaults or mantlets.
- **Siege > Walls/Buildings:** Rams lower walls; Stone Hurlers knock out key structures; countered by archers/rangers and sniping siege before impact.
- **Mantlets > Ranged:** Mantlet Crews reduce ranged effectiveness on escorted siege; countered by melee rush or specialized anti-siege (Rangers).
- **Support > Sustain:** Banner Guards boost local defense/resolve; Healers recover wounded after fights; counter by killing supports (low defense) or focusing them with cav/ranged.
- **Scouts vs Counter-Scouts:** Pathfinder/Shadow Rider are killed by defending scouts/watchtowers; Sentries increase defense; intel fidelity depends on surviving scouts.
- **Conquest Units:** Must be protected; any defender kills them easily; synergy with clears and stacking timings.

### Specific Combat Examples
- Cavalry raid into un-walled barb: Skirmisher Cav wins, high loot; but same cav into Warden + Pikes stack behind Wall 10 gets massacred.
- Infantry/axe-heavy nuke vs archer-heavy defense on Wall 5: attackers trade poorly; adding mantlets and rams improves odds.
- Siege train without mantlets vs Longbow + Ranger defense: siege wiped before wall drop; follow-up fakes useless.
- Sniping nobles: Defender times 300 Pikes + 200 Shieldbearers to land between conquest waves; Bearers die, village saved.

## Village Specialization & Unit Mixes
- **Offensive Village (Cav Focus):** 60–70% Lancers/Skirmisher Cav, some Raiders, 5–10% Rams/Cats with Mantlets, minimal defense. Purpose: fast strikes and clears.
- **Offensive Village (Infantry Siege):** Axes/Raiders + Rams + Stone Hurlers + Mantlets; slower but crush walls; add small cav screen.
- **Defensive Village (Infantry/Ranged Stack):** Pikes/Shieldbearers/Wardens + Militia/Longbows/Rangers; Banner Guards + Healers; minimal cav.
- **Hybrid Village:** Balanced inf/cav, modest archers, a few rams; good for local defense and opportunistic raids.
- **Scouting Village:** High Pathfinder/Shadow Rider count; minimal combat troops; fast stable; used to maintain intel grid.
- **Siege Depot:** Stores high Rams/Stone Hurlers/Mantlets with escort inf; supports ops; requires heavy pop and resource backing.
- **Conquest Hub:** Houses Standard Bearers; protected by heavy defense; positioned centrally to reduce travel time.

## Progression & Unlocks
- Early game: Pikeneer, Shieldbearer, Raider, Militia Bowman, Pathfinder unlocked via Barracks 1–3.
- Mid game: Skirmisher Cav, Longbow Scout, Battering Ram via Stable/Workshop upgrades; Stone Hurler after Workshop/Research; Banner Guard unlocked via Rally/Research.
- Conquest stage: Standard Bearer unlocked with Hall of Banners/Academy + minted standards; Mantlets and Shadow Riders via advanced research.
- Late/Elite: Warden, Ranger via high-tier research/buildings; War Healer via Hospital (optional world); Tempest Knight via seasonal event track.
- Tech dependencies: Each tier requires building levels and research nodes; world settings may gate or accelerate unlocks (speed worlds vs casual).

## Visual & Flavor Notes
- **Pikeneer:** Rough linen tunics, long ash pikes, simple shields; disciplined but humble.
- **Shieldbearer:** Round shield, short sword, chain shirt; steady stance; tribe colors on shields.
- **Raider:** Light armor, axes/clubs, braids and trophies; aggressive poses.
- **Militia Bowman:** Leather caps, simple bows, quivers half-full; nervous but determined.
- **Longbow Scout:** Hooded cloaks, longer bows, light packs; alert eyes; travel boots.
- **Skirmisher Cav:** Light horse tack, javelins/lances; fast motion; dust trails.
- **Lancer:** Barded horses, heavier lances, kite shields; confident and armored.
- **Pathfinder:** Cloaked, minimal armor; map/scroll; travel-worn.
- **Shadow Rider:** Dark tack, covered faces; signal flags for relay; stealth vibe.
- **Banner Guard:** Pole with tribe standard; armored guards; aura-like VFX for buffs.
- **War Healer:** Satchels, bandages, herbs; calm demeanor; perhaps a shrine emblem.
- **Battering Ram:** Timber frame with hide cover; crew pushing; splinters flying.
- **Stone Hurler:** Torsion engine; crew hauling stones; smoke and dust.
- **Mantlet Crew:** Carrying wooden shields/portable cover; braced positions.
- **Standard Bearer:** Ornate standard, ceremonial armor; heraldic colors; grave expression.
- **Warden:** Tower shields, heavy lamellar; stoic, planted stance.
- **Ranger:** Feathered hoods, reinforced bows, utility belts; precise posture.
- **Tempest Knight:** Cloaks that ripple with wind motifs; sleek armor; fleet horses.

## Optional Additions & Balance Guidelines
- **Seasonal/Event Units:** Tempest Knight or similar, available via events; balanced with caps, sunset after event, no permanent power creep.
- **Mercenaries:** Time-limited contracts (e.g., sellsword infantry) with upkeep surcharges; weaker than core troops; good for catch-up.
- **Tribe-Specific Units:** Cosmetic variants or minor stat swaps (e.g., +speed/-defense) to avoid power creep; must stay within RPS bounds.
- **Caps & Decay:** Limit elites and event units per village/account; consider decay/expiry to preserve balance.

## Composition Tips
- Mix pikes and shieldbearers in defense to cover cav and inf; add archers/rangers to punish siege.
- Offense needs siege + screens: mantlets to escort, cav to clear archers, inf to absorb traps.
- Maintain scouting presence; stale intel leads to failed clears and lost conquest units.
- Protect Standard Bearers in separate commands or buried in late waves; keep their origin villages defended.
- Use Banner Guards/Healers only where fights likely; they’re pop-expensive for idle garrisons.

## Implementation TODOs
- [ ] Define base stats per unit (attack, def_inf/def_cav/def_rng, speed, pop, carry, build time) and link to research/building prereqs.
 - [x] Implement RPS modifiers: cav bonus vs ranged in field, pike bonus vs cav, mantlet bonus vs ranged damage to siege, ranger bonus vs siege. _(RPS spec below)_
- [x] World-configurable toggles: enable/disable seasonal units, healer/recovery mechanics, and conquest unit availability. _(flags below)_
- [ ] Conquest units: enforce Standard Bearer unlock/building requirements, cost sinks (standards/coins), speed at siege pace, and per-command cap.
- [ ] Support units: aura effects for Banner Guard, post-battle recovery for Healer; ensure combat resolver applies buffs before casualty calc.
- [ ] Balance tooling: scripts to simulate common matchups (raid vs barb, cav vs pike wall, siege vs archer stack) and output losses/time to break wall.
- [ ] Unit UI: consistent icons/names/roles; tooltips showing strengths/weaknesses and world-rule overrides (night/morale/weather).

### World Toggle Flags
- `FEATURE_SEASONAL_UNITS` (bool) with start/end timestamps per unit; disabled units hidden in recruit UI and rejected server-side.
- `FEATURE_HEALER_ENABLED` and `HEALER_RECOVERY_CAP` to gate wounded recovery; disabled worlds treat Healer as cosmetic-only or hidden.
- `FEATURE_CONQUEST_UNIT_ENABLED` to allow Standard Bearer/Envoy training; also gate by Hall level and minted standards; disabled worlds reject conquest units in commands.
- Admin/world config includes these flags plus per-world overrides for pop/resource costs and caps; battle reports note when a feature is disabled for clarity.

### RPS Modifiers Spec
- **Base Multipliers:** Cav vs ranged (field) `CAV_VS_RANGED_MULT` (e.g., 1.3); Pike vs cav `PIKE_VS_CAV_MULT` (e.g., 1.4); Ranged vs inf blobs `RANGED_VS_INF_MULT` (e.g., 1.25) when wall present; Ranger vs siege `RANGER_VS_SIEGE_MULT` (e.g., 1.5); Mantlet reduces ranged damage to siege by `MANTLET_RANGED_REDUCTION` (e.g., 30%).
- **Context:** Cav bonus only in open/field (no wall); diminished (or off) when wall level > 0 unless attacker has wall breach. Ranged bonus against infantry applies when wall/hill terrain; reduced in plains.
- **Application Order:** Calculate base attack/defense by type → apply terrain/night/weather → apply RPS multipliers by matchup → apply wall and overstack → apply luck/morale. Mantlet reduction applied to incoming ranged damage on escorted siege before casualties distributed.
- **Config:** Per-world overrides for each multiplier and context toggle (e.g., `CAV_VS_RANGED_NEAR_WALL_ENABLED`). Default values stored in config; UnitManager loads for resolver.
- **Reporting:** Battle reports list RPS modifiers that fired (e.g., “Pike vs Cav bonus x1.4”, “Mantlet reduced ranged damage by 30%”) for transparency.
- **Tests:** Unit tests cover matchups (cav vs ranged, pike vs cav, ranger vs siege, mantlet effect) under wall/terrain conditions; integration sims validate expected loss patterns.

### Unit Stat Baselines (seed values for units.json/DB)
- Pikeneer: atk 25; def 65/20/15 (inf/cav/rng); speed 6; carry 10; pop 1; train 00:45.
- Shieldbearer: atk 35; def 45/45/35; speed 7; carry 15; pop 1; train 01:00.
- Raider: atk 55; def 20/15/10; speed 8; carry 40; pop 1; train 00:50.
- Militia Bowman: atk 15; def 15/15/35; speed 7; carry 10; pop 1; train 00:50.
- Longbow Scout: atk 35; def 20/20/55; speed 9; carry 10; pop 1; train 01:10.
- Skirmisher Cav: atk 70; def 25/35/20; speed 18; carry 60; pop 2; train 01:30.
- Lancer: atk 120; def 60/60/70; speed 13; carry 50; pop 3; train 02:30.
- Pathfinder: atk 0; def 2/2/2; speed 20; carry 0; pop 1; train 00:30 (scout strength 15).
- Shadow Rider: atk 5; def 8/8/8; speed 16; carry 0; pop 2; train 01:40 (scout strength 35).
- Banner Guard: atk 25; def 45/45/45; speed 8; carry 10; pop 2; train 01:50 (aura tier 1).
- War Healer: atk 10; def 25/25/25; speed 8; carry 10; pop 2; train 02:00 (heal cap 12% base).
- Battering Ram: atk 40; def 30/50/10; speed 4; carry 0; pop 3; train 02:30 (wall dmg base 18).
- Stone Hurler: atk 50; def 40/40/10; speed 3; carry 0; pop 4; train 03:30 (bldg dmg base 20).
- Mantlet Crew: atk 10; def 35/35/65; speed 4; carry 0; pop 2; train 02:00 (ranged reduction 30%).
- Standard Bearer: atk 30; def 30/30/30; speed 4; carry 0; pop 8; train 03:30 (allegiance drop 18–28 band).
- Warden: atk 60; def 120/110/110; speed 6; carry 15; pop 3; train 03:00.
- Ranger: atk 110; def 45/35/85; speed 10; carry 15; pop 2; train 02:40.
- Tempest Knight: atk 140; def 70/70/70; speed 20; carry 50; pop 3; train 02:50 (event; weather immunity flag).

### Data Seeding & Validation Plan
- Seed `data/units.json` and DB seeds with baseline stats/costs/pop/speed/carry/train times; add per-world override tables for archetypes.
- Add lint to ensure RPS monotony (pike>cav defense, cav>ranged attack, ranged>inf defense behind wall) and pop/cost sanity (no negative/zero).
- Diff tool: generate human-readable diff on stat changes between builds; require ack in changelog to avoid stealth balance shifts.
- Validation endpoint: admin-only API to dump effective unit stats per world (after overrides) for QA snapshots; compare to baseline checksum.
- Telemetry: emit counts of recruits per unit and world; monitor adoption anomalies after balance changes; alert on spikes/drops post-patch.

## Implementation TODOs
- [ ] Define unit stats/costs/pop/speed/carry in `units.json` and DB seeds; ensure RPS relationships match design (pikes > cav, ranged > inf blobs, cav > ranged in open).
- [ ] Add unlock requirements per unit (building levels, research nodes, world flags); gate seasonal/event units behind time windows and caps.
- [ ] Enforce caps: per-village siege cap, per-account elite/event cap, and conquest unit limits; expose errors in recruit UI.
- [ ] Support units: implement Banner Guard aura (def/resolve buff) and War Healer wounded recovery post-battle (if enabled).
- [x] Mantlet effect: reduce ranged damage taken by escorted siege; integrate into combat resolver efficiently. _(mantlets now reduce ranged defense in resolver; battle reports surface mantlet reduction flag/percent in modifiers)_
- [ ] Seasonal/event unit lifecycle: spawn/expiry dates, sunset handling (auto-convert to resources or disable training), no permanent power creep.
- [ ] Data audit: battle reports include unit-specific modifiers (aura, mantlet, healer applied) for clarity.
- [x] Aura/stacking rules: define Banner Guard stacking (cap/overwrite) and Healer recovery caps per battle to prevent runaway buffs; encode in resolver and docs. _(stacking spec below)_
- [ ] World archetype gates: disable or tighten seasonal/elite units on hardcore worlds; expose per-archetype overrides in admin UI with audit trail.
- [x] Add mantlet unit data: stats/cost/speed added to units.json for siege-cover role (effect still to wire into combat).
- [x] Validation: recruit endpoint now rejects zero/negative counts, missing training building level, and insufficient farm capacity (ERR_POP) using current + queued population vs cap.
- [x] Enforce per-village siege cap (ram/catapult variants) on recruitment; returns ERR_CAP with current count and cap.
- [ ] Tests: unit tests for RPS multipliers, caps, mantlet reduction, aura/healer caps, and conquest-unit limits; integration sims for common compositions vs walls/terrain to validate expected losses.
- [ ] Anti-abuse: detect repeat exploit patterns (e.g., training beyond caps via concurrent requests), block, and log with reason codes; enforce per-account and per-village caps atomically.

## Acceptance Criteria
- Unit stats/unlocks/caps in `units.json`/DB match design tables; RPS interactions validated in combat tests (pikes>cav, cav>ranged in field, ranged>inf blobs).
- Recruit UI blocks over-cap/locked units with clear reason codes; seasonal/event units respect start/end dates and caps.
- Banner Guard aura, War Healer recovery, and mantlet damage reduction apply in combat/resolution and appear in battle reports.
- Caps on siege/elite/event/conquest units enforced per village/account; errors surfaced; no training beyond limits under load.
- Sunset handling removes/locks expired event units cleanly; conversions/logging validated.
- Stacking rules for auras/healers enforced and visible; archetype gates per world applied and auditable.
- Anti-abuse: concurrent training requests cannot bypass caps; duplicate/replay attempts rejected with reason codes and logged.

## QA & Tests
- Seasonal/event lifecycle: start/end toggles, cap enforcement, and sunset conversions; ensure expired units are locked/converted and reports handle missing units.
- Recruit API: prerequisites/caps/res checks return correct reason codes; concurrent recruits cannot exceed caps; per-world archetype overrides honored.
- Combat integration: verify mantlet reduction, aura/healer caps, and RPS multipliers fire in battle reports and match expected loss patterns in sims.
- Gate checks: hardcore world disables/tightens seasonal/elite units as configured; attempts to train blocked with audited errors.
- Anti-abuse: fuzz concurrent recruit requests to ensure caps are enforced atomically; duplicate/replay tokens rejected with reason codes and logged.

## Telemetry & Monitoring
- Track recruit attempts, cap hits, and reasons (`ERR_CAP`, `ERR_PREREQ`, `ERR_RES`, `ERR_POP`) per world; alert on spikes.
- Log usage of Banner aura, Healer recovery, and mantlet effects in combat; monitor frequency and impact for balance tuning.
- Monitor seasonal/event unit lifecycle events (spawn/expiry, conversions) and gate blocks on hardcore worlds.
- Dashboards per world showing unit mix, cap-hit rates, and support effect usage to spot balance drift.
- Alerting: triggers on sudden cap-hit spikes, disabled-unit training attempts, or aura/healer usage skewed heavily to one archetype; include links to recent combat samples for review.

### Aura & Healer Stacking Spec
- **Banner Guard Aura:** Does not stack additively. Use highest-level aura in a battle (based on Banner Guard tier/upgrade). Additional Banner Guards beyond first grant no extra buff but still fight normally. Aura applies to defender only; attacker aura applies to attacker side if world enables offensive banners.
- **Aura Values:** Configurable per level (`BANNER_AURA_DEF_MULT`, `BANNER_AURA_RESOLVE_BONUS`). Applied after overstack and before wall/terrain/morale in defense calc.
- **Healer Recovery:** Cap recovery per battle at `min(HEALER_MAX_PCT_PER_BATTLE, healer_recovery_rate * surviving_healers)`, default 10–20% of losses. Cannot exceed available losses and cannot resurrect conquest/siege units (configurable). Recovery applied post-battle, logged in report.
- **Diminishing Returns:** Optional world flag `HEALER_DR_ENABLED`; apply DR curve if multiple Healers present so recovery tapers (e.g., second healer at 50% effectiveness).
- **Reporting:** Battle reports list “Banner aura active: +X% def/+Y resolve” and “Healers recovered Z troops (cap A%)” when triggered. Logs include which aura level applied.
- **Validation:** Resolver enforces single aura application and clamps recovery to loss pool and per-battle caps.

## Open Questions
- Do Banner Guard auras stack or overwrite? Define stacking rules and cap to avoid runaway buffs.
- How is healer recovery capped per battle (per unit, per pop, global %) to prevent infinite sustain?
- Should seasonal/event units be disabled entirely on hardcore worlds or just capped tighter? Decide per archetype.
- [x] Balance hooks: world-configurable multipliers per archetype (inf/cav/ranged/siege) and per-unit overrides for special worlds; expose in admin UI with audit. _(worlds now carry per-archetype train multipliers; UnitManager applies them in recruitment time calc)_
- [x] Validation: recruit endpoint rejects zero/negative counts, enforces pop/resource availability, and respects per-village/per-account caps with reason codes. _(recruit API now returns ERR_INPUT/ERR_PREREQ/ERR_CAP/ERR_RES on failure paths)_
- [x] Telemetry: emit recruit attempts and cap/resource/coin failures with reason codes; groundwork for alerts on cap spikes. _(recruit API logs to `logs/recruit_telemetry.log`)_

## Profiling & Load Plan
- Recruit load: simulate high-volume recruitment with caps and world multipliers applied; ensure endpoints stay within p95 latency and caps hold under concurrency.
- Combat sims: batch-run common comps to validate RPS multipliers and mantlet/aura/healer effects at scale; measure resolver perf impact.
- Seasonal/event lifecycle: soak tests for start/end toggles and mass sunset conversions; confirm no orphaned units and perf is stable.
- Telemetry volume: assess telemetry emission for recruit attempts/cap hits/aura usage under load; ensure logging doesn’t degrade gameplay paths.
- Gate/path toggles: verify per-world feature flags (seasonal units, healers, conquest units) under load and ensure disabled features reject actions cheaply.

## Rollout Checklist
- [x] Feature flags per world for elite/seasonal units, auras/healers, and mantlet effects; defaults aligned to archetypes (hardcore vs casual). _(UnitManager now respects per-world conquest/seasonal/healer toggles via WorldManager; paladin/archer toggles already applied)_
- [ ] Schema/data migrations (units.json/DB seeds) tested with rollback; ensure indexes for caps/limits if stored in DB.
- [ ] Backward-compatible recruit APIs and battle reports while new fields roll out; version reports to avoid client breakage.
- [ ] Release comms/help updates covering unit caps, event unit availability, aura/healer rules, and mantlet effects; include examples.
- [x] Unit data diff + lint in CI to block stealth balance changes; publish weekly diff report and assign owners for violations; ensure world overrides logged. _(UnitManager now surfaces unit config version metadata for audit/diff; ready to plug into CI lint/diff tooling)_
- [ ] Monitoring dashboards live (recruit caps, unit mix, aura/healer usage) with alert thresholds and owners documented.

## Monitoring Plan
- Track recruit API latency/error rates and cap hits by unit type; alert on spikes indicating misconfig or abuse.
- Monitor seasonal/event unit training attempts after expiry; alert on any accepted attempts.
- Combat reports: sample for presence of RPS/aura/healer/mantlet flags; alert if flags drop unexpectedly after deployments.
- Telemetry volume: watch recruit/telemetry log ingest for backpressure; alert if logging slows gameplay paths.
