# UI/UX Specification — Main Screens (Village, World Map, Reports)

## General UI Principles
- **Clarity first:** Surface current state (resources, timers, diplomacy) without clutter; progressive disclosure for advanced details.
- **Low misclick risk:** Large hit areas for primary buttons, confirmation for destructive actions (attack send, demolish), distinct colors for attack vs support vs trade.
- **Responsiveness:** Adaptive grid; desktop uses sidebars and hover tooltips; mobile uses collapsible drawers, bottom nav, and long-press menus.
- **Consistency:** Shared iconography and color coding across screens (red = hostile, green = friendly/support, blue = trade/info, gold = premium/cosmetic-only cues).
- **Accessibility:** High-contrast theme toggle, scalable fonts, keyboard navigation, reduced-motion option.
- **Performance:** Lazy-load lists (reports, commands), map tile caching, skeleton loaders.

## Village Screen
### Layout Zones (Default Desktop)
- **Top Bar:** Resources (Wood/Clay/Iron/Food with per-hour delta), premium currency, population cap, notifications (attacks/trade/build done), global search, profile menu.
- **Left Side Menu:** Quick nav tabs (Overview, Buildings, Troops, Recruit, Research, Market, Tribe, Reports), collapsible to icons.
- **Central Area:** Primary context panel (buildings grid or list, recruit queues, research tree, rally point actions); dynamic based on tab.
- **Right Context Panel:** Timers and queues (build, recruit, research), incoming/outgoing commands summary, alerts (incoming attacks), tribe ops widget.
- **Bottom Bar:** Action shortcuts (Build, Recruit, Send Troops, Trade, Set Preset), chat dock (tribe/global), breadcrumbs for village switching.

### Mobile Variant
- **Top Bar (compact):** Resource row with swipe to view extended stats; attack alert icon pinned.
- **Bottom Nav:** Home (Overview), Map, Recruit, Reports, More (drawer for tribe/market/settings).
- **Slide-in Drawers:** Build/recruit queues; incoming commands; tribe chat.
- **Long-Press Menus:** On building cards for upgrade/instant queue; on rally point for presets.

### Information Shown
- **Resource Strip:** Current amounts, storage cap, production rates, vault-protected amounts, population used/free.
- **Queues:** Build queue with ETA and progress bars; recruit queue by building; research queue; indicate blockers (resources, pop) with inline tooltips.
- **Troop Overview:** Garrison counts by type, troops outside, supports stationed, wounded (if hospital), population cost.
- **Alerts:** Incoming attacks/supports with stacked countdowns, empty queue reminders, storage near cap, protection ending.
- **Village Metadata:** Name, coordinates, continent/shire, loyalty/allegiance value, wall level snapshot.

### Primary Actions
- **Build/Upgrade:** Tap building card → detail with costs/requirements → confirm; queue if available. Quick-upgrade buttons for common fields.
- **Recruit:** Barracks/Stable/Workshop tabs with slider inputs; presets for common batches; queue management (reorder/cancel with confirmations).
- **Send Troops:** Rally Point → select preset or manual composition → target input (coords/bookmark) → choose action (Attack/Support/Scout/Fake) → send with confirmation showing travel time, arrival time, and slowest unit.
- **Trade:** Market → send resources (with tax preview), create offers, accept offers; shortcuts to balance resources across owned villages.
- **Research (if applicable):** Tech tree with unlocks and effects; queue with ETA.
- **Presets & Bookmarks:** Create/assign per village; accessible from rally point and map.

### Layout Variants
- **Grid View (Classic):** Building icons on a map-like layout; hover for level/cost; click to upgrade. Suits desktop nostalgia.
- **List View (Efficient):** Sorted by category (Economy/Defense/Military/Utility); shows levels, costs, and upgrade buttons inline; best for mobile/fast play.
- **Compact Ops Mode:** Collapsible side menu and condensed resource bar; prioritizes commands and timers during wars.

## World Map Screen
### Layout
- **Top Bar:** Current village selector, resource mini-strip, diplomacy legend toggle, search coords input, filter/overlay toggles.
- **Main Map Canvas:** Scroll/drag to pan; mouse wheel/pinch to zoom; tiles show villages/POIs with diplomacy colors and icons (attack incoming, support stationed, barb/POI markers).
- **Right Panel:** Command list (incoming/outgoing/support/trade), bookmarks, tribe markers, ops plans; collapsible.
- **Bottom Bar:** Quick actions (Send from selected village, Bookmark, Share to tribe, Center on home, Cycle villages).

### Interaction Patterns
- **Click/Tap:** Select tile → opens tooltip card with village name, owner/tribe, loyalty, wall level (if known), recent intel timestamp, distance/travel time from current village, quick actions (attack/support/scout/trade/bookmark).
- **Drag:** Pan map; inertia on mobile.
- **Scroll/Pinch:** Zoom levels snap to show more/less detail; cluster markers when zoomed out.
- **Hover (desktop):** Preview tooltips for diplomacy/owner; show incoming/outgoing lines when hovering bookmarks.
- **Long-Press (mobile):** Open quick action radial (Attack/Support/Scout/Bookmark) from tile.

