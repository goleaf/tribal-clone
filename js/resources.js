/**
 * Dynamic resource updates without page refresh.
 * Keeps client-side counters in sync with server production.
 */

class ResourceUpdater {
    /**
     * @param {Object} options configuration overrides
     */
    constructor(options = {}) {
        const defaultApiUrl = `${window.location.origin}/ajax_proxy.php`;
        this.options = {
            apiUrl: defaultApiUrl,
            updateInterval: 30000, // 30s
            tickInterval: 1000, // 1s
            resourcesSelector: '#resources-bar',
            villageId: null,
            ...options
        };

        this.resources = {
            wood: { amount: 0, capacity: 0, production: 0, production_per_second: 0 },
            clay: { amount: 0, capacity: 0, production: 0, production_per_second: 0 },
            iron: { amount: 0, capacity: 0, production: 0, production_per_second: 0 },
            population: { amount: 0, capacity: 0 }
        };

        this.isInitialized = false;
        this.updateTimer = null;
        this.tickTimer = null;
        this.lastServerUpdate = null;
        this.lastClientUpdate = null;
        this.abortController = null;

        this.init();
    }

    async init() {
        try {
            const data = await this.fetchUpdate();
            if (data) {
                this.isInitialized = true;
                this.startUpdateTimer();
                this.startTickTimer();
            }
        } catch (error) {
            console.error('Resource updater initialization error:', error);
        }
    }

    async fetchUpdate() {
        try {
            if (this.abortController) {
                this.abortController.abort();
            }
            this.abortController = new AbortController();

            let url = this.options.apiUrl;
            if (this.options.villageId) {
                url += `?village_id=${this.options.villageId}`;
            }

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                },
                credentials: 'same-origin',
                signal: this.abortController.signal
            });

            if (!response.ok) {
                if (response.status === 401) {
                    return null;
                }
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText.substring(0, 120)}`);
            }

            const rawText = await response.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                console.error('Invalid JSON response:', rawText.substring(0, 200));
                throw e;
            }

            if (data.status !== 'success') {
                throw new Error(data.message || 'Unknown server error');
            }

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
            this.resources.population = {
                amount: parseFloat(data.data.population.amount),
                capacity: parseFloat(data.data.population.capacity) || 0
            };

            this.lastServerUpdate = new Date(data.data.current_server_time);
            this.lastClientUpdate = new Date();

            this.updateUI();
            return data;
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Error fetching resource updates:', error);
            }
            return null;
        } finally {
            this.abortController = null;
        }
    }

    updateUI() {
        const container = document.querySelector(this.options.resourcesSelector);
        if (!container) return;

        this.updateResourceDisplay(container, 'wood', this.resources.wood);
        this.updateResourceDisplay(container, 'clay', this.resources.clay);
        this.updateResourceDisplay(container, 'iron', this.resources.iron);
        this.updateResourceDisplay(container, 'population', this.resources.population);
    }

    updateResourceDisplay(container, resourceType, resourceData) {
        const currentAmount = Math.floor(resourceData.amount);
        const capacity = resourceData.capacity;
        const productionPerHour = resourceData.production ?? resourceData.production_per_hour ?? 0;

        const valueElement = container.querySelector(`#current-${resourceType}`);
        if (valueElement) {
            valueElement.textContent = this.formatNumber(currentAmount);
            if (capacity) {
                if (currentAmount >= capacity) {
                    valueElement.classList.add('resource-full');
                    valueElement.classList.remove('resource-almost-full');
                } else if (currentAmount >= capacity * 0.9) {
                    valueElement.classList.add('resource-almost-full');
                    valueElement.classList.remove('resource-full');
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

        const tooltipCurrent = container.querySelector(`#tooltip-current-${resourceType}`);
        if (tooltipCurrent) tooltipCurrent.textContent = this.formatNumber(currentAmount);

        const tooltipCapacity = container.querySelector(`#tooltip-capacity-${resourceType}`);
        if (tooltipCapacity && capacity) tooltipCapacity.textContent = this.formatNumber(capacity);

        const tooltipProduction = container.querySelector(`#tooltip-prod-${resourceType}`);
        if (tooltipProduction && productionPerHour !== undefined) {
            tooltipProduction.textContent = `+${this.formatNumber(productionPerHour)}/h`;
        }

        const progressBarInner = container.querySelector(`#bar-${resourceType}`);
        if (progressBarInner && capacity) {
            const percentage = Math.min(100, (currentAmount / capacity) * 100);
            progressBarInner.style.width = `${percentage}%`;
        }
    }

    tick() {
        if (!this.isInitialized || !this.lastClientUpdate) return;

        const now = new Date();
        const elapsedSeconds = (now - this.lastClientUpdate) / 1000;
        this.lastClientUpdate = now;

        for (const resourceType of ['wood', 'clay', 'iron']) {
            const resource = this.resources[resourceType];
            if (resource.production_per_second > 0) {
                const newAmount = resource.amount + (resource.production_per_second * elapsedSeconds);
                resource.amount = resource.capacity ? Math.min(newAmount, resource.capacity) : newAmount;
            }
        }

        if (this.resources.population.capacity) {
            this.resources.population.amount = Math.min(this.resources.population.amount, this.resources.population.capacity);
        }

        this.updateUI();
    }

    startUpdateTimer() {
        this.stopUpdateTimer();
        this.updateTimer = setInterval(() => this.fetchUpdate(), this.options.updateInterval);
    }

    stopUpdateTimer() {
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
            this.updateTimer = null;
        }
    }

    startTickTimer() {
        this.stopTickTimer();
        this.tickTimer = setInterval(() => this.tick(), this.options.tickInterval);
    }

    stopTickTimer() {
        if (this.tickTimer) {
            clearInterval(this.tickTimer);
            this.tickTimer = null;
        }
    }

    formatNumber(number) {
        return window.formatNumber(number);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const villageId = window.currentVillageId || null;
    if (villageId) {
        window.resourceUpdater = new ResourceUpdater({ villageId });
    } else {
        console.warn('Village ID not found. Resource updater will not start.');
    }
});
