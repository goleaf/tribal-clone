/**
 * Frontend logic for the Mint panel
 */

// Assume formatDuration and formatNumber are available globally

// Function to fetch and render the Mint panel
async function fetchAndRenderMintPanel(villageId, buildingInternalName) {
    const actionContent = document.getElementById('popup-action-content');
    const detailsContent = document.getElementById('building-details-content');
    if (!actionContent || !detailsContent || !villageId || buildingInternalName !== 'mint') {
        console.error('Missing elements or parameters for Mint panel or wrong building type.');
        return;
    }

    // Show loading indicator
    actionContent.innerHTML = '<p>Loading mint panel...</p>';
    actionContent.style.display = 'block';
    detailsContent.style.display = 'none'; // Hide details when showing action content

    try {
        // Use the existing get_building_action.php endpoint
        const response = await fetch(`/buildings/get_building_action.php?village_id=${villageId}&building_type=${buildingInternalName}`);
        const data = await response.json();

        if (data.status === 'success' && data.action_type === 'mint') {
            // Assuming backend provides necessary data for coin minting
            const buildingName = data.data.building_name;
            const buildingLevel = data.data.building_level;
            // TODO: Extract minting-specific data from data.data

            // Render the Mint panel HTML
            let html = `
                <h3>${buildingName} (Level ${buildingLevel}) - Mint</h3>
                <p>Here you can mint coins required for conquering villages (Palace required).</p>

                <h4>Coin minting:</h4>
                <div class="coin-minting-form">
                    <p>TODO: Coin minting form (cost, time).</p>
                    <button class="btn-primary" disabled>Mint coins (TODO)</button>
                </div>

                <h4>Minting status:</h4>
                 <div class="coin-minting-queue">
                     <p>TODO: Display coin minting queue.</p>
                 </div>

                <!-- Add other minting-related options -->

            `;

            actionContent.innerHTML = html;

            // Setup event listeners for any buttons/forms within the panel
            setupMintListeners(villageId, buildingInternalName);

        } else if (data.error) {
            actionContent.innerHTML = '<p>Error loading mint panel: ' + data.error + '</p>';
            window.toastManager.showToast(data.error, 'error');
        } else {
             actionContent.innerHTML = '<p>Invalid server response or action does not belong to Mint.</p>';
         }

    } catch (error) {
        console.error('AJAX error fetching Mint panel:', error);
        actionContent.innerHTML = '<p>Server communication error.</p>';
        window.toastManager.showToast('Server communication error while fetching Mint panel.', 'error');
    }
}

// Function to setup event listeners for the Mint panel
function setupMintListeners(villageId, buildingInternalName) {
    // TODO: Add event listeners for coin minting form, etc.
}

// Add the function to the global scope or make it accessible
// window.fetchAndRenderMintPanel = fetchAndRenderMintPanel; 
