<?php
declare(strict_types=1);

/**
 * WorldConfigManager
 * 
 * Provides a clean interface for accessing world-specific configuration,
 * with a focus on conquest system settings. Wraps WorldManager and adds
 * conquest-specific convenience methods.
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5
 */

require_once __DIR__ . '/WorldManager.php';

class WorldConfigManager
{
    private $conn;
    private WorldManager $worldManager;
    private array $configCache = [];

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->worldManager = new WorldManager($conn);
    }

    /**
     * Get all conquest configuration for a world.
     * Caches results per request for performance.
     * 
     * @param int $worldId World ID
     * @return array Conquest configuration array
     * 
     * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5
     */
    public function getConquestConfig(int $worldId = CURRENT_WORLD_ID): array
    {
        if (isset($this->configCache[$worldId])) {
            return $this->configCache[$worldId];
        }

        $config = $this->worldManager->getConquestSettings($worldId);
        $this->configCache[$worldId] = $config;
        
        return $config;
    }

    /**
     * Check if conquest system is enabled for a world.
     * 
     * @param int $worldId World ID
     * @return bool True if conquest is enabled
     * 
     * Requirements: 10.3
     */
    public function isConquestEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        return $this->worldManager->isConquestEnabled($worldId);
    }

    /**
     * Get conquest mode for a world.
     * 
     * @param int $worldId World ID
     * @return string 'allegiance' or 'control'
     * 
     * Requirements: 10.1
     */
    public function getConquestMode(int $worldId = CURRENT_WORLD_ID): string
    {
        return $this->worldManager->getConquestMode($worldId);
    }

    /**
     * Check if world is using allegiance-drop mode.
     * 
     * @param int $worldId World ID
     * @return bool True if allegiance mode
     * 
     * Requirements: 10.1
     */
    public function isAllegianceMode(int $worldId = CURRENT_WORLD_ID): bool
    {
        return $this->getConquestMode($worldId) === 'allegiance';
    }

    /**
     * Check if world is using control-uptime mode.
     * 
     * @param int $worldId World ID
     * @return bool True if control mode
     * 
     * Requirements: 10.1
     */
    public function isControlMode(int $worldId = CURRENT_WORLD_ID): bool
    {
        return $this->getConquestMode($worldId) === 'control';
    }

    /**
     * Get allegiance regeneration rate (percentage points per hour).
     * 
     * @param int $worldId World ID
     * @return float Regeneration rate
     * 
     * Requirements: 10.2
     */
    public function getAllegianceRegenRate(int $worldId = CURRENT_WORLD_ID): float
    {
        $config = $this->getConquestConfig($worldId);
        return (float)$config['alleg_regen_per_hour'];
    }

    /**
     * Get wall reduction factor per level.
     * 
     * @param int $worldId World ID
     * @return float Reduction factor (e.g., 0.02 = 2% per level)
     * 
     * Requirements: 10.2
     */
    public function getWallReductionFactor(int $worldId = CURRENT_WORLD_ID): float
    {
        $config = $this->getConquestConfig($worldId);
        return (float)$config['alleg_wall_reduction_per_level'];
    }

    /**
     * Get allegiance drop range per Envoy.
     * 
     * @param int $worldId World ID
     * @return array ['min' => int, 'max' => int]
     * 
     * Requirements: 10.2
     */
    public function getAllegianceDropRange(int $worldId = CURRENT_WORLD_ID): array
    {
        $config = $this->getConquestConfig($worldId);
        return [
            'min' => (int)$config['alleg_drop_min'],
            'max' => (int)$config['alleg_drop_max']
        ];
    }

    /**
     * Get anti-snipe floor value.
     * 
     * @param int $worldId World ID
     * @return int Floor value (0-100)
     * 
     * Requirements: 10.2
     */
    public function getAntiSnipeFloor(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['anti_snipe_floor'];
    }

    /**
     * Get anti-snipe duration in seconds.
     * 
     * @param int $worldId World ID
     * @return int Duration in seconds
     * 
     * Requirements: 10.2
     */
    public function getAntiSnipeDuration(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['anti_snipe_seconds'];
    }

    /**
     * Get post-capture starting allegiance value.
     * 
     * @param int $worldId World ID
     * @return int Starting allegiance (0-100)
     * 
     * Requirements: 10.2
     */
    public function getPostCaptureStart(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['post_capture_start'];
    }

    /**
     * Get capture cooldown duration in seconds.
     * 
     * @param int $worldId World ID
     * @return int Cooldown in seconds
     * 
     * Requirements: 10.2
     */
    public function getCaptureCooldown(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['capture_cooldown_seconds'];
    }

    /**
     * Get uptime duration required for capture (control mode).
     * 
     * @param int $worldId World ID
     * @return int Duration in seconds
     * 
     * Requirements: 10.2
     */
    public function getUptimeDuration(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['uptime_duration_seconds'];
    }

    /**
     * Get control gain rate per minute (control mode).
     * 
     * @param int $worldId World ID
     * @return int Gain rate
     * 
     * Requirements: 10.2
     */
    public function getControlGainRate(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['control_gain_rate_per_min'];
    }

    /**
     * Get control decay rate per minute (control mode).
     * 
     * @param int $worldId World ID
     * @return int Decay rate
     * 
     * Requirements: 10.2
     */
    public function getControlDecayRate(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['control_decay_rate_per_min'];
    }

    /**
     * Get minimum wave spacing in milliseconds.
     * 
     * @param int $worldId World ID
     * @return int Spacing in milliseconds
     * 
     * Requirements: 10.4
     */
    public function getWaveSpacing(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['wave_spacing_ms'];
    }

    /**
     * Get maximum Envoys per command.
     * 
     * @param int $worldId World ID
     * @return int Maximum Envoys
     * 
     * Requirements: 10.5
     */
    public function getMaxEnvoysPerCommand(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['max_envoys_per_command'];
    }

    /**
     * Get daily influence crest minting cap per account.
     * 
     * @param int $worldId World ID
     * @return int Daily cap
     * 
     * Requirements: 10.5
     */
    public function getDailyMintCap(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['conquest_daily_mint_cap'];
    }

    /**
     * Get daily Envoy training cap per account.
     * 
     * @param int $worldId World ID
     * @return int Daily cap
     * 
     * Requirements: 10.5
     */
    public function getDailyTrainCap(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['conquest_daily_train_cap'];
    }

    /**
     * Get minimum defender points required for conquest.
     * 
     * @param int $worldId World ID
     * @return int Minimum points
     * 
     * Requirements: 10.5
     */
    public function getMinDefenderPoints(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['conquest_min_defender_points'];
    }

    /**
     * Check if building loss on capture is enabled.
     * 
     * @param int $worldId World ID
     * @return bool True if enabled
     * 
     * Requirements: 10.5
     */
    public function isBuildingLossEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        $config = $this->getConquestConfig($worldId);
        return !empty($config['conquest_building_loss_enabled']);
    }

    /**
     * Get building loss probability on capture.
     * 
     * @param int $worldId World ID
     * @return float Probability (0.0 to 1.0)
     * 
     * Requirements: 10.5
     */
    public function getBuildingLossChance(int $worldId = CURRENT_WORLD_ID): float
    {
        $config = $this->getConquestConfig($worldId);
        return (float)$config['conquest_building_loss_chance'];
    }

    /**
     * Get resource transfer percentage on capture.
     * 
     * @param int $worldId World ID
     * @return float Transfer percentage (0.0 to 1.0)
     * 
     * Requirements: 10.5
     */
    public function getResourceTransferPercent(int $worldId = CURRENT_WORLD_ID): float
    {
        $config = $this->getConquestConfig($worldId);
        return (float)$config['conquest_resource_transfer_pct'];
    }

    /**
     * Check if abandonment decay is enabled.
     * 
     * @param int $worldId World ID
     * @return bool True if enabled
     * 
     * Requirements: 10.5
     */
    public function isAbandonmentDecayEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        $config = $this->getConquestConfig($worldId);
        return !empty($config['conquest_abandonment_decay_enabled']);
    }

    /**
     * Get abandonment threshold in hours.
     * 
     * @param int $worldId World ID
     * @return int Threshold in hours
     * 
     * Requirements: 10.5
     */
    public function getAbandonmentThreshold(int $worldId = CURRENT_WORLD_ID): int
    {
        $config = $this->getConquestConfig($worldId);
        return (int)$config['conquest_abandonment_threshold_hours'];
    }

    /**
     * Get abandonment decay rate (percentage points per hour).
     * 
     * @param int $worldId World ID
     * @return float Decay rate
     * 
     * Requirements: 10.5
     */
    public function getAbandonmentDecayRate(int $worldId = CURRENT_WORLD_ID): float
    {
        $config = $this->getConquestConfig($worldId);
        return (float)$config['conquest_abandonment_decay_rate'];
    }

    /**
     * Check if Envoy unit is enabled (feature flag).
     * 
     * @param int $worldId World ID
     * @return bool True if enabled
     * 
     * Requirements: 10.3
     */
    public function isEnvoyEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        return $this->worldManager->isConquestUnitEnabled($worldId);
    }

    /**
     * Check if anti-snipe protection is enabled (feature flag).
     * 
     * @param int $worldId World ID
     * @return bool True if enabled
     * 
     * Requirements: 10.3
     */
    public function isAntiSnipeEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        return $this->worldManager->isConquestAntiSnipeEnabled($worldId);
    }

    /**
     * Check if wall modifiers are enabled (feature flag).
     * 
     * @param int $worldId World ID
     * @return bool True if enabled
     * 
     * Requirements: 10.3
     */
    public function isWallModifierEnabled(int $worldId = CURRENT_WORLD_ID): bool
    {
        return $this->worldManager->isConquestWallModifierEnabled($worldId);
    }

    /**
     * Validate conquest configuration for a world.
     * Returns validation errors if any.
     * 
     * @param int $worldId World ID
     * @return array ['valid' => bool, 'errors' => string[]]
     * 
     * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5
     */
    public function validateConfig(int $worldId = CURRENT_WORLD_ID): array
    {
        $config = $this->getConquestConfig($worldId);
        $errors = [];

        // Validate mode
        if (!in_array($config['mode'], ['allegiance', 'control'], true)) {
            $errors[] = "Invalid conquest mode: {$config['mode']}. Must be 'allegiance' or 'control'.";
        }

        // Validate regeneration rate
        if ($config['alleg_regen_per_hour'] < 0 || $config['alleg_regen_per_hour'] > 100) {
            $errors[] = "Allegiance regeneration rate must be between 0 and 100.";
        }

        // Validate wall reduction
        if ($config['alleg_wall_reduction_per_level'] < 0 || $config['alleg_wall_reduction_per_level'] > 1) {
            $errors[] = "Wall reduction factor must be between 0 and 1.";
        }

        // Validate drop range
        if ($config['alleg_drop_min'] < 0 || $config['alleg_drop_min'] > 100) {
            $errors[] = "Allegiance drop minimum must be between 0 and 100.";
        }
        if ($config['alleg_drop_max'] < 0 || $config['alleg_drop_max'] > 100) {
            $errors[] = "Allegiance drop maximum must be between 0 and 100.";
        }
        if ($config['alleg_drop_min'] > $config['alleg_drop_max']) {
            $errors[] = "Allegiance drop minimum cannot exceed maximum.";
        }

        // Validate anti-snipe settings
        if ($config['anti_snipe_floor'] < 0 || $config['anti_snipe_floor'] > 100) {
            $errors[] = "Anti-snipe floor must be between 0 and 100.";
        }
        if ($config['anti_snipe_seconds'] < 0) {
            $errors[] = "Anti-snipe duration cannot be negative.";
        }

        // Validate post-capture start
        if ($config['post_capture_start'] < 0 || $config['post_capture_start'] > 100) {
            $errors[] = "Post-capture start value must be between 0 and 100.";
        }

        // Validate cooldown
        if ($config['capture_cooldown_seconds'] < 0) {
            $errors[] = "Capture cooldown cannot be negative.";
        }

        // Validate control mode settings
        if ($config['mode'] === 'control') {
            if ($config['uptime_duration_seconds'] <= 0) {
                $errors[] = "Uptime duration must be positive.";
            }
            if ($config['control_gain_rate_per_min'] <= 0) {
                $errors[] = "Control gain rate must be positive.";
            }
            if ($config['control_decay_rate_per_min'] < 0) {
                $errors[] = "Control decay rate cannot be negative.";
            }
        }

        // Validate wave spacing
        if ($config['wave_spacing_ms'] < 0) {
            $errors[] = "Wave spacing cannot be negative.";
        }

        // Validate caps
        if ($config['max_envoys_per_command'] <= 0) {
            $errors[] = "Maximum Envoys per command must be positive.";
        }
        if ($config['conquest_daily_mint_cap'] < 0) {
            $errors[] = "Daily mint cap cannot be negative.";
        }
        if ($config['conquest_daily_train_cap'] < 0) {
            $errors[] = "Daily train cap cannot be negative.";
        }

        // Validate building loss
        if ($config['conquest_building_loss_chance'] < 0 || $config['conquest_building_loss_chance'] > 1) {
            $errors[] = "Building loss chance must be between 0 and 1.";
        }

        // Validate resource transfer
        if ($config['conquest_resource_transfer_pct'] < 0 || $config['conquest_resource_transfer_pct'] > 1) {
            $errors[] = "Resource transfer percentage must be between 0 and 1.";
        }

        // Validate abandonment settings
        if ($config['conquest_abandonment_threshold_hours'] < 0) {
            $errors[] = "Abandonment threshold cannot be negative.";
        }
        if ($config['conquest_abandonment_decay_rate'] < 0) {
            $errors[] = "Abandonment decay rate cannot be negative.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Clear configuration cache.
     * Call this after updating world configuration.
     * 
     * @return void
     */
    public function clearCache(): void
    {
        $this->configCache = [];
    }
}

