/**
 * Travel Time Calculator
 * Calculates unit travel times based on distance and unit composition
 */

export type Unit = 
  | 'spear' 
  | 'sword' 
  | 'axe' 
  | 'archer' 
  | 'scout' 
  | 'light' 
  | 'heavy' 
  | 'ram' 
  | 'catapult' 
  | 'noble';

export type UnitSpeed = Record<Unit, number>;

export type Coordinates = {
  x: number;
  y: number;
};

export type UnitComposition = Partial<Record<Unit, number>>;

export interface TravelResult {
  distance: number;
  travelMinutes: number;
  arrival: Date;
}

/**
 * Unit speeds in minutes per field (base speed without world modifiers)
 */
export const UNIT_SPEED: UnitSpeed = {
  spear: 18,
  sword: 22,
  axe: 18,
  archer: 18,
  scout: 9,
  light: 10,
  heavy: 11,
  ram: 30,
  catapult: 30,
  noble: 35
};

/**
 * Calculate Euclidean distance between two coordinates
 * @param a Starting coordinates
 * @param b Target coordinates
 * @returns Distance in fields
 */
export function distance(a: Coordinates, b: Coordinates): number {
  return Math.hypot(b.x - a.x, b.y - a.y);
}

/**
 * Find the slowest unit speed in the army composition
 * @param units Unit composition with counts
 * @returns Minutes per field for the slowest unit, or 0 if no units
 */
export function slowestSpeed(units: UnitComposition): number {
  let max = 0;
  for (const [unit, count] of Object.entries(units)) {
    if (count && count > 0) {
      max = Math.max(max, UNIT_SPEED[unit as Unit]);
    }
  }
  return max;
}

/**
 * Calculate arrival time for unit movement
 * @param from Starting coordinates
 * @param to Target coordinates
 * @param units Unit composition
 * @param now Current server time (defaults to now)
 * @param worldSpeed World speed modifier (default 1.0)
 * @returns Travel calculation result
 * @throws Error if no units selected or invalid coordinates
 */
export function calcArrival(
  from: Coordinates,
  to: Coordinates,
  units: UnitComposition,
  now: Date = new Date(),
  worldSpeed: number = 1.0
): TravelResult {
  // Validate same coordinates
  if (from.x === to.x && from.y === to.y) {
    throw new Error("Cannot travel to the same coordinates");
  }

  const dist = distance(from, to);
  let minutesPerField = slowestSpeed(units);

  if (!minutesPerField) {
    throw new Error("No units selected");
  }

  // Apply world speed modifier (faster world = shorter time)
  if (worldSpeed > 0) {
    minutesPerField = minutesPerField / worldSpeed;
  }

  const travelMinutes = dist * minutesPerField;
  const arrival = new Date(now.getTime() + travelMinutes * 60_000);

  return {
    distance: dist,
    travelMinutes,
    arrival
  };
}

/**
 * Validate coordinates are within map bounds
 * @param coords Coordinates to validate
 * @param maxX Maximum X coordinate (inclusive)
 * @param maxY Maximum Y coordinate (inclusive)
 * @returns Clamped coordinates
 */
export function clampCoordinates(
  coords: Coordinates,
  maxX: number,
  maxY: number
): Coordinates {
  return {
    x: Math.max(0, Math.min(coords.x, maxX)),
    y: Math.max(0, Math.min(coords.y, maxY))
  };
}

/**
 * Format travel time as human-readable string
 * @param minutes Total travel time in minutes
 * @returns Formatted string (e.g., "2h 30m 15s")
 */
export function formatTravelTime(minutes: number): string {
  const totalSeconds = Math.round(minutes * 60);
  const hours = Math.floor(totalSeconds / 3600);
  const mins = Math.floor((totalSeconds % 3600) / 60);
  const secs = totalSeconds % 60;

  const parts: string[] = [];
  if (hours > 0) parts.push(`${hours}h`);
  if (mins > 0) parts.push(`${mins}m`);
  if (secs > 0 || parts.length === 0) parts.push(`${secs}s`);

  return parts.join(' ');
}
