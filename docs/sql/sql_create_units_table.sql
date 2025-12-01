-- Create unit types table (unit definitions)
CREATE TABLE IF NOT EXISTS `unit_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `internal_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `building_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT 'barracks, stable, workshop/statue/academy',
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
  `points` int(11) DEFAULT 1,
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
  `training_time_base`, `is_active`, `points`
) VALUES
('tribesman', 'Tribesman', 'Basic infantry unit, defensive backbone.', 'barracks', 12, 20, 15, 10, 18, 25, 1, 40, 30, 20, NULL, 0, 1, 300, 1, 1),
('spearguard', 'Spearguard', 'Anti-cavalry specialist, defensive formation fighter.', 'barracks', 10, 30, 60, 20, 20, 15, 1, 50, 60, 30, 'spear_training', 1, 5, 420, 1, 1),
('axe_warrior', 'Axe Warrior', 'Offensive infantry, breakthrough unit.', 'barracks', 45, 20, 15, 10, 18, 35, 1, 70, 40, 60, 'advanced_weapons', 1, 6, 480, 1, 2),
('bowman', 'Bowman', 'Basic ranged unit, defensive support.', 'barracks', 25, 10, 20, 5, 18, 20, 1, 60, 30, 40, 'archery', 1, 2, 600, 1, 1),
('slinger', 'Slinger', 'Anti-armor ranged, siege support.', 'barracks', 25, 12, 30, 8, 18, 15, 1, 40, 40, 20, 'ranged_warfare', 1, 4, 540, 1, 1),
('scout', 'Scout', 'Intelligence gathering, fastest unit.', 'barracks', 0, 2, 2, 2, 5, 0, 1, 50, 30, 20, NULL, 0, 1, 180, 1, 1),
('raider', 'Raider', 'Fast raiding cavalry.', 'stable', 60, 20, 15, 15, 8, 80, 2, 100, 50, 80, 'horse_breeding', 1, 1, 600, 1, 3),
('lancer', 'Lancer', 'Heavy cavalry, shock troops.', 'stable', 150, 60, 40, 30, 9, 40, 3, 150, 120, 200, 'heavy_cavalry', 1, 5, 1200, 1, 5),
('horse_archer', 'Horse Archer', 'Mobile ranged harassment, skirmisher.', 'stable', 80, 35, 30, 40, 9, 30, 3, 140, 80, 160, 'mounted_archery', 1, 10, 1200, 1, 5),
('supply_cart', 'Supply Cart', 'Army logistics, extended campaigns.', 'workshop', 0, 10, 5, 5, 30, 500, 4, 200, 200, 100, 'logistics', 1, 3, 1800, 1, 4),
('battering_ram', 'Battering Ram', 'Wall breaching, gate destruction.', 'workshop', 2, 20, 50, 20, 30, 0, 5, 300, 200, 200, 'siege_warfare', 1, 8, 2400, 1, 5),
('catapult', 'Catapult', 'Long-range siege, wall destruction.', 'workshop', 100, 100, 100, 100, 35, 0, 8, 320, 400, 150, 'artillery', 1, 12, 3000, 1, 8),
('berserker', 'Berserker', 'Elite shock infantry, morale breaker.', 'barracks', 200, 60, 40, 40, 15, 30, 2, 160, 120, 150, 'battle_rage', 1, 15, 2400, 1, 6),
('shieldmaiden', 'Shieldmaiden', 'Elite defensive unit, formation anchor.', 'barracks', 60, 220, 220, 180, 20, 20, 2, 120, 160, 150, 'elite_training', 1, 15, 2400, 1, 6),
('warlord', 'Warlord', 'Army commander, cavalry elite.', 'stable', 220, 180, 150, 150, 9, 50, 5, 400, 300, 300, 'leadership', 1, 20, 7200, 1, 10),
('rune_priest', 'Rune Priest', 'Mystical support, morale and blessing.', 'church', 0, 30, 30, 30, 20, 0, 3, 150, 150, 200, 'divine_blessing', 1, 15, 3600, 1, 4);

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
