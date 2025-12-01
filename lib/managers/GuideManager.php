<?php
declare(strict_types=1);

class GuideManager
{
    private $conn;
    private bool $schemaEnsured = false;
    private string $driver;

    public function __construct($conn)
    {
        $this->conn = $conn;
        if (is_object($conn) && get_class($conn) === 'SQLiteAdapter') {
            $this->driver = 'sqlite';
        } else {
            $this->driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
        }
    }

    /**
     * Ensures the guides tables exist.
     */
    public function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $guidesSql = $this->driver === 'sqlite'
            ? "CREATE TABLE IF NOT EXISTS guides (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                summary TEXT NOT NULL,
                body_html TEXT NOT NULL,
                tags TEXT DEFAULT '',
                category TEXT DEFAULT 'general',
                status TEXT NOT NULL DEFAULT 'draft',
                version INTEGER NOT NULL DEFAULT 1,
                locale TEXT NOT NULL DEFAULT 'en',
                author_id INTEGER NULL,
                reviewer_id INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
            : "CREATE TABLE IF NOT EXISTS guides (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $this->conn->query($guidesSql);

        $this->schemaEnsured = true;
    }

    private function slugify(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
        return $slug ?: ('guide-' . bin2hex(random_bytes(3)));
    }

    private function normalizeTags(string|array $tags): string
    {
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }
        $tags = array_slice(array_unique(array_map('strtolower', $tags)), 0, 8);
        return implode(',', $tags);
    }

    public function createGuide(
        string $title,
        string $summary,
        string $bodyHtml,
        string|array $tags = '',
        string $category = 'general',
        string $status = 'draft',
        ?int $authorId = null,
        string $locale = 'en'
    ): array {
        $this->ensureSchema();

        $slug = $this->slugify($title);
        $tagsString = $this->normalizeTags($tags);
        $status = in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft';

        $stmt = $this->conn->prepare("
            INSERT INTO guides (slug, title, summary, body_html, tags, category, status, author_id, locale)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            return ['success' => false, 'error' => $this->conn->error ?? 'Prepare failed'];
        }

        $stmt->bind_param("sssssssis", $slug, $title, $summary, $bodyHtml, $tagsString, $category, $status, $authorId, $locale);
        $ok = $stmt->execute();
        if (!$ok) {
            return ['success' => false, 'error' => $stmt->error ?? 'Insert failed'];
        }

        return [
            'success' => true,
            'id' => $stmt->insert_id,
            'slug' => $slug,
        ];
    }

    public function updateGuide(int $id, array $data): bool
    {
        $this->ensureSchema();

        $fields = [];
        $params = [];
        $types = '';

        $allowed = ['title', 'summary', 'body_html', 'tags', 'category', 'status', 'version', 'locale', 'reviewer_id'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "{$key} = ?";
                $params[] = $key === 'version' ? (int)$data[$key] : $data[$key];
                $types .= $key === 'version' || $key === 'reviewer_id' ? 'i' : 's';
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE guides SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        return $ok && ($stmt->affected_rows >= 0);
    }

    public function getGuideBySlug(string $slug, bool $includeDraft = false): ?array
    {
        $this->ensureSchema();

        $sql = "SELECT * FROM guides WHERE slug = ?";
        if (!$includeDraft) {
            $sql .= " AND status = 'published'";
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }

    public function getGuideById(int $id, bool $includeDraft = false): ?array
    {
        $this->ensureSchema();

        $sql = "SELECT * FROM guides WHERE id = ?";
        if (!$includeDraft) {
            $sql .= " AND status = 'published'";
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }

    /**
     * Lists guides with optional filters.
     */
    public function searchGuides(
        string $query = '',
        ?string $category = null,
        array $tags = [],
        array $statuses = ['published'],
        int $limit = 20,
        int $offset = 0,
        string $locale = 'en'
    ): array {
        $this->ensureSchema();

        $conditions = [];
        $params = [];
        $types = '';

        if (!empty($statuses)) {
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $conditions[] = "status IN ($placeholders)";
            foreach ($statuses as $status) {
                $params[] = $status;
                $types .= 's';
            }
        }

        if ($category) {
            $conditions[] = "category = ?";
            $params[] = $category;
            $types .= 's';
        }

        if ($locale) {
            $conditions[] = "locale = ?";
            $params[] = $locale;
            $types .= 's';
        }

        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $conditions[] = "tags LIKE ?";
                $params[] = '%' . $tag . '%';
                $types .= 's';
            }
        }

        if ($query !== '') {
            $conditions[] = "(title LIKE ? OR summary LIKE ? OR body_html LIKE ?)";
            $like = '%' . $query . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $sql = "SELECT id, slug, title, summary, tags, category, status, locale, updated_at, created_at FROM guides {$where} ORDER BY updated_at DESC LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $guides = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $guides[] = $row;
            }
        }

        return $guides;
    }

    public function countGuides(array $statuses = ['published']): int
    {
        $this->ensureSchema();
        $conditions = [];
        $params = [];
        $types = '';

        if (!empty($statuses)) {
            $conditions[] = "status IN (" . implode(',', array_fill(0, count($statuses), '?')) . ")";
            foreach ($statuses as $status) {
                $params[] = $status;
                $types .= 's';
            }
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT COUNT(*) AS cnt FROM guides {$where}";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && ($row = $result->fetch_assoc())) {
            return (int)$row['cnt'];
        }
        return 0;
    }

    /**
     * Seeds a couple of guides if none exist.
     */
    public function seedDefaults(?int $authorId = null): void
    {
        $this->ensureSchema();
        if ($this->countGuides(['draft', 'published', 'archived']) > 0) {
            return;
        }

        $samples = [
            [
                'title' => 'Getting started: first village',
                'summary' => 'Basics of resources, build order, and protection.',
                'body' => '<p>Focus on warehouse and farm, then sawmill/clay/iron. Keep troops minimal until the wall is built.</p>',
                'tags' => 'basics,economy',
                'category' => 'basics'
            ],
            [
                'title' => 'Combat primer: scouts, wall, rams',
                'summary' => 'How scouting, walls, and siege units interact.',
                'body' => '<p>Scouts reveal armies and buildings. Walls add defense; rams reduce effective wall level during battle. Bring enough rams to offset the wall.</p>',
                'tags' => 'combat,scout,wall',
                'category' => 'combat'
            ],
            [
                'title' => 'Research and upgrades',
                'summary' => 'Academy research unlocks advanced units; costs resources and time, once per village.',
                'body' => '<ul>'
                    . '<li><strong>Academy research</strong>: unlocks advanced unit types; each research has building level prerequisites.</li>'
                    . '<li><strong>Costs and timers</strong>: research consumes resources and takes time to finish; one research at a time per village.</li>'
                    . '<li><strong>One-time unlocks</strong>: research is done once per village, then units stay unlocked.</li>'
                    . '<li><strong>Noble research</strong>: requires minted coins before unlocking nobleman training.</li>'
                    . '</ul>',
                'tags' => 'research,academy,noble,upgrades',
                'category' => 'research'
            ]
        ];

        foreach ($samples as $sample) {
            $this->createGuide(
                $sample['title'],
                $sample['summary'],
                $sample['body'],
                $sample['tags'],
                $sample['category'],
                'published',
                $authorId
            );
        }
    }
}
