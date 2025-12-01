# Implementation Plan

- [x] 1. Validate and enhance building configuration schema
- [x] 1.1 Review building_types table schema and add missing columns
  - Verify all required columns exist (cost_factor, production_type, population_cost, etc.)
  - Add any missing columns with appropriate defaults
  - _Requirements: 1.1, 14.1, 18.1_

- [x] 1.2 Populate building_types table with all 20+ building definitions
  - Define all building types with costs, times, production rates, and prerequisites
  - Set appropriate max_level caps for each building type
  - Configure cost_factor and time_factor for balanced progression
  - _Requirements: 1.1, 4.1, 4.2, 4.3, 14.1_

- [x] 1.3 Populate building_requirements table with prerequisite chains
  - Define Town Hall prerequisites for military buildings (Barracks at TH3, Stable at TH5, etc.)
  - Define building-to-building prerequisites (Market requires Storage, etc.)
  - Validate no circular dependencies exist
  - _Requirements: 1.1, 3.2, 3.3, 3.4, 14.2_

- [ ]* 1.4 Write property test for prerequisite validation
  - **Property 1: Prerequisite Validation Completeness**
  - **Validates: Requirements 1.1, 14.2**
  - Generate random village states with various building levels
  - Verify prerequisite validation correctly identifies missing requirements
  - Test with circular dependency detection

- [-] 2. Implement core building upgrade validation
- [x] 2.1 Enhance BuildingManager::canUpgradeBuilding validation
  - Implement complete validation chain (input, protection, caps, prerequisites, queue, resources, population, storage)
  - Return appropriate error codes for each validation failure
  - Add user ID parameter for First Church uniqueness check
  - _Requirements: 1.1, 1.2, 1.3, 14.1, 14.2, 14.3, 14.4, 19.1, 19.2, 19.3_

- [ ]* 2.2 Write property test for resource validation and deduction
  - **Property 2: Resource Deduction Atomicity**
  - **Validates: Requirements 1.2, 19.3**
  - Generate random resource states and building costs
  - Verify resources are validated correctly and deducted atomically
  - Test rollback on validation failures

- [ ]* 2.3 Write property test for population capacity enforcement
  - **Property 3: Population Capacity Enforcement**
  - **Validates: Requirements 1.3, 19.2**
  - Generate random farm levels and building population costs
  - Verify validation correctly identifies insufficient capacity
  - Test error message includes current and required values

- [ ]* 2.4 Write property test for maximum level cap enforcement
  - **Property 32: Maximum Level Cap Enforcement**
  - **Validates: Requirements 14.1**
  - Generate random buildings at max level
  - Verify upgrade attempts are rejected with ERR_CAP

- [ ] 3. Implement building cost and time calculations
- [ ] 3.1 Enhance BuildingConfigManager cost calculation
  - Implement exponential cost formula with cost_factor
  - Add cost_factor clamping (1.01 to 1.6)
  - Implement cost caching per building/level
  - Apply world archetype multipliers
  - _Requirements: 1.2, 14.5, 15.2, 15.4_

- [ ] 3.2 Enhance BuildingConfigManager time calculation
  - Implement exponential time formula with time_factor
  - Apply Town Hall time reduction (2% per level)
  - Apply world speed and build speed multipliers
  - Implement tier floors for mid/late game builds
  - Implement time caching per building/level/hq/world
  - _Requirements: 1.4, 3.1, 15.1, 15.3_

- [ ]* 3.3 Write property test for build time calculation
  - **Property 4: Build Time Calculation Consistency**
  - **Validates: Requirements 1.4, 15.3**
  - Generate random building types, levels, TH levels, and world configs
  - Verify calculated time is deterministic and applies all multipliers correctly
  - Test complete formula: (base_time × level_factor^level) / (world_speed × build_speed × hq_bonus)

- [ ]* 3.4 Write property test for Town Hall time reduction
  - **Property 10: Town Hall Time Reduction**
  - **Validates: Requirements 3.1**
  - Generate random TH levels (0-20)
  - Verify time reduction multiplier is applied correctly (2% per level)

