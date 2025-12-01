<?php
declare(strict_types=1);
require '../init.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';
require_once __DIR__ . '/../lib/managers/ReportStateManager.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);
$reportStateManager = new ReportStateManager($conn);

// Handle AJAX requests for report details
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && isset($_GET['report_id'])) {
    $report_id = (int)$_GET['report_id'];
    $result = $battleManager->getBattleReport($report_id, $user_id);
    if ($result['success']) {
        $reportStateManager->markRead($report_id, $user_id);
        $state = $reportStateManager->getState($report_id, $user_id);
        $result['report']['is_starred'] = $state['is_starred'];
        $result['report']['is_read'] = $state['is_read'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit(); // Stop execution after returning JSON
}

$username = $_SESSION['username'];

// Process completed attacks (BattleManager already uses $conn from init.php)
$battleManager->processCompletedAttacks($user_id);

// === Pagination ===
$reportsPerPage = 20; // Reports per page
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $reportsPerPage;
$totalReports = 0;
$totalPages = 1;

// Fetch total reports for pagination using BattleManager
$totalReports = $battleManager->getTotalBattleReportsForUser($user_id);

if ($totalReports > 0) {
    $totalPages = ceil($totalReports / $reportsPerPage);

    // Ensure the current page does not exceed the total page count
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $reportsPerPage;
    }
}

// Direct report details (optional initial render)
$report_details = null;
if (isset($_GET['report_id'])) {
    $report_id = (int)$_GET['report_id'];
    $result = $battleManager->getBattleReport($report_id, $user_id);
    
    if ($result['success']) {
        $reportStateManager->markRead($report_id, $user_id);
        $state = $reportStateManager->getState($report_id, $user_id);
        $report_details = $result['report'];
        $report_details['is_starred'] = $state['is_starred'];
        $report_details['is_read'] = $state['is_read'];
    }
}

// Fetch battle reports the user participated in (with pagination)
$reports = $battleManager->getBattleReportsForUser($user_id, $reportsPerPage, $offset);
foreach ($reports as &$report) {
    $state = $reportStateManager->getState((int)$report['report_id'], $user_id);
    $report['is_starred'] = $state['is_starred'] ?? 0;
    $report['is_read'] = $state['is_read'] ?? 0;
}
unset($report);
$unreadCount = $reportStateManager->countUnreadForUser($user_id);

$pageTitle = 'Battle reports'; // Will become generic "Reports" once other types are added
require '../header.php';
?>

    <div id="game-container">
    <!-- Game header with resources -->
        <header id="main-header">
            <div class="header-title">
            <span class="game-logo">&#128196;</span> <!-- Icon for reports -->
            <span>Reports</span>
        </div>
        <div class="header-user">
            Player: <?= htmlspecialchars($username) ?><br>
            <?php if (!empty($firstVidData)): ?>
                <span class="village-name-display" data-village-id="<?= $firstVidData['id'] ?>"><?= htmlspecialchars($firstVidData['name']) ?> (<?= $firstVidData['x_coord'] ?>|<?= $firstVidData['y_coord'] ?>)</span>
            <?php else: ?>
                <span class="village-name-display">No village</span>
            <?php endif; ?>
        </div>
        </header>
        
        <div id="main-content">
        <!-- Sidebar navigation -->
        
            
            <main>
                <h2>Battle reports</h2>
                <div class="reports-stats" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
                    <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                        <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Total</div>
                        <div style="font-size:22px;font-weight:700;"><?= (int)$totalReports ?></div>
                    </div>
                    <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                        <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Unread</div>
                        <div style="font-size:22px;font-weight:700;" data-unread-count><?= (int)$unreadCount ?></div>
                    </div>
                    <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                        <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Page</div>
                        <div style="font-size:22px;font-weight:700;"><?= $currentPage ?> / <?= $totalPages ?></div>
                    </div>
                </div>
                
            <?php if (isset($_GET['action_success'])): ?>
                <div class="success-message">Action completed successfully.</div>
            <?php endif; ?>
            
            <!-- Placeholder for future tabs for different report types -->
            <div class="reports-tabs">
                 <a href="reports.php" class="tab active">Battle reports</a>
                 <!-- <a href="reports.php?type=trade" class="tab">Trade reports</a> -->
                 <!-- <a href="reports.php?type=support" class="tab">Support reports</a> -->
                 <!-- <a href="reports.php?type=other" class="tab">Other reports</a> -->
            </div>

            <?php if (!empty($reports)): ?>
                <div class="reports-container">
                    <!-- Reports list -->
                    <div class="reports-list">
                        <?php foreach ($reports as $report): ?>
                            <div class="report-item <?= empty($report['is_read']) ? 'unread' : '' ?> <?= !empty($report['is_starred']) ? 'starred' : '' ?>" data-report-id="<?= $report['report_id'] ?>" data-read="<?= !empty($report['is_read']) ? '1' : '0' ?>" data-starred="<?= !empty($report['is_starred']) ? '1' : '0' ?>">
                                <div class="report-title">
                                    <span class="report-icon"><?= $report['attacker_won'] ? '&#9876;' : '&#128737;' ?></span>
                                    <span class="report-name"><?= ucfirst($report['type']) ?> - <?= htmlspecialchars($report['source_village_name']) ?> (<?= $report['source_x'] ?>|<?= $report['source_y'] ?>) → <?= htmlspecialchars($report['target_village_name']) ?> (<?= $report['target_x'] ?>|<?= $report['target_y'] ?>)</span>
                                    <button type="button" class="report-star<?= !empty($report['is_starred']) ? ' active' : '' ?>" data-report-id="<?= $report['report_id'] ?>" data-starred="<?= !empty($report['is_starred']) ? '1' : '0' ?>" aria-pressed="<?= !empty($report['is_starred']) ? 'true' : 'false' ?>" title="<?= !empty($report['is_starred']) ? 'Starred report' : 'Mark as important' ?>">
                                        <?= !empty($report['is_starred']) ? '★' : '☆' ?>
                                    </button>
                                </div>
                                <div class="report-villages">
                                    From: <?= htmlspecialchars($report['attacker_name']) ?> (<?= htmlspecialchars($report['source_village_name']) ?>) To: <?= htmlspecialchars($report['defender_name']) ?> (<?= htmlspecialchars($report['target_village_name']) ?>)
                                </div>
                                <div class="report-date">
                                    <?= htmlspecialchars($report['formatted_date']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Report details (loaded dynamically or shown after selection) -->
                    <div class="report-details" id="report-details">
                        <?php if ($report_details): ?>
                            <p>Loading report...</p>
                        <?php else: ?>
                            <p>Select a report from the list to view details.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                     <?php if ($totalPages > 1): ?>
                        Page: 
                         <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="reports.php?page=<?= $i ?>" class="page-link <?= $i == $currentPage ? 'active' : '' ?>"><?= $i ?></a>
                         <?php endfor; ?>
                        
                         <?php if ($currentPage < $totalPages): ?>
                             <a href="reports.php?page=<?= $currentPage + 1 ?>" class="page-link">Next</a>
                         <?php endif; ?>
                     <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="reports-empty" style="background:#fff;border:1px dashed #d9c4a7;padding:20px;border-radius:10px;">
                    <h3>No battle reports yet</h3>
                    <p>You have no battle reports on this page. Launch an attack or check another page to see new reports.</p>
                </div>
            <?php endif; ?>
            </main>
        </div>
    </div>

<?php require '../footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const reportsList = document.querySelector('.reports-list');
    const reportDetailsArea = document.getElementById('report-details');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let unreadCount = <?= (int)$unreadCount ?>;
    const unreadCountEl = document.querySelector('[data-unread-count]');

    function formatDateTime(datetimeString) {
        const date = new Date(datetimeString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${day}.${month}.${year} ${hours}:${minutes}`;
    }

    function escapeHTML(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function updateUnreadDisplay() {
        if (unreadCountEl) {
            unreadCountEl.textContent = unreadCount;
        }
    }

    function markListItemRead(reportId) {
        if (!reportsList) return;
        const item = reportsList.querySelector(`.report-item[data-report-id="${reportId}"]`);
        if (item && item.dataset.read !== '1') {
            item.dataset.read = '1';
            item.classList.remove('unread');
            unreadCount = Math.max(0, unreadCount - 1);
            updateUnreadDisplay();
        }
    }

    function setStarButtonState(button, isStarred) {
        button.dataset.starred = isStarred ? '1' : '0';
        const isListButton = button.classList.contains('report-star');
        button.textContent = isListButton ? (isStarred ? '★' : '☆') : (isStarred ? 'Unstar' : 'Star');
        button.setAttribute('aria-pressed', isStarred ? 'true' : 'false');
        button.classList.toggle('active', isStarred);
        button.title = isStarred ? 'Starred report' : 'Mark as important';
    }

    function syncListStar(reportId, isStarred) {
        if (!reportsList) return;
        const item = reportsList.querySelector(`.report-item[data-report-id="${reportId}"]`);
        if (!item) return;
        item.dataset.starred = isStarred ? '1' : '0';
        item.classList.toggle('starred', isStarred);
        const starBtn = item.querySelector('.report-star');
        if (starBtn) {
            setStarButtonState(starBtn, isStarred);
        }
    }

    function toggleStar(reportId, shouldStar) {
        const payload = new URLSearchParams();
        payload.set('report_id', reportId);
        payload.set('starred', shouldStar ? '1' : '0');
        if (csrfToken) {
            payload.set('csrf_token', csrfToken);
        }

        return fetch('../ajax/reports/toggle_star.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Request failed.');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    return true;
                }
                throw new Error(data.message || 'Could not update star.');
            });
    }

    function renderUnitsTable(units) {
        let tableHtml = `
            <table class="units-table">
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Sent</th>
                        <th>Lost</th>
                        <th>Remaining</th>
                    </tr>
                </thead>
                <tbody>
        `;
        if (units && units.length > 0) {
            units.forEach(unit => {
                tableHtml += `
                    <tr>
                        <td class="unit-name">${escapeHTML(unit.name || 'Unit')}</td>
                        <td>${unit.initial_count ?? 0}</td>
                        <td class="unit-lost">${unit.lost_count ?? 0}</td>
                        <td>${unit.remaining_count ?? 0}</td>
                    </tr>
                `;
            });
        } else {
            tableHtml += `<tr><td colspan="4">No units</td></tr>`;
        }
        tableHtml += `
                </tbody>
            </table>
        `;
        return tableHtml;
    }

    function renderSpyIntel(details) {
        const intel = details.intel || {};
        let html = `
            <div class="battle-summary">
                <p>${details.success ? 'Mission succeeded' : 'Mission failed'}.</p>
                <p>Scouts sent: ${details.attacker_spies_sent ?? 0}, lost: ${details.attacker_spies_lost ?? 0}.</p>
                <p>Defender scouts: ${details.defender_spies ?? 0}, lost: ${details.defender_spies_lost ?? 0}.</p>
        `;

        if (intel.resources) {
            html += `
                <div class="battle-loot">
                    <h4>Resources seen</h4>
                    <p>Wood: ${intel.resources.wood ?? 0}, Clay: ${intel.resources.clay ?? 0}, Iron: ${intel.resources.iron ?? 0}</p>
                </div>
            `;
        }

        if (intel.buildings) {
            html += `<div class="village-changes"><h4>Building levels</h4><ul>`;
            Object.entries(intel.buildings).forEach(([key, value]) => {
                html += `<li>${escapeHTML(value.name || key)}: level ${value.level ?? 0}</li>`;
            });
            html += `</ul></div>`;
        }

        if (intel.units && Array.isArray(intel.units)) {
            html += `<div class="battle-loot"><h4>Garrison overview</h4><ul>`;
            intel.units.forEach(unit => {
                html += `<li>${escapeHTML(unit.name || unit.internal_name || 'Unit')}: ${unit.count ?? 0}</li>`;
            });
            html += `</ul></div>`;
        }

        if (intel.research) {
            html += `<div class="village-changes"><h4>Research intel</h4><ul>`;
            Object.entries(intel.research).forEach(([key, value]) => {
                html += `<li>${escapeHTML(value.name || key)}: level ${value.level ?? 0}</li>`;
            });
            html += `</ul></div>`;
        }

        html += `</div>`;
        return html;
    }

    function renderSummaryChips(report) {
        const chips = [];
        const details = report.details || {};
        if (details.morale !== undefined) {
            chips.push(`<span class="chip">Morale: ${(details.morale * 100).toFixed(0)}%</span>`);
        }
        if (details.attack_luck !== undefined) {
            const pct = ((details.attack_luck - 1) * 100).toFixed(0);
            chips.push(`<span class="chip">Luck: ${pct > 0 ? '+' : ''}${pct}%</span>`);
        }
        if (details.wall_level !== undefined) {
            const eff = details.effective_wall_level ?? details.wall_level;
            chips.push(`<span class="chip">Wall: ${details.wall_level} → ${eff}</span>`);
        }
        if (details.loyalty && details.loyalty.drop) {
            chips.push(`<span class="chip">Loyalty: ${details.loyalty.before} → ${details.loyalty.after}</span>`);
        }
        return chips.length ? `<div class="report-chips">${chips.join('')}</div>` : '';
    }

    function renderReportDetails(reportData) {
        const report = reportData.report;
        const details = report.details || {};
        const type = report.type || report.attack_type || details.type || 'battle';
        const isStarred = !!report.is_starred;
        let detailsHtml = `
            <div class="report-details-content">
                <div class="report-header">
                    <div>
                        <h3>${type === 'spy' ? 'Spy report' : 'Battle report'} #${report.id}</h3>
                        <p class="battle-village">${escapeHTML(report.attacker_name)} (${escapeHTML(report.source_village_name)} ${report.source_x}|${report.source_y}) → ${escapeHTML(report.defender_name)} (${escapeHTML(report.target_village_name)} ${report.target_x}|${report.target_y})</p>
                        <p class="battle-time">Timestamp: ${formatDateTime(report.battle_time)}</p>
                    </div>
                </div>
                ${renderSummaryChips(report)}
        `;

        if (type === 'spy') {
            detailsHtml += renderSpyIntel(details);
        } else {
            detailsHtml += `
                <div class="battle-summary">
                    <div class="battle-side ${report.attacker_won ? 'winner' : 'loser'}">
                        <h4>Attacker</h4>
                        ${renderUnitsTable(report.attacker_units)}
                    </div>
                    <div class="battle-side ${report.attacker_won ? 'loser' : 'winner'}">
                        <h4>Defender</h4>
                        ${renderUnitsTable(report.defender_units)}
                    </div>
            `;

            if (details.loot) {
                detailsHtml += `
                    <div class="battle-loot">
                        <h4>Loot</h4>
                        <p>Wood: ${details.loot.wood ?? 0}, Clay: ${details.loot.clay ?? 0}, Iron: ${details.loot.iron ?? 0}</p>
                    </div>
                `;
            }

            if (details.loyalty) {
                const l = details.loyalty;
                const change = l.drop ? ` (${l.drop > 0 ? '-' : ''}${l.drop})` : '';
                const conquered = l.conquered ? '<strong>Village conquered!</strong>' : '';
                detailsHtml += `
                    <div class="battle-loyalty">
                        <h4>Loyalty</h4>
                        <p>Before: ${l.before ?? '?'} → After: ${l.after ?? '?'}${change} ${conquered}</p>
                    </div>
                `;
            }

            if (details.building_damage) {
                const b = details.building_damage;
                detailsHtml += `
                    <div class="battle-building">
                        <h4>Building damage</h4>
                        <p>${escapeHTML(b.building_name || 'Target')}: ${b.initial_level ?? '?'} → ${b.final_level ?? '?'}</p>
                    </div>
                `;
            }

            if (details.wall_damage) {
                const w = details.wall_damage;
                detailsHtml += `
                    <div class="battle-wall">
                        <h4>Wall damage</h4>
                        <p>Wall: ${w.initial_level ?? '?'} → ${w.final_level ?? '?'}</p>
                    </div>
                `;
            }

            detailsHtml += `</div>`;
        }

        detailsHtml += `
                <div class="report-actions">
                    <button class="btn-secondary toggle-star ${isStarred ? 'active' : ''}" data-report-id="${report.id}" data-starred="${isStarred ? '1' : '0'}" aria-pressed="${isStarred ? 'true' : 'false'}">${isStarred ? 'Unstar' : 'Star'}</button>
                    <button class="btn-secondary copy-link" data-link="reports.php?report_id=${report.id}">Copy share link</button>
                    <button class="btn-secondary share-tribe" disabled title="Tribe sharing coming soon">Share with tribe</button>
                    <button class="btn-secondary forward-report" disabled title="Forwarding to players coming soon">Forward to player</button>
                </div>
            </div>
        `;

        reportDetailsArea.innerHTML = detailsHtml;

        const starBtn = reportDetailsArea.querySelector('.toggle-star');
        if (starBtn) {
            starBtn.addEventListener('click', () => {
                const nextState = starBtn.dataset.starred === '1' ? false : true;
                starBtn.disabled = true;
                toggleStar(report.id, nextState)
                    .then(() => {
                        setStarButtonState(starBtn, nextState);
                        syncListStar(report.id, nextState);
                    })
                    .catch(error => {
                        alert(error.message || 'Could not update star state.');
                    })
                    .finally(() => {
                        starBtn.disabled = false;
                    });
            });
        }

        // Wire copy action
        const copyBtn = reportDetailsArea.querySelector('.copy-link');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                const link = copyBtn.dataset.link;
                navigator.clipboard.writeText(window.location.origin + '/' + link).then(() => {
                    copyBtn.textContent = 'Link copied';
                    setTimeout(() => copyBtn.textContent = 'Copy share link', 1500);
                }).catch(() => alert('Could not copy link'));
            });
        }
    }

    function loadReportDetails(reportId) {
        reportDetailsArea.innerHTML = '<p>Loading report...</p>';
        reportDetailsArea.classList.add('loading');

        fetch(`reports.php?report_id=${reportId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                reportDetailsArea.classList.remove('loading');

                if (data.success) {
                    renderReportDetails(data);
                    if (data.report && data.report.id) {
                        markListItemRead(data.report.id);
                        syncListStar(data.report.id, !!data.report.is_starred);
                    }
                } else {
                    reportDetailsArea.innerHTML = `<p class="error-message">${escapeHTML(data.message || data.error || 'Failed to load the report.')}</p>`;
                }
            })
            .catch(error => {
                reportDetailsArea.classList.remove('loading');
                reportDetailsArea.innerHTML = '<p class="error-message">A communication error occurred while loading the report.</p>';
                console.error('Fetch error:', error);
            });
    }

    if (reportsList && reportDetailsArea) {
        reportsList.addEventListener('click', (event) => {
            const starBtn = event.target.closest('.report-star');
            if (starBtn) {
                event.stopPropagation();
                const reportId = starBtn.dataset.reportId || starBtn.closest('.report-item')?.dataset.reportId;
                if (!reportId) return;
                const nextState = starBtn.dataset.starred === '1' ? false : true;
                starBtn.disabled = true;
                toggleStar(reportId, nextState)
                    .then(() => {
                        setStarButtonState(starBtn, nextState);
                        syncListStar(reportId, nextState);
                        const detailStar = reportDetailsArea.querySelector('.toggle-star');
                        if (detailStar && detailStar.dataset.reportId === reportId) {
                            setStarButtonState(detailStar, nextState);
                        }
                    })
                    .catch(error => alert(error.message || 'Could not update star state.'))
                    .finally(() => {
                        starBtn.disabled = false;
                    });
                return;
            }

            const reportItem = event.target.closest('.report-item');
            if (reportItem) {
                const reportId = reportItem.dataset.reportId;
                if (reportId) {
                    loadReportDetails(reportId);
                }
            }
        });

        const urlParams = new URLSearchParams(window.location.search);
        const initialReportId = urlParams.get('report_id');
        if (initialReportId) {
            loadReportDetails(initialReportId);
        } else {
            const firstReport = reportsList.querySelector('.report-item');
            if (firstReport) {
                loadReportDetails(firstReport.dataset.reportId);
            } else {
                reportDetailsArea.innerHTML = '<p>Select a report from the list to view details.</p>';
            }
        }
    }
});
</script>

<style>
/* Pagination styles (copied from messages.php for consistency) */
/*
.pagination {
    display: flex;
    justify-content: center;
    margin-top: var(--spacing-md);
    gap: var(--spacing-sm);
}

.pagination .page-link {
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--beige-darker);
    border-radius: var(--border-radius-small);
    text-decoration: none;
    color: var(--brown-primary);
    background-color: var(--beige-light);
    transition: background-color var(--transition-fast), border-color var(--transition-fast);
}

.pagination .page-link:hover {
    background-color: var(--beige-dark);
    border-color: var(--brown-primary);
}

.pagination .page-link.active {
    background-color: var(--brown-primary);
    color: white;
    border-color: var(--brown-primary);
    cursor: default;
}
*/
</style>
