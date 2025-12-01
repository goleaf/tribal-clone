# World Map & Navigation — Medieval Tribal War Browser MMO

## Map Structure (Layouts & Options)
- **Square Grid (Default):** Tiles with (x,y) coordinates; easy distance math; supports continent/sector grouping.
- **Hex Grid (Variant):** Better movement readability and adjacent connections; slightly higher complexity. Used in experimental worlds.
- **Hierarchies:**
  - **Tiles → Sectors → Provinces → Continents:** Standard for spawn and scoring; K-code style notation (e.g., Continent K55 = province 5,5).
  - **Rings/Frontiers:** Concentric rings around central relic/wonder; inner ring high-value, outer ring spawn-friendly.
  - **River/Valley Bands:** Natural lanes that speed or slow movement; creates strategic chokepoints.
- **Why Choose Which:**
  - Square + continents: familiar, fast to parse, good for classic/competitive worlds.
  - Hex: smoother movement, fresh feel for experimental seasons.
  - Rings: focus conflict toward center; good for timed seasonal ladders.
  - River/valley: emphasizes terrain-based planning; good for hardcore/strategic worlds.

## Coordinates & Distance
- **Coordinate System:** (x|y) integers; map origin bottom-left or center defined per world; continents/provinces chunked (e.g., 100x100 tiles per continent).
- **Distance Calculation:**
  - Square: Chebyshev or Euclidean-style adjusted for unit speeds; commonly `max(|dx|, |dy|)` for travel time; optional road/river multipliers.
  - Hex: axial/offset distance; travel time = steps × unit speed modifier.
- **Travel Time Concept:** Travel time = base_unit_speed × distance × world unit-speed modifier; slowest unit in a command sets pace.
- **Typical Examples:**
  - 10 tiles at 9 min/tile (LC on 1x world) ≈ 90 min.
  - 30 tiles at 18 min/tile (infantry) ≈ 9h.
  - Road lane with -20% time: same 30 tiles ≈ 7h12m.
- **Strategic Use:** Closer targets → faster fakes/support; long lanes → nobles exposed; players cluster war fronts to minimize transit; roads/rivers alter viable comps.

## Player Placement
- **Initial Spawn:** Fill by sectors/provinces starting near center or designated spawn ring; avoid placing new players next to high-power tribes; prefer clustering peers.
- **Safe Zones:** Starter sectors with limited aggression during protection; cannot spawn in relic/wonder core.
- **Balancing Over Time:** Later joiners placed in fresher outer sectors or in backfill slots of decayed villages; catch-up production buffs for late spawns on seasonal worlds.
- **Relocation Options:**
  - **Starter Relocation:** One-time move within spawn ring during protection; blocked if hostile commands inbound.
  - **Tribe Relocation Tokens:** Allow shifting closer to tribe cluster; cooldown and distance limits.
  - **Post-Wipe Respawn:** If wiped early, offer respawn in calmer sector with partial rebuild.

## Village & Map Entities
- **Player Villages:** Standard settlements, conquerable; show owner, tribe, points, wall silhouette.
- **Barbarian/Neutral Villages:** Farm targets; may grow over time; difficulty scales; some decay from abandoned players.
- **Special Objective Villages:**
  - Relic shrines/ancient capitals producing VP.
  - Beacon nodes for network/wonder unlocks.
  - Signal fires/watch posts revealing intel.
  - Resource-rich hamlets (woodland/clay/iron bias) with higher production but defensible.
- **Event Structures:** Raider camps, caravan routes, harvest caravans, siege trials camps, seasonal beasts.
- **Tribe Capitals/Cities (optional):** Cosmetic hubs; rally point for ops; potentially offer intel buffs.
- **Outposts/Encampments (if enabled):** Temporary staging points with limited recruitment/supply.

## Map UI & Controls
- **Zoom Levels:**
  - Far: continent/province view; aggregates villages into clusters; shows tribe colors/heatmaps.
  - Mid: sector view; individual villages visible; icons for type (player/barb/objective/event).
  - Near: tile view; shows command lines, wall silhouettes, markers.
- **Filters & Color-Coding:**
  - Diplomacy (ally/NAP/neutral/enemy), village type, activity (recently active/inactive), event hotspots, terrain/roads, intel freshness.
  - Icon sets for nobles, siege, scouts in commands; markers for beacons/relics.
