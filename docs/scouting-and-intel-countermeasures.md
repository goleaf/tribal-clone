# Scouting, Intel, and Counter-Scouting Mechanics — Medieval Tribal War Browser MMO

## Counter-Scouting Mechanics (Detect, Mislead, Punish)
- **Defensive Scouts & Duel Resolution:** Stationed scouts roll against incoming scouts; more defenders = higher survival and report fidelity denial. Tribe/tech modifiers can add flat defense.
- **Watchtower/Beacon Spire:** Building that extends detection radius, reveals inbound scouts earlier, and adds defensive scout strength. Higher levels unlock pre-emptive alerts ("Scouts spotted; ETA 2:30").
- **Traps/Snares:** Consumable defenses that trigger on first wave; kill or capture a % of incoming scouts before duel. Reset after use; can be detected with high scout count.
- **Counter-Intel Techs:**
  - **Camouflage (defense):** Lowers visibility of stationed troops/buildings; forces range-based intel instead of exact counts.
  - **Signal Jammers:** Reduces fidelity of enemy reports (rounds values, hides specific building levels).
  - **Interrogation (offense/defense):** Chance to extract origin info from captured scouts (coords, tribe) if defender wins.
- **Decoy Garrisons:** Assign decoy troops/resources visible only to low-fidelity scouts; higher tiers see through. Configurable per village.
- **Misdirection Reports:** If enabled, defenders can send forged follow-up to attacker with slight delays, showing altered troop counts/buildings (flagged in logs to prevent abuse; cooldowns apply).
- **Anti-Survey Mode:** Temporarily masks building upgrades in progress and queues from being scouted; consumes a resource or has cooldown.
- **Counter-Sabotage:** If sabotage is a scout action, defenders can stack counter-sabotage traps that backfire on attacker (resource loss, scout casualties).

## Intel Decay (Handling Stale Information)
- **Time-Based Fidelity Decay:** Each report gains "staleness" tiers: Fresh (0–2h), Recent (2–12h), Old (12–48h), Stale (48h+). UI color codes and tooltips reflect accuracy.
- **Automatic Value Ranges:** Older reports convert exact numbers to ranges (e.g., 800–1,000 Spears) and building levels to bands (e.g., 15–17).
- **Expiry & Archive:** After X days (configurable per world), reports auto-archive; intel overlays fade out. Starred reports can persist but are marked stale.
- **Activity Overrides:** If the target is seen in combat/trade recently (battle/trade report within Y hours), intel decay partially resets for troop counts but not buildings.
- **Map Overlay Aging:** Intel markers fade opacity with time; tooltip shows "Last scouted 18h ago." Clicking prompts to resend scouts.
- **Tribe Intel Alerts:** Optional pings when key intel becomes stale on priority targets.

## Tribe Intel Sharing (Coordination & Access)
- **Shared Reports Repository:** Tribe-level inbox with tags (Frontline, Relic, Farm, OP). Permissions: view-only for recruits, tag/edit for Intel Officers.
- **Map Markers with Intel:** Pins that carry last-known troop/building info, scout timestamp, and owner. Color-coded by priority and operation. Expire or fade with intel decay.
- **Intel Channels:** Dedicated chat/board for intel dumps; supports embedding report links and coords. Role-based posting rights to avoid spam.
- **Spy/Observer Ranks:** Special role with limited intel view to avoid leaks; alternative is delayed intel sharing for new members (e.g., 24h probation before full access).
- **Assignment & Coverage:** Sector assignments for scouts; dashboard showing which sectors are fresh vs stale; reminders to re-scout gaps.
- **Sharing Controls:** Option to redact resources or exact numbers when sharing outside tribe (allies) while keeping fidelity internally.
- **Audit & Logs:** Who shared which report, when; edits to tags/markers logged for accountability.

## Advanced Features & Anti-Abuse
- **False Reports (Generated):**
  - Defenders can craft false intel items that, when intercepted, show incorrect numbers. Cooldowns and cost ensure sparing use; flagged internally so defenders cannot exploit for self.
  - Anti-abuse: False reports cannot show zero troops/resources; banded within plausible ranges. Limited per day and per village.
