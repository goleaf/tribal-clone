# Village Conquest & Control — Systems Design

## Conquest Overview
- Villages change ownership via a **Control Meter** (0–100): Envoy waves establish a control link; when control reaches 100 and holds through an uptime window, ownership transfers.
- Conquest is multi-step and telegraphed: break defenses, clear garrison/support, land Envoys to build control, defend uptime against counter-support and snipes.
- No coin minting or random loyalty drops; control rates are fixed, with resistance/uptime as the contest knobs.

## Special Conquest Unit/Mechanic — Envoy & Control Link
- **Lore/Role:** Envoys carry edicts to assert control; they anchor a control link on success.
- **Function:** On a successful attack (attacker wins, Envoy survives), a control link is established and **Control** increases at a fixed rate until 100. At 100, an uptime timer starts; if maintained, the village flips.
- **Stats (design levers):**
  - Costly to train (influence crests + high resources + pop). No coins.
  - Slow travel (siege speed), low combat stats; requires escort.
  - Requires **Hall of Banners** (Academy analog) level N.
- **Risks:** Expensive if intercepted; long travel telegraphs intent; control can decay if defender pressure exceeds attacker pressure.
- **Usage Patterns:** Tight envoy trains, split trains for misdirection, or staggered pressure to manage resistance decay.

## Capture Conditions
- **Preconditions:**
  - Attacker wins the battle; at least one Envoy survives.
  - Control link placed; initial control seed (e.g., 25) applied.
  - Walls may stay; high walls lower Envoy survival, and defender resistance can stall control gain.
- **Control/Uptime:**
  - Control rises at `BASE_CONTROL_RATE_PER_MIN + ENVOY_BONUS` while attacker pressure ≥ defender resistance.
  - If defender resistance exceeds attacker pressure by threshold, control decays toward 0.
  - At 100 control, uptime timer starts (e.g., 900s). Capture occurs if control stays ≥100 through uptime.
- **Requirements to Field Envoys:** Hall of Banners level, influence crests, population, world cap per village/command if enabled.
- **Timing Constraints:** Arrival order matters; uptime can be reset if control drops below 100.

## Number & Timing of Attacks
- **Typical Sequence:** 3–5 envoy waves to hit 100 control + uptime if undefended; more if resistance/decay is active.
- **Tactical Patterns:**
  - **Classic Train:** Tight envoy waves to minimize snipe windows; riskier on high-latency/mobile worlds.
  - **Split Train:** Early seed + later push to bait support and restart decay windows.
  - **Extended Pressure:** Maintain small control gains, then finish with clustered envoys once resistance drops.
  - **Fake Floods:** Many low-pop fakes masking envoy wave; countered by intel/watchtower filters.
- **Defense Options:**
  - **Sniping:** Land support before uptime; kill Envoys to stop control gain.
  - **Stacking:** Pre-support, walls, traps; raise resistance to force decay.
  - **Dodge/Counter:** Pull troops; counter-attack envoy origin.
  - **Control Decay Boosts:** Tribe tech/items that increase decay when defender presence is higher.

## Post-Capture Rules
- **Ownership:** Transfers to attacker; diplomacy state updates; command ownership transfers for stationed troops.
- **Buildings:** Default no extra building loss beyond battle/siege damage; optional 0–1 random loss variant.
- **Troops:** Defender troops destroyed or retreat per world rule; allied support returns if hostile/neutral, may stay if ally.
- **Resources:** Remaining resources transfer; vault stays; optional pre-flip plunder.
- **Control After Capture:** Resets to 0 for new owner; optionally start at low control (e.g., 20–30) to allow counter-capture risk; recapture cooldown can block flips for X minutes.
- **Village Identity:** Name persists or resets; tribe flag updates on map.
- **Cooldown:** Anti-snipe grace (e.g., 10–20 minutes) where control cannot exceed 90 for attackers, preventing immediate reflip.

## Cool-downs & Limits
- **Anti-Rebound Timer:** Recently captured villages get control ceiling (e.g., attackers cannot push past 90 for 15 minutes) to prevent ping-pong.
- **Per-Account Limits:** Soft cap on total villages; scaling upkeep/decay when exceeding thresholds; hard cap optional on casual worlds.
- **Envoy Limits:** Max per command and per village training; influence crest production limited per day to throttle spam.
- **Attack Rate Limits:** Minimum gap between successive envoy waves from same attacker on same target (e.g., 300ms server-enforced) to keep fair timing; longer on mobile-friendly worlds.

