# Live Ops Incident Response — TODOs

## Goals
- Contain and resolve live incidents fast (rollback, hotfix, comms).
- Reduce repeat incidents via blameless postmortems and action items.

## Tasks
- **Runbooks:** Create runbooks for top risks (payment failure, tick backlog, login outage, data corruption, exploit). Include detection signals, immediate mitigations, comms template, and rollback steps.
- **Feature Flags:** Ensure critical systems (combat, payments, events, diplomacy) have kill switches and scoped flags; document owners and expected blast radius.
- **On-Call & Paging:** Define primary/secondary rotations for engineering, ops, and community; paging policies (SLA by severity), and coverage for seasonal launches.
- **Dashboards & Alerts:** Minimal SLOs for uptime, error rate, tick latency, payment success, auth latency. Alerts with clear runbook links and ownership.
- **Rollback Mechanisms:** Targeted rollback tooling for player/account/village changes; snapshot cadence and retention; dry-run mode before applying.
- **Comms Plan:** Templates for in-game banner, Discord, forum, and email updates by severity; single source of truth link.
- **Exploit Handling:** Triage flow for abuse reports; isolate offending actions via flags; lock offending accounts; prepare remediation (resource clawback, ban) with audit trail.
- **Postmortems:** Blameless templates with timeline, impact, contributing factors, fixes, and owners. Track action items to completion with due dates.
- **Game Integrity Checks:** Automated canaries on combat resolution, economy sinks/sources, and map updates each deploy; alert on drift from expected ranges.

## Drills & Tooling
- **Chaos/Fire Drills:** Monthly simulated incidents (auth outage, tick backlog, bad flag) with time-to-detect/time-to-mitigate metrics; capture learnings.
- **Shadow Deploy & Canary:** Deploy to canary world with automatic rollback on SLO breach; health gate before full rollout.
- **One-Click Mitigations:** Scripts for disabling events, pausing payments, or freezing attacks; ensure they log actors/reason and are permission-guarded.
- **State Diffing:** Tool to diff player/village state pre/post deploy; fast scope assessment for rollbacks.
- **Incident Timeline Bot:** Chat bot to collect timeline entries, decisions, and links; exports to postmortem template.

## Acceptance Criteria
- On-call schedule and paging runbooks published; alerts link to owners and playbooks.
- Feature flags exist for core systems with documented owners and rollback expectations.
- Runbooks cover top 5 incident types with tested rollback/mitigation steps.
- Comms templates ready; incident page/link used consistently.
- Postmortem process in place with action item tracking.
