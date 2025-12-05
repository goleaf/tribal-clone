# Market & Trade Hooks

## 19. before_trade_offer_create

**Trigger Point:** Before creating a market trade offer

**Parameters:**
- `int $village_id` - Village creating the offer
- `array $offering` - Resources being offered (wood, clay, iron)
- `array $requesting` - Resources being requested
- `int $merchant_count` - Number of merchants allocated
- `float $exchange_rate` - Calculated exchange rate

**Return Value:** `bool|WP_Error` - True if valid, error object if invalid

**Use Cases:**
1. Validate fair exchange rates to prevent resource pushing
2. Check merchant availability and market building level
3. Enforce trade limits based on player points difference
4. Prevent exploitative trades between allied players
5. Apply trade fees or taxes

**Example Implementation:**
```php
add_filter('before_trade_offer_create', function($valid, $village_id, $offering, $requesting, $merchant_count) {
    $user_id = get_village_owner($village_id);
    
    // Validate merchant availability
    $available_merchants = get_available_merchants($village_id);
    if ($merchant_count > $available_merchants) {
        return new WP_Error('insufficient_merchants', 
            "Only {$available_merchants} merchants available.");
    }
    
    // Calculate fair exchange rate bounds (0.5 to 2.0)
    $offered_value = array_sum($offering);
    $requested_value = array_sum($requesting);
    $rate = $requested_value / $offered_value;
    
    if ($rate < 0.5 || $rate > 2.0) {
        return new WP_Error('unfair_rate', 
            'Exchange rate must be between 0.5 and 2.0 to prevent resource pushing.');
    }
    
    // Check daily trade limit for non-premium
    if (!has_premium_account($user_id)) {
        $daily_trades = count_user_trades($user_id, 86400);
        if ($daily_trades >= 10) {
            return new WP_Error('trade_limit', 
                'Daily trade limit reached. Upgrade to premium for unlimited trades.');
        }
    }
    
    return true;
}, 10);
```

---

## 20. after_trade_complete

**Trigger Point:** After a trade is successfully completed

**Parameters:**
- `int $sender_village_id` - Village that sent resources
- `int $receiver_village_id` - Village that received resources
- `array $resources_sent` - Resources transferred
- `int $merchant_count` - Merchants used
- `int $completion_time` - Unix timestamp of completion

**Return Value:** `void` (action hook)

**Use Cases:**
1. Return merchants to sender village
2. Update trade statistics and achievements
3. Send completion notifications to both players
4. Track trade routes for analytics
5. Apply merchant experience or bonuses
