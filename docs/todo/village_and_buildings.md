# Village & Building System — Medieval Tribal War Browser MMO

## Village Concept
- A village is a player-controlled settlement that produces resources, trains troops, and serves as a node on the world map.
- Players start with one village and can own many over time through conquest or founding (world-configurable). Soft caps and upkeep keep mega-empires in check.
- **Archetypes:**
  - **Offensive Hub:** Prioritizes unit production (Barracks/Stable/Workshop), siege, and rally speed.
  - **Defensive Bastion:** High Wall/Watchtower/Hospital, dense infantry/ranged production.
  - **Support/Production Village:** Focus on resource fields, Storage/Granary, Market, Hospital, logistics buildings.
  - **Scouting/Intel Post:** Watchtower, Scout Hall, pathfinder stables, intel tech focus.
  - **Conquest/Capital:** Hall of Banners (Academy), Standard Bearer production, high defenses, command center.
  - **Special Villages:** Event-linked outposts, relic shrines, or regional resource bias.

## Building List (20+ Types)
- **Town Hall (Headquarters)**
- **Lumber Yard**
- **Clay Pit**
- **Iron Mine**
- **Farm/Granary** (population/food cap)
- **Storage/Warehouse**
- **Vault/Hidden Storage**
- **Barracks** (Infantry)
- **Garrison** (Advanced Infantry/Defense)
- **Stable** (Cavalry)
- **Scout Hall** (Intel units)
- **Workshop** (Siege Frames/Rams/Mantlets)
- **Siege Foundry** (Catapults/Stone Hurlers)
- **Hall of Banners (Academy analog)**
- **Rally Point/Command Post**
- **Wall**
- **Watchtower/Beacon Spire**
- **Market/Caravanserai**
- **Hospital/Healer’s Lodge**
- **Temple/Shrine** (blessings, allegiance aura if enabled)
- **Library/Research Hall** (tech unlocks)
- **Blacksmith/Armory** (unit improvements if world uses blacksmith model)
- **Outpost/Encampment** (temporary build slot for forward bases; optional)
- **Decor/Cosmetic Plazas** (cosmetic only)

## Building Details Table
| Building | Purpose & Effects | Unlock Conditions | Upgrade Effects | Synergies & Strategic Use |
| --- | --- | --- | --- | --- |
| Town Hall | Core progression; reduces build times; unlocks higher building caps | Starting building | Faster construction; more build queue slots at milestones | Enables rapid growth; prerequisite for military buildings |
| Lumber Yard | Produces Wood | None | Higher production/hour | Synergy with Storage; key early priority |
| Clay Pit | Produces Clay | None | Higher production/hour | Walls/storage heavy builds rely on Clay |
| Iron Mine | Produces Iron | None | Higher production/hour | Late-game troop costs; prioritize mid-late |
| Farm/Granary | Increases population/food cap | None | Raises pop cap; small production bonus | Controls army size; essential before troop surges |
| Storage/Warehouse | Raises resource caps | None | Higher cap; small efficiency bump | Prevent overflow; prerequisite for Market |
| Vault/Hidden Storage | Protects % of resources from plunder | Storage level N | More protected resources | Anti-raid; useful for farmers and during wars |
| Barracks | Trains basic infantry (Pikeneer, Shieldbearer, Raider) | Town Hall 2–3 | Faster training; unlocks advanced infantry at higher levels or via tech | Offensive/defensive core; multiple barracks in offense villages (if world allows parallel) |
| Garrison | Trains/boosts elite infantry (Wardens) | Barracks + Research | Defensive bonuses; faster elite training | Defense villages anchor; synergy with Hospital |
| Stable | Trains cavalry (Skirmisher Cav, Lancer, scouts) | Town Hall + Barracks | Faster training; unlocks heavier cav | Offense hubs; scouting outposts |
| Scout Hall | Trains Pathfinders/Shadow Riders; intel tech | Stable + Watchtower | Better scout training speed; intel fidelity boosts | Intel-focused villages; pairs with Watchtower |
| Workshop | Produces Rams/Mantlets | Town Hall + Barracks/Stable | Faster siege frame output | Offense hubs; needed to crack walls |
| Siege Foundry | Produces Stone Hurlers/Catapults | Workshop + Research | Faster catapult build; accuracy boosts | Target buildings; endgame sieges |
| Hall of Banners | Mints standards/coins; trains Standard Bearers (conquest) | Town Hall high level + Research | Faster minting; higher noble cap (if any) | Conquest hubs; must be protected |
| Rally Point/Command Post | Send commands; manage presets; view incoming/outgoing | Town Hall | More presets; formation options; command overview | Ops coordination; speed presets |
| Wall | Defense multiplier; casualty reduction | Town Hall + Clay Pit | Higher defense bonus; trap slots | Defense villages; slows clears |
| Watchtower/Beacon Spire | Early warning, scout defense, intel radius | Wall level; Scout Hall synergy | Larger detection radius; reveals noble-bearing commands | Critical for frontline; counters fakes |
| Market/Caravanserai | Trade resources; send aid | Storage level N | More caravans; lower tax; faster caravans | Support/logistics villages; tribe aid hub |
| Hospital/Healer’s Lodge | Recovers % wounded after battles | Town Hall + Shrine/Research | Higher recovery %, faster heal | Defense/offense sustain; pairs with Warden/Garrison |
| Temple/Shrine | Provides blessings, allegiance regen buffs (if enabled) | Town Hall + Farm | Stronger blessings; larger radius | Conquest defense; morale/resolve buffs |
| Library/Research Hall | Unlocks tech (mantlets, elites, intel upgrades) | Town Hall + Market | Faster research; more research slots | Strategic tech pacing; intel/offense boosts |
| Blacksmith/Armory | Improves unit equipment (world-optional) | Barracks/Stable + Research | Small % attack/defense boosts; capped | Offensive focus; careful balance |
| Outpost/Encampment | Temporary forward build for staging troops | Special item/tech | Limited training; expires | Ops staging; risky but potent |
| Cosmetic Plaza | Cosmetic only; placeable | None | Unlocks cosmetic slots | Player expression; no gameplay |

