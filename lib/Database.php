<?php

if (!defined('MYSQLI_ASSOC')) {
    define('MYSQLI_ASSOC', 1);
}

class Database {
    private $driver;
    private $host;
    private $user;
    private $pass;
    private $dbname;
    private $conn;

    public function __construct($host = null, $user = null, $pass = null, $dbname = null) {
        $this->driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->dbname = $dbname;
        $this->connect();
    }

    private function connect() {
        if ($this->driver === 'sqlite') {
            $dbPath = defined('DB_PATH') ? DB_PATH : ($this->dbname ?: __DIR__ . '/../data/tribal_wars.sqlite');
            $this->conn = new SQLiteAdapter($dbPath);
            return;
        }

        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    public function getConnection() {
        return $this->conn;
    }

    public function closeConnection() {
        if (method_exists($this->conn, 'close')) {
            $this->conn->close();
        }
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function real_escape_string($string) {
        return method_exists($this->conn, 'real_escape_string')
            ? $this->conn->real_escape_string($string)
            : $string;
    }
}

class SQLiteAdapter {
    public $insert_id = 0;
    public $error = '';

    private $pdo;

    public function __construct($dbPath) {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        // Register MySQL-like helper functions for compatibility
        if (method_exists($this->pdo, 'sqliteCreateFunction')) {
            $this->pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
            $this->pdo->sqliteCreateFunction('UNIX_TIMESTAMP', fn() => time());
            $this->pdo->sqliteCreateFunction('FROM_UNIXTIME', fn($ts) => date('Y-m-d H:i:s', (int)$ts));
        }
    }

    public function getPdo() {
        return $this->pdo;
    }

    public function prepare($sql) {
        return new SQLiteStatement($this, $sql);
    }

    public function query($sql) {
        try {
            $converted = SQLiteStatement::convertSql($sql);
            $trimmed = ltrim($converted);
            $isSelect = stripos($trimmed, 'SELECT') === 0 || stripos($trimmed, 'PRAGMA') === 0 || stripos($trimmed, 'WITH') === 0;

            if ($isSelect) {
                $stmt = $this->pdo->query($converted);
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                return new SQLiteResult($rows);
            }

            $this->pdo->exec($converted);
            $this->insert_id = (int)$this->pdo->lastInsertId();
            $this->error = '';
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function real_escape_string($string) {
        return substr($this->pdo->quote($string), 1, -1);
    }

    public function set_charset($charset) {
        // Not needed for SQLite, provided for API compatibility
        return true;
    }

    public function begin_transaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollBack();
    }

    public function close() {
        $this->pdo = null;
    }
}

class SQLiteStatement {
    public $error = '';
    public $num_rows = 0;
    public $insert_id = 0;
    public $affected_rows = 0;

    private $conn;
    private $sql;
    private $pdoStmt;
    private $boundParams = [];
    private $boundResultVars = [];
    private $resultRows = [];
    private $resultIndex = 0;

    public function __construct($conn, $sql) {
        $this->conn = $conn;
        $this->sql = self::convertSql($sql);
    }

    public static function convertSql($sql) {
        $sql = str_replace('`', '"', $sql);
        $sql = preg_replace('/\bNOW\s*\(\)/i', 'CURRENT_TIMESTAMP', $sql);
        $sql = preg_replace("/UNIX_TIMESTAMP\s*\(\s*\)/i", "strftime('%s','now')", $sql);
        $sql = preg_replace("/FROM_UNIXTIME\s*\(\s*\?\s*\)/i", "datetime(?,'unixepoch')", $sql);
        $sql = preg_replace('/INSERT\s+IGNORE/i', 'INSERT OR IGNORE', $sql);
        return $sql;
    }

    public function bind_param($types, &...$vars) {
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

    public function bind_result(&...$vars) {
        $this->boundResultVars = [];
        foreach ($vars as &$var) {
            $this->boundResultVars[] = &$var;
        }
        return true;
    }

    private function mapType(string $typeChar) {
        return match ($typeChar) {
            'i' => PDO::PARAM_INT,
            'd' => PDO::PARAM_STR,
            default => PDO::PARAM_STR,
        };
    }

    public function execute() {
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

    public function get_result() {
        return new SQLiteResult($this->resultRows);
    }

    public function store_result() {
        // Results are already stored eagerly
        return true;
    }

    public function fetch() {
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

    public function close() {
        $this->pdoStmt = null;
        $this->resultRows = [];
        $this->resultIndex = 0;
        return true;
    }
}

class SQLiteResult {
    public $num_rows = 0;
    private $rows = [];
    private $index = 0;

    public function __construct($rows = []) {
        $this->rows = $rows ?: [];
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc() {
        if ($this->index >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->index++];
    }

    public function fetch_all($mode = MYSQLI_ASSOC) {
        return $this->rows;
    }

    public function free() {
        $this->rows = [];
        $this->num_rows = 0;
    }

    public function close() {
        $this->free();
        return true;
    }
}
?>
