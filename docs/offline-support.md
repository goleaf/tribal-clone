# Offline & Poor-Connection Support — TODOs

## Goals
- Keep core flows usable with intermittent connectivity (mobile, weak Wi-Fi).
- Prevent command loss and reduce user confusion on reconnect.

## Tasks
- ✅ **Local Draft Queue:** Cache pending actions (builds, recruits, commands, trades) with TTL and order; sync on reconnect with per-item status (sent/failed/conflict).
- **Conflict Handling:** If server state changed (resources spent, queue full), surface inline errors with retry suggestions (e.g., adjust quantities, choose new slot).
- **Offline Indicators:** Show clear offline badge and pause timers; disable destructive actions that cannot be validated; allow viewing cached reports/maps.
- **Lightweight Sync:** On reconnect, fetch deltas (resources, queues, commands) instead of full payload; throttle retries with exponential backoff.
- **Report Storage:** Cache last 50 reports and map bookmarks locally for offline reading; purge oldest after limit.
- ✅ **Telemetry:** Emit offline session counts, failed sync attempts, draft drop rate, and average time to reconcile.

## Design Decisions (to implement)
- ✅ Use an offline draft queue per action type with `id`, `type`, `payload`, `created_at`, `ttl`, and `status` (queued/sent/failed/conflict). Persist in localStorage; drop expired on next load.
- Batch-sync drafts on reconnect in timestamp order; mark each with server response and clear on success.
- Add an offline badge in the top bar and pause countdowns (display “paused – offline”). Re-enable when websocket/poll succeeds.
- Cache last 50 reports + bookmarks in IndexedDB for offline viewing; evict LRU beyond cap.
- Expose telemetry events: `offline_start`, `offline_end`, `draft_sync_success`, `draft_sync_failure`, `draft_conflict`.
- ✅ **Error Codes:** Standardize reconnect/sync errors (`ERR_OFFLINE`, `ERR_STALE_STATE`, `ERR_CONFLICT`, `ERR_REPLAYED`) with user-friendly guidance.
- **Security:** Do not allow offline queuing of premium spends or role changes; require fresh auth on reconnect before sending any queued commands.
- **Testing:** Simulate flaky network scenarios (packet loss, latency spikes) and assert draft preservation, correct error surfacing, and retry timing.

## Acceptance Criteria
- Offline badge appears within 2s of connection loss; timers show paused state and resume correctly on reconnect.
- Drafted commands/builds queued offline survive tab reload; on reconnect they either send or show per-item error with guidance.
- Sync uses deltas (not full payload) and respects exponential backoff; no more than N retries in 5 minutes (configurable).
- Cached reports/bookmarks accessible offline (50 entries); eviction policy keeps most recent.
- Disallowed actions (premium spends, role changes) are blocked offline with clear error code; queued items never execute without fresh auth.

## Edge Cases & UX Notes
- Show “blocked offline” tooltip on premium/role-change actions instead of hiding them; include link to reconnect.
- If reconnect happens mid-send and server returns conflict, keep draft with surfaced conflict reason and allow one-click adjust/resend.
- Handle time skew: if client clock drifts, rely on server timestamps for TTL/ordering; show warning if skew > 60s.
- Graceful degradation on outdated cache schema: clear and rebuild local cache with notification to user.
- Avoid duplicate sends: use client-generated idempotency key per draft so reconnect bursts cannot double-send commands.
