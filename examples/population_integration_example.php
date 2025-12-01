<?php
declare(strict_types=1);

/**
 * Example: Population System Integration
 * 
 * This file demonstrates how to integrate the PopulationManager
 * into various game systems (buildings, units, support).
 */

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/managers/PopulationManager.php';

// ============================================================================
// Example 1: Building Upgrade with Population Check
// ============================================================================

function upgradeBuildingWithPopulationCheck(int $villageId, string $buildingType): array
{
    $db = Database::getInstance();
    $popManager = new PopulationManager($db);
    
    // Get current building level
    $stmt = $db->query("
        SELECT level FROM buildings 
        WHERE village_id = ? AND building_type = ?
    ", [$villageId, $buildingType]);
    
    $building = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    $currentLevel = $building ? (int)$building['level'] : 0;
    $nextLevel = $currentLevel + 1;
    
    // Check population availability
    $popCheck = $popManager->canAffordBuildingPopulation($villageId, $buildingType, $nextLevel);
    
    if (!$popCheck['success']) {
        return [
            'success' => false,
            'message' => $popCheck['message']
        ];
    }
    
    // Check resources, requirements, etc. (omitted for brevity)
    // ...
    
    // Queue the building upgrade
    // Population will be consumed when the building completes
    
    return [
        'success' => true,
        'message' => 'Building upgrade queued successfully'
    ];
}

// ============================================================================
// Example 2: Unit Recruitment with Population Check
// ============================================================================

function recruitUnitsWithPopulationCheck(int $villageId, string $unitType, int $quantity): array
{
    $db = Database::getInstance();
    $popManager = new PopulationManager($db);
    
    // Check population availability
    $popCheck = $popManager->canAffordUnitPopulation($villageId, $unitType, $quantity);
    
    if (!$popCheck['success']) {
        return [
            'success' => false,
            'message' => $popCheck['message']
        ];
    }
    
    // Check resources (omitted for brevity)
    // ...
    
    $db->beginTransaction();
    
    try {
        // Deduct resources
        // $resourceManager->deductResources($villageId, $costs);
        
        // Add to recruitment queue
        // Population is reserved immediately (counted in queue)
        $db->execute("
            INSERT INTO unit_queue (village_id, unit_type, quantity, complete_at)
            VALUES (?, ?, ?, ?)
        ", [
            $villageId,
            $unitType,
            $quantity,
            time() + 3600 // 1 hour training time
        ]);
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => "Recruitment queued: {$quantity} × {$unitType}"
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return [
            'success' => false,
            'message' => 'Failed to queue recruitment: ' . $e->getMessage()
        ];
    }
}

// ============================================================================
// Example 3: Support Troops Arrival
// ============================================================================

