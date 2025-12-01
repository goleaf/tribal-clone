# Map Grid System - Quick Reference

## Key Constants

```php
MAP_SIZE = 1000        // Grid dimensions
CHUNK_SIZE = 20        // Chunk size for storage
BASE_RADIUS = 80       // Initial spawn radius
GROWTH_PER_K = 20      // Radius growth per 1000 players
BARBARIAN_DENSITY = 0.08  // 8% of tiles per chunk
```

## Common Operations

### Calculate Distance
```php
$distance = $mapManager->distance(
    ['x' => $x1, 'y' => $y1],
    ['x' => $x2, 'y' => $y2]
);
```

### Check if Tile is Empty
```php
$isEmpty = $mapManager->isEmpty($x, $y, $worldId);
```

### Get Chunk for Coordinate
```php
$chunk = $mapManager->getChunkCoords($x, $y);
// Returns: ['x' => chunkX, 'y' => chunkY]
```

### Create New Player Village
```php
$spawnManager = new SpawnManager($conn);
$village = $spawnManager->createStarterVillage($userId, $worldId);
```

### Seed Barbarians
```php
// Full map seeding
$totalPlaced = $mapManager->seedBarbarians($worldId, 0.08);

// CLI command
php jobs/seed_barbarians.php [world_id] [density]
```

### Get Spawn Statistics
```php
$stats = $spawnManager->getSpawnStats($worldId);
// Returns: player_count, spawn_radius, max_radius, avg_spawn_density, center
```

### Update Chunk Cache
```php
$chunk = $mapManager->getChunkCoords($x, $y);
$mapManager->updateChunkMetadata($chunk['x'], $chunk['y'], $worldId);
```

## Spawn Ring Formula

```
radius = 80 + floor(playerCount / 1000) * 20
maxRadius = min(radius + 40, 500)
```

| Players | Radius Range |
|---------|--------------|
| 0-999   | 80-120       |
| 1000-1999 | 100-140    |
| 2000-2999 | 120-160    |
| 5000+   | 180-220      |

## Barbarian Density

Per chunk (20×20 = 400 tiles):
- 8% density = 32 barbarian villages
- 10% density = 40 barbarian villages
- 5% density = 20 barbarian villages

## TypeScript Usage

```typescript
import { distance, pickSpawnCoord, seedBarbarians } from './src/map/MapGrid';

// Distance
const dist = distance({ x: 500, y: 500 }, { x: 520, y: 510 });

// Spawn
const tx = new MapGridTransaction(db);
const coord = await pickSpawnCoord(tx);

// Seed
const placed = await seedBarbarians(tx, 0.08);
```

## Testing

```bash
# Run test suite
php tests/map_grid_test.php
```

## Database Queries

### Find Empty Tiles Near Coordinate
```sql
SELECT x_coord, y_coord 
FROM villages 
WHERE x_coord BETWEEN ? AND ? 
  AND y_coord BETWEEN ? AND ?
  AND user_id IS NULL;
```

### Count Villages in Chunk
```sql
SELECT COUNT(*) 
FROM villages 
WHERE x_coord >= ? AND x_coord < ?
  AND y_coord >= ? AND y_coord < ?
  AND world_id = ?;
```

### Get Barbarian Villages
```sql
SELECT * FROM villages 
WHERE (user_id IS NULL OR user_id = -1)
  AND world_id = ?;
```

## Performance Tips

1. **Use chunk caching** for frequent density queries
2. **Batch operations** when seeding barbarians
3. **Index coordinates** with `(x_coord, y_coord, world_id)`
4. **Pre-generate spawns** during low-traffic periods
5. **Update chunk metadata** after village creation/deletion

## Integration Points

### User Registration
```php
$village = $spawnManager->createStarterVillage($userId);
$_SESSION['village_id'] = $village['id'];
```

### Periodic Maintenance
```php
// Cron job: Daily at 3 AM
$mapManager->seedBarbarians($worldId, 0.08);
```

### Map Display
```php
// Already integrated in map/map_data.php
// Uses x_coord, y_coord for village positioning
```

## Troubleshooting

### No spawn slots found
- Check if map is too crowded
- Increase max radius
- Lower density requirements

### Barbarian seeding slow
- Enable chunk caching
- Reduce density
- Run during off-peak hours

### Uneven distribution
- Re-run seeding periodically
- Check chunk metadata accuracy
- Verify random number generation
