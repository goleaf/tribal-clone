# Combat Anti-Abuse & Safety Rails

## Goals
- Keep combat competitive without spam or exploitative behavior.
- Protect new players from predatory tactics while keeping PvP open.
- Preserve server performance by discouraging excessive low-value traffic.

## Core Rules
- **Minimum Payload**: Attacks must include at least 5 population worth of units or 1 siege unit; smaller sends are auto-rejected as noise.
- **Fake Throttling**: More than 20 zero-siege, sub-50-pop attacks to the same target within 60 minutes are rate-limited; subsequent sends are delayed by 5 minutes each.
- **Per-Target Cap**: Maximum 200 concurrent incoming commands (attacks + fakes + scouts) per target village; additional sends queue behind oldest command or are blocked (configurable).
- **Sitter Restrictions**: Sitter accounts cannot launch conquest/loyalty attacks and cannot exceed 10 outgoing commands per hour.
- **Friendly Fire**: Attacks against allied/NAP tribes are blocked unless leadership override is used; overrides log a reputation penalty and are visible to both tribes.
- **Beginner Shield**: Villages under beginner protection cannot be hit by siege or loyalty-reduction attacks; normal raids still allowed after 24 hours.

## Detection & Enforcement
- **IP/Alt Linking**: Kills/points from accounts flagged as linked do not count toward war scores or quests; loyalty cannot be reduced by linked accounts.
- **Pattern Detection**: Repeated coordinated fakes from the same subnet to multiple targets trigger progressive delays and alert logs for moderators.
- **Command Audits**: All overrides (ally attacks, cap bypasses) record actor, time, IP hash, and reason; exposed in tribe and admin audit views.
- **Abuse Flags**: Exceeding caps or triggering throttles raises a soft flag; 3 soft flags in 24h temporarily halves outgoing command cap for that account.

## Player Feedback
- **Pre-Send Warnings**: UI surfaces when a send will be throttled or blocked, showing the limit and time until allowed.
- **Live Caps**: Incoming/outgoing command meters per village with color-coded thresholds (green <50%, yellow 50-80%, red 80%+).
- **Reason Codes**: Blocked commands return clear codes (e.g., `MIN_PAYLOAD`, `TARGET_CAP_REACHED`, `ALLY_OVERRIDE_REQUIRED`).
- **Tribe Visibility**: Tribe leadership dashboard lists members currently rate-limited or under soft flags to coordinate behavior.