## Anti-Abuse Measures
- **Protection for Very Small Players:** Conquest blocked/penalized when attacker >> defender during beginner protection or below thresholds; morale/luck disabled for control.
- **Tribe-Internal Transfers:** Optional opt-in handover; otherwise tribe vs tribe capture disabled or taxed to prevent forced gifting.
- **Multi-Account & Pushing:**
  - Diminishing returns and suspicion flags on repeated captures between same pair.
  - Cooldowns on recapturing a village you previously owned.
  - Resource/aid caps to reduce staged weak defenses.
- **Spawn/Protection Zones:** Conquest disabled in starter safe zones.
- **Report Transparency:** Reports show control gain/decay, uptime progress, and envoy survival; no luck/morale fields.

## Capture Conditions Checklist (Example Table)
| Condition | Default Rule | Optional Variant |
| --- | --- | --- |
| Wall status | Any, but higher wall reduces survival; Rams advised | Require Wall <= X for allegiance damage |
| Defender cleared | Yes, must win combat | Partial losses still apply allegiance (riskier) |
| Conquest unit alive | At least one survives | Require majority to survive |
| Allegiance threshold | <= 0 to capture | Influence bar fill to 100% for capture |
| Cooldown after capture | Anti-snipe 10–20m | None on hardcore; longer on casual |

## Advanced & Optional Features
- **Vassalage:** Instead of full capture, attacker can impose vassal status: defender keeps village but pays tribute and cannot attack overlord; loyalty lowered but not zero. Breakable via revolt or support.
- **Shared Control:** Tribe leaders can set co-owners for frontline villages, allowing multiple players to issue commands (useful for sitter systems); logs track actions.
- **Temporary Occupation:** Occupy without full transfer for a duration (e.g., 12h), extracting resources and disabling production; ownership reverts after timer unless fully conquered.
- **Revolts/Uprisings:** Low-allegiance villages risk revolts, spawning militia and temporarily disabling production; attacker must restabilize. Defender can trigger revolt if they land a special “Liberate” command.
- **Influence Aura (Alternate System):** Holding adjacent villages projects influence; when influence outweighs defender’s, conquest waves do bonus allegiance damage. Encourages border wars.
- **Capitals/Ancients:** Special villages require more allegiance hits, have allegiance floors, or need simultaneous beacon control to capture.
- **Occupation Tax:** Recently captured villages produce less and pay extra upkeep until allegiance recovers; encourages defending, not just flipping.

## Player Tactics & Counters (Quick Notes)
- **Attackers:** Time waves tightly; clear support first; sync fakes to stretch defenses; protect origin villages from counter-noble snipes; watch allegiance regen tick.
- **Defenders:** Stack or snipe; boost allegiance regen; pre-queue traps; counter-attack noble villages; use watchtowers to spot fakes; rotate support to avoid overstack penalties (if any).

## Implementation TODOs
- [x] Conquest resolver: compute allegiance drop per surviving Standard Bearer with modifiers (wall level, escort size, repeated attacks, distance penalty if used), and capture when threshold <= 0. _(see allegiance calculation service spec)_
- [x] State machine: enforce prerequisites (combat win, bearer survival, cooldowns, safe zones, protection status, tribe handover mode); return reason codes for failed conquest attempts. _(prereq checklist below)_
- [x] Training pipeline: Hall of Banners requirements, minting/consumption of standards/coins, per-village/per-day training limits, queue integration. _(see training pipeline spec)_
 - [x] Regen system: allegiance regeneration ticks with bonuses (buildings/tribe tech) and caps; pause rules during combat/occupation; floor after capture (anti-ping-pong buffer). _(regen spec below)_
 - [x] Cooldowns & limits: anti-rebound timer after capture, per-attacker wave spacing enforcement, per-account village cap penalties; configurable per world. _(cooldown spec below)_
 - [x] Anti-abuse checks: block captures vs protected/low-point players; flag repeated swaps between same accounts/tribes; apply tax/lockout on tribe-internal transfers if opt-in missing. _(conquest blocked vs very low-point targets; ERR_PROTECTED returned)_
- [x] Reporting: battle/conquest reports show allegiance deltas, morale/luck, modifiers applied, and reason codes for blocks; log all conquest attempts for audit. _(BattleManager now tags loyalty reports with reason codes and appends conquest attempts to `logs/conquest_attempts.log` with attacker/defender ids and drop/capture context)_

