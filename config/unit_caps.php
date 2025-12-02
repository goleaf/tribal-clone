<?php
/**
 * Unit Caps Configuration
 * 
 * This file defines the maximum number of specific unit types that can be
 * trained or maintained per village, per account, or per command. Unit caps
 * prevent exploits, maintain game balance, and encourage strategic diversity.
 * 
 * Caps are enforced during recruitment and validated before battle commands
 * are executed. The system counts both stationed units and units in transit
 * or queued for training.
 */

/**
 * ============================================================================
 * PER-VILLAGE CAPS
 * ============================================================================
 * 
 * These caps limit the total number of specific unit types that can exist
 * in a single village, including:
 * - Units stationed in the village
 * - Units currently in the recruitment queue
 * - Units returning from commands
 * 
 * Per-village caps prevent single villages from becoming unstoppable
 * fortresses or siege factories.
 */

/**
 * Siege Unit Cap Per Village
 * 
 * Maximum total siege units (Battering Ram, Stone Hurler, Mantlet Crew)
 * that can be trained or stationed in a single village.
 * 
 * Rationale:
 * - Prevents single-village siege stacking
 * - Encourages distributed siege production across multiple villages
 * - Limits the destructive power of a single attack
 * - Forces players to coordinate multi-village siege operations
 * 
 * Applied to: ram, battering_ram, catapult, stone_hurler, mantlet_crew
 */
const SIEGE_CAP_PER_VILLAGE = 200;

/**
 * Siege Unit Internal Names
 * 
 * List of unit internal names that count toward the siege cap.
 */
const SIEGE_UNIT_INTERNALS = [
    'ram',
    'battering_ram',
    'catapult',
    'stone_hurler',
    'mantlet_crew',
    'trebuchet'
];

/**
 * ============================================================================
 * PER-ACCOUNT CAPS
 * ============================================================================
 * 
 * These caps limit the total number of specific unit types across ALL
 * villages owned by a single player account. This includes:
 * - Units stationed in all villages
 * - Units in transit between villages
 * - Units in recruitment queues across all villages
 * 
 * Per-account caps prevent elite unit hoarding and ensure powerful units
 * remain rare and strategic rather than mass-produced.
 */

/**
 * Elite Unit Caps Per Account
 * 
 * Maximum total elite units that can be owned by a single player across
 * all their villages.
 * 
 * Rationale:
 * - Elite units have superior stats and should be rare
 * - Prevents players from creating unstoppable elite-only armies
 * - Encourages strategic deployment of elite units
 * - Maintains balance between veteran and newer players
 * 
 * Elite units are defined by their high resource costs, long training times,
 * and superior combat stats.
 */
const ELITE_UNIT_CAPS = [
    'warden' => 100,           // Elite defensive infantry
    'ranger' => 100,           // Elite ranged with anti-siege bonus
    'tempest_knight' => 50,    // Seasonal elite cavalry
    'event_knight' => 50       // Event-specific elite unit
];

/**
 * Seasonal Unit Caps Per Account
 * 
 * Seasonal units are limited-time units available during special events.
 * They have per-account caps to prevent hoarding and ensure fair access
 * during the event window.
 * 
 * Rationale:
 * - Prevents players from stockpiling seasonal units
 * - Ensures seasonal units remain special and limited
 * - Balances power between active and inactive players during events
 * - Allows sunset handling when events expire
 * 
 * Default cap: 50 per seasonal unit type
 * Can be overridden in seasonal_units table per event
 */
const SEASONAL_UNIT_DEFAULT_CAP = 50;

/**
 * ============================================================================
 * PER-COMMAND CAPS
 * ============================================================================
 * 
 * These caps limit the number of specific unit types that can be sent in
 * a single attack or support command. This prevents single-command
 * overwhelming strategies.
 */

