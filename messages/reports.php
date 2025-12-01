<?php
declare(strict_types=1);
require '../init.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle AJAX requests for report details
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && isset($_GET['report_id'])) {
    $report_id = (int)$_GET['report_id'];
    
    $villageManager = new VillageManager($conn);
    $buildingConfigManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfigManager);
    $battleManager = new BattleManager($conn, $villageManager, $buildingManager);
    
    $result = $battleManager->getBattleReport($report_id, $user_id);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit(); // Stop execution after returning JSON
}

$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);

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
        $report_details = $result['report'];
    }
}

// Fetch battle reports the user participated in (with pagination)
$reports = $battleManager->getBattleReportsForUser($user_id, $reportsPerPage, $offset);
$unreadCount = 0;

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
                        <div style="font-size:22px;font-weight:700;"><?= (int)$unreadCount ?></div>
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
                            <div class="report-item" data-report-id="<?= $report['report_id'] ?>">
                                <div class="report-title">
                                    <span class="report-icon"><?= $report['attacker_won'] ? '&#9876;' : '&#128737;' ?></span>
                                    <?= ucfirst($report['type']) ?> - <?= htmlspecialchars($report['source_village_name']) ?> (<?= $report['source_x'] ?>|<?= $report['source_y'] ?>) → <?= htmlspecialchars($report['target_village_name']) ?> (<?= $report['target_x'] ?>|<?= $report['target_y'] ?>)
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

    function renderReportDetails(reportData) {
        const report = reportData.report;
        const details = report.details || {};
        const type = report.type || report.attack_type || details.type || 'battle';
        let detailsHtml = `
            <div class="report-details-content">
                <h3>${type === 'spy' ? 'Spy report' : 'Battle report'} #${report.id}</h3>
                <p class="battle-village">${escapeHTML(report.attacker_name)} (${escapeHTML(report.source_village_name)} ${report.source_x}|${report.source_y}) → ${escapeHTML(report.defender_name)} (${escapeHTML(report.target_village_name)} ${report.target_x}|${report.target_y})</p>
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

            detailsHtml += `</div>`;
        }

        detailsHtml += `
                <div class="report-footer">
                    Battle time: ${formatDateTime(report.battle_time)}
                </div>
            </div>
        `;

        reportDetailsArea.innerHTML = detailsHtml;
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
