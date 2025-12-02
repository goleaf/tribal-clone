# Implementation Plan

- [x] 1. Set up database schema and migrations
  - Create migration for allegiance/control columns on villages table
  - Create conquest_attempts audit log table
  - Add indexes for performance (village_id, last_allegiance_update, capture_cooldown_until)
  - Update unit configuration JSON with Envoy unit definition
  - _Requirements: 1.1, 2.1, 4.1, 5.1, 7.1_

- [x] 2. Implement world configuration system
  - Add conquest configuration fields to world config schema
  - Implement WorldConfigManager to load and cache conquest settings
  - Support both allegiance-drop and control-uptime modes
  - Add feature flags (FEATURE_CONQUEST_UNIT_ENABLED, etc.)
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [ ] 3. Implement AllegianceService core calculations
  - [ ] 3.1 Implement allegiance drop calculation with wall reduction
    - Calculate base drop with random band per Envoy
    - Apply wall level reduction factor
    - Apply world multipliers and modifiers
    - Clamp result to valid range [0, 100]
    - _Requirements: 2.1, 3.2_

  - [ ]* 3.2 Write property test for allegiance drop calculation
    - **Property 3: Control establishment on victory**
    - **Property 7: Wall impact on survival**
    - **Validates: Requirements 2.1, 2.5, 3.2**

  - [ ] 3.3 Implement regeneration tick calculation
    - Calculate time-based regeneration with elapsed seconds
    - Apply building and tech bonuses with multiplier cap
    - Implement pause logic for anti-snipe periods
    - Clamp to maximum value of 100
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

  - [ ]* 3.4 Write property test for regeneration calculation
    - **Property 11: Time-based regeneration**
    - **Property 12: Allegiance clamping**
    - **Property 13: Regeneration pause during anti-snipe**
    - **Validates: Requirements 4.1, 4.2, 4.3, 4.4**

  - [ ] 3.5 Implement anti-snipe floor enforcement
    - Check if anti-snipe period is active
    - Enforce minimum allegiance floor
    - Handle floor expiry logic
    - _Requirements: 5.2, 5.3, 5.4_

  - [ ]* 3.6 Write property test for floor enforcement
    - **Property 16: Floor enforcement during anti-snipe**
    - **Property 17: Floor expiry**
    - **Validates: Requirements 5.3, 5.4**

  - [ ] 3.7 Implement capture detection logic
    - Check if allegiance <= 0 (allegiance mode) or control >= 100 with uptime complete (control mode)
    - Respect anti-snipe and cooldown states
    - Return capture flag and metadata
    - _Requirements: 2.4, 5.1_

  - [ ]* 3.8 Write property test for capture detection
    - **Property 5: Capture on uptime completion**
    - **Validates: Requirements 2.4**

- [ ] 4. Implement ConquestStateMachine validation
  - [ ] 4.1 Implement prerequisite validation
    - Validate combat win requirement
    - Validate Envoy survival
    - Check feature flags
    - Return structured validation results with reason codes
    - _Requirements: 2.1, 2.5_

  - [ ]* 4.2 Write property test for prerequisite validation
    - **Property 3: Control establishment on victory**
    - **Validates: Requirements 2.1, 2.5**

  - [ ] 4.3 Implement protection checks
    - Check if target is protected/beginner player
    - Check if target is in safe zone
    - Check power delta thresholds
    - Return ERR_PROTECTED or ERR_SAFE_ZONE
    - _Requirements: 6.1, 6.2_

  - [ ]* 4.4 Write property test for protection checks
    - **Property 18: Protection blocking**
    - **Validates: Requirements 6.1, 6.2**

  - [ ] 4.5 Implement cooldown enforcement
    - Check capture cooldown status
    - Check anti-snipe period
    - Return ERR_COOLDOWN when active
    - _Requirements: 5.5_

  - [ ] 4.6 Implement wave spacing validation
    - Track last wave arrival per attacker-target pair
    - Enforce minimum spacing per world config
    - Return ERR_SPACING on violations
    - _Requirements: 8.1, 8.2_

  - [ ]* 4.7 Write property test for wave spacing
    - **Property 23: Wave spacing enforcement**
    - **Validates: Requirements 8.1, 8.2**

  - [ ] 4.8 Implement village cap and handover checks
    - Check per-account village limits
    - Check tribe handover opt-in status
    - Return ERR_VILLAGE_CAP or ERR_HANDOVER_OFF
    - _Requirements: 6.3, 6.5_

  - [ ]* 4.9 Write property test for cap enforcement
    - **Property 19: Village cap enforcement**
    - **Property 21: Handover opt-in enforcement**
    - **Validates: Requirements 6.3, 6.5**