- **Controls (Desktop):** Click to select tile → info card; drag to pan; scroll to zoom; hover for tooltips; right-click for context menu (attack/support/scout/bookmark/report/center).
- **Controls (Mobile):** Tap to select; long-press for radial quick actions; pinch to zoom; swipe to pan; bottom action bar for selected tile options.
- **Quick Actions:**
  - Send attack/support/scout from current village or choose village selector.
  - Open village screen; open last report; bookmark/marker drop; share to tribe chat.
- **Command Visualization:** Lines/arrows color-coded by type (red attack, green support, blue trade, purple scout). Arrival times on hover/tap; noble icon on noble-bearing commands (if detected).

## Information Layers (Overlays)
- **Diplomacy Overlay:** Colors by relation; toggle legend.
- **Activity Heatmap:** Shows recent activity (logins/battles/reports) aggregated; decays over time.
- **Tribe Territories:** Soft borders based on majority control; visualized as gradients; no gameplay effect unless world-specific.
- **Danger Zones:** Highlight areas with many incoming attacks or raid events; useful for evac/stack decisions.
- **Resource Richness:** Optional layer indicating resource-biased villages or POIs.
- **Event Hotspots:** Raider spawns, trade winds routes, relic migrations, siege trial camps.
- **Intel Freshness:** Marker opacity/halo indicates staleness tiers; tooltips show last-scouted time.
- **Terrain/Movement:** Road/river/forest/hill modifiers; show arrows for speed bonuses/penalties.

## World Expansion & Contraction
- **Expansion:** New sectors/continents unlock when population or player count hits thresholds; used on long worlds to add frontier space.
- **Contraction/Merge:** Abandoned sectors may merge/decay into barb fields; late-stage worlds can close outer rings to focus conflict. Finals/merge worlds can combine top tribes from multiple shards.
- **Spawn Closure:** Stop new spawns after midgame; shift focus to conflict; late joiners redirected to new worlds or designated late-spawn zones with boosts.
- **Relic/Objective Migration:** Move objectives inward over time to force convergence; rotate event POIs to fresh regions.

## Example Scenarios
- **Scanning for Targets (Desktop):** Player zooms to sector view, applies filter “Enemy + Barb + Fresh Intel <12h.” Sees cluster of enemy villages with stale intel; drops markers and assigns scout runs. Spots barb line along road for farming; sends LC presets via context menu, arrival times overlayed.
- **Coordinating Tribe Defense (Mobile):** Attack alarm; player opens map, filter “Incoming.” Red lines show 6 commands on Oakridge. Long-press Oakridge → radial “Support”; selects preset; timer shows arrival before first hit. Shares tile to tribe chat with “Stack here.”
- **Relic Push:** Endgame starts; relic shrines migrate to central ring. Ops leader toggles event hotspot overlay; sets tribe markers on three shrines. Uses command planner to sync noble trains; map shows arrival windows and needed fakes. Intel freshness overlay highlights one shrine stale; scouts dispatched.
- **World Expansion Event:** Population threshold reached; announcement: “Frontier opens east.” New continent appears; high-resource hamlets spawn. Tribe decides to send settlers/outposts; roads marked; defense lines planned via markers.
- **Cleanup/Merge:** Late in world, outer ring set to decay; barb take-over begins. Players relocate inward; tribe territory overlay shrinks, focusing war front around relic beacons.

## Implementation TODOs
- [ ] Map data model: tiles/sectors/provinces with terrain/road/river modifiers; objectives (relics/beacons/POIs) and event hotspots as separate layers.
- [ ] Distance API: support square/hex distance calculations with world unit-speed modifier and terrain multipliers; expose travel time endpoints for presets.
- [ ] Overlays: implement diplomacy/activity/intel freshness/terrain overlays with server-provided data; fade/decay logic for intel and activity heatmaps.
- [ ] Command visualization: API for incoming/outgoing/support/trade/scout lines with arrival times; noble icon flag; filters per command type/state.
- [ ] Filters & search: server-side filtering for diplomacy state, village type, activity window, intel freshness; coordinate search and bookmark lookup.
- [ ] Spawn/relocation: services for initial placement, late-joiner boosts, one-time relocation during protection, tribe relocation tokens with cooldowns and distance bounds.
- [ ] Expansion/contraction: trigger unlocking/closing of sectors based on thresholds; migrate objectives inward; decay abandoned rings into barb fields.
- [ ] Mobile controls: radial/long-press quick actions and bottom action bar; ensure parity with desktop context menu actions.
- [x] Caching/perf: tile caching/CDN for static map layers; paginate commands/markers; skeleton states for zoom levels to avoid jank. _(map_data.php now ships ETag/Last-Modified + cache-control; front-end already paginates markers per viewport)_
