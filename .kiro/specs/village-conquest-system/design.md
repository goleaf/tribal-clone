# Design Document: Village Conquest & Control System

## Overview

The Village Conquest & Control system implements a strategic, multi-phase village capture mechanism. The system supports two primary modes:

1. **Allegiance Drop Mode**: Villages have an allegiance value (0-100) that decreases with successful Envoy attacks; capture occurs when allegiance reaches 0
2. **Control/Uptime Mode**: Attackers build a control meter (0-100) through Envoy waves; when control reaches 100 and holds through an uptime window, capture occurs

Both modes share core mechanics: special Envoy units, telegraphed multi-wave attacks, defensive resistance, regeneration systems, and anti-abuse protections. The system is designed to be contestable, requiring coordination and timing while preventing instant captures and ping-pong ownership transfers.

## Architecture

### High-Level Components

```
┌─────────────────────────────────────────────────────────────┐
│                     Conquest System                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────────┐      ┌──────────────────┐             │
│  │  Training        │      │  Combat          │             │
│  │  Pipeline        │──────│  Integration     │             │
│  │                  │      │                  │             │
│  │ - Hall of Banners│      │ - Battle Hook    │             │
│  │ - Envoy Queue    │      │ - Survival Check │             │
│  │ - Crest Minting  │      │ - Win Validation │             │
│  └──────────────────┘      └──────────────────┘             │
│           │                         │                       │
│           │                         ▼                       │
│           │         ┌──────────────────────────┐            │
│           │         │  Allegiance/Control      │            │
│           └────────▶│  Service                 │            │
│                     │                          │            │
│                     │ - Drop Calculation       │            │
│                     │ - Regen Tick             │            │
│                     │ - Floor Enforcement      │            │
│                     │ - Capture Detection      │            │
│                     └──────────────────────────┘            │
│                                 │                           │
│                                 ▼                           │
│                     ┌──────────────────────────┐            │
│                     │  State Machine           │            │
│                     │                          │            │
│                     │ - Prereq Validation      │            │
│                     │ - Protection Checks      │            │
│                     │ - Cooldown Enforcement   │            │
│                     │ - Reason Codes           │            │
│                     └──────────────────────────┘            │
│                                 │                           │
│                                 ▼                           │
│                     ┌──────────────────────────┐            │
│                     │  Post-Capture Handler    │            │
│                     │                          │            │
│                     │ - Ownership Transfer     │            │
│                     │ - Resource Transfer      │            │
│                     │ - Support Handling       │            │
│                     │ - Anti-Snipe Setup       │            │
│                     └──────────────────────────┘            │
│                                 │                           │
│                                 ▼                           │
│                     ┌──────────────────────────┐            │
│                     │  Reporting System        │            │
│                     │                          │            │
│                     │ - Conquest Reports       │            │
│                     │ - Reason Codes           │            │
│                     │ - Audit Logs             │            │
│                     └──────────────────────────┘            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Component Interactions

1. **Training Pipeline** → Validates prerequisites, consumes resources, queues Envoy production
2. **Combat Integration** → Hooks into battle resolution, checks Envoy survival, triggers allegiance service
3. **Allegiance/Control Service** → Core calculation engine for drops, regen, floors, and capture detection
4. **State Machine** → Validates all prerequisites, enforces protections, returns reason codes
5. **Post-Capture Handler** → Executes ownership transfer, resource handling, anti-snipe setup
6. **Reporting System** → Generates detailed reports with all relevant conquest data

## Components and Interfaces

### AllegianceService

**Responsibility**: Core calculation engine for allegiance/control mechanics

**Interface**:
```php
class AllegianceService {
    // Calculate allegiance drop from a conquest wave
    public function calculateDrop(
        int $villageId,
        int $survivingEnvoys,
        int $wallLevel,
        array $modifiers
    ): array; // Returns ['new_allegiance', 'drop_amount', 'captured']
    
    // Apply regeneration tick
    public function applyRegeneration(
        int $villageId,
        int $currentAllegiance,
        int $elapsedSeconds,
        array $bonuses
    ): int; // Returns new allegiance value
    
    // Check and enforce anti-snipe floor
    public function enforceFloor(
        int $villageId,
        int $proposedAllegiance
    ): int; // Returns clamped allegiance
    
