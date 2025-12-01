<?php
declare(strict_types=1);

require_once __DIR__ . '/../managers/WorldManager.php';

/**
 * ConquestStateMachine - Validates prerequisites and enforces business rules for conquest
 * 
 * Handles:
 * - Prerequisite validation (combat win, Envoy survival, feature flags)
 * - Protection checks (beginner protection, safe zones, power deltas)
 * - Cooldown enforcement
 * - Wave spacing validation
 * - Village cap and handover checks
 */
class ConquestStateMachine
{
    private $conn;
    private WorldManager $worldManager;

    // Error codes
    const ERR_PREREQ = 'ERR_PREREQ';
    const ERR_CAP = 'ERR_CAP';
    const ERR_RES = 'ERR_RES';
    const ERR_POP = 'ERR_POP';
    const ERR_PROTECTED = 'ERR_PROTECTED';
    const ERR_SAFE_ZONE = 'ERR_SAFE_ZONE';
    const ERR_COOLDOWN = 'ERR_COOLDOWN';
    const ERR_SPACING = 'ERR_SPACING';
    const ERR_VILLAGE_CAP = 'ERR_VILLAGE_CAP';
    const ERR_HANDOVER_OFF = 'ERR_HANDOVER_OFF';
    const ERR_COMBAT_LOSS = 'ERR_COMBAT_LOSS';
    const ERR_NO_BEARER = 'ERR_NO_BEARER';
    const ERR_FEATURE_OFF = 'ERR_FEATURE_OFF';
    const ERR_MIN_POINTS = 'ERR_MIN_POINTS';

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->worldManager = new WorldManager($conn);
    }

    /**
     * Validate all prerequisites for a conquest attempt
     * 
     * @param int $attackerId Attacker player ID
     * @param int $defenderId Defender player ID
     * @param int $villageId Target village ID
     * @param bool $attackerWon Whether attacker won the battle
     * @param int $survivingEnvoys Number of Envoys that survived
     * @param int $worldId World ID
     * @return array ['allowed' => bool, 'reason_code' => string|null, 'message' => string]
     */
    public function validateAttempt(
        int $attackerId,
        int $defenderId,
        int $villageId,
        bool $attackerWon,
        int $survivingEnvoys,
        int $worldId = CURRENT_WORLD_ID
    ): array {
        // Check if conquest is enabled
        if (!$this->worldManager->isConquestEnabled($worldId)) {
            return [
                'allowed' => false,
                'reason_code' => self::ERR_FEATURE_OFF,
                'message' => 'Conquest system is not enabled on this world.'
            ];
        }

        // Check combat win requirement
        if (!$attackerWon) {
            return [
                'allowed' => false,
                'reason_code' => self::ERR_COMBAT_LOSS,
                'message' => 'Attacker must win the battle to affect allegiance.'
            ];
        }

        // Check Envoy survival
        if ($survivingEnvoys <= 0) {
            return [
                'allowed' => false,
                'reason_code' => self::ERR_NO_BEARER,
                'message' => 'At least one Envoy must survive to affect allegiance.'
            ];
        }

        // Check protection status
        $protectionCheck = $this->checkProtection($defenderId, $villageId, $worldId);
        if (!$protectionCheck['allowed']) {
            return $protectionCheck;
        }

        // Check cooldown
        $cooldownCheck = $this->checkCooldown($villageId);
        if (!$cooldownCheck['allowed']) {
            return $cooldownCheck;
        }

        // Check village cap
        $capCheck = $this->checkVillageCap($attackerId, $worldId);
        if (!$capCheck['allowed']) {
            return $capCheck;
        }

        // Check tribe handover rules
        $handoverCheck = $this->checkHandover($attackerId, $defenderId, $worldId);
        if (!$handoverCheck['allowed']) {
            return $handoverCheck;
        }

        return [
            'allowed' => true,
            'reason_code' => null,
            'message' => 'Conquest attempt validated successfully.'
        ];
    }

    /**
     * Check if target is protected
     * 
     * @param int $defenderId Defender player ID
     * @param int $villageId Target village ID
     * @param int $worldId World ID
     * @return array Validation result
     */
    public function checkProtection(int $defenderId, int $villageId, int $worldId = CURRENT_WORLD_ID): array
    {
        // Check beginner protection
        $stmt = $this->conn->prepare("SELECT points, beginner_protection_until FROM users WHERE id = ?");
        $stmt->bind_param("i", $defenderId);
        $stmt->execute();
        $defender = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$defender) {
            return [
                'allowed' => false,
                'reason_code' => self::ERR_PREREQ,
                'message' => 'Defender not found.'
            ];
        }

        // Check beginner protection
        $protectionUntil = $defender['beginner_protection_until'] ? strtotime($defender['beginner_protection_until']) : null;
        if ($protectionUntil && $protectionUntil > time()) {
            return [
                'allowed' => false,
                'reason_code' => self::ERR_PROTECTED,
                'message' => 'Target player is under beginner protection.'
            ];
        }

        // Check minimum points requirement
        $config = $this->worldManager->getConquestSettings($worldId);
        $minPoints = $config['conquest_min_defender_points'];
        if ($defender['points'] < $minPoints) {
            return [
                'allowed' => false,
                'reason_code' => self::ERR_MIN_POINTS,
                'message' => "Target player must have at least {$minPoints} points."
            ];
        }

        // Check safe zone (if implemented)
        // TODO: Implement safe zone check based on village coordinates

        return ['allowed' => true, 'reason_code' => null, 'message' => ''];
    }

    /**
     * Check capture cooldown status
     * 
     * @param int $villageId Village ID
     * @return array Validation result
     */
    public function checkCooldown(int $villageId): array
    {
        $stmt = $this->conn->prepare("SELECT capture_cooldown_until FROM villages WHERE id = ?");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $village = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$village) {
            return [
                'allowed' => false,
                'reason_code' => self::ERR_PREREQ,
                'message' => 'Village not found.'
            ];
        }

        $cooldownUntil = $village['capture_cooldown_until'] ? strtotime($village['capture_cooldown_until']) : null;
        if ($cooldownUntil && $cooldownUntil > time()) {
            $remaining = $cooldownUntil - time();
            $minutes = ceil($remaining / 60);
            return [
                'allowed' => false,
                'reason_code' => self::ERR_COOLDOWN,
                'message' => "Village is in capture cooldown for {$minutes} more minutes."
            ];
        }

        return ['allowed' => true, 'reason_code' => null, 'message' => ''];
    }

    /**
     * Check wave spacing requirements
     * 
     * @param int $attackerId Attacker player ID
     * @param int $villageId Target village ID
     * @param int $arrivalTime Wave arrival timestamp
     * @param int $worldId World ID
     * @return array Validation result
     */
    public function checkWaveSpacing(
        int $attackerId,
        int $villageId,
        int $arrivalTime,
        int $worldId = CURRENT_WORLD_ID
    ): array {
        $config = $this->worldManager->getConquestSettings($worldId);
        $minSpacingMs = $config['wave_spacing_ms'];

        // Get last wave arrival time for this attacker-target pair
        $stmt = $this->conn->prepare("
            SELECT MAX(timestamp) as last_arrival 
            FROM conquest_attempts 
            WHERE attacker_id = ? AND village_id = ?
        ");
        $stmt->bind_param("ii", $attackerId, $villageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result && $result['last_arrival']) {
            $lastArrival = strtotime($result['last_arrival']);
            $timeDiffMs = ($arrivalTime - $lastArrival) * 1000;

            if ($timeDiffMs < $minSpacingMs) {
                return [
                    'allowed' => false,
                    'reason_code' => self::ERR_SPACING,
                    'message' => "Waves must be spaced at least {$minSpacingMs}ms apart."
                ];
            }
        }

        return ['allowed' => true, 'reason_code' => null, 'message' => ''];
    }

    /**
     * Check village cap enforcement
     * 
     * @param int $attackerId Attacker player ID
     * @param int $worldId World ID
     * @return array Validation result
     */
    public function checkVillageCap(int $attackerId, int $worldId = CURRENT_WORLD_ID): array
    {
        // Get max villages per player (could be world-specific or global config)
        $maxVillages = defined('MAX_VILLAGES_PER_PLAYER') ? (int)MAX_VILLAGES_PER_PLAYER : 100;

        $stmt = $this->conn->prepare("SELECT COUNT(*) as village_count FROM villages WHERE user_id = ?");
        $stmt->bind_param("i", $attackerId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $villageCount = (int)$result['village_count'];

        if ($villageCount >= $maxVillages) {
            return [
                'allowed' => false,
                'reason_code' => self::ERR_VILLAGE_CAP,
                'message' => "You have reached the maximum of {$maxVillages} villages."
            ];
        }

        return ['allowed' => true, 'reason_code' => null, 'message' => ''];
    }

    /**
     * Check tribe handover opt-in enforcement
     * 
     * @param int $attackerId Attacker player ID
     * @param int $defenderId Defender player ID
     * @param int $worldId World ID
     * @return array Validation result
     */
    public function checkHandover(int $attackerId, int $defenderId, int $worldId = CURRENT_WORLD_ID): array
    {
        // Get tribe memberships
        $stmt = $this->conn->prepare("
            SELECT tm1.tribe_id as attacker_tribe, tm2.tribe_id as defender_tribe
            FROM users u1
            LEFT JOIN tribe_members tm1 ON tm1.user_id = u1.id
            LEFT JOIN users u2 ON u2.id = ?
            LEFT JOIN tribe_members tm2 ON tm2.user_id = u2.id
            WHERE u1.id = ?
        ");
        $stmt->bind_param("ii", $defenderId, $attackerId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            return ['allowed' => true, 'reason_code' => null, 'message' => ''];
        }

        $attackerTribe = $result['attacker_tribe'];
        $defenderTribe = $result['defender_tribe'];

        // If same tribe, check handover opt-in
        if ($attackerTribe && $defenderTribe && $attackerTribe === $defenderTribe) {
            // Check if tribe has handover enabled
            // TODO: Implement tribe handover opt-in setting
            // For now, allow same-tribe conquest
            return ['allowed' => true, 'reason_code' => null, 'message' => ''];
        }

        return ['allowed' => true, 'reason_code' => null, 'message' => ''];
    }

    /**
     * Check if village is protected
     * 
     * @param int $playerId Player ID
     * @param int $villageId Village ID
     * @return bool True if protected
     */
    public function isProtected(int $playerId, int $villageId): bool
    {
        $result = $this->checkProtection($playerId, $villageId);
        return !$result['allowed'];
    }

    /**
     * Check if village is in cooldown
     * 
     * @param int $villageId Village ID
     * @return bool True if in cooldown
     */
    public function isInCooldown(int $villageId): bool
    {
        $result = $this->checkCooldown($villageId);
        return !$result['allowed'];
    }
}
