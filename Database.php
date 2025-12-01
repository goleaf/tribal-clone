<?php
declare(strict_types=1);

/**
 * Lightweight SQLite database wrapper with:
 * - Singleton connection
 * - Prepared statements with bound parameters
 * - Safe helpers: query, execute, fetchAll, fetchOne, lastInsertId
 * - Transaction helpers
 * - Automatic reconnection attempts
 * - Error logging to logs/database.log
 */
class Database
{
    private const DB_FILE = __DIR__ . '/game.db';
    private const LOG_FILE = __DIR__ . '/logs/database.log';
    private static ?Database $instance = null;

    private ?PDO $pdo = null;
    private bool $reconnecting = false;

    private function __construct()
    {
        $this->ensureFiles();
        $this->connect();
        register_shutdown_function([$this, 'close']);
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function query(string $sql, array $params = []): ?PDOStatement
    {
        return $this->run($sql, $params, false);
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->run($sql, $params, true);
        return $stmt ? $stmt->rowCount() : 0;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->run($sql, $params, false);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->run($sql, $params, false);
        if (!$stmt) {
            return null;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function lastInsertId(): string
    {
        $this->ensureConnection();
        return $this->pdo?->lastInsertId() ?? '0';
    }

    public function beginTransaction(): bool
    {
        $this->ensureConnection();
        return $this->pdo?->beginTransaction() ?? false;
    }

    public function commit(): bool
    {
        return $this->pdo?->commit() ?? false;
    }

    public function rollback(): bool
    {
        return $this->pdo?->rollBack() ?? false;
    }

    public function close(): void
    {
        $this->pdo = null;
    }

    private function run(string $sql, array $params, bool $isExec): ?PDOStatement
    {
        $this->ensureConnection();
        if ($this->pdo === null) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                throw new PDOException('Failed to prepare statement.');
            }
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            if ($this->tryReconnect($e)) {
                return $this->run($sql, $params, $isExec);
            }
            return null;
        }
    }

    private function connect(): void
    {
        try {
            $this->pdo = new PDO('sqlite:' . self::DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
        } catch (PDOException $e) {
            $this->pdo = null;
            $this->logError($e, 'CONNECT', []);
        }
    }

    private function ensureConnection(): void
    {
        if ($this->pdo === null) {
            $this->connect();
        }
    }

    private function tryReconnect(PDOException $e): bool
    {
        if ($this->reconnecting) {
            return false;
        }
        $this->reconnecting = true;
        $this->close();
        $this->connect();
        $this->reconnecting = false;
        return $this->pdo !== null;
    }

    private function ensureFiles(): void
    {
        $dbDir = dirname(self::DB_FILE);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        if (!file_exists(self::DB_FILE)) {
            touch(self::DB_FILE);
        }

        $logDir = dirname(self::LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        if (!file_exists(self::LOG_FILE)) {
            touch(self::LOG_FILE);
        }
    }

    private function logError(Throwable $e, string $sql, array $params): void
    {
        $message = sprintf(
            "[%s] %s | SQL: %s | Params: %s\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $sql,
            json_encode($params)
        );
        file_put_contents(self::LOG_FILE, $message, FILE_APPEND);
    }
}
