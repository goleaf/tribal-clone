# Offline & Poor-Connection Support — TODOs

## Goals
- Keep core flows usable with intermittent connectivity (mobile, weak Wi-Fi).
- Prevent command loss and reduce user confusion on reconnect.

## Tasks
- **Local Draft Queue:** Cache pending actions (builds, recruits, commands, trades) with TTL and order; sync on reconnect with per-item status (sent/failed/conflict).
- **Conflict Handling:** If server state changed (resources spent, queue full), surface inline errors with retry suggestions (e.g., adjust quantities, choose new slot).
- **Offline Indicators:** Show clear offline badge and pause timers; disable destructive actions that cannot be validated; allow viewing cached reports/maps.
- **Lightweight Sync:** On reconnect, fetch deltas (resources, queues, commands) instead of full payload; throttle retries with exponential backoff.
- **Report Storage:** Cache last 50 reports and map bookmarks locally for offline reading; purge oldest after limit.
- **Telemetry:** Emit offline session counts, failed sync attempts, draft drop rate, and average time to reconcile.

## Design Decisions (to implement)
- Use an offline draft queue per action type with `id`, `type`, `payload`, `created_at`, `ttl`, and `status` (queued/sent/failed/conflict). Persist in localStorage; drop expired on next load.
- Batch-sync drafts on reconnect in timestamp order; mark each with server response and clear on success.
- Add an offline badge in the top bar and pause countdowns (display “paused – offline”). Re-enable when websocket/poll succeeds.
- Cache last 50 reports + bookmarks in IndexedDB for offline viewing; evict LRU beyond cap.
- Expose telemetry events: `offline_start`, `offline_end`, `draft_sync_success`, `draft_sync_failure`, `draft_conflict`.
- **Error Codes:** Standardize reconnect/sync errors (`ERR_OFFLINE`, `ERR_STALE_STATE`, `ERR_CONFLICT`, `ERR_REPLAYED`) with user-friendly guidance.
- **Security:** Do not allow offline queuing of premium spends or role changes; require fresh auth on reconnect before sending any queued commands.
- **Testing:** Simulate flaky network scenarios (packet loss, latency spikes) and assert draft preservation, correct error surfacing, and retry timing.
