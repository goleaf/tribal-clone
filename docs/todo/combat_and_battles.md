# Combat System & Battle Resolution — Real-Time Server-Tick Medieval Tribal War MMO

## Combat Overview
- Battles occur when an **attack command** arrives at a target village. Resolution is server-tick based (exact second timestamps); ties handled deterministically.
- Outcome depends on **attacker total power vs defender total power** per damage type (infantry/cavalry/archer/siege), modified by **terrain**, **wall**, **morale**, **luck**, **time-of-day modifiers (night/day/ weather)**, and any world-specific rules.
- **Attacker goals:** kill defenders, breach walls/targets, plunder resources, and optionally drop allegiance (if conquest units present).
- **Defender goals:** preserve village, kill attackers, maintain wall, protect resources (vault), and hold loyalty.

## Attack & Command Types
- **Standard Attack:** Full combat intent; uses normal casualty calculations; can include conquest units.
- **Raid/Plunder:** Reduced siege effect; capped troop count or capped plunder; lower risk/return.
- **Siege Focus:** Siege-heavy command intended to lower walls/buildings; may have reduced loot; can include “siege hold” (stay to continue wall pressure on some worlds).
- **Support Movement:** Friendly troops sent to defend target; never plunder; fight on defender side until returning.
- **Scout/Recon:** Intel-only; resolves via scout duel mechanics; minimal combat otherwise.
- **Mass Attack (Coordinated Waves):** Multiple commands launched to land within tight windows; used for clears + fakes + conquest trains.
- **Fake Attack:** Intentionally low-population commands to draw defenses or mask real targets; may be limited by minimum pop rules to prevent 1-unit spam.
- **Counter-Attack/Retaliation:** Automated or rapid manual send post-defense; uses same combat rules.
- **Siege Hold/Occupy (optional world rule):** Attackers stay occupying the village for a duration, applying debuffs; distinct from conquest.

## Pre-Battle Phase (Preparation & Visibility)
- **Scouting:** Attacker may scout to learn troops, wall, resources. Defender may counter-scout, jam, or set traps.
- **Incoming Visibility:**
  - Attacks visible to defender with type icon (attack/support/scout/trade) and origin; troop composition hidden unless scouts/watchtower reveal.
  - Noble/conquest units may be flagged if detected by watchtower/intel.
- **Movement Timing:** Attacker chooses send time to coordinate waves (fakes + clears + conquest). Defender times snipes/support/dodges.
- **Defender Prep:** Stack support, raise wall, activate traps, evacuate resources (trade), dodge main army, queue builds to dump resources.
- **Attacker Prep:** Clear known support, send nukes/clears, coordinate fake floods, ensure conquest waves behind clears, set rally presets.

## Battle Resolution Logic (Step-by-Step)
1. **Arrival Check:** Determine if defender has emergency shield/peace active; if so and allowed, attack bounces without combat.
2. **Merge Defending Forces:** Combine local garrison + stationed allied support + traps; apply morale, night/day/weather modifiers to defender stats.
3. **Calculate Effective Attack/Defense:** Sum attacker power by type vs defender defense by type; apply wall multiplier to defender; apply terrain effects and any formation/world modifiers.
4. **Apply Luck (if enabled):** Random modifier within configured band applied to attacker or overall ratio.
5. **Resolve Casualties:**
   - Determine casualty ratio based on attack vs defense after modifiers.
   - Siege acts: Rams attempt to reduce wall; catapults target designated building (or random if none specified). Siege effect scales with surviving siege.
   - Casualties applied proportionally across unit types on both sides; conquest units have lowest priority on damage output, normal priority on taking damage.
6. **Wall/Building Effects:** Wall level reduces based on surviving rams; catapults damage target building if attackers win or meet threshold.
7. **Conquest/Allegiance:** If attacker wins ground battle and conquest units survive, apply allegiance drop; capture if at/below 0 (subject to cooldown rules).
8. **Plunder:** If attackers have capacity and survive, calculate loot up to (resources available - vault protection - cap). Split evenly among surviving plunder-capable units; siege/conquest units typically carry 0.
9. **Survivor Return/Stay:** Attacking survivors return with loot unless configured to occupy; supports remain until recalled or timed.
10. **Reporting:** Generate reports for both sides with fidelity based on surviving scouts/vision rules.