/**
 * Conquest Units Per Command
 * 
 * Maximum number of conquest units (Noble, Standard Bearer) that can be
 * sent in a single attack command.
 * 
 * Rationale:
 * - Prevents instant village capture with mass nobles
 * - Forces multiple coordinated attacks for conquest
 * - Gives defenders time to respond and reinforce
 * - Creates strategic depth in conquest warfare
 * - Prevents "noble trains" that trivialize conquest
 * 
 * Applied to: noble, chieftain, senator, chief, envoy, standard_bearer
 */
const MAX_LOYALTY_UNITS_PER_COMMAND = 1;

/**
 * Conquest Unit Internal Names
 * 
 * List of unit internal names that count toward the conquest cap.
 */
const LOYALTY_UNIT_INTERNALS = [
    'noble',
    'chieftain',
    'senator',
    'chief',
    'envoy',
    'standard_bearer'
];

/**
 * ============================================================================
 * SCOUT CAPS
 * ============================================================================
 * 
 * Scout units have special caps to prevent intelligence spam and abuse.
 */

/**
 * Scout Commands Per Minute
 * 
 * Maximum number of scout commands a player can send per minute.
 * 
 * Rationale:
 * - Prevents scout spam to overwhelm defenders
 * - Limits intelligence gathering rate
 * - Reduces server load from excessive scouting
 */
const MAX_SCOUTS_PER_MINUTE = 10;

/**
 * Scout Commands Per Target Per Window
 * 
 * Maximum number of scout commands a single attacker can send to a single
 * target within a 15-minute window.
 * 
 * Rationale:
 * - Prevents repeated scouting of the same target
 * - Forces players to act on intelligence rather than constantly re-scout
 * - Reduces harassment potential
 */
const MAX_SCOUTS_PER_TARGET_PER_WINDOW = 5;

/**
 * Scout Target Window (seconds)
 * 
 * Time window for scout per-target cap enforcement.
 */
const SCOUT_TARGET_WINDOW_SECONDS = 900; // 15 minutes

/**
 * ============================================================================
 * SUPPORT UNIT CAPS
 * ============================================================================
 * 
 * Support units (Banner Guard, War Healer) have implicit caps based on
 * their mechanics rather than hard limits.
 */

/**
 * Banner Guard Stacking
 * 
 * Multiple Banner Guards do NOT stack their aura effects. Only the highest
 * tier aura applies to defending troops.
 * 
 * Rationale:
 * - Prevents exponential defense scaling with banner stacking
 * - Encourages diverse army compositions
 * - Maintains predictable combat outcomes
 * 
 * Implementation: No hard cap, but only highest aura applies
 */

/**
 * War Healer Recovery Cap
 * 
 * War Healers can recover a percentage of lost troops after battle, but
 * the total recovery is capped per battle.
 * 
 * Rationale:
 * - Prevents healers from making armies nearly invincible
 * - Maintains meaningful casualties in combat
 * - Balances healer value without making them mandatory
 * 
 * Default: 15% of total losses per battle
 * Configured in worlds table: healer_recovery_cap
 */
const HEALER_RECOVERY_CAP_DEFAULT = 0.15; // 15%

/**
 * ============================================================================
 * CAP ENFORCEMENT RULES
 * ============================================================================
 * 
 * How caps are enforced during gameplay:
 */

/**
 * Recruitment Enforcement
 * 
 * When a player attempts to train units:
 * 1. System counts existing units (stationed + in transit)
 * 2. System counts queued units (in recruitment queue)
 * 3. System adds requested count to total
 * 4. If total exceeds cap, request is rejected with ERR_CAP
 * 5. Error response includes current count and cap limit
 * 
 * See: lib/managers/UnitManager.php::checkRecruitRequirements()
 */

/**
 * Command Enforcement
 * 
 * When a player sends an attack or support command:
 * 1. System validates conquest unit count ≤ MAX_LOYALTY_UNITS_PER_COMMAND
 * 2. System checks scout spam limits if command type is 'spy'
 * 3. If limits exceeded, command is rejected before execution
 * 4. Telemetry logs cap violations for monitoring
 * 
 * See: lib/managers/BattleManager.php::validateCommand()
 */

