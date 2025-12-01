# Building System Notes

## Core formulas
- Build time: `base_time × (BUILD_TIME_LEVEL_FACTOR ^ level) / (WORLD_SPEED × (1 + HQ_level × 0.02))`. Defaults: `BUILD_TIME_LEVEL_FACTOR=1.18`, `WORLD_SPEED=1.0`, 2% faster per HQ (main building) level.
- Farm capacity: `240 × 1.172^level` (tunable via `FARM_GROWTH_FACTOR`).
- Wall defense: `1 + 0.08 × wall_level` (flat +8% per level).

## Building roles and requirements
- Headquarters (`main_building`): required for all builds; speeds construction.
- Barracks: infantry recruitment.
- Stable: cavalry recruitment; requires Barracks 10 and Smithy 5.
- Workshop: siege recruitment; requires Smithy 10.
- Smithy: research/unlocks units.
- Academy: noble research/minting prerequisite.
- Rally Point: required to send troops and see overviews; unlocked with HQ 1.
- Market: trade; requires HQ 3 and Warehouse 2.
- Wall: defense bonus; requires Barracks 1.
- Church / First Church: faith structures; first_church unique starter, both require HQ 5 (first_church HQ 2). Faith adds a defense multiplier.
- Statue: noble training sink; requires Academy 1. Demolishing costs time and refunds 90% of the last level’s cost.
- Hiding Place: protects resources from raids; hides `150 * 1.233^level` per resource before loot is calculated.
- Resource producers: Timber Camp, Clay Pit, Iron Mine (max 30).
- Farm: expands population cap.
- Warehouse: stores resources.
