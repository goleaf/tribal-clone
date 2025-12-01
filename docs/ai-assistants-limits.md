# AI Assistants & Automation Limits

## Goals
- Preserve fair-play by restricting AI-driven automation and advisor tools.
- Make AI usage transparent and non-abusive while keeping player experience smooth.

## Tasks
- Define allowed vs. banned AI uses: allowed = UI translations, accessibility summaries, rule clarifications; banned = automated farming/raiding, combat scripting, multi-account orchestration.
- Implement server-side detection hooks for abnormal command patterns (perfect intervals, 24/7 play, identical timing across accounts); flag for review.
- Add in-game “AI Assistance” toggle: players must disclose if they use external AI helpers for guidance; display badge on profile; requires acceptance of AI policy.
- Rate-limit high-frequency actions (attack creation, scout spam, market spam) with per-account + per-IP caps; surface explicit `RATE_LIMITED` error codes.
- Add CAPTCHA on suspicious bursts (e.g., >50 commands in 5 minutes or repetitive patterns).
- Update Terms/Rules page with AI policy and enforcement consequences; require re-acceptance on next login.
- Add admin dashboard for AI flags: view suspicious sessions, cluster by IP/device, and action (warn, temp ban, escalate).
- Logging: store hashed IP/device fingerprints and command timestamps for flagged sessions; auto-purge after retention window.
- Player reporting: add “AI/bot suspicion” report reason; feed into the same dashboard with correlation to flagged data.

## Acceptance Criteria
- Clear player-facing AI policy published and acknowledged.
- Rate limits and CAPTCHA trigger on high-frequency automation patterns without blocking normal play.
- Admins can see and act on AI suspicion signals; data retention respects privacy limits.
- Banned automation (bots, macro farming) detectable via pattern analysis with low false positives in load tests.
