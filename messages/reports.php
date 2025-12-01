<?php
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

// Handle direct report detail requests
$report_details = null;
if (isset($_GET['report_id'])) {
    $report_id = (int)$_GET['report_id'];
    $result = $battleManager->getBattleReport($report_id, $user_id);
    
    if ($result['success']) {
        $report_details = $result['report'];
        
        // Mark the report as read
        // This read-marking logic could be moved into getBattleReport or handled via AJAX
        $stmt_read = $conn->prepare("
            UPDATE battle_reports 
            SET is_read_by_attacker = 1 
            WHERE id = ? AND source_village_id IN (SELECT id FROM villages WHERE user_id = ?)
        ");
        $stmt_read->bind_param("ii", $report_id, $user_id);
        $stmt_read->execute();
        $stmt_read->close();
        
        $stmt_read = $conn->prepare("
            UPDATE battle_reports 
            SET is_read_by_defender = 1 
            WHERE id = ? AND target_village_id IN (SELECT id FROM villages WHERE user_id = ?)
        ");
        $stmt_read->bind_param("ii", $report_id, $user_id);
        $stmt_read->execute();
        $stmt_read->close();
    } else {

    }
}

// Fetch battle reports the user participated in (with pagination) via BattleManager
$reports = $battleManager->getBattleReportsForUser($user_id, $reportsPerPage, $offset);

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
                            <div class="report-item <?= !$report['is_read'] ? 'unread' : '' ?>" data-report-id="<?= $report['report_id'] ?>">
                                    <div class="report-title">
                                    <span class="report-icon"><?= $report['attacker_won'] ? '&#9876;' : '&#128737;' ?></span>
                                    <?= $report['attacker_won'] ? 'Victory' : 'Defeat' ?> - <?= htmlspecialchars($report['source_village_name']) ?> (<?= $report['source_x'] ?>|<?= $report['source_y'] ?>) attacks <?= htmlspecialchars($report['target_village_name']) ?> (<?= $report['target_x'] ?>|<?= $report['y_coord'] ?>)
                                    </div>
                                    <div class="report-villages">
                                     From: <?= htmlspecialchars($report['attacker_name']) ?> (<?= htmlspecialchars($report['source_village_name']) ?>) To: <?= htmlspecialchars($report['defender_name']) ?> (<?= htmlspecialchars($report['target_village_name']) ?>)
                                    </div>
                                <div class="report-date">
                                    <?= date('d.m.Y H:i:s', strtotime($report['created_at'])) ?>
                                </div>
                                </div>
                            <?php endforeach; ?>
                    </div>
                    
                    <!-- Report details (loaded dynamically or shown after selection) -->
                    <div class="report-details" id="report-details">
                        <?php if ($report_details): ?>
                             <!-- Render battle report details -->
                             <h3>Battle report #<?= $report_details['report_id'] ?></h3>
                            
                            <div class="battle-summary">
                                 <div class="battle-side <?= $report_details['attacker_won'] ? 'winner' : 'loser' ?>">
                                    <h4>Attacker</h4>
                                     <p class="battle-village"><?= htmlspecialchars($report_details['attacker_name']) ?> from <?= htmlspecialchars($report_details['source_village_name']) ?> (<?= $report_details['source_x'] ?>|<?= $report_details['source_y'] ?>)</p>
                                     <!-- Add attack/defense strength if calculated and stored -->
                                      <p class="battle-strength">Attack strength: ???</p>
                                     
                                      <h4>Units sent</h4>
                                    <table class="units-table">
                                        <thead>
                                            <tr>
                                                <th>Unit</th>
                                                  <th>Count</th>
                                                <th>Losses</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                              <?php foreach ($report_details['attacker_units'] as $unit_type => $count): ?>
                                                  <tr>
                                                      <td class="unit-name"><img src="../img/ds_graphic/unit/<?= $unit_type ?>.png" alt="<?= $unit_type ?>"> <?= $unit_type ?></td>
                                                      <td><?= $count ?></td>
                                                      <td class="unit-lost"><?= $report_details['attacker_losses'][$unit_type] ?? 0 ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                 </div>
                                 
                                 <div class="battle-side <?= $report_details['attacker_won'] ? 'loser' : 'winner' ?>">
                                     <h4>Defender</h4>
                                     <p class="battle-village"><?= htmlspecialchars($report_details['defender_name']) ?> from <?= htmlspecialchars($report_details['target_village_name']) ?> (<?= $report_details['target_x'] ?>|<?= $report_details['target_y'] ?>)</p>
                                     <!-- Add attack/defense strength if calculated and stored -->
                                      <p class="battle-strength">Defense strength: ???</p>
                                      
                                      <h4>Units present (post-battle)</h4>
                                    <table class="units-table">
                                        <thead>
                                            <tr>
                                                <th>Unit</th>
                                                  <th>Count</th>
                                                <th>Losses</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                             <?php foreach ($report_details['defender_units'] as $unit_type => $count): ?>
                                                  <tr>
                                                      <td class="unit-name"><img src="../img/ds_graphic/unit/<?= $unit_type ?>.png" alt="<?= $unit_type ?>"> <?= $unit_type ?></td>
                                                      <td><?= $count ?></td>
                                                      <td class="unit-lost"><?= $report_details['defender_losses'][$unit_type] ?? 0 ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                 </div>
                                 
                                 <div class="battle-loot">
                                     <h4>Loot:</h4>
                                     <p>Wood: <?= $report_details['loot_wood'] ?? 0 ?>, Clay: <?= $report_details['loot_clay'] ?? 0 ?>, Iron: <?= $report_details['loot_iron'] ?? 0 ?></p>
                                 </div>
                                 
                                 <?php if ($report_details['ram_level_change'] > 0): ?>
                 <div class="village-changes">
                      <h4>Defender village changes</h4>
                      <p>Wall reduced by <?= $report_details['ram_level_change'] ?> level<?= $report_details['ram_level_change'] > 1 ? 's' : '' ?>.</p>
                 </div>
                 <?php endif; ?>
            </div>
             
             <div class="report-footer">
                 Battle time: <?= $report_details['formatted_date'] ?>
             </div>

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
                <p>No battle reports.</p>
            <?php endif; ?>
            </main>
        </div>
    </div>

<?php require '../footer.php'; ?>

<script>
// js/reports.js - embedded
document.addEventListener('DOMContentLoaded', () => {
    const reportsList = document.querySelector('.reports-list');
    const reportDetailsArea = document.getElementById('report-details');

    if (reportsList && reportDetailsArea) {
        // Click handler for report items
        reportsList.addEventListener('click', (event) => {
            const reportItem = event.target.closest('.report-item');
            if (reportItem) {
                const reportId = reportItem.dataset.reportId;
                if (reportId) {
                    loadReportDetails(reportId);
                }
            }
        });

        // Load report details via AJAX
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
                    renderReportDetails(data.report);
                    // Optionally mark as read in UI here
                } else {
                    reportDetailsArea.innerHTML = `<p class="error-message">${data.message || 'Failed to load the report.'}</p>`;
                    console.error('Error loading report details:', data.message);
                }
            })
            .catch(error => {
                reportDetailsArea.classList.remove('loading');
                reportDetailsArea.innerHTML = '<p class="error-message">A communication error occurred while loading the report.</p>';
                console.error('Fetch error:', error);
            });
        }

        // Render report details HTML from JSON data
        function renderReportDetails(reportData) {
            const attackerSideClass = reportData.winner === 'attacker' ? 'winner' : 'loser';
            const defenderSideClass = reportData.winner === 'defender' ? 'winner' : 'loser';

            function renderUnitsTable(units) {
                let tableHtml = `
                    <table class="units-table">
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th>Count</th>
                                <th>Losses</th>
                                <th>Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                if (units && units.length > 0) {
                    units.forEach(unit => {
                        tableHtml += `
                            <tr>
                                <td class="unit-name"><img src="../img/ds_graphic/unit/${unit.internal_name}.png" alt="${unit.name}"> ${unit.name}</td>
                                <td>${unit.initial_count}</td>
                                <td class="unit-lost">${unit.lost_count}</td>
                                <td>${unit.remaining_count}</td>
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

            let details = {};
            try {
                details = JSON.parse(reportData.details_json);
            } catch (e) {
                console.error('Error parsing report details JSON:', e);
                details = { attacker_losses: {}, defender_losses: {}, loot: { wood: 0, clay: 0, iron: 0 }, ram_level_change: 0 };
            }

            const detailsHtml = `
                <div class="report-details-content">
                    <h3>Battle report #${reportData.id}</h3>

                    <div class="battle-summary">
                        <div class="battle-side ${attackerSideClass}">
                            <h4>Attacker</h4>
                            <p class="battle-village">${escapeHTML(reportData.attacker_name)} from village ${escapeHTML(reportData.source_village_name)} (${reportData.source_x}|${reportData.source_y})</p>
                            <p class="battle-strength">Attack strength: ${reportData.total_attack_strength}</p>

                            <h4>Attacking units</h4>
                            ${renderUnitsTable(reportData.attacker_units)}
                        </div>

                        <div class="battle-side ${defenderSideClass}">
                            <h4>Defender</h4>
                            <p class="battle-village">${escapeHTML(reportData.defender_name)} from village ${escapeHTML(reportData.target_village_name)} (${reportData.target_x}|${reportData.target_y})</p>
                            <p class="battle-strength">Defense strength: ${reportData.total_defense_strength}</p>

                            <h4>Defending units</h4>
                            ${renderUnitsTable(reportData.defender_units)}
                        </div>

                        ${reportData.winner === 'attacker' && (details.loot.wood > 0 || details.loot.clay > 0 || details.loot.iron > 0) ? `
                            <div class="battle-loot">
                                <h4>Loot:</h4>
                                <p>Wood: ${details.loot.wood}, Clay: ${details.loot.clay}, Iron: ${details.loot.iron}</p>
                            </div>
                        ` : ''}

                        ${details.ram_level_change > 0 ? `
                            <div class="village-changes">
                                <h4>Defender village changes</h4>
                                <p>Wall reduced by ${details.ram_level_change} level${details.ram_level_change > 1 ? 's' : ''}.</p>
                            </div>
                        ` : ''}
                    </div>

                    <div class="report-footer">
                        Battle time: ${formatDateTime(reportData.created_at)}
                    </div>
                </div>
            `;

            reportDetailsArea.innerHTML = detailsHtml;
        }

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

        // Initial state: load a report from URL if present
        const urlParams = new URLSearchParams(window.location.search);
        const initialReportId = urlParams.get('report_id');
        if (initialReportId) {
            loadReportDetails(initialReportId);
        } else if (reportDetailsArea.innerHTML.trim() === '') {
            reportDetailsArea.innerHTML = '<p>Select a report from the list to view details.</p>';
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
