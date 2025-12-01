// Dynamic refresh for village resources and building panel
function showLoader(targetId) {
    const el = document.getElementById(targetId);
    if (el) el.innerHTML = '<div class="loader">Loading...</div>';
}
function showNotification(message, type = 'info') {
    let notif = document.getElementById('village-notification');
    if (!notif) {
        notif = document.createElement('div');
        notif.id = 'village-notification';
        notif.className = 'village-notification';
        document.body.appendChild(notif);
    }
    notif.className = 'village-notification ' + type;
    notif.innerHTML = message;
    notif.style.display = 'block';
    setTimeout(() => { notif.style.display = 'none'; }, 4000);
}
function fetchVillageResources() {
    showLoader('village-resources-panel');
    const villageParam = window.currentVillageId ? `?village_id=${window.currentVillageId}` : '';
    fetch(`/ajax/get_resources.php${villageParam}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('wood-count').textContent = data.wood;
            document.getElementById('clay-count').textContent = data.clay;
            document.getElementById('iron-count').textContent = data.iron;
            document.getElementById('warehouse-capacity').textContent = data.warehouse_capacity;
            document.getElementById('population-count').textContent = data.population;
        })
        .catch(() => showNotification('Error loading resources', 'error'));
}
function fetchVillageBuildings() {
    //showLoader('village-buildings-panel'); // Loader commented out
    // fetch('get_building_details.php') // Fetch commented out
    //     .then(r => r.text())
    //     .then(html => {
    //         document.getElementById('village-buildings-panel').innerHTML = html; // Insert commented out
    //     })
    //     .catch(() => showNotification('Error loading buildings', 'error')); // Catch commented out
    
    // Optionally clear building panel if it should stay empty
    const buildingPanel = document.getElementById('village-buildings-panel');
    if(buildingPanel) {
        buildingPanel.innerHTML = ''; // Clear panel
    }
}
function fetchVillageQueue() {
    showLoader('village-queue-panel');
    const villageParam = window.currentVillageId ? `&village_id=${window.currentVillageId}` : '';
    fetch(`/buildings/get_building_action.php?action=queue${villageParam}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('village-queue-panel').innerHTML = html;
        })
        .catch(() => showNotification('Error loading build queue', 'error'));
}
function fetchCurrentUnits() {
    showLoader('current-units-panel');
    const villageParam = window.currentVillageId ? `?village_id=${window.currentVillageId}` : '';
    fetch(`/units/get_units.php${villageParam}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('current-units-panel').innerHTML = html;
        })
        .catch(() => showNotification('Error loading units', 'error'));
}
function fetchRecruitmentPanel() {
    showLoader('recruitment-panel');
    const villageParam = window.currentVillageId ? `?village_id=${window.currentVillageId}` : '';
    fetch(`/units/get_recruitment_panel.php${villageParam}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('recruitment-panel').innerHTML = html;
        })
        .catch(() => showNotification('Error loading recruitment panel', 'error'));
}
function fetchRecruitmentQueue() {
    showLoader('recruitment-queue-panel');
    const villageParam = window.currentVillageId ? `?village_id=${window.currentVillageId}` : '';
    fetch(`/units/get_recruitment_queue.php${villageParam}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('recruitment-queue-panel').innerHTML = html;
        })
        .catch(() => showNotification('Error loading recruitment queue', 'error'));
}
function fetchCurrentResearch() {
    showLoader('current-research-panel');
    const villageParam = window.currentVillageId ? `?village_id=${window.currentVillageId}` : '';
    fetch(`/research/get_current_research.php${villageParam}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('current-research-panel').innerHTML = html;
        })
        .catch(() => showNotification('Error loading research', 'error'));
}
function fetchResearchQueue() {
    showLoader('research-queue-panel');
    const villageParam = window.currentVillageId ? `?village_id=${window.currentVillageId}` : '';
    fetch(`/research/get_research_queue.php${villageParam}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('research-queue-panel').innerHTML = html;
        })
        .catch(() => showNotification('Error loading research queue', 'error'));
}
function refreshVillagePanel() {
    fetchVillageResources();
    // fetchVillageBuildings(); // Call removed to hide vertical building list
    fetchVillageQueue();
    fetchCurrentUnits();
    fetchRecruitmentPanel();
    fetchRecruitmentQueue();
    fetchCurrentResearch();
    fetchResearchQueue();
}
setInterval(refreshVillagePanel, 5000);
document.addEventListener('DOMContentLoaded', refreshVillagePanel);

// Expose refreshVillagePanel globally
window.refreshVillagePanel = refreshVillagePanel; 
