DROP TABLE IF EXISTS `building_requirements`;
DROP TABLE IF EXISTS `village_buildings`;
DROP TABLE IF EXISTS `building_types`;

CREATE TABLE IF NOT EXISTS building_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    internal_name VARCHAR(50) NOT NULL UNIQUE, -- e.g. 'main_building', 'barracks', 'warehouse', 'sawmill', 'clay_pit', 'iron_mine'
    name VARCHAR(100) NOT NULL, -- Display name (legacy column name)
    description TEXT,
    max_level INT DEFAULT 20,
    base_build_time_initial INT DEFAULT 900, -- Build time for the first level in seconds
    build_time_factor FLOAT DEFAULT 1.2, -- Multiplier for subsequent levels
    cost_wood_initial INT DEFAULT 100,
    cost_clay_initial INT DEFAULT 100,
    cost_iron_initial INT DEFAULT 100,
    cost_factor FLOAT DEFAULT 1.25, -- Cost multiplier for subsequent levels
    production_type VARCHAR(50) NULL, -- 'wood', 'clay', 'iron' or NULL if it does not produce resources
    production_initial INT NULL, -- Production at level 1
    production_factor FLOAT NULL, -- Production multiplier for subsequent levels
    -- Time reduction bonus (for the town hall)
    bonus_time_reduction_factor FLOAT DEFAULT 1.0 COMMENT 'Build time reduction factor (for Town Hall)'
);

-- Table holding building dependencies (requirements)
CREATE TABLE IF NOT EXISTS building_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_type_id INT NOT NULL,
    required_building VARCHAR(50) NOT NULL, -- internal_name of the required building
    required_level INT NOT NULL DEFAULT 1,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS village_buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    village_id INT NOT NULL,
    building_type_id INT NOT NULL,
    level INT DEFAULT 0, -- Current completed level
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE,
    UNIQUE (village_id, building_type_id) -- Each building type can appear only once per village
);

-- Seed basic building types
INSERT INTO `building_types` (`internal_name`, `name`, `description`, `max_level`, `base_build_time_initial`, `build_time_factor`, `cost_wood_initial`, `cost_clay_initial`, `cost_iron_initial`, `cost_factor`, `production_type`, `production_initial`, `production_factor`, `bonus_time_reduction_factor`) VALUES
('main_building', 'Town Hall', 'The town hall is the center of your village. Higher levels reduce building times.', 20, 900, 1.2, 90, 80, 70, 1.25, NULL, NULL, NULL, 0.95),
('sawmill', 'Timber Camp', 'Produces wood. Higher levels increase production.', 30, 600, 1.18, 50, 60, 40, 1.26, 'wood', 30, 1.16, 1.0),
('clay_pit', 'Clay Pit', 'Produces clay. Higher levels increase production.', 30, 600, 1.18, 65, 50, 40, 1.26, 'clay', 30, 1.16, 1.0),
('iron_mine', 'Iron Mine', 'Produces iron. Higher levels increase production.', 30, 720, 1.18, 75, 65, 60, 1.26, 'iron', 30, 1.16, 1.0),
('warehouse', 'Warehouse', 'Stores your resources. Higher levels increase capacity.', 30, 800, 1.15, 60, 50, 40, 1.22, NULL, 1000, 1.227, 1.0),
('farm', 'Farm', 'Increases village population capacity.', 30, 1000, 1.2, 80, 100, 70, 1.28, NULL, 240, 1.17, 1.0),
('barracks', 'Barracks', 'Train infantry units.', 25, 1200, 1.22, 200, 170, 90, 1.26, NULL, NULL, NULL, 1.0),
('stable', 'Stable', 'Train cavalry units.', 20, 1500, 1.25, 270, 240, 260, 1.28, NULL, NULL, NULL, 1.0),
('workshop', 'Workshop', 'Build siege engines.', 15, 2000, 1.3, 300, 320, 290, 1.3, NULL, NULL, NULL, 1.0),
('smithy', 'Smithy', 'Research and improve weapons and armor.', 20, 1100, 1.24, 180, 250, 220, 1.24, NULL, NULL, NULL, 1.0),
('market', 'Market', 'Trade resources with other players.', 25, 1300, 1.22, 150, 200, 130, 1.23, NULL, NULL, NULL, 1.0),
('wall', 'Wall', 'Provides defense against enemy attacks.', 20, 1400, 1.26, 100, 300, 200, 1.25, NULL, NULL, NULL, 1.0),
('academy', 'Academy', 'Research new technologies.', 20, 1600, 1.28, 260, 300, 220, 1.27, NULL, NULL, NULL, 1.0);

-- Building requirements
INSERT INTO `building_requirements` (`building_type_id`, `required_building`, `required_level`) VALUES
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
