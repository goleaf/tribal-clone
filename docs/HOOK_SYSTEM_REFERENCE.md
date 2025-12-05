# Hook System Reference

Production-ready programmatic hooks for Tribal Wars-inspired strategy game built with PHP 8.4+, following WordPress-style action/filter patterns.

## Table of Contents

1. [Resource System Hooks](#resource-system-hooks) (3 hooks)
2. [Building System Hooks](#building-system-hooks) (3 hooks)
3. [Combat System Hooks](#combat-system-hooks) (3 hooks)
4. [Troop System Hooks](#troop-system-hooks) (3 hooks)
5. [Village System Hooks](#village-system-hooks) (3 hooks)
6. [Alliance System Hooks](#alliance-system-hooks) (3 hooks)
7. [Market & Trade Hooks](#market--trade-hooks) (3 hooks)
8. [Research System Hooks](#research-system-hooks) (3 hooks)
9. [Movement System Hooks](#movement-system-hooks) (3 hooks)
10. [Notification System Hooks](#notification-system-hooks) (3 hooks)
11. [Security & Anti-Cheat Hooks](#security--anti-cheat-hooks) (3 hooks)

## Hook Implementation Pattern

```php
// Registering hook handlers
add_action('hook_name', 'callback_function', $priority = 10);
add_filter('hook_name', 'callback_function', $priority = 10);

// Executing hooks
do_action('hook_name', $param1, $param2); // Actions don't return values
$result = apply_filters('hook_name', $value, $param1); // Filters modify and return values
```

---

## Resource System Hooks

### 1. before_resource_production_calculate

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
