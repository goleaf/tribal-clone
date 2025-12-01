/**
 * Dynamic resource updates without page refresh
 */

// ResourceUpdater manages real-time resource updates
class ResourceUpdater {
    /**
     * Constructor
     * @param {Object} options - Configuration options
     */
    constructor(options = {}) {
        // Default options
        this.options = {
            apiUrl: 'http://localhost/ajax_proxy.php', // Full URL to the proxy
            updateInterval: 30000, // 30 seconds
            tickInterval: 1000, // 1 second
            resourcesSelector: '#resources-bar',
            villageId: null,
            ...options
        };
        
        // Resource state
        this.resources = {
            wood: { amount: 0, capacity: 0, production: 0, production_per_second: 0 },
            clay: { amount: 0, capacity: 0, production: 0, production_per_second: 0 },
            iron: { amount: 0, capacity: 0, production: 0, production_per_second: 0 },
            population: { amount: 0 }
        };
        
        // Flags
        this.isInitialized = false;
        this.updateTimer = null;
        this.tickTimer = null;
        this.lastServerUpdate = null;
        this.lastClientUpdate = null;
        
        // Initialize
        this.init();
    }
    
    /**
     * Initialize the resource updater
     */
    async init() {
        try {
            // Fetch initial data
            const data = await this.fetchUpdate();
            
            // Start timers if data loaded
            if (data) {
                this.isInitialized = true;
                this.startUpdateTimer();
                this.startTickTimer();
            }
        } catch (error) {
            console.error('Resource updater initialization error:', error);
        }
    }
    
