# Nobleman Conquest System

## Overview

The nobleman conquest system allows players to capture enemy villages by reducing their loyalty to zero through successful noble attacks. This is a core endgame mechanic in Tribal Wars-style games.

## Core Mechanics

### Loyalty System

Each village has a **loyalty** value ranging from 0 to 100:
- **Default loyalty**: 100 (full loyalty to current owner)
- **Minimum loyalty**: 0 (village can be conquered)
- **Maximum loyalty**: 100

### Conquest Flow

When a noble attack succeeds, the following happens:

1. **Battle Resolution**: Normal combat is resolved first
2. **Victory Check**: Attacker must win the battle
3. **Noble Survival**: At least one noble must survive the battle
4. **Defender Elimination**: All defending units must be eliminated
5. **Loyalty Drop**: If all conditions are met, loyalty drops by a random amount

### Loyalty Drop Calculation

```php
// Random drop between 20-35 points per successful noble attack
$drop = random_int(20, 35);
$newLoyalty = currentLoyalty - $drop;
```

**Constants:**
- `LOYALTY_DROP_MIN`: 20 (minimum loyalty drop)
- `LOYALTY_DROP_MAX`: 35 (maximum loyalty drop)

### Conquest Conditions

A village is conquered when:
1. Attacker wins the battle
2. At least one noble survives
3. All defenders are eliminated
4. Loyalty drops to 0 or below
5. **Point Range Gate**: Defender village points must be 50%-150% of attacker village points (prevents conquering villages too weak or too strong)

When conquered:
- `owner_id` changes to attacker's user ID
- `loyalty` resets to 100 (full loyalty to new owner)
- All defending troops are eliminated
- Stationed support troops are handled according to game rules

### Loyalty Floor (Special Items)

Some special items can prevent full conquest:

**Vasco's Scepter** (Paladin weapon):
- Sets loyalty floor to 30 instead of 0
- Village cannot be conquered, but loyalty can drop to 30
- Useful for weakening but not capturing villages

```php
private function getEffectiveLoyaltyFloor(array $attackingUnits): int
{
    if ($this->hasPaladin($attackingUnits) && $this->getPaladinWeapon() === 'vascos_scepter') {
        return 30; // Cannot conquer, only reduce to 30
    }
    return 0; // Normal conquest possible
}
```

## Database Schema

### Villages Table

```sql
CREATE TABLE villages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    x_coord INT NOT NULL,
    y_coord INT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    loyalty INT NOT NULL DEFAULT 100,
    last_loyalty_update TIMESTAMP NULL,
    wood INT NOT NULL DEFAULT 0,
    clay INT NOT NULL DEFAULT 0,
    iron INT NOT NULL DEFAULT 0,
    -- ... other columns
);
```

**Key columns:**
- `loyalty`: Current loyalty value (0-100)
- `last_loyalty_update`: Timestamp of last loyalty change (for regeneration systems)
- `user_id`: Current owner of the village

## Implementation Details

### Battle Resolution (BattleManager.php)

The conquest logic is integrated into the `processBattle()` method:

```php
// After battle resolution, check for noble conquest
$loyalty_report = null;
$villageConquered = false;
$loyalty_before = $this->getVillageLoyalty($attack['target_village_id']);
$loyalty_after = $loyalty_before;

if ($attacker_win && $this->hasNobleUnit($attacking_units) && !$defenderAlive) {
    // Calculate loyalty drop
    $dropMin = defined('NOBLE_MIN_DROP') ? (int)NOBLE_MIN_DROP : self::LOYALTY_DROP_MIN;
    $dropMax = defined('NOBLE_MAX_DROP') ? (int)NOBLE_MAX_DROP : self::LOYALTY_DROP_MAX;
    $drop = random_int($dropMin, $dropMax);
    
    // Apply loyalty floor (e.g., Vasco's Scepter)
    $loyalty_floor = $this->getEffectiveLoyaltyFloor($attacking_units);
    $loyalty_after = max($loyalty_floor, $loyalty_before - $drop);
    
    // Check if village is conquered
    $villageConquered = ($loyalty_floor === self::LOYALTY_MIN) && $loyalty_after <= self::LOYALTY_MIN;
    
    // Enforce point-range gate (50%-150%)
    $defender_points = $this->getVillagePointsWithFallback($attack['target_village_id']);
    $attacker_points = $this->getVillagePointsWithFallback($attack['source_village_id']);
    if ($attacker_points > 0 && $defender_points > 0) {
        $ratio = $defender_points / $attacker_points;
        if ($ratio < 0.5 || $ratio > 1.5) {
            $villageConquered = false; // Loyalty drops but cannot capture
        }
    }
    
    // Reset loyalty to 100 if conquered
    if ($villageConquered) {
        $loyalty_after = self::LOYALTY_MAX;
    }
    
    $loyalty_report = [
        'before' => $loyalty_before,
        'after' => $loyalty_after,
        'drop' => $drop,
        'conquered' => $villageConquered,
        'floor' => $loyalty_floor
    ];
}

// Apply changes in transaction
if ($loyalty_report) {
    if ($villageConquered && $attacker_user_id !== null) {
        $this->transferVillageOwnership($attack['target_village_id'], $attacker_user_id, $loyalty_after);
    } else {
        $this->updateVillageLoyalty($attack['target_village_id'], $loyalty_after);
    }
}
```

