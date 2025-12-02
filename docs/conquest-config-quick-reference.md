# Conquest Configuration Quick Reference

## Overview

The conquest system is fully configurable per world through the `worlds` table. Configuration is accessed via `WorldConfigManager`, which provides a clean, type-safe interface to all conquest settings.

## Requirements Coverage

- **10.1**: Support for both allegiance-drop and control-uptime modes
- **10.2**: Configurable regeneration rates, caps, and modifiers
- **10.3**: Feature flags (FEATURE_CONQUEST_UNIT_ENABLED, etc.)
- **10.4**: Configurable wave spacing requirements
- **10.5**: Configurable Envoy costs and limits

## Usage

```php
require_once __DIR__ . '/lib/managers/WorldConfigManager.php';

$configManager = new WorldConfigManager($conn);

// Check if conquest is enabled
if ($configManager->isConquestEnabled()) {
    // Get conquest mode
    $mode = $configManager->getConquestMode(); // 'allegiance' or 'control'
    
    // Get specific settings
    $regenRate = $configManager->getAllegianceRegenRate();
    $maxEnvoys = $configManager->getMaxEnvoysPerCommand();
}
```

## Configuration Fields

### Core Settings

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `conquest_enabled` | boolean | 1 | Enable/disable conquest system |
| `conquest_mode` | string | 'allegiance' | Mode: 'allegiance' or 'control' |

### Allegiance Mode Settings

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `alleg_regen_per_hour` | float | 2.0 | Regeneration rate (% per hour) |
| `alleg_wall_reduction_per_level` | float | 0.02 | Wall reduction factor per level |
| `alleg_drop_min` | int | 18 | Minimum allegiance drop per Envoy |
| `alleg_drop_max` | int | 28 | Maximum allegiance drop per Envoy |

### Anti-Snipe Protection

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `anti_snipe_floor` | int | 10 | Minimum allegiance during grace period |
| `anti_snipe_seconds` | int | 900 | Grace period duration (15 min) |
| `post_capture_start` | int | 25 | Starting allegiance after capture |
| `capture_cooldown_seconds` | int | 900 | Cooldown before recapture (15 min) |

### Control Mode Settings

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `uptime_duration_seconds` | int | 900 | Required uptime for capture (15 min) |
| `control_gain_rate_per_min` | int | 5 | Control gain rate (% per minute) |
| `control_decay_rate_per_min` | int | 3 | Control decay rate (% per minute) |

### Wave and Training Limits

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `wave_spacing_ms` | int | 300 | Minimum wave spacing (milliseconds) |
| `max_envoys_per_command` | int | 1 | Maximum Envoys per command |
| `conquest_daily_mint_cap` | int | 5 | Daily influence crest minting cap |
| `conquest_daily_train_cap` | int | 3 | Daily Envoy training cap |
| `conquest_min_defender_points` | int | 1000 | Minimum defender points for conquest |

### Optional Features

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `conquest_building_loss_enabled` | boolean | 0 | Enable building loss on capture |
| `conquest_building_loss_chance` | float | 0.100 | Building loss probability (10%) |
| `conquest_resource_transfer_pct` | float | 1.000 | Resource transfer percentage (100%) |
| `conquest_abandonment_decay_enabled` | boolean | 0 | Enable abandonment decay |
| `conquest_abandonment_threshold_hours` | int | 168 | Abandonment threshold (7 days) |
| `conquest_abandonment_decay_rate` | float | 1.0 | Abandonment decay rate (% per hour) |

## WorldConfigManager Methods

### Mode Detection

```php
$configManager->isConquestEnabled($worldId): bool
$configManager->getConquestMode($worldId): string
$configManager->isAllegianceMode($worldId): bool
$configManager->isControlMode($worldId): bool
```

### Allegiance Settings

```php
$configManager->getAllegianceRegenRate($worldId): float
$configManager->getWallReductionFactor($worldId): float
$configManager->getAllegianceDropRange($worldId): array // ['min' => int, 'max' => int]
```

### Anti-Snipe Settings

```php
$configManager->getAntiSnipeFloor($worldId): int
$configManager->getAntiSnipeDuration($worldId): int
$configManager->getPostCaptureStart($worldId): int
$configManager->getCaptureCooldown($worldId): int
```

### Control Mode Settings

```php
$configManager->getUptimeDuration($worldId): int
$configManager->getControlGainRate($worldId): int
$configManager->getControlDecayRate($worldId): int
```

### Wave and Training Limits

```php
$configManager->getWaveSpacing($worldId): int
$configManager->getMaxEnvoysPerCommand($worldId): int
$configManager->getDailyMintCap($worldId): int
$configManager->getDailyTrainCap($worldId): int
$configManager->getMinDefenderPoints($worldId): int
```

### Optional Features

```php
$configManager->isBuildingLossEnabled($worldId): bool
$configManager->getBuildingLossChance($worldId): float
$configManager->getResourceTransferPercent($worldId): float
$configManager->isAbandonmentDecayEnabled($worldId): bool
$configManager->getAbandonmentThreshold($worldId): int
$configManager->getAbandonmentDecayRate($worldId): float
```

### Feature Flags

```php
$configManager->isEnvoyEnabled($worldId): bool
$configManager->isAntiSnipeEnabled($worldId): bool
$configManager->isWallModifierEnabled($worldId): bool
```

### Validation

```php
$validation = $configManager->validateConfig($worldId);
// Returns: ['valid' => bool, 'errors' => string[]]
```

### Complete Configuration

```php
$config = $configManager->getConquestConfig($worldId);
// Returns: array with all 24 conquest settings
```

## Feature Flags

Feature flags can be set via PHP constants or world configuration:

- `FEATURE_CONQUEST_ENABLED`: Enable/disable conquest system globally
- `FEATURE_CONQUEST_UNIT_ENABLED`: Enable/disable Envoy unit
- `CONQUEST_ANTI_SNIPE_ENABLED`: Enable/disable anti-snipe protection
- `CONQUEST_WALL_MOD_ENABLED`: Enable/disable wall modifiers
- `CONQUEST_MODE`: Default conquest mode ('allegiance' or 'control')

## Migration

The conquest configuration columns are automatically added to the `worlds` table by:

1. `WorldManager::ensureSchema()` - Auto-adds columns on first access
2. `migrations/add_conquest_world_config.php` - Explicit migration script

All columns have sensible defaults for backward compatibility.

## Examples

See `examples/world_config_usage.php` for complete usage examples.

## Testing

Run the test suite:

```bash
php tests/world_config_manager_test.php
```

All 11 tests should pass, covering:
- Configuration loading
- Mode detection
- Setting retrieval
- Validation
- Feature flags

## Architecture

```
WorldConfigManager
    ↓ wraps
WorldManager
    ↓ reads from
worlds table (conquest_* columns)
```

The `WorldConfigManager` provides a conquest-focused interface while `WorldManager` handles the underlying database operations and caching.

