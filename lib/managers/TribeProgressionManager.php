<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/TribeManager.php';

class TribeProgressionManager
{
    public const XP_PER_SKILL_POINT = 50;

    private $conn;
    private TribeManager $tribeManager;

    public function __construct($conn, TribeManager $tribeManager)
    {
        $this->conn = $conn;
        $this->tribeManager = $tribeManager;
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        // Add XP/skill_points columns if missing
        if (!dbColumnExists($this->conn, 'tribes', 'xp')) {
            $this->conn->query("ALTER TABLE tribes ADD COLUMN xp INTEGER NOT NULL DEFAULT 0");
        }
        if (!dbColumnExists($this->conn, 'tribes', 'skill_points')) {
            $this->conn->query("ALTER TABLE tribes ADD COLUMN skill_points INTEGER NOT NULL DEFAULT 0");
        }

        // Skills table
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_skills (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tribe_id INTEGER NOT NULL,
                skill_key TEXT NOT NULL,
                level INTEGER NOT NULL DEFAULT 0,
                xp INTEGER NOT NULL DEFAULT 0,
                unlocked_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(tribe_id, skill_key)
            )
        ");

        // Quests table
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS tribe_quests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tribe_id INTEGER NOT NULL,
                quest_key TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'open',
                progress INTEGER NOT NULL DEFAULT 0,
                target INTEGER NOT NULL DEFAULT 0,
                reward_xp INTEGER NOT NULL DEFAULT 0,
                expires_at TEXT NULL,
                completed_at TEXT NULL,
                claimed_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(tribe_id, quest_key)
            )
        ");
    }

    public function getSkillDefinitions(): array
    {
        return [
            'resource_boost' => [
                'name' => 'Resource Focus',
                'description' => 'Increases resource production for all tribe members.',
                'max_level' => 5,
                'effect' => '+2% production per level'
            ],
            'recruitment_speed' => [
                'name' => 'Swift Recruitment',
                'description' => 'Improves unit recruitment speeds.',
                'max_level' => 3,
                'effect' => '+3% faster training per level'
            ],
            'defense_drills' => [
                'name' => 'Defense Drills',
                'description' => 'Bolsters village defenses.',
                'max_level' => 3,
                'effect' => '+2% defensive strength per level'
            ]
        ];
    }

    public function getQuestDefinitions(): array
    {
        return [
            'recruitment_drive' => [
                'name' => 'Recruitment Drive',
                'description' => 'Reach 3 tribe members.',
                'target_field' => 'member_count',
                'target_value' => 3,
                'reward_xp' => 40
            ],
            'expansion_wave' => [
                'name' => 'Expansion Wave',
                'description' => 'Control 5 villages as a tribe.',
                'target_field' => 'village_count',
                'target_value' => 5,
                'reward_xp' => 50
            ],
            'wealth_growth' => [
                'name' => 'Wealth Growth',
                'description' => 'Reach 1,000 total tribe points.',
                'target_field' => 'points',
                'target_value' => 1000,
                'reward_xp' => 60
            ],
            'trade_alliance' => [
                'name' => 'Trade Alliance',
                'description' => 'Post 3 trade offers as a tribe.',
                'target_field' => 'trade_offers',
                'target_value' => 3,
                'reward_xp' => 35
            ]
        ];
    }

    public function getTribeSkills(int $tribeId): array
    {
        $definitions = $this->getSkillDefinitions();
        $this->ensureSkillRows($tribeId, $definitions);

        $stmt = $this->conn->prepare("SELECT skill_key, level, xp FROM tribe_skills WHERE tribe_id = ?");
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $skills = [];
        while ($row = $result->fetch_assoc()) {
            $key = $row['skill_key'];
            $skills[] = array_merge($definitions[$key] ?? [], [
                'key' => $key,
                'level' => (int)$row['level'],
                'xp' => (int)$row['xp']
            ]);
        }
        $stmt->close();

        return $skills;
    }

    public function getTribeQuests(int $tribeId): array
    {
        $definitions = $this->getQuestDefinitions();
        $this->ensureQuestRows($tribeId, $definitions);
        $this->evaluateQuests($tribeId, $definitions);

        $stmt = $this->conn->prepare("
            SELECT quest_key, status, progress, target, reward_xp, completed_at, claimed_at
            FROM tribe_quests
            WHERE tribe_id = ?
            ORDER BY quest_key ASC
        ");
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $quests = [];
        while ($row = $result->fetch_assoc()) {
            $key = $row['quest_key'];
            $quests[] = array_merge($definitions[$key] ?? [], [
                'key' => $key,
                'status' => $row['status'],
                'progress' => (int)$row['progress'],
                'target' => (int)$row['target'],
                'reward_xp' => (int)$row['reward_xp'],
                'completed_at' => $row['completed_at'],
                'claimed_at' => $row['claimed_at']
            ]);
        }
        $stmt->close();

        return $quests;
    }

    public function getAvailableSkillPoints(int $tribeId): int
    {
        $stmt = $this->conn->prepare("SELECT skill_points FROM tribes WHERE id = ?");
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $points = 0;
        if ($row = $result->fetch_assoc()) {
            $points = (int)$row['skill_points'];
        }
        $stmt->close();
        return $points;
    }

    public function claimQuestReward(int $tribeId, string $questKey, int $actorId): array
    {
        $definitions = $this->getQuestDefinitions();
        if (!isset($definitions[$questKey])) {
            return ['success' => false, 'message' => 'Unknown quest.'];
        }

        $stmt = $this->conn->prepare("SELECT id, status, reward_xp, progress, target FROM tribe_quests WHERE tribe_id = ? AND quest_key = ? LIMIT 1");
        $stmt->bind_param("is", $tribeId, $questKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $quest = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$quest) {
            return ['success' => false, 'message' => 'Quest not found.'];
        }
        if ($quest['status'] === 'claimed') {
            return ['success' => false, 'message' => 'Quest reward already claimed.'];
        }
        if ((int)$quest['progress'] < (int)$quest['target'] || $quest['status'] !== 'completed') {
            return ['success' => false, 'message' => 'Quest is not ready to claim.'];
        }

        $rewardXp = (int)$quest['reward_xp'];

        // Update quest status
        $updateQuest = $this->conn->prepare("UPDATE tribe_quests SET status = 'claimed', claimed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateQuest->bind_param("i", $quest['id']);
        $updateQuest->execute();
        $updateQuest->close();

        $this->grantTribeXp($tribeId, $rewardXp);

        return ['success' => true, 'message' => 'Quest reward claimed (+'.$rewardXp.' XP).'];
    }

    public function upgradeSkill(int $tribeId, string $skillKey, int $actorId): array
    {
        $definitions = $this->getSkillDefinitions();
        if (!isset($definitions[$skillKey])) {
            return ['success' => false, 'message' => 'Unknown skill.'];
        }

        $available = $this->getAvailableSkillPoints($tribeId);
        if ($available < 1) {
            return ['success' => false, 'message' => 'No skill points available.'];
        }

        $stmt = $this->conn->prepare("SELECT id, level FROM tribe_skills WHERE tribe_id = ? AND skill_key = ? LIMIT 1");
        $stmt->bind_param("is", $tribeId, $skillKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $skill = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$skill) {
            return ['success' => false, 'message' => 'Skill row missing.'];
        }

        $currentLevel = (int)$skill['level'];
        $maxLevel = (int)$definitions[$skillKey]['max_level'];
        if ($currentLevel >= $maxLevel) {
            return ['success' => false, 'message' => 'Skill is already at max level.'];
        }

        // Spend point and level up
        $this->conn->begin_transaction();
        try {
            $updateSkill = $this->conn->prepare("UPDATE tribe_skills SET level = level + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateSkill->bind_param("i", $skill['id']);
            $updateSkill->execute();
            $updateSkill->close();

            $updateTribe = $this->conn->prepare("UPDATE tribes SET skill_points = skill_points - 1 WHERE id = ? AND skill_points > 0");
            $updateTribe->bind_param("i", $tribeId);
            $updateTribe->execute();
            $updateTribe->close();

            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('Failed to upgrade tribe skill: '.$e->getMessage());
            return ['success' => false, 'message' => 'Failed to upgrade skill.'];
        }

        return ['success' => true, 'message' => 'Skill upgraded to level '.($currentLevel + 1).'.'];
    }

    private function ensureSkillRows(int $tribeId, array $definitions): void
    {
        foreach ($definitions as $key => $def) {
            $stmt = $this->conn->prepare("SELECT id FROM tribe_skills WHERE tribe_id = ? AND skill_key = ? LIMIT 1");
            $stmt->bind_param("is", $tribeId, $key);
            $stmt->execute();
            $exists = $stmt->get_result();
            $hasRow = $exists && $exists->num_rows > 0;
            $stmt->close();

            if (!$hasRow) {
                $insert = $this->conn->prepare("INSERT INTO tribe_skills (tribe_id, skill_key, level, xp) VALUES (?, ?, 0, 0)");
                $insert->bind_param("is", $tribeId, $key);
                $insert->execute();
                $insert->close();
            }
        }
    }

    private function ensureQuestRows(int $tribeId, array $definitions): void
    {
        foreach ($definitions as $key => $def) {
            $stmt = $this->conn->prepare("SELECT id FROM tribe_quests WHERE tribe_id = ? AND quest_key = ? LIMIT 1");
            $stmt->bind_param("is", $tribeId, $key);
            $stmt->execute();
            $exists = $stmt->get_result();
            $hasRow = $exists && $exists->num_rows > 0;
            $stmt->close();

            if (!$hasRow) {
                $insert = $this->conn->prepare("
                    INSERT INTO tribe_quests (tribe_id, quest_key, target, reward_xp) VALUES (?, ?, ?, ?)
                ");
                $target = (int)$def['target_value'];
                $rewardXp = (int)$def['reward_xp'];
                $insert->bind_param("isii", $tribeId, $key, $target, $rewardXp);
                $insert->execute();
                $insert->close();
            }
        }
    }

    private function evaluateQuests(int $tribeId, array $definitions): void
    {
        $stats = $this->getTribeAggregates($tribeId);
        foreach ($definitions as $key => $def) {
            $progressValue = $stats[$def['target_field']] ?? 0;
            $targetValue = (int)$def['target_value'];

            $stmt = $this->conn->prepare("
                UPDATE tribe_quests 
                SET progress = ?, status = CASE 
                    WHEN status = 'claimed' THEN status
                    WHEN ? >= target THEN 'completed'
                    ELSE 'open'
                END,
                completed_at = CASE 
                    WHEN status <> 'claimed' AND ? >= target THEN COALESCE(completed_at, CURRENT_TIMESTAMP)
                    ELSE completed_at
                END
                WHERE tribe_id = ? AND quest_key = ?
            ");
            $stmt->bind_param("iiiis", $progressValue, $progressValue, $progressValue, $tribeId, $key);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function grantTribeXp(int $tribeId, int $xp): void
    {
        // Calculate skill point gain based on XP thresholds
        $stmt = $this->conn->prepare("SELECT xp, skill_points FROM tribes WHERE id = ?");
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : ['xp' => 0, 'skill_points' => 0];
        $stmt->close();

        $currentXp = (int)($row['xp'] ?? 0);
        $newXp = $currentXp + $xp;

        $pointsBefore = intdiv($currentXp, self::XP_PER_SKILL_POINT);
        $pointsAfter = intdiv($newXp, self::XP_PER_SKILL_POINT);
        $pointDelta = max(0, $pointsAfter - $pointsBefore);

        $update = $this->conn->prepare("UPDATE tribes SET xp = ?, skill_points = skill_points + ? WHERE id = ?");
        $update->bind_param("iii", $newXp, $pointDelta, $tribeId);
        $update->execute();
        $update->close();
    }

    private function getTribeAggregates(int $tribeId): array
    {
        // Member count
        $memberCount = 0;
        $stmt = $this->conn->prepare("SELECT COUNT(*) as c FROM tribe_members WHERE tribe_id = ?");
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $memberCount = (int)$row['c'];
        }
        $stmt->close();

        // Villages and points
        $villageCount = 0;
        $points = 0;
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT v.id) as villages, COALESCE(SUM(v.points),0) as points
            FROM villages v
            JOIN tribe_members tm ON tm.user_id = v.user_id
            WHERE tm.tribe_id = ?
        ");
        $stmt->bind_param("i", $tribeId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $villageCount = (int)$row['villages'];
            $points = (int)$row['points'];
        }
        $stmt->close();

        // Trade offers posted by tribe villages
        $tradeOffers = 0;
        if (dbTableExists($this->conn, 'trade_offers')) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as offers
                FROM trade_offers o
                JOIN villages v ON v.id = o.source_village_id
                JOIN tribe_members tm ON tm.user_id = v.user_id
                WHERE tm.tribe_id = ?
            ");
            $stmt->bind_param("i", $tribeId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $tradeOffers = (int)$row['offers'];
            }
            $stmt->close();
        }

        return [
            'member_count' => $memberCount,
            'village_count' => $villageCount,
            'points' => $points,
            'trade_offers' => $tradeOffers
        ];
    }
}