- [ ]* 3.5 Write property test for world multiplier application
  - **Property 35: World Multiplier Application**
  - **Validates: Requirements 14.5, 15.1, 15.2, 15.4**
  - Generate random world archetype settings
  - Verify multipliers are applied to base costs and times consistently

- [ ] 4. Implement queue management enhancements
- [ ] 4.1 Enhance BuildingQueueManager::enqueueBuild
  - Implement queue slot limit calculation based on TH milestones
  - Add validation for queue capacity (ERR_QUEUE_FULL vs ERR_QUEUE_CAP)
  - Implement parallel queue rules (resource + military) when enabled
  - Add protection mode military building blocking
  - Improve error messages with current/required values
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 14.4_

- [ ] 4.2 Implement queue rebalancing logic
  - Ensure pending builds are resequenced after active completes
  - Eliminate timing gaps when builds are cancelled
  - Promote next pending to active atomically
  - _Requirements: 2.5_

- [ ]* 4.3 Write property test for queue slot unlocking
  - **Property 6: Queue Slot Milestone Unlocking**
  - **Validates: Requirements 2.2, 3.5**
  - Generate random TH levels
  - Verify slot count equals base + floor((TH-1) / milestone_step)

- [ ]* 4.4 Write property test for queue capacity rejection
  - **Property 7: Queue Capacity Rejection**
  - **Validates: Requirements 2.3**
  - Generate random queue states at capacity
  - Verify additional builds are rejected with appropriate error code

- [ ]* 4.5 Write property test for parallel queue rules
  - **Property 8: Parallel Queue Rules**
  - **Validates: Requirements 2.4**
  - Generate random building combinations with parallel enabled
  - Verify at most one resource and one military build are allowed

- [ ]* 4.6 Write property test for cancellation refunds
  - **Property 9: Cancellation Refund Accuracy**
  - **Validates: Requirements 2.5**
  - Generate random building costs
  - Verify refund equals exactly 90% of original cost, rounded down

- [ ]* 4.7 Write property test for concurrent queue atomicity
  - **Property 38: Concurrent Queue Atomicity**
  - **Validates: Requirements 19.4**
  - Simulate concurrent queue requests
  - Verify only valid requests succeed and limits are enforced atomically

- [ ] 5. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 6. Implement resource production calculations
- [ ] 6.1 Enhance BuildingConfigManager::calculateProduction
  - Implement production formula: base × growth^(level-1) × world_speed × build_speed
  - Use base=30 for wood/clay, base=25 for iron, growth=1.163
  - Apply world speed and build speed multipliers
  - _Requirements: 4.1, 4.2, 4.3_

- [ ] 6.2 Implement resource accumulation with capacity capping
  - Create resource production tick processor
  - Cap resources at storage capacity
  - Log capacity warnings when resources are capped
  - _Requirements: 4.4, 4.5_

- [ ]* 6.3 Write property test for resource production scaling
  - **Property 11: Resource Production Scaling**
  - **Validates: Requirements 4.1, 4.2, 4.3**
  - Generate random resource building levels
  - Verify production follows formula: base × growth^(level-1) × world_speed × build_speed

- [ ]* 6.4 Write property test for storage capacity capping
  - **Property 12: Storage Capacity Capping**
  - **Validates: Requirements 4.4, 4.5**
  - Generate random production rates and storage capacities
  - Verify resources are capped at exactly storage capacity

- [ ] 7. Implement storage and vault calculations
- [ ] 7.1 Enhance BuildingConfigManager::calculateWarehouseCapacity
  - Implement capacity formula: 1000 × 1.229^level
  - Apply to both Storage and Warehouse buildings
  - _Requirements: 5.1, 5.2_

- [ ] 7.2 Implement vault protection calculation
  - Add calculateVaultProtection method to BuildingConfigManager
  - Implement protection percentage by vault level
  - Integrate with plunder calculation in battle system
  - _Requirements: 5.3, 5.4_

- [ ] 7.3 Add storage capacity prerequisite validation
  - Check if building cost exceeds storage capacity
  - Reject with ERR_STORAGE_CAP if exceeded
  - _Requirements: 5.5_

