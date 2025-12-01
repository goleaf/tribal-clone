# Travel Calculator Examples

## Basic Usage

```typescript
import { calcArrival, formatTravelTime } from '../lib/utils/TravelCalculator';

// Example 1: Simple attack calculation
const from = { x: 500, y: 500 };
const to = { x: 520, y: 515 };
const units = {
  spear: 1000,
  sword: 500,
  ram: 10
};

const result = calcArrival(from, to, units);
console.log(`Distance: ${result.distance.toFixed(2)} fields`);
console.log(`Travel time: ${formatTravelTime(result.travelMinutes)}`);
console.log(`Arrival: ${result.arrival.toISOString()}`);
// Output:
// Distance: 25.00 fields
// Travel time: 12h 30m 0s
// Arrival: 2025-12-01T00:30:00.000Z
```

## With World Speed

```typescript
// Example 2: Fast world (2x speed)
const worldSpeed = 2.0;
const result = calcArrival(from, to, units, new Date(), worldSpeed);
console.log(`Travel time: ${formatTravelTime(result.travelMinutes)}`);
// Output: Travel time: 6h 15m 0s (half the time)
```

## Scout Rush

```typescript
// Example 3: Fast scout attack
const scoutUnits = { scout: 100 };
const result = calcArrival(from, to, scoutUnits);
console.log(`Scout arrival: ${formatTravelTime(result.travelMinutes)}`);
// Output: Scout arrival: 3h 45m 0s (scouts are 9 min/field)
```

## Return Trip

```typescript
// Example 4: Calculate return time with surviving units
const returningUnits = {
  spear: 800,  // Some died in battle
  sword: 400,
  ram: 8
};

const returnResult = calcArrival(to, from, returningUnits);
console.log(`Return time: ${formatTravelTime(returnResult.travelMinutes)}`);
// Same logic applies - slowest unit (ram) determines speed
```

## Coordinate Validation

```typescript
import { clampCoordinates } from '../lib/utils/TravelCalculator';

// Example 5: Ensure coordinates are within map bounds
const mapSize = 1000;
const userInput = { x: 1500, y: -50 };
const validCoords = clampCoordinates(userInput, mapSize, mapSize);
console.log(validCoords);
// Output: { x: 1000, y: 0 }
```

## Unit Speed Reference

| Unit     | Speed (min/field) | Relative Speed |
|----------|-------------------|----------------|
| Scout    | 9                 | Fastest        |
| Light    | 10                | Very Fast      |
| Heavy    | 11                | Fast           |
| Spear    | 18                | Medium         |
| Axe      | 18                | Medium         |
| Archer   | 18                | Medium         |
| Sword    | 22                | Slow           |
| Ram      | 30                | Very Slow      |
| Catapult | 30                | Very Slow      |
| Noble    | 35                | Slowest        |

## Integration Tips

### Server-Side (PHP)
You can port this logic to PHP for server-side validation:

```php
function calcArrival($from, $to, $units, $worldSpeed = 1.0) {
    $unitSpeeds = [
        'spear' => 18, 'sword' => 22, 'axe' => 18,
        'archer' => 18, 'scout' => 9, 'light' => 10,
        'heavy' => 11, 'ram' => 30, 'catapult' => 30,
        'noble' => 35
    ];
    
    $distance = sqrt(pow($to['x'] - $from['x'], 2) + pow($to['y'] - $from['y'], 2));
    
    $slowest = 0;
    foreach ($units as $unit => $count) {
        if ($count > 0) {
            $slowest = max($slowest, $unitSpeeds[$unit]);
        }
    }
    
    if ($slowest === 0) throw new Exception("No units selected");
    
    $minutesPerField = $slowest / $worldSpeed;
    $travelMinutes = $distance * $minutesPerField;
    
    return [
        'distance' => $distance,
        'travelMinutes' => $travelMinutes,
        'arrival' => time() + ($travelMinutes * 60)
    ];
}
```

### Client-Side Display
```typescript
// Show live countdown to arrival
function updateCountdown(arrivalDate: Date) {
  const now = new Date();
  const remaining = arrivalDate.getTime() - now.getTime();
  
  if (remaining <= 0) {
    return "Arrived!";
  }
  
  const minutes = remaining / 60_000;
  return formatTravelTime(minutes);
}
```
