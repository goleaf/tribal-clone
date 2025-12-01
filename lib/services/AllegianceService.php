<?php
declare(strict_types=1);

require_once __DIR__ . '/../managers/WorldManager.php';

/**
 * AllegianceService - Core calculation engine for conquest allegiance/control mechanics
 * 
 * Handles:
 * - Allegiance drop calculations with wall reduction
 * - Time-based regeneration with bonuses
 * - Anti-snipe floor enforcement
 * - Capture detection for both modes
 */
class AllegianceService
{
    private $conn;
    private WorldManager $worldManager;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->worldManager = new WorldManager($conn);
    }

    /**
     * Calculate allegiance drop from a conquest wave
     * 
     * @param int $villageId Target village ID
     * @param int $survivingEnvoys Number of Envoys that survived the battle
     * @param int $wallLevel Current wall level of target village
     * @param array $modifiers Additional modifiers (tech bonuses, etc.)
     * @param int $worldId World ID for configuration
     * @return array ['new_allegiance', 'drop_amount', 'captured', 'clamped']
     */
    public function calculateDrop(
        int $villageId,
        int $survivingEnvoys,
        int $wallLevel,
        array $modifiers = [],
        int $worldId = CURRENT_WORLD_ID
    ): array {
        if ($survivingEnvoys <= 0) {
            return [
                'new_allegiance' => null,
                'drop_amount' => 0,
                'captured' => false,
                'clamped' => false
            ];
        }

        // Get current allegiance
        $stmt = $this->conn->prepare("SELECT allegiance, anti_snipe_until, allegiance_floor FROM villages WHERE id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $village = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$village) {
            return ['new_allegiance' => null, 'drop_amount' => 0, 'captured' => false, 'clamped' => false];
        }

        $currentAllegiance = (int)$village['allegiance'];
        $config = $this->worldManager->getConquestSettings($worldId);

        // Calculate base drop per Envoy (random between min and max)
        $dropPerEnvoy = random_int($config['alleg_drop_min'], $config['alleg_drop_max']);
        
        // Apply wall reduction factor
        $wallReduction = 1.0 - min(0.5, $wallLevel * $config['alleg_wall_reduction_per_level']);
        
        // Apply world multiplier (if any)
        $worldMultiplier = $modifiers['world_multiplier'] ?? 1.0;
        
        // Calculate total drop
        $totalDrop = (int)floor($dropPerEnvoy * $survivingEnvoys * $wallReduction * $worldMultiplier);
        
        // Calculate new allegiance
        $newAllegiance = $currentAllegiance - $totalDrop;
        
        // Check anti-snipe floor
        $antiSnipeUntil = $village['anti_snipe_until'] ? strtotime($village['anti_snipe_until']) : null;
        $isAntiSnipeActive = $antiSnipeUntil && $antiSnipeUntil > time();
        $floor = $isAntiSnipeActive ? (int)$village['allegiance_floor'] : 0;
        
        $clamped = false;
        if ($newAllegiance < $floor) {
            $newAllegiance = $floor;
            $clamped = true;
        }
        
        // Clamp to valid range [0, 100]
        $newAllegiance = max(0, min(100, $newAllegiance));
        
        // Check if captured
        $captured = $newAllegiance <= 0 && !$isAntiSnipeActive;
        
        return [
            'new_allegiance' => $newAllegiance,
            'drop_amount' => $totalDrop,
            'captured' => $captured,
            'clamped' => $clamped,
            'wall_reduction' => $wallReduction,
            'floor_active' => $isAntiSnipeActive
        ];
    }

    /**
     * Apply regeneration tick to a village
     * 
     * @param int $villageId Village ID
     * @param int $currentAllegiance Current allegiance value
     * @param int $elapsedSeconds Seconds since last update
     * @param array $bonuses Building and tech bonuses
     * @param int $worldId World ID for configuration
     * @return int New allegiance value
     */
    public function applyRegeneration(
        int $villageId,
        int $currentAllegiance,
        int $elapsedSeconds,
        array $bonuses = [],
        int $worldId = CURRENT_WORLD_ID
    ): int {
        // Check if anti-snipe is active (pauses regeneration)
        $stmt = $this->conn->prepare("SELECT anti_snipe_until FROM villages WHERE id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $village = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$village) {
            return $currentAllegiance;
        }

        $antiSnipeUntil = $village['anti_snipe_until'] ? strtotime($village['anti_snipe_until']) : null;
        $isAntiSnipeActive = $antiSnipeUntil && $antiSnipeUntil > time();

        // Pause regeneration during anti-snipe period
        if ($isAntiSnipeActive) {
            return $currentAllegiance;
        }

        // Already at max
        if ($currentAllegiance >= 100) {
            return 100;
        }

        $config = $this->worldManager->getConquestSettings($worldId);
        
        // Calculate base regeneration
        $baseRatePerHour = $config['alleg_regen_per_hour'];
        $regenPerSecond = $baseRatePerHour / 3600.0;
        
        // Apply bonuses (building multipliers, tech bonuses, etc.)
        $buildingMultiplier = $bonuses['building_multiplier'] ?? 1.0;
        $techMultiplier = $bonuses['tech_multiplier'] ?? 1.0;
        $totalMultiplier = $buildingMultiplier * $techMultiplier;
        
        // Cap multiplier at reasonable maximum (e.g., 3x)
        $totalMultiplier = min(3.0, $totalMultiplier);
        
        // Calculate regeneration amount
        $regenAmount = $regenPerSecond * $elapsedSeconds * $totalMultiplier;
        
        // Apply regeneration
        $newAllegiance = $currentAllegiance + (int)floor($regenAmount);
        
        // Clamp to [0, 100]
        return max(0, min(100, $newAllegiance));
    }

    /**
     * Enforce anti-snipe floor
     * 
     * @param int $villageId Village ID
     * @param int $proposedAllegiance Proposed new allegiance value
     * @param int $worldId World ID for configuration
     * @return int Clamped allegiance value
     */
    public function enforceFloor(
        int $villageId,
        int $proposedAllegiance,
        int $worldId = CURRENT_WORLD_ID
    ): int {
        $stmt = $this->conn->prepare("SELECT anti_snipe_until, allegiance_floor FROM villages WHERE id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $village = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$village) {
            return $proposedAllegiance;
        }

        $antiSnipeUntil = $village['anti_snipe_until'] ? strtotime($village['anti_snipe_until']) : null;
        $isAntiSnipeActive = $antiSnipeUntil && $antiSnipeUntil > time();

        if (!$isAntiSnipeActive) {
            return $proposedAllegiance;
        }

        $floor = (int)$village['allegiance_floor'];
        return max($proposedAllegiance, $floor);
    }

    /**
     * Check if capture conditions are met
     * 
     * @param int $villageId Village ID
     * @param int $allegiance Current allegiance value
     * @param int $worldId World ID for configuration
     * @return bool True if village should be captured
     */
    public function checkCapture(
        int $villageId,
        int $allegiance,
        int $worldId = CURRENT_WORLD_ID
    ): bool {
        $config = $this->worldManager->getConquestSettings($worldId);
        $mode = $config['mode'];

        // Check anti-snipe and cooldown
        $stmt = $this->conn->prepare("
            SELECT anti_snipe_until, capture_cooldown_until, control_meter, uptime_started_at 
            FROM villages WHERE id = ?
        ");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $village = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$village) {
            return false;
        }

        // Check cooldown
        $cooldownUntil = $village['capture_cooldown_until'] ? strtotime($village['capture_cooldown_until']) : null;
        if ($cooldownUntil && $cooldownUntil > time()) {
            return false;
        }

        // Check anti-snipe
        $antiSnipeUntil = $village['anti_snipe_until'] ? strtotime($village['anti_snipe_until']) : null;
        if ($antiSnipeUntil && $antiSnipeUntil > time()) {
            return false;
        }

        if ($mode === 'allegiance') {
            // Allegiance mode: capture when allegiance reaches 0
            return $allegiance <= 0;
        } else {
            // Control/uptime mode: capture when control >= 100 and uptime complete
            $controlMeter = (int)$village['control_meter'];
            $uptimeStartedAt = $village['uptime_started_at'] ? strtotime($village['uptime_started_at']) : null;

            if ($controlMeter < 100) {
                return false;
            }

            if (!$uptimeStartedAt) {
                return false;
            }

            $uptimeDuration = $config['uptime_duration_seconds'];
            $uptimeElapsed = time() - $uptimeStartedAt;

            return $uptimeElapsed >= $uptimeDuration;
        }
    }

    /**
     * Update village allegiance in database
     * 
     * @param int $villageId Village ID
     * @param int $newAllegiance New allegiance value
     * @return bool Success
     */
    public function updateAllegiance(int $villageId, int $newAllegiance): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE villages 
            SET allegiance = ?, allegiance_last_update = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $newAllegiance, $villageId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Initialize post-capture state
     * 
     * @param int $villageId Village ID
     * @param int $worldId World ID for configuration
     * @return bool Success
     */
    public function initializePostCapture(int $villageId, int $worldId = CURRENT_WORLD_ID): bool
    {
        $config = $this->worldManager->getConquestSettings($worldId);
        
        $postCaptureStart = $config['post_capture_start'];
        $antiSnipeFloor = $config['anti_snipe_floor'];
        $antiSnipeSeconds = $config['anti_snipe_seconds'];
        $cooldownSeconds = $config['capture_cooldown_seconds'];

        $antiSnipeUntil = date('Y-m-d H:i:s', time() + $antiSnipeSeconds);
        $cooldownUntil = date('Y-m-d H:i:s', time() + $cooldownSeconds);

        $stmt = $this->conn->prepare("
            UPDATE villages 
            SET allegiance = ?,
                allegiance_floor = ?,
                anti_snipe_until = ?,
                capture_cooldown_until = ?,
                allegiance_last_update = CURRENT_TIMESTAMP,
                control_meter = 0,
                uptime_started_at = NULL
            WHERE id = ?
        ");
        $stmt->bind_param("iissi", $postCaptureStart, $antiSnipeFloor, $antiSnipeUntil, $cooldownUntil, $villageId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}
