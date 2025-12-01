# Combat System Guide

## Overview

The combat system in this game is based on real-time server-tick resolution. Battles occur when attack commands arrive at target villages, with outcomes determined by troop strength, modifiers, and tactical decisions.

## Attack Types

### Standard Attack
- Full combat with normal casualty calculations
- Can include conquest units to capture villages
- Allows plundering of resources

### Raid/Plunder
- Reduced siege effectiveness
- Lower risk but also lower rewards
- Capped troop count or plunder amount

### Siege Focus
- Heavy siege units to damage walls and buildings
- Reduced loot potential
- Can include "siege hold" on some worlds

### Support Movement
- Send troops to defend friendly villages
- Troops fight on defender's side
- No plundering allowed

### Scout/Recon
- Intelligence gathering only
- Minimal combat (scout duel)
- Reveals enemy troops, resources, and buildings

### Fake Attack
- Low-population commands to deceive defenders
- Forces defenders to spread support
- May be limited by minimum population rules

## Battle Resolution

### Phase 1: Pre-Battle
1. **Scouting** - Gather intelligence on target
2. **Timing** - Coordinate wave arrivals
3. **Preparation** - Stack support or evacuate resources

### Phase 2: Combat
1. **Arrival Check** - Verify no shields/protection active
2. **Merge Forces** - Combine defender garrison + support
3. **Apply Modifiers** - Wall, terrain, morale, luck, night/weather
4. **Resolve Casualties** - Calculate losses for both sides
5. **Siege Effects** - Damage walls and buildings
6. **Conquest** - Drop allegiance if conquest units survive
7. **Plunder** - Calculate and distribute loot

### Phase 3: Post-Battle
1. **Survivors Return** - Troops return with loot
2. **Reports Generated** - Detailed battle reports
3. **Intel Shared** - Information distributed to tribe

## Combat Modifiers

### Morale
- Reduces attacker strength when attacking weaker players
- Helps protect newer/smaller players
- Configurable per world

### Luck
- Random variation in battle outcome
- Typically ±15% range
- Can be disabled on competitive worlds

### Night Bonus
- Defense multiplier during night hours
- Typically 1.1x to 1.5x defender strength
- Configurable time windows per world

### Terrain
- Forest: Buffs infantry and ranged units
- Plains: Buffs cavalry
- Hills: Buffs siege and defense
- Applied before wall modifier

### Weather
- **Fog**: Reduces scout accuracy and cavalry attack
- **Rain**: Lowers siege accuracy
- **Storm**: Narrows luck band
- **Clear**: No modifiers

### Wall
- Multiplies defender's defensive strength
- Higher wall = stronger defense
- Can be damaged by rams

### Overstack Penalty
- Applies when too many troops defend one village
- Diminishing returns on defense past threshold
- Prevents "turtle" meta
- Example: 60k population with 30k threshold = 0.7x defense

## Special Tactics

### Offensive Tactics

**Fake Floods**
- Send many low-population attacks
- Forces defender to spread support
- Masks real attack waves

**Nukes/Clears**
- High-attack stacks to wipe defense
- Usually cavalry + rams
- Sent before conquest waves

**Follow-Up Conquers**
- Conquest wave seconds after clear
- Relies on defender being wiped
- May need multiple waves

**Layered Waves**
- Sequence: siege → offense → conquest → support
- Each wave has specific purpose
- Timing is critical

### Defensive Tactics

**Sniping**
- Time support to land between conquest waves
- Kills nobles while letting first wave hit
- Requires precise timing

**Stacking Defense**
- Pre-load massive defense in target
- Can stop conquest trains
- Risk: huge losses if cleared

**Dodge & Counter**
- Move army out before impact
- Counter-attack attacker's origin
- Requires good timing and intel

**Timing Defense**
- Synchronize support from multiple villages
- Land just before or between waves
- Coordinate with tribe

## Battle Reports

### Report Structure

**Header**
- Time, attacker/defender names
- Village coordinates
- Command type
- War status

**Modifiers**
- Luck percentage
- Morale value
- Night/weather/terrain effects
- Overstack penalty
- Wall multiplier

**Troops**
- Sent / Lost / Survived
- Broken down by unit type
- Support listed separately
- Conquest units flagged

**Siege Effects**
- Wall level before/after
- Building targeted and damage
- Traps triggered

**Conquest**
- Allegiance before/after
- Drop amount
- Capture status
- Anti-snipe protection

**Plunder**
- Resources available
- Vault protection
- Amount plundered
- Carry capacity used

**Intel**
- Full details if scouts survive
- Redacted if scouts die
- Affected by fog/night