## Upgrade System
- **Levels:** Most buildings 1–20 (configurable). Costs/time scale exponentially but with soft knees to keep midgame flowing.
- **Queues:**
  - Base 1 construction slot; Town Hall milestones add more slots or enable parallel queues (world setting). Premium can add capped extra slots (QoL, not speed multipliers).
  - Cancel returns partial resources; confirmation to prevent misclicks.
- **Parallel Construction:** Optional world rule: allow one resource building + one military building simultaneously; avoids dead time.
- **Speed-Ups:** Limited, capped build speed tokens (+10% for 30m) from events/quests; no instant completes beyond rare tutorial tokens. Tribe bonuses provide small % reductions.
- **Scaling:** Higher levels demand more pop; overbuilding without Farm upgrades can stall production. Costs weighted by resource type to shape scarcity (walls clay-heavy, siege iron-heavy).

## Village Specialization & Build Orders
### Offensive Hub (Cav/Siege)
- Prioritize: Town Hall → Barracks/Stable → Farm → Storage → Workshop → Siege Foundry → Hall of Banners.
- Keep resource fields mid-level (8–12) then funnel via trades from support villages.
- Early build order: TH2 → Barracks1 → Farm2 → Lumber/Clay/Iron to 4–5 → Stable → Storage → Workshop → Wall minimal (2–3).

### Defensive Bastion
- Prioritize: Wall → Watchtower → Barracks/Garrison → Hospital → Farm → Storage → Shrine.
- Resource fields mid-high to sustain replenishment; Market for aid.
- Build order: TH2 → Wall2 → Storage → Lumber/Clay/Iron 5–6 → Barracks2 → Watchtower → Hospital.

### Support/Production
- Prioritize: Resource fields → Storage/Warehouse → Market → Vault → Farm → Town Hall for queue slots.
- Minimal military; rely on allies; keep Wall moderate.
- Build order: Resource to 6–8 → Storage → Market → Vault → TH upgrades → Farm as pop needs.

### Scouting/Intel Post
- Prioritize: Watchtower → Scout Hall → Stable (for Pathfinders) → Library (intel tech) → Wall moderate.
- Resource fields moderate; focus on speed and intel fidelity.

### Conquest/Capital
- Prioritize: Hall of Banners → Wall/Watchtower → Hospital → Rally presets → Storage for coin minting; defense troops.
- Keep siege support; highly defended.