    // Detect if capture conditions are met
    public function checkCapture(
        int $allegiance,
        bool $inAntiSnipe
    ): bool;
}
```

**Key Algorithms**:
- **Drop Calculation**: `base_drop = random(18, 28) * envoy_count * world_multiplier * (1 - min(0.5, wall_level * 0.02))`
- **Regen Calculation**: `regen = (base_rate / 3600) * elapsed_seconds * building_multiplier * tech_multiplier`, clamped to [0, 100]
- **Floor Enforcement**: `max(proposed_allegiance, floor_value)` while anti-snipe active

### ConquestStateMachine

**Responsibility**: Validate prerequisites and enforce business rules

**Interface**:
```php
class ConquestStateMachine {
    // Validate all prerequisites for a conquest attempt
    public function validateAttempt(
        int $attackerId,
        int $defenderId,
        int $villageId,
        bool $attackerWon,
        int $survivingEnvoys
    ): ValidationResult; // Returns ['allowed', 'reason_code', 'message']
    
    // Check protection status
    public function isProtected(int $playerId, int $villageId): bool;
    
    // Check cooldown status
    public function isInCooldown(int $villageId): bool;
    
    // Enforce wave spacing
    public function checkWaveSpacing(
        int $attackerId,
        int $villageId,
        int $arrivalTime
    ): bool;
}
```

**Validation Checks**:
1. Combat win required
2. At least one Envoy survived
3. Target not in safe zone
4. Target not protected/beginner
5. Capture cooldown not active
6. Wave spacing requirements met
7. Tribe handover rules satisfied
8. Village cap not exceeded

### TrainingPipeline

**Responsibility**: Handle Envoy training, prerequisites, and resource consumption

**Interface**:
```php
class TrainingPipeline {
    // Validate training prerequisites
    public function canTrain(
        int $villageId,
        int $quantity
    ): ValidationResult;
    
    // Queue Envoy training
    public function queueTraining(
        int $villageId,
        int $quantity,
        array $costs
    ): bool;
    
    // Mint influence crests
    public function mintCrests(
        int $villageId,
        int $quantity
    ): bool;
}
```

**Prerequisites**:
- Hall of Banners at required level
- Research node `conquest_training` completed
- Sufficient influence crests in inventory
- Resources and population available
- Per-command and per-day caps not exceeded
- World feature flag enabled

### PostCaptureHandler

**Responsibility**: Execute all post-capture state transitions

**Interface**:
```php
class PostCaptureHandler {
    // Execute full capture sequence
    public function executeCapture(
        int $villageId,
        int $newOwnerId,
        int $oldOwnerId
    ): CaptureResult;
    
    // Transfer ownership
    private function transferOwnership(int $villageId, int $newOwnerId): void;
    
    // Handle stationed troops
    private function handleSupport(int $villageId, int $newOwnerId): void;
    
    // Setup anti-snipe protection
    private function setupAntiSnipe(int $villageId): void;
    
