# Implementation Plan

- [x] 1. Set up database schema and data structures
- [x] 1.1 Create database migration for new tables (seasonal_units, elite_unit_caps)
  - Add seasonal_units table with event windows and caps
  - Add elite_unit_caps table for per-account tracking
  - _Requirements: 10.1, 10.2, 9.2_

- [x] 1.2 Create database migration for worlds table extensions
  - Add feature flag columns (conquest_units_enabled, seasonal_units_enabled, healer_enabled)
  - Add training multiplier columns (train_multiplier_inf, train_multiplier_cav, train_multiplier_rng, train_multiplier_siege)
  - Add healer_recovery_cap column
  - _Requirements: 11.1, 11.2, 15.5_

- [x] 1.3 Create database migration for unit_types table extensions
  - Add category column for unit classification
  - Add rps_bonuses JSON column for combat modifiers
  - Add special_abilities JSON column for unit abilities
  - Add aura_config JSON column for support units
  - _Requirements: 1.4, 6.3, 14.2_

- [x] 1.4 Populate data/units.json with complete 16+ unit roster
  - Add infantry units (Pikeneer, Shieldbearer, Raider, Warden)
  - Add ranged units (Militia Bowman, Longbow Scout, Ranger)
  - Add cavalry units (Skirmisher Cavalry, Lancer)
  - Add scout units (Pathfinder, Shadow Rider)
  - Add siege units (Battering Ram, Stone Hurler, Mantlet Crew)
  - Add support units (Banner Guard, War Healer)
  - Add conquest units (Noble, Standard Bearer)
  - Include all stats: attack, defense values, speed, carry, population, costs, training time, RPS bonuses
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 4.1, 4.2, 5.1, 5.2, 6.1, 6.2, 7.1, 7.2, 8.1, 8.2, 14.1_

- [ ]* 1.5 Write property test for unit data completeness
  - **Property 20: Unit data completeness**
  - **Validates: Requirements 13.1, 13.3**

- [x] 2. Extend UnitManager with new functionality
- [x] 2.1 Implement getUnitCategory() method
  - Return category based on unit internal name or building type
  - Categories: 'infantry', 'cavalry', 'ranged', 'siege', 'scout', 'support', 'conquest'
  - _Requirements: 1.4, 3.3, 8.4_

- [x] 2.2 Implement isUnitAvailable() method
  - Check world feature flags (conquest, seasonal, healer)
  - Check seasonal window if applicable
  - Return boolean availability
  - _Requirements: 10.1, 10.2, 15.5_

- [x] 2.3 Implement getEffectiveUnitStats() method
  - Load base stats from unit_types
  - Apply world training time multipliers by archetype
  - Apply world cost multipliers by archetype
  - Return effective stats
  - _Requirements: 11.1, 11.2, 11.3, 11.4_

- [ ]* 2.4 Write property test for world multiplier application
  - **Property 18: World multiplier application**
  - **Validates: Requirements 11.1, 11.3**

- [ ]* 2.5 Write property test for world cost multiplier application
  - **Property 19: World cost multiplier application**
  - **Validates: Requirements 11.2, 11.4**

- [x] 2.6 Implement checkSeasonalWindow() method
  - Query seasonal_units table for unit
  - Compare current timestamp with start/end timestamps
  - Return availability status with window details
  - _Requirements: 10.1, 10.2, 10.4_

- [ ]* 2.7 Write property test for seasonal window enforcement
  - **Property 17: Seasonal window enforcement**
  - **Validates: Requirements 10.1, 10.2, 10.4**

- [x] 2.8 Implement checkEliteUnitCap() method
  - Query elite_unit_caps table for user
  - Count existing units across all villages
  - Compare against per-account cap
  - Return cap status
  - _Requirements: 9.2_

- [x] 2.9 Extend checkRecruitRequirements() to include seasonal and elite checks
  - Add seasonal window validation
  - Add elite unit cap validation
  - Return detailed error codes (ERR_SEASONAL_EXPIRED, ERR_CAP)
  - _Requirements: 10.4, 9.2, 15.4, 15.5_

- [ ]* 2.10 Write property test for unit unlock prerequisites
  - **Property 1: Unit unlock prerequisites**
  - **Validates: Requirements 1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 4.1, 4.2, 5.1, 5.2, 6.1, 6.2, 7.1, 7.2, 8.1, 8.2, 14.1, 15.1, 15.2**

