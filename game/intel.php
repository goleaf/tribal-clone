<?php
declare(strict_types=1);

$pageTitle = 'Intel';
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../lib/managers/IntelManager.php';
require_once __DIR__ . '/../lib/managers/TribeManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];
$intelManager = new IntelManager($conn);
$tribeManager = new TribeManager($conn);
$membership = $tribeManager->getMembershipPublic($userId);
$reports = $intelManager->getReportsForUser($userId, 50, true);
$missionTypes = $intelManager->getMissionTypes();

function renderPill(string $text, string $tone = 'muted'): string {
    $toneClass = 'pill-' . $tone;
    return '<span class="pill ' . htmlspecialchars($toneClass) . '">' . htmlspecialchars($text) . '</span>';
}
?>

<main class="content">
    <div class="intel-dashboard card">
        <div class="intel-header">
            <div>
                <p class="eyebrow">Fog of War</p>
                <h2>Intel Dashboard</h2>
                <p class="muted">Latest scouting reports, freshness, and sharing tools.</p>
            </div>
            <div class="intel-meta">
                <?= renderPill('Reports: ' . count($reports), 'accent'); ?>
                <?= renderPill($membership ? 'Tribe sharing on' : 'Solo', $membership ? 'success' : 'muted'); ?>
            </div>
        </div>

        <section class="intel-legend">
            <h3>Mission types</h3>
            <div class="legend-grid">
                <?php foreach ($missionTypes as $preset): ?>
                    <div class="legend-card">
                        <div class="legend-title">
                            <strong><?= htmlspecialchars($preset['name']) ?></strong>
                            <span class="muted"><?= htmlspecialchars($preset['category']) ?></span>
                        </div>
                        <p class="muted"><?= htmlspecialchars($preset['summary']) ?></p>
                        <div class="legend-row">
                            <span><?= renderPill('Detect ' . (int)$preset['base_detection_risk'] . '%', 'warning') ?></span>
                            <span><?= renderPill('Quality ' . (int)$preset['base_quality'], 'info') ?></span>
                            <span><?= renderPill('Speed x' . number_format((float)$preset['speed_factor'], 2), 'neutral') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="intel-reports">
            <div class="section-head">
                <h3>Recent reports</h3>
                <p class="muted">Includes your own scouts and tribe-shared intel.</p>
            </div>

            <?php if (empty($reports)): ?>
                <p class="muted">No intel yet. Send a spy mission from the Rally Point to populate this screen.</p>
            <?php else: ?>
                <table class="intel-table">
                    <thead>
                        <tr>
                            <th>Target</th>
                            <th>Mission</th>
                            <th>Outcome</th>
                            <th>Quality</th>
                            <th>Freshness</th>
                            <th>Detection</th>
                            <th>Intel</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report):
                            $intelPayload = json_decode($report['intel_json'] ?? '{}', true) ?: [];
                            $intelSections = [];
                            if (!empty($intelPayload['intel']['resources'])) $intelSections[] = 'Resources';
                            if (!empty($intelPayload['intel']['buildings'])) $intelSections[] = 'Buildings';
                            if (!empty($intelPayload['intel']['units'])) $intelSections[] = 'Units';
                            if (!empty($intelPayload['intel']['research'])) $intelSections[] = 'Research';
                            $intelSummary = !empty($intelSections) ? implode(', ', $intelSections) : 'Minimal';

                            $outcomeTone = $report['outcome'] === 'success'
                                ? 'success'
                                : ($report['outcome'] === 'partial' ? 'info' : 'warning');
                            $freshTone = match ($report['freshness']) {
                                'fresh', 'recent' => 'success',
                                'aging' => 'info',
                                'stale' => 'warning',
                                default => 'muted'
                            };
                            $canShare = !empty($membership['tribe_id']) && empty($report['shared_with_tribe']);
                            ?>
                            <tr>
                                <td>
                                    <div class="target-line">
                                        <strong><?= htmlspecialchars($report['target_village_name'] ?? 'Village') ?></strong>
                                        <span class="muted">
                                            (<?= (int)($report['target_x'] ?? 0) ?>|<?= (int)($report['target_y'] ?? 0) ?>)
                                        </span>
                                    </div>
                                    <div class="muted small">
                                        From <?= htmlspecialchars($report['source_village_name'] ?? 'Unknown') ?>
                                    </div>
                                </td>
                                <td><?= renderPill($report['mission_type'] ?? 'light_scout', 'neutral') ?></td>
                                <td><?= renderPill(ucfirst($report['outcome']), $outcomeTone) ?></td>
                                <td>
                                    <div class="stat-line">
                                        <span class="stat-label">Q</span>
                                        <span><?= (int)$report['quality'] ?>%</span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="stat-label">Conf</span>
                                        <span><?= (int)$report['confidence'] ?>%</span>
                                    </div>
                                </td>
                                <td><?= renderPill($report['freshness_label'] ?? '', $freshTone) ?></td>
                                <td><?= renderPill(!empty($report['detection']) ? 'Detected' : 'Hidden', !empty($report['detection']) ? 'warning' : 'success') ?></td>
                                <td>
                                    <div class="muted small"><?= htmlspecialchars($intelSummary) ?></div>
                                    <?php if (!empty($report['tags'])): ?>
                                        <div class="tag-row">
                                            <?php foreach ($report['tags'] as $tag): ?>
                                                <span class="tag-pill" style="background: <?= htmlspecialchars($tag['color']) ?>;">
                                                    <?= htmlspecialchars($tag['name']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-stack">
                                        <span class="muted small"><?= relativeTime((int)$report['gathered_at']) ?></span>
                                        <?php if ($canShare): ?>
                                            <button class="btn btn-secondary share-report-btn" data-report-id="<?= (int)$report['id'] ?>">Share to tribe</button>
                                        <?php elseif (!empty($report['shared_with_tribe'])): ?>
                                            <?= renderPill('Shared', 'accent') ?>
                                        <?php else: ?>
                                            <span class="muted small">No tribe share</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    document.querySelectorAll('.share-report-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const reportId = btn.dataset.reportId;
            btn.disabled = true;
            btn.textContent = 'Sharing...';
            try {
                const res = await fetch('/ajax/scout/share_report.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token
                    },
                    body: JSON.stringify({ report_id: Number(reportId) })
                });
                const data = await res.json();
                if (data.success) {
                    btn.textContent = 'Shared';
                    btn.classList.add('btn-success');
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Share to tribe';
                    alert(data.error || 'Unable to share report.');
                }
            } catch (err) {
                btn.disabled = false;
                btn.textContent = 'Share to tribe';
                alert('Network error while sharing report.');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