- [ ]* 7.4 Write property test for storage capacity scaling
  - **Property 13: Storage Capacity Scaling**
  - **Validates: Requirements 5.1, 5.2**
  - Generate random storage/warehouse levels
  - Verify capacity follows formula: 1000 × 1.229^level

- [ ]* 7.5 Write property test for vault protection
  - **Property 14: Vault Protection Calculation**
  - **Validates: Requirements 5.3, 5.4**
  - Generate random vault levels and resource amounts
  - Verify protected amount calculation and plunder subtraction

- [ ]* 7.6 Write property test for storage capacity prerequisite
  - **Property 15: Storage Capacity Prerequisite**
  - **Validates: Requirements 5.5**
  - Generate random building costs exceeding storage
  - Verify rejection with ERR_STORAGE_CAP

- [ ] 8. Implement wall mechanics
- [ ] 8.1 Enhance BuildingManager::getWallDefenseBonus
  - Verify formula: 1 + (0.08 × wall_level)
  - Integrate with battle resolver
  - _Requirements: 7.1_

- [ ] 8.2 Implement wall damage from siege attacks
  - Add applyWallDamage method to BuildingManager
  - Calculate damage based on surviving ram count
  - Apply minimum 0.25 level reduction per wave
  - Persist wall level changes
  - _Requirements: 7.2_

- [ ] 8.3 Implement wall repair queueing
  - Add wall repair to building queue system
  - Calculate repair costs and time
  - Restore wall level on completion
  - _Requirements: 7.3_

- [ ] 8.4 Implement repair blocking with incoming attacks
  - Check for hostile commands within repair block window
  - Reject repair attempts with ERR_REPAIR_BLOCKED
  - _Requirements: 7.4_

- [ ] 8.5 Enhance wall decay implementation
  - Verify applyWallDecayIfNeeded logic
  - Check inactivity threshold (72 hours)
  - Check decay interval (24 hours)
  - Reduce wall by 1 level per interval
  - Log decay events
  - _Requirements: 7.5_

- [ ]* 8.6 Write property test for wall defense multiplier
  - **Property 17: Wall Defense Multiplier**
  - **Validates: Requirements 7.1**
  - Generate random wall levels
  - Verify multiplier equals 1 + (0.08 × wall_level)

- [ ]* 8.7 Write property test for wall damage
  - **Property 18: Wall Damage from Siege**
  - **Validates: Requirements 7.2**
  - Generate random ram counts and wall levels
  - Verify wall reduction with minimum 0.25 levels per wave

- [ ]* 8.8 Write property test for wall repair
  - **Property 19: Wall Repair Queue Processing**
  - **Validates: Requirements 7.3**
  - Generate random wall damage states
  - Verify repair restores level and consumes resources

- [ ]* 8.9 Write property test for repair blocking
  - **Property 20: Repair Blocking with Incoming Attacks**
  - **Validates: Requirements 7.4**
  - Generate random incoming command ETAs
  - Verify repair is blocked when ETA < repair_block_window

- [ ]* 8.10 Write property test for wall decay
  - **Property 21: Wall Decay Application**
  - **Validates: Requirements 7.5**
  - Generate random inactivity periods
  - Verify decay applies after threshold with correct interval

- [ ] 9. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 10. Implement watchtower detection system
- [ ] 10.1 Add watchtower detection radius calculation
  - Add calculateDetectionRadius method to BuildingConfigManager
  - Implement level-to-radius curve
  - _Requirements: 8.1, 8.5_

- [ ] 10.2 Integrate watchtower detection with command system
  - Check distance between command and village
  - Create warning notification when command enters radius
  - Include command type and ETA in warning
  - _Requirements: 8.2_

- [ ] 10.3 Implement noble detection flagging
  - Check if command contains conquest units
  - Flag command with noble indicator when detected
  - Apply detection probability modifiers
  - _Requirements: 8.3_

- [ ] 10.4 Implement detection probability modifiers
  - Apply Scout Hall level modifier
  - Apply terrain type modifier
  - Apply weather condition modifier
  - _Requirements: 8.4_

