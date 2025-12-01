'use strict';
/**
 * Building interactions (popups, AJAX upgrades)
 */

// Format seconds into D HH:MM:SS
function formatDuration(seconds) {
    if (seconds < 0) seconds = 0; // Avoid negative time
    const d = Math.floor(seconds / (3600 * 24));
    const h = Math.floor((seconds % (3600 * 24)) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);

    let parts = [];
    if (d > 0) parts.push(d + 'd');
    if (h > 0 || d > 0) parts.push(h.toString().padStart(2, '0') + 'h');
    parts.push(m.toString().padStart(2, '0') + 'm');
    parts.push(s.toString().padStart(2, '0') + 's');

    return parts.join(' ');
}

const buildingEndpoints = window.buildingEndpoints || {
    details: '/buildings/get_building_details.php',
    action: '/buildings/get_building_action.php',
    upgrade: '/buildings/upgrade_building.php',
    cancel: '/buildings/cancel_upgrade.php',
    queue: '/ajax/buildings/get_queue.php'
};
window.buildingEndpoints = buildingEndpoints;

function getCsrfToken() {
    const tokenEl = document.querySelector('meta[name="csrf-token"]');
    return tokenEl ? tokenEl.content : '';
}

// Update all timers on the page
function updateTimers() {
    if (document.hidden || window.appOffline) return;

    const timers = document.querySelectorAll('[data-ends-at]');
    const currentTime = Math.floor(Date.now() / 1000); // Current Unix time

    timers.forEach(timerElement => {
        const finishTime = parseInt(timerElement.dataset.endsAt, 10);
        if (!Number.isFinite(finishTime)) return;
        const remainingTime = finishTime - currentTime;

        // Locate progress bar
        const progressContainer = timerElement.closest('.item-progress');
        const progressBarFill = progressContainer ? progressContainer.querySelector('.progress-fill') : null;

        // Related queue element (assumes timer lives inside .queue-item)
        const queueItemElement = timerElement.closest('.queue-item');

        // Find related building graphic by internal name
        let buildingImage = null;
        let internalName = null;

        if (queueItemElement && queueItemElement.dataset.buildingInternalName) {
             internalName = queueItemElement.dataset.buildingInternalName;
             // Try to find placeholder on the map
             const buildingPlaceholder = document.querySelector(`.building-placeholder[data-building-internal-name='${internalName}']`);
             if (buildingPlaceholder) {
                 buildingImage = buildingPlaceholder.querySelector('.building-graphic');
             }
             // TODO: add lookup in building list if not in village view
        }


        if (remainingTime > 0) {
            timerElement.textContent = formatDuration(remainingTime);

            // Update progress bar
            if (progressBarFill && timerElement.dataset.startTime) { // start_time required
                 const startTime = parseInt(timerElement.dataset.startTime, 10);
                 const duration = finishTime - startTime;
                 // Guard against zero/negative duration
                 const progress = duration > 0 ? ((duration - remainingTime) / duration) * 100 : 100;
                 progressBarFill.style.width = `${Math.min(100, Math.max(0, progress))}%`;
            }

            // Swap to GIF if available while building
            if (buildingImage) {
                const currentSrc = buildingImage.src;
                if (currentSrc.endsWith('.png')) {
                    const gifSrc = currentSrc.replace('.png', '.gif');
                    // Remember the original so we can restore it safely later
                    buildingImage.dataset.originalSrc = currentSrc;
                    buildingImage.src = gifSrc;
                }
            }

        } else {
            timerElement.textContent = 'Completed!';
            timerElement.classList.add('timer-finished');
            if (progressBarFill) progressBarFill.style.width = '100%';

             // Revert graphic to PNG if it was GIF
            if (buildingImage) {
                const originalSrc = buildingImage.dataset.originalSrc;
                if (originalSrc) {
                    buildingImage.src = originalSrc;
                    delete buildingImage.dataset.originalSrc;
                }
            }

            // Update related building elements
             const queueItemElementToRemove = timerElement.closest('.queue-item');
            if (queueItemElementToRemove) {
                // Grab internal_name before removing element
                const buildingInternalName = queueItemElementToRemove.dataset.buildingInternalName;

                // Remove queue element
                queueItemElementToRemove.remove();

                // Update status on list/map after removal
                if (buildingInternalName) {
                     // Remove upgrading class on placeholder
                     const buildingPlaceholder = document.querySelector(`.building-placeholder[data-building-internal-name='${buildingInternalName}']`);
                     if (buildingPlaceholder) {
                         buildingPlaceholder.classList.remove('building-upgrading');
                     }
                     // Find building-item and update status
                    const buildingItem = document.querySelector(`.building-item[data-internal-name='${buildingInternalName}']`);
                     if (buildingItem) {
                           // TODO: adjust displayed level and upgrade button state if needed
                           const statusElement = buildingItem.querySelector('.upgrade-status');
                           if (statusElement && statusElement.textContent.includes('Upgrading')) {
                                statusElement.textContent = `Upgrade to level ${parseInt(buildingItem.dataset.currentLevel || 0, 10) + 1}:`;
                           }
                           const timerElementInItem = buildingItem.querySelector('.upgrade-timer');
                           if (timerElementInItem) timerElementInItem.remove();

                            // Re-enable upgrade button
                            const upgradeButton = buildingItem.querySelector('.upgrade-button');
                            const upgradeButtonInItem = buildingItem.querySelector('.upgrade-building-button');
                             if (upgradeButtonInItem) {
                                 upgradeButtonInItem.disabled = false;
                                 upgradeButtonInItem.classList.remove('btn-secondary');
                                 upgradeButtonInItem.classList.add('btn-primary');
                                 // Hide unavailable reason if present
                                 const reasonElement = buildingItem.querySelector('.upgrade-unavailable-reason');
                                 if(reasonElement) reasonElement.style.display = 'none';
                                 // Optionally update button text
                            }
                     }
               }
           }


            updateBuildingQueue();
            if (window.resourceUpdater) {
                 // Force resource refresh
                 window.resourceUpdater.fetchUpdate();
             }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const popupOverlay = document.getElementById('popup-overlay');
    const buildingDetailsPopup = document.getElementById('building-action-popup');
    // Find close button only if popup exists
    const popupCloseBtn = buildingDetailsPopup ? buildingDetailsPopup.querySelector('.close-button') : null;

    // Popup elements (null checks added)
    const popupBuildingName = document.getElementById('popup-building-name');
    const popupBuildingDescription = document.getElementById('popup-building-description');
    const popupCurrentLevel = document.getElementById('popup-current-level');
    const popupProductionInfo = document.getElementById('popup-production-info');
    const popupCapacityInfo = document.getElementById('popup-capacity-info');
    const popupNextLevel = document.getElementById('popup-next-level');
    const popupUpgradeCosts = document.getElementById('popup-upgrade-costs');
    const popupUpgradeTime = document.getElementById('popup-upgrade-time');
    const popupRequirements = document.getElementById('popup-requirements');
    const popupUpgradeReason = document.getElementById('popup-upgrade-reason');
    const popupUpgradeButton = document.getElementById('popup-upgrade-button');
    const popupActionContent = document.getElementById('popup-action-content');
    const buildingDetailsContent = document.getElementById('building-details-content'); // Container to swap views in the popup


    let currentVillageId = window.currentVillageId; // Village ID from global

    function formatBuildingLabel(internalName) {
        if (!internalName) return 'Building';
        return internalName
            .split('_')
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join(' ');
    }

    function openActionPopupShell(internalName, options = {}) {
        if (!popupActionContent || !buildingDetailsContent) return;

        const {
            name,
            level,
            description,
            loadingMessage = '<p>Loading action...</p>'
        } = options;

        if (popupOverlay) popupOverlay.style.display = 'block';
        if (buildingDetailsPopup) {
            buildingDetailsPopup.style.display = 'flex';
            if (internalName === 'main_building') {
                buildingDetailsPopup.classList.add('main-building-popup');
            } else {
                buildingDetailsPopup.classList.remove('main-building-popup');
            }
        }

        popupActionContent.style.display = 'block';
        popupActionContent.innerHTML = loadingMessage;
        buildingDetailsContent.style.display = 'none';

        if (popupBuildingName) popupBuildingName.textContent = name || formatBuildingLabel(internalName);
        if (popupCurrentLevel) {
            const resolvedLevel = (level !== undefined && level !== null && level !== '') ? level : '-';
            popupCurrentLevel.textContent = resolvedLevel;
        }
        if (popupBuildingDescription && description) popupBuildingDescription.textContent = description;
    }

    // Opens the building details popup
    async function openBuildingDetailsPopup(villageId, internalName) {
        if (!villageId || !internalName) {
            console.error('Missing villageId or internalName for building popup.');
            return;
        }

        // Show loader and clear previous content
        if (popupBuildingName) popupBuildingName.textContent = 'Loading...';
        if (popupBuildingDescription) popupBuildingDescription.textContent = '';
        if (popupCurrentLevel) popupCurrentLevel.textContent = '';
        if (popupProductionInfo) popupProductionInfo.textContent = '';
        if (popupCapacityInfo) popupCapacityInfo.textContent = '';
        if (popupNextLevel) popupNextLevel.textContent = '';
        if (popupUpgradeCosts) popupUpgradeCosts.innerHTML = ''; // Use innerHTML because it contains markup
        if (popupUpgradeTime) popupUpgradeTime.textContent = '';
        if (popupRequirements) popupRequirements.innerHTML = ''; // Clear requirements section
        if (popupUpgradeReason) popupUpgradeReason.textContent = '';
        if (popupUpgradeButton) popupUpgradeButton.style.display = 'none';
        if (popupActionContent) {
            popupActionContent.innerHTML = '';
            popupActionContent.style.display = 'none'; // Hide action content initially
        }
        if (buildingDetailsContent) buildingDetailsContent.style.display = 'block'; // Show details content


        if (buildingDetailsPopup) {
             buildingDetailsPopup.classList.remove('main-building-popup'); // Reset class for the main building
             buildingDetailsPopup.style.display = 'flex';
        }
        if (popupOverlay) popupOverlay.style.display = 'block';

        try {
            const response = await fetch(`${buildingEndpoints.details}?village_id=${villageId}&building_internal_name=${internalName}`);
            const data = await response.json();

            if (data.error) {
                console.error('Error fetching building details:', data.error);
                if (window.toastManager) window.toastManager.showToast(data.error, 'error');
                closeBuildingDetailsPopup();
                return;
            }

            // Fill the popup with data (null checks added)
            if (popupBuildingName) popupBuildingName.textContent = `${data.name} (Level ${data.level})`;
            if (popupBuildingDescription) popupBuildingDescription.textContent = data.description;
            if (popupCurrentLevel) popupCurrentLevel.textContent = data.level;

            // Production/capacity information
            if (data.production_info) {
                if (data.production_info.type === 'production') {
                    if (popupProductionInfo) {
                        popupProductionInfo.textContent = `Production: ${formatNumber(data.production_info.amount_per_hour)}/h ${data.production_info.resource_type}`;
                        if (data.production_info.amount_per_hour_next_level) {
                            popupProductionInfo.textContent += ` (Next lvl: +${formatNumber(data.production_info.amount_per_hour_next_level)})`;
                        }
                        popupProductionInfo.style.display = 'block';
                    }
                    if (popupCapacityInfo) popupCapacityInfo.style.display = 'none';
                } else if (data.production_info.type === 'capacity') {
                     if (popupCapacityInfo) {
                        popupCapacityInfo.textContent = `Capacity: ${formatNumber(data.production_info.amount)}`;
                        if (data.production_info.amount_next_level) {
                            popupCapacityInfo.textContent += ` (Next lvl: ${formatNumber(data.production_info.amount_next_level)})`;
                        }
                        popupCapacityInfo.style.display = 'block';
                     }
                    if (popupProductionInfo) popupProductionInfo.style.display = 'none';
                } else {
                    if (popupProductionInfo) popupProductionInfo.style.display = 'none';
                    if (popupCapacityInfo) popupCapacityInfo.style.display = 'none';
                }
            } else { // No production_info type
                if (popupProductionInfo) popupProductionInfo.style.display = 'none';
                if (popupCapacityInfo) popupCapacityInfo.style.display = 'none';
            }

            // Upgrade info
             // Only show upgrade section if the building can be upgraded
             // Assume building-upgrade-section exists in the popup HTML
             const buildingUpgradeSection = document.getElementById('building-upgrade-section');
             if (buildingUpgradeSection) {
                 if (data.level < data.max_level) {
                     buildingUpgradeSection.style.display = 'block';
                      if (data.is_upgrading) {
                          if (popupNextLevel) popupNextLevel.textContent = data.queue_level_after;
                          if (popupUpgradeCosts) popupUpgradeCosts.innerHTML = `<p class="upgrade-status">Upgrading to level ${data.queue_level_after}.</p>`;
                           // Check timer element before setting innerHTML
                          if (popupUpgradeTime) popupUpgradeTime.innerHTML = `<p class="upgrade-timer" data-ends-at="${data.queue_finish_time}">${getRemainingTimeText(data.queue_finish_time)}</p>`;
                          if (popupUpgradeButton) popupUpgradeButton.style.display = 'none';
                          if (popupUpgradeReason) {
                            popupUpgradeReason.textContent = data.upgrade_not_available_reason;
                            popupUpgradeReason.style.display = 'block';
                          }
                      } else { // Not upgrading
                          if (popupNextLevel) popupNextLevel.textContent = data.level + 1;
                          if (data.upgrade_costs) {
                              if (popupUpgradeCosts) {
                                // Using relative resource image paths in the popup
                                popupUpgradeCosts.innerHTML = `Cost:
                                    <span class="resource-cost wood"><img src="../img/ds_graphic/wood.png" alt="Wood"> ${formatNumber(data.upgrade_costs.wood)}</span>
                                    <span class="resource-cost clay"><img src="../img/ds_graphic/stone.png" alt="Clay"> ${formatNumber(data.upgrade_costs.clay)}</span>
                                    <span class="resource-cost iron"><img src="../img/ds_graphic/iron.png" alt="Iron"> ${formatNumber(data.upgrade_costs.iron)}</span>`;
                              }
                              if (popupUpgradeTime) popupUpgradeTime.textContent = `Build time: ${data.upgrade_time_formatted}`;

                              // Requirements
                              if (data.requirements && data.requirements.length > 0) {
                                  if (popupRequirements) {
                                      let reqHtml = '<div class="building-requirements"><p>Requirements:</p><ul>';
                                      data.requirements.forEach(req => {
                                          const isMet = req.met;
                                          const requirementClass = isMet ? 'requirement-met' : 'requirement-not-met';
                                          const statusText = isMet ? '(Met)' : ' (Required)';
                                          reqHtml += `<li class="${requirementClass}">${req.name} (Level ${req.required_level}, your level: ${req.current_level}) ${statusText}</li>`;
                                      });
                                      reqHtml += '</ul></div>';
                                      popupRequirements.innerHTML = reqHtml;
                                      popupRequirements.style.display = 'block';
                                  }
                              } else {
                                  if (popupRequirements) popupRequirements.style.display = 'none';
                              }

                              if (data.can_upgrade) {
                                  if (popupUpgradeButton) {
                                      popupUpgradeButton.style.display = 'block';
                                      popupUpgradeButton.textContent = `Upgrade to level ${data.level + 1}`;
                                      popupUpgradeButton.dataset.villageId = villageId;
                                      popupUpgradeButton.dataset.buildingInternalName = internalName;
                                      popupUpgradeButton.dataset.currentLevel = data.level;
                                  }
                                  if (popupUpgradeReason) popupUpgradeReason.style.display = 'none';
                              } else { // Cannot upgrade (e.g., insufficient resources, missing requirements)
                                  if (popupUpgradeButton) popupUpgradeButton.style.display = 'none';
                                  if (popupUpgradeReason) {
                                    popupUpgradeReason.textContent = data.upgrade_not_available_reason || 'No upgrade data available.';
                                    popupUpgradeReason.style.display = 'block';
                                  }
                              }
                          } else { // No upgrade costs data
                              if (popupUpgradeCosts) popupUpgradeCosts.textContent = 'Cannot calculate upgrade costs.';
                              if (popupUpgradeTime) popupUpgradeTime.textContent = '';
                              if (popupUpgradeButton) popupUpgradeButton.style.display = 'none';
                              if (popupUpgradeReason) {
                                popupUpgradeReason.textContent = data.upgrade_not_available_reason || 'No upgrade data available.';
                                popupUpgradeReason.style.display = 'block';
                              }
                          }
                      }
                 } else { // Max level reached
                     if (buildingUpgradeSection) buildingUpgradeSection.style.display = 'none'; // Hide if max level
                     // Still show max level info in the main details area
                     if (popupNextLevel) popupNextLevel.textContent = data.max_level;
                     if (popupUpgradeCosts) popupUpgradeCosts.innerHTML = ''; // Clear upgrade costs section
                     if (popupUpgradeTime) popupUpgradeTime.textContent = '';
                     if (popupRequirements) popupRequirements.innerHTML = '';
                     if (popupUpgradeReason) {
                        popupUpgradeReason.textContent = 'Maximum level reached.';
                        popupUpgradeReason.style.display = 'block';
                     }
                 }
             }

            // Special handling for the Main Building
            if (buildingDetailsPopup && internalName === 'main_building') {
                buildingDetailsPopup.classList.add('main-building-popup');
                // Here you could load the list of all buildings to upgrade
                // fetchAndRenderAllBuildingsForMainBuilding(villageId); // Called via building-action-button
            } else {
                if (buildingDetailsPopup) buildingDetailsPopup.classList.remove('main-building-popup');
            }

            // Start timers in the popup if needed; global interval already runs on all timers
            // updateTimers();
            // The interval is already running globally

         } catch (error) {
            console.error('Building detail AJAX error:', error);
            if (window.toastManager) window.toastManager.showToast('A communication error occurred while fetching details.', 'error');
            closeBuildingDetailsPopup();
        }
    }

    // Closes the building details popup
    function closeBuildingDetailsPopup() {
        if (buildingDetailsPopup) buildingDetailsPopup.style.display = 'none';
        if (popupOverlay) popupOverlay.style.display = 'none';
        if (popupActionContent) {
            popupActionContent.innerHTML = ''; // Clear the action content
            popupActionContent.style.display = 'none'; // Hide action content
        }
        if (buildingDetailsContent) buildingDetailsContent.style.display = 'block'; // Show details content
         // Clear timer interval if it's only for popup - Not needed; timers are global
         // clearInterval(popupTimerInterval);
    }

    // Handle clicks on building placeholders - open details popup
    // Use event delegation on the container because placeholders may be dynamic
    const villageViewGraphic = document.getElementById('village-view-graphic');
    if (villageViewGraphic) {
        villageViewGraphic.addEventListener('click', function(event) {
            const placeholder = event.target.closest('.building-placeholder');
            if (placeholder) {
                const internalName = placeholder.dataset.buildingInternalName;
                 if (window.currentVillageId) { // Check that villageId is available
                      openBuildingDetailsPopup(window.currentVillageId, internalName);
                 } else {
                      console.error('Village ID not available to open building details.');
                 }
            }
        });
    }


    async function requestUpgrade(villageId, buildingInternalName, currentLevel, button, onSuccess) {
        if (!villageId || !buildingInternalName || currentLevel === undefined) {
            console.error('Missing data for upgrade.', { villageId, buildingInternalName, currentLevel });
            if (window.toastManager) window.toastManager.showToast('Missing data for upgrade.', 'error');
            return;
        }

        const csrfToken = getCsrfToken();
        const originalText = button ? button.textContent : '';
        if (button) {
            button.disabled = true;
            button.textContent = 'Upgrading...';
        }

        try {
            const response = await fetch(buildingEndpoints.upgrade, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `village_id=${encodeURIComponent(villageId)}&building_type_internal_name=${encodeURIComponent(buildingInternalName)}&current_level=${encodeURIComponent(currentLevel)}&csrf_token=${encodeURIComponent(csrfToken)}`
            });
            const data = await response.json();

            if (data.status === 'success') {
                if (window.toastManager) window.toastManager.showToast(data.message, 'success');
                if (typeof onSuccess === 'function') onSuccess(data);
                if (window.resourceUpdater) window.resourceUpdater.fetchUpdate();
                updateBuildingQueue();
            } else {
                if (window.toastManager) window.toastManager.showToast(data.message || 'Upgrade error.', 'error');
            }
        } catch (error) {
            console.error('Upgrade AJAX error:', error);
            if (window.toastManager) window.toastManager.showToast('A communication error occurred during upgrade.', 'error');
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText || button.textContent;
            }
        }
    }

    // Handle building action button clicks (using event delegation) - buttons might be inside the popup
    document.addEventListener('click', async function(event) {
        const upgradeListButton = event.target.closest('.upgrade-building-button');
        if (upgradeListButton) {
            const villageId = upgradeListButton.dataset.villageId || window.currentVillageId;
            const buildingInternalName = upgradeListButton.dataset.buildingInternalName;
            const currentLevel = parseInt(upgradeListButton.dataset.currentLevel, 10);
            await requestUpgrade(villageId, buildingInternalName, currentLevel, upgradeListButton, (data) => {
                // Keep the button disabled after queuing to reflect the in-progress state
                upgradeListButton.disabled = true;
                upgradeListButton.classList.remove('btn-primary');
                upgradeListButton.classList.add('btn-secondary');
                upgradeListButton.textContent = `Upgrading to level ${currentLevel + 1}`;
            });
            return;
        }

        const button = event.target.closest('.building-action-button');

        if (button) {
            event.preventDefault();
            const villageId = window.currentVillageId || button.dataset.villageId;
            const buildingInternalName = (button.dataset.buildingInternalName || '').trim();
            const normalizedInternalName = buildingInternalName === 'main_building_flag' ? 'main_building' : buildingInternalName;

            const recruitmentBuildings = ['barracks', 'stable', 'workshop'];
            if (recruitmentBuildings.includes(normalizedInternalName)) {
                return;
            }

            if (!popupActionContent || !buildingDetailsContent || !villageId || !buildingInternalName) {
                console.error('Missing elements or data for building action.');
                if (window.toastManager) window.toastManager.showToast('Error: missing data to perform the action.', 'error');
                return;
            }

            // Main building has a dedicated panel; handle it directly for reliability
            if (normalizedInternalName === 'main_building') {
                openActionPopupShell(normalizedInternalName, {
                    name: button.dataset.buildingName,
                    level: button.dataset.buildingLevel,
                    description: button.dataset.buildingDescription
                });
                if (typeof fetchAndRenderMainBuildingPanel === 'function') {
                    fetchAndRenderMainBuildingPanel(villageId, normalizedInternalName);
                } else {
                    popupActionContent.innerHTML = '<p>Town hall panel is unavailable right now.</p>';
                    popupActionContent.style.display = 'block';
                    buildingDetailsContent.style.display = 'none';
                }
                return;
            }

            openActionPopupShell(normalizedInternalName, {
                name: button.dataset.buildingName,
                level: button.dataset.buildingLevel,
                description: button.dataset.buildingDescription
            });

            const specialPanelHandlers = {
                main_building: typeof fetchAndRenderMainBuildingPanel === 'function' ? fetchAndRenderMainBuildingPanel : null,
                smithy: typeof fetchAndRenderResearchPanel === 'function' ? fetchAndRenderResearchPanel : null,
                academy: typeof fetchAndRenderResearchPanel === 'function' ? fetchAndRenderResearchPanel : null,
                market: typeof fetchAndRenderMarketPanel === 'function' ? fetchAndRenderMarketPanel : null,
                rally_point: typeof fetchAndRenderRallyPanel === 'function' ? fetchAndRenderRallyPanel : null,
                statue: typeof fetchAndRenderNoblePanel === 'function' ? fetchAndRenderNoblePanel : null,
                mint: typeof fetchAndRenderMintPanel === 'function' ? fetchAndRenderMintPanel : null,
                warehouse: typeof fetchAndRenderInfoPanel === 'function' ? fetchAndRenderInfoPanel : null,
                sawmill: typeof fetchAndRenderInfoPanel === 'function' ? fetchAndRenderInfoPanel : null,
                wood_production: typeof fetchAndRenderInfoPanel === 'function' ? fetchAndRenderInfoPanel : null,
                clay_pit: typeof fetchAndRenderInfoPanel === 'function' ? fetchAndRenderInfoPanel : null,
                iron_mine: typeof fetchAndRenderInfoPanel === 'function' ? fetchAndRenderInfoPanel : null,
                wall: typeof fetchAndRenderInfoPanel === 'function' ? fetchAndRenderInfoPanel : null
            };

            const handler = specialPanelHandlers[normalizedInternalName];
            if (handler) {
                handler(villageId, normalizedInternalName);
                return;
            }

            const originalActionButtonContent = button.innerHTML;
            button.disabled = true;
            button.textContent = 'Loading...'; // Or add a spinner
            popupActionContent.innerHTML = '<p>Loading action content...</p>';
            popupActionContent.style.display = 'block';
            buildingDetailsContent.style.display = 'none'; // Hide details when showing action content

            try {
                const response = await fetch(`${buildingEndpoints.action}?village_id=${villageId}&building_internal_name=${buildingInternalName}`);
                const data = await response.json();

                if (data.status === 'success' && popupActionContent) {
                    popupActionContent.innerHTML = data.html; // Assume the response includes HTML in 'html'
                    popupActionContent.style.display = 'block';
                    buildingDetailsContent.style.display = 'none'; // Ensure details are hidden

                    updateTimers(); // Restart timers for the new content

                    switch(data.action_type) {
                        case 'recruit_barracks':
                        case 'recruit_stable':
                        case 'recruit_workshop':
                            break;
                        case 'research':
                            break;
                        case 'trade':
                            break;
                        case 'main_building':
                            break;
                        case 'noble':
                            break;
                        case 'mint':
                            break;
                    }


                } else if (popupActionContent) {
                    popupActionContent.innerHTML = '<p>Error loading action: ' + (data.message || data.error || 'Unknown error') + '</p>';
                    if (window.toastManager) window.toastManager.showToast(data.message || data.error || 'Server error.', 'error');
                    popupActionContent.style.display = 'block'; // Show error message in action section
                    buildingDetailsContent.style.display = 'none';
                }

            } catch (error) {
                console.error('Building action AJAX error:', error);
                if (popupActionContent) {
                    popupActionContent.innerHTML = '<p>A communication error occurred.</p>';
                    popupActionContent.style.display = 'block';
                }
                if (window.toastManager) window.toastManager.showToast('A communication error occurred while fetching the action.', 'error');
                if (buildingDetailsContent) buildingDetailsContent.style.display = 'none';
            } finally {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = originalActionButtonContent || button.textContent;
                }
            }
        } else if (event.target.classList.contains('upgrade-building-button')) { // Handle clicks on the 'Upgrade' button in the building list
             // This logic is already implemented in the popupUpgradeButton click listener.
             // If list buttons should also work, move the popup handler logic here and adjust data fetching.
             // For now, upgrades are handled via the popup button only.
        }
        });

     // Helper to get building action text (keep in sync with PHP getBuildingActionText)
     function getBuildingActionText(internalName) {
          switch(internalName) {
               case 'main_building': return 'Manage village';
               case 'barracks': return 'Recruit units';
               case 'stable': return 'Recruit units';
               case 'workshop': return 'Recruit units';
               case 'academy': return 'Research technology';
               case 'market': return 'Trade resources';
               case 'statue': return 'Noble statue';
               case 'church': return 'Church';
               case 'first_church': return 'First church';
               case 'mint': return 'Mint';
               // For production buildings (wood_production, clay_pit, iron_mine, farm) and others (warehouse, wall, watchtower)
               // The action might just be "Details" or similar, handled by the details popup itself.
              default: return 'Action'; // Default text when no specific action
         }
     }


    // Handle clicking the popup close button
    if (popupCloseBtn) {
        popupCloseBtn.addEventListener('click', closeBuildingDetailsPopup);
    }
    if (popupOverlay) {
        popupOverlay.addEventListener('click', closeBuildingDetailsPopup); // Close when clicking the overlay
    }

    // Handle clicking the 'Upgrade' button in the popup
    if (popupUpgradeButton) {
        popupUpgradeButton.addEventListener('click', async function() {
            const button = this;
            const villageId = button.dataset.villageId || window.currentVillageId;
            const buildingInternalName = button.dataset.buildingInternalName;
            const currentLevel = parseInt(button.dataset.currentLevel, 10);
            await requestUpgrade(villageId, buildingInternalName, currentLevel, button, () => {
                closeBuildingDetailsPopup();
            });
        });
    }


    // Funkcja do aktualizacji kolejki budowy
    async function updateBuildingQueue() {
        const buildingQueueList = document.getElementById('building-queue-list'); // Ensure this element exists in game.php
        if (!buildingQueueList || !window.currentVillageId) return; // Use the global variable


        // Optional: Show a loading indicator for the queue itself
        buildingQueueList.innerHTML = '<p class="queue-empty">Loading build queue...</p>'; // Loading message


        try {
            // Use the global variable villageId
            const response = await fetch(`${buildingEndpoints.queue}?village_id=${window.currentVillageId}`);
            const data = await response.json();

            if (data.status === 'success') {
                const queueItem = data.data.queue_item;
                buildingQueueList.innerHTML = ''; // Clear the current queue

                if (queueItem) {
                    const startAttr = queueItem.start_time ? ` data-start-time="${queueItem.start_time}"` : '';
                    const queueHtml = `
                        <div class="queue-item current" data-building-internal-name="${queueItem.building_internal_name}"> <!-- Dodano atrybut building-internal-name -->
                            <div class="item-header">
                                <div class="item-title">
                                    <span class="building-name">${queueItem.building_name}</span>
                                    <span class="building-level">Level ${queueItem.level}</span>
                                </div>
                                <div class="item-actions">
                                    <button class="cancel-button" data-queue-id="${queueItem.id}" title="Cancel construction">X</button>
                                </div>
                            </div>
                            <div class="item-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 0%;"></div>
                                </div>
                                <div class="progress-time" data-ends-at="${queueItem.finish_time}"${startAttr}></div>
                            </div>
                        </div>
                    `;
                    buildingQueueList.innerHTML = queueHtml;
                    // Restart timers for the newly added element
                    // updateTimers(); // Global interval handles this
                } else {
                    buildingQueueList.innerHTML = '<p class="queue-empty">No tasks in the build queue.</p>';
                }
            } else {
                console.error('Error fetching build queue:', data.message);
                if (window.toastManager) window.toastManager.showToast('Error fetching the build queue.', 'error');
                 if (buildingQueueList) buildingQueueList.innerHTML = '<p class="queue-empty error">Error loading queue.</p>'; // Show error state
            }
        } catch (error) {
            console.error('Build queue AJAX error:', error);
             if (window.toastManager) window.toastManager.showToast('A communication error occurred while fetching the build queue.', 'error');
             if (buildingQueueList) buildingQueueList.innerHTML = '<p class="queue-empty error">A communication error occurred.</p>'; // Show error state
        }
    }

    // === Cancel building task handling ===
    // Add event listener for cancel buttons in the build queue (event delegation)
    document.addEventListener('click', async function(event) {
        const cancelButton = event.target.closest('.cancel-button');
        // Ensure it's a building cancel button, not recruitment (if they use the same class)
         if (!cancelButton || cancelButton.classList.contains('recruitment-cancel-button')) return; // Ensure this is the cancel button

        const queueItemId = cancelButton.dataset.queueId;
        if (!queueItemId) {
            console.error('Missing build queue ID to cancel.');
            return;
        }

        // User cancellation confirmation
        if (!confirm('Are you sure you want to cancel this construction? You will recover 90% of the resources.')) {
            return;
        }

        // Disable button and show loading state
         cancelButton.disabled = true;
         cancelButton.textContent = '...'; // Or a spinner

        try {
            // Send AJAX request to cancel_upgrade.php
            const response = await fetch(buildingEndpoints.cancel, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `queue_item_id=${queueItemId}&csrf_token=${encodeURIComponent(getCsrfToken())}&ajax=1` // Add ajax flag
            });
            const data = await response.json();

            if (data.success) {
                if (window.toastManager) window.toastManager.showToast(data.message, 'success');
                // Update resource numbers
                if (window.resourceUpdater && data.village_info) {
                    // We can update resource data directly in resourceUpdater
                    window.resourceUpdater.resources.wood.amount = data.village_info.wood;
                    window.resourceUpdater.resources.clay.amount = data.village_info.clay;
                    window.resourceUpdater.resources.iron.amount = data.village_info.iron;
                    window.resourceUpdater.resources.population.amount = data.village_info.population; // Population may change after canceling the farm
                    // Update capacities (may change if warehouse/farm upgrade was canceled)
                    window.resourceUpdater.resources.wood.capacity = data.village_info.warehouse_capacity;
                    window.resourceUpdater.resources.clay.capacity = data.village_info.warehouse_capacity;
                    window.resourceUpdater.resources.iron.capacity = data.village_info.warehouse_capacity;
                    window.resourceUpdater.resources.population.capacity = data.village_info.farm_capacity;

                    window.resourceUpdater.updateUI(); // Refresh display
                }
                // Update the build queue by removing the entry
                updateBuildingQueue(); // Simplest solution: reload

                // Re-enable the upgrade button for the canceled building
                // Find building-item or placeholder based on internal_name
                const buildingInternalNameAfterCancel = data.building_internal_name; // Grab internal_name from the server response
                if (buildingInternalNameAfterCancel) {
                    const buildingItem = document.querySelector(`.building-item[data-internal-name='${buildingInternalNameAfterCancel}']`);
                    const buildingPlaceholder = document.querySelector(`.building-placeholder[data-building-internal-name='${buildingInternalNameAfterCancel}']`);

                    if (buildingItem) {
                         // Find the upgrade button in that building-item and enable it
                        const upgradeButton = buildingItem.querySelector('.upgrade-button'); // Ensure selector is correct
                        if (upgradeButton) {
                            upgradeButton.disabled = false;
                            upgradeButton.classList.remove('btn-secondary');
                            upgradeButton.classList.add('btn-primary');
                            // Remove unavailable reason if present
                            const reasonElement = buildingItem.querySelector('.upgrade-unavailable-reason'); // Assuming such a class
                            if(reasonElement) reasonElement.style.display = 'none';
                        }
                         // Remove 'upgrading' status and timer
                         const statusElement = buildingItem.querySelector('.upgrade-status');
                         if (statusElement && statusElement.textContent.includes('Upgrading')) {
                              // Update status text - either guess new level or clear build status
                               // Ideally the server response should include the new building level
                               // If data.new_level is available: statusElement.textContent = `Level ${data.new_level}:`;
                               // Otherwise reset to a generic status
                               statusElement.textContent = `Upgrade to level ${parseInt(buildingItem.dataset.currentLevel || 0, 10) + 1}:`; // May be inaccurate if the last level was canceled
                         }
                         const timerElement = buildingItem.querySelector('.upgrade-timer'); // Ensure selector is correct
                         if (timerElement) timerElement.remove();
                    }

                     if (buildingPlaceholder) {
                        buildingPlaceholder.classList.remove('building-upgrading');
                         // After cancel, switch the graphic back to PNG if it was a GIF
                         const buildingImage = buildingPlaceholder.querySelector('.building-graphic');
                          if (buildingImage) {
                              const currentSrc = buildingImage.src;
                              if (currentSrc.endsWith('.gif')) {
                                  const pngSrc = currentSrc.replace('.gif', '.png');
                                  buildingImage.src = pngSrc;
                              }
                          }
                     }
                }


            } else {
                 if (window.toastManager) window.toastManager.showToast(data.error || data.message || 'Error cancelling construction.', 'error');
            }
        } catch (error) {
            console.error('Construction cancellation AJAX error:', error);
            if (window.toastManager) window.toastManager.showToast('A communication error occurred during cancellation.', 'error');
        } finally {
             // Re-enable button regardless of success or failure (if it still exists in DOM)
             if (cancelButton && cancelButton.parentNode) {
                  cancelButton.disabled = false;
                  cancelButton.textContent = 'X'; // Restore original text
             }
        }
    });
    // =========================================

    // Start timers after DOM load and refresh every second
    updateTimers(); // Initial call
    setInterval(updateTimers, 1000); // Refresh every second

     // Initial load of the building queue when the page loads
     // Assuming currentVillageId is set globally in game.php
     const villageId = window.currentVillageId || null;
     if (villageId) {
         updateBuildingQueue(villageId);
     } else {
         console.warn('Village ID not available. Cannot initialize building queue.');
     }
});

// Funkcja do formatowania liczb z separatorami
function formatNumber(number) {
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}
