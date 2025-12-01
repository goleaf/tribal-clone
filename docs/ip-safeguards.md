# IP Safeguards Checklist

## Goals
- Keep conquest/combat/diplomacy loops original and distant from classic tribal war browser games.
- Ensure naming, visuals, and player-facing terminology are unique across UI, reports, and marketing.

## Checklist
- **Language Audit**: Replace legacy terms (Nobleman, K-codes, Barbarian Village, Night Bonus, Morale) with new lexicon everywhere (UI, reports, tutorials, notifications).
- **Mechanics Divergence**: Conquest uses influence/control uptime and non-coin costs; siege uses breach severity/DoT; remove luck/morale bands that mirror classic ranges.
- **Content Differentiation**: New POI names (Forsaken Hamlets, Caravan Lanes), renamed buildings/units, non-static PvE loops (migrating caravans, seasonal beasts) instead of barb farms.
- **Visual Identity**: Distinct map styling (hex/river valleys), banners, and iconography; avoid brown/green palette defaults reminiscent of predecessors.
- **Tutorial/NPE**: Teach unique mechanics (control channels, weather fronts, edicts) in first session so flow feels new.
- **Legal/IP Review**: Run final design and naming through legal; keep provenance notes for new names/mechanics and log approvals.

## Action Items
- Schedule a whole-game terminology pass before next milestone; log replacements in a shared glossary.
- Update conquest/siege specs in core design doc to reflect influence/breach mechanics and remove coin-mint dependencies.
- Brief art/UI on new visual direction to avoid legacy color/icon tropes and produce a reference kit.
- Add NPE/tutorial tasks that surface at least two differentiated mechanics in the first 10 minutes.

## Progress
- Created `docs/ip-glossary.md` to codify IP-safe terminology replacements (e.g., Nobleman → Envoy, K-code → Province Code, Barbarian Village → Forsaken Hamlet, Night Bonus → Night Watch).

## Latest Divergence Deltas (added)
- Combat: Luck disabled; morale bands removed. Siege uses breach severity + DoT ticks instead of single-roll wall hits.
- Conquest: Coin minting removed; conquest costs are influence/control uptime. Nobles renamed to Envoys across UI/reports.
- Terminology: Barbarian Village → Forsaken Hamlet; K-codes hidden; Night Bonus replaced by Weather Fronts (visibility/speed modifiers).
- PvE: Static barb farming replaced with migrating Caravans/Beasts POIs to differentiate resource loops.
- Visuals: Map palette locked to blue-sand; village markers hexagonal with new icon set; banners/flags use custom pack (no classic shields).
