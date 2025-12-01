# Tribal Wars Game Engine

Modern PHP remake of the Tribal Wars browser engine with real-time building, recruiting, and combat loops backed by a configurable data model.

## At a Glance
- Backend: PHP 8.x with SQLite (default) or MySQL, zero framework.
- Frontend: vanilla JS + AJAX endpoints, CSS in `css/`, HTML/PHP templates.
- Real-time timers for builds, recruitment, resources, and attack travel; queues are processed whenever players load the game.
- Interactive world map with tile popups and an attack launcher.
- Sessions, prepared statements, CSRF tokens, and debug/error handling.

## Feature Map
| Icon | Area | What works now | Status |
| --- | --- | --- | --- |
| Key | Accounts & worlds | Registration/login flows, session handling, world selection with `CURRENT_WORLD_ID` stored in session. | Stable |
| Villages | Auto-generated start village with resources/buildings, village rename, population/farm cap tracking, offline resource catch-up. | Stable |
| Resources | Wood/clay/iron production driven by building levels, warehouse caps, hourly rate calc in `ResourceManager`, UI counters updated via JS/AJAX. | Stable |
| Buildings | Config-driven costs/time/requirements, upgrade queue stored in `building_queue`, main-building speed bonus, requirement checks, per-building max level enforcement. | Stable |
| Units & recruitment | Unit catalogue with stats/requirements, recruitment queue by building type (barracks/stable/workshop), cost/time scaling, queue processing. | Stable |
| Research | Research tree with per-building unlocks, prerequisite checks, queue processing via `ResearchManager`. | Needs UI polish/balancing |
| Combat & reports | Attack sending with slowest-unit travel time, battle resolution (wall/rams/catapults), loot calculation, battle reports, notifications to attacker/defender. | Requires tuning/edge-case coverage |
| Map | Draggable grid map (`map/map.php`) showing nearby villages, modal popup with owner/details and attack shortcut. | Stable |
| Messaging | Private messages with inbox/sent/archive tabs, bulk actions, unread counters; DB-backed via `MessageManager`. | UI integration & validation passes |
| Rankings | Player and tribe leaderboards (villages/population based) with pagination and point aggregation. | Stable |
| Notifications | Session toasts plus persistent notifications table with expiry, fetch/read helpers in `NotificationManager`. | Hook into all events |
| Trade & tribes | Trade routes still planned; tribe system live with schema, invites, membership, and ranking hooks. | Trade planned / Tribes beta |

## Core Services & Functions
- `lib/Database.php` - dual SQLite/MySQL adapter exposing a `mysqli`-like API, charset setup, and a PDO-backed SQLite adapter.
- `lib/functions.php` - shared helpers (formatting, CSRF tokens, resource widgets, validation, distance/travel time, notifications, links, etc.).
- `lib/managers/BuildingConfigManager.php` - caches building definitions from `building_types`, computes costs/time/production, requirements, warehouse/farm capacity, population cost.
- `lib/managers/BuildingManager.php` - upgrade costs/time, requirement checks, per-village building data, production rates, display names, and max levels.
- `lib/managers/ResourceManager.php` - hourly production calculation by building levels, warehouse caps, offline resource catch-up, and DB persistence.
- `lib/managers/VillageManager.php` - village CRUD (create, rename, list), building queue processing, recruitment/research completion dispatch, population recalculation, default village selection.
- `lib/managers/UnitManager.php` - unit catalogue cache, recruitment requirements (building level/research), resource affordability, recruitment time scaling, queue processing and synchronization with `village_units`.
- `lib/managers/ResearchManager.php` - research type cache, building/prerequisite checks, level caps, queue processing into `village_research`.
- `lib/managers/BattleManager.php` - attack creation, travel-time calculation, combat resolution (strength comparison, wall/catapults/rams effects, loot), battle report persistence, completion notifications.
- `lib/managers/MessageManager.php` - message retrieval by tab, bulk actions (mark read/unread, archive, delete), unread/archived counters, safety checks per user.
- `lib/managers/NotificationManager.php` - CRUD for persistent notifications with expiry and unread counts.
- `lib/managers/RankingManager.php` - player and tribe ranking queries with pagination and point aggregation.
- `lib/managers/TradeManager.php` - future trade routes/market logic placeholder.
- Frontend JS in `js/` - resource/queue polling, building and recruitment panels, research UI, notifications, utility helpers (`utils.js`, `resources.js`, `buildings.js`, `units.js`, etc.).
- AJAX endpoints in `ajax/` - building upgrades (`ajax/buildings`), unit recruitment (`ajax/units`), and other in-page actions powering the dynamic UI.

## Data & Configuration
- Default database driver is SQLite with the file path set in `config/config.php` (`DB_PATH`). Switch to MySQL by changing `DB_DRIVER` and credentials.
- SQL schemas for MySQL live in `docs/sql/*.sql` (buildings, units, research, battles, messages, notifications, worlds, villages, users).
- `install.php` provides a guided installer for creating tables and an admin account through the browser.
- Global constants in `config/config.php` cover starting resources/population, warehouse/farm math, main-building speed factor, base URL, trader speed, and default world.

## Running Locally
1. Install PHP 8.x with the SQLite (or MySQL) extension enabled.
2. Clone/copy the repo and ensure `config/config.php` points to your chosen driver; SQLite will create `data/tribal_wars.sqlite` on first run.
3. Create the schema:
   - Quick start: open `install.php` in the browser and follow the steps, or
   - Manual: import the `docs/sql/sql_create_*.sql` files into your MySQL database (or adapt them for SQLite).
4. Serve the project (example): `php -S localhost:8000 -t /path/to/tribal-clone`.
5. Visit `http://localhost:8000/`, register a user, and create your first village. Use `map/map.php` for the world view and `game/game.php` for the village overview.

## Roadmap
- [ ] Finish trade routes and market actions (`TradeManager`, AJAX + UI).
- [x] Implement tribe/alliance data model, tribe rankings, and invite/role flows.
- [ ] Harden combat formulas (wall/ram/catapult balance, spy/scout actions) and add automated report links in UI.
- [ ] Complete messaging UI integration and validation (attachments, blocking, spam controls).
- [ ] Wire notifications into all major events (build/recruit/research complete, attacks, messages).
- [ ] Add automated jobs/cron to process queues and attacks without page loads.
- [ ] Improve responsive layout and accessibility across `game/` and `map/`.
- [ ] Add tests/fixtures for managers and AJAX endpoints plus seed/demo data.

## Directory Guide
- `game/` - main authenticated gameplay pages (`game.php`, `world_select.php`).
- `map/` - interactive world map (`map.php`, `map_data.php`).
- `ajax/` - in-page actions for buildings, units, and more.
- `auth/` - registration/login/reset flows.
- `css/`, `js/`, `img/` - frontend styling, scripts, and assets.
- `docs/` - notes and SQL schema files.
- `admin/` - installer verification and admin utilities.