## Special Tactics (Original Wording)
- **Fake Floods:** Numerous low-pop commands mimic real waves, forcing defenders to spread support. Minimum-pop rules make fakes detectable; watchtower helps filter.
- **Nukes/Clears:** High-attack stacks (often cavalry/axes + some rams) sent to annihilate defense before conquest. Sometimes multiple clears staggered.
- **Follow-Up Conquers:** Conquest wave timed seconds after a clear; relies on defender being wiped. May be followed by a second conquer to secure capture after regen or mis-snipe.
- **Sniping:** Defender times support to land between conquest waves in a train, killing nobles/conquest units while letting first wave hit.
- **Stacking Defense:** Pre-loading massive defense/support in target village to absorb clears. Risk: if cleared, losses are huge; benefit: can stop trains.
- **Dodge & Counter:** Defender moves main army out before impact to avoid losses, then counter-attacks attacker origin as their army returns.
- **Timing Defense:** Use incoming timers to synchronize support from multiple villages to land just before or between waves.
- **Shadow Clears:** Small clears to remove traps/watchtower and test defender readiness before main op.
- **Layered Waves:** Sequencing siege-first, then main offense, then conquest, then backfilled support to hold capture.

## Morale & Luck Systems (Options)
- **Morale (Underdog Aid):** Scales attacker strength down when attacking much weaker players; curve and floor configurable. Variants: distance-based fatigue, defender-only buff, or disabled on hardcore.
- **Luck:** Random variation applied to attacker strength within a band (e.g., -15% to +15%). Options: reduced range on competitive worlds, fixed seed per battle to prevent variance stacking, or disabled.
- **Night/Weather Bonus:** Time-of-day defense boost (night) or weather modifiers (fog reduces scout fidelity, rain reduces siege accuracy). Toggle per world.
- **Resolve/Resolve Decay:** Alternative to morale: defenders gain resolve after repeated defenses; decays over time.

## Battle Reports (Structure & Examples)
- **Structure:**
  - Header: Time, attacker/defender names/tribes, coords, command type.
  - Outcome: Win/Loss/Draw; morale; luck; night/weather indicators.
  - Troops: Sent / Lost / Survived for both sides (by unit type). Support listed separately.
  - Siege Effects: Wall change, building targeted/damage.
  - Conquest: Allegiance drop and result (captured/not captured).
  - Plunder: Resources taken; vault protection noted.
  - Intel: If scouts survived, show defender resources/buildings/troops survived; otherwise fogged.
  - Notes: Traps triggered, fake detection, war status.

- **Example Reports:**
  - **Crushing Win (Attacker):** `Battle at Oakridge (512|489) — Attacker Victory. Luck +7%, Morale 1.00, Night Off. Troops sent 4,500 Axes / 400 LC / 60 Rams; lost 380 Axes / 22 LC / 10 Rams. Defender 3,200 Spears / 1,100 Swords + support; all lost. Wall 10→0. Targeted THall dmg: -1 level. Plunder: 18,000W/17,500C/12,400I (Vault 3k each). Allegiance -0 (no nobles).`
  - **Narrow Win (Attacker with Conquest):** `Battle at Bramblehold (498|501) — Attacker Victory. Luck -3%, Morale 0.86, Night On. Sent 3,800 Axes / 300 HC / 50 Rams / 3 Standard Bearers; lost 2,900 Axes / 220 HC / 42 Rams / 1 Bearer. Defender 3,500 mixed inf/cav; all lost. Wall 9→3. Allegiance -27 (73→46). Not captured.`
  - **Narrow Loss (Defender Holds):** `Battle at Iron Nest (410|422) — Defender Victory. Luck +2%, Morale 0.74. Attacker sent 5,000 LC / 80 Rams; lost all. Defender lost 1,400 Spears / 500 Archers / 10 Rams. Wall 11→9. No plunder.`
  - **Devastating Loss (Attacker Wiped, Defender Barely):** `Battle at Frosthill (500|500) — Defender Victory. Luck -10%, Morale 1.00. Attacker 6,000 Axes / 400 Rams; all lost. Defender lost 4,800 Spears / 1,000 Swords; 12 Rams remain. Wall 8→4. No plunder.`
  - **Failed Scout:** `Recon Failed — Elmwatch (505|497): All 20 Pathfinders killed by defending scouts/traps. No intel gathered.`
  - **Partial Scout:** `Partial Recon — Ashen Ford (333|478): Troops estimated 900–1,200; Wall ~10–12; Resources high. 8/25 scouts lost.`