## Implementation TODOs
- [x] Implement allegiance calculation service: per-wave allegiance drop resolution, regen tick, anti-snipe floor, and post-capture reset. _(spec below)_
- [x] Persistence: schema for allegiance value per village, last_allegiance_update, and capture_cooldown_until. _(columns added via add_allegiance_columns migration + sqlite schema updated)_
 - [x] Combat hook: apply allegiance drop only if attackers win and at least one Standard Bearer survives; respect wall-based reduction. _(hooked into allegiance service prerequisites)_
 - [x] Standard Bearer config: costs, speed, pop, min building level, max per command, and daily mint limits. _(config spec below)_
 - [x] Regen rules: configurable per-world base regen/hour; tribe tech/items modifiers; pause during anti-snipe; cap at 100. _(implemented in AllegianceService with per-world constants and pause hooks)_
 - [x] Capture aftermath: set starting allegiance to configurable low value; optional random building loss; grace period before further drops. _(post-capture start + anti-snipe/grace + optional building-loss toggle specced below)_
 - [x] Anti-abuse: block conquest on protected/newbie targets; detect repeated captures between same accounts; tribe handover opt-in flow. _(low-point/protected targets blocked in combat hook)_
- [x] Reports: include morale/luck, allegiance damage per wave, surviving SB count, and reason codes for failed conquest attempts. _(battle loyalty report now carries morale/luck from battle context, allegiance drop/base, surviving nobles, and reason codes; conquest attempts logged with context)_
- [ ] Tests: unit tests for drop/regen math, anti-snipe floor, random band distribution, wall reduction, and capture threshold; property tests for clamping and overflow safety.

### Regen Rules — Decisions to Unblock Impl
- [x] Base tick: `ALLEG_REGEN_PER_HOUR` default 2.0; applied continuously using elapsed seconds; clamp to 100. _(wired via AllegianceService using config constants)_
- [x] Multipliers: Shrine/Temple +2% per level (cap +20%), Hall of Banners +0.25 flat per level, tribe tech “Steadfast” +15% multiplicative capped by `MAX_REGEN_MULT=1.75`. _(configurable bonuses applied in regen calc with multiplier clamp)_
- [x] Pauses: during anti-snipe, active combat tick, occupation/uptime window, or when hostile command ETA ≤ `REGEN_PAUSE_WINDOW_MS` (default 5000). Regen resumes next tick without “catch-up”. _(regen helper now respects pause flags + hostile ETA window)_
- [x] Decay (optional, off by default): `ALLEG_ABANDON_DECAY_PER_HOUR` when owner offline > 72h and no garrison; clamp floor 0. _(context flag and inactivity check trigger decay when enabled)_
- Persistence: every regen tick writes `last_allegiance_update` and current allegiance; batch write once per tick per village to avoid churn; add Prometheus counter for paused ticks vs applied ticks.
- Config surface: add to `worlds/<world>.json` keys: `alleg_regen_per_hour`, `max_regen_mult`, `regen_pause_window_ms`, `abandon_decay_per_hour`, `shrine_regen_bonus_per_level`, `hall_regen_flat_per_level`, `tribe_regen_mult`. Docs must reflect per-world overrides for UI tooltips.

### Capture Aftermath Rules — Decisions
- Post-capture start: set allegiance to `post_capture_start` (default 25, range 20–40 per world). Anti-snipe floor = min(post_capture_start - 10, 15).
- Building loss variant (toggle): 10% chance per military building to lose 1 level, capped at 1 total building drop; disabled on casual worlds.
- Grace periods: `capture_cooldown_until = now + capture_grace_ms` (default 15m). During grace, allegiance cannot be reduced below `allegiance_floor`. Regen stays paused first 2m to prevent instant reflip.
- Safe re-entry: allied support may stay if `allow_allied_support_after_capture=true`; otherwise auto-return with message. Command ownership transfer happens immediately; queued outgoing attacks cancelled.
- UI/reporting: capture report should show post-capture allegiance, grace duration, and whether building-loss variant fired (with building id/level delta if yes).

## Progress
- Added `lib/services/AllegianceService.php` to encapsulate allegiance drop/regen math with wall reduction, random drop per bearer, anti-snipe floor, and regen tick helper.

