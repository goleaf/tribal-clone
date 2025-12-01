PRAGMA foreign_keys = ON;

-- Reset existing tables (order matters because of FK constraints)
DROP TABLE IF EXISTS battle_report_units;
DROP TABLE IF EXISTS battle_reports;
DROP TABLE IF EXISTS attack_units;
DROP TABLE IF EXISTS attacks;
DROP TABLE IF EXISTS ai_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS trade_routes;
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
    ally_id INTEGER DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
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
    name_pl TEXT NOT NULL,
    description_pl TEXT,
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
    upgrade_level_to INTEGER DEFAULT NULL,
    upgrade_ends_at TEXT DEFAULT NULL,
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
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (village_building_id) REFERENCES village_buildings(id) ON DELETE CASCADE,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS unit_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    internal_name TEXT NOT NULL UNIQUE,
    name_pl TEXT NOT NULL,
    description_pl TEXT,
    building_type TEXT NOT NULL,
    attack INTEGER NOT NULL DEFAULT 0,
    defense INTEGER NOT NULL DEFAULT 0,
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
    name_pl TEXT NOT NULL,
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
    FOREIGN KEY (source_village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (target_village_id) REFERENCES villages(id) ON DELETE CASCADE
);

-- Seed data
INSERT INTO worlds (id, name, created_at) VALUES (1, 'Świat 1', CURRENT_TIMESTAMP);
INSERT OR IGNORE INTO users (id, username, email, password, is_admin, is_banned, created_at) VALUES (-1, 'Barbarzyńcy', 'barbarians@localhost', '', 0, 0, CURRENT_TIMESTAMP);

INSERT INTO building_types (internal_name, name_pl, description_pl, max_level, base_build_time_initial, build_time_factor, cost_wood_initial, cost_clay_initial, cost_iron_initial, cost_factor, production_type, production_initial, production_factor, bonus_time_reduction_factor, population_cost, base_points) VALUES
('main_building', 'Ratusz', 'Ratusz jest centralnym punktem Twojej wioski. Im wyższy poziom ratusza, tym szybciej możesz budować inne budynki.', 20, 900, 1.2, 90, 80, 70, 1.25, NULL, NULL, NULL, 0.95, 0, 1),
('sawmill', 'Tartak', 'Tartak produkuje drewno. Im wyższy poziom, tym większa produkcja drewna.', 30, 600, 1.18, 50, 60, 40, 1.26, 'wood', 30, 1.16, 1.0, 0, 1),
('clay_pit', 'Cegielnia', 'Cegielnia produkuje glinę. Im wyższy poziom, tym większa produkcja gliny.', 30, 600, 1.18, 65, 50, 40, 1.26, 'clay', 30, 1.16, 1.0, 0, 1),
('iron_mine', 'Huta Żelaza', 'Huta żelaza produkuje żelazo. Im wyższy poziom, tym większa produkcja żelaza.', 30, 720, 1.18, 75, 65, 60, 1.26, 'iron', 30, 1.16, 1.0, 0, 1),
('warehouse', 'Magazyn', 'Magazyn przechowuje Twoje surowce. Im wyższy poziom, tym większa pojemność magazynu.', 30, 800, 1.15, 60, 50, 40, 1.22, NULL, 1000, 1.227, 1.0, 0, 1),
('farm', 'Farma', 'Farma zwiększa populację wioski. Im wyższy poziom, tym więcej mieszkańców może żyć w wiosce.', 30, 1000, 1.2, 80, 100, 70, 1.28, NULL, 240, 1.17, 1.0, 0, 1),
('barracks', 'Koszary', 'W koszarach możesz szkolić jednostki piechoty.', 25, 1200, 1.22, 200, 170, 90, 1.26, NULL, NULL, NULL, 1.0, 0, 1),
('stable', 'Stajnia', 'W stajni możesz szkolić jednostki kawalerii.', 20, 1500, 1.25, 270, 240, 260, 1.28, NULL, NULL, NULL, 1.0, 0, 1),
('workshop', 'Warsztat', 'W warsztacie możesz budować machiny oblężnicze.', 15, 2000, 1.3, 300, 320, 290, 1.3, NULL, NULL, NULL, 1.0, 0, 1),
('smithy', 'Kuźnia', 'W kuźni możesz ulepszać swoją broń i zbroje.', 20, 1100, 1.24, 180, 250, 220, 1.24, NULL, NULL, NULL, 1.0, 0, 1),
('market', 'Targ', 'Na targu możesz handlować surowcami z innymi graczami.', 25, 1300, 1.22, 150, 200, 130, 1.23, NULL, NULL, NULL, 1.0, 0, 1),
('wall', 'Mur', 'Mur zapewnia ochronę przed atakami wroga.', 20, 1400, 1.26, 100, 300, 200, 1.25, NULL, NULL, NULL, 1.0, 0, 1),
('academy', 'Akademia', 'W akademii możesz prowadzić badania nowych technologii.', 20, 1600, 1.28, 260, 300, 220, 1.27, NULL, NULL, NULL, 1.0, 0, 1);