### Map Overlays & Tools
- **Diplomacy Overlay:** Color by relation (ally, NAP, neutral, enemy, barbarian, POI). Legend toggle.
- **Intel Heatmap:** Shows recency/quality of scout intel; fades with time.
- **Command Traces:** Lines/arrows for active commands; filter by type (attack red, support green, trade blue, scout purple).
- **Terrain/Weather:** Optional layer affecting speed/vision; display modifiers on hover.
- **Spawn/Protection Zones:** Highlight starter regions and no-attack areas; useful for NPE clarity.
- **Tribe Markers:** Shared markers with labels, expiry timers, and permissions; can be grouped by operation.

### Use Cases
- **Offensive Planning:** Select target → view travel time for presets → queue multiple attacks/fakes; visualize synchronized arrival windows.
- **Defensive Response:** Filter to incoming attacks; center on threatened village; quick-send supports using presets; show stack count if shared by tribe.
- **Farming:** Multi-select barb/POI tiles (desktop) or rapid tap mode (mobile) to send farm presets; batching to reduce clicks.
- **Scouting Sweep:** Drag-select area (desktop) to queue scouts to multiple targets; show expected report return times.
- **Tribe Ops:** Display shared markers and operation timelines; allow one-tap join to assigned waves.

## Reports Screen
### Categories
- **Battle, Scouting, Trade, Tribe, System, Event** (color-coded chips).

### Layout
- **Top Controls:** Filters, search bar, tag selector, bulk action buttons (archive/delete/share/star).
- **Left Sidebar (desktop):** Folders (All, Unread, Starred, Battles, Scouts, Trades, Tribe, System, Event, Shared with tribe). On mobile, collapsible drawer.
- **Main List:** Each row shows type icon, title, opponent/ally, village coords, timestamp, outcome (Win/Loss/Neutral), badges (nobles, siege, night/weather), unread indicator.
- **Detail Panel:** Opens inline (desktop split view) or as full-page (mobile) with tabs (Summary, Troops, Buildings, Loot/Costs, Timeline, Attachments).

### Filters & Tagging
- By type, date range, village, opponent tribe, outcome, contains nobles/siege, night/weather modifier, shared/unshared, unread/archived.
- Custom tags (e.g., “OP Alpha,” “Scout East,” “Defense Loss”) with color labels.
- Saved filter sets for quick reuse.

### Bulk Actions
- Select multiple → archive/delete/star/share to tribe → apply tag → mark read/unread.
- Auto-archive rules (e.g., farm reports older than 7 days) configurable; starred reports exempt.

### Importance Highlighting
- **Auto-star:** Incoming attack reports with nobles, defense losses with wall drop, relic-related events.
- **Pinned Section:** Pinned/starred reports float to top.
- **Severity Badges:** Red for major loss, gold for significant win, purple for relic/event-related.

## Navigation & Shortcuts
- **Hotkeys (desktop):** `V` village overview, `M` map, `R` reports, `B` build, `T` train, `A` attack/send troops, `S` scout, `F` farm mode toggle, arrow keys to cycle villages, `Ctrl/Cmd+F` focus search.
- **Quick Bars:** Bottom action bar with customizable slots (presets, favorite targets, common buildings). On mobile, long-press to edit.
- **Breadcrumbs:** Village name → continent/shire → world; clickable for quick navigation and context.
- **Multi-Village Play:** Dropdown and arrow cycling; “next with empty queue” and “next under attack” shortcuts.
- **Context Menus:** Right-click/long-press for actions on villages, reports, and timers.

## Feedback & States
- **Loading:** Skeleton placeholders for lists; spinner on map tile fetch; “Retry” button on failures.
- **Success:** Subtle toast/inline checkmark for queued builds/commands; shows ETA.
- **Failure:** Inline errors with reasons (insufficient resources/pop, requirement missing, protection active, diplomacy forbids action); disabled buttons with tooltip explanation.
- **Confirmations:** Modal or slide-up for attacks/supports/trades showing summary and arrival time; option to skip confirmation per session (not persisted for attacks).
- **Timers:** Progress bars + countdowns; change color under 60s; show absolute arrival/build finish time on hover/tap; paused state labeled with reason.
- **State Persistence:** Filters, sorting, and layout preferences saved per device; warn on unsent form data when navigating away.

## Example Flows
- **Village — Build and Recruit:** Player opens village (`V`), sees build queue empty; clicks Lumber Camp card → detail → Upgrade; queue shows T-00:12. Opens Recruit tab, uses preset to train 50 Spears; recruit queue lists ETA. Alert shows storage near cap; player sends trade to alt village via Market.
- **World Map — Defend Against Wave:** Attack badge flashes; player hits `M`, map centers on threatened village with red lines. Right panel lists 8 incoming. Player selects village, taps Support preset to two nearby allies; uses quick action on enemy origin to send scouts. Pins global countdown for earliest hit.
- **Reports — Review and Share:** After defense, player presses `R`, filters by Battles, sees latest auto-starred defense report. Opens detail, checks wall drop and surviving troops, clicks Share to tribe with redacted resources. Tags report “OP Beta” and pins it. Batch archives old farm reports with one click.
- **Multi-Village Farming (desktop):** Player toggles Farm Mode (`F`), selects row of barb villages on map via drag-select; sends Farm Preset A to each; timer list shows staggered returns. Notifications are badge-only to avoid spam.
- **Mobile Quick Attack:** On phone, user long-presses target tile → radial shows Attack/Support/Scout/Bookmark; chooses Attack, selects preset, sees send confirmation with arrival time; bottom nav allows switch back to village without losing command state.
