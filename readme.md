# Tribal Wars Game Engine

Modern PHP remake of the Tribal Wars browser engine with real-time building, recruiting, and combat loops backed by a configurable data model.

## At a Glance
- Backend: PHP 8.4+ with SQLite (default) or MySQL, zero framework.
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
- `lib/managers/TradeManager.php` - trader availability, transport processing, and market offers.
- Frontend JS in `js/` - resource/queue polling, building and recruitment panels, research UI, notifications, utility helpers (`utils.js`, `resources.js`, `buildings.js`, `units.js`, etc.).
- AJAX endpoints in `ajax/` - building upgrades (`ajax/buildings`), unit recruitment (`ajax/units`), and other in-page actions powering the dynamic UI.

## Data & Configuration
- Default database driver is SQLite with the file path set in `config/config.php` (`DB_PATH`). Switch to MySQL by changing `DB_DRIVER` and credentials.
- SQL schemas for MySQL live in `docs/sql/*.sql` (buildings, units, research, battles, messages, notifications, worlds, villages, users).
- `install.php` provides a guided installer for creating tables and an admin account through the browser.
- Global constants in `config/config.php` cover starting resources/population, warehouse/farm math, main-building speed factor, base URL, trader speed/capacity, and default world.

## Running Locally
1. Install PHP 8.4+ with the SQLite (or MySQL) extension enabled (run `php tests/php_84_compat_check.php` to verify).
2. Clone/copy the repo and ensure `config/config.php` points to your chosen driver; SQLite will create `data/tribal_wars.sqlite` on first run.
3. Create the schema:
   - Quick start: open `install.php` in the browser and follow the steps, or
   - Manual: import the `docs/sql/sql_create_*.sql` files into your MySQL database (or adapt them for SQLite).
4. Serve the project (example): `php -S localhost:8000 -t /path/to/tribal-clone`.
5. Visit `http://localhost:8000/`, register a user, and create your first village. Use `map/map.php` for the world view and `game/game.php` for the village overview.
6. (Optional) Run cron-style processing so queues and attacks finish even when nobody is online: `php jobs/process_queues.php` (also converts long-inactive villages to barbarian; see `INACTIVE_TO_BARBARIAN_DAYS`).

## Roadmap
- [x] Implement trade routes, trader limits, and market offers (create/accept/cancel).
- [x] Implement tribe/alliance data model, tribe rankings, and invite/role flows.
- [ ] Combat depth: separate spy/scout intel, richer battle reports, movement queues on the map, and wall/ram/catapult tuning (see `docs/roadmap.md`).
- [ ] Complete messaging UI integration and validation (attachments, blocking, spam controls).
- [x] Wire notifications into all major events (build/recruit/research complete, attacks, messages).
- [x] Add automated jobs/cron to process queues and attacks without page loads.
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

## Military Units System

The game features a comprehensive military system with 16+ distinct unit types across seven categories, each with unique combat roles and strategic purposes. The system implements rock-paper-scissors combat dynamics, support unit mechanics, and conquest capabilities.

### Unit Categories and Roles

**Infantry Units** (Barracks)
- **Pikeneer**: Anti-cavalry specialist with high defense against mounted units (1.4x bonus)
- **Shieldbearer**: Balanced defensive unit with equal defense against all damage types
- **Raider**: High-attack offensive unit with low defense, ideal for raids
- **Warden**: Elite defensive infantry with very high defense across all types

**Cavalry Units** (Stable)
- **Skirmisher Cavalry**: Fast raiding unit with high speed and carry capacity
- **Lancer**: Heavy cavalry with high attack and population cost
- Cavalry gains 1.5x attack bonus vs ranged units in open field (wall = 0)

**Ranged Units** (Barracks)
- **Militia Bowman**: Basic ranged defense unit
- **Longbow Scout**: Improved offensive ranged unit
- **Ranger**: Elite ranged unit with 1.6x bonus damage against siege equipment
- Ranged units gain 1.5x defense bonus vs infantry when defending behind walls

**Scout Units** (Barracks/Stable)
- **Pathfinder**: Fast scout revealing troop counts and resources
- **Shadow Rider**: Advanced scout revealing building levels and queues
- Scouts must survive to reveal intelligence; defending scouts can kill attackers

