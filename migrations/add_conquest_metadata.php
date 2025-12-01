<?php
/**
 * Migration: add conquest metadata to villages (is_capital, conquered_at).
 *
 * - is_capital: marks a player's capital village (higher loyalty cap).
 * - conquered_at: timestamp of last conquest (recently conquered = vulnerable).
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection available.\n";
    exit(1);
}

echo "Starting conquest metadata migration...\n";

function columnExistsLocal($conn, string $column): bool {
    if (function_exists('dbColumnExists')) {
        return dbColumnExists($conn, 'villages', $column);
    }
    return false;
}

try {
    // is_capital
    if (columnExistsLocal($conn, 'is_capital')) {
        echo "- Column 'is_capital' already exists. Skipping.\n";
    } else {
        echo "- Adding column 'is_capital'...\n";
        $conn->query("ALTER TABLE villages ADD COLUMN is_capital TINYINT(1) NOT NULL DEFAULT 0");
    }

    // conquered_at
    if (columnExistsLocal($conn, 'conquered_at')) {
        echo "- Column 'conquered_at' already exists. Skipping.\n";
    } else {
        echo "- Adding column 'conquered_at'...\n";
        $conn->query("ALTER TABLE villages ADD COLUMN conquered_at DATETIME NULL DEFAULT NULL");
    }

    echo "Migration completed.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
