<?php
declare(strict_types=1);

/**
 * Migration: add per-world economy knobs (resource multiplier, vault protection percent).
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/functions.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection available.\n";
    exit(1);
}

echo "Adding world economy settings columns...\n";

$isSqlite = is_object($conn) && method_exists($conn, 'getPdo');

$columns = [
    'resource_multiplier' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN resource_multiplier REAL NOT NULL DEFAULT 1.0"
        : "ALTER TABLE worlds ADD COLUMN resource_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.0",
    'vault_protect_pct' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN vault_protect_pct INTEGER NOT NULL DEFAULT 10"
        : "ALTER TABLE worlds ADD COLUMN vault_protect_pct INT NOT NULL DEFAULT 10",
];

foreach ($columns as $name => $sql) {
    if (dbColumnExists($conn, 'worlds', $name)) {
        echo "- Column {$name} already exists. Skipping.\n";
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
