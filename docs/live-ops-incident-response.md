# Live Ops & Incident Response Playbook

## Goals
- Minimize downtime and player impact during incidents (crashes, queue stalls, DB issues).
- Standardize detection, mitigation, communication, and postmortems.

## Detection & Alerting
- Metrics: API error rate, queue lag (build/unit/research), attack tick duration, DB slow queries, cron success/failure, worker queue depth.
- SLOs: attack tick <60s, resource tick <60s, queue processors <120s, error rate <1% p95; alert on sustained breach for 5 minutes.
- Dashboards: status board showing current tick durations, queue depth, error rate, DB health, cache hit ratio.

## Runbooks
- **DB Lock/Deadlock:** capture running queries, kill offenders, failover if replica ready, temporarily pause conquest/attack creation, announce degraded state.
- **Queue Stall:** restart workers with jitter, drain poison messages, requeue stuck items, backfill missed ticks with idempotent rerun.
- **Attack Tick Overrun:** shed non-critical jobs, widen tick window temporarily, disable heavy endpoints (bulk trades) behind feature flag, notify players of delay.
- **Cache Outage:** fall back to DB reads, enable rate limits on heavy endpoints, warm critical caches after recovery.
- **DDoS/Traffic Spike:** enable WAF rules, tighten rate limits, CAPTCHA on auth/attack creation, switch to read-only mode for non-critical features if needed.

## Communication
- Status page: incident start, impact, mitigations in progress, next update ETA, resolution notice.
- In-game banner/message for major incidents; short, time-stamped updates.
- Post-incident summary mailed/posted to forums/Discord with root cause and follow-ups.

## Operations
- Feature flags: ability to pause conquest, pause new attacks, cap trades, disable premium store during instability.
- Backfills: scripts to reconcile missed ticks and refunds (resources, queue time, premium timers) with audit logs.
- Access: incident commander + on-call engineer + comms lead; rotation calendar with contact info.
- Data safety: snapshots before risky mitigations; test rollback steps documented.

## Postmortem
- Timeline, impact, contributing factors, fixes, and action items with owners/dates.
- Blameless; publish internally within 48 hours; player-facing summary for major outages.
- Track action item completion; prevent regressions with tests/alerts.
