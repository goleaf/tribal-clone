# World Map Performance & UX Fixes — TODOs

## Goals
- Keep map interactions smooth on desktop/mobile under heavy command load.
- Reduce server load from frequent map polling.
- Preserve clarity and accessibility with high-density overlays.

## Tasks
- **Data freshness:** Add ETag/Last-Modified to map/command endpoints; clients poll with conditional requests and respect `max-age`.
- **Batching:** Collapse incoming command updates into 1s batches per village; send deltas instead of full lists. Batch marker updates similarly.
- **Pagination:** Paginate command lists (incoming/outgoing/support/trade/scout) for selected areas; lazy-load on scroll.
- **Rate Limits:** Throttle marker drops and command-visualization fetches per user; return `ERR_RATE_LIMITED` with retry-after.
- **Skeletons:** Implement skeleton states for zoom levels while tiles/commands load; avoid jarring redraws on pan/zoom.
- **Accessibility:** Provide high-contrast palette for diplomacy/overlays, keyboard navigation for selection/filter toggles, and reduced-motion mode for command lines.
- **Metrics:** Track map request rate, cache hit %, average payload size, and client render time; alert on spikes in payload or render latency.
- **Testing:** Simulate 500+ concurrent commands on a sector; assert p95 render < 200ms on mid-tier mobile and server responses < 200ms with caching enabled.
