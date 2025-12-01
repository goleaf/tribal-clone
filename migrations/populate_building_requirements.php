<?php
/**
 * Migration: Populate building_requirements table with prerequisite chains
 * 
 * This migration defines all building prerequisites including:
 * - Town Hall prerequisites for military buildings
 * - Building-to-building prerequisites
 * - Validates no circular dependencies exist
 * 
 * Requirements: 1.1, 3.2, 3.3, 3.4, 14.2
 */

// Direct SQLite connection for migration
$dbPath = __DIR__ . '/../data/tribal_wars.sqlite';
if (!file_exists($dbPath)) {
    echo "Database file not found: {$dbPath}\n";
    exit(1);
}

$conn = new SQLite3($dbPath);
if (!$conn) {
    echo "Failed to connect to database\n";
    exit(1);
}

echo "Populating building_requirements table...\n";

// Define all prerequisite relationships
// Format: [building_internal_name, required_building_internal_name, required_level]
$requirements = [
    // Core Building Prerequisites
    ['rally_point', 'main_building', 1],
    
    // Military Building Prerequisites (from requirements 3.2, 3.3, 3.4)
    ['barracks', 'main_building', 3],
    ['stable', 'main_building', 5],
    ['stable', 'barracks', 5],
    ['stable', 'smithy', 1],
    ['workshop', 'main_building', 10],
    ['workshop', 'smithy', 10],
    ['siege_foundry', 'main_building', 10],
    ['siege_foundry', 'workshop', 5],
    ['smithy', 'main_building', 3],
    ['smithy', 'barracks', 1],
    
    // Defensive Building Prerequisites
    ['wall', 'barracks', 1],
    ['watchtower', 'main_building', 5],
    ['garrison', 'main_building', 8],
    ['garrison', 'barracks', 5],
    ['hospital', 'main_building', 12],
    ['hospital', 'barracks', 10],
    
    // Support Building Prerequisites
    ['market', 'main_building', 3],
    ['market', 'warehouse', 2],
    ['scout_hall', 'main_building', 5],
    ['scout_hall', 'rally_point', 1],
    
    // Special Building Prerequisites
    ['hall_of_banners', 'main_building', 15],
    ['hall_of_banners', 'smithy', 15],
    ['hall_of_banners', 'market', 10],
    ['academy', 'main_building', 5],
    ['academy', 'smithy', 5],
    ['academy', 'market', 5],
    
    // Religious Building Prerequisites
    ['statue', 'main_building', 10],
    ['church', 'main_building', 5],
    ['church', 'farm', 5],
    ['first_church', 'main_building', 20],
    ['first_church', 'church', 3],
];

/**
 * Get building ID by internal name
 */
function getBuildingId($conn, $internalName) {
    $stmt = $conn->prepare('SELECT id FROM building_types WHERE internal_name = :internal_name');
    $stmt->bindValue(':internal_name', $internalName, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['id'] : null;
}

/**
 * Check if a requirement already exists
 */
function requirementExists($conn, $buildingId, $requiredBuilding, $requiredLevel) {
    $stmt = $conn->prepare('
        SELECT id FROM building_requirements 
        WHERE building_type_id = :building_id 
        AND required_building = :required_building 
        AND required_level = :required_level
    ');
    $stmt->bindValue(':building_id', $buildingId, SQLITE3_INTEGER);
    $stmt->bindValue(':required_building', $requiredBuilding, SQLITE3_TEXT);
    $stmt->bindValue(':required_level', $requiredLevel, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row !== false;
}

/**
 * Detect circular dependencies using depth-first search
 */
function hasCircularDependency($conn, $buildingName, $visited = [], $recursionStack = []) {
    if (in_array($buildingName, $recursionStack)) {
        return true; // Circular dependency detected
    }
    
    if (in_array($buildingName, $visited)) {
        return false; // Already checked this path
    }
    
    $visited[] = $buildingName;
    $recursionStack[] = $buildingName;
    
    // Get all requirements for this building
    $buildingId = getBuildingId($conn, $buildingName);
    if (!$buildingId) {
        return false;
    }
    
    $stmt = $conn->prepare('
        SELECT required_building FROM building_requirements 
        WHERE building_type_id = :building_id
    ');
    $stmt->bindValue(':building_id', $buildingId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (hasCircularDependency($conn, $row['required_building'], $visited, $recursionStack)) {
            return true;
        }
    }
    
    // Remove from recursion stack
    array_pop($recursionStack);
    
    return false;
}

try {
    $conn->exec('BEGIN TRANSACTION');
    
    $added = 0;
    $updated = 0;
    $skipped = 0;
    
    foreach ($requirements as $req) {
        list($buildingName, $requiredBuilding, $requiredLevel) = $req;
        
        $buildingId = getBuildingId($conn, $buildingName);
        if (!$buildingId) {
            echo "Warning: Building '{$buildingName}' not found, skipping requirement\n";
            $skipped++;
            continue;
        }
        
        $requiredBuildingId = getBuildingId($conn, $requiredBuilding);
        if (!$requiredBuildingId) {
            echo "Warning: Required building '{$requiredBuilding}' not found, skipping requirement\n";
            $skipped++;
            continue;
        }
        
        if (requirementExists($conn, $buildingId, $requiredBuilding, $requiredLevel)) {
            $skipped++;
            continue;
        }
        
        // Insert the requirement
        $stmt = $conn->prepare('
            INSERT INTO building_requirements (building_type_id, required_building, required_level)
            VALUES (:building_id, :required_building, :required_level)
        ');
        $stmt->bindValue(':building_id', $buildingId, SQLITE3_INTEGER);
        $stmt->bindValue(':required_building', $requiredBuilding, SQLITE3_TEXT);
        $stmt->bindValue(':required_level', $requiredLevel, SQLITE3_INTEGER);
        $stmt->execute();
        
        echo "Added: {$buildingName} requires {$requiredBuilding} level {$requiredLevel}\n";
        $added++;
    }
    
    // Validate no circular dependencies
    echo "\nValidating for circular dependencies...\n";
    $result = $conn->query('SELECT DISTINCT internal_name FROM building_types');
    $hasCircular = false;
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (hasCircularDependency($conn, $row['internal_name'])) {
            echo "✗ Circular dependency detected for: {$row['internal_name']}\n";
            $hasCircular = true;
        }
    }
    
    if ($hasCircular) {
        throw new Exception("Circular dependencies detected in prerequisite chains");
    }
    
    echo "✓ No circular dependencies found\n";
    
    $conn->exec('COMMIT');
    
    echo "\n✓ Successfully populated building_requirements table\n";
    echo "Added: {$added}, Skipped (already exists): {$skipped}\n";
    
    // Display summary
    $result = $conn->query('SELECT COUNT(*) as count FROM building_requirements');
    $row = $result->fetchArray(SQLITE3_ASSOC);
    echo "Total requirements in database: {$row['count']}\n";
    
    $conn->close();
    
} catch (Exception $e) {
    $conn->exec('ROLLBACK');
    echo "\n✗ Error populating building_requirements: " . $e->getMessage() . "\n";
    $conn->close();
    exit(1);
}
