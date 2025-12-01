# Notifications, Reports, and Timers — UX Design for Medieval Tribal War Browser MMO

## Notification Types (What Triggers Alerts)
- **Incoming Attacks/Supports:** Incoming hostile commands, friendly supports, siege-only waves, fake indicators, noble-bearing attacks.
- **Construction:** Building start/complete, queue blocked (resources missing), paused/delayed builds.
- **Recruitment:** Troop training completion, queue finished, queue halted (population/storage shortfall), premium/QoL queue slots expiring.
- **Troop Movements:** Armies arrived (attack/support/return), dodge executed, retreat failures, convoy interception (if enabled).
- **Trades & Resources:** Incoming/outgoing caravans, trade accepted/declined, market orders filled, aid taxed.
- **Tribe & Social:** Tribe announcements, new operation assignments, rally time pings, role changes, diplomacy state changes, tribe quests updated, member online/pings (configurable), friend login/achievement.
- **Events & Timers:** Event start/end warnings, personal event milestones reached, reward claim reminders, battle pass tier unlocks.
- **System:** Protection ending, relocation available/expiring, shield/truce timers, spending cap reminders, report inbox near capacity.

## Notification Channels (Delivery & Trade-offs)
| Channel | Pros | Cons | Use Cases |
| --- | --- | --- | --- |
| On-screen banners/toasts (top-right) | Immediate, glanceable, can show countdown | Can stack/clutter; need throttle | Attack warnings, build complete, event expiring |
| Icon badges (header nav) | Low noise, persistent until seen | Easy to ignore; needs color coding | Reports, tribe messages, shop/event reminders |
| Sidebar feed (collapsible) | High density, filterable | Consumes space on mobile; requires grouping | All notifications with filters; ops timeline |
| In-game mail/messages | Rich text, archives | Slower to open; not for urgent items | Tribe announcements, diplomacy updates, system notices |
| Browser notifications (opt-in) | Works while tab unfocused | Requires permission; platform variance | Incoming attacks/supports, build/recruit complete, trade arrival |
| Mobile-friendly banners (responsive UI) | Single-line, thumb-reachable actions | Limited text; must avoid spam | Attack alerts, op assignments, reward claims |
| Sound/vibration cues | Non-visual channel | Accessibility concerns; context sensitive | Attack alarms, rally send reminders (user-configurable) |

### Channel Guidelines
- Default to **quiet**: only high-severity (incoming attacks, tribe pings) trigger sound/push by default; others badge-only.
- **Grouping**: batch similar notifications (e.g., “5 builds completed”) to avoid spam.
- **User controls**: per-type toggles, do-not-disturb schedules, push opt-in, volume sliders, and "snooze" for 30–120 minutes.

## Timer Presentation (Ongoing Actions)
- **Global Action Bar:** Fixed header strip with critical countdowns (next attack landing on any village, protection end, event expiry).
- **Village Timers Panel:** Per-village grouped timers for build queues, recruit queues, research, and ongoing commands (incoming/outgoing/support). Sort by soonest completion.
- **Map Command Lines:** Lines/arrows with arrival time badges; color-coded by type (attack red, support green, trade blue, event purple). Hover/tap reveals exact arrival timestamp and slowest unit.
- **List Styles:**
  - **Grouped by Type:** Attacks grouped per village; trades grouped per origin/destination.
  - **Stacked Countdowns:** e.g., Attack wave list shows T-00:23, T-00:47, T-01:12 for fakes/mains; bold for nobles if detected.
  - **Progress Bars:** For builds and training; percent with ETA; bars dim when paused/blockers.
- **Countdown Formatting:** Relative (T-00:05:12) primary; absolute timestamps secondary on hover/tap. Flash or highlight under 60s (desktop) / vibrate pulse (mobile) if enabled.
- **Batch Actions:** From timers list, open rally presets, jump to village, or apply dodge/stack presets.

## Report Types & Structures
- **Battle Reports:**
  - Fields: Date/time, location (village), attacker/defender, diplomacy state, troop sent/lost/survived, wall change, building damage, loyalty/allegiance change, loot, morale/luck values, support present, night/weather modifiers.
  - Example: `Battle at Oakridge (X:512|489) — Result: Defender Victory. Attacker: RavenTribe/Arlen. Defender: OakGuard/Brina. Wall: 8→6. Loyalty: -12 (88→76). Luck: -4%. Morale: 0.72. Loot: 0 (vault protected).`
- **Scouting Reports:**
  - Fields: Target coords, scouts sent/survived, detected troops (ranges if partial), buildings, resources, traps/watchtower info, recent movement traces (if watchtower/pathfinder features), report fidelity tier.
  - Example: `Scout Recon on Bramblehold (498|501): Troops seen (est): 200–260 Spears, 60–90 Archers; Wall 10; Resources: Wood 12k, Clay 11k, Iron 9k (Vault hides ~2k each); Watchtower present; Our scouts lost: 3/20.`
