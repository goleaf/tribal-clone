# QA & Release Checklist — Tribal War Browser MMO

## Goals
- Prevent regressions on core loops (build, recruit, combat, trade, map).
- Ensure deploys are safe with rollbacks and monitoring in place.

## Pre-Release
- Verify migrations apply cleanly on staging; rollback script prepared.
- Run automated tests (unit/integration/e2e) and lint; review failures.
- Smoke tests: login, village load, resource tick, build queue add/cancel, recruit queue add/cancel, attack send, scout send, trade send, reports load.
- Load test key APIs (build queue, attack creation, map fetch) with staging data; check p95 latency within SLO.
- Security checks: dependency audit, CSRF/session flags verified, rate limits in place on auth/attacks/trades.
- Feature flags set to safe defaults; kill switches documented for combat, attacks, trades, payments.

## Release Steps
- Tag release version; capture changelog and migration summary.
- Deploy to staging; run smoke suite; confirm metrics stable (error rate, queue lag, DB CPU).
- Deploy to production with canary/rolling; watch dashboards for 30–60 minutes.
- Announce release window/status to community if player-facing changes are significant.

## Post-Release Validation
- Spot-check: new builds/recruits, attack resolution, reports, tribe chat, market offers, achievements.
- Verify background jobs running: queue processors, attack tick, resource tick, notification workers.
- Check logs for errors, increased rate limits, or deadlocks; address anomalies quickly.
- Confirm backups/snapshots taken and retention healthy.

## Rollback Plan
- Preconditions: known good tag, reversible migrations or down scripts, data snapshots.
- Steps: pause new attack creation/trades if needed, deploy previous tag, run down migrations (if safe), clear caches, resume features.
- Post-rollback: notify players, run integrity checks (queues/resources), and schedule postmortem.
