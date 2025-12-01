# Population System - Quick Reference

## Installation

```bash
php migrations/add_population_columns.php
```

## Basic Usage

```php
require_once 'lib/managers/PopulationManager.php';
$popManager = new PopulationManager($conn);
```

## Common Operations

### Get Population State

```php
$state = $popManager->getPopulationState($villageId);
// Returns: ['used' => 150, 'cap' => 240, 'available' => 90]
```

### Check Before Building

```php
$check = $popManager->canAffordBuildingPopulation($villageId, 'barracks', 5);
if (!$check['success']) {
    echo $check['message'];
    return;
}
```

### Check Before Recruiting

```php
$check = $popManager->canAffordUnitPopulation($villageId, 'spear', 10);
if (!$check['success']) {
    echo $check['message'];
    return;
}
```

### Update After Farm Upgrade

```php
$update = $popManager->updateFarmCapacity($villageId);
echo "New capacity: {$update['new_cap']}";
```

### Sanity Check

```php
$sanity = $popManager->sanityCheck($villageId);
if ($sanity['over_capacity']) {
    echo "WARNING: Over capacity!";
}
```

## Farm Capacity Formula

```
popCap(level) = floor(240 * 1.17^(level-1))
```

| Level | Capacity |
|-------|----------|
| 1     | 240      |
| 5     | 313      |
| 10    | 518      |
| 15    | 855      |
| 20    | 1,434    |
| 25    | 2,407    |
| 30    | 3,968    |

## Population Sources

1. **Buildings**: Each building consumes population (defined in `building_types.population_cost`)
2. **Troops**: Own units (defined in `unit_types.population`)
3. **Support**: Allied troops stationed in village (tracked in `support_units` table)

## Integration Points

### BuildingManager

```php
// In canUpgradeBuilding()
$popCheck = $popManager->canAffordBuildingPopulation($villageId, $internalName, $nextLevel);
if (!$popCheck['success']) {
    return $popCheck;
}
```

### UnitManager

```php
// In queueRecruitment()
$popCheck = $popManager->canAffordUnitPopulation($villageId, $unitType, $quantity);
if (!$popCheck['success']) {
    return ['success' => false, 'message' => $popCheck['message']];
}
```

### BattleManager

```php
// When support arrives
$db->execute("
    INSERT INTO support_units (stationed_village_id, owner_village_id, unit_type, quantity)
    VALUES (?, ?, ?, ?)
", [$targetVillageId, $sourceVillageId, $unitType, $quantity]);

// When support leaves
$db->execute("
    UPDATE support_units SET quantity = quantity - ?
    WHERE stationed_village_id = ? AND owner_village_id = ? AND unit_type = ?
", [$quantity, $stationedVillageId, $ownerVillageId, $unitType]);
```

### VillageManager

```php
// After farm upgrade completes
if ($completedBuilding['internal_name'] === 'farm') {
    $popManager->updateFarmCapacity($villageId);
}
```

## Error Messages

- `"Not enough population. Required: X, Available: Y (Used: Z/Cap)"`
- `"Unknown building type."`
- `"Unknown unit type."`

## Database Schema

### building_types
- Added: `population_cost INTEGER NOT NULL DEFAULT 0`

### support_units (NEW)
```sql
CREATE TABLE support_units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    stationed_village_id INTEGER NOT NULL,
    owner_village_id INTEGER NOT NULL,
    unit_type TEXT NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 0,
    arrived_at INTEGER NOT NULL,
    UNIQUE(stationed_village_id, owner_village_id, unit_type)
);
```

## Testing

```bash
php tests/PopulationManagerTest.php
```

## Examples

See `examples/population_integration_example.php` for complete integration examples.

## Documentation

See `docs/population-system.md` for detailed documentation.
