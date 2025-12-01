# Combat System Implementation

Notes to turn the design doc into something implementable in this codebase.

## Quick Recap
- Server-side, tick-based resolution for all movements. Phases: movement → arrival detection → pre-battle prep → wall/terrain/time/weather modifiers → combat → loot/aftermath → reports/returns.
- Mission types to support: `attack`, `raid`, `siege`, `support`, `spy` (scout), `fake`, `return`, `noble` (loyalty), `pillage`, `escort`, `intercept/ambush`, plus meta cases like `mass_coordinated` (parallel attacks in same tick).
- Outcome drivers: unit classes/tech, walls/terrain/time-of-day/weather, morale (points/loyalty context), bounded luck, defender prep + stacked supports, loyalty state, mission-specific rules (raid retreat, fake cancel, siege multi-phase).

## Player / System Requirements (User Stories)
- As an attacker I can launch a movement with a mission type, payload (units, noble, siege target), and see travel + return times; once launched it is immutable except cancellation rules.
- As a defender I see incoming movements with visibility gated by watchtower: ETA, origin, size band, possible comp if my scouts survive.
- On arrival tick, the server resolves missions: applies wall/terrain/time/weather/morale/luck, runs siege-wall damage before main combat, then casualties and possible retreats (raid/flee rules).
- If attackers win and have capacity, resources are plundered (mission-dependent caps/bonuses). Siege can drop wall/building levels; nobles drop loyalty; pillage damages random buildings; support missions station; fakes turn around before impact.
- Support troops from multiple owners stack defensively and consume host food; owners can recall anytime.
- Dodging is allowed (units in transit are absent from defense); intercept missions can target troops mid-transit.
- Reports are generated for all parties with forces, losses, modifiers, loot, wall/building/loyalty deltas, and intel (for scouts).

## Domain Model (Conceptual)
- `UnitType`: id/internal, class (infantry/cavalry/archer/siege/special), attack/def stats, carry, speed, pop, bonuses (vs class), research hooks.
- `Army`: map<unitTypeId, count>, owner, origin village, morale context, payload flags (has_noble, has_siege).
- `Movement` (current `attacks` table): id, missionType, origin, target, departAt, arriveAt, returnAt?, is_canceled, target_building, payload (maybe JSON for nobles/escorts), visibility metadata.
- `VillageState` at tick: wall level, buildings, terrain, loyalty, resources snapshot, watchtower level, supporters (list of Armies with owners), food capacity/usage, weather/time-of-day at coord/time.
- `BattleContext`: luck roll (bounded), morale factor, terrain/time/weather multipliers, wall multiplier, research bonuses, night bonus.
- `BattleReport`: persisted JSON of inputs/modifiers/losses/loot/wall/building/loyalty changes; ties to attacker/defender users and villages.

## Resolution Pipeline (Per Tick)
1. Collect movements whose `arrival_time <= now` and not completed/canceled.
2. Group by `target_village_id`, assemble defenders = owner garrison + supporters currently stationed (exclude those in transit).
3. For each movement:
   - If `missionType` is `support`: station army, mark completed, notify.
   - If `missionType` is `fake`: mark completed and schedule return from current position (or flip at fake threshold).
   - If `missionType` is `return`: add units back to target village.
   - Else: run battle resolution.
4. Battle resolution steps:
   - Snapshot context (terrain, time-of-day, weather, wall, morale, luck, research, night bonus).
   - Wall phase: apply siege damage (rams/trebs), defender siege counters, update effective wall level + multiplier.
   - Main combat: compute effective attack vs defense with class matching and modifiers; apply casualty curve; allow raid retreat logic.
   - Post-combat: plunder (mission carry bonus/cap), wall/building damage (catapults), loyalty drop + conquest (noble), pillage building damage, morale changes, food impact.
   - Persist surviving defenders/attackers, set attack completed, create return movement for surviving attackers.
   - Emit battle report + notifications.

## Casualty Curve & Modifiers (Defaults)
- Luck: clamp to [-25%, +25%] multiplier, applied to attack and/or defense once per battle.
- Morale: based on attacker vs defender points; clamp to [MIN_MORALE, 1.0]; skip if disabled.
- Wall: multiplier grows per level; reduced by siege damage before combat; may give reduced benefit to cavalry if desired.
- Time/Weather: configurable multipliers per unit class (e.g., night defense bonus, rain hurts archers).
- Ratio-based losses (Lanchester-ish): `ratio = attPow/defPow`; if attacker wins, defender wiped, attacker losses scaled by `1/ratio^k`; if defender wins, attacker wiped, defender losses scaled by `ratio^k`; choose `k≈1.5` to soften edges; enforce min loss on winner.
- Raid retreat: if `ratio` below a threshold, attackers flee early with reduced losses and no wall/building/loyalty effects.

## Mission Semantics (Delta from plain attack)
- `raid`: faster speed modifier, carry bonus, capped loot %, reduced casualties when retreating, no wall/loyalty damage.
- `siege`: slower speed, requires siege units, wall phase emphasized, enables conquest with nobles.
- `support`: joins defender stack until recalled.
- `spy`: uses scouts only; contest vs defender scouts + wall; intel tiered by surviving scouts.
- `fake`: auto-return before arrival (configurable distance %, maybe randomized per fake depth).
- `noble`: on win, reduce loyalty by random range; conquest at loyalty <= 0 respecting caps; failed noble can chip small loyalty.
- `pillage`: after a win, damage random production buildings.
- `escort`: mission that brings a passive payload; fights like normal if intercepted.
- `intercept`: target a movement/location/time; combat happens in transit without wall bonuses.

## Pseudo-code Skeleton (Tick)
```php
function processTick(array $arrivals, WorldState $world, RNG $rng) {
    foreach (groupByTarget($arrivals) as $targetId => $movements) {
        $village = $world->snapshotVillage($targetId);
        $defenders = assembleDefenders($village);
        foreach ($movements as $move) {
            switch ($move->mission) {
                case 'support': stationSupport($move, $village); continue 2;
                case 'fake': scheduleReturn($move, $rng); markComplete($move); continue 2;
                case 'return': restoreUnits($move); markComplete($move); continue 2;
            }
            $ctx = gatherContext($village, $world, $rng);
            $state = new BattleState($move, $defenders, $ctx);
            applyWallPhase($state);
            applyMainCombat($state);
            applyPostCombat($state, $world);
            persistBattle($state, $world);
        }
    }
}
```

## Implementation Notes for This Repo
- Existing `BattleManager::processBattle` handles most flows; align mission types (`raid`, `support`, `spy`, `fake`, `return`) and extend for `siege/pillage/intercept` if needed.
- There is already a `BattleEngine` class (docs/battle-engine.md, lib/managers/BattleEngine.php) with ratio-based casualties and siege math; reuse or refactor `processBattle` to delegate core math there for consistency.
- Add fields needed for context (terrain, weather, time-of-day flags, watchtower detection bands) either as world config or per-village attributes.
- Ensure reports capture modifiers and mission-specific effects for both attacker and defender.
- Wiring helpers added: `BattleManager::processAttackArrival($attackId)` to route by mission type, and `lib/managers/CombatTickProcessor.php` to process all due attacks server-side.

## Open Decisions
- Exact luck bounds and morale curve (current code uses ±25% luck and morale floor 0.3).
- Raid retreat threshold and casualty reduction.
- Fake return point (fixed % or randomized per mission depth).
- Terrain/weather tables and time-of-day window.
- Intercept targeting rules (pure time-based vs map coordinate interception).
