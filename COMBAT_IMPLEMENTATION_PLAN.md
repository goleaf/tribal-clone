# Combat System Implementation Plan

## Status Overview

### ✅ Already Completed
- Conquest integration with allegiance drop
- Command ordering for simultaneous arrivals
- Fake/min-pop rules enforcement
- Overstack penalties system
- Night/terrain/weather flags
- Rate limits and backpressure
- Basic reporting structure
- Plunder/loot calculations with vault protection

### ⏳ Remaining Tasks

#### High Priority (Core Combat)
1. **Combat Resolver Implementation**
   - [ ] Attack/defense aggregation by type
   - [ ] Apply wall/terrain/morale/luck/night/weather modifiers
   - [ ] Resolve casualties proportionally
   - [ ] Apply siege effects (wall/building damage)

2. **Unit Tests for Combat Resolver**
   - [ ] Modifier tests (morale/luck/night/terrain)
   - [ ] Siege damage scaling tests
   - [ ] Overstack penalty tests
   - [ ] Proportional casualty tests

3. **Siege Attrition Variant Tests**
   - [ ] Test SIEGE_ATTRITION_MULT when enabled
   - [ ] Verify reports show flag/value
   - [ ] Ensure disabled worlds remain unchanged

#### Medium Priority (Features)
4. **Migration Scripts**
   - [ ] Schema changes for allegiance fields
   - [ ] Report tables migration
   - [ ] Rollback steps tested

5. **Occupation/Hold System** (if enabled)
   - [ ] Occupation state management
   - [ ] Attrition/upkeep rules
   - [ ] Loot rules during occupation
   - [ ] Reports show occupation timers/status

6. **Anti-Cheat Signals**
   - [ ] Flag impossible command patterns
   - [ ] Detect duplicate command IDs
   - [ ] Detect tampered payloads
   - [ ] Log for audit

#### Low Priority (Polish & Monitoring)
7. **Audit/Telemetry**
   - [ ] Log battle resolution traces
   - [ ] Emit metrics (tick duration, battles resolved, etc.)
   - [ ] Alert on spikes

8. **Load Tests**
   - [ ] Simulate large stacked battles
   - [ ] Test fake floods
   - [ ] Validate performance under load

9. **Determinism Tests**
   - [ ] Ensure identical inputs = identical outputs
   - [ ] Test across servers and replays

10. **Property Fuzzing**
    - [ ] Fuzz negative/overflow troop counts
    - [ ] Test extreme modifiers
    - [ ] Test malformed commands

11. **Integration Simulations**
    - [ ] End-to-end scenarios
    - [ ] Compare against golden reports

12. **Monitoring Dashboards**
    - [ ] Battle volume per world
    - [ ] Resolver latency
    - [ ] Error rates
    - [ ] Alert thresholds

13. **Release Communications**
    - [ ] Patch notes
    - [ ] Sample reports
    - [ ] Player-facing FAQ

## Implementation Order

### Phase 1: Core Combat (Week 1-2)
1. Implement combat resolver
2. Add unit tests
3. Test siege attrition

### Phase 2: Database & Features (Week 3)
4. Migration scripts
5. Occupation system (if needed)
6. Anti-cheat signals

### Phase 3: Testing & Monitoring (Week 4)
7. Audit/telemetry
8. Load tests
9. Determinism tests
10. Property fuzzing
11. Integration sims

### Phase 4: Launch Prep (Week 5)
12. Monitoring dashboards
13. Release communications

## Quick Wins (Can Do Now)

These can be implemented immediately:

1. **Migration Script Template** - Create schema migration structure
2. **Anti-Cheat Logging** - Add command validation logging
3. **Monitoring Metrics** - Add basic metric collection
4. **Documentation** - Create player-facing combat guide

## Files to Create/Modify

### New Files Needed
- `lib/managers/CombatResolver.php` - Core combat logic
- `tests/combat_resolver_test.php` - Unit tests
- `migrations/add_combat_fields.php` - Database migration
- `tools/combat_simulator.php` - Testing tool
- `docs/COMBAT_GUIDE.md` - Player documentation

### Files to Modify
- `lib/managers/BattleManager.php` - Integrate combat resolver
- `lib/managers/ReportManager.php` - Enhanced reporting
- `config/config.php` - Combat configuration constants

## Next Steps

Would you like me to:
1. Start with the core combat resolver implementation?
2. Create the migration scripts first?
3. Build the testing framework?
4. Set up monitoring and metrics?

Let me know which priority you'd like to tackle first!
