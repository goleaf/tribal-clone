CREATE TABLE IF NOT EXISTS tribes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL UNIQUE,
    tag VARCHAR(12) NOT NULL UNIQUE,
    description TEXT,
    internal_text TEXT,
    founder_id INT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (founder_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tribe_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tribe_id INT NOT NULL,
    user_id INT NOT NULL UNIQUE,
    role ENUM('leader','member') NOT NULL DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tribe_id) REFERENCES tribes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tribe_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tribe_id INT NOT NULL,
    invited_user_id INT NOT NULL,
    inviter_id INT DEFAULT NULL,
    status ENUM('pending','accepted','declined','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uniq_invite (tribe_id, invited_user_id),
    FOREIGN KEY (tribe_id) REFERENCES tribes(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE SET NULL
);
