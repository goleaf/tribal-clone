/**
 * Frontend logic for the Statue (Noble) panel
 */

async function fetchAndRenderNoblePanel(villageId, buildingInternalName) {
    const actionContent = document.getElementById('popup-action-content');
    const detailsContent = document.getElementById('building-details-content');
    if (!actionContent || !detailsContent || !villageId || buildingInternalName !== 'statue') {
        console.error('Missing elements or parameters for Noble panel or wrong building type.');
        return;
    }

    actionContent.innerHTML = '<p>Loading noble panel...</p>';
    actionContent.style.display = 'block';
    detailsContent.style.display = 'none';

    try {
        const response = await fetch(`/ajax/noble/panel.php?village_id=${villageId}`);
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to load noble data.');
        }

        const { village, buildings, noble, caps, coin_costs } = data.data;
        const reqMet = buildings.statue > 0 && buildings.academy >= 1 && buildings.smithy >= 20 && buildings.market >= 10;
        const coinCostText = `${coin_costs.wood}W / ${coin_costs.clay}C / ${coin_costs.iron}I`;
        const nobleCostText = `${noble.costs.wood}W / ${noble.costs.clay}C / ${noble.costs.iron}I + 1 coin`;

        const html = `
            <h3>Statue – Noble management</h3>
            <div class="noble-stats">
                <p><strong>Loyalty:</strong> ${village.loyalty}/100</p>
                <p><strong>Coins:</strong> <span id="noble-coins">${village.coins}</span></p>
                <p><strong>Nobles:</strong> ${caps.current_nobles} / ${caps.max_nobles}</p>
            </div>
            <div class="noble-block">
                <h4>Mint coin</h4>
                <p>Cost: ${coinCostText}</p>
                <button id="mint-coin-btn" class="btn-primary">Mint coin</button>
            </div>
            <div class="noble-block">
                <h4>Recruit noble</h4>
                <p>Requirements: Statue (built), Academy 1, Smithy 20, Market 10.</p>
                <p>Cost: ${nobleCostText} | Time: ${formatDuration(noble.training_time || 0)}</p>
                <label>Count <input id="noble-count" type="number" min="1" value="1"></label>
                <button id="recruit-noble-btn" class="btn-primary"${reqMet ? '' : ' disabled'}>${reqMet ? 'Recruit' : 'Requirements missing'}</button>
            </div>
        `;

        actionContent.innerHTML = html;

        document.getElementById('mint-coin-btn').addEventListener('click', async () => {
            try {
                const res = await fetch('/ajax/noble/mint_coin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `village_id=${villageId}&csrf_token=${encodeURIComponent(getCsrfToken())}`
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Mint failed');
                document.getElementById('noble-coins').textContent = json.coins;
                if (window.resourceUpdater && json.resources) {
                    window.resourceUpdater.resources.wood.amount = json.resources.wood;
                    window.resourceUpdater.resources.clay.amount = json.resources.clay;
                    window.resourceUpdater.resources.iron.amount = json.resources.iron;
                }
                if (window.toastManager) window.toastManager.showToast('Coin minted', 'success');
            } catch (err) {
                if (window.toastManager) window.toastManager.showToast(err.message, 'error');
                console.error(err);
            }
        });

        document.getElementById('recruit-noble-btn').addEventListener('click', async () => {
            const count = parseInt(document.getElementById('noble-count').value, 10) || 1;
            try {
                const res = await fetch(`/ajax/units/recruit.php?village_id=${villageId}&building=academy`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ unit_id: noble.unit_id || noble.unit_type_id || 0, count })
                });
                const json = await res.json();
                if (!json.success && json.error) throw new Error(json.error);
                if (window.toastManager) window.toastManager.showToast('Noble recruitment started', 'success');
                // Reload panel to refresh counts
                fetchAndRenderNoblePanel(villageId, buildingInternalName);
            } catch (err) {
                if (window.toastManager) window.toastManager.showToast(err.message, 'error');
                console.error(err);
            }
        });
    } catch (error) {
        console.error('Noble panel error', error);
        actionContent.innerHTML = '<p>Server communication error.</p>';
        if (window.toastManager) window.toastManager.showToast('Server communication error while fetching Statue panel.', 'error');
    }
}
