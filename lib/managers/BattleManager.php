<?php
/**
 * BattleManager handles battles between villages.
 */
class BattleManager
{
    private $conn;
    private $villageManager;
    private $buildingManager; // BuildingManager dependency
    private $notificationManager; // Persistent in-game notifications
    private $reportManager; // Generic report log
    private $tribeWarManager; // Tribe war tracking
    private $intelManager; // Scouting/intel logging
    private string $conquestLogFile;
    private ?array $worldSettings = null;

    private const RANDOM_VARIANCE = 0.25; // +/- 25% luck
    private const FAITH_DEFENSE_PER_LEVEL = 0.05; // 5% per church level
    private const FIRST_CHURCH_DEFENSE_BONUS = 0.1; // Flat 10% if first church exists
    private const MIN_MORALE = 0.30;
    private const WINNER_MINIMUM_LOSS = 0.05; // winner always loses at least 5% of troops
    private const RAID_CASUALTY_FACTOR = 0.65; // raids inflict/take fewer losses
    private const RAID_LOOT_FACTOR = 0.6; // raids cap loot to 60% of stored resources
    private const FAKE_TURNBACK_RATIO = 0.8; // fake attacks turn back after 80% of the path
    private const WALL_BONUS_PER_LEVEL = 0.08;
    private const OVERSTACK_ENABLED_DEFAULT = false;
    private const OVERSTACK_MIN_MULTIPLIER_DEFAULT = 0.4;
    private const OVERSTACK_PENALTY_RATE_DEFAULT = 0.1;
    private const OVERSTACK_THRESHOLD_DEFAULT = 30000;
    private const BEGINNER_PROTECTION_POINTS = 200;
    private const WORLD_UNIT_SPEED = 1.0; // fields per hour baseline
    private const TARGET_COMMAND_CAP = 200; // Max concurrent incoming commands per target village
    private const SIEGE_UNIT_INTERNALS = ['ram', 'catapult', 'trebuchet'];
    private const LOYALTY_UNIT_INTERNALS = ['noble', 'chieftain', 'senator', 'chief', 'envoy', 'standard_bearer'];
    private const FAKE_THROTTLE_THRESHOLD = 20; // zero-siege sub-50-pop commands in window before delaying
    private const FAKE_THROTTLE_WINDOW_SEC = 3600; // 60 minutes
    private const FAKE_THROTTLE_DELAY_SEC = 300; // 5 minutes per excess send
    private const SOFT_FLAG_WINDOW_SEC = 86400; // 24 hours
    private const SOFT_FLAG_THRESHOLD = 3; // flags in window to trigger penalty
    private const SOFT_FLAG_PENALTY_MULTIPLIER = 0.5; // halve caps when flagged
    private const SITTER_MAX_OUTGOING_PER_HOUR = 10; // sitter cannot exceed this command count per hour
    private const MAX_LOYALTY_UNITS_PER_COMMAND = 1; // nobles/standard bearers per command
    private const MANTLET_RANGED_REDUCTION = 0.4; // 40% reduction to ranged defense vs escorted siege
    private const PLUNDER_DR_WINDOW_SEC = 7200; // 2h diminishing returns window
    private const PLUNDER_DR_STEPS = [1.0, 0.75, 0.5, 0.25]; // multipliers by streak index
    private const PHASE_ORDER = ['infantry', 'cavalry', 'archer'];
    private const RESEARCH_BONUS_PER_LEVEL = 0.10; // +10% per smithy level
    private const LOYALTY_MIN = 0;
    private const LOYALTY_MAX = 100;
    private const LOYALTY_DROP_MIN = 20;
    private const LOYALTY_DROP_MAX = 35;
    private const LOYALTY_FAIL_DROP_MIN = 5;
    private const LOYALTY_FAIL_DROP_MAX = 10;
    private const RECENT_CAPTURE_FLOOR = 10; // anti-rebound buffer
    private const RECENT_CAPTURE_WINDOW_SECONDS = 900; // 15 minutes after capture
    private const REPORT_VERSION = 1;
    private const OFFENSIVE_ATTACK_TYPES = ['attack', 'raid', 'spy', 'fake'];
    private const MAX_COMMANDS_PER_MINUTE = 20;
    private const MAX_COMMANDS_PER_HOUR = 200;
    private const MAX_SCOUTS_PER_MINUTE = 10; // dedicated scout spam cap
    private const MAX_SCOUTS_PER_TARGET_PER_WINDOW = 5; // per attacker/target 15-minute window
    private const SCOUT_TARGET_WINDOW_SECONDS = 900;
    private const MAX_ATTACKS_PER_TARGET_PER_DAY = 10; // generic cap for any matchup
    private const MAX_COMMANDS_PER_WINDOW = 30; // soft anti-bot burst cap
    private const COMMAND_RATE_WINDOW_SECONDS = 60;
    private const MAX_SCOUT_COMMANDS_PER_WINDOW = 15;
    private const LOW_POWER_ATTACK_CAP_POINTS = 500; // applies stricter caps to defenders at/below this score
    private const LOW_POWER_ATTACKS_PER_ATTACKER_PER_DAY = 5;
    private const LOW_POWER_ATTACKS_PER_TRIBE_PER_DAY = 20;
    private const MIN_ATTACK_POP = 5;
    private const CONQUEST_MIN_DEFENDER_POINTS = 500; // block conquest against very low-point targets

    /**
     * @param mysqli $conn Database connection
     * @param VillageManager $villageManager Village manager instance
     * @param BuildingManager $buildingManager Building manager instance
     */
    public function __construct($conn, VillageManager $villageManager, BuildingManager $buildingManager)
    {
        $this->conn = $conn;
        $this->villageManager = $villageManager;
        $this->buildingManager = $buildingManager;
        if (!class_exists('NotificationManager')) {
            require_once __DIR__ . '/NotificationManager.php';
        }
        $this->notificationManager = new NotificationManager($conn);
        // Lazy-load ReportManager if available
        if (!class_exists('ReportManager')) {
            require_once __DIR__ . '/ReportManager.php';
        }
        $this->reportManager = new ReportManager($conn);
        if (!class_exists('TribeWarManager')) {
            require_once __DIR__ . '/TribeWarManager.php';
        }
        $this->tribeWarManager = new TribeWarManager($conn);
        if (!class_exists('IntelManager')) {
            require_once __DIR__ . '/IntelManager.php';
        }
        $this->intelManager = new IntelManager($conn);
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $this->conquestLogFile = $logDir . '/conquest_attempts.log';
    }
    
    /**
     * Sends an attack from one village to another.
     * 
     * @param int $source_village_id Attacker village ID
     * @param int $target_village_id Target village ID
     * @param array $units_sent Map of unit type IDs to counts
     * @param string $attack_type Attack type ('attack', 'raid', 'support', 'spy')
     * @param string|null $target_building Target building for catapults
     * @param array $options Additional options (e.g., ['mission_type' => 'deep_spy'])
     * @return array Operation status
     */
    public function sendAttack($source_village_id, $target_village_id, $units_sent, $attack_type = 'attack', $target_building = null, array $options = [])
    {
        $attack_type = in_array($attack_type, ['attack', 'raid', 'support', 'spy', 'fake'], true) ? $attack_type : 'attack';
        $missionType = is_string($options['mission_type'] ?? null) ? $options['mission_type'] : 'light_scout';
        $allowFriendlyFireOverride = !empty($options['allow_friendly_fire']);
        if (!isset($_SESSION['attack_target_rate'])) {
            $_SESSION['attack_target_rate'] = [];
        }

        // Ensure both villages exist
        $stmt_check_villages = $this->conn->prepare("
            SELECT 
                v1.id as source_id, v1.name as source_name, v1.x_coord as source_x, v1.y_coord as source_y, v1.user_id as source_user_id, v1.world_id as source_world_id,
                v2.id as target_id, v2.name as target_name, v2.x_coord as target_x, v2.y_coord as target_y, v2.user_id as target_user_id, v2.world_id as target_world_id
            FROM villages v1, villages v2
            WHERE v1.id = ? AND v2.id = ?
        ");
        $stmt_check_villages->bind_param("ii", $source_village_id, $target_village_id);
        $stmt_check_villages->execute();
        $result = $stmt_check_villages->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'One or both villages do not exist.'
            ];
        }
        
        $villages = $result->fetch_assoc();
        $stmt_check_villages->close();

        $attacker_user_id = (int)$villages['source_user_id'];
        $defender_user_id = (int)$villages['target_user_id'];
        $target_is_barb = $defender_user_id === -1;
        $defender_points = $target_is_barb ? null : $this->getUserPoints($defender_user_id);

        // Global rate limit per attacker to deter automation/burst spam
        $rateLimitCheck = $this->enforceCommandRateLimit($attacker_user_id, $attack_type, $defender_user_id);
        if ($rateLimitCheck !== true) {
            return [
                'success' => false,
                'error' => is_string($rateLimitCheck) ? $rateLimitCheck : 'RATE_CAP: Too many commands sent recently.'
            ];
        }

        // Pair (attacker->target) burst cap in session to deter rapid resend spam
        if (!isset($_SESSION['attack_target_rate'])) {
            $_SESSION['attack_target_rate'] = [];
        }
        $pairKey = $attacker_user_id . ':' . $defender_user_id;
        $now = microtime(true);
        $windowSec = 30;
        $maxPerWindow = 5;
        $bucket = $_SESSION['attack_target_rate'][$pairKey] ?? [];
        $bucket = array_values(array_filter($bucket, function ($ts) use ($now, $windowSec) {
            return ($ts + $windowSec) > $now;
        }));
        if (count($bucket) >= $maxPerWindow) {
            $retryAfter = max(1, (int)ceil(($bucket[0] + $windowSec) - $now));
            return [
                'success' => false,
                'error' => 'Too many commands to this target in a short window. Try again shortly.',
                'code' => AjaxResponse::ERR_RATE_LIMIT,
                'retry_after' => $retryAfter
            ];
        }
        $bucket[] = $now;
        $_SESSION['attack_target_rate'][$pairKey] = $bucket;

        $attackerTribeId = $this->getUserTribeId($attacker_user_id);
        $defenderTribeId = $this->getUserTribeId($defender_user_id);
        $sitterContext = $this->getSitterContext();
        $attacker_points = $this->getUserPoints($attacker_user_id);
        $defender_points = $this->getUserPoints($defender_user_id);
        $attacker_protected = $this->isBeginnerProtected($attacker_points);
        $targetProtection = $this->getUserProtectionState($defender_user_id);
        $defender_protected = $targetProtection['protected'] ?? false;
        $conquestEnabled = defined('FEATURE_CONQUEST_UNIT_ENABLED') ? (bool)FEATURE_CONQUEST_UNIT_ENABLED : true;
        $rateCheck = $this->enforceCommandRateLimit($attacker_user_id);
        if ($attack_type === 'spy') {
            $rateCheck = $rateCheck === true ? $this->enforceScoutRateLimit($attacker_user_id) : $rateCheck;
        }
        if ($rateCheck !== true) {
            return [
                'success' => false,
                'error' => is_string($rateCheck) ? $rateCheck : 'Command rate limit reached. Please wait a moment.',
                'code' => 'RATE_LIMIT'
            ];
        }
        $capCheck = $this->enforceAttackCap($attacker_user_id, $defender_user_id, $defender_points, $attackerTribeId);
        if ($capCheck !== true) {
            $this->logAbuseFlag($attacker_user_id, 'TARGET_CAP', ['target_user_id' => $defender_user_id]);
            return [
                'success' => false,
                'error' => is_string($capCheck) ? $capCheck : 'Attack limit reached for this target.',
                'code' => 'ATTACK_CAP'
            ];
        }

        if (!$target_is_barb && $attack_type !== 'support') {
            if ($attacker_protected && !$defender_protected) {
                return [
                    'success' => false,
                    'error' => 'You are under beginner protection and cannot attack stronger players yet.',
                    'code' => AjaxResponse::ERR_PROTECTED
                ];
            }
            if ($defender_protected && !$attacker_protected) {
                return [
                    'success' => false,
                    'error' => 'This target is under beginner protection.',
                    'code' => AjaxResponse::ERR_PROTECTED
                ];
            }
        }
        
        // Prevent attacking your own villages
        if ($villages['source_user_id'] === $villages['target_user_id'] && $attack_type !== 'support') {
            return [
                'success' => false,
                'error' => 'You cannot attack your own villages.',
                'code' => 'SELF_ATTACK'
            ];
        }

        // Friendly fire: block attacking same tribe or allied/NAP tribes unless override is explicitly allowed.
        if ($attack_type !== 'support') {
            if ($attackerTribeId && $defenderTribeId) {
                if ($attackerTribeId === $defenderTribeId && !$allowFriendlyFireOverride) {
                    return [
                        'success' => false,
                        'error' => 'Attacks against your own tribe members are blocked.',
                        'code' => 'FRIENDLY_FIRE'
                    ];
                }
                $dipStatus = $this->getDiplomacyStatus($attackerTribeId, $defenderTribeId);
                if (in_array($dipStatus, ['ally', 'nap'], true) && !$allowFriendlyFireOverride) {
                    return [
                        'success' => false,
                        'error' => 'Attacks against allied/NAP tribes are blocked. Request a leadership override to proceed.',
                        'code' => 'ALLY_OVERRIDE_REQUIRED'
                    ];
                }
                if ($allowFriendlyFireOverride && in_array($dipStatus, ['ally', 'nap'], true)) {
                    $this->logTribeAction($attackerTribeId, (int)$villages['source_user_id'], 'friendly_fire_override', [
                        'target_tribe_id' => $defenderTribeId,
                        'attack_type' => $attack_type
                    ]);
                }
            }
        }

        // Beginner protection checks (skip for supports)
        if ($attack_type !== 'support') {
            $protectionCheck = $this->validateBeginnerProtection(
                (int)$villages['source_user_id'],
                (int)$villages['target_user_id'],
                (int)$villages['source_id'],
                (int)$villages['target_id']
            );
            if (!$protectionCheck['allowed']) {
                return [
                    'success' => false,
                    'error' => $protectionCheck['error'] ?? 'Attack blocked due to beginner protection.',
                    'code' => AjaxResponse::ERR_PROTECTED
                ];
            }
        }

        // Require Rally Point to send any troops
        $rallyLevel = $this->buildingManager->getBuildingLevel((int)$source_village_id, 'rally_point');
        if ($rallyLevel <= 0) {
            return [
                'success' => false,
                'error' => 'You need a Rally Point to send troops from this village.'
            ];
        }
        