    // Optional building loss
    private function applyBuildingLoss(int $villageId): ?array;
}
```

**Post-Capture Actions**:
1. Transfer village ownership
2. Update diplomacy states for stationed troops
3. Transfer remaining resources
4. Handle allied support (stay or return based on config)
5. Set post-capture allegiance start value
6. Activate anti-snipe floor and cooldown
7. Optionally reduce one military building
8. Cancel outgoing attacks from captured village
9. Generate capture report

## Data Models

### Village Allegiance/Control

```sql
-- Extended villages table
ALTER TABLE villages ADD COLUMN allegiance INTEGER DEFAULT 100;
ALTER TABLE villages ADD COLUMN last_allegiance_update INTEGER DEFAULT 0;
ALTER TABLE villages ADD COLUMN capture_cooldown_until INTEGER DEFAULT 0;
ALTER TABLE villages ADD COLUMN allegiance_floor INTEGER DEFAULT 0;
ALTER TABLE villages ADD COLUMN anti_snipe_until INTEGER DEFAULT 0;
ALTER TABLE villages ADD COLUMN control_meter INTEGER DEFAULT 0;
ALTER TABLE villages ADD COLUMN uptime_started_at INTEGER DEFAULT 0;
```

### Conquest Attempts Log

```sql
CREATE TABLE conquest_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp INTEGER NOT NULL,
    world_id INTEGER NOT NULL,
    attacker_id INTEGER NOT NULL,
    defender_id INTEGER NOT NULL,
    village_id INTEGER NOT NULL,
    surviving_envoys INTEGER NOT NULL,
    allegiance_before INTEGER NOT NULL,
    allegiance_after INTEGER NOT NULL,
    drop_amount INTEGER NOT NULL,
    captured BOOLEAN NOT NULL,
    reason_code TEXT,
    wall_level INTEGER,
    modifiers TEXT -- JSON
);
```

### Envoy Unit Configuration

```json
{
  "envoy": {
    "name": "Envoy",
    "wood": 40000,
    "clay": 50000,
    "iron": 50000,
    "population": 100,
    "speed": 30,
    "attack": 30,
    "defense": 100,
    "defense_cavalry": 50,
    "defense_archer": 80,
    "carry": 0,
    "build_time": 36000,
    "required_building": "hall_of_banners",
    "required_level": 1,
    "special": "conquest"
  }
}
```

### World Configuration

```json
{
  "conquest_mode": "allegiance_drop",
  "feature_conquest_enabled": true,
  "alleg_regen_per_hour": 2.0,
  "alleg_wall_reduction_per_level": 0.02,
  "anti_snipe_floor": 10,
  "anti_snipe_seconds": 900,
  "post_capture_start": 25,
  "capture_cooldown_seconds": 900,
  "wave_spacing_ms": 300,
  "max_envoys_per_command": 1,
  "conquest_daily_mint_cap": 5,
  "conquest_daily_train_cap": 3,
  "conquest_min_defender_points": 1000,
  "uptime_duration_seconds": 900,
  "control_gain_rate_per_min": 5,
  "control_decay_rate_per_min": 3
}
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

After analyzing all acceptance criteria, several properties can be consolidated:
- Properties 1.1-1.4 all test prerequisite validation and can be combined into a comprehensive prerequisite enforcement property
- Properties 7.1-7.5 all test report generation and can be combined into a comprehensive reporting property
- Properties 2.1 and 2.5 test opposite conditions (success vs failure) and can be combined
- Properties 5.1, 5.2, and 5.5 all test post-capture initialization and can be combined

### Core Conquest Properties

**Property 1: Prerequisite enforcement**
*For any* Envoy training attempt, the system should validate all prerequisites (Hall of Banners level, influence crests, resources, population, caps) and return the appropriate error code (ERR_PREREQ, ERR_CAP, ERR_RES, ERR_POP) when any prerequisite is not met, or allow training when all are satisfied.
**Validates: Requirements 1.1, 1.3, 1.4**

**Property 2: Resource consumption on training**
*For any* successful Envoy training, the system should deduct exactly the configured amounts of influence crests, wood, clay, iron, and population from the player's inventory.
**Validates: Requirements 1.2**

**Property 3: Control establishment on victory**
*For any* battle where the attacker wins and at least one Envoy survives, the system should establish a control link and apply initial control gain; for any battle where the attacker loses or no Envoys survive, control should remain unchanged.
**Validates: Requirements 2.1, 2.5**

**Property 4: Control gain rate calculation**
*For any* active control link where attacker pressure exceeds defender resistance, control should increase by exactly `(rate_per_minute / 60) * elapsed_seconds`, clamped to [0, 100].
**Validates: Requirements 2.2**

**Property 5: Capture on uptime completion**
*For any* village where control reaches 100 and remains at or above 100 for the entire uptime duration, ownership should transfer to the attacker.
**Validates: Requirements 2.4**

**Property 6: Control decay on defender dominance**
*For any* village where defender resistance exceeds attacker pressure by the threshold, control should decay toward zero at the configured decay rate.
**Validates: Requirements 3.1**

**Property 7: Wall impact on survival**
*For any* Envoy attack against a village with walls, the Envoy survival rate should decrease as wall level increases, following the formula `survival_modifier = (1 - min(0.5, wall_level * reduction_factor))`.
**Validates: Requirements 3.2**

