<?php
/**
 * Per-user storage usage sources.
 *
 * Every source returns rows of ['email','name','bytes','items'] — bytes are the
 * space occupied by that account. Nothing here touches the filesystem: values are
 * read from databases that already track usage, so the admin panel never blocks.
 *
 * External databases are opened read-only with a statement timeout, so a heavy
 * or unreachable database can never stall the dashboard or the collector loop.
 */

const STORAGE_USAGE_DEFAULT_TIMEOUT_MS = 20000;

/** Password: dedicated override first, then the app's own compose variable */
function storageUsagePassword(string $key): string {
    $specific = getenv('STORAGE_USAGE_' . strtoupper($key) . '_DB_PASSWORD');
    if (is_string($specific) && $specific !== '') return $specific;
    $shared = getenv(strtoupper($key) . '_DB_PASSWORD');
    if (is_string($shared) && $shared !== '') return $shared;
    return match ($key) {
        'gitea' => storageUsageEnvFilesValue(['/opt/docker/gitea/.env'], ['GITEA__database__PASSWD', 'GITEA_DB_PASSWORD', 'DB_PASSWORD'])
            ?: storageUsageIniValue('/opt/docker/gitea/data/gitea/conf/app.ini', 'PASSWD'),
        'immich' => storageUsageEnvFilesValue(['/opt/docker/immich/.env'], ['DB_PASSWORD', 'IMMICH_DB_PASSWORD']),
        'dawarich' => storageUsageEnvFilesValue(['/opt/docker/dawarich/.env'], ['DATABASE_PASSWORD', 'POSTGRES_PASSWORD', 'DAWARICH_DB_PASSWORD']),
        default => '',
    };
}

function storageUsageEnv(string $name, string $default): string {
    $value = getenv($name);
    return (is_string($value) && $value !== '') ? $value : $default;
}

function storageUsageEnvFileValue(string $path, string $name): string {
    if (!is_readable($path)) return '';
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return '';
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (trim($key) !== $name) continue;
        return trim(trim($value), "\"'");
    }
    return '';
}

function storageUsageEnvFilesValue(array $paths, array $names): string {
    foreach ($paths as $path) {
        foreach ($names as $name) {
            $value = storageUsageEnvFileValue($path, $name);
            if ($value !== '') return $value;
        }
    }
    return '';
}

function storageUsageIniValue(string $path, string $name): string {
    if (!is_readable($path)) return '';
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return '';
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (trim($key) !== $name) continue;
        return trim(trim($value), "\"'");
    }
    return '';
}

/**
 * Source definitions in collection order.
 * 'internal' sources read the dashboard database, 'external' ones their own Postgres.
 */
function storageUsageSourceDefs(): array {
    $host = storageUsageEnv('STORAGE_USAGE_DB_HOST', storageUsageEnv('DB_HOST', 'host.docker.internal'));
    $port = (int)storageUsageEnv('STORAGE_USAGE_DB_PORT', storageUsageEnv('DB_PORT', '5432'));

    $external = static function (string $key, string $label, string $db, string $user) use ($host, $port): array {
        return [
            'label'    => $label,
            'type'     => 'external',
            'host'     => storageUsageEnv('STORAGE_USAGE_' . strtoupper($key) . '_DB_HOST', $host),
            'port'     => (int)storageUsageEnv('STORAGE_USAGE_' . strtoupper($key) . '_DB_PORT', (string)$port),
            'db'       => storageUsageEnv('STORAGE_USAGE_' . strtoupper($key) . '_DB', $db),
            'user'     => storageUsageEnv('STORAGE_USAGE_' . strtoupper($key) . '_DB_USER', $key === 'dawarich' ? storageUsageEnvFilesValue(['/opt/docker/dawarich/.env'], ['DATABASE_USERNAME']) ?: $user : $user),
            'password' => storageUsagePassword($key),
        ];
    };

    return [
        'cloud'   => ['label' => 'Cloud', 'type' => 'internal'],
        'immich'  => $external('immich', 'Immich', 'immich', 'postgres'),
        'gitea'   => $external('gitea', 'Gitea', 'gitea', 'gitea'),
        'dawarich' => $external('dawarich', 'Dawarich', 'dawarich_development', 'postgres'),
    ];
}

