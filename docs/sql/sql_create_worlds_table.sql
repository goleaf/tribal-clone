CREATE TABLE IF NOT EXISTS worlds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    world_speed FLOAT NOT NULL DEFAULT 1.0,
    troop_speed FLOAT NOT NULL DEFAULT 1.0,
    enable_archer TINYINT(1) NOT NULL DEFAULT 1,
    enable_paladin TINYINT(1) NOT NULL DEFAULT 1,
    enable_paladin_weapons TINYINT(1) NOT NULL DEFAULT 1,
    tech_mode VARCHAR(32) NOT NULL DEFAULT 'normal',
    tribe_member_limit INT DEFAULT NULL,
    victory_type VARCHAR(64) DEFAULT NULL,
    victory_value INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); 
