<?php
declare(strict_types=1);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/sql_translate.php';
if (!defined('SQLITE3_ASSOC')) define('SQLITE3_ASSOC', 1);
if (!defined('SQLITE3_NUM')) define('SQLITE3_NUM', 2);
if (!defined('SQLITE3_BOTH')) define('SQLITE3_BOTH', 3);
if (!defined('SQLITE3_TEXT')) define('SQLITE3_TEXT', 3);
if (!defined('SQLITE3_INTEGER')) define('SQLITE3_INTEGER', 1);
if (!defined('SQLITE3_FLOAT')) define('SQLITE3_FLOAT', 2);
if (!defined('SQLITE3_NULL')) define('SQLITE3_NULL', 5);
final class AppDatabaseResult {
    private ?PDOStatement $stmt;
    public function __construct(?PDOStatement $stmt) { $this->stmt = $stmt; }
    public function fetchArray(int $mode = SQLITE3_ASSOC): ?array {
        if (!$this->stmt) return null;
        $row = $this->stmt->fetch($mode === SQLITE3_NUM ? PDO::FETCH_NUM : PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
final class AppDatabaseStatement {
    private PDO $pdo;
    private PDOStatement $stmt;
    private AppDatabase $db;
    public function __construct(AppDatabase $db, PDO $pdo, string $sql) {
        $this->db = $db;
        $this->pdo = $pdo;
        $sql = app_sql_translate_query($sql);
        if ($sql === '') throw new RuntimeException('Unsupported SQL statement');
        $this->stmt = $pdo->prepare($sql);
    }
    public function bindValue(string|int $name, mixed $value, int $type = SQLITE3_TEXT): bool {
        if ($type === SQLITE3_NULL || $value === null) {
            $this->stmt->bindValue($name, null, PDO::PARAM_NULL);
            return true;
        }
        if ($type === SQLITE3_INTEGER) {
            $this->stmt->bindValue($name, (int)$value, PDO::PARAM_INT);
            return true;
        }
        if ($type === SQLITE3_FLOAT) {
            $this->stmt->bindValue($name, (float)$value);
            return true;
        }
        $this->stmt->bindValue($name, (string)$value, PDO::PARAM_STR);
        return true;
    }
    public function execute(): AppDatabaseResult {
        $this->stmt->execute();
        $this->db->setLastChanges($this->stmt->rowCount());
        return new AppDatabaseResult($this->stmt);
    }
}
final class AppDatabase {
    private static ?self $instance = null;
    /** @var array<string,self> */
    private static array $userInstances = [];
    private PDO $pdo;
    private int $lastChanges = 0;
    private bool $exceptions = true;
    private function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    public static function connect(): self {
        if (self::$instance === null) {
            self::$instance = new self(app_pdo_connect(false));
        } else {
            self::$instance->pdo->exec('SET search_path TO public');
        }
        return self::$instance;
    }
    public static function connectUserSchema(string $schemaSql): self {
        if (!isset(self::$userInstances[$schemaSql])) {
            $pdo = app_pdo_connect(true);
            $pdo->exec('SET search_path TO ' . $schemaSql . ', public');
            self::$userInstances[$schemaSql] = new self($pdo);
        }
        return self::$userInstances[$schemaSql];
    }
    public static function escapeString(string $value): string {
        return str_replace("'", "''", $value);
    }
    public function enableExceptions(bool $on = true): void { $this->exceptions = $on; }
    public function busyTimeout(int $ms): void {}
    public function setLastChanges(int $n): void { $this->lastChanges = $n; }
    public function exec(string $sql): bool {
        $parts = app_sql_split_statements($sql);
        foreach ($parts as $part) {
            $t = app_sql_translate_query($part);
            if ($t === '' || app_sql_is_noop($part)) continue;
            try {
                $this->lastChanges = $this->pdo->exec($t) ?: 0;
            } catch (PDOException $e) {
                if ($this->exceptions) throw $e;
                return false;
            }
        }
        return true;
    }
    public function prepare(string $sql): AppDatabaseStatement {
        return new AppDatabaseStatement($this, $this->pdo, $sql);
    }
    public function query(string $sql): AppDatabaseResult {
        $t = app_sql_translate_query($sql);
        if ($t === '' || app_sql_is_noop($sql)) return new AppDatabaseResult(null);
        $stmt = $this->pdo->query($t);
        return new AppDatabaseResult($stmt ?: null);
    }
    public function querySingle(string $sql, bool $entireRow = false): mixed {
        $res = $this->query($sql);
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if ($row === null) return null;
        if ($entireRow) return $row;
        return reset($row);
    }
    public function changes(): int { return $this->lastChanges; }
    public function lastInsertRowID(): int {
        $v = $this->pdo->query("SELECT lastval()")->fetchColumn();
        return (int)($v ?: 0);
    }
    public function getPdo(): PDO { return $this->pdo; }
}
function app_sql_split_statements(string $sql): array {
    $out = [];
    $buf = '';
    $inSingle = false;
    $inDouble = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($ch === "'" && !$inDouble) $inSingle = !$inSingle;
        if ($ch === '"' && !$inSingle) $inDouble = !$inDouble;
        if ($ch === ';' && !$inSingle && !$inDouble) {
            $part = trim($buf);
            if ($part !== '') $out[] = $part;
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    $part = trim($buf);
    if ($part !== '') $out[] = $part;
    return $out;
}
function app_open_main_db(): AppDatabase {
    return AppDatabase::connect();
}