- [ ] 5. Implement TrainingPipeline for Envoys
  - [ ] 5.1 Implement Hall of Banners building
    - Add building definition to database
    - Implement building upgrade logic
    - Add to building construction system
    - _Requirements: 1.1_

  - [ ] 5.2 Implement training prerequisite validation
    - Check Hall of Banners level
    - Check research node completion
    - Check influence crest inventory
    - Check resource and population availability
    - Return appropriate error codes
    - _Requirements: 1.1, 1.3_

  - [ ]* 5.3 Write property test for training prerequisites
    - **Property 1: Prerequisite enforcement**
    - **Validates: Requirements 1.1, 1.3, 1.4**

  - [ ] 5.4 Implement training cap enforcement
    - Enforce per-command Envoy limits
    - Enforce per-village training caps
    - Enforce per-day training caps per account
    - Return ERR_CAP on violations
    - _Requirements: 1.4, 8.5_

  - [ ]* 5.5 Write property test for training caps
    - **Property 26: Training cap enforcement**
    - **Validates: Requirements 8.5**

  - [ ] 5.6 Implement resource consumption
    - Deduct influence crests from inventory
    - Deduct wood, clay, iron from village
    - Deduct population from village
    - Use configured costs per world
    - _Requirements: 1.2_

  - [ ]* 5.7 Write property test for resource consumption
    - **Property 2: Resource consumption on training**
    - **Validates: Requirements 1.2**

  - [ ] 5.8 Integrate with training queue system
    - Add Envoy training to Hall of Banners queue
    - Set siege-speed training time
    - Handle queue cancellation with partial refunds
    - _Requirements: 1.5_

- [ ] 6. Implement combat integration
  - [ ] 6.1 Hook into battle resolution system
    - Add conquest attempt hook after battle resolution
    - Pass battle outcome and surviving Envoys to conquest system
    - Trigger allegiance/control calculations
    - _Requirements: 2.1, 2.5_

  - [ ] 6.2 Implement Envoy survival calculation
    - Apply wall-based survival reduction
    - Calculate casualties based on combat results
    - Return surviving Envoy count
    - _Requirements: 3.2, 3.5_

  - [ ]* 6.3 Write property test for Envoy survival
    - **Property 7: Wall impact on survival**
    - **Property 10: Zero survivors means no control**
    - **Validates: Requirements 3.2, 3.5**

  - [ ] 6.4 Implement control/allegiance application
    - Call AllegianceService with battle results
    - Apply control gain or allegiance drop
    - Handle control decay when defender dominates
    - Update village state in database
    - _Requirements: 2.1, 2.2, 3.1_

  - [ ]* 6.5 Write property test for control application
    - **Property 4: Control gain rate calculation**
    - **Property 6: Control decay on defender dominance**
    - **Validates: Requirements 2.2, 3.1**

  - [ ] 6.6 Implement resistance calculation
    - Calculate attacker pressure from attacking troops
    - Calculate defender resistance from defending troops and walls
    - Determine if decay should apply
    - _Requirements: 3.1, 3.3_

  - [ ]* 6.7 Write property test for resistance mechanics
    - **Property 8: Resistance increase on support**
    - **Validates: Requirements 3.3**

- [ ] 7. Implement control/uptime mechanics
  - [ ] 7.1 Implement uptime timer system
    - Start uptime timer when control reaches 100
    - Track uptime progress
    - Reset timer if control drops below 100
    - Trigger capture when uptime completes
    - _Requirements: 2.3, 2.4, 3.4_

  - [ ]* 7.2 Write property test for uptime mechanics
    - **Property 9: Uptime timer reset**
    - **Validates: Requirements 3.4**

  - [ ] 7.3 Implement control decay system
    - Calculate decay rate based on resistance dominance
    - Apply decay when defender resistance exceeds threshold
    - Clamp control to [0, 100]
    - _Requirements: 3.1_

