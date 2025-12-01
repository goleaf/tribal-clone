<?php
declare(strict_types=1);

/**
 * Migration: add high-churn indexes for movements, battles, notifications, and trades.
 *
 * Tables covered:
 * - attacks: arrival_time + village lookups
 * - battle_reports: village lookups + battle_time
 * - notifications: user + expiry
 * - trade_offers: source village + status timelines
 * - trade_routes: source/target villages + arrival_time
 */

require_once __DIR__ . '/../init.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo "No database connection available.\n";
    exit(1);
}

function isSqlite($conn): bool
{
    return is_object($conn) && get_class($conn) === 'SQLiteAdapter';
}

function indexExists($conn, string $table, string $index): bool
{
    if (isSqlite($conn)) {
        $stmt = $conn->query("PRAGMA index_list('{$table}')");
        if ($stmt && method_exists($stmt, 'fetch_all')) {
            $rows = $stmt->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as $row) {
                if (($row['name'] ?? '') === $index) {
                    return true;
                }
            }
        }
        return false;
    }

    $stmt = $conn->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = ?");
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("s", $index);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

function createIndex($conn, string $name, string $table, string $columns): void
{
    if (indexExists($conn, $table, $name)) {
        echo "- Index {$name} already exists on {$table}, skipping.\n";
        return;
    }

    $sql = "CREATE INDEX " . (isSqlite($conn) ? "IF NOT EXISTS " : "") . "{$name} ON {$table} ({$columns})";
    $ok = @$conn->query($sql);
    if ($ok === false && isset($conn->error)) {
        $msg = $conn->error;
        // Ignore duplicate index errors on MySQL < 8 (no IF NOT EXISTS)
        if (stripos((string)$msg, 'Duplicate') !== false || stripos((string)$msg, 'already exists') !== false) {
            echo "- Index {$name} already exists (duplicate reported), skipping.\n";
            return;
        }
        echo "- Failed to create {$name} on {$table}: {$msg}\n";
    } else {
        echo "- Created index {$name} on {$table} ({$columns}).\n";
    }
}

echo "Adding performance indexes...\n";

// Movements (attacks)
createIndex($conn, 'idx_attacks_arrival', 'attacks', 'arrival_time');
createIndex($conn, 'idx_attacks_target_arrival', 'attacks', 'target_village_id, arrival_time');
createIndex($conn, 'idx_attacks_source_arrival', 'attacks', 'source_village_id, arrival_time');

// Battle reports
createIndex($conn, 'idx_battle_reports_target_time', 'battle_reports', 'target_village_id, battle_time');
createIndex($conn, 'idx_battle_reports_source_time', 'battle_reports', 'source_village_id, battle_time');

// Notifications
createIndex($conn, 'idx_notifications_user', 'notifications', 'user_id, is_read, expires_at');
createIndex($conn, 'idx_notifications_expires', 'notifications', 'expires_at');

// Trades
createIndex($conn, 'idx_trade_offers_source_status', 'trade_offers', 'source_village_id, status, created_at');
createIndex($conn, 'idx_trade_offers_status_created', 'trade_offers', 'status, created_at');
createIndex($conn, 'idx_trade_routes_source_arrival', 'trade_routes', 'source_village_id, arrival_time');
createIndex($conn, 'idx_trade_routes_target_arrival', 'trade_routes', 'target_village_id, arrival_time');

echo "Done.\n";
