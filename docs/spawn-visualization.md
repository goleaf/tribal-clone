# Spawn Ring Visualization

## Spawn Ring Expansion Over Time

The spawn system uses expanding rings to cluster players while preventing overcrowding.

### Formula
```
radius = BASE_RADIUS + floor(playerCount / 1000) × GROWTH_PER_K
maxRadius = min(radius + 40, MAP_SIZE / 2)

Where:
- BASE_RADIUS = 80
- GROWTH_PER_K = 20
- MAP_SIZE = 1000
```

### Progression Table

| Player Count | Min Radius | Max Radius | Ring Width | Area (approx) |
|--------------|------------|------------|------------|---------------|
| 0-999        | 80         | 120        | 40         | 12,566 tiles  |
| 1,000-1,999  | 100        | 140        | 40         | 15,708 tiles  |
| 2,000-2,999  | 120        | 160        | 40         | 17,592 tiles  |
| 3,000-3,999  | 140        | 180        | 40         | 20,106 tiles  |
| 4,000-4,999  | 160        | 200        | 40         | 22,619 tiles  |
| 5,000-5,999  | 180        | 220        | 40         | 25,133 tiles  |
| 10,000+      | 280        | 320        | 40         | 37,699 tiles  |

### Visual Representation

```
Map Center: (500, 500)

0-999 Players:
┌─────────────────────────────────┐
│                                 │
│         ╔═══════════╗           │
│         ║           ║           │
│         ║    (●)    ║           │  ● = Center (500,500)
│         ║  r=80-120 ║           │  ═ = Spawn ring
│         ╚═══════════╝           │
│                                 │
└─────────────────────────────────┘

1,000-1,999 Players:
┌─────────────────────────────────┐
│                                 │
│       ╔═════════════════╗       │
│       ║                 ║       │
│       ║      (●)        ║       │
│       ║   r=100-140     ║       │
│       ╚═════════════════╝       │
│                                 │
└─────────────────────────────────┘

5,000+ Players:
┌─────────────────────────────────┐
│   ╔═════════════════════════╗   │
│   ║                         ║   │
│   ║                         ║   │
│   ║         (●)             ║   │
│   ║      r=180-220          ║   │
│   ║                         ║   │
│   ╚═════════════════════════╝   │
└─────────────────────────────────┘
```

## Spawn Distribution

### Polar Coordinate Generation
```
angle = random(0, 2π)
radius = random(minRadius, maxRadius)
x = 500 + radius × cos(angle)
y = 500 + radius × sin(angle)
```

### Example Spawn Points (1000 players)

```
Ring: 100-140 fields from center

     N (500, 360)
      |
      |
W ----●---- E
(360,500) (640,500)
      |
      |
     S (500, 640)

Actual spawns are randomly distributed
within the ring, not just cardinal directions.
```

## Density Management

### Chunk-Based Density Check

Each chunk is 20×20 tiles (400 tiles total).

```
Low Density Chunk (< 10% filled):
┌────────────────────┐
│ ·  ·    ·    ·   · │  · = Village
│    ·       ·       │  (space) = Empty
│ ·       ·      ·   │
│    ·    ·          │
└────────────────────┘

Medium Density Chunk (10-30% filled):
┌────────────────────┐
│ · · ·  · ·  · ·  · │
│ ·   · ·   ·   · ·  │
│  · ·   · ·  ·   ·  │
│ ·  · ·   · ·  ·  · │
└────────────────────┘

High Density Chunk (> 50% filled):
┌────────────────────┐
│ ·· ·· ·· ·· ·· ·· ·│  Spawn algorithm
│ ·· ·· ·· ·· ·· ·· ·│  avoids these chunks
│ ·· ·· ·· ·· ·· ·· ·│
│ ·· ·· ·· ·· ·· ·· ·│
└────────────────────┘
```

## Snap-to-Empty Algorithm

When a spawn coordinate is generated, the system searches for the nearest empty tile.

```
Target: (505, 510)

Search Pattern (radius 5):
┌─────────────┐
│ 5 5 5 5 5 5 │  Numbers indicate
│ 5 4 4 4 4 5 │  search order
│ 5 4 3 3 4 5 │  (distance from target)
│ 5 4 3 2 3 4 │
│ 5 4 3 2 1 3 │
│ 5 4 3 2 ● 2 │  ● = Target (505,510)
│ 5 4 3 2 1 3 │
│ 5 4 3 2 3 4 │
│ 5 4 3 3 4 5 │
│ 5 4 4 4 4 5 │
│ 5 5 5 5 5 5 │
└─────────────┘

First empty tile found is used.
```

## Barbarian Distribution

### 8% Density Example

