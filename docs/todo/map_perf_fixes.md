# World Map Performance & UX Fixes — TODOs

## Goals
- Keep map interactions smooth on desktop/mobile under heavy command load.
- Reduce server load from frequent map polling.
- Preserve clarity and accessibility with high-density overlays.

## Tasks
- [x] **Data freshness:** Add ETag/Last-Modified to map/command endpoints; clients poll with conditional requests and respect `max-age`. _(map_data.php now emits ETag/Last-Modified + short revalidate cache-control)_
- [x] **Rate Limits:** Throttle marker drops and command-visualization fetches per user; return `ERR_RATE_LIMITED` with retry-after. _(per-user map fetch limiter added)_
- [x] **Accessibility:** Provide high-contrast palette for diplomacy/overlays, keyboard navigation for selection/filter toggles, and reduced-motion mode for command lines. _(high-contrast + reduced-motion toggles live on map toolbar; arrow keys now pan the map)_
- [x] **Metrics:** Track map request rate, cache hit %, average payload size, and client render time; alert on spikes in payload or render latency. _(server logs map_metrics; client render time now posted via ajax/telemetry/map_perf.php sampling)_
- **Batching:** Collapse incoming command updates into 1s batches per village; send deltas instead of full lists. Batch marker updates similarly.
- **Pagination:** Paginate command lists (incoming/outgoing/support/trade/scout) for selected areas; lazy-load on scroll.
- [x] **Skeletons:** Implement skeleton states for zoom levels while tiles/commands load; avoid jarring redraws on pan/zoom. _(skeleton grid scales to requested map size and stays visible during fetch/render)_
- **Testing:** Simulate 500+ concurrent commands on a sector; assert p95 render < 200ms on mid-tier mobile and server responses < 200ms with caching enabled.

## Additional Fixes
- **Command line thinning:** Simplify geometry for distant zoom (straight segments, no arrowheads) to reduce draw calls; use instanced rendering where possible.
- **LOD for markers:** Reduce marker detail/icons at far zoom; cluster markers and commands to avoid thousands of DOM/SVG nodes.
- [x] **Debounce pan/zoom:** Debounce fetches during rapid pan/zoom; fetch only on idle state with a small delay; cancel in-flight requests. _(debounce + abortable fetch in map/map.php)_
- **Delta compression:** Compress command deltas (binary/MessagePack) to shrink payloads for high-volume worlds.
- **Server-side culling:** Curb returned commands/markers outside viewport + padding; enforce max payload size with continuation tokens.
- **Client perf logging:** Log render duration and dropped frames on map interactions (sampled); ship to telemetry for regression tracking.
- **Fallback mode:** If device perf is low (dropped frames threshold), auto-switch to simplified visuals: hide minor overlays, reduce command line density/update frequency; allow user opt-back.
- **Offline/poor-connection mode:** Cache last tiles/markers for current viewport; queue marker drops locally and sync on reconnect with conflict resolution; show stale indicator on data.

## Acceptance Criteria
- Map endpoints return 304 with ETag/Last-Modified when unchanged; payload size tracked and stable under load.
- Paginated/clustered commands and markers keep p95 client render under 200ms on target devices in a 500+ command scenario.
- Rate limits return `ERR_RATE_LIMITED` with retry-after; fetch debouncing prevents duplicate loads on rapid pan/zoom.
- Accessibility modes (high contrast/reduced motion) apply to diplomacy/overlays and command lines without breaking performance.
- Telemetry shows cache hit %, request rate, payload size, render time; alerts fire on payload spikes or render regressions.
- Fallback/low-perf mode engages when dropped-frame threshold hit and can be toggled off; user sees simplified overlays/lines.
- Offline/poor-connection mode caches last viewport, queues marker drops, syncs on reconnect with conflicts resolved, and marks stale data clearly.

## Open Questions
- Should far-zoom clustering be done server-side (pre-aggregated) or client-side? Decide per world size/perf budget.
- How aggressive can delta compression be without harming low-end devices? Need benchmarks for JSON vs binary.
- What is the acceptable retry-after window for rate limiting map fetches without hurting UX (e.g., 500ms vs 2s)?
- **Cold-start cache:** Pre-warm tiles/overlays for current continent/sector after login; store in-memory/LRU to reduce first-pan jank, with capped memory budget.
- Should offline mode allow basic actions (bookmark drops) while offline, and how to resolve conflicts when coming back online?

## Progress
- Added an AJAX travel-time endpoint (`ajax/map/travel_time.php`) so map/Rally interactions can fetch distance/ETA server-side using world speed modifiers (reduces client-side recompute and keeps timings consistent).
- Added low-perf mode on `map_data.php` (`?lowperf=1`) to skip movement payloads and flag response, reducing payload/processing for constrained clients.
- Map UI surfaces low-perf state with a movement-warning banner when movements are hidden.

## QA & Tests
- Simulate heavy load: 500+ command lines + 200 markers in viewport; verify batching/pagination keep p95 render <200ms and payload under max size.
- Offline/poor-connection: toggle offline, cache last viewport, queue marker drops, reconnect and confirm conflict resolution and stale indicators.
- Fallback mode: force low-perf device profile; ensure minor overlays/lines hide, update rate drops, and user toggle works.
- Rate limits: hammer map fetch/marker-drop endpoints to confirm `ERR_RATE_LIMITED` with retry-after and no server degradation.

## Profiling Plan
- Front-end: capture performance profiles on low/mid/high devices with 500–1000 commands + overlays; record main-thread time, memory, and dropped frames; compare with/without clustering and fallback mode.
- Backend: benchmark map endpoints under concurrent load with conditional requests vs full; log p50/p95 latency and payload sizes; verify ETag/cache hit ratios.
- Payloads: test JSON vs binary delta payloads for size and CPU cost; choose defaults per world type; document thresholds to auto-switch.

## Rollout Checklist
- [ ] Feature flags per world for batching/pagination/clustering/fallback mode; enable gradually by archetype.
- [ ] Schema/config changes (if any) for map settings validated with rollback; ensure new settings are read with sane defaults when absent.
- [ ] Backward compatibility: maintain legacy map endpoints/fields while new deltas/clustering roll out; version responses to avoid client breaks.
- [x] Release comms/help: explain new map performance modes (conditional requests, clustering, fallback) and how to toggle high-contrast/reduced-motion. _(see docs/map_performance_comms.md for release copy + FAQ)_
- [x] Monitoring: client perf logging enabled (render time/dropped frames) with alerts on regressions; server map_metrics dashboards up (latency/cache hit/payload). _(map_data.php now writes alert log on slow/large responses; client perf telemetry triggers alert log on high render time/dropped frames)_
- [ ] Low-perf/offline toggles surfaced in UI with state retention per device/session; QA covers toggle on/off and re-entry.

## Monitoring Plan
- Track map endpoint latency (p50/p95/p99), cache hit %, payload size, and rate-limit hits; alert on regressions after rollout.
- Monitor client-side perf telemetry (render time, dropped frames, fallback engagements) sampled across devices; alert on spikes.
- Watch clustering/pagination adoption and error rates; alert if clustering toggles fail or payload caps hit unexpectedly.
- Detect stale ETag/conditional responses (miss rate); alert if caching breaks or 304 rate drops sharply.
- Canary worlds: tighter thresholds on latency/payload and fallback triggers to catch issues early.
