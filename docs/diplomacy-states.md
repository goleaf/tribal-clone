# Diplomacy States & War Declaration

## Relationship Types

1) **Neutral (default)**
- Attacks allowed without warnings
- No intel sharing; gray map color
- Standard trade tax

2) **Non-Aggression Pact (NAP)**
- Attacks show warning modal but are not blocked
- Limited intel sharing (last-seen, village count)
- Map color: blue
- Breaking NAP: -reputation, world notice, 24h lockout on new treaties

3) **Alliance**
- Attacks blocked by default (requires leadership override)
- Full intel sharing (scout reports, movement visibility if enabled)
- Map color: green; allied territory overlay
- Support troops allowed with -20% travel time; reduced market tax
- Breaking alliance: major reputation loss, 48h treaty lockout, auto-recall support

4) **War**
- Attacks free; support blocked
- War markers on map (red), war feed in tribe UI
- Shared war stats: losses, villages conquered, objectives
- No trading between war targets (or taxed heavily if enabled)

5) **Armistice / Truce**
- Temporary ceasefire with timer
- Attacks blocked; support allowed if agreed
- Automatically expires; converts to Neutral unless upgraded to NAP/Alliance

### State Rules
- Only one primary state per tribe pair (Neutral, NAP, Alliance, War, Truce)
- Minimum duration: NAP 7d, Alliance 14d, War 24h, Truce 12h
- Cooldowns: cannot re-ally within 48h after breaking an alliance; cannot declare war twice within 24h on same tribe
- Mutual consent required for NAP/Alliance/Truce; unilateral for War (with warning timer)

### Intel Sharing Matrix
- **Layers**: Basic (village list/points), Reports (scout/battle), Live (movements/commands), and Status (online indicators if enabled).
- **Neutral**: None.
- **NAP**: Basic + Status; no reports/live.
- **Alliance**: Basic + Reports; Live optional toggle per treaty. Support/attack movements visible with ETA; can be restricted to War state only.
- **Truce**: Keeps previous non-live layers; live data suspended during truce.
- **War**: No sharing; shows enemy war markers and last-seen intel if scouted.
- **Controls**: Need-to-know flag hides specific operations/villages from allies; all hides are logged.
- **Staleness**: Live intel auto-expires after 15 minutes idle; shared reports auto-expire after 14 days.

---

## War Declaration & Resolution

### Declaration Flow
1) Diplomat/Leader chooses target tribe and sets war reason and start timer (default T+12h).
2) Declaration broadcast to both tribes and world feed; map highlights pending war.
3) During prep window: attacks allowed but do not count toward war stats; support still allowed.
4) At start time: war state active; support between tribes blocked; war stats begin tracking.

### Victory Conditions (configurable)
- **Points**: first to X victory points (kills, village captures, objectives)
- **Territory**: control Y% of contested sectors for Z hours
- **Attrition**: hold kill/loss ratio above threshold for N days
- **Surrender**: leadership of either tribe can offer surrender terms; other side must accept

### War Points & Objective Tracking
- **Default Scoring**: 1 point per 1,000 enemy pop killed, 25 points per village captured, 5 points per successful defense with 3:1 casualty ratio.
- **Objective Tiles**: Map objectives grant periodic points while held; control requires majority presence for 10 minutes.
- **Decay**: War points decay 2% per day after 7 days to incentivize momentum; village capture points do not decay.
- **Anti-Cheat Filters**: No points from friendly-fire or kills on allied/alt-tagged accounts; repeated trading of the same village within 48h yields zero points.
- **UI**: War dashboard shows total points, trend over last 24h, and per-member contribution; exports to war summary at end.

### Resolution & Aftermath
- Auto-resolves after 30 days of inactivity (no captures/kills) or hitting victory condition
- Peace treaty cooldown: 24h of enforced Truce after war ends
- War summary report archived: losses, captures, MVPs, timeline
- Reputation shifts: declaring on much smaller tribes reduces reputation; beating stronger tribes grants prestige

---

## Reputation & Betrayal

- **Reputation Score**: tribe-level metric shown on profile; influenced by honoring treaties, fair wars, and breaking pacts.
- **Negative Actions**: breaking NAP/Alliance early (-reputation), friendly fire incidents, repeated war declarations on much weaker tribes.
- **Positive Actions**: holding treaties full term, honoring surrenders, defending allies, cleanly ending wars.
- **Threshold Effects**:
  - Very Low: higher market taxes, slower treaty acceptance, warning badges on profile.
  - High: discounted diplomacy costs, faster treaty approvals, cosmetic laurels on tag.
- **Logging**: all treaty changes and betrayals appear in a public log with timestamps and actor role.

### Treaty Management UX
- **Request Flow**: Propose, counter, accept/decline with reason; shows cost/timers before confirmation.
- **Visibility**: Pending treaties visible to leadership and diplomats; members see state and timers only.
- **Warnings**: Attack order to treaty partner triggers confirmation with penalty reminder; override requires leadership rank.
- **Timers**: Countdown chips for minimum duration and cooldowns; hover shows exact end time and who set it.
- **History**: Treaty timeline (created, accepted, broken, expired) with actor, timestamp, and reputation delta.

### Costs, Limits, and Safeguards
- **Diplomacy Points**: Tribe currency earned via quests/wars; consumed to propose treaties (NAP low, Alliance medium, War zero, Truce low).
- **Gold Surcharge**: Optional gold fee for instant treaty activation; otherwise 1-hour processing window where either side can cancel.
- **Rate Limits**: Max 3 outgoing treaty proposals per 24h; repeated toggling to same tribe within 48h is blocked.
- **Power Disparity Guard**: Alliance proposals require reputation above threshold when target tribe is >2x smaller; prevents bullying.
- **Grace Period**: Breaking a treaty within its minimum duration triggers a reputation hit and a 24h diplomatic cooldown for the breaker.
- **Auditability**: All costs and penalties shown before confirmation; ledger shows who paid and remaining diplomacy points.