## Edge Cases & Concurrency
- **Simultaneous Attacks:** If multiple commands land same tick, resolve in timestamp order; support arriving same tick counted if timestamp <= attack; deterministic ordering shown in log.
- **Multiple Defenders (Support):** All stationed allied troops fight as defender; upkeep stays with owners. Casualties applied proportionally.
- **Offline Defenders:** No change to combat; may have shield auto-trigger (if world allows) at resource/rarity cost.
- **Emergency Shields/Truces (world optional):** If activated before impact, attack bounces; cooldown and limited charges to prevent abuse.
- **Server Lag Considerations:** Commands processed by server time; client shows synced countdown; tie-breaking rules documented; anti-sniping buffer (e.g., min 100ms spacing) on send to avoid exploit.
- **Overstack Penalties (optional):** Worlds may penalize defense beyond certain population to reduce turtle meta (e.g., diminishing defense after pop threshold).
- **Friendly Fire Rules:** Attacks on allies/NAP may be blocked or heavily warned; support cannot enter enemy villages.
- **Post-Battle Occupation (optional):** Attacker can choose to hold position; if so, survivors remain and defend until recalled.

## Battle Phases Summary (Checklist)
- Pre-battle: Scout → Plan timing → Send fakes/clears/siege → Defender stacks/dodges.
- Impact: Merge defenders → Apply modifiers (morale/luck/night/terrain/wall) → Resolve casualties → Apply siege → Apply conquest → Calculate plunder.
- Post-battle: Survivors return or hold → Reports generated → Intel shared → Regen ticks (loyalty, wall repairs if rules allow).

## Implementation TODOs
- [ ] Combat resolver: implement attack/defense aggregation by type, apply wall/terrain/morale/luck/night/weather, resolve casualties proportionally, and apply siege effects (wall/building).
- [x] Conquest integration: hook allegiance drop after combat win with surviving conquest units; enforce cooldowns and reason codes on failures. _(see allegiance service spec; requires attack win + surviving conquest unit, respects anti-snipe floor and reports reason codes)_
- [x] Command ordering: deterministic tick ordering for simultaneous arrivals; include support if timestamp <= attack; log ordering for reports/audit. _(ordering spec below)_
- [x] Fake/min-pop rules: enforce minimum payload for attack commands; tag fakes for reporting/intel filters; throttle sub-50-pop spam server-side. _(rate-limit spec added; fake tags surfaced for intel filters)_
- [x] Overstack/penalties: optional world rule to apply diminishing defense past population threshold; ensure performance on large stacks. _(config + multiplier applied; reported in battle details)_
- [x] Night/terrain/weather flags: world-configurable toggles and modifiers; expose in battle report and UI. _(env modifier spec below; engine reads terrain/weather multipliers from world config)_
- [x] Rate limits/backpressure: cap command creation per player/target/time window; apply friendly error codes; prevent laggy floods from degrading ticks. _(generic + scout-specific burst caps implemented in BattleManager)_
- [x] Reporting: generate battle reports with full context (modifiers, casualties, siege, plunder, allegiance change); redact intel when scouts die; support tribe sharing. _(report spec below)_
  - Progress: reports now include vault protection percent and protected amounts used in plunder calculation.
  - Next: include applied environment modifiers, overstack penalty, and reason codes for blocked/partial outcomes.