### Helper Methods

**Check for Noble Units:**
```php
private function hasNobleUnit(array $attackingUnits): bool
{
    $nobleNames = ['noble', 'nobleman', 'nobleman_unit'];
    foreach ($attackingUnits as $unit) {
        if (($unit['count'] ?? 0) > 0 && in_array(strtolower($unit['internal_name'] ?? ''), $nobleNames, true)) {
            return true;
        }
    }
    return false;
}
```

**Get Village Loyalty:**
```php
private function getVillageLoyalty(int $villageId): int
{
    $stmt = $this->conn->prepare("SELECT loyalty FROM villages WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $villageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return isset($row['loyalty']) ? (int)$row['loyalty'] : self::LOYALTY_MAX;
}
```

**Update Village Loyalty:**
```php
private function updateVillageLoyalty(int $villageId, int $loyalty): void
{
    $stmt = $this->conn->prepare("UPDATE villages SET loyalty = ?, last_loyalty_update = CURRENT_TIMESTAMP WHERE id = ?");
    $loyalty = max(self::LOYALTY_MIN, min(self::LOYALTY_MAX, $loyalty));
    $stmt->bind_param("ii", $loyalty, $villageId);
    $stmt->execute();
    $stmt->close();
}
```

**Transfer Village Ownership:**
```php
private function transferVillageOwnership(int $villageId, int $newUserId, int $loyaltyAfter): void
{
    $stmt = $this->conn->prepare("UPDATE villages SET user_id = ?, loyalty = ?, last_loyalty_update = CURRENT_TIMESTAMP WHERE id = ?");
    $loyaltyAfter = max(self::LOYALTY_MIN, min(self::LOYALTY_MAX, $loyaltyAfter));
    $stmt->bind_param("iii", $newUserId, $loyaltyAfter, $villageId);
    $stmt->execute();
    $stmt->close();
}
```

## Battle Report Integration

Loyalty changes are included in battle reports:

```php
$details = [
    'type' => 'battle',
    'attacker_losses' => $attacker_losses,
    'defender_losses' => $defender_losses,
    'loot' => $loot,
    // ... other battle details
    'loyalty' => $loyalty_report // Includes before, after, drop, conquered, floor
];
```

### Report Display

The `processCompletedAttacks()` method generates user-facing messages:

```php
$loyaltyInfo = $report['details']['loyalty'] ?? null;
if ($loyaltyInfo && !empty($loyaltyInfo['drop'])) {
    if (!empty($loyaltyInfo['conquered'])) {
        // Village was conquered
        $messages[] = "<p class='success-message'>Loyalty of <b>{$target_name}</b> dropped to zero. The village was conquered!</p>";
    } else {
        // Loyalty dropped but village not conquered
        $drop = (int)$loyaltyInfo['drop'];
        $after = (int)$loyaltyInfo['after'];
        $messages[] = "<p class='info-message'>Noble attack reduced loyalty of <b>{$target_name}</b> by {$drop} to {$after}.</p>";
    }
}
```

## Edge Cases & Rules

