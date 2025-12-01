<?php
declare(strict_types=1);

/**
 * Example: Using WorldConfigManager for Conquest Configuration
 * 
 * This example demonstrates how to use WorldConfigManager to access
 * conquest system configuration in a clean, type-safe way.
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/WorldConfigManager.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    die("No database connection available.\n");
}

echo "=== WorldConfigManager Usage Examples ===\n\n";

// Initialize the config manager
$configManager = new WorldConfigManager($conn);

// Example 1: Check if conquest is enabled
echo "1. Checking if conquest is enabled:\n";
if ($configManager->isConquestEnabled()) {
    echo "   ✓ Conquest system is enabled\n";
} else {
    echo "   ✗ Conquest system is disabled\n";
}
echo "\n";

// Example 2: Determine conquest mode
echo "2. Determining conquest mode:\n";
$mode = $configManager->getConquestMode();
echo "   Mode: {$mode}\n";

if ($configManager->isAllegianceMode()) {
    echo "   Using allegiance-drop mechanics\n";
} elseif ($configManager->isControlMode()) {
    echo "   Using control-uptime mechanics\n";
}
echo "\n";

// Example 3: Get allegiance settings (for allegiance mode)
echo "3. Allegiance mode settings:\n";
$regenRate = $configManager->getAllegianceRegenRate();
$wallFactor = $configManager->getWallReductionFactor();
$dropRange = $configManager->getAllegianceDropRange();

echo "   Regeneration: {$regenRate}% per hour\n";
echo "   Wall reduction: " . ($wallFactor * 100) . "% per level\n";
echo "   Drop range: {$dropRange['min']}-{$dropRange['max']} per Envoy\n";
echo "\n";

// Example 4: Get anti-snipe protection settings
echo "4. Anti-snipe protection:\n";
$floor = $configManager->getAntiSnipeFloor();
$duration = $configManager->getAntiSnipeDuration();
$postStart = $configManager->getPostCaptureStart();
$cooldown = $configManager->getCaptureCooldown();

echo "   Floor value: {$floor}%\n";
echo "   Protection duration: " . ($duration / 60) . " minutes\n";
echo "   Post-capture start: {$postStart}%\n";
echo "   Capture cooldown: " . ($cooldown / 60) . " minutes\n";
echo "\n";

// Example 5: Get control mode settings (for control mode)
echo "5. Control mode settings:\n";
$uptimeDuration = $configManager->getUptimeDuration();
$gainRate = $configManager->getControlGainRate();
$decayRate = $configManager->getControlDecayRate();

echo "   Uptime required: " . ($uptimeDuration / 60) . " minutes\n";
echo "   Control gain: {$gainRate}% per minute\n";
echo "   Control decay: {$decayRate}% per minute\n";
echo "\n";

// Example 6: Get training and wave limits
echo "6. Training and wave limits:\n";
$waveSpacing = $configManager->getWaveSpacing();
$maxEnvoys = $configManager->getMaxEnvoysPerCommand();
$mintCap = $configManager->getDailyMintCap();
$trainCap = $configManager->getDailyTrainCap();
$minPoints = $configManager->getMinDefenderPoints();

echo "   Wave spacing: {$waveSpacing}ms\n";
echo "   Max Envoys per command: {$maxEnvoys}\n";
echo "   Daily mint cap: {$mintCap} crests\n";
echo "   Daily train cap: {$trainCap} Envoys\n";
echo "   Min defender points: {$minPoints}\n";
echo "\n";

// Example 7: Check optional features
echo "7. Optional features:\n";
$buildingLoss = $configManager->isBuildingLossEnabled();
$buildingLossChance = $configManager->getBuildingLossChance();
$resourceTransfer = $configManager->getResourceTransferPercent();
$abandonmentDecay = $configManager->isAbandonmentDecayEnabled();

echo "   Building loss: " . ($buildingLoss ? 'enabled' : 'disabled');
if ($buildingLoss) {
    echo " (" . ($buildingLossChance * 100) . "% chance)";
}
echo "\n";
echo "   Resource transfer: " . ($resourceTransfer * 100) . "%\n";
echo "   Abandonment decay: " . ($abandonmentDecay ? 'enabled' : 'disabled') . "\n";
echo "\n";

// Example 8: Validate configuration
echo "8. Configuration validation:\n";
$validation = $configManager->validateConfig();

if ($validation['valid']) {
    echo "   ✓ Configuration is valid\n";
} else {
    echo "   ✗ Configuration has errors:\n";
    foreach ($validation['errors'] as $error) {
        echo "     - {$error}\n";
    }
}
echo "\n";

// Example 9: Get all config at once
echo "9. Getting complete configuration:\n";
$fullConfig = $configManager->getConquestConfig();
echo "   Retrieved " . count($fullConfig) . " configuration settings\n";
echo "   Sample settings:\n";
echo "     - enabled: " . ($fullConfig['enabled'] ? 'true' : 'false') . "\n";
echo "     - mode: {$fullConfig['mode']}\n";
echo "     - alleg_regen_per_hour: {$fullConfig['alleg_regen_per_hour']}\n";
echo "\n";

// Example 10: Feature flags
echo "10. Feature flags:\n";
$envoyEnabled = $configManager->isEnvoyEnabled();
$antiSnipeEnabled = $configManager->isAntiSnipeEnabled();
$wallModEnabled = $configManager->isWallModifierEnabled();

echo "   FEATURE_CONQUEST_UNIT_ENABLED: " . ($envoyEnabled ? 'true' : 'false') . "\n";
echo "   CONQUEST_ANTI_SNIPE_ENABLED: " . ($antiSnipeEnabled ? 'true' : 'false') . "\n";
echo "   CONQUEST_WALL_MOD_ENABLED: " . ($wallModEnabled ? 'true' : 'false') . "\n";
echo "\n";

echo "=== Usage Examples Complete ===\n";