    /**
     * Fetch resource updates from the server
     */
    async fetchUpdate() {
        try {
            // Build request URL
            let url = this.options.apiUrl;
            if (this.options.villageId) {
                url += `?village_id=${this.options.villageId}`;
            }
            
            console.log(`Fetching resources from: ${url}`);
            
            // Perform request
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                },
                credentials: 'same-origin'
            });
            
            // Validate response
            if (!response.ok) {
                // Handle 401/500 explicitly
                if (response.status === 401) {
                    console.log('Session expired or user not logged in. Redirect to login if desired...');
                    // Optional redirect to login
                    // window.location.href = 'login.php';
                    return null;
                }
                
                if (response.status === 500) {
                    const errorText = await response.text();
                    console.error('Server error 500:', errorText);
                    throw new Error(`HTTP 500 error: ${errorText.substring(0, 100)}...`);
                }
                
                throw new Error(`HTTP error: ${response.status}`);
            }
            
            // Parse JSON with error handling
            let data;
            try {
                const text = await response.text();
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text.substring(0, 100) + '...');
                    throw new Error('Server returned invalid data format');
                }
            } catch (e) {
                console.error('Response processing error:', e);
                throw e;
            }
            
            // Check response status
            if (data.status !== 'success') {
                throw new Error(data.message || 'Unknown server error');
            }
            
            // Update resource data, including production rates
            this.resources.wood = {
                amount: parseFloat(data.data.wood.amount),
                capacity: parseFloat(data.data.wood.capacity),
                production: parseFloat(data.data.wood.production) || 0,
                production_per_second: parseFloat(data.data.wood.production_per_second) || 0
            };
            this.resources.clay = {
                amount: parseFloat(data.data.clay.amount),
                capacity: parseFloat(data.data.clay.capacity),
                production: parseFloat(data.data.clay.production) || 0,
                production_per_second: parseFloat(data.data.clay.production_per_second) || 0
            };
            this.resources.iron = {
                amount: parseFloat(data.data.iron.amount),
                capacity: parseFloat(data.data.iron.capacity),
                production: parseFloat(data.data.iron.production) || 0,
                production_per_second: parseFloat(data.data.iron.production_per_second) || 0
            };
            // Population has no per-second production; only amount and capacity
            this.resources.population = {
                amount: parseFloat(data.data.population.amount),
                capacity: parseFloat(data.data.population.capacity) || 0 // Can be 0 at early levels
            };
            
            // Save timestamps
            this.lastServerUpdate = new Date(data.data.current_server_time);
            this.lastClientUpdate = new Date();
            
            // Refresh UI
            this.updateUI();
            
            return data;
        } catch (error) {
            console.error('Error fetching resource updates:', error);
            // Keep running with previous values
            return null;
        }
    }
    
    /**
     * Update the UI with current resource values
     */
    updateUI() {
        // Find resource container
        const container = document.querySelector(this.options.resourcesSelector);
        if (!container) return;
        
        // Update resource values
        this.updateResourceDisplay(container, 'wood', this.resources.wood);
        this.updateResourceDisplay(container, 'clay', this.resources.clay);
        this.updateResourceDisplay(container, 'iron', this.resources.iron);
        this.updateResourceDisplay(container, 'population', this.resources.population);
    }
    
    /**
     * Update display of a single resource including tooltips/bars.
     * @param {HTMLElement} container - Resource container.
     * @param {string} resourceType - e.g. 'wood', 'clay'.
     * @param {Object} resourceData - Data object (amount, capacity, production_per_hour, production_per_second).
     */
    updateResourceDisplay(container, resourceType, resourceData) {
        const currentAmount = Math.floor(resourceData.amount);
        const capacity = resourceData.capacity;
        const productionPerHour = resourceData.production_per_hour;

        // Update main resource bar
        const valueElement = container.querySelector(`#current-${resourceType}`);
        if (valueElement) {
            valueElement.textContent = this.formatNumber(currentAmount);
            if (capacity) {
                if (currentAmount >= capacity * 0.9 && currentAmount < capacity) {
                    valueElement.classList.add('resource-almost-full');
                    valueElement.classList.remove('resource-full');
                } else if (currentAmount >= capacity) {
                    valueElement.classList.add('resource-full');
                    valueElement.classList.remove('resource-almost-full');
                } else {
                    valueElement.classList.remove('resource-almost-full', 'resource-full');
                }
            }
        }

        const capacityElement = container.querySelector(`#capacity-${resourceType}`);
        if (capacityElement && capacity) {
            capacityElement.textContent = this.formatNumber(capacity);
        }

        const productionElement = container.querySelector(`#prod-${resourceType}`);
        if (productionElement && productionPerHour !== undefined) {
            productionElement.textContent = `+${this.formatNumber(productionPerHour)}/h`;
        }

        // Update tooltip
        const tooltipCurrent = container.querySelector(`#tooltip-current-${resourceType}`);
        if (tooltipCurrent) tooltipCurrent.textContent = this.formatNumber(currentAmount);

        const tooltipCapacity = container.querySelector(`#tooltip-capacity-${resourceType}`);
        if (tooltipCapacity && capacity) tooltipCapacity.textContent = this.formatNumber(capacity);

        const tooltipProduction = container.querySelector(`#tooltip-prod-${resourceType}`);
        if (tooltipProduction && productionPerHour !== undefined) {
            tooltipProduction.textContent = `+${this.formatNumber(productionPerHour)}/h`;
        }

        // Update progress bar in tooltip
        const progressBarInner = container.querySelector(`#bar-${resourceType}`);
        if (progressBarInner && capacity) {
            const percentage = Math.min(100, (currentAmount / capacity) * 100);
            progressBarInner.style.width = `${percentage}%`;
        }
    }
    
    /**
     * Update resources based on elapsed time
     */
    tick() {
        if (!this.isInitialized || !this.lastClientUpdate) return;
        
        // Calculate elapsed time since last client update
        const now = new Date();
        const elapsedSeconds = (now - this.lastClientUpdate) / 1000;
        this.lastClientUpdate = now;
        
        // Update resources using per-second production
        for (const resourceType of ['wood', 'clay', 'iron']) {
            const resource = this.resources[resourceType];
            
            // Add produced resources
            if (resource.production_per_second > 0) {
                const newAmount = resource.amount + (resource.production_per_second * elapsedSeconds);
                
                // Do not exceed storage capacity
                resource.amount = resource.capacity ? Math.min(newAmount, resource.capacity) : newAmount;
            }
        }
        // Population has no production but has a cap
        if (this.resources.population.capacity) {
            this.resources.population.amount = Math.min(this.resources.population.amount, this.resources.population.capacity);
        }
        
        // Update UI
        this.updateUI();
    }
    
    /**
     * Starts the server update timer
     */
    startUpdateTimer() {
        this.stopUpdateTimer();
        this.updateTimer = setInterval(() => this.fetchUpdate(), this.options.updateInterval);
    }
    
    /**
     * Stops the server update timer
     */
    stopUpdateTimer() {
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
            this.updateTimer = null;
        }
    }
    
    /**
     * Starts the client tick timer
     */
    startTickTimer() {
        this.stopTickTimer();
        this.tickTimer = setInterval(() => this.tick(), this.options.tickInterval);
    }
    
    /**
     * Stops the client tick timer
     */
    stopTickTimer() {
        if (this.tickTimer) {
            clearInterval(this.tickTimer);
            this.tickTimer = null;
        }
    }
    
    /**
     * Formats a number for display
     */
    formatNumber(number) {
        return window.formatNumber(number); // Use global function from utils.js
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Get village ID from the global JavaScript variable
    const villageId = window.currentVillageId || null;
    
    // Initialize only if village ID is available
    if (villageId) {
        window.resourceUpdater = new ResourceUpdater({
            villageId: villageId
        });
    } else {
        console.warn('Village ID not found. Resource updater will not start.');
    }
});