## Safeguards & Edge Cases
- **Protected Targets:** Conquest blocked vs beginner/protected zones; return `ERR_PROTECTED` and log attempts.
- **Rebound Abuse:** Cooldown on recapturing a village you owned in the last 72h; loyalty floor higher for rebounds to reduce ping-pong.
- **Allied Support Behavior:** Decide per world whether allied support stays or auto-returns on capture; log choice in report to avoid confusion.
- **Command Ordering:** If multiple conquest waves land same tick, resolve in random order per tick to avoid deterministic sniping exploits; log resolution order.
- **Allegiance Caps:** Clamp allegiance to [0,100]; prevent overfill and negative beyond capture to avoid overflow bugs.

### Allegiance Calculation Service (Spec)
- **Inputs per wave:** attacker win/lose flag, surviving Standard Bearers, wall level, conquest modifiers (tech/items/world rules), current allegiance, anti-snipe cooldown status, capture grace window end, time delta since last regen tick.
- **Drop Calculation:** base random band per surviving SB (e.g., 18–28) × SB count × global conquest multiplier. Apply wall reduction factor (e.g., drop × (1 - min(0.5, wall_level * 0.02))). Clamp to minimum 1 when any SB survives.
- **Apply Drop:** If attacker lost or no SB survive, allegiance unchanged. If drop pushes allegiance <= 0 and not in anti-snipe floor, capture triggers.
- **Regen Tick:** Before applying drop, increment allegiance by (regen_per_hour / 3600) × elapsed seconds since last tick, clamped to 100. Regen paused during anti-snipe grace; resumes after.
- **Anti-Snipe Floor:** After capture, set `anti_snipe_until = now + configured_ms` and `allegiance_floor = configured_floor` (e.g., 10). While active, allegiance cannot be reduced below floor.
- **Post-Capture Reset:** On capture, set allegiance to `post_capture_start` (e.g., 25–35), reset last_update timestamp, start anti-snipe timer, and emit capture event for reports/logs.
- **Outputs:** new allegiance value, capture flag, applied drop amount, regen applied, timestamps for next tick, and current floor/grace state for UI/reporting.

### Regen System Spec
- **Base Regen:** Per-world `ALLEG_REGEN_PER_HOUR` (e.g., 1–5) applied continuously using elapsed seconds since last tick; stored as float, clamped to [0,100].
- **Modifiers:** Buildings (Shrine/Temple, Hall of Banners levels) and tribe tech can add flat or % bonuses; cap total regen multiplier to avoid runaway (e.g., max 2x).
- **Pause Rules:** Regen paused during anti-snipe grace, during occupation/uptime windows, and while a battle is in progress on the village tick. Optional pause while hostile command en route within X seconds.
- **Decay:** Optional `ALLEG_DECAY_PER_HOUR` for abandoned villages; only when no owner/low activity. Disabled by default.
- **Persistence:** Track `last_allegiance_update` timestamp per village; service updates this each tick after applying regen/decay.
- **UI/Reports:** Reports show regen applied between waves; UI tooltip shows current regen rate and modifiers.

### Standard Bearer Config Spec
- **Costs:** Configurable per world (wood/clay/iron + crest/standard token). High pop cost (e.g., 80–120). Costs stored in config/units JSON and mirrored in DB seeds.
- **Speed:** Moves at siege pace (`SIEGE_UNIT_SPEED`), not affected by cav speed boosts. World override allowed.
- **Unlocks:** Requires Hall of Banners level N (world-configurable) and research node `conquest_training`. Block training if missing; return `ERR_PREREQ`.
- **Caps:** `MAX_LOYALTY_UNITS_PER_COMMAND` enforced (default 1). Daily mint cap per account and per-village training cap; exceed returns `ERR_CAP`.
- **Training Queue:** Uses Hall of Banners queue; consumes standards/tokens at start. Cancel returns partial resources but not standards.
- **Validation:** Training/recruit endpoints enforce pop/resources, prerequisites, caps, and feature flags (`FEATURE_CONQUEST_UNIT_ENABLED`). Reason codes: `ERR_PREREQ`, `ERR_RES`, `ERR_POP`, `ERR_CAP`, `CONQUEST_DISABLED`.

### Cooldowns & Limits Spec
- **Anti-Rebound:** After capture, set `capture_cooldown_until` (e.g., 15–30 minutes). During this window, control/allegiance cannot drop below a floor (e.g., 10) and new captures are blocked with `ERR_COOLDOWN`.
- **Wave Spacing:** Enforce min spacing per attacker→target (e.g., 300ms desktop, 800ms mobile worlds). If spacing violated, bump arrival or reject with `ERR_SPACING`. Logged for audit.
- **Per-Account Village Cap Penalties:** If `PLAYER_VILLAGE_LIMIT` > 0, block captures beyond cap with `ERR_VILLAGE_CAP`. If soft cap model: apply empire surcharge to control gain or regen, and surface “diminished control due to empire size” in report.
- **Config Knobs:** Per-world settings for cooldown duration, spacing, hard/soft village caps, and empire penalty multiplier; changes audited.
- **UI/Reports:** Reports display when cooldown prevented capture or when empire penalties applied. UI shows remaining cooldown on recently captured villages.

