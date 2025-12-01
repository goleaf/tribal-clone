# Nobleman Conquest - Quick Reference

## TL;DR

Nobles reduce village loyalty by 20-35 per successful attack. When loyalty reaches 0, the village is conquered.

## Requirements for Loyalty Drop

✓ Attacker wins the battle  
✓ At least one noble survives  
✓ All defenders are eliminated  

## Requirements for Conquest

✓ All loyalty drop requirements  
✓ Loyalty reaches 0 or below  
✓ Target village points are 50%-150% of attacker village points  
✓ No loyalty floor (e.g., Vasco's Scepter)  

## Key Numbers

| Metric | Value |
|--------|-------|
| Default loyalty | 100 |
| Loyalty drop per attack | 20-35 (random) |
| Average attacks to conquer | ~4 |
| Point range gate | 50%-150% |
| Loyalty after conquest | 100 (reset) |
| Noble cost | 40k wood, 50k clay, 50k iron, 4 coins |
| Noble population | 100 |

## Code Constants

```php
// In BattleManager.php
const LOYALTY_MIN = 0;
const LOYALTY_MAX = 100;
const LOYALTY_DROP_MIN = 20;
const LOYALTY_DROP_MAX = 35;

// Configurable via defines
define('NOBLE_MIN_DROP', 20);
define('NOBLE_MAX_DROP', 35);
```

## Database Schema

```sql
-- villages table
loyalty INT NOT NULL DEFAULT 100
last_loyalty_update TIMESTAMP NULL
user_id INT NOT NULL  -- Changes on conquest
```

## Common Scenarios

### Scenario 1: Successful Conquest
```
Attack 1: 100 → 72 (drop 28)
Attack 2: 72 → 45 (drop 27)
Attack 3: 45 → 15 (drop 30)
Attack 4: 15 → 0 (drop 22) → CONQUERED
```

### Scenario 2: Point Gate Blocks Conquest
```
Attacker: 1000 points
Target: 400 points (40% - too weak)
Loyalty: 0
Result: Loyalty drops but NO conquest
```

### Scenario 3: Vasco's Scepter
```
Attack 1: 100 → 72
Attack 2: 72 → 45
Attack 3: 45 → 30 (floor reached)
Attack 4: 30 → 30 (cannot drop further)
Result: Cannot conquer
```

### Scenario 4: Noble Dies
```
Battle: Attacker wins but noble dies
Loyalty: No change
Result: No conquest progress
```

## Battle Report JSON

```json
{
  "loyalty": {
    "before": 72,
    "after": 45,
    "drop": 27,
    "conquered": false,
    "floor": 0
  }
}
```

## Key Methods

```php
// Check for nobles
$this->hasNobleUnit($attackingUnits)

// Get current loyalty
$this->getVillageLoyalty($villageId)

// Update loyalty
$this->updateVillageLoyalty($villageId, $newLoyalty)

// Transfer ownership
$this->transferVillageOwnership($villageId, $newUserId, $loyalty)

// Get loyalty floor (Vasco's Scepter)
$this->getEffectiveLoyaltyFloor($attackingUnits)
```

## Edge Cases

| Case | Behavior |
|------|----------|
| Multiple nobles | Only 1 drop per attack |
| Support attack | No loyalty change |
| Spy mission | No loyalty change |
| Raid attack | Loyalty can drop |
| Barbarian village | No point gate |
| Failed attack | No loyalty change |
| Defenders survive | No loyalty change |

## Testing Checklist

- [ ] Noble attack reduces loyalty
- [ ] Multiple attacks conquer village
- [ ] Point gate prevents conquest
- [ ] Vasco's Scepter prevents conquest
- [ ] Failed attacks don't change loyalty
- [ ] Battle reports show loyalty changes
- [ ] Ownership transfers correctly
- [ ] Loyalty resets to 100 after conquest

## Files to Review

- `lib/managers/BattleManager.php` - Main logic
- `data/units.json` - Noble unit definition
- `docs/nobleman-conquest-system.md` - Full documentation
- `examples/nobleman-conquest-demo.php` - Demo script
- `migrations/add_loyalty_tracking.php` - Database migration

## Quick Debug

```php
// Check village loyalty
SELECT id, name, user_id, loyalty, last_loyalty_update 
FROM villages 
WHERE id = ?;

// Check battle reports with loyalty changes
SELECT br.id, br.attacker_won, br.report_data
FROM battle_reports br
WHERE JSON_EXTRACT(report_data, '$.loyalty') IS NOT NULL
ORDER BY br.battle_time DESC
LIMIT 10;

// Check noble attacks
SELECT a.id, a.attack_type, a.arrival_time
FROM attacks a
JOIN attack_units au ON a.id = au.attack_id
JOIN unit_types ut ON au.unit_type_id = ut.id
WHERE ut.internal_name = 'noble'
AND a.is_completed = 0;
```

## Configuration

```php
// config/game.php (example)
define('NOBLE_MIN_DROP', 20);
define('NOBLE_MAX_DROP', 35);
define('CONQUEST_MIN_RATIO', 0.5);
define('CONQUEST_MAX_RATIO', 1.5);
define('FEATURE_PALADIN_ENABLED', true);
define('PALADIN_WEAPON', 'none'); // or 'vascos_scepter'
```

## Future Enhancements

- [ ] Loyalty regeneration (+1/hour)
- [ ] Church reduces loyalty drop
- [ ] Multiple drops per noble (optional)
- [ ] Conquest cooldown
- [ ] Noble training limits
- [ ] Loyalty display in village view
- [ ] Conquest history/statistics
