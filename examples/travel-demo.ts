/**
 * Standalone demo of the Travel Calculator
 * Run with: npx ts-node examples/travel-demo.ts
 */

import {
  calcArrival,
  formatTravelTime,
  distance,
  slowestSpeed,
  type Coordinates,
  type UnitComposition
} from '../lib/utils/TravelCalculator';

console.log('=== Travel Time Calculator Demo ===\n');

// Example 1: Mixed army attack
console.log('Example 1: Mixed Army Attack');
const village1: Coordinates = { x: 500, y: 500 };
const village2: Coordinates = { x: 520, y: 515 };
const army: UnitComposition = {
  spear: 1000,
  sword: 500,
  archer: 300,
  ram: 10
};

const result1 = calcArrival(village1, village2, army);
console.log(`  From: (${village1.x}, ${village1.y})`);
console.log(`  To: (${village2.x}, ${village2.y})`);
console.log(`  Distance: ${result1.distance.toFixed(2)} fields`);
console.log(`  Travel time: ${formatTravelTime(result1.travelMinutes)}`);
console.log(`  Arrival: ${result1.arrival.toLocaleString()}`);
console.log(`  Slowest unit: Ram (30 min/field)\n`);

// Example 2: Scout mission
console.log('Example 2: Scout Mission (Fast)');
const scoutArmy: UnitComposition = { scout: 100 };
const result2 = calcArrival(village1, village2, scoutArmy);
console.log(`  Same distance: ${result2.distance.toFixed(2)} fields`);
console.log(`  Travel time: ${formatTravelTime(result2.travelMinutes)}`);
console.log(`  Arrival: ${result2.arrival.toLocaleString()}`);
console.log(`  Slowest unit: Scout (9 min/field)\n`);

// Example 3: With world speed modifier
console.log('Example 3: 2x World Speed');
const worldSpeed = 2.0;
const result3 = calcArrival(village1, village2, army, new Date(), worldSpeed);
console.log(`  Same army, 2x speed world`);
console.log(`  Travel time: ${formatTravelTime(result3.travelMinutes)}`);
console.log(`  (Half the normal time)\n`);

// Example 4: Long distance noble train
console.log('Example 4: Noble Train (Very Slow)');
const farVillage: Coordinates = { x: 600, y: 600 };
const nobleArmy: UnitComposition = {
  spear: 5000,
  noble: 1
};
const result4 = calcArrival(village1, farVillage, nobleArmy);
console.log(`  Distance: ${result4.distance.toFixed(2)} fields`);
console.log(`  Travel time: ${formatTravelTime(result4.travelMinutes)}`);
console.log(`  Arrival: ${result4.arrival.toLocaleString()}`);
console.log(`  Slowest unit: Noble (35 min/field)\n`);

// Example 5: Speed comparison
console.log('Example 5: Speed Comparison (10 fields)');
const start: Coordinates = { x: 500, y: 500 };
const end: Coordinates = { x: 510, y: 500 };

const unitTypes = [
  { name: 'Scout', units: { scout: 1 } },
  { name: 'Light Cavalry', units: { light: 1 } },
  { name: 'Spear', units: { spear: 1 } },
  { name: 'Sword', units: { sword: 1 } },
  { name: 'Ram', units: { ram: 1 } },
  { name: 'Noble', units: { noble: 1 } }
];

unitTypes.forEach(({ name, units }) => {
  const result = calcArrival(start, end, units);
  console.log(`  ${name.padEnd(15)}: ${formatTravelTime(result.travelMinutes)}`);
});

console.log('\n=== Demo Complete ===');
