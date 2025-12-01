<?php
declare(strict_types=1);

/**
 * Cron-friendly script that disbands tribes with fewer than 3 active members
 * for at least 14 days. "Active" is defined by users.last_activity_at.
 *
 * Run: php jobs/disband_inactive_tribes.php
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/TribeManager.php';

$tribeManager = new TribeManager($conn);

$graceDays = 14;
$now = time();
$activeSince = date('Y-m-d H:i:s', $now - ($graceDays * 86400));
$createdBefore = date('Y-m-d H:i:s', $now - ($graceDays * 86400));

$stmt = $conn->prepare("SELECT id, name, tag, created_at FROM tribes");
if ($stmt === false) {
    fwrite(STDERR, "Failed to fetch tribes: " . ($conn->error ?? 'unknown error') . PHP_EOL);
    exit(1);
}
$stmt->execute();
$res = $stmt->get_result();
$tribes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

$disbanded = 0;

foreach ($tribes as $tribe) {
    $tribeId = (int)$tribe['id'];
    $activeMembers = $tribeManager->getActiveMemberCountSince($tribeId, $activeSince);
    if ($activeMembers >= 3) {
        continue;
    }

    $createdAt = $tribe['created_at'] ?? null;
    if ($createdAt && strtotime($createdAt) > strtotime($createdBefore)) {
        // Recent tribe; give it more time.
        continue;
    }

    $result = $tribeManager->systemDisbandTribe($tribeId, 'inactive_below_min_members');
    if ($result['success'] ?? false) {
        $disbanded++;
        $tag = $tribe['tag'] ?? '';
        echo "[DISBANDED] Tribe #{$tribeId} {$tribe['name']} ({$tag}) — active members: {$activeMembers}" . PHP_EOL;
    } else {
        $msg = $result['message'] ?? 'unknown error';
        echo "[SKIPPED] Failed to disband tribe #{$tribeId}: {$msg}" . PHP_EOL;
    }
}

echo "Cleanup complete. Disbanded {$disbanded} tribe(s)." . PHP_EOL;
