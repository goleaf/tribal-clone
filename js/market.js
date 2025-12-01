/**
 * Frontend logic for Market Panel and Trade
 */

// Assume formatDuration is available globally from buildings.js or units.js
// function formatDuration(seconds) { ... }

// Function to update timers specifically within the Market popup for active trades
function updateTradeTimersPopup() {
    const timers = document.querySelectorAll('#popup-action-content .trade-timer');
    const currentTime = Math.floor(Date.now() / 1000);

    timers.forEach(timerElement => {
        const finishTime = parseInt(timerElement.dataset.endsAt, 10);
        const remainingTime = finishTime - currentTime;

        if (remainingTime > 0) {
            timerElement.textContent = formatDuration(remainingTime);
        } else {
            timerElement.textContent = 'Arrived!';
            timerElement.classList.add('timer-finished');
            timerElement.removeAttribute('data-ends-at'); // Stop refreshing this timer

            // This trade has finished. We should ideally refresh the active trades list.
            // For simplicity now, just mark it finished.
             const tradeRow = timerElement.closest('tr');
             if (tradeRow) {
                  tradeRow.classList.add('finished');
                  // Might need to visually update resources if it was an incoming trade
                  // Or update available traders if it was an outgoing trade
             }
             // A trade finished, refresh the market panel to update lists and trader count
             // Need a way to get current villageId and buildingInternalName (market)
             // Let's assume popup-action-content has data attributes
             const actionContent = document.getElementById('popup-action-content');
              if (actionContent && actionContent.dataset.villageId && actionContent.dataset.buildingInternalName === 'market') {
                   const villageId = actionContent.dataset.villageId;
                   const buildingInternalName = actionContent.dataset.buildingInternalName;
                    // Refresh the panel after a short delay to allow backend processing
                    setTimeout(() => {
                         fetchAndRenderMarketPanel(villageId, buildingInternalName); // Assuming this function exists and fetches market data
                    }, 1000); // Delay by 1 second
              }
        }
    });
}

// Setup interval for updating trade popup timers
let tradeTimerInterval = null;
function startTradeTimerInterval() {
    if (tradeTimerInterval === null) {
        tradeTimerInterval = setInterval(updateTradeTimersPopup, 1000);
    }
}

