<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
if (!defined('CURRENT_WORLD_ID')) {
    define('CURRENT_WORLD_ID', 1);
}

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/managers/TribeManager.php';

class TinyAssert
{
    public static function equals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $prefix = $message ? "{$message} - " : '';
            throw new RuntimeException($prefix . "Expected '" . var_export($expected, true) . "' but got '" . var_export($actual, true) . "'");
        }
    }

    public static function true(bool $condition, string $message = ''): void
    {
        if ($condition !== true) {
            throw new RuntimeException($message ?: 'Expected condition to be true');
        }
    }

    public static function false(bool $condition, string $message = ''): void
    {
        if ($condition !== false) {
            throw new RuntimeException($message ?: 'Expected condition to be false');
        }
    }
}

function setupTribeSchema(SQLiteAdapter $conn): void
{
    $conn->query("CREATE TABLE tribes (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, tag TEXT, description TEXT, founder_id INTEGER, world_id INTEGER DEFAULT 1)");
    $conn->query("CREATE TABLE tribe_members (id INTEGER PRIMARY KEY AUTOINCREMENT, tribe_id INTEGER NOT NULL, user_id INTEGER NOT NULL UNIQUE, role TEXT NOT NULL DEFAULT 'member', joined_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE tribe_invitations (id INTEGER PRIMARY KEY AUTOINCREMENT, tribe_id INTEGER NOT NULL, invited_user_id INTEGER NOT NULL, inviter_id INTEGER DEFAULT NULL, status TEXT NOT NULL DEFAULT 'pending', created_at TEXT DEFAULT CURRENT_TIMESTAMP, responded_at TEXT DEFAULT NULL)");
    $conn->query("CREATE TABLE tribe_diplomacy (id INTEGER PRIMARY KEY AUTOINCREMENT, tribe_id INTEGER NOT NULL, target_tribe_id INTEGER NOT NULL, status TEXT NOT NULL, created_by INTEGER NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(tribe_id, target_tribe_id))");
    $conn->query("CREATE TABLE tribe_forum_threads (id INTEGER PRIMARY KEY AUTOINCREMENT, tribe_id INTEGER NOT NULL, title TEXT NOT NULL, author_id INTEGER NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE tribe_forum_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, thread_id INTEGER NOT NULL, author_id INTEGER NOT NULL, body TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)");
}

function seedTribe(SQLiteAdapter $conn): void
{
    $conn->query("INSERT INTO tribes (id, name, tag, founder_id) VALUES (1, 'TestTribe', 'TEST', 1)");
}

function addMember(SQLiteAdapter $conn, int $tribeId, int $userId, string $role): void
{
    $stmt = $conn->prepare("INSERT INTO tribe_members (tribe_id, user_id, role) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $tribeId, $userId, $role);
    $stmt->execute();
    $stmt->close();
}

$conn = new SQLiteAdapter(':memory:');
setupTribeSchema($conn);
seedTribe($conn);
$conn->query("INSERT INTO users (id, username) VALUES (1, 'Leader'), (2, 'Baron'), (3, 'Officer'), (4, 'Member')");
$manager = new TribeManager($conn);

$tests = [];

$tests[] = function () use ($conn, $manager) {
    // Legacy role should map to canonical co_leader
    addMember($conn, 1, 2, 'baron');
    $tribe = $manager->getTribeForUser(2);
    TinyAssert::equals('co_leader', $tribe['role'], 'Legacy baron should canonicalize to co_leader');
};

$tests[] = function () use ($conn, $manager) {
    // Officer (legacy diplomat) can invite and access forum but not manage roles/diplomacy
    addMember($conn, 1, 3, 'diplomat');
    $membership = ['role' => 'diplomat'];
    TinyAssert::true($manager->roleHasPermission($membership['role'], 'invite'), 'Officer should be able to invite');
    TinyAssert::true($manager->roleHasPermission($membership['role'], 'forum'), 'Officer should access forum');
    TinyAssert::false($manager->roleHasPermission($membership['role'], 'diplomacy'), 'Officer cannot manage diplomacy');
    TinyAssert::false($manager->roleHasPermission($membership['role'], 'manage_roles'), 'Officer cannot manage roles');
};

$tests[] = function () use ($conn, $manager) {
    // Leader upgrades member to co_leader; DB stores legacy value
    addMember($conn, 1, 1, 'leader');
    addMember($conn, 1, 4, 'member');
    $result = $manager->changeMemberRole(1, 1, 4, 'co_leader');
    TinyAssert::true($result['success'], 'Leader should be able to promote to co_leader');

    $row = $conn->query("SELECT role FROM tribe_members WHERE user_id = 4")->fetch_assoc();
    TinyAssert::equals('baron', $row['role'], 'DB should store legacy value for co_leader');
};

$tests[] = function () use ($conn, $manager) {
    // Diplomacy upsert should mirror
    addMember($conn, 1, 1, 'leader');
    $conn->query("INSERT INTO tribes (id, name, tag, founder_id) VALUES (2, 'Target', 'ALLY', 5)");
    $result = $manager->setDiplomacyStatus(1, 1, 2, 'ally');
    TinyAssert::true($result['success'], 'Diplomacy set should succeed');
    $rows = $conn->query("SELECT tribe_id, target_tribe_id, status FROM tribe_diplomacy ORDER BY tribe_id")->fetch_all(MYSQLI_ASSOC);
    TinyAssert::equals([
        ['tribe_id' => 1, 'target_tribe_id' => 2, 'status' => 'ally'],
        ['tribe_id' => 2, 'target_tribe_id' => 1, 'status' => 'ally'],
    ], $rows, 'Diplomacy should be symmetric');
};

$tests[] = function () use ($conn, $manager) {
    // Forum posting permitted for officers
    addMember($conn, 1, 3, 'diplomat'); // officer
    $thread = $manager->createThread(1, 3, 'Hello', 'First post');
    TinyAssert::true($thread['success'], 'Officer should create thread');
    TinyAssert::true(isset($thread['thread_id']), 'Thread id should exist');
    $post = $manager->addPost(1, (int)$thread['thread_id'], 3, 'Reply');
    TinyAssert::true($post['success'], 'Officer should add post');
    $threads = $manager->getThreads(1);
    TinyAssert::equals('Hello', $threads[0]['title'], 'Thread title should match');
    $posts = $manager->getPosts((int)$thread['thread_id'], 1);
    TinyAssert::equals(2, count($posts), 'Two posts (initial + reply) expected');
};

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "[PASS] Test " . ($name + 1) . "\n";
    } catch (Throwable $e) {
        $failed++;
        echo "[FAIL] Test " . ($name + 1) . ': ' . $e->getMessage() . "\n";
    }
}

echo "----\n";
echo (count($tests) - $failed) . " passed, {$failed} failed\n";
if ($failed > 0) {
    exit(1);
}
