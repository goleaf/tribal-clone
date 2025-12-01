# Nobleman Conquest Flow Chart

## Attack Resolution Flow

```
┌─────────────────────────────────────┐
│   Noble Attack Sent                 │
│   (with nobleman units)             │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│   Troops Arrive at Target           │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│   Battle Resolution                 │
│   - Calculate attack/defense power  │
│   - Apply morale, wall, faith       │
│   - Resolve combat phases           │
│   - Determine winner                │
└──────────────┬──────────────────────┘
               │
               ▼
        ┌──────┴──────┐
        │             │
        ▼             ▼
   Attacker      Attacker
    Loses         Wins
        │             │
        │             ▼
        │      ┌─────────────────┐
        │      │ Check Conditions│
        │      └────────┬─────────┘
        │               │
        │               ▼
        │      ┌─────────────────────────┐
        │      │ Noble Survived?         │
        │      └────┬──────────────┬─────┘
        │           │ No           │ Yes
        │           │              │
        │           │              ▼
        │           │      ┌──────────────────┐
        │           │      │ All Defenders    │
        │           │      │ Eliminated?      │
        │           │      └────┬────────┬────┘
        │           │           │ No     │ Yes
        │           │           │        │
        │           ▼           ▼        ▼
        │      ┌────────────────────────────┐
        │      │ NO LOYALTY CHANGE          │
        │      │ - Noble died               │
        │      │ - Defenders survived       │
        │      └────────────────────────────┘
        │                                   │
        └───────────────┬───────────────────┘
                        │
                        ▼
               ┌────────────────┐
               │ Return Troops  │
               │ Generate Report│
               └────────────────┘
```

## Loyalty Drop & Conquest Flow

```
┌─────────────────────────────────────┐
│   Attacker Won + Noble Survived     │
│   + All Defenders Eliminated        │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│   Calculate Loyalty Drop            │
│   drop = random(20, 35)             │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│   Check Loyalty Floor               │
│   (Vasco's Scepter = 30, else 0)    │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│   newLoyalty = max(floor,           │
│                    currentLoyalty - drop)│
└──────────────┬──────────────────────┘
               │
               ▼
        ┌──────┴──────┐
        │             │
        ▼             ▼
   Loyalty > 0   Loyalty <= 0
        │             │
        │             ▼
        │      ┌─────────────────┐
        │      │ Check Point Gate│
        │      └────────┬─────────┘
        │               │
        │               ▼
        │      ┌─────────────────────────┐
        │      │ Defender Points in      │
        │      │ 50%-150% of Attacker?   │
        │      └────┬──────────────┬─────┘
        │           │ No           │ Yes
        │           │              │
        │           ▼              ▼
        │      ┌────────────┐  ┌──────────────┐
        │      │ Loyalty    │  │ CONQUEST!    │
        │      │ Drops But  │  │ Transfer     │
        │      │ No Conquest│  │ Ownership    │
        │      └────────────┘  └──────┬───────┘
        │                              │
        │                              ▼
        │                      ┌───────────────┐
        │                      │ Set owner_id  │
        │                      │ Set loyalty=100│
        │                      └───────────────┘
        │                              │
        └──────────────┬───────────────┘
                       │
                       ▼
              ┌────────────────┐
              │ Update Database│
              │ Generate Report│
              └────────────────┘
```

## Conquest Conditions Matrix

| Condition                  | Required for Loyalty Drop | Required for Conquest |
|----------------------------|---------------------------|----------------------|
| Attacker Wins Battle       | ✓ Yes                     | ✓ Yes                |
| Noble Survives             | ✓ Yes                     | ✓ Yes                |
| All Defenders Eliminated   | ✓ Yes                     | ✓ Yes                |
| Loyalty Reaches 0          | ✗ No                      | ✓ Yes                |
| Point Range 50%-150%       | ✗ No                      | ✓ Yes                |
| Loyalty Floor = 0          | ✗ No                      | ✓ Yes                |

## Example Conquest Sequence

### Scenario: Conquering a Village with 100 Loyalty

```
Initial State:
┌──────────────────────────┐
│ Village: Enemy Town      │
│ Owner: Player B          │
│ Loyalty: 100             │
│ Points: 1000             │
└──────────────────────────┘

Attack #1:
┌──────────────────────────┐
│ Attacker: Player A       │
│ Units: 1 Noble + Army    │
│ Result: Victory          │
│ Loyalty Drop: -28        │
│ New Loyalty: 72          │
└──────────────────────────┘

Attack #2:
┌──────────────────────────┐
│ Attacker: Player A       │
│ Units: 1 Noble + Army    │
│ Result: Victory          │
│ Loyalty Drop: -31        │
│ New Loyalty: 41          │
└──────────────────────────┘

Attack #3:
┌──────────────────────────┐
│ Attacker: Player A       │
│ Units: 1 Noble + Army    │
│ Result: Victory          │
│ Loyalty Drop: -25        │
│ New Loyalty: 16          │
└──────────────────────────┘

Attack #4:
┌──────────────────────────┐
│ Attacker: Player A       │
│ Units: 1 Noble + Army    │
│ Result: Victory          │
│ Loyalty Drop: -22        │
│ New Loyalty: -6 → 0      │
│ CONQUERED!               │
└──────────────────────────┘

Final State:
┌──────────────────────────┐
│ Village: Enemy Town      │
│ Owner: Player A ← CHANGED│
│ Loyalty: 100 ← RESET     │
│ Points: 1000             │
└──────────────────────────┘
```

