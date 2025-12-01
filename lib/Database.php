<?php
declare(strict_types=1);

if (!defined('MYSQLI_ASSOC')) {
    define('MYSQLI_ASSOC', 1);
}

class Database {
    private string $driver;
    private ?string $host;
    private ?string $user;
    private ?string $pass;
    private ?string $dbname;
    /** @var mysqli|SQLiteAdapter|null */
    private $conn = null;

    public function __construct(?string $host = null, ?string $user = null, ?string $pass = null, ?string $dbname = null) {
        $this->driver = defined('DB_DRIVER') ? (string)DB_DRIVER : 'mysql';
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->dbname = $dbname;
        $this->connect();
    }

    private function connect(): void {
        if ($this->driver === 'sqlite') {
            $dbPath = defined('DB_PATH') ? DB_PATH : ($this->dbname ?: __DIR__ . '/../data/tribal_wars.sqlite');
            $this->conn = new SQLiteAdapter($dbPath);
            return;
        }

        if (!class_exists('mysqli')) {
            throw new \RuntimeException('The mysqli extension is not available but DB_DRIVER is set to mysql.');
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
        $this->conn->set_charset("utf8mb4");
    }

    public function getConnection(): mysqli|SQLiteAdapter {
        if ($this->conn === null) {
            throw new \RuntimeException('Database connection has not been initialised.');
        }
        return $this->conn;
    }

    public function closeConnection(): void {
        if ($this->conn && method_exists($this->conn, 'close')) {
            $this->conn->close();
        }
        $this->conn = null;
    }

    public function query(string $sql): mixed {
        return $this->getConnection()->query($sql);
    }

    public function prepare(string $sql): mysqli_stmt|SQLiteStatement|false {
        return $this->getConnection()->prepare($sql);
    }

    public function real_escape_string(string $string): string {
        return method_exists($this->getConnection(), 'real_escape_string')
            ? $this->getConnection()->real_escape_string($string)
            : $string;
    }
}

class SQLiteAdapter {
    public int $insert_id = 0;
    public string $error = '';

    private ?PDO $pdo = null;

    public function __construct(string $dbPath) {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->pdo->exec('PRAGMA journal_mode = WAL');

        // Register MySQL-like helper functions for compatibility
        if (method_exists($this->pdo, 'sqliteCreateFunction')) {
            $this->pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
            $this->pdo->sqliteCreateFunction('UNIX_TIMESTAMP', fn() => time());
            $this->pdo->sqliteCreateFunction('FROM_UNIXTIME', fn($ts) => date('Y-m-d H:i:s', (int)$ts));
        }
    }

    public function getPdo(): PDO {
        if (!$this->pdo) {
            throw new \RuntimeException('SQLite connection is not available.');
        }
        return $this->pdo;
    }

    public function prepare(string $sql): SQLiteStatement {
        return new SQLiteStatement($this, $sql);
    }

