<?php
declare(strict_types=1);

class HomeController extends Controller
{
    public function index(): void
    {
        $stats = [
            'worlds' => 0,
            'players' => 0,
            'villages' => 0
        ];

        // Pull simple counts for the homepage
        if ($this->conn) {
            $stats['worlds'] = $this->fetchCount("SELECT COUNT(*) AS count FROM worlds");
            $stats['players'] = $this->fetchCount("SELECT COUNT(*) AS count FROM users");
            $stats['villages'] = $this->fetchCount("SELECT COUNT(*) AS count FROM villages");
        }

        $this->render('home', [
            'title' => 'Tribal Wars - New Version',
            'stats' => $stats
        ]);
    }

    private function fetchCount(string $sql): int
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return (int)($row['count'] ?? 0);
    }
}
