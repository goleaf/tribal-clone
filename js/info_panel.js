/**
 * Frontend logic for generic Info Panels for buildings
 */

// Assume formatDuration and formatNumber are available globally

// Function to fetch and render a generic Info Panel
async function fetchAndRenderInfoPanel(villageId, buildingInternalName) {
    const actionContent = document.getElementById('popup-action-content');
    const detailsContent = document.getElementById('building-details-content');
    if (!actionContent || !detailsContent || !villageId || !buildingInternalName) {
        console.error('Missing elements or parameters for Info panel.');
        return;
    }

    // Show loading indicator
    actionContent.innerHTML = '<p>Loading building information...</p>';
    actionContent.style.display = 'block';
    detailsContent.style.display = 'none'; // Hide details when showing action content

    try {
        // Use the existing get_building_action.php endpoint
        const response = await fetch(`get_building_action.php?village_id=${villageId}&building_type=${buildingInternalName}`);
        const data = await response.json();

        // This panel handles 'info' and 'info_production' action types
        if (data.status === 'success' && (data.action_type === 'info' || data.action_type === 'info_production')) {
            const buildingName = data.data.building_name;
            const buildingLevel = data.data.building_level;
            const additionalInfoHtml = data.data.additional_info_html; // HTML content from backend

            // Render the Info panel HTML
            let html = `
                <h3>${buildingName} (Level ${buildingLevel})</h3>
                <div class="building-info-content">
                     ${additionalInfoHtml} // Inject HTML from backend
                </div>
                <!-- No specific actions needed for generic info -->
            `;

            actionContent.innerHTML = html;

        } else if (data.error) {
            actionContent.innerHTML = '<p>Error loading building info: ' + data.error + '</p>';
            window.toastManager.showToast(data.error, 'error');
        } else {
             // This case should ideally not happen if called correctly from buildings.js
             actionContent.innerHTML = '<p>Invalid server response or action does not belong to info panel.</p>';
         }

    } catch (error) {
        console.error('AJAX error fetching building info:', error);
        actionContent.innerHTML = '<p>Server communication error.</p>';
        window.toastManager.showToast('Server communication error while fetching building info.', 'error');
    }
}

// No specific listeners needed for generic info panel

// Add the function to the global scope or make it accessible
// window.fetchAndRenderInfoPanel = fetchAndRenderInfoPanel; 
