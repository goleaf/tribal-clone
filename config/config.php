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
// WORLD_SPEED affects global pacing, while the specific multipliers below fine-tune sub-systems.
define('WORLD_SPEED', 1.0); // General world pace multiplier
define('BUILD_TIME_LEVEL_FACTOR', 1.18); // base_time * factor^level for construction scaling
define('MAIN_BUILDING_TIME_REDUCTION_PER_LEVEL', 0.02); // HQ reduces build times by 2% per level
define('FARM_GROWTH_FACTOR', 1.172); // Farm capacity scaling per level

// Dedicated speed multipliers (per-world balance knobs)
define('UNIT_SPEED_MULTIPLIER', 1.0);       // Multiplies unit travel speed (lower = slower arrival)
define('BUILD_SPEED_MULTIPLIER', 1.0);      // Additional multiplier applied to building times
define('UNIT_TRAINING_MULTIPLIER', 1.0);    // Multiplier for recruitment times
define('RESEARCH_SPEED_MULTIPLIER', 1.0);   // Multiplier for research times
define('BUILDING_QUEUE_MAX_ITEMS', 10);     // Max active+pending items per village build queue

// World configuration
define('WORLD_SIZE', 1000);               // Square world size (e.g., 500, 1000)
define('TRIBE_MEMBER_LIMIT', 0);          // 0 = unlimited, otherwise max members per tribe
define('PLAYER_VILLAGE_LIMIT', 0);        // 0 = unlimited, otherwise max villages per player
define('NOBLE_COIN_COST_WOOD', 40000);
define('NOBLE_COIN_COST_CLAY', 50000);
define('NOBLE_COIN_COST_IRON', 50000);
define('BEGINNER_PROTECTION_HOURS', 72);  // Duration of beginner protection (hours) for new players
define('MORALE_TYPE', 'points');          // 'points', 'none'
define('FEATURE_CHURCH_ENABLED', false);
define('FEATURE_PALADIN_ENABLED', false);
define('FEATURE_WATCHTOWER_ENABLED', false);
define('WORLD_VICTORY_CONDITION', 'domination'); // descriptive string
define('WORLD_DURATION_DAYS', 0);         // 0 = unlimited; otherwise days until end

// Paths and game constants
define('BASE_URL', 'http://localhost:8000/'); // Change to the appropriate URL if the project is not in the htdocs root
define('TRADER_SPEED', 100); // Trader speed in fields per hour
define('TRADER_CAPACITY', 1000); // Resources one trader can carry
define('TRADE_POWER_DELTA_BLOCK_RATIO', 8); // Block aid when power gap exceeds this multiple for protected players
define('TRADE_POWER_DELTA_PROTECTED_POINTS', 500); // Players below this score are protected from lopsided aid
define('TRADE_ALT_IP_BLOCK_ENABLED', true); // If identity fingerprints match (e.g., IP hash), block trades as potential alts
define('FEATURE_MIN_PAYLOAD_ENABLED', true); // Enforce minimum pop payload for commands
define('MIN_ATTACK_POP', 5); // Minimum population required to send a command (unless siege is present)
define('PLUNDER_DR_ENABLED', true); // Enable diminishing returns on repeated plunder within a window
define('FEATURE_WEATHER_COMBAT_ENABLED', false); // Enable weather multipliers in combat resolution
define('INACTIVE_TO_BARBARIAN_DAYS', 30); // Days of inactivity before a player village becomes barbarian (cron-driven)
define('PALADIN_WEAPON', 'none'); // Options: 'none', 'bonfire', 'vascos_scepter'
define('PALADIN_WEAPON_BONFIRE_MULTIPLIER', 1.5); // Attack/defense multiplier for catapults when Bonfire is equipped
define('WORLD_UNIT_SPEED', 1.0); // Baseline unit speed in fields/hour
define('COIN_COST_WOOD', 20000);
define('COIN_COST_CLAY', 20000);
define('COIN_COST_IRON', 20000);
define('NOBLE_MIN_DROP', 20);
define('NOBLE_MAX_DROP', 35);

// Feature flags
define('FEATURE_TASKS_ENABLED', true); // Daily/weekly tasks/challenges

// Allegiance (conquest) regen/decay tuning
define('ALLEG_REGEN_PER_HOUR', 2.0); // base allegiance regeneration per hour
define('ALLEG_MAX_REGEN_MULT', 1.75); // cap for regen multiplier after bonuses
define('ALLEG_REGEN_PAUSE_WINDOW_MS', 5000); // pause regen if hostile command ETA within this window
define('ALLEG_ABANDON_DECAY_PER_HOUR', 0.0); // optional decay when abandoned/offline (0 = disabled)
define('ALLEG_SHRINE_REGEN_BONUS_PER_LEVEL', 0.02); // +2% regen per shrine/temple level
define('ALLEG_HALL_REGEN_FLAT_PER_LEVEL', 0.25); // flat regen/hour added per Hall of Banners level
define('ALLEG_TRIBE_REGEN_MULT', 0.15); // default tribe tech regen multiplier (15%)

// Default world ID
define('INITIAL_WORLD_ID', 1);

// Newbie protection settings
define('NEWBIE_PROTECTION_DAYS_MIN', 3);    // Minimum days of protection from registration
define('NEWBIE_PROTECTION_DAYS_MAX', 7);    // Maximum days of protection
define('NEWBIE_PROTECTION_POINTS_CAP', 200); // Protection auto-ends above this point total

// Command rate limiting
define('ATTACK_SEND_COOLDOWN_MS', 700); // minimum ms between attack/command sends per user
define('ATTACK_PAIR_WINDOW_SEC', 30); // sliding window for per-attacker->target caps
define('ATTACK_PAIR_LIMIT_PER_WINDOW', 5); // max commands per attacker->target per window

// Overstack defense penalty (optional)
define('OVERSTACK_ENABLED', false); // set true to enable defense penalties for overstacked villages
define('OVERSTACK_POP_THRESHOLD', 30000); // population threshold before penalties apply
define('OVERSTACK_PENALTY_RATE', 0.1); // penalty per threshold over (e.g., 0.1 = -10% defense per +threshold pop)
define('OVERSTACK_MIN_MULTIPLIER', 0.4); // floor multiplier to avoid zeroing defense
?>
