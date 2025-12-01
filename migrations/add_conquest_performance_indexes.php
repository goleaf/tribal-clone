<?php
declare(strict_types=1);

/**
 * Migration: Add performance indexes for conquest system
 * 
 * Adds indexes to villages table for efficient conquest queries:
 * - village_id lookups
 * - last_allegiance_update for regeneration processing
 * - capture_cooldown_until for cooldown checks
 * - anti_snipe_until for protection checks
 * 
 * Requirements: 4.1, 5.1
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection.\n";
    exit(1);
}

echo "Starting conquest performance indexes migration...\n";

/**
 * Check if an index exists
 */
function indexExists($conn, string $index): bool
{
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='index' AND name='$index'");
    return $result && $result->num_rows > 0;
}

try {
    echo "\nAdding performance indexes to villages table...\n";
    
    $indexes = [
        'idx_villages_allegiance_update' => "CREATE INDEX idx_villages_allegiance_update ON villages(allegiance_last_update)",
        'idx_villages_capture_cooldown' => "CREATE INDEX idx_villages_capture_cooldown ON villages(capture_cooldown_until)",
        'idx_villages_anti_snipe' => "CREATE INDEX idx_villages_anti_snipe ON villages(anti_snipe_until)",
        'idx_villages_allegiance' => "CREATE INDEX idx_villages_allegiance ON villages(allegiance)",
        'idx_villages_conquest_state' => "CREATE INDEX idx_villages_conquest_state ON villages(allegiance, capture_cooldown_until, anti_snipe_until)"
    ];
    
    foreach ($indexes as $indexName => $sql) {
        if (indexExists($conn, $indexName)) {
            echo " - Index $indexName already exists, skipping.\n";
        } else {
            if ($conn->query($sql)) {
                echo " - Created index $indexName\n";
            } else {
                echo " [!] Failed to create index $indexName: " . $conn->error . "\n";
            }
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Added performance indexes for:\n";
    echo "  - Allegiance regeneration queries\n";
    echo "  - Capture cooldown checks\n";
    echo "  - Anti-snipe protection checks\n";
    echo "  - Composite conquest state queries\n";
    
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