### Multiple Nobles
- Only **one loyalty drop** per attack, regardless of how many nobles survive
- Classic Tribal Wars behavior: multiple nobles increase survival chance but don't multiply drops
- Alternative: You can implement multiple drops (one per surviving noble) with a cap

### Support Attacks
- Support attacks **never** trigger conquest logic
- Nobles sent as support do not reduce loyalty

### Spy Missions
- Spy missions **never** trigger conquest logic
- Scouts cannot reduce loyalty

### Raid Attacks
- Raids can include nobles and reduce loyalty
- Same conquest rules apply as normal attacks

### Barbarian Villages
- Barbarian villages (user_id = -1) can be conquered
- No point-range gate applies to barbarians
- Loyalty mechanics work the same way

### Failed Attacks
- If attacker loses, loyalty is unchanged
- If noble dies in battle, loyalty is unchanged
- If any defenders survive, loyalty is unchanged

### Stationed Troops
When a village is conquered:
- All defending troops are eliminated (already handled in battle resolution)
- Support troops from other players should be:
  - **Option A**: Eliminated (harsh)
  - **Option B**: Returned to their home villages (generous)
  - **Option C**: Captured and converted to attacker's troops (complex)

Currently, the system eliminates all defending units during battle resolution.

## Configuration Constants

You can customize these in your config:

```php
// Loyalty drop range
define('NOBLE_MIN_DROP', 20);
define('NOBLE_MAX_DROP', 35);

// Point range gate for conquest
define('CONQUEST_MIN_RATIO', 0.5);  // 50% of attacker points
define('CONQUEST_MAX_RATIO', 1.5);  // 150% of attacker points

// Paladin features
define('FEATURE_PALADIN_ENABLED', true);
define('PALADIN_WEAPON', 'none'); // or 'vascos_scepter', 'bonfire', etc.
```

## Future Enhancements

### Loyalty Regeneration
Villages could slowly regenerate loyalty over time:
- +1 loyalty per hour (configurable)
- Stops at 100
- Can be implemented with a cron job or on-demand calculation

```php
public function regenerateLoyalty(int $villageId): void
{
    $stmt = $this->conn->prepare("
        SELECT loyalty, last_loyalty_update 
        FROM villages 
        WHERE id = ? AND loyalty < 100
    ");
    $stmt->bind_param("i", $villageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $village = $result->fetch_assoc();
    $stmt->close();
    
    if (!$village) return;
    
    $lastUpdate = strtotime($village['last_loyalty_update']);
    $now = time();
    $hoursPassed = floor(($now - $lastUpdate) / 3600);
    
    if ($hoursPassed > 0) {
        $newLoyalty = min(100, $village['loyalty'] + $hoursPassed);
        $this->updateVillageLoyalty($villageId, $newLoyalty);
    }
}
```

### Church/Faith Loyalty Bonus
Churches could slow loyalty loss:
- Each church level reduces loyalty drop by 5%
- First church provides flat 10% reduction

### Noble Training Limits
- Limit nobles per village (e.g., 3 nobles max)
- Require special building (Academy) at high level
- Expensive coin cost (already implemented: 4 coins per noble)

### Conquest Cooldown
- Prevent re-conquering the same village too quickly
- E.g., 24-hour cooldown after conquest

## Testing Checklist

- [ ] Noble attack on full loyalty village (100 → 65-80)
- [ ] Multiple noble attacks to conquer (100 → 70 → 40 → 10 → conquered)
- [ ] Noble attack with Vasco's Scepter (stops at 30)
- [ ] Noble attack outside point range (loyalty drops but no conquest)
- [ ] Failed noble attack (attacker loses, no loyalty change)
- [ ] Noble dies in battle (no loyalty change)
- [ ] Defenders survive (no loyalty change)
- [ ] Support attack with nobles (no loyalty change)
- [ ] Spy mission (no loyalty change)
- [ ] Barbarian village conquest
- [ ] Battle report shows loyalty changes correctly
- [ ] Village ownership transfers correctly
- [ ] Loyalty resets to 100 after conquest

## Related Files

- `lib/managers/BattleManager.php` - Main conquest logic
- `data/units.json` - Noble unit definition
- `install.php` - Database schema with loyalty column
- `docs/nobleman-conquest-system.md` - This documentation
