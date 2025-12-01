<?php
declare(strict_types=1);

/**
 * Map Grid System Test
 * Tests spawn placement, distance calculation, and barbarian seeding
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/MapGridManager.php';
require_once __DIR__ . '/../lib/managers/SpawnManager.php';

echo "=== Map Grid System Test ===\n\n";

$mapManager = new MapGridManager($conn);
$spawnManager = new SpawnManager($conn);
$worldId = 1;

// Test 1: Distance calculation
echo "Test 1: Distance Calculation\n";
$pointA = ['x' => 500, 'y' => 500];
$pointB = ['x' => 520, 'y' => 510];
$dist = $mapManager->distance($pointA, $pointB);
echo "Distance from (500,500) to (520,510): " . round($dist, 2) . " fields\n";
$expected = sqrt(20*20 + 10*10);
echo "Expected: " . round($expected, 2) . " fields\n";
echo $dist === $expected ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 2: Bounds checking
echo "Test 2: Bounds Checking\n";
$tests = [
    [0, 0, true],
    [500, 500, true],
    [999, 999, true],
    [-1, 0, false],
    [0, -1, false],
    [1000, 500, false],
    [500, 1000, false]
];
$passed = 0;
foreach ($tests as [$x, $y, $expected]) {
    $result = $mapManager->inBounds($x, $y);
    if ($result === $expected) {
        $passed++;
    } else {
        echo "✗ FAIL: inBounds($x, $y) expected " . ($expected ? 'true' : 'false') . "\n";
    }
}
echo "$passed/" . count($tests) . " tests passed\n\n";

// Test 3: Chunk coordinates
echo "Test 3: Chunk Coordinates\n";
$chunkTests = [
    [0, 0, 0, 0],
    [10, 10, 0, 0],
    [19, 19, 0, 0],
    [20, 20, 20, 20],
    [25, 35, 20, 20],
    [500, 500, 500, 500]
];
$passed = 0;
foreach ($chunkTests as [$x, $y, $expectedX, $expectedY]) {
    $chunk = $mapManager->getChunkCoords($x, $y);
    if ($chunk['x'] === $expectedX && $chunk['y'] === $expectedY) {
        $passed++;
    } else {
        echo "✗ FAIL: getChunkCoords($x, $y) expected ($expectedX, $expectedY), got ({$chunk['x']}, {$chunk['y']})\n";
    }
}
echo "$passed/" . count($chunkTests) . " tests passed\n\n";

// Test 4: Spawn coordinate generation
echo "Test 4: Spawn Coordinate Generation\n";
$playerCount = $mapManager->countVillages($worldId);
echo "Current player count: $playerCount\n";

$coords = $mapManager->pickSpawnCoord($worldId);
if ($coords !== null) {
    echo "✓ Generated spawn coordinate: ({$coords['x']}, {$coords['y']})\n";
    $centerDist = $mapManager->distance(['x' => 500, 'y' => 500], $coords);
    echo "Distance from center: " . round($centerDist, 2) . " fields\n";
    
    // Verify it's empty
    $isEmpty = $mapManager->isEmpty($coords['x'], $coords['y'], $worldId);
    echo $isEmpty ? "✓ Coordinate is empty\n" : "✗ Coordinate is occupied\n";
} else {
    echo "✗ FAIL: Could not generate spawn coordinate\n";
}
echo "\n";

// Test 5: Spawn statistics
echo "Test 5: Spawn Statistics\n";
$stats = $spawnManager->getSpawnStats($worldId);
echo "Player count: {$stats['player_count']}\n";
echo "Spawn radius: {$stats['spawn_radius']} fields\n";
echo "Max radius: {$stats['max_radius']} fields\n";
echo "Avg spawn density: " . round($stats['avg_spawn_density'], 4) . "\n";
echo "Center: ({$stats['center']['x']}, {$stats['center']['y']})\n\n";

// Test 6: Chunk density
echo "Test 6: Chunk Density Calculation\n";
$testChunks = [
    [0, 0],
    [500, 500],
    [980, 980]
];
foreach ($testChunks as [$x, $y]) {
    $density = $mapManager->getChunkDensity($x, $y, $worldId);
    $barbCount = $mapManager->countBarbsInChunk($x, $y, $worldId);
    echo "Chunk ($x, $y): density=" . round($density, 4) . ", barbarians=$barbCount\n";
}
echo "\n";

// Test 7: Small barbarian seeding test (single chunk)
echo "Test 7: Barbarian Seeding (Single Chunk)\n";
$testChunkX = 900;
$testChunkY = 900;
$beforeCount = $mapManager->countBarbsInChunk($testChunkX, $testChunkY, $worldId);
echo "Barbarians before: $beforeCount\n";

// Seed just this chunk manually
$target = intval(floor(20 * 20 * 0.08));
$placed = 0;
for ($i = 0; $i < $target && $placed < 10; $i++) {
    $rx = $testChunkX + mt_rand(0, 19);
    $ry = $testChunkY + mt_rand(0, 19);
    if ($mapManager->isEmpty($rx, $ry, $worldId)) {
        if ($mapManager->placeBarbarian($rx, $ry, $worldId)) {
            $placed++;
        }
    }
}

$afterCount = $mapManager->countBarbsInChunk($testChunkX, $testChunkY, $worldId);
echo "Barbarians after: $afterCount\n";
echo "Placed: $placed\n";
echo $afterCount > $beforeCount ? "✓ PASS\n" : "✗ FAIL\n";
echo "\n";

echo "=== Test Complete ===\n";
