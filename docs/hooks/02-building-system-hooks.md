# Building System Hooks

## 4. before_building_upgrade_start

**Trigger Point:** Before initiating a building upgrade or construction

**Parameters:**
- `int $village_id` - Village where building is being upgraded
- `string $building_type` - Type of building (headquarters, barracks, etc.)
- `int $current_level` - Current building level (0 if new construction)
- `int $target_level` - Target level after upgrade
- `array $costs` - Resource costs for this upgrade
- `int $duration` - Base construction time in seconds

**Return Value:** `array` - Modified upgrade parameters (costs, duration)

**Use Cases:**
1. Apply construction time reduction bonuses (premium, research, relics)
2. Modify costs based on seasonal events or territory bonuses
3. Validate building prerequisites and dependencies
4. Apply construction speed boosts from temporary buffs
5. Implement custom building restrictions or requirements

**Example Implementation:**
```php
add_filter('before_building_upgrade_start', function($params, $village_id, $building_type, $current_level) {
    $user_id = get_village_owner($village_id);
    
    // Apply premium 10% time reduction
    if (has_premium_account($user_id)) {
        $params['duration'] = floor($params['duration'] * 0.9);
    }
    
    // Apply Master Builder relic bonus
    if (has_active_relic($user_id, 'master_builder')) {
        $params['duration'] = floor($params['duration'] * 0.85);
    }
    
    // Autumn season: 15% cost reduction
    if (get_current_season() === 'autumn') {
        $params['costs'] = array_map(fn($cost) => floor($cost * 0.85), $params['costs']);
    }
    
    return $params;
}, 10);
```

---

## 5. after_building_upgrade_complete

**Trigger Point:** After a building upgrade completes during tick processing

**Parameters:**
- `int $village_id` - Village where upgrade completed
- `string $building_type` - Type of building upgraded
- `int $new_level` - New building level
- `int $completion_time` - Unix timestamp of completion

**Return Value:** `void` (action hook)

**Use Cases:**
1. Trigger achievement checks for building milestones
2. Send completion notifications to player
3. Update village points calculation
4. Unlock new features or units at specific building levels
5. Update quest progress for construction objectives

**Example Implementation:**
```php
add_action('after_building_upgrade_complete', function($village_id, $building_type, $new_level, $completion_time) {
    $user_id = get_village_owner($village_id);
    
    // Create notification
    create_notification($user_id, 'building_complete', [
        'village_id' => $village_id,
        'building' => $building_type,
        'level' => $new_level,
        'timestamp' => $completion_time
    ]);
    
    // Check achievement: all buildings level 10+
    if ($new_level >= 10) {
        $all_buildings = get_village_buildings($village_id);
        $all_level_10 = true;
        foreach ($all_buildings as $building => $level) {
            if ($level < 10) {
                $all_level_10 = false;
                break;
            }
        }
        if ($all_level_10 && !has_achievement($user_id, 'master_builder')) {
            grant_achievement($user_id, 'master_builder');
        }
    }
    
    // Update village points
    recalculate_village_points($village_id);
}, 10);
```

---

## 6. validate_building_queue

**Trigger Point:** Before adding a building to the construction queue

**Parameters:**
- `int $village_id` - Target village
- `array $current_queue` - Existing construction queue entries
- `string $building_type` - Building being added to queue
- `int $queue_limit` - Maximum queue size (1 for free, 2 for premium)

**Return Value:** `bool|WP_Error` - True if valid, error object if invalid

**Use Cases:**
1. Enforce queue size limits (free vs premium accounts)
2. Prevent duplicate buildings in queue
3. Validate building dependencies (e.g., barracks requires headquarters level 3)
4. Check for conflicting construction (can't upgrade building already in queue)
5. Implement custom queue management rules


**Example Implementation:**
```php
add_filter('validate_building_queue', function($valid, $village_id, $current_queue, $building_type, $queue_limit) {
    $user_id = get_village_owner($village_id);
    
    // Check queue size limit
    if (count($current_queue) >= $queue_limit) {
        return new WP_Error('queue_full', 
            'Construction queue is full. Upgrade to premium for 2 simultaneous builds.');
    }
    
    // Check if building already in queue
    foreach ($current_queue as $queued) {
        if ($queued['building_type'] === $building_type) {
            return new WP_Error('already_queued', 'This building is already being upgraded.');
        }
    }
    
    // Validate dependencies
    $current_level = get_building_level($village_id, $building_type);
    $requirements = get_building_requirements($building_type, $current_level + 1);
    
    foreach ($requirements as $req_building => $req_level) {
        if (get_building_level($village_id, $req_building) < $req_level) {
            return new WP_Error('missing_requirement', 
                "Requires {$req_building} level {$req_level}.");
        }
    }
    
    return true;
}, 10);
```
