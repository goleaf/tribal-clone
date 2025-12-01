<?php
declare(strict_types=1);

/**
 * Simple in-DB rate limiter keyed by arbitrary token.
 * Uses a sliding window with counts; intended for low-QPS endpoints.
 */
class RateLimiter
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->ensureTable();
    }

    public function allow(string $key, int $maxPerWindow, int $windowSeconds): bool
    {
        if ($maxPerWindow <= 0 || $windowSeconds <= 0) {
            return true;
        }
        $now = time();
        $windowStart = $now - $windowSeconds;

        $stmt = $this->conn->prepare("
            INSERT INTO rate_limits (rate_key, window_start, count)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE
                count = CASE WHEN window_start >= VALUES(window_start) THEN count + 1 ELSE 1 END,
                window_start = CASE WHEN window_start >= VALUES(window_start) THEN window_start ELSE VALUES(window_start) END
        ");
        if ($stmt === false) {
            return true;
        }
        $stmt->bind_param("si", $key, $windowStart);
        $stmt->execute();
        $stmt->close();

        $stmt2 = $this->conn->prepare("SELECT count, window_start FROM rate_limits WHERE rate_key = ?");
        if ($stmt2 === false) {
            return true;
        }
        $stmt2->bind_param("s", $key);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        if (!$row) {
            return true;
        }
        $count = (int)$row['count'];
        $win = (int)$row['window_start'];
        if ($now - $win > $windowSeconds) {
            // stale window; reset next call
            return true;
        }
        return $count <= $maxPerWindow;
    }

    private function ensureTable(): void
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS rate_limits (
                rate_key VARCHAR(128) PRIMARY KEY,
                window_start INT NOT NULL DEFAULT 0,
                count INT NOT NULL DEFAULT 0
            )
        ");
    }
}