**Property 8: Resistance increase on support**
*For any* village receiving defender support troops, the resistance value should increase proportionally to the defensive strength of the arriving troops.
**Validates: Requirements 3.3**

**Property 9: Uptime timer reset**
*For any* village in uptime where control drops below 100, the uptime timer should reset to zero.
**Validates: Requirements 3.4**

**Property 10: Zero survivors means no control**
*For any* battle where all Envoys are eliminated, control gain should be exactly zero.
**Validates: Requirements 3.5**

### Regeneration Properties

**Property 11: Time-based regeneration**
*For any* village with allegiance below 100, when time elapses, allegiance should increase by exactly `(base_rate / 3600) * elapsed_seconds * multiplier`, where multiplier is the product of all active bonuses capped at the maximum.
**Validates: Requirements 4.1, 4.3**

**Property 12: Allegiance clamping**
*For any* allegiance calculation (drop, regen, or initialization), the resulting value should always be clamped to the range [0, 100].
**Validates: Requirements 4.2**

**Property 13: Regeneration pause during anti-snipe**
*For any* village in anti-snipe grace period, allegiance regeneration should not apply regardless of elapsed time.
**Validates: Requirements 4.4**

**Property 14: Abandonment decay**
*For any* village where the owner has been offline for longer than the configured abandonment duration and the village has no garrison, allegiance should decay at the configured decay rate when the feature is enabled.
**Validates: Requirements 4.5**

### Anti-Snipe Properties

**Property 15: Post-capture initialization**
*For any* captured village, the system should set allegiance to the configured post-capture start value, activate the anti-snipe floor, and set the capture cooldown.
**Validates: Requirements 5.1, 5.2, 5.5**

**Property 16: Floor enforcement during anti-snipe**
*For any* village with an active anti-snipe floor, allegiance should never drop below the floor value regardless of attack strength.
**Validates: Requirements 5.3**

**Property 17: Floor expiry**
*For any* village where the anti-snipe period has expired, allegiance should be reducible below the previous floor value by subsequent attacks.
**Validates: Requirements 5.4**

### Protection Properties

**Property 18: Protection blocking**
*For any* conquest attempt against a protected player, beginner player, or village in a safe zone, the system should block the attempt and return the appropriate error code (ERR_PROTECTED, ERR_SAFE_ZONE).
**Validates: Requirements 6.1, 6.2**

**Property 19: Village cap enforcement**
*For any* player at or above the per-account village limit, conquest attempts should be blocked with ERR_VILLAGE_CAP.
**Validates: Requirements 6.3**

**Property 20: Repeated capture detection**
*For any* pair of accounts where captures occur repeatedly within the detection window, the system should flag the activity and apply diminishing returns to subsequent attempts.
**Validates: Requirements 6.4**

**Property 21: Handover opt-in enforcement**
*For any* tribe-internal conquest attempt where handover opt-in is required but not enabled, the system should block the attempt with ERR_HANDOVER_OFF.
**Validates: Requirements 6.5**

### Reporting Properties

**Property 22: Comprehensive conquest reporting**
*For any* conquest attempt (successful or blocked), the system should generate a report containing allegiance/control changes, surviving Envoy count, reason codes (if blocked), regeneration amounts (if applicable), and all active modifiers.
**Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5**

### Timing Properties

**Property 23: Wave spacing enforcement**
*For any* sequence of Envoy waves from the same attacker to the same target, the system should enforce minimum spacing per world configuration and reject or adjust waves that violate spacing with ERR_SPACING.
**Validates: Requirements 8.1, 8.2**

**Property 24: Random resolution order**
*For any* set of waves arriving in the same tick, the system should resolve them in a non-deterministic order to prevent exploitation.
**Validates: Requirements 8.3**

**Property 25: Resolution audit logging**
*For any* wave resolution, the system should log the resolution order, timestamp, and outcome for audit purposes.
**Validates: Requirements 8.4**

**Property 26: Training cap enforcement**
*For any* Envoy training attempt, the system should enforce per-village and per-day caps and reject attempts that exceed limits.
**Validates: Requirements 8.5**

### Post-Capture Properties

**Property 27: Ownership transfer completeness**
*For any* captured village, the system should transfer ownership, update diplomacy states for all stationed troops, transfer remaining resources, and handle allied support according to world configuration.
**Validates: Requirements 9.1, 9.2, 9.3, 9.4**