/**
 * Concurrent Request Handling
 * 
 * Multiple simultaneous recruitment requests are handled atomically:
 * 1. Database transactions ensure cap checks are serialized
 * 2. Row-level locking prevents race conditions
 * 3. If concurrent requests would exceed cap, at least one is rejected
 * 4. First request to acquire lock succeeds, others fail
 * 
 * See: lib/managers/UnitManager.php::recruitUnits()
 */

/**
 * ============================================================================
 * WORLD-SPECIFIC OVERRIDES
 * ============================================================================
 * 
 * World administrators can override these caps for specific server archetypes:
 */

/**
 * Speed Worlds
 * 
 * May increase caps to allow faster army building:
 * - SIEGE_CAP_PER_VILLAGE: 300 (50% increase)
 * - ELITE_UNIT_CAPS: 150 per type (50% increase)
 * - MAX_LOYALTY_UNITS_PER_COMMAND: 1 (unchanged for balance)
 */

/**
 * Hardcore Worlds
 * 
 * May decrease caps to increase difficulty:
 * - SIEGE_CAP_PER_VILLAGE: 100 (50% decrease)
 * - ELITE_UNIT_CAPS: 50 per type (50% decrease)
 * - MAX_LOYALTY_UNITS_PER_COMMAND: 1 (unchanged)
 */

/**
 * Casual Worlds
 * 
 * Use standard caps defined in this file.
 */

/**
 * ============================================================================
 * BALANCE NOTES
 * ============================================================================
 * 
 * Cap Design Principles:
 * 
 * 1. Caps should be high enough to not feel restrictive in normal play
 * 2. Caps should be low enough to prevent degenerate strategies
 * 3. Caps should encourage village specialization (siege village, defense village)
 * 4. Caps should scale with player progression (account caps > village caps)
 * 5. Caps should be transparent and clearly communicated to players
 * 
 * Historical Balance Changes:
 * - v1.0: Initial implementation with 200 siege cap per village
 * - v1.1: Added elite unit caps at 100 per type
 * - v1.2: Reduced conquest units per command from 3 to 1 (too powerful)
 * - v1.3: Added seasonal unit caps at 50 per type
 * - v1.4: Added scout spam prevention caps
 * 
 * Testing Recommendations:
 * - Test cap enforcement with concurrent requests (race conditions)
 * - Test cap counting includes queued and in-transit units
 * - Test error messages clearly communicate current count and limit
 * - Monitor telemetry for cap hit rates (should be < 5% of requests)
 * - Alert on unusual cap hit patterns (potential exploits)
 */

/**
 * ============================================================================
 * TELEMETRY AND MONITORING
 * ============================================================================
 * 
 * Cap violations are logged for balance monitoring:
 * 
 * Metrics tracked:
 * - Cap hit rate by unit type and world
 * - Cap hit rate by player (detect hoarding patterns)
 * - Time-to-cap for elite units (progression pacing)
 * - Concurrent cap violation attempts (exploit detection)
 * 
 * Alerts triggered:
 * - Cap hit rate > 10% for any unit type (cap too low)
 * - Single player hitting elite caps repeatedly (hoarding)
 * - Burst of cap violations (potential exploit)
 * - Seasonal unit caps hit before event midpoint (cap too low)
 * 
 * See: logs/recruit_telemetry.log
 * See: logs/cap_hit_counters.log
 */

/**
 * ============================================================================
 * IMPLEMENTATION REFERENCE
 * ============================================================================
 * 
 * Cap enforcement is implemented in:
 * - lib/managers/UnitManager.php::checkRecruitRequirements()
 * - lib/managers/UnitManager.php::checkEliteUnitCap()
 * - lib/managers/UnitManager.php::getVillageUnitCountWithQueue()
 * - lib/managers/BattleManager.php::validateCommand()
 * 
 * Tests:
 * - tests/elite_unit_cap_test.php
 * - tests/siege_cap_per_village_test.php
 * - tests/conquest_per_command_cap_test.php
 * - tests/unit_availability_test.php
 */

