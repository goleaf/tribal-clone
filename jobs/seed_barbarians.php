<?php
declare(strict_types=1);

/**
 * Barbarian Seeding Job
 * Seeds barbarian villages across the map with configurable density
 * Usage: php jobs/seed_barbarians.php [world_id] [density]
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/MapGridManager.php';

$worldId = isset($argv[1]) ? (int)$argv[1] : 1;
$density = isset($argv[2]) ? (float)$argv[2] : 0.08;

echo "Starting barbarian seeding for world {$worldId} with density {$density}...\n";

$mapManager = new MapGridManager($conn);

$startTime = microtime(true);
$totalPlaced = $mapManager->seedBarbarians($worldId, $density);
$endTime = microtime(true);

$duration = round($endTime - $startTime, 2);

echo "Seeding complete!\n";
echo "Total barbarian villages placed: {$totalPlaced}\n";
echo "Time taken: {$duration} seconds\n";

// Update chunk metadata for caching (optional)
echo "Updating chunk metadata...\n";
for ($x = 0; $x < 1000; $x += 20) {
    for ($y = 0; $y < 1000; $y += 20) {
        $mapManager->updateChunkMetadata($x, $y, $worldId);
    }
}
echo "Chunk metadata updated.\n";
