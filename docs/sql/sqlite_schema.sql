PRAGMA foreign_keys = ON;

-- Reset existing tables (order matters because of FK constraints)
DROP TABLE IF EXISTS battle_report_units;
DROP TABLE IF EXISTS battle_reports;
DROP TABLE IF EXISTS attack_units;
DROP TABLE IF EXISTS attacks;
DROP TABLE IF EXISTS ai_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS user_achievements;
DROP TABLE IF EXISTS achievements;
DROP TABLE IF EXISTS trade_routes;
DROP TABLE IF EXISTS trade_offers;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS research_queue;
DROP TABLE IF EXISTS village_research;
DROP TABLE IF EXISTS research_types;
DROP TABLE IF EXISTS unit_queue;
DROP TABLE IF EXISTS village_units;
DROP TABLE IF EXISTS unit_types;
DROP TABLE IF EXISTS building_queue;
DROP TABLE IF EXISTS village_buildings;
DROP TABLE IF EXISTS building_requirements;
DROP TABLE IF EXISTS building_types;
DROP TABLE IF EXISTS tribe_invitations;
DROP TABLE IF EXISTS tribe_members;
DROP TABLE IF EXISTS tribes;
DROP TABLE IF EXISTS villages;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS worlds;

CREATE TABLE IF NOT EXISTS worlds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    is_admin INTEGER NOT NULL DEFAULT 0,
    is_banned INTEGER NOT NULL DEFAULT 0,
    points INTEGER NOT NULL DEFAULT 0,
    is_protected INTEGER NOT NULL DEFAULT 1,
    ally_id INTEGER DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tribes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    tag TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT '',
    internal_text TEXT DEFAULT '',
    founder_id INTEGER NOT NULL,
    points INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (founder_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS achievements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_name TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT 'general',
    condition_type TEXT NOT NULL,
    condition_target TEXT DEFAULT NULL,
    condition_value INTEGER NOT NULL,
    reward_wood INTEGER NOT NULL DEFAULT 0,
    reward_clay INTEGER NOT NULL DEFAULT 0,
    reward_iron INTEGER NOT NULL DEFAULT 0,
    reward_points INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_achievements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    achievement_id INTEGER NOT NULL,
    progress INTEGER NOT NULL DEFAULT 0,
    unlocked INTEGER NOT NULL DEFAULT 0,
    unlocked_at TEXT DEFAULT NULL,
    reward_claimed INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE (user_id, achievement_id)
);

CREATE TABLE IF NOT EXISTS villages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    world_id INTEGER NOT NULL DEFAULT 1,
    name TEXT NOT NULL,
    x_coord INTEGER NOT NULL,
    y_coord INTEGER NOT NULL,
    wood INTEGER DEFAULT 0,
    clay INTEGER DEFAULT 0,
    iron INTEGER DEFAULT 0,
    warehouse_capacity INTEGER DEFAULT 1000,
    population INTEGER DEFAULT 100,
    farm_capacity INTEGER DEFAULT 0,
    loyalty INTEGER NOT NULL DEFAULT 100,
    points INTEGER DEFAULT 0,
    last_resource_update TEXT DEFAULT CURRENT_TIMESTAMP,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (x_coord, y_coord, world_id)
);

CREATE TABLE IF NOT EXISTS building_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_name TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    max_level INTEGER DEFAULT 20,
    base_build_time_initial INTEGER DEFAULT 900,
    build_time_factor REAL DEFAULT 1.2,
    cost_wood_initial INTEGER DEFAULT 100,
    cost_clay_initial INTEGER DEFAULT 100,
    cost_iron_initial INTEGER DEFAULT 100,
    cost_factor REAL DEFAULT 1.25,
    production_type TEXT NULL,
    production_initial INTEGER NULL,
    production_factor REAL NULL,
    bonus_time_reduction_factor REAL DEFAULT 1.0,
    population_cost INTEGER DEFAULT 0,
    base_points INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS building_requirements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    building_type_id INTEGER NOT NULL,
    required_building TEXT NOT NULL,
    required_level INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS village_buildings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    building_type_id INTEGER NOT NULL,
    level INTEGER DEFAULT 0,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE,
    UNIQUE (village_id, building_type_id)
);

