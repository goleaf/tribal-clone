# Design Document: Resource System

## Overview

This design document describes the technical architecture for a comprehensive resource and village management system for a browser-based strategy game. The system implements resource production, storage, building construction, troop recruitment, combat resolution, and village conquest mechanics using a minimalist WAP-style interface with server-side rendering.

The design builds upon the existing PHP/SQLite codebase, extending the current `ResourceManager`, `BuildingManager`, and related services while maintaining backward compatibility.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Presentation Layer                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ Village View│  │ Building UI │  │ Combat View │              │
│  │   (PHP)     │  │   (PHP)     │  │   (PHP)     │              │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
└─────────┼────────────────┼────────────────┼─────────────────────┘
          │                │                │
┌─────────▼────────────────▼────────────────▼─────────────────────┐
│                        Service Layer                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ Resource    │  │ Building    │  │ Combat      │              │
│  │ Manager     │  │ Manager     │  │ Engine      │              │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
│         │                │                │                      │
│  ┌──────▼──────┐  ┌──────▼──────┐  ┌──────▼──────┐              │
│  │ Production  │  │ Queue       │  │ Conquest    │              │
│  │ Calculator  │  │ Processor   │  │ Handler     │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
└─────────────────────────────────────────────────────────────────┘
          │                │                │
┌─────────▼────────────────▼────────────────▼─────────────────────┐
│                        Data Layer                                │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    SQLite Database                          ││
│  │  villages | building_types | village_buildings | attacks    ││
│  │  unit_types | village_units | battle_reports | ...          ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

### Request Flow

1. Player requests village overview via HTTP GET
2. PHP controller loads village data from database
3. ResourceManager calculates offline resource gains
4. BuildingManager retrieves building states and queue
5. View renders HTML table with text-only interface
6. Response includes meta-refresh tag for timer updates

## Components and Interfaces

### ResourceManager (Extended)

Handles resource production, storage, and spending.

```php
interface IResourceManager {
    // Get production rates for all resources
    public function getProductionRates(int $villageId): array;
    
    // Update resources based on elapsed time (offline gains)
    public function updateVillageResources(array $village): array;
    
    // Deduct resources for construction/recruitment
    public function spendResources(int $villageId, array $costs): array;
    
    // Format resource display string
    public function formatResourceDisplay(int $amount, float $rate): string;
    
    // Check if village has sufficient resources
    public function hasResources(int $villageId, array $required): bool;
}
```

### BuildingManager (Extended)

Manages building construction, upgrades, and prerequisites.

```php
interface IBuildingManager {
    // Get building level for a village
    public function getBuildingLevel(int $villageId, string $internalName): int;
    
    // Check if upgrade is possible (prerequisites, resources, queue)
    public function canUpgradeBuilding(int $villageId, string $internalName, ?int $userId = null): array;
    
    // Queue a building upgrade
    public function queueUpgrade(int $villageId, string $internalName): array;
    
    // Get upgrade cost for next level
    public function getBuildingUpgradeCost(string $internalName, int $nextLevel): ?array;
    
    // Get upgrade time in seconds
    public function getBuildingUpgradeTime(string $internalName, int $nextLevel, int $hqLevel): ?int;
    
    // Get hourly production for production buildings
    public function getHourlyProduction(string $internalName, int $level): float;
    
    // Get warehouse capacity at level
    public function getWarehouseCapacityByLevel(int $level): int;
}
```

### CombatEngine (New)

Handles battle resolution and report generation.

```php
interface ICombatEngine {
    // Resolve a battle between attacker and defender
    public function resolveBattle(array $attacker, array $defender, array $options): BattleResult;
    
    // Calculate damage for a combat round
    public function calculateRoundDamage(array $attackingUnits, array $defendingUnits, float $wallBonus): array;
    
    // Apply unit type advantages
    public function getTypeAdvantageMultiplier(string $attackerType, string $defenderType): float;
    
    // Generate battle report
    public function generateReport(BattleResult $result): array;
}
```

### ConquestHandler (Extended)

Manages nobleman attacks and village capture.

```php
interface IConquestHandler {
    // Process nobleman attack on village
    public function processNoblemanAttack(int $attackId, int $noblemanCount): array;
    
    // Calculate loyalty reduction (20-35 per nobleman)
    public function calculateLoyaltyDrop(int $noblemanCount): int;
    
    // Transfer village ownership
    public function transferVillage(int $villageId, int $newOwnerId): array;
    
    // Check nobleman training prerequisites
    public function canTrainNobleman(int $villageId): array;
}
```

### QueueProcessor (Extended)

Processes building and unit queues.

```php
interface IQueueProcessor {
    // Process all pending building queue items
    public function processBuildingQueue(int $villageId): array;
    
    // Process all pending unit queue items
    public function processUnitQueue(int $villageId): array;
    
    // Get active queue items for display
    public function getActiveQueueItems(int $villageId): array;
    
    // Format queue item for display
    public function formatQueueDisplay(array $queueItem): string;
}
```