- **Fog-of-War Events:** Periodic events increasing fog density (shorter intel range, faster decay) or storms that scramble scout results. Counterplay via weather beacons or temporary intel boosts.
- **Info-Reveal Objectives:** Map objectives (signal fires, watch posts, relics) that, when captured/held, reveal:
  - Movement glimpses in adjacent provinces (without exact counts).
  - Building level snapshots every N minutes.
  - Warning pings for incoming commands from tagged tribes.
- **Interception & Path Watch:** High-level watchtowers can mark paths; if attacker uses same lane repeatedly, defender gains partial intel on future sends. Anti-loop bonus decays over time.
- **Counter-Scout Missions:** Send scouts offensively to destroy enemy scouts stationed in forward outposts; successful mission reduces enemy intel radius temporarily.
- **Anti-Spam & Fairness:**
  - Cap on scout commands per minute per player to reduce bot-like spam.
  - Diminishing returns on repeated failed scouts on same target within an hour (higher losses, worse reports).
  - Transparency: attackers see fidelity tier in report; defenders see whether misdirection/traps triggered.
  - No pay-to-win intel boosts; any premium elements limited to UI/QoL (extra report storage) with no fidelity changes.
  - Rate-limit tribe intel shares per minute to avoid flood; batch multiple shares from same player into one feed item.
  - Apply cooldown to false report generation per village and per player to prevent spam; log each use with actor/timestamp.

## Examples
- **Counter-Scout Punish:** Attacker sends 20 scouts to fortified village with Watchtower 8 + traps. Traps kill 30% on entry; remaining duel defending scouts with +20% from tribe tech. Only 2 attackers survive, yielding a low-fidelity report showing troop ranges; defender logs attacker’s coords via Interrogation.
- **Intel Decay in Action:** Tribe marker on target shows “Fresh 1h.” After 14h it fades to “Old,” showing ranges instead of exacts. Ops leader gets alert to refresh intel before launching noble trains.
- **Tribe Intel Sharing Flow:** Scout returns with full report; player shares to tribe. Intel Officer tags it “OP Dawn” and pins marker on map. Recruits see marker but without resource numbers (redacted). After 24h, marker fades and auto-reminds assigned scout to redo.
- **False Report Event:** During a fog-of-war storm, defender activates a false report after defeating incoming scouts. Attacker receives a report implying high cavalry; actually the village holds infantry stack. Cooldown prevents immediate reuse; logs mark “Possible Misdirection” to discourage over-reliance.
- **Info-Reveal Objective:** Tribe captures a Signal Fire POI; for 30 minutes, all incoming commands within 10 tiles show origin coords and arrival time to tribe members, aiding defense coordination.

## Implementation TODOs
- [ ] Build intel decay service: compute fidelity tier per report (Fresh/Recent/Old/Stale), apply range conversion, and feed UI overlays; emit stale alerts to ops.
- [ ] Add counter-scout resolution to combat: duel math, trap/snare triggers, tribe tech modifiers, and interrogation outcomes; surface results and reason codes in reports.
- [ ] False/misdirection reports: enforce per-village and per-player cooldowns; log actor/timestamp; tag reports as “Possible Misdirection” internally; block zero-troop/resource falses.
- [ ] Intel sharing backend: tribe repo with tags/filters, role-based permissions, share throttling/batching, and map marker API (includes last-scouted timestamp and decay status).
- [x] Anti-spam limits: per-player scout command rate cap, diminishing returns on repeated scouts to same target within 60 minutes, tribe intel share rate cap. _(see rate-limit spec below)_
- [ ] Instrumentation: metrics for scout sends, success/fail, trap triggers, misdirection usage, decay alerts sent, and share throttles; alerts on spikes or abuse patterns.

