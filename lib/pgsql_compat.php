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
    private string $sql;
    private array $bindings = [];
    public function __construct(AppDatabase $db, PDO $pdo, string $sql) {
        $this->db = $db;
        $this->pdo = $pdo;
        $this->sql = $sql;
        $this->stmt = $pdo->prepare($sql);
    }
    public function bindValue(string|int $name, mixed $value, int $type = PDO::PARAM_STR): bool {
        $this->bindings[$name] = [$value, $type];
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
    public function reset(): bool {
        $this->bindings = [];
        $this->stmt = $this->pdo->prepare($this->sql);
        return true;
    }
    public function closeCursor(): bool {
        return $this->stmt->closeCursor();
    }
    private function reprepare(): void {
        $this->db->reconnect();
        $this->pdo = $this->db->getPdo();
        $this->stmt = $this->pdo->prepare($this->sql);
        foreach ($this->bindings as $name => [$value, $type]) {
            $this->bindValue($name, $value, $type);
        }
    }
    public function execute(): AppDatabaseResult {
        $run = function (): AppDatabaseResult {
            try {
                $this->stmt->execute();
            } catch (PDOException $e) {
                if (!app_db_is_transient_connection_error($e)) throw $e;
                $this->reprepare();
                if ($this->db->needsScopedSessionPublic()) $this->db->beginScopedSessionPublic();
                $this->stmt->execute();
            }
            $this->db->setLastChanges($this->stmt->rowCount());
            return new AppDatabaseResult($this->stmt);
        };
        if (!$this->db->needsScopedSessionPublic()) return $run();
        $this->db->beginScopedSessionPublic();
        try {
            $result = $run();
            $this->db->endScopedSessionPublic(true);
            return $result;
        } catch (Throwable $e) {
            $this->db->endScopedSessionPublic(false);
            throw $e;
        }
    }
    public function fetch(int $mode = PDO::FETCH_ASSOC): ?array {
        $row = $this->stmt->fetch($mode);
        return $row === false ? null : $row;
    }
}
final class AppDatabase {
    private static ?self $instance = null;
    /** @var array<string,self> */
    private static array $userInstances = [];
    private PDO $pdo;
    private ?string $schemaSql;
    private array $initSql = [];
    private int $lastChanges = 0;
    private bool $publicPathSet = false;
    private bool $exceptions = true;
    private int $txnDepth = 0;
    private function __construct(PDO $pdo, ?string $schemaSql = null, array $initSql = []) {
        $this->pdo = $pdo;
        $this->schemaSql = $schemaSql;
        $this->initSql = $initSql;
    }
    public function reconnect(): void {
        $this->publicPathSet = false;
        $this->pdo = app_pdo_connect(true);
        $this->txnDepth = 0;
    }
    public static function connect(): self {
        if (self::$instance === null) {
            self::$instance = new self(app_pdo_connect(false, 'main'));
        } else {
            try {
                app_pdo_reset_session(self::$instance->pdo);
                self::$instance->publicPathSet = true;
            } catch (PDOException $e) {
                if (!app_db_is_transient_connection_error($e)) throw $e;
                self::$instance->reconnect();
            }
        }
        return self::$instance;
    }
    public static function connectUserSchema(string $schemaSql, array $initSql = []): self {
        $key = $schemaSql . '|' . implode(';', $initSql);
        if (!isset(self::$userInstances[$key])) {
            $pdo = app_pdo_connect(true, 'user');
            self::$userInstances[$key] = new self($pdo, $schemaSql, $initSql);
        }
        return self::$userInstances[$key];
    }
    private function needsScopedSession(): bool {
        return $this->schemaSql !== null && $this->initSql !== [];
    }
    private function beginScopedSession(): void {
        if (!$this->needsScopedSession()) return;
        if ($this->txnDepth > 0) {
            $this->txnDepth++;
            return;
        }
        $this->pdo->exec('BEGIN');
        $this->txnDepth = 1;
        $this->pdo->exec('SET LOCAL search_path TO ' . $this->schemaSql . ', public');
        foreach ($this->initSql as $q) $this->pdo->exec((string)$q);
    }
    private function endScopedSession(bool $ok): void {
        if (!$this->needsScopedSession() || $this->txnDepth <= 0) return;
        $this->txnDepth--;
        if ($this->txnDepth > 0) return;
        $this->pdo->exec($ok ? 'COMMIT' : 'ROLLBACK');
    }
    /** @template T @param callable():T $fn @return T */
    private function withScopedSession(callable $fn): mixed {
        if (!$this->needsScopedSession()) return $fn();
        $this->beginScopedSession();
        try {
            $result = $fn();
            $this->endScopedSession(true);
            return $result;
        } catch (Throwable $e) {
            $this->endScopedSession(false);
            throw $e;
        }
    }
    public static function escapeString(string $value): string {
        return str_replace("'", "''", $value);
    }
    public function enableExceptions(bool $on = true): void { $this->exceptions = $on; }
    public function busyTimeout(int $ms): void {}
    public function setLastChanges(int $n): void { $this->lastChanges = $n; }
    public function exec(string $sql): bool {
        return (bool)$this->withScopedSession(function () use ($sql) {
            $parts = app_sql_split_statements($sql);
            $scoped = $this->needsScopedSession();
            foreach ($parts as $part) {
                $upper = strtoupper(ltrim($part));
                if ($scoped && (str_starts_with($upper, 'BEGIN') || str_starts_with($upper, 'START TRANSACTION'))) {
                    $this->txnDepth++;
                    continue;
                }
                if ($scoped && (str_starts_with($upper, 'COMMIT') || str_starts_with($upper, 'ROLLBACK'))) {
                    if ($this->txnDepth > 1) $this->txnDepth--;
                    continue;
                }
                try {
                    $this->lastChanges = $this->pdo->exec($part) ?: 0;
                } catch (PDOException $e) {
                    if (app_db_is_transient_connection_error($e)) {
                        $this->reconnect();
                        if ($scoped) $this->beginScopedSession();
                        $this->lastChanges = $this->pdo->exec($part) ?: 0;
                        continue;
                    }
                    if ($this->exceptions) throw $e;
                    return false;
                }
            }
            return true;
        });
    }
    public function prepare(string $sql): AppDatabaseStatement {
        try {
            return new AppDatabaseStatement($this, $this->pdo, $sql);
        } catch (PDOException $e) {
            if (!app_db_is_transient_connection_error($e)) throw $e;
            $this->reconnect();
            return new AppDatabaseStatement($this, $this->pdo, $sql);
        }
    }
    public function query(string $sql): AppDatabaseResult {
        return $this->withScopedSession(function () use ($sql) {
            try {
                $stmt = $this->pdo->query($sql);
            } catch (PDOException $e) {
                if (!app_db_is_transient_connection_error($e)) throw $e;
                $this->reconnect();
                $this->beginScopedSession();
                $stmt = $this->pdo->query($sql);
            }
            return new AppDatabaseResult($stmt ?: null);
        });
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
        return (int)$this->withScopedSession(function () {
            $v = $this->pdo->query("SELECT lastval()")->fetchColumn();
            return (int)($v ?: 0);
        });
    }
    public function getPdo(): PDO { return $this->pdo; }
    public function beginScopedSessionPublic(): void { $this->beginScopedSession(); }
    public function endScopedSessionPublic(bool $ok): void { $this->endScopedSession($ok); }
    public function needsScopedSessionPublic(): bool { return $this->needsScopedSession(); }
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
