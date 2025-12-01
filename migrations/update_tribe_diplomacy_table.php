<?php
declare(strict_types=1);

/**
 * Migration: expand tribe_diplomacy for the state machine (pending states, durations, cooldowns, audit columns).
 *
 * Adds columns if missing:
 * - is_pending, starts_at, ends_at, requested_by_user_id, accepted_by_user_id, reason, cooldown_until, updated_at
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/functions.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection available.\n";
    exit(1);
}

echo "Updating tribe_diplomacy table...\n";

$columns = [
    'is_pending' => "ALTER TABLE tribe_diplomacy ADD COLUMN is_pending TINYINT(1) NOT NULL DEFAULT 0",
    'starts_at' => "ALTER TABLE tribe_diplomacy ADD COLUMN starts_at BIGINT DEFAULT NULL",
    'ends_at' => "ALTER TABLE tribe_diplomacy ADD COLUMN ends_at BIGINT DEFAULT NULL",
    'requested_by_user_id' => "ALTER TABLE tribe_diplomacy ADD COLUMN requested_by_user_id INT DEFAULT NULL",
    'accepted_by_user_id' => "ALTER TABLE tribe_diplomacy ADD COLUMN accepted_by_user_id INT DEFAULT NULL",
    'reason' => "ALTER TABLE tribe_diplomacy ADD COLUMN reason VARCHAR(255) DEFAULT ''",
    'cooldown_until' => "ALTER TABLE tribe_diplomacy ADD COLUMN cooldown_until BIGINT NOT NULL DEFAULT 0",
    'updated_at' => "ALTER TABLE tribe_diplomacy ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
];

$isSqlite = is_object($conn) && method_exists($conn, 'getPdo');
if ($isSqlite) {
    // SQLite syntax tweaks
    $columns = [
        'is_pending' => "ALTER TABLE tribe_diplomacy ADD COLUMN is_pending INTEGER NOT NULL DEFAULT 0",
        'starts_at' => "ALTER TABLE tribe_diplomacy ADD COLUMN starts_at INTEGER DEFAULT NULL",
        'ends_at' => "ALTER TABLE tribe_diplomacy ADD COLUMN ends_at INTEGER DEFAULT NULL",
        'requested_by_user_id' => "ALTER TABLE tribe_diplomacy ADD COLUMN requested_by_user_id INTEGER DEFAULT NULL",
        'accepted_by_user_id' => "ALTER TABLE tribe_diplomacy ADD COLUMN accepted_by_user_id INTEGER DEFAULT NULL",
        'reason' => "ALTER TABLE tribe_diplomacy ADD COLUMN reason TEXT DEFAULT ''",
        'cooldown_until' => "ALTER TABLE tribe_diplomacy ADD COLUMN cooldown_until INTEGER NOT NULL DEFAULT 0",
        'updated_at' => "ALTER TABLE tribe_diplomacy ADD COLUMN updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP"
    ];
}

foreach ($columns as $name => $sql) {
    if (dbColumnExists($conn, 'tribe_diplomacy', $name)) {
        echo "- Column {$name} already exists, skipping.\n";
        continue;
    }
    $ok = $conn->query($sql);
    if ($ok === false && isset($conn->error)) {
        echo "- Failed to add {$name}: {$conn->error}\n";
    } else {
        echo "- Added column {$name}.\n";
    }
}

echo "Done.\n";