- [x] 2.11 Extend recruitUnits() to handle conquest unit resource deduction
  - Check for Noble/Standard Bearer unit types
  - Verify coin/standard availability
  - Deduct coins/standards atomically in transaction
  - Return ERR_RES if insufficient
  - _Requirements: 7.1, 7.2, 15.3_

- [ ]* 2.12 Write property test for conquest resource deduction
  - **Property 23: Conquest resource deduction**
  - **Validates: Requirements 15.3, 16.4**

- [-] 3. Implement unit cap enforcement
- [x] 3.1 Implement getVillageUnitCountWithQueue() helper (already exists, verify it works)
  - Count existing units in village_units
  - Count queued units in unit_queue
  - Return total count
  - _Requirements: 9.5_

- [x] 3.2 Add per-village siege cap check to recruitUnits()
  - Count total siege units (rams, catapults) with queue
  - Compare against SIEGE_CAP_PER_VILLAGE constant
  - Reject if exceeded with ERR_CAP
  - _Requirements: 9.1_

- [-] 3.3 Add per-account elite cap check to recruitUnits()
  - Call checkEliteUnitCap() for elite units
  - Reject if exceeded with ERR_CAP
  - _Requirements: 9.2_

- [ ] 3.4 Add per-command conquest cap validation
  - Verify conquest units per command ≤ MAX_LOYALTY_UNITS_PER_COMMAND
  - Reject if exceeded with ERR_CAP
  - _Requirements: 9.3_

- [ ]* 3.5 Write property test for unit cap enforcement
  - **Property 16: Unit cap enforcement**
  - **Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5, 16.3**

- [ ]* 3.6 Write property test for concurrent cap enforcement
  - **Property 28: Concurrent cap enforcement**
  - **Validates: Requirements 17.4**

- [ ] 4. Implement validation and error handling
- [ ] 4.1 Add input validation to recruit.php
  - Validate count is positive integer
  - Validate unit_id exists
  - Return ERR_INPUT for invalid inputs
  - _Requirements: 17.1_

- [ ]* 4.2 Write property test for input validation
  - **Property 25: Input validation**
  - **Validates: Requirements 17.1**

- [ ] 4.3 Add population capacity validation to recruitUnits()
  - Calculate total population (current + queued + requested)
  - Compare against farm capacity
  - Return ERR_POP if exceeded
  - _Requirements: 17.2_

- [ ]* 4.4 Write property test for population capacity enforcement
  - **Property 26: Population capacity enforcement**
  - **Validates: Requirements 17.2**

- [ ] 4.5 Add resource availability validation to recruitUnits()
  - Check wood, clay, iron availability
  - Return ERR_RES with missing amounts if insufficient
  - _Requirements: 17.3_

- [ ]* 4.6 Write property test for resource availability enforcement
  - **Property 27: Resource availability enforcement**
  - **Validates: Requirements 17.3**

- [ ] 4.7 Add feature flag validation to recruit.php
  - Check world feature flags for conquest/seasonal/healer units
  - Return ERR_FEATURE_DISABLED if disabled
  - _Requirements: 15.5_

- [ ]* 4.8 Write property test for feature flag enforcement
  - **Property 24: Feature flag enforcement**
  - **Validates: Requirements 15.5**

- [ ] 5. Implement telemetry and logging
- [ ] 5.1 Extend logRecruitTelemetry() in recruit.php
  - Log all training requests (success and failure)
  - Include unit type, count, world ID, player ID, outcome, error code
  - Write to logs/recruit_telemetry.log
  - _Requirements: 17.5, 18.1_

- [ ]* 5.2 Write property test for failure logging
  - **Property 29: Failure logging**
  - **Validates: Requirements 17.5**

- [ ]* 5.3 Write property test for telemetry emission
  - **Property 30: Telemetry emission**
  - **Validates: Requirements 18.1, 18.2, 18.3**

- [ ] 5.4 Add cap hit counter incrementation
  - Increment counter when ERR_CAP is returned
  - Track by unit type and world
  - _Requirements: 18.2_

- [ ] 5.5 Add error counter incrementation
  - Increment counter for each error code
  - Track by reason code
  - _Requirements: 18.3_

- [ ] 6. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Implement RPS combat modifiers in BattleManager
- [ ] 7.1 Create applyRPSModifiers() method
  - Load unit categories for attacker and defender
  - Apply cavalry vs ranged bonus in open field
  - Apply pike vs cavalry bonus
  - Apply ranger vs siege bonus
  - Return modified attack/defense values
  - _Requirements: 1.4, 3.3, 3.4, 8.4_

