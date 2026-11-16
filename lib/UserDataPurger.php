<?php
/**
 * Complete removal of a user account and everything that belongs to it.
 *
 * Deleting a row from `users` is not enough: application modules keep their
 * own tables (with email/user_id columns), the file index stores per-user
 * rows, and data directories live on the storage volumes. This service makes
 * deletion deterministic and reports exactly what was removed, so an admin
 * never leaves orphaned records or files behind.
 *
 * It is safe to call twice (idempotent).
 */
declare(strict_types=1);

final class UserDataPurger
{
    /** Columns that identify a user in application tables. */
    private const USER_COLUMNS = [
        'email', 'user_email', 'member_email', 'sender_email', 'admin_email',
        'owner_email', 'created_by', 'shared_by', 'uploaded_by', 'user_id', 'user_name',
    ];

    /** Tables handled by dedicated code, never by the generic column sweep. */
    private const SKIP_TABLES = [
        'users', 'pending_users', 'verification_tokens',
        'storage_files', 'storage_file_outbox', 'storage_index_jobs', 'storage_index_queue', 'storage_index_meta',
        'storage_disks', 'storage_migrations', 'storage_migration_files', 'storage_events', 'storage_usage_samples',
        'schema_once', 'app_schema_runs', 'cloud_groups',
    ];

    /** @var list<string> */
    private static array $knownApps = [
        'cloud', 'films', 'recipes', 'spends', 'steps', 'smart_home', 'storage',
        'dawarich', 'gitea', 'immich', 'kanboard', 'sentry', 'lopaka',
    ];

