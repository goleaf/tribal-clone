<?php
declare(strict_types=1);

require_once __DIR__ . '/MapGridManager.php';

/**
 * Spawn Manager
 * Handles new player village placement using clustered spawn algorithm
 */
class SpawnManager
{
    private $db;
    private $mapManager;

    public function __construct($db)
    {
        $this->db = $db;
        $this->mapManager = new MapGridManager($db);
    }

    /**
     * Create a new village for a player at an optimal spawn location
     */
    public function createStarterVillage(int $userId, int $worldId = 1, ?string $villageName = null): ?array
    {
        // Pick spawn coordinates using clustered algorithm
        $coords = $this->mapManager->pickSpawnCoord($worldId);
        
        if ($coords === null) {
            error_log("Failed to find spawn coordinates for user {$userId}");
            return null;
        }

        $x = $coords['x'];
        $y = $coords['y'];

        // Generate village name if not provided
        if ($villageName === null) {
            $villageName = $this->generateVillageName($userId);
        }

        // Create the village
        $stmt = $this->db->prepare(
            'INSERT INTO villages (name, x_coord, y_coord, user_id, world_id, points, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())'
        );
        $stmt->bind_param('siiii', $villageName, $x, $y, $userId, $worldId);
        
        if (!$stmt->execute()) {
            error_log("Failed to create village for user {$userId}: " . $stmt->error);
            $stmt->close();
            return null;
        }

        $villageId = $stmt->insert_id;
        $stmt->close();

        // Initialize village resources
        $this->initializeVillageResources($villageId);

        // Update chunk metadata
        $chunk = $this->mapManager->getChunkCoords($x, $y);
        $this->mapManager->updateChunkMetadata($chunk['x'], $chunk['y'], $worldId);

        return [
            'id' => $villageId,
            'name' => $villageName,
            'x' => $x,
            'y' => $y,
            'world_id' => $worldId
        ];
    }

    /**
     * Generate a default village name
     */
    private function generateVillageName(int $userId): string
    {
        $stmt = $this->db->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $username = $result['username'] ?? 'Player';
        return "{$username}'s Village";
    }

    /**
     * Initialize starting resources for a new village
     */
    private function initializeVillageResources(int $villageId): void
    {
        $startingWood = defined('STARTING_WOOD') ? STARTING_WOOD : 1000;
        $startingClay = defined('STARTING_CLAY') ? STARTING_CLAY : 1000;
        $startingIron = defined('STARTING_IRON') ? STARTING_IRON : 1000;

        $stmt = $this->db->prepare(
            'INSERT INTO village_resources (village_id, wood, clay, iron, last_updated)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
                wood = VALUES(wood),
                clay = VALUES(clay),
                iron = VALUES(iron),
                last_updated = NOW()'
        );
        $stmt->bind_param('iiii', $villageId, $startingWood, $startingClay, $startingIron);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Get spawn statistics for monitoring
     */
    public function getSpawnStats(int $worldId = 1): array
    {
        $playerCount = $this->mapManager->countVillages($worldId);
        
        // Calculate current spawn ring
        $baseRadius = 80;
        $growthPerK = 20;
        $radius = $baseRadius + intval(floor($playerCount / 1000)) * $growthPerK;
        $maxRadius = min($radius + 40, 500);

        // Get density in spawn ring
        $centerX = 500;
        $centerY = 500;
        $densities = [];
        
        for ($angle = 0; $angle < 360; $angle += 45) {
            $rad = deg2rad($angle);
            $x = intval($centerX + $radius * cos($rad));
            $y = intval($centerY + $radius * sin($rad));
            $chunk = $this->mapManager->getChunkCoords($x, $y);
            $densities[] = $this->mapManager->getChunkDensity($chunk['x'], $chunk['y'], $worldId);
        }

        $avgDensity = count($densities) > 0 ? array_sum($densities) / count($densities) : 0;

        return [
            'player_count' => $playerCount,
            'spawn_radius' => $radius,
            'max_radius' => $maxRadius,
            'avg_spawn_density' => round($avgDensity, 4),
            'center' => ['x' => $centerX, 'y' => $centerY]
        ];
    }
}
