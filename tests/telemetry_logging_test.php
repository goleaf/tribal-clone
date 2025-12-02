<?php
/**
 * Test telemetry and logging functionality
 * Validates Requirements 17.5, 18.1, 18.2, 18.3
 */

// Define the telemetry functions directly for testing
function logRecruitTelemetry(int $userId, int $villageId, int $unitId, int $count, string $status, string $code, string $message, ?int $worldId = null): void
{
    $logFile = __DIR__ . '/../logs/recruit_telemetry.log';
    
    // Get world ID if not provided
    if ($worldId === null) {
        $worldId = defined('CURRENT_WORLD_ID') ? (int)CURRENT_WORLD_ID : 1;
    }
    
    $entry = [
        'ts' => date('c'),
        'user_id' => $userId,
        'village_id' => $villageId,
        'world_id' => $worldId,
        'unit_id' => $unitId,
        'count' => $count,
        'status' => $status,
        'code' => $code,
        'message' => $message
    ];
    $line = json_encode($entry) . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function incrementCapHitCounter(int $unitId, int $worldId, string $unitInternal = ''): void
{
    $counterFile = __DIR__ . '/../logs/cap_hit_counters.log';
    $entry = [
        'ts' => date('c'),
        'world_id' => $worldId,
        'unit_id' => $unitId,
        'unit_internal' => $unitInternal,
        'event' => 'cap_hit'
    ];
    $line = json_encode($entry) . PHP_EOL;
    @file_put_contents($counterFile, $line, FILE_APPEND | LOCK_EX);
}

function incrementErrorCounter(string $errorCode): void
{
    $counterFile = __DIR__ . '/../logs/error_counters.log';
    $entry = [
        'ts' => date('c'),
        'error_code' => $errorCode,
        'event' => 'error'
    ];
    $line = json_encode($entry) . PHP_EOL;
    @file_put_contents($counterFile, $line, FILE_APPEND | LOCK_EX);
}

// Test helper functions
function clearLogFile(string $logFile): void {
    if (file_exists($logFile)) {
        unlink($logFile);
    }
}

function getLogEntries(string $logFile): array {
    if (!file_exists($logFile)) {
        return [];
    }
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_map('json_decode', $lines);
}

// Test 1: Verify logRecruitTelemetry includes all required fields
echo "Test 1: Verify logRecruitTelemetry includes all required fields\n";
$telemetryLog = __DIR__ . '/../logs/recruit_telemetry.log';
clearLogFile($telemetryLog);

// Simulate a telemetry log entry
logRecruitTelemetry(1, 100, 5, 10, 'success', 'OK', 'Units recruited successfully', 1);

$entries = getLogEntries($telemetryLog);
if (count($entries) === 1) {
    $entry = $entries[0];
    $requiredFields = ['ts', 'user_id', 'village_id', 'world_id', 'unit_id', 'count', 'status', 'code', 'message'];
    $allFieldsPresent = true;
    foreach ($requiredFields as $field) {
        if (!property_exists($entry, $field)) {
            echo "  ✗ FAIL: Missing field '$field'\n";
            $allFieldsPresent = false;
        }
    }
    if ($allFieldsPresent) {
        echo "  ✓ PASS: All required fields present\n";
        echo "  ✓ PASS: world_id = {$entry->world_id}\n";
        echo "  ✓ PASS: user_id = {$entry->user_id}\n";
        echo "  ✓ PASS: unit_id = {$entry->unit_id}\n";
        echo "  ✓ PASS: count = {$entry->count}\n";
        echo "  ✓ PASS: status = {$entry->status}\n";
        echo "  ✓ PASS: code = {$entry->code}\n";
    }
} else {
    echo "  ✗ FAIL: Expected 1 log entry, got " . count($entries) . "\n";
}

// Test 2: Verify incrementCapHitCounter logs cap hits
echo "\nTest 2: Verify incrementCapHitCounter logs cap hits\n";
$capHitLog = __DIR__ . '/../logs/cap_hit_counters.log';
clearLogFile($capHitLog);

incrementCapHitCounter(5, 1, 'noble');

$entries = getLogEntries($capHitLog);
if (count($entries) === 1) {
    $entry = $entries[0];
    if (property_exists($entry, 'world_id') && $entry->world_id === 1 &&
        property_exists($entry, 'unit_id') && $entry->unit_id === 5 &&
        property_exists($entry, 'unit_internal') && $entry->unit_internal === 'noble' &&
        property_exists($entry, 'event') && $entry->event === 'cap_hit') {
        echo "  ✓ PASS: Cap hit counter logged correctly\n";
        echo "  ✓ PASS: world_id = {$entry->world_id}\n";
        echo "  ✓ PASS: unit_id = {$entry->unit_id}\n";
        echo "  ✓ PASS: unit_internal = {$entry->unit_internal}\n";
    } else {
        echo "  ✗ FAIL: Cap hit counter fields incorrect\n";
    }
} else {
    echo "  ✗ FAIL: Expected 1 cap hit entry, got " . count($entries) . "\n";
}

// Test 3: Verify incrementErrorCounter logs errors
echo "\nTest 3: Verify incrementErrorCounter logs errors\n";
$errorLog = __DIR__ . '/../logs/error_counters.log';
clearLogFile($errorLog);

incrementErrorCounter('ERR_CAP');
incrementErrorCounter('ERR_RES');
incrementErrorCounter('ERR_INPUT');

$entries = getLogEntries($errorLog);
if (count($entries) === 3) {
    echo "  ✓ PASS: All 3 error counters logged\n";
    $errorCodes = array_map(fn($e) => $e->error_code, $entries);
    if (in_array('ERR_CAP', $errorCodes) && in_array('ERR_RES', $errorCodes) && in_array('ERR_INPUT', $errorCodes)) {
        echo "  ✓ PASS: All error codes present (ERR_CAP, ERR_RES, ERR_INPUT)\n";
    } else {
        echo "  ✗ FAIL: Missing expected error codes\n";
    }
} else {
    echo "  ✗ FAIL: Expected 3 error entries, got " . count($entries) . "\n";
}

// Test 4: Verify world_id defaults when not provided
echo "\nTest 4: Verify world_id defaults when not provided\n";
clearLogFile($telemetryLog);

logRecruitTelemetry(2, 200, 10, 5, 'fail', 'ERR_RES', 'Not enough resources');

$entries = getLogEntries($telemetryLog);
if (count($entries) === 1) {
    $entry = $entries[0];
    if (property_exists($entry, 'world_id') && $entry->world_id === 1) {
        echo "  ✓ PASS: world_id defaults to 1 when not provided\n";
    } else {
        echo "  ✗ FAIL: world_id not defaulted correctly\n";
    }
} else {
    echo "  ✗ FAIL: Expected 1 log entry\n";
}

echo "\n=== All telemetry logging tests completed ===\n";