/** Read-only PDO to an external application database */
function storageUsageExternalPdo(array $def, int $timeoutMs): PDO {
    $seconds = max(1, intdiv($timeoutMs, 1000));
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;connect_timeout=%d', $def['host'], (int)$def['port'], $def['db'], $seconds);
    $pdo = new PDO($dsn, (string)$def['user'], (string)($def['password'] ?? ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT            => $seconds,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('SET statement_timeout = ' . $timeoutMs);
    $pdo->exec('SET default_transaction_read_only = on');
    return $pdo;
}

/** Column list of a table (lowercased), empty when the table does not exist */
function storageUsageColumns(PDO $pdo, string $schema, string $table): array {
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns
                           WHERE table_schema = :s AND table_name = :t");
    $stmt->execute([':s' => $schema, ':t' => $table]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = strtolower((string)$row['column_name']);
    }
    return $out;
}

/** Quote a SQL identifier when it really exists */
function storageUsageIdent(array $columns, string $candidate, string $table): string {
    if (!in_array(strtolower($candidate), $columns, true)) {
        throw new RuntimeException(sprintf('%s.%s is missing', $table, $candidate));
    }
    return '"' . $candidate . '"';
}

// ─────────────────────────────────────────────────────────────────────────────
// Collectors
// ─────────────────────────────────────────────────────────────────────────────

/** Cloud: the cloud app already caches per-user totals in its own table */
function storageUsageCollectCloud(): array {
    $db = getDB();
    $pdo = $db->getPdo();
    if (!in_array('root_total_bytes', storageUsageColumns($pdo, 'public', 'cloud_storage_usage_stats'), true)) {
        throw new RuntimeException('cloud_storage_usage_stats not initialised yet');
    }
    $sql = "SELECT s.email AS email, COALESCE(u.name, s.email) AS name,
                   s.root_total_bytes AS bytes, s.updated_at AS measured_at
            FROM cloud_storage_usage_stats s
            LEFT JOIN users u ON lower(u.email) = lower(s.email)";
    $rows = [];
    foreach ($pdo->query($sql)->fetchAll() as $r) {
        $bytes = max(0, (int)$r['bytes']);
        if ($bytes <= 0) continue;
        $rows[] = [
            'email'       => (string)$r['email'],
            'name'        => (string)$r['name'],
            'bytes'       => $bytes,
            'items'       => 0,
            'measured_at' => (int)$r['measured_at'],
        ];
    }
    return $rows;
}

/** Immich: original file sizes per owner, from asset_exif (indexed lookup, ~60 ms on 35k assets) */
function storageUsageCollectImmich(PDO $pdo): array {
    $users = storageUsageColumns($pdo, 'public', 'user');
    $asset = storageUsageColumns($pdo, 'public', 'asset');
    $exif  = storageUsageColumns($pdo, 'public', 'asset_exif');

    $emailCol   = storageUsageIdent($users, 'email', 'user');
    $nameExpr   = in_array('name', $users, true) ? 'u."name"' : "''";
    $userId     = storageUsageIdent($users, 'id', 'user');
    $ownerCol   = storageUsageIdent($asset, 'ownerId', 'asset');
    $assetId    = storageUsageIdent($asset, 'id', 'asset');
    $exifAsset  = storageUsageIdent($exif, 'assetId', 'asset_exif');
    $exifSize   = storageUsageIdent($exif, 'fileSizeInByte', 'asset_exif');
    $liveFilter = in_array('deletedat', $asset, true) ? ' AND a."deletedAt" IS NULL' : '';
    $userFilter = in_array('deletedat', $users, true) ? ' AND u."deletedAt" IS NULL' : '';

    $sql = "SELECT u.$emailCol AS email, $nameExpr AS name,
                   COALESCE(SUM(x.bytes), 0)::bigint AS bytes,
                   COALESCE(SUM(x.items), 0)::bigint AS items
            FROM \"user\" u
            LEFT JOIN (
                SELECT a.$ownerCol AS owner,
                       COALESCE(SUM(e.$exifSize), 0)::bigint AS bytes,
                       COUNT(*)::bigint AS items
                FROM asset a
                LEFT JOIN asset_exif e ON e.$exifAsset = a.$assetId
                WHERE true$liveFilter
                GROUP BY a.$ownerCol
            ) x ON x.owner = u.$userId
            WHERE COALESCE(u.$emailCol, '') <> ''$userFilter
            GROUP BY u.$emailCol, $nameExpr";
    return storageUsageRowsFromPdo($pdo, $sql);
}

/** Gitea: repository objects (KB) plus LFS objects per account */
function storageUsageCollectGitea(PDO $pdo): array {
    $repo = storageUsageColumns($pdo, 'public', 'repository');
    $user = storageUsageColumns($pdo, 'public', 'user');

    $ownerCol = storageUsageIdent($repo, 'owner_id', 'repository');
    $sizeCol  = storageUsageIdent($repo, 'size', 'repository');
    $lfsCol   = in_array('lfs_size', $repo, true) ? 'r."lfs_size"' : '0';
    $idCol    = storageUsageIdent($repo, 'id', 'repository');
    $isFork   = in_array('is_fork', $repo, true) ? ' AND r."is_fork" = false' : '';

    $userId   = storageUsageIdent($user, 'id', 'user');
    $emailCol = storageUsageIdent($user, 'email', 'user');
    $nameExpr = in_array('name', $user, true) ? 'u."name"' : "''";
    $keyExpr  = "NULLIF(btrim(COALESCE(NULLIF(u.$emailCol, ''), $nameExpr)), '')";

    $sql = "SELECT $keyExpr AS email,
                   $nameExpr AS name,
                   (COALESCE(SUM(r.$sizeCol), 0) + COALESCE(SUM(COALESCE($lfsCol, 0)), 0))::bigint * 1024 AS bytes,
                   COUNT(r.$idCol)::bigint AS items
            FROM repository r
            JOIN \"user\" u ON u.$userId = r.$ownerCol
            WHERE true$isFork
            GROUP BY u.$emailCol, $nameExpr";
    return storageUsageRowsFromPdo($pdo, $sql);
}

/**
 * Dawarich: photos live in ActiveStorage, so usage = blob bytes reachable from a
 * user's records (photos when the assets table exists, imports, exports).
 */
function storageUsageCollectDawarich(PDO $pdo): array {
    $attachments = storageUsageColumns($pdo, 'public', 'active_storage_attachments');
    $blobs       = storageUsageColumns($pdo, 'public', 'active_storage_blobs');
    $users       = storageUsageColumns($pdo, 'public', 'users');

    $blobId   = storageUsageIdent($blobs, 'id', 'active_storage_blobs');
    $blobSize = storageUsageIdent($blobs, 'byte_size', 'active_storage_blobs');
    $attId    = storageUsageIdent($attachments, 'record_id', 'active_storage_attachments');
    $attType  = storageUsageIdent($attachments, 'record_type', 'active_storage_attachments');
    $attBlob  = storageUsageIdent($attachments, 'blob_id', 'active_storage_attachments');
    $userId   = storageUsageIdent($users, 'id', 'users');
    $emailCol = storageUsageIdent($users, 'email', 'users');
    $nameCols = array_filter([
        in_array('first_name', $users, true) ? 'first_name' : '',
        in_array('last_name', $users, true) ? 'last_name' : '',
    ]);
    $nameExpr = $nameCols ? "NULLIF(trim(concat_ws(' ', " . implode(', ', array_map(static fn($c) => 'u."' . $c . '"', $nameCols)) . ")), '')" : "''";

    // owner table per attachment record type
    $owners = [];
    foreach (['assets' => 'Asset', 'imports' => 'Import', 'exports' => 'Export'] as $table => $recordType) {
        $cols = storageUsageColumns($pdo, 'public', $table);
        if (!$cols) continue;
        $ownerCol = in_array('user_id', $cols, true) ? 'user_id' : (in_array('owner_id', $cols, true) ? 'owner_id' : '');
        if ($ownerCol === '' || !in_array('id', $cols, true)) continue;
        $owners[] = sprintf(
            "SELECT t.%s AS user_id, a.%s AS blob_id FROM %s t JOIN active_storage_attachments a ON a.%s = t.id AND a.%s = '%s'",
            $ownerCol, $attBlob, $table, $attId, $attType, $recordType
        );
    }
    if (!$owners) {
        throw new RuntimeException('no dawarich tables with a user owner column (photos not imported yet)');
    }

    $owned = '(' . implode(' UNION ', $owners) . ')';
    $sql = "SELECT u.$emailCol AS email, $nameExpr AS name,
                   COALESCE(SUM(b.$blobSize), 0)::bigint AS bytes,
                   COUNT(*)::bigint AS items
            FROM $owned o
            JOIN active_storage_blobs b ON b.$blobId = o.blob_id
            JOIN users u ON u.$userId = o.user_id
            WHERE COALESCE(u.$emailCol, '') <> ''
            GROUP BY u.$emailCol, $nameExpr";
    // DISTINCT union of (user, blob) already removes duplicate attachments per record type
    return storageUsageRowsFromPdo($pdo, $sql);
}

// ─────────────────────────────────────────────────────────────────────────────
// Orchestration
// ─────────────────────────────────────────────────────────────────────────────

function storageUsageRowsFromPdo(PDO $pdo, string $sql): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $email = trim((string)($r['email'] ?? ''));
        if ($email === '') continue;
        $bytes = max(0, (int)($r['bytes'] ?? 0));
        $items = max(0, (int)($r['items'] ?? 0));
        if ($bytes <= 0 && $items <= 0) continue;
        $rows[] = [
            'email' => $email,
            'name'  => (string)($r['name'] ?? ''),
            'bytes' => $bytes,
            'items' => $items,
        ];
    }
    return $rows;
}

