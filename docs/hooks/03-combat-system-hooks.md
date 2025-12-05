# Combat System Hooks

## 7. before_combat_calculation

**Trigger Point:** Before calculating battle outcome when troops arrive

**Parameters:**
- `int $attacker_village_id` - Attacking village ID
- `int $defender_village_id` - Defending village ID
- `array $attacker_units` - Array of unit types and quantities
- `array $defender_units` - Array of defending unit types and quantities
- `int $wall_level` - Defender's wall level
- `array $combat_modifiers` - Existing modifiers (research, relics, etc.)

**Return Value:** `array` - Modified combat parameters (units, modifiers, wall bonus)

**Use Cases:**
1. Apply research bonuses (smithy upgrades to attack/defense)
2. Add relic combat bonuses
3. Apply territory control defensive bonuses
4. Implement seasonal combat modifiers
5. Add temporary buff effects from items or events

**Example Implementation:**
```php
add_filter('before_combat_calculation', function($params, $attacker_village_id, $defender_village_id) {
    $attacker_id = get_village_owner($attacker_village_id);
    $defender_id = get_village_owner($defender_village_id);
    
    // Apply smithy research bonuses
    $attacker_research = get_research_levels($attacker_id);
    $defender_research = get_research_levels($defender_id);
    
    $params['combat_modifiers']['attacker_bonus'] = 1.0 + ($attacker_research['attack'] * 0.01);
    $params['combat_modifiers']['defender_bonus'] = 1.0 + ($defender_research['defense'] * 0.01);
    
    // Apply Ancient Banner relic (+5% cavalry attack)
    if (has_active_relic($attacker_id, 'ancient_banner')) {
        foreach ($params['attacker_units'] as $unit => $quantity) {
            if (in_array($unit, ['scout', 'light_cavalry', 'heavy_cavalry'])) {
                $params['combat_modifiers']['cavalry_attack_bonus'] = 1.05;
                break;
            }
        }
    }
    
    // Territory control: +10% defense in controlled region
    $region = get_village_region($defender_village_id);
    if (is_region_controlled_by_alliance($region, get_user_alliance($defender_id))) {
        $params['combat_modifiers']['territory_defense'] = 1.10;
    }
    
    return $params;
}, 10);
```

---

## 8. after_combat_resolution

**Trigger Point:** After combat calculations complete, before generating report

**Parameters:**
- `array $combat_result` - Complete battle outcome data
- `int $attacker_village_id` - Attacking village
- `int $defender_village_id` - Defending village
- `array $casualties` - Units lost by both sides
- `array $resources_plundered` - Resources stolen (if attacker won)
- `int $loyalty_damage` - Loyalty reduction (if nobleman present)

**Return Value:** `array` - Modified combat result (can adjust plunder, loyalty, etc.)

**Use Cases:**
1. Calculate reputation/bounty rewards
2. Update attack/defense ranking points
3. Track combat statistics for achievements
4. Apply plunder bonuses or penalties
5. Trigger alliance war mechanics or territory changes

**Example Implementation:**
```php
add_filter('after_combat_resolution', function($combat_result, $attacker_village_id, $defender_village_id) {
    $attacker_id = get_village_owner($attacker_village_id);
    $defender_id = get_village_owner($defender_village_id);
    
    // Update attack/defense points for rankings
    $attacker_points = calculate_destroyed_unit_points($combat_result['casualties']['defender']);
    $defender_points = calculate_destroyed_unit_points($combat_result['casualties']['attacker']);
    
    increment_user_stat($attacker_id, 'attacker_points', $attacker_points);
    increment_user_stat($defender_id, 'defender_points', $defender_points);
    
    // Check bounty system
    $active_bounty = get_active_bounty($defender_id);
    if ($active_bounty && $combat_result['outcome'] === 'attacker_victory') {
        award_bounty($attacker_id, $active_bounty);
        $combat_result['bounty_claimed'] = $active_bounty['reward'];
    }
    
    // Summer season: +15% plunder bonus
    if (get_current_season() === 'summer' && isset($combat_result['resources_plundered'])) {
        $combat_result['resources_plundered'] = array_map(
            fn($amount) => floor($amount * 1.15), 
            $combat_result['resources_plundered']
        );
    }
    
    return $combat_result;
}, 10);
```

---

## 9. generate_combat_report

**Trigger Point:** When creating combat report for database storage

**Parameters:**
- `array $report_data` - Base report structure
- `string $report_type` - Type: attack/defense/scout
- `int $attacker_id` - Attacker user ID
- `int $defender_id` - Defender user ID
- `bool $is_premium_attacker` - Whether attacker has premium
- `bool $is_premium_defender` - Whether defender has premium

**Return Value:** `array` - Modified report data with additional details

**Use Cases:**
1. Add detailed statistics for premium users
2. Include unit-specific performance breakdowns
3. Add strategic recommendations or analysis
4. Include espionage intelligence cross-references
5. Format report data for different display contexts


**Example Implementation:**
```php
add_filter('generate_combat_report', function($report_data, $report_type, $attacker_id, $defender_id, $is_premium_attacker) {
    // Add detailed casualty breakdown for premium users
    if ($is_premium_attacker && $report_type === 'attack') {
        $report_data['detailed_casualties'] = [
            'attacker_losses_by_unit' => calculate_unit_losses($report_data['casualties']['attacker']),
            'defender_losses_by_unit' => calculate_unit_losses($report_data['casualties']['defender']),
            'efficiency_rating' => calculate_attack_efficiency($report_data),
            'optimal_composition' => suggest_optimal_composition($defender_id)
        ];
    }
    
    // Add espionage cross-reference
    $recent_intel = get_recent_intelligence($attacker_id, $defender_id, 86400); // Last 24h
    if ($recent_intel) {
        $report_data['intelligence_note'] = "Previous scout report available from " . 
            date('Y-m-d H:i', $recent_intel['timestamp']);
    }
    
    // Add achievement progress hints
    $report_data['achievement_progress'] = [
        'total_enemy_defeated' => get_user_stat($attacker_id, 'total_enemy_troops_defeated'),
        'next_milestone' => get_next_combat_achievement_threshold($attacker_id)
    ];
    
    return $report_data;
}, 10);
```
