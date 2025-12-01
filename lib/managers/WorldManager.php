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
        'weather_enabled' => "INTEGER NOT NULL DEFAULT 0",
        'resource_production_multiplier' => "REAL NOT NULL DEFAULT 1.0",
        'vault_protection_percent' => "REAL NOT NULL DEFAULT 0.0",
        'resource_decay_enabled' => "INTEGER NOT NULL DEFAULT 0",
        'resource_decay_threshold_pct' => "REAL NOT NULL DEFAULT 0.8",
        'resource_decay_rate_per_hour' => "REAL NOT NULL DEFAULT 0.01",
        'overstack_enabled' => "INTEGER NOT NULL DEFAULT 0",
        'overstack_pop_threshold' => "INTEGER NOT NULL DEFAULT 0",
        'overstack_penalty_rate' => "REAL NOT NULL DEFAULT 0.1",
        'overstack_min_multiplier' => "REAL NOT NULL DEFAULT 0.4",
        'min_attack_pop_enabled' => "INTEGER NOT NULL DEFAULT 1",
        'min_attack_pop' => "INTEGER NOT NULL DEFAULT 5",
        'catchup_multiplier' => "REAL NOT NULL DEFAULT 1.0",
        'catchup_duration_hours' => "INTEGER NOT NULL DEFAULT 0",
        'terrain_attack_multiplier' => "REAL NOT NULL DEFAULT 1.0",
        'terrain_defense_multiplier' => "REAL NOT NULL DEFAULT 1.0",
        'weather_attack_multiplier' => "REAL NOT NULL DEFAULT 1.0",
        'weather_defense_multiplier' => "REAL NOT NULL DEFAULT 1.0",
        'enable_archer' => "INTEGER NOT NULL DEFAULT 1",
        'enable_paladin' => "INTEGER NOT NULL DEFAULT 1",
        'enable_paladin_weapons' => "INTEGER NOT NULL DEFAULT 1",
        'tech_mode' => "TEXT NOT NULL DEFAULT 'normal'",
        'tribe_member_limit' => "INTEGER DEFAULT NULL",
        'victory_type' => "TEXT DEFAULT NULL",
        'victory_value' => "INTEGER DEFAULT NULL",
        'winner_tribe_id' => "INTEGER DEFAULT NULL",
        'victory_at' => "TEXT DEFAULT NULL",
        'archetype' => "TEXT DEFAULT NULL"
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
            'inf_train_multiplier' => 1.0,
            'cav_train_multiplier' => 1.0,
            'rng_train_multiplier' => 1.0,
            'siege_train_multiplier' => 1.0,
            'night_bonus_enabled' => false,
            'night_start_hour' => 22,
            'night_end_hour' => 6,
            'weather_enabled' => defined('FEATURE_WEATHER_COMBAT_ENABLED') ? (bool)FEATURE_WEATHER_COMBAT_ENABLED : false,
            'resource_production_multiplier' => 1.0,
            'resource_multiplier' => 1.0,
            'vault_protection_percent' => 0.0,
            'vault_protect_pct' => 0.0,
            'resource_decay_enabled' => false,
            'resource_decay_threshold_pct' => 0.8,
            'resource_decay_rate_per_hour' => 0.01,
            'overstack_enabled' => defined('OVERSTACK_ENABLED') ? (bool)OVERSTACK_ENABLED : false,
            'overstack_pop_threshold' => defined('OVERSTACK_POP_THRESHOLD') ? (int)OVERSTACK_POP_THRESHOLD : 30000,
            'overstack_penalty_rate' => defined('OVERSTACK_PENALTY_RATE') ? (float)OVERSTACK_PENALTY_RATE : 0.1,
            'overstack_min_multiplier' => defined('OVERSTACK_MIN_MULTIPLIER') ? (float)OVERSTACK_MIN_MULTIPLIER : 0.4,
            'min_attack_pop_enabled' => defined('FEATURE_MIN_PAYLOAD_ENABLED') ? (bool)FEATURE_MIN_PAYLOAD_ENABLED : true,
            'min_attack_pop' => defined('MIN_ATTACK_POP') ? (int)MIN_ATTACK_POP : 5,
            'terrain_attack_multiplier' => 1.0,
            'terrain_defense_multiplier' => 1.0,
            'weather_attack_multiplier' => 1.0,
            'weather_defense_multiplier' => 1.0,
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
                                if (in_array($key, ['world_speed', 'troop_speed', 'build_speed', 'train_speed', 'research_speed', 'resource_production_multiplier', 'resource_multiplier', 'vault_protection_percent', 'vault_protect_pct', 'inf_train_multiplier', 'cav_train_multiplier', 'rng_train_multiplier', 'siege_train_multiplier', 'overstack_penalty_rate', 'overstack_min_multiplier'], true)) {
                                    $defaults[$key] = (float)$val;
                                } elseif ($key === 'tribe_member_limit' || $key === 'victory_value' || $key === 'overstack_pop_threshold' || $key === 'min_attack_pop') {
                                    $defaults[$key] = $val === null ? null : (int)$val;
                                } elseif (in_array($key, ['enable_archer', 'enable_paladin', 'enable_paladin_weapons', 'night_bonus_enabled', 'resource_decay_enabled', 'overstack_enabled', 'min_attack_pop_enabled', 'weather_enabled'], true)) {
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

    public function areSitterAttacksEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        if (defined('SITTER_ATTACKS_ENABLED')) {
            return (bool)SITTER_ATTACKS_ENABLED;
        }
        return true;
    }

    public function areSitterSupportsEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        if (defined('SITTER_SUPPORT_ENABLED')) {
            return (bool)SITTER_SUPPORT_ENABLED;
        }
        return true;
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

    public function getTrainSpeedForArchetype(string $archetype, int $worldId = CURRENT_WORLD_ID): float
    {
        $settings = $this->getSettings($worldId);
        $key = match (strtolower($archetype)) {
            'inf' => 'inf_train_multiplier',
            'cav' => 'cav_train_multiplier',
            'rng' => 'rng_train_multiplier',
            'siege' => 'siege_train_multiplier',
            default => null,
        };
        $base = $this->getTrainSpeed($worldId);
        $mult = $key && array_key_exists($key, $settings) ? (float)$settings[$key] : 1.0;
        return max(0.1, $base * $mult);
    }

    public function getResearchSpeed(int $worldId = CURRENT_WORLD_ID): float
    {
        $settings = $this->getSettings($worldId);
        return max(0.1, (float)($settings['research_speed'] ?? 1.0));
    }

    public function getTrainSpeedForUnit(string $internalName, int $worldId = CURRENT_WORLD_ID): float
    {
        $settings = $this->getSettings($worldId);
        $base = $this->getTrainSpeed($worldId);
        $internalName = strtolower(trim($internalName));

        // Map archetype buckets
        $bucket = 'inf';
        if (in_array($internalName, ['light', 'heavy', 'marcher', 'cavalry', 'knight'], true)) {
            $bucket = 'cav';
        } elseif (in_array($internalName, ['archer', 'marcher'], true)) {
            $bucket = 'rng';
        } elseif (in_array($internalName, ['ram', 'catapult', 'trebuchet'], true)) {
            $bucket = 'siege';
        }

        $bucketKey = match ($bucket) {
            'cav' => 'cav_train_multiplier',
            'rng' => 'rng_train_multiplier',
            'siege' => 'siege_train_multiplier',
            default => 'inf_train_multiplier',
        };

        $bucketMult = isset($settings[$bucketKey]) ? (float)$settings[$bucketKey] : 1.0;
        return max(0.1, $base * $bucketMult);
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

    public function getResourceProductionMultiplier(int $worldId = CURRENT_WORLD_ID): float
    {
        return max(0.1, (float)($this->getSettings($worldId)['resource_production_multiplier'] ?? 1.0));
    }

    public function getVaultProtectionPercent(int $worldId = CURRENT_WORLD_ID): float
    {
        $pct = (float)($this->getSettings($worldId)['vault_protection_percent'] ?? 0.0);
        return max(0.0, min(100.0, $pct));
    }

    public function isResourceDecayEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        return (bool)($this->getSettings($worldId)['resource_decay_enabled'] ?? false);
    }

    public function getResourceDecayThresholdPct(int $worldId = CURRENT_WORLD_ID): float
    {
        $pct = (float)($this->getSettings($worldId)['resource_decay_threshold_pct'] ?? 0.8);
        return max(0.0, min(1.0, $pct));
    }

    public function getResourceDecayRatePerHour(int $worldId = CURRENT_WORLD_ID): float
    {
        $rate = (float)($this->getSettings($worldId)['resource_decay_rate_per_hour'] ?? 0.01);
        return max(0.0, $rate);
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
            $this->ensureAuditTable();
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
            'weather_enabled' => defined('FEATURE_WEATHER_COMBAT_ENABLED') && FEATURE_WEATHER_COMBAT_ENABLED ? 1 : 0,
            'resource_production_multiplier' => 1.0,
            'vault_protection_percent' => 0.0,
            'resource_decay_enabled' => 0,
            'overstack_enabled' => defined('OVERSTACK_ENABLED') && OVERSTACK_ENABLED ? 1 : 0,
            'overstack_pop_threshold' => defined('OVERSTACK_POP_THRESHOLD') ? (int)OVERSTACK_POP_THRESHOLD : 30000,
            'overstack_penalty_rate' => defined('OVERSTACK_PENALTY_RATE') ? (float)OVERSTACK_PENALTY_RATE : 0.1,
            'overstack_min_multiplier' => defined('OVERSTACK_MIN_MULTIPLIER') ? (float)OVERSTACK_MIN_MULTIPLIER : 0.4,
            'min_attack_pop_enabled' => defined('FEATURE_MIN_PAYLOAD_ENABLED') && FEATURE_MIN_PAYLOAD_ENABLED ? 1 : 1,
            'min_attack_pop' => defined('MIN_ATTACK_POP') ? (int)MIN_ATTACK_POP : 5,
            'terrain_attack_multiplier' => 1.0,
            'terrain_defense_multiplier' => 1.0,
            'weather_attack_multiplier' => 1.0,
            'weather_defense_multiplier' => 1.0,
            'enable_archer' => 1,
            'enable_paladin' => 1,
            'enable_paladin_weapons' => 1,
            'tech_mode' => 'normal',
            'archetype' => null
        ];
        foreach ($defaults as $col => $val) {
            $quotedVal = is_numeric($val) ? $val : ("'" . $this->conn->real_escape_string((string)$val) . "'");
            $this->conn->query("UPDATE worlds SET {$col} = {$quotedVal} WHERE {$col} IS NULL");
        }
        $this->ensureAuditTable();
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

        $stmtInsert = $this->conn->prepare("INSERT INTO worlds (name, world_speed, troop_speed, build_speed, train_speed, research_speed, night_bonus_enabled, night_start_hour, night_end_hour, resource_production_multiplier, vault_protection_percent, resource_decay_enabled, enable_archer, enable_paladin, enable_paladin_weapons, tech_mode, tribe_member_limit, victory_type, victory_value, archetype) VALUES ('World 1', 1.0, 1.0, 1.0, 1.0, 1.0, 0, 22, 6, 1.0, 0.0, 0, 1, 1, 1, 'normal', NULL, NULL, NULL, NULL)");
        if ($stmtInsert) {
            $stmtInsert->execute();
            $stmtInsert->close();
        }
        $this->logConfigChange(1, null, [], $this->getSettings(1), 'seed_world');
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

    /**
     * Lightweight validation for world settings arrays.
     * Returns ['success' => bool, 'errors' => string[]].
     */
    public function validateWorldConfig(array $config): array
    {
        $errors = [];

        $speeds = [
            'world_speed' => $config['world_speed'] ?? null,
            'troop_speed' => $config['troop_speed'] ?? null,
            'build_speed' => $config['build_speed'] ?? null,
            'train_speed' => $config['train_speed'] ?? null,
            'research_speed' => $config['research_speed'] ?? null,
        ];
        foreach ($speeds as $key => $val) {
            if ($val !== null && (!is_numeric($val) || $val <= 0)) {
                $errors[] = "$key must be a positive number.";
            }
        }

        $tribeLimit = $config['tribe_member_limit'] ?? null;
        if ($tribeLimit !== null && (!is_numeric($tribeLimit) || $tribeLimit < 0)) {
            $errors[] = 'tribe_member_limit must be null or >= 0.';
        }

        $victoryType = $config['victory_type'] ?? null;
        $victoryValue = $config['victory_value'] ?? null;
        if (empty($victoryType)) {
            $errors[] = 'victory_type is required.';
        } elseif (in_array($victoryType, ['domination', 'tribe_domination', 'tribe_village_percent'], true)) {
            if (!is_numeric($victoryValue) || $victoryValue <= 0 || $victoryValue > 100) {
                $errors[] = 'victory_value must be between 1 and 100 for domination-style victories.';
            }
        }

        $nightEnabled = !empty($config['night_bonus_enabled']);
        $startHour = isset($config['night_start_hour']) ? (int)$config['night_start_hour'] : 22;
        $endHour = isset($config['night_end_hour']) ? (int)$config['night_end_hour'] : 6;
        if ($nightEnabled) {
            if ($startHour < 0 || $startHour > 23 || $endHour < 0 || $endHour > 23) {
                $errors[] = 'Night bonus hours must be between 0 and 23.';
            }
            if ($startHour === $endHour) {
                $errors[] = 'Night bonus start and end hours cannot be the same.';
            }
        }

        return ['success' => empty($errors), 'errors' => $errors];
    }

    /**
     * Archetype templates for world creation seeding.
     */
    public function getArchetypeTemplates(): array
    {
        return [
            'casual' => [
                'world_speed' => 0.75,
                'troop_speed' => 1.0,
                'build_speed' => 0.75,
                'train_speed' => 0.75,
                'research_speed' => 0.75,
                'night_bonus_enabled' => 1,
                'night_start_hour' => 22,
                'night_end_hour' => 6,
                'tribe_member_limit' => 30,
                'victory_type' => 'domination',
                'victory_value' => 60,
                'tech_mode' => 'normal',
                'archetype' => 'casual'
            ],
            'classic' => [
                'world_speed' => 1.0,
                'troop_speed' => 1.0,
                'build_speed' => 1.0,
                'train_speed' => 1.0,
                'research_speed' => 1.0,
                'night_bonus_enabled' => 1,
                'night_start_hour' => 23,
                'night_end_hour' => 7,
                'tribe_member_limit' => 50,
                'victory_type' => 'domination',
                'victory_value' => 70,
                'tech_mode' => 'normal',
                'archetype' => 'classic'
            ],
            'blitz' => [
                'world_speed' => 3.0,
                'troop_speed' => 2.5,
                'build_speed' => 4.0,
                'train_speed' => 4.0,
                'research_speed' => 3.0,
                'night_bonus_enabled' => 0,
                'night_start_hour' => 0,
                'night_end_hour' => 0,
                'tribe_member_limit' => 40,
                'victory_type' => 'domination',
                'victory_value' => 70,
                'tech_mode' => 'normal',
                'archetype' => 'blitz'
            ],
            'hardcore' => [
                'world_speed' => 1.25,
                'troop_speed' => 1.0,
                'build_speed' => 1.5,
                'train_speed' => 1.5,
                'research_speed' => 1.25,
                'night_bonus_enabled' => 0,
                'night_start_hour' => 0,
                'night_end_hour' => 0,
                'tribe_member_limit' => 35,
                'victory_type' => 'domination',
                'victory_value' => 75,
                'tech_mode' => 'normal',
                'archetype' => 'hardcore'
            ],
            'seasonal' => [
                'world_speed' => 1.5,
                'troop_speed' => 1.2,
                'build_speed' => 1.5,
                'train_speed' => 1.5,
                'research_speed' => 1.5,
                'night_bonus_enabled' => 1,
                'night_start_hour' => 23,
                'night_end_hour' => 7,
                'tribe_member_limit' => 45,
                'victory_type' => 'domination',
                'victory_value' => 70,
                'tech_mode' => 'normal',
                'archetype' => 'seasonal'
            ],
            'experimental' => [
                'world_speed' => 1.0,
                'troop_speed' => 1.0,
                'build_speed' => 1.2,
                'train_speed' => 1.2,
                'research_speed' => 1.2,
                'night_bonus_enabled' => 0,
                'night_start_hour' => 0,
                'night_end_hour' => 0,
                'tribe_member_limit' => 40,
                'victory_type' => 'domination',
                'victory_value' => 65,
                'tech_mode' => 'normal',
                'archetype' => 'experimental'
            ]
        ];
    }

    /**
     * Applies a world archetype template to an existing world row.
     */
    public function applyArchetypeToWorld(int $worldId, string $archetype, ?int $actorUserId = null): array
    {
        $templates = $this->getArchetypeTemplates();
        $key = strtolower(trim($archetype));
        if (!isset($templates[$key])) {
            return ['success' => false, 'message' => 'Unknown archetype.'];
        }

        $this->ensureSchema();
        $before = $this->getSettings($worldId);
        $template = $templates[$key];
        $columns = $this->getWorldColumns();
        $applicable = array_intersect(array_keys($template), $columns);
        if (empty($applicable)) {
            return ['success' => false, 'message' => 'No columns to update for archetype.'];
        }

        $sets = [];
        $types = '';
        $values = [];
        foreach ($applicable as $col) {
            $sets[] = "{$col} = ?";
            $val = $template[$col];
            if (is_float($val)) {
                $types .= 'd';
            } elseif (is_int($val)) {
                $types .= 'i';
            } else {
                $types .= 's';
            }
            $values[] = $val;
        }
        $types .= 'i';
        $values[] = $worldId;

        $sql = "UPDATE worlds SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Failed to prepare archetype update.'];
        }
        $stmt->bind_param($types, ...$values);
        $ok = $stmt->execute();
        $stmt->close();

        // Clear cache
        unset($this->settingsCache[$worldId]);

        if ($ok) {
            $validation = $this->validateWorldConfig(array_merge($this->getSettings($worldId), $template));
            if (!$validation['success']) {
                return ['success' => false, 'message' => 'Applied archetype but validation failed: ' . implode('; ', $validation['errors'])];
            }
            $this->logConfigChange($worldId, $actorUserId, $before, $this->getSettings($worldId), 'apply_archetype:' . $key);
            return ['success' => true, 'message' => "Applied '{$key}' archetype to world {$worldId}."];
        }

        return ['success' => false, 'message' => 'Failed to apply archetype.'];
    }

    /**
     * Append an audit entry for world configuration changes.
     */
    public function logConfigChange(int $worldId, ?int $actorUserId, array $before, array $after, string $action = 'update'): void
    {
        $this->ensureAuditTable();
        $stmt = $this->conn->prepare("
            INSERT INTO world_config_audit (world_id, actor_user_id, action, before_json, after_json)
            VALUES (?, ?, ?, ?, ?)
        ");
        if ($stmt === false) {
            return;
        }
        $beforeJson = json_encode($before);
        $afterJson = json_encode($after);
        $stmt->bind_param(
            "iisss",
            $worldId,
            $actorUserId,
            $action,
            $beforeJson,
            $afterJson
        );
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Returns recent audit log entries for a world.
     */
    public function getConfigAudit(int $worldId, int $limit = 20): array
    {
        $this->ensureAuditTable();
        $stmt = $this->conn->prepare("
            SELECT id, world_id, actor_user_id, action, before_json, after_json, created_at
            FROM world_config_audit
            WHERE world_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT ?
        ");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param("ii", $worldId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    private function ensureAuditTable(): void
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS world_config_audit (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                world_id INTEGER NOT NULL,
                actor_user_id INTEGER NULL,
                action TEXT NOT NULL,
                before_json TEXT,
                after_json TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}