**Property 28: Optional building loss**
*For any* captured village in a world with building loss enabled, the system should have a configured probability of reducing one military building by one level, and should never reduce more than one building.
**Validates: Requirements 9.5**

### Configuration Properties

**Property 29: Mode-specific behavior**
*For any* world configured with allegiance-drop mode, the system should use allegiance mechanics; for any world configured with control-uptime mode, the system should use control meter and uptime mechanics.
**Validates: Requirements 10.1**

**Property 30: Configuration application**
*For any* world-specific configuration value (regeneration rates, caps, modifiers, spacing, costs, limits), the system should apply that value consistently across all operations in that world.
**Validates: Requirements 10.2, 10.3, 10.4, 10.5**

## Error Handling

### Error Codes

The system uses structured error codes for all validation failures:

- **ERR_PREREQ**: Prerequisites not met (building level, research, etc.)
- **ERR_CAP**: Capacity limit exceeded (per-command, per-day, per-account)
- **ERR_RES**: Insufficient resources
- **ERR_POP**: Insufficient population
- **ERR_PROTECTED**: Target is protected or beginner player
- **ERR_SAFE_ZONE**: Target is in a safe zone
- **ERR_COOLDOWN**: Capture cooldown active
- **ERR_SPACING**: Wave spacing violation
- **ERR_VILLAGE_CAP**: Village limit exceeded
- **ERR_HANDOVER_OFF**: Tribe handover not enabled
- **ERR_COMBAT_LOSS**: Attacker lost the battle
- **ERR_NO_BEARER**: No Envoys survived
- **ERR_FEATURE_OFF**: Conquest feature disabled

### Error Handling Strategy

1. **Validation First**: All prerequisites validated before any state changes
2. **Atomic Operations**: Capture sequences are atomic; partial failures roll back
3. **Detailed Logging**: All errors logged with context for debugging and audit
4. **User Feedback**: Error codes translated to user-friendly messages in reports
5. **Graceful Degradation**: System continues operating even if individual conquest attempts fail

### Edge Cases

- **Simultaneous Captures**: If multiple attackers reach capture conditions in the same tick, resolve in random order; first successful capture wins
- **Cooldown Expiry During Transit**: If cooldown expires while Envoys are in transit, allow the attack to proceed normally
- **Configuration Changes Mid-Attack**: Use configuration values from attack launch time, not arrival time
- **Overflow Protection**: All numeric calculations use safe math to prevent integer overflow
- **Concurrent Modifications**: Use database transactions to prevent race conditions on allegiance updates

## UI Design Specifications

### Design Integration from Stitch

The village conquest system UI will be integrated into a medieval-themed interface based on the Stitch design (project ID: 561542776319018918). The design features a parchment-style aesthetic with ornate borders and medieval visual elements.

### Village Overview Layout

**Header Navigation**
- Horizontal navigation bar with buttons: MAP, REPORTS, ALLIANCE, RANKINGS, SETTINGS, LOG OUT
- Player info display: Username (e.g., "Lord_Ragnar") and server time (e.g., "14:32:05")
- Medieval button styling with aged parchment texture

**Village Title Section**
- Large heading: "VILLAGE OVERVIEW: [VILLAGE NAME]"
- Centered with decorative border elements
- Medieval font styling

**Resources Panel**
- Horizontal layout with 4 resource types:
  - Wood: Icon + amount/hr (e.g., "1200 / HR")
  - Clay: Icon + amount/hr (e.g., "1050 / HR")
  - Iron: Icon + amount/hr (e.g., "900 / HR")
  - Crop: Icon + amount/hr (e.g., "2500 / HR")
- Warehouse capacity display: "WAREHOUSE CAPACITY: 5000 / 5000"
- Decorative header with "RESOURCES" label
- Parchment background with ornate borders

**Buildings Section**
- Grid layout (2 columns) displaying all buildings
- Each building card shows:
  - Building icon (medieval graphic)
  - Building name (e.g., "TOWN HALL")
  - Current level (e.g., "Level 15")
  - Upgrade button: "Upgrade to 16" with progress bar
  - Progress bar showing upgrade progress (green fill)
