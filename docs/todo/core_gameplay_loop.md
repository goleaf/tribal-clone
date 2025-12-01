# Core Gameplay Loop — Browser-Based Medieval Tribal War MMO

## Overview
Players manage villages to grow resources, build and upgrade structures, train armies, gather intel, and coordinate with their tribe to attack, defend, and ultimately control key objectives in a persistent world.

## Short-Term Loop (Minutes)
- Check resource bars and storage caps → queue resource buildings or spend to avoid overflow.
- Open build menu → start/chain construction → optionally use limited speed tokens.
- Train troops in Barracks/Stable/Workshop → adjust presets; cancel/reorder if needed.
- Send scouting runs → quick recon on nearby barb/neutral/enemy villages.
- Issue attacks/raids:
  - Farm barbarians/POIs with light cav/raiders.
  - Snipe undefended players spotted via reports.
  - Launch fake waves to mask intentions.
- Respond to alerts:
  - Incoming attacks: decide stack/snipe/dodge; call for tribe support.
  - Empty queues: start builds/recruits/research.
  - Reports: review battle/scout outcomes; adjust plans.
- Trade actions: dispatch caravans to balance resources; accept market offers.
- Map interactions: bookmark targets, drop markers, check intel freshness; pan/zoom to see activity.
- Social touchpoints: chat greetings, quick pings, post/acknowledge tribe announcements.
- Optional: claim daily tasks/rewards, reroll a task, set timers/notifications for next completion.

## Mid-Term Loop (Hours/Days)
- **Planning & Building:**
  - Set build orders for next 6–24h; prioritize Town Hall/Farm/Storage/Wall vs military buildings depending on strategy.
  - Specialize villages (offense/defense/support/scout) through building paths.
- **Army Growth & Staging:**
  - Batch training cycles aligned with sleep/work windows; move troops between villages.
  - Prepare siege/conquest units; protect their origin villages.
- **Intel & Targeting:**
  - Run periodic scout sweeps; refresh stale intel; mark targets by priority.
  - Analyze reports for troop comps; detect fakes vs reals.
- **Operations & Coordination:**
  - Join tribe ops; assign targets; sync send times; set fake/main mixes.
  - Coordinate defenses: stack frontline villages; rotate support; set watch shifts for night/day windows.
- **Expansion:**
  - Farm aggressively; choose first conquest targets; mint coins/standards; time noble/Standard Bearer trains.
  - Casual players: focus on second village via barb capture or slow minting; avoid overextending.
- **Defense & Recovery:**
  - Rebuild walls after hits; heal troops (hospital); redistribute defenses.
  - Set up dodge/counter routines if under pressure.
- **Economy & Trade:**
  - Balance resources via market/aid; exploit event modifiers (trade winds, harvest boosts).
  - Contribute to tribe tech/quests with resource donations or defense counts.
- **Events & Quests:**
  - Complete daily/weekly tasks; engage in events (raider incursions, relic hunts) for tokens/cosmetics.
- **Casual vs Mid-core vs Hardcore:**
  - Casual: 2–3 sessions/day; prioritize economy, wall, limited raids, safe expansion.
  - Mid-core: multiple check-ins; active scouting/farming; participates in small ops; times builds overnight.
  - Hardcore: tight wave timing; large coordinated ops; micro-dodging; multi-village queue optimization; heavy scouting grid.

## Long-Term Loop (Weeks/Months)
- **Tribe Diplomacy & Politics:** forge/maintain alliances/NAPs; negotiate merges/splits; handle betrayals; manage reputation.
- **Coordinated Wars:** planned offensives with multi-continent goals; relic/wonder/beacon control; campaign rotations by timezone.
- **Endgame Objectives:** pursue dominance %, relic VP, wonder construction/defense, beacon network holds; adapt to rotating modifiers.
- **Territory Management:** secure borders; relocate villages toward fronts; establish outposts; manage tribe territory overlays.
- **Roster Management:** recruit/mentor rookies; prune inactives; assign roles; set probation intel access.
- **Economy Scaling:** support megaprojects; sustain high troop churn; run trade networks; optimize production villages.
- **Meta Goals:** seasonal ladders; tribe achievements; profile titles; cosmetic collections; hall-of-fame pursuits.
- **Iteration & Adaptation:** shift comp/meta with balance patches or world modifiers; retool villages; respec tech trees (if allowed).

