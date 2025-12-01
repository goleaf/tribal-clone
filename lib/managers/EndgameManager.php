<?php
declare(strict_types=1);

/**
 * Computes lightweight endgame/dominance snapshots for UI signals.
 * Uses tribe points as a proxy for control until full objective tracking exists.
 */
class EndgameManager
{
    private $conn;

    // Warn when the leading tribe owns at least 60% of total tribe points.
    private const DOMINANCE_WARN_THRESHOLD = 0.60;
    private const MIN_POINTS_SAMPLE = 1;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
    * Returns top tribes and dominance share.
    *
    * @return array{
    *   total_points:int,
    *   top?:array{id:int,name:string,points:int,share:float},
    *   leaders:array<int,array{id:int,name:string,points:int,share:float}>
    * }
    */
    public function getTribeDominanceSnapshot(): array
    {
        $leaders = [];
        $totalPoints = 0;

        $stmtTotal = $this->conn->prepare("SELECT COALESCE(SUM(points),0) AS total FROM tribes WHERE points > 0");
        if ($stmtTotal) {
            $stmtTotal->execute();
            $row = $stmtTotal->get_result()->fetch_assoc();
            $stmtTotal->close();
            $totalPoints = (int)($row['total'] ?? 0);
        }

        $stmtTop = $this->conn->prepare("
            SELECT id, name, points
            FROM tribes
            WHERE points > 0
            ORDER BY points DESC
            LIMIT 3
        ");
        if ($stmtTop) {
            $stmtTop->execute();
            $res = $stmtTop->get_result();
            while ($row = $res->fetch_assoc()) {
                $points = (int)$row['points'];
                $share = ($totalPoints > 0) ? $points / $totalPoints : 0.0;
                $leaders[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'points' => $points,
                    'share' => $share,
                ];
            }
            $stmtTop->close();
        }

        $snapshot = [
            'total_points' => $totalPoints,
            'leaders' => $leaders,
        ];

        if (!empty($leaders)) {
            $snapshot['top'] = $leaders[0];
        }

        return $snapshot;
    }

    /**
     * Whether to show a dominance warning banner based on tribe point share.
     */
    public function shouldShowDominanceWarning(array $snapshot): bool
    {
        if (($snapshot['total_points'] ?? 0) < self::MIN_POINTS_SAMPLE) {
            return false;
        }
        $top = $snapshot['top'] ?? null;
        if (!$top) {
            return false;
        }
        return ($top['share'] ?? 0) >= self::DOMINANCE_WARN_THRESHOLD;
    }
}