function processSupportArrival(int $targetVillageId, int $sourceVillageId, array $units): array
{
    $db = Database::getInstance();
    $popManager = new PopulationManager($db);
    
    // Calculate total population of arriving support
    $totalPopulation = 0;
    foreach ($units as $unitType => $quantity) {
        $stmt = $db->query("
            SELECT population FROM unit_types WHERE internal_name = ?
        ", [$unitType]);
        
        $unitData = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($unitData) {
            $totalPopulation += (int)$unitData['population'] * $quantity;
        }
    }
    
    // Check if target village has capacity for support
    $state = $popManager->getPopulationState($targetVillageId);
    
    if (($state['used'] + $totalPopulation) > $state['cap']) {
        return [
            'success' => false,
            'message' => sprintf(
                'Target village does not have enough population capacity. Required: %d, Available: %d',
                $totalPopulation,
                $state['available']
            )
        ];
    }
    
    $db->beginTransaction();
    
    try {
        // Add support troops to target village
        foreach ($units as $unitType => $quantity) {
            $db->execute("
                INSERT INTO support_units (stationed_village_id, owner_village_id, unit_type, quantity)
                VALUES (?, ?, ?, ?)
                ON CONFLICT(stationed_village_id, owner_village_id, unit_type)
                DO UPDATE SET quantity = quantity + excluded.quantity
            ", [$targetVillageId, $sourceVillageId, $unitType, $quantity]);
        }
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Support troops arrived successfully'
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return [
            'success' => false,
            'message' => 'Failed to process support arrival: ' . $e->getMessage()
        ];
    }
}

// ============================================================================
// Example 4: Support Troops Withdrawal
// ============================================================================

function withdrawSupport(int $stationedVillageId, int $ownerVillageId, array $units): array
{
    $db = Database::getInstance();
    
    $db->beginTransaction();
    
    try {
        foreach ($units as $unitType => $quantity) {
            // Remove from support_units
            $db->execute("
                UPDATE support_units
                SET quantity = quantity - ?
                WHERE stationed_village_id = ? 
                  AND owner_village_id = ? 
                  AND unit_type = ?
            ", [$quantity, $stationedVillageId, $ownerVillageId, $unitType]);
            
            // Add back to owner village
            $db->execute("
                INSERT INTO units (village_id, unit_type, quantity)
                VALUES (?, ?, ?)
                ON CONFLICT(village_id, unit_type)
                DO UPDATE SET quantity = quantity + excluded.quantity
            ", [$ownerVillageId, $unitType, $quantity]);
        }
        
        // Clean up zero-quantity support entries
        $db->execute("DELETE FROM support_units WHERE quantity <= 0");
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Support troops withdrawn successfully'
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return [
            'success' => false,
            'message' => 'Failed to withdraw support: ' . $e->getMessage()
        ];
    }
}

// ============================================================================
// Example 5: Farm Upgrade Completion
// ============================================================================

function completeFarmUpgrade(int $villageId, int $newLevel): array
{
    $db = Database::getInstance();
    $popManager = new PopulationManager($db);
    
    // Update farm level
    $db->execute("
        UPDATE buildings
        SET level = ?
        WHERE village_id = ? AND building_type = 'farm'
    ", [$newLevel, $villageId]);
    
    // Update population capacity
    $update = $popManager->updateFarmCapacity($villageId);
    
    return [
        'success' => true,
        'message' => sprintf(
            'Farm upgraded to level %d. Population capacity: %d → %d (Available: %d)',
            $newLevel,
            $update['old_cap'],
            $update['new_cap'],
            $update['available']
        ),
        'capacity_change' => $update
    ];
}

// ============================================================================
// Example 6: Display Population in UI
// ============================================================================

function displayPopulationWidget(int $villageId): string
{
    $popManager = new PopulationManager(Database::getInstance());
    $state = $popManager->getPopulationState($villageId);
    
    $percentage = $state['cap'] > 0 ? ($state['used'] / $state['cap']) * 100 : 0;
    $colorClass = $percentage > 90 ? 'danger' : ($percentage > 75 ? 'warning' : 'normal');
    
    return sprintf(
        '<div class="population-widget %s">
            <span class="icon">👥</span>
            <span class="used">%d</span>
            <span class="separator">/</span>
            <span class="cap">%d</span>
            <div class="progress-bar">
                <div class="progress-fill" style="width: %.1f%%"></div>
            </div>
        </div>',
        $colorClass,
        $state['used'],
        $state['cap'],
        $percentage
    );
}

// ============================================================================
// Example 7: Periodic Sanity Check (Cron Job)
// ============================================================================

function runPopulationSanityCheck(): void
{
    $db = Database::getInstance();
    $popManager = new PopulationManager($db);
    
    // Get all active villages
    $villages = $db->fetchAll("SELECT id FROM villages WHERE user_id > 0");
    
    $issues = [];
    
    foreach ($villages as $village) {
        $villageId = (int)$village['id'];
        $sanity = $popManager->sanityCheck($villageId);
        
        if ($sanity['over_capacity']) {
            $issues[] = sprintf(
                "Village %d is over capacity: %d/%d (Buildings: %d, Troops: %d, Support: %d)",
                $villageId,
                $sanity['total'],
                $sanity['cap'],
                $sanity['buildings'],
                $sanity['troops'],
                $sanity['support']
            );
        }
    }
    
    if (!empty($issues)) {
        error_log("Population sanity check found issues:\n" . implode("\n", $issues));
    } else {
        echo "Population sanity check: All villages OK\n";
    }
}

// ============================================================================
// Example 8: Population Breakdown for Admin Panel
// ============================================================================

function getPopulationBreakdown(int $villageId): array
{
    $popManager = new PopulationManager(Database::getInstance());
    
    return [
        'capacity' => $popManager->getPopulationCap($villageId),
        'breakdown' => [
            'buildings' => $popManager->getBuildingPopulation($villageId),
            'troops' => $popManager->getTroopPopulation($villageId),
            'support' => $popManager->getSupportPopulation($villageId),
        ],
        'total_used' => $popManager->getPopulationUsed($villageId),
        'state' => $popManager->getPopulationState($villageId)
    ];
}

// ============================================================================
// Usage Examples
// ============================================================================

if (php_sapi_name() === 'cli') {
    echo "Population System Integration Examples\n";
    echo "======================================\n\n";
    
    // Example: Check population for a village
    $villageId = 1; // Replace with actual village ID
    
    $popManager = new PopulationManager(Database::getInstance());
    $state = $popManager->getPopulationState($villageId);
    
    echo "Village {$villageId} Population:\n";
    echo "  Used: {$state['used']}\n";
    echo "  Capacity: {$state['cap']}\n";
    echo "  Available: {$state['available']}\n\n";
    
    // Example: Check if can recruit units
    $check = $popManager->canAffordUnitPopulation($villageId, 'spear', 10);
    echo "Can recruit 10 spearmen: " . ($check['success'] ? 'Yes' : 'No') . "\n";
    if (!$check['success']) {
        echo "  Reason: {$check['message']}\n";
    }
    echo "\n";
    
    // Example: Get population breakdown
    $breakdown = getPopulationBreakdown($villageId);
    echo "Population Breakdown:\n";
    echo "  Buildings: {$breakdown['breakdown']['buildings']}\n";
    echo "  Troops: {$breakdown['breakdown']['troops']}\n";
    echo "  Support: {$breakdown['breakdown']['support']}\n";
    echo "  Total: {$breakdown['total_used']}/{$breakdown['capacity']}\n";
}