- **Trade Reports:**
  - Fields: Sender/receiver, resources shipped/received, tax, travel time, convoy losses (if interception), market order details.
  - Example: `Trade Complete: You received 6,000 Wood, 4,000 Clay, 2,000 Iron from Ally (tax 8%) — Arrival 14:22.`
- **Tribe Reports:**
  - Fields: New diplomacy states, member joins/leaves, role changes, op assignments, tribe quest progress, shared intel highlights.
  - Example: `Diplomacy Update: War declared on IronFist. Ceasefire cooldown ends 24h.`
- **System Reports:**
  - Fields: Protection ending, relocation available, rule changes, spending cap reminders, ban/rollback notices.
  - Example: `Beginner Shield ends in 12h. Consider upgrading Wall and setting alerts.`
- **Event Reports:**
  - Fields: Event progress, reward claims, leaderboard placement, bonus modifiers active.
  - Example: `Harvest Event: You placed 12th in Province for Day 2. Reward: 150 Tokens, Harvest Banner (cosmetic).`

### Report Presentation
- **List View:** Paginated with infinite scroll, tag chips by type (Battle, Scout, Trade, Tribe, System, Event), color-coded status (Win/Loss/Neutral).
- **Detail View:** Tabs for Summary, Troops, Buildings, Loot/Costs, Timeline (for multi-wave battles), Attachments (shared intel, markers).
- **Sharing:** One-click share to tribe forum/ops with permission; redact resources/troops if chosen.

## Prioritization & Filtering
- **Priority Markers:** Star/pin important reports (stay at top); auto-pin incoming attack warnings until resolved.
- **Folders/Tags:** Smart folders (All Battles, Defense Wins, Scout Intel, Trades, Tribe Ops). Custom tags allowed.
- **Filters:** By type, date range, village, opponent tribe, outcome, contains nobles/siege, night/weather modifier, unread/archived.
- **Search:** Keyword + structured search (e.g., `type:battle result:loss tribe:IronFist nobles:true`).
- **Archive/Delete:** Batch select; archive keeps for reference, delete purges. Auto-archive after N days (configurable) with exceptions for starred.

## Anti-Spam Design
- **Aggregation:** Combine multiple similar events: “4 attacks from IronFist landing in next 5m,” “3 builds finished.”
- **Rate Limits:** Per-minute toast cap; overflow goes to sidebar feed only.
- **Noise Controls:** User can mute non-critical categories, set quiet hours, and enable only badges for low-severity types.
- **Smart Escalation:** If >X attacks on same village within Y minutes, escalate to high-priority alarm instead of many small toasts.
- **Auto-Expiration:** Low-value toasts expire quickly; critical ones persist until acknowledged.
- **Cross-Device Respect:** Notifications sync read/muted state across devices to avoid double spam.

## Reliability & Delivery
- **Dedupe & Collapse:** Server sends idempotent notifications with `event_id` and `thread_id`; clients dedupe and collapse updates into a single card.
- **Retry & TTL:** Push/webhook retries with backoff; expire low-priority events after 10m; high-priority (attacks) after landing/resolve.
- **Quiet Hours & Overrides:** Per-device quiet hours with override toggle for attack alerts and tribe pings; respect system focus mode when available.
- **Sync:** Read/ack state syncs across devices within 10s; offline clients batch updates and replay on reconnect.
- **Backpressure:** When more than N events/minute, server aggregates and emits summary packets; client shows “compressed feed” banner.
- **Telemetry:** Emit delivery success/fail, latency p50/p95, and drop counts by channel; alert on spikes in failed pushes or dedupe misses.

## Example Scenarios
- **Massive Incoming Wave:**
  - Global action bar highlights “Next impact T-00:02:10 (3 nobles detected).”
  - Attack icon badge shows red badge “12”; sidebar groups as “12 incoming on Oakridge” with stacked countdowns; noble-bearing waves marked with crown icon.
  - Toasts collapse into one aggregated banner: “Multiple attacks incoming on Oakridge — earliest T-00:02:10.” User taps to open rally presets; can pin report thread for this village.
  - Sound/vibration triggers once (configurable). Subsequent waves within 60s suppress new sounds.
  - Reports auto-pin after resolution (battle outcomes) for review; user can share summarized report to tribe ops.
- **Quiet Farming Period:**
  - Minimal toasts; small green badge for “Trades completed (3)” and “Build queue empty” reminder.
  - Timers panel shows outgoing farm runs with calm blue bars; returns trigger silent badges only.
  - Event reminder appears as subtle banner: “Harvest Event ends in 4h — claim tokens.”
  - Player can snooze tribe pings for 30 minutes; inbox remains tidy via auto-archive of old farm reports.
