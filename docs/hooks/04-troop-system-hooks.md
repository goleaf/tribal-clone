# Troop System Hooks

## 10. before_troop_recruitment

**Trigger Point:** Before adding troops to training queue

**Parameters:**
- `int $village_id` - Village recruiting troops
- `string $unit_type` - Type of unit being recruited
- `int $quantity` - Number of units to recruit
- `array $costs` - Total resource costs
- `int $training_time` - Total training time in seconds
- `int $population_cost` - Total population required

**Return Value:** `array` - Modified recruitment parameters

**Use Cases:**
1. Apply training time reduction bonuses
2. Modify costs based on events or research
3. Validate population capacity
4. Apply recruitment speed buffs
5. Implement unit recruitment restrictions or quotas

**Example Implementation:**
```php
add_filter('before_troop_recruitment', function($params, $village_id, $unit_type, $quantity) {
    $user_id = get_village_owner($village_id);
    
    // Apply research bonus: -10% training time per research level
    $research_level = get_research_level($user_id, $unit_type . '_training');
    $params['training_time'] = floor($params['training_time'] * (1 - ($research_level * 0.10)));
    
    // Daily quest buff: +20% recruitment speed for 24h
    if (has_active_buff($user_id, 'fast_recruitment')) {
        $params['training_time'] = floor($params['training_time'] * 0.80);
    }
    
    // Validate population capacity
    $current_pop = get_village_population($village_id);
    $max_pop = get_village_max_population($village_id);
    
    if (($current_pop + $params['population_cost']) > $max_pop) {
        return new WP_Error('insufficient_population', 
            'Not enough population capacity. Upgrade farm.');
    }
    
    return $params;
}, 10);
```

---

## 11. after_troop_training_complete

**Trigger Point:** After troops finish training during tick processing

**Parameters:**
- `int $village_id` - Village where training completed
- `string $unit_type` - Type of unit trained
- `int $quantity` - Number of units completed
- `int $completion_time` - Unix timestamp of completion

**Return Value:** `void` (action hook)

**Use Cases:**
1. Send training completion notifications
2. Update quest progress for troop recruitment objectives
3. Track military production statistics
4. Trigger achievements for army size milestones
5. Update village military strength calculations


**Example Implementation:**
```php
add_action('after_troop_training_complete', function($village_id, $unit_type, $quantity, $completion_time) {
    $user_id = get_village_owner($village_id);
    
    // Create notification
    create_notification($user_id, 'training_complete', [
        'village_id' => $village_id,
        'unit_type' => $unit_type,
        'quantity' => $quantity,
        'timestamp' => $completion_time
    ]);
    
    // Update daily quest progress
    update_quest_progress($user_id, 'recruit_troops', $quantity);
    
    // Track lifetime troop production
    increment_user_stat($user_id, 'total_troops_trained', $quantity);
    
    // Check achievement: train 10,000 total troops
    $total_trained = get_user_stat($user_id, 'total_troops_trained');
    if ($total_trained >= 10000 && !has_achievement($user_id, 'military_industrial_complex')) {
        grant_achievement($user_id, 'military_industrial_complex');
    }
}, 10);
```

---

## 12. before_troop_movement

**Trigger Point:** Before initiating troop movement (attack/support/trade)

**Parameters:**
- `int $from_village_id` - Origin village
- `int $to_village_id` - Destination village
- `array $units` - Unit composition being sent
- `string $movement_type` - Type: attack/support/return/trade
- `int $travel_time` - Calculated travel time in seconds
- `array $carry_capacity` - Resources being carried (if applicable)

**Return Value:** `array` - Modified movement parameters

**Use Cases:**
1. Apply movement speed bonuses (research, relics, territory control)
2. Validate troop availability and ownership
3. Calculate supply line interception risk
4. Apply seasonal movement modifiers
5. Implement movement restrictions or cooldowns

**Example Implementation:**
```php
add_filter('before_troop_movement', function($params, $from_village_id, $to_village_id, $movement_type) {
    $user_id = get_village_owner($from_village_id);
    
    // Apply cavalry speed research bonus
    $has_cavalry = false;
    foreach ($params['units'] as $unit => $quantity) {
        if (in_array($unit, ['scout', 'light_cavalry', 'heavy_cavalry'])) {
            $has_cavalry = true;
            break;
        }
    }
    
    if ($has_cavalry) {
        $speed_research = get_research_level($user_id, 'cavalry_speed');
        $params['travel_time'] = floor($params['travel_time'] * (1 - ($speed_research * 0.05)));
    }
    
    // Territory control: -15% travel time within controlled region
    $from_region = get_village_region($from_village_id);
    $to_region = get_village_region($to_village_id);
    $user_alliance = get_user_alliance($user_id);
    
    if ($from_region === $to_region && 
        is_region_controlled_by_alliance($from_region, $user_alliance)) {
        $params['travel_time'] = floor($params['travel_time'] * 0.85);
    }
    
    // Calculate supply line risk for trade caravans
    if ($movement_type === 'trade') {
        $params['interception_risk'] = calculate_route_risk($from_village_id, $to_village_id);
    }
    
    return $params;
}, 10);
```
