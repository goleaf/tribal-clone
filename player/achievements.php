<?php
declare(strict_types=1);
require '../init.php';
require_once __DIR__ . '/../lib/managers/AchievementManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];

$villageManager = new VillageManager($conn);
$achievementManager = new AchievementManager($conn);

$village = $villageManager->getFirstVillage($user_id);
if ($village) {
    $village = $villageManager->getVillageInfo($village['id']);
}

// Refresh achievements in case progress was made elsewhere
$achievementManager->evaluateAutoUnlocks($user_id);
$achievements = $achievementManager->getUserAchievementsWithProgress($user_id);

$pageTitle = 'Achievements';

require '../header.php';
?>

<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">&#127942;</span>
            <span>Achievements</span>
        </div>
        <div class="header-user">
            Player: <?= htmlspecialchars($username) ?><br>
            <?php if ($village): ?>
                <span class="village-name-display" data-village-id="<?= $village['id'] ?>"><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</span>
            <?php endif; ?>
        </div>
    </header>

    <div id="main-content">
        <main class="achievements-page">
            <section class="achievements-intro">
                <div>
                    <h2>Milestones and rewards</h2>
                    <p>Complete milestones to earn instant resource boosts and extra points. Rewards are delivered automatically to your first village.</p>
                </div>
                <div class="achievements-legend">
                    <span class="legend unlocked">Unlocked</span>
                    <span class="legend in-progress">In progress</span>
                </div>
            </section>

            <?php if (empty($achievements)): ?>
                <div class="no-data">No achievements are configured yet.</div>
            <?php else: ?>
                <div class="achievements-grid">
                    <?php foreach ($achievements as $achievement):
                        $progress = (int)$achievement['progress'];
                        $target = max(1, (int)$achievement['condition_value']);
                        $percent = min(100, (int)floor(($progress / $target) * 100));
                        $isUnlocked = $achievement['unlocked'];
                        $statusLabel = $isUnlocked ? 'Unlocked' : 'In progress';
                        $rewardParts = [];
                        if ($achievement['reward_wood'] > 0) { $rewardParts[] = 'Wood +' . formatNumber($achievement['reward_wood']); }
                        if ($achievement['reward_clay'] > 0) { $rewardParts[] = 'Clay +' . formatNumber($achievement['reward_clay']); }
                        if ($achievement['reward_iron'] > 0) { $rewardParts[] = 'Iron +' . formatNumber($achievement['reward_iron']); }
                        if ($achievement['reward_points'] > 0) { $rewardParts[] = '+' . formatNumber($achievement['reward_points']) . ' ranking points'; }
                        $rewardText = empty($rewardParts) ? 'No reward configured' : implode(' · ', $rewardParts);
                        $unlockedAt = $achievement['unlocked_at'] ? date('Y-m-d H:i', strtotime($achievement['unlocked_at'])) : null;
                    ?>
                        <article class="achievement-card <?= $isUnlocked ? 'unlocked' : 'in-progress' ?>">
                            <div class="achievement-card__top">
                                <span class="achievement-category"><?= htmlspecialchars(ucfirst($achievement['category'])) ?></span>
                                <span class="achievement-status"><?= htmlspecialchars($statusLabel) ?></span>
                            </div>
                            <h3><?= htmlspecialchars($achievement['name']) ?></h3>
                            <p class="achievement-description"><?= htmlspecialchars($achievement['description']) ?></p>

                            <div class="achievement-progress">
                                <div class="achievement-progress-bar" style="width: <?= $percent ?>%;"></div>
                            </div>
                            <div class="achievement-progress-meta">
                                <span><?= formatNumber($progress) ?> / <?= formatNumber($target) ?></span>
                                <?php if ($unlockedAt): ?>
                                    <span class="achievement-date">Unlocked <?= htmlspecialchars($unlockedAt) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="achievement-rewards">
                                <strong>Reward:</strong> <?= htmlspecialchars($rewardText) ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require '../footer.php'; ?>
