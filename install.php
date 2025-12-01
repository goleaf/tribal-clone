<?php
declare(strict_types=1);

/**
 * Installation script for the Tribal Wars clone (SQLite).
 * - Creates all required tables with indexes
 * - Seeds world config, building types, unit types
 * - Creates admin account
 * - Spawns barbarian villages on the map
 * Safe to re-run: skips existing tables/seeds when present.
 */

require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

// ---- Helpers ----
function tableExists(Database $db, string $table): bool
{
    $row = $db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
    return $row !== null;
}

function ensureTable(Database $db, string $name, string $createSql): void
{
    if (!tableExists($db, $name)) {
        $db->execute($createSql);
        echo "Created table: {$name}\n";
    } else {
        echo "Table already exists, skipping: {$name}\n";
    }
}

function ensureIndex(Database $db, string $name, string $sql): void
{
    $db->execute("CREATE INDEX IF NOT EXISTS {$name} ON {$sql}");
}

function seedIfEmpty(Database $db, string $table, callable $seeder): void
{
    $row = $db->fetchOne("SELECT COUNT(*) AS cnt FROM {$table}");
    if (!$row || (int)$row['cnt'] === 0) {
        $seeder($db);
    }
}

// ---- Create tables ----
ensureTable($db, 'users', "
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    points INTEGER NOT NULL DEFAULT 0,
    is_admin INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
)");
ensureIndex($db, 'idx_users_username', 'users(username)');

ensureTable($db, 'tribes', "
CREATE TABLE tribes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    tag TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT '',
    founder_id INTEGER NOT NULL,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    points INTEGER NOT NULL DEFAULT 0
)");
ensureIndex($db, 'idx_tribes_tag', 'tribes(tag)');

ensureTable($db, 'tribe_members', "
CREATE TABLE tribe_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tribe_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    rank TEXT NOT NULL DEFAULT 'member',
    joined_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    UNIQUE(tribe_id, user_id)
)");
ensureIndex($db, 'idx_tribe_members_user', 'tribe_members(user_id)');

