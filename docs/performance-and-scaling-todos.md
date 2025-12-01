# Performance & Scaling TODOs

Shortlist of concrete performance tasks to harden the game backend before live load.

## Priority 1: Battle & Movement Ticks
- [ ] Benchmark tick executor under 5k/10k/25k concurrent movements; record p95/p99 latency.
- [ ] Add guardrails to prevent tick overrun: per-tick deadline, spillover queue, metric on deferred jobs.
- [ ] Introduce lightweight read cache for unit stats and research modifiers to avoid repeated DB hits in combat loops.
- [ ] Profile battle resolution hotspots (damage loop, wall reduction, loyalty change) and cache shared calculations per battle.
- [ ] Implement load-shedding: if tick overruns deadline, defer low-priority jobs (notifications, non-blocking logs) and surface a "deferred work" metric/alert.
- [ ] Add backpressure to movement creation when tick queue depth exceeds threshold; return friendly error suggesting retry window.

## Priority 2: Database Health
- [ ] Add missing indexes for high-churn tables (movements, battle_logs, notifications, trades) on `world_id`, `village_id`, `arrival_at`.
- [ ] Write archiving job to move resolved battles/reports older than 30 days into cold storage.
- [ ] Stress-test transaction contention on conquest flows (loyalty updates + village ownership transfer).
- [ ] Add DB-level rate limits/locks for conquest race conditions (claiming same village) and double-spend of resources on simultaneous queues.

## Priority 3: Caching & Delivery
- [ ] Introduce CDN caching for static assets; verify cache-busting strategy on deployments.
- [x] Add ETag/Last-Modified headers to map/tile APIs; measure hit rate and bandwidth savings. _(map_data.php now emits strong validators + short revalidate cache-control)_
- [ ] Cache tribe/village public profile payloads with short TTL; bust on ownership or name changes.

## Priority 4: Rate Limiting & Abuse
- [ ] Implement per-IP and per-account rate limits for attack creation, support sending, and market offers.
- [ ] Add exponential backoff for failed login attempts; emit security events to admin dashboard.
- [ ] Throttle scouting spam: cap simultaneous scout missions per attacker/target pair per hour.
- [ ] Cap per-target incoming commands to prevent command floods; expose limit errors in UI and metrics.
- [ ] Add fake-attack throttling (rate-limit sub-50-pop sends and zero-siege fakes) and log offenders.

## Observability
- [ ] Emit metrics: tick duration, battles resolved per tick, queue depth, DB slow queries, cache hit ratio.
- [ ] Add structured logs with correlation ids for each battle/conquest action; ship to log aggregator.
- [ ] Create Grafana dashboards for tick latency, DB ops/sec, error rates, and conquest throughput.

## Load Test Plan
- [ ] Scripted scenario: 1k players sending staggered attacks, 500 scouting waves, 200 simultaneous conquests.
- [ ] Measure resource usage (CPU, RAM, DB connections) and identify saturation points; document break-even numbers.
- [ ] Capture optimization backlog from findings and feed into this TODO list.
