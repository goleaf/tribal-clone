# AI Assistants & Scripting Limits — TODOs

## Goals
- Keep gameplay skill-based; allow helper tooling without enabling botting or unfair automation.
- Provide clear boundaries for UI helpers (presets, reminders) vs. prohibited automation (auto-send attacks, farm bots).
- Detect and deter abusive patterns with transparent enforcement.

## Rules & Boundaries
- **Allowed QoL:** Saved presets, calculators (travel time, loot), inline recommendations, and reminder timers initiated by the player.
- **Prohibited Automation:** Auto-sending commands, auto-accepting trades, continuous farming loops, and decision-making without player input.
- **Rate Caps:** Limit command creation per minute and per hour; stricter caps for repeated low-payload raids to prevent scripting abuse.
- **Visibility:** All AI suggestions must be explainable and dismissible; no hidden actions. Player must confirm any AI-suggested command.

## Detection & Enforcement
- **Behavioral Signals:** Repetitive timing patterns, 24/7 activity, identical command payloads, ultra-low reaction latency, and synchronized multi-account actions.
- **Shadow Mode:** Log suspicious behavior first; if exceeding thresholds, apply soft caps and warnings before bans.
- **Challenges:** Occasional human-check prompts (non-intrusive) after suspicious bursts; failure pauses command sending temporarily.
- **Audit Trail:** Record flagged actions with timestamps, account id, IP/device hash, reason code.
- **Penalties:** Graduated: warning → temp cap → temp ban → permanent ban for confirmed automation/pushing.

## Player UX & Transparency
- **Settings Panel:** Show what is allowed (presets, calculators) and what is not; show current command caps and remaining burst quota.
- **Warnings:** Clear in-UI warnings with reason codes (`RATE_CAP`, `PATTERN_FLAG`, `CHALLENGE_FAIL`); link to appeal/support.
- **Opt-Out:** Players can disable AI suggestions entirely; default on but non-persistent commands still require confirmation.

## Engineering Tasks
- Implement server-side command rate limiting with per-action buckets and burst tracking; expose headers/fields for client messaging.
- Add detection jobs for repetitive patterns (cron + streaming) with thresholds; emit metrics and store flags.
- Build lightweight human-check flow (simple puzzle/button) triggered on flagged bursts; ensure accessibility.
- Instrument all AI suggestions with `suggestion_id`, `accepted/declined`, latency to acceptance; store for audits.
- Add admin review tool to inspect flagged sessions (timeline of commands, flags, challenges).

## Acceptance Criteria
- Clear allow/deny list surfaced to players; all AI-suggested actions require explicit confirmation.
- Rate limits enforced server-side with user-facing error codes and metrics.
- Detection flags and challenges reduce confirmed automation without blocking normal high-skill play (measured by false-positive rate targets).
- Admin tooling available to review and act on flagged accounts with audit logs.

## Implementation Notes
- Rate limiting: define buckets per action type (attack/support/scout/trade) with burst and sustained limits; include per-target caps for spam fakes.
- Reason codes: standardize (`RATE_CAP`, `PATTERN_FLAG`, `CHALLENGE_REQUIRED`, `AUTO_ACTION_BLOCKED`) and surface in API responses for UI.
- Challenge UX: non-intrusive modal with keyboard navigation; 60s timeout; retries limited; fully disabled for accessibility-labeled accounts if required.
- Appeals: in-UI link to support form prefilled with recent reason codes and timestamps; store appeals with outcome and reviewer.
- Logging: persist AI suggestion telemetry and rate-limit hits for 30 days; hash IP/UA; ensure GDPR export includes flags/challenges.
