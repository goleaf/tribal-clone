# Map Performance, Accessibility, and Low-Perf Modes — Release Notes & Help Copy

## Player-Facing Summary
- Map now reuses cached data (ETag/Last-Modified) to avoid refetching unchanged tiles; most pans return instantly when nothing changed.
- Rapid pans/zooms are debounced and rate-limited; if you hammer the map you may see "Updates slowed, please wait" for a moment.
- New skeleton loader shows grid placeholders while tiles/commands refresh to reduce flash during zoom changes.
- Low-performance mode toggle hides movement overlays and trims payloads for slower devices/connections; you can toggle it from the map filters.
- High-contrast and reduced-motion toggles live in the map toolbar; they persist per device and affect overlays/command lines.
- Movement density is capped on far zooms; zoom closer or apply filters to see every command.

## Help / FAQ Snippets
- **Why did my map stop updating?** You hit the request limit. Wait for the retry timer shown in the banner, then pan/zoom again.
- **Map feels slow on my phone.** Turn on "Low performance mode" in the map filters to hide movements, reduce payload size, and lighten rendering.
- **Overlays are hard to see.** Enable "High contrast" in the toolbar for stronger diplomacy colors and labels.
- **Animations make me dizzy.** Enable "Reduced motion" to remove shimmer/line animations; skeleton loader respects this setting.
- **I see placeholders instead of tiles.** That's the skeleton loader while fresh data arrives; it should resolve quickly once the fetch completes.

## Tribe/Community Messaging (Changelog)
- Added conditional map fetches (ETag/Last-Modified) to cut bandwidth and speed up pans.
- Added debounced fetches + rate-limit protection; players now see a banner when hammering map refresh.
- Introduced zoom-aware skeleton loader to smooth redraws during pan/zoom.
- New low-performance toggle trims movement payloads and omits heavy overlays on demand.
- Accessibility: high-contrast palette and reduced-motion mode now apply to map overlays/command lines and persist per device.
- Movement rendering now thins at far zoom to protect performance; zoom in for full detail.

## Admin/Support Notes
- Low-perf mode also available server-side via `?lowperf=1` for debugging/support.
- Client telemetry logs render time and dropped frames (sampled); alerts fire on spikes in render time or dropped frames.
- Server map metrics log payload size, cache hits, and duration; alerts on slow/large responses land in `logs/map_metric_alerts.log`.
