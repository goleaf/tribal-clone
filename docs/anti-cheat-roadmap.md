# Anti-Cheat Roadmap

## Goals
- Detect and deter bots, pushers, multi-account abuse, and exploit use.
- Keep false positives low; make enforcement transparent and appealable.

## Tasks
- Detection:
  - Movement/command pattern analysis: flag impossible timings, 24/7 activity, synchronized alts, perfect intervals.
  - Economy anomalies: resource gains/spends outside expected ranges; repeat transfers between linked accounts; market outliers.
  - Client integrity: optional signature/hash checks; detect tampered assets/calls where feasible.
  - Exploit canaries: synthetic accounts performing known exploit vectors to validate detection.
- Enforcement & UX:
  - Graduated responses: warning → soft caps → temp bans → perma bans, depending on severity.
  - Clear reason codes in UI and emails; link to appeal form; show timers for temporary actions.
  - Auto-lock high-value actions (trades, tribe roles) on flagged accounts until review.
- Tooling:
  - Admin console for case review: timelines, commands, transfers, flags, IP/device clusters, notes.
  - Batch actions with audit trails; reversible where possible.
  - Metrics: flagged accounts/day, false-positive rate from appeals, time-to-resolution.
- Data & Privacy:
  - Hash IP/UA; retain cheat logs 180d cold storage; anonymize where possible.
  - Document detection criteria and thresholds internally; version them with changelog.
- Drills:
  - Monthly red-team simulations (bot waves, push schemes, exploit attempts); track detection and response time.
  - Review and adjust thresholds post-drill; publish internal report.

## Acceptance Criteria
- Detection rules cover command/economy anomalies and client tampering; exploit canaries in place.
- Enforcement path with reason codes, appeals, and admin tooling live.
- Metrics monitored; false positives tracked; thresholds tuned quarterly.
- Red-team drills executed monthly with documented outcomes.