    /**
     * @param array{delete_files?:bool, dry_run?:bool} $options
     * @return array<string,mixed>
     */
    public static function purge(string $email, array $options = []): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('invalid_email');
        }
        $deleteFiles = !array_key_exists('delete_files', $options) || (bool)$options['delete_files'];
        $dryRun = !empty($options['dry_run']);

        $report = [
            'email' => $email,
            'files' => [],
            'tables' => [],
            'dropped_tables' => [],
            'dropped_schemas' => [],
            'index' => [],
            'redis' => false,
            'errors' => [],
        ];

        $userRow = self::userRow($email);
        $variants = self::userVariants($email, (string)($userRow['name'] ?? ''));

        // The avatar is referenced from the user row; keep it before deletion.
        $avatar = (string)($userRow['photo'] ?? '');

        if (!$dryRun) {
            try {
                $report['index'] = self::purgeIndex($variants);
            } catch (Throwable $e) {
                $report['errors'][] = 'index: ' . $e->getMessage();
            }
            try {
                $report['tables'] = self::purgeDatabase($email, $variants);
            } catch (Throwable $e) {
                $report['errors'][] = 'database: ' . $e->getMessage();
            }
            try {
                $report['dropped_schemas'] = self::dropPerUserSchemas($email);
            } catch (Throwable $e) {
                $report['errors'][] = 'schemas: ' . $e->getMessage();
            }
            try {
                $report['dropped_tables'] = self::dropPerUserTables($email);
            } catch (Throwable $e) {
                $report['errors'][] = 'tables: ' . $e->getMessage();
            }
            try {
                if (function_exists('removeUserFromActiveSessions')) removeUserFromActiveSessions($email);
                $report['redis'] = self::purgeRedis($email);
            } catch (Throwable $e) {
                $report['errors'][] = 'redis: ' . $e->getMessage();
            }
            if ($deleteFiles) {
                try {
                    $report['files'] = self::purgeFiles($variants, $avatar);
                } catch (Throwable $e) {
                    $report['errors'][] = 'files: ' . $e->getMessage();
                }
            }
        } else {
            $report['index']['planned_users'] = $variants;
            $report['files']['planned'] = self::fileTargets($variants);
        }

        return $report;
    }

    /** @return list<string> */
    public static function userVariants(string $email, string $name = ''): array
    {
        $email = strtolower(trim($email));
        $local = explode('@', $email)[0] ?? '';
        $variants = [
            $email,
            $local,
            str_replace(['@', '.'], '_', $email),
            str_replace('.', '_', $email),
            preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $email) ?: '',
            preg_replace('/[^a-zA-Z0-9_\-]/', '', $email) ?: '',
        ];
        if ($name !== '') {
            $variants[] = str_replace(' ', '_', $name);
            $variants[] = strtolower(str_replace(' ', '_', $name));
            $variants[] = strtoupper(str_replace(' ', '_', $name));
        }
        return array_values(array_unique(array_filter(array_map('strval', $variants), static fn($v) => $v !== '')));
    }

    /** @return array<string,mixed>|null */
    private static function userRow(string $email): ?array
    {
        try {
            $pdo = self::pdo();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE lower(email) = :e LIMIT 1');
            $stmt->bindValue(':e', $email, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function pdo(): PDO
    {
        if (class_exists('StorageDb')) return StorageDb::pdo();
        if (function_exists('getDB')) {
            $db = getDB();
            if ($db instanceof PDO) return $db;
            if (is_object($db) && method_exists($db, 'getPdo')) return $db->getPdo();
        }
        throw new RuntimeException('database_unavailable');
    }

    private static function isSqlite(PDO $pdo): bool
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private static function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /** @return array<string,mixed> */
    private static function purgeIndex(array $variants): array
    {
        if (!class_exists('FileIndexDb')) {
            $file = dirname(__DIR__) . '/lib/Storage/FileIndexDb.php';
            if (is_file($file)) require_once $file;
        }
        if (!class_exists('FileIndexDb')) return ['skipped' => 'index_unavailable'];
        return FileIndexDb::purgeUser($variants);
    }

    /**
     * Delete every row that references the user in any application table.
     *
     * @return array<string,int>
     */
    private static function purgeDatabase(string $email, array $variants): array
    {
        $pdo = self::pdo();
        $deleted = [];
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = [];

        if ($driver === 'pgsql') {
            $in = "'" . implode("','", array_map(static fn($c) => str_replace("'", "''", $c), self::USER_COLUMNS)) . "'";
            $sql = "SELECT table_name, column_name FROM information_schema.columns
                    WHERE table_schema = 'public' AND data_type IN ('text','character varying')
                      AND column_name IN ($in)";
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $columns[] = [(string)$row['table_name'], (string)$row['column_name']];
            }
        } else {
            foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN) ?: [] as $table) {
                $info = $pdo->query('PRAGMA table_info(' . self::quote((string)$table) . ')')->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($info as $col) {
                    if (in_array((string)$col['name'], self::USER_COLUMNS, true)) {
                        $columns[] = [(string)$table, (string)$col['name']];
                    }
                }
            }
        }

        $ph = [];
        foreach ($variants as $i => $_) $ph[] = ':v' . $i;
        $inList = implode(',', $ph);

        $textCast = $driver === 'pgsql' ? '::text' : '';
        foreach ($columns as [$table, $column]) {
            if (in_array($table, self::SKIP_TABLES, true)) continue;
            $sql = 'DELETE FROM ' . self::quote($table) . ' WHERE lower(' . self::quote($column) . $textCast . ') IN (' . $inList . ')';
            try {
                $stmt = $pdo->prepare($sql);
                foreach ($variants as $i => $value) $stmt->bindValue(':v' . $i, strtolower($value), PDO::PARAM_STR);
                $stmt->execute();
                $count = $stmt->rowCount();
                if ($count > 0) $deleted[$table . '.' . $column] = $count;
            } catch (Throwable $e) {
                // A column may not be castable or the table may be locked; keep
                // purging the rest and surface the failure in the report.
                $deleted['error:' . $table . '.' . $column] = 0;
            }
        }

        // Explicit account-level rows. They are skipped by the sweep above.
        $textCast = $driver === 'pgsql' ? '::text' : '';
        foreach ([
            ['cloud_groups', 'admin_email'],
            ['cloud_group_members', 'member_email'],
            ['cloud_group_chat_messages', 'sender_email'],
            ['cloud_group_chat_read', 'member_email'],
            ['cloud_group_file_history', 'user_email'],
        ] as [$table, $column]) {
            try {
                $stmt = $pdo->prepare('DELETE FROM ' . self::quote($table) . ' WHERE lower(' . self::quote($column) . $textCast . ') IN (' . $inList . ')');
                foreach ($variants as $i => $value) $stmt->bindValue(':v' . $i, strtolower($value), PDO::PARAM_STR);
                $stmt->execute();
                if ($stmt->rowCount() > 0) $deleted[$table . '.' . $column] = $stmt->rowCount();
            } catch (Throwable $e) {}
        }

        foreach (['users', 'pending_users', 'verification_tokens'] as $table) {
            try {
                $stmt = $pdo->prepare('DELETE FROM ' . self::quote($table) . " WHERE lower(email) IN ($inList)");
                foreach ($variants as $i => $value) $stmt->bindValue(':v' . $i, strtolower($value), PDO::PARAM_STR);
                $stmt->execute();
                if ($stmt->rowCount() > 0) $deleted[$table . '.email'] = $stmt->rowCount();
            } catch (Throwable $e) {}
        }
        return $deleted;
    }

    /** @return list<string> */
    private static function dropPerUserSchemas(string $email): array
    {
        $pdo = self::pdo();
        if (self::isSqlite($pdo) || !self::hasEmailToken($email)) return [];
        $token = self::emailToken($email);
        $dropped = [];
        $rows = $pdo->query("SELECT schema_name FROM information_schema.schemata
            WHERE schema_name NOT IN ('public','information_schema','pg_catalog','pg_toast')
              AND schema_name NOT LIKE 'pg_temp%' AND schema_name NOT LIKE 'pg_toast%'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($rows as $schema) {
            $schema = (string)$schema;
            if (!str_contains(strtolower($schema), $token)) continue;
            try {
                $pdo->exec('DROP SCHEMA IF EXISTS ' . self::quote($schema) . ' CASCADE');
                $dropped[] = $schema;
            } catch (Throwable $e) {}
        }
        return $dropped;
    }

    /** @return list<string> */
    private static function dropPerUserTables(string $email): array
    {
        $pdo = self::pdo();
        if (self::isSqlite($pdo) || !self::hasEmailToken($email)) return [];
        $token = self::emailToken($email);
        $dropped = [];
        $rows = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($rows as $table) {
            $table = (string)$table;
            if (!str_contains(strtolower($table), $token)) continue;
            try {
                $pdo->exec('DROP TABLE IF EXISTS ' . self::quote($table) . ' CASCADE');
                $dropped[] = $table;
            } catch (Throwable $e) {}
        }
        return $dropped;
    }

    private static function emailToken(string $email): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', strtolower($email)), '_');
    }

    /** Only drop objects for tokens that are specific enough to be a user name. */
    private static function hasEmailToken(string $email): bool
    {
        return strlen(self::emailToken($email)) >= 8;
    }

    private static function purgeRedis(string $email): bool
    {
        if (!function_exists('app_redis')) return false;
        $redis = app_redis();
        if (!$redis) return false;
        $prefix = function_exists('app_redis_key') ? app_redis_key('') : '';
        $needles = array_map('strtolower', [$email, str_replace(['@', '.'], '_', $email), explode('@', $email)[0] ?? '']);
        $it = null;
        while (($keys = $redis->scan($it, $prefix . '*', 500)) !== false) {
            foreach ($keys as $key) {
                $lower = strtolower((string)$key);
                foreach ($needles as $needle) {
                    if ($needle !== '' && str_contains($lower, $needle)) {
                        $redis->del((string)$key);
                        break;
                    }
                }
            }
            if ($it === 0) break;
        }
        return true;
    }

    /**
     * Directories and files belonging to the user. Symlinks are unlinked,
     * never followed, so a purge cannot escape the storage volume.
     *
     * @return array<string,mixed>
     */
    private static function purgeFiles(array $variants, string $avatar = ''): array
    {
        $removed = [];
        $targets = self::fileTargets($variants);
        foreach ($targets as $target) {
            if (is_link($target)) {
                if (@unlink($target)) $removed[] = $target;
                continue;
            }
            if (is_dir($target)) {
                if (self::deleteTree($target)) $removed[] = $target;
                continue;
            }
            if (is_file($target) && @unlink($target)) $removed[] = $target;
        }

        // Avatars are stored as "uploads/avatars/<random>.webp".
        if ($avatar !== '' && str_starts_with($avatar, 'uploads/avatars/')) {
            $abs = rtrim(appdata_uploads_base_dir(), '/') . '/' . substr($avatar, strlen('uploads/avatars/'));
            if (is_file($abs) && @unlink($abs)) $removed[] = $abs;
        }

        // Runtime session/temp files that embed the username in their JSON.
        foreach (['cloud_runtime/upload_sessions', 'cloud_runtime/temp', 'cloud_runtime/transcription_uploads', 'cloud_runtime/tts_output'] as $rel) {
            $dir = rtrim(appdata_base_dir(), '/') . '/' . $rel;
            if (!is_dir($dir)) continue;
            foreach (@scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $path = $dir . '/' . $entry;
                if (is_link($path)) { @unlink($path); continue; }
                if (is_file($path) && str_ends_with($path, '.json')) {
                    $raw = @file_get_contents($path);
                    if (!is_string($raw)) continue;
                    foreach ($variants as $variant) {
                        if (str_contains($raw, '"' . $variant . '"') || str_contains($raw, $variant . '/')) {
                            if (@unlink($path)) $removed[] = $path;
                            break;
                        }
                    }
                } elseif (is_dir($path)) {
                    foreach ($variants as $variant) {
                        if ($entry === $variant) { self::deleteTree($path); $removed[] = $path; break; }
                    }
                }
            }
        }
        return ['removed' => $removed, 'count' => count($removed)];
    }

    /** @return list<string> */
    private static function fileTargets(array $variants): array
    {
        $targets = [];
        $bases = [];
        foreach (self::$knownApps as $app) {
            try { $bases[] = appdata_app_base_dir($app); } catch (Throwable $e) {}
        }
        try { $bases[] = rtrim(appdata_uploads_base_dir(), '/'); } catch (Throwable $e) {}
        foreach (array_unique($bases) as $base) {
            if ($base === '' || !is_dir($base)) continue;
            foreach ($variants as $variant) {
                foreach ([$base . '/' . $variant, $base . '/users/' . $variant, $base . '/uploads/' . $variant] as $candidate) {
                    if (file_exists($candidate) || is_link($candidate)) $targets[] = $candidate;
                }
            }
        }
        return array_values(array_unique($targets));
    }

    private static function deleteTree(string $dir): bool
    {
        if (is_link($dir)) return @unlink($dir);
        if (!is_dir($dir)) return is_file($dir) ? @unlink($dir) : false;
        $ok = true;
        foreach (@scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            if (is_link($path)) {
                $ok = @unlink($path) && $ok;
            } elseif (is_dir($path)) {
                $ok = self::deleteTree($path) && $ok;
            } else {
                $ok = @unlink($path) && $ok;
            }
        }
        return @rmdir($dir) && $ok;
    }
}
