# Implementation Plan: Resource System

## Overview
This implementation plan covers the resource and village management system for the browser-based strategy game. The system includes resource production/storage, building construction, troop recruitment/combat, and village conquest mechanics with a minimalist WAP-style interface.

## Current State Analysis
- ✅ ResourceManager exists with production rate calculation and offline gains
- ✅ BuildingManager exists with upgrade cost/time calculation
- ✅ BattleEngine exists with combat resolution
- ✅ BuildingQueueManager exists for construction queues
- ✅ Basic village overview UI exists in game/game.php
- ⚠️ Missing: Formal ViewRenderer component for WAP-style interfaces
- ⚠️ Missing: ConquestHandler for nobleman attacks and village capture
- ⚠️ Missing: Complete combat report generation and display
- ⚠️ Missing: Hiding Place protection mechanics
- ⚠️ Missing: Property-based tests for correctness properties

---

## Tasks

- [x] 1. Enhance ResourceManager for complete WAP-style display ✅
  - ✅ Implement `formatResourceDisplay()` method that returns "[Resource]: [Amount] (+[Rate]/hr)" format
  - ✅ Add validation to ensure resources never exceed warehouse capacity in all code paths
  - ✅ Add method to check if village has sufficient resources before operations
  - _Requirements: 1.1, 1.3, 1.4, 1.5_
  - _Completed: lib/managers/ResourceManager.php updated with formatResourceDisplay(), hasResources(), enforceWarehouseCapacity()_

- [x] 1.1 Write property test for resource display format ✅
  - **Property 1: Resource Display Format**
  - **Validates: Requirements 1.1**
  - _Completed: tests/resource_manager_property_test.php (100/100 iterations passed)_

- [x] 1.2 Write property test for production rate calculation ✅
  - **Property 2: Production Rate Calculation**
  - **Validates: Requirements 1.2**
  - _Completed: tests/resource_manager_property_test.php (100/100 iterations passed)_

- [x] 1.3 Write property test for resource capacity enforcement ✅
  - **Property 3: Resource Capacity Enforcement**
  - **Validates: Requirements 1.3, 1.4**
  - _Completed: tests/resource_manager_property_test.php (100/100 iterations passed)_

- [x] 2. Complete BuildingManager WAP-style interface methods
  - Add method to format building list as HTML table rows with upgrade links
  - Add method to format queue display as timestamped text entries
  - Ensure headquarters prerequisite is enforced before any construction
  - Add validation that building completion immediately applies new production rates
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_

- [x] 2.1 Write property test for building upgrade state transition ✅
  - **Property 4: Building Upgrade State Transition**
  - **Validates: Requirements 2.2**
  - _Completed: tests/building_manager_property_test.php with security hardening_
  - _Security: Database protection, test isolation, ownership validation, cleanup on exit_

- [x] 2.2 Write property test for headquarters prerequisite ✅
  - **Property 5: Headquarters Prerequisite**
  - **Validates: Requirements 2.5**
  - _Completed: tests/building_manager_property_test.php with security hardening_

- [x] 2.3 Write property test for building completion effects ✅
  - **Property 6: Building Completion Effects**
  - **Validates: Requirements 2.6**
  - _Completed: tests/building_manager_property_test.php with security hardening_

- [x] 3. Create ViewRenderer component for WAP-style interfaces
  - Implement `renderVillageOverview()` - compact HTML table with buildings/resources/movements
  - Implement `renderBuildingList()` - table rows with name, level, cost, time, upgrade link
  - Implement `renderResourceBar()` - text-only resource display with rates
  - Implement `renderNavigation()` - hyperlink menu for main sections
  - Implement `renderBattleReport()` - formatted text tables for combat results
  - Ensure all output is text-based with minimal HTML, suitable for WAP constraints
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 4. Implement troop recruitment WAP-style interface
  - Create recruitment panel showing unit queues as text: "[Unit] ([Completed]/[Total] complete, [Time] remaining)"
  - Display recruitment costs as "Cost: [Wood]W, [Clay]C, [Iron]I, [Pop] Pop, Time: [Duration]"
  - Create unit statistics comparison table with Attack/Defense columns
  - Add quantity input boxes and "Recruit" buttons (no drag-and-drop)
  - Ensure recruitment deducts resources and population, adds to training queue
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [-] 4.1 Write property test for recruitment resource deduction
  - **Property 7: Recruitment Resource Deduction**
  - **Validates: Requirements 4.5**

- [ ] 5. Implement troop movement system
  - Create movement initiation via hyperlink commands with timestamp generation
  - Display outgoing movements to sender: destination, arrival time, unit composition
  - Display incoming movements to defender: origin, arrival time, attack type
  - Implement movement resolution on arrival (attack/support/return)
  - _Requirements: 5.1, 5.2, 5.3, 5.4_

- [x] 5.1 Write property test for movement entry creation
  - **Property 8: Movement Entry Creation**
  - **Validates: Requirements 5.1**

