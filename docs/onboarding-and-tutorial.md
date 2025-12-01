# Onboarding & Tutorial

## Goals
- Get new players through basics (production, construction, attack, trade) within first 10 minutes.
- Reduce day-1 churn and support onramp for mobile/desktop.

## Tasks
- Create a guided tutorial script covering: collect resources, upgrade HQ, build a lumber camp, train 5 spearmen, scout a barbarian camp, send first attack, accept a market trade.
- Implement UI hints: step-by-step overlays with skip/next, highlight target UI element, and concise copy (<120 chars).
- Add starter boosts: 15-minute production buff and build-speed buff; auto-expire or when tutorial completes.
- Safety rails: block PvP attacks until tutorial done or 12h elapsed; allow barbarian attacks.
- Rewards: grant small resource packs and 50 gold at key steps; show reward toast.
- Persistence: store tutorial state per village; resume after logout; server-side validation to prevent bypass.
- Metrics: log step completion, skips, time-to-complete; dashboard for completion rate and drop-off.

### Tutorial Flow & Validation
- **Step order**: Collect resources → Upgrade HQ (to 2) → Build Lumber Camp (to 1) → Train 5 Spearmen → Scout Barbarian Camp → Send first attack (vs barb) → Accept market trade.
- **Triggering**: Each step unlocks only after prior step server-confirmed; client shows “pending validation” spinner until server ack.
- **Completion checks**: Server-side validate resource delta, building level, unit count, scout report presence, attack target type, and trade acceptance before marking step complete.
- **Rewards**: 5–10% of starting storage per economy step; 50 gold after first attack; reward claimed on server to avoid dupes.
- **Skips**: Skipping auto-marks step as complete without rewards; skip counts toward metrics.
- **Timeouts**: Steps auto-skip after 10 minutes of inactivity with a prompt; player can resume or skip manually.

### Metrics & Anti-Abuse
- **Events**: `tutorial_start`, `step_start`, `step_complete`, `step_skip`, `tutorial_complete`, `tutorial_abandon` (timestamp, step_id, client, platform, latency).
- **Guards**: No PvP commands allowed while tutorial incomplete; server rejects with clear error code. Barbarian-only restriction enforced server-side on attack target.
- **Rate limits**: Max 3 tutorial restarts per account per day; repeated abandons flag the account for review.
- **Dashboard**: Funnel view (start → each step → complete), median/p95 time per step, skip rates, platform split, and error codes surfaced for failures.
- **Data retention**: Keep event logs 30 days; aggregates kept longer for trend reporting.

## Acceptance Criteria
- Tutorial can be completed in under 10 minutes without errors on desktop and mobile.
- Each step is validated server-side; skipping still advances state cleanly.
- PvP is blocked until tutorial completion or 12h; barbarian attacks still allowed.
- Metrics collected for start, each step completion/skip, and finish, viewable in a simple dashboard/export.
