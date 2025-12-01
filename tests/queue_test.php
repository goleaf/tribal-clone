<?php
declare(strict_types=1);

// Focused, dependency-light sanity tests for build queues and resources.
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
// Avoid touching deprecated session INI to silence startup/shutdown notices.
ini_set('session.use_cookies', '0');
ini_set('session.use_trans_sid', '0');

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/managers/BuildingConfigManager.php';
require_once __DIR__ . '/../lib/managers/BuildingManager.php';
require_once __DIR__ . '/../lib/managers/ResourceManager.php';

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

function createSchema(SQLiteAdapter $conn): array
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
            user_id INTEGER NOT NULL DEFAULT 1,
            name TEXT NOT NULL,
            wood INTEGER DEFAULT 0,
            clay INTEGER DEFAULT 0,
            iron INTEGER DEFAULT 0,
            warehouse_capacity INTEGER DEFAULT 1000,
            population INTEGER DEFAULT 100,
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
        CREATE TABLE IF NOT EXISTS building_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            village_id INTEGER NOT NULL,
            village_building_id INTEGER NOT NULL,
            building_type_id INTEGER NOT NULL,
            level INTEGER NOT NULL,
            starts_at TEXT,
            finish_time TEXT
        );
    ");

    return [
        'building_types' => true,
        'building_requirements' => true,
        'villages' => true,
        'village_buildings' => true,
        'building_queue' => true,
    ];
}

function seedBuildingTypes(SQLiteAdapter $conn): array
{
    $conn->query("
        INSERT INTO building_types (id, internal_name, name, description, max_level, base_build_time_initial, build_time_factor, cost_wood_initial, cost_clay_initial, cost_iron_initial, cost_factor, production_type, production_initial, production_factor, bonus_time_reduction_factor, population_cost) VALUES
        (1, 'main_building', 'Town Hall', 'The heart of your village.', 20, 900, 1.2, 90, 80, 70, 1.25, NULL, NULL, NULL, 0.95, 0),
        (2, 'barracks', 'Barracks', 'Train infantry units.', 25, 1200, 1.22, 200, 170, 90, 1.26, NULL, NULL, NULL, 1.0, 0),
        (3, 'sawmill', 'Timber Camp', 'Produces wood.', 30, 600, 1.18, 50, 60, 40, 1.26, 'wood', 30, 1.16, 1.0, 0),
        (4, 'clay_pit', 'Clay Pit', 'Produces clay.', 30, 600, 1.18, 65, 50, 40, 1.26, 'clay', 30, 1.16, 1.0, 0),
        (5, 'iron_mine', 'Iron Mine', 'Produces iron.', 30, 720, 1.18, 75, 65, 60, 1.26, 'iron', 30, 1.16, 1.0, 0)
    ");

    $conn->query("INSERT INTO building_requirements (building_type_id, required_building, required_level) VALUES (2, 'main_building', 3)");

    return [
        'main_building' => 1,
        'barracks' => 2,
        'sawmill' => 3,
        'clay_pit' => 4,
        'iron_mine' => 5
    ];
}

function seedVillage(SQLiteAdapter $conn, array $buildingTypeMap, int $villageId, array $resources, array $buildingLevels): array
{
    $wood = $resources['wood'] ?? 0;
    $clay = $resources['clay'] ?? 0;
    $iron = $resources['iron'] ?? 0;
    $conn->query(
        "INSERT INTO villages (id, name, user_id, wood, clay, iron, warehouse_capacity, population, farm_capacity, last_resource_update) VALUES " .
        "({$villageId}, 'Village {$villageId}', 1, {$wood}, {$clay}, {$iron}, 50000, 100, 100, '2024-01-01 00:00:00')"
    );

    $buildingIds = [];
    $nextId = 1;
    foreach ($buildingLevels as $internalName => $level) {
        if (!isset($buildingTypeMap[$internalName])) {
            throw new RuntimeException("Unknown building internal name '{$internalName}'");
        }

        $villageBuildingId = $nextId++;
        $buildingTypeId = $buildingTypeMap[$internalName];
        $conn->query("
            INSERT INTO village_buildings (id, village_id, building_type_id, level) 
            VALUES ({$villageBuildingId}, {$villageId}, {$buildingTypeId}, {$level})
        ");
        $buildingIds[$internalName] = $villageBuildingId;
    }

    return $buildingIds;
}

function seedBuildingQueue(SQLiteAdapter $conn, int $villageId, int $villageBuildingId, int $buildingTypeId, int $level, ?string $startTime, string $finishTime): void
{
    $startValue = $startTime ? "'{$startTime}'" : 'NULL';
    $conn->query("
        INSERT INTO building_queue (village_id, village_building_id, building_type_id, level, starts_at, finish_time)
        VALUES ({$villageId}, {$villageBuildingId}, {$buildingTypeId}, {$level}, {$startValue}, '{$finishTime}')
    ");
}

$runner = new TinyTestRunner();

$runner->add('Blocking upgrade when queue exists', function () {
    $conn = new SQLiteAdapter(':memory:');
    createSchema($conn);
    $buildingTypeMap = seedBuildingTypes($conn);
    $configManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $configManager);

    $buildingIds = seedVillage(
        $conn,
        $buildingTypeMap,
        1,
        ['wood' => 50000, 'clay' => 50000, 'iron' => 50000],
        ['main_building' => 3, 'sawmill' => 1]
    );

    seedBuildingQueue($conn, 1, $buildingIds['sawmill'], $buildingTypeMap['sawmill'], 2, '2024-01-01 00:00:00', '2024-01-01 01:00:00');

    $result = $buildingManager->canUpgradeBuilding(1, 'main_building');
    assertFalse($result['success'], 'Upgrade should be blocked while queue is occupied');
    assertEquals('Another upgrade is already in progress in this village.', $result['message'], 'Should surface queue-in-progress message');
});

$runner->add('Building view exposes queue timestamps', function () {
    $conn = new SQLiteAdapter(':memory:');
    createSchema($conn);
    $buildingTypeMap = seedBuildingTypes($conn);
    $configManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $configManager);

    $buildingIds = seedVillage(
        $conn,
        $buildingTypeMap,
        1,
        ['wood' => 50000, 'clay' => 50000, 'iron' => 50000],
        ['main_building' => 3, 'sawmill' => 1]
    );

    $start = '2024-01-01 00:00:00';
    $finish = '2024-01-01 01:00:00';
    seedBuildingQueue($conn, 1, $buildingIds['sawmill'], $buildingTypeMap['sawmill'], 2, $start, $finish);

    $view = $buildingManager->getVillageBuildingsViewData(1, 3);
    $sawmill = $view['sawmill'];

    assertEquals(strtotime($start), $sawmill['queue_start_time'], 'Should expose queue start timestamp');
    assertEquals(strtotime($finish), $sawmill['queue_finish_time'], 'Should expose queue finish timestamp');
});

$runner->add('Building view estimates start time when missing', function () {
    $conn = new SQLiteAdapter(':memory:');
    createSchema($conn);
    $buildingTypeMap = seedBuildingTypes($conn);
    $configManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $configManager);

    $buildingIds = seedVillage(
        $conn,
        $buildingTypeMap,
        1,
        ['wood' => 50000, 'clay' => 50000, 'iron' => 50000],
        ['main_building' => 3, 'sawmill' => 1]
    );

    $finish = '2024-01-01 01:00:00';
    // Intentionally omit start time to force fallback
    seedBuildingQueue($conn, 1, $buildingIds['sawmill'], $buildingTypeMap['sawmill'], 2, null, $finish);

    $view = $buildingManager->getVillageBuildingsViewData(1, 3);
    $sawmill = $view['sawmill'];

    $expectedDuration = $configManager->calculateUpgradeTime('sawmill', 1, 3);
    $expectedStart = strtotime($finish) - $expectedDuration;

    assertTrue($sawmill['queue_start_time'] !== null, 'Start time should be inferred');
    assertEquals($expectedStart, $sawmill['queue_start_time'], 'Start time should be derived from finish minus duration');
    assertEquals($expectedDuration, $sawmill['upgrade_time_seconds'], 'Duration should be present on view data');
});

