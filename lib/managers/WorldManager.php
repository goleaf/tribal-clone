<?php
declare(strict_types=1);

/**
 * Provides per-world configuration such as speed, unit availability, and victory conditions.
 * Falls back to config defaults when columns/data are missing.
 */
class WorldManager
{
    private $conn;
    private array $columnCache = [];
    private array $settingsCache = [];
    private array $knownColumns = [
        'world_speed' => "REAL NOT NULL DEFAULT 1.0",
        'troop_speed' => "REAL NOT NULL DEFAULT 1.0",
        'build_speed' => "REAL NOT NULL DEFAULT 1.0",
        'train_speed' => "REAL NOT NULL DEFAULT 1.0",
        'research_speed' => "REAL NOT NULL DEFAULT 1.0",
        'night_bonus_enabled' => "INTEGER NOT NULL DEFAULT 0",
        'night_start_hour' => "INTEGER NOT NULL DEFAULT 22",
        'night_end_hour' => "INTEGER NOT NULL DEFAULT 6",
        'enable_archer' => "INTEGER NOT NULL DEFAULT 1",
        'enable_paladin' => "INTEGER NOT NULL DEFAULT 1",
        'enable_paladin_weapons' => "INTEGER NOT NULL DEFAULT 1",
        'tech_mode' => "TEXT NOT NULL DEFAULT 'normal'",
        'tribe_member_limit' => "INTEGER DEFAULT NULL",
        'victory_type' => "TEXT DEFAULT NULL",
        'victory_value' => "INTEGER DEFAULT NULL",
        'winner_tribe_id' => "INTEGER DEFAULT NULL",
        'victory_at' => "TEXT DEFAULT NULL"
    ];

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
    * Returns merged settings for a world (cached per request).
    */
    public function getSettings(int $worldId): array
    {
        if (isset($this->settingsCache[$worldId])) {
            return $this->settingsCache[$worldId];
        }

        $this->ensureSchema();
        $this->ensureDefaultWorld();

        $defaults = [
            'world_speed' => defined('WORLD_SPEED') ? (float)WORLD_SPEED : 1.0,
            'troop_speed' => defined('UNIT_SPEED_MULTIPLIER') ? (float)UNIT_SPEED_MULTIPLIER : 1.0,
            'build_speed' => 1.0,
            'train_speed' => 1.0,
            'research_speed' => 1.0,
            'night_bonus_enabled' => false,
            'night_start_hour' => 22,
            'night_end_hour' => 6,
            'enable_archer' => true,
            'enable_paladin' => true,
            'enable_paladin_weapons' => true,
            'tech_mode' => 'normal',
            'tribe_member_limit' => null,
            'victory_type' => null,
            'victory_value' => null,
        ];

        $columns = $this->getWorldColumns();
        $selectable = array_intersect(array_keys($defaults), $columns);

        if (!empty($selectable)) {
            $sql = "SELECT id, " . implode(',', $selectable) . " FROM worlds WHERE id = ? LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $worldId);
                if ($stmt->execute()) {
                    $row = $stmt->get_result()->fetch_assoc();
                    if ($row) {
                        foreach ($selectable as $key) {
                            if (array_key_exists($key, $row) && $row[$key] !== null) {
                                $val = $row[$key];
                                if (in_array($key, ['world_speed', 'troop_speed', 'build_speed', 'train_speed', 'research_speed'], true)) {
                                    $defaults[$key] = (float)$val;
                                } elseif ($key === 'tribe_member_limit' || $key === 'victory_value') {
                                    $defaults[$key] = $val === null ? null : (int)$val;
                                } elseif (in_array($key, ['enable_archer', 'enable_paladin', 'enable_paladin_weapons', 'night_bonus_enabled'], true)) {
                                    $defaults[$key] = (bool)$val;
                                } elseif (in_array($key, ['night_start_hour', 'night_end_hour'], true)) {
                                    $defaults[$key] = (int)$val;
                                } else {
                                    $defaults[$key] = $val;
                                }
                            }
                        }
                    }
                }
                $stmt->close();
            }
        }

