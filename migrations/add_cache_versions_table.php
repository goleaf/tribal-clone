<?php
declare(strict_types=1);

/**
 * Migration: Add cache_versions table for map caching infrastructure
 * 
 * This table tracks data and diplomacy versions per world to support
 * cache invalidation when commands, villages, or diplomacy changes occur.
 */

require_once __DIR__ . '/../init.php';

echo "Adding cache_versions table...\n";

try {
    // Check if table already exists
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cache_versions'");
    
    if ($result && $result->num_rows > 0) {
        echo "Table cache_versions already exists. Skipping.\n";
        exit(0);
    }
    
    // Create cache_versions table
    $sql = "
    CREATE TABLE cache_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        world_id INTEGER NOT NULL UNIQUE,
        data_version INTEGER NOT NULL DEFAULT 0,
        diplomacy_version INTEGER NOT NULL DEFAULT 0,
        updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
    )";
    
    $conn->query($sql);
    echo "Created cache_versions table.\n";
    
    // Create index on world_id for fast lookups
    $conn->query("CREATE INDEX idx_cache_versions_world ON cache_versions(world_id)");
    echo "Created index on cache_versions.world_id.\n";
    
    // Initialize cache versions for existing worlds
    $worldResult = $conn->query("SELECT id FROM world_config");
    if ($worldResult && $worldResult->num_rows > 0) {
        $timestamp = time();
        while ($world = $worldResult->fetch_assoc()) {
            $worldId = (int)$world['id'];
            $stmt = $conn->prepare(
                "INSERT INTO cache_versions (world_id, data_version, diplomacy_version, updated_at) 
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('iiii', $worldId, $timestamp, $timestamp, $timestamp);
            $stmt->execute();
            $stmt->close();
            echo "Initialized cache versions for world {$worldId}.\n";
        }
    }
    
    echo "Migration completed successfully.\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
