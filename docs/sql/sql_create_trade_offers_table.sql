CREATE TABLE IF NOT EXISTS trade_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_village_id INT NOT NULL,
    offered_wood INT NOT NULL DEFAULT 0,
    offered_clay INT NOT NULL DEFAULT 0,
    offered_iron INT NOT NULL DEFAULT 0,
    requested_wood INT NOT NULL DEFAULT 0,
    requested_clay INT NOT NULL DEFAULT 0,
    requested_iron INT NOT NULL DEFAULT 0,
    merchants_required INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    accepted_village_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME NULL,
    completed_at DATETIME NULL,
    FOREIGN KEY (source_village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (accepted_village_id) REFERENCES villages(id) ON DELETE SET NULL
);
