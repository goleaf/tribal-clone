<?php
/**
 * Migration: Populate building_types table with all 20+ building definitions
 * 
 * This migration adds missing buildings and updates existing ones with proper
 * costs, times, production rates, and prerequisites as specified in the design.
 * 
 * Requirements: 1.1, 4.1, 4.2, 4.3, 14.1
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

echo "Populating building_types table...\n";

// Define all building types with their properties
$buildings = [
    // Core Building
    [
        'internal_name' => 'main_building',
        'name' => 'Town Hall',
        'description' => 'The Town Hall controls construction speed and unlocks advanced buildings. Each level reduces build time by 2% and unlocks additional queue slots.',
        'max_level' => 20,
        'cost_wood_initial' => 90,
        'cost_clay_initial' => 80,
        'cost_iron_initial' => 70,
        'cost_factor' => 1.26,
        'base_build_time_initial' => 900,
        'build_time_factor' => 1.18,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 5
    ],
    
    // Resource Buildings
    [
        'internal_name' => 'sawmill',
        'name' => 'Lumber Yard',
        'description' => 'Produces wood over time. Higher levels increase production rate.',
        'max_level' => 30,
        'cost_wood_initial' => 50,
        'cost_clay_initial' => 60,
        'cost_iron_initial' => 40,
        'cost_factor' => 1.26,
        'base_build_time_initial' => 600,
        'build_time_factor' => 1.18,
        'production_type' => 'wood',
        'production_initial' => 30,
        'production_factor' => 1.163,
        'population_cost' => 2
    ],
    [
        'internal_name' => 'clay_pit',
        'name' => 'Clay Pit',
        'description' => 'Produces clay over time. Higher levels increase production rate.',
        'max_level' => 30,
        'cost_wood_initial' => 65,
        'cost_clay_initial' => 50,
        'cost_iron_initial' => 40,
        'cost_factor' => 1.26,
        'base_build_time_initial' => 600,
        'build_time_factor' => 1.18,
        'production_type' => 'clay',
        'production_initial' => 30,
        'production_factor' => 1.163,
        'population_cost' => 2
    ],
    [
        'internal_name' => 'iron_mine',
        'name' => 'Iron Mine',
        'description' => 'Produces iron over time. Higher levels increase production rate.',
        'max_level' => 30,
        'cost_wood_initial' => 75,
        'cost_clay_initial' => 65,
        'cost_iron_initial' => 70,
        'cost_factor' => 1.26,
        'base_build_time_initial' => 900,
        'build_time_factor' => 1.18,
        'production_type' => 'iron',
        'production_initial' => 25,
        'production_factor' => 1.163,
        'population_cost' => 3
    ],
    [
        'internal_name' => 'farm',
        'name' => 'Farm',
        'description' => 'Provides population capacity for buildings and units. Higher levels increase capacity.',
        'max_level' => 30,
        'cost_wood_initial' => 45,
        'cost_clay_initial' => 40,
        'cost_iron_initial' => 30,
        'cost_factor' => 1.28,
        'base_build_time_initial' => 1200,
        'build_time_factor' => 1.20,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 0
    ],
    
    // Storage Buildings
    [
        'internal_name' => 'warehouse',
        'name' => 'Storage',
        'description' => 'Increases resource storage capacity for all three resource types.',
        'max_level' => 30,
        'cost_wood_initial' => 60,
        'cost_clay_initial' => 50,
        'cost_iron_initial' => 40,
        'cost_factor' => 1.22,
        'base_build_time_initial' => 900,
        'build_time_factor' => 1.15,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 1
    ],
    [
        'internal_name' => 'storage',
        'name' => 'Warehouse',
        'description' => 'Provides additional resource storage capacity beyond the base Storage building.',
        'max_level' => 30,
        'cost_wood_initial' => 80,
        'cost_clay_initial' => 70,
        'cost_iron_initial' => 60,
        'cost_factor' => 1.22,
        'base_build_time_initial' => 1200,
        'build_time_factor' => 1.15,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 1
    ],
    [
        'internal_name' => 'hiding_place',
        'name' => 'Vault',
        'description' => 'Protects a percentage of resources from being plundered in raids.',
        'max_level' => 20,
        'cost_wood_initial' => 50,
        'cost_clay_initial' => 60,
        'cost_iron_initial' => 50,
        'cost_factor' => 1.25,
        'base_build_time_initial' => 600,
        'build_time_factor' => 1.18,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 1
    ],
    
    // Military Buildings
    [
        'internal_name' => 'barracks',
        'name' => 'Barracks',
        'description' => 'Trains basic infantry units. Higher levels reduce training time.',
        'max_level' => 25,
        'cost_wood_initial' => 200,
        'cost_clay_initial' => 170,
        'cost_iron_initial' => 90,
        'cost_factor' => 1.26,
        'base_build_time_initial' => 1800,
        'build_time_factor' => 1.22,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 7
    ],
    [
        'internal_name' => 'stable',
        'name' => 'Stable',
        'description' => 'Trains cavalry units. Higher levels reduce training time.',
        'max_level' => 20,
        'cost_wood_initial' => 270,
        'cost_clay_initial' => 240,
        'cost_iron_initial' => 260,
        'cost_factor' => 1.28,
        'base_build_time_initial' => 2400,
        'build_time_factor' => 1.25,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 8
    ],
    [
        'internal_name' => 'workshop',
        'name' => 'Workshop',
        'description' => 'Trains siege units including battering rams and mantlets.',
        'max_level' => 15,
        'cost_wood_initial' => 300,
        'cost_clay_initial' => 240,
        'cost_iron_initial' => 260,
        'cost_factor' => 1.30,
        'base_build_time_initial' => 3600,
        'build_time_factor' => 1.30,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 10
    ],
    [
        'internal_name' => 'siege_foundry',
        'name' => 'Siege Foundry',
        'description' => 'Trains advanced siege units including catapults.',
        'max_level' => 15,
        'cost_wood_initial' => 350,
        'cost_clay_initial' => 280,
        'cost_iron_initial' => 360,
        'cost_factor' => 1.32,
        'base_build_time_initial' => 4800,
        'build_time_factor' => 1.32,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 12
    ],
    [
        'internal_name' => 'smithy',
        'name' => 'Smithy',
        'description' => 'Researches unit upgrades to improve attack, defense, and speed.',
        'max_level' => 20,
        'cost_wood_initial' => 220,
        'cost_clay_initial' => 180,
        'cost_iron_initial' => 240,
        'cost_factor' => 1.24,
        'base_build_time_initial' => 2400,
        'build_time_factor' => 1.24,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 6
    ],
    
    // Defensive Buildings
    [
        'internal_name' => 'wall',
        'name' => 'Wall',
        'description' => 'Multiplies defender defense values in combat. Each level provides 8% bonus.',
        'max_level' => 20,
        'cost_wood_initial' => 50,
        'cost_clay_initial' => 100,
        'cost_iron_initial' => 20,
        'cost_factor' => 1.25,
        'base_build_time_initial' => 3600,
        'build_time_factor' => 1.26,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 5
    ],
    [
        'internal_name' => 'watchtower',
        'name' => 'Watchtower',
        'description' => 'Detects incoming attacks within a radius. Higher levels increase detection range.',
        'max_level' => 20,
        'cost_wood_initial' => 120,
        'cost_clay_initial' => 100,
        'cost_iron_initial' => 80,
        'cost_factor' => 1.26,
        'base_build_time_initial' => 1800,
        'build_time_factor' => 1.22,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 4
    ],
    [
        'internal_name' => 'garrison',
        'name' => 'Garrison',
        'description' => 'Houses defensive troops and provides defensive bonuses.',
        'max_level' => 20,
        'cost_wood_initial' => 180,
        'cost_clay_initial' => 220,
        'cost_iron_initial' => 160,
        'cost_factor' => 1.27,
        'base_build_time_initial' => 2400,
        'build_time_factor' => 1.24,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 6
    ],
    [
        'internal_name' => 'hospital',
        'name' => 'Hospital',
        'description' => 'Recovers a percentage of wounded troops after defensive battles.',
        'max_level' => 20,
        'cost_wood_initial' => 200,
        'cost_clay_initial' => 180,
        'cost_iron_initial' => 220,
        'cost_factor' => 1.28,
        'base_build_time_initial' => 2700,
        'build_time_factor' => 1.26,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 8
    ],
    
    // Support Buildings
    [
        'internal_name' => 'market',
        'name' => 'Market',
        'description' => 'Enables resource trading with other players. Higher levels increase merchant count and speed.',
        'max_level' => 25,
        'cost_wood_initial' => 100,
        'cost_clay_initial' => 100,
        'cost_iron_initial' => 100,
        'cost_factor' => 1.23,
        'base_build_time_initial' => 1800,
        'build_time_factor' => 1.22,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 4
    ],
    [
        'internal_name' => 'rally_point',
        'name' => 'Rally Point',
        'description' => 'Coordinates military commands including attacks, support, and scouting.',
        'max_level' => 20,
        'cost_wood_initial' => 110,
        'cost_clay_initial' => 160,
        'cost_iron_initial' => 90,
        'cost_factor' => 1.20,
        'base_build_time_initial' => 1200,
        'build_time_factor' => 1.18,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 3
    ],
    [
        'internal_name' => 'scout_hall',
        'name' => 'Scout Hall',
        'description' => 'Improves scouting capabilities and detection probability modifiers.',
        'max_level' => 20,
        'cost_wood_initial' => 140,
        'cost_clay_initial' => 120,
        'cost_iron_initial' => 100,
        'cost_factor' => 1.25,
        'base_build_time_initial' => 1800,
        'build_time_factor' => 1.22,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 5
    ],
    
    // Special Buildings
    [
        'internal_name' => 'hall_of_banners',
        'name' => 'Hall of Banners',
        'description' => 'Mints conquest resources (coins/standards) and trains conquest units.',
        'max_level' => 20,
        'cost_wood_initial' => 400,
        'cost_clay_initial' => 350,
        'cost_iron_initial' => 450,
        'cost_factor' => 1.30,
        'base_build_time_initial' => 5400,
        'build_time_factor' => 1.28,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 15
    ],
    [
        'internal_name' => 'academy',
        'name' => 'Library',
        'description' => 'Unlocks research technologies for advanced units and buildings.',
        'max_level' => 20,
        'cost_wood_initial' => 250,
        'cost_clay_initial' => 200,
        'cost_iron_initial' => 280,
        'cost_factor' => 1.27,
        'base_build_time_initial' => 3600,
        'build_time_factor' => 1.28,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 10
    ],
    
    // Religious Buildings (optional/special)
    [
        'internal_name' => 'statue',
        'name' => 'Statue',
        'description' => 'Provides morale bonuses and cultural benefits.',
        'max_level' => 15,
        'cost_wood_initial' => 220,
        'cost_clay_initial' => 220,
        'cost_iron_initial' => 220,
        'cost_factor' => 1.22,
        'base_build_time_initial' => 2400,
        'build_time_factor' => 1.22,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 8
    ],
    [
        'internal_name' => 'church',
        'name' => 'Church',
        'description' => 'Provides faith-based bonuses to the village.',
        'max_level' => 3,
        'cost_wood_initial' => 160,
        'cost_clay_initial' => 180,
        'cost_iron_initial' => 140,
        'cost_factor' => 1.25,
        'base_build_time_initial' => 3600,
        'build_time_factor' => 1.25,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 6
    ],
    [
        'internal_name' => 'first_church',
        'name' => 'First Church',
        'description' => 'The first church built in the world, providing unique bonuses.',
        'max_level' => 1,
        'cost_wood_initial' => 28000,
        'cost_clay_initial' => 30000,
        'cost_iron_initial' => 25000,
        'cost_factor' => 1.22,
        'base_build_time_initial' => 86400,
        'build_time_factor' => 1.22,
        'production_type' => null,
        'production_initial' => null,
        'production_factor' => null,
        'population_cost' => 50
    ]
];

try {
    $conn->exec('BEGIN TRANSACTION');
    
    foreach ($buildings as $building) {
        // Check if building exists
        $stmt = $conn->prepare('SELECT id FROM building_types WHERE internal_name = :internal_name');
        $stmt->bindValue(':internal_name', $building['internal_name'], SQLITE3_TEXT);
        $result = $stmt->execute();
        $existing = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($existing) {
            // Update existing building
            echo "Updating: {$building['name']} ({$building['internal_name']})\n";
            
            $updateSql = "UPDATE building_types SET 
                name = :name,
                description = :description,
                max_level = :max_level,
                cost_wood_initial = :cost_wood_initial,
                cost_clay_initial = :cost_clay_initial,
                cost_iron_initial = :cost_iron_initial,
                cost_factor = :cost_factor,
                base_build_time_initial = :base_build_time_initial,
                build_time_factor = :build_time_factor,
                production_type = :production_type,
                production_initial = :production_initial,
                production_factor = :production_factor,
                population_cost = :population_cost
                WHERE internal_name = :internal_name";
            
            $stmt = $conn->prepare($updateSql);
            $stmt->bindValue(':name', $building['name'], SQLITE3_TEXT);
            $stmt->bindValue(':description', $building['description'], SQLITE3_TEXT);
            $stmt->bindValue(':max_level', $building['max_level'], SQLITE3_INTEGER);
            $stmt->bindValue(':cost_wood_initial', $building['cost_wood_initial'], SQLITE3_INTEGER);
            $stmt->bindValue(':cost_clay_initial', $building['cost_clay_initial'], SQLITE3_INTEGER);
            $stmt->bindValue(':cost_iron_initial', $building['cost_iron_initial'], SQLITE3_INTEGER);
            $stmt->bindValue(':cost_factor', $building['cost_factor'], SQLITE3_FLOAT);
            $stmt->bindValue(':base_build_time_initial', $building['base_build_time_initial'], SQLITE3_INTEGER);
            $stmt->bindValue(':build_time_factor', $building['build_time_factor'], SQLITE3_FLOAT);
            $stmt->bindValue(':production_type', $building['production_type'], SQLITE3_TEXT);
            $stmt->bindValue(':production_initial', $building['production_initial'], SQLITE3_INTEGER);
            $stmt->bindValue(':production_factor', $building['production_factor'], SQLITE3_FLOAT);
            $stmt->bindValue(':population_cost', $building['population_cost'], SQLITE3_INTEGER);
            $stmt->bindValue(':internal_name', $building['internal_name'], SQLITE3_TEXT);
            $stmt->execute();
            
        } else {
            // Insert new building
            echo "Adding: {$building['name']} ({$building['internal_name']})\n";
            
            $insertSql = "INSERT INTO building_types (
                internal_name, name, description, max_level,
                cost_wood_initial, cost_clay_initial, cost_iron_initial, cost_factor,
                base_build_time_initial, build_time_factor,
                production_type, production_initial, production_factor,
                population_cost
            ) VALUES (
                :internal_name, :name, :description, :max_level,
                :cost_wood_initial, :cost_clay_initial, :cost_iron_initial, :cost_factor,
                :base_build_time_initial, :build_time_factor,
                :production_type, :production_initial, :production_factor,
                :population_cost
            )";
            
            $stmt = $conn->prepare($insertSql);
            $stmt->bindValue(':internal_name', $building['internal_name'], SQLITE3_TEXT);
            $stmt->bindValue(':name', $building['name'], SQLITE3_TEXT);
            $stmt->bindValue(':description', $building['description'], SQLITE3_TEXT);
            $stmt->bindValue(':max_level', $building['max_level'], SQLITE3_INTEGER);
            $stmt->bindValue(':cost_wood_initial', $building['cost_wood_initial'], SQLITE3_INTEGER);
            $stmt->bindValue(':cost_clay_initial', $building['cost_clay_initial'], SQLITE3_INTEGER);
            $stmt->bindValue(':cost_iron_initial', $building['cost_iron_initial'], SQLITE3_INTEGER);
            $stmt->bindValue(':cost_factor', $building['cost_factor'], SQLITE3_FLOAT);
            $stmt->bindValue(':base_build_time_initial', $building['base_build_time_initial'], SQLITE3_INTEGER);
            $stmt->bindValue(':build_time_factor', $building['build_time_factor'], SQLITE3_FLOAT);
            $stmt->bindValue(':production_type', $building['production_type'], SQLITE3_TEXT);
            $stmt->bindValue(':production_initial', $building['production_initial'], SQLITE3_INTEGER);
            $stmt->bindValue(':production_factor', $building['production_factor'], SQLITE3_FLOAT);
            $stmt->bindValue(':population_cost', $building['population_cost'], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
    
    $conn->exec('COMMIT');
    echo "\n✓ Successfully populated building_types table\n";
    
    // Display summary
    $result = $conn->query('SELECT COUNT(*) as count FROM building_types');
    $row = $result->fetchArray(SQLITE3_ASSOC);
    echo "Total buildings in database: {$row['count']}\n";
    
    $conn->close();
    
} catch (Exception $e) {
    $conn->exec('ROLLBACK');
    echo "\n✗ Error populating building_types: " . $e->getMessage() . "\n";
    $conn->close();
    exit(1);
}
