/**
 * Frontend logic for the Statue (Noble) panel
 */

// Assume formatDuration and formatNumber are available globally

// Function to fetch and render the Noble panel
async function fetchAndRenderNoblePanel(villageId, buildingInternalName) {
    const actionContent = document.getElementById('popup-action-content');
    const detailsContent = document.getElementById('building-details-content');
    if (!actionContent || !detailsContent || !villageId || buildingInternalName !== 'statue') {
        console.error('Missing elements or parameters for Noble panel or wrong building type.');
        return;
    }

    // Show loading indicator
    actionContent.innerHTML = '<p>Loading Statue panel...</p>';
    actionContent.style.display = 'block';
    detailsContent.style.display = 'none'; // Hide details when showing action content

    try {
        // Use the existing get_building_action.php endpoint
        const response = await fetch(`/buildings/get_building_action.php?village_id=${villageId}&building_type=${buildingInternalName}`);
        const data = await response.json();

        if (data.status === 'success' && data.action_type === 'noble') {
            // Assuming backend provides necessary data for the noble system
            const buildingName = data.data.building_name;
            const buildingLevel = data.data.building_level;
            // TODO: Extract noble-specific data from data.data

            // Render the Noble panel HTML
            let html = `
                <h3>${buildingName} (Level ${buildingLevel}) - Noble</h3>
                <p>Recruit/manage nobles here.</p>

                <h4>Noble status:</h4>
                <div class="noble-status">
                    <p>TODO: Display current noble status (if any).</p>
                </div>

                <h4>Noble recruitment:</h4>
                <div class="noble-recruitment">
                    <p>TODO: Recruitment form (cost, time, requirements).</p>
                     <button class="btn-primary" disabled>Recruit noble (TODO)</button>
                </div>

                <h4>Coin minting:</h4>
                <div class="coin-minting">
                    <p>TODO: Coin minting interface (if applicable).</p>
                     <button class="btn-primary" disabled>Mint coins (TODO)</button>
                </div>

                <!-- Add other noble-related options -->

            `;

            actionContent.innerHTML = html;

            // Setup event listeners for any buttons/forms within the panel
            setupNobleListeners(villageId, buildingInternalName);

        } else if (data.error) {
            actionContent.innerHTML = '<p>Error loading Statue panel: ' + data.error + '</p>';
            window.toastManager.showToast(data.error, 'error');
        } else {
             actionContent.innerHTML = '<p>Invalid server response or action does not belong to Statue.</p>';
         }

    } catch (error) {
        console.error('AJAX error fetching Statue panel:', error);
        actionContent.innerHTML = '<p>Server communication error.</p>';
        window.toastManager.showToast('Server communication error while fetching Statue panel.', 'error');
    }
}

// Function to setup event listeners for the Noble panel
function setupNobleListeners(villageId, buildingInternalName) {
    // TODO: Add event listeners for noble recruitment form, coin minting form, etc.
}

// Add the function to the global scope or make it accessible
// window.fetchAndRenderNoblePanel = fetchAndRenderNoblePanel; 