- [ ] 8. Implement PostCaptureHandler
  - [ ] 8.1 Implement ownership transfer
    - Update village owner_id
    - Update village tribe_id
    - Log ownership change
    - _Requirements: 9.1_

  - [ ] 8.2 Implement diplomacy state updates
    - Update diplomacy for all stationed troops
    - Handle allied support per world config (stay or return)
    - Transfer command ownership
    - _Requirements: 9.2, 9.4_

  - [ ]* 8.3 Write property test for post-capture state
    - **Property 27: Ownership transfer completeness**
    - **Validates: Requirements 9.1, 9.2, 9.3, 9.4**

  - [ ] 8.4 Implement resource transfer
    - Transfer remaining resources to new owner
    - Preserve vault protection
    - Log resource transfer amounts
    - _Requirements: 9.3_

  - [ ] 8.5 Implement anti-snipe initialization
    - Set post-capture allegiance start value
    - Activate anti-snipe floor
    - Set capture cooldown timestamp
    - Pause regeneration for initial period
    - _Requirements: 5.1, 5.2, 5.5_

  - [ ]* 8.6 Write property test for anti-snipe initialization
    - **Property 15: Post-capture initialization**
    - **Validates: Requirements 5.1, 5.2, 5.5**

  - [ ] 8.7 Implement optional building loss
    - Check if building loss is enabled for world
    - Apply configured probability (e.g., 10%)
    - Reduce one random military building by one level
    - Cap at one building loss per capture
    - _Requirements: 9.5_

  - [ ]* 8.8 Write property test for building loss
    - **Property 28: Optional building loss**
    - **Validates: Requirements 9.5**

  - [ ] 8.9 Handle outgoing commands
    - Cancel outgoing attacks from captured village
    - Return troops from cancelled commands
    - Notify affected players
    - _Requirements: 9.1_

- [ ] 9. Implement reporting system
  - [ ] 9.1 Implement conquest report generation
    - Create report structure with all required fields
    - Include allegiance/control changes
    - Include surviving Envoy count
    - Include reason codes for blocked attempts
    - Include regeneration amounts
    - Include active modifiers (wall, tech, etc.)
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

  - [ ]* 9.2 Write property test for report generation
    - **Property 22: Comprehensive conquest reporting**
    - **Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5**

  - [ ] 9.3 Implement audit logging
    - Log all conquest attempts to conquest_attempts table
    - Log resolution order for same-tick waves
    - Log reason codes for blocked attempts
    - Include full context for debugging
    - _Requirements: 8.4_

  - [ ]* 9.4 Write property test for audit logging
    - **Property 25: Resolution audit logging**
    - **Validates: Requirements 8.4**

- [ ] 10. Implement regeneration background job
  - [ ] 10.1 Create regeneration tick processor
    - Query villages needing regeneration updates
    - Batch process regeneration calculations
    - Update allegiance values and timestamps
    - Handle pause conditions (anti-snipe, combat, etc.)
    - _Requirements: 4.1, 4.4_

  - [ ] 10.2 Implement abandonment decay
    - Check for abandoned villages (owner offline > threshold)
    - Apply decay when enabled and no garrison present
    - Clamp to minimum of 0
    - _Requirements: 4.5_

  - [ ]* 10.3 Write property test for abandonment decay
    - **Property 14: Abandonment decay**
    - **Validates: Requirements 4.5**

  - [ ] 10.4 Schedule regeneration job
    - Set up cron job or scheduled task
    - Run every 1-5 minutes based on world config
    - Add monitoring and error handling
    - _Requirements: 4.1_

- [ ] 11. Implement anti-abuse detection
  - [ ] 11.1 Implement repeated capture detection
    - Track captures between account pairs
    - Flag repeated captures within detection window
    - Apply diminishing returns to subsequent attempts
    - Log flagged activity for review
    - _Requirements: 6.4_

  - [ ]* 11.2 Write property test for abuse detection
    - **Property 20: Repeated capture detection**
    - **Validates: Requirements 6.4**

  - [ ] 11.3 Implement rate limiting
    - Add rate limits on training commands
    - Add rate limits on attack commands
    - Return appropriate errors on violations
    - _Requirements: 8.1_

- [ ] 12. Implement same-tick wave resolution
  - [ ] 12.1 Implement random resolution order
    - Detect waves arriving in same tick
    - Randomize resolution order
    - Process waves sequentially in random order
    - _Requirements: 8.3_

  - [ ]* 12.2 Write property test for random resolution
    - **Property 24: Random resolution order**
    - **Validates: Requirements 8.3**