- Buildings displayed:
  - Town Hall, Wall, Barracks, Farm, Stable, Warehouse, Workshop, Timber Camp, Academy, Clay Pit, Market, Iron Mine, Hiding Place
- Decorative header with "BUILDINGS" label
- Parchment background with ornate borders

**Troops Section**
- Vertical list of unit types with quantities:
  - Spearmen: 500
  - Swordsmen: 350
  - Archers: 200
  - Light Cavalry: 150
  - Heavy Cavalry: 50
  - Ram: 20
  - Catapult: 10
  - Nobleman: 1
- Unit icons displayed next to each type
- Decorative header with "TROOPS" label
- Parchment background with ornate borders

**Village Status Panel**
- Displays three key metrics:
  - LOYALTY: 100% (maps to allegiance/control system)
  - MORALE: 100%
  - POPULATION: 3450 / 4000
- Decorative header with "VILLAGE STATUS" label
- Parchment background with ornate borders
- Visual indicators for status levels (color-coded)

**Current Construction Panel**
- Shows ongoing building upgrades:
  - Building name and target level (e.g., "Barracks (Level 11)")
  - Countdown timer (e.g., "00:45:30")
  - Multiple items if queued
- Action buttons:
  - CANCEL button (red/brown)
  - SPEED UP button (green/gold)
- Decorative header with "CURRENT CONSTRUCTION" label
- Parchment background with ornate borders

### Conquest-Specific UI Elements

**Loyalty Display (Allegiance/Control)**
- Integrated into Village Status panel
- Shows current allegiance/control value as percentage
- Color-coded indicators:
  - 100-75%: Green (secure)
  - 74-50%: Yellow (contested)
  - 49-25%: Orange (vulnerable)
  - 24-0%: Red (critical)
- Tooltip on hover explaining allegiance mechanics

**Anti-Snipe Protection Indicator**
- Shield icon next to Loyalty display when active
- Countdown timer showing remaining protection time
- Tooltip explaining anti-snipe mechanics
- Visual effect (glow/pulse) when active

**Capture Cooldown Indicator**
- Lock icon displayed when cooldown is active
- Countdown timer showing remaining cooldown
- Tooltip explaining cooldown mechanics
- Grayed-out effect on conquest-related actions

**Control Link Indicator**
- Displayed when village is under active conquest attempt
- Shows attacker name and control progress
- Progress bar showing control meter (0-100)
- Uptime timer when control reaches 100
- Visual alert (pulsing border) when in uptime phase

**Hall of Banners Building Page**
- Medieval building interior background
- Building level and description at top
- Envoy training interface:
  - Envoy unit card with stats and icon
  - Quantity selector (1-10 Envoys)
  - Resource cost display (wood, clay, iron)
  - Influence crest cost display with icon
  - "Train Envoys" button (large, medieval styled)
- Training queue section:
  - List of queued Envoy training
  - Countdown timers for each queue item
  - Cancel buttons for each item
- Influence crest inventory:
  - Large icon display
  - Current quantity
  - "Mint Crests" button linking to minting interface

**Conquest Report Display**
- Styled as medieval scroll/parchment
- Header: "CONQUEST REPORT"
- Sections:
  - Battle outcome (victory/defeat)
  - Surviving Envoys count with icon
  - Allegiance/control change (before → after)
  - Visual progress bar showing change
  - Active modifiers list (wall reduction, tech bonuses, etc.)
  - Reason codes (if blocked) with explanations
  - Timestamp and coordinates
- Decorative scroll borders and wax seal graphic

### Color Palette

**Primary Colors**
- Parchment background: #F4E8D0
- Dark brown borders: #3E2723
- Gold accents: #D4AF37
- Dark green: #2E5C3E

**Status Colors**
- Success/Secure: #4CAF50
- Warning/Contested: #FFC107
- Danger/Critical: #F44336
- Info: #2196F3

**Text Colors**
- Primary text: #2C1810
- Secondary text: #5D4E37
- Disabled text: #9E9E9E

### Typography

**Fonts**
- Headers: Medieval/Gothic style font (e.g., "Cinzel", "Uncial Antiqua")
- Body text: Readable serif font (e.g., "Crimson Text", "Lora")
- Numbers/Stats: Monospace font for alignment (e.g., "Courier New")

