<?php
// Database configuration - using SQLite instead of MySQL
define('DB_DRIVER', 'sqlite');
define('DB_PATH', __DIR__ . '/../data/tribal_wars.sqlite');
define('DB_HOST', 'localhost'); // kept for backwards compatibility
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', 'tribal_wars_new');

// Debug mode (true for development, false for production)
define('DEBUG_MODE', true);

// Default values for a new village
define('INITIAL_WOOD', 500);
define('INITIAL_CLAY', 500);
define('INITIAL_IRON', 500);
define('INITIAL_WAREHOUSE_CAPACITY', 1000);
define('INITIAL_POPULATION', 1);

// Dynamic warehouse capacity (used in BuildingManager)
define('WAREHOUSE_BASE_CAPACITY', 1000); // Capacity of the warehouse at level 1
define('WAREHOUSE_CAPACITY_FACTOR', 1.227); // Capacity multiplier for subsequent warehouse levels

// Main building time reduction factor (each town hall level reduces time by this factor ^ (level-1))
// Example: 0.95 means 5% faster per level compared to the previous one
define('MAIN_BUILDING_TIME_REDUCTION_FACTOR', 0.95);

// Paths and game constants
define('BASE_URL', 'http://localhost:8000/'); // Change to the appropriate URL if the project is not in the htdocs root
define('TRADER_SPEED', 100); // Trader speed in fields per hour
define('TRADER_CAPACITY', 1000); // Resources one trader can carry

// Default world ID
define('INITIAL_WORLD_ID', 1);
?>
