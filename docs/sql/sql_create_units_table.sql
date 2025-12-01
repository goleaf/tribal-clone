-- Create unit types table (unit definitions)
CREATE TABLE IF NOT EXISTS `unit_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `internal_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `building_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT 'barracks, stable, garage',
  `attack` int(11) NOT NULL DEFAULT 0,
  `defense` int(11) NOT NULL DEFAULT 0,
  `defense_cavalry` int(11) NOT NULL DEFAULT 0,
  `defense_archer` int(11) NOT NULL DEFAULT 0,
  `speed` int(11) NOT NULL DEFAULT 0,
  `carry_capacity` int(11) NOT NULL DEFAULT 0,
  `population` int(11) NOT NULL DEFAULT 1,
  `cost_wood` int(11) NOT NULL DEFAULT 0,
  `cost_clay` int(11) NOT NULL DEFAULT 0,
  `cost_iron` int(11) NOT NULL DEFAULT 0,
  `required_tech` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'internal_name of required research',
  `required_tech_level` int(11) NOT NULL DEFAULT 0,
  `required_building_level` int(11) NOT NULL DEFAULT 1,
  `training_time_base` int(11) NOT NULL DEFAULT 0 COMMENT 'Base training time in seconds',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `internal_name` (`internal_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Seed base unit types
INSERT INTO `unit_types` (
  `internal_name`, `name`, `description`, `building_type`,
  `attack`, `defense`, `defense_cavalry`, `defense_archer`,
  `speed`, `carry_capacity`, `population`,
  `cost_wood`, `cost_clay`, `cost_iron`,
  `required_tech`, `required_tech_level`, `required_building_level`,
  `training_time_base`, `is_active`
) VALUES
-- Barracks
('spear', 'Spearman', 'Basic infantry, strong against cavalry.', 'barracks', 10, 15, 45, 20, 14, 25, 1, 50, 30, 10, NULL, 0, 1, 90, 1),
('sword', 'Swordsman', 'Stronger infantry, solid versus other infantry.', 'barracks', 25, 50, 40, 30, 14, 15, 1, 30, 30, 70, NULL, 0, 1, 110, 1),
('axe', 'Axeman', 'Powerful infantry attacker.', 'barracks', 40, 10, 5, 10, 14, 10, 1, 60, 30, 40, NULL, 0, 2, 95, 1),
('archer', 'Archer', 'Ranged infantry for attack and defense.', 'barracks', 15, 50, 40, 5, 18, 10, 1, 100, 30, 60, 'improved_axe', 1, 5, 1800, 1),

-- Stable
('spy', 'Scout', 'Fast cavalry scout.', 'stable', 0, 2, 2, 2, 9, 0, 2, 50, 50, 20, NULL, 0, 1, 900, 1),
('light', 'Light Cavalry', 'Fast attacking cavalry.', 'stable', 130, 30, 40, 30, 9, 80, 4, 125, 100, 250, NULL, 0, 1, 400, 1),
('heavy', 'Heavy Cavalry', 'Powerful cavalry for attack and defense.', 'stable', 150, 200, 150, 120, 11, 50, 6, 200, 150, 600, 'improved_sword', 2, 3, 900, 1),
('marcher', 'Mounted Archer', 'Ranged cavalry unit.', 'stable', 120, 50, 40, 150, 10, 50, 5, 250, 100, 150, 'horseshoe', 1, 3, 700, 1),

-- Workshop
('ram', 'Ram', 'Siege unit for breaking walls.', 'workshop', 2, 20, 50, 20, 30, 0, 5, 300, 200, 200, NULL, 0, 1, 600, 1),
('catapult', 'Catapult', 'Siege unit for destroying buildings.', 'workshop', 100, 100, 100, 100, 30, 0, 8, 320, 400, 100, 'improved_catapult', 1, 2, 900, 1),

-- Academy / Statue
('noble', 'Nobleman', 'Reduces loyalty and conquers villages.', 'academy', 30, 100, 50, 50, 35, 0, 100, 40000, 50000, 50000, NULL, 0, 1, 18000, 1),
('paladin', 'Paladin', 'Heroic leader that boosts armies.', 'statue', 150, 250, 200, 180, 10, 100, 20, 20000, 20000, 40000, NULL, 0, 1, 3600, 1);

-- Village units table
CREATE TABLE IF NOT EXISTS `village_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `village_id` int(11) NOT NULL,
  `unit_type_id` int(11) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `village_unit_unique` (`village_id`, `unit_type_id`),
  FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`unit_type_id`) REFERENCES `unit_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Recruitment queue table
CREATE TABLE IF NOT EXISTS `unit_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `village_id` int(11) NOT NULL,
  `unit_type_id` int(11) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 1,
  `count_finished` int(11) NOT NULL DEFAULT 0,
  `started_at` int(11) NOT NULL,
  `finish_at` int(11) NOT NULL,
  `building_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT 'barracks, stable, garage',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`unit_type_id`) REFERENCES `unit_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
