# Battle Engine Documentation

## Overview

The Battle Engine implements a sophisticated combat resolution system for Tribal Wars-style gameplay. It calculates battle outcomes based on unit statistics, defensive bonuses, morale, luck, and siege mechanics.

## Core Mechanics

### 1. Offense Calculation

Total offensive power is calculated by summing all attacking units' offense values:

```
totalOff = Σ(unit.off × count) × luck × morale
```

### 2. Defense Calculation

Defense is calculated based on unit class matching:

- **Infantry**: Uses `def.gen` against infantry, `def.cav` against cavalry, `def.arc` against archers
- **Cavalry**: Similar class-based defense
- **Archers**: Similar class-based defense

The effective defense is weighted by the attacker's unit composition:

```
effectiveDef = Σ(unit.defVsClass × count) × wallMultiplier × nightBonus
```

### 3. Modifiers

#### Wall Bonus
- Base formula: `1.037^wallLevel` for levels 1-10
- Enhanced formula: `1.037^10 × 1.05^(level-10)` for levels 11+
- Multiplies defender's effective defense

#### Morale
- Formula: `clamp(0.5, 1.5, 0.3 + defenderPoints / attackerPoints)`
- Range: 0.5 (min) to 1.5 (max)
- Multiplies attacker's offense
- Lower morale when attacking stronger players

#### Luck
- Random value between 0.75 and 1.25 (±25%)
- Multiplies attacker's offense
- Adds unpredictability to battles

#### Night Bonus
- 1.5× defense multiplier during night hours
- Configurable time window (default: 22:00 - 06:00)
- Must be enabled in world config

### 4. Battle Resolution

The battle ratio determines the outcome:

```
ratio = totalOff / effectiveDef
```

**If ratio ≥ 1 (Attacker Wins):**
- Defender loss factor: `1.0` (total loss)
- Attacker loss factor: `1 / ratio^1.5`

**If ratio < 1 (Defender Holds):**
- Attacker loss factor: `1.0` (total loss)
- Defender loss factor: `ratio^1.5`

The exponent of 1.5 creates a "square-root mechanic" that softens casualty edges.

### 5. Casualty Calculation

For each unit type:
```
lost = ceil(count × lossFactor)
survivors = max(0, count - lost)
```

### 6. Siege Mechanics

#### Rams
- Damage walls when present in attacking force
- Formula: `wallDrop = floor(survivingRams / ramsPerLevel)`
- Rams per level increases with wall level and decreases with world speed
- Applied regardless of battle outcome

#### Catapults
- Damage specific buildings (only if attacker wins)
- Formula: `levelsDrop = floor(survivingCatapults / catapultsPerLevel)`
- Catapults per level increases with building level
- Target building can be specified or random

## Unit Classes

| Unit | Class | Offense | Defense (Gen/Cav/Arc) |
|------|-------|---------|----------------------|
| Spear | Infantry | 10 | 15/45/20 |
| Sword | Infantry | 25 | 50/15/40 |
| Axe | Infantry | 40 | 10/5/10 |
| Archer | Archer | 15 | 50/40/5 |
| Scout | Cavalry | 0 | 2/1/2 |
| Light Cavalry | Cavalry | 130 | 40/30/30 |
| Heavy Cavalry | Cavalry | 150 | 200/80/180 |
| Ram | Infantry | 2 | 20/50/20 |
| Catapult | Infantry | 100 | 100/50/100 |
| Noble | Cavalry | 30 | 100/50/100 |

## Usage Example

```php
require_once 'lib/managers/BattleEngine.php';

$engine = new BattleEngine($conn);

$result = $engine->resolveBattle(
    attackerUnits: [
        'axe' => 100,
        'light' => 50,
        'ram' => 10
    ],
    defenderUnits: [
        'spear' => 80,
        'sword' => 40
    ],
    wallLevel: 10,
    defenderPoints: 5000,
    attackerPoints: 8000,
    worldConfig: [
        'speed' => 1.0,
        'night_bonus_enabled' => true,
        'night_start' => 22,
        'night_end' => 6
    ],
    targetBuilding: 'barracks',
    targetBuildingLevel: 15
);

// Result structure:
// [
//     'outcome' => 'attacker_win' | 'defender_hold',
//     'luck' => 0.75-1.25,
//     'morale' => 0.5-1.5,
//     'ratio' => float,
//     'wall' => ['start' => int, 'end' => int],
//     'building' => ['target' => string, 'start' => int, 'end' => int],
//     'attacker' => ['sent' => [], 'lost' => [], 'survivors' => []],
//     'defender' => ['present' => [], 'lost' => [], 'survivors' => []]
// ]
```

## Balance Tuning

Key constants that can be adjusted for game balance:

```php
// In BattleEngine.php
private const LUCK_MIN = 0.75;              // Minimum luck factor
private const LUCK_MAX = 1.25;              // Maximum luck factor
private const MORALE_MIN = 0.5;             // Minimum morale
private const MORALE_MAX = 1.5;             // Maximum morale
private const MORALE_BASE = 0.3;            // Base morale value
private const CASUALTY_EXPONENT = 1.5;      // Casualty calculation exponent
private const NIGHT_BONUS = 1.5;            // Night defense multiplier
private const WALL_BASE_MULTIPLIER = 1.037; // Wall bonus per level
private const WALL_LEVEL_THRESHOLD = 10;    // Level where wall bonus increases
```

## Testing

Run the test suite to validate battle mechanics:

```bash
php tests/BattleEngine.test.php
```

The test suite covers:
1. Basic attacks (attacker wins)
2. Defensive victories (defender holds)
3. Wall bonus effects
4. Morale impact
5. Ram siege mechanics
6. Catapult siege mechanics
7. Unit class specialization
8. Luck variance

## Integration with BattleManager

The BattleEngine can be integrated into the existing BattleManager:

```php
// In BattleManager::processBattle()
require_once __DIR__ . '/BattleEngine.php';

$battleEngine = new BattleEngine($this->conn);

// Prepare unit arrays
$attackerUnits = $this->prepareUnitArray($attack_id, 'attacker');
$defenderUnits = $this->prepareUnitArray($target_village_id, 'defender');

// Get wall level
$wallLevel = $this->buildingManager->getBuildingLevel($target_village_id, 'wall');

// Resolve battle
$result = $battleEngine->resolveBattle(
    $attackerUnits,
    $defenderUnits,
    $wallLevel,
    $defenderPoints,
    $attackerPoints,
    $worldConfig,
    $targetBuilding,
    $targetBuildingLevel
);

// Process result (update database, create reports, etc.)
```

## Future Enhancements

Potential additions to the battle engine:

1. **Research bonuses**: Smithy upgrades affecting unit stats
2. **Hero system**: Hero bonuses in battle
3. **Tribe bonuses**: Tribal research affecting combat
4. **Weather effects**: Additional environmental modifiers
5. **Formation bonuses**: Tactical positioning
6. **Fatigue system**: Multiple battles reducing effectiveness
7. **Loyalty system**: Noble attacks reducing village loyalty
8. **Support mechanics**: Friendly troops defending together

## Performance Considerations

- Unit data is loaded once from JSON file
- All calculations use native PHP math functions
- No database queries during battle resolution
- Suitable for processing hundreds of battles per minute

## Notes

- The battle engine is stateless and doesn't modify the database
- Integration with BattleManager handles persistence
- All randomness uses `mt_rand()` for better distribution
- Floating-point precision is maintained throughout calculations
