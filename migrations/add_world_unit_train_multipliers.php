<?php
declare(strict_types=1);

/**
 * Migration: add per-archetype training speed multipliers to worlds.
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/functions.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection available.\n";
    exit(1);
}

echo "Adding world unit training multipliers...\n";

$isSqlite = is_object($conn) && method_exists($conn, 'getPdo');
$columns = [
    'inf_train_multiplier' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN inf_train_multiplier REAL NOT NULL DEFAULT 1.0"
        : "ALTER TABLE worlds ADD COLUMN inf_train_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.0",
    'cav_train_multiplier' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN cav_train_multiplier REAL NOT NULL DEFAULT 1.0"
        : "ALTER TABLE worlds ADD COLUMN cav_train_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.0",
    'rng_train_multiplier' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN rng_train_multiplier REAL NOT NULL DEFAULT 1.0"
        : "ALTER TABLE worlds ADD COLUMN rng_train_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.0",
    'siege_train_multiplier' => $isSqlite
        ? "ALTER TABLE worlds ADD COLUMN siege_train_multiplier REAL NOT NULL DEFAULT 1.0"
        : "ALTER TABLE worlds ADD COLUMN siege_train_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.0",
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
