<?php
declare(strict_types=1);

/**
 * Migration: Add conquest research types
 * 
 * Adds research nodes required for conquest system:
 * - conquest_training: Unlocks Envoy training
 * 
 * Requirements: 1.1, 1.3
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Starting conquest research migration...\n";

try {
    // Check if conquest_training research already exists
    $result = $conn->query("SELECT id FROM research_types WHERE internal_name = 'conquest_training'");
    
    if ($result && $result->num_rows > 0) {
        echo " - conquest_training research already exists, skipping.\n";
    } else {
        echo "\n1. Adding conquest_training research...\n";
        
        $sql = "INSERT INTO research_types (
            internal_name,
            name,
            description,
            building_type,
            required_building_level,
            cost_wood,
            cost_clay,
            cost_iron,
            research_time_base,
            research_time_factor,
            max_level,
            is_active,
            prerequisite_research_id,
            prerequisite_research_level
        ) VALUES (
            'conquest_training',
            'Conquest Training',
            'Unlock the ability to train Envoys for village conquest. Envoys establish control links on successful attacks.',
            'academy',
            10,
            5000,
            6000,
            7000,
            14400,
            1.2,
            1,
            1,
            NULL,
            NULL
        )";
        
        if ($conn->query($sql)) {
            echo " - Added conquest_training research\n";
            echo "   - Requires Academy level 10\n";
            echo "   - Cost: 5k wood, 6k clay, 7k iron\n";
            echo "   - Research time: 14400 seconds (4 hours base)\n";
        } else {
            echo " [!] Failed to add conquest_training: " . $conn->error . "\n";
            exit(1);
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Added:\n";
    echo "  - conquest_training research node\n";
    echo "  - Prerequisite for training Envoys\n";
    
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
