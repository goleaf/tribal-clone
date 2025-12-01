/**
 * Rally Point panel: shows outgoing/incoming commands and lets you cancel/recall.
 */

async function fetchAndRenderRallyPanel(villageId, buildingInternalName = 'rally_point') {
    const actionContent = document.getElementById('popup-action-content');
    const detailsContent = document.getElementById('building-details-content');
    if (!actionContent || !detailsContent || !villageId) {
        console.error('Missing elements or parameters for rally panel.');
        return;
    }

    actionContent.innerHTML = '<p>Loading rally point...</p>';
    actionContent.style.display = 'block';
    detailsContent.style.display = 'none';

    actionContent.dataset.villageId = villageId;
    actionContent.dataset.buildingInternalName = buildingInternalName || 'rally_point';

    try {
        const response = await fetch(`/ajax/rally/get_rally_data.php?village_id=${encodeURIComponent(villageId)}`);
        const data = await response.json();
        if (data.status === 'success' && data.data && data.data.html) {
            actionContent.innerHTML = data.data.html;
            setupRallyListeners();
        } else {
            const msg = data.error || data.message || 'Unable to load rally point.';
            actionContent.innerHTML = `<p>${msg}</p>`;
            if (window.toastManager) window.toastManager.showToast(msg, 'error');
        }
    } catch (err) {
        console.error('Rally panel AJAX error:', err);
        actionContent.innerHTML = '<p>Server communication error.</p>';
        if (window.toastManager) window.toastManager.showToast('Server communication error while fetching rally point.', 'error');
    }
}

function setupRallyListeners() {
    const container = document.getElementById('popup-action-content');
    if (!container || container.dataset.rallyBound === 'true') return;

    const csrfToken = () => {
        const el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.content : '';
    };
    const villageId = container.dataset.villageId || window.currentVillageId;

    container.addEventListener('click', async (event) => {
        const cancelBtn = event.target.closest('.cancel-command-btn');
        if (!cancelBtn) return;
        event.preventDefault();

        const attackId = cancelBtn.dataset.attackId;
        if (!attackId) return;
        if (!confirm('Cancel this command and recall the troops?')) return;

        cancelBtn.disabled = true;
        cancelBtn.textContent = 'Canceling...';

        try {
            const resp = await fetch('/ajax/rally/cancel_attack.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ attack_id: attackId, csrf_token: csrfToken() }).toString()
            });
            const data = await resp.json();
            if (data.status === 'success') {
                if (window.toastManager) window.toastManager.showToast(data.message || 'Command canceled.', 'success');
                fetchAndRenderRallyPanel(villageId, 'rally_point');
            } else {
                if (window.toastManager) window.toastManager.showToast(data.message || 'Could not cancel command.', 'error');
            }
        } catch (err) {
            console.error('Cancel command error:', err);
            if (window.toastManager) window.toastManager.showToast('Server communication error.', 'error');
        } finally {
            cancelBtn.disabled = false;
            cancelBtn.textContent = 'Cancel';
        }
    });

    container.dataset.rallyBound = 'true';
}

document.addEventListener('DOMContentLoaded', () => {
    // nothing to init globally
});