- [ ] 6. Enhance BattleEngine for complete combat resolution
  - Verify sequential round combat: attackers strike first, defenders counter-attack
  - Ensure damage formula: Attack × Quantity × Random(0.8-1.2) vs Defense × Quantity × Wall Bonus × Random(0.8-1.2)
  - Implement unit type advantage cycle: cavalry > archers > infantry > spears > cavalry
  - Generate complete battle reports with initial forces, wall bonus, casualties, plunder, loyalty damage
  - Add 16×16 icon support for victory/defeat/scout indicators
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [x] 6.1 Write property test for combat damage bounds
  - **Property 9: Combat Damage Bounds**
  - **Validates: Requirements 6.2**

- [x] 6.2 Write property test for unit type advantage cycle
  - **Property 10: Unit Type Advantage Cycle**
  - **Validates: Requirements 6.3**

- [x] 6.3 Write property test for battle report completeness
  - **Property 11: Battle Report Completeness**
  - **Validates: Requirements 6.4**

- [ ] 7. Create ConquestHandler for village capture mechanics
  - Implement nobleman training prerequisites (Academy research + special coins)
  - Implement loyalty reduction: random value between 20-35 points per nobleman attack
  - Implement village ownership transfer when loyalty reaches zero
  - Preserve all buildings and troops during ownership transfer
  - Display loyalty as "Loyalty: [Current]/100" on village overview
  - Record historical loyalty attacks with attacker name, timestamp, loyalty change
  - Add dropdown menu for multi-village navigation
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

- [x] 7.1 Write property test for nobleman loyalty reduction bounds
  - **Property 12: Nobleman Loyalty Reduction Bounds**
  - **Validates: Requirements 7.2**

- [x] 7.2 Write property test for village conquest preservation
  - **Property 13: Village Conquest Preservation**
  - **Validates: Requirements 7.3**

- [ ] 8. Implement 15 core buildings system
  - Verify production buildings: Timber Camp, Clay Pit, Iron Mine with per-level production rates
  - Verify military buildings: Barracks, Stable, Workshop with unit type unlocking
  - Verify support buildings: Headquarters, Academy, Smithy, Rally Point (with coin minting)
  - Verify economic buildings: Market, Warehouse, Farm
  - Verify defensive buildings: Wall, Hiding Place
  - Ensure all buildings integrate with existing BuildingManager and BuildingConfigManager
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [x] 8.1 Write property test for production building effects
  - **Property 14: Production Building Effects**
  - **Validates: Requirements 8.1**

- [ ] 9. Implement Hiding Place resource protection
  - Add Hiding Place capacity calculation based on building level
  - Modify plunder calculation to protect resources up to Hiding Place capacity
  - Ensure only resources exceeding protection limit can be plundered
  - Display both Warehouse capacity and Hiding Place protection in storage info
  - _Requirements: 9.1, 9.2, 9.3_

- [x] 9.1 Write property test for hiding place protection
  - **Property 15: Hiding Place Protection**
  - **Validates: Requirements 9.1, 9.2**

- [ ] 10. Integrate all components into village overview page
  - Update game/game.php to use ViewRenderer for WAP-style display
  - Ensure compact layout: buildings (left), resources (center), movements (right)
  - Add navigation header with all main section links
  - Ensure zero-scroll access to critical information
  - Add meta-refresh tags for timer updates (or manual reload instructions)
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 11. Create battle report display system
  - Create reports archive page with hyperlinked entries ordered by timestamp
  - Create report detail page showing full statistics in tabular format
  - Include initial forces, wall bonus, casualties per unit type, resources plundered, loyalty damage
  - Add 16×16 victory/defeat/scout icons
  - Ensure all reports are text-based with minimal graphics
  - _Requirements: 6.4, 6.5, 6.6_

- [-] 12. Final checkpoint - Ensure all tests pass
  - Run all property-based tests with minimum 100 iterations each
  - Verify all unit tests pass
  - Test complete upgrade workflow: check → deduct → queue → complete
  - Test combat resolution with actual database state
  - Test conquest flow from attack to ownership transfer
  - Ensure all tests pass, ask the user if questions arise

---

## Testing Notes

### Property-Based Testing Framework
- Using **PHPUnit with eris/eris** for property-based testing
- Each property test runs minimum 100 iterations
- Tests are tagged with format: `**Feature: resource-system, Property {number}: {property_text}**`
- Each property test validates specific requirements from design document

### Unit Testing Focus
- Edge cases for level 0 and max level buildings
- Boundary conditions for resource calculations
- Error handling paths (ERR_RES, ERR_PREREQ, ERR_QUEUE_FULL, etc.)
- Database transaction integrity

### Integration Testing Focus
- Full upgrade workflow validation
- Combat resolution with database state
- Conquest flow end-to-end
- Queue processing and completion

### Manual Testing Checklist
- WAP-style interface rendering on low-bandwidth connection
- Meta-refresh timer updates
- Multi-village navigation
- Battle report archive browsing
- Resource protection during plunder
