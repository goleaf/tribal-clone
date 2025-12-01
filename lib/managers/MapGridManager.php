<?php
declare(strict_types=1);

/**
 * Map Grid Manager
 * Handles grid generation, spawn placement, and barbarian seeding
 */
class MapGridManager
{
    private const MAP_SIZE = 1000;
    private const CHUNK_SIZE = 20;
    private const BASE_RADIUS = 80;
    private const GROWTH_PER_K = 20;
    private const SNAP_RADIUS = 5;
    private const MAX_SPAWN_ATTEMPTS = 200;

    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Calculate Euclidean distance between two coordinates
     */
    public function distance(array $a, array $b): float
    {
        $dx = $b['x'] - $a['x'];
        $dy = $b['y'] - $a['y'];
        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Check if coordinates are within map bounds
     */
    public function inBounds(int $x, int $y): bool
    {
        return $x >= 0 && $x < self::MAP_SIZE && $y >= 0 && $y < self::MAP_SIZE;
    }

    /**
     * Get chunk coordinates for a given position
     */
    public function getChunkCoords(int $x, int $y): array
    {
        return [
            'x' => intval(floor($x / self::CHUNK_SIZE) * self::CHUNK_SIZE),
            'y' => intval(floor($y / self::CHUNK_SIZE) * self::CHUNK_SIZE)
        ];
    }

    /**
     * Count total player villages
     */
    public function countVillages(int $worldId = 1): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM villages WHERE user_id IS NOT NULL AND user_id > 0 AND world_id = ?'
        );
        $stmt->bind_param('i', $worldId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($result['count'] ?? 0);
    }