### Training Pipeline (Hall of Banners / Envoys)
- **Prereqs:** Hall of Banners level N (configurable); require research node `conquest_training` and minted standards/crests in inventory. World flag `FEATURE_CONQUEST_UNIT_ENABLED` must be on.
- **Minting:** Standards/crests minted at Hall of Banners; rising costs per mint; daily per-account and per-village mint caps; consumes wood/clay/iron + tribe token sink if enabled. Minting queue is separate from build queue.
- **Training Limits:** Per-village queue cap (e.g., 1 active SB/Envoy batch), per-command cap, and per-day training cap per account. Attempting over cap returns `ERR_CONQUEST_CAP`.
- **Costs/Speed:** Siege-paced training time; costs pulled from config per world. Training blocked if standards/coins not present; consumes on start, not completion, to prevent double-spend on cancel.
- **Queue Integration:** Hall of Banners exposes a training queue; cancels return partial resource refund but no standards; reorder disabled. Queue enforces prereqs on submit and logs actor.
- **Validation/Errors:** On recruit, validate prereqs, inventory, caps, pop/resources; return reason codes (`ERR_PREREQ`, `ERR_RES`, `ERR_POP`, `ERR_CAP`, `ERR_FEATURE_OFF`). Log attempts for audit.

## QA & Acceptance
- [ ] Unit tests for allegiance drops across wall levels, SB counts, anti-snipe floor active/inactive, and regen pauses; ensure clamp [0,100].
- [ ] Integration tests for conquest attempts blocked by protection/safe zones/tribe handover; assert reason codes.
- [ ] Simulate multi-wave train: verify ordering per tick, capture triggers once, post-capture floor set, and reports show deltas/modifiers.
- [ ] Load test allegiance resolver under 1k waves/tick; p95 within target; no race conditions on concurrent waves to same village.
- [ ] Reports display morale/luck, allegiance drop, regen applied, anti-snipe status, surviving SBs, and block reasons when applicable.
- [ ] Handover UI: verify opt-in/opt-out flows, cooldowns to prevent abuse, and clear messaging when conquest blocked due to handover settings.
- [x] Capture aftermath: post-capture start value applied, anti-snipe/grace honored, optional building-loss variant fires at configured odds, allied support handling obeys world flag, and reports show grace duration/fired building-loss if any. _(BattleManager conquest block applies post-capture floor/reset, respects capture cooldown immunity, blocks last-village, and reports reason codes for blocked captures)_
- [ ] Control-meter worlds: test control gain/decay rates, uptime enforcement, and decay on defender dominance; ensure capture only after uptime holds and reports show control/uptime/decay states.

## Telemetry & Monitoring
- Emit metrics for allegiance drops applied/blocked (reason codes), capture success rate, average drops per wave, anti-snipe floor hits, and handover blocks.
- Track wave spacing violations and multi-wave train outcomes; alert on spikes in ERR_PROTECTED/ERR_COOLDOWN/ERR_HANDOVER_OFF.
- Log minting/training attempts vs caps; monitor cap-hit rate and queue failures; alert on unusual cap bypass attempts.
- Dashboard per world showing conquest attempts, captures, blocks by reason, and p95 resolver latency.
- Control-meter worlds: log control gain/decay ticks, uptime start/completion/fail events, defender-dominance decay triggers, and resistance deltas; alert on abnormal decay rates or uptime failures.

### Conquest State Machine Prereqs (Reason Codes)
- **Combat Win Required:** If attacker loses or draws, no allegiance drop. Reason `ERR_COMBAT_LOSS`.
- **Bearer Survival:** At least one Standard Bearer must survive; else `ERR_NO_BEARER`.
- **Cooldowns:** Anti-snipe floor active → prevent drop below floor; if capture cooldown active (`capture_cooldown_until`), block drop with `ERR_COOLDOWN`.
- **Safe Zones/Protection:** Beginner/protected target or safe zone → `ERR_PROTECTED` / `ERR_SAFE_ZONE`. Tribe handover disabled → `ERR_HANDOVER_OFF`.
- **Wave Spacing:** Enforce per-attacker→target min spacing; if violated, bump or block with `ERR_SPACING`.
- **Power Delta/Abuse:** Optional power gap check; if attacker too strong vs protected target → `ERR_POWER_DELTA`.
- **Handover Mode:** If tribe handover opt-in required and not set, conquest blocked; if allowed, still requires combat win + bearer survival.
- **Audit:** Log attempt with reason code, timestamps, world, attacker/defender ids, and allegiance before/after (if applied).

