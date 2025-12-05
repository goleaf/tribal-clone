# Implementation Plan

- [ ] 1. Database schema and migrations
  - Create or verify building_queue table with status column
  - Add indexes for performance (village_id, status, finish_time)
  - Create building_requirements table if not exists
  - Verify building_types and village_buildings tables
  - _Requirements: 1.1, 2.1, 7.1, 8.1, 8.2_

- [ ] 1.1 Write property test for database schema integrity
  - **Property: Schema Consistency**
  - **Validates: Requirements 8.1, 8.2**

- [ ] 2. Implement BuildingConfigManager core functionality
  - Implement calculateUpgradeCost() with exponential scaling
  - Implement calculateUpgradeTime() with HQ bonus formula
  - Implement getBuildingRequirements() for prerequisite lookup
  - Add caching for configurations, costs, and times
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 9.1_

- [ ] 2.1 Write property test for build time formula
  - **Property 16: Build Time Formula Application**
  - **Validates: Requirements 5.1**

- [ ] 2.2 Write property test for cost calculation
  - **Property: Cost Scaling**
  - **Validates: Requirements 1.1**

- [ ] 3. Implement BuildingQueueManager - enqueue functionality
  - Implement enqueueBuild() with transaction handling
  - Add village row locking (SELECT FOR UPDATE / BEGIN IMMEDIATE)
  - Implement immediate resource deduction
  - Implement queue status logic (active vs pending)
  - Calculate finish times based on queue state
  - Validate queue capacity limits
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 7.1, 7.2_

- [ ] 3.1 Write property test for immediate resource deduction
  - **Property 1: Immediate Resource Deduction**
  - **Validates: Requirements 1.1**

- [ ] 3.2 Write property test for active status on empty queue
  - **Property 2: Active Status for Empty Queue**
  - **Validates: Requirements 1.2**

- [ ] 3.3 Write property test for pending status on non-empty queue
  - **Property 3: Pending Status for Non-Empty Queue**
  - **Validates: Requirements 1.3**

- [ ] 3.4 Write property test for insufficient resources rejection
  - **Property 4: Insufficient Resources Rejection**
  - **Validates: Requirements 1.5**

- [ ] 4. Implement BuildingQueueManager - completion functionality
  - Implement onBuildComplete() with idempotency guards
  - Increment building level in village_buildings
  - Mark queue item as completed
  - Implement rebalanceQueue() for pending promotion
  - Recalculate finish times on promotion
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 4.2, 4.5_

- [ ] 4.1 Write property test for build completion increments level
  - **Property 5: Build Completion Increments Level**
  - **Validates: Requirements 2.1**

- [ ] 4.2 Write property test for completion status transition
  - **Property 6: Completion Status Transition**
  - **Validates: Requirements 2.2**

- [ ] 4.3 Write property test for pending promotion on completion
  - **Property 7: Pending Promotion on Completion**
  - **Validates: Requirements 2.3**

- [ ] 4.4 Write property test for finish time recalculation on promotion
  - **Property 8: Finish Time Recalculation on Promotion**
  - **Validates: Requirements 2.4**

- [ ] 4.5 Write property test for idempotent completion
  - **Property 15: Idempotent Completion**
  - **Validates: Requirements 4.5**

- [ ] 5. Implement BuildingQueueManager - cancellation functionality
  - Implement cancelBuild() with ownership validation
  - Calculate 90% refund
  - Apply refund to village resources
  - Delete or mark queue item as canceled
  - Promote next pending if active was canceled
  - Rebalance remaining queue
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 5.1 Write property test for cancellation refund calculation
  - **Property 9: Cancellation Refund Calculation**
  - **Validates: Requirements 3.1**

- [ ] 5.2 Write property test for active cancellation promotes pending
  - **Property 10: Active Cancellation Promotes Pending**
  - **Validates: Requirements 3.2**

- [ ] 5.3 Write property test for pending cancellation isolation
  - **Property 11: Pending Cancellation Isolation**
  - **Validates: Requirements 3.3**

- [ ] 5.4 Write property test for cancellation status transition
  - **Property 12: Cancellation Status Transition**
  - **Validates: Requirements 3.4**

- [ ] 6. Implement BuildingQueueManager - query functionality
  - Implement getVillageQueue() for display
  - Order results by starts_at ascending
  - Filter to active and pending only
  - Include all required fields (building_name, level, status, finish_time)
  - Calculate queue positions for pending items
  - _Requirements: 6.1, 6.2, 6.3, 6.5_

