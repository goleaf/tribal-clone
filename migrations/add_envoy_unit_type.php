<?php
declare(strict_types=1);

/**
 * Migration: Add Envoy unit type
 * 
 * Creates the Envoy unit type for village conquest.
 * Envoys establish control links on successful attacks.
 * 
 * Requirements: 1.1, 1.2, 2.1
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Starting Envoy unit type migration...\n";

try {
    // Check if Envoy unit already exists
    $result = $conn->query("SELECT id FROM unit_types WHERE internal_name = 'envoy'");
    
    if ($result && $result->num_rows > 0) {
        echo " - Envoy unit type already exists, skipping.\n";
    } else {
        echo "\n1. Adding Envoy unit type...\n";
        
        $sql = "INSERT INTO unit_types (
            internal_name,
            name,
            description,
            building_type,
            attack,
            defense,
            defense_cavalry,
            defense_archer,
            speed,
            carry_capacity,
            population,
            cost_wood,
            cost_clay,
            cost_iron,
            required_tech,
            required_tech_level,
            required_building_level,
            training_time_base,
            is_active,
            points
        ) VALUES (
            'envoy',
            'Envoy',
            'Special conquest unit that carries edicts to assert control over enemy villages. Establishes control links on successful attacks.',
            'hall_of_banners',
            30,
            100,
            50,
            80,
            30,
            0,
            100,
            40000,
            50000,
            50000,
            'conquest_training',
            1,
            1,
            36000,
            1,
            10
        )";
        
        if ($conn->query($sql)) {
            echo " - Added Envoy unit type\n";
            echo "   - Attack: 30\n";
            echo "   - Defense: 100 (infantry), 50 (cavalry), 80 (ranged)\n";
            echo "   - Speed: 30 min/field (siege speed)\n";
            echo "   - Population: 100\n";
            echo "   - Cost: 40k wood, 50k clay, 50k iron + 1 influence crest\n";
            echo "   - Training time: 36000 seconds (10 hours base)\n";
        } else {
            echo " [!] Failed to add Envoy unit: " . $conn->error . "\n";
            exit(1);
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Added:\n";
    echo "  - Envoy unit type for conquest system\n";
    echo "  - Requires Hall of Banners level 1\n";
    echo "  - Requires conquest_training research\n";
    
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
