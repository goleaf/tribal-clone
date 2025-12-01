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
- [ ] Build “next best action” nudges for empty queues, stale intel, near-cap resources, and expiring tasks; localize copy; add dismiss duration.
- [ ] Daily/weekly hooks: backend for tasks/challenges with reroll logic, reset timers, and claim states; emit telemetry.
- [ ] Notification system: opt-in web/mobile push for attacks, builds/recruits done, task resets; respect quiet hours/night bonus windows.
- [ ] Catch-up buffs: late-joiner production boosts and rebuild packs after wipes; ensure anti-abuse caps and expiries.
- [ ] Sitter/role delegation (if enabled): permissions for sending support/attacks; audit actions; optional per-world enable.
- [ ] Loop-specific tutorials/tooltips: surface context tips (empty queue, overcap resources, stale intel) with reason codes and quick actions.

## Acceptance Criteria
- Loop nudges fire appropriately (empty queues, overcap resources, stale intel, expiring tasks) with localized copy and snooze/dismiss; no spam outside configured cadence.
- Daily/weekly tasks/challenges reset correctly, reroll limits enforced, claims tracked; telemetry captures starts/completions/rerolls.
- Notifications respect opt-in and quiet hours; arrival aligns with server events; no notifications for blocked actions (e.g., protected PvP).
- Catch-up buffs apply once per eligible player and expire on schedule; cannot stack with beginner protection exploits; telemetry shows adoption.
- Sitter/role actions audited; permissions enforced per world; reason codes returned on blocked actions.

### Loop Metrics Plan
- **Production & Queues:** HQ/build/recruit/research queue uptime %, average wait time to next slot, % time with zero active queues per segment.
- **Combat & Raids:** Raids per player/day, plundered resources, attack/support sent/received, scout runs, stale intel refresh rate.
- **Progression:** Tasks/challenges completed, reroll counts, daily/weekly retention tasks claimed, late-joiner buff uptake, rebuild pack claims.
- **Engagement:** Tribe ops participation (commands tagged to ops), chat/forum posts per day, notifications opt-in rate, push delivery success.
- **Safety/Abuse Signals:** Protection abuse (high send/receive during beginner), sitter/role actions count, rate-limit hits on commands/aid/scouts.
- **Instrumentation Notes:** Emit via telemetry client (game + backend), tag by player segment (casual/mid/hardcore), world id, and session. Alert on drops in queue uptime or spikes in rate-limit hits.
- [ ] Anti-burnout: add DND/quiet-hour scheduling with auto-snooze on flood (attack waves) and post-war cooldown reminders; log usage to tune defaults.
- [ ] Safety checks: block PvP sends while under beginner protection; return `ERR_PROTECTED` with guidance; guard against zero-pop sends and duplicate commands on resend.
- [ ] Telemetry: per-loop funnel (scout → raid → queue update), time-in-state (building, attacking, idle), and drop-off points; alerts on churn spikes after wipes/war losses.