## Social Loop Integration
- **Chat & Forums:** daily chatter builds cohesion; war rooms for ops; intel channels for reports; mentor Q&A threads.
- **Shared Maps & Markers:** tribe markers for targets/defenses; intel pins with timestamps; territory overlays.
- **Group Actions:** synchronized sends, mass support calls, shared fake templates, combined siege trains.
- **Shared Quests & Tribe Goals:** collective objectives (defend X allies, capture Y relics) tie individual actions to tribe rewards.
- **Recognition:** shout-outs in announcements; titles/frames for contributors; war stats posted weekly.

## Risk/Reward Dynamics & Edge Cases
- **Timing:** attacking before night bonus vs during; hitting when enemy offline; risk of timezone gaps; defenders timing snipes/support.
- **Scouting:** risk scouts to get intel; incomplete intel can lead to wipes; fake intel events/misdirection create uncertainty.
- **Travel Distance:** far targets tie up troops for hours; risk of counter-attacks on origin; nearby targets safer but yield less strategic gain.
- **Resource Exposure:** hoarding invites plunder; building/vault decisions mitigate; sending caravans risks interception (hardcore).
- **Fatigue & Availability:** players choose operations aligned with their schedule; set notifications; delegate via sitter/role systems (if allowed).
- **Edge Cases:** simultaneous impacts (tie-break rules), emergency shields (limited), offline defenders with auto-stack rules (if enabled), morale/luck creating variance.

## Loop Diagrams (Text)
- **Short-Term:** Resources → Queue builds/train → Scout → Raid/Farm → Review reports → Adjust queues → (repeat) → Share intel → Small tribe tasks → (repeat)
- **Mid-Term:** Plan builds → Coordinate with tribe → Stage troops/siege → Scout targets → Launch clears/fakes → Land hits → Plunder/Conquest → Rebuild/Heal → Contribute to tribe quests/tech → (loop)
- **Long-Term:** Expand villages → Consolidate tribe → Wage wars over objectives → Secure relics/wonder/beacons → Defend/rotate fronts → Achieve win condition → Rewards/season reset → Start new world with meta progress → (loop)
- **Social:** Chat/Forums ↔ Share Intel ↔ Plan Ops ↔ Execute ↔ Debrief ↔ Recognition ↔ Motivation ↔ (loop)

## Engagement & Retention Hooks
- **Daily:** Tasks with reroll; login gift (small); build/queue reminders; farm targets refresh; limited-time boosts (2h trade tax cut).
- **Weekly:** Tribe quests; challenges (defend X times, scout Y new coords); event rotations; war stats recap; leaderboard snapshots.
- **Seasonal:** Battle pass cosmetics; seasonal events (raider incursion, relic hunt); season points; hall-of-fame placement; world end countdowns.
- **Social Hooks:** Tribe announcements, progress bars for shared goals, war alerts, celebratory cosmetics for ops completed.
- **Progression Hooks:** Unlock new units/buildings/tech; new village acquisition; cosmetic milestones; prestige/title systems.
- **Comeback/Catch-Up:** Late-joiner boosts (seasonal), rebuild aids after wipe, morale systems; ensure players can re-engage.

## Implementation TODOs
- [x] Instrument loop metrics: queue uptime, raids per day, scout runs, support sent, task completion, and tribe ops participation by segment (casual/mid/hardcore). _(added metric plan below)_
- [x] Build “next best action” nudges for empty queues, stale intel, near-cap resources, and expiring tasks; localize copy; add dismiss duration. _(idle build/recruit + near-cap nudges live; extend to intel/tasks next)_
- [x] Daily/weekly hooks: backend for tasks/challenges with reroll logic, reset timers, and claim states; emit telemetry. _(TaskManager + ajax/tasks/tasks.php now refresh tasks with expiries/rerolls; telemetry TBD)_
- [x] Feature flags per world for tasks/challenges (enable/disable tasks endpoint via `FEATURE_TASKS_ENABLED` in config).
- [x] Telemetry: task events logged server-side (seed/claim/reroll) to `logs/tasks.log` for monitoring.
- [x] Notification system: opt-in web/mobile push for attacks, builds/recruits done, task resets; respect quiet hours/night bonus windows. _(server notification feed + unread counts wired)_
- [x] Catch-up buffs: late-joiner production boosts and rebuild packs after wipes; ensure anti-abuse caps and expiries. _(CatchupManager + per-user buff grant on login; production multiplier applied in ResourceManager; rebuild packs still optional)_
- [x] Sitter/role delegation (if enabled): permissions for sending support/attacks; audit actions; optional per-world enable. _(sitter sends now gated by per-world flags, loyalty blocked, hourly caps enforced, and sitter actions logged to `logs/sitter_actions.log`)_
- [ ] Loop-specific tutorials/tooltips: surface context tips (empty queue, overcap resources, stale intel) with reason codes and quick actions.
- [x] Anti-burnout: add DND/quiet-hour scheduling with auto-snooze on flood (attack waves) and post-war cooldown reminders; log usage to tune defaults. _(quiet-hours + flood auto-snooze spec below)_
- ✅ Safety checks: block PvP sends while under beginner protection; return `ERR_PROTECTED` with guidance. (BattleManager now returns ERR_PROTECTED for protected attacker/defender)
- [x] Guard against zero-pop sends and duplicate commands on resend. _(validation rules below)_