ensureTable($db, 'tribe_diplomacy', "
CREATE TABLE tribe_diplomacy (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tribe_id_1 INTEGER NOT NULL,
    tribe_id_2 INTEGER NOT NULL,
    relation_type TEXT NOT NULL,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
)");
ensureIndex($db, 'idx_tribe_diplomacy_pair', 'tribe_diplomacy(tribe_id_1, tribe_id_2)');

ensureTable($db, 'world_config', "
CREATE TABLE world_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    world_name TEXT NOT NULL DEFAULT 'World 1',
    world_size INTEGER NOT NULL DEFAULT 500,
    unit_speed REAL NOT NULL DEFAULT 1.0,
    build_speed REAL NOT NULL DEFAULT 1.0,
    research_speed REAL NOT NULL DEFAULT 1.0,
    beginner_protection_points INTEGER NOT NULL DEFAULT 200,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
)");

ensureTable($db, 'villages', "
CREATE TABLE villages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    x_coord INTEGER NOT NULL,
    y_coord INTEGER NOT NULL,
    points INTEGER NOT NULL DEFAULT 0,
    loyalty INTEGER NOT NULL DEFAULT 100,
    is_capital INTEGER NOT NULL DEFAULT 0,
    conquered_at TEXT DEFAULT NULL,
    last_loyalty_update INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    wood INTEGER NOT NULL DEFAULT 0,
    clay INTEGER NOT NULL DEFAULT 0,
    iron INTEGER NOT NULL DEFAULT 0,
    last_update INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    is_barbarian INTEGER NOT NULL DEFAULT 0,
    UNIQUE (x_coord, y_coord)
)");
ensureIndex($db, 'idx_villages_user', 'villages(user_id)');
ensureIndex($db, 'idx_villages_coords', 'villages(x_coord, y_coord)');

ensureTable($db, 'building_types', "
CREATE TABLE building_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_name TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    max_level INTEGER NOT NULL DEFAULT 30,
    base_wood INTEGER NOT NULL DEFAULT 0,
    base_clay INTEGER NOT NULL DEFAULT 0,
    base_iron INTEGER NOT NULL DEFAULT 0,
    base_time INTEGER NOT NULL DEFAULT 60,
    base_points INTEGER NOT NULL DEFAULT 1
)");

ensureTable($db, 'buildings', "
CREATE TABLE buildings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    building_type TEXT NOT NULL,
    level INTEGER NOT NULL DEFAULT 0,
    UNIQUE(village_id, building_type)
)");
ensureIndex($db, 'idx_buildings_village', 'buildings(village_id)');

ensureTable($db, 'building_queue', "
CREATE TABLE building_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    building_type TEXT NOT NULL,
    target_level INTEGER NOT NULL,
    complete_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
)");
ensureIndex($db, 'idx_build_queue_village', 'building_queue(village_id, complete_at)');

ensureTable($db, 'unit_types', "
CREATE TABLE unit_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_name TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    building_type TEXT NOT NULL DEFAULT 'barracks',
    attack INTEGER NOT NULL DEFAULT 0,
    defense INTEGER NOT NULL DEFAULT 0,
    speed REAL NOT NULL DEFAULT 0,
    carry_capacity INTEGER NOT NULL DEFAULT 0,
    population INTEGER NOT NULL DEFAULT 1,
    cost_wood INTEGER NOT NULL DEFAULT 0,
    cost_clay INTEGER NOT NULL DEFAULT 0,
    cost_iron INTEGER NOT NULL DEFAULT 0,
    required_tech TEXT DEFAULT NULL,
    required_tech_level INTEGER NOT NULL DEFAULT 0,
    required_building_level INTEGER NOT NULL DEFAULT 1,
    training_time_base INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    points INTEGER NOT NULL DEFAULT 1,
    defense_cavalry INTEGER NOT NULL DEFAULT 0,
    defense_archer INTEGER NOT NULL DEFAULT 0
)");

ensureTable($db, 'units', "
CREATE TABLE units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    unit_type TEXT NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 0,
    UNIQUE(village_id, unit_type)
)");
ensureIndex($db, 'idx_units_village', 'units(village_id)');

ensureTable($db, 'unit_queue', "
CREATE TABLE unit_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    unit_type TEXT NOT NULL,
    quantity INTEGER NOT NULL,
    complete_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
)");
ensureIndex($db, 'idx_unit_queue_village', 'unit_queue(village_id, complete_at)');

ensureTable($db, 'troop_movements', "
CREATE TABLE troop_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    from_village_id INTEGER NOT NULL,
    to_village_id INTEGER NOT NULL,
    command_type TEXT NOT NULL,
    units_json TEXT NOT NULL,
    departure_time INTEGER NOT NULL,
    arrival_time INTEGER NOT NULL,
    is_returning INTEGER NOT NULL DEFAULT 0,
    catapult_target TEXT DEFAULT NULL
)");
ensureIndex($db, 'idx_troops_arrival', 'troop_movements(arrival_time)');
ensureIndex($db, 'idx_troops_to', 'troop_movements(to_village_id)');

ensureTable($db, 'technologies', "
CREATE TABLE technologies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    tech_type TEXT NOT NULL,
    level INTEGER NOT NULL DEFAULT 0,
    complete_at INTEGER DEFAULT NULL,
    UNIQUE(village_id, tech_type)
)");

ensureTable($db, 'reports', "
CREATE TABLE reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    timestamp INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    data TEXT NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0
)");
ensureIndex($db, 'idx_reports_user', 'reports(user_id, is_read)');

ensureTable($db, 'messages', "
CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    from_user_id INTEGER NOT NULL,
    to_user_id INTEGER NOT NULL,
    subject TEXT NOT NULL,
    body TEXT NOT NULL,
    timestamp INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    is_read INTEGER NOT NULL DEFAULT 0,
    folder TEXT NOT NULL DEFAULT 'inbox'
)");
ensureIndex($db, 'idx_messages_to', 'messages(to_user_id, is_read)');

ensureTable($db, 'market_offers', "
CREATE TABLE market_offers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    offering_resource TEXT NOT NULL,
    offering_amount INTEGER NOT NULL,
    requesting_resource TEXT NOT NULL,
    requesting_amount INTEGER NOT NULL,
    created_time INTEGER NOT NULL DEFAULT (strftime('%s','now'))
)");
ensureIndex($db, 'idx_market_village', 'market_offers(village_id)');

// ---- Seed data ----
seedIfEmpty($db, 'world_config', function (Database $db) {
    $db->execute("INSERT INTO world_config (world_name, world_size, unit_speed, build_speed, research_speed, beginner_protection_points) VALUES (?, ?, ?, ?, ?, ?)", [
        'World 1', 500, 1.0, 1.0, 1.0, 200
    ]);
});

seedIfEmpty($db, 'users', function (Database $db) {
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $db->execute("INSERT INTO users (username, email, password, points, is_admin) VALUES (?, ?, ?, ?, 1)", [
        'admin', 'admin@example.com', $password, 0
    ]);
});

seedIfEmpty($db, 'building_types', function (Database $db) {
    $types = [
        ['headquarters', 'Headquarters', 30, 90, 80, 70, 900, 10],
        ['barracks', 'Barracks', 25, 200, 170, 90, 1200, 8],
        ['stable', 'Stable', 20, 270, 240, 260, 1500, 8],
        ['workshop', 'Workshop', 15, 300, 320, 290, 2000, 8],
        ['academy', 'Academy', 20, 260, 300, 220, 1600, 8],
        ['smithy', 'Smithy', 20, 180, 250, 220, 1100, 5],
        ['market', 'Market', 25, 150, 200, 130, 1300, 4],
        ['timber_camp', 'Timber Camp', 30, 50, 60, 40, 600, 2],
        ['clay_pit', 'Clay Pit', 30, 65, 50, 40, 600, 2],
        ['iron_mine', 'Iron Mine', 30, 75, 65, 60, 720, 2],
        ['farm', 'Farm', 30, 80, 100, 70, 1000, 2],
        ['warehouse', 'Warehouse', 30, 60, 50, 40, 800, 2],
        ['hiding_place', 'Hiding Place', 10, 50, 50, 50, 300, 1],
        ['wall', 'Wall', 20, 100, 300, 200, 1400, 4],
        ['statue', 'Statue', 1, 200, 200, 200, 1800, 5],
    ];
    foreach ($types as $t) {
        $db->execute("INSERT INTO building_types (internal_name, name, max_level, base_wood, base_clay, base_iron, base_time, base_points) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", $t);
    }
});

seedIfEmpty($db, 'unit_types', function (Database $db) {
    $units = [
        // internal, name, building_type, attack, defense, def_cav, def_arch, speed, carry, pop, wood, clay, iron, training_time_base, req_building_level, points
        ['spear', 'Spearman', 'barracks', 10, 15, 45, 20, 18, 25, 1, 50, 30, 10, 600, 1, 1],
        ['sword', 'Swordsman', 'barracks', 25, 50, 15, 15, 22, 15, 1, 30, 30, 70, 900, 1, 1],
        ['axe', 'Axeman', 'barracks', 40, 10, 5, 5, 18, 10, 1, 60, 30, 40, 900, 2, 2],
        ['light', 'Light Cavalry', 'stable', 130, 40, 30, 30, 10, 80, 4, 125, 100, 250, 1800, 3, 4],
        ['heavy', 'Heavy Cavalry', 'stable', 150, 200, 80, 80, 11, 50, 6, 200, 150, 600, 3600, 3, 6],
        ['marcher', 'Mounted Archer', 'stable', 120, 40, 50, 120, 10, 50, 5, 250, 100, 150, 2400, 3, 5],
        ['ram', 'Ram', 'workshop', 2, 20, 2, 2, 30, 0, 5, 300, 200, 100, 4800, 1, 5],
        ['catapult', 'Catapult', 'workshop', 100, 100, 100, 100, 30, 0, 8, 320, 400, 100, 7200, 1, 8],
        ['noble', 'Nobleman', 'academy', 30, 100, 100, 100, 35, 0, 100, 40000, 50000, 50000, 36000, 1, 40],
        ['paladin', 'Paladin', 'statue', 150, 200, 200, 200, 30, 0, 10, 400, 400, 200, 7200, 1, 20],
    ];
    foreach ($units as $u) {
        $db->execute("INSERT INTO unit_types (internal_name, name, building_type, attack, defense, defense_cavalry, defense_archer, speed, carry_capacity, population, cost_wood, cost_clay, cost_iron, training_time_base, required_building_level, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $u);
    }
});

// ---- Barbarian villages ----
seedIfEmpty($db, 'villages', function (Database $db) {
    $world = $db->fetchOne("SELECT world_size FROM world_config LIMIT 1");
    $size = $world ? (int)$world['world_size'] : 500;
    $count = random_int(100, 300);
    $used = [];

    $db->beginTransaction();
    try {
        for ($i = 0; $i < $count; $i++) {
            do {
                $x = random_int(0, $size - 1);
                $y = random_int(0, $size - 1);
                $key = "{$x}:{$y}";
            } while (isset($used[$key]));
            $used[$key] = true;
            $db->execute("INSERT INTO villages (user_id, name, x_coord, y_coord, points, loyalty, wood, clay, iron, is_barbarian) VALUES (-1, ?, ?, ?, 0, 100, 500, 500, 500, 1)", [
                "Barbarian {$x}|{$y}", $x, $y
            ]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
});

echo "Installation finished.\n";
