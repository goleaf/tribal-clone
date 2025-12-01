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
$totalAchievements = count($achievements);
$unlockedCount = array_reduce($achievements, fn($carry, $a) => $carry + ($a['unlocked'] ? 1 : 0), 0);
$completionPercent = $totalAchievements > 0 ? (int)floor(($unlockedCount / $totalAchievements) * 100) : 0;
$categories = array_values(array_unique(array_map(fn($a) => $a['category'], $achievements)));
$categories = array_filter($categories);

$pageTitle = 'Achievements';

require '../header.php';
?>

<div id="game-container" class="achievements-shell">
    <header id="main-header" class="achievements-header">
        <div class="header-title">
            <span class="game-logo"><img src="../img/ds_graphic/quests/check.png" alt="Achievements"></span>
            <div>
                <div class="eyebrow">Hall of Fame</div>
                <div class="title">Achievements</div>
            </div>
        </div>
        <div class="header-user">
            <div class="player-name"><?= htmlspecialchars($username) ?></div>
            <?php if ($village): ?>
                <div class="village-name-display" data-village-id="<?= $village['id'] ?>"><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</div>
            <?php endif; ?>
        </div>
    </header>

    <div id="main-content">
        <main class="achievements-page">
            <section class="achievements-hero">
                <div>
                    <h2>Milestones & rewards</h2>
                    <p>Push your empire forward. Earn resources, ranking points, and bragging rights.</p>
                    <div class="legend-row">
                        <span class="legend unlocked">Unlocked</span>
                        <span class="legend in-progress">In progress</span>
                    </div>
                </div>
                <div class="achievements-stats">
                    <div class="stat-card">
                        <div class="label">Unlocked</div>
                        <div class="value"><?= $unlockedCount ?> / <?= $totalAchievements ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Completion</div>
                        <div class="value"><?= $completionPercent ?>%</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Categories</div>
                        <div class="value"><?= count($categories) ?></div>
                    </div>
                </div>
            </section>

            <?php if (empty($achievements)): ?>
                <div class="no-data">No achievements are configured yet.</div>
            <?php else: ?>
                <section class="achievements-filters">
                    <div class="filter-group">
                        <span class="filter-label">Status</span>
                        <button class="filter-pill active" data-filter="all">All</button>
                        <button class="filter-pill" data-filter="unlocked">Unlocked</button>
                        <button class="filter-pill" data-filter="progress">In progress</button>
                    </div>
                    <?php if (!empty($categories)): ?>
                    <div class="filter-group">
                        <span class="filter-label">Category</span>
                        <button class="filter-pill active" data-category="all">All</button>
                        <?php foreach ($categories as $cat): ?>
                            <button class="filter-pill" data-category="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars(ucfirst($cat)) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>

                <div class="achievements-grid" id="achievements-grid">
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
                        <article class="achievement-card <?= $isUnlocked ? 'unlocked' : 'in-progress' ?>" data-status="<?= $isUnlocked ? 'unlocked' : 'progress' ?>" data-category="<?= htmlspecialchars($achievement['category']) ?>">
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

<style>
.achievements-shell {
    background: radial-gradient(circle at 20% 20%, #f1e4c9, #e0c99f 60%, #d0b580);
}
.achievements-header .game-logo img { width: 28px; height: 28px; }
.eyebrow { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #8d5c2c; }
.title { font-size: 22px; font-weight: 700; color: #402611; }
.achievements-page { padding: 14px 16px 24px; }
.achievements-hero { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; background: rgba(255,255,255,0.85); border:1px solid #d2b17a; border-radius:14px; padding:14px 16px; box-shadow:0 14px 24px rgba(0,0,0,0.08); margin-bottom:14px; flex-wrap:wrap; }
.achievements-hero h2 { margin:0 0 4px 0; }
.achievements-hero p { margin:0; color:#4d341a; }
.achievements-stats { display:flex; gap:10px; flex-wrap:wrap; }
.stat-card { background:#fff; border:1px solid #d2b17a; border-radius:10px; padding:10px 12px; min-width:110px; box-shadow:0 10px 18px rgba(0,0,0,0.08); }
.stat-card .label { font-size:11px; text-transform:uppercase; color:#8d5c2c; letter-spacing:0.06em; }
.stat-card .value { font-size:18px; font-weight:700; color:#3b2410; }
.legend-row { display:flex; gap:8px; align-items:center; margin-top:8px; }
.legend { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.legend.unlocked { background:#2f9d64; color:#fff; }
.legend.in-progress { background:#f0c46b; color:#3b2410; }
.achievements-filters { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:10px; align-items:center; }
.filter-group { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.filter-label { font-weight:700; color:#3b2410; }
.filter-pill { border:1px solid #d2b17a; background:#fff8ec; color:#3b2410; padding:6px 10px; border-radius:999px; cursor:pointer; transition:all 120ms ease; }
.filter-pill.active { background:#e7b36f; color:#2d1a0a; box-shadow:0 8px 14px rgba(0,0,0,0.08); }
.achievements-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:12px; }
.achievement-card { background:rgba(255,255,255,0.92); border:1px solid #d2b17a; border-radius:12px; padding:12px; box-shadow:0 12px 20px rgba(0,0,0,0.08); display:flex; flex-direction:column; gap:8px; }
.achievement-card.unlocked { border-color:#2f9d64; box-shadow:0 12px 22px rgba(47,157,100,0.2); }
.achievement-card__top { display:flex; justify-content:space-between; align-items:center; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; }
.achievement-category { color:#8d5c2c; }
.achievement-status { font-weight:700; color:#2f9d64; }
.achievement-card.in-progress .achievement-status { color:#c1711f; }
.achievement-description { margin:0; color:#3b2410; min-height:38px; }
.achievement-progress { width:100%; height:8px; border-radius:999px; background:#f5e6ca; overflow:hidden; }
.achievement-progress-bar { height:100%; border-radius:999px; background:linear-gradient(135deg, #f7d8aa, #e7b36f); }
.achievement-progress-meta { display:flex; justify-content:space-between; font-size:12px; color:#5a3a1d; }
.achievement-rewards { font-size:13px; color:#3b2410; }
.achievement-date { color:#74522d; }
@media (max-width: 768px) {
    .achievements-hero { flex-direction:column; }
    .achievements-stats { width:100%; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterPills = document.querySelectorAll('.filter-pill');
    const cards = document.querySelectorAll('.achievement-card');

    function applyFilters() {
        const statusFilter = document.querySelector('.filter-pill.active[data-filter]')?.dataset.filter || 'all';
        const categoryFilter = document.querySelector('.filter-pill.active[data-category]')?.dataset.category || 'all';

        cards.forEach(card => {
            const status = card.dataset.status;
            const category = card.dataset.category;
            const statusMatch = statusFilter === 'all' || status === statusFilter;
            const categoryMatch = categoryFilter === 'all' || category === categoryFilter;
            card.style.display = (statusMatch && categoryMatch) ? '' : 'none';
        });
    }

    filterPills.forEach(pill => {
        pill.addEventListener('click', () => {
            const groupAttr = pill.dataset.filter ? 'filter' : (pill.dataset.category ? 'category' : null);
            if (!groupAttr) return;
            document.querySelectorAll(`.filter-pill[data-${groupAttr}]`).forEach(btn => btn.classList.remove('active'));
            pill.classList.add('active');
            applyFilters();
        });
    });
});
</script>