- [ ]* 10.5 Write property test for watchtower detection radius
  - **Property 22: Watchtower Detection Radius**
  - **Validates: Requirements 8.1, 8.5**
  - Generate random watchtower levels
  - Verify detection radius follows configured curve

- [ ]* 10.6 Write property test for noble detection flagging
  - **Property 23: Noble Detection Flagging**
  - **Validates: Requirements 8.3**
  - Generate random commands with/without nobles
  - Verify noble flag is set when nobles present and within range

- [ ]* 10.7 Write property test for detection probability modifiers
  - **Property 24: Detection Probability Modifiers**
  - **Validates: Requirements 8.4**
  - Generate random modifier combinations
  - Verify probability calculation incorporates all modifiers

- [ ] 11. Implement hospital recovery system
- [ ] 11.1 Add hospital recovery rate calculation
  - Add calculateRecoveryRate method to BuildingConfigManager
  - Implement level-to-rate curve with maximum cap
  - _Requirements: 9.1, 9.2_

- [ ] 11.2 Integrate hospital recovery with battle resolver
  - Apply recovery after defensive battles
  - Add recovered troops to garrison
  - Deduct recovery costs from village resources
  - Include recovery count in battle reports
  - _Requirements: 9.3, 9.4_

- [ ] 11.3 Implement hospital feature flag handling
  - Check world configuration for hospital_enabled
  - Hide hospital construction when disabled
  - Skip recovery calculations when disabled
  - _Requirements: 9.5_

- [ ]* 11.4 Write property test for hospital recovery rate
  - **Property 25: Hospital Recovery Rate**
  - **Validates: Requirements 9.1, 9.2**
  - Generate random hospital levels
  - Verify recovery rate follows curve and respects maximum cap

- [ ]* 11.5 Write property test for hospital recovery application
  - **Property 26: Hospital Recovery Application**
  - **Validates: Requirements 9.3**
  - Generate random battle casualties
  - Verify recovered troops are added and costs are deducted

- [ ] 12. Implement market, hall of banners, and library mechanics
- [ ] 12.1 Add market caravan count calculation
  - Add calculateCaravanCount method to BuildingConfigManager
  - Implement level-to-count formula
  - _Requirements: 10.2_

- [ ] 12.2 Add market speed scaling calculation
  - Add calculateMerchantSpeed method to BuildingConfigManager
  - Implement level-to-speed curve
  - _Requirements: 10.3_

- [ ] 12.3 Implement Hall of Banners minting caps
  - Add daily minting limit tracking
  - Enforce per-village or per-account caps
  - Reject minting when cap reached
  - _Requirements: 11.4_

- [ ] 12.4 Add Hall of Banners time reduction calculation
  - Add calculateMintingTime method to BuildingConfigManager
  - Implement level-to-time curve
  - Potentially increase daily caps at higher levels
  - _Requirements: 11.5_

- [ ] 12.5 Add library research time scaling
  - Add calculateResearchTime method to BuildingConfigManager
  - Implement level-to-time curve
  - _Requirements: 12.4_

- [ ]* 12.6 Write property test for market caravan count
  - **Property 27: Market Caravan Count**
  - **Validates: Requirements 10.2**
  - Generate random market levels
  - Verify caravan count follows configured formula

- [ ]* 12.7 Write property test for market speed scaling
  - **Property 28: Market Speed Scaling**
  - **Validates: Requirements 10.3**
  - Generate random market levels
  - Verify merchant speed follows configured curve

- [ ]* 12.8 Write property test for minting caps
  - **Property 29: Hall of Banners Minting Caps**
  - **Validates: Requirements 11.4**
  - Generate random minting attempts
  - Verify cap enforcement per village or account

- [ ]* 12.9 Write property test for hall time reduction
  - **Property 30: Hall of Banners Time Reduction**
  - **Validates: Requirements 11.5**
  - Generate random hall levels
  - Verify minting time reduction and cap increases

- [ ]* 12.10 Write property test for library time scaling
  - **Property 31: Library Research Time Scaling**
  - **Validates: Requirements 12.4**
  - Generate random library levels
  - Verify research time follows configured curve

