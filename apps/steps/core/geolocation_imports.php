<?php
/**
 * Location import pipeline (task 19).
 *
 * Files live on the existing APPDATA volume, jobs and provenance in shared
 * PostgreSQL. Parsers stream canonical drafts into the same ingestion
 * repository used by mobile uploads. A file can be removed without touching
 * points still owned by another import or by the mobile app.
 */
require_once __DIR__ . '/geolocation_ingestion.php';
require_once __DIR__ . '/geolocation_import_parsers.php';
require_once __DIR__ . '/geolocation_quality.php';
require_once __DIR__ . '/geolocation_trips.php';
require_once __DIR__ . '/geolocation_statistics.php';

final class StepsGeolocationImports
{
    public const MAX_BYTES_DEFAULT = 268435456; // 256 MB

    public static function uuid(): string
    {
        return StepsGeolocationIngestion::uuid();
    }

    public static function maxBytes(): int
    {
        $raw = getenv('GEO_IMPORT_MAX_BYTES');
        $value = is_string($raw) && is_numeric($raw) ? (int)$raw : self::MAX_BYTES_DEFAULT;
        return max(1048576, min(2147483648, $value));
    }

    public static function baseDir(): string
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        $helper = dirname(__DIR__, 3) . '/storage_helper.php';
        if (is_file($helper)) require_once $helper;
        if (function_exists('appdata_app_base_dir')) {
            try { $cached = rtrim(appdata_app_base_dir('steps'), '/') . '/geo_imports'; return $cached; } catch (Throwable $e) {}
        }
        $cached = dirname(__DIR__) . '/upload_sessions/geo_imports';
        return $cached;
    }

    public static function userDir(string $owner): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._@-]/', '_', $owner) ?: 'user';
        return self::baseDir() . '/' . $safe;
    }

    public static function sanitizeFilename(string $filename): string
    {
        $name = basename(str_replace('\\', '/', $filename));
        $name = preg_replace('/[^A-Za-z0-9._ ()\-]+/', '_', $name) ?: 'import';
        return function_exists('mb_substr') ? mb_substr($name, 0, 180) : substr($name, 0, 180);
    }

    public static function acceptFile(string $tmpPath, string $filename, string $owner, array $options = [], bool $uploaded = true): array
    {
        if (!is_file($tmpPath)) throw new RuntimeException('upload_missing');
        $size = (int)filesize($tmpPath);
        if ($size <= 0) throw new RuntimeException('empty_file');
        if ($size > self::maxBytes()) throw new RuntimeException('file_too_large');
        $detected = StepsGeoImportParsers::detect($tmpPath, $filename);
        if ($detected === null) throw new RuntimeException('unsupported_format');
        $checksum = hash_file('sha256', $tmpPath);
        if (!is_string($checksum) || $checksum === '') throw new RuntimeException('checksum_failed');
        $pdo = app_main_db()->getPdo();
        $existing = $pdo->prepare("SELECT i.* FROM geo_import_files f JOIN geo_imports i ON i.id = f.import_id WHERE f.user_email = :owner AND f.checksum = :checksum AND f.deleted_at IS NULL AND f.state <> 'failed' AND i.state IN ('pending','processing','ready') ORDER BY f.created_at DESC LIMIT 1");
        $existing->execute([':owner' => $owner, ':checksum' => $checksum]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $result = self::shape($pdo, $owner, $row);
            $result['duplicate'] = true;
            return $result;
        }

        $importId = self::uuid();
        $fileId = self::uuid();
        $safeName = self::sanitizeFilename($filename);
        $dir = self::userDir($owner) . '/' . $importId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('storage_unavailable');
        $target = $dir . '/' . $safeName;
        $moved = $uploaded ? move_uploaded_file($tmpPath, $target) : @rename($tmpPath, $target);
        if (!$moved) { @copy($tmpPath, $target); $moved = is_file($target); }
        if (!$moved) { @rmdir($dir); throw new RuntimeException('storage_unavailable'); }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO geo_imports(id, user_email, source_type, state, progress_json, format, storage_dir, options_json) VALUES(:id, :owner, 'import', 'pending', '{}'::jsonb, :format, :dir, :options)")
                ->execute([':id' => $importId, ':owner' => $owner, ':format' => $detected['format'], ':dir' => $dir, ':options' => json_encode(['delete_source' => !empty($options['delete_source']), 'parser' => $detected['parser']], JSON_UNESCAPED_SLASHES)]);
            $pdo->prepare("INSERT INTO geo_import_files(id, import_id, user_email, filename, checksum, storage_path, state, source_format, size_bytes) VALUES(:id, :import, :owner, :filename, :checksum, :path, 'pending', :format, :size)")
                ->execute([':id' => $fileId, ':import' => $importId, ':owner' => $owner, ':filename' => $safeName, ':checksum' => $checksum, ':path' => $target, ':format' => $detected['format'], ':size' => $size]);
            $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, details_json) VALUES(:id, :owner, 'import', 'pending', :version, :details)")
                ->execute([':id' => self::uuid(), ':owner' => $owner, ':version' => 'imports-1', ':details' => json_encode(['import_id' => $importId], JSON_UNESCAPED_SLASHES)]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            @unlink($target);
            @rmdir($dir);
            throw $e;
        }
        $import = self::find($pdo, $owner, $importId);
        if ($import === null) throw new RuntimeException('import_failed');
        return $import;
    }

    public static function find(PDO $pdo, string $owner, string $id): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM geo_imports WHERE id = :id AND user_email = :owner AND deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::shape($pdo, $owner, $row) : null;
    }

    public static function history(PDO $pdo, string $owner, int $limit = 25): array
    {
        $stmt = $pdo->prepare("SELECT * FROM geo_imports WHERE user_email = :owner ORDER BY created_at DESC LIMIT $limit");
        $stmt->execute([':owner' => $owner]);
        return array_map(static fn(array $row): array => self::shape($pdo, $owner, $row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function shape(PDO $pdo, string $owner, array $row): array
    {
        $files = $pdo->prepare('SELECT id, filename, checksum, storage_path, state, source_format, size_bytes, point_count, accepted_count, duplicate_count, rejected_count, error_json, processed_at, deleted_at FROM geo_import_files WHERE import_id = :import AND user_email = :owner ORDER BY created_at ASC');
        $files->execute([':import' => $row['id'], ':owner' => $owner]);
        $fileRows = [];
        foreach ($files->fetchAll(PDO::FETCH_ASSOC) as $file) {
            $file['size_bytes'] = (int)$file['size_bytes'];
            foreach (['point_count', 'accepted_count', 'duplicate_count', 'rejected_count'] as $key) $file[$key] = (int)$file[$key];
            $file['error_json'] = json_decode((string)$file['error_json'], true) ?: [];
            $file['source_available'] = $file['storage_path'] !== '' && is_file($file['storage_path']);
            $fileRows[] = $file;
        }
        return [
            'id' => (string)$row['id'],
            'state' => (string)$row['state'],
            'format' => (string)($row['format'] ?? ''),
            'source_type' => (string)$row['source_type'],
            'progress' => json_decode((string)$row['progress_json'], true) ?: [],
            'options' => json_decode((string)($row['options_json'] ?? '{}'), true) ?: [],
            'errors' => json_decode((string)($row['error_json'] ?? '{}'), true) ?: [],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'files' => $fileRows,
        ];
    }

    /** Process one pending import. @return array{points:int,accepted:int,duplicate:int,rejected:int,failed:int} */
    public static function process(PDO $pdo, string $owner, string $importId): array
    {
        $import = $pdo->prepare("SELECT * FROM geo_imports WHERE id = :id AND user_email = :owner AND deleted_at IS NULL");
        $import->execute([':id' => $importId, ':owner' => $owner]);
        $row = $import->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('import_not_found');
        if (in_array((string)$row['state'], ['deleted', 'ready'], true)) return ['points' => 0, 'accepted' => 0, 'duplicate' => 0, 'rejected' => 0, 'failed' => 0];
        $options = json_decode((string)($row['options_json'] ?? '{}'), true) ?: [];
        $parserKey = (string)($options['parser'] ?? '');
        $pdo->prepare("UPDATE geo_imports SET state = 'processing', updated_at = now() WHERE id = :id")->execute([':id' => $importId]);

        $files = $pdo->prepare("SELECT * FROM geo_import_files WHERE import_id = :import AND user_email = :owner AND deleted_at IS NULL ORDER BY created_at ASC");
        $files->execute([':import' => $importId, ':owner' => $owner]);
        $fileRows = $files->fetchAll(PDO::FETCH_ASSOC);
        $totals = ['points' => 0, 'accepted' => 0, 'duplicate' => 0, 'rejected' => 0, 'failed' => 0];

        $deviceId = (string)($row['device_id'] ?? '');
        if (!StepsGeolocationDomain::isUuid($deviceId)) {
            $deviceId = self::uuid();
            $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, :name, :platform, 'import', 'active')")
                ->execute([':id' => $deviceId, ':owner' => $owner, ':name' => 'Import · ' . (string)($fileRows[0]['filename'] ?? 'file'), ':platform' => (string)$row['format']]);
            $pdo->prepare('UPDATE geo_imports SET device_id = :device WHERE id = :id')->execute([':device' => $deviceId, ':id' => $importId]);
        }

        foreach ($fileRows as $file) {
            if ((string)$file['state'] === 'ready') continue;
            $fileId = (string)$file['id'];
            try {
                $parser = $parserKey !== '' ? $parserKey : (StepsGeoImportParsers::detect((string)$file['storage_path'], (string)$file['filename'])['parser'] ?? '');
                if ($parser === '' || !is_file((string)$file['storage_path'])) throw new RuntimeException('source_missing');
                $counts = ['points' => 0, 'accepted' => 0, 'duplicate' => 0, 'rejected' => 0];
                $pdo->prepare("UPDATE geo_import_files SET state = 'processing' WHERE id = :id")->execute([':id' => $fileId]);
                $pdo->prepare("UPDATE geo_imports SET progress_json = :progress, updated_at = now() WHERE id = :id")
                    ->execute([':progress' => json_encode(['file' => $file['filename'], 'processed' => 0]), ':id' => $importId]);
                foreach (StepsGeoImportParsers::parse($parser, (string)$file['storage_path']) as $draft) {
                    $draft['device_id'] = $deviceId;
                    // Canonical event ids must stay UUIDs even when a source keys
                    // events by a non-UUID place or asset identifier.
                    $draft['client_event_id'] = StepsGeoImportParsers::stableEventId((string)$draft['source_namespace'], (string)$draft['source_at'], (float)$draft['latitude'], (float)$draft['longitude']);
                    $draft['source_type'] = 'import';
                    $draft['import_file_id'] = $fileId;
                    $draft['device_name'] = 'Import · ' . (string)$file['filename'];
                    $draft['platform'] = (string)$row['format'];
                    $counts['points']++;
                    $result = StepsGeolocationIngestion::point($pdo, $owner, $draft);
                    $status = (string)($result['status'] ?? 'rejected');
                    $counts[$status] = ($counts[$status] ?? 0) + 1;
                    if ($counts['points'] % 200 === 0) {
                        $pdo->prepare("UPDATE geo_import_files SET point_count = :points, accepted_count = :accepted, duplicate_count = :duplicate, rejected_count = :rejected WHERE id = :id")
                            ->execute([':points' => $counts['points'], ':accepted' => $counts['accepted'], ':duplicate' => $counts['duplicate'], ':rejected' => $counts['rejected'], ':id' => $fileId]);
                        $pdo->prepare('UPDATE geo_imports SET progress_json = :progress, updated_at = now() WHERE id = :id')
                            ->execute([':progress' => json_encode(['file' => $file['filename'], 'processed' => $counts['points']], JSON_UNESCAPED_SLASHES), ':id' => $importId]);
                    }
                }
                $pdo->prepare("UPDATE geo_import_files SET state = 'ready', point_count = :points, accepted_count = :accepted, duplicate_count = :duplicate, rejected_count = :rejected, processed_at = now() WHERE id = :id")
                    ->execute([':points' => $counts['points'], ':accepted' => $counts['accepted'], ':duplicate' => $counts['duplicate'], ':rejected' => $counts['rejected'], ':id' => $fileId]);
                foreach ($counts as $key => $value) $totals[$key] += $value;
            } catch (Throwable $e) {
                $totals['failed']++;
                $message = $e->getMessage() !== '' ? $e->getMessage() : 'parse_failed';
                $pdo->prepare("UPDATE geo_import_files SET state = 'failed', error_json = :error, processed_at = now() WHERE id = :id")
                    ->execute([':error' => json_encode(['error' => $message], JSON_UNESCAPED_SLASHES), ':id' => $fileId]);
                error_log('geolocation import file failed: ' . $message);
            }
        }

        $state = $totals['failed'] > 0 ? 'failed' : 'ready';
        $progress = $totals + ['finished_at' => gmdate('c')];
        $pdo->prepare("UPDATE geo_imports SET state = :state, progress_json = :progress, completed_at = now(), updated_at = now(), error_json = :errors WHERE id = :id")
            ->execute([':state' => $state, ':progress' => json_encode($progress, JSON_UNESCAPED_SLASHES), ':errors' => json_encode($totals['failed'] > 0 ? ['files_failed' => $totals['failed']] : new stdClass(), JSON_UNESCAPED_SLASHES), ':id' => $importId]);
        $pdo->prepare("UPDATE geo_processor_runs SET state = :state, details_json = :details, updated_at = now() WHERE user_email = :owner AND kind = 'import' AND state IN ('pending','processing') AND details_json->>'import_id' = :import")
            ->execute([':state' => $state === 'ready' ? 'ready' : 'failed', ':details' => json_encode($totals, JSON_UNESCAPED_SLASHES), ':owner' => $owner, ':import' => $importId]);

        if ($state === 'ready' && !empty($options['delete_source'])) self::deleteSourceFiles($pdo, $owner, $importId);
        try {
            StepsGeolocationQuality::enqueue($pdo, $owner);
            StepsGeolocationTrips::enqueue($pdo, $owner);
            StepsGeolocationStatistics::invalidate($pdo, $owner);
        } catch (Throwable $e) {
            error_log('import downstream enqueue: ' . $e->getMessage());
        }
        try {
            require_once __DIR__ . '/geolocation_notifications.php';
            StepsGeolocationNotifications::importComplete($pdo, $owner, $importId, (int)$totals['accepted'], (int)$totals['duplicate'], (int)$totals['rejected']);
        } catch (Throwable $e) {
            error_log('import notification: ' . $e->getMessage());
        }
        return $totals;
    }

    public static function deleteSourceFiles(PDO $pdo, string $owner, string $importId): int
    {
        $stmt = $pdo->prepare('SELECT id, storage_path FROM geo_import_files WHERE import_id = :import AND user_email = :owner AND deleted_at IS NULL');
        $stmt->execute([':import' => $importId, ':owner' => $owner]);
        $removed = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $file) {
            if ($file['storage_path'] !== '' && is_file($file['storage_path']) && @unlink($file['storage_path'])) $removed++;
        }
        $pdo->prepare('UPDATE geo_imports SET options_json = COALESCE(options_json, \'{}\'::jsonb) || \'{"source_deleted": true}\'::jsonb, updated_at = now() WHERE id = :id')->execute([':id' => $importId]);
        return $removed;
    }

    /** Remove one import and only its unshared points. */
    public static function remove(PDO $pdo, string $owner, string $importId): array
    {
        $import = self::find($pdo, $owner, $importId);
        if ($import === null) throw new RuntimeException('import_not_found');
        $files = $pdo->prepare('SELECT id FROM geo_import_files WHERE import_id = :import AND user_email = :owner AND deleted_at IS NULL');
        $files->execute([':import' => $importId, ':owner' => $owner]);
        $fileIds = $files->fetchAll(PDO::FETCH_COLUMN);
        $deletedPoints = 0;
        $keptPoints = 0;
        $pdo->beginTransaction();
        try {
            foreach ($fileIds as $fileId) {
                $points = $pdo->prepare('SELECT point_id FROM geo_import_points WHERE import_file_id = :file AND user_email = :owner');
                $points->execute([':file' => $fileId, ':owner' => $owner]);
                $pointIds = $points->fetchAll(PDO::FETCH_COLUMN);
                $pdo->prepare('DELETE FROM geo_import_points WHERE import_file_id = :file AND user_email = :owner')->execute([':file' => $fileId, ':owner' => $owner]);
                foreach ($pointIds as $pointId) {
                    $remaining = $pdo->prepare('SELECT COUNT(*) FROM geo_import_points WHERE point_id = :point AND user_email = :owner');
                    $remaining->execute([':point' => $pointId, ':owner' => $owner]);
                    $stillLinked = (int)$remaining->fetchColumn();
                    $source = $pdo->prepare('SELECT source_type FROM geo_location_points WHERE id = :point AND user_email = :owner');
                    $source->execute([':point' => $pointId, ':owner' => $owner]);
                    $sourceType = $source->fetchColumn();
                    if ($stillLinked === 0 && $sourceType === 'import') {
                        $pdo->prepare('DELETE FROM geo_location_points WHERE id = :point AND user_email = :owner')->execute([':point' => $pointId, ':owner' => $owner]);
                        $deletedPoints++;
                    } else {
                        $keptPoints++;
                    }
                }
                $pdo->prepare("UPDATE geo_import_files SET state = 'deleted', deleted_at = now() WHERE id = :id")->execute([':id' => $fileId]);
            }
            $pdo->prepare("UPDATE geo_imports SET state = 'deleted', deleted_at = now(), updated_at = now() WHERE id = :id AND user_email = :owner")->execute([':id' => $importId, ':owner' => $owner]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::deleteSourceFiles($pdo, $owner, $importId);
        try {
            StepsGeolocationQuality::enqueue($pdo, $owner);
            StepsGeolocationTrips::enqueue($pdo, $owner);
            StepsGeolocationStatistics::invalidate($pdo, $owner);
        } catch (Throwable $e) {
            error_log('import removal enqueue: ' . $e->getMessage());
        }
        return ['deleted_points' => $deletedPoints, 'kept_points' => $keptPoints];
    }
}
