# Implementation Plan

## Status: Ready for Implementation

This task list bridges the gap between the current BattleEngine/BattleManager implementation and the comprehensive battle resolution system specified in the design document.

**Current State Analysis:**
- ✅ Core BattleEngine exists with basic combat mechanics
- ✅ Unit class system (infantry/cavalry/archer) implemented
- ✅ Wall, morale, luck, and night bonus modifiers working
- ✅ Ram and catapult siege mechanics functional
- ✅ Basic casualty calculations with square-root mechanic
- ⚠️ BattleManager has extensive validation but lacks formal structure
- ❌ No command processor with deterministic sorting
- ❌ No plunder calculator component
- ❌ No conquest handler component
- ❌ No formal report generator
- ❌ No rate limiting infrastructure
- ❌ No overstack penalty system
- ❌ No property-based tests
- ❌ Database schema doesn't match design spec

---

- [ ] 1. Refactor and enhance core battle resolution components
  - Extract stateless calculation logic from BattleEngine
  - Implement missing modifiers (overstack, terrain, weather)
  - Add comprehensive input validation
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 3.1, 3.2, 3.3, 7.1, 7.2, 7.3, 8.1, 8.2, 8.3_

- [ ] 1.1 Create ModifierApplier component
  - Implement calculateMorale() with proper clamping
  - Implement generateLuck() with configurable bounds
  - Implement calculateWallMultiplier() with two-tier formula
  - Implement calculateOverstackPenalty() with threshold logic
  - Implement applyEnvironmentModifiers() for night/terrain/weather
  - Ensure modifiers are applied in correct order: overstack → wall → environment → morale → luck
  - _Requirements: 3.1, 3.2, 3.3, 7.1, 7.2, 7.3, 7.4, 8.1, 8.2, 8.3_

- [ ]* 1.2 Write property test for ModifierApplier
  - **Property 10: Morale Calculation**
  - **Property 11: Luck Bounds**
  - **Property 12: Modifier Application Order**
  - **Property 13: Night Bonus Application**
  - **Property 17: Overstack Penalty Formula**
  - **Property 18: Overstack Modifier Ordering**
  - **Validates: Requirements 3.1, 3.2, 3.3, 7.1, 8.1, 8.2, 8.3**

- [ ] 1.3 Enhance CombatCalculator component
  - Refactor calculateOffensivePower() to use class multipliers
  - Refactor calculateDefensivePower() with weighted defense
  - Implement calculateCasualties() with ratio^1.5 formula
  - Implement determineWinner() based on ratio threshold
  - Add unit conservation validation
  - _Requirements: 1.2, 1.3, 1.4_

- [ ]* 1.4 Write property tests for CombatCalculator
  - **Property 1: Force Merging Completeness**
  - **Property 2: Power Calculation Correctness**
  - **Property 3: Casualty Proportionality**
  - **Property 4: Unit Conservation**
  - **Validates: Requirements 1.1, 1.2, 1.3, 1.4**

- [ ] 2. Implement PlunderCalculator component
  - Create PlunderCalculator class with stateless methods
  - Implement calculateAvailableLoot() with vault protection
  - Implement calculateCarryCapacity() excluding siege/conquest units
  - Implement distributePlunder() with deterministic distribution
  - Add validation for negative values and edge cases
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [ ]* 2.1 Write property tests for PlunderCalculator
  - **Property 19: Vault Protection**
  - **Property 20: Carry Capacity Limit**
  - **Property 21: Plunder Determinism**
  - **Property 22: Siege Unit Carry Capacity**
  - **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5**

- [ ] 3. Implement ConquestHandler component
  - Create ConquestHandler class for allegiance management
  - Implement reduceAllegiance() with per-unit drop calculation
  - Implement checkCaptureConditions() for ownership transfer
  - Implement transferOwnership() with database updates
  - Implement applyPostCaptureAllegiance() with floor setting
  - Add conquest cooldown enforcement
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [ ]* 3.1 Write property tests for ConquestHandler
  - **Property 23: Allegiance Reduction on Victory**
  - **Property 24: Ownership Transfer Threshold**
  - **Property 25: Post-Capture Allegiance Floor**
  - **Property 26: No Allegiance Drop on Loss**
  - **Property 27: Conquest Cooldown Enforcement**
  - **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5**

- [ ] 4. Implement SiegeHandler component
  - Create SiegeHandler class for siege mechanics
  - Implement applyRamDamage() with formula: ramsPerLevel = max(1, ceil((2 + wallLevel × 0.5) / worldSpeed))
  - Implement applyCatapultDamage() with formula: catapultsPerLevel = max(1, ceil((8 + buildingLevel × 2) / worldSpeed))
  - Implement selectRandomBuilding() for untargeted catapults
  - Add validation for negative levels and zero siege units
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ]* 4.1 Write property tests for SiegeHandler
  - **Property 6: Wall Multiplier Application**
  - **Property 7: Ram Damage Determinism**
  - **Property 8: Catapult Damage on Victory**
  - **Property 9: Wall Persistence**
  - **Validates: Requirements 2.1, 2.2, 2.3, 2.5**

- [ ] 5. Implement ReportGenerator component
  - Create ReportGenerator class for battle reports
  - Implement generateReport() with complete battle data
  - Implement includeIntelligence() for scout survival
  - Implement redactIntelligence() for scout death
  - Ensure reports include all modifiers, troops, siege, plunder, conquest data
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8_