- [ ]* 7.2 Write property test for RPS defense bonuses
  - **Property 2: RPS defense bonuses**
  - **Validates: Requirements 1.4, 3.4**

- [ ]* 7.3 Write property test for ranger anti-siege bonus
  - **Property 15: Ranger anti-siege bonus**
  - **Validates: Requirements 8.4**

- [ ] 7.4 Integrate applyRPSModifiers() into resolveBattle()
  - Call after loading unit compositions
  - Apply before casualty calculation
  - Include modifiers in battle report
  - _Requirements: 1.4, 3.3, 3.4, 8.4_

- [ ] 7.5 Add ranged wall bonus calculation
  - Check if defender has ranged units and wall > 0
  - Apply bonus defense multiplier against infantry
  - Reduce effectiveness in open field (wall = 0)
  - _Requirements: 2.3, 2.4_

- [ ]* 7.6 Write property test for ranged wall bonus
  - **Property 4: Ranged wall bonus**
  - **Validates: Requirements 2.3, 2.4**

- [ ] 8. Implement support unit mechanics in BattleManager
- [ ] 8.1 Create calculateBannerAura() method
  - Scan defender units for Banner Guards
  - Identify highest aura tier present
  - Return aura multiplier and resolve bonus
  - Ensure no stacking (only highest applies)
  - _Requirements: 6.3, 6.5_

- [ ]* 8.2 Write property test for banner aura application
  - **Property 10: Banner aura application**
  - **Validates: Requirements 6.3, 6.5**

- [ ] 8.3 Integrate calculateBannerAura() into resolveBattle()
  - Call before casualty calculation
  - Apply aura multiplier to defender defense
  - Include aura details in battle report
  - _Requirements: 6.3, 6.5_

- [ ] 8.4 Create calculateMantletProtection() method
  - Scan attacker units for Mantlets
  - Calculate ranged damage reduction percentage
  - Return protection multiplier (0.0 to 1.0)
  - Remove protection if mantlets are killed
  - _Requirements: 14.2, 14.3, 14.4_

- [ ]* 8.5 Write property test for mantlet protection
  - **Property 22: Mantlet protection application**
  - **Validates: Requirements 14.2, 14.3, 14.4**

- [ ] 8.6 Integrate calculateMantletProtection() into resolveBattle()
  - Apply before distributing casualties to siege units
  - Reduce ranged damage to siege by protection percentage
  - Include mantlet effect in battle report
  - _Requirements: 14.2, 14.3, 14.4, 14.5_

- [ ] 8.7 Create applyHealerRecovery() method
  - Scan survivor units for War Healers
  - Calculate recovery amount based on healer count and losses
  - Cap recovery at world healer_recovery_cap percentage
  - Return recovered units by type
  - _Requirements: 6.4_

- [ ]* 8.8 Write property test for healer recovery cap
  - **Property 11: Healer recovery cap**
  - **Validates: Requirements 6.4**

- [ ] 8.9 Integrate applyHealerRecovery() into resolveBattle()
  - Call after casualty calculation
  - Add recovered units back to survivor counts
  - Include recovery details in battle report
  - _Requirements: 6.4_

- [ ] 9. Implement conquest mechanics in BattleManager
- [ ] 9.1 Create processConquestAllegiance() method
  - Check if attacker won the battle
  - Count surviving Noble/Standard Bearer units
  - Calculate allegiance reduction (configured amount per unit)
  - Update village allegiance in database
  - Check if allegiance ≤ 0 for capture
  - Return allegiance change and capture status
  - _Requirements: 7.3, 7.4, 7.5_

- [ ]* 9.2 Write property test for conquest allegiance reduction
  - **Property 12: Conquest allegiance reduction**
  - **Validates: Requirements 7.3**

- [ ]* 9.3 Write property test for village capture on zero allegiance
  - **Property 13: Village capture on zero allegiance**
  - **Validates: Requirements 7.4**

- [ ]* 9.4 Write property test for conquest requires victory
  - **Property 14: Conquest requires victory**
  - **Validates: Requirements 7.5**

- [ ] 9.2 Integrate processConquestAllegiance() into resolveBattle()
  - Call after casualty calculation if attacker won
  - Transfer village ownership if captured
  - Include allegiance change in battle report
  - Log conquest attempts to conquest_attempts.log
  - _Requirements: 7.3, 7.4, 7.5_