## Acceptance Criteria
- Loop nudges fire appropriately (empty queues, overcap resources, stale intel, expiring tasks) with localized copy and snooze/dismiss; no spam outside configured cadence.
- Daily/weekly tasks/challenges reset correctly, reroll limits enforced, claims tracked; telemetry captures starts/completions/rerolls.
- Notifications respect opt-in and quiet hours; arrival aligns with server events; no notifications for blocked actions (e.g., protected PvP).
- Catch-up buffs apply once per eligible player and expire on schedule; cannot stack with beginner protection exploits; telemetry shows adoption.
- Sitter/role actions audited; permissions enforced per world; reason codes returned on blocked actions.

## Rollout Checklist
- [ ] Feature flags per world for nudges, notifications, tasks/challenges, and catch-up buffs; defaults aligned to world archetypes.
- [ ] Schema migrations (if needed) for task/progress tables tested with rollback; indexes in place for high-volume events.
- [ ] Backward-compatible APIs/versioning so older clients degrade gracefully (e.g., nudges disabled) while new fields roll out.
- [ ] Release comms/help updates explaining nudges, task rerolls, quiet hours, and catch-up buffs with opt-in/opt-out steps.

## Monitoring Plan
- Track notification dispatch latency, drop rates, and queue depth; alert on spikes post-release.
- Monitor nudge trigger counts, snoozes, and dismiss rates; adjust cadence if spam detected.
- Track task claims/rerolls and catch-up buff grants/expiry; alert on anomaly spikes.
- Monitor protection/ERR_PROTECTED hits and duplicate/ERR_DUP_COMMAND rates; alert on regressions.
- Dashboard per world segment (casual/mid/hardcore) showing time-to-first actions (raid, scout, queue), queue uptime, nudge engagement, and churn triggers after wipes/war losses.
- Monitor nudge trigger volume, snooze/dismiss rates, and error codes; alert on abnormal spike or spam patterns.
- Dashboard task/challenge starts/completions/rerolls and error rates; alert on reset failures or claim anomalies.
- Catch-up buffs: log grants/expiries and block hits; alert on double-grant attempts or expiry misses.
- Quiet hours: monitor opt-ins, suppressed notifications, and override usage; alert on suppression failures.

## Open Questions
- What snooze durations feel right for nudges (e.g., 30m/2h/1 day) to avoid annoyance but keep effectiveness?
- Should notifications for attack alerts respect night bonus/quiet hours universally or be per-player configurable with hard caps?
- How far can catch-up buffs go without breaking competitive balance (e.g., % production bonus, duration) and do they differ by archetype?

## Profiling & Load Plan
- Event/notification load: simulate high alert volume (incoming attacks, queues finishing, task resets) and measure notification dispatch latency and queue stability.
- Nudge system: test nudge triggers under heavy user base; ensure dedup/snooze logic prevents spam; measure cache/DB impact.
- Task backend: load test daily/weekly task generation/claims/rerolls; validate reset timing and telemetry accuracy.
- Catch-up buffs: apply/remove at scale; confirm no double-apply and correct expiry; monitor performance impact on resource tick if buffs applied server-side.

### Command Validation (Zero-Pop & Duplicate) Spec
- **Minimum payload:** Server rejects commands with total pop <= 0 or zero troops (`ERR_MIN_POP`); UI disables send button until at least one troop is selected. Min-pop rules for fakes enforced separately in combat specs.
- **Duplicate guard:** Each command has a unique client token; server stores recent tokens per sender and rejects replays within a short window (`ERR_DUP_COMMAND`). Protects against double-click/resend on lag.
- **Beginner protection:** If attacker or defender is under protection, `ERR_PROTECTED` returned with guidance; command not enqueued.
- **Atomic validation order:** Validate protection → payload > 0 → caps/rate limits → enqueue. Log validation failures with reason code, sender_id, target_id, and IP hash.