/**
 * Collect one source.
 * Returns ['status' => 'ok'|'unavailable'|'error', 'message' => string, 'rows' => array].
 * Callers must keep the previous cache when the status is not 'ok'.
 */
function storageUsageCollectSource(string $key, ?array $def = null, ?int $timeoutMs = null): array {
    $defs = storageUsageSourceDefs();
    if (!isset($defs[$key])) {
        return ['status' => 'error', 'message' => 'unknown source ' . $key, 'rows' => []];
    }
    $def = $def ?? $defs[$key];
    $timeoutMs = $timeoutMs ?? (int)storageUsageEnv('STORAGE_USAGE_TIMEOUT_MS', (string)STORAGE_USAGE_DEFAULT_TIMEOUT_MS);

    try {
        if ($def['type'] === 'internal') {
            $rows = storageUsageCollectCloud();
        } else {
            $rows = storageUsageCollectSourceExternal($key, $def, $timeoutMs);
        }
        return ['status' => 'ok', 'message' => '', 'rows' => $rows];
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        $soft = ['does not exist', 'Undefined', 'password authentication failed', 'no pg_hba.conf entry', 'no password supplied', 'connection refused', 'connection timed out', 'could not connect', 'name or service not known', 'no route to host', 'database system is starting up'];
        foreach ($soft as $needle) {
            if (stripos($msg, $needle) !== false) {
                if ($needle === 'password authentication failed') {
                    $msg .= ' — set STORAGE_USAGE_' . strtoupper($key) . '_DB_PASSWORD';
                }
                return ['status' => 'unavailable', 'message' => $msg, 'rows' => []];
            }
        }
        return ['status' => 'error', 'message' => $msg, 'rows' => []];
    } catch (Throwable $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'rows' => []];
    }
}

function storageUsageCollectSourceExternal(string $key, array $def, int $timeoutMs): array {
    $pdo = storageUsageExternalPdo($def, $timeoutMs);
    switch ($key) {
        case 'immich':  return storageUsageCollectImmich($pdo);
        case 'gitea':   return storageUsageCollectGitea($pdo);
        case 'dawarich': return storageUsageCollectDawarich($pdo);
    }
    throw new RuntimeException('no collector for source ' . $key);
}

/** Human label for a source key (used by the admin panel) */
function storageUsageSourceLabel(string $key): string {
    $defs = storageUsageSourceDefs();
    return (string)($defs[$key]['label'] ?? ucfirst($key));
}

/** Compact human size for logs and messages */
function storageUsageHumanBytes(int $bytes): string {
    $bytes = max(0, $bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $exp = $bytes > 0 ? min((int)floor(log($bytes, 1024)), count($units) - 1) : 0;
    return round($bytes / (1024 ** $exp), 2) . ' ' . $units[$exp];
}