### Overstack Penalty Spec
- **Config:** `OVERSTACK_ENABLED` (bool), `OVERSTACK_POP_THRESHOLD` (e.g., 30k pop), `OVERSTACK_PENALTY_RATE` (e.g., 0.1 per threshold overage), `OVERSTACK_MIN_MULTIPLIER` (e.g., 0.4), optional exemptions for capitals/wonder villages.
- **Scope:** Applies to total defending population in the village (garrison + support) when the battle resolves. Attacker unaffected.
- **Formula:** `overstack_multiplier = max(OVERSTACK_MIN_MULTIPLIER, 1 - OVERSTACK_PENALTY_RATE * max(0, (def_pop - threshold) / threshold))`. Example: at 60k pop with threshold 30k, rate 0.1 → multiplier 0.7.
- **Order of Ops:** Sum defender defense by type → apply overstack multiplier to those defense values → then apply wall, terrain, morale, night/weather, luck. Siege effects still use resulting ratios.
- **Reporting:** Battle report includes “Overstack penalty applied: X%” when triggered; logs include defender_pop and threshold for audit/telemetry.
- **Performance:** Compute from pre-counted pop totals (avoid per-unit loops); guard rails in resolver to skip if disabled. Add metrics on frequency and average multiplier for tuning.

### Command Ordering Spec
- **Key:** Sort by `arrival_at` (server epoch ms), then `sequence` (monotonic per sender), then type priority (support before attack/trade/scout when exact same timestamp), then `command_id`.
- **Support inclusion:** Any support with `arrival_at <= attack_arrival` in that tick is merged before resolution. Attacks at same timestamp resolve after all qualifying support is merged.
- **Anti-snipe spacing:** Enforce minimum 100ms spacing per attacker→target; if violated, bump later command to next tick and flag reason code `CMD_SPACING`.
- **Determinism:** Stable sort with above keys; luck seeded per battle id; identical inputs yield identical outcomes across servers/replays.
- **Logging:** Persist ordering list (command_id + arrival + sequence + type) for the tick in debug/audit; battle reports note “Simultaneous arrivals resolved; support included” when applicable.

### Environment Modifiers (Night/Terrain/Weather) Spec
- **World Flags:** `FEATURE_NIGHT_BONUS`, `FEATURE_TERRAIN_MODIFIERS`, `FEATURE_WEATHER_MODIFIERS` with per-world multiplier tables and schedules.
- **Night Bonus:** Time windows per world; defender defense multiplier (e.g., 1.1–1.5) and optional scout/ram penalty. Applied after overstack and before luck. Reports show “Night bonus x1.xx”.
- **Terrain:** Map tiles carry terrain; defense multipliers per unit type (forest buffs inf/ranged, plains buff cav, hills buff siege/def). Applied before wall/morale to keep ratios sensible. Stored on battle for replay consistency.
- **Weather:** Rotating weather state per world/region (fog, rain, storm, clear) with effects: fog lowers scout fidelity and cav attack; rain lowers siege accuracy; storm narrows luck band. Timestamped and cached.
- **UI/Reports:** Battle reports list active env modifiers and values; send form shows current night/weather state; incoming details flag noble detection + weather/night context.

### Battle Report Spec
- **Header:** time, attacker/defender names/tribes, coords, command type, war status, world id.
- **Modifiers:** luck (%), morale, night/weather/terrain flags with values, overstack penalty %, banner aura/healer/mantlet applied, and any empire/catch-up buffs affecting combat.
- **Troops:** Sent/Lost/Survived by unit type for attacker and defender; support listed separately; conquest units flagged.
- **Siege & Structures:** Wall before/after, targeted building and delta, traps triggered; siege hit/miss data if weather affects accuracy.
- **Conquest:** Allegiance before/after, drop amount, capture flag, anti-snipe status, post-capture floor if triggered.
- **Plunder:** Resources available, vault protection, plundered amounts, loot cap or DR applied; carry used/remaining.
- **Intel:** If scouts survive, show defender resources/buildings/troops per fidelity rules; otherwise redact and note “No intel (scouts lost)” or “Intel reduced (fog/night).”
- **Sharing:** Tribe-share link/flag; shared reports honor intel redaction; includes note if redacted for allies.
- **Reason Codes:** For blocked/partial outcomes: `ERR_PROTECTED`, `ERR_SAFE_ZONE`, `ERR_MIN_POP`, `ERR_SPACING`, `ERR_WEATHER_BLOCK` (if storm blocks certain commands), and conquest failure reasons.
- **Telemetry/Audit:** Log correlation id, arrival ordering, modifiers applied, and random seed; used for replay/determinism checks.