- [ ]* 5.1 Write property tests for ReportGenerator
  - **Property 28: Report Generation**
  - **Property 29: Report Troop Completeness**
  - **Property 30: Report Modifier Completeness**
  - **Property 31: Report Siege Tracking**
  - **Property 32: Report Plunder Tracking**
  - **Property 33: Report Conquest Tracking**
  - **Property 34: Scout Intelligence Inclusion**
  - **Property 35: Scout Intelligence Redaction**
  - **Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8**

- [ ] 6. Implement CommandProcessor component
  - Create CommandProcessor class for command validation and sorting
  - Implement validateCommand() with comprehensive checks
  - Implement sortCommands() with deterministic ordering: arrival → sequence → type → ID
  - Implement enforceRateLimits() with sliding window
  - Implement checkMinimumPopulation() with configurable threshold
  - Add fake attack detection and tagging
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 10.1, 10.2, 10.3, 10.4, 10.5_

- [ ]* 6.1 Write property tests for CommandProcessor
  - **Property 36: Command Sorting Determinism**
  - **Property 37: Support Timing Inclusion**
  - **Property 38: Sequential Processing Order**
  - **Property 39: Battle Determinism**
  - **Property 40: Command Spacing Enforcement**
  - **Property 41: Per-Player Rate Limit**
  - **Property 42: Per-Target Rate Limit**
  - **Property 43: Rate Limit Error Response**
  - **Property 44: Minimum Population Enforcement**
  - **Property 45: Fake Attack Tagging**
  - **Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5, 10.1, 10.2, 10.3, 10.4, 10.5**

- [ ] 7. Implement edge case handling and error codes
  - Define BattleErrorCode constants (ERR_PROTECTED, ERR_VALIDATION, etc.)
  - Implement shield protection checks
  - Implement input validation with error codes
  - Implement modifier clamping to valid ranges
  - Add graceful error handling with safe defaults
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [ ]* 7.1 Write property tests for edge cases
  - **Property 46: Shield Protection**
  - **Property 47: Input Validation**
  - **Property 48: Modifier Clamping**
  - **Property 49: Offline Defender Handling**
  - **Validates: Requirements 11.1, 11.2, 11.3, 11.5**

- [ ] 8. Checkpoint - Ensure all component tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 9. Create database migration for battle resolution schema
  - Create commands table with proper indexes
  - Create battle_reports table with all required fields
  - Create battle_metrics table for telemetry
  - Create rate_limit_tracking table
  - Add indexes for performance (arrival_at, recipient_id, battle_id)
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [ ] 10. Implement BattleResolverCore orchestrator
  - Create BattleResolverCore class that coordinates all components
  - Implement resolveBattle() method using all components
  - Implement resolveBattles() for batch processing
  - Add correlation ID generation and tracking
  - Integrate with persistence layer
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [ ]* 10.1 Write integration property tests for BattleResolverCore
  - **Property 5: Post-Battle Unit Movement**
  - Test full battle flow with all components
  - Verify determinism across multiple executions
  - **Validates: Requirements 1.5**

- [ ] 11. Implement telemetry and logging
  - Add metrics emission for resolver latency
  - Add metrics for battle outcomes and modifiers
  - Implement correlation ID logging
  - Add error context logging with command/player IDs
  - Add rate limit counter increments
  - Add timing data recording
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [ ]* 11.1 Write property tests for telemetry
  - **Property 50: Metrics Emission**
  - **Property 51: Correlation ID Logging**
  - **Property 52: Error Context Logging**
  - **Property 53: Rate Limit Counter Increment**
  - **Property 54: Timing Data Recording**
  - **Validates: Requirements 12.1, 12.2, 12.3, 12.4, 12.5**

- [ ] 12. Integrate with existing BattleManager
  - Update BattleManager to use new BattleResolverCore
  - Migrate existing battle processing to new system
  - Maintain backward compatibility during transition
  - Add feature flag for gradual rollout
  - _Requirements: All_

- [ ] 13. Create API endpoints for battle resolution
  - Implement queueCommand() endpoint
  - Implement getCommandsForTick() endpoint
  - Implement checkRateLimits() endpoint
  - Add proper error responses with codes
  - Add request validation
  - _Requirements: 9.1, 10.1, 10.2, 10.3_

- [ ] 14. Update CombatTickProcessor
  - Integrate with new CommandProcessor for sorting
  - Use BattleResolverCore for resolution
  - Add batch processing optimization
  - Add error recovery and rollback
  - _Requirements: 9.1, 9.2, 9.3_

- [ ] 15. Final checkpoint - Integration testing
  - Run full battle scenarios end-to-end
  - Verify all property tests pass
  - Test rate limiting under load
  - Verify determinism across servers
  - Test error handling and recovery
  - Ensure all tests pass, ask the user if questions arise.

---

## Notes

**Property-Based Testing Setup:**
- Framework: PHPUnit with Eris library
- Each property test must run minimum 100 iterations
- Tag format: `/** Feature: battle-resolution, Property X: [description] */`
- Each correctness property from design.md must have exactly one PBT test

**Testing Philosophy:**
- Unit tests verify specific examples and edge cases
- Property tests verify universal correctness properties
- Both are complementary and essential for correctness

**Implementation Order:**
- Components are designed to be stateless and testable in isolation
- Build and test each component before integration
- Checkpoints ensure stability before proceeding

**Optional Tasks:**
- Tasks marked with `*` are optional (primarily tests)
- Core implementation tasks are never optional
- Property-based tests are highly recommended for correctness validation