```
Chunk (20×20 = 400 tiles):
Target: 32 barbarian villages

┌────────────────────┐
│ B   B  B    B   B  │  B = Barbarian village
│   B    B  B    B   │  (space) = Empty or player
│ B    B    B   B  B │
│   B  B      B    B │
│ B    B  B    B   B │  Randomly distributed
│   B    B  B    B   │  within chunk
│ B  B    B    B   B │
│   B    B  B    B   │
│ B    B    B   B  B │
│   B  B      B    B │
└────────────────────┘
```

## Real-World Example

### World with 2,500 Players

```
Spawn Ring: 120-160 fields from center

Map View (simplified):
┌─────────────────────────────────────────┐
│                                         │
│                                         │
│          ╔═══════════════════╗          │
│          ║ · · · · · · · · · ║          │
│          ║ · · · · · · · · · ║          │
│          ║ · · · · ● · · · · ║          │
│          ║ · · · · · · · · · ║          │
│          ║ · · · · · · · · · ║          │
│          ╚═══════════════════╝          │
│                                         │
│                                         │
└─────────────────────────────────────────┘

Legend:
● = Map center (500, 500)
· = Player villages (clustered in ring)
═ = Spawn ring boundaries
```

## Coordinate Examples

### Sample Spawn Coordinates (2000 players, ring 120-160)

```
Angle    Radius  X     Y     Distance from Center
0°       130     630   500   130.0
45°      145     602   602   145.0
90°      125     500   625   125.0
135°     140     401   601   140.7
180°     155     345   500   155.0
225°     130     408   408   130.1
270°     150     500   350   150.0
315°     135     595   405   134.4
```

## Performance Characteristics

### Spawn Attempts Distribution

```
Attempts needed to find empty tile:

1-10 attempts:   ████████████████████ 95%
11-50 attempts:  ███ 4%
51-100 attempts: █ 0.8%
100+ attempts:   · 0.2%

Average: 3-5 attempts
Max allowed: 200 attempts
```

## Density Over Time

```
Map Density Evolution:

Week 1:  [▓░░░░░░░░░] 5%  - Mostly empty
Week 2:  [▓▓░░░░░░░░] 10% - Spawn ring filling
Week 4:  [▓▓▓░░░░░░░] 15% - Multiple rings
Week 8:  [▓▓▓▓░░░░░░] 20% - Expanding outward
Week 12: [▓▓▓▓▓░░░░░] 25% - Mature world

▓ = Occupied
░ = Empty
```

## Chunk Density Heatmap

```
Example 10×10 chunk grid (each cell = 20×20 tiles):

     0   1   2   3   4   5   6   7   8   9
  ┌───┬───┬───┬───┬───┬───┬───┬───┬───┬───┐
0 │ 2 │ 3 │ 5 │ 8 │12 │15 │10 │ 6 │ 4 │ 2 │
  ├───┼───┼───┼───┼───┼───┼───┼───┼───┼───┤
1 │ 3 │ 5 │ 8 │15 │25 │28 │18 │10 │ 6 │ 3 │
  ├───┼───┼───┼───┼───┼───┼───┼───┼───┼───┤
2 │ 5 │ 8 │12 │20 │35 │38 │25 │15 │ 8 │ 5 │
  ├───┼───┼───┼───┼───┼───┼───┼───┼───┼───┤
3 │ 8 │15 │20 │30 │45 │48 │32 │20 │12 │ 8 │
  ├───┼───┼───┼───┼───┼───┼───┼───┼───┼───┤
4 │12 │25 │35 │45 │60 │65 │45 │30 │18 │10 │  Numbers = villages
  ├───┼───┼───┼───┼───┼───┼───┼───┼───┼───┤  per chunk
5 │15 │28 │38 │48 │65 │70 │50 │35 │20 │12 │
  ├───┼───┼───┼───┼───┼───┼───┼───┼───┼───┤  Center (5,5) has
6 │10 │18 │25 │32 │45 │50 │35 │25 │15 │ 8 │  highest density
  ├───┼───┼───┼───┼───┼───┼───┼───┼───┼───┤
7 │ 6 │10 │15 │20 │30 │35 │25 │18 │10 │ 6 │
  ├───┼───┼───┼───┼───┼───┼───┼───┼───┼───┤
8 │ 4 │ 6 │ 8 │12 │18 │20 │15 │10 │ 6 │ 4 │
  ├───┼───┼───┼───┼───┼───┼───┼───┼───┼───┤
9 │ 2 │ 3 │ 5 │ 8 │10 │12 │ 8 │ 6 │ 4 │ 2 │
  └───┴───┴───┴───┴───┴───┴───┴───┴───┴───┘
```

## Summary

The spawn system creates a natural clustering effect:
- ✅ New players spawn near existing players
- ✅ Prevents overcrowding through density checks
- ✅ Expands outward as population grows
- ✅ Maintains playable distances between villages
- ✅ Balances clustering with space for growth