INSERT INTO building_requirements (building_type_id, required_building, required_level) VALUES
((SELECT id FROM building_types WHERE internal_name = 'barracks'), 'main_building', 3),
((SELECT id FROM building_types WHERE internal_name = 'stable'), 'barracks', 3),
((SELECT id FROM building_types WHERE internal_name = 'stable'), 'smithy', 2),
((SELECT id FROM building_types WHERE internal_name = 'workshop'), 'stable', 3),
((SELECT id FROM building_types WHERE internal_name = 'workshop'), 'smithy', 3),
((SELECT id FROM building_types WHERE internal_name = 'smithy'), 'main_building', 3),
((SELECT id FROM building_types WHERE internal_name = 'market'), 'main_building', 3),
((SELECT id FROM building_types WHERE internal_name = 'market'), 'warehouse', 2),
((SELECT id FROM building_types WHERE internal_name = 'wall'), 'barracks', 1),
((SELECT id FROM building_types WHERE internal_name = 'academy'), 'main_building', 5),
((SELECT id FROM building_types WHERE internal_name = 'academy'), 'smithy', 1);

INSERT INTO unit_types (internal_name, name_pl, description_pl, building_type, attack, defense, speed, carry_capacity, population, cost_wood, cost_clay, cost_iron, required_tech, required_tech_level, required_building_level, training_time_base, is_active, points) VALUES
('spear', 'Pikinier', 'Podstawowa jednostka piechoty, dobra do obrony przed kawalerią.', 'barracks', 10, 15, 18, 25, 1, 50, 30, 10, NULL, 0, 1, 900, 1, 1),
('sword', 'Miecznik', 'Silniejsza jednostka piechoty, dobra do obrony przed piechota.', 'barracks', 25, 50, 22, 15, 1, 30, 30, 70, NULL, 0, 1, 1300, 1, 1),
('axe', 'Topornik', 'Silna jednostka piechoty do ataku.', 'barracks', 40, 10, 18, 10, 1, 60, 30, 40, NULL, 0, 2, 1000, 1, 2),
('archer', 'Łucznik', 'Jednostka dystansowa do obrony i ataku.', 'barracks', 15, 50, 18, 10, 1, 100, 30, 60, 'improved_axe', 1, 5, 1800, 1, 2),
('spy', 'Zwiadowca', 'Szybka jednostka kawalerii do zwiadu.', 'stable', 0, 2, 9, 0, 2, 50, 50, 20, NULL, 0, 1, 900, 1, 2),
('light', 'Lekka kawaleria', 'Szybka jednostka kawalerii do ataku.', 'stable', 130, 30, 10, 80, 4, 125, 100, 250, NULL, 0, 3, 1800, 1, 4),
('heavy', 'Ciężka kawaleria', 'Silna jednostka kawalerii do ataku i obrony.', 'stable', 150, 200, 11, 50, 6, 200, 150, 600, 'improved_sword', 2, 10, 3600, 1, 6),
('marcher', 'Konny łucznik', 'Jednostka dystansowa na koniu.', 'stable', 120, 40, 10, 50, 5, 250, 100, 150, 'horseshoe', 1, 5, 2400, 1, 5),
('ram', 'Taran', 'Oblężnicza jednostka do niszczenia murów.', 'garage', 2, 20, 30, 0, 5, 300, 200, 100, NULL, 0, 1, 4800, 1, 5),
('catapult', 'Katapulta', 'Oblężnicza jednostka do niszczenia budynków.', 'garage', 100, 100, 30, 0, 8, 320, 400, 100, 'improved_catapult', 1, 2, 7200, 1, 8);

INSERT INTO research_types (internal_name, name_pl, description, building_type, required_building_level, cost_wood, cost_clay, cost_iron, research_time_base, research_time_factor, max_level, is_active, prerequisite_research_id, prerequisite_research_level) VALUES
('improved_axe', 'Ulepszona Siekiera', 'Zwiększa atak piechoty o 10% za każdy poziom.', 'smithy', 1, 180, 150, 220, 3600, 1.2, 3, 1, NULL, NULL),
('improved_armor', 'Ulepszona Zbroja', 'Zwiększa obronę piechoty o 10% za każdy poziom.', 'smithy', 2, 200, 180, 240, 4200, 1.2, 3, 1, NULL, NULL),
('improved_sword', 'Ulepszony Miecz', 'Zwiększa atak kawalerii o 10% za każdy poziom.', 'smithy', 3, 220, 200, 260, 4800, 1.2, 3, 1, NULL, NULL),
('horseshoe', 'Podkowy', 'Zwiększa szybkość kawalerii o 10% za każdy poziom.', 'smithy', 4, 240, 220, 280, 5400, 1.2, 3, 1, NULL, NULL),
('improved_catapult', 'Ulepszony Katapult', 'Zwiększa obrażenia katapult o 10% za każdy poziom.', 'smithy', 5, 300, 280, 350, 6000, 1.2, 3, 1, NULL, NULL),
('spying', 'Szpiegostwo', 'Pozwala na dokładniejsze raporty zwiadowcze.', 'academy', 1, 400, 600, 500, 7200, 1.2, 3, 1, NULL, NULL),
('improved_maps', 'Ulepszone Mapy', 'Zwiększa zasięg widoczności mapy.', 'academy', 2, 500, 700, 600, 8400, 1.2, 3, 1, NULL, NULL),
('military_tactics', 'Taktyka Wojenna', 'Zwiększa morale wojsk o 5% za każdy poziom.', 'academy', 3, 600, 800, 700, 9600, 1.2, 3, 1, NULL, NULL);
