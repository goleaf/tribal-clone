<?php

require_once __DIR__ . '/../functions.php';

class RankingManager
{
    private $conn;

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
        $query = "
            SELECT 
                u.id, 
                u.username, 
                COUNT(v.id) as village_count, 
                SUM(v.population) as total_population,
                SUM(
                    (SELECT COUNT(*) FROM village_units vu WHERE vu.village_id = v.id)
                ) as total_units -- This sum is incorrect; it should sum unit population
            FROM 
                users u
            LEFT JOIN 
                villages v ON u.id = v.user_id
            GROUP BY 
                u.id
            ORDER BY 
                total_population DESC, village_count DESC
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
            // Calculate points based on total population (example logic)
            $row['points'] = $row['total_population'] ? $row['total_population'] * 10 : 0;
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
                COALESCE(SUM(v.population), 0) as total_population
            FROM
                tribes t
            LEFT JOIN
                tribe_members tm ON tm.tribe_id = t.id
            LEFT JOIN
                villages v ON v.user_id = tm.user_id
            GROUP BY
                t.id
            ORDER BY
                total_population DESC, member_count DESC, t.name ASC
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
             $calculatedPoints = $row['total_population'] ? (int)$row['total_population'] * 10 : 0;
             $storedPoints = (int)($row['stored_points'] ?? 0);
             $row['points'] = max($storedPoints, $calculatedPoints);
             unset($row['stored_points']);
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

}

?> 
