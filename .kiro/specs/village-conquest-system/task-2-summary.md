# Task 2 Summary: World Configuration System

## Status: ✅ Complete

## Overview

Implemented a comprehensive world configuration system for the village conquest feature, supporting both allegiance-drop and control-uptime modes with full per-world configurability.

## Requirements Addressed

- **10.1**: Support for both allegiance-drop and control-uptime conquest modes ✅
- **10.2**: Configurable regeneration rates, caps, and modifiers ✅
- **10.3**: Feature flags (FEATURE_CONQUEST_UNIT_ENABLED, etc.) ✅
- **10.4**: Configurable wave spacing requirements ✅
- **10.5**: Configurable Envoy costs and limits ✅

## Implementation Details

### 1. Database Schema

**Migration**: `migrations/add_conquest_world_config.php`

Added 24 conquest configuration columns to the `worlds` table:

**Core Settings**:
- `conquest_enabled` - Enable/disable conquest system
- `conquest_mode` - Mode selection ('allegiance' or 'control')

**Allegiance Mode**:
- `alleg_regen_per_hour` - Regeneration rate (default: 2.0)
- `alleg_wall_reduction_per_level` - Wall reduction factor (default: 0.02)
- `alleg_drop_min` - Minimum drop per Envoy (default: 18)
- `alleg_drop_max` - Maximum drop per Envoy (default: 28)

**Anti-Snipe Protection**:
- `anti_snipe_floor` - Minimum allegiance during grace (default: 10)
- `anti_snipe_seconds` - Grace period duration (default: 900)
- `post_capture_start` - Starting allegiance after capture (default: 25)
- `capture_cooldown_seconds` - Cooldown before recapture (default: 900)

**Control Mode**:
- `uptime_duration_seconds` - Required uptime (default: 900)
- `control_gain_rate_per_min` - Gain rate (default: 5)
- `control_decay_rate_per_min` - Decay rate (default: 3)

**Wave and Training Limits**:
- `wave_spacing_ms` - Minimum wave spacing (default: 300)
- `max_envoys_per_command` - Max Envoys per command (default: 1)
- `conquest_daily_mint_cap` - Daily mint cap (default: 5)
- `conquest_daily_train_cap` - Daily train cap (default: 3)
- `conquest_min_defender_points` - Min defender points (default: 1000)

**Optional Features**:
- `conquest_building_loss_enabled` - Enable building loss (default: 0)
- `conquest_building_loss_chance` - Loss probability (default: 0.100)
- `conquest_resource_transfer_pct` - Transfer percentage (default: 1.000)
- `conquest_abandonment_decay_enabled` - Enable abandonment decay (default: 0)
- `conquest_abandonment_threshold_hours` - Abandonment threshold (default: 168)
- `conquest_abandonment_decay_rate` - Decay rate (default: 1.0)

### 2. WorldConfigManager Class

**File**: `lib/managers/WorldConfigManager.php`

Created a dedicated configuration manager that wraps `WorldManager` and provides:

**Mode Detection Methods**:
- `isConquestEnabled()` - Check if conquest is enabled
- `getConquestMode()` - Get mode ('allegiance' or 'control')
- `isAllegianceMode()` - Check if allegiance mode
- `isControlMode()` - Check if control mode

**Allegiance Settings**:
- `getAllegianceRegenRate()` - Get regeneration rate
- `getWallReductionFactor()` - Get wall reduction factor
- `getAllegianceDropRange()` - Get drop range [min, max]

**Anti-Snipe Settings**:
- `getAntiSnipeFloor()` - Get floor value
- `getAntiSnipeDuration()` - Get duration in seconds
- `getPostCaptureStart()` - Get starting allegiance
- `getCaptureCooldown()` - Get cooldown duration

**Control Mode Settings**:
- `getUptimeDuration()` - Get uptime duration
- `getControlGainRate()` - Get gain rate per minute
- `getControlDecayRate()` - Get decay rate per minute

**Wave and Training Limits**:
- `getWaveSpacing()` - Get wave spacing in ms
- `getMaxEnvoysPerCommand()` - Get max Envoys per command
- `getDailyMintCap()` - Get daily mint cap
- `getDailyTrainCap()` - Get daily train cap
- `getMinDefenderPoints()` - Get min defender points

**Optional Features**:
- `isBuildingLossEnabled()` - Check if building loss enabled
- `getBuildingLossChance()` - Get loss probability
- `getResourceTransferPercent()` - Get transfer percentage
- `isAbandonmentDecayEnabled()` - Check if abandonment decay enabled
- `getAbandonmentThreshold()` - Get abandonment threshold
- `getAbandonmentDecayRate()` - Get decay rate

**Feature Flags**:
- `isEnvoyEnabled()` - Check FEATURE_CONQUEST_UNIT_ENABLED
- `isAntiSnipeEnabled()` - Check CONQUEST_ANTI_SNIPE_ENABLED
- `isWallModifierEnabled()` - Check CONQUEST_WALL_MOD_ENABLED

