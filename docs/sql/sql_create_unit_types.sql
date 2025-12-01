DROP TABLE IF EXISTS `unit_types`;

-- Create unit types table
CREATE TABLE IF NOT EXISTS `unit_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `internal_name` VARCHAR(50) NOT NULL UNIQUE, -- e.g. 'spear', 'sword', 'axe', 'archer'
  `name` VARCHAR(100) NOT NULL, -- Unit name (currently using the existing name column)
  `description` TEXT,
  `building_type` VARCHAR(50) NULL, -- internal name of the required recruitment building (e.g. 'barracks', 'stable')
  `attack` INT(11) DEFAULT 0,
  `defense` INT(11) DEFAULT 0,
  `speed` INT(11) DEFAULT 0 COMMENT 'Speed in fields per hour',
  `carry_capacity` INT(11) DEFAULT 0 COMMENT 'Resource carry capacity',
  `population` INT(11) DEFAULT 1 COMMENT 'Population cost per unit',
  `wood_cost` INT(11) DEFAULT 0,
  `clay_cost` INT(11) DEFAULT 0,
  `iron_cost` INT(11) DEFAULT 0,
  `required_tech` VARCHAR(50) NULL, -- internal name of required tech
  `required_tech_level` INT(11) DEFAULT 0, -- required tech level
  `required_building_level` INT(11) DEFAULT 0, -- required building level
  `training_time_base` INT(11) DEFAULT 0 COMMENT 'Recruitment time in seconds',
  `is_active` TINYINT(1) DEFAULT 1, -- Whether the unit is active
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
