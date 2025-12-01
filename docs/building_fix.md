# Building System - Fixed Issues and Notes

## 1. Root cause
A missing `upgrade_level_to` and `upgrade_ends_at` column in the `village_buildings` table caused critical errors when processing building upgrades.

## 2. Fixes applied
- Added the missing `upgrade_level_to` and `upgrade_ends_at` columns to `village_buildings`.
- Updated `sql_create_buildings_tables.sql` so fresh installs include the new columns.
- Removed a stray space after the closing PHP tag in `lib/BuildingManager.php` that triggered header warnings.

### Updated table snippet
```
level INT DEFAULT 0,
upgrade_level_to INT DEFAULT NULL,
upgrade_ends_at DATETIME DEFAULT NULL,
UNIQUE (village_id, building_type_id)
```

## 3. Diagnostic tools
- **show_table_structure.php** - shows `village_buildings` structure and allows adding missing columns.
- **test_building_system.php** - end-to-end diagnostics (building types, upgrade cost/time calculations, resource production checks).

## 4. Upgrade flow
1. Player triggers an upgrade (form in `game.php`).
2. Data is processed by `upgrade_building.php` which validates resources and building requirements.
3. On success it deducts resources and sets `upgrade_level_to`/`upgrade_ends_at` on `village_buildings`.
4. `game.php` checks on each refresh whether upgrades finished and displays success messages.

## 5. Possible improvements
- Full build queue support (currently single task).
- Premium speed-ups.
- Additional building dependency logic.
- Visual progress indicators for upgrades.
- Canceling builds with partial resource refunds.

These changes resolve the critical upgrade issue and ensure the installer creates the correct schema for new setups.