- [ ] 6.1 Write property test for queue query returns active and pending only
  - **Property 21: Queue Query Returns Active and Pending Only**
  - **Validates: Requirements 6.1**

- [ ] 6.2 Write property test for queue response completeness
  - **Property 22: Queue Response Completeness**
  - **Validates: Requirements 6.2**

- [ ] 6.3 Write property test for queue ordering by start time
  - **Property 23: Queue Ordering by Start Time**
  - **Validates: Requirements 6.3**

- [ ] 7. Implement cron processor
  - Create jobs/process_building_queue.php
  - Implement processCompletedBuilds() batch handler
  - Query for active builds with finish_time <= NOW()
  - Call onBuildComplete() for each
  - Log processing results
  - Handle errors gracefully (continue processing other items)
  - _Requirements: 4.1, 4.2, 4.4, 4.5_

- [ ] 7.1 Write property test for cron processor selection
  - **Property 13: Cron Processor Selection**
  - **Validates: Requirements 4.1**

- [ ] 7.2 Write property test for active status precondition
  - **Property 14: Active Status Precondition**
  - **Validates: Requirements 4.2**

- [ ] 8. Implement BuildingManager validation
  - Implement canUpgradeBuilding() comprehensive validation
  - Add checkProtectionStatus() for protection mode blocking
  - Add checkResearchPrerequisites() for research validation
  - Add checkStorageCapacity() for storage limits
  - Validate building prerequisites
  - Validate max level caps
  - Validate resource availability
  - Validate population capacity
  - _Requirements: 9.1, 9.2, 9.3, 9.4_

- [ ] 8.1 Write property test for prerequisite validation
  - **Property 25: Prerequisite Validation**
  - **Validates: Requirements 9.1**

- [ ] 8.2 Write property test for level jump validation
  - **Property 26: Level Jump Validation**
  - **Validates: Requirements 9.3**

- [ ] 9. Implement notification system integration
  - Create notifications on build completion
  - Include building name and new level in notification
  - Link notification to village overview
  - Respect user notification preferences
  - Create separate notifications for each completion
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [ ] 9.1 Write property test for notification creation on completion
  - **Property 27: Notification Creation on Completion**
  - **Validates: Requirements 10.1**

- [ ] 9.2 Write property test for notification content completeness
  - **Property 28: Notification Content Completeness**
  - **Validates: Requirements 10.2**

- [ ] 9.3 Write property test for multiple build notification independence
  - **Property 29: Multiple Build Notification Independence**
  - **Validates: Requirements 10.4**

- [ ] 10. Implement logging and metrics
  - Add logQueueEvent() for audit log
  - Add logQueueMetric() for metrics log
  - Log all enqueue, complete, cancel events
  - Log failures with error codes
  - Use JSONL format for easy parsing
  - _Requirements: 4.4, 8.5_

- [ ] 10.1 Write unit tests for logging functionality
  - Test log file creation
  - Test JSONL format
  - Test error logging
  - _Requirements: 4.4, 8.5_

- [ ] 11. Create AJAX endpoints
  - Create ajax/buildings/upgrade_building.php
  - Create ajax/buildings/cancel_upgrade.php
  - Create ajax/buildings/get_queue.php
  - Add CSRF protection
  - Add ownership validation
  - Return structured JSON responses with error codes
  - _Requirements: 1.1, 3.1, 6.1, 7.1, 8.4_

- [ ] 11.1 Write integration tests for AJAX endpoints
  - Test upgrade_building.php flow
  - Test cancel_upgrade.php flow
  - Test get_queue.php response format
  - Test CSRF protection
  - Test ownership validation
  - _Requirements: 1.1, 3.1, 6.1_

- [ ] 12. Implement queue slot limits based on HQ level
  - Implement getQueueSlotLimit() calculation
  - Base slots + (HQ_level - 1) / milestone_step
  - Enforce slot limits in enqueueBuild()
  - Return appropriate error code (ERR_QUEUE_CAP)
  - _Requirements: 1.4_

- [ ] 12.1 Write property test for HQ level capture at queue time
  - **Property 17: HQ Level Capture at Queue Time**
  - **Validates: Requirements 5.2**

- [ ] 12.2 Write property test for HQ level recalculation on promotion
  - **Property 20: HQ Level Recalculation on Promotion**
  - **Validates: Requirements 5.5**

