<?php
declare(strict_types=1);

require_once __DIR__ . '/NotificationManager.php';

class AchievementManager
{
    private $conn;
    private NotificationManager $notificationManager;
    private bool $schemaChecked = false;
    private ?array $achievementCache = null;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->notificationManager = new NotificationManager($conn);
        $this->ensureSchema();
    }

    /**
     * Creates required tables (if missing) and seeds default achievements.
     */
    private function ensureSchema(): void
    {
        if ($this->schemaChecked) {
            return;
        }
        $this->schemaChecked = true;

        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';

        if ($driver === 'sqlite') {
            $this->conn->query("
                CREATE TABLE IF NOT EXISTS achievements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    internal_name TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    description TEXT NOT NULL,
                    category TEXT NOT NULL DEFAULT 'general',
                    condition_type TEXT NOT NULL,
                    condition_target TEXT NULL,
                    condition_value INTEGER NOT NULL,
                    reward_wood INTEGER NOT NULL DEFAULT 0,
                    reward_clay INTEGER NOT NULL DEFAULT 0,
                    reward_iron INTEGER NOT NULL DEFAULT 0,
                    reward_points INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $this->conn->query("
                CREATE TABLE IF NOT EXISTS user_achievements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    achievement_id INTEGER NOT NULL,
                    progress INTEGER NOT NULL DEFAULT 0,
                    unlocked INTEGER NOT NULL DEFAULT 0,
                    unlocked_at TEXT DEFAULT NULL,
                    reward_claimed INTEGER NOT NULL DEFAULT 0,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
                    UNIQUE (user_id, achievement_id)
                )
            ");
        } else {
            $this->conn->query("
                CREATE TABLE IF NOT EXISTS achievements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    internal_name VARCHAR(100) NOT NULL UNIQUE,
                    name VARCHAR(150) NOT NULL,
                    description TEXT NOT NULL,
                    category VARCHAR(100) NOT NULL DEFAULT 'general',
                    condition_type VARCHAR(100) NOT NULL,
                    condition_target VARCHAR(100) NULL,
                    condition_value INT NOT NULL,
                    reward_wood INT NOT NULL DEFAULT 0,
                    reward_clay INT NOT NULL DEFAULT 0,
                    reward_iron INT NOT NULL DEFAULT 0,
                    reward_points INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $this->conn->query("
                CREATE TABLE IF NOT EXISTS user_achievements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    achievement_id INT NOT NULL,
                    progress INT NOT NULL DEFAULT 0,
                    unlocked TINYINT(1) NOT NULL DEFAULT 0,
                    unlocked_at TIMESTAMP NULL DEFAULT NULL,
                    reward_claimed TINYINT(1) NOT NULL DEFAULT 0,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
                    UNIQUE KEY user_achievement_unique (user_id, achievement_id)
                )
            ");
        }

        $this->seedDefaults($driver);
    }

    /**
     * Inserts a baseline set of achievements. Uses INSERT IGNORE/OR IGNORE to stay idempotent.
     */
    private function seedDefaults(string $driver): void
    {
        $defaults = [
            [
                'internal_name' => 'first_steps',
                'name' => 'A New Beginning',
                'description' => 'Establish your first village and start constructing the Town Hall.',
                'category' => 'progression',
                'condition_type' => 'building_level',
                'condition_target' => 'main_building',
                'condition_value' => 1,
                'reward_wood' => 150,
                'reward_clay' => 150,
                'reward_iron' => 150,
                'reward_points' => 2,
            ],
            [
                'internal_name' => 'town_hall_lvl5',
                'name' => 'Organized Village',
                'description' => 'Upgrade the Town Hall to level 5.',
                'category' => 'progression',
                'condition_type' => 'building_level',
                'condition_target' => 'main_building',
                'condition_value' => 5,
                'reward_wood' => 350,
                'reward_clay' => 350,
                'reward_iron' => 350,
                'reward_points' => 4,
            ],
            [
                'internal_name' => 'fortified',
                'name' => 'Fortified',
                'description' => 'Build the Wall to level 3.',
                'category' => 'defense',
                'condition_type' => 'building_level',
                'condition_target' => 'wall',
                'condition_value' => 3,
                'reward_wood' => 250,
                'reward_clay' => 350,
                'reward_iron' => 200,
                'reward_points' => 3,
            ],
            [
                'internal_name' => 'resource_keeper',
                'name' => 'Well Stocked',
                'description' => 'Hold at least 5,000 of each resource in one village.',
                'category' => 'economy',
                'condition_type' => 'resource_stock',
                'condition_target' => 'balanced',
                'condition_value' => 5000,
                'reward_wood' => 500,
                'reward_clay' => 500,
                'reward_iron' => 500,
                'reward_points' => 2,
            ],
            [
                'internal_name' => 'recruiter_50',
                'name' => 'Drill Sergeant',
                'description' => 'Train a total of 50 units.',
                'category' => 'military',
                'condition_type' => 'units_trained',
                'condition_target' => 'any',
                'condition_value' => 50,
                'reward_wood' => 300,
                'reward_clay' => 300,
                'reward_iron' => 200,
                'reward_points' => 3,
            ],
            [
                'internal_name' => 'recruiter_200',
                'name' => 'Army Quartermaster',
                'description' => 'Train a total of 200 units.',
                'category' => 'military',
                'condition_type' => 'units_trained',
                'condition_target' => 'any',
                'condition_value' => 200,
                'reward_wood' => 600,
                'reward_clay' => 600,
                'reward_iron' => 400,
                'reward_points' => 5,
            ],
            [
                'internal_name' => 'first_conquest',
                'name' => 'First Blood',
                'description' => 'Conquer your first village.',
                'category' => 'conquest',
                'condition_type' => 'conquest',
                'condition_target' => 'any',
                'condition_value' => 1,
                'reward_points' => 10,
                'reward_wood' => 500,
                'reward_clay' => 500,
                'reward_iron' => 500,
            ],
            [
                'internal_name' => 'defeat_500',
                'name' => 'Skull Collector',
                'description' => 'Defeat 500 enemy troops.',
                'category' => 'combat',
                'condition_type' => 'enemies_defeated',
                'condition_target' => 'any',
                'condition_value' => 500,
                'reward_points' => 6,
                'reward_wood' => 400,
                'reward_clay' => 400,
                'reward_iron' => 300,
            ],
            [
                'internal_name' => 'points_10000',
                'name' => 'Growing Power',
                'description' => 'Reach 10,000 points.',
                'category' => 'progression',
                'condition_type' => 'points_total',
                'condition_target' => 'any',
                'condition_value' => 10000,
                'reward_points' => 10,
                'reward_wood' => 800,
                'reward_clay' => 800,
                'reward_iron' => 800,
            ],
            [
                'internal_name' => 'attack_victories_10',
                'name' => 'Offensive Mind',
                'description' => 'Win 10 attacks.',
                'category' => 'combat',
                'condition_type' => 'successful_attack',
                'condition_target' => 'any',
                'condition_value' => 10,
                'reward_points' => 5,
            ],
            [
                'internal_name' => 'defense_victories_10',
                'name' => 'Shield Wall',
                'description' => 'Win 10 defenses.',
                'category' => 'combat',
                'condition_type' => 'successful_defense',
                'condition_target' => 'any',
                'condition_value' => 10,
                'reward_points' => 5,
            ],
            [
                'internal_name' => 'all_buildings_max',
                'name' => 'Master Architect',
                'description' => 'Max out every building type in at least one village.',
                'category' => 'progression',
                'condition_type' => 'all_buildings_max',
                'condition_target' => 'any',
                'condition_value' => 1,
                'reward_points' => 12,
                'reward_wood' => 1000,
                'reward_clay' => 1000,
                'reward_iron' => 1000,
            ],
        ];

        $insertPrefix = $driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $sql = "
            $insertPrefix INTO achievements
            (internal_name, name, description, category, condition_type, condition_target, condition_value, reward_wood, reward_clay, reward_iron, reward_points)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log('AchievementManager::seedDefaults failed to prepare insert statement');
            return;
        }

        foreach ($defaults as $achievement) {
            $stmt->bind_param(
                "ssssssiiiii",
                $achievement['internal_name'],
                $achievement['name'],
                $achievement['description'],
                $achievement['category'],
                $achievement['condition_type'],
                $achievement['condition_target'],
                $achievement['condition_value'],
                $achievement['reward_wood'],
                $achievement['reward_clay'],
                $achievement['reward_iron'],
                $achievement['reward_points']
            );
            $stmt->execute();
        }

        $stmt->close();
    }

    /**
     * Fetches all achievements from cache or database.
     */
    private function getAllAchievements(): array
    {
        if ($this->achievementCache !== null) {
            return $this->achievementCache;
        }

        $achievements = [];
        $result = $this->conn->query("
            SELECT id, internal_name, name, description, category, condition_type, condition_target, condition_value,
                   reward_wood, reward_clay, reward_iron, reward_points
            FROM achievements
            ORDER BY category ASC, condition_value ASC
        ");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['condition_value'] = (int)$row['condition_value'];
                $row['reward_wood'] = (int)$row['reward_wood'];
                $row['reward_clay'] = (int)$row['reward_clay'];
                $row['reward_iron'] = (int)$row['reward_iron'];
                $row['reward_points'] = (int)$row['reward_points'];
                $achievements[$row['id']] = $row;
            }
        }

        $this->achievementCache = $achievements;
        return $achievements;
    }

    /**
     * Ensures the linking row exists for a user/achievement pair.
     */
    private function ensureUserAchievementRow(int $userId, int $achievementId): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, progress, unlocked, unlocked_at, reward_claimed
            FROM user_achievements
            WHERE user_id = ? AND achievement_id = ?
            LIMIT 1
        ");
        if ($stmt === false) {
            return [
                'id' => null,
                'progress' => 0,
                'unlocked' => 0,
                'unlocked_at' => null,
                'reward_claimed' => 0,
            ];
        }

        $stmt->bind_param("ii", $userId, $achievementId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row) {
            $row['progress'] = (int)$row['progress'];
            $row['unlocked'] = (int)$row['unlocked'];
            $row['reward_claimed'] = (int)$row['reward_claimed'];
            return $row;
        }

        $insert = $this->conn->prepare("
            INSERT INTO user_achievements (user_id, achievement_id, progress, unlocked, reward_claimed)
            VALUES (?, ?, 0, 0, 0)
        ");
        if ($insert) {
            $insert->bind_param("ii", $userId, $achievementId);
            $insert->execute();
            $insert->close();
        }

        return [
            'id' => $this->conn->insert_id ?: null,
            'progress' => 0,
            'unlocked' => 0,
            'unlocked_at' => null,
            'reward_claimed' => 0,
        ];
    }

    /**
     * Adds progress for achievements that track cumulative unit training.
     */
    public function addUnitsTrainedProgress(int $userId, int $unitsProduced): void
    {
        if ($unitsProduced <= 0) {
            return;
        }

        foreach ($this->getAllAchievements() as $achievement) {
            if ($achievement['condition_type'] !== 'units_trained') {
                continue;
            }

            $row = $this->ensureUserAchievementRow($userId, (int)$achievement['id']);
            $newProgress = $row['progress'] + $unitsProduced;
            $this->updateProgress($userId, (int)$achievement['id'], $newProgress);

            if (!$row['unlocked'] && $newProgress >= $achievement['condition_value']) {
                $this->unlockAchievement($userId, $achievement, null);
            }
        }
    }

    /**
     * Checks achievements tied to a specific building level.
     */
    public function checkBuildingLevel(int $userId, string $buildingInternalName, int $newLevel, ?int $villageId = null): void
    {
        foreach ($this->getAllAchievements() as $achievement) {
            if ($achievement['condition_type'] !== 'building_level') {
                continue;
            }
            if ($achievement['condition_target'] !== $buildingInternalName) {
                continue;
            }

            $row = $this->ensureUserAchievementRow($userId, (int)$achievement['id']);
            $this->updateProgress($userId, (int)$achievement['id'], max($row['progress'], $newLevel));

            if (!$row['unlocked'] && $newLevel >= $achievement['condition_value']) {
                $this->unlockAchievement($userId, $achievement, $villageId);
            }
        }
    }

    /**
     * Checks resource-based achievements using the provided village snapshot.
     */
    public function checkResourceStock(int $userId, array $village, ?int $villageId = null): void
    {
        if (empty($village)) {
            return;
        }

        $balancedStock = (int)min((int)$village['wood'], (int)$village['clay'], (int)$village['iron']);

        foreach ($this->getAllAchievements() as $achievement) {
            if ($achievement['condition_type'] !== 'resource_stock') {
                continue;
            }

            $row = $this->ensureUserAchievementRow($userId, (int)$achievement['id']);
            $this->updateProgress($userId, (int)$achievement['id'], max($row['progress'], $balancedStock));

            if (!$row['unlocked'] && $balancedStock >= $achievement['condition_value']) {
                $this->unlockAchievement($userId, $achievement, $villageId);
            }
        }
    }

    /**
     * Performs a snapshot-based evaluation so existing accounts can unlock overdue achievements.
     */
    public function evaluateAutoUnlocks(int $userId): void
    {
        $snapshot = $this->buildUserSnapshot($userId);
        $achievements = $this->getAllAchievements();

        foreach ($achievements as $achievement) {
            $row = $this->ensureUserAchievementRow($userId, (int)$achievement['id']);

            if ((int)$row['unlocked'] === 1) {
                continue;
            }

            $currentValue = $this->getCurrentValueForAchievement($achievement, $snapshot, $row);
            $this->updateProgress($userId, (int)$achievement['id'], $currentValue);

            if ($currentValue >= $achievement['condition_value']) {
                $this->unlockAchievement($userId, $achievement, null);
            }
        }
    }

    /**
     * Returns achievements enriched with user progress for UI.
     */
    public function getUserAchievementsWithProgress(int $userId): array
    {
        $snapshot = $this->buildUserSnapshot($userId);
        $achievements = $this->getAllAchievements();

        $result = [];
        foreach ($achievements as $achievement) {
            $row = $this->ensureUserAchievementRow($userId, (int)$achievement['id']);
            $currentValue = $this->getCurrentValueForAchievement($achievement, $snapshot, $row);

            $result[] = [
                'id' => (int)$achievement['id'],
                'internal_name' => $achievement['internal_name'],
                'name' => $achievement['name'],
                'description' => $achievement['description'],
                'category' => $achievement['category'],
                'condition_type' => $achievement['condition_type'],
                'condition_target' => $achievement['condition_target'],
                'condition_value' => (int)$achievement['condition_value'],
                'progress' => min($currentValue, (int)$achievement['condition_value']),
                'unlocked' => (int)$row['unlocked'] === 1,
                'unlocked_at' => $row['unlocked_at'],
                'reward_claimed' => (int)$row['reward_claimed'] === 1,
                'reward_wood' => (int)$achievement['reward_wood'],
                'reward_clay' => (int)$achievement['reward_clay'],
                'reward_iron' => (int)$achievement['reward_iron'],
                'reward_points' => (int)$achievement['reward_points'],
            ];
        }

        return $result;
    }

    /**
     * Calculates the current numeric progress value for an achievement.
     */
    private function getCurrentValueForAchievement(array $achievement, array $snapshot, array $row): int
    {
        return match ($achievement['condition_type']) {
            'building_level' => $snapshot['building_levels'][$achievement['condition_target']] ?? 0,
            'resource_stock' => $snapshot['best_balanced_stock'] ?? 0,
            'units_trained' => max((int)$row['progress'], (int)($snapshot['total_units'] ?? 0)),
            'points_total' => (int)($snapshot['total_points'] ?? 0),
            'enemies_defeated' => (int)($snapshot['enemies_defeated'] ?? 0),
            'successful_attack' => (int)($snapshot['successful_attacks'] ?? 0),
            'successful_defense' => (int)($snapshot['successful_defenses'] ?? 0),
            'conquest' => (int)($snapshot['conquests'] ?? 0),
            'all_buildings_max' => (int)($snapshot['maxed_buildings'] ?? 0),
            default => (int)$row['progress'],
        };
    }

    /**
     * Updates stored progress when it increases.
     */
    private function updateProgress(int $userId, int $achievementId, int $newValue): void
    {
        $stmt = $this->conn->prepare("
            UPDATE user_achievements
            SET progress = ?
            WHERE user_id = ? AND achievement_id = ? AND progress < ?
        ");

        if ($stmt) {
            $stmt->bind_param("iiii", $newValue, $userId, $achievementId, $newValue);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Unlocks an achievement, rewards the user, and notifies them.
     */
    private function unlockAchievement(int $userId, array $achievement, ?int $villageId = null): void
    {
        $row = $this->ensureUserAchievementRow($userId, (int)$achievement['id']);
        if ((int)$row['unlocked'] === 1) {
            return;
        }

        $unlockedAt = date('Y-m-d H:i:s');
        $progressValue = max((int)$row['progress'], (int)$achievement['condition_value']);

        $stmt = $this->conn->prepare("
            UPDATE user_achievements
            SET unlocked = 1, unlocked_at = ?, progress = ?
            WHERE user_id = ? AND achievement_id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("siii", $unlockedAt, $progressValue, $userId, $achievement['id']);
            $stmt->execute();
            $stmt->close();
        }

        $this->grantRewards($userId, $achievement, $villageId);

        $this->notificationManager->addNotification(
            $userId,
            'Achievement unlocked: ' . $achievement['name'],
            'success'
        );
    }

    /**
     * Grants configured rewards to the user (resources + points).
     */
    private function grantRewards(int $userId, array $achievement, ?int $villageId = null): void
    {
        $row = $this->ensureUserAchievementRow($userId, (int)$achievement['id']);
        if ((int)$row['reward_claimed'] === 1) {
            return;
        }

        // Add resources to the target village (or first available).
        if (($achievement['reward_wood'] ?? 0) > 0 || ($achievement['reward_clay'] ?? 0) > 0 || ($achievement['reward_iron'] ?? 0) > 0) {
            $targetVillageId = $villageId ?? $this->getAnyVillageId($userId);
            if ($targetVillageId !== null) {
                $villageStmt = $this->conn->prepare("SELECT wood, clay, iron, warehouse_capacity FROM villages WHERE id = ? LIMIT 1");
                if ($villageStmt) {
                    $villageStmt->bind_param("i", $targetVillageId);
                    $villageStmt->execute();
                    $village = $villageStmt->get_result()->fetch_assoc();
                    $villageStmt->close();

                    if ($village) {
                        $newWood = min($village['wood'] + (int)$achievement['reward_wood'], (int)$village['warehouse_capacity']);
                        $newClay = min($village['clay'] + (int)$achievement['reward_clay'], (int)$village['warehouse_capacity']);
                        $newIron = min($village['iron'] + (int)$achievement['reward_iron'], (int)$village['warehouse_capacity']);

                        $updateVillage = $this->conn->prepare("
                            UPDATE villages
                            SET wood = ?, clay = ?, iron = ?
                            WHERE id = ?
                        ");
                        if ($updateVillage) {
                            $updateVillage->bind_param("dddi", $newWood, $newClay, $newIron, $targetVillageId);
                            $updateVillage->execute();
                            $updateVillage->close();
                        }
                    }
                }
            }
        }

        // Add points directly to the user profile.
        if (($achievement['reward_points'] ?? 0) > 0) {
            $pointsStmt = $this->conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
            if ($pointsStmt) {
                $pointsStmt->bind_param("ii", $achievement['reward_points'], $userId);
                $pointsStmt->execute();
                $pointsStmt->close();
            }
        }

        $update = $this->conn->prepare("
            UPDATE user_achievements
            SET reward_claimed = 1
            WHERE user_id = ? AND achievement_id = ?
        ");
        if ($update) {
            $update->bind_param("ii", $userId, $achievement['id']);
            $update->execute();
            $update->close();
        }
    }

    /**
     * Builds a lightweight snapshot of the user's current state for progress calculations.
     */
    private function buildUserSnapshot(int $userId): array
    {
        $buildingLevels = [];
        $stmt = $this->conn->prepare("
            SELECT bt.internal_name, MAX(vb.level) as level
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            JOIN villages v ON vb.village_id = v.id
            WHERE v.user_id = ?
            GROUP BY bt.internal_name
        ");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $buildingLevels[$row['internal_name']] = (int)$row['level'];
            }
            $stmt->close();
        }

        $bestBalanced = 0;
        $resourcesStmt = $this->conn->prepare("
            SELECT wood, clay, iron
            FROM villages
            WHERE user_id = ?
        ");
        if ($resourcesStmt) {
            $resourcesStmt->bind_param("i", $userId);
            $resourcesStmt->execute();
            $result = $resourcesStmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $balanced = (int)min((int)$row['wood'], (int)$row['clay'], (int)$row['iron']);
                if ($balanced > $bestBalanced) {
                    $bestBalanced = $balanced;
                }
            }
            $resourcesStmt->close();
        }

        $totalUnits = 0;
        $unitsStmt = $this->conn->prepare("
            SELECT SUM(vu.count) as total_units
            FROM village_units vu
            JOIN villages v ON vu.village_id = v.id
            WHERE v.user_id = ?
        ");
        if ($unitsStmt) {
            $unitsStmt->bind_param("i", $userId);
            $unitsStmt->execute();
            $row = $unitsStmt->get_result()->fetch_assoc();
            if ($row && isset($row['total_units'])) {
                $totalUnits = (int)$row['total_units'];
            }
            $unitsStmt->close();
        }

        $totalPoints = 0;
        $pointsStmt = $this->conn->prepare("SELECT points FROM users WHERE id = ? LIMIT 1");
        if ($pointsStmt) {
            $pointsStmt->bind_param("i", $userId);
            $pointsStmt->execute();
            $pRow = $pointsStmt->get_result()->fetch_assoc();
            $pointsStmt->close();
            $totalPoints = $pRow ? (int)$pRow['points'] : 0;
        }

        $enemiesDefeated = $this->getOpponentsDefeated($userId);
        $successfulAttacks = $this->countSuccessfulAttacks($userId);
        $successfulDefenses = $this->countSuccessfulDefenses($userId);
        $conquests = $this->countConquests($userId);
        $maxedBuildings = $this->countMaxedBuildingTypes($userId);

        return [
            'building_levels' => $buildingLevels,
            'best_balanced_stock' => $bestBalanced,
            'total_units' => $totalUnits,
            'total_points' => $totalPoints,
            'enemies_defeated' => $enemiesDefeated,
            'successful_attacks' => $successfulAttacks,
            'successful_defenses' => $successfulDefenses,
            'conquests' => $conquests,
            'maxed_buildings' => $maxedBuildings,
        ];
    }

    /**
     * Returns the first village id for a user (if any).
     */
    private function getAnyVillageId(int $userId): ?int
    {
        $stmt = $this->conn->prepare("SELECT id FROM villages WHERE user_id = ? ORDER BY id ASC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($result) {
                return (int)$result['id'];
            }
        }

        return null;
    }

    private function getOpponentsDefeated(int $userId): int
    {
        if (!$this->tableExists('battle_reports') || !$this->tableExists('battle_report_units')) {
            return 0;
        }

        $sql = "
            SELECT SUM(kills) AS total_kills FROM (
                SELECT SUM(CASE WHEN bru.side = 'defender' THEN bru.lost_count ELSE 0 END) AS kills
                FROM battle_reports br
                JOIN battle_report_units bru ON bru.report_id = br.id
                WHERE br.attacker_user_id = ?
                UNION ALL
                SELECT SUM(CASE WHEN bru.side = 'attacker' THEN bru.lost_count ELSE 0 END) AS kills
                FROM battle_reports br
                JOIN battle_report_units bru ON bru.report_id = br.id
                WHERE br.defender_user_id = ?
            ) agg
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row && isset($row['total_kills']) ? (int)$row['total_kills'] : 0;
    }

    private function countSuccessfulAttacks(int $userId): int
    {
        if (!$this->tableExists('battle_reports')) {
            return 0;
        }
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM battle_reports WHERE attacker_user_id = ? AND attacker_won = 1");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['cnt'] : 0;
    }

    private function countSuccessfulDefenses(int $userId): int
    {
        if (!$this->tableExists('battle_reports')) {
            return 0;
        }
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM battle_reports WHERE defender_user_id = ? AND attacker_won = 0");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['cnt'] : 0;
    }

    private function countConquests(int $userId): int
    {
        if (!$this->tableExists('battle_reports')) {
            return 0;
        }
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM battle_reports WHERE attacker_user_id = ? AND attacker_won = 1");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['cnt'] : 0;
    }

    private function countMaxedBuildingTypes(int $userId): int
    {
        if (!$this->tableExists('building_types') || !$this->tableExists('village_buildings')) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT bt.id, bt.max_level, MAX(vb.level) AS lvl
            FROM building_types bt
            LEFT JOIN village_buildings vb ON vb.building_type_id = bt.id
            LEFT JOIN villages v ON vb.village_id = v.id AND v.user_id = ?
            GROUP BY bt.id, bt.max_level
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $maxed = 0;
        while ($row = $result->fetch_assoc()) {
            if ((int)$row['lvl'] >= (int)$row['max_level']) {
                $maxed++;
            }
        }
        $stmt->close();
        return $maxed;
    }

    private function tableExists(string $table): bool
    {
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
        if ($driver === 'sqlite') {
            $stmt = $this->conn->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ? LIMIT 1");
        } else {
            $stmt = $this->conn->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        }
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param("s", $table);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (bool)$row;
    }
}
