<?php
/**
 * Mandatory backup of this application's user data.
 *
 * One fixed Restic profile always exists ("App user data"), always enabled and
 * always contains the user-data sources plus a fresh pg_dump. Extra backups are
 * created by the operator through the web UI or through
 * APPDASHBOARD_BACKUPS_JSON in docker-compose.yml.
 */
declare(strict_types=1);

final class AppDataBackup
{
    public const PROFILE_ID = 'a11da7a0';
    public const PROFILE_NAME = 'App user data';

    /** @return list<string> */
    public static function requiredSources(): array
    {
        $sources = [];
        $base = getenv('APPDATA_BASE_DIR');
        if (is_string($base) && $base !== '' && is_dir($base)) $sources[] = rtrim($base, '/');
        $uploads = getenv('UPLOADS_BASE_DIR');
        if (is_string($uploads) && $uploads !== '' && is_dir($uploads)) $sources[] = rtrim($uploads, '/');

        $storageRoot = getenv('STORAGE_ROOT');
        if (!is_string($storageRoot) || trim($storageRoot) === '') $storageRoot = '/storage';
        $storageRoot = rtrim($storageRoot, '/');
        // Only back up the pool when it is really a MergerFS mount; a plain
        // /storage directory must never be snapshotted as user data.
        if (is_dir($storageRoot) && self::isMergerfsMount($storageRoot)) $sources[] = $storageRoot;

        $sources[] = self::dumpDir();

        // Drop child paths already covered by a parent source.
        $sources = array_values(array_unique(array_filter($sources)));
        $filtered = [];
        foreach ($sources as $source) {
            $covered = false;
            foreach ($filtered as $parent) {
                if ($source === $parent || str_starts_with($source . '/', $parent . '/')) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) $filtered[] = $source;
        }
        return $filtered;
    }

    private static function isMergerfsMount(string $root): bool
    {
        $info = @file_get_contents('/proc/self/mountinfo');
        if (!is_string($info)) return @stat($root . '/.mergerfs') !== false;
        foreach (preg_split('/\R/u', $info) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            if (count($parts) < 7) continue;
            if (rtrim((string)($parts[4] ?? ''), '/') !== $root) continue;
            $sep = array_search('-', $parts, true);
            $fstype = $sep !== false ? strtolower((string)($parts[$sep + 1] ?? '')) : '';
            if (str_contains($fstype, 'mergerfs')) return true;
        }
        return false;
    }

    public static function dumpDir(): string
    {
        $dir = resticDataDir() . '/appdata-db';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        self::chownAppUser($dir, false);
        return $dir;
    }