**Siege Units** (Workshop)
- **Battering Ram**: Reduces wall levels on successful attacks
- **Stone Hurler**: Damages buildings on successful attacks
- **Mantlet Crew**: Provides 40% ranged damage reduction to escorted siege units
- Capped at 200 total siege units per village

**Support Units** (Rally Point/Hospital)
- **Banner Guard**: Provides defensive aura buff (1.15x defense) to all defending troops
- **War Healer**: Recovers up to 15% of lost troops after battle (configurable per world)
- Multiple Banner Guards don't stack; only highest tier aura applies

**Conquest Units** (Academy/Hall of Banners)
- **Noble**: Reduces village allegiance by 20-35 points per surviving unit
- **Standard Bearer**: Alternative conquest unit with similar mechanics
- Limited to 1 conquest unit per attack command
- Requires minted coins/standards to train

### RPS (Rock-Paper-Scissors) Mechanics

The combat system implements strategic matchups where unit types have advantages and disadvantages:

**Cavalry vs Ranged (Open Field)**
- Cavalry gains 1.5x attack bonus against ranged units when wall level = 0
- Represents cavalry's ability to close distance and overwhelm archers
- Negated by fortifications (wall level > 0)

**Pike vs Cavalry**
- Pikeneer gains 1.4x defense bonus against cavalry attacks
- Represents pike formations stopping cavalry charges
- Applies regardless of wall level

**Ranger vs Siege**
- Ranger gains 1.6x attack bonus against siege equipment
- Represents targeting and disabling siege weapons from range
- Makes siege vulnerable without proper escort

**Ranged Wall Bonus**
- Ranged units gain 1.5x defense bonus vs infantry when wall level > 0
- Represents advantage of elevated positions and fortifications
- Scales with wall level (maximum benefit at level 10+)

**Configuration**: See `config/rps_modifiers.php` for detailed modifier documentation

### Support Unit Mechanics

**Banner Guard Auras**
- Provides 1.15x defense multiplier to all defending troops
- Adds +5 resolve bonus (morale/combat effectiveness)
- Multiple Banner Guards don't stack; only highest tier applies
- Aura persists as long as Banner Guards survive

**War Healer Recovery**
- Recovers percentage of lost troops after battle
- Default cap: 15% of total losses per battle
- Configurable per world via `healer_recovery_cap` setting
- Recovery applies to all unit types, not just infantry

**Mantlet Protection**
- Reduces incoming ranged damage to siege units by 40%
- Protection applies while Mantlet units survive
- Removed if Mantlets are killed during battle
- Essential for siege operations against ranged defenders

### Conquest Mechanics

**Village Capture Process**
1. Train Noble or Standard Bearer units (requires minted coins/standards)
2. Send attack with conquest units (max 1 per command)
3. Win the battle (conquest units must survive)
4. Each surviving conquest unit reduces allegiance by 20-35 points
5. Village captured when allegiance reaches 0

**Conquest Rules**
- Only 1 conquest unit allowed per attack command
- Conquest units must survive the battle to reduce allegiance
- Losing attacks don't reduce allegiance
- Newly captured villages have 7-day conquest immunity
- Minimum defender points: 500 (prevents conquest of very weak targets)

**Resource Requirements**
- Nobles require minted coins (Academy level 3, Smithy level 20)
- Standard Bearers require crafted standards (Hall of Banners)
- Coins/standards are consumed when training conquest units

### Seasonal Unit Lifecycle

**Event Units**
- Limited-time units available during special events
- Configured with start/end timestamps in `seasonal_units` table
- Per-account cap (default: 50) prevents hoarding
- Training disabled outside event window

**Sunset Handling**
- When event expires, training is automatically disabled
- Existing units can be converted to resources or disabled (world config)
- Seasonal unit activation/sunset managed by `jobs/process_seasonal_units.php`
- Telemetry tracks adoption rates and alerts on anomalies

**Configuration**: See `docs/seasonal-units-lifecycle.md` for detailed lifecycle documentation

### Unit Caps

**Per-Village Caps**
- Siege units: 200 total per village (rams, catapults, mantlets)
- Prevents single-village siege stacking
- Encourages distributed production across multiple villages

**Per-Account Caps**
- Elite units: 100 per type (Warden, Ranger)
- Seasonal units: 50 per type (configurable per event)
- Prevents elite unit hoarding
- Maintains rarity and strategic value

