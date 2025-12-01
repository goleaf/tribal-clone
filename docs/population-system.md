# Population System Documentation

## Overview

The population system manages village capacity and consumption based on:
- **Farm level**: Determines population capacity
- **Buildings**: Each building consumes population
- **Troops**: Own units consume population
- **Support**: Allied troops stationed in the village consume population

## Formula

Population capacity from farm level:
```
popCap(level) = floor(240 * 1.17^(level-1))
```

Examples:
- Level 1: 240 population
- Level 5: 313 population
- Level 10: 518 population
- Level 20: 1,434 population
- Level 30: 3,968 population

## Installation

1. Run the migration to add required columns:
```bash
php migrations/add_population_columns.php
```

2. The migration adds:
   - `population_cost` column to `building_types`
   - `support_units` table for tracking allied troops
   - Default population costs for all buildings

## Usage

### Basic Population Check

```php
require_once 'lib/managers/PopulationManager.php';

$popManager = new PopulationManager($conn);

// Get current population state
$state = $popManager->getPopulationState($villageId);
// Returns: ['used' => 150, 'cap' => 240, 'available' => 90]

echo "Population: {$state['used']}/{$state['cap']}";
```

### Before Building Construction

```php
// Check if village has enough population for building upgrade
$check = $popManager->canAffordBuildingPopulation($villageId, 'barracks', 5);

if (!$check['success']) {
    echo $check['message']; // "Not enough population. Required: 3, Available: 2 (Used: 238/240)"
    return;
}

// Proceed with construction...
```

### Before Unit Recruitment

```php
// Check if village has enough population for unit training
$check = $popManager->canAffordUnitPopulation($villageId, 'spear', 10);

if (!$check['success']) {
    echo $check['message']; // "Not enough population. Required: 10, Available: 5 (Used: 235/240)"
    return;
}

// Proceed with recruitment...
// Population is reserved immediately to prevent overspend
```

### After Farm Upgrade

```php
// Update farm capacity after farm level changes
$update = $popManager->updateFarmCapacity($villageId);

echo "Farm capacity updated: {$update['old_cap']} → {$update['new_cap']}";
echo "Available population: {$update['available']}";
```

### Periodic Sanity Check

```php
// Recompute population from authoritative sources (prevents drift)
$sanity = $popManager->sanityCheck($villageId);

echo "Buildings: {$sanity['buildings']}";
echo "Troops: {$sanity['troops']}";
echo "Support: {$sanity['support']}";
echo "Total: {$sanity['total']}/{$sanity['cap']}";

if ($sanity['over_capacity']) {
    echo "WARNING: Village is over capacity!";
}
```

## Integration Points

### 1. Building Construction

**When**: Before queuing a building upgrade
**Action**: Check population availability

```php
// In BuildingManager::canUpgradeBuilding()
$popManager = new PopulationManager($this->conn);
$popCheck = $popManager->canAffordBuildingPopulation($villageId, $internalName, $nextLevel);

if (!$popCheck['success']) {
    return $popCheck; // Reject upgrade
}
```

**When**: After building completion
**Action**: Population is automatically consumed (building level increased)

### 2. Unit Recruitment

**When**: Before queuing unit training
**Action**: Reserve population immediately

```php
// In UnitManager::queueRecruitment()
$popManager = new PopulationManager($this->conn);
$popCheck = $popManager->canAffordUnitPopulation($villageId, $unitType, $quantity);

if (!$popCheck['success']) {
    return ['success' => false, 'message' => $popCheck['message']];
}

// Add to recruitment queue
// Population is now reserved (counted in getTroopPopulation via queue)
```

**When**: Unit training completes
**Action**: Units added to village, population already reserved

**When**: Unit dies or is lost
**Action**: Population automatically freed (unit removed from database)

### 3. Support Troops

**When**: Allied troops arrive at village
**Action**: Add to support_units table

```php
// In BattleManager::processArrival() for support commands
$db->execute("
    INSERT INTO support_units (stationed_village_id, owner_village_id, unit_type, quantity)
    VALUES (?, ?, ?, ?)
    ON CONFLICT(stationed_village_id, owner_village_id, unit_type)
    DO UPDATE SET quantity = quantity + excluded.quantity
", [$targetVillageId, $sourceVillageId, $unitType, $quantity]);
```

**When**: Support troops leave
**Action**: Remove from support_units table

```php
// When support returns home
$db->execute("
    UPDATE support_units
    SET quantity = quantity - ?
    WHERE stationed_village_id = ? AND owner_village_id = ? AND unit_type = ?
", [$quantity, $stationedVillageId, $ownerVillageId, $unitType]);

// Clean up zero-quantity entries
$db->execute("DELETE FROM support_units WHERE quantity <= 0");
```