- [ ] 13. Implement additional validation and error handling
- [ ] 13.1 Add research prerequisite validation
  - Check if required research is completed
  - Reject with ERR_RESEARCH if not met
  - _Requirements: 14.3_

- [ ] 13.2 Implement protection mode military blocking
  - Check if village is under emergency shield
  - Block military building upgrades when configured
  - Allow other building upgrades
  - _Requirements: 14.4_

- [ ] 13.3 Add invalid building ID validation
  - Validate building ID exists in building_types
  - Reject with ERR_INPUT if invalid
  - _Requirements: 19.1_

- [ ]* 13.4 Write property test for research prerequisite validation
  - **Property 33: Research Prerequisite Validation**
  - **Validates: Requirements 14.3**
  - Generate random research states
  - Verify validation correctly identifies missing research

- [ ]* 13.5 Write property test for protection mode blocking
  - **Property 34: Protection Mode Military Blocking**
  - **Validates: Requirements 14.4**
  - Generate random protection states
  - Verify military buildings are blocked while others are allowed

- [ ]* 13.6 Write property test for invalid building ID rejection
  - **Property 37: Invalid Building ID Rejection**
  - **Validates: Requirements 19.1**
  - Generate invalid building IDs
  - Verify rejection with ERR_INPUT

- [ ] 14. Implement building completion and level updates
- [ ] 14.1 Enhance BuildingQueueManager::onBuildComplete
  - Verify idempotency (check status before applying)
  - Update building level in village_buildings
  - Apply new production rates or capacities
  - Mark queue item as completed
  - Promote next pending build to active
  - _Requirements: 1.5_

- [ ]* 14.2 Write property test for level increment on completion
  - **Property 5: Level Increment on Completion**
  - **Validates: Requirements 1.5**
  - Generate random completed builds
  - Verify level increments by exactly one and production updates

- [ ] 15. Implement UI endpoints and data formatting
- [ ] 15.1 Create building list endpoint
  - Return all buildings with current levels, costs, times, and prerequisites
  - Include queue status and ETA for upgrading buildings
  - Apply world multipliers to displayed costs and times
  - _Requirements: 16.1, 16.2, 16.3, 16.4_

- [ ] 15.2 Create building upgrade endpoint
  - Validate upgrade eligibility
  - Enqueue build with BuildingQueueManager
  - Return success/error with appropriate codes
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3_

- [ ] 15.3 Create building cancel endpoint
  - Validate ownership
  - Cancel build with BuildingQueueManager
  - Return refund amounts
  - _Requirements: 2.5_

- [ ] 15.4 Create queue status endpoint
  - Return current queue with all items
  - Include start times, finish times, and statuses
  - Calculate remaining time for active build
  - _Requirements: 2.1, 2.2, 2.3_

- [ ] 16. Implement cron job for queue processing
- [ ] 16.1 Create queue processor cron job
  - Query for completed builds (finish_time <= NOW())
  - Process each completed build with onBuildComplete
  - Log processing results
  - Handle errors gracefully
  - _Requirements: 1.5_

- [ ] 16.2 Add queue processing monitoring
  - Emit metrics for processing latency
  - Track completion success/failure rates
  - Alert on processing delays
  - _Requirements: 20.1, 20.2, 20.3, 20.4_

- [ ] 17. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 18. Documentation and deployment
- [ ] 18.1 Update API documentation
  - Document all building endpoints
  - Include request/response examples
  - Document error codes and meanings
  - _Requirements: 16.1, 16.2, 16.3, 16.4_

- [ ] 18.2 Create database migration scripts
  - Add last_wall_decay_at column to villages
  - Add status column to building_queue (if missing)
  - Add is_demolition and refund columns to building_queue
  - Create indexes on building_queue
  - _Requirements: 7.5_

- [ ] 18.3 Update world configuration documentation
  - Document all feature flags
  - Document world archetype multipliers
  - Document queue configuration constants
  - _Requirements: 14.5, 15.1, 15.2_

- [ ] 18.4 Create deployment checklist
  - Validate building configurations
  - Test queue processing cron job
  - Verify feature flags work correctly
  - Test rollback procedures
  - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5_