        // Check available units
        $stmt_check_units = $this->conn->prepare("
            SELECT unit_type_id, count 
            FROM village_units 
            WHERE village_id = ?
        ");
        $stmt_check_units->bind_param("i", $source_village_id);
        $stmt_check_units->execute();
        $units_result = $stmt_check_units->get_result();
        
        $available_units = [];
        while ($unit = $units_result->fetch_assoc()) {
            $available_units[$unit['unit_type_id']] = $unit['count'];
        }
        $stmt_check_units->close();
        
        // Ensure the player is not sending more units than available and counts are positive
        foreach ($units_sent as $unit_type_id => $count) {
            if ($count <= 0) {
                return [
                    'success' => false,
                    'error' => 'Unit counts must be positive.',
                    'code' => 'ERR_INPUT'
                ];
            }
            if (!isset($available_units[$unit_type_id]) || $available_units[$unit_type_id] < $count) {
                return [
                    'success' => false,
                    'error' => 'You do not have enough units to perform this attack.',
                    'code' => 'INSUFFICIENT_UNITS'
                ];
            }
        }

        // Load unit metadata for validation and speed calculation
        $unit_type_ids = array_keys($units_sent);
        $unit_meta = [];
        $placeholders = implode(',', array_map('intval', $unit_type_ids));

        $stmt_get_units = $this->conn->prepare("
            SELECT id, internal_name, speed, population
            FROM unit_types
            WHERE id IN ($placeholders)
        ");
        $stmt_get_units->execute();
        $meta_result = $stmt_get_units->get_result();
        while ($row = $meta_result->fetch_assoc()) {
            $unit_meta[$row['id']] = $row;
        }
        $stmt_get_units->close();

        // Validate spy-only missions
        if ($attack_type === 'spy') {
            foreach ($units_sent as $unit_type_id => $count) {
                if (!isset($unit_meta[$unit_type_id]) || $unit_meta[$unit_type_id]['internal_name'] !== 'spy') {
                    return [
                        'success' => false,
                        'error' => 'Spy missions can only include scouts.'
                    ];
                }
            }
        }

        // Require at least one unit to be sent
        $total_units = 0;
        $total_pop = 0;
        $hasSiege = false;
        $hasLoyaltyUnit = false;
        $loyaltyCount = 0;
        $minPayloadPop = $this->getMinPayloadPop();
        $enforceMinPayload = $this->isMinPayloadEnabled();
        foreach ($units_sent as $count) {
            $total_units += $count;
        }
        
        if ($total_units === 0) {
            return [
                'success' => false,
                'error' => 'You must send at least one unit.',
                'code' => 'MIN_UNITS'
            ];
        }

        // Enforce minimum payload anti-abuse rule: 5 pop or any siege unit.
        $hasUnits = false;
        foreach ($units_sent as $unit_type_id => $count) {
            if (!isset($unit_meta[$unit_type_id])) {
                continue;
            }
            if ($count < 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid troop counts provided.',
                    'code' => 'ERR_INVALID_PAYLOAD'
                ];
            }
            if ($count > 0) {
                $hasUnits = true;
            }
            $total_pop += ((int)$unit_meta[$unit_type_id]['population']) * $count;
            $internal = $unit_meta[$unit_type_id]['internal_name'];
            if ($count > 0 && in_array($internal, self::SIEGE_UNIT_INTERNALS, true)) {
                $hasSiege = true;
            }
            if ($count > 0 && in_array($internal, self::LOYALTY_UNIT_INTERNALS, true)) {
                $hasLoyaltyUnit = true;
                $loyaltyCount += $count;
            }
        }
        if (!$hasUnits) {
            return [
                'success' => false,
                'error' => 'You must send at least one unit.',
                'code' => 'MIN_PAYLOAD'
            ];
        }
        if ($enforceMinPayload && $total_pop < $minPayloadPop && !$hasSiege) {
            return [
                'success' => false,
                'error' => sprintf('Minimum payload is %d population or at least one siege unit.', $minPayloadPop),
                'code' => 'MIN_PAYLOAD'
            ];
        }
        if ($loyaltyCount > self::MAX_LOYALTY_UNITS_PER_COMMAND) {
            return [
                'success' => false,
                'error' => sprintf('Only %d conquest unit(s) may be sent per command.', self::MAX_LOYALTY_UNITS_PER_COMMAND),
                'code' => 'CONQUEST_CAP'
            ];
        }
        if ($hasLoyaltyUnit && !$conquestEnabled) {
            return [
                'success' => false,
                'error' => 'Conquest units are disabled on this world.',
                'code' => 'CONQUEST_DISABLED'
            ];
        }

        // Anti-abuse: block conquest vs very low-point targets even if protection lapsed
        if ($hasLoyaltyUnit && !$target_is_barb) {
            $minConquestPoints = defined('CONQUEST_MIN_DEFENDER_POINTS')
                ? (int)CONQUEST_MIN_DEFENDER_POINTS
                : self::CONQUEST_MIN_DEFENDER_POINTS;
            if ($defender_points !== null && $defender_points < $minConquestPoints) {
                $this->logConquestAttempt('block_low_points', $attacker_user_id, $defender_user_id, (int)$villages['target_id'], false, [
                    'defender_points' => $defender_points,
                    'min_points' => $minConquestPoints
                ]);
                $this->logAbuseFlag($attacker_user_id, 'CONQUEST_BLOCK_LOW_POINTS', [
                    'target_user_id' => $defender_user_id,
                    'target_points' => $defender_points,
                    'min_points' => $minConquestPoints
                ]);
                return [
                    'success' => false,
                    'error' => 'Conquest attacks are blocked against targets below ' . $minConquestPoints . ' points.',
                    'code' => 'ERR_PROTECTED'
                ];
            }
        }

        // Beginner shield: block siege/loyalty; allow raids only after 24h account age.
        if ($defender_protected && $attack_type !== 'support') {
            $hoursOld = $this->getAccountAgeHours($targetProtection);
            $raidAllowed = $attack_type === 'raid' && $hoursOld !== null && $hoursOld >= 24;
            if ($hasSiege || $hasLoyaltyUnit) {
                if ($hasLoyaltyUnit) {
                    $this->logConquestAttempt('block_protected', $attacker_user_id, $defender_user_id, (int)$villages['target_id'], false, [
                        'reason' => 'beginner_protection'
                    ]);
                    $this->logAbuseFlag($attacker_user_id, 'CONQUEST_BLOCK_PROTECTED', [
                        'target_user_id' => $defender_user_id
                    ]);
                }
                return [
                    'success' => false,
                    'error' => 'Beginner shield: siege or loyalty attacks are blocked on protected villages.',
                    'code' => 'ERR_PROTECTED'
                ];
            }
            if (!$raidAllowed) {
                return [
                    'success' => false,
                    'error' => 'This village is under beginner protection. Raids are allowed only after 24 hours of account age.',
                    'code' => 'ERR_PROTECTED'
                ];
            }
        }

        // Sitter restrictions: no loyalty attacks and stricter command cap.
        $worldId = isset($villages['source_world_id']) ? (int)$villages['source_world_id'] : (defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1);
        if ($sitterContext['is_sitter']) {
            $sitterPerms = $this->getSitterPermissions($worldId);
            $sitterAttackAllowed = $sitterPerms['attack'];
            $sitterSupportAllowed = $sitterPerms['support'];
            if (!$sitterAttackAllowed && $attack_type !== 'support') {
                $this->logSitterAction([
                    'action' => 'blocked',
                    'reason' => 'attack_disabled',
                    'attack_type' => $attack_type,
                    'owner_id' => $sitterContext['owner_id'],
                    'sitter_id' => $sitterContext['sitter_id'],
                    'source_village_id' => $source_village_id,
                    'target_village_id' => $target_village_id
                ]);
                return [
                    'success' => false,
                    'error' => 'Sitter permissions: attacks are disabled on this world.',
                    'code' => 'SITTER_ATTACKS_DISABLED'
                ];
            }
            if (!$sitterSupportAllowed && $attack_type === 'support') {
                $this->logSitterAction([
                    'action' => 'blocked',
                    'reason' => 'support_disabled',
                    'attack_type' => $attack_type,
                    'owner_id' => $sitterContext['owner_id'],
                    'sitter_id' => $sitterContext['sitter_id'],
                    'source_village_id' => $source_village_id,
                    'target_village_id' => $target_village_id
                ]);
                return [
                    'success' => false,
                    'error' => 'Sitter permissions: support sends are disabled on this world.',
                    'code' => 'SITTER_SUPPORT_DISABLED'
                ];
            }
            if ($hasLoyaltyUnit) {
                return [
                    'success' => false,
                    'error' => 'Sitters cannot launch loyalty/reduction attacks.',
                    'code' => 'SITTER_NO_LOYALTY'
                ];
            }
            $sitterLimitCheck = $this->enforceSitterHourlyLimit($sitterContext['owner_id']);
            if ($sitterLimitCheck !== true) {
                return [
                    'success' => false,
                    'error' => $sitterLimitCheck,
                    'code' => 'SITTER_RATE_CAP'
                ];
            }
        }

        // Calculate distance and travel time
        $distance = $this->calculateDistance(
            $villages['source_x'], $villages['source_y'],
            $villages['target_x'], $villages['target_y']
        );

        // Per-target incoming cap to prevent command spam
        $stmt_count_commands = $this->conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM attacks
            WHERE target_village_id = ?
              AND is_completed = 0
              AND is_canceled = 0
              AND arrival_time > NOW()
              AND attack_type IN ('attack','raid','spy','fake')
        ");
        if ($stmt_count_commands) {
            $stmt_count_commands->bind_param("i", $target_village_id);
            $stmt_count_commands->execute();
            $countRow = $stmt_count_commands->get_result()->fetch_assoc();
            $stmt_count_commands->close();
            $incomingCount = (int)($countRow['cnt'] ?? 0);
            if ($incomingCount >= self::TARGET_COMMAND_CAP) {
                return [
                    'success' => false,
                    'error' => sprintf(
                        'Target command cap reached for this village (%d/%d). Try again later.',
                        $incomingCount,
                        self::TARGET_COMMAND_CAP
                    ),
                    'code' => 'TARGET_CAP_REACHED'
                ];
            }
        }

        // If attacker is protected and attacks a non-barbarian target, drop protection
        if ($attack_type !== 'support' && (int)$villages['target_user_id'] > 0) {
            $stmt_drop_prot = $this->conn->prepare("UPDATE users SET is_protected = 0 WHERE id = ?");
            if ($stmt_drop_prot) {
                $stmt_drop_prot->bind_param("i", $villages['source_user_id']);
                $stmt_drop_prot->execute();
                $stmt_drop_prot->close();
            }
        }
        
        // Find the slowest unit
        $slowest_speed = null;
        foreach ($units_sent as $unit_type_id => $count) {
            if (!isset($unit_meta[$unit_type_id])) {
                return [
                    'success' => false,
                    'error' => 'Unit information could not be found.'
                ];
            }
            $speed = (int)$unit_meta[$unit_type_id]['speed'];
            if ($slowest_speed === null || $speed < $slowest_speed) {
                $slowest_speed = $speed;
            }
        }
        
        if ($slowest_speed === null) {
            return [
                'success' => false,
                'error' => 'Cannot determine unit speed.'
            ];
        }

        // Calculate travel time in seconds (distance in fields, speed in fields/hour -> seconds)
        require_once __DIR__ . '/WorldManager.php';
        $wm = new WorldManager($this->conn);
        $worldSpeed = $wm->getWorldSpeed();
        $troopSpeed = $wm->getTroopSpeed();
        $unitSpeedMultiplier = defined('UNIT_SPEED_MULTIPLIER') ? max(0.1, (float)UNIT_SPEED_MULTIPLIER) : 1.0;
        $effectiveSpeed = self::WORLD_UNIT_SPEED * $worldSpeed * $troopSpeed * $unitSpeedMultiplier;
        $travel_time = (int)ceil(($distance * $slowest_speed / $effectiveSpeed) * 3600);
        $throttleDelay = 0;
        $start_time = time();
        // Fake attacks should turn around before hitting the target.
        if ($attack_type === 'fake') {
            $travel_time = max(1, (int)floor($travel_time * self::FAKE_TURNBACK_RATIO));
        }
        // Fake throttling for low-payload spam.
        if ($attack_type !== 'support' && !$hasSiege && $total_pop < 50) {
            $recentCount = $this->countRecentLowPayloadCommands($target_village_id);
            if ($recentCount >= self::FAKE_THROTTLE_THRESHOLD) {
                $excess = $recentCount - self::FAKE_THROTTLE_THRESHOLD + 1;
                $throttleDelay = max($throttleDelay, $excess * self::FAKE_THROTTLE_DELAY_SEC);
                $this->logAbuseFlag($attacker_user_id, 'FAKE_THROTTLE', [
                    'target_village_id' => $target_village_id,
                    'recent_count' => $recentCount
                ]);
            }
        }
        if ($throttleDelay > 0) {
            $start_time += $throttleDelay;
        }
        $arrival_time = $start_time + $travel_time;
        
        // Begin transaction
        $this->conn->begin_transaction();
        
        try {
            // Subtract units from the source village
            foreach ($units_sent as $unit_type_id => $count) {
                $stmt_update_units = $this->conn->prepare("
                    UPDATE village_units 
                    SET count = count - ? 
                    WHERE village_id = ? AND unit_type_id = ?
                ");
                $stmt_update_units->bind_param("iii", $count, $source_village_id, $unit_type_id);
                $stmt_update_units->execute();
                $stmt_update_units->close();
            }
            
            // Add the attack to the attacks table
            $stmt_add_attack = $this->conn->prepare("
                INSERT INTO attacks (
                    source_village_id, target_village_id,
                    attack_type, start_time, arrival_time,
                    is_completed, is_canceled, target_building
                ) VALUES (?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), 0, 0, ?)
            ");
            $stmt_add_attack->bind_param(
                "iisiss",
                $source_village_id, $target_village_id,
                $attack_type, $start_time, $arrival_time,
                $target_building
            );
            $stmt_add_attack->execute();
            $attack_id = $stmt_add_attack->insert_id;
            $stmt_add_attack->close();
            if ($sitterContext['is_sitter']) {
                $this->logSitterAction([
                    'action' => 'sent',
                    'attack_type' => $attack_type,
                    'owner_id' => $sitterContext['owner_id'],
                    'sitter_id' => $sitterContext['sitter_id'],
                    'source_village_id' => $source_village_id,
                    'target_village_id' => $target_village_id,
                    'attack_id' => $attack_id,
                    'arrival_time' => $arrival_time
                ]);
            }

            // Notify defender of incoming attack (warning)
            if (!empty($villages['target_user_id']) && $villages['target_user_id'] != $villages['source_user_id']) {
                $arrivalDate = date('Y-m-d H:i:s', $arrival_time);
                $msg = sprintf(
                    'Incoming %s from %s (%d|%d) arriving at %s.',
                    ucfirst($attack_type),
                    $villages['source_name'],
                    $villages['source_x'],
                    $villages['source_y'],
                    $arrivalDate
                );
                $this->notificationManager->addNotification(
                    (int)$villages['target_user_id'],
                    $msg,
                    'warning',
                    '/game/game.php'
                );
            }
            
            // Add unit records to attack_units
            foreach ($units_sent as $unit_type_id => $count) {
                $stmt_add_units = $this->conn->prepare("
                    INSERT INTO attack_units (
                        attack_id, unit_type_id, count
                    ) VALUES (?, ?, ?)
                ");
                $stmt_add_units->bind_param("iii", $attack_id, $unit_type_id, $count);
                $stmt_add_units->execute();
                $stmt_add_units->close();
            }

            // Attach scouting mission metadata for spy attacks
            if ($attack_type === 'spy') {
                try {
                    $stmtMission = $this->conn->prepare("
                        INSERT IGNORE INTO scout_missions (
                            attack_id, mission_type, requested_by_user_id, requested_by_village_id
                        ) VALUES (?, ?, ?, ?)
                    ");
                    if ($stmtMission) {
                        $sourceUserId = (int)($villages['source_user_id'] ?? 0);
                        $stmtMission->bind_param("isii", $attack_id, $missionType, $sourceUserId, $source_village_id);
                        $stmtMission->execute();
                        $stmtMission->close();
                    }
                } catch (Throwable $e) {
                    error_log('Failed to record scout mission meta: ' . $e->getMessage());
                }
            }
            
            // Commit transaction
            $this->conn->commit();

            if ($hasLoyaltyUnit) {
                $this->logConquestAttempt('sent', $attacker_user_id, (int)$villages['target_user_id'], $target_village_id, true, [
                    'attack_id' => $attack_id,
                    'arrival_time' => $arrival_time,
                    'attack_type' => $attack_type
                ]);
            }
            
            // Build response payload
            $arrival_date = date('Y-m-d H:i:s', $arrival_time);
            
            return [
                'success' => true,
                'message' => "Attack sent successfully. Arrival time: $arrival_date",
                'attack_id' => $attack_id,
                'source_village_id' => $source_village_id,
                'target_village_id' => $target_village_id,
                'attack_type' => $attack_type,
                'units_sent' => $units_sent,
                'distance' => $distance,
                'travel_time' => $travel_time,
                'throttle_delay' => $throttleDelay,
                'arrival_time' => $arrival_time,
                'arrival_date' => $arrival_date
            ];
        } catch (Exception $e) {
            // Roll back on failure
            $this->conn->rollback();
            
            return [
                'success' => false,
                'error' => 'An error occurred while sending the attack: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel an attack if it has not arrived yet.
     * 
     * @param int $attack_id Attack ID
     * @param int $user_id User ID (permission check)
     * @return array Operation status
     */
    public function cancelAttack($attack_id, $user_id)
    {
        // Ensure the attack exists and belongs to the user
        $stmt_check_attack = $this->conn->prepare("
            SELECT a.id, a.source_village_id, a.target_village_id, a.attack_type, 
                   a.start_time, a.arrival_time, a.is_completed, a.is_canceled
            FROM attacks a
            JOIN villages v ON a.source_village_id = v.id
            WHERE a.id = ? AND v.user_id = ? AND a.is_completed = 0 AND a.is_canceled = 0
        ");
        $stmt_check_attack->bind_param("ii", $attack_id, $user_id);
        $stmt_check_attack->execute();
        $result = $stmt_check_attack->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'The attack does not exist, is already finished or canceled, or you do not have access to it.'
            ];
        }
        
        $attack = $result->fetch_assoc();
        $stmt_check_attack->close();
        
        // Ensure the attack has not already arrived
        $current_time = time();
        $arrival_time = strtotime($attack['arrival_time']);
        
        if ($current_time >= $arrival_time) {
            return [
                'success' => false,
                'error' => 'Cannot cancel an attack that has already arrived.'
            ];
        }
        
        // Begin transaction
        $this->conn->begin_transaction();
        
        try {
            // Mark the attack as canceled
            $stmt_cancel_attack = $this->conn->prepare("
                UPDATE attacks 
                SET is_canceled = 1 
                WHERE id = ?
            ");
            $stmt_cancel_attack->bind_param("i", $attack_id);
            $stmt_cancel_attack->execute();
            $stmt_cancel_attack->close();
            
            // Fetch units from the attack
            $stmt_get_units = $this->conn->prepare("
                SELECT unit_type_id, count 
                FROM attack_units 
                WHERE attack_id = ?
            ");
            $stmt_get_units->bind_param("i", $attack_id);
            $stmt_get_units->execute();
            $units_result = $stmt_get_units->get_result();
            
            $units_to_return = [];
            while ($unit = $units_result->fetch_assoc()) {
                $units_to_return[$unit['unit_type_id']] = $unit['count'];
            }
            $stmt_get_units->close();

            // Create a return march from the current position back to the source village.
            $startTs = strtotime($attack['start_time']);
            $arrivalTs = strtotime($attack['arrival_time']);
            $now = time();
            $elapsed = max(1, $now - $startTs);
            $total = max(1, $arrivalTs - $startTs);
            $returnTravel = min($elapsed, $total); // approximate distance already covered
            $returnArrival = $now + $returnTravel;

            $returnSource = $attack['target_village_id'] ?: $attack['source_village_id'];
            $returnTarget = $attack['source_village_id'];

            $stmt_return = $this->conn->prepare("
                INSERT INTO attacks (
                    source_village_id, target_village_id,
                    attack_type, start_time, arrival_time,
                    is_completed, is_canceled, target_building
                ) VALUES (?, ?, 'return', FROM_UNIXTIME(?), FROM_UNIXTIME(?), 0, 0, NULL)
            ");
            $stmt_return->bind_param(
                "iiii",
                $returnSource,
                $returnTarget,
                $now,
                $returnArrival
            );
            $stmt_return->execute();
            $returnAttackId = $stmt_return->insert_id;
            $stmt_return->close();

            foreach ($units_to_return as $unit_type_id => $count) {
                $stmt_add_units = $this->conn->prepare("
                    INSERT INTO attack_units (attack_id, unit_type_id, count)
                    VALUES (?, ?, ?)
                ");
                $stmt_add_units->bind_param("iii", $returnAttackId, $unit_type_id, $count);
                $stmt_add_units->execute();
                $stmt_add_units->close();
            }

            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'The attack was canceled. Troops are returning.',
                'attack_id' => $attack_id,
                'returned_units' => $units_to_return,
                'return_attack_id' => $returnAttackId,
                'return_arrival' => $returnArrival
            ];
        } catch (Exception $e) {
            // Roll back on error
            $this->conn->rollback();
            
            return [
                'success' => false,
                'error' => 'An error occurred while canceling the attack: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Processes completed attacks and generates user-facing messages.
     * @param int $user_id User ID for which to process attacks.
     * @return array Messages to display.
     */
    public function processCompletedAttacks(int $user_id): array
    {
        $messages = [];
        $current_time = date('Y-m-d H:i:s');
        
        // Fetch user village IDs
        $user_village_ids = $this->villageManager->getUserVillageIds($user_id);
        
        if (empty($user_village_ids)) {
             return []; // User has no villages; no attacks to process
        }

        // Fetch unfinished, uncanceled attacks that should have arrived by now,
        // that involve the user's villages (attacker or defender)
        // Using FIND_IN_SET because we cannot bind arrays to IN with prepare()
        $village_ids_string = implode(',', $user_village_ids);

        $stmt_get_attacks = $this->conn->prepare("
            SELECT id, source_village_id, target_village_id, attack_type
            FROM attacks
            WHERE is_completed = 0 AND is_canceled = 0 AND arrival_time <= ?
              AND (FIND_IN_SET(source_village_id, ?) OR FIND_IN_SET(target_village_id, ?))
            ORDER BY arrival_time ASC, id ASC
        ");
        
         if ($stmt_get_attacks === false) {
             error_log("Prepare failed for getCompletedAttacks (BattleManager): " . $this->conn->error);
             return ['<p class="error-message">An error occurred while fetching completed attacks.</p>'];
         }

        $stmt_get_attacks->bind_param("sss", $current_time, $village_ids_string, $village_ids_string);
        $stmt_get_attacks->execute();
        $attacks_result = $stmt_get_attacks->get_result();
        
        while ($attack = $attacks_result->fetch_assoc()) {
            // Process based on attack type
            if ($attack['attack_type'] === 'spy') {
                $battle_result = $this->processSpyMission($attack['id']);
            } elseif ($attack['attack_type'] === 'support') {
                $battle_result = $this->processSupportArrival($attack['id']);
            } elseif ($attack['attack_type'] === 'return') {
                $battle_result = $this->processReturnArrival($attack['id']);
            } elseif ($attack['attack_type'] === 'fake') {
                $battle_result = $this->processFakeArrival($attack['id']);
            } else {
                $battle_result = $this->processBattle($attack['id']);
            }

            if ($battle_result && $battle_result['success']) {
                // Fetch attack and village details for messaging
                $stmt_details = $this->conn->prepare("
                    SELECT
                        a.id, a.source_village_id, a.target_village_id, a.attack_type,
                        sv.name as source_name, tv.name as target_name
                    FROM attacks a
                    JOIN villages sv ON a.source_village_id = sv.id
                    JOIN villages tv ON a.target_village_id = tv.id
                    WHERE a.id = ? LIMIT 1
                ");
                $stmt_details->bind_param("i", $attack['id']);
                $stmt_details->execute();
                $attack_details = $stmt_details->get_result()->fetch_assoc();
                $stmt_details->close();

                if ($attack_details) {
                    if (in_array($attack_details['attack_type'], ['support', 'return', 'fake'], true)) {
                        // Optional light-touch messaging for non-combat commands
                        $source_name = htmlspecialchars($attack_details['source_name']);
                        $target_name = htmlspecialchars($attack_details['target_name']);
                        if ($attack_details['attack_type'] === 'support' && in_array($attack['target_village_id'], $user_village_ids)) {
                            $messages[] = "<p class='info-message'>Support from <b>{$source_name}</b> has arrived at <b>{$target_name}</b>.</p>";
                        }
                        if ($attack_details['attack_type'] === 'return' && in_array($attack['target_village_id'], $user_village_ids)) {
                            $messages[] = "<p class='info-message'>Troops returned to <b>{$target_name}</b>.</p>";
                        }
                        if ($attack_details['attack_type'] === 'fake' && in_array($attack['source_village_id'], $user_village_ids)) {
                            $messages[] = "<p class='info-message'>Your fake attack from <b>{$source_name}</b> has turned around.</p>";
                        }
                        continue;
                    }
                    // Fetch the battle report to identify the winner and loot (if any)
                    // processBattle/processSpyMission creates the report, so fetch it immediately after.
                    $report = $this->getBattleReportForAttack($attack['id']); // Dedicated helper

                    if ($report) {
                        $source_name = htmlspecialchars($attack_details['source_name']);
                        $target_name = htmlspecialchars($attack_details['target_name']);
                        $report_type = $report['type'] ?? $attack_details['attack_type'];

                        if ($report_type === 'spy') {
                            $success = !empty($report['attacker_won']);
                            $intel = $report['details']['intel'] ?? [];
                            if (in_array($attack['source_village_id'], $user_village_ids)) {
                                $resourcesText = '';
                                if (!empty($intel['resources'])) {
                                    $res = $intel['resources'];
                                    $resourcesText = " Resources - Wood: {$res['wood']}, Clay: {$res['clay']}, Iron: {$res['iron']}.";
                                }
                                if ($success) {
                                    $messages[] = "<p class='success-message'>Your scouts from <b>{$source_name}</b> successfully scouted <b>{$target_name}</b>.{$resourcesText}</p>";
                                } else {
                                    $messages[] = "<p class='error-message'>Your scouts from <b>{$source_name}</b> were intercepted at <b>{$target_name}</b>.</p>";
                                }
                            }

                            if (in_array($attack['target_village_id'], $user_village_ids)) {
                                if ($success) {
                                    $messages[] = "<p class='error-message'>Enemy scouts from <b>{$source_name}</b> gathered intel on <b>{$target_name}</b>.</p>";
                                } else {
                                    $messages[] = "<p class='success-message'>Enemy scouts from <b>{$source_name}</b> were caught near <b>{$target_name}</b>.</p>";
                                }
                            }
                        } else {
                            $winner = $report['attacker_won'] ? 'attacker' : 'defender';
                            $loot = $report['details']['loot'] ?? ['wood' => 0, 'clay' => 0, 'iron' => 0];

                            // Message for the attacker (if the source village belongs to the user)
                            if (in_array($attack['source_village_id'], $user_village_ids)) {
                                if ($winner === 'attacker') {
                                    $messages[] = "<p class='success-message'>Your attack from village <b>{$source_name}</b> on <b>{$target_name}</b> ended in victory! Looted: Wood: {$loot['wood']}, Clay: {$loot['clay']}, Iron: {$loot['iron']}.</p>";
                                } else {
                                    $messages[] = "<p class='error-message'>Your attack from village <b>{$source_name}</b> on <b>{$target_name}</b> ended in defeat.</p>";
                                }
                            }

                            // Message for the defender (if the target village belongs to the user)
                            if (in_array($attack['target_village_id'], $user_village_ids)) {
                                if ($winner === 'defender') {
                                    $messages[] = "<p class='success-message'>Your village <b>{$target_name}</b> defended against an attack from village <b>{$source_name}</b>.</p>";
                                } else {
                                    $messages[] = "<p class='error-message'>Your village <b>{$target_name}</b> was defeated in an attack from village <b>{$source_name}</b>. Resources were lost.</p>";
                                }
                            }

                            // Loyalty and conquest messaging
                            $loyaltyInfo = $report['details']['loyalty'] ?? null;
                            if ($loyaltyInfo && !empty($loyaltyInfo['drop'])) {
                                if (!empty($loyaltyInfo['conquered'])) {
                                    if (in_array($attack['source_village_id'], $user_village_ids)) {
                                        $messages[] = "<p class='success-message'>Loyalty of <b>{$target_name}</b> dropped to zero. The village was conquered!</p>";
                                    }
                                    if (in_array($attack['target_village_id'], $user_village_ids)) {
                                        $messages[] = "<p class='error-message'>Your village <b>{$target_name}</b> was conquered after losing all loyalty.</p>";
                                    }
                                } else {
                                    $drop = (int)$loyaltyInfo['drop'];
                                    $after = (int)$loyaltyInfo['after'];
                                    if (in_array($attack['source_village_id'], $user_village_ids)) {
                                        $messages[] = "<p class='info-message'>Noble attack reduced loyalty of <b>{$target_name}</b> by {$drop} to {$after}.</p>";
                                    }
                                    if (in_array($attack['target_village_id'], $user_village_ids)) {
                                        $messages[] = "<p class='error-message'>Loyalty of <b>{$target_name}</b> was reduced by {$drop}. Current loyalty: {$after}.</p>";
                                    }
                                }
                            }
                        }

                        // Add a link to the full battle report here if available
                    } else {
                        error_log("Error: No battle report found for completed attack ID: " . $attack['id']);
                        $messages[] = "<p class='error-message'>An error occurred while generating the battle report for attack ID: " . $attack['id'] . ".</p>";
                    }
                } else {
                    error_log("Error: Attack details not found for attack ID: " . $attack['id'] . " while generating messages.");
                    $messages[] = "<p class='error-message'>An error occurred while fetching attack details for attack ID: " . $attack['id'] . ".</p>";
                }
            } else {
                error_log("Battle processing error for attack ID: " . $attack['id'] . ". Result: " . json_encode($battle_result));
                $messages[] = "<p class='error-message'>An error occurred while processing the battle for attack ID: " . $attack['id'] . ".</p>";
            }
        }

        $attacks_result->free(); // Free memory
        $stmt_get_attacks->close();

        return $messages; // Return collected messages
    }
    
    /**
     * Fetches a battle report by attack ID for post-battle messaging.
     * @param int $attack_id Attack ID
     * @return array|null Battle report data or null if missing.
     */
    public function getBattleReportForAttack(int $attack_id): ?array
    {
         $stmt = $this->conn->prepare("
            SELECT br.id, br.attacker_won, br.report_data, br.battle_time, a.attack_type
            FROM battle_reports br
            JOIN attacks a ON a.id = br.attack_id
            WHERE br.attack_id = ?
            LIMIT 1
         ");
         if ($stmt === false) {
              error_log("Prepare failed for getBattleReportForAttack: " . $this->conn->error);
              return null;
         }
         $stmt->bind_param("i", $attack_id);
         $stmt->execute();
         $result = $stmt->get_result();
         $report = $result->fetch_assoc();
         $stmt->close();
         if (!$report) {
             return null;
         }

         $details = json_decode($report['report_data'], true);
         if (!is_array($details)) {
             $details = [];
         }

         return [
             'id' => $report['id'],
             'attack_type' => $report['attack_type'],
             'type' => $details['type'] ?? $report['attack_type'] ?? 'battle',
             'attacker_won' => (int)$report['attacker_won'],
             'details' => $details,
             'battle_time' => $report['battle_time']
         ];
    }

    /**
     * Handle support arrival (no battle, units join target garrison).
     */
    private function processSupportArrival(int $attack_id): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM attacks WHERE id = ? AND is_completed = 0 AND is_canceled = 0 LIMIT 1");
        $stmt->bind_param("i", $attack_id);
        $stmt->execute();
        $attack = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$attack) {
            return ['success' => false, 'error' => 'Support command missing.'];
        }

        $units = $this->getAttackUnits($attack_id);
        $unitsMap = [];
        foreach ($units as $unit) {
            $unitsMap[$unit['unit_type_id']] = $unit['count'];
        }

        $this->conn->begin_transaction();
        try {
            $this->addUnitsToVillage((int)$attack['target_village_id'], $unitsMap);

            $stmtUpdate = $this->conn->prepare("UPDATE attacks SET is_completed = 1 WHERE id = ?");
            $stmtUpdate->bind_param("i", $attack_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('Support arrival failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Support could not arrive.'];
        }

        return ['success' => true];
    }

    /**
     * Handle return march arrival (restore units to target village).
     */
    private function processReturnArrival(int $attack_id): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM attacks WHERE id = ? AND is_completed = 0 AND is_canceled = 0 LIMIT 1");
        $stmt->bind_param("i", $attack_id);
        $stmt->execute();
        $attack = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$attack) {
            return ['success' => false, 'error' => 'Return command missing.'];
        }

        $units = $this->getAttackUnits($attack_id);
        $unitsMap = [];
        foreach ($units as $unit) {
            $unitsMap[$unit['unit_type_id']] = $unit['count'];
        }

        $this->conn->begin_transaction();
        try {
            $this->addUnitsToVillage((int)$attack['target_village_id'], $unitsMap);

            $stmtUpdate = $this->conn->prepare("UPDATE attacks SET is_completed = 1 WHERE id = ?");
            $stmtUpdate->bind_param("i", $attack_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            $this->conn->commit();

            // Notify owner that troops have returned
            $owner = $this->villageManager->getVillageInfo((int)$attack['target_village_id']);
            if ($owner && !empty($owner['user_id'])) {
                $this->notificationManager->addNotification(
                    (int)$owner['user_id'],
                    'Your troops have returned to ' . ($owner['name'] ?? 'village') . '.',
                    'info',
                    '/game/game.php'
                );
            }
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('Return arrival failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Return march failed.'];
        }

        return ['success' => true];
    }

    /**
     * Handle fake attack arrival (no battle; troops turn around).
     */
    private function processFakeArrival(int $attack_id): array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM attacks
            WHERE id = ? AND is_completed = 0 AND is_canceled = 0
            LIMIT 1
        ");
        if ($stmt === false) {
            return ['success' => false, 'error' => 'Failed to load fake attack.'];
        }
        $stmt->bind_param("i", $attack_id);
        $stmt->execute();
        $attack = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$attack || $attack['attack_type'] !== 'fake') {
            return ['success' => false, 'error' => 'Fake attack not found.'];
        }

        $units = $this->getAttackUnits($attack_id);
        $unitsMap = [];
        foreach ($units as $unit) {
            $unitsMap[$unit['unit_type_id']] = (int)$unit['count'];
        }

        $startTs = strtotime($attack['start_time']);
        $arrivalTs = strtotime($attack['arrival_time']);
        $travel = max(1, $arrivalTs - $startTs);

        $returnStart = time();
        $returnArrival = $returnStart + $travel;

        $this->conn->begin_transaction();
        try {
            // Mark fake as completed
            $stmt_complete = $this->conn->prepare("UPDATE attacks SET is_completed = 1 WHERE id = ?");
            $stmt_complete->bind_param("i", $attack_id);
            $stmt_complete->execute();
            $stmt_complete->close();

            // Insert return march
            $stmt_return = $this->conn->prepare("
                INSERT INTO attacks (
                    source_village_id, target_village_id,
                    attack_type, start_time, arrival_time,
                    is_completed, is_canceled, target_building
                ) VALUES (?, ?, 'return', FROM_UNIXTIME(?), FROM_UNIXTIME(?), 0, 0, NULL)
            ");
            $stmt_return->bind_param(
                "iiii",
                $attack['target_village_id'],
                $attack['source_village_id'],
                $returnStart,
                $returnArrival
            );
            $stmt_return->execute();
            $returnAttackId = $stmt_return->insert_id;
            $stmt_return->close();

            foreach ($unitsMap as $unit_type_id => $count) {
                $stmt_add_units = $this->conn->prepare("
                    INSERT INTO attack_units (attack_id, unit_type_id, count)
                    VALUES (?, ?, ?)
                ");
                $stmt_add_units->bind_param("iii", $returnAttackId, $unit_type_id, $count);
                $stmt_add_units->execute();
                $stmt_add_units->close();
            }

            $this->conn->commit();
            return [
                'success' => true,
                'message' => 'Fake attack turned around.',
                'return_attack_id' => $returnAttackId,
                'return_arrival' => $returnArrival
            ];
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('Fake arrival failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fake attack resolution failed.'];
        }
    }

    /**
     * Add units to a village (update or insert counts).
     */
    private function addUnitsToVillage(int $villageId, array $units): void
    {
        foreach ($units as $unitTypeId => $count) {
            $stmt_check_existing = $this->conn->prepare("
                SELECT id, count 
                FROM village_units 
                WHERE village_id = ? AND unit_type_id = ?
            ");
            $stmt_check_existing->bind_param("ii", $villageId, $unitTypeId);
            $stmt_check_existing->execute();
            $existing_result = $stmt_check_existing->get_result();

            if ($existing_result->num_rows > 0) {
                $existing = $existing_result->fetch_assoc();
                $new_count = $existing['count'] + $count;
                $stmt_update = $this->conn->prepare("
                    UPDATE village_units 
                    SET count = ? 
                    WHERE id = ?
                ");
                $stmt_update->bind_param("ii", $new_count, $existing['id']);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
                $stmt_insert = $this->conn->prepare("
                    INSERT INTO village_units (
                        village_id, unit_type_id, count
                    ) VALUES (?, ?, ?)
                ");
                $stmt_insert->bind_param("iii", $villageId, $unitTypeId, $count);
                $stmt_insert->execute();
                $stmt_insert->close();
            }

            $stmt_check_existing->close();
        }
    }
    /**
     * Processes a single battle: calculates losses/loot, updates DB, creates report.
     * @param int $attack_id Attack ID to process.
     * @return array Battle processing result (success/error).
     */
    private function processBattle(int $attack_id): array
    {
        $this->ensureLoyaltyColumn();

        // Fetch attack details
        $stmt_get_attack = $this->conn->prepare("
            SELECT id, source_village_id, target_village_id, attack_type, target_building
            FROM attacks
            WHERE id = ?
        ");
        $stmt_get_attack->bind_param("i", $attack_id);
        $stmt_get_attack->execute();
        $attack = $stmt_get_attack->get_result()->fetch_assoc();
        $stmt_get_attack->close();
        if (!$attack) {
            return [ 'success' => false, 'error' => 'Attack does not exist.' ];
        }

        $isRaid = $attack['attack_type'] === 'raid';

        // User IDs (needed for loyalty transfer and reports)
        $stmt_users = $this->conn->prepare("SELECT v1.user_id as attacker_user_id, v2.user_id as defender_user_id FROM villages v1, villages v2 WHERE v1.id = ? AND v2.id = ?");
        $stmt_users->bind_param("ii", $attack['source_village_id'], $attack['target_village_id']);
        $stmt_users->execute();
        $users = $stmt_users->get_result()->fetch_assoc();
        $stmt_users->close();
        $attacker_user_id = $users['attacker_user_id'] ?? null;
        $defender_user_id = $users['defender_user_id'] ?? null;

        // Smithy research levels for both sides
        $attackerResearch = $this->getResearchLevelsMap($attack['source_village_id']);
        $defenderResearch = $this->getResearchLevelsMap($attack['target_village_id']);

        // Fetch attacking units with metadata and apply tech bonuses
        $stmt_get_attack_units = $this->conn->prepare("
            SELECT au.unit_type_id, au.count, ut.attack, ut.defense, ut.name, ut.carry_capacity, ut.internal_name, ut.building_type
            FROM attack_units au
            JOIN unit_types ut ON au.unit_type_id = ut.id
            WHERE au.attack_id = ?
        ");
        $stmt_get_attack_units->bind_param("i", $attack_id);
        $stmt_get_attack_units->execute();
        $attack_units_result = $stmt_get_attack_units->get_result();
        $attacking_units = [];
        while ($unit = $attack_units_result->fetch_assoc()) {
            $category = $this->getUnitCategory($unit['internal_name'], $unit['building_type'] ?? '');
            $effective = $this->applyTechBonusesToUnit($unit, $category, $attackerResearch, false);
            $attacking_units[$unit['unit_type_id']] = [
                'unit_type_id' => (int)$unit['unit_type_id'],
                'internal_name' => $unit['internal_name'],
                'name' => $unit['name'],
                'category' => $category,
                'attack' => $effective['attack'],
                'defense' => $effective['defense'],
                'count' => (int)$unit['count'],
                'carry_capacity' => (int)$unit['carry_capacity'],
                'building_type' => $unit['building_type'] ?? ''
            ];
        }
        $stmt_get_attack_units->close();
        // Paladin weapon bonuses (attacker)
        $this->applyPaladinWeaponBonuses($attacking_units, 'attacker');

        // Fetch defending units and apply tech bonuses
        $stmt_get_defense_units = $this->conn->prepare("
            SELECT vu.unit_type_id, vu.count, ut.attack, ut.defense, ut.name, ut.internal_name, ut.building_type
            FROM village_units vu
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE vu.village_id = ?
        ");
        $stmt_get_defense_units->bind_param("i", $attack['target_village_id']);
        $stmt_get_defense_units->execute();
        $defense_units_result = $stmt_get_defense_units->get_result();
        $defending_units = [];
        while ($unit = $defense_units_result->fetch_assoc()) {
            $category = $this->getUnitCategory($unit['internal_name'], $unit['building_type'] ?? '');
            $effective = $this->applyTechBonusesToUnit($unit, $category, $defenderResearch, true);
            $defending_units[$unit['unit_type_id']] = [
                'unit_type_id' => (int)$unit['unit_type_id'],
                'internal_name' => $unit['internal_name'],
                'name' => $unit['name'],
                'category' => $category,
                'attack' => $effective['attack'],
                'defense' => $effective['defense'],
                'count' => (int)$unit['count'],
                'carry_capacity' => 0,
                'building_type' => $unit['building_type'] ?? ''
            ];
        }
        $stmt_get_defense_units->close();
        // Paladin weapon bonuses (defender)
        $this->applyPaladinWeaponBonuses($defending_units, 'defender');

        // --- RANDOMNESS, MORALE & WALL/FAITH ---
        $attack_random = $this->rollRandomFactor(self::RANDOM_VARIANCE);
        $defense_random = $this->rollRandomFactor(self::RANDOM_VARIANCE);
        $attacker_points = $this->getVillagePointsWithFallback($attack['source_village_id']);
        $defender_points = $this->getVillagePointsWithFallback($attack['target_village_id']);
        $morale = $this->calculateMoraleFactor($attacker_points, $defender_points);

        $wall_level = $this->buildingManager->getBuildingLevel($attack['target_village_id'], 'wall');
        $effective_wall_level = $wall_level;
        $wall_bonus = 1 + ($effective_wall_level * self::WALL_BONUS_PER_LEVEL);
        $faith_bonus = $this->calculateFaithDefenseBonus($attack['target_village_id']);
        $defense_multiplier = $wall_bonus * $faith_bonus * $defense_random;

        // --- PHASED COMBAT (Infantry -> Cavalry -> Archer) ---
        $initial_attacker_counts = [];
        $initial_defender_counts = [];
        foreach ($attacking_units as $id => $unit) {
            $initial_attacker_counts[$id] = $unit['count'];
        }
        foreach ($defending_units as $id => $unit) {
            $initial_defender_counts[$id] = $unit['count'];
        }

        $phaseReports = [];
        foreach (self::PHASE_ORDER as $phase) {
            $phaseReports[] = $this->resolveCombatPhase(
                $phase,
                $attacking_units,
                $defending_units,
                $morale,
                $defense_multiplier,
                $attack_random,
                $isRaid
            );
        }

        $attackerAlive = array_sum(array_column($attacking_units, 'count')) > 0;
        $defenderAlive = array_sum(array_column($defending_units, 'count')) > 0;

        // Overstack penalty (optional world rule)
        $overstack = $this->getOverstackMultiplier($defending_units);
        $defense_multiplier *= $overstack['multiplier'];

        $attackPowerFinal = $this->sumPower($attacking_units, 'attack') * $morale * $attack_random;
        $defensePowerFinal = $this->sumPower($defending_units, 'defense') * $defense_multiplier;

        $attacker_win = $attackerAlive && (($defenderAlive === 0) || $attackPowerFinal >= $defensePowerFinal);
        if (!$attacker_win) {
            foreach ($attacking_units as &$unit) {
                $unit['count'] = 0;
            }
            unset($unit);
        } elseif ($attacker_win && $defenderAlive) {
            foreach ($defending_units as &$unit) {
                $unit['count'] = 0;
            }
            unset($unit);
            $defenderAlive = false;
        }

        // --- LOSSES & REMAINING UNITS ---
        $attacker_losses = [];
        $defender_losses = [];
        $remaining_attacking_units = [];
        $remaining_defending_units = [];

        foreach ($initial_attacker_counts as $unit_type_id => $initial) {
            $remaining = $attacking_units[$unit_type_id]['count'] ?? 0;
            $lost = max(0, $initial - $remaining);
            $attacker_losses[$unit_type_id] = [
                'unit_name' => $attacking_units[$unit_type_id]['name'] ?? 'Unit',
                'initial_count' => $initial,
                'lost_count' => $lost,
                'remaining_count' => $remaining
            ];
            if ($remaining > 0) {
                $remaining_attacking_units[$unit_type_id] = $remaining;
            }
        }

        foreach ($initial_defender_counts as $unit_type_id => $initial) {
            $remaining = $defending_units[$unit_type_id]['count'] ?? 0;
            $lost = max(0, $initial - $remaining);
            $defender_losses[$unit_type_id] = [
                'unit_name' => $defending_units[$unit_type_id]['name'] ?? 'Unit',
                'initial_count' => $initial,
                'lost_count' => $lost,
                'remaining_count' => $remaining
            ];
            if ($remaining > 0) {
                $remaining_defending_units[$unit_type_id] = $remaining;
            }
        }

        // --- LOOT ---
        $loot = [ 'wood' => 0, 'clay' => 0, 'iron' => 0 ];
        $vaultPct = 0.0;
        $vaultProtected = ['wood' => 0, 'clay' => 0, 'iron' => 0];
        $availableAfterProtection = null;
        $plunderDrMultiplier = 1.0;
        if ($attacker_win && !empty($remaining_attacking_units)) {
            $attack_capacity = 0;
            foreach ($remaining_attacking_units as $unit_type_id => $count) {
                if (isset($attacking_units[$unit_type_id])) {
                    $attack_capacity += ($attacking_units[$unit_type_id]['carry_capacity'] ?? 0) * $count;
                }
            }
            if ($attack_capacity > 0) {
                $stmt_res = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
                $stmt_res->bind_param("i", $attack['target_village_id']);
                $stmt_res->execute();
                $res = $stmt_res->get_result()->fetch_assoc();
                $stmt_res->close();

                $hiddenPerResource = $this->getHiddenResourcesPerType($attack['target_village_id']);
                if (!class_exists('WorldManager')) {
                    require_once __DIR__ . '/WorldManager.php';
                }
                if (class_exists('WorldManager')) {
                    $wm = new WorldManager($this->conn);
                    if (method_exists($wm, 'getVaultProtectionPercent')) {
                        $vaultPct = (float)$wm->getVaultProtectionPercent();
                    }
                }
                $vaultFactor = max(0.0, min(100.0, $vaultPct)) / 100.0;
                $vaultProtected = [
                    'wood' => (int)ceil(($res['wood'] ?? 0) * $vaultFactor),
                    'clay' => (int)ceil(($res['clay'] ?? 0) * $vaultFactor),
                    'iron' => (int)ceil(($res['iron'] ?? 0) * $vaultFactor),
                ];
                $available = [
                    'wood' => max(0, $res['wood'] - max($hiddenPerResource, $vaultProtected['wood'])),
                    'clay' => max(0, $res['clay'] - max($hiddenPerResource, $vaultProtected['clay'])),
                    'iron' => max(0, $res['iron'] - max($hiddenPerResource, $vaultProtected['iron'])),
                ];
                $availableAfterProtection = $available;

                $max_available = $available['wood'] + $available['clay'] + $available['iron'];
                $raidFactorApplied = $isRaid ? self::RAID_LOOT_FACTOR : 1.0;
                if ($isRaid) {
                    $max_available = floor($max_available * self::RAID_LOOT_FACTOR);
                }
                $plunderDrMultiplier = $this->getPlunderDiminishingReturnsMultiplier(
                    (int)$attacker_user_id,
                    (int)$attack['target_village_id']
                );
                $max_available = (int)floor($max_available * $plunderDrMultiplier);
                $total_loot = min($attack_capacity, $max_available);
                if ($total_loot > 0) {
                    $share = (int)floor($total_loot / 3);
                    $loot['wood'] = min($available['wood'], $share);
                    $loot['clay'] = min($available['clay'], $share);
                    $loot['iron'] = min($available['iron'], $total_loot - $loot['wood'] - $loot['clay']);
                }
                $stmt_update = $this->conn->prepare("UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ? WHERE id = ?");
                $stmt_update->bind_param("iiii", $loot['wood'], $loot['clay'], $loot['iron'], $attack['target_village_id']);
                $stmt_update->execute();
                $stmt_update->close();
            }
        }

        // --- WALL DAMAGE (RAMS) ---
        $wall_damage_report = ['initial_level' => $wall_level, 'final_level' => $wall_level];
        if ($attacker_win && !$isRaid) {
            $surviving_rams = 0;
            foreach ($remaining_attacking_units as $unit_type_id => $count) {
                if (isset($attacking_units[$unit_type_id]) && $attacking_units[$unit_type_id]['internal_name'] === 'ram') {
                    $surviving_rams += $count;
                }
            }

            if ($surviving_rams > 0 && $wall_level > 0) {
                $damage_value = ($surviving_rams * 2) - ($wall_level * 0.5);
                $levels_destroyed = (int)floor(max(0, $damage_value));
                if ($levels_destroyed > 0) {
                    $new_wall_level = max(0, $wall_level - min($levels_destroyed, $wall_level));
                    $wall_damage_report['final_level'] = $new_wall_level;
                }
            }
        }

        // --- BUILDING DAMAGE (CATAPULTS) ---
        $building_damage_report = null;
        if ($attacker_win && !$isRaid) {
            $surviving_catapults = 0;
            foreach ($remaining_attacking_units as $unit_type_id => $count) {
                if (isset($attacking_units[$unit_type_id]) && $attacking_units[$unit_type_id]['internal_name'] === 'catapult') {
                    $surviving_catapults += $count;
                }
            }

            if ($surviving_catapults > 0) {
                $target_building_name = $attack['target_building'];

                if (empty($target_building_name)) {
                    $village_buildings = $this->buildingManager->getVillageBuildingsLevels($attack['target_village_id']);
                    $possible_targets = array_filter($village_buildings, function($level) {
                        return $level > 0;
                    });
                    if (!empty($possible_targets)) {
                        $target_building_name = array_rand($possible_targets);
                    }
                }

                if (!empty($target_building_name)) {
                    $initial_level = $this->buildingManager->getBuildingLevel($attack['target_village_id'], $target_building_name);
                    if ($initial_level > 0) {
                        $accuracy_factor = 0.25;
                        if (!empty($attack['target_building'])) {
                            $accuracy_factor += 0.25;
                        }
                        $accuracy_factor = min(1.0, $accuracy_factor);

                        $hitRoll = $this->randomFloat(0, 1);
                        if ($hitRoll > $accuracy_factor) {
                            $village_buildings = $this->buildingManager->getVillageBuildingsLevels($attack['target_village_id']);
                            $possible_targets = array_filter($village_buildings, fn($level) => $level > 0);
                            if (!empty($possible_targets)) {
                                $target_building_name = array_rand($possible_targets);
                                $initial_level = $possible_targets[$target_building_name];
                            }
                        }

                        $catapult_bonus = 1 + (($attackerResearch['improved_catapult'] ?? 0) * self::RESEARCH_BONUS_PER_LEVEL);
                        $damage_value = $surviving_catapults * 2 * $accuracy_factor * $catapult_bonus;
                        $levels_destroyed = (int)floor($damage_value);

                        if ($levels_destroyed > 0) {
                            $final_level = max(0, $initial_level - $levels_destroyed);
                            $building_damage_report = [
                                'building_name' => $target_building_name,
                                'initial_level' => $initial_level,
                                'final_level' => $final_level
                            ];
                        }
                    }
                }
            }
        }

        // --- LOYALTY / CONQUEST (NOBLES) ---
        $loyalty_report = null;
        $villageConquered = false;
        $loyalty_before = $this->getVillageLoyalty($attack['target_village_id']);
        $loyalty_after = $loyalty_before;
        $loyalty_cap = method_exists($this->villageManager, 'getEffectiveLoyaltyCap')
            ? (int)round($this->villageManager->getEffectiveLoyaltyCap($attack['target_village_id']))
            : self::LOYALTY_MAX;
        $dropMultiplier = method_exists($this->villageManager, 'getLoyaltyDropMultiplier')
            ? $this->villageManager->getLoyaltyDropMultiplier($attack['target_village_id'])
            : 1.0;

        $loyalty_floor = $this->getEffectiveLoyaltyFloor($attacking_units, (int)$attack['target_village_id']);
        $noblePresent = $this->hasNobleUnit($attacking_units);
        $survivingNobles = $this->countNobleUnits($attacking_units);
        $defenderVillageCount = $defender_user_id ? $this->getVillageCountForUser((int)$defender_user_id) : null;

        $conquestReason = $noblePresent ? 'attempt' : 'no_noble_present';
        if ($noblePresent) {
            $dropMin = defined('NOBLE_MIN_DROP') ? (int)NOBLE_MIN_DROP : self::LOYALTY_DROP_MIN;
            $dropMax = defined('NOBLE_MAX_DROP') ? (int)NOBLE_MAX_DROP : self::LOYALTY_DROP_MAX;
            $dropBase = 0;
            $dropApplied = 0;

            if ($attacker_win && !$defenderAlive) {
                // Successful noble strike
                $dropBase = random_int($dropMin, $dropMax);
                $dropApplied = max(1, (int)round($dropBase * $dropMultiplier));
                $loyalty_after = max($loyalty_floor, $loyalty_before - $dropApplied);
                $villageConquered = ($loyalty_floor === self::LOYALTY_MIN) && $loyalty_after <= self::LOYALTY_MIN;

                // Enforce conquest point-range gate (50%-150%) when not barbarian
                $defender_points = $this->getVillagePointsWithFallback($attack['target_village_id']);
                $attacker_points = $this->getVillagePointsWithFallback($attack['source_village_id']);
                if ($attacker_points > 0 && $defender_points > 0) {
                    $ratio = $defender_points / $attacker_points;
                    if ($ratio < 0.5 || $ratio > 1.5) {
                        $villageConquered = false; // Loyalty can drop, but cannot capture out-of-range targets
                        $conquestReason = 'point_range_block';
                    }
                }
                // Last-village protection: cannot conquer defender's final village.
                if ($defenderVillageCount !== null && $defenderVillageCount <= 1) {
                    $villageConquered = false;
                    $conquestReason = 'last_village_protected';
                }

                if ($villageConquered) {
                    $loyalty_after = $this->getConqueredLoyaltyReset((float)$loyalty_cap);
                    $conquestReason = 'captured';
                } elseif ($dropApplied > 0 && $conquestReason === 'attempt') {
                    $conquestReason = 'drop_applied';
                }
            } else {
                // Failed attempt still chips loyalty
                $dropBase = random_int(self::LOYALTY_FAIL_DROP_MIN, self::LOYALTY_FAIL_DROP_MAX);
                $dropApplied = max(0, (int)round($dropBase * $dropMultiplier));
                if ($dropApplied > 0) {
                    $loyalty_after = max($loyalty_floor, $loyalty_before - $dropApplied);
                    $conquestReason = 'drop_on_failed_wave';
                } else {
                    $conquestReason = $attacker_win ? 'defender_survived' : 'attacker_lost';
                }
            }

            $loyalty_report = [
                'before' => $loyalty_before,
                'after' => $loyalty_after,
                'drop' => $dropApplied,
                'drop_base' => $dropBase,
                'conquered' => $villageConquered,
                'floor' => $loyalty_floor,
                'cap' => $loyalty_cap,
                'drop_multiplier' => $dropMultiplier,
                'reason' => $conquestReason,
                'surviving_nobles' => $survivingNobles
            ];

            $logPath = __DIR__ . '/../../logs/conquest_attempts.log';
            $logPayload = [
                'ts' => time(),
                'attack_id' => $attack_id,
                'source_village_id' => $attack['source_village_id'],
                'target_village_id' => $attack['target_village_id'],
                'attacker_user_id' => $attacker_user_id,
                'defender_user_id' => $defender_user_id,
                'attacker_won' => $attacker_win,
                'loyalty_before' => $loyalty_before,
                'loyalty_after' => $loyalty_after,
                'drop' => $dropApplied,
                'drop_base' => $dropBase,
                'conquered' => $villageConquered,
                'reason' => $conquestReason,
                'defender_village_count' => $defenderVillageCount,
                'surviving_nobles' => $survivingNobles
            ];
            @file_put_contents($logPath, json_encode($logPayload) . PHP_EOL, FILE_APPEND);
        }

        // --- TRANSACTION ---
        $this->conn->begin_transaction();
        try {
            if ($wall_damage_report['initial_level'] !== $wall_damage_report['final_level']) {
                $this->buildingManager->setBuildingLevel(
                    $attack['target_village_id'],
                    'wall',
                    $wall_damage_report['final_level']
                );
            }

            if ($building_damage_report && $building_damage_report['initial_level'] !== $building_damage_report['final_level']) {
                $this->buildingManager->setBuildingLevel(
                    $attack['target_village_id'],
                    $building_damage_report['building_name'],
                    $building_damage_report['final_level']
                );
            }

            $stmt_complete_attack = $this->conn->prepare("
                UPDATE attacks 
                SET is_completed = 1 
                WHERE id = ?
            ");
            $stmt_complete_attack->bind_param("i", $attack_id);
            $stmt_complete_attack->execute();
            $stmt_complete_attack->close();

            foreach ($defending_units as $unit_type_id => $unit) {
                $new_count = isset($remaining_defending_units[$unit_type_id]) ? $remaining_defending_units[$unit_type_id] : 0;
                if ($new_count > 0) {
                    $stmt_update = $this->conn->prepare("
                        UPDATE village_units 
                        SET count = ? 
                        WHERE village_id = ? AND unit_type_id = ?
                    ");
                    $stmt_update->bind_param("iii", $new_count, $attack['target_village_id'], $unit_type_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    $stmt_delete = $this->conn->prepare("
                        DELETE FROM village_units 
                        WHERE village_id = ? AND unit_type_id = ?
                    ");
                    $stmt_delete->bind_param("ii", $attack['target_village_id'], $unit_type_id);
                    $stmt_delete->execute();
                    $stmt_delete->close();
                }
            }

            if ($loyalty_report) {
                if ($villageConquered && $attacker_user_id !== null) {
                    $this->transferVillageOwnership($attack['target_village_id'], $attacker_user_id, $loyalty_after);
                } else {
                    $this->updateVillageLoyalty($attack['target_village_id'], $loyalty_after);
                }
            }

            foreach ($remaining_attacking_units as $unit_type_id => $count) {
                $stmt_check_existing = $this->conn->prepare("
                    SELECT id, count 
                    FROM village_units 
                    WHERE village_id = ? AND unit_type_id = ?
                ");
                $stmt_check_existing->bind_param("ii", $attack['source_village_id'], $unit_type_id);
                $stmt_check_existing->execute();
                $existing_result = $stmt_check_existing->get_result();
                if ($existing_result->num_rows > 0) {
                    $existing = $existing_result->fetch_assoc();
                    $new_count = $existing['count'] + $count;
                    $stmt_update = $this->conn->prepare("
                        UPDATE village_units 
                        SET count = ? 
                        WHERE id = ?
                    ");
                    $stmt_update->bind_param("ii", $new_count, $existing['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    $stmt_insert = $this->conn->prepare("
                        INSERT INTO village_units (
                            village_id, unit_type_id, count
                        ) VALUES (?, ?, ?)
                    ");
                    $stmt_insert->bind_param("iii", $attack['source_village_id'], $unit_type_id, $count);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                $stmt_check_existing->close();
            }

            $details = [
                'type' => 'battle',
                'attacker_losses' => $attacker_losses,
                'defender_losses' => $defender_losses,
                'loot' => $loot,
                'attack_luck' => $attack_random,
                'defense_luck' => $defense_random,
                'morale' => $morale,
                'environment' => [
                    'night' => $this->isNightTimeWorldConfig(),
                    'terrain_attack_multiplier' => $this->getEnvMultiplier('terrain_attack_multiplier'),
                    'terrain_defense_multiplier' => $this->getEnvMultiplier('terrain_defense_multiplier'),
                    'weather_attack_multiplier' => $this->getEnvMultiplier('weather_attack_multiplier'),
                    'weather_defense_multiplier' => $this->getEnvMultiplier('weather_defense_multiplier'),
                ],
                'overstack' => $overstack,
                'plunder_dr_multiplier' => $plunderDrMultiplier,
                'attacker_points' => $attacker_points,
                'defender_points' => $defender_points,
                'attack_type' => $attack['attack_type'],
                'wall_level' => $wall_level,
                'effective_wall_level' => $effective_wall_level,
                'wall_bonus' => $wall_bonus,
                'faith_bonus' => $faith_bonus,
                'wall_damage' => $wall_damage_report,
                'building_damage' => $building_damage_report,
                'hiding_place_level' => $this->buildingManager->getBuildingLevel($attack['target_village_id'], 'hiding_place'),
                'hidden_per_resource' => $this->getHiddenResourcesPerType($attack['target_village_id']),
                'vault_protection_percent' => $vaultPct,
                'vault_protected' => $vaultProtected,
                'available_after_protection' => $availableAfterProtection,
                'report_version' => self::REPORT_VERSION,
                'modifiers' => [
                    'wall_level' => $wall_level,
                    'effective_wall_level' => $effective_wall_level,
                    'wall_bonus' => $wall_bonus,
                    'morale' => $morale,
                    'luck' => [
                        'attack' => $attack_random,
                        'defense' => $defense_random
                    ],
                    'environment' => [
                        'night' => $this->isNightTimeWorldConfig(),
                        'terrain_attack_multiplier' => $this->getEnvMultiplier('terrain_attack_multiplier'),
                        'terrain_defense_multiplier' => $this->getEnvMultiplier('terrain_defense_multiplier'),
                        'weather_attack_multiplier' => $this->getEnvMultiplier('weather_attack_multiplier'),
                        'weather_defense_multiplier' => $this->getEnvMultiplier('weather_defense_multiplier'),
                    ],
                    'overstack' => $overstack
                ],
                'attack_power' => $attackPowerFinal,
                'defense_power' => $defensePowerFinal,
                'phase_reports' => $phaseReports,
                'research' => [
                    'attacker' => $attackerResearch,
                    'defender' => $defenderResearch
                ],
                'loyalty' => $loyalty_report,
                'overstack' => $overstack,
                'last_village_protected' => ($defenderVillageCount !== null && $defenderVillageCount <= 1)
            ];
            $report_data_json = json_encode($details);
            $attacker_won_int = $attacker_win ? 1 : 0;

            $stmt_add_report = $this->conn->prepare("
                INSERT INTO battle_reports (
                    attack_id, source_village_id, target_village_id,
                    battle_time, attacker_user_id, defender_user_id,
                    attacker_won, report_data
                ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)
            ");
            $stmt_add_report->bind_param(
                "iiiiis",
                $attack_id, $attack['source_village_id'], $attack['target_village_id'],
                $attacker_user_id, $defender_user_id,
                $attacker_won_int, $report_data_json
            );
            $stmt_add_report->execute();
            $stmt_add_report->close();

            $attackerTitle = ucfirst($attack['attack_type'] === 'raid' ? 'Raid' : 'Attack') . " on " . $this->getVillageName($attack['target_village_id']);
            $defenderTitle = "Defense at " . $this->getVillageName($attack['target_village_id']);
            if ($this->reportManager) {
                $this->reportManager->addReport(
                    $attacker_user_id,
                    $attack['attack_type'] === 'support' ? 'support' : 'attack',
                    $attackerTitle,
                    $details,
                    $attack_id
                );
                $this->reportManager->addReport(
                    $defender_user_id,
                    $attack['attack_type'] === 'support' ? 'support' : 'defense',
                    $defenderTitle,
                    $details,
                    $attack_id
                );
            }

            // Notifications for battle outcome
            if ($attacker_user_id) {
                $this->notificationManager->addNotification(
                    $attacker_user_id,
                    $attacker_win ? "Your attack on {$this->getVillageName($attack['target_village_id'])} succeeded." : "Your attack on {$this->getVillageName($attack['target_village_id'])} failed.",
                    $attacker_win ? 'success' : 'error',
                    '/messages/reports.php?report_id=' . $attack_id
                );
            }
            if ($defender_user_id) {
                $this->notificationManager->addNotification(
                    $defender_user_id,
                    $attacker_win ? "Your village {$this->getVillageName($attack['target_village_id'])} was attacked and lost." : "You defended {$this->getVillageName($attack['target_village_id'])} successfully.",
                    $attacker_win ? 'error' : 'success',
                    '/messages/reports.php?report_id=' . $attack_id
                );
            }

            $this->conn->commit();
            return [ 'success' => true ];
        } catch (Exception $e) {
            $this->conn->rollback();
            return [ 'success' => false, 'error' => $e->getMessage() ];
        }
    }


    private function processSpyMission(int $attack_id): array
    {
        // Fetch attack details
        $stmt_get_attack = $this->conn->prepare("
            SELECT id, source_village_id, target_village_id
            FROM attacks
            WHERE id = ?
        ");
        $stmt_get_attack->bind_param("i", $attack_id);
        $stmt_get_attack->execute();
        $attack = $stmt_get_attack->get_result()->fetch_assoc();
        $stmt_get_attack->close();

        if (!$attack) {
            return ['success' => false, 'error' => 'Attack does not exist.'];
        }

        // Fetch attacking units
        $stmt_get_units = $this->conn->prepare("
            SELECT au.unit_type_id, au.count, ut.internal_name, ut.name
            FROM attack_units au
            JOIN unit_types ut ON au.unit_type_id = ut.id
            WHERE au.attack_id = ?
        ");
        $stmt_get_units->bind_param("i", $attack_id);
        $stmt_get_units->execute();
        $attack_units_result = $stmt_get_units->get_result();

        $attacker_spies = 0;
        $spy_unit_type_id = null;
        $other_units = [];

        while ($unit = $attack_units_result->fetch_assoc()) {
            if ($unit['internal_name'] === 'spy') {
                $attacker_spies += $unit['count'];
                $spy_unit_type_id = $unit['unit_type_id'];
            } else {
                // Safety: return any non-spy units untouched
                $other_units[$unit['unit_type_id']] = $unit['count'];
            }
        }
        $stmt_get_units->close();

        // Defender spies
        $defender_spies = 0;
        $defender_spy_row_id = null;
        $stmt_def_spies = $this->conn->prepare("
            SELECT vu.id, vu.count
            FROM village_units vu
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE vu.village_id = ? AND ut.internal_name = 'spy'
        ");
        $stmt_def_spies->bind_param("i", $attack['target_village_id']);
        $stmt_def_spies->execute();
        $def_res = $stmt_def_spies->get_result();
        if ($row = $def_res->fetch_assoc()) {
            $defender_spies = (int)$row['count'];
            $defender_spy_row_id = (int)$row['id'];
        }
        $stmt_def_spies->close();

        $attacker_spy_level = $this->getResearchLevelForVillage($attack['source_village_id'], 'spying');
        $defender_spy_level = $this->getResearchLevelForVillage($attack['target_village_id'], 'spying');
        $wall_level = $this->buildingManager->getBuildingLevel($attack['target_village_id'], 'wall');

        // Scores and outcome
        $attack_score = max(1, $attacker_spies) * (1 + 0.15 * $attacker_spy_level);
        $defense_score = max(0, $defender_spies * (1 + 0.15 * $defender_spy_level)) + ($wall_level * 0.6);
        $attack_score *= (random_int(90, 110) / 100);
        $defense_score *= (random_int(90, 110) / 100);

        $success = $attack_score >= max(1, $defense_score) && $attacker_spies > 0;

        // Casualties
        $attacker_losses = 0;
        if ($attacker_spies > 0) {
            if ($success) {
                $attacker_losses = min(
                    $attacker_spies,
                    (int)ceil(($defense_score / max(1, $attack_score)) * $attacker_spies * 0.6)
                );
            } else {
                $attacker_losses = $attacker_spies;
            }
        }
        $attacker_survivors = max(0, $attacker_spies - $attacker_losses);

        if ($success) {
            $defender_losses = min($defender_spies, max(0, (int)floor($attacker_spies / 2)));
        } else {
            $defender_losses = min($defender_spies, (int)ceil($attacker_spies * 0.3));
        }
        $defender_survivors = max(0, $defender_spies - $defender_losses);

        // Prepare intel if successful
        $intel = [];
        if ($success) {
            $stmt_res = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
            $stmt_res->bind_param("i", $attack['target_village_id']);
            $stmt_res->execute();
            $resources = $stmt_res->get_result()->fetch_assoc();
            $stmt_res->close();
            $intel['resources'] = $resources ?: ['wood' => 0, 'clay' => 0, 'iron' => 0];

            $intel_level = $attacker_spy_level + ($attacker_survivors >= 5 ? 2 : ($attacker_survivors >= 2 ? 1 : 0));

            if ($intel_level >= 2) {
                $intel['buildings'] = $this->getBuildingSnapshot($attack['target_village_id']);
            }
            if ($intel_level >= 3) {
                $intel['units'] = $this->getVillageUnitSnapshot($attack['target_village_id']);
            }
            if ($intel_level >= 4) {
                $intel['research'] = $this->getResearchSnapshot($attack['target_village_id']);
            }
        }

        // Units to return (include any non-spy units to avoid losing them)
        $units_to_return = $other_units;
        if ($spy_unit_type_id !== null && $attacker_survivors > 0) {
            $units_to_return[$spy_unit_type_id] = ($units_to_return[$spy_unit_type_id] ?? 0) + $attacker_survivors;
        }

        // Fetch user IDs
        $stmt_users = $this->conn->prepare("
            SELECT v1.user_id as attacker_user_id, v2.user_id as defender_user_id
            FROM villages v1, villages v2
            WHERE v1.id = ? AND v2.id = ?
        ");
        $stmt_users->bind_param("ii", $attack['source_village_id'], $attack['target_village_id']);
        $stmt_users->execute();
        $users = $stmt_users->get_result()->fetch_assoc();
        $stmt_users->close();

        $details = [
            'type' => 'spy',
            'success' => $success,
            'attacker_spies_sent' => $attacker_spies,
            'attacker_spies_lost' => $attacker_losses,
            'attacker_spies_returned' => $attacker_survivors,
            'defender_spies' => $defender_spies,
            'defender_spies_lost' => $defender_losses,
            'defender_spies_remaining' => $defender_survivors,
            'attacker_spy_level' => $attacker_spy_level,
            'defender_spy_level' => $defender_spy_level,
            'wall_level' => $wall_level,
            'scores' => [
                'attack' => round($attack_score, 2),
                'defense' => round($defense_score, 2)
            ],
            'intel' => $intel,
            'returned_units' => $units_to_return
        ];
        $report_data_json = json_encode($details);
        $attacker_won_int = $success ? 1 : 0;

        // Persist results
        $this->conn->begin_transaction();
        try {
            // Update defender spies
            if ($defender_spy_row_id !== null) {
                if ($defender_survivors > 0) {
                    $stmt_update = $this->conn->prepare("UPDATE village_units SET count = ? WHERE id = ?");
                    $stmt_update->bind_param("ii", $defender_survivors, $defender_spy_row_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    $stmt_delete = $this->conn->prepare("DELETE FROM village_units WHERE id = ?");
                    $stmt_delete->bind_param("i", $defender_spy_row_id);
                    $stmt_delete->execute();
                    $stmt_delete->close();
                }
            }

            // Return surviving units to the source village
            foreach ($units_to_return as $unit_type_id => $count) {
                if ($count <= 0) {
                    continue;
                }
                $stmt_check_existing = $this->conn->prepare("
                    SELECT id, count
                    FROM village_units
                    WHERE village_id = ? AND unit_type_id = ?
                ");
                $stmt_check_existing->bind_param("ii", $attack['source_village_id'], $unit_type_id);
                $stmt_check_existing->execute();
                $existing_result = $stmt_check_existing->get_result();
                if ($existing_result->num_rows > 0) {
                    $existing = $existing_result->fetch_assoc();
                    $new_count = $existing['count'] + $count;
                    $stmt_update = $this->conn->prepare("
                        UPDATE village_units
                        SET count = ?
                        WHERE id = ?
                    ");
                    $stmt_update->bind_param("ii", $new_count, $existing['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    $stmt_insert = $this->conn->prepare("
                        INSERT INTO village_units (village_id, unit_type_id, count)
                        VALUES (?, ?, ?)
                    ");
                    $stmt_insert->bind_param("iii", $attack['source_village_id'], $unit_type_id, $count);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                $stmt_check_existing->close();
            }

            // Mark attack as completed
            $stmt_complete_attack = $this->conn->prepare("
                UPDATE attacks
                SET is_completed = 1
                WHERE id = ?
            ");
            $stmt_complete_attack->bind_param("i", $attack_id);
            $stmt_complete_attack->execute();
            $stmt_complete_attack->close();

            // Add spy report
            $stmt_add_report = $this->conn->prepare("
                INSERT INTO battle_reports (
                    attack_id, source_village_id, target_village_id,
                    battle_time, attacker_user_id, defender_user_id,
                    attacker_won, report_data
                ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)
            ");
            $stmt_add_report->bind_param(
                "iiiiiis",
                $attack_id, $attack['source_village_id'], $attack['target_village_id'],
                $users['attacker_user_id'], $users['defender_user_id'],
                $attacker_won_int, $report_data_json
            );
            $stmt_add_report->execute();
            $stmt_add_report->close();

            // Generic reports: scout for attacker, defense for defender
            if ($this->reportManager) {
                $attackerTitle = "Scout report: " . $this->getVillageName($attack['target_village_id']);
                $defenderTitle = "Scouts near " . $this->getVillageName($attack['target_village_id']);
                $this->reportManager->addReport(
                    $users['attacker_user_id'],
                    'scout',
                    $attackerTitle,
                    $details,
                    $attack_id
                );
                $this->reportManager->addReport(
                    $users['defender_user_id'],
                    'defense',
                    $defenderTitle,
                    $details,
                    $attack_id
                );
            }

            // Persist intel snapshot & mark mission resolved
            $missionType = $this->getMissionTypeForAttack($attack_id);
            if ($this->intelManager) {
                try {
                    $this->intelManager->recordSpyReport([
                        'attack_id' => $attack_id,
                        'mission_type' => $missionType,
                        'source_village_id' => $attack['source_village_id'],
                        'target_village_id' => $attack['target_village_id'],
                        'source_user_id' => $users['attacker_user_id'] ?? 0,
                        'target_user_id' => $users['defender_user_id'] ?? null,
                        'details' => $details,
                    ]);
                } catch (Throwable $e) {
                    error_log('Intel snapshot failed for attack ' . $attack_id . ': ' . $e->getMessage());
                }
            }
            $this->markScoutMissionResolved($attack_id);

            $this->conn->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch village points; fallback to population when points are not yet calculated.
     */
    private function getVillagePointsWithFallback(int $villageId): int
    {
        $stmt = $this->conn->prepare("SELECT points, population FROM villages WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return 0;
        }

        $points = (int)$row['points'];
        if ($points > 0) {
            return $points;
        }

        return (int)$row['population'];
    }

    private function getWorldSettings(): array
    {
        if (!class_exists('WorldManager')) {
            require_once __DIR__ . '/WorldManager.php';
        }
        if (!class_exists('WorldManager')) {
            $this->worldSettings = [];
            return [];
        }
        if ($this->worldSettings !== null) {
            return $this->worldSettings;
        }
        $wm = new WorldManager($this->conn);
        $this->worldSettings = $wm->getSettings(CURRENT_WORLD_ID);
        return $this->worldSettings;
    }

    /**
     * Whether current server time is inside the configured night window.
     */
    private function isNightTimeWorldConfig(): bool
    {
        $settings = $this->getWorldSettings();
        if (empty($settings['night_bonus_enabled'])) {
            return false;
        }
        $hour = (int)date('H');
        $start = (int)($settings['night_start_hour'] ?? 22);
        $end = (int)($settings['night_end_hour'] ?? 6);
        if ($start > $end) {
            return $hour >= $start || $hour < $end;
        }
        return $hour >= $start && $hour < $end;
    }

    private function getEnvMultiplier(string $key): float
    {
        $settings = $this->getWorldSettings();
        if (strpos($key, 'weather_') === 0) {
            $enabled = $settings['weather_enabled'] ?? (defined('FEATURE_WEATHER_COMBAT_ENABLED') ? FEATURE_WEATHER_COMBAT_ENABLED : false);
            if (!$enabled) {
                return 1.0;
            }
        }
        return isset($settings[$key]) ? (float)$settings[$key] : 1.0;
    }

    private function isMinPayloadEnabled(): bool
    {
        $settings = $this->getWorldSettings();
        if (array_key_exists('min_attack_pop_enabled', $settings)) {
            return (bool)$settings['min_attack_pop_enabled'];
        }
        return defined('FEATURE_MIN_PAYLOAD_ENABLED') ? (bool)FEATURE_MIN_PAYLOAD_ENABLED : true;
    }

    private function getMinPayloadPop(): int
    {
        $settings = $this->getWorldSettings();
        if (isset($settings['min_attack_pop'])) {
            $val = (int)$settings['min_attack_pop'];
            if ($val > 0) {
                return $val;
            }
        }
        if (defined('MIN_ATTACK_POP')) {
            return max(1, (int)MIN_ATTACK_POP);
        }
        return self::MIN_ATTACK_POP;
    }

    /**
     * Morale punishes oversized attackers; no penalty if attacker is weaker.
     * Returns multiplier between MIN_MORALE and 1.0.
     */
    private function calculateMoraleFactor(int $attackerPoints, int $defenderPoints): float
    {
        if ($attackerPoints <= 0 || $defenderPoints <= 0) {
            return 1.0;
        }

        if ($attackerPoints <= $defenderPoints) {
            return 1.0;
        }

        $moraleType = defined('MORALE_TYPE') ? strtolower((string)MORALE_TYPE) : 'points';
        if ($moraleType === 'none') {
            return 1.0;
        }

        // Default points-based morale
        $ratio = $defenderPoints / max(1, $attackerPoints);
        $morale = sqrt($ratio);
        return max(self::MIN_MORALE, min(1.0, $morale));
    }

    /**
     * Validate beginner protection before an attack is sent.
     * Returns ['allowed'=>bool, 'error'=>?]
     */
    private function validateBeginnerProtection(int $attackerUserId, int $defenderUserId, int $attackerVillageId, int $defenderVillageId): array
    {
        // Barbarian villages (-1 owner) are never protected
        if ($defenderUserId <= 0 || $attackerUserId <= 0) {
            return ['allowed' => true];
        }

        $hours = defined('BEGINNER_PROTECTION_HOURS') ? (int)BEGINNER_PROTECTION_HOURS : 72;
        $protectionConfig = [
            'min_hours' => $hours,
            'max_hours' => $hours,
            'points_cap' => defined('NEWBIE_PROTECTION_POINTS_CAP') ? NEWBIE_PROTECTION_POINTS_CAP : 200,
        ];

        $stmt = $this->conn->prepare("
            SELECT id, created_at, points, is_protected
            FROM users
            WHERE id IN (?, ?)
        ");
        if ($stmt === false) {
            return ['allowed' => true];
        }
        $stmt->bind_param("ii", $attackerUserId, $defenderUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        $users = [];
        while ($row = $res->fetch_assoc()) {
            $users[(int)$row['id']] = $row;
        }
        $stmt->close();

        $now = new DateTimeImmutable('now');
        $attacker = $users[$attackerUserId] ?? null;
        $defender = $users[$defenderUserId] ?? null;

        if (!$attacker || !$defender) {
            return ['allowed' => true];
        }

        $attackerPoints = (int)($attacker['points'] ?? 0);
        $defenderPoints = (int)($defender['points'] ?? 0);

        $attackerProtection = $this->isUnderProtection($attacker, $now, $protectionConfig);
        $defenderProtection = $this->isUnderProtection($defender, $now, $protectionConfig);

        // Attacker under protection cannot attack outside protected range unless target is barbarian
        if ($attackerProtection && !$defenderProtection) {
            return [
                'allowed' => false,
                'error' => 'You are under beginner protection and cannot attack other players yet.',
                'code' => AjaxResponse::ERR_PROTECTED
            ];
        }

        // Defender under protection cannot be attacked by non-protected players
        if ($defenderProtection && !$attackerProtection) {
            return [
                'allowed' => false,
                'error' => 'Target player is under beginner protection.',
                'code' => AjaxResponse::ERR_PROTECTED
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Enforce per-attacker->defender daily attack cap for offensive missions.
     * Returns true if allowed, otherwise an error message string.
     */
    private function enforceAttackCap(int $attackerUserId, int $defenderUserId, ?int $defenderPoints = null, ?int $attackerTribeId = null)
    {
        if (self::MAX_ATTACKS_PER_TARGET_PER_DAY <= 0) {
            return true;
        }
        if ($attackerUserId <= 0 || $defenderUserId <= 0 || $attackerUserId === $defenderUserId) {
            return true; // ignore barb/self/support cases
        }

        $windowStart = time() - 86400;
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM attacks a
            JOIN villages sv ON a.source_village_id = sv.id
            JOIN villages tv ON a.target_village_id = tv.id
            WHERE sv.user_id = ?
              AND tv.user_id = ?
              AND a.is_canceled = 0
              AND a.attack_type IN ('attack','raid','spy','fake')
              AND UNIX_TIMESTAMP(a.start_time) >= ?
        ";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return true; // fail open to avoid blocking on DB error
        }
        $stmt->bind_param("iii", $attackerUserId, $defenderUserId, $windowStart);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $attackerCount = (int)($row['cnt'] ?? 0);
        if ($attackerCount >= self::MAX_ATTACKS_PER_TARGET_PER_DAY) {
            return sprintf('Attack limit reached: max %d attacks to this player per 24h.', self::MAX_ATTACKS_PER_TARGET_PER_DAY);
        }

        // Stricter caps for low-power defenders (anti-griefing)
        $defPts = $defenderPoints ?? $this->getUserPoints($defenderUserId);
        if ($defPts !== null && $defPts <= self::LOW_POWER_ATTACK_CAP_POINTS) {
            if ($attackerCount >= self::LOW_POWER_ATTACKS_PER_ATTACKER_PER_DAY) {
                return sprintf(
                    'Attack limit reached for low-power target: %d/%d attacks in the last 24h.',
                    $attackerCount,
                    self::LOW_POWER_ATTACKS_PER_ATTACKER_PER_DAY
                );
            }

            if ($attackerTribeId) {
                $sqlTribe = "
                    SELECT COUNT(*) AS cnt
                    FROM attacks a
                    JOIN villages sv ON a.source_village_id = sv.id
                    JOIN tribe_members tm ON sv.user_id = tm.user_id
                    JOIN villages tv ON a.target_village_id = tv.id
                    WHERE tm.tribe_id = ?
                      AND tv.user_id = ?
                      AND a.is_canceled = 0
                      AND a.attack_type IN ('attack','raid','spy','fake')
                      AND UNIX_TIMESTAMP(a.start_time) >= ?
                ";
                $stmtTribe = $this->conn->prepare($sqlTribe);
                if ($stmtTribe) {
                    $stmtTribe->bind_param("iii", $attackerTribeId, $defenderUserId, $windowStart);
                    $stmtTribe->execute();
                    $tribeRow = $stmtTribe->get_result()->fetch_assoc();
                    $stmtTribe->close();
                    $tribeCount = (int)($tribeRow['cnt'] ?? 0);
                    if ($tribeCount >= self::LOW_POWER_ATTACKS_PER_TRIBE_PER_DAY) {
                        return sprintf(
                            'Tribe attack cap reached for low-power target: %d/%d tribe attacks in the last 24h.',
                            $tribeCount,
                            self::LOW_POWER_ATTACKS_PER_TRIBE_PER_DAY
                        );
                    }
                }
            }
        }
        return true;
    }

    /**
     * Scout-specific burst cap to reduce spammy probing.
     */
    private function enforceScoutRateLimit(int $userId)
    {
        if (self::MAX_SCOUT_COMMANDS_PER_WINDOW <= 0 || $userId <= 0) {
            return true;
        }

        $windowStart = time() - self::COMMAND_RATE_WINDOW_SECONDS;
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM attacks a
            JOIN villages v ON a.source_village_id = v.id
            WHERE v.user_id = ?
              AND a.is_canceled = 0
              AND a.attack_type = 'spy'
              AND UNIX_TIMESTAMP(a.start_time) >= ?
        ";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return true;
        }
        $stmt->bind_param("ii", $userId, $windowStart);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $count = (int)($row['cnt'] ?? 0);
        if ($count >= self::MAX_SCOUT_COMMANDS_PER_WINDOW) {
            return sprintf('Scout rate limit reached: max %d scout commands per %d seconds.', self::MAX_SCOUT_COMMANDS_PER_WINDOW, self::COMMAND_RATE_WINDOW_SECONDS);
        }
        return true;
    }

    /**
     * Rate limit outgoing offensive commands per attacker (burst + sustained).
     */
    private function enforceCommandRateLimit(int $attackerUserId, string $attackType, ?int $defenderUserId = null)
    {
        if ($attackerUserId <= 0) {
            return true;
        }
        if (!in_array($attackType, self::OFFENSIVE_ATTACK_TYPES, true)) {
            return true;
        }
        $now = time();
        $minuteWindow = $now - 60;
        $hourWindow = $now - 3600;

        $sql = "
            SELECT
                SUM(CASE WHEN UNIX_TIMESTAMP(a.start_time) >= ? THEN 1 ELSE 0 END) AS last_minute,
                SUM(CASE WHEN UNIX_TIMESTAMP(a.start_time) >= ? THEN 1 ELSE 0 END) AS last_hour
            FROM attacks a
            JOIN villages sv ON a.source_village_id = sv.id
            WHERE sv.user_id = ?
              AND a.is_canceled = 0
              AND a.attack_type IN ('attack','raid','spy','fake')
        ";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return true; // fail open to avoid false positives on DB errors
        }
        $stmt->bind_param("iii", $minuteWindow, $hourWindow, $attackerUserId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $lastMinute = (int)($row['last_minute'] ?? 0);
        $lastHour = (int)($row['last_hour'] ?? 0);

        $isScout = ($attackType === 'spy');
        $penaltyMultiplier = $this->getAbusePenaltyMultiplier($attackerUserId);
        $minuteCap = max(1, (int)ceil(($isScout ? self::MAX_SCOUTS_PER_MINUTE : self::MAX_COMMANDS_PER_MINUTE) * $penaltyMultiplier));
        $hourCap = max(1, (int)ceil(self::MAX_COMMANDS_PER_HOUR * $penaltyMultiplier));

        if ($lastMinute >= $minuteCap) {
            $this->logAbuseFlag($attackerUserId, 'RATE_CAP_MINUTE', ['count' => $lastMinute, 'cap' => $minuteCap, 'attack_type' => $attackType]);
            return sprintf('RATE_CAP: Too many %s commands in the last minute (%d/%d).', $isScout ? 'scout' : 'commands', $lastMinute, $minuteCap);
        }
        if ($lastHour >= $hourCap) {
            $this->logAbuseFlag($attackerUserId, 'RATE_CAP_HOUR', ['count' => $lastHour, 'cap' => $hourCap]);
            return sprintf('RATE_CAP: Too many commands in the last hour (%d/%d).', $lastHour, $hourCap);
        }

        // Per target cap for scouts in short window
        if ($isScout && $defenderUserId && self::MAX_SCOUTS_PER_TARGET_PER_WINDOW > 0) {
            $targetWindowStart = $now - self::SCOUT_TARGET_WINDOW_SECONDS;
            $sqlTarget = "
                SELECT COUNT(*) AS cnt
                FROM attacks a
                JOIN villages sv ON a.source_village_id = sv.id
                JOIN villages tv ON a.target_village_id = tv.id
                WHERE sv.user_id = ?
                  AND tv.user_id = ?
                  AND a.is_canceled = 0
                  AND a.attack_type = 'spy'
                  AND UNIX_TIMESTAMP(a.start_time) >= ?
            ";
            $stmtTarget = $this->conn->prepare($sqlTarget);
            if ($stmtTarget) {
                $stmtTarget->bind_param("iii", $attackerUserId, $defenderUserId, $targetWindowStart);
                $stmtTarget->execute();
                $tRow = $stmtTarget->get_result()->fetch_assoc();
                $stmtTarget->close();
                $targetCnt = (int)($tRow['cnt'] ?? 0);
                if ($targetCnt >= self::MAX_SCOUTS_PER_TARGET_PER_WINDOW) {
                    $retryIn = max(1, self::SCOUT_TARGET_WINDOW_SECONDS - ($now - $targetWindowStart));
                    $this->logAbuseFlag($attackerUserId, 'SCOUT_TARGET_CAP', [
                        'target_user_id' => $defenderUserId,
                        'count' => $targetCnt
                    ]);
                    return sprintf(
                        'SCOUT_RATE_LIMITED: Too many scouts to this target in the last %d minutes (%d/%d). Retry in ~%d seconds.',
                        (int)(self::SCOUT_TARGET_WINDOW_SECONDS / 60),
                        $targetCnt,
                        self::MAX_SCOUTS_PER_TARGET_PER_WINDOW,
                        $retryIn
                    );
                }
            }
        }
        return true;
    }

    /**
     * Track soft abuse flags (throttles, caps) and derive penalty multipliers.
     */
    private function logAbuseFlag(int $userId, string $code, array $meta = []): void
    {
        if ($userId <= 0) {
            return;
        }
        $this->ensureAbuseFlagTable();
        $metaJson = json_encode($meta);
        $stmt = $this->conn->prepare("
            INSERT INTO attack_abuse_flags (user_id, code, meta, created_at)
            VALUES (?, ?, ?, strftime('%s','now'))
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("iss", $userId, $code, $metaJson);
        $stmt->execute();
        $stmt->close();
    }

    private function logConquestAttempt(string $event, int $attackerUserId, ?int $defenderUserId, int $targetVillageId, bool $allowed, array $meta = []): void
    {
        $entry = [
            'ts' => date('c'),
            'event' => $event,
            'attacker_user_id' => $attackerUserId,
            'defender_user_id' => $defenderUserId,
            'target_village_id' => $targetVillageId,
            'world_id' => defined('CURRENT_WORLD_ID') ? CURRENT_WORLD_ID : null,
            'allowed' => $allowed,
            'meta' => $meta
        ];
        $line = json_encode($entry);
        if ($line !== false) {
            @file_put_contents($this->conquestLogFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    private function getAbusePenaltyMultiplier(int $userId): float
    {
        if ($userId <= 0) {
            return 1.0;
        }
        $thresholdTs = time() - self::SOFT_FLAG_WINDOW_SEC;
        $this->ensureAbuseFlagTable();
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM attack_abuse_flags
            WHERE user_id = ?
              AND created_at >= ?
        ");
        if (!$stmt) {
            return 1.0;
        }
        $stmt->bind_param("ii", $userId, $thresholdTs);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $cnt = (int)($row['cnt'] ?? 0);
        if ($cnt >= self::SOFT_FLAG_THRESHOLD) {
            return self::SOFT_FLAG_PENALTY_MULTIPLIER;
        }
        return 1.0;
    }

    private function ensureAbuseFlagTable(): void
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS attack_abuse_flags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                code TEXT NOT NULL,
                meta TEXT DEFAULT NULL,
                created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            )
        ");
        $this->conn->query("CREATE INDEX IF NOT EXISTS idx_abuse_flags_user_time ON attack_abuse_flags(user_id, created_at)");
    }

    private function hasInternalUnit(array $units, string $internal): bool
    {
        $needle = strtolower($internal);
        foreach ($units as $unit) {
            if (isset($unit['internal_name']) && strtolower($unit['internal_name']) === $needle && ($unit['count'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }

    private function getVillageCountForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM villages WHERE user_id = ?");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Calculate diminishing returns multiplier for repeated plunder of the same target within a window.
     */
    private function getPlunderDiminishingReturnsMultiplier(int $attackerUserId, int $targetVillageId): float
    {
        if ($attackerUserId <= 0 || $targetVillageId <= 0) {
            return 1.0;
        }
        $windowStart = time() - self::PLUNDER_DR_WINDOW_SEC;
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM battle_reports br
            JOIN attacks a ON br.attack_id = a.id
            WHERE br.attacker_user_id = ?
              AND br.target_village_id = ?
              AND br.attacker_won = 1
              AND a.attack_type IN ('attack','raid')
              AND UNIX_TIMESTAMP(br.battle_time) >= ?
        ");
        if (!$stmt) {
            return 1.0;
        }
        $stmt->bind_param("iii", $attackerUserId, $targetVillageId, $windowStart);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $count = (int)($row['cnt'] ?? 0);
        // First raid in window gets index 0 (1.0), then step down.
        $index = min($count, count(self::PLUNDER_DR_STEPS) - 1);
        return self::PLUNDER_DR_STEPS[$index] ?? 1.0;
    }

    private function enforceSitterHourlyLimit(int $ownerId)
    {
        if ($ownerId <= 0 || self::SITTER_MAX_OUTGOING_PER_HOUR <= 0) {
            return true;
        }
        $hourAgo = time() - 3600;
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM attacks a
            JOIN villages v ON a.source_village_id = v.id
            WHERE v.user_id = ?
              AND a.is_canceled = 0
              AND UNIX_TIMESTAMP(a.start_time) >= ?
              AND a.attack_type IN ('attack','raid','spy','fake','support')
        ";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return true;
        }
        $stmt->bind_param("ii", $ownerId, $hourAgo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $count = (int)($row['cnt'] ?? 0);
        if ($count >= self::SITTER_MAX_OUTGOING_PER_HOUR) {
            return sprintf('Sitter limit reached: max %d outgoing commands per hour while sitting.', self::SITTER_MAX_OUTGOING_PER_HOUR);
        }
        return true;
    }

    private function isUnderProtection(array $userRow, DateTimeImmutable $now, array $config): bool
    {
        if (isset($userRow['is_protected']) && (int)$userRow['is_protected'] === 0) {
            return false;
        }

        $pointsCap = $config['points_cap'];
        if (($userRow['points'] ?? 0) > $pointsCap) {
            return false;
        }

        $createdAt = isset($userRow['created_at']) ? new DateTimeImmutable($userRow['created_at']) : null;
        if (!$createdAt) {
            return false;
        }

        $hoursSinceCreate = (int)floor(($now->getTimestamp() - $createdAt->getTimestamp()) / 3600);
        return $hoursSinceCreate < ($config['max_hours'] ?? 72);
    }

    /**
     * Defensive faith bonus from churches.
     */
    private function calculateFaithDefenseBonus(int $villageId): float
    {
        $bonus = 1.0;

        $churchLevel = $this->buildingManager->getBuildingLevel($villageId, 'church');
        if ($churchLevel > 0) {
            $bonus += $churchLevel * self::FAITH_DEFENSE_PER_LEVEL;
        }

        $firstChurchLevel = $this->buildingManager->getBuildingLevel($villageId, 'first_church');
        if ($firstChurchLevel > 0) {
            $bonus += self::FIRST_CHURCH_DEFENSE_BONUS;
        }

        return max(1.0, $bonus);
    }

    /**
     * Return a random factor centered at 1 with a configurable spread.
     */
    private function rollRandomFactor(float $spread = self::RANDOM_VARIANCE): float
    {
        $spread = max(0, $spread);
        $min = 1 - $spread;
        $max = 1 + $spread;
        return $this->randomFloat($min, $max);
    }

    /**
     * Inclusive-ish random float helper.
     */
    private function randomFloat(float $min, float $max): float
    {
        $min = min($min, $max);
        $max = max($min, $max);
        return $min + (random_int(0, 1000000) / 1000000) * ($max - $min);
    }

    /**
     * Hidden resources protected by the hiding place per resource type.
     */
    private function getHiddenResourcesPerType(int $villageId): int
    {
        $level = $this->buildingManager->getBuildingLevel($villageId, 'hiding_place');
        if ($level <= 0) {
            return 0;
        }

        return (int)floor(150 * pow(1.233, $level));
    }

    /**
     * Attack power: simple sum of attack stat * count (smithy bonuses can hook in later).
     */
    private function calculateAttackPower(array $units, ?int $villageId = null): float
    {
        $power = 0;
        foreach ($units as $unit) {
            $bonus = $this->getSmithyBonus($villageId, $unit['internal_name'] ?? '', true);
            $power += ($unit['attack'] ?? 0) * ($unit['count'] ?? 0) * $bonus;
        }
        return max(0, $power);
    }

    /**
     * Attack profile split by unit class.
     */
    private function calculateAttackProfile(array $units, ?int $villageId): array
    {
        $profile = [
            'by_class' => [
                'infantry' => 0,
                'cavalry' => 0,
                'archer' => 0,
                'siege' => 0
            ],
            'total' => 0
        ];

        foreach ($units as $unit) {
            $class = $this->getUnitClass($unit['internal_name'] ?? '');
            $bonus = $this->getSmithyBonus($villageId, $unit['internal_name'] ?? '', true);
            $attackValue = ($unit['attack'] ?? 0) * ($unit['count'] ?? 0) * $bonus;
            $profile['by_class'][$class] += $attackValue;
            $profile['total'] += $attackValue;
        }

        return $profile;
    }

    /**
     * Defense profile for a single unit (per class, fallback to general defense).
     */
    private function getDefenseProfile(array $unit, ?int $villageId): array
    {
        $base = (float)($unit['defense'] ?? 0);
        $defCav = (float)($unit['defense_cavalry'] ?? $base);
        $defArch = (float)($unit['defense_archer'] ?? $base);
        $bonus = $this->getSmithyBonus($villageId, $unit['internal_name'] ?? '', false);

        return [
            'infantry' => $base * $bonus,
            'cavalry' => $defCav * $bonus,
            'archer' => $defArch * $bonus,
            'siege' => $base * $bonus
        ];
    }

    /**
     * Map internal unit name to combat class.
     */
    private function getUnitClass(string $internalName): string
    {
        $internalName = strtolower($internalName);
        if (in_array($internalName, ['light', 'heavy', 'marcher', 'spy'])) {
            return 'cavalry';
        }
        if (in_array($internalName, ['archer', 'marcher'])) {
            return 'archer';
        }
        if (in_array($internalName, ['ram', 'catapult'])) {
            return 'siege';
        }
        return 'infantry';
    }

    private function getSmithyBonus(?int $villageId, string $unitInternal, bool $isAttack): float
    {
        // Placeholder: extend with smithy research/upgrade lookup. For now, 1.0.
        return 1.0;
    }

    private function isBeginnerProtected(int $points): bool
    {
        return $points > 0 && $points < self::BEGINNER_PROTECTION_POINTS;
    }

    private function getUserPoints(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $stmt = $this->conn->prepare("SELECT points FROM users WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($res['points']) ? (int)$res['points'] : 0;
    }

    /**
     * Fetch protection state for a user (beginner shield).
     */
    private function getUserProtectionState(int $userId): array
    {
        if ($userId <= 0) {
            return ['protected' => false];
        }
        $stmt = $this->conn->prepare("SELECT points, is_protected, created_at FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return ['protected' => false];
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return ['protected' => false];
        }
        $points = (int)($row['points'] ?? 0);
        $explicit = isset($row['is_protected']) ? (int)$row['is_protected'] === 1 : false;
        $protected = $explicit || $this->isBeginnerProtected($points);
        return [
            'protected' => $protected,
            'created_at' => $row['created_at'] ?? null,
            'points' => $points
        ];
    }

    /**
     * Calculate account age in hours using created_at, if available.
     */
    private function getAccountAgeHours(array $protectionState): ?float
    {
        if (empty($protectionState['created_at'])) {
            return null;
        }
        $created = strtotime($protectionState['created_at']);
        if ($created <= 0) {
            return null;
        }
        $seconds = max(0, time() - $created);
        return $seconds / 3600;
    }

    private function getSitterContext(): array
    {
        $isSitter = isset($_SESSION['sitter_original_user_id'], $_SESSION['sitter_owner_id']) && (int)($_SESSION['sitter_owner_id']) === (int)($_SESSION['user_id']);
        return [
            'is_sitter' => $isSitter,
            'sitter_id' => $isSitter ? (int)$_SESSION['sitter_original_user_id'] : null,
            'owner_id' => $isSitter ? (int)$_SESSION['user_id'] : null,
        ];
    }


    /**
     * Count low-payload (pop < 50, no siege) commands to a target in the recent window.
     */
    private function countRecentLowPayloadCommands(int $targetVillageId): int
    {
        if ($targetVillageId <= 0) {
            return 0;
        }
        $thresholdTs = time() - self::FAKE_THROTTLE_WINDOW_SEC;
        // strftime works on SQLite; UNIX_TIMESTAMP works on MySQL. Use both.
        $sql = "
            SELECT COUNT(*) AS cnt FROM (
                SELECT 
                    a.id,
                    SUM(au.count * ut.population) AS pop,
                    SUM(CASE WHEN ut.internal_name IN ('ram','catapult','trebuchet') THEN au.count ELSE 0 END) AS siege_count
                FROM attacks a
                JOIN attack_units au ON au.attack_id = a.id
                JOIN unit_types ut ON ut.id = au.unit_type_id
                WHERE a.target_village_id = ?
                  AND a.is_canceled = 0
                  AND a.is_completed = 0
                  AND COALESCE(strftime('%s', a.start_time), UNIX_TIMESTAMP(a.start_time)) >= ?
                  AND a.attack_type IN ('attack','raid','fake','spy')
                GROUP BY a.id
                HAVING pop < 50 AND siege_count = 0
            ) AS t
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("ii", $targetVillageId, $thresholdTs);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return isset($row['cnt']) ? (int)$row['cnt'] : 0;
    }

    /**
     * Defense power with wall bonus and luck already baked in.
     */
    private function calculateDefensePower(array $units, float $wallBonus, float $luck, array $attackProfile, ?int $villageId): float
    {
        $defenseProfile = [
            'infantry' => 0,
            'cavalry' => 0,
            'archer' => 0,
            'siege' => 0
        ];

        foreach ($units as $unit) {
            $profile = $this->getDefenseProfile($unit, $villageId);
            $count = $unit['count'] ?? 0;
            $defenseProfile['infantry'] += $profile['infantry'] * $count;
            $defenseProfile['cavalry'] += $profile['cavalry'] * $count;
            $defenseProfile['archer'] += $profile['archer'] * $count;
            $defenseProfile['siege'] += $profile['siege'] * $count;
        }

        $defenseProfile = array_map(fn($v) => $v * $wallBonus * $luck, $defenseProfile);

        $effectiveDefense = 0;
        $attackTotal = max(1, $attackProfile['total']);
        foreach (['infantry', 'cavalry', 'archer', 'siege'] as $class) {
            $attackShare = ($attackProfile['by_class'][$class] ?? 0) / $attackTotal;
            $effectiveDefense += $attackShare * ($defenseProfile[$class] ?? 0);
        }

        return max(1, $effectiveDefense);
    }

    /**
     * Optional overstack penalty: reduces defense when population exceeds threshold.
     */
    private function getOverstackMultiplier(array $defendingUnits): array
    {
        $settings = $this->getWorldSettings();
        $enabled = isset($settings['overstack_enabled'])
            ? (bool)$settings['overstack_enabled']
            : (defined('OVERSTACK_ENABLED') ? (bool)OVERSTACK_ENABLED : self::OVERSTACK_ENABLED_DEFAULT);

        $threshold = isset($settings['overstack_pop_threshold'])
            ? max(0, (int)$settings['overstack_pop_threshold'])
            : (defined('OVERSTACK_POP_THRESHOLD') ? max(0, (int)OVERSTACK_POP_THRESHOLD) : self::OVERSTACK_THRESHOLD_DEFAULT);

        $penaltyRate = isset($settings['overstack_penalty_rate'])
            ? (float)$settings['overstack_penalty_rate']
            : (defined('OVERSTACK_PENALTY_RATE') ? (float)OVERSTACK_PENALTY_RATE : self::OVERSTACK_PENALTY_RATE_DEFAULT);

        $minMultiplier = isset($settings['overstack_min_multiplier'])
            ? (float)$settings['overstack_min_multiplier']
            : (defined('OVERSTACK_MIN_MULTIPLIER') ? (float)OVERSTACK_MIN_MULTIPLIER : self::OVERSTACK_MIN_MULTIPLIER_DEFAULT);

        if (!$enabled || $threshold <= 0 || $penaltyRate <= 0) {
            return [
                'enabled' => false,
                'multiplier' => 1.0,
                'population' => 0,
                'threshold' => $threshold,
                'penalty_rate' => $penaltyRate,
                'min_multiplier' => $minMultiplier
            ];
        }

        $totalPop = 0;
        foreach ($defendingUnits as $unit) {
            $pop = (int)($unit['population'] ?? 0);
            $cnt = (int)($unit['count'] ?? 0);
            $totalPop += $pop * $cnt;
        }

        if ($totalPop <= $threshold) {
            return [
                'enabled' => true,
                'multiplier' => 1.0,
                'population' => $totalPop,
                'threshold' => $threshold,
                'penalty_rate' => $penaltyRate,
                'min_multiplier' => $minMultiplier
            ];
        }

        $over = $totalPop - $threshold;
        $steps = $over / $threshold; // how many thresholds above cap
        $multiplier = 1 - ($penaltyRate * $steps);
        $multiplier = max($minMultiplier, $multiplier);

        return [
            'enabled' => true,
            'multiplier' => $multiplier,
            'population' => $totalPop,
            'threshold' => $threshold,
            'penalty_rate' => $penaltyRate,
            'min_multiplier' => $minMultiplier,
            'over_pop' => $over
        ];
    }

    /**
     * Calculate casualties for both sides using exponential ratios.
     */
    private function calculateCasualties(array $attacking_units, array $defending_units, float $attackPower, float $defensePower, bool $isRaid): array
    {
        $attackPower = max(1, $attackPower);
        $defensePower = max(1, $defensePower);
        $attackerWins = $attackPower >= $defensePower;
        $ratio = $attackPower / $defensePower;

        $minLoss = 0.30; // floors for realism
        $lossScale = 0.9;

        if ($attackerWins) {
            $defenderLossFactor = min(1.0, max($minLoss, $lossScale * pow($ratio, 0.9)));
            $attackerLossFactor = min(1.0, max($minLoss, $lossScale * pow(1 / $ratio, 0.65)));
        } else {
            $attackerLossFactor = min(1.0, max($minLoss, $lossScale * pow(1 / max(0.0001, $ratio), 0.9)));
            $defenderLossFactor = min(1.0, max($minLoss, $lossScale * pow($ratio, 0.65)));
        }

        if ($isRaid) {
            $attackerLossFactor *= self::RAID_CASUALTY_FACTOR;
            $defenderLossFactor *= self::RAID_CASUALTY_FACTOR;
        }

        $attacker_losses = [];
        $remaining_attacking_units = [];
        foreach ($attacking_units as $unit_type_id => $unit) {
            $loss_count = (int)round($unit['count'] * $attackerLossFactor);
            $remaining = max(0, $unit['count'] - $loss_count);
            $attacker_losses[$unit_type_id] = [
                'unit_name' => $unit['name'],
                'initial_count' => $unit['count'],
                'lost_count' => $loss_count,
                'remaining_count' => $remaining
            ];
            if ($remaining > 0) {
                $remaining_attacking_units[$unit_type_id] = $remaining;
            }
        }

        $defender_losses = [];
        $remaining_defending_units = [];
        foreach ($defending_units as $unit_type_id => $unit) {
            $loss_count = (int)round($unit['count'] * $defenderLossFactor);
            $remaining = max(0, $unit['count'] - $loss_count);
            $defender_losses[$unit_type_id] = [
                'unit_name' => $unit['name'],
                'initial_count' => $unit['count'],
                'lost_count' => $loss_count,
                'remaining_count' => $remaining
            ];
            if ($remaining > 0) {
                $remaining_defending_units[$unit_type_id] = $remaining;
            }
        }

        return [
            'attacker_win' => $attackerWins,
            'attacker_losses' => $attacker_losses,
            'defender_losses' => $defender_losses,
            'remaining_attacking_units' => $remaining_attacking_units,
            'remaining_defending_units' => $remaining_defending_units
        ];
    }

    /**
     * Calculates how many troops the winning side loses.
     * Scales with power ratio and is softened for raids.
     */
    private function calculateWinnerLossRatio(float $winnerPower, float $loserPower, bool $isRaid): float
    {
        if ($winnerPower <= 0) {
            return 1.0;
        }

        $ratio = $loserPower / max(1, $winnerPower);
        $loss_ratio = max(self::WINNER_MINIMUM_LOSS, pow($ratio, 0.6));

        if ($isRaid) {
            $loss_ratio *= self::RAID_CASUALTY_FACTOR;
        }

        return min(1.0, $loss_ratio);
    }

    /**
     * Fetch all research levels for a village keyed by internal_name.
     */
    private function getResearchLevelsMap(int $villageId): array
    {
        $levels = [];
        $stmt = $this->conn->prepare("
            SELECT rt.internal_name, vr.level
            FROM village_research vr
            JOIN research_types rt ON vr.research_type_id = rt.id
            WHERE vr.village_id = ?
        ");
        if ($stmt === false) {
            return $levels;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $levels[$row['internal_name']] = (int)$row['level'];
        }
        $stmt->close();
        return $levels;
    }

    /**
     * Apply smithy research bonuses to a unit's attack/defense values.
     */
    private function applyTechBonusesToUnit(array $unit, string $category, array $researchLevels, bool $isDefense): array
    {
        $attack = (float)($unit['attack'] ?? 0);
        $defense = (float)($unit['defense'] ?? 0);

        if ($category === 'infantry') {
            $attack *= 1 + (($researchLevels['improved_axe'] ?? 0) * self::RESEARCH_BONUS_PER_LEVEL);
            if ($isDefense) {
                $defense *= 1 + (($researchLevels['improved_armor'] ?? 0) * self::RESEARCH_BONUS_PER_LEVEL);
            }
        } elseif ($category === 'cavalry') {
            $attack *= 1 + (($researchLevels['improved_sword'] ?? 0) * self::RESEARCH_BONUS_PER_LEVEL);
            if ($isDefense) {
                $defense *= 1 + (($researchLevels['horseshoe'] ?? 0) * self::RESEARCH_BONUS_PER_LEVEL);
            }
        } elseif ($category === 'siege') {
            $attack *= 1 + (($researchLevels['improved_catapult'] ?? 0) * self::RESEARCH_BONUS_PER_LEVEL);
        }

        return [
            'attack' => (float)$attack,
            'defense' => (float)$defense
        ];
    }

    /**
     * Map internal/building type to combat category.
     */
    private function getUnitCategory(string $internalName, string $buildingType): string
    {
        $name = strtolower($internalName);
        if (in_array($name, ['archer', 'marcher'], true)) {
            return 'archer';
        }
        if (in_array($name, ['light', 'heavy', 'spy', 'scout', 'paladin', 'knight'], true) || $buildingType === 'stable') {
            return 'cavalry';
        }
        if (in_array($name, ['ram', 'catapult'], true) || $buildingType === 'garage') {
            return 'siege';
        }
        return 'infantry';
    }

    /**
     * Resolve one combat phase and mutate unit counts.
     */
    private function resolveCombatPhase(
        string $phase,
        array &$attackingUnits,
        array &$defendingUnits,
        float $morale,
        float $defenseMultiplier,
        float $attackLuck,
        bool $isRaid
    ): array {
        $attackersInPhase = array_filter($attackingUnits, function ($unit) use ($phase) {
            $category = $unit['category'] ?? 'infantry';
            return $category === $phase || ($phase === 'infantry' && $category === 'siege');
        });

        if (empty($attackersInPhase)) {
            return [
                'phase' => $phase,
                'attack_power' => 0,
                'defense_power' => 0,
                'attacker_loss_factor' => 0,
                'defender_loss_factor' => 0,
                'attacker_losses' => [],
                'defender_losses' => []
            ];
        }

        $attackPowerBase = 0.0;
        foreach ($attackersInPhase as $unit) {
            $attackPowerBase += ($unit['attack'] ?? 0) * ($unit['count'] ?? 0);
        }
        $defensePowerBase = 0.0;
        $hasMantlets = $this->hasInternalUnit($attackingUnits, 'mantlet');
        $hasSiegeInPhase = array_reduce($attackersInPhase, function ($carry, $unit) {
            return $carry || (($unit['category'] ?? '') === 'siege');
        }, false);
        $reduceRanged = $hasMantlets && $hasSiegeInPhase;
        foreach ($defendingUnits as $unit) {
            $unitDefense = ($unit['defense'] ?? 0) * ($unit['count'] ?? 0);
            if ($reduceRanged && ($unit['category'] ?? '') === 'archer') {
                $unitDefense *= (1.0 - self::MANTLET_RANGED_REDUCTION);
            }
            $defensePowerBase += $unitDefense;
        }

        $attackPower = $attackPowerBase * $morale * $attackLuck;
        $defensePower = $defensePowerBase * $defenseMultiplier;

        if ($attackPower <= 0 || $defensePower <= 0) {
            return [
                'phase' => $phase,
                'attack_power' => $attackPower,
                'defense_power' => $defensePower,
                'attacker_loss_factor' => 0,
                'defender_loss_factor' => 1,
                'attacker_losses' => [],
                'defender_losses' => []
            ];
        }

        $ratio = $attackPower / max(1e-6, $defensePower);
        $baseLossModifier = 0.7 * ($isRaid ? self::RAID_CASUALTY_FACTOR : 1.0);

        if ($attackPower >= $defensePower) {
            $defenderLossFactor = min(1.0, $baseLossModifier * pow($ratio, 0.85));
            $attackerLossFactor = min(1.0, max(self::WINNER_MINIMUM_LOSS, $baseLossModifier * pow(1 / $ratio, 0.55)));
        } else {
            $attackerLossFactor = min(1.0, $baseLossModifier * pow(1 / max(0.0001, $ratio), 0.85));
            $defenderLossFactor = min(1.0, max(self::WINNER_MINIMUM_LOSS, $baseLossModifier * pow($ratio, 0.55)));
        }

        $attackerLosses = [];
        foreach ($attackingUnits as $id => &$unit) {
            $category = $unit['category'] ?? 'infantry';
            if ($category !== $phase && !($phase === 'infantry' && $category === 'siege')) {
                continue;
            }
            $lossCount = (int)round(($unit['count'] ?? 0) * $attackerLossFactor);
            $unit['count'] = max(0, ($unit['count'] ?? 0) - $lossCount);
            $attackerLosses[$id] = $lossCount;
        }
        unset($unit);

        $defenderLosses = [];
        foreach ($defendingUnits as $id => &$unit) {
            $lossCount = (int)round(($unit['count'] ?? 0) * $defenderLossFactor);
            $unit['count'] = max(0, ($unit['count'] ?? 0) - $lossCount);
            $defenderLosses[$id] = $lossCount;
        }
        unset($unit);

        return [
            'phase' => $phase,
            'attack_power' => $attackPower,
            'defense_power' => $defensePower,
            'attacker_loss_factor' => $attackerLossFactor,
            'defender_loss_factor' => $defenderLossFactor,
            'attacker_losses' => $attackerLosses,
            'defender_losses' => $defenderLosses
        ];
    }

    /**
     * Sum total power (attack or defense) for a side.
     */
    private function sumPower(array $units, string $key): float
    {
        $total = 0.0;
        foreach ($units as $unit) {
            $total += ($unit[$key] ?? 0) * ($unit['count'] ?? 0);
        }
        return $total;
    }

    /**
     * Detect surviving nobles in the attacking army.
     */
    private function hasNobleUnit(array $attackingUnits): bool
    {
        $nobleNames = ['noble', 'nobleman', 'nobleman_unit'];
        foreach ($attackingUnits as $unit) {
            if (($unit['count'] ?? 0) > 0 && in_array(strtolower($unit['internal_name'] ?? ''), $nobleNames, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count surviving nobles in the attacking army.
     */
    private function countNobleUnits(array $attackingUnits): int
    {
        $nobleNames = ['noble', 'nobleman', 'nobleman_unit'];
        $total = 0;
        foreach ($attackingUnits as $unit) {
            if (in_array(strtolower($unit['internal_name'] ?? ''), $nobleNames, true)) {
                $total += (int)($unit['count'] ?? 0);
            }
        }
        return $total;
    }

    /**
     * Sitter permission switches per world (default allow).
     */
    private function getSitterPermissions(int $worldId): array
    {
        if (!class_exists('WorldManager')) {
            require_once __DIR__ . '/WorldManager.php';
        }
        $wm = class_exists('WorldManager') ? new WorldManager($this->conn) : null;
        return [
            'attack' => $wm ? $wm->areSitterAttacksEnabled($worldId) : true,
            'support' => $wm ? $wm->areSitterSupportsEnabled($worldId) : true
        ];
    }

    /**
     * Append sitter action/audit log.
     */
    private function logSitterAction(array $payload): void
    {
        $payload['ts'] = $payload['ts'] ?? time();
        $logPath = __DIR__ . '/../../logs/sitter_actions.log';
        @file_put_contents($logPath, json_encode($payload) . PHP_EOL, FILE_APPEND);
    }

    /**
     * Detect paladin presence in an army.
     */
    private function hasPaladin(array $units): bool
    {
        foreach ($units as $unit) {
            if (($unit['count'] ?? 0) > 0 && in_array(strtolower($unit['internal_name'] ?? ''), ['paladin', 'knight'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Apply paladin weapon bonuses to a side if applicable.
     */
    private function applyPaladinWeaponBonuses(array &$units, string $side): void
    {
        if (!$this->hasPaladin($units)) {
            return;
        }

        $weapon = $this->getPaladinWeapon();
        if ($weapon === 'bonfire') {
            $mult = defined('PALADIN_WEAPON_BONFIRE_MULTIPLIER') ? PALADIN_WEAPON_BONFIRE_MULTIPLIER : 1.5;
            foreach ($units as &$unit) {
                if (($unit['internal_name'] ?? '') === 'catapult') {
                    $unit['attack'] *= $mult;
                    $unit['defense'] *= $mult;
                }
            }
        }
    }

    /**
     * Determine effective loyalty floor for an attack (e.g., Vasco's Scepter).
     */
    private function getEffectiveLoyaltyFloor(array $attackingUnits, ?int $targetVillageId = null): int
    {
        $floor = self::LOYALTY_MIN;
        if ($this->hasPaladin($attackingUnits) && $this->getPaladinWeapon() === 'vascos_scepter') {
            $floor = max($floor, 30);
        }

        // Anti-rebound: recently captured villages cannot be dropped below a buffer for a short window
        if ($targetVillageId) {
            $conqueredAt = $this->getVillageConqueredAt($targetVillageId);
            if ($conqueredAt) {
                $elapsed = time() - $conqueredAt;
                if ($elapsed >= 0 && $elapsed <= self::RECENT_CAPTURE_WINDOW_SECONDS) {
                    $floor = max($floor, self::RECENT_CAPTURE_FLOOR);
                }
            }
        }

        return $floor;
    }

    /**
     * Current world paladin weapon (global).
     */
    private function getPaladinWeapon(): string
    {
        if (!defined('FEATURE_PALADIN_ENABLED') || !FEATURE_PALADIN_ENABLED) {
            return 'none';
        }
        if (!defined('PALADIN_WEAPON')) {
            return 'none';
        }
        return strtolower((string)PALADIN_WEAPON);
    }

    /**
     * Ensure loyalty column exists in villages table.
     */
    private function ensureLoyaltyColumn(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $result = $this->conn->query("SHOW COLUMNS FROM villages LIKE 'loyalty'");
            if ($result && $result->num_rows > 0) {
                return;
            }
        } catch (\Throwable $e) {
            // fallback for SQLite or failures
        }

        try {
            $this->conn->query("ALTER TABLE villages ADD COLUMN loyalty INT NOT NULL DEFAULT 100");
        } catch (\Throwable $e) {
            // Ignore if cannot alter; functions will fallback to default in getters
        }
    }

    private function getVillageLoyalty(int $villageId): int
    {
        $stmt = $this->conn->prepare("SELECT loyalty FROM villages WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return self::LOYALTY_MAX;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !isset($row['loyalty'])) {
            return self::LOYALTY_MAX;
        }
        return (int)$row['loyalty'];
    }

    /**
     * Fetch conquered_at as unix timestamp if present.
     */
    private function getVillageConqueredAt(int $villageId): ?int
    {
        $stmt = $this->conn->prepare("SELECT conquered_at FROM villages WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || empty($row['conquered_at'])) {
            return null;
        }
        $ts = strtotime($row['conquered_at']);
        return $ts !== false ? $ts : null;
    }

    private function updateVillageLoyalty(int $villageId, int $loyalty): void
    {
        $stmt = $this->conn->prepare("UPDATE villages SET loyalty = ?, last_loyalty_update = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt === false) {
            return;
        }
        $loyalty = $this->clampLoyalty($villageId, $loyalty);
        $stmt->bind_param("ii", $loyalty, $villageId);
        $stmt->execute();
        $stmt->close();
    }

    private function transferVillageOwnership(int $villageId, int $newUserId, int $loyaltyAfter): void
    {
        $setClauses = "user_id = ?, loyalty = ?, last_loyalty_update = CURRENT_TIMESTAMP";
        if ($this->villageColumnExists('conquered_at')) {
            $setClauses .= ", conquered_at = CURRENT_TIMESTAMP";
        }
        $stmt = $this->conn->prepare("UPDATE villages SET {$setClauses} WHERE id = ?");
        if ($stmt === false) {
            return;
        }
        $loyaltyAfter = $this->clampLoyalty($villageId, $loyaltyAfter);
        $stmt->bind_param("iii", $newUserId, $loyaltyAfter, $villageId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Clamp loyalty to min/max bounds (dynamic cap aware).
     */
    private function clampLoyalty(int $villageId, int $loyalty): int
    {
        $cap = self::LOYALTY_MAX;
        if (method_exists($this->villageManager, 'getEffectiveLoyaltyCap')) {
            try {
                $cap = (int)round($this->villageManager->getEffectiveLoyaltyCap($villageId));
            } catch (\Throwable $e) {
                $cap = self::LOYALTY_MAX;
            }
        }
        $cap = max(self::LOYALTY_MIN, $cap);
        return max(self::LOYALTY_MIN, min($cap, $loyalty));
    }

    /**
     * Recently conquered villages restart at a vulnerable loyalty.
     */
    private function getConqueredLoyaltyReset(float $cap): int
    {
        // Spec: recently conquered villages start at ~50% loyalty
        $reset = 50;
        return (int)max(self::LOYALTY_MIN, min($cap, $reset));
    }

    /**
     * Column existence helper (cached).
     */
    private function villageColumnExists(string $column): bool
    {
        static $cache = [];
        if (isset($cache[$column])) {
            return $cache[$column];
        }
        if (function_exists('dbColumnExists')) {
            return $cache[$column] = dbColumnExists($this->conn, 'villages', $column);
        }
        return $cache[$column] = false;
    }

    /**
     * Suppress wall bonus based on incoming rams before losses are applied.
     * Raids suppress less wall power and never inflict permanent damage.
     */
    private function calculateEffectiveWallLevel(int $wallLevel, array $attackingUnits, bool $isRaid): int
    {
        if ($wallLevel <= 0) {
            return 0;
        }

        $ramCount = 0;
        foreach ($attackingUnits as $unit) {
            if (!empty($unit['internal_name']) && $unit['internal_name'] === 'ram') {
                $ramCount += $unit['count'];
            }
        }

        if ($ramCount <= 0) {
            return $wallLevel;
        }

        $effectiveness = random_int(
            (int)round(self::RAM_EFFECTIVENESS_MIN * 100),
            (int)round(self::RAM_EFFECTIVENESS_MAX * 100)
        ) / 100;

        $levels_ignored = floor(($ramCount / self::RAMS_PER_WALL_LEVEL) * $effectiveness);

        if ($isRaid) {
            $levels_ignored = floor($levels_ignored * 0.6);
        }

        return max(0, $wallLevel - $levels_ignored);
    }

    /**
     * Calculates distance between two map points
     * 
     * @param int $x1 X coordinate of point 1
     * @param int $y1 Y coordinate of point 1
     * @param int $x2 X coordinate of point 2
     * @param int $y2 Y coordinate of point 2
     * @return float Distance between points
     */
    private function calculateDistance($x1, $y1, $x2, $y2)
    {
        return sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
    }

    /**
     * Helper: fetch research level for a given village and research internal name.
     */
    private function getResearchLevelForVillage(int $villageId, string $researchInternalName): int
    {
        $stmt = $this->conn->prepare("
            SELECT vr.level
            FROM village_research vr
            JOIN research_types rt ON vr.research_type_id = rt.id
            WHERE vr.village_id = ? AND rt.internal_name = ?
        ");
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param("is", $villageId, $researchInternalName);
        $stmt->execute();
        $result = $stmt->get_result();
        $level = 0;
        if ($row = $result->fetch_assoc()) {
            $level = (int)$row['level'];
        }
        $stmt->close();
        return $level;
    }

    /**
     * Helper: return building levels snapshot for a village.
     */
    private function getBuildingSnapshot(int $villageId): array
    {
        $stmt = $this->conn->prepare("
            SELECT bt.internal_name, bt.name, vb.level
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ?
        ");
        $snapshot = [];
        if ($stmt === false) {
            return $snapshot;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $snapshot[$row['internal_name']] = [
                'name' => $row['name'],
                'level' => (int)$row['level']
            ];
        }
        $stmt->close();
        ksort($snapshot);
        return $snapshot;
    }

    /**
     * Helper: return unit snapshot (counts + metadata) for a village.
     */
    private function getVillageUnitSnapshot(int $villageId): array
    {
        $stmt = $this->conn->prepare("
            SELECT ut.internal_name, ut.name, vu.count
            FROM village_units vu
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE vu.village_id = ? AND vu.count > 0
        ");
        $units = [];
        if ($stmt === false) {
            return $units;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $units[] = [
                'internal_name' => $row['internal_name'],
                'name' => $row['name'],
                'count' => (int)$row['count']
            ];
        }
        $stmt->close();
        return $units;
    }

    /**
     * Helper: village name by ID (empty string on failure).
     */
    private function getVillageName(int $villageId): string
    {
        $stmt = $this->conn->prepare("SELECT name FROM villages WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return '';
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res['name'] ?? '';
    }

    /**
     * Resolve mission type for a given attack (defaults to light_scout).
     */
    private function getMissionTypeForAttack(int $attackId): string
    {
        $stmt = $this->conn->prepare("SELECT mission_type FROM scout_missions WHERE attack_id = ? LIMIT 1");
        if ($stmt === false) {
            return 'light_scout';
        }
        $stmt->bind_param("i", $attackId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $res && $res->free();
        $stmt->close();
        return $row['mission_type'] ?? 'light_scout';
    }

    /**
     * Mark a scout mission as resolved once the spy report is processed.
     */
    private function markScoutMissionResolved(int $attackId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE scout_missions
            SET status = 'resolved', resolved_at = UNIX_TIMESTAMP()
            WHERE attack_id = ?
        ");
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param("i", $attackId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Helper: get tribe id for a user (null if none).
     */
    private function getUserTribeId(int $userId): ?int
    {
        $stmt = $this->conn->prepare("SELECT tribe_id FROM tribe_members WHERE user_id = ? LIMIT 1");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$res || !$res['tribe_id']) {
            return null;
        }
        return (int)$res['tribe_id'];
    }

    /**
     * Helper: get diplomacy status between two tribes (ally/nap/enemy/etc).
     */
    private function getDiplomacyStatus(?int $tribeA, ?int $tribeB): ?string
    {
        if (!$tribeA || !$tribeB || $tribeA === $tribeB) {
            return null;
        }
        $stmt = $this->conn->prepare("
            SELECT status
            FROM tribe_diplomacy
            WHERE tribe_id = ? AND target_tribe_id = ?
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $tribeA, $tribeB);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row && isset($row['status'])) {
                return $row['status'];
            }
        }
        $stmt2 = $this->conn->prepare("
            SELECT status
            FROM tribe_diplomacy
            WHERE tribe_id = ? AND target_tribe_id = ?
            LIMIT 1
        ");
        if ($stmt2) {
            $stmt2->bind_param("ii", $tribeB, $tribeA);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $row2 = $res2 ? $res2->fetch_assoc() : null;
            $stmt2->close();
            if ($row2 && isset($row2['status'])) {
                return $row2['status'];
            }
        }
        return null;
    }

    /**
     * Helper: record tribe activity such as friendly fire overrides.
     */
    private function logTribeAction(int $tribeId, int $actorUserId, string $action, array $meta = []): void
    {
        if ($tribeId <= 0) {
            return;
        }
        $metaJson = json_encode($meta);
        $stmt = $this->conn->prepare("
            INSERT INTO tribe_activity_log (tribe_id, actor_user_id, action, meta, created_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("iiss", $tribeId, $actorUserId, $action, $metaJson);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Helper: return research snapshot (unlocked tech levels) for a village.
     */
    private function getResearchSnapshot(int $villageId): array
    {
        $stmt = $this->conn->prepare("
            SELECT rt.internal_name, rt.name, vr.level
            FROM village_research vr
            JOIN research_types rt ON vr.research_type_id = rt.id
            WHERE vr.village_id = ? AND vr.level > 0
        ");
        $research = [];
        if ($stmt === false) {
            return $research;
        }
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $research[$row['internal_name']] = [
                'name' => $row['name'],
                'level' => (int)$row['level']
            ];
        }
        $stmt->close();
        ksort($research);
        return $research;
    }
    
    /**
     * Fetch list of incoming attacks for a village
     * 
     * @param int $village_id Village ID
     * @return array Incoming attacks
     */
    public function getIncomingAttacks($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.source_village_id, a.attack_type, a.start_time, a.arrival_time,
                   v.name as source_village_name, v.x_coord as source_x, v.y_coord as source_y,
                   u.username as attacker_name
            FROM attacks a
            JOIN villages v ON a.source_village_id = v.id
            JOIN users u ON v.user_id = u.id
            WHERE a.target_village_id = ? AND a.is_completed = 0 AND a.is_canceled = 0
            ORDER BY a.arrival_time ASC
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $incoming_attacks = [];
        while ($attack = $result->fetch_assoc()) {
            // Calculate remaining time
            $arrival_time = strtotime($attack['arrival_time']);
            $current_time = time();
            $remaining_time = max(0, $arrival_time - $current_time);
            
            $attack['remaining_time'] = $remaining_time;
            $attack['formatted_remaining_time'] = $this->formatTime($remaining_time);
            $attack['formatted_start_time'] = date('Y-m-d H:i:s', strtotime($attack['start_time']));
            $attack['formatted_arrival_time'] = date('Y-m-d H:i:s', $arrival_time);
            
            $incoming_attacks[] = $attack;
        }
        $stmt->close();
        
        return $incoming_attacks;
    }
    
    /**
     * Fetch list of outgoing attacks for a village
     * 
     * @param int $village_id Village ID
     * @return array Outgoing attacks
     */
    public function getOutgoingAttacks($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.target_village_id, a.attack_type, a.start_time, a.arrival_time,
                   v.name as target_village_name, v.x_coord as target_x, v.y_coord as target_y,
                   u.username as defender_name
            FROM attacks a
            JOIN villages v ON a.target_village_id = v.id
            JOIN users u ON v.user_id = u.id
            WHERE a.source_village_id = ? AND a.is_completed = 0 AND a.is_canceled = 0
            ORDER BY a.arrival_time ASC
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $outgoing_attacks = [];
        while ($attack = $result->fetch_assoc()) {
            // Calculate remaining time
            $arrival_time = strtotime($attack['arrival_time']);
            $start_time = strtotime($attack['start_time']);
            $current_time = time();
            $remaining_time = max(0, $arrival_time - $current_time);
            $travel_time = max(1, $arrival_time - $start_time);
            $return_time = $arrival_time + $travel_time;
            $return_remaining = max(0, $return_time - $current_time);
            
            $attack['remaining_time'] = $remaining_time;
            $attack['formatted_remaining_time'] = $this->formatTime($remaining_time);
            $attack['formatted_start_time'] = date('Y-m-d H:i:s', $start_time);
            $attack['formatted_arrival_time'] = date('Y-m-d H:i:s', $arrival_time);
            $attack['formatted_return_time'] = date('Y-m-d H:i:s', $return_time);
            $attack['formatted_return_remaining'] = $this->formatTime($return_remaining);
            
            // Add info about sent units
            $attack['units'] = $this->getAttackUnits($attack['id']);
            
            $outgoing_attacks[] = $attack;
        }
        $stmt->close();
        
        return $outgoing_attacks;
    }
    
    /**
     * Fetch units involved in an attack
     * 
     * @param int $attack_id Attack ID
     * @return array Units list
     */
    public function getAttackUnits($attack_id)
    {
        $stmt = $this->conn->prepare("
            SELECT au.unit_type_id, au.count, ut.name, ut.internal_name
            FROM attack_units au
            JOIN unit_types ut ON au.unit_type_id = ut.id
            WHERE au.attack_id = ?
        ");
        $stmt->bind_param("i", $attack_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $units = [];
        while ($unit = $result->fetch_assoc()) {
            $units[] = $unit;
        }
        $stmt->close();
        
        return $units;
    }
    
    /**
     * Quick access check for a battle report.
     */
    public function userCanViewReport(int $reportId, int $userId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT 1
            FROM battle_reports br
            JOIN villages sv ON br.source_village_id = sv.id
            JOIN villages tv ON br.target_village_id = tv.id
            WHERE br.id = ? AND (sv.user_id = ? OR tv.user_id = ?)
            LIMIT 1
        ");
        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param("iii", $reportId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result ? $result->num_rows > 0 : false;
        $stmt->close();
        return $exists;
    }
    
    /**
     * Fetch a battle report
     * 
     * @param int $report_id Report ID
     * @param int $user_id User ID (permission check)
     * @return array Battle report data
     */
    public function getBattleReport($report_id, $user_id)
    {
        $stmt = $this->conn->prepare("
            SELECT
                br.id, br.attack_id, br.source_village_id, br.target_village_id,
                br.battle_time, br.attacker_won, br.report_data,
                a.attack_type,
                sv.name as source_village_name, sv.x_coord as source_x, sv.y_coord as source_y, sv.user_id as source_user_id,
                tv.name as target_village_name, tv.x_coord as target_x, tv.y_coord as target_y, tv.user_id as target_user_id,
                attacker.username as attacker_name, defender.username as defender_name
            FROM battle_reports br
            JOIN attacks a ON a.id = br.attack_id
            JOIN villages sv ON br.source_village_id = sv.id
            JOIN villages tv ON br.target_village_id = tv.id
            JOIN users attacker ON sv.user_id = attacker.id
            JOIN users defender ON tv.user_id = defender.id
            WHERE br.id = ? AND (sv.user_id = ? OR tv.user_id = ?)
            LIMIT 1
        ");
        if ($stmt === false) {
            return [
                'success' => false,
                'error' => 'Failed to load report.'
            ];
        }

        $stmt->bind_param("iii", $report_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'Report does not exist or you do not have access.'
            ];
        }

        $report_row = $result->fetch_assoc();
        $stmt->close();

        $details = json_decode($report_row['report_data'], true);
        if (!is_array($details)) {
            $details = [];
        }

        $type = $details['type'] ?? $report_row['attack_type'] ?? 'battle';

        // Build unit summaries from the stored details (if present)
        $attacker_units = [];
        $defender_units = [];

        if (!empty($details['attacker_losses']) && is_array($details['attacker_losses'])) {
            foreach ($details['attacker_losses'] as $unit_type_id => $unit) {
                $attacker_units[] = [
                    'unit_type_id' => (int)$unit_type_id,
                    'name' => $unit['unit_name'] ?? 'Unit',
                    'initial_count' => $unit['initial_count'] ?? 0,
                    'lost_count' => $unit['lost_count'] ?? 0,
                    'remaining_count' => $unit['remaining_count'] ?? 0
                ];
            }
        }

        if (!empty($details['defender_losses']) && is_array($details['defender_losses'])) {
            foreach ($details['defender_losses'] as $unit_type_id => $unit) {
                $defender_units[] = [
                    'unit_type_id' => (int)$unit_type_id,
                    'name' => $unit['unit_name'] ?? 'Unit',
                    'initial_count' => $unit['initial_count'] ?? 0,
                    'lost_count' => $unit['lost_count'] ?? 0,
                    'remaining_count' => $unit['remaining_count'] ?? 0
                ];
            }
        }

        // Spy reports store counts differently
        if ($type === 'spy' && empty($attacker_units) && isset($details['attacker_spies_sent'])) {
            $attacker_units[] = [
                'unit_type_id' => null,
                'name' => 'Scout',
                'initial_count' => $details['attacker_spies_sent'],
                'lost_count' => $details['attacker_spies_lost'] ?? 0,
                'remaining_count' => $details['attacker_spies_returned'] ?? 0
            ];
            $defender_units[] = [
                'unit_type_id' => null,
                'name' => 'Defender scouts',
                'initial_count' => $details['defender_spies'] ?? 0,
                'lost_count' => $details['defender_spies_lost'] ?? 0,
                'remaining_count' => $details['defender_spies_remaining'] ?? ($details['defender_spies'] ?? 0)
            ];
        }

        $report = [
            'id' => $report_row['id'],
            'attack_id' => $report_row['attack_id'],
            'attack_type' => $report_row['attack_type'],
            'type' => $type,
            'attacker_won' => (bool)$report_row['attacker_won'],
            'battle_time' => $report_row['battle_time'],
            'attacker_name' => $report_row['attacker_name'],
            'defender_name' => $report_row['defender_name'],
            'source_village_name' => $report_row['source_village_name'],
            'target_village_name' => $report_row['target_village_name'],
            'source_x' => $report_row['source_x'],
            'source_y' => $report_row['source_y'],
            'target_x' => $report_row['target_x'],
            'target_y' => $report_row['target_y'],
            'details' => $details,
            'attacker_units' => $attacker_units,
            'defender_units' => $defender_units
        ];

        return [
            'success' => true,
            'report' => $report
        ];
    }

    /**
     * Process a single attack by ID based on its mission type.
     * Public entry point for tick-based processors.
     *
     * @param int $attack_id Attack command ID.
     * @return array Result of processing.
     */
    public function processAttackArrival(int $attack_id): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, attack_type
            FROM attacks
            WHERE id = ? AND is_completed = 0 AND is_canceled = 0
            LIMIT 1
        ");
        if ($stmt === false) {
            return ['success' => false, 'error' => 'Failed to load attack.'];
        }
        $stmt->bind_param("i", $attack_id);
        $stmt->execute();
        $attack = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$attack) {
            return ['success' => false, 'error' => 'Attack not found or already resolved.'];
        }

        switch ($attack['attack_type']) {
            case 'spy':
                return $this->processSpyMission($attack_id);
            case 'support':
                return $this->processSupportArrival($attack_id);
            case 'return':
                return $this->processReturnArrival($attack_id);
            case 'fake':
                return $this->processFakeArrival($attack_id);
            default:
                return $this->processBattle($attack_id);
        }
    }
    
    /**
     * Format seconds into a readable hh:mm:ss string.
     * 
     * @param int $seconds Time in seconds
     * @return string Formatted time
     */
    private function formatTime($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * Fetch paginated battle reports for a user.
     *
     * @param int $userId User ID.
     * @param int $limit Reports per page.
     * @param int $offset Offset dla paginacji.
     * @return array Battle reports.
     */
    public function getBattleReportsForUser(int $userId, int $limit, int $offset): array
    {
        $reports = [];
        $stmt = $this->conn->prepare("
            SELECT
                br.id, br.attacker_won, br.battle_time as created_at, br.report_data,
                a.attack_type,
                sv.name as source_village_name, sv.x_coord as source_x, sv.y_coord as source_y, sv.user_id as source_user_id,
                tv.name as target_village_name, tv.x_coord as target_x, tv.y_coord as target_y, tv.user_id as target_user_id,
                u_attacker.username as attacker_name, u_defender.username as defender_name
            FROM battle_reports br
            JOIN attacks a ON a.id = br.attack_id
            JOIN villages sv ON br.source_village_id = sv.id
            JOIN villages tv ON br.target_village_id = tv.id
            JOIN users u_attacker ON sv.user_id = u_attacker.id
            JOIN users u_defender ON tv.user_id = u_defender.id
            WHERE sv.user_id = ? OR tv.user_id = ?
            ORDER BY br.battle_time DESC
            LIMIT ? OFFSET ?
        ");
        // Bind parameters in order: sv.user_id, tv.user_id, limit, offset
        $stmt->bind_param("iiii", $userId, $userId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $details = json_decode($row['report_data'] ?? '', true);
            if (!is_array($details)) {
                $details = [];
            }
            // Determine whether the user was attacker or defender
            $row['is_attacker'] = ($row['source_user_id'] == $userId);
            $row['type'] = $details['type'] ?? $row['attack_type'] ?? 'battle';
            // Format the date (could also be done in the frontend)
            $row['formatted_date'] = date('d.m.Y H:i:s', strtotime($row['created_at']));
            $row['report_id'] = $row['id'];
            $reports[] = $row;
        }
        $stmt->close();

        return $reports;
    }

    /**
     * Fetch total number of battle reports for the user.
     *
     * @param int $userId User ID.
     * @return int Total number of reports.
     */
    public function getTotalBattleReportsForUser(int $userId): int
    {
        $countQuery = "SELECT COUNT(*) as total
                     FROM battle_reports br
                     JOIN villages sv ON br.source_village_id = sv.id
                     JOIN villages tv ON br.target_village_id = tv.id
                     WHERE sv.user_id = ? OR tv.user_id = ?";
        $countStmt = $this->conn->prepare($countQuery);
        $countStmt->bind_param("ii", $userId, $userId);
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();

        return $countResult['total'] ?? 0;
    }

} 
