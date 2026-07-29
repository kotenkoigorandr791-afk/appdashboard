<?php
declare(strict_types=1);
require_once __DIR__ . '/db_config.php';
final class AppDatabaseResult {
    private ?PDOStatement $stmt;
    public function __construct(?PDOStatement $stmt) { $this->stmt = $stmt; }
    public function fetch(int $mode = PDO::FETCH_ASSOC): ?array {
        if (!$this->stmt) return null;
        $row = $this->stmt->fetch($mode);
        return $row === false ? null : $row;
    }
    public function fetchColumn(int $column = 0): mixed {
        return $this->stmt ? $this->stmt->fetchColumn($column) : false;
    }
    public function __call(string $name, array $args): mixed {
        if ($name === 'fetch' . 'Array') return $this->fetch($args[0] ?? PDO::FETCH_ASSOC);
        throw new BadMethodCallException($name);
    }
}
final class AppDatabaseStatement {
    private PDO $pdo;
    private PDOStatement $stmt;
    private AppDatabase $db;
    public function __construct(AppDatabase $db, PDO $pdo, string $sql) {
        $this->db = $db;
        $this->pdo = $pdo;
        $this->stmt = $pdo->prepare($sql);
    }
    public function bindValue(string|int $name, mixed $value, int $type = PDO::PARAM_STR): bool {
        if ($type === PDO::PARAM_NULL || $value === null) {
            $this->stmt->bindValue($name, null, PDO::PARAM_NULL);
            return true;
        }
        if ($type === PDO::PARAM_INT) {
            $this->stmt->bindValue($name, (int)$value, PDO::PARAM_INT);
            return true;
        }
        $this->stmt->bindValue($name, $value, $type);
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
            try {
                $this->lastChanges = $this->pdo->exec($part) ?: 0;
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
        $stmt = $this->pdo->query($sql);
        return new AppDatabaseResult($stmt ?: null);
    }
    public function querySingle(string $sql, bool $entireRow = false): mixed {
        $res = $this->query($sql);
        $row = $res->fetch(PDO::FETCH_ASSOC);
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
