<?php
/**
 * RPS (Rock-Paper-Scissors) Combat Modifiers Configuration
 * 
 * This file defines the standard multipliers for unit matchups in the combat system.
 * These modifiers are applied during battle resolution to create strategic depth
 * and encourage diverse army compositions.
 * 
 * Modifiers are stored in units.json under the 'rps_bonuses' field for each unit type.
 * This configuration file serves as documentation and reference for balance tuning.
 */

/**
 * ============================================================================
 * CAVALRY VS RANGED (OPEN FIELD)
 * ============================================================================
 * 
 * Cavalry units gain a significant attack bonus against ranged units when
 * fighting in open field (wall level = 0). This represents cavalry's ability
 * to close distance quickly and overwhelm archers before they can effectively
 * respond.
 * 
 * Context Requirements:
 * - Attacker has cavalry units
 * - Defender has ranged units
 * - Wall level = 0 (open field)
 * 
 * Applied to: Cavalry unit attack values
 */
const CAV_VS_RANGED_MULT = 1.5;

/**
 * ============================================================================
 * PIKE VS CAVALRY
 * ============================================================================
 * 
 * Pike infantry (Pikeneer) gains a defense bonus against cavalry attacks.
 * This represents pike formations' historical effectiveness at stopping
 * cavalry charges.
 * 
 * Context Requirements:
 * - Attacker has cavalry units
 * - Defender has pike infantry (units with 'vs_cavalry' RPS bonus)
 * 
 * Applied to: Pike unit defense values
 */
const PIKE_VS_CAV_MULT = 1.4;

/**
 * ============================================================================
 * RANGER VS SIEGE
 * ============================================================================
 * 
 * Elite ranger units (Ranger) gain an attack bonus against siege equipment.
 * This represents their ability to target and disable siege weapons from
 * range before they can be deployed effectively.
 * 
 * Context Requirements:
 * - Attacker has ranger units (units with 'vs_siege' RPS bonus)
 * - Defender has siege units
 * 
 * Applied to: Ranger unit attack values
 */
const RANGER_VS_SIEGE_MULT = 1.6;

/**
 * ============================================================================
 * RANGED WALL BONUS VS INFANTRY
 * ============================================================================
 * 
 * Ranged units gain a defense bonus against infantry when defending behind
 * walls. This represents the advantage of elevated positions and fortifications
 * for archers. The bonus scales with wall level.
 * 
 * Context Requirements:
 * - Defender has ranged units
 * - Wall level > 0
 * - Attacker has infantry units
 * 
 * Applied to: Ranged unit defense values
 */
const RANGED_WALL_BONUS_VS_INFANTRY_MULT = 1.5;

/**
 * ============================================================================
 * CONTEXT RULES
 * ============================================================================
 * 
 * RPS modifiers are context-dependent and only apply when specific battle
 * conditions are met. The following rules govern when modifiers are applied:
 */

/**
 * Wall Level Thresholds
 * 
 * - Wall level 0: Open field combat (cavalry vs ranged bonus applies)
 * - Wall level 1+: Fortified defense (ranged wall bonus applies)
 * - Wall level 10+: Maximum defensive advantage for ranged units
 */
const WALL_LEVEL_OPEN_FIELD = 0;
const WALL_LEVEL_FORTIFIED = 1;
const WALL_LEVEL_MAX_BONUS = 10;

/**
 * Unit Category Matching
 * 
 * Categories used for RPS matchup detection:
 * - 'infantry': Ground melee units (Pikeneer, Shieldbearer, Raider, Warden)
 * - 'cavalry': Mounted units (Skirmisher Cavalry, Lancer)
 * - 'ranged': Archer units (Militia Bowman, Longbow Scout, Ranger)
 * - 'siege': Siege equipment (Battering Ram, Stone Hurler, Mantlet Crew)
 * - 'scout': Intelligence units (Pathfinder, Shadow Rider)
 * - 'support': Buff units (Banner Guard, War Healer)
 * - 'conquest': Allegiance reduction units (Noble, Standard Bearer)
 */

/**
 * Modifier Application Order
 * 
 * Modifiers are applied in the following order during battle resolution:
 * 1. Load base unit stats from unit_types table
 * 2. Apply world training multipliers (if applicable)
 * 3. Apply RPS matchup modifiers (this configuration)
 * 4. Apply support unit effects (Banner aura, Mantlet protection)
 * 5. Calculate casualties with modified values
 */

/**
 * ============================================================================
 * BALANCE NOTES
 * ============================================================================
 * 
 * RPS Modifier Design Principles:
 * 
 * 1. Modifiers should be significant enough to matter (1.3x minimum)
 * 2. Modifiers should not be so large as to make matchups unwinnable (2.0x maximum)
 * 3. Context requirements prevent modifiers from always applying
 * 4. Multiple modifiers do not stack (only highest applies per unit)
 * 5. Modifiers encourage diverse army compositions rather than mono-unit stacks
 * 
 * Historical Balance Changes:
 * - v1.0: Initial implementation with 1.5x standard multiplier
 * - v1.1: Reduced cavalry vs ranged from 1.6x to 1.5x (too dominant)
 * - v1.2: Increased ranger vs siege from 1.5x to 1.6x (siege too safe)
 * - v1.3: Added pike vs cavalry at 1.4x (cavalry needed counter)
 * 
 * Testing Recommendations:
 * - Test each matchup with 100+ property-based test iterations
 * - Verify modifiers apply only when context requirements are met
 * - Ensure modifiers are included in battle reports for transparency
 * - Monitor telemetry for matchup win rates and adjust as needed
 */

/**
 * ============================================================================
 * WORLD-SPECIFIC OVERRIDES
 * ============================================================================
 * 
 * World administrators can override these multipliers in units.json for
 * specific world archetypes:
 * 
 * - Speed worlds: May reduce all multipliers to 1.3x for faster battles
 * - Hardcore worlds: May increase multipliers to 1.8x for more decisive matchups
 * - Casual worlds: May use standard multipliers (1.4-1.6x range)
 * 
 * Overrides are defined per-unit in the 'rps_bonuses' field:
 * 
 * Example:
 * {
 *   "pikeneer": {
 *     "rps_bonuses": {
 *       "vs_cavalry": 1.4
 *     }
 *   }
 * }
 */

/**
 * ============================================================================
 * IMPLEMENTATION REFERENCE
 * ============================================================================
 * 
 * RPS modifiers are applied in BattleManager::applyRPSModifiers()
 * 
 * The method:
 * 1. Counts unit types in attacker and defender armies
 * 2. Checks context requirements (wall level, unit categories)
 * 3. Loads RPS bonuses from units.json for matching units
 * 4. Applies multipliers to attack or defense values
 * 5. Returns array of applied modifiers for battle report
 * 
 * See: lib/managers/BattleManager.php::applyRPSModifiers()
 * Tests: tests/rps_modifiers_integration_test.php
 */