## Edge Cases & Safeguards
- **Protected Targets:** Beginner/protected villages still block scout intel beyond basic presence; reports show “Protected” and no troop/resource data.
- **Mixed Vision:** When allied intel sharing conflicts with personal fog (you never scouted), show tribe-shared ranges but mark them as “Shared” with trust badge; personal scouts overwrite shared ranges.
- **Clock Skew:** Use server time for staleness; if client time differs, show server time in tooltips to avoid confusion.
- **Bulk Deletes:** Tribe intel bulk-delete requires Intel Officer role and logs actor/time; deletion marks stale markers as removed for all members.
- **Privacy Opt-Outs:** Allies can flag specific villages/ops as “need-to-know”; shared intel hides coords/troops for non-assigned members while still showing marker stub.

## Implementation TODOs
- [ ] Scout duel resolver: defensive scouts vs incoming scouts with watchtower/trap/tech modifiers; output casualties and fidelity tier.
- [x] Intel fidelity tiers: define Fresh/Recent/Old/Stale bands with data redaction rules (ranges, banded building levels) and UI color codes. _(see fidelity spec below)_
- [ ] Intel decay job: periodic task to age reports/markers, fade map overlays, and trigger stale-alert pings for priority targets.
- [ ] Trap/misdirection systems: consumable traps with cooldowns and limits; misdirection/false-report creation with logs to prevent abuse; reason codes in reports.
- [ ] Tribe intel repository: shared report storage with tags, permissions by role, and audit logging of shares/edits; API for map markers carrying intel metadata.
- [ ] Sector coverage dashboard: assign scouts to sectors; display freshness and reminders; optional auto-reminders via tribe alerts.
- [ ] Rate limits: server-side caps on scout commands per player/target per minute/hour; diminishing returns on repeated failed scouts; expose error codes for UI.
- [ ] Anti-abuse checks: limit false reports per day, prevent zero-troop misreports, and forbid premium fidelity boosts; hash IP/UA in logs.

### Fidelity Tier Spec
- **Fresh (0–2h):** Exact counts/building levels shown; UI color Green. Marker opacity 100%. Shows command origin if captured scouts reveal it.
- **Recent (2–12h):** Counts rounded to nearest 5% and displayed as small ranges (e.g., 950–1,000). Buildings banded to ±1 level. UI color Teal; marker opacity 80%.
- **Old (12–48h):** Counts bucketed to 20% bands (e.g., 800–1,000). Buildings shown as level ranges of width 3. Hides queue info and support origin. UI color Amber; opacity 55%.
- **Stale (48h+):** Only category presence (infantry/cav/siege) and “low/med/high” strength; buildings hidden except key defenses (Wall/Watchtower) shown as low/med/high. UI color Red; opacity 30%. Prompts resend-scout CTA.
- **Decayed Overrides:** Any combat/trade report newer than the scout report nudges fidelity up one band for troop presence only (not buildings). Manual “starred” reports keep visibility but never upgrade fidelity.

### Rate-Limit & Anti-Spam Spec
- **Send Caps:** Sliding-window limiter per player: max 20 scout commands / 60s and 200 / hour across all targets. Per-target limiter: max 5 scout commands / 15 minutes per (attacker, target) pair; returns `SCOUT_RATE_LIMITED` error with retry-after seconds.
- **Diminishing Returns:** If more than 3 failed scout attempts to same target within 60 minutes, apply +25% scout casualty and -1 fidelity tier (never below Stale/None) for subsequent attempts in that window.
- **Intel Shares:** Tribe intel share endpoint capped at 10 shares / minute per player and 100 / minute per tribe; burst shares from same player within 30 seconds are auto-batched into one feed item. Returns `INTEL_SHARE_LIMIT` on excess.
- **Reset Windows:** All windows slide; cooldown state stored in fast cache (Redis/memcached) keyed by player_id + target_id. Limits reset naturally; no hard daily cap unless configured.
- **Audit & Messaging:** Every limit breach logged with player_id, target_id (if applicable), IP hash, UA hash, and timestamp. UI shows friendly toast with retry time.
