# Village System Hooks

## 13. after_village_conquest

**Trigger Point:** After village ownership transfers due to loyalty reaching zero

**Parameters:**
- `int $village_id` - Village that was conquered
- `int $previous_owner_id` - Former owner user ID
- `int $new_owner_id` - New owner user ID
- `int $conquest_time` - Unix timestamp of conquest
- `array $remaining_troops` - Troops that survived in village
- `array $remaining_resources` - Resources left in village

**Return Value:** `void` (action hook)

**Use Cases:**
1. Transfer alliance territory control
2. Update conquest achievement progress
3. Send notifications to both players and alliances
4. Recalculate regional influence zones
5. Award dynasty prestige for conquest

**Example Implementation:**
```php
add_action('after_village_conquest', function($village_id, $previous_owner_id, $new_owner_id, $conquest_time) {
    // Notify both players
    create_notification($previous_owner_id, 'village_lost', [
        'village_id' => $village_id,
        'conqueror' => get_username($new_owner_id),
        'timestamp' => $conquest_time
    ]);
    
    create_notification($new_owner_id, 'village_conquered', [
        'village_id' => $village_id,
        'previous_owner' => get_username($previous_owner_id),
        'timestamp' => $conquest_time
    ]);
    
    // Update territory control
    $region = get_village_region($village_id);
    recalculate_region_control($region);
    
    // Award dynasty prestige
    $dynasty_id = get_user_dynasty($new_owner_id);
    if ($dynasty_id) {
        increment_dynasty_prestige($dynasty_id, 100); // 100 prestige per conquest
    }
    
    // Check achievement: conquer first village
    $total_conquests = get_user_stat($new_owner_id, 'villages_conquered');
    if ($total_conquests === 1 && !has_achievement($new_owner_id, 'first_conquest')) {
        grant_achievement($new_owner_id, 'first_conquest');
    }
    
    // Alliance war tracking
    $attacker_alliance = get_user_alliance($new_owner_id);
    $defender_alliance = get_user_alliance($previous_owner_id);
    
    if ($attacker_alliance && $defender_alliance && $attacker_alliance !== $defender_alliance) {
        increment_war_stat($attacker_alliance, $defender_alliance, 'villages_captured');
    }
}, 10);
```

---

## 14. calculate_village_points

**Trigger Point:** When recalculating village point value for rankings

**Parameters:**
- `int $village_id` - Village being calculated
- `array $building_levels` - All building levels
- `int $base_points` - Base calculation from building levels
- `array $modifiers` - Additional point modifiers

**Return Value:** `int` - Final village point value


**Use Cases:**
1. Apply bonus points for special buildings or achievements
2. Implement point decay for inactive villages
3. Add points for relic possession or territory control
4. Apply seasonal point modifiers
5. Implement custom point calculation formulas

**Example Implementation:**
```php
add_filter('calculate_village_points', function($base_points, $village_id, $building_levels, $modifiers) {
    $points = $base_points;
    $user_id = get_village_owner($village_id);
    
    // Bonus points for fully upgraded core buildings
    $core_buildings = ['headquarters', 'barracks', 'stable', 'workshop'];
    $all_maxed = true;
    
    foreach ($core_buildings as $building) {
        if (!isset($building_levels[$building]) || $building_levels[$building] < 20) {
            $all_maxed = false;
            break;
        }
    }
    
    if ($all_maxed) {
        $points += 500; // Bonus for military infrastructure
    }
    
    // Territory control bonus
    $region = get_village_region($village_id);
    if (is_region_controlled_by_alliance($region, get_user_alliance($user_id))) {
        $points = floor($points * 1.05); // 5% bonus in controlled territory
    }
    
    // Inactivity penalty: -1% per day inactive (max -50%)
    $last_activity = get_user_last_activity($user_id);
    $days_inactive = floor((time() - $last_activity) / 86400);
    
    if ($days_inactive > 7) {
        $penalty = min(0.50, ($days_inactive - 7) * 0.01);
        $points = floor($points * (1 - $penalty));
    }
    
    return $points;
}, 10);
```

---

## 15. validate_village_rename

**Trigger Point:** Before allowing village name change

**Parameters:**
- `int $village_id` - Village being renamed
- `string $new_name` - Proposed new name
- `string $old_name` - Current village name
- `int $user_id` - Owner user ID
- `bool $is_premium` - Whether user has premium account

**Return Value:** `bool|WP_Error` - True if valid, error object if invalid

**Use Cases:**
1. Enforce name length limits (20 chars free, unlimited premium)
2. Filter profanity and inappropriate content
3. Prevent duplicate names within user's villages
4. Validate character restrictions (alphanumeric + spaces)
5. Check rename token availability for non-premium users

**Example Implementation:**
```php
add_filter('validate_village_rename', function($valid, $village_id, $new_name, $old_name, $user_id, $is_premium) {
    // Length validation
    $max_length = $is_premium ? 100 : 20;
    if (strlen($new_name) > $max_length) {
        return new WP_Error('name_too_long', 
            "Village name must be {$max_length} characters or less.");
    }
    
    // Character validation
    if (!preg_match('/^[a-zA-Z0-9\s\-\_]+$/', $new_name)) {
        return new WP_Error('invalid_characters', 
            'Village name can only contain letters, numbers, spaces, hyphens, and underscores.');
    }
    
    // Profanity filter
    if (contains_profanity($new_name)) {
        return new WP_Error('inappropriate_name', 'Village name contains inappropriate content.');
    }
    
    // Check for duplicate names in user's villages
    $user_villages = get_user_villages($user_id);
    foreach ($user_villages as $village) {
        if ($village['id'] !== $village_id && 
            strtolower($village['name']) === strtolower($new_name)) {
            return new WP_Error('duplicate_name', 'You already have a village with this name.');
        }
    }
    
    // Check rename token for non-premium
    if (!$is_premium) {
        $rename_tokens = get_user_meta($user_id, 'rename_tokens', 0);
        if ($rename_tokens < 1) {
            return new WP_Error('no_tokens', 
                'You need a rename token. Purchase in shop or upgrade to premium.');
        }
    }
    
    return true;
}, 10);
```