CREATE TABLE IF NOT EXISTS building_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    village_building_id INTEGER NOT NULL,
    building_type_id INTEGER NOT NULL,
    level INTEGER NOT NULL,
    starts_at TEXT NOT NULL,
    finish_time TEXT NOT NULL,
    is_demolition INTEGER NOT NULL DEFAULT 0,
    refund_wood INTEGER NOT NULL DEFAULT 0,
    refund_clay INTEGER NOT NULL DEFAULT 0,
    refund_iron INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (village_building_id) REFERENCES village_buildings(id) ON DELETE CASCADE,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS unit_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_name TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    building_type TEXT NOT NULL,
    attack INTEGER NOT NULL DEFAULT 0,
    defense INTEGER NOT NULL DEFAULT 0,
    defense_cavalry INTEGER NOT NULL DEFAULT 0,
    defense_archer INTEGER NOT NULL DEFAULT 0,
    speed INTEGER NOT NULL DEFAULT 0,
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
    points INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS village_units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    unit_type_id INTEGER NOT NULL,
    count INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_type_id) REFERENCES unit_types(id) ON DELETE CASCADE,
    UNIQUE (village_id, unit_type_id)
);

CREATE TABLE IF NOT EXISTS unit_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    unit_type_id INTEGER NOT NULL,
    count INTEGER NOT NULL DEFAULT 1,
    count_finished INTEGER NOT NULL DEFAULT 0,
    started_at INTEGER NOT NULL,
    finish_at INTEGER NOT NULL,
    building_type TEXT NOT NULL,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_type_id) REFERENCES unit_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS research_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_name TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    building_type TEXT NOT NULL,
    required_building_level INTEGER NOT NULL DEFAULT 1,
    cost_wood INTEGER NOT NULL DEFAULT 0,
    cost_clay INTEGER NOT NULL DEFAULT 0,
    cost_iron INTEGER NOT NULL DEFAULT 0,
    research_time_base INTEGER NOT NULL DEFAULT 3600,
    research_time_factor REAL NOT NULL DEFAULT 1.2,
    max_level INTEGER NOT NULL DEFAULT 3,
    is_active INTEGER NOT NULL DEFAULT 1,
    prerequisite_research_id INTEGER DEFAULT NULL,
    prerequisite_research_level INTEGER DEFAULT NULL,
    FOREIGN KEY (prerequisite_research_id) REFERENCES research_types(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS village_research (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    research_type_id INTEGER NOT NULL,
    level INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (research_type_id) REFERENCES research_types(id) ON DELETE CASCADE,
    UNIQUE (village_id, research_type_id)
);

CREATE TABLE IF NOT EXISTS research_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    village_id INTEGER NOT NULL,
    research_type_id INTEGER NOT NULL,
    level_after INTEGER NOT NULL,
    started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ends_at TEXT NOT NULL,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (research_type_id) REFERENCES research_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER NOT NULL,
    subject TEXT NOT NULL,
    body TEXT NOT NULL,
    sent_at TEXT DEFAULT CURRENT_TIMESTAMP,
    is_read INTEGER DEFAULT 0,
    is_archived INTEGER DEFAULT 0,
    is_sender_deleted INTEGER DEFAULT 0,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    report_type TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    is_read INTEGER DEFAULT 0,
    is_archived INTEGER DEFAULT 0,
    is_deleted INTEGER DEFAULT 0,
    related_id INTEGER NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attacks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_village_id INTEGER NOT NULL,
    target_village_id INTEGER NOT NULL,
    attack_type TEXT NOT NULL DEFAULT 'attack',
    start_time TEXT NOT NULL,
    arrival_time TEXT NOT NULL,
    is_completed INTEGER NOT NULL DEFAULT 0,
    is_canceled INTEGER NOT NULL DEFAULT 0,
    report_id INTEGER NULL,
    target_building TEXT DEFAULT NULL,
    FOREIGN KEY (source_village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (target_village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS attack_units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    attack_id INTEGER NOT NULL,
    unit_type_id INTEGER NOT NULL,
    count INTEGER NOT NULL,
    FOREIGN KEY (attack_id) REFERENCES attacks(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_type_id) REFERENCES unit_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS battle_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    attack_id INTEGER NOT NULL,
    source_village_id INTEGER NOT NULL,
    target_village_id INTEGER NOT NULL,
    battle_time TEXT NOT NULL,
    attacker_user_id INTEGER NOT NULL,
    defender_user_id INTEGER NOT NULL,
    attacker_won INTEGER NOT NULL,
    report_data TEXT NOT NULL,
    FOREIGN KEY (attack_id) REFERENCES attacks(id) ON DELETE CASCADE,
    FOREIGN KEY (source_village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (target_village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (attacker_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (defender_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS battle_report_units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    unit_type_id INTEGER NOT NULL,
    side TEXT NOT NULL,
    initial_count INTEGER NOT NULL,
    lost_count INTEGER NOT NULL,
    remaining_count INTEGER NOT NULL,
    FOREIGN KEY (report_id) REFERENCES battle_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_type_id) REFERENCES unit_types(id)
);

CREATE TABLE IF NOT EXISTS ai_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,
    village_id INTEGER DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    link TEXT DEFAULT '',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    is_read INTEGER DEFAULT 0,
    expires_at INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS trade_offers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_village_id INTEGER NOT NULL,
    offered_wood INTEGER NOT NULL DEFAULT 0,
    offered_clay INTEGER NOT NULL DEFAULT 0,
    offered_iron INTEGER NOT NULL DEFAULT 0,
    requested_wood INTEGER NOT NULL DEFAULT 0,
    requested_clay INTEGER NOT NULL DEFAULT 0,
    requested_iron INTEGER NOT NULL DEFAULT 0,
    merchants_required INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'open',
    accepted_village_id INTEGER NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at TEXT NULL,
    completed_at TEXT NULL,
    FOREIGN KEY (source_village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (accepted_village_id) REFERENCES villages(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS trade_routes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_village_id INTEGER NOT NULL,
    target_village_id INTEGER NULL,
    target_x INTEGER NOT NULL,
    target_y INTEGER NOT NULL,
    wood INTEGER NOT NULL,
    clay INTEGER NOT NULL,
    iron INTEGER NOT NULL,
    traders_count INTEGER NOT NULL DEFAULT 1,
    departure_time TEXT NOT NULL,
    arrival_time TEXT NOT NULL,
    offer_id INTEGER NULL,
    FOREIGN KEY (source_village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (target_village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (offer_id) REFERENCES trade_offers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS tribe_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tribe_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL UNIQUE,
    role TEXT NOT NULL DEFAULT 'member',
    joined_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tribe_id) REFERENCES tribes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tribe_invitations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tribe_id INTEGER NOT NULL,
    invited_user_id INTEGER NOT NULL,
    inviter_id INTEGER DEFAULT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    responded_at TEXT DEFAULT NULL,
    UNIQUE(tribe_id, invited_user_id),
    FOREIGN KEY (tribe_id) REFERENCES tribes(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS tribe_diplomacy (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tribe_id INTEGER NOT NULL,
    target_tribe_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tribe_id, target_tribe_id),
    FOREIGN KEY (tribe_id) REFERENCES tribes(id) ON DELETE CASCADE,
    FOREIGN KEY (target_tribe_id) REFERENCES tribes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tribe_forum_threads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tribe_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    author_id INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tribe_id) REFERENCES tribes(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tribe_forum_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    thread_id INTEGER NOT NULL,
    author_id INTEGER NOT NULL,
    body TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES tribe_forum_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Performance indexes for hot lookups
CREATE INDEX IF NOT EXISTS idx_villages_user ON villages(user_id);
CREATE INDEX IF NOT EXISTS idx_village_buildings_village ON village_buildings(village_id);
CREATE INDEX IF NOT EXISTS idx_village_buildings_type ON village_buildings(building_type_id);
CREATE INDEX IF NOT EXISTS idx_building_queue_village_finish ON building_queue(village_id, finish_time);
CREATE INDEX IF NOT EXISTS idx_building_queue_building ON building_queue(village_building_id);
CREATE INDEX IF NOT EXISTS idx_unit_queue_village_finish ON unit_queue(village_id, finish_at);
CREATE INDEX IF NOT EXISTS idx_research_queue_village_finish ON research_queue(village_id, ends_at);
CREATE INDEX IF NOT EXISTS idx_trade_routes_source_arrival ON trade_routes(source_village_id, arrival_time);
CREATE INDEX IF NOT EXISTS idx_trade_routes_offer ON trade_routes(offer_id);
CREATE INDEX IF NOT EXISTS idx_trade_routes_target ON trade_routes(target_village_id);
CREATE INDEX IF NOT EXISTS idx_trade_offers_status ON trade_offers(status);
CREATE INDEX IF NOT EXISTS idx_trade_offers_source ON trade_offers(source_village_id);

-- Seed data
INSERT INTO worlds (id, name, created_at) VALUES (1, 'World 1', CURRENT_TIMESTAMP);
INSERT OR IGNORE INTO users (id, username, email, password, is_admin, is_banned, created_at) VALUES (-1, 'Barbarians', 'barbarians@localhost', '', 0, 0, CURRENT_TIMESTAMP);

INSERT INTO building_types (internal_name, name, description, max_level, base_build_time_initial, build_time_factor, cost_wood_initial, cost_clay_initial, cost_iron_initial, cost_factor, production_type, production_initial, production_factor, bonus_time_reduction_factor, population_cost, base_points) VALUES
('main_building', 'Town Hall', 'The town hall is the center of your village. Higher levels reduce building times.', 20, 900, 1.2, 90, 80, 70, 1.25, NULL, NULL, NULL, 0.95, 0, 1),
('sawmill', 'Timber Camp', 'Produces wood. Higher levels increase production.', 30, 600, 1.18, 50, 60, 40, 1.26, 'wood', 30, 1.16, 1.0, 0, 1),
('clay_pit', 'Clay Pit', 'Produces clay. Higher levels increase production.', 30, 600, 1.18, 65, 50, 40, 1.26, 'clay', 30, 1.16, 1.0, 0, 1),
('iron_mine', 'Iron Mine', 'Produces iron. Higher levels increase production.', 30, 720, 1.18, 75, 65, 60, 1.26, 'iron', 30, 1.16, 1.0, 0, 1),
('warehouse', 'Warehouse', 'Stores your resources. Higher levels increase capacity.', 30, 800, 1.15, 60, 50, 40, 1.22, NULL, 1000, 1.227, 1.0, 0, 1),
('farm', 'Farm', 'Increases village population capacity.', 30, 1000, 1.2, 80, 100, 70, 1.28, NULL, 240, 1.172, 1.0, 0, 1),
('barracks', 'Barracks', 'Train infantry units.', 25, 1200, 1.22, 200, 170, 90, 1.26, NULL, NULL, NULL, 1.0, 0, 1),
('stable', 'Stable', 'Train cavalry units.', 20, 1500, 1.25, 270, 240, 260, 1.28, NULL, NULL, NULL, 1.0, 0, 1),
('workshop', 'Workshop', 'Build siege engines.', 15, 2000, 1.3, 300, 320, 290, 1.3, NULL, NULL, NULL, 1.0, 0, 1),
('smithy', 'Smithy', 'Improve weapons and armor.', 20, 1100, 1.24, 180, 250, 220, 1.24, NULL, NULL, NULL, 1.0, 0, 1),
('market', 'Market', 'Trade resources with other players.', 25, 1300, 1.22, 150, 200, 130, 1.23, NULL, NULL, NULL, 1.0, 0, 1),
('wall', 'Wall', 'Provides defense against enemy attacks.', 20, 1400, 1.26, 100, 300, 200, 1.25, NULL, NULL, NULL, 1.0, 0, 1),
('academy', 'Academy', 'Research new technologies.', 20, 1600, 1.28, 260, 300, 220, 1.27, NULL, NULL, NULL, 1.0, 0, 1),
('rally_point', 'Rally Point', 'Coordinate and launch troop movements.', 20, 600, 1.18, 80, 70, 60, 1.2, NULL, NULL, NULL, 1.0, 0, 1),
('statue', 'Statue', 'Train nobles; levels drop by 5 per noble trained.', 15, 1200, 1.22, 220, 220, 180, 1.22, NULL, NULL, NULL, 1.0, 0, 1),
('church', 'Church', 'Provides faith radius and defensive bonuses nearby.', 3, 2400, 1.25, 500, 500, 400, 1.25, NULL, NULL, NULL, 1.0, 0, 1),
('first_church', 'First Church', 'Unique first church built by a player.', 1, 1800, 1.22, 300, 300, 250, 1.22, NULL, NULL, NULL, 1.0, 0, 1),
('hiding_place', 'Hiding Place', 'Protects a portion of resources from raids.', 20, 600, 1.18, 50, 60, 50, 1.25, NULL, NULL, NULL, 1.0, 0, 1);

INSERT INTO building_requirements (building_type_id, required_building, required_level) VALUES
((SELECT id FROM building_types WHERE internal_name = 'barracks'), 'main_building', 3),
((SELECT id FROM building_types WHERE internal_name = 'stable'), 'barracks', 10),
((SELECT id FROM building_types WHERE internal_name = 'stable'), 'smithy', 5),
((SELECT id FROM building_types WHERE internal_name = 'workshop'), 'smithy', 10),
((SELECT id FROM building_types WHERE internal_name = 'smithy'), 'main_building', 3),
((SELECT id FROM building_types WHERE internal_name = 'market'), 'main_building', 3),
((SELECT id FROM building_types WHERE internal_name = 'market'), 'warehouse', 2),
((SELECT id FROM building_types WHERE internal_name = 'wall'), 'barracks', 1),
((SELECT id FROM building_types WHERE internal_name = 'academy'), 'main_building', 5),
((SELECT id FROM building_types WHERE internal_name = 'academy'), 'smithy', 1),
((SELECT id FROM building_types WHERE internal_name = 'rally_point'), 'main_building', 1),
((SELECT id FROM building_types WHERE internal_name = 'statue'), 'academy', 1),
((SELECT id FROM building_types WHERE internal_name = 'church'), 'main_building', 5),
((SELECT id FROM building_types WHERE internal_name = 'first_church'), 'main_building', 2);

INSERT INTO unit_types (
    internal_name, name, description, building_type,
    attack, defense, defense_cavalry, defense_archer,
    speed, carry_capacity, population,
    cost_wood, cost_clay, cost_iron,
    required_tech, required_tech_level, required_building_level,
    training_time_base, is_active, points
) VALUES
('spear', 'Spearman', 'Basic infantry, strong against cavalry.', 'barracks', 10, 15, 45, 20, 14, 25, 1, 50, 30, 10, NULL, 0, 1, 90, 1, 1),
('sword', 'Swordsman', 'Stronger infantry, solid versus other infantry.', 'barracks', 25, 50, 40, 30, 14, 15, 1, 30, 30, 70, NULL, 0, 1, 110, 1, 1),
('axe', 'Axeman', 'Powerful infantry attacker.', 'barracks', 40, 10, 5, 10, 14, 10, 1, 60, 30, 40, NULL, 0, 2, 95, 1, 2),
('archer', 'Archer', 'Ranged infantry for attack and defense.', 'barracks', 15, 50, 40, 5, 18, 10, 1, 100, 30, 60, 'improved_axe', 1, 5, 1800, 1, 2),
('spy', 'Scout', 'Fast cavalry scout.', 'stable', 0, 2, 2, 2, 9, 0, 2, 50, 50, 20, NULL, 0, 1, 900, 1, 2),
('light', 'Light Cavalry', 'Fast attacking cavalry.', 'stable', 130, 30, 40, 30, 9, 80, 4, 125, 100, 250, NULL, 0, 1, 400, 1, 4),
('heavy', 'Heavy Cavalry', 'Powerful cavalry for attack and defense.', 'stable', 150, 200, 150, 120, 11, 50, 6, 200, 150, 600, 'improved_sword', 2, 3, 900, 1, 6),
('marcher', 'Mounted Archer', 'Ranged cavalry unit.', 'stable', 120, 50, 40, 150, 10, 50, 5, 250, 100, 150, 'horseshoe', 1, 3, 700, 1, 5),
('ram', 'Ram', 'Siege unit for breaking walls.', 'workshop', 2, 20, 50, 20, 30, 0, 5, 300, 200, 200, NULL, 0, 1, 600, 1, 5),
('catapult', 'Catapult', 'Siege unit for destroying buildings.', 'workshop', 100, 100, 100, 100, 30, 0, 8, 320, 400, 100, 'improved_catapult', 1, 2, 900, 1, 8),
('noble', 'Nobleman', 'Reduces loyalty and conquers villages.', 'academy', 30, 100, 50, 50, 35, 0, 100, 40000, 50000, 50000, NULL, 0, 1, 18000, 1, 80),
('paladin', 'Paladin', 'Heroic leader that boosts armies.', 'statue', 150, 250, 200, 180, 10, 100, 20, 20000, 20000, 40000, NULL, 0, 1, 3600, 1, 10);

INSERT INTO research_types (internal_name, name, description, building_type, required_building_level, cost_wood, cost_clay, cost_iron, research_time_base, research_time_factor, max_level, is_active, prerequisite_research_id, prerequisite_research_level) VALUES
('improved_axe', 'Improved Axe', 'Increases infantry attack by 10% per level.', 'smithy', 1, 180, 150, 220, 3600, 1.2, 3, 1, NULL, NULL),
('improved_armor', 'Improved Armor', 'Increases infantry defense by 10% per level.', 'smithy', 2, 200, 180, 240, 4200, 1.2, 3, 1, NULL, NULL),
('improved_sword', 'Improved Sword', 'Increases cavalry attack by 10% per level.', 'smithy', 3, 220, 200, 260, 4800, 1.2, 3, 1, NULL, NULL),
('horseshoe', 'Horseshoes', 'Increases cavalry speed by 10% per level.', 'smithy', 4, 240, 220, 280, 5400, 1.2, 3, 1, NULL, NULL),
('improved_catapult', 'Improved Catapult', 'Increases catapult damage by 10% per level.', 'smithy', 5, 300, 280, 350, 6000, 1.2, 3, 1, NULL, NULL),
('spying', 'Espionage', 'Enables more detailed scouting reports.', 'academy', 1, 400, 600, 500, 7200, 1.2, 3, 1, NULL, NULL),
('improved_maps', 'Improved Maps', 'Expands visible map range.', 'academy', 2, 500, 700, 600, 8400, 1.2, 3, 1, NULL, NULL),
('military_tactics', 'Military Tactics', 'Raises troop morale by 5% per level.', 'academy', 3, 600, 800, 700, 9600, 1.2, 3, 1, NULL, NULL);

INSERT OR IGNORE INTO achievements (internal_name, name, description, category, condition_type, condition_target, condition_value, reward_wood, reward_clay, reward_iron, reward_points) VALUES
('first_steps', 'A New Beginning', 'Establish your first village and start constructing the Town Hall.', 'progression', 'building_level', 'main_building', 1, 150, 150, 150, 2),
('town_hall_lvl5', 'Organized Village', 'Upgrade the Town Hall to level 5.', 'progression', 'building_level', 'main_building', 5, 350, 350, 350, 4),
('fortified', 'Fortified', 'Build the Wall to level 3.', 'defense', 'building_level', 'wall', 3, 250, 350, 200, 3),
('resource_keeper', 'Well Stocked', 'Hold at least 5,000 of each resource in one village.', 'economy', 'resource_stock', 'balanced', 5000, 500, 500, 500, 2),
('recruiter_50', 'Drill Sergeant', 'Train a total of 50 units.', 'military', 'units_trained', 'any', 50, 300, 300, 200, 3),
('recruiter_200', 'Army Quartermaster', 'Train a total of 200 units.', 'military', 'units_trained', 'any', 200, 600, 600, 400, 5);

-- Guides / documentation
CREATE TABLE IF NOT EXISTS guides (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    summary TEXT NOT NULL,
    body_html TEXT NOT NULL,
    tags TEXT DEFAULT '',
    category TEXT DEFAULT 'general',
    status TEXT NOT NULL DEFAULT 'draft',
    version INTEGER NOT NULL DEFAULT 1,
    locale TEXT NOT NULL DEFAULT 'en',
    author_id INTEGER NULL,
    reviewer_id INTEGER NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