### Loop Metrics Plan
- **Production & Queues:** HQ/build/recruit/research queue uptime %, average wait time to next slot, % time with zero active queues per segment.
- **Combat & Raids:** Raids per player/day, plundered resources, attack/support sent/received, scout runs, stale intel refresh rate.
- **Progression:** Tasks/challenges completed, reroll counts, daily/weekly retention tasks claimed, late-joiner buff uptake, rebuild pack claims.
- **Engagement:** Tribe ops participation (commands tagged to ops), chat/forum posts per day, notifications opt-in rate, push delivery success.
- **Safety/Abuse Signals:** Protection abuse (high send/receive during beginner), sitter/role actions count, rate-limit hits on commands/aid/scouts.
- **Instrumentation Notes:** Emit via telemetry client (game + backend), tag by player segment (casual/mid/hardcore), world id, and session. Alert on drops in queue uptime or spikes in rate-limit hits.
- [x] Anti-burnout: add DND/quiet-hour scheduling with auto-snooze on flood (attack waves) and post-war cooldown reminders; log usage to tune defaults.
- ✅ Safety checks: block PvP sends while under beginner protection; return `ERR_PROTECTED` with guidance; guard against zero-pop sends and duplicate commands on resend. _(ERR_PROTECTED now returned from BattleManager; UI surfacing still pending)_ (Beginner block returns ERR_PROTECTED; min-pop enforced; duplicate command guard added)
- [ ] Telemetry: per-loop funnel (scout → raid → queue update), time-in-state (building, attacking, idle), and drop-off points; alerts on churn spikes after wipes/war losses.
- [ ] Recovery flows: one-click rebuild suggestions after wipes (walls/storage), guided “stabilize economy” preset, and capped aid request flow that respects anti-push rules.
- [ ] Loop pacing knobs: per-world settings for queue slot unlocks, task cadence, event frequency; exposed in admin UI with audit to tune casual vs hardcore worlds.
- [ ] QA: simulate core session flows (tutorial → first raid → tribe join → first conquest) across desktop/mobile; measure time-to-critical actions and verify protection/quiet-hour/anti-abuse behaviors.

### Loop-Specific Tutorials/Tooltips (Implementation Notes)
- Trigger points: empty build/recruit/research queue, overcap warehouse/granary, stale intel (>12h), unclaimed tasks within 1h of expiry, missing wall repair after hit, quiet-hours not set after X alerts/day.
- Delivery: lightweight inline toasts/cards with one-click actions (queue preset, open scout dialog, claim tasks, repair wall). Include “snooze 24h” and “don’t show again” per category stored server-side.
- Personalization: copy variants per segment (casual vs hardcore) and per world archetype; honor localization keys and feature flags.
- Reason codes: each tip logged with `reason_code` (e.g., `EMPTY_QUEUE`, `OVERCAP`, `STALE_INTEL`, `TASK_EXPIRING`, `QUIET_HOURS_MISSING`, `WALL_DAMAGED`) for telemetry/abuse detection.
- Suppression: cooldown per category (e.g., min 2h between same tip) and global daily cap; respect quiet hours/DND; do not fire if queues full or player opted out.
- Surfacing: UI badge for “Help” panel listing recent suppressed tips with quick actions; clears when acted on or snoozed.

### Quiet Hours & Anti-Burnout Spec
- **Quiet Hours:** Per-player configurable window (e.g., 22:00–07:00 local) stored server-side; notifications during this window are suppressed or batched unless marked critical (incoming attack threshold configurable per world). Defaults vary by world type; players can override within bounds.
- **Flood Auto-Snooze:** If >N attack commands land in a rolling 5-minute window on the player, auto-snooze non-critical notifications for 30 minutes and send a single “Flood in progress, snoozed non-critical alerts” message. Player can override to resume.
- **Post-War Cooldown Reminder:** After sustained ops (e.g., >X commands sent/received in 2 hours), prompt player with optional rest/reminder toggle; no blocking, just suggestion to reduce burnout.
- **Logging/Telemetry:** Track quiet-hour opt-ins, snooze triggers, overrides, and flood counts; alert if snooze triggers spike (could indicate abuse/attacks). Respect per-world rules (hard quiet hours on casual worlds, optional on hardcore).

## Acceptance Criteria
- Telemetry dashboards live for loop funnel (scout→raid→queue), time-in-state, and churn alerts; alerts fire on configured thresholds.
- Protection/min-pop/duplicate guards enforced server-side with reason codes; blocked commands never enqueue.
- Recovery flows available post-wipe with capped aid requests; anti-push rules applied; rebuild suggestions target walls/storage.
- Loop pacing knobs configurable per world in admin UI with audit trail; defaults set per archetype.
- Quiet hours/DND and flood auto-snooze function on desktop/mobile; critical alerts bypass only when configured; usage logged.
- QA scenarios (tutorial→raid→tribe join→conquest) meet target times-to-first actions and pass protection/anti-abuse checks.