### Command Rate Limits & Backpressure Spec
- **Per-player caps:** Sliding windows per sender (e.g., 30 commands/60s, 300/hour) with lower caps for scout spam; configurable per world and command type.
- **Per-target caps:** Pair (attacker, target) limit (e.g., 5 commands/30s, 30/hour). Exceeding returns `ERR_RATE_LIMIT` with retry-after seconds.
- **Backpressure:** When tick queue depth exceeds threshold, respond with `ERR_TRY_LATER` and recommended retry delay; log backpressure events and emit metrics.
- **Storage:** Use fast cache keyed by sender and sender:target; sliding window counters; validated even if command ultimately blocked for protection/safe zone.
- **Audit/UI:** Log rate-limit hits (sender_id, target_id, type, retry_after, ip_hash). UI shows friendly toast and next-available time; ops dashboards display spikes to tune defaults.

### Plunder/Loot Spec
- **Inputs:** Defender resources, vault %, hiding place protection per type, plunder cap per attack, surviving carriers with capacity, and raid/plunder modifiers.
- **Protection:** Protected per resource = `max(vault_pct * stored, hiding_place_protected)`. Subtract before computing lootable.
- **Lootable:** `lootable = max(0, available - protected)`; apply world plunder cap (percent of storage or fixed) if configured.
- **Capacity:** Total carry = sum(carry of surviving plunder-capable units) × plunder modifier (raid bonus). If capacity < lootable, distribute per configured order or proportionally.
- **Split:** Split loot deterministically across carriers; round with stable remainder distribution.
- **Reporting:** Show available, protected (vault/hiding place), lootable, cap applied, carry used/remaining, and loot per resource; note raid bonuses/DR.
- **Edge Cases:** No surviving carriers → loot 0; protected >= available → loot 0 with note; clamp negatives to 0; conquest/siege units carry 0.
- **DR/Band Rules:** If diminishing returns on repeat raids apply, multiply lootable by DR factor per attacker→target window and surface factor in report; ensure DR calculated before capacity split.

### Siege Hold/Occupy Spec (World-Optional)
- **Enable Flag:** `FEATURE_SIEGE_HOLD_ENABLED` per world; defaults off. If off, attacks always return after combat.
- **Activation:** Attacker selects "Hold" when sending (if world allows). Hold only possible on win. Occupation timer set per world (e.g., 30–120 minutes).
- **Effects During Occupation:** Occupying troops remain and defend as garrison; apply optional debuffs to defender (reduced production/recruit speed, blocked builds) and/or buffs to occupants (supply line bonus). Loot typically disabled during occupation (loot on initial battle only) unless world config allows trickle tax.
- **Attrition/Upkeep:** Optional attrition tick for occupying troops (percent loss/hour) and higher upkeep paid by occupier. If supply breaks (no food/pop), occupation ends early.
- **Release/Relief:** Occupier can manually end occupation; defender can break it by winning a battle. Occupation auto-ends when timer expires or all occupants die.
- **Reporting/UI:** Battle/occupation reports show occupation start/end time, remaining timer, attrition applied, debuffs active, and occupancy state. Incoming list marks occupied villages. Reason codes: `ERR_HOLD_DISABLED`, `ERR_HOLD_LOSS`, `ERR_HOLD_TIMER`.

