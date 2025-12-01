# Battle Engine Integration Guide

## Integrating BattleEngine with BattleManager

This guide shows how to integrate the new BattleEngine into your existing BattleManager.

## Step 1: Update BattleManager Constructor

Add the BattleEngine to your BattleManager:

```php
class BattleManager
{
    private $conn;
    private $villageManager;
    private $buildingManager;
    private $battleEngine; // Add this
    
    public function __construct($conn, VillageManager $villageManager, BuildingManager $buildingManager)
    {
        $this->conn = $conn;
        $this->villageManager = $villageManager;
        $this->buildingManager = $buildingManager;
        
        // Initialize BattleEngine
        require_once __DIR__ . '/BattleEngine.php';
        $this->battleEngine = new BattleEngine($conn);
    }
}
```

## Step 2: Create Helper Methods

Add these helper methods to prepare data for the battle engine:

```php
/**
 * Convert attack units to internal_name => count format
 */
private function getAttackerUnits(int $attack_id): array
{
    $stmt = $this->conn->prepare("
        SELECT ut.internal_name, au.count
        FROM attack_units au
        JOIN unit_types ut ON au.unit_type_id = ut.id
        WHERE au.attack_id = ?
    ");
    $stmt->bind_param("i", $attack_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[$row['internal_name']] = (int)$row['count'];
    }
    $stmt->close();
    
    return $units;
}

/**
 * Get defender units (including supports)
 */
private function getDefenderUnits(int $village_id): array
{
    $stmt = $this->conn->prepare("
        SELECT ut.internal_name, vu.count
        FROM village_units vu
        JOIN unit_types ut ON vu.unit_type_id = ut.id
        WHERE vu.village_id = ? AND vu.count > 0
    ");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[$row['internal_name']] = (int)$row['count'];
    }
    $stmt->close();
    
    // TODO: Add support troops from other players
    
    return $units;
}

/**
 * Get world configuration
 */
private function getWorldConfig(): array
{
    require_once __DIR__ . '/WorldManager.php';
    $worldManager = new WorldManager($this->conn);
    
    return [
        'speed' => $worldManager->getWorldSpeed(),
        'night_bonus_enabled' => $worldManager->isNightBonusEnabled(),
        'night_start' => 22,
        'night_end' => 6
    ];
}

/**
 * Get user points
 */
private function getUserPoints(int $user_id): int
{
    if ($user_id <= 0) {
        return 0; // Barbarian village
    }
    
    $stmt = $this->conn->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return (int)$row['points'];
    }
    
    return 0;
}
```

## Step 3: Update processBattle Method

Replace the existing battle resolution logic with the new engine:

```php
public function processBattle(int $attack_id): array
{
    // Get attack details
    $stmt = $this->conn->prepare("
        SELECT 
            a.id, a.source_village_id, a.target_village_id, 
            a.attack_type, a.target_building,
            sv.user_id as attacker_user_id,
            tv.user_id as defender_user_id
        FROM attacks a
        JOIN villages sv ON a.source_village_id = sv.id
        JOIN villages tv ON a.target_village_id = tv.id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $attack_id);
    $stmt->execute();
    $attack = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$attack) {
        return ['success' => false, 'error' => 'Attack not found'];
    }
    
    // Prepare battle data
    $attackerUnits = $this->getAttackerUnits($attack_id);
    $defenderUnits = $this->getDefenderUnits($attack['target_village_id']);
    $wallLevel = $this->buildingManager->getBuildingLevel(
        $attack['target_village_id'], 
        'wall'
    );
    
    $attackerPoints = $this->getUserPoints($attack['attacker_user_id']);
    $defenderPoints = $this->getUserPoints($attack['defender_user_id']);
    $worldConfig = $this->getWorldConfig();
    
    // Get target building level if catapults present
    $targetBuildingLevel = 0;
    if (!empty($attack['target_building'])) {
        $targetBuildingLevel = $this->buildingManager->getBuildingLevel(
            $attack['target_village_id'],
            $attack['target_building']
        );
    }
    
    // Resolve battle using the engine
    $battleResult = $this->battleEngine->resolveBattle(
        $attackerUnits,
        $defenderUnits,
        $wallLevel,
        $defenderPoints,
        $attackerPoints,
        $worldConfig,
        $attack['target_building'],
        $targetBuildingLevel
    );
    
    // Process results
    $this->conn->begin_transaction();
    
    try {
        // Update wall if damaged
        if ($battleResult['wall']['end'] < $battleResult['wall']['start']) {
            $this->buildingManager->setBuildingLevel(
                $attack['target_village_id'],
                'wall',
                $battleResult['wall']['end']
            );
        }
        
        // Update target building if damaged
        if (!empty($attack['target_building']) && 
            $battleResult['building']['end'] < $battleResult['building']['start']) {
            $this->buildingManager->setBuildingLevel(
                $attack['target_village_id'],
                $attack['target_building'],
                $battleResult['building']['end']
            );
        }
        
        // Update defender units
        $this->updateVillageUnits(
            $attack['target_village_id'],
            $battleResult['defender']['survivors']
        );
        
        // Calculate loot if attacker won
        $loot = ['wood' => 0, 'clay' => 0, 'iron' => 0];
        if ($battleResult['outcome'] === 'attacker_win') {
            $loot = $this->calculateLoot(
                $attack['target_village_id'],
                $battleResult['attacker']['survivors'],
                $attack['attack_type']
            );
            
            // Apply loot
            $this->applyLoot(
                $attack['source_village_id'],
                $attack['target_village_id'],
                $loot
            );
        }
        
        // Create return march with survivors
        $this->createReturnMarch(
            $attack_id,
            $attack['source_village_id'],
            $attack['target_village_id'],
            $battleResult['attacker']['survivors']
        );
        
        // Create battle report
        $this->createBattleReport(
            $attack_id,
            $attack,
            $battleResult,
            $loot
        );
        
        // Mark attack as completed
        $stmt = $this->conn->prepare("
            UPDATE attacks SET is_completed = 1 WHERE id = ?
        ");
        $stmt->bind_param("i", $attack_id);
        $stmt->execute();
        $stmt->close();
        
        $this->conn->commit();
        
        return [
            'success' => true,
            'battle_result' => $battleResult,
            'loot' => $loot
        ];
        
    } catch (Exception $e) {
        $this->conn->rollback();
        error_log("Battle processing error: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'Failed to process battle: ' . $e->getMessage()
        ];
    }
}

/**
 * Update village units after battle
 */
private function updateVillageUnits(int $village_id, array $survivors): void
{
    foreach ($survivors as $internal_name => $count) {
        $stmt = $this->conn->prepare("
            UPDATE village_units vu
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            SET vu.count = ?
            WHERE vu.village_id = ? AND ut.internal_name = ?
        ");
        $stmt->bind_param("iis", $count, $village_id, $internal_name);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Create battle report
 */
private function createBattleReport(
    int $attack_id,
    array $attack,
    array $battleResult,
    array $loot
): void {
    $reportData = json_encode([
        'attacker_won' => $battleResult['outcome'] === 'attacker_win',
        'luck' => $battleResult['luck'],
        'morale' => $battleResult['morale'],
        'ratio' => $battleResult['ratio'],
        'attacker' => $battleResult['attacker'],
        'defender' => $battleResult['defender'],
        'wall' => $battleResult['wall'],
        'building' => $battleResult['building'],
        'loot' => $loot
    ]);
    
    // Insert into battle_reports table
    $stmt = $this->conn->prepare("
        INSERT INTO battle_reports (
            attack_id, 
            attacker_user_id, 
            defender_user_id,
            report_data,
            created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
        "iiis",
        $attack_id,
        $attack['attacker_user_id'],
        $attack['defender_user_id'],
        $reportData
    );
    $stmt->execute();
    $stmt->close();
}
```

