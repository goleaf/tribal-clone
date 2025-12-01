<?php
declare(strict_types=1);

/**
 * Migration: Add Hall of Banners building type
 * 
 * Creates the Hall of Banners building type required for training Envoys.
 * This is analogous to the Academy for nobles.
 * 
 * Requirements: 1.1
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Starting Hall of Banners building migration...\n";

try {
    // Check if Hall of Banners already exists
    $result = $conn->query("SELECT id FROM building_types WHERE internal_name = 'hall_of_banners'");
    
    if ($result && $result->num_rows > 0) {
        echo " - Hall of Banners building type already exists, skipping.\n";
    } else {
        echo "\n1. Adding Hall of Banners building type...\n";
        
        $sql = "INSERT INTO building_types (
            internal_name,
            name,
            description,
            max_level,
            base_build_time_initial,
            build_time_factor,
            cost_wood_initial,
            cost_clay_initial,
            cost_iron_initial,
            cost_factor,
            production_type,
            production_initial,
            production_factor,
            bonus_time_reduction_factor,
            population_cost,
            base_points
        ) VALUES (
            'hall_of_banners',
            'Hall of Banners',
            'Train Envoys for village conquest. Higher levels enable more efficient conquest operations.',
            10,
            2400,
            1.28,
            300,
            350,
            280,
            1.27,
            NULL,
            NULL,
            NULL,
            1.0,
            0,
            2
        )";
        
        if ($conn->query($sql)) {
            echo " - Added Hall of Banners building type\n";
        } else {
            echo " [!] Failed to add Hall of Banners: " . $conn->error . "\n";
            exit(1);
        }
    }
    
    // Add building requirements for Hall of Banners
    echo "\n2. Adding building requirements for Hall of Banners...\n";
    
    // Get the building_type_id for hall_of_banners
    $result = $conn->query("SELECT id FROM building_types WHERE internal_name = 'hall_of_banners'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $buildingTypeId = $row['id'];
        
        // Check if requirement already exists
        $checkSql = "SELECT id FROM building_requirements 
                     WHERE building_type_id = $buildingTypeId 
                     AND required_building = 'main_building'";
        $checkResult = $conn->query($checkSql);
        
        if ($checkResult && $checkResult->num_rows > 0) {
            echo " - Building requirements already exist, skipping.\n";
        } else {
            // Hall of Banners requires Main Building level 10 and Academy level 3
            $requirements = [
                ['required_building' => 'main_building', 'required_level' => 10],
                ['required_building' => 'academy', 'required_level' => 3]
            ];
            
            foreach ($requirements as $req) {
                $reqBuilding = $req['required_building'];
                $reqLevel = $req['required_level'];
                
                $sql = "INSERT INTO building_requirements (building_type_id, required_building, required_level) 
                        VALUES ($buildingTypeId, '$reqBuilding', $reqLevel)";
                
                if ($conn->query($sql)) {
                    echo " - Added requirement: $reqBuilding level $reqLevel\n";
                } else {
                    echo " [!] Failed to add requirement: " . $conn->error . "\n";
                }
            }
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Added:\n";
    echo "  - Hall of Banners building type\n";
    echo "  - Building requirements (Main Building 10, Academy 3)\n";
    
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