- [ ] 13. Implement configuration mode switching
  - [ ] 13.1 Implement mode detection and routing
    - Read conquest_mode from world config
    - Route to allegiance-drop or control-uptime logic
    - Ensure both modes work correctly
    - _Requirements: 10.1_

  - [ ]* 13.2 Write property test for mode switching
    - **Property 29: Mode-specific behavior**
    - **Validates: Requirements 10.1**

  - [ ] 13.3 Implement configuration application
    - Load all world-specific config values
    - Apply values consistently across all operations
    - Cache configuration for performance
    - _Requirements: 10.2, 10.3, 10.4, 10.5_

  - [ ]* 13.4 Write property test for configuration application
    - **Property 30: Configuration application**
    - **Validates: Requirements 10.2, 10.3, 10.4, 10.5**

- [ ] 14. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 15. Implement UI components (based on Stitch design integration)
  - [ ] 15.1 Create medieval-themed village overview layout
    - Implement parchment-style resource panel with wood, clay, iron, crop icons
    - Add warehouse capacity display (e.g., "5000 / 5000")
    - Style production rates with medieval aesthetic
    - Add decorative borders and medieval UI elements
    - _Requirements: Design integration, existing village overview enhancement_

  - [ ] 15.2 Enhance buildings section with grid layout
    - Display buildings in organized grid with icons
    - Show building levels and upgrade progress bars
    - Add "Upgrade to X" buttons with medieval styling
    - Implement building icons with medieval graphics
    - Group buildings by type (military, resource, infrastructure)
    - _Requirements: Design integration, building display enhancement_

  - [ ] 15.3 Create troops display panel
    - List all unit types with quantities (Spearmen, Swordsmen, Archers, etc.)
    - Add unit icons with medieval styling
    - Display current troop counts
    - Style with medieval aesthetic matching the design
    - _Requirements: Design integration, troop display_

  - [ ] 15.4 Implement village status panel
    - Display Loyalty percentage (maps to allegiance/control)
    - Show Morale percentage
    - Display Population (current/max)
    - Style with medieval panel design
    - Add visual indicators for status levels
    - _Requirements: 2.1, 5.2, 5.5, Design integration_

  - [ ] 15.5 Create current construction panel
    - Show ongoing building upgrades with timers
    - Display multiple construction items if queued
    - Add "CANCEL" and "SPEED UP" buttons
    - Implement countdown timers
    - Style with medieval aesthetic
    - _Requirements: Design integration, construction queue display_

  - [ ] 15.6 Create Hall of Banners building page
    - Display building information and level
    - Show Envoy training interface with medieval styling
    - Display training queue with timers
    - Show influence crest inventory with icon
    - Match design aesthetic from village overview
    - _Requirements: 1.1, 1.2_

  - [ ] 15.7 Create conquest report display
    - Show allegiance/control changes with visual indicators
    - Display surviving Envoy count with unit icons
    - Show reason codes for blocked attempts
    - Display modifiers and calculations
    - Style as medieval scroll/parchment report
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

  - [ ] 15.8 Add conquest indicators to village display
    - Integrate Loyalty display (allegiance/control value) into Village Status panel
    - Show anti-snipe status with shield icon and remaining time
    - Show capture cooldown status with timer
    - Display active control links with visual indicators
    - Add tooltips explaining conquest mechanics
    - _Requirements: 2.1, 5.2, 5.5_

  - [ ] 15.9 Implement medieval CSS theme
    - Create medieval-themed color palette (browns, golds, dark greens)
    - Add parchment/scroll background textures
    - Style buttons with medieval aesthetic
    - Add decorative borders and ornaments
    - Implement medieval font styling for headers
    - Create responsive layout matching the design
    - _Requirements: Design integration_

  - [ ] 15.10 Add conquest tooltips and help
    - Explain allegiance/control mechanics with hover tooltips
    - Show regeneration rates and bonuses in status panel
    - Explain anti-snipe protection with info icons
    - Document error codes in help section
    - Add contextual help for conquest features
    - _Requirements: 4.1, 5.1, 7.3_

- [ ]* 16. Integration testing
  - Test end-to-end conquest flows (training → attack → capture)
  - Test multi-wave conquest trains
  - Test concurrent attacks on same village
  - Test post-capture state transitions
  - Test report generation with all data fields
  - _Requirements: All_

- [ ]* 17. Load testing
  - Simulate 1000+ waves per tick
  - Test concurrent regeneration ticks
  - Test high-volume training and minting
  - Verify p95 latency targets met
  - _Requirements: All_

- [ ] 18. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
