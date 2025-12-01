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

// Global speed/balance knobs
define('WORLD_SPEED', 1.0); // Higher values speed up all construction times proportionally
define('BUILD_TIME_LEVEL_FACTOR', 1.18); // base_time * factor^level for construction scaling
define('MAIN_BUILDING_TIME_REDUCTION_PER_LEVEL', 0.02); // HQ reduces build times by 2% per level
define('FARM_GROWTH_FACTOR', 1.172); // Farm capacity scaling per level

// Paths and game constants
define('BASE_URL', 'http://localhost:8000/'); // Change to the appropriate URL if the project is not in the htdocs root
define('TRADER_SPEED', 100); // Trader speed in fields per hour
define('TRADER_CAPACITY', 1000); // Resources one trader can carry
define('INACTIVE_TO_BARBARIAN_DAYS', 30); // Days of inactivity before a player village becomes barbarian (cron-driven)

// Default world ID
define('INITIAL_WORLD_ID', 1);
?>