### ViewRenderer (New)

Renders WAP-style HTML interfaces.

```php
interface IViewRenderer {
    // Render village overview table
    public function renderVillageOverview(array $village, array $buildings, array $movements): string;
    
    // Render building list with upgrade options
    public function renderBuildingList(array $buildings, array $village): string;
    
    // Render resource display
    public function renderResourceBar(array $resources, array $rates, int $capacity): string;
    
    // Render navigation header
    public function renderNavigation(): string;
    
    // Render combat report
    public function renderBattleReport(array $report): string;
}
```

## Data Models

### Village

```sql
-- Existing table with key fields
villages (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    world_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    x_coord INTEGER NOT NULL,
    y_coord INTEGER NOT NULL,
    wood INTEGER DEFAULT 0,
    clay INTEGER DEFAULT 0,
    iron INTEGER DEFAULT 0,
    warehouse_capacity INTEGER DEFAULT 1000,
    population INTEGER DEFAULT 100,
    farm_capacity INTEGER DEFAULT 0,
    loyalty INTEGER NOT NULL DEFAULT 100,
    last_resource_update TEXT,
    ...
)
```

### Building Types

```sql
-- Existing table defining 15 core buildings
building_types (
    id INTEGER PRIMARY KEY,
    internal_name TEXT NOT NULL UNIQUE,  -- 'sawmill', 'barracks', etc.
    name TEXT NOT NULL,                   -- Display name
    max_level INTEGER DEFAULT 20,
    cost_wood_initial INTEGER,
    cost_clay_initial INTEGER,
    cost_iron_initial INTEGER,
    cost_factor REAL DEFAULT 1.25,
    production_type TEXT NULL,            -- 'wood', 'clay', 'iron', or NULL
    production_initial INTEGER NULL,
    production_factor REAL NULL,
    ...
)
```

### Unit Types

```sql
-- Existing table defining unit statistics
unit_types (
    id INTEGER PRIMARY KEY,
    internal_name TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    building_type TEXT NOT NULL,          -- Which building trains this unit
    attack INTEGER NOT NULL,
    defense INTEGER NOT NULL,
    defense_cavalry INTEGER NOT NULL,
    defense_archer INTEGER NOT NULL,
    speed INTEGER NOT NULL,
    carry_capacity INTEGER NOT NULL,
    population INTEGER NOT NULL,
    cost_wood INTEGER NOT NULL,
    cost_clay INTEGER NOT NULL,
    cost_iron INTEGER NOT NULL,
    training_time_base INTEGER NOT NULL,
    ...
)
```

### Battle Reports