        $this->settingsCache[$worldId] = $defaults;
        return $defaults;
    }

    public function getWorldSpeed(int $worldId = CURRENT_WORLD_ID): float
    {
        $settings = $this->getSettings($worldId);
        return max(0.1, (float)($settings['world_speed'] ?? 1.0));
    }

    public function getTroopSpeed(int $worldId = CURRENT_WORLD_ID): float
    {
        $settings = $this->getSettings($worldId);
        return max(0.1, (float)($settings['troop_speed'] ?? 1.0));
    }

    public function getBuildSpeed(int $worldId = CURRENT_WORLD_ID): float
    {
        $settings = $this->getSettings($worldId);
        return max(0.1, (float)($settings['build_speed'] ?? 1.0));
    }

    public function getTrainSpeed(int $worldId = CURRENT_WORLD_ID): float
    {
        $settings = $this->getSettings($worldId);
        return max(0.1, (float)($settings['train_speed'] ?? 1.0));
    }

    public function getResearchSpeed(int $worldId = CURRENT_WORLD_ID): float
    {
        $settings = $this->getSettings($worldId);
        return max(0.1, (float)($settings['research_speed'] ?? 1.0));
    }

    public function isNightBonusEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        return (bool)($this->getSettings($worldId)['night_bonus_enabled'] ?? false);
    }

    public function getNightBonusWindow(int $worldId = CURRENT_WORLD_ID): array
    {
        $settings = $this->getSettings($worldId);
        return [
            'start' => (int)($settings['night_start_hour'] ?? 22),
            'end' => (int)($settings['night_end_hour'] ?? 6),
        ];
    }

    public function isArcherEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        return (bool)($this->getSettings($worldId)['enable_archer'] ?? true);
    }

    public function isPaladinEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        return (bool)($this->getSettings($worldId)['enable_paladin'] ?? true);
    }

    public function isPaladinWeaponsEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        return (bool)($this->getSettings($worldId)['enable_paladin_weapons'] ?? true);
    }

    public function getTribeLimit(int $worldId = CURRENT_WORLD_ID): ?int
    {
        $limit = $this->getSettings($worldId)['tribe_member_limit'] ?? null;
        return $limit === null ? null : (int)$limit;
    }

    public function getVictorySettings(int $worldId = CURRENT_WORLD_ID): array
    {
        $settings = $this->getSettings($worldId);
        return [
            'type' => $settings['victory_type'] ?? null,
            'value' => $settings['victory_value'] ?? null,
        ];
    }

    /**
     * Returns column names for the worlds table to avoid hard failures when columns are missing.
     */
    private function getWorldColumns(): array
    {
        if (!empty($this->columnCache)) {
            return $this->columnCache;
        }

        $cols = [];
        $res = $this->conn->query("PRAGMA table_info('worlds')");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (isset($row['name'])) {
                    $cols[] = $row['name'];
                }
            }
        }
        $this->columnCache = $cols;
        return $cols;
    }

    /**
     * Adds missing world columns so per-world settings can be stored.
     */
    private function ensureSchema(): void
    {
        $existing = $this->getWorldColumns();
        $missing = array_diff(array_keys($this->knownColumns), $existing);
        if (empty($missing)) {
            return;
        }

        foreach ($missing as $col) {
            $definition = $this->knownColumns[$col] ?? null;
            if (!$definition) {
                continue;
            }
            $sql = "ALTER TABLE worlds ADD COLUMN {$col} {$definition}";
            $this->conn->query($sql);
        }
        // Reset cache so subsequent calls see new columns
        $this->columnCache = [];

        // Backfill defaults for existing rows where new columns are NULL
        $defaults = [
            'world_speed' => 1.0,
            'troop_speed' => 1.0,
            'build_speed' => 1.0,
            'train_speed' => 1.0,
            'research_speed' => 1.0,
            'night_bonus_enabled' => 0,
            'night_start_hour' => 22,
            'night_end_hour' => 6,
            'enable_archer' => 1,
            'enable_paladin' => 1,
            'enable_paladin_weapons' => 1,
            'tech_mode' => 'normal'
        ];
        foreach ($defaults as $col => $val) {
            $quotedVal = is_numeric($val) ? $val : ("'" . $this->conn->real_escape_string((string)$val) . "'");
            $this->conn->query("UPDATE worlds SET {$col} = {$quotedVal} WHERE {$col} IS NULL");
        }
    }

    /**
     * Ensures at least one world exists (id=1) so selection and defaults work.
     */
    private function ensureDefaultWorld(): void
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM worlds");
        if (!$stmt) {
            return;
        }
        $stmt->execute();
        $countRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (isset($countRow['cnt']) && (int)$countRow['cnt'] > 0) {
            return;
        }

        $stmtInsert = $this->conn->prepare("INSERT INTO worlds (name, world_speed, troop_speed, build_speed, train_speed, research_speed, night_bonus_enabled, night_start_hour, night_end_hour, enable_archer, enable_paladin, enable_paladin_weapons, tech_mode, tribe_member_limit, victory_type, victory_value) VALUES ('World 1', 1.0, 1.0, 1.0, 1.0, 1.0, 0, 22, 6, 1, 1, 1, 'normal', NULL, NULL, NULL)");
        if ($stmtInsert) {
            $stmtInsert->execute();
            $stmtInsert->close();
        }
    }

    /**
     * Checks if a world meets its victory condition; records winner if achieved.
     * Supports domination (victory_type = 'domination', victory_value = required % of villages).
     */
    public function checkVictory(int $worldId = CURRENT_WORLD_ID): array
    {
        $settings = $this->getSettings($worldId);
        $type = $settings['victory_type'] ?? null;
        $value = isset($settings['victory_value']) ? (int)$settings['victory_value'] : null;

        // Already finished?
        $existingWinner = $this->getWinner($worldId);
        if ($existingWinner !== null) {
            return ['achieved' => true, 'tribe_id' => $existingWinner['tribe_id'], 'victory_at' => $existingWinner['victory_at'], 'type' => $type];
        }

        $isTribeDomination = in_array($type, ['domination', 'tribe_domination', 'tribe_village_percent'], true);
        if (!$isTribeDomination || !$value || $value <= 0) {
            return ['achieved' => false, 'reason' => 'unsupported_or_unconfigured'];
        }

        // Total villages in world (includes barbarians/untribed)
        $total = 0;
        $stmtTotal = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM villages WHERE world_id = ?");
        if ($stmtTotal) {
            $stmtTotal->bind_param("i", $worldId);
            $stmtTotal->execute();
            $rowTotal = $stmtTotal->get_result()->fetch_assoc();
            $stmtTotal->close();
            $total = (int)($rowTotal['cnt'] ?? 0);
        }

        $tribeCounts = [];
        $stmt = $this->conn->prepare("
            SELECT tm.tribe_id, COUNT(*) AS villages
            FROM villages v
            JOIN users u ON u.id = v.user_id
            JOIN tribe_members tm ON tm.user_id = u.id
            WHERE v.world_id = ?
            GROUP BY tm.tribe_id
        ");
        if ($stmt) {
            $stmt->bind_param("i", $worldId);
            $stmt->execute();
            $res = $stmt->get_result();

            while ($row = $res->fetch_assoc()) {
                $tribeId = (int)$row['tribe_id'];
                $count = (int)$row['villages'];
                $tribeCounts[$tribeId] = $count;
            }
            $stmt->close();
        } else {
            return ['achieved' => false, 'reason' => 'query_failed'];
        }

        if ($total <= 0 || empty($tribeCounts)) {
            return ['achieved' => false, 'reason' => 'no_tribes'];
        }

        arsort($tribeCounts);
        $topTribeId = array_key_first($tribeCounts);
        $topCount = $tribeCounts[$topTribeId];
        $share = ($topCount / $total) * 100.0;

        if ($share >= $value) {
            $this->recordVictory($worldId, $topTribeId);
            return ['achieved' => true, 'tribe_id' => $topTribeId, 'share' => $share, 'type' => $type];
        }

        return ['achieved' => false, 'share' => $share, 'required' => $value];
    }

    public function recordVictory(int $worldId, int $tribeId): void
    {
        $stmt = $this->conn->prepare("UPDATE worlds SET winner_tribe_id = ?, victory_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $tribeId, $worldId);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function getWinner(int $worldId = CURRENT_WORLD_ID): ?array
    {
        $stmt = $this->conn->prepare("SELECT winner_tribe_id, victory_at FROM worlds WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $worldId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || empty($row['winner_tribe_id'])) {
            return null;
        }
        return ['tribe_id' => (int)$row['winner_tribe_id'], 'victory_at' => $row['victory_at'] ?? null];
    }
}