// Attach market listeners (send resources, create/cancel/accept offers)
function setupMarketListeners() {
    const container = document.getElementById('popup-action-content');
    if (!container || container.dataset.tradeBound === 'true') return;

    const getVillageId = () => container.dataset.villageId || window.currentVillageId;
    const csrfToken = () => {
        const tokenEl = document.querySelector('meta[name="csrf-token"]');
        return tokenEl ? tokenEl.content : '';
    };

    const submitHandler = async (event) => {
        const form = event.target;
        const formId = form.id;
        if (formId !== 'send-resources-form' && formId !== 'create-offer-form') return;
        event.preventDefault();

        const villageId = getVillageId();
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken());
        if (!formData.has('village_id') && villageId) {
            formData.append('village_id', villageId);
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : null;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Working...';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(formData).toString()
            });
            const data = await response.json();

            if (data.status === 'success') {
                if (window.toastManager) window.toastManager.showToast(data.message || 'Success', 'success');
                fetchAndRenderMarketPanel(villageId, 'market');

                if (formId === 'send-resources-form' && window.resourceUpdater && data.data && data.data.village_info) {
                    window.resourceUpdater.resources.wood.amount = data.data.village_info.wood;
                    window.resourceUpdater.resources.clay.amount = data.data.village_info.clay;
                    window.resourceUpdater.resources.iron.amount = data.data.village_info.iron;
                    window.resourceUpdater.updateUI();
                }
            } else {
                if (window.toastManager) window.toastManager.showToast(data.message || 'Action failed.', 'error');
            }
        } catch (error) {
            console.error('Market action AJAX error:', error);
            if (window.toastManager) window.toastManager.showToast('Server communication error.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText || submitBtn.textContent;
            }
        }
    };

    const clickHandler = async (event) => {
        const villageId = getVillageId();
        const acceptBtn = event.target.closest('.accept-offer-btn');
        const cancelBtn = event.target.closest('.cancel-offer-btn');
        const csrf = csrfToken();

        if (acceptBtn) {
            event.preventDefault();
            const offerId = acceptBtn.dataset.offerId;
            if (!offerId) return;

            acceptBtn.disabled = true;
            acceptBtn.textContent = 'Accepting...';

            try {
                const response = await fetch('/ajax/trade/accept_offer.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({ offer_id: offerId, village_id: villageId, csrf_token: csrf }).toString()
                });
                const data = await response.json();
                if (data.status === 'success') {
                    if (window.toastManager) window.toastManager.showToast(data.message || 'Offer accepted.', 'success');
                    fetchAndRenderMarketPanel(villageId, 'market');
                } else {
                    if (window.toastManager) window.toastManager.showToast(data.message || 'Could not accept offer.', 'error');
                }
            } catch (error) {
                console.error('Accept offer error:', error);
                if (window.toastManager) window.toastManager.showToast('Server communication error.', 'error');
            } finally {
                acceptBtn.disabled = false;
                acceptBtn.textContent = 'Accept';
            }
            return;
        }

        if (cancelBtn) {
            event.preventDefault();
            const offerId = cancelBtn.dataset.offerId;
            if (!offerId) return;

            cancelBtn.disabled = true;
            cancelBtn.textContent = 'Canceling...';

            try {
                const response = await fetch('/ajax/trade/cancel_offer.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({ offer_id: offerId, village_id: villageId, csrf_token: csrf }).toString()
                });
                const data = await response.json();
                if (data.status === 'success') {
                    if (window.toastManager) window.toastManager.showToast(data.message || 'Offer canceled.', 'success');
                    fetchAndRenderMarketPanel(villageId, 'market');
                } else {
                    if (window.toastManager) window.toastManager.showToast(data.message || 'Could not cancel offer.', 'error');
                }
            } catch (error) {
                console.error('Cancel offer error:', error);
                if (window.toastManager) window.toastManager.showToast('Server communication error.', 'error');
            } finally {
                cancelBtn.disabled = false;
                cancelBtn.textContent = 'Cancel';
            }
        }
    };

    container.addEventListener('submit', submitHandler);
    container.addEventListener('click', clickHandler);
    container.dataset.tradeBound = 'true';
}

// Add a function to fetch and render the Market panel (to be called from buildings.js)
async function fetchAndRenderMarketPanel(villageId, buildingInternalName = 'market') {
     const actionContent = document.getElementById('popup-action-content');
     const detailsContent = document.getElementById('building-details-content');
     if (!actionContent || !detailsContent || !villageId) {
         console.error('Missing elements or parameters for market panel.');
         return;
     }

     // Show loading indicator
     actionContent.innerHTML = '<p>Loading market panel...</p>';
     actionContent.style.display = 'block';
     detailsContent.style.display = 'none'; // Hide details when showing action content

     // Add data attributes to actionContent for easy access in timer updates
     actionContent.dataset.villageId = villageId;
     actionContent.dataset.buildingInternalName = buildingInternalName || 'market';

     try {
         const response = await fetch(`/ajax/trade/get_market_data.php?village_id=${villageId}`);
         const data = await response.json();

         if (data.status === 'success' && data.data && data.data.html) {
             actionContent.innerHTML = data.data.html;
             setupMarketListeners(); // Setup listeners after rendering
              updateTradeTimersPopup(); // Start timers for the popup queue

             if (Array.isArray(data.data.messages)) {
                 data.data.messages.forEach((msg) => {
                     if (window.toastManager) window.toastManager.showToast(msg, 'success');
                 });
             }
         } else if (data.error || data.message) {
             actionContent.innerHTML = '<p>Error loading market panel: ' + (data.error || data.message) + '</p>';
             if (window.toastManager) window.toastManager.showToast(data.error || data.message, 'error');
         } else {
              actionContent.innerHTML = '<p>Invalid server response or the action is not related to the market.</p>';
         }

     } catch (error) {
         console.error('Market panel AJAX error:', error);
         actionContent.innerHTML = '<p>Server communication error.</p>';
         if (window.toastManager) window.toastManager.showToast('Server communication error while fetching the market panel.', 'error');
     }
}

// Add the fetchAndRenderMarketPanel function to the global scope or make it accessible
// window.fetchAndRenderMarketPanel = fetchAndRenderMarketPanel;

// Ensure timers start when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Start the interval for trade popup timers
     startTradeTimerInterval();
}); 