    /**
     * The root watcher/scheduler must not leave root-owned backup state behind:
     * the web UI runs as www-data and has to read the repository and replace
     * the database dump.
     */
    private static function chownAppUser(string $path, bool $recursive): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) return;
        if (!function_exists('posix_getpwnam')) return;
        $www = @posix_getpwnam('www-data');
        if (!is_array($www) || !isset($www['uid'], $www['gid'])) return;
        $uid = (int)$www['uid'];
        $gid = (int)$www['gid'];
        if (!is_dir($path)) return;
        if ($recursive) {
            try {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $node) {
                    /** @var SplFileInfo $node */
                    @chown($node->getPathname(), $uid);
                    @chgrp($node->getPathname(), $gid);
                }
            } catch (Throwable $e) {
            }
        }
        @chown($path, $uid);
        @chgrp($path, $gid);
    }

    /**
     * Ensure the fixed profile exists with the required sources.
     *
     * @return array<string,mixed>|null
     */
    public static function ensureProfile(bool $initializeRepository = true): ?array
    {
        if (!function_exists('resticLoadConfig')) return null;
        $cfg = resticLoadConfig();
        $existing = resticFindProfile($cfg, self::PROFILE_ID);
        $sources = self::requiredSources();

        $repository = (string)($existing['repository'] ?? '');
        $password = (string)($existing['password'] ?? '');
        if ($repository === '') {
            foreach ((array)($cfg['backups'] ?? []) as $candidate) {
                if (!is_array($candidate)) continue;
                if ((string)($candidate['repository'] ?? '') !== '' && (string)($candidate['password'] ?? '') !== '') {
                    $repository = (string)$candidate['repository'];
                    $password = (string)$candidate['password'];
                    break;
                }
            }
        }
        if ($repository === '') {
            $repository = (string)($cfg['repository'] ?? '');
            $password = (string)($cfg['password'] ?? '');
        }
        $generated = false;
        if ($repository === '') {
            $repository = resticDataDir() . '/appdata-repo';
            $password = bin2hex(random_bytes(24));
            $generated = true;
        }
        if ($password === '') $password = bin2hex(random_bytes(24));

        $schedule = is_array($existing['schedule'] ?? null) ? $existing['schedule'] : [];
        $schedule = array_replace([
            'enabled' => true,
            'frequency' => 'daily',
            'time' => '03:00',
            'weekday' => 1,
            'monthday' => 1,
            'interval_hours' => 6,
        ], $schedule);
        $schedule['enabled'] = true;
        $retention = is_array($existing['retention'] ?? null) ? $existing['retention'] : [];
        $retention = array_replace([
            'keep_last' => 0,
            'keep_hourly' => 0,
            'keep_daily' => 7,
            'keep_weekly' => 4,
            'keep_monthly' => 6,
            'keep_yearly' => 0,
            'keep_within' => '',
            // Never prune automatically: a missing disk must not delete history.
            'prune' => false,
        ], $retention);
        $retention['prune'] = false;

        $desired = [
            'id' => self::PROFILE_ID,
            'name' => self::PROFILE_NAME,
            'description' => 'Mandatory backup of this application\'s user files and PostgreSQL dump.',
            'repository' => $repository,
            'password' => $password,
            'host' => (string)($existing['host'] ?? ($cfg['host'] ?? 'appdashboard')),
            'sources' => $sources,
            'excludes' => array_values(array_unique(array_merge(
                (array)($existing['excludes'] ?? []),
                [
                    '*.appdashboard-part-*',
                    '*.tmp.appdashboard-*',
                    // Caches and model/toolchain trees are not user data.
                    'models',
                    'bot-venv',
                    'cloud_video_tmp',
                    'cloud_libreoffice_home',
                    'latex-texmf',
                    'latex-pdf-cache',
                    '.composer-cache',
                ]
            ))),
            'schedule' => $schedule,
            'retention' => $retention,
            'enabled' => true,
        ];

        $needsSave = $existing === null
            || (string)($existing['name'] ?? '') !== self::PROFILE_NAME
            || (string)($existing['repository'] ?? '') !== $repository
            || (string)($existing['password'] ?? '') !== $password
            || array_values((array)($existing['sources'] ?? [])) !== $sources
            || empty($existing['enabled'])
            || empty($existing['schedule']['enabled'])
            || !empty($existing['retention']['prune']);
        if ($needsSave) {
            $profiles = [];
            $replaced = false;
            foreach ((array)($cfg['backups'] ?? []) as $profile) {
                if ((string)($profile['id'] ?? '') === self::PROFILE_ID) {
                    $profiles[] = $desired;
                    $replaced = true;
                } else {
                    $profiles[] = $profile;
                }
            }
            if (!$replaced) $profiles[] = $desired;
            $cfg = resticSaveConfig(['backups' => $profiles], $cfg);
        }

        $saved = resticFindProfile($cfg, self::PROFILE_ID);
        if ($saved === null) return null;
        self::chownAppUser(resticDataDir(), false);
        $repoCfg = resticRepoConfig($cfg, self::PROFILE_ID);
        $localRepo = backupRepoIsLocal($repository) ? rtrim(resticRepositoryLocation($repository), '/') : '';
        $repositoryMissing = $localRepo !== '' && !is_file($localRepo . '/config');
        if ($initializeRepository && ($generated || $repositoryMissing)) {
            try {
                resticInitRepository($repoCfg);
                if ($localRepo !== '') self::chownAppUser($localRepo, true);
            } catch (Throwable $e) {
                // A repository may already exist at a path restic cannot write
                // from this process; the UI will surface the error on test/run.
                @file_put_contents(
                    resticDataDir() . '/appdata-backup.log',
                    '[' . gmdate('c') . '] repository init failed: ' . $e->getMessage() . "\n",
                    FILE_APPEND | LOCK_EX
                );
            }
        }
        return $saved;
    }

    /**
     * Extra profiles from APPDASHBOARD_BACKUPS_JSON (docker-compose).
     *
     * @return list<array<string,mixed>>
     */
    public static function syncEnvProfiles(): array
    {
        if (!function_exists('resticLoadConfig')) return [];
        $raw = getenv('APPDASHBOARD_BACKUPS_JSON');
        if (!is_string($raw) || trim($raw) === '') return [];
        $data = json_decode($raw, true);
        if (!is_array($data)) return [];
        $cfg = resticLoadConfig();
        $profiles = array_values((array)($cfg['backups'] ?? []));
        $changed = false;
        $result = [];
        foreach ($data as $entry) {
            if (!is_array($entry)) continue;
            $name = trim((string)($entry['name'] ?? ''));
            $id = strtolower(trim((string)($entry['id'] ?? '')));
            if (!preg_match('/^[a-f0-9]{8,32}$/', $id)) {
                $id = substr(sha1('env:' . $name), 0, 12);
            }
            if ($id === self::PROFILE_ID) continue;
            if ($name === '') $name = 'Compose backup ' . $id;
            $entry['id'] = $id;
            $entry['name'] = $name;
            $entry['enabled'] = $entry['enabled'] ?? true;
            try {
                $normalized = resticNormalizeProfile($entry, self::findProfile($profiles, $id));
            } catch (Throwable $e) {
                continue;
            }
            $replaced = false;
            foreach ($profiles as $i => $profile) {
                if ((string)($profile['id'] ?? '') !== $id) continue;
                $replaced = true;
                if (self::comparable($profile) === self::comparable($normalized)) {
                    $result[] = $profile;
                    break;
                }
                $profiles[$i] = $normalized;
                $changed = true;
                $result[] = $normalized;
                break;
            }
            if (!$replaced) {
                $profiles[] = $normalized;
                $changed = true;
                $result[] = $normalized;
            }
        }
        if ($changed) resticSaveConfig(['backups' => $profiles], $cfg);
        return $result;
    }

    /** @param array<string,mixed> $profile @return array<string,mixed> */
    private static function comparable(array $profile): array
    {
        return [
            'id' => (string)($profile['id'] ?? ''),
            'name' => (string)($profile['name'] ?? ''),
            'repository' => (string)($profile['repository'] ?? ''),
            'password' => (string)($profile['password'] ?? ''),
            'host' => (string)($profile['host'] ?? ''),
            'sources' => array_values((array)($profile['sources'] ?? [])),
            'excludes' => array_values((array)($profile['excludes'] ?? [])),
            'schedule' => (array)($profile['schedule'] ?? []),
            'retention' => (array)($profile['retention'] ?? []),
            'enabled' => !empty($profile['enabled']),
        ];
    }

    /** @param list<array<string,mixed>> $profiles */
    private static function findProfile(array $profiles, string $id): ?array
    {
        foreach ($profiles as $profile) {
            if ((string)($profile['id'] ?? '') === $id) return $profile;
        }
        return null;
    }

    /**
     * Dump PostgreSQL with pg_dump (never by copying live database files).
     *
     * @return array{ok:bool,path:string,error:string}
     */
    public static function dumpDatabase(): array
    {
        if (!function_exists('app_db_load_config')) {
            return ['ok' => false, 'path' => '', 'error' => 'db_config_unavailable'];
        }
        try {
            $cfg = app_db_load_config();
        } catch (Throwable $e) {
            return ['ok' => false, 'path' => '', 'error' => 'db_config_unavailable'];
        }
        $binary = trim((string)getenv('PG_DUMP_BINARY'));
        if ($binary === '') $binary = 'pg_dump';
        $dir = self::dumpDir();
        $file = $dir . '/appdatabase.dump';
        $tmp = $file . '.tmp.' . getmypid();
        $argv = [
            $binary,
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--file=' . $tmp,
            '--host=' . (string)($cfg['host'] ?? '127.0.0.1'),
            '--port=' . (string)($cfg['port'] ?? '5432'),
            '--username=' . (string)($cfg['user'] ?? 'appdashboard'),
            (string)($cfg['name'] ?? 'appdashboard'),
        ];
        $env = getenv();
        if (!is_array($env)) $env = [];
        $env['PGPASSWORD'] = (string)($cfg['password'] ?? '');
        $env['PGCONNECT_TIMEOUT'] = (string)($cfg['connect_timeout'] ?? '10');
        $env['PATH'] = $env['PATH'] ?? '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($argv, $descriptors, $pipes, null, $env);
        if (!is_resource($proc)) {
            return ['ok' => false, 'path' => '', 'error' => 'pg_dump_start_failed'];
        }
        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0 || !is_file($tmp)) {
            @unlink($tmp);
            return ['ok' => false, 'path' => '', 'error' => 'pg_dump_failed: ' . trim($stderr)];
        }
        @chmod($tmp, 0600);
        @rename($tmp, $file);
        return ['ok' => true, 'path' => $file, 'error' => ''];
    }
}