$runner->add('Blocking upgrade at max level', function () {
    $conn = new SQLiteAdapter(':memory:');
    createSchema($conn);
    $buildingTypeMap = seedBuildingTypes($conn);
    $configManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $configManager);

    seedVillage(
        $conn,
        $buildingTypeMap,
        1,
        ['wood' => 999999, 'clay' => 999999, 'iron' => 999999],
        ['main_building' => 20]
    );

    $result = $buildingManager->canUpgradeBuilding(1, 'main_building');
    assertFalse($result['success'], 'Upgrade should be blocked at max level');
    assertEquals('Maximum level reached for this building.', $result['message'], 'Should report max-level condition');
});

$runner->add('ResourceManager caps production at warehouse capacity', function () {
    $conn = new SQLiteAdapter(':memory:');
    createSchema($conn);
    $buildingTypeMap = seedBuildingTypes($conn);
    $configManager = new BuildingConfigManager($conn);
    $buildingManager = new BuildingManager($conn, $configManager);
    $resourceManager = new ResourceManager($conn, $buildingManager);

    // Village with low warehouse capacity to force capping
    $conn->query("
        INSERT INTO villages (id, name, user_id, wood, clay, iron, warehouse_capacity, population, farm_capacity, last_resource_update)
        VALUES (1, 'CapTest', 1, 0, 0, 0, 10, 100, 100, '" . date('Y-m-d H:i:s', time() - 3600) . "')
    ");

    // Production buildings at level 1 (30/hour each per seed config)
    $conn->query("INSERT INTO village_buildings (id, village_id, building_type_id, level) VALUES (1, 1, {$buildingTypeMap['sawmill']}, 1)");
    $conn->query("INSERT INTO village_buildings (id, village_id, building_type_id, level) VALUES (2, 1, {$buildingTypeMap['clay_pit']}, 1)");
    $conn->query("INSERT INTO village_buildings (id, village_id, building_type_id, level) VALUES (3, 1, {$buildingTypeMap['iron_mine']}, 1)");

    $warehouseLevel = $buildingManager->getBuildingLevel(1, 'warehouse');
    $capacity = $buildingManager->getWarehouseCapacityByLevel($warehouseLevel);

    $village = $conn->query("SELECT * FROM villages WHERE id = 1")->fetch_assoc();
    $updated = $resourceManager->updateVillageResources($village);

    assertTrue($updated['wood'] <= $capacity, 'Wood should not exceed warehouse (got ' . $updated['wood'] . ')');
    assertTrue($updated['clay'] <= $capacity, 'Clay should not exceed warehouse (got ' . $updated['clay'] . ')');
    assertTrue($updated['iron'] <= $capacity, 'Iron should not exceed warehouse (got ' . $updated['iron'] . ')');
});

$runner->run();
