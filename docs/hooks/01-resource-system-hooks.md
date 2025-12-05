# Resource System Hooks

## 1. before_resource_production_calculate

**Trigger Point:** Before calculating hourly resource production during tick processing

**Parameters:**
- `int $village_id` - Target village ID
- `array $building_levels` - Associative array of building types and levels
- `int $population` - Current village population
- `string $season` - Current game season (spring/summer/autumn/winter)

**Return Value:** `array` - Modified building levels or production modifiers

**Use Cases:**
1. Apply seasonal production bonuses/penalties (winter -20% farm efficiency)
2. Implement relic bonuses affecting resource production
3. Apply territory control bonuses (+5% production in controlled regions)
4. Add premium account production boosts
5. Implement temporary event-based production modifiers

**Example Implementation:**
```php
add_filter('before_resource_production_calculate', function($building_levels, $village_id, $population, $season) {
    // Apply winter penalty to farm production
    if ($season === 'winter' && isset($building_levels['farm'])) {
        $building_levels['farm'] = floor($building_levels['farm'] * 0.8);
    }
    
    // Check for active production relics
    $active_relics = get_active_relics($village_id, 'production');
    foreach ($active_relics as $relic) {
        if ($relic['bonus_type'] === 'wood_production') {
            $building_levels['timber_camp'] = floor(
                $building_levels['timber_camp'] * (1 + $relic['bonus_value'])
            );
        }
    }
    
    return $building_levels;
}, 10);
```

---

## 2. after_resource_update

**Trigger Point:** After resources are updated in database during tick processing

**Parameters:**
- `int $village_id` - Village that received resources
- `array $resources_added` - Array with keys: wood, clay, iron (amounts added)
- `array $new_totals` - Current resource totals after update
- `bool $storage_capped` - Whether any resource hit storage capacity

**Return Value:** `void` (action hook, no return value)

**Use Cases:**
1. Trigger achievement checks for resource milestones
2. Log resource production for analytics and statistics
3. Send notifications when storage is full
4. Update dynasty prestige based on economic output
5. Track resource generation for quest completion

**Example Implementation:**
```php
add_action('after_resource_update', function($village_id, $resources_added, $new_totals, $storage_capped) {
    $user_id = get_village_owner($village_id);
    
    // Check achievement: produce 1 million total resources
    $lifetime_production = get_user_meta($user_id, 'lifetime_resource_production', 0);
    $lifetime_production += array_sum($resources_added);
    update_user_meta($user_id, 'lifetime_resource_production', $lifetime_production);
    
    if ($lifetime_production >= 1000000 && !has_achievement($user_id, 'economic_powerhouse')) {
        grant_achievement($user_id, 'economic_powerhouse');
    }
    
    // Notify if storage capped
    if ($storage_capped) {
        create_notification($user_id, 'warning', 
            "Storage full in village {$village_id}. Upgrade warehouse!");
    }
}, 10);
```

---

## 3. validate_resource_expenditure

**Trigger Point:** Before deducting resources for any action (building, recruiting, trading)

**Parameters:**
- `int $village_id` - Village spending resources
- `array $costs` - Required resources (wood, clay, iron)
- `string $action_type` - Type of expenditure (building/troop/trade/research)
- `array $action_data` - Additional context (building_type, unit_type, etc.)

**Return Value:** `bool|WP_Error` - True if valid, WP_Error object with message if invalid

**Use Cases:**
1. Prevent negative resource exploits
2. Apply discount modifiers (premium accounts, research bonuses)
3. Validate sufficient resources exist
4. Check for resource protection (hiding place mechanics)
5. Log suspicious resource spending patterns for anti-cheat

**Example Implementation:**
```php
add_filter('validate_resource_expenditure', function($valid, $village_id, $costs, $action_type, $action_data) {
    $current = get_village_resources($village_id);
    $user_id = get_village_owner($village_id);
    
    // Apply premium discount if applicable
    if (has_premium_account($user_id) && $action_type === 'building') {
        $costs = array_map(fn($cost) => floor($cost * 0.9), $costs);
    }
    
    // Validate sufficient resources
    foreach (['wood', 'clay', 'iron'] as $resource) {
        if ($current[$resource] < $costs[$resource]) {
            return new WP_Error('insufficient_resources', 
                "Not enough {$resource}. Need {$costs[$resource]}, have {$current[$resource]}.");
        }
    }
    
    // Anti-cheat: detect rapid spending patterns
    $recent_spending = get_recent_spending($village_id, 60); // Last 60 seconds
    if ($recent_spending > 100000) {
        log_suspicious_activity($user_id, 'rapid_resource_spending', $recent_spending);
    }
    
    return true;
}, 10);
```
