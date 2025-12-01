<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
ini_set('session.use_cookies', '0');
ini_set('session.use_trans_sid', '0');

if (!defined('CURRENT_WORLD_ID')) {
    define('CURRENT_WORLD_ID', 1);
}
// Broaden ratio guardrails so fair-band checks fire in tests.
if (!defined('TRADE_MIN_RATIO')) {
    define('TRADE_MIN_RATIO', 0.1);
}
if (!defined('TRADE_MAX_RATIO')) {
    define('TRADE_MAX_RATIO', 10.0);
}
if (!defined('TRADE_ACTIVE_ROUTE_SOFT_LIMIT')) {
    define('TRADE_ACTIVE_ROUTE_SOFT_LIMIT', 1);
}
if (!defined('TRADE_OPEN_OFFERS_SOFT_LIMIT')) {
    define('TRADE_OPEN_OFFERS_SOFT_LIMIT', 1);
}
if (!defined('TRADE_LOADCHECK_TTL_SEC')) {
    define('TRADE_LOADCHECK_TTL_SEC', 0);
}

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/ResourceManager.php';
require_once __DIR__ . '/../lib/managers/TradeManager.php';
require_once __DIR__ . '/../lib/managers/BattleManager.php';
require_once __DIR__ . '/../lib/utils/EconomyError.php';

class TinyTestRunner
{
    private array $results = [];

    public function add(string $name, callable $fn): void
    {
        try {
            $fn();
            $this->results[] = ['name' => $name, 'status' => 'passed'];
        } catch (Throwable $e) {
            $this->results[] = ['name' => $name, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    public function run(): void
    {
        $fails = 0;
        foreach ($this->results as $result) {
            if ($result['status'] === 'passed') {
                echo "[PASS] {$result['name']}\n";
            } else {
                $fails++;
                echo "[FAIL] {$result['name']}: {$result['message']}\n";
            }
        }

        echo "----\n";
        echo (count($this->results) - $fails) . " passed, {$fails} failed\n";
        if ($fails > 0) {
            exit(1);
        }
    }
}

function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $prefix = $message ? "{$message} - " : '';
        throw new RuntimeException($prefix . "Expected '" . var_export($expected, true) . "' but got '" . var_export($actual, true) . "'");
    }
}

function assertTrue(bool $condition, string $message = ''): void
{
    if ($condition !== true) {
        throw new RuntimeException($message ?: 'Expected condition to be true');
    }
}

function assertFalse(bool $condition, string $message = ''): void
{
    if ($condition !== false) {
        throw new RuntimeException($message ?: 'Expected condition to be false');
    }
}

function createEconomySchema(SQLiteAdapter $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS building_types (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            internal_name TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            description TEXT,
            max_level INTEGER DEFAULT 20,
            base_build_time_initial INTEGER DEFAULT 900,
            build_time_factor REAL DEFAULT 1.2,
            cost_wood_initial INTEGER DEFAULT 100,
            cost_clay_initial INTEGER DEFAULT 100,
            cost_iron_initial INTEGER DEFAULT 100,
            cost_factor REAL DEFAULT 1.25,
            production_type TEXT NULL,
            production_initial INTEGER NULL,
            production_factor REAL NULL,
            bonus_time_reduction_factor REAL DEFAULT 1.0,
            population_cost INTEGER DEFAULT 0
        );
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS building_requirements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            building_type_id INTEGER NOT NULL,
            required_building TEXT NOT NULL,
            required_level INTEGER NOT NULL
        );
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS villages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            world_id INTEGER DEFAULT 1,
            x_coord INTEGER DEFAULT 500,
            y_coord INTEGER DEFAULT 500,
            wood INTEGER DEFAULT 0,
            clay INTEGER DEFAULT 0,
            iron INTEGER DEFAULT 0,
            warehouse_capacity INTEGER DEFAULT 1000,
            population INTEGER DEFAULT 0,
            farm_capacity INTEGER DEFAULT 0,
            last_resource_update TEXT DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS village_buildings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            village_id INTEGER NOT NULL,
            building_type_id INTEGER NOT NULL,
            level INTEGER DEFAULT 0
        );
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            points INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            is_protected INTEGER DEFAULT 0
        );
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS worlds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            world_speed REAL DEFAULT 1.0,
            troop_speed REAL DEFAULT 1.0,
            build_speed REAL DEFAULT 1.0,
            train_speed REAL DEFAULT 1.0,
            research_speed REAL DEFAULT 1.0,
            resource_production_multiplier REAL DEFAULT 1.0,
            resource_multiplier REAL DEFAULT 1.0,
            vault_protection_percent REAL DEFAULT 0.0,
            resource_decay_enabled INTEGER DEFAULT 0,
            resource_decay_threshold_pct REAL DEFAULT 0.8,
            resource_decay_rate_per_hour REAL DEFAULT 0.01
        );
    ");
}

