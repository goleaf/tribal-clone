<?php
/**
 * Migration: Validate and enhance building_types table schema
 * 
 * This migration ensures all required columns exist in the building_types table
 * with appropriate defaults as specified in the design document.
 * 
 * Requirements: 1.1, 14.1, 18.1
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

function validateBuildingTypesSchema($conn) {
    echo "Validating building_types table schema...\n";
    
    // Get current columns
    $result = $conn->query("PRAGMA table_info(building_types)");
    $existingColumns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $existingColumns[$row['name']] = $row;
    }
    
    // Define required columns with their specifications
    $requiredColumns = [
        'internal_name' => [
            'type' => 'TEXT',
            'not_null' => true,
            'default' => null,
            'unique' => true
        ],
        'name' => [
            'type' => 'TEXT',
            'not_null' => true,
            'default' => null
        ],
        'description' => [
            'type' => 'TEXT',
            'not_null' => false,
            'default' => null
        ],
        'max_level' => [
            'type' => 'INTEGER',
            'not_null' => false,
            'default' => 20
        ],
        'cost_wood_initial' => [
            'type' => 'INTEGER',
            'not_null' => false,
            'default' => 100
        ],
        'cost_clay_initial' => [
            'type' => 'INTEGER',
            'not_null' => false,
            'default' => 100
        ],
        'cost_iron_initial' => [
            'type' => 'INTEGER',
            'not_null' => false,
            'default' => 100
        ],
        'cost_factor' => [
            'type' => 'REAL',
            'not_null' => false,
            'default' => 1.26
        ],
        'base_build_time_initial' => [
            'type' => 'INTEGER',
            'not_null' => false,
            'default' => 900
        ],
        'build_time_factor' => [
            'type' => 'REAL',
            'not_null' => false,
            'default' => 1.18
        ],
        'production_type' => [
            'type' => 'TEXT',
            'not_null' => false,
            'default' => null
        ],
        'production_initial' => [
            'type' => 'INTEGER',
            'not_null' => false,
            'default' => null
        ],
        'production_factor' => [
            'type' => 'REAL',
            'not_null' => false,
            'default' => null
        ],
        'population_cost' => [
            'type' => 'INTEGER',
            'not_null' => false,
            'default' => 0
        ]
    ];
    
    $missingColumns = [];
    $columnsToAdd = [];
    
    foreach ($requiredColumns as $columnName => $spec) {
        if (!isset($existingColumns[$columnName])) {
            $missingColumns[] = $columnName;
            $columnsToAdd[] = [
                'name' => $columnName,
                'spec' => $spec
            ];
        }
    }
    
    if (empty($missingColumns)) {
        echo "✓ All required columns exist in building_types table\n";
        return true;
    }
    
    echo "Missing columns detected: " . implode(', ', $missingColumns) . "\n";
    echo "Adding missing columns...\n";
    
    try {
        $conn->exec('BEGIN TRANSACTION');
        
        foreach ($columnsToAdd as $column) {
            $name = $column['name'];
            $spec = $column['spec'];
            
            $sql = "ALTER TABLE building_types ADD COLUMN {$name} {$spec['type']}";
            
            if ($spec['not_null']) {
                $sql .= " NOT NULL";
            }
            
            if ($spec['default'] !== null) {
                if (is_numeric($spec['default'])) {
                    $sql .= " DEFAULT {$spec['default']}";
                } else {
                    $sql .= " DEFAULT '{$spec['default']}'";
                }
            }
            
            echo "  Adding column: {$name}\n";
            $conn->exec($sql);
        }
        
        $conn->exec('COMMIT');
        echo "✓ Successfully added missing columns\n";
        return true;
        
    } catch (Exception $e) {
        $conn->exec('ROLLBACK');
        echo "✗ Error adding columns: " . $e->getMessage() . "\n";
        return false;
    }
}

// Run migration
if (validateBuildingTypesSchema($conn)) {
    echo "\n✓ Building types schema validation complete\n";
    $conn->close();
} else {
    echo "\n✗ Building types schema validation failed\n";
    $conn->close();
    exit(1);
}
