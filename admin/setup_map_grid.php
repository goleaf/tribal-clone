<?php
declare(strict_types=1);

/**
 * Map Grid Setup Script
 * Initializes database tables and seeds initial barbarian villages
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/MapGridManager.php';

// Admin check
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die("Access denied. Admin privileges required.\n");
}

echo "=== Map Grid Setup ===\n\n";

$mapManager = new MapGridManager($conn);

// Step 1: Create map_chunks table
echo "Step 1: Creating map_chunks table...\n";
try {
    $mapManager->initializeChunksTable();
    echo "✓ map_chunks table created\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 2: Add indexes to villages table if missing
echo "Step 2: Checking villages table indexes...\n";
try {
    // Check if coordinate index exists
    $result = $conn->query("SHOW INDEX FROM villages WHERE Key_name = 'idx_coords'");
    if ($result->num_rows === 0) {
        $conn->query("CREATE UNIQUE INDEX idx_coords ON villages(x_coord, y_coord, world_id)");
        echo "✓ Added idx_coords index\n";
    } else {
        echo "✓ idx_coords index already exists\n";
    }

    // Check if world index exists
    $result = $conn->query("SHOW INDEX FROM villages WHERE Key_name = 'idx_world'");
    if ($result->num_rows === 0) {
        $conn->query("CREATE INDEX idx_world ON villages(world_id)");
        echo "✓ Added idx_world index\n";
    } else {
        echo "✓ idx_world index already exists\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 3: Seed barbarian villages
echo "Step 3: Seeding barbarian villages...\n";
$worldId = 1;
$density = 0.08;

echo "World ID: $worldId\n";
echo "Density: " . ($density * 100) . "%\n";
echo "This may take a few minutes...\n\n";

$startTime = microtime(true);
try {
    $totalPlaced = $mapManager->seedBarbarians($worldId, $density);
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "✓ Seeding complete!\n";
    echo "Total barbarian villages placed: $totalPlaced\n";
    echo "Time taken: {$duration} seconds\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 4: Update chunk metadata
echo "Step 4: Updating chunk metadata...\n";
$chunksUpdated = 0;
try {
    for ($x = 0; $x < 1000; $x += 20) {
        for ($y = 0; $y < 1000; $y += 20) {
            $mapManager->updateChunkMetadata($x, $y, $worldId);
            $chunksUpdated++;
        }
    }
    echo "✓ Updated $chunksUpdated chunks\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 5: Display statistics
echo "Step 5: Map Statistics\n";
try {
    $totalVillages = $conn->query("SELECT COUNT(*) as count FROM villages WHERE world_id = $worldId")->fetch_assoc()['count'];
    $playerVillages = $conn->query("SELECT COUNT(*) as count FROM villages WHERE world_id = $worldId AND user_id IS NOT NULL AND user_id > 0")->fetch_assoc()['count'];
    $barbVillages = $conn->query("SELECT COUNT(*) as count FROM villages WHERE world_id = $worldId AND (user_id IS NULL OR user_id = -1)")->fetch_assoc()['count'];
    
    echo "Total villages: $totalVillages\n";
    echo "Player villages: $playerVillages\n";
    echo "Barbarian villages: $barbVillages\n";
    
    if ($totalVillages > 0) {
        $barbPercent = round(($barbVillages / $totalVillages) * 100, 2);
        echo "Barbarian percentage: {$barbPercent}%\n";
    }
    
    // Sample chunk densities
    echo "\nSample chunk densities:\n";
    $sampleChunks = [[0, 0], [500, 500], [980, 980]];
    foreach ($sampleChunks as [$x, $y]) {
        $density = $mapManager->getChunkDensity($x, $y, $worldId);
        $barbCount = $mapManager->countBarbsInChunk($x, $y, $worldId);
        echo "  Chunk ($x, $y): density=" . round($density, 4) . ", barbarians=$barbCount\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Setup Complete ===\n";
echo "\nNext steps:\n";
echo "1. Test spawn placement: php tests/map_grid_test.php\n";
echo "2. View map: Visit map/map.php in your browser\n";
echo "3. Create test player: Register a new account\n";