    public function query(string $sql): SQLiteResult|bool {
        try {
            $converted = SQLiteStatement::convertSql($sql);
            $trimmed = ltrim($converted);
            $isSelect = stripos($trimmed, 'SELECT') === 0 || stripos($trimmed, 'PRAGMA') === 0 || stripos($trimmed, 'WITH') === 0;

            if ($isSelect) {
            $stmt = $this->getPdo()->query($converted);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            return new SQLiteResult($rows);
        }

            $this->getPdo()->exec($converted);
            $this->insert_id = (int)$this->getPdo()->lastInsertId();
            $this->error = '';
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function real_escape_string(string $string): string {
        return substr($this->getPdo()->quote($string), 1, -1);
    }

    public function set_charset(string $charset): bool {
        // Not needed for SQLite, provided for API compatibility
        return true;
    }

    public function begin_transaction(): bool {
        return $this->getPdo()->beginTransaction();
    }

    public function commit(): bool {
        return $this->getPdo()->commit();
    }

    public function rollback(): bool {
        return $this->getPdo()->rollBack();
    }

    public function close(): void {
        $this->pdo = null;
    }
}

class SQLiteStatement {
    public string $error = '';
    public int $num_rows = 0;
    public int $insert_id = 0;
    public int $affected_rows = 0;

    private SQLiteAdapter $conn;
    private string $sql;
    private ?PDOStatement $pdoStmt = null;
    private array $boundParams = [];
    private array $boundResultVars = [];
    private array $resultRows = [];
    private int $resultIndex = 0;

    public function __construct(SQLiteAdapter $conn, string $sql) {
        $this->conn = $conn;
        $this->sql = self::convertSql($sql);
    }

    public static function convertSql(string $sql): string {
        $sql = str_replace('`', '"', $sql);
        $sql = preg_replace('/\bNOW\s*\(\)/i', 'CURRENT_TIMESTAMP', $sql);
        $sql = preg_replace("/UNIX_TIMESTAMP\s*\(\s*\)/i", "strftime('%s','now')", $sql);
        $sql = preg_replace("/FROM_UNIXTIME\s*\(\s*\?\s*\)/i", "datetime(?,'unixepoch')", $sql);
        $sql = preg_replace('/INSERT\s+IGNORE/i', 'INSERT OR IGNORE', $sql);
        return $sql;
    }

    public function bind_param(string $types, &...$vars): bool {
        $this->boundParams = [];
        $typeChars = str_split($types);
        foreach ($typeChars as $index => $typeChar) {
            $this->boundParams[] = [
                'type' => $typeChar,
                'value' => &$vars[$index]
            ];
        }
        return true;
    }

    public function bind_result(&...$vars): bool {
        $this->boundResultVars = [];
        foreach ($vars as &$var) {
            $this->boundResultVars[] = &$var;
        }
        return true;
    }

    private function mapType(string $typeChar): int {
        return match ($typeChar) {
            'i' => PDO::PARAM_INT,
            'd' => PDO::PARAM_STR,
            default => PDO::PARAM_STR,
        };
    }

    public function execute(): bool {
        try {
            $this->pdoStmt = $this->conn->getPdo()->prepare($this->sql);
            foreach ($this->boundParams as $index => $meta) {
                $value = $meta['value'];
                $pdoType = $value === null ? PDO::PARAM_NULL : $this->mapType($meta['type']);
                $this->pdoStmt->bindValue($index + 1, $value, $pdoType);
            }

            $this->pdoStmt->execute();
            $this->resultRows = $this->pdoStmt->fetchAll(PDO::FETCH_ASSOC);
            $this->num_rows = count($this->resultRows);
            $this->affected_rows = $this->pdoStmt->rowCount();
            $this->insert_id = (int)$this->conn->getPdo()->lastInsertId();
            $this->conn->insert_id = $this->insert_id;
            $this->resultIndex = 0;
            $this->error = '';
            $this->conn->error = '';
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->conn->error = $this->error;
            return false;
        }
    }

    public function get_result(): SQLiteResult {
        return new SQLiteResult($this->resultRows);
    }

    public function store_result(): bool {
        // Results are already stored eagerly
        return true;
    }

    public function fetch(): bool {
        if ($this->resultIndex >= count($this->resultRows)) {
            return false;
        }
        $row = $this->resultRows[$this->resultIndex];
        $values = array_values($row);
        foreach ($this->boundResultVars as $i => &$ref) {
            $ref = $values[$i] ?? null;
        }
        $this->resultIndex++;
        return true;
    }

    public function close(): bool {
        $this->pdoStmt = null;
        $this->resultRows = [];
        $this->resultIndex = 0;
        return true;
    }
}

class SQLiteResult {
    public int $num_rows = 0;
    private array $rows = [];
    private int $index = 0;

    public function __construct(array $rows = []) {
        $this->rows = $rows;
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc(): ?array {
        if ($this->index >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->index++];
    }

    public function fetch_row(): ?array {
        if ($this->index >= $this->num_rows) {
            return null;
        }
        $row = array_values($this->rows[$this->index]);
        $this->index++;
        return $row;
    }

    public function fetch_all(int $mode = MYSQLI_ASSOC): array {
        return $this->rows;
    }

    public function free(): void {
        $this->rows = [];
        $this->num_rows = 0;
    }

    public function close(): bool {
        $this->free();
        return true;
    }
}
?>