## Early/Mid/Late-Game Priorities
- **Early (Day 0–3):** Resource fields to 5–7; Storage/Farm; Barracks1; Wall1–2; Rally Point; Market unlock; watch protection timers.
- **Mid (Day 4–14):** Push Town Hall; unlock Stable/Workshop; Wall 6–10; Watchtower; start Hospital; specialize villages; open Market trade routes; first Hall of Banners if aiming for conquest.
- **Late (Post 2 weeks):** High Walls (10–15), Watchtower high; Siege Foundry; Library tech; Hospital upgrades; Hall of Banners minting; support queue management across villages; cosmetic/polish builds.
- **Casual Path:** Slower TH upgrades; higher Vault; longer protection; focus on production, Wall, and Market.
- **Hardcore Path:** Aggressive TH/Stable/Workshop rush; lower Vault; minimal walls early; faster to nobles/conquest.

## Visual & UX Notes
- **Building Screen:** Grid or list toggle; clear level badges; progress bars with ETA; resource costs with “missing” highlighted; one-tap queue; reorder/cancel with confirmations.
- **Tooltips:** Show next-level benefits, prerequisites, pop cost, and synergy tips (e.g., “Upgrade Storage to unlock Market”).
- **Queue UI:** Collapsible right panel (desktop) / bottom drawer (mobile); drag to reorder if allowed; timers sync to server.
- **Indicators:** Empty queue reminder; resource cap warnings; wall damage indicator; watchtower intel status; Hospital wounded count.
- **Accessibility:** High-contrast option; icon labels; text size; reduced motion; long-press explanations on mobile.

## Optional & Experimental Features
- **Prestige Buildings:** Cosmetic upgrades (golden hall, marble plaza) unlocked via achievements; no gameplay effect.
- **Building Skins:** Seasonal skins (Harvest, Winter, Storm) change visuals and sounds; earnable via events.
- **Regional Buildings:** Terrain-biased structures (Harbor on coasts, Mountain Mine) with small bonuses; world-configurable.
- **Outposts/Encampments:** Temporary forward bases consuming resources; expire; allow staging troops and small queues.
- **Decay & Repair:** Optional wear on walls/buildings over time, repaired with resources to keep upkeep relevant.
- **Slot Constraints:** Some worlds limit total military building count per village to enforce specialization.

### Watchtower / Intel Integration Spec
- **Detection Radius:** Radius grows per level (config table, e.g., level 1 = 3 tiles, +1–2 tiles per level). Option to double radius for noble-bearing commands when `WATCHTOWER_NOBLE_DETECT` is enabled.
- **Reveal Rules:** Commands entering radius are tagged by type; noble-bearing commands flagged with icon/tag if detected. Scout commands more likely to be detected; stealth/scout tech can reduce detection chance via config.
- **Warning Timers:** On detection, enqueue warning with ETA; show timer on map UI and optional notifications (respect quiet hours). Supports progressive alerts as commands close distance.
- **Map Overlay:** Toggle to show coverage; detected commands highlighted; noble flags rendered on lines/markers; intel badge shows last update time.
- **Synergy/Modifiers:** Scout Hall/tech add detection strength; terrain/weather can reduce radius (fog/rain); tribe tech can add flat radius/accuracy.
- **Reporting/Logs:** Battle reports include “Detected via Watchtower Lx”; logs store detection timestamp, radius used, and whether noble flag was shown for audit/telemetry.
## Implementation TODOs
- [ ] Building schema/config: per-building caps, costs, time scaling, pop costs, prerequisites, and world overrides (caps on wall/watchtower).
- [x] Queue system: enforce 1 base slot + Town Hall milestone slots; optional parallel resource+military queue; premium extra slots capped; cancellations return partial resources. _(queue design locked: base 1, Town Hall unlocks extra, premium capped, partial refund on cancel)_
- [x] Hall of Banners pipeline: minting standards/coins, training Standard Bearers/Envoys with requirements and daily caps. _(covered in allegiance/conquest spec; requires Hall level, mint caps, siege-speed conquest unit)_
- [x] Watchtower/intel integration: detection radius by level, noble-bearing command flagging, and warning timers; feed into map overlays. _(spec below)_
- [x] Hospital/wounded system (if enabled): post-battle recovery %, speed by level; consumes resources; integrates into reports. _(wounded pool + recovery scaffold added)_
- [ ] Outpost/encampment mechanics: temporary build slots with expiry and limited training; block if hostile commands inbound; clean up on expiry.
- [ ] Wall damage/repair: apply siege damage to wall; repair queue; optional decay if world enables wear.
- [ ] UI/API: building list/grid endpoints with costs, ETA, missing resources, prerequisites; reorder if allowed; tooltips for next-level benefits.