- [ ] 13. Implement world speed multiplier support
  - Integrate WorldManager for world-specific settings
  - Apply world_speed and build_speed_multiplier in calculations
  - Cache world settings to avoid repeated queries
  - _Requirements: 5.3_

- [ ] 13.1 Write property test for world speed multiplier application
  - **Property 18: World Speed Multiplier Application**
  - **Validates: Requirements 5.3**

- [ ] 14. Implement build time rounding
  - Ensure calculateUpgradeTime() returns integers
  - Round to nearest second (ceil for safety)
  - _Requirements: 5.4_

- [ ] 14.1 Write property test for build time integer rounding
  - **Property 19: Build Time Integer Rounding**
  - **Validates: Requirements 5.4**

- [ ] 15. Add error handling and transaction rollback
  - Wrap all operations in try-catch blocks
  - Use database transactions with BEGIN/COMMIT/ROLLBACK
  - Return structured error responses with codes
  - Log errors without exposing sensitive data
  - _Requirements: 7.4, 8.5_

- [ ] 15.1 Write unit tests for transaction rollback
  - Test rollback on resource deduction failure
  - Test rollback on queue insert failure
  - Test rollback on completion failure
  - _Requirements: 7.4_

- [ ] 16. Implement database abstraction for SQLite/MySQL
  - Detect database type (SQLite vs MySQL)
  - Use BEGIN IMMEDIATE for SQLite transactions
  - Use SELECT FOR UPDATE for MySQL row locking
  - Test with both database types
  - _Requirements: 8.1, 8.2, 8.3_

- [ ] 16.1 Write integration tests for both database types
  - Test enqueue with SQLite
  - Test enqueue with MySQL
  - Test completion with SQLite
  - Test completion with MySQL
  - _Requirements: 8.1, 8.2, 8.3_

- [ ] 17. Add performance optimizations
  - Add database indexes (village_id, status, finish_time)
  - Implement configuration caching in BuildingConfigManager
  - Implement cost/time caching
  - Use prepared statements for all queries
  - Minimize transaction scope
  - _Requirements: 7.1, 7.2, 7.3_

- [ ] 17.1 Write performance tests
  - Test query performance with indexes
  - Test cache hit rates
  - Test transaction duration
  - _Requirements: 7.1, 7.2, 7.3_

- [ ] 18. Create configuration constants
  - Define BUILDING_QUEUE_MAX_ITEMS
  - Define BUILDING_BASE_QUEUE_SLOTS
  - Define BUILDING_HQ_MILESTONE_STEP
  - Define BUILDING_MAX_QUEUE_SLOTS
  - Define BUILD_TIME_LEVEL_FACTOR
  - Define MAIN_BUILDING_TIME_REDUCTION_PER_LEVEL
  - Define BUILDING_CANCEL_REFUND_RATE
  - _Requirements: 1.4, 3.1, 5.1, 5.2_

- [ ] 19. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 20. Create migration scripts
  - Create migration for adding status column
  - Create migration for adding indexes
  - Create migration for backfilling status values
  - Test migrations on both SQLite and MySQL
  - _Requirements: 8.1, 8.2_

- [ ] 20.1 Write unit tests for migrations
  - Test status column addition
  - Test index creation
  - Test backfill logic
  - _Requirements: 8.1, 8.2_

- [ ] 21. Setup cron job documentation
  - Document cron job setup in README
  - Provide example crontab entry
  - Document log file locations
  - Document troubleshooting steps
  - _Requirements: 4.1, 4.4_

- [ ] 22. Implement queue position calculation
  - Calculate position for pending items in getVillageQueue()
  - Position = count of items with earlier start times + 1
  - _Requirements: 6.5_

- [ ] 22.1 Write property test for queue position calculation
  - **Property 24: Queue Position Calculation**
  - **Validates: Requirements 6.5**

- [ ] 23. Add backward compatibility layer
  - Preserve addBuildingToQueue() method for legacy code
  - Ensure status column defaults work for legacy inserts
  - Test with existing game code
  - _Requirements: 1.1, 2.1_

- [ ] 23.1 Write integration tests for backward compatibility
  - Test legacy addBuildingToQueue() method
  - Test legacy queue queries
  - Test mixed legacy/new queue items
  - _Requirements: 1.1, 2.1_

- [ ] 24. Final Checkpoint - Make sure all tests are passing
  - Ensure all tests pass, ask the user if questions arise.