- [ ] 10. Implement scout intel mechanics in BattleManager
- [ ] 10.1 Extend scout combat resolution
  - Compare attacking scouts vs defending scouts
  - Kill attacking scouts if outnumbered
  - Prevent intel revelation if scouts die
  - _Requirements: 4.5_

- [ ]* 10.2 Write property test for scout combat resolution
  - **Property 7: Scout combat resolution**
  - **Validates: Requirements 4.5**

- [ ] 10.3 Extend battle report generation for scout intel
  - Include troop counts and resources for Pathfinder survivors
  - Include building levels and queues for Shadow Rider survivors
  - Redact intel if scouts die
  - _Requirements: 4.3, 4.4_

- [ ]* 10.4 Write property test for scout intel revelation
  - **Property 6: Scout intel revelation**
  - **Validates: Requirements 4.3, 4.4**

- [ ] 11. Implement siege mechanics in BattleManager
- [ ] 11.1 Extend wall reduction logic for Battering Rams
  - Count surviving rams after battle
  - Calculate wall level reduction based on ram count
  - Update village wall level in database
  - Include wall change in battle report
  - _Requirements: 5.3_

- [ ]* 11.2 Write property test for siege wall reduction
  - **Property 8: Siege wall reduction**
  - **Validates: Requirements 5.3**

- [ ] 11.3 Extend building damage logic for Stone Hurlers
  - Count surviving catapults after battle
  - Damage targeted building or select random if none specified
  - Update building level in database
  - Include building damage in battle report
  - _Requirements: 5.4_

- [ ]* 11.4 Write property test for catapult building damage
  - **Property 9: Catapult building damage**
  - **Validates: Requirements 5.4**

- [ ] 12. Implement UI and display logic
- [ ] 12.1 Update recruitment panel to show unit details
  - Display attack, defense values by type, speed, carry, population
  - Display resource costs, training time, prerequisites
  - Display RPS matchup information (strengths/weaknesses)
  - Display special abilities (aura, siege, conquest)
  - _Requirements: 12.1, 12.2, 12.3, 12.4_

- [ ] 12.2 Update recruitment panel to show effective values
  - Apply world multipliers to displayed costs and times
  - Add notation indicating world modifications
  - _Requirements: 11.5, 12.5_

- [ ] 12.3 Update battle reports to include new modifiers
  - Display RPS modifiers that were applied
  - Display aura effects and tier
  - Display mantlet protection percentage
  - Display healer recovery amounts
  - Display allegiance changes and conquest outcomes
  - _Requirements: 14.5_

- [ ] 13. Implement seasonal unit lifecycle management
- [ ] 13.1 Create seasonal unit sunset job
  - Query seasonal_units for expired events
  - Disable training for expired units
  - Convert existing units to resources or disable based on world config
  - Log sunset events
  - _Requirements: 10.3_

- [ ] 13.2 Create seasonal unit activation job
  - Query seasonal_units for newly active events
  - Enable training for active units
  - Log activation events
  - _Requirements: 10.1_

- [ ] 14. Implement data validation and diff tooling
- [ ] 14.1 Create unit data validation script
  - Validate all required fields are present and positive
  - Validate RPS relationships (pike def_cav > pike def_inf, etc.)
  - Validate world overrides maintain balance constraints
  - Output validation errors
  - _Requirements: 13.2, 13.3, 13.5_

- [ ]* 14.2 Write property test for RPS relationship validation
  - **Property 21: RPS relationship validation**
  - **Validates: Requirements 13.2, 13.5**

- [ ] 14.3 Create unit data diff generator
  - Compare current units.json with previous version
  - Generate human-readable diff showing stat changes
  - Output diff for changelog documentation
  - _Requirements: 13.4_

- [ ] 15. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 16. Create configuration files and documentation
- [ ] 16.1 Create config/rps_modifiers.php
  - Define RPS multiplier constants (CAV_VS_RANGED_MULT, PIKE_VS_CAV_MULT, etc.)
  - Define context rules (wall level, terrain)
  - Document each modifier

- [ ] 16.2 Create config/unit_caps.php
  - Define default cap values per unit category
  - Define per-village, per-account, per-command caps
  - Document cap rationale

- [ ] 16.3 Update README with unit system documentation
  - Document unit categories and roles
  - Document RPS mechanics
  - Document support unit mechanics
  - Document conquest mechanics
  - Document seasonal unit lifecycle