### Example Reports

**Crushing Victory**
```
Battle at Oakridge (512|489) — Attacker Victory
Luck +7%, Morale 1.00, Night Off, Wall 10→0

Attacker: 4,500 Axes / 400 LC / 60 Rams
Lost: 380 Axes / 22 LC / 10 Rams

Defender: 3,200 Spears / 1,100 Swords (all lost)

Targeted: Town Hall -1 level
Plunder: 18,000 Wood / 17,500 Clay / 12,400 Iron
(Vault protected: 3,000 each)
```

**Narrow Win with Conquest**
```
Battle at Bramblehold (498|501) — Attacker Victory
Luck -3%, Morale 0.86, Night On

Attacker: 3,800 Axes / 300 HC / 50 Rams / 3 Nobles
Lost: 2,900 Axes / 220 HC / 42 Rams / 1 Noble

Defender: 3,500 mixed (all lost)

Wall: 9→3
Allegiance: 73→46 (-27)
Status: Not captured (above threshold)
```

**Defender Victory**
```
Battle at Iron Nest (410|422) — Defender Victory
Luck +2%, Morale 0.74

Attacker: 5,000 LC / 80 Rams (all lost)

Defender Lost: 1,400 Spears / 500 Archers / 10 Rams

Wall: 11→9
Plunder: None
```

## Advanced Concepts

### Command Ordering
- Multiple commands at same time resolved by:
  1. Arrival timestamp
  2. Sequence number
  3. Type priority (support before attack)
  4. Command ID

### Rate Limits
- Maximum commands per time window
- Per-player and per-target caps
- Prevents spam and server overload
- Returns retry-after time when exceeded

### Minimum Population
- Attacks must meet minimum troop count
- Prevents 1-unit fake spam
- Configurable per world
- Typically 5-50 population

### Vault Protection
- Percentage of resources protected
- Hiding places add extra protection
- Higher of vault% or hiding place used
- Shown in battle reports

### Plunder Capacity
- Each unit has carry capacity
- Siege and conquest units carry 0
- Loot distributed among survivors
- Raid bonus may apply

### Occupation/Hold (Optional)
- Attackers can stay after winning
- Apply debuffs to defender
- Suffer attrition over time
- Can be broken by defender victory

## Tips & Strategy

### For Attackers
1. Scout before attacking
2. Send fakes to confuse defender
3. Clear defense before conquest
4. Time waves carefully
5. Use night bonus against you wisely
6. Check weather before sending

### For Defenders
1. Keep scouts to detect incoming
2. Stack support in key villages
3. Use watchtower to detect nobles
4. Time snipes between waves
5. Dodge when outmatched
6. Counter-attack when possible

### For Both
1. Understand modifiers
2. Use terrain to your advantage
3. Coordinate with tribe
4. Read battle reports carefully
5. Learn from losses
6. Adapt tactics to world rules

## World-Specific Rules

Different worlds may have:
- Different morale/luck settings
- Varying night bonus times
- Unique terrain effects
- Custom overstack thresholds
- Special unit availability
- Modified plunder caps
- Occupation mechanics

Always check your world's specific rules!

## Troubleshooting

### Attack Blocked
- **ERR_PROTECTED**: Target has protection
- **ERR_SAFE_ZONE**: Target in safe zone
- **ERR_MIN_POP**: Below minimum population
- **ERR_RATE_LIMIT**: Too many commands
- **ERR_SPACING**: Commands too close together

### No Plunder
- All resources protected by vault
- No surviving carriers
- Plunder cap reached
- Raid diminishing returns active

### Conquest Failed
- No conquest units survived
- Allegiance above capture threshold
- Anti-snipe protection active
- Cooldown period active

## FAQ

**Q: Why did my attack fail with higher numbers?**
A: Check modifiers - wall, night bonus, terrain, and morale can significantly affect outcomes.

**Q: How do I see enemy troops?**
A: Send scouts. If they survive, you'll get intel. Watchtower also helps detect incoming.

**Q: What's the best unit composition?**
A: Depends on target and strategy. Balanced armies are safer, specialized armies are riskier but more effective.

**Q: How do I protect my resources?**
A: Upgrade vault, use hiding places, trade away excess, or spend on builds/troops.

**Q: Can I recall an attack?**
A: Usually no, once sent it cannot be recalled. Plan carefully!

**Q: What happens if I'm offline during attack?**
A: Battle resolves normally. Some worlds have emergency shields you can activate.

## See Also

- Unit Statistics Guide
- Building Guide
- Conquest System Guide
- Tribe Warfare Guide
- World Settings Reference
