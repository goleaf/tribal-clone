# Resource Production Logic

## Overview

The resource production system implements a balanced formula for generating wood, clay, and iron resources over time, with support for offline gains and warehouse capacity limits.

## Production Formula

Resources are produced per hour using the formula:

```
prod(l) = base * growth^(l-1) * world_speed * building_speed
```

Where:
- `l` = building level (1-30)
- `base` = base production rate (30 for timber/clay, 25 for iron)
- `growth` = growth factor (1.163)
- `world_speed` = world speed multiplier (from worlds table)
- `building_speed` = building speed multiplier (from worlds table)

### Resource-Specific Constants

| Resource | Building | Base Production | Growth Factor |
|----------|----------|----------------|---------------|
| Wood | Sawmill | 30 | 1.163 |
| Clay | Clay Pit | 30 | 1.163 |
| Iron | Iron Mine | 25 | 1.163 |

## Offline Gain Calculation

When a player returns after being offline, resources are calculated based on elapsed time:

```php
$dt_hours = (now - last_tick_at) / 3600
$gained = prod_eff * dt_hours
$new_stock = min(stock + gained, warehouse_cap * 1.02)
```

### Warehouse Capacity

- Base capacity: 1000 resources
- Growth formula: `1000 * 1.229^level`
- **Buffer**: 2% overflow tolerance (warehouse_cap * 1.02) before clamping to display value
- Resources are capped at warehouse capacity to prevent unlimited accumulation

## Implementation

### BuildingConfigManager

The `calculateProduction()` method in `BuildingConfigManager` handles the core production calculation:

```php
public function calculateProduction(string $internalName, int $level): ?float
```

- Applies world speed and building speed multipliers automatically
- Returns production per hour
- Returns null for non-producing buildings

### ResourceManager

The `updateVillageResources()` method in `ResourceManager` handles offline gains:

```php
public function updateVillageResources(array $village): array
```

- Calculates elapsed time since last update
- Applies production rates with multipliers
- Clamps resources to warehouse capacity (with 2% buffer)
- Sends notifications when warehouse is full
- Updates database with new resource values

## Balance Notes

### Early Game (Levels 1-10)
- Base production provides steady early-game income
- Players can upgrade buildings within hours
- Resource scarcity encourages strategic choices

### Mid Game (Levels 11-20)
- Exponential growth becomes noticeable
- Warehouse upgrades become important
- Build times increase to 1-4 hours

### Late Game (Levels 21-30)
- High production rates support large armies
- Warehouse capacity becomes critical
- Build times can exceed 4 hours

### Tuning Parameters

To adjust game pacing:

1. **Faster early game**: Increase `base` values (30 → 40)
2. **Slower late game**: Decrease `growth` factor (1.163 → 1.15)
3. **Overall speed**: Adjust `world_speed` multiplier in worlds table
4. **Building construction**: Adjust `build_speed` multiplier in worlds table

## Configuration

World-specific multipliers are stored in the `worlds` table:

- `world_speed`: General world pace multiplier (default: 1.0)
- `build_speed`: Building construction speed (default: 1.0)
- `troop_speed`: Unit movement speed (default: 1.0)
- `train_speed`: Unit training speed (default: 1.0)
- `research_speed`: Research speed (default: 1.0)

These can be adjusted per-world to create different game experiences (e.g., speed worlds, slow worlds).

## Example Production Rates

At world_speed = 1.0 and build_speed = 1.0:

| Level | Wood/Clay (per hour) | Iron (per hour) |
|-------|---------------------|-----------------|
| 1 | 30 | 25 |
| 5 | 56 | 47 |
| 10 | 105 | 88 |
| 15 | 197 | 164 |
| 20 | 369 | 308 |
| 25 | 692 | 577 |
| 30 | 1,297 | 1,081 |

## Testing

To test production calculations:

```php
// Test production rate calculation
$buildingConfigManager = new BuildingConfigManager($conn);
$rate = $buildingConfigManager->calculateProduction('sawmill', 10);
// Expected: ~105 per hour at world_speed=1.0, build_speed=1.0

// Test offline gains
$resourceManager = new ResourceManager($conn, $buildingManager);
$village = $resourceManager->updateVillageResources($villageData);
// Resources should increase based on elapsed time
```

## Future Enhancements

Potential improvements to consider:

1. **Resource bonuses**: Special buildings or research that boost production
2. **Events**: Temporary production multipliers during events
3. **Premium features**: Resource production boosts for premium accounts
4. **Village specialization**: Bonus production for specific resource types
5. **Trade routes**: Passive resource generation from trade agreements
