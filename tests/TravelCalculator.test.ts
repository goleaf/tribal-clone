import {
  distance,
  slowestSpeed,
  calcArrival,
  clampCoordinates,
  formatTravelTime,
  UNIT_SPEED,
  type Coordinates,
  type UnitComposition
} from '../lib/utils/TravelCalculator';

describe('TravelCalculator', () => {
  describe('distance', () => {
    it('calculates Euclidean distance correctly', () => {
      const a: Coordinates = { x: 500, y: 500 };
      const b: Coordinates = { x: 503, y: 504 };
      expect(distance(a, b)).toBe(5); // 3-4-5 triangle
    });

    it('returns 0 for same coordinates', () => {
      const a: Coordinates = { x: 100, y: 100 };
      expect(distance(a, a)).toBe(0);
    });
  });

  describe('slowestSpeed', () => {
    it('returns slowest unit speed', () => {
      const units: UnitComposition = {
        spear: 100,
        scout: 50,
        ram: 10
      };
      expect(slowestSpeed(units)).toBe(30); // ram is slowest
    });

    it('ignores units with zero count', () => {
      const units: UnitComposition = {
        scout: 50,
        ram: 0
      };
      expect(slowestSpeed(units)).toBe(9); // scout only
    });

    it('returns 0 for empty army', () => {
      expect(slowestSpeed({})).toBe(0);
    });
  });

  describe('calcArrival', () => {
    const now = new Date('2025-12-01T12:00:00Z');

    it('calculates arrival time correctly', () => {
      const from: Coordinates = { x: 500, y: 500 };
      const to: Coordinates = { x: 510, y: 500 };
      const units: UnitComposition = { spear: 100 };

      const result = calcArrival(from, to, units, now);

      expect(result.distance).toBe(10);
      expect(result.travelMinutes).toBe(180); // 10 fields * 18 min/field
      expect(result.arrival.getTime()).toBe(now.getTime() + 180 * 60_000);
    });

    it('applies world speed modifier', () => {
      const from: Coordinates = { x: 500, y: 500 };
      const to: Coordinates = { x: 510, y: 500 };
      const units: UnitComposition = { spear: 100 };
      const worldSpeed = 2.0;

      const result = calcArrival(from, to, units, now, worldSpeed);

      expect(result.travelMinutes).toBe(90); // 180 / 2
    });

    it('throws error for same coordinates', () => {
      const coords: Coordinates = { x: 500, y: 500 };
      const units: UnitComposition = { spear: 100 };

      expect(() => calcArrival(coords, coords, units, now)).toThrow(
        'Cannot travel to the same coordinates'
      );
    });

    it('throws error for no units', () => {
      const from: Coordinates = { x: 500, y: 500 };
      const to: Coordinates = { x: 510, y: 500 };

      expect(() => calcArrival(from, to, {}, now)).toThrow('No units selected');
    });
  });

  describe('clampCoordinates', () => {
    it('clamps coordinates within bounds', () => {
      const coords: Coordinates = { x: 1500, y: -10 };
      const clamped = clampCoordinates(coords, 1000, 1000);

      expect(clamped).toEqual({ x: 1000, y: 0 });
    });

    it('leaves valid coordinates unchanged', () => {
      const coords: Coordinates = { x: 500, y: 500 };
      const clamped = clampCoordinates(coords, 1000, 1000);

      expect(clamped).toEqual(coords);
    });
  });

  describe('formatTravelTime', () => {
    it('formats hours, minutes, and seconds', () => {
      expect(formatTravelTime(150)).toBe('2h 30m 0s');
    });

    it('formats minutes and seconds only', () => {
      expect(formatTravelTime(5.5)).toBe('5m 30s');
    });

    it('formats seconds only', () => {
      expect(formatTravelTime(0.5)).toBe('30s');
    });

    it('handles zero time', () => {
      expect(formatTravelTime(0)).toBe('0s');
    });
  });
});
