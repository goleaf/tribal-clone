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

        $defaults = [
            'world_speed' => defined('WORLD_SPEED') ? (float)WORLD_SPEED : 1.0,
            'troop_speed' => defined('UNIT_SPEED_MULTIPLIER') ? (float)UNIT_SPEED_MULTIPLIER : 1.0,
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
                                if (in_array($key, ['world_speed', 'troop_speed'], true)) {
                                    $defaults[$key] = (float)$val;
                                } elseif ($key === 'tribe_member_limit' || $key === 'victory_value') {
                                    $defaults[$key] = $val === null ? null : (int)$val;
                                } elseif (in_array($key, ['enable_archer', 'enable_paladin', 'enable_paladin_weapons'], true)) {
                                    $defaults[$key] = (bool)$val;
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
}