## Point Range Gate Examples

### Valid Conquests (50%-150% range)

```
Attacker: 1000 points
├─ ✓ Can conquer: 500 points (50%)
├─ ✓ Can conquer: 750 points (75%)
├─ ✓ Can conquer: 1000 points (100%)
├─ ✓ Can conquer: 1250 points (125%)
└─ ✓ Can conquer: 1500 points (150%)
```

### Invalid Conquests (outside range)

```
Attacker: 1000 points
├─ ✗ Cannot conquer: 400 points (40% - too weak)
├─ ✗ Cannot conquer: 300 points (30% - too weak)
└─ ✗ Cannot conquer: 1600 points (160% - too strong)

Note: Loyalty will still drop, but ownership won't transfer
```

### Barbarian Villages (no gate)

```
Attacker: Any points
└─ ✓ Can always conquer barbarian villages (user_id = -1)
```

## Special Cases

### Vasco's Scepter (Loyalty Floor = 30)

```
Initial Loyalty: 100

Attack #1: 100 → 72 (drop 28)
Attack #2: 72 → 41 (drop 31)
Attack #3: 41 → 30 (drop 11, floor reached)
Attack #4: 30 → 30 (drop 25, but floor prevents)
Attack #5: 30 → 30 (drop 22, but floor prevents)

Result: Cannot conquer, loyalty stuck at 30
```

### Multiple Nobles in One Attack

```
Current Implementation:
- 3 nobles sent
- All 3 survive battle
- Only 1 loyalty drop applied (20-35)

Alternative Implementation (not default):
- 3 nobles sent
- All 3 survive battle
- 3 loyalty drops applied (60-105 total)
- Cap at 100 to prevent instant conquest
```

### Failed Noble Attack

```
Scenario: Noble Dies in Battle
┌──────────────────────────┐
│ Before Battle:           │
│ - Attacker: 1 Noble      │
│ - Defender: Strong Army  │
│ Loyalty: 100             │
└──────────────────────────┘
              │
              ▼
┌──────────────────────────┐
│ Battle Result:           │
│ - Attacker Wins: No      │
│ - Noble Survived: No     │
│ Loyalty: 100 (unchanged) │
└──────────────────────────┘
```

## Database State Changes

### Before Conquest

```sql
-- villages table
id | user_id | name        | loyalty | last_loyalty_update
1  | 5       | Enemy Town  | 16      | 2025-12-01 10:30:00
```

### After Conquest

```sql
-- villages table
id | user_id | name        | loyalty | last_loyalty_update
1  | 3       | Enemy Town  | 100     | 2025-12-01 10:35:00
   ↑ Changed              ↑ Reset   ↑ Updated
```

### Battle Report

```json
{
  "type": "battle",
  "attacker_won": true,
  "loyalty": {
    "before": 16,
    "after": 100,
    "drop": 22,
    "conquered": true,
    "floor": 0
  },
  "attacker_losses": { ... },
  "defender_losses": { ... },
  "loot": { ... }
}
```

## Integration Points

### 1. Battle Resolution
- File: `lib/managers/BattleManager.php`
- Method: `processBattle()`
- Checks conditions and applies loyalty changes

### 2. Database Updates
- Table: `villages`
- Columns: `user_id`, `loyalty`, `last_loyalty_update`
- Transaction ensures atomic ownership transfer

### 3. Battle Reports
- Table: `battle_reports`
- Column: `report_data` (JSON with loyalty info)
- Displayed to both attacker and defender

### 4. Notifications
- Attacker: "Village conquered!" or "Loyalty reduced to X"
- Defender: "Village lost!" or "Loyalty reduced to X"

### 5. Unit Data
- File: `data/units.json`
- Noble unit: `internal_name: "noble"`
- Cost: 40k wood, 50k clay, 50k iron, 4 coins
- Population: 100

## Testing Scenarios

### Test 1: Standard Conquest
```
✓ Send noble attack
✓ Win battle with noble surviving
✓ Verify loyalty drops 20-35
✓ Repeat until loyalty = 0
✓ Verify ownership transfers
✓ Verify loyalty resets to 100
```

### Test 2: Point Range Gate
```
✓ Attack village with 40% of attacker points
✓ Reduce loyalty to 0
✓ Verify ownership does NOT transfer
✓ Verify loyalty stays at 0
```

### Test 3: Vasco's Scepter
```
✓ Enable Vasco's Scepter
✓ Attack village
✓ Reduce loyalty to 30
✓ Verify loyalty cannot drop below 30
✓ Verify ownership does NOT transfer
```

### Test 4: Failed Conditions
```
✓ Noble dies in battle → no loyalty change
✓ Attacker loses → no loyalty change
✓ Defenders survive → no loyalty change
✓ Support attack with noble → no loyalty change
```
