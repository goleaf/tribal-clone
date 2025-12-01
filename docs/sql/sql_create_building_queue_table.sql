CREATE TABLE IF NOT EXISTS `building_queue` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    village_id INT NOT NULL,
    village_building_id INT NOT NULL, -- ID entry in village_buildings
    building_type_id INT NOT NULL, -- ID of the building type from building_types
    level INT NOT NULL, -- Level after the build finishes
    starts_at DATETIME NOT NULL, -- Build start time
    finish_time DATETIME NOT NULL, -- Build finish time
    is_demolition TINYINT(1) NOT NULL DEFAULT 0, -- 1 if this queue item is a demolition
    refund_wood INT NOT NULL DEFAULT 0,
    refund_clay INT NOT NULL DEFAULT 0,
    refund_iron INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (village_building_id) REFERENCES village_buildings(id) ON DELETE CASCADE,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE
);