## Progress
- Queue system design enforced (single active slot with cap errors; Town Hall milestones specced), Hall of Banners pipeline specced, hospital scaffold added; next focus: watchtower intel integration and wall repair flow.
- Combat/raid plunder now respects world vault percent and hiding place; BattleManager reports protected amounts for clarity.

## Acceptance Criteria
- Building caps, costs, times, pop, and prerequisites load correctly per world overrides; wall/watchtower caps respected.
- Queue rules enforced (base slot + milestones; parallel if enabled; premium slots capped); cancellations refund correct partial resources.
- Hall of Banners minting/training enforces requirements and caps; errors return reason codes; daily limits cannot be bypassed.
- Watchtower warnings/noble flags fire per level rules; detection radius matches config; overlays show intel status.
- Hospital (if enabled) recovers correct % and respects speed by level; reports show wounded recovered.
- Outposts expire correctly, block creation when hostile commands inbound, and clean up queues/markers on expiry.
- Wall damage/repair queues apply expected level changes; optional decay toggles on/off per world.

## Monitoring Plan
- Track build queue latency, failure rates, and refund mismatches; alert on spikes.
- Monitor minting/training errors and cap hits for Hall of Banners; alert on bypass attempts.
- Watch watchtower detection events and noble flag rates; alert if detection drops unexpectedly after deployments.
- Monitor hospital recovery applications and report generation time; alert on anomalies or missing recovery entries.
- Track outpost creation/expiry events and cleanup jobs; alert on orphaned markers/queues.
- Monitor wall damage/repair events and decay toggles per world; alert on unexpected level changes or decay when disabled.

## QA & Tests
- Queue validation: prereq/pop/resource caps return correct reason codes; reorder/cancel refunds match spec; parallel queue rules enforced per world.
- Watchtower: detection radius/noble flag per level; warnings surface in overlays and respect intel freshness hooks.
- Hospital: wounded pool/recovery applied per level; reports show recovered counts; capped per config; disabled worlds hide hospital flows.
- Outposts: creation blocked when hostile commands inbound; expiry cleans temp slots/queues/markers.
- Wall: siege damage reduces levels; repair queue restores; decay toggle obeys world config.
- Cache/versioning: client receives cost/time version; cache bust verified on config change.

## Profiling & Load Plan
- Stress-test build queue API under high concurrency (enqueue/reorder/cancel) with caps/prereqs enabled; measure p50/p95/p99 latency and refund accuracy.
- Watchtower detection load: simulate heavy incoming commands to ensure detection/flagging stays within p95 targets and does not spam logs.
- Hospital recovery processing: bulk apply recoveries post-battle at scale; measure resolver overhead and report generation time.
- Outpost lifecycle soak: mass create/expire outposts to validate cleanup and queue removal without leaks or slowdowns.
- Wall damage/repair: batch siege events and repairs to profile state updates and ensure decay toggle checks are cheap.

## Rollout Checklist
- [ ] Feature flags per world for parallel queues, watchtower intel, hospital recovery, and outposts/decay; defaults aligned to archetypes.
- [ ] Migrations/config changes for building caps/queue rules tested with rollback; sane defaults when settings absent.
- [ ] Backward-compatible building endpoints (cost/queue) while new fields roll out; version responses to avoid client breakage.
- [ ] Release comms/help: explain queue rules, watchtower intel, hospital recovery, and outpost behavior; include examples/tooltips.
## Open Questions
- Should parallel construction be globally allowed (resource + military) or world-configurable, and how to message when blocked?
- Do hospitals recover wounded after all battles or only defenses? Define to avoid free sustain for attackers.
- For watchtower detection of noble-bearing commands, is there a minimum scout count or always-on per level? Clarify for UI.
- How many concurrent minting/training slots should Hall of Banners support (1 vs world-configurable), and does it share queue with other buildings?
- [x] Validation & errors: enforce prerequisites on queue submit, block protected wall builds, and return reason codes (`ERR_PREREQ`, `ERR_POP`, `ERR_RES`, `ERR_PROTECTED`, `ERR_CAP`) on upgrade attempts/queueing.
- [ ] Auditing/telemetry: log build queue actions (add/reorder/cancel), costs, refunds, and actor; emit metrics on queue uptime, average build level per village type, and error-rate spikes.
- [ ] Caching: cache per-building cost/time curves server-side with versioning; bust cache on config changes; expose version in API for client-side cache.
- [ ] Tests: unit tests for prerequisites, caps, and refund math; integration tests for queue reorder/cancel with partial refund; property tests to prevent negative/overflow costs.
