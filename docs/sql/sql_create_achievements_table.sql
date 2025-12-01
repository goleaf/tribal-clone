DROP TABLE IF EXISTS `user_achievements`;
DROP TABLE IF EXISTS `achievements`;

CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    internal_name VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'general',
    condition_type VARCHAR(100) NOT NULL,
    condition_target VARCHAR(100) DEFAULT NULL,
    condition_value INT NOT NULL,
    reward_wood INT NOT NULL DEFAULT 0,
    reward_clay INT NOT NULL DEFAULT 0,
    reward_iron INT NOT NULL DEFAULT 0,
    reward_points INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    progress INT NOT NULL DEFAULT 0,
    unlocked TINYINT(1) NOT NULL DEFAULT 0,
    unlocked_at TIMESTAMP NULL DEFAULT NULL,
    reward_claimed TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE KEY user_achievement_unique (user_id, achievement_id)
);

INSERT IGNORE INTO achievements (internal_name, name, description, category, condition_type, condition_target, condition_value, reward_wood, reward_clay, reward_iron, reward_points) VALUES
('first_steps', 'A New Beginning', 'Establish your first village and start constructing the Town Hall.', 'progression', 'building_level', 'main_building', 1, 150, 150, 150, 2),
('town_hall_lvl5', 'Organized Village', 'Upgrade the Town Hall to level 5.', 'progression', 'building_level', 'main_building', 5, 350, 350, 350, 4),
('fortified', 'Fortified', 'Build the Wall to level 3.', 'defense', 'building_level', 'wall', 3, 250, 350, 200, 3),
('resource_keeper', 'Well Stocked', 'Hold at least 5,000 of each resource in one village.', 'economy', 'resource_stock', 'balanced', 5000, 500, 500, 500, 2),
('recruiter_50', 'Drill Sergeant', 'Train a total of 50 units.', 'military', 'units_trained', 'any', 50, 300, 300, 200, 3),
('recruiter_200', 'Army Quartermaster', 'Train a total of 200 units.', 'military', 'units_trained', 'any', 200, 600, 600, 400, 5);