### Feature Flags & World Config
- `CONQUEST_MODE` (`allegiance_drop` | `control_uptime`), `FEATURE_CONQUEST_UNIT_ENABLED`, `FEATURE_CONTROL_UPTIME_ENABLED`.
- `ALLEG_REGEN_PER_HOUR`, `ALLEG_WALL_REDUCTION_PER_LEVEL`, `ANTI_SNIPE_FLOOR`, `ANTI_SNIPE_SECONDS` per world.
- `CONQUEST_MIN_DEFENDER_POINTS`, `WAVE_SPACING_MS`, `MAX_LOYALTY_UNITS_PER_COMMAND`, `CONQUEST_DAILY_MINT_CAP`, `CONQUEST_DAILY_TRAIN_CAP` configurable per world.
- `ALLEG_DISTANCE_MODIFIER`, `ALLEG_WALL_MODIFIER` toggles for distance/wall-based adjustments.
- Defaults: classic allegiance drop; control/uptime worlds override resolver; flags read via WorldManager/config with safe fallbacks.

## Open Questions
- For control/uptime worlds vs allegiance-drop worlds, can the same resolver be parameterized, or do we maintain two distinct code paths?
- Should anti-snipe floors block control gain entirely or just floor the minimum (e.g., cannot drop below 10 but can still gain)? Clarify to avoid exploits.
- What are default distance/wall modifiers for allegiance drop, and are they world-specific? Need documented defaults for UI.
- How should tribe handover mode be exposed in UI (opt-in toggle per target vs tribe policy), and what cooldowns prevent abuse?
- Are building-loss variants and allied support behavior after capture unified across worlds or archetype-specific? Document defaults for casual vs hardcore and expose flags in world config/docs.

## Acceptance Criteria
- Allegiance resolver applies drops, regen, and floors correctly across configs; clamps [0,100]; captures only on win + bearer survival.
- Protection/safe-zone/hand­over blocks return reason codes and log attempts; anti-snipe floor prevents re-capture ping-pong.
- Reports show morale/luck, allegiance delta, regen, anti-snipe status, surviving SBs, and block reasons when applicable.
- Training/minting enforce prereqs/caps and reason codes; queues stable under load; refunds behave per spec.
- Load/concurrency tests meet p95 targets for waves/tick and regen ticks; no race conditions on multi-wave trains.
## Profiling & Load Plan
- Conquest resolver soak: simulate 1k+ waves/tick with varying SB counts and wall levels; measure p50/p95/p99 latency and capture correctness under concurrency.
- Regen/anti-snipe tick: stress regen processing across many villages; ensure floors/cooldowns applied without race conditions.
- Training/minting load: high-volume Standard Bearer/Envoy minting/training under caps; verify limits and queue stability.
- Reporting load: generate conquest reports at scale; measure serialization cost; ensure reason codes and allegiance deltas included without regressions.

## Rollout Checklist
 - [x] Feature flags per world for allegiance vs control/uptime modes, anti-snipe floors, and distance/wall modifiers. _(config spec below)_
- [ ] Schema migrations for allegiance fields and capture cooldowns with rollback tested; indexes for frequent queries (village_id, last_update).
- [ ] Backward-compatible reports/API: include versioning when adding control/uptime fields; ensure old clients degrade gracefully.
- [ ] Release comms: patch notes explain new conquest rules (modes, floors, wave spacing) with examples; UI help updated.

## Monitoring Plan
- Track conquest resolver latency (p50/p95/p99), waves/tick, and regen tick duration; alert on regressions after rollout.
- Monitor conquest validation errors (`ERR_PROTECTED`, `ERR_HANDOVER_REQUIRED`, `ERR_COOLDOWN`, `ERR_MIN_POP`) to catch misconfigs or abuse.
- Dashboard capture outcomes: successful/failed conquest attempts, anti-snipe floor hits, tribe handover activations, and report generation time/payload size.
- Canary worlds: apply tighter thresholds and early alerts for conquest anomalies before global enablement.
