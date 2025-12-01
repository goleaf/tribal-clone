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
- [ ] Implement RPS modifiers: cav bonus vs ranged in field, pike bonus vs cav, mantlet bonus vs ranged damage to siege, ranger bonus vs siege.
- [ ] World-configurable toggles: enable/disable seasonal units, healer/recovery mechanics, and conquest unit availability.
- [ ] Conquest units: enforce Standard Bearer unlock/building requirements, cost sinks (standards/coins), speed at siege pace, and per-command cap.
- [ ] Support units: aura effects for Banner Guard, post-battle recovery for Healer; ensure combat resolver applies buffs before casualty calc.
- [ ] Balance tooling: scripts to simulate common matchups (raid vs barb, cav vs pike wall, siege vs archer stack) and output losses/time to break wall.
- [ ] Unit UI: consistent icons/names/roles; tooltips showing strengths/weaknesses and world-rule overrides (night/morale/weather).

## Implementation TODOs
- [ ] Define unit stats/costs/pop/speed/carry in `units.json` and DB seeds; ensure RPS relationships match design (pikes > cav, ranged > inf blobs, cav > ranged in open).
- [ ] Add unlock requirements per unit (building levels, research nodes, world flags); gate seasonal/event units behind time windows and caps.
- [ ] Enforce caps: per-village siege cap, per-account elite/event cap, and conquest unit limits; expose errors in recruit UI.
- [ ] Support units: implement Banner Guard aura (def/resolve buff) and War Healer wounded recovery post-battle (if enabled).
- [ ] Mantlet effect: reduce ranged damage taken by escorted siege; integrate into combat resolver efficiently.
- [ ] Seasonal/event unit lifecycle: spawn/expiry dates, sunset handling (auto-convert to resources or disable training), no permanent power creep.
- [ ] Data audit: battle reports include unit-specific modifiers (aura, mantlet, healer applied) for clarity.

## Acceptance Criteria
- Unit stats/unlocks/caps in `units.json`/DB match design tables; RPS interactions validated in combat tests (pikes>cav, cav>ranged in field, ranged>inf blobs).
- Recruit UI blocks over-cap/locked units with clear reason codes; seasonal/event units respect start/end dates and caps.
- Banner Guard aura, War Healer recovery, and mantlet damage reduction apply in combat/resolution and appear in battle reports.
- Caps on siege/elite/event/conquest units enforced per village/account; errors surfaced; no training beyond limits under load.
- Sunset handling removes/locks expired event units cleanly; conversions/logging validated.
- [ ] Balance hooks: world-configurable multipliers per archetype (inf/cav/ranged/siege) and per-unit overrides for special worlds; expose in admin UI with audit.
- [ ] Validation: recruit endpoint rejects zero/negative counts, enforces pop/resource availability, and respects per-village/per-account caps with reason codes.
- [ ] Telemetry: emit recruit attempts, cap hits, and aura/mantlet/healer usage; alert on cap-hit spikes or disabled unit training errors.
