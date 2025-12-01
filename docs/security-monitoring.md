# Security Monitoring & Alerting — TODOs

## Goals
- Detect and respond to auth abuse, resource exploits, and infrastructure issues quickly.
- Keep signal-to-noise high with actionable, owner-tagged alerts.

## Tasks
- Metrics/alerts:
  - Auth: login failure rate, password reset rate, suspicious IP/device pivots, CAPTCHA fail rate.
  - Economy: spikes in resource transfers, trade anomalies (rate > allowed), duplicate queue creation, conquest attempts per minute.
  - Combat/commands: attack creation/error rate, fake spam detection (sub-50-pop), per-target incoming cap hits.
  - Infrastructure: DB deadlocks/slow queries, queue lag, cache miss spikes, worker crash loops.
- Dashboards: auth, economy/trade, combat/command, infra; p95/p99 latency and error rate with SLO bars.
- Alert routing: tag each alert with owner/team; severity mapping (page vs notify).
- Correlation IDs: ensure all commands/transactions carry trace IDs in logs and metrics.
- Anomaly detection: simple baselines on trades/attacks per account; alert on 3σ deviations with cooldowns.
- Log retention: 30d hot, 180d cold for security/audit logs; PII minimized (hashed IP/UA).
- Drill: monthly incident drill using synthetic spikes (auth brute force, trade exploit, command flood) to validate alerts and runbooks.

## Acceptance Criteria
- Dashboards live for auth, economy, combat, and infra with SLO markers.
- Alerts page on sustained breaches (error rate, queue lag, auth abuse) with clear runbook links.
- Trace IDs present in logs for attack/trade/auth flows.
- Monthly drills executed and logged; gaps tracked to closure.
