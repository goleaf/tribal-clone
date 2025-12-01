# Building System - Fixed Issues and Notes

## 1. Root cause
Early builds relied on `upgrade_level_to` / `upgrade_ends_at` columns on `village_buildings` for upgrade tracking. The current architecture uses the `building_queue` table instead, so keeping those columns leads to drift and confusion.

## 2. Fixes applied
- Standardized on `building_queue` for upgrade tracking; `village_buildings` keeps only current `level`.
- Updated `sql_create_buildings_tables.sql` to drop legacy `upgrade_level_to` / `upgrade_ends_at`.
- Admin helpers now report the schema as up-to-date rather than adding legacy columns.

## 3. Diagnostic tools
- **show_table_structure.php** - shows `village_buildings` structure.
- **test_building_system.php** - end-to-end diagnostics (building types, upgrade cost/time calculations, resource production checks).

## 4. Upgrade flow
1. Player triggers an upgrade (form in `game.php`).
2. Data is processed by `upgrade_building.php` which validates resources and building requirements.
3. On success it deducts resources and enqueues the task in `building_queue`.
4. `game.php` and managers poll `building_queue` to complete upgrades and display progress.

## 5. Possible improvements
- Full multi-task queue support (currently single task).
- Premium speed-ups.
- Additional building dependency logic.
- Visual progress indicators for upgrades.
- Canceling builds with partial resource refunds.

These changes keep installs aligned to the queue-based schema and avoid legacy column drift.
