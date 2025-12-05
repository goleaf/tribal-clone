<?php
declare(strict_types=1);

/**
 * Test: WorldConfigManager
 * 
 * Verifies that WorldConfigManager correctly loads and provides access to
 * conquest configuration settings from the worlds table.
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/managers/WorldConfigManager.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "FAIL: No database connection available.\n";
    exit(1);
}

echo "Testing WorldConfigManager...\n\n";

$configManager = new WorldConfigManager($conn);
$testsPassed = 0;
$testsFailed = 0;

// Test 1: Get conquest configuration
echo "Test 1: Get conquest configuration\n";
try {
    $config = $configManager->getConquestConfig(CURRENT_WORLD_ID);
    
    if (!is_array($config)) {
        echo "FAIL: Config is not an array\n";
        $testsFailed++;
    } elseif (empty($config)) {
        echo "FAIL: Config is empty\n";
        $testsFailed++;
    } else {
        echo "PASS: Got conquest configuration with " . count($config) . " settings\n";
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 2: Check conquest enabled flag
echo "Test 2: Check conquest enabled flag\n";
try {
    $enabled = $configManager->isConquestEnabled(CURRENT_WORLD_ID);
    
    if (!is_bool($enabled)) {
        echo "FAIL: Enabled flag is not boolean\n";
        $testsFailed++;
    } else {
        echo "PASS: Conquest enabled = " . ($enabled ? 'true' : 'false') . "\n";
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 3: Get conquest mode
echo "Test 3: Get conquest mode\n";
try {
    $mode = $configManager->getConquestMode(CURRENT_WORLD_ID);
    
    if (!in_array($mode, ['allegiance', 'control'], true)) {
        echo "FAIL: Invalid mode: {$mode}\n";
        $testsFailed++;
    } else {
        echo "PASS: Conquest mode = {$mode}\n";
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 4: Check mode detection methods
echo "Test 4: Check mode detection methods\n";
try {
    $isAllegiance = $configManager->isAllegianceMode(CURRENT_WORLD_ID);
    $isControl = $configManager->isControlMode(CURRENT_WORLD_ID);
    
    if ($isAllegiance && $isControl) {
        echo "FAIL: Both modes cannot be true\n";
        $testsFailed++;
    } elseif (!$isAllegiance && !$isControl) {
        echo "FAIL: One mode must be true\n";
        $testsFailed++;
    } else {
        echo "PASS: Mode detection works correctly\n";
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 5: Get allegiance settings
echo "Test 5: Get allegiance settings\n";
try {
    $regenRate = $configManager->getAllegianceRegenRate(CURRENT_WORLD_ID);
    $wallFactor = $configManager->getWallReductionFactor(CURRENT_WORLD_ID);
    $dropRange = $configManager->getAllegianceDropRange(CURRENT_WORLD_ID);
    
    if ($regenRate < 0 || $regenRate > 100) {
        echo "FAIL: Invalid regen rate: {$regenRate}\n";
        $testsFailed++;
    } elseif ($wallFactor < 0 || $wallFactor > 1) {
        echo "FAIL: Invalid wall factor: {$wallFactor}\n";
        $testsFailed++;
    } elseif (!isset($dropRange['min']) || !isset($dropRange['max'])) {
        echo "FAIL: Drop range missing min/max\n";
        $testsFailed++;
    } elseif ($dropRange['min'] > $dropRange['max']) {
        echo "FAIL: Drop min > max\n";
        $testsFailed++;
    } else {
        echo "PASS: Allegiance settings valid\n";
        echo "  - Regen rate: {$regenRate}%/hour\n";
        echo "  - Wall factor: {$wallFactor}\n";
        echo "  - Drop range: {$dropRange['min']}-{$dropRange['max']}\n";
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 6: Get anti-snipe settings
echo "Test 6: Get anti-snipe settings\n";
try {
    $floor = $configManager->getAntiSnipeFloor(CURRENT_WORLD_ID);
    $duration = $configManager->getAntiSnipeDuration(CURRENT_WORLD_ID);
    $postStart = $configManager->getPostCaptureStart(CURRENT_WORLD_ID);
    $cooldown = $configManager->getCaptureCooldown(CURRENT_WORLD_ID);
    
    if ($floor < 0 || $floor > 100) {
        echo "FAIL: Invalid floor: {$floor}\n";
        $testsFailed++;
    } elseif ($duration < 0) {
        echo "FAIL: Invalid duration: {$duration}\n";
        $testsFailed++;
    } elseif ($postStart < 0 || $postStart > 100) {
        echo "FAIL: Invalid post-capture start: {$postStart}\n";
        $testsFailed++;
    } elseif ($cooldown < 0) {
        echo "FAIL: Invalid cooldown: {$cooldown}\n";
        $testsFailed++;
    } else {
        echo "PASS: Anti-snipe settings valid\n";
        echo "  - Floor: {$floor}\n";
        echo "  - Duration: {$duration}s\n";
        echo "  - Post-capture start: {$postStart}\n";
        echo "  - Cooldown: {$cooldown}s\n";
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 7: Get control mode settings
echo "Test 7: Get control mode settings\n";
try {
    $uptimeDuration = $configManager->getUptimeDuration(CURRENT_WORLD_ID);
    $gainRate = $configManager->getControlGainRate(CURRENT_WORLD_ID);
    $decayRate = $configManager->getControlDecayRate(CURRENT_WORLD_ID);
    
    if ($uptimeDuration <= 0) {
        echo "FAIL: Invalid uptime duration: {$uptimeDuration}\n";
        $testsFailed++;
    } elseif ($gainRate <= 0) {
        echo "FAIL: Invalid gain rate: {$gainRate}\n";
        $testsFailed++;
    } elseif ($decayRate < 0) {
        echo "FAIL: Invalid decay rate: {$decayRate}\n";
        $testsFailed++;
    } else {
        echo "PASS: Control mode settings valid\n";
        echo "  - Uptime duration: {$uptimeDuration}s\n";
        echo "  - Gain rate: {$gainRate}/min\n";
        echo "  - Decay rate: {$decayRate}/min\n";
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 8: Get wave and training limits
echo "Test 8: Get wave and training limits\n";
try {
    $waveSpacing = $configManager->getWaveSpacing(CURRENT_WORLD_ID);
    $maxEnvoys = $configManager->getMaxEnvoysPerCommand(CURRENT_WORLD_ID);
    $mintCap = $configManager->getDailyMintCap(CURRENT_WORLD_ID);
    $trainCap = $configManager->getDailyTrainCap(CURRENT_WORLD_ID);
    $minPoints = $configManager->getMinDefenderPoints(CURRENT_WORLD_ID);
    
    if ($waveSpacing < 0) {
        echo "FAIL: Invalid wave spacing: {$waveSpacing}\n";
        $testsFailed++;
    } elseif ($maxEnvoys <= 0) {
        echo "FAIL: Invalid max envoys: {$maxEnvoys}\n";
        $testsFailed++;
    } elseif ($mintCap < 0) {
        echo "FAIL: Invalid mint cap: {$mintCap}\n";
        $testsFailed++;
    } elseif ($trainCap < 0) {
        echo "FAIL: Invalid train cap: {$trainCap}\n";
        $testsFailed++;
    } elseif ($minPoints < 0) {
        echo "FAIL: Invalid min points: {$minPoints}\n";
        $testsFailed++;
    } else {
        echo "PASS: Wave and training limits valid\n";
        echo "  - Wave spacing: {$waveSpacing}ms\n";
        echo "  - Max envoys/command: {$maxEnvoys}\n";
        echo "  - Daily mint cap: {$mintCap}\n";
        echo "  - Daily train cap: {$trainCap}\n";
        echo "  - Min defender points: {$minPoints}\n";
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 9: Get optional features
echo "Test 9: Get optional features\n";
try {
    $buildingLossEnabled = $configManager->isBuildingLossEnabled(CURRENT_WORLD_ID);
    $buildingLossChance = $configManager->getBuildingLossChance(CURRENT_WORLD_ID);
    $resourceTransfer = $configManager->getResourceTransferPercent(CURRENT_WORLD_ID);
    $abandonmentEnabled = $configManager->isAbandonmentDecayEnabled(CURRENT_WORLD_ID);
    
    if (!is_bool($buildingLossEnabled)) {
        echo "FAIL: Building loss enabled is not boolean\n";
        $testsFailed++;
    } elseif ($buildingLossChance < 0 || $buildingLossChance > 1) {
        echo "FAIL: Invalid building loss chance: {$buildingLossChance}\n";
        $testsFailed++;
    } elseif ($resourceTransfer < 0 || $resourceTransfer > 1) {
        echo "FAIL: Invalid resource transfer: {$resourceTransfer}\n";
        $testsFailed++;
    } elseif (!is_bool($abandonmentEnabled)) {
        echo "FAIL: Abandonment enabled is not boolean\n";
        $testsFailed++;
    } else {
        echo "PASS: Optional features valid\n";
        echo "  - Building loss: " . ($buildingLossEnabled ? 'enabled' : 'disabled') . "\n";
        echo "  - Building loss chance: " . ($buildingLossChance * 100) . "%\n";
        echo "  - Resource transfer: " . ($resourceTransfer * 100) . "%\n";
        echo "  - Abandonment decay: " . ($abandonmentEnabled ? 'enabled' : 'disabled') . "\n";
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 10: Validate configuration
echo "Test 10: Validate configuration\n";
try {
    $validation = $configManager->validateConfig(CURRENT_WORLD_ID);
    
    if (!isset($validation['valid']) || !isset($validation['errors'])) {
        echo "FAIL: Validation result missing required fields\n";
        $testsFailed++;
    } elseif (!is_bool($validation['valid'])) {
        echo "FAIL: Valid flag is not boolean\n";
        $testsFailed++;
    } elseif (!is_array($validation['errors'])) {
        echo "FAIL: Errors is not an array\n";
        $testsFailed++;
    } else {
        if ($validation['valid']) {
            echo "PASS: Configuration is valid\n";
        } else {
            echo "PASS: Validation detected errors:\n";
            foreach ($validation['errors'] as $error) {
                echo "  - {$error}\n";
            }
        }
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 11: Feature flags
echo "Test 11: Feature flags\n";
try {
    $envoyEnabled = $configManager->isEnvoyEnabled(CURRENT_WORLD_ID);
    $antiSnipeEnabled = $configManager->isAntiSnipeEnabled(CURRENT_WORLD_ID);
    $wallModEnabled = $configManager->isWallModifierEnabled(CURRENT_WORLD_ID);
    
    if (!is_bool($envoyEnabled)) {
        echo "FAIL: Envoy enabled is not boolean\n";
        $testsFailed++;
    } elseif (!is_bool($antiSnipeEnabled)) {
        echo "FAIL: Anti-snipe enabled is not boolean\n";
        $testsFailed++;
    } elseif (!is_bool($wallModEnabled)) {
        echo "FAIL: Wall modifier enabled is not boolean\n";
        $testsFailed++;
    } else {
        echo "PASS: Feature flags valid\n";
        echo "  - Envoy enabled: " . ($envoyEnabled ? 'true' : 'false') . "\n";
        echo "  - Anti-snipe enabled: " . ($antiSnipeEnabled ? 'true' : 'false') . "\n";
        echo "  - Wall modifier enabled: " . ($wallModEnabled ? 'true' : 'false') . "\n";
        $testsPassed++;
    }
} catch (Exception $e) {
    echo "FAIL: Exception - " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Summary
echo "========================================\n";
echo "Test Summary:\n";
echo "  Passed: {$testsPassed}\n";
echo "  Failed: {$testsFailed}\n";
echo "  Total:  " . ($testsPassed + $testsFailed) . "\n";
echo "========================================\n";

if ($testsFailed > 0) {
    exit(1);
}

echo "\nAll tests passed!\n";
exit(0);

