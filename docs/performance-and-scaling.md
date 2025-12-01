# Performance & Scaling

## Overview
Ensure core ticks (resources, queues, attacks), map queries, and trading endpoints stay fast as player counts grow. Focus on database indexes, batch updates, and predictable cron durations.

## Tasks
- Resource tick batching: group village updates per world in deterministic batches (e.g., 5–10k villages) and measure completion time; target <60s for 100k villages.
- Queue processing: add covering indexes for `building_queue`/`unit_queue`/`research_queue` on `(village_id, finish_time)` and `(world_id, finish_time)` to keep cron scans O(log n); verify via EXPLAIN.
- Combat/attack polls: ensure `attacks` table has composite indexes on `(target_village_id, arrival_time)` and `(origin_village_id, arrival_time)`; cap poll window to next 15 minutes.
- Map queries: add sector-based queries (100×100 tiles) with an index on `(world_id, sector_x, sector_y)` and only fetch visible fields; target <200ms per map fetch for 500 villages/sector.
- Config caching: cache building/unit/research configs in APCu (or in-memory singleton) and avoid per-request disk reads; add cache-busting hook on deploy.
- Logging/retention: rotate `logs/` daily, cap to 14 days, and disable SQL debug logs in production; ensure failures surface via error monitoring instead.
- Monitoring: instrument cron durations and DB query latency percentiles; alert if ticks exceed SLO (e.g., 60s for resources, 120s for queues) or if deadlocks occur.

## Acceptance Criteria
- Resource tick and queue cron complete within SLO at 100k villages without deadlocks.
- Map and attack polling endpoints stay under 200ms p95 with realistic load.
- No missing indexes on hot paths (validated via EXPLAIN on production-like data).
- Logs remain under retention and do not impact disk space or I/O.