**Per-Command Caps**
- Conquest units: 1 per attack command
- Prevents instant village capture
- Forces multiple coordinated attacks

**Configuration**: See `config/unit_caps.php` for detailed cap documentation and rationale

### World-Specific Multipliers

World administrators can configure multipliers for different server archetypes:

**Training Time Multipliers**
- `train_multiplier_inf`: Infantry training speed (default: 1.0)
- `train_multiplier_cav`: Cavalry training speed (default: 1.0)
- `train_multiplier_rng`: Ranged training speed (default: 1.0)
- `train_multiplier_siege`: Siege training speed (default: 1.0)

**Cost Multipliers**
- `cost_multiplier_inf`: Infantry resource costs (default: 1.0)
- `cost_multiplier_cav`: Cavalry resource costs (default: 1.0)
- `cost_multiplier_rng`: Ranged resource costs (default: 1.0)
- `cost_multiplier_siege`: Siege resource costs (default: 1.0)

**Feature Flags**
- `conquest_units_enabled`: Enable/disable conquest units (default: true)
- `seasonal_units_enabled`: Enable/disable seasonal units (default: true)
- `healer_enabled`: Enable/disable War Healer units (default: false)
- `healer_recovery_cap`: Healer recovery percentage cap (default: 0.15)

**Example Configurations**
- Speed worlds: 0.5x training time, 1.0x costs
- Hardcore worlds: 1.0x training time, 1.5x costs
- Casual worlds: 1.0x training time, 1.0x costs

### Unit Data Management

**Unit Definitions** (`data/units.json`)
- Complete unit roster with stats, costs, and requirements
- RPS bonuses defined per unit type
- Special abilities and aura configurations
- Version tracking for balance changes

**Validation** (`scripts/validate_unit_data.php`)
- Validates all required fields are present and positive
- Validates RPS relationships are maintained
- Validates world overrides don't break balance constraints
- Outputs validation errors for review

**Diff Generation** (`scripts/generate_unit_diff.php`)
- Compares current units.json with previous version
- Generates human-readable diff showing stat changes
- Outputs diff for changelog documentation
- Helps track balance changes over time

### Testing

**Unit Tests**
- Unit unlock prerequisites and building requirements
- Resource and population validation
- Cap enforcement (per-village, per-account, per-command)
- Feature flag filtering

**Property-Based Tests**
- RPS modifier application across random inputs
- World multiplier calculations
- Conquest allegiance reduction
- Support unit mechanics (auras, healing, mantlets)
- Concurrent cap enforcement

**Integration Tests**
- End-to-end recruitment workflow
- Combat resolution with RPS modifiers
- Conquest workflow (train → attack → capture)
- Seasonal unit lifecycle (activate → train → sunset)

**Test Files**
- `tests/rps_modifiers_integration_test.php`
- `tests/support_unit_mechanics_test.php`
- `tests/conquest_allegiance_integration_test.php`
- `tests/siege_mechanics_integration_test.php`
- `tests/scout_intel_mechanics_test.php`
- `tests/seasonal_unit_integration_test.php`
- `tests/elite_unit_cap_test.php`

### Implementation Reference

**Core Managers**
- `lib/managers/UnitManager.php`: Unit loading, prerequisites, caps, recruitment
- `lib/managers/BattleManager.php`: Combat resolution, RPS modifiers, support effects
- `lib/managers/WorldManager.php`: World settings, feature flags, multipliers

**AJAX Endpoints**
- `ajax/units/recruit.php`: Handle training requests
- `ajax/units/get_recruitment_panel.php`: Display available units
- `ajax/units/cancel_recruitment.php`: Cancel queued training

**Background Jobs**
- `jobs/process_queues.php`: Process recruitment queues
- `jobs/process_seasonal_units.php`: Manage seasonal unit lifecycle

**Configuration Files**
- `config/rps_modifiers.php`: RPS multiplier constants and documentation
- `config/unit_caps.php`: Unit cap values and rationale
- `data/units.json`: Complete unit definitions

**Documentation**
- `docs/seasonal-units-lifecycle.md`: Seasonal unit management
- `docs/seasonal-units-quick-reference.md`: Quick reference guide
- `.kiro/specs/military-units-system/`: Complete specification and design

## Documentation
- Roadmaps: `docs/roadmap.md`
- Guides section plan: `docs/guides_section_plan.md`
