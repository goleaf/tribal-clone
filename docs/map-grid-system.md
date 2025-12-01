# Map Grid Generation System

## Overview

The map grid system implements a 1000x1000 square grid with chunked storage, clustered spawn placement, and barbarian village seeding.

## Features

### 1. Grid Model
- **Size**: 1000x1000 configurable square grid
- **Chunking**: 20x20 chunks for efficient storage and caching
- **Indexing**: Unique index on (x, y) coordinates
- **Types**: `empty`, `player`, `barbarian`

### 2. Distance Calculation
Uses Euclidean distance formula:
```
distance = sqrt((x2 - x1)² + (y2 - y1)²)
```

### 3. Clustered Spawn Placement
New players spawn in expanding rings:
- **Base radius**: 80 fields from center (500, 500)
- **Growth**: +20 fields per 1000 players
- **Max radius**: Base + 40 fields (capped at map size / 2)
- **Density bias**: Prefers lower-density chunks
- **Snap radius**: 5 fields to find nearest empty tile

### 4. Barbarian Villages
- **Density**: 8% per chunk (configurable)
- **Distribution**: Seeded per chunk with density cap
- **Respawn**: Can be re-run periodically to fill gaps
- **Avoidance**: Won't block spawn slots

## Usage

### PHP Implementation

#### Initialize Map Grid Manager
```php
require_once 'lib/managers/MapGridManager.php';

$mapManager = new MapGridManager($conn);
```

#### Seed Barbarian Villages
```php
// Seed with default 8% density
$totalPlaced = $mapManager->seedBarbarians($worldId = 1, $density = 0.08);
echo "Placed {$totalPlaced} barbarian villages\n";
```

#### Create New Player Village
```php
require_once 'lib/managers/SpawnManager.php';

$spawnManager = new SpawnManager($conn);
$village = $spawnManager->createStarterVillage($userId, $worldId = 1);

if ($village) {
    echo "Village created at ({$village['x']}, {$village['y']})\n";
}
```

#### Calculate Distance
```php
$distance = $mapManager->distance(
    ['x' => 500, 'y' => 500],
    ['x' => 520, 'y' => 510]
);
echo "Distance: {$distance} fields\n";
```

#### Get Spawn Statistics
```php
$stats = $spawnManager->getSpawnStats($worldId = 1);
print_r($stats);
// Output:
// [
//     'player_count' => 1500,
//     'spawn_radius' => 110,
//     'max_radius' => 150,
//     'avg_spawn_density' => 0.0234,
//     'center' => ['x' => 500, 'y' => 500]
// ]
```

### TypeScript Implementation

```typescript
import { 
  MapGridTransaction, 
  pickSpawnCoord, 
  seedBarbarians,
  distance 
} from './src/map/MapGrid';

// Calculate distance
const dist = distance({ x: 500, y: 500 }, { x: 520, y: 510 });
console.log(`Distance: ${dist} fields`);

// Pick spawn coordinate
const tx = new MapGridTransaction(db);
const coord = await pickSpawnCoord(tx);
console.log(`Spawn at (${coord.x}, ${coord.y})`);

// Seed barbarians
const placed = await seedBarbarians(tx, 0.08);
console.log(`Placed ${placed} barbarian villages`);
```

### CLI Commands

#### Seed Barbarians
```bash
# Default world (1) with 8% density
php jobs/seed_barbarians.php

# Specific world with custom density
php jobs/seed_barbarians.php 2 0.10
```

## Database Schema

### Villages Table
```sql
CREATE TABLE villages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    x_coord INT NOT NULL,
    y_coord INT NOT NULL,
    user_id INT NULL,
    world_id INT NOT NULL DEFAULT 1,
    points INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_coords (x_coord, y_coord, world_id),
    INDEX idx_user (user_id),
    INDEX idx_world (world_id)
);
```

### Map Chunks Table (Optional Cache)
```sql
CREATE TABLE map_chunks (
    chunk_x INT NOT NULL,
    chunk_y INT NOT NULL,
    world_id INT NOT NULL DEFAULT 1,
    village_count INT NOT NULL DEFAULT 0,
    barbarian_count INT NOT NULL DEFAULT 0,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (chunk_x, chunk_y, world_id),
    INDEX idx_world (world_id)
);
```

## Configuration

Add to `config/config.php`:

```php
// Map configuration
define('MAP_SIZE', 1000);
define('CHUNK_SIZE', 20);
define('SPAWN_BASE_RADIUS', 80);
define('SPAWN_GROWTH_PER_K', 20);
define('BARBARIAN_DENSITY', 0.08);

// Starting resources
define('STARTING_WOOD', 1000);
define('STARTING_CLAY', 1000);
define('STARTING_IRON', 1000);
```

## Performance Optimization

### Chunk Caching
The `map_chunks` table caches village counts per chunk for faster density queries:

```php
// Initialize chunks table
$mapManager->initializeChunksTable();

// Update chunk metadata after changes
$chunk = $mapManager->getChunkCoords($x, $y);
$mapManager->updateChunkMetadata($chunk['x'], $chunk['y'], $worldId);
```

### Batch Operations
When seeding barbarians, operations are batched per chunk to minimize database queries.

## Algorithm Details

### Spawn Ring Expansion
```
radius = BASE_RADIUS + floor(playerCount / 1000) * GROWTH_PER_K
maxRadius = min(radius + 40, MAP_SIZE / 2)
```

Example progression:
- 0-999 players: radius 80-120
- 1000-1999 players: radius 100-140
- 2000-2999 players: radius 120-160
- 5000+ players: radius 180-220

### Snap to Empty Algorithm
Searches in expanding squares from target coordinate:
1. Check target (0,0)
2. Check 1-field radius (8 tiles)
3. Check 2-field radius (16 tiles)
4. Continue up to 5-field radius
5. Return first empty tile found

### Barbarian Density
Per chunk (20x20 = 400 tiles):
- 8% density = 32 barbarian villages per chunk
- Distributed randomly within chunk
- Respects existing villages
- Can be re-seeded to fill gaps from conquests

## Integration Example

```php
// In user registration flow
require_once 'lib/managers/SpawnManager.php';

function createNewPlayer($username, $password) {
    global $conn;
    
    // Create user account
    $userId = createUserAccount($username, $password);
    
    // Create starter village
    $spawnManager = new SpawnManager($conn);
    $village = $spawnManager->createStarterVillage($userId);
    
    if ($village) {
        $_SESSION['village_id'] = $village['id'];
        return $village;
    }
    
    return null;
}
```

## Monitoring

Track spawn health with statistics:

```php
$stats = $spawnManager->getSpawnStats($worldId);

if ($stats['avg_spawn_density'] > 0.5) {
    echo "Warning: Spawn area is getting crowded!\n";
}
```

## Future Enhancements

1. **Protected spawn zones**: Reserve inner ring for tutorials
2. **Continent-based spawning**: Bias toward specific continents
3. **Tribe clustering**: Spawn tribe members near each other
4. **Dynamic density**: Adjust barbarian density based on player activity
5. **Spawn queue**: Pre-generate spawn coordinates for faster registration
