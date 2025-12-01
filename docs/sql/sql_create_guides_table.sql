CREATE TABLE IF NOT EXISTS `guides` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    tags VARCHAR(255) DEFAULT '',
    category VARCHAR(100) DEFAULT 'general',
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    version INT NOT NULL DEFAULT 1,
    locale VARCHAR(10) NOT NULL DEFAULT 'en',
    author_id INT NULL,
    reviewer_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_guides_status (status),
    KEY idx_guides_category (category),
    KEY idx_guides_locale (locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