    /**
     * Check if a tile is empty
     */
    public function isEmpty(int $x, int $y, int $worldId = 1): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM villages WHERE x_coord = ? AND y_coord = ? AND world_id = ? LIMIT 1'
        );
        $stmt->bind_param('iii', $x, $y, $worldId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result === null;
    }

    /**
     * Count barbarian villages in a chunk
     */
    public function countBarbsInChunk(int $chunkX, int $chunkY, int $worldId = 1): int
    {
        $maxX = $chunkX + self::CHUNK_SIZE;
        $maxY = $chunkY + self::CHUNK_SIZE;
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM villages 
             WHERE (user_id IS NULL OR user_id = -1)
             AND x_coord >= ? AND x_coord < ?
             AND y_coord >= ? AND y_coord < ?
             AND world_id = ?'
        );
        $stmt->bind_param('iiiii', $chunkX, $maxX, $chunkY, $maxY, $worldId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($result['count'] ?? 0);
    }

    /**
     * Place a barbarian village at coordinates
     */
    public function placeBarbarian(int $x, int $y, int $worldId = 1): bool
    {
        $name = 'Barbarian Village';
        $stmt = $this->db->prepare(
            'INSERT INTO villages (name, x_coord, y_coord, user_id, world_id, points, created_at)
             VALUES (?, ?, ?, NULL, ?, 100, NOW())'
        );
        $stmt->bind_param('siii', $name, $x, $y, $worldId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Get density of villages in a chunk
     */
    public function getChunkDensity(int $chunkX, int $chunkY, int $worldId = 1): float
    {
        $maxX = $chunkX + self::CHUNK_SIZE;
        $maxY = $chunkY + self::CHUNK_SIZE;
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM villages 
             WHERE x_coord >= ? AND x_coord < ?
             AND y_coord >= ? AND y_coord < ?
             AND world_id = ?'
        );
        $stmt->bind_param('iiiii', $chunkX, $maxX, $chunkY, $maxY, $worldId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $count = (int)($result['count'] ?? 0);
        return $count / (self::CHUNK_SIZE * self::CHUNK_SIZE);
    }

    /**
     * Pick a spawn coordinate clustered near existing players but in low-density area
     */
    public function pickSpawnCoord(int $worldId = 1): ?array
    {
        $playerCount = $this->countVillages($worldId);
        $radius = self::BASE_RADIUS + intval(floor($playerCount / 1000)) * self::GROWTH_PER_K;
        $maxRadius = min($radius + 40, intval(self::MAP_SIZE / 2));

        for ($attempts = 0; $attempts < self::MAX_SPAWN_ATTEMPTS; $attempts++) {
            $angle = mt_rand() / mt_getrandmax() * 2 * M_PI;
            $r = $radius + mt_rand() / mt_getrandmax() * ($maxRadius - $radius);
            $cx = intval(floor(self::MAP_SIZE / 2 + $r * cos($angle)));
            $cy = intval(floor(self::MAP_SIZE / 2 + $r * sin($angle)));

            $coord = $this->snapToEmpty($cx, $cy, $worldId);
            if ($coord !== null) {
                return $coord;
            }
        }

        return null;
    }

    /**
     * Snap to nearest empty tile in a local radius
     */
    public function snapToEmpty(int $x, int $y, int $worldId = 1, int $radius = self::SNAP_RADIUS): ?array
    {
        for ($d = 0; $d <= $radius; $d++) {
            for ($dx = -$d; $dx <= $d; $dx++) {
                for ($dy = -$d; $dy <= $d; $dy++) {
                    $cx = $x + $dx;
                    $cy = $y + $dy;
                    if (!$this->inBounds($cx, $cy)) {
                        continue;
                    }
                    if ($this->isEmpty($cx, $cy, $worldId)) {
                        return ['x' => $cx, 'y' => $cy];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Seed barbarian villages across the map with desired density
     */
    public function seedBarbarians(int $worldId = 1, float $desiredDensity = 0.08): int
    {
        $totalPlaced = 0;

        for ($x = 0; $x < self::MAP_SIZE; $x += self::CHUNK_SIZE) {
            for ($y = 0; $y < self::MAP_SIZE; $y += self::CHUNK_SIZE) {
                $barbs = $this->countBarbsInChunk($x, $y, $worldId);
                $target = intval(floor(self::CHUNK_SIZE * self::CHUNK_SIZE * $desiredDensity));
                $placed = 0;

                while ($barbs + $placed < $target && $placed < $target * 2) {
                    $rx = $x + mt_rand(0, self::CHUNK_SIZE - 1);
                    $ry = $y + mt_rand(0, self::CHUNK_SIZE - 1);
                    if ($this->isEmpty($rx, $ry, $worldId)) {
                        if ($this->placeBarbarian($rx, $ry, $worldId)) {
                            $placed++;
                        }
                    }
                }

                $totalPlaced += $placed;
            }
        }

        return $totalPlaced;
    }

    /**
     * Initialize map grid with chunks metadata (optional optimization)
     */
    public function initializeChunksTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS map_chunks (
            chunk_x INT NOT NULL,
            chunk_y INT NOT NULL,
            world_id INT NOT NULL DEFAULT 1,
            village_count INT NOT NULL DEFAULT 0,
            barbarian_count INT NOT NULL DEFAULT 0,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (chunk_x, chunk_y, world_id),
            INDEX idx_world (world_id)
        )";
        $this->db->query($sql);
    }

    /**
     * Update chunk metadata for caching
     */
    public function updateChunkMetadata(int $chunkX, int $chunkY, int $worldId = 1): void
    {
        $maxX = $chunkX + self::CHUNK_SIZE;
        $maxY = $chunkY + self::CHUNK_SIZE;

        $stmt = $this->db->prepare(
            'SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN user_id IS NULL OR user_id = -1 THEN 1 ELSE 0 END) as barbs
             FROM villages 
             WHERE x_coord >= ? AND x_coord < ?
             AND y_coord >= ? AND y_coord < ?
             AND world_id = ?'
        );
        $stmt->bind_param('iiiii', $chunkX, $maxX, $chunkY, $maxY, $worldId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total = (int)($result['total'] ?? 0);
        $barbs = (int)($result['barbs'] ?? 0);

        $updateStmt = $this->db->prepare(
            'INSERT INTO map_chunks (chunk_x, chunk_y, world_id, village_count, barbarian_count, last_updated)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
                village_count = VALUES(village_count),
                barbarian_count = VALUES(barbarian_count),
                last_updated = NOW()'
        );
        $updateStmt->bind_param('iiiii', $chunkX, $chunkY, $worldId, $total, $barbs);
        $updateStmt->execute();
        $updateStmt->close();
    }
}