## QA & Acceptance
- [ ] Unit tests for combat resolver: modifiers (morale/luck/night/terrain), siege damage scaling, overstack penalty, and proportional casualties.
- [ ] Conquest hook tests: capture only on attacker win + surviving conquest units; blocked by protection/cooldowns; reason codes returned.
- [ ] Timing tests: simultaneous arrivals ordering, support inclusion, and anti-sniping spacing enforcement.
- [ ] Rate-limit tests: command creation caps and fake/min-pop enforcement return correct errors; no tick degradation under spam.
- [x] Report validation: reports show correct deltas, modifiers, plunder, allegiance change, and redact intel when scouts die; tribe sharing works with permissions. _(modifiers block now includes wall/morale/luck/env/overstack)_
- [x] Plunder validation: protected amounts vs vault/hiding place, DR factors, caps, and carry splits match spec; reports reflect exact loot math; raid vs standard caps covered. _(BattleManager plunder math now applies max(vault %, hiding place) per resource, DR per attacker→target cooldown, raid cap multiplier, and surfaces protected amounts/percent in reports)_
- [ ] Occupation/hold (if enabled): occupation state set/cleared, attrition/upkeep rules applied, loot rules during occupation enforced, and reports show occupation timers/status.
- [ ] Telemetry: battle resolver emits correlation ids, modifier flags applied, command ordering decisions, and rate-limit errors; alerts on spikes in errors/latency.

## Open Questions
- Should casualty proportionality be linear or tuned per unit class (e.g., siege attrition different)? Decide before implementation.
- How to seed luck for fairness (per-battle fixed seed vs per-round variance) to prevent variance stacking abuse?
- What is the exact min-pop rule for fakes across worlds (uniform vs world-specific)? Document and expose to UI.
- How should raid vs standard attack plunder caps differ, and are they percent-based or fixed per world? Need defaults for reports/UI.
- For siege hold/occupy (if enabled), should occupying troops suffer attrition or upkeep changes, and how is loot handled during occupation?

## Profiling & Load Plan
- Benchmark combat resolver under load: 1k/5k/10k battles per tick with mixed unit comps and siege; record p50/p95/p99 latency and CPU/memory.
- Concurrency tests: simultaneous waves to same village to ensure locking prevents double-resolve; measure contention overhead.
- Luck/morale variants: profile with modifiers on/off to ensure negligible overhead; decide default seeding strategy based on results.
- Fake/min-pop enforcement: simulate spam attempts to validate rate limiting and impact on tick processing.
- Report generation: measure cost of full-fidelity vs redacted reports at volume; optimize serialization if needed.

## Rollout Checklist
- [ ] Feature flags for new combat rules (overstack penalties, night/weather, min-pop enforcement) to enable gradual rollout per world.
- [ ] Migration scripts for any schema changes (allegiance fields, report tables) with rollback steps tested.
- [x] Backward-compatible report rendering while new fields propagate; add versioning to reports to avoid client breaks. _(report_version included in battle details)_
- [ ] Release comms: patch notes covering combat changes, min-pop rules, and reporting improvements; include example reports.
- [x] Safeguards: block attacks vs protected/newbie targets (return `ERR_PROTECTED`); validate payloads (non-zero troops, no negative counts); clamp luck/morale to configured ranges. _(protected-target block added; payload validation partially in place)_
- [ ] Audit/telemetry: log battle resolution traces with correlation ids; emit metrics (tick duration, battles resolved, casualty calc errors, report generation failures); alert on spikes.
- [ ] Load tests: simulate large stacked battles and fake floods to validate performance of casualty loops, overstack penalties, and command ordering under load.
- [ ] Determinism tests: ensure identical inputs produce identical casualty/wall/allegiance outputs across servers and replays.
- [ ] Property fuzzing: fuzz negative/overflow troop counts, extreme modifiers, and malformed commands; expect graceful errors not crashes.
- [ ] Integration sims: end-to-end scenarios covering fakes + clears + conquest waves + overstack penalties + night/weather; compare against golden reports.
- [ ] Anti-cheat signals: flag impossible command patterns (sub-100ms repeated sends bypassing fake throttles), duplicate command ids, and tampered payloads; resolver must reject with reason codes and log for audit.
- [ ] Plunder math tests: verify vault protection vs hiding place, plunder caps, and loot split across surviving carriers; ensure report matches calculations.

## Monitoring Plan
- Track combat resolver metrics (battles/tick, p50/p95/p99 latency, queue depth) with alerts on regressions after release.
- Monitor validation error rates (`ERR_PROTECTED`, `ERR_MIN_POP`, `ERR_RATE_LIMIT`) and fake/throttle hits to catch misconfigurations.
- Measure report generation time and payload size deltas per deployment; alert on >20% increases.
- Use canary worlds with tighter thresholds to detect issues before global rollout.
