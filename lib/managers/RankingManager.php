<?php

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/PointsManager.php';

class RankingManager
{
    private $conn;
    private ?array $opponentsMap = null;
    private ?array $conquestsMap = null;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Fetches the player ranking with pagination.
     *
     * @param int $limit Players per page.
     * @param int $offset Pagination offset.
     * @return array Ranked player rows.
     */
    public function getPlayersRanking(int $limit, int $offset): array
    {
        $pointsManager = new PointsManager($this->conn);
        $opponentsMap = $this->getOpponentsDefeatedMap();
        $conquestsMap = $this->getConquestsMap();

        $query = "
            SELECT 
                u.id, 
                u.username, 
                u.points,
                COUNT(DISTINCT v.id) as village_count, 
                COALESCE(SUM(v.population), 0) as total_population
            FROM 
                users u
            LEFT JOIN 
                villages v ON u.id = v.user_id
            GROUP BY 
                u.id
            ORDER BY 
                u.points DESC, village_count DESC, u.username ASC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->conn->prepare($query);
        // Check for prepare errors
        if ($stmt === false) {
            error_log("RankingManager::getPlayersRanking prepare failed: " . $this->conn->error);
            return []; // Return empty array on error
        }

        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $players = [];
        // Rank will be calculated in the calling code based on offset
        while ($row = $result->fetch_assoc()) {
            $userId = (int)$row['id'];
            $row['points'] = (int)($row['points'] ?? 0);
            $row['opponents_defeated'] = $opponentsMap[$userId] ?? 0;
            $row['conquests'] = $conquestsMap[$userId] ?? 0;
            $row['growth_day'] = $pointsManager->getGrowthDelta($userId, 1);
            $row['growth_week'] = $pointsManager->getGrowthDelta($userId, 7);
            $row['growth_month'] = $pointsManager->getGrowthDelta($userId, 30);
            $players[] = $row;
        }

        $stmt->close();

        return $players;
    }

    /**
     * Fetches total number of players for ranking pagination.
     *
     * @return int Total player count.
     */
    public function getTotalPlayersCount(): int
    {
        $count_query = "SELECT COUNT(*) as total FROM users";
        $stmt_count = $this->conn->prepare($count_query);
         // Check for prepare errors
        if ($stmt_count === false) {
            error_log("RankingManager::getTotalPlayersCount prepare failed: " . $this->conn->error);
            return 0; // Return 0 on error
        }
        $stmt_count->execute();
        $total_records = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        return $total_records ?? 0;
    }

    /**
     * Fetches tribe ranking with pagination.
     *
     * @param int $limit Tribes per page.
     * @param int $offset Pagination offset.
     * @return array Ranked tribe rows.
     */
    public function getTribesRanking(int $limit, int $offset): array
    {
        if (!$this->tableExists('tribes') || !$this->tableExists('tribe_members')) {
            return [];
        }

        $query = "
            SELECT 
                t.id,
                t.name,
                t.tag,
                t.points as stored_points,
                COUNT(DISTINCT tm.user_id) as member_count,
                COUNT(DISTINCT v.id) as village_count,
                COALESCE(SUM(u.points), 0) as member_points
            FROM
                tribes t
            LEFT JOIN
                tribe_members tm ON tm.tribe_id = t.id
            LEFT JOIN
                users u ON u.id = tm.user_id
            LEFT JOIN
                villages v ON v.user_id = tm.user_id
            GROUP BY
                t.id
            ORDER BY
                member_points DESC, member_count DESC, t.name ASC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            error_log("RankingManager::getTribesRanking prepare failed: " . $this->conn->error);
            return [];
        }
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $tribes = [];
        while ($row = $result->fetch_assoc()) {
             $calculatedPoints = (int)($row['member_points'] ?? 0);
             $storedPoints = (int)($row['stored_points'] ?? 0);
             $row['points'] = max($storedPoints, $calculatedPoints);
             unset($row['stored_points']);
             unset($row['member_points']);
             $tribes[] = $row;
        }
        $stmt->close();
        return $tribes;
    }

     /**
     * Fetches total number of tribes for ranking pagination.
     *
     * @return int Total tribe count.
     */
    public function getTotalTribesCount(): int
    {
        if (!$this->tableExists('tribes')) {
            return 0;
        }

        $count_query = "SELECT COUNT(*) as total FROM tribes";
        $stmt_count = $this->conn->prepare($count_query);
        if ($stmt_count === false) {
            error_log("RankingManager::getTotalTribesCount prepare failed: " . $this->conn->error);
            return 0;
        }
        $stmt_count->execute();
        $total_records = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();
        return (int)($total_records ?? 0);
    }

    private function tableExists(string $table): bool
    {
        if (function_exists('dbTableExists')) {
            return dbTableExists($this->conn, $table);
        }
        // Fallback: assume table exists if helper is unavailable
        return true;
    }

    /**
     * Cached map of user_id => opponents defeated (kills inflicted).
     */
    private function getOpponentsDefeatedMap(): array
    {
        if ($this->opponentsMap !== null) {
            return $this->opponentsMap;
        }

        if (!$this->tableExists('battle_reports') || !$this->tableExists('battle_report_units')) {
            $this->opponentsMap = [];
            return $this->opponentsMap;
        }

        $sql = "
            SELECT user_id, SUM(kills) AS total_kills FROM (
                SELECT br.attacker_user_id AS user_id, SUM(CASE WHEN bru.side = 'defender' THEN bru.lost_count ELSE 0 END) AS kills
                FROM battle_reports br
                JOIN battle_report_units bru ON bru.report_id = br.id
                GROUP BY br.attacker_user_id
                UNION ALL
                SELECT br.defender_user_id AS user_id, SUM(CASE WHEN bru.side = 'attacker' THEN bru.lost_count ELSE 0 END) AS kills
                FROM battle_reports br
                JOIN battle_report_units bru ON bru.report_id = br.id
                GROUP BY br.defender_user_id
            ) agg
            GROUP BY user_id
        ";

        $result = $this->conn->query($sql);
        $map = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $map[(int)$row['user_id']] = (int)$row['total_kills'];
            }
        }
        $this->opponentsMap = $map;
        return $this->opponentsMap;
    }

    /**
     * Cached map of user_id => conquests (approximated as won attacks).
     */
    private function getConquestsMap(): array
    {
        if ($this->conquestsMap !== null) {
            return $this->conquestsMap;
        }

        if (!$this->tableExists('battle_reports')) {
            $this->conquestsMap = [];
            return $this->conquestsMap;
        }

        $sql = "
            SELECT attacker_user_id AS user_id, COUNT(*) AS conquests
            FROM battle_reports
            WHERE attacker_won = 1
            GROUP BY attacker_user_id
        ";
        $result = $this->conn->query($sql);
        $map = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $map[(int)$row['user_id']] = (int)$row['conquests'];
            }
        }
        $this->conquestsMap = $map;
        return $this->conquestsMap;
    }

}

?> 