### 4. Farm Level Changes

**When**: Farm upgrade completes
**Action**: Update population capacity

```php
// In VillageManager::processBuildingQueue()
if ($completedBuilding['internal_name'] === 'farm') {
    $popManager = new PopulationManager($this->conn);
    $update = $popManager->updateFarmCapacity($villageId);
    
    // Optionally notify player of new capacity
    $message = "Farm upgraded! Population capacity: {$update['new_cap']}";
}
```

## Enforcement Examples

### Reject Building Upgrade

```php
// Before queuing build
$popCheck = $popManager->canAffordBuildingPopulation($villageId, 'barracks', $nextLevel);

if (!$popCheck['success']) {
    return [
        'success' => false,
        'message' => $popCheck['message']
    ];
}
```

### Reject Unit Training

```php
// Before training units
$popCheck = $popManager->canAffordUnitPopulation($villageId, 'spear', $count);

if (!$popCheck['success']) {
    return [
        'success' => false,
        'message' => $popCheck['message']
    ];
}
```

### Display in UI

```php
// In header.php or village overview
$popManager = new PopulationManager($conn);
$popState = $popManager->getPopulationState($villageId);

echo "<div class='population'>";
echo "  <span class='pop-used'>{$popState['used']}</span>";
echo "  <span class='pop-separator'>/</span>";
echo "  <span class='pop-cap'>{$popState['cap']}</span>";
echo "</div>";
```

## Data Storage

### Villages Table
- No changes needed (farm level stored in buildings table)

### Buildings Table
- Existing structure works (building levels tracked here)

### Building Types Table
- Added: `population_cost INTEGER NOT NULL DEFAULT 0`

### Units Table
- Existing structure works (unit quantities tracked here)

### Unit Types Table
- Existing: `population INTEGER NOT NULL DEFAULT 1`

### Support Units Table (NEW)
```sql
CREATE TABLE support_units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    stationed_village_id INTEGER NOT NULL,
    owner_village_id INTEGER NOT NULL,
    unit_type TEXT NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 0,
    arrived_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    UNIQUE(stationed_village_id, owner_village_id, unit_type)
);
```

## Transaction Safety

All population mutations should be wrapped in transactions to avoid race conditions:

```php
$conn->begin_transaction();

try {
    // Check population
    $popCheck = $popManager->canAffordUnitPopulation($villageId, $unitType, $quantity);
    if (!$popCheck['success']) {
        throw new Exception($popCheck['message']);
    }
    
    // Deduct resources
    $resourceManager->deductResources($villageId, $costs);
    
    // Queue recruitment
    $unitManager->addToQueue($villageId, $unitType, $quantity);
    
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    return ['success' => false, 'message' => $e->getMessage()];
}
```

## Testing

### Test Population Calculation

```php
$popManager = new PopulationManager($conn);

// Test farm capacity formula
assert($popManager->calculateFarmCapacity(1) === 240);
assert($popManager->calculateFarmCapacity(5) === 313);
assert($popManager->calculateFarmCapacity(10) === 518);
assert($popManager->calculateFarmCapacity(20) === 1434);
assert($popManager->calculateFarmCapacity(30) === 3968);
```

### Test Population Tracking

```php
// Create test village with farm level 5
$villageId = createTestVillage();
setFarmLevel($villageId, 5);

$state = $popManager->getPopulationState($villageId);
assert($state['cap'] === 313);
assert($state['used'] === 0);

// Add some buildings
addBuilding($villageId, 'barracks', 3); // 3 pop
addBuilding($villageId, 'stable', 2);   // 4 pop

$state = $popManager->getPopulationState($villageId);
assert($state['used'] === 7);

// Add some troops
addUnits($villageId, 'spear', 10); // 10 pop

$state = $popManager->getPopulationState($villageId);
assert($state['used'] === 17);
assert($state['available'] === 296);
```

## Performance Considerations

- Population calculations are lightweight (simple queries)
- Use caching for frequently accessed villages
- Sanity checks should run periodically (e.g., daily cron)
- Index support_units table on stationed_village_id for fast lookups

## Future Enhancements

1. **Population scaling**: Buildings could consume more population at higher levels
2. **Population bonuses**: Special buildings or research could increase capacity
3. **Population events**: Plagues, celebrations affecting capacity
4. **Population growth**: Natural population growth over time
5. **Detailed breakdown**: UI showing population breakdown by category