function seedBuildingTypesForEconomy(SQLiteAdapter $conn): array
{
    $conn->query("
        INSERT INTO building_types (id, internal_name, name, description, max_level, base_build_time_initial, build_time_factor, cost_wood_initial, cost_clay_initial, cost_iron_initial, cost_factor, production_type, production_initial, production_factor, bonus_time_reduction_factor, population_cost) VALUES
        (1, 'main_building', 'Town Hall', 'The heart of your village.', 20, 900, 1.2, 90, 80, 70, 1.25, NULL, NULL, NULL, 0.95, 0),
        (2, 'barracks', 'Barracks', 'Train infantry units.', 25, 1200, 1.22, 200, 170, 90, 1.26, NULL, NULL, NULL, 1.0, 0),
        (3, 'sawmill', 'Timber Camp', 'Produces wood.', 30, 600, 1.18, 50, 60, 40, 1.26, 'wood', 30, 1.16, 1.0, 0),
        (4, 'clay_pit', 'Clay Pit', 'Produces clay.', 30, 600, 1.18, 65, 50, 40, 1.26, 'clay', 30, 1.16, 1.0, 0),
        (5, 'iron_mine', 'Iron Mine', 'Produces iron.', 30, 720, 1.18, 75, 65, 60, 1.26, 'iron', 30, 1.16, 1.0, 0),
        (6, 'warehouse', 'Warehouse', 'Stores resources.', 20, 900, 1.2, 80, 80, 80, 1.22, NULL, NULL, NULL, 1.0, 0),
        (7, 'market', 'Market', 'Trade resources.', 20, 900, 1.2, 80, 70, 70, 1.25, NULL, NULL, NULL, 1.0, 0)
    ");

    return [
        'main_building' => 1,
        'barracks' => 2,
        'sawmill' => 3,
        'clay_pit' => 4,
        'iron_mine' => 5,
        'warehouse' => 6,
        'market' => 7
    ];
}

function seedVillageWithWorld(
    SQLiteAdapter $conn,
    int $villageId,
    int $userId,
    array $buildingTypeMap,
    array $resources,
    array $buildingLevels,
    int $worldId = 1,
    string $lastUpdateOffset = '-1 hour'
): void {
    $wood = (int)($resources['wood'] ?? 0);
    $clay = (int)($resources['clay'] ?? 0);
    $iron = (int)($resources['iron'] ?? 0);
    $lastUpdate = date('Y-m-d H:i:s', strtotime($lastUpdateOffset));

    $conn->query(
        "INSERT INTO villages (id, name, user_id, world_id, x_coord, y_coord, wood, clay, iron, warehouse_capacity, population, farm_capacity, last_resource_update) VALUES " .
        "({$villageId}, 'Village {$villageId}', {$userId}, {$worldId}, 500, 500, {$wood}, {$clay}, {$iron}, 1000, 100, 100, '{$lastUpdate}')"
    );

    foreach ($buildingLevels as $internalName => $level) {
        $level = (int)$level;
        if ($level <= 0) {
            continue;
        }
        if (!isset($buildingTypeMap[$internalName])) {
            throw new RuntimeException("Unknown building internal name '{$internalName}'");
        }
        $typeId = $buildingTypeMap[$internalName];
        $conn->query("
            INSERT INTO village_buildings (village_id, building_type_id, level) 
            VALUES ({$villageId}, {$typeId}, {$level})
        ");
    }
}

$runner = new TinyTestRunner();

$runner->add('Resource decay trims over-threshold stock when enabled', function () {
    $conn = new SQLiteAdapter(':memory:');
    createEconomySchema($conn);
    $buildingTypeMap = seedBuildingTypesForEconomy($conn);

    // Enable decay and lower threshold to keep math simple
    $conn->query("INSERT INTO worlds (id, name, resource_production_multiplier, resource_multiplier, vault_protection_percent, resource_decay_enabled, resource_decay_threshold_pct, resource_decay_rate_per_hour) VALUES (1, 'DecayWorld', 1.0, 1.0, 0, 1, 0.8, 0.5)");
    $conn->query("INSERT INTO users (id, username, points, created_at, is_protected) VALUES (1, 'decay_user', 0, '" . date('Y-m-d H:i:s', strtotime('-1 day')) . "', 0)");
    seedVillageWithWorld($conn, 1, 1, $buildingTypeMap, ['wood' => 900, 'clay' => 900, 'iron' => 900], [], 1, '-1 hour');

    $buildingConfigManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $buildingConfigManager);
    $resourceManager = new ResourceManager($conn, $buildingManager);

    $village = $conn->query("SELECT * FROM villages WHERE id = 1")->fetch_assoc();
    $updated = $resourceManager->updateVillageResources($village);

    // Threshold 80% of 1000 = 800; overage 100; 50% decay => 50 trimmed.
    assertEquals(850, (int)round($updated['wood']), 'Decay should trim wood over threshold');
    assertEquals(850, (int)round($updated['clay']), 'Decay should trim clay over threshold');
    assertEquals(850, (int)round($updated['iron']), 'Decay should trim iron over threshold');
});

$runner->add('TradeManager rejects offers outside fair ratio band', function () {
    $conn = new SQLiteAdapter(':memory:');
    createEconomySchema($conn);
    $buildingTypeMap = seedBuildingTypesForEconomy($conn);

    $conn->query("INSERT INTO users (id, username, points) VALUES (1, 'trader', 100)");
    $conn->query("INSERT INTO worlds (id, name) VALUES (1, 'TradeWorld')");
    seedVillageWithWorld($conn, 1, 1, $buildingTypeMap, ['wood' => 10000, 'clay' => 10000, 'iron' => 10000], ['market' => 5], 1, '-10 minutes');

    $tm = new TradeManager($conn);
    $result = $tm->createOffer(1, 1, ['wood' => 5000], ['iron' => 500]);

    assertFalse($result['success'], 'Unfair ratio should be rejected');
    assertEquals(EconomyError::ERR_RATIO, $result['code'] ?? null, 'Should surface ERR_RATIO for unfair band');
    assertEquals(0.25, $result['details']['min_ratio'] ?? null, 'Should include fair band lower bound');
    assertEquals(4.0, $result['details']['max_ratio'] ?? null, 'Should include fair band upper bound');
});

$runner->add('Open offers reserve merchants for new trades', function () {
    $conn = new SQLiteAdapter(':memory:');
    createEconomySchema($conn);
    $buildingTypeMap = seedBuildingTypesForEconomy($conn);

    $conn->query("INSERT INTO users (id, username, points) VALUES (1, 'merchant', 200)");
    $conn->query("INSERT INTO worlds (id, name) VALUES (1, 'TradeWorld')");
    // Market level 2 => two merchants available
    seedVillageWithWorld($conn, 1, 1, $buildingTypeMap, ['wood' => 10000, 'clay' => 10000, 'iron' => 10000], ['market' => 2], 1, '-5 minutes');

    $tm = new TradeManager($conn);

    $first = $tm->createOffer(1, 1, ['wood' => 1500], ['clay' => 1000]);
    assertTrue($first['success'] ?? false, 'First offer should post while merchants available');

    $second = $tm->createOffer(1, 1, ['wood' => 500], ['clay' => 500]);
    assertFalse($second['success'] ?? true, 'Second offer should fail when merchants are reserved');
    assertEquals(EconomyError::ERR_CAP, $second['code'] ?? null, 'Should return ERR_CAP when merchants are exhausted');
});

$runner->add('Vault protection chooses stronger of vault vs hiding place', function () {
    $resources = ['wood' => 1000, 'clay' => 1000, 'iron' => 1000];

    // Vault 10% protects 100 each; hidden 50 — vault wins.
    $loot = BattleManager::computeLootableResources($resources, 50, 10.0);
    assertEquals(['wood' => 100, 'clay' => 100, 'iron' => 100], $loot['protected'], 'Vault should protect 10% of each resource');
    assertEquals(['wood' => 900, 'clay' => 900, 'iron' => 900], $loot['available'], 'Available should subtract vault protection when larger than hiding place');

    // Hidden larger than vault-protected should be used instead.
    $lootHidden = BattleManager::computeLootableResources($resources, 200, 10.0);
    assertEquals(['wood' => 100, 'clay' => 100, 'iron' => 100], $lootHidden['protected'], 'Vault protection value unchanged');
    assertEquals(['wood' => 800, 'clay' => 800, 'iron' => 800], $lootHidden['available'], 'Available should subtract hiding place when larger than vault');
});

$runner->add('TradeManager blocks power-delta pushes to protected/low-point targets', function () {
    $conn = new SQLiteAdapter(':memory:');
    createEconomySchema($conn);
    $buildingTypeMap = seedBuildingTypesForEconomy($conn);

    // Sender is strong; target is low-point and protected.
    $conn->query("INSERT INTO users (id, username, points, created_at, is_protected) VALUES (1, 'strong', 10000, '" . date('Y-m-d H:i:s', strtotime('-10 days')) . "', 0)");
    $conn->query("INSERT INTO users (id, username, points, created_at, is_protected) VALUES (2, 'rookie', 100, '" . date('Y-m-d H:i:s', strtotime('-1 day')) . "', 1)");
    $conn->query("INSERT INTO worlds (id, name) VALUES (1, 'TradeWorld')");
    seedVillageWithWorld($conn, 1, 1, $buildingTypeMap, ['wood' => 5000, 'clay' => 5000, 'iron' => 5000], ['market' => 3], 1, '-15 minutes');
    seedVillageWithWorld($conn, 2, 2, $buildingTypeMap, ['wood' => 500, 'clay' => 500, 'iron' => 500], ['market' => 1], 1, '-5 minutes');

    $tm = new TradeManager($conn);
    $result = $tm->sendResources(1, 1, '500|505', ['wood' => 300]);

    assertFalse($result['success'] ?? true, 'Power-delta push to protected target should be blocked');
    assertEquals(EconomyError::ERR_ALT_BLOCK, $result['code'] ?? null, 'Block should surface ERR_ALT_BLOCK');
    assertEquals('power_delta', $result['details']['reason'] ?? null, 'Block reason should note power_delta');
});

$runner->add('TradeManager load shedding blocks new sends when routes/offers exceed soft limit', function () {
    $conn = new SQLiteAdapter(':memory:');
    createEconomySchema($conn);
    $buildingTypeMap = seedBuildingTypesForEconomy($conn);

    $conn->query("INSERT INTO users (id, username, points) VALUES (1, 'sender', 2000)");
    $conn->query("INSERT INTO users (id, username, points) VALUES (2, 'receiver', 1500)");
    $conn->query("INSERT INTO worlds (id, name) VALUES (1, 'TradeWorld')");
    seedVillageWithWorld($conn, 1, 1, $buildingTypeMap, ['wood' => 5000, 'clay' => 5000, 'iron' => 5000], ['market' => 3], 1, '-10 minutes');
    seedVillageWithWorld($conn, 2, 2, $buildingTypeMap, ['wood' => 500, 'clay' => 500, 'iron' => 500], ['market' => 1], 1, '-5 minutes');

    $tm = new TradeManager($conn);
    // Insert two active routes to exceed the soft limit of 1.
    $future = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $conn->query("INSERT INTO trade_routes (source_village_id, target_village_id, wood, clay, iron, traders_count, arrival_time) VALUES (1, 2, 100, 0, 0, 1, '{$future}')");
    $conn->query("INSERT INTO trade_routes (source_village_id, target_village_id, wood, clay, iron, traders_count, arrival_time) VALUES (1, 2, 200, 0, 0, 1, '{$future}')");

    $result = $tm->sendResources(1, 1, '500|505', ['wood' => 100]);
    assertFalse($result['success'] ?? true, 'Overloaded trade system should reject send');
    assertEquals(EconomyError::ERR_RATE_LIMIT, $result['code'] ?? null, 'Should return ERR_RATE_LIMIT when overloaded');
    assertTrue(isset($result['details']['retry_after_sec']), 'Should include retry_after_sec hint');
});

$runner->run();