## Step 4: Test the Integration

Create a test script to verify the integration:

```php
<?php
require_once 'init.php';
require_once 'lib/managers/BattleManager.php';
require_once 'lib/managers/VillageManager.php';
require_once 'lib/managers/BuildingManager.php';
require_once 'lib/managers/BuildingConfigManager.php';

$conn = $GLOBALS['conn'];

$villageManager = new VillageManager($conn);
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$battleManager = new BattleManager($conn, $villageManager, $buildingManager);

// Process a specific attack
$attack_id = 1; // Replace with actual attack ID
$result = $battleManager->processBattle($attack_id);

if ($result['success']) {
    echo "Battle processed successfully!\n";
    echo "Outcome: " . $result['battle_result']['outcome'] . "\n";
    echo "Loot: Wood: {$result['loot']['wood']}, Clay: {$result['loot']['clay']}, Iron: {$result['loot']['iron']}\n";
} else {
    echo "Error: " . $result['error'] . "\n";
}
```

## Additional Considerations

### 1. Support Troops

To include support troops in defense:

```php
private function getDefenderUnits(int $village_id): array
{
    // Get village's own units
    $units = $this->getVillageOwnUnits($village_id);
    
    // Get support troops
    $stmt = $this->conn->prepare("
        SELECT ut.internal_name, SUM(au.count) as total
        FROM attacks a
        JOIN attack_units au ON a.id = au.attack_id
        JOIN unit_types ut ON au.unit_type_id = ut.id
        WHERE a.target_village_id = ? 
          AND a.attack_type = 'support'
          AND a.is_completed = 1
          AND a.is_canceled = 0
        GROUP BY ut.internal_name
    ");
    $stmt->bind_param("i", $village_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $units[$row['internal_name']] = 
            ($units[$row['internal_name']] ?? 0) + (int)$row['total'];
    }
    $stmt->close();
    
    return $units;
}
```

### 2. Research Bonuses

Add smithy research bonuses:

```php
// In BattleEngine, modify calculateTotalOffense and calculateEffectiveDefense
// to accept research levels and apply bonuses
```

### 3. Loyalty System

For noble attacks, add loyalty reduction:

```php
if (isset($attackerUnits['noble']) && $attackerUnits['noble'] > 0) {
    $loyaltyDrop = rand(20, 35);
    $this->reduceLoyalty($attack['target_village_id'], $loyaltyDrop);
}
```

## Performance Tips

1. Cache world config to avoid repeated queries
2. Batch unit updates when possible
3. Use prepared statements for all database operations
4. Consider queueing battle processing for high-traffic scenarios

## Troubleshooting

**Issue**: Units not updating correctly
- Check that internal_name matches between unit_types and units.json
- Verify foreign key relationships

**Issue**: Incorrect battle outcomes
- Run the test suite to verify engine calculations
- Check that unit data is loaded correctly
- Verify morale and luck calculations

**Issue**: Performance problems
- Add indexes on attack_id, village_id columns
- Consider caching unit data
- Profile database queries