**Font Sizes**
- Page title: 32px
- Section headers: 24px
- Building/unit names: 18px
- Body text: 14px
- Small text/labels: 12px

### Responsive Design

**Desktop (1200px+)**
- Full grid layout with all panels visible
- 2-column building grid
- Side-by-side resource and status panels

**Tablet (768px - 1199px)**
- Stacked panels
- 2-column building grid maintained
- Condensed navigation

**Mobile (< 768px)**
- Single column layout
- Collapsible sections
- Simplified building cards
- Touch-optimized buttons

### Accessibility

**ARIA Labels**
- All interactive elements have descriptive labels
- Status indicators include text alternatives
- Progress bars include current/max values

**Keyboard Navigation**
- Tab order follows logical flow
- All actions accessible via keyboard
- Focus indicators clearly visible

**Color Contrast**
- All text meets WCAG AA standards (4.5:1 minimum)
- Status colors supplemented with icons/text
- High contrast mode support

## Testing Strategy

### Unit Testing

Unit tests will cover:
- Individual calculation functions (drop, regen, floor enforcement)
- Prerequisite validation logic
- Error code generation
- Configuration parsing and application
- Edge cases (boundary values, null inputs, invalid states)

### Property-Based Testing

The system will use **PHPUnit with Eris** for property-based testing. Each correctness property will be implemented as a property-based test running a minimum of 100 iterations.

Property tests will:
- Generate random valid game states (villages, players, configurations)
- Generate random conquest attempts with varying parameters
- Verify that properties hold across all generated inputs
- Use shrinking to find minimal failing cases when properties are violated

Each property-based test will be tagged with a comment explicitly referencing the correctness property:
```php
/**
 * Feature: village-conquest-system, Property 1: Prerequisite enforcement
 */
public function testPrerequisiteEnforcement() { ... }
```

### Integration Testing

Integration tests will verify:
- End-to-end conquest flows (training → attack → capture)
- Multi-wave conquest trains
- Concurrent attacks on the same village
- Post-capture state transitions
- Report generation with all data fields

### Load Testing

Load tests will simulate:
- 1000+ waves per tick to verify resolver performance
- Concurrent regeneration ticks across many villages
- High-volume training and minting operations
- Report generation at scale

Performance targets:
- p95 conquest resolution latency < 100ms
- p95 regeneration tick latency < 50ms
- No race conditions under concurrent load

### Test Data Generation

Property tests will use generators for:
- **Villages**: Random allegiance [0-100], wall levels [0-20], coordinates
- **Players**: Random points, village counts, protection status
- **Envoys**: Random survival counts [0-10], attack configurations
- **Time Deltas**: Random elapsed seconds for regeneration testing
- **Configurations**: Random world configs within valid ranges

## Implementation Notes

### Performance Considerations

1. **Batch Regeneration**: Process regeneration ticks in batches to reduce database load
2. **Indexed Queries**: Add indexes on `village_id`, `last_allegiance_update`, `capture_cooldown_until`
3. **Cached Configuration**: Cache world configuration to avoid repeated database reads
4. **Async Reporting**: Generate detailed reports asynchronously to avoid blocking conquest resolution

### Security Considerations

1. **Input Validation**: Validate all user inputs before processing
2. **Rate Limiting**: Enforce rate limits on training and attack commands
3. **Audit Logging**: Log all conquest attempts for abuse detection
4. **Transaction Isolation**: Use appropriate isolation levels to prevent race conditions
5. **Privilege Checks**: Verify player permissions before allowing actions

### Scalability Considerations

1. **Horizontal Scaling**: Design for multi-server deployment with shared database
2. **Event Sourcing**: Consider event sourcing for conquest history and audit trail
3. **Caching Strategy**: Cache frequently accessed data (world config, player stats)
4. **Database Partitioning**: Partition conquest logs by world_id for better performance

### Monitoring and Observability

1. **Metrics**: Track conquest attempts, success rates, error rates by type
2. **Latency Tracking**: Monitor p50/p95/p99 latencies for all operations
3. **Alerting**: Alert on unusual patterns (spike in errors, performance degradation)
4. **Dashboards**: Per-world dashboards showing conquest activity and health metrics