```sql
-- Existing table for combat reports
battle_reports (
    id INTEGER PRIMARY KEY,
    attack_id INTEGER NOT NULL,
    source_village_id INTEGER NOT NULL,
    target_village_id INTEGER NOT NULL,
    battle_time TEXT NOT NULL,
    attacker_user_id INTEGER NOT NULL,
    defender_user_id INTEGER NOT NULL,
    attacker_won INTEGER NOT NULL,
    report_data TEXT NOT NULL,            -- JSON with full battle details
    ...
)
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

Based on the acceptance criteria analysis, the following correctness properties must be verified:

### Property 1: Resource Display Format
*For any* resource amount and production rate, the formatted display string SHALL match the pattern "[Resource]: [Amount] (+[Rate]/hr)" where Amount and Rate are numeric values.
**Validates: Requirements 1.1**

### Property 2: Production Rate Calculation
*For any* village with production buildings at levels 0-30, the calculated production rate SHALL equal `base * growth^(level-1) * world_speed * building_speed` where base and growth are building-specific constants.
**Validates: Requirements 1.2**

### Property 3: Resource Capacity Enforcement
*For any* village, after any resource update operation, the resource amounts SHALL NOT exceed the warehouse capacity determined by the warehouse building level.
**Validates: Requirements 1.3, 1.4**

### Property 4: Building Upgrade State Transition
*For any* valid building upgrade initiation, the village resources SHALL decrease by exactly the upgrade cost, AND the building queue SHALL contain exactly one new entry for that building.
**Validates: Requirements 2.2**

### Property 5: Headquarters Prerequisite
*For any* village without a Headquarters building (level 0), attempting to upgrade any other building SHALL return a failure result with prerequisite error.
**Validates: Requirements 2.5**

### Property 6: Building Completion Effects
*For any* building upgrade that completes, the building level SHALL increment by exactly 1, AND production rates (if applicable) SHALL update to reflect the new level.
**Validates: Requirements 2.6**

### Property 7: Recruitment Resource Deduction
*For any* valid unit recruitment, the village resources SHALL decrease by exactly (unit_cost × quantity), AND population used SHALL increase by (unit_pop × quantity), AND the unit queue SHALL contain the recruitment entry.
**Validates: Requirements 4.5**

### Property 8: Movement Entry Creation
*For any* troop movement initiation, the attacks table SHALL contain a new entry with valid source_village_id, target_village_id, start_time, and arrival_time where arrival_time > start_time.
**Validates: Requirements 5.1**

### Property 9: Combat Damage Bounds
*For any* combat calculation with attack value A, quantity Q, and random factor R in [0.8, 1.2], the damage dealt SHALL be within the range [A × Q × 0.8, A × Q × 1.2].
**Validates: Requirements 6.2**

### Property 10: Unit Type Advantage Cycle
*For any* combat between unit types, the type advantage multiplier SHALL follow the cycle: cavalry > archers > infantry > spears > cavalry, where ">" indicates a bonus multiplier > 1.0.
**Validates: Requirements 6.3**

### Property 11: Battle Report Completeness
*For any* completed battle, the generated report SHALL contain: initial_forces (both sides), wall_bonus, casualties (per unit type), resources_plundered, and loyalty_damage fields.
**Validates: Requirements 6.4**

### Property 12: Nobleman Loyalty Reduction Bounds
*For any* nobleman attack, the loyalty reduction SHALL be a random value in the range [20, 35] per surviving nobleman.
**Validates: Requirements 7.2**

### Property 13: Village Conquest Preservation
*For any* village conquest (loyalty reaches 0), after ownership transfer: the new owner_id SHALL be the attacker's user_id, AND all building levels SHALL remain unchanged, AND all unit counts SHALL remain unchanged.
**Validates: Requirements 7.3**

### Property 14: Production Building Effects
*For any* production building (Timber Camp, Clay Pit, Iron Mine) at level L > 0, the corresponding resource production rate SHALL be greater than the rate at level L-1.
**Validates: Requirements 8.1**

### Property 15: Hiding Place Protection
*For any* plunder calculation where village has Hiding Place at level L, the protected amount SHALL equal the hiding capacity at level L, AND plundered resources SHALL NOT include any amount up to that capacity.
**Validates: Requirements 9.1, 9.2**

## Error Handling

### Resource Errors
- **ERR_RES**: Insufficient resources for operation
  - Return detailed breakdown of required vs available
  - Do not partially complete operations

### Prerequisite Errors
- **ERR_PREREQ**: Building/research prerequisites not met
  - Return specific missing prerequisite with required level
  - Suggest upgrade path to user

### Queue Errors
- **ERR_QUEUE_FULL**: Building or unit queue at capacity
  - Return current queue count and maximum
  - Display estimated completion time of next slot

### Combat Errors
- **ERR_NO_TROOPS**: Attack initiated with no troops
  - Reject command before creating attack entry
  - Return clear error message

### Conquest Errors
- **ERR_NO_NOBLE**: Conquest attack without nobleman
  - Allow attack but skip loyalty reduction
  - Generate standard battle report

## Testing Strategy

### Property-Based Testing Framework

The system will use **PHPUnit with eris/eris** for property-based testing in PHP. This library provides generators for random test data and shrinking for minimal failing examples.

```php
// Example property test structure
use Eris\Generator;
use Eris\TestTrait;

class ResourceSystemPropertyTest extends PHPUnit\Framework\TestCase {
    use TestTrait;
    
    /**
     * Feature: resource-system, Property 3: Resource Capacity Enforcement
     * Validates: Requirements 1.3, 1.4
     */
    public function testResourceCapacityEnforcement() {
        $this->forAll(
            Generator\choose(0, 30),      // warehouse level
            Generator\choose(0, 100000),  // initial resources
            Generator\choose(0, 10000)    // resources to add
        )->then(function($warehouseLevel, $initial, $toAdd) {
            $capacity = $this->buildingManager->getWarehouseCapacityByLevel($warehouseLevel);
            $village = $this->createVillageWithResources($initial, $warehouseLevel);
            $updated = $this->resourceManager->addResources($village, $toAdd);
            
            $this->assertLessThanOrEqual($capacity, $updated['wood']);
            $this->assertLessThanOrEqual($capacity, $updated['clay']);
            $this->assertLessThanOrEqual($capacity, $updated['iron']);
        });
    }
}
```

### Test Configuration

- Minimum 100 iterations per property test
- Seed logging for reproducibility
- Shrinking enabled for minimal counterexamples

### Unit Tests

Unit tests will cover:
- Edge cases for level 0 and max level buildings
- Boundary conditions for resource calculations
- Error handling paths
- Database transaction integrity

### Integration Tests

Integration tests will verify:
- Full upgrade workflow (check → deduct → queue → complete)
- Combat resolution with actual database state
- Conquest flow from attack to ownership transfer
