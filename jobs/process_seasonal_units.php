<?php
declare(strict_types=1);

/**
 * Seasonal Unit Lifecycle Processor
 * 
 * Handles activation and sunset of seasonal/event units based on time windows.
 * 
 * Run this script via cron every hour to process seasonal unit lifecycle:
 * 0 * * * * php /path/to/jobs/process_seasonal_units.php >> /path/to/logs/seasonal_units.log 2>&1
 * 
 * Requirements: 10.1, 10.3
 */

// Set CLI flag before init
$IS_CLI = true;

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/UnitManager.php';
require_once __DIR__ . '/../lib/managers/WorldManager.php';

$logFile = __DIR__ . '/../logs/seasonal_units.log';

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

/**
 * Process seasonal unit activations
 * Enables training for units whose event window has started
 * 
 * Requirements: 10.1
 */
function processActivations($conn): array
{
    $currentTime = time();
    $activated = [];
    
    // Find seasonal units that should be active but aren't marked as active
    $stmt = $conn->prepare("
        SELECT id, unit_internal_name, event_name, start_timestamp, end_timestamp
        FROM seasonal_units
        WHERE is_active = 0
        AND start_timestamp <= ?
        AND end_timestamp >= ?
    ");
    
    if (!$stmt) {
        logMessage("ERROR: Failed to prepare activation query: " . $conn->error);
        return $activated;
    }
    
    $stmt->bind_param("ii", $currentTime, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $unitName = $row['unit_internal_name'];
        $eventName = $row['event_name'];
        $id = $row['id'];
        
        // Activate the unit
        $updateStmt = $conn->prepare("UPDATE seasonal_units SET is_active = 1 WHERE id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param("i", $id);
            if ($updateStmt->execute()) {
                $activated[] = [
                    'unit' => $unitName,
                    'event' => $eventName,
                    'start' => $row['start_timestamp'],
                    'end' => $row['end_timestamp']
                ];
                logMessage("ACTIVATED: Unit '{$unitName}' for event '{$eventName}' (window: " . 
                          date('Y-m-d H:i:s', $row['start_timestamp']) . " to " . 
                          date('Y-m-d H:i:s', $row['end_timestamp']) . ")");
            } else {
                logMessage("ERROR: Failed to activate unit '{$unitName}': " . $updateStmt->error);
            }
            $updateStmt->close();
        }
    }
    
    $stmt->close();
    return $activated;
}

/**
 * Process seasonal unit sunsets
 * Disables training for units whose event window has ended
 * 
 * Requirements: 10.3
 */
function processSunsets($conn): array
{
    $currentTime = time();
    $sunset = [];
    
    // Find seasonal units that are active but have expired
    $stmt = $conn->prepare("
        SELECT id, unit_internal_name, event_name, start_timestamp, end_timestamp
        FROM seasonal_units
        WHERE is_active = 1
        AND end_timestamp < ?
    ");
    
    if (!$stmt) {
        logMessage("ERROR: Failed to prepare sunset query: " . $conn->error);
        return $sunset;
    }
    
    $stmt->bind_param("i", $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $unitName = $row['unit_internal_name'];
        $eventName = $row['event_name'];
        $id = $row['id'];
        
        // Deactivate the unit
        $updateStmt = $conn->prepare("UPDATE seasonal_units SET is_active = 0 WHERE id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param("i", $id);
            if ($updateStmt->execute()) {
                $sunset[] = [
                    'unit' => $unitName,
                    'event' => $eventName,
                    'end' => $row['end_timestamp']
                ];
                logMessage("SUNSET: Unit '{$unitName}' for event '{$eventName}' expired at " . 
                          date('Y-m-d H:i:s', $row['end_timestamp']));
                
                // Handle existing units based on world configuration
                handleExistingUnits($conn, $unitName, $eventName);
            } else {
                logMessage("ERROR: Failed to sunset unit '{$unitName}': " . $updateStmt->error);
            }
            $updateStmt->close();
        }
    }
    
    $stmt->close();
    return $sunset;
}

/**
 * Handle existing seasonal units after sunset
 * Options: convert to resources, disable use, or leave as-is
 * 
 * Requirements: 10.3
 */
function handleExistingUnits($conn, string $unitName, string $eventName): void
{
    // Get world configuration for seasonal unit handling
    // For now, we'll log the existing units but not convert them
    // This allows world admins to decide the policy per world
    
    // Count existing units of this type across all villages
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total_count, SUM(vu.count) as total_units
        FROM village_units vu
        JOIN unit_types ut ON vu.unit_type_id = ut.id
        WHERE ut.internal_name = ?
    ");
    
    if ($countStmt) {
        $countStmt->bind_param("s", $unitName);
        $countStmt->execute();
        $result = $countStmt->get_result();
        $row = $result->fetch_assoc();
        $totalUnits = $row['total_units'] ?? 0;
        $totalVillages = $row['total_count'] ?? 0;
        
        if ($totalUnits > 0) {
            logMessage("  → Found {$totalUnits} existing '{$unitName}' units across {$totalVillages} villages");
            logMessage("  → Units remain functional but cannot be trained (world config determines conversion policy)");
        }
        
        $countStmt->close();
    }
    
    // Also check queued units
    $queueStmt = $conn->prepare("
        SELECT COUNT(*) as queue_count, SUM(uq.count - uq.count_finished) as pending_units
        FROM unit_queue uq
        JOIN unit_types ut ON uq.unit_type_id = ut.id
        WHERE ut.internal_name = ?
        AND uq.count_finished < uq.count
    ");
    
    if ($queueStmt) {
        $queueStmt->bind_param("s", $unitName);
        $queueStmt->execute();
        $result = $queueStmt->get_result();
        $row = $result->fetch_assoc();
        $pendingUnits = $row['pending_units'] ?? 0;
        
        if ($pendingUnits > 0) {
            logMessage("  → Found {$pendingUnits} '{$unitName}' units still in training queues");
            logMessage("  → Queued units will complete normally but no new training allowed");
        }
        
        $queueStmt->close();
    }
}

try {
    logMessage("=== Starting seasonal unit lifecycle processor ===");
    
    $startTime = microtime(true);
    
    // Process activations first (new events starting)
    logMessage("\n--- Processing Activations ---");
    $activated = processActivations($conn);
    
    if (empty($activated)) {
        logMessage("No seasonal units to activate.");
    } else {
        logMessage("Activated " . count($activated) . " seasonal unit(s).");
    }
    
    // Process sunsets (expired events)
    logMessage("\n--- Processing Sunsets ---");
    $sunset = processSunsets($conn);
    
    if (empty($sunset)) {
        logMessage("No seasonal units to sunset.");
    } else {
        logMessage("Sunset " . count($sunset) . " seasonal unit(s).");
    }
    
    $duration = microtime(true) - $startTime;
    
    logMessage("\n=== Seasonal unit lifecycle processor completed ===");
    logMessage("Summary:");
    logMessage("  - Activated: " . count($activated));
    logMessage("  - Sunset: " . count($sunset));
    logMessage("  - Duration: " . number_format($duration, 3) . "s");
    logMessage("");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}