**Validation**:
- `validateConfig()` - Validate all configuration settings
- Returns `['valid' => bool, 'errors' => string[]]`

**Complete Configuration**:
- `getConquestConfig()` - Get all 24 settings at once
- Results are cached per request for performance

### 3. Integration with WorldManager

The `WorldManager` class already had conquest configuration support built in:

- `getConquestSettings()` - Returns all conquest settings
- `isConquestEnabled()` - Check if conquest enabled
- `getConquestMode()` - Get conquest mode
- `isConquestUnitEnabled()` - Check Envoy unit flag
- `isConquestAntiSnipeEnabled()` - Check anti-snipe flag
- `isConquestWallModifierEnabled()` - Check wall modifier flag

The `WorldConfigManager` wraps these methods and adds conquest-specific convenience methods.

### 4. Testing

**Test File**: `tests/world_config_manager_test.php`

Comprehensive test suite with 11 tests covering:

1. ✅ Get conquest configuration
2. ✅ Check conquest enabled flag
3. ✅ Get conquest mode
4. ✅ Check mode detection methods
5. ✅ Get allegiance settings
6. ✅ Get anti-snipe settings
7. ✅ Get control mode settings
8. ✅ Get wave and training limits
9. ✅ Get optional features
10. ✅ Validate configuration
11. ✅ Feature flags

**Test Results**: All 11 tests passed ✅

### 5. Documentation

**Quick Reference**: `docs/conquest-config-quick-reference.md`

Comprehensive documentation including:
- Configuration field reference table
- Method signatures for all WorldConfigManager methods
- Usage examples
- Feature flag documentation
- Migration information
- Testing instructions

**Usage Example**: `examples/world_config_usage.php`

Complete working example demonstrating:
- Checking if conquest is enabled
- Determining conquest mode
- Getting allegiance settings
- Getting anti-snipe settings
- Getting control mode settings
- Getting training and wave limits
- Checking optional features
- Validating configuration
- Getting complete configuration
- Checking feature flags

## Files Created

1. `migrations/add_conquest_world_config.php` - Database migration
2. `lib/managers/WorldConfigManager.php` - Configuration manager class
3. `tests/world_config_manager_test.php` - Test suite
4. `examples/world_config_usage.php` - Usage examples
5. `docs/conquest-config-quick-reference.md` - Documentation
6. `.kiro/specs/village-conquest-system/task-2-summary.md` - This summary

## Key Features

### Mode Flexibility
- Supports both allegiance-drop and control-uptime modes
- Mode can be configured per world
- Easy mode detection with `isAllegianceMode()` and `isControlMode()`

### Type Safety
- All methods have explicit return types
- Configuration values are properly typed (int, float, bool, string)
- Validation ensures configuration integrity

### Performance
- Configuration is cached per request
- Single database query loads all settings
- Efficient access through dedicated methods

### Extensibility
- Easy to add new configuration fields
- Clean separation between WorldManager and WorldConfigManager
- Feature flags allow gradual rollout

### Validation
- Comprehensive validation of all settings
- Range checks for numeric values
- Mode-specific validation
- Clear error messages

## Usage Pattern

```php
// Initialize
$configManager = new WorldConfigManager($conn);

// Check if conquest is enabled
if (!$configManager->isConquestEnabled()) {
    return; // Conquest disabled
}

// Get mode-specific settings
if ($configManager->isAllegianceMode()) {
    $regenRate = $configManager->getAllegianceRegenRate();
    $dropRange = $configManager->getAllegianceDropRange();
    // Use allegiance mechanics
} else {
    $uptimeDuration = $configManager->getUptimeDuration();
    $gainRate = $configManager->getControlGainRate();
    // Use control mechanics
}

// Get common settings
$maxEnvoys = $configManager->getMaxEnvoysPerCommand();
$waveSpacing = $configManager->getWaveSpacing();
```

## Next Steps

This configuration system is now ready to be used by:
- Task 3: AllegianceService (will use regen rates, drop ranges, etc.)
- Task 4: ConquestStateMachine (will use caps, limits, feature flags)
- Task 5: TrainingPipeline (will use training caps, costs)
- Task 6: Combat integration (will use wall modifiers, mode detection)
- All subsequent conquest system components

## Verification

✅ Migration runs successfully  
✅ All 11 tests pass  
✅ Example code runs without errors  
✅ Configuration validation works correctly  
✅ Both allegiance and control modes supported  
✅ Feature flags accessible  
✅ Documentation complete  

## Notes

- The WorldManager already had conquest configuration support, so we leveraged that
- WorldConfigManager provides a cleaner, conquest-focused interface
- All configuration has sensible defaults for backward compatibility
- The system is fully extensible for future configuration needs

