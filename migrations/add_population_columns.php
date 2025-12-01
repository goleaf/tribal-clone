<?php
declare(strict_types=1);

/**
 * Migration: Add population tracking columns
 * 
 * Adds:
 * - population_cost to building_types (if not exists)
 * - support_units table for tracking allied troops stationed in villages
 */

require_once __DIR__ . '/../Database.php';

$db = Database::getInstance();

echo "Running population columns migration...\n";

// Check if population_cost column exists in building_types
$columns = $db->fetchAll("PRAGMA table_info(building_types)");
$hasPopulationCost = false;

foreach ($columns as $col) {
    if ($col['name'] === 'population_cost') {
        $hasPopulationCost = true;
        break;
    }
}

if (!$hasPopulationCost) {
    echo "Adding population_cost column to building_types...\n";
    $db->execute("ALTER TABLE building_types ADD COLUMN population_cost INTEGER NOT NULL DEFAULT 0");
    echo "✓ Added population_cost column\n";
} else {
    echo "✓ population_cost column already exists\n";
}

// Create support_units table if it doesn't exist
$tableExists = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='support_units'");

if (!$tableExists) {
    echo "Creating support_units table...\n";
    $db->execute("
        CREATE TABLE support_units (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            stationed_village_id INTEGER NOT NULL,
            owner_village_id INTEGER NOT NULL,
            unit_type TEXT NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 0,
            arrived_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
            UNIQUE(stationed_village_id, owner_village_id, unit_type)
        )
    ");
    
    $db->execute("CREATE INDEX idx_support_units_stationed ON support_units(stationed_village_id)");
    $db->execute("CREATE INDEX idx_support_units_owner ON support_units(owner_village_id)");
    
    echo "✓ Created support_units table\n";
} else {
    echo "✓ support_units table already exists\n";
}

// Update building_types with population costs (example values)
echo "Updating building population costs...\n";

$buildingPopCosts = [
    'headquarters' => 5,
    'barracks' => 3,
    'stable' => 4,
    'workshop' => 3,
    'academy' => 5,
    'smithy' => 2,
    'market' => 2,
    'timber_camp' => 1,
    'clay_pit' => 1,
    'iron_mine' => 1,
    'farm' => 0, // Farm provides population, doesn't consume it
    'warehouse' => 1,
    'hiding_place' => 1,
    'wall' => 2,
    'statue' => 3,
];

foreach ($buildingPopCosts as $building => $popCost) {
    $db->execute(
        "UPDATE building_types SET population_cost = ? WHERE internal_name = ?",
        [$popCost, $building]
    );
}

echo "✓ Updated building population costs\n";

echo "\nMigration completed successfully!\n";
echo "\nPopulation system is now ready:\n";
echo "- Farm capacity formula: floor(240 * 1.17^(level-1))\n";
echo "- Population tracking: buildings + troops + support\n";
echo "- Use PopulationManager to enforce limits\n";
