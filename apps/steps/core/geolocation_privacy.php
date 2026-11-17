<?php
/**
 * Privacy controls: correction, range deletion, retention and account purge.
 *
 * All operations are owner-scoped. Derived data is rebuilt through the normal
 * processors; backups are removed by the ordinary backup retention policy and
 * can never be restored into another tenant.
 */
require_once __DIR__ . '/geolocation_security.php';
require_once __DIR__ . '/geolocation_domain.php';
require_once __DIR__ . '/geolocation_observability.php';

final class StepsGeolocationPrivacyError extends RuntimeException
{
    public $errorCode;
    public $status;

    public function __construct(string $errorCode, int $status = 400)
    {
        $this->errorCode = $errorCode;
        $this->status = $status;
        parent::__construct($errorCode);
    }
}

final class StepsGeolocationPrivacy
{
    public const MAX_RANGE_DAYS = 3660;
    public const PURGE_CONFIRMATION = 'PURGE';

    public static function uuid(): string
    {
        return StepsGeolocationSecurity::uuid();
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) return $cache[$table];
        $stmt = $pdo->prepare('SELECT to_regclass(:name) IS NOT NULL');
        $stmt->execute([':name' => 'public.' . $table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    }

    private static function parseTime(?string $value, string $field): int
    {
        $value = trim((string)$value);
        if ($value === '') throw new StepsGeolocationPrivacyError('invalid_' . $field);
        $ts = strtotime($value);
        if ($ts === false) throw new StepsGeolocationPrivacyError('invalid_' . $field);
        return $ts;
    }

    private static function interval(?string $from, ?string $to): array
    {
        $fromAt = self::parseTime($from, 'from');
        $toAt = self::parseTime($to, 'to');
        if ($toAt <= $fromAt) throw new StepsGeolocationPrivacyError('invalid_range');
        if ($toAt - $fromAt > self::MAX_RANGE_DAYS * 86400) throw new StepsGeolocationPrivacyError('range_too_large');
        return [gmdate('c', $fromAt), gmdate('c', $toAt), $fromAt, $toAt];
    }

    /** @return array<string,int> */
    public static function counts(PDO $pdo, string $owner): array
    {
        $tables = [
            'points' => 'geo_location_points',
            'devices' => 'geo_devices',
            'trips' => 'geo_trips',
            'places' => 'geo_places',
            'visits' => 'geo_visits',
            'imports' => 'geo_imports',
            'exports' => 'geo_exports',
            'immich_assets' => 'geo_immich_assets',
            'api_keys' => 'geo_api_keys',
            'audit_events' => 'geo_audit_events',
        ];
        $counts = [];
        foreach ($tables as $label => $table) {
            if (!self::tableExists($pdo, $table)) { $counts[$label] = 0; continue; }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE user_email = :owner");
            $stmt->execute([':owner' => $owner]);
            $counts[$label] = (int)$stmt->fetchColumn();
        }
        return $counts;
    }

    public static function policy(PDO $pdo, string $owner): array
    {
        return StepsGeolocationSecurity::policy($pdo, $owner);
    }

    public static function savePolicy(PDO $pdo, string $owner, array $input): array
    {
        return StepsGeolocationSecurity::savePolicy($pdo, $owner, $input);
    }

    /** Correct a single owner point; other tenants are never addressable. */
    public static function correctPoint(PDO $pdo, string $owner, string $id, $latitude, $longitude, ?string $observedAt = null): array
    {
        if (!StepsGeolocationDomain::isUuid($id)) throw new StepsGeolocationPrivacyError('invalid_point');
        if (!is_numeric($latitude) || !is_numeric($longitude) || !StepsGeolocationDomain::isCoordinate((float)$latitude, (float)$longitude)) {
            throw new StepsGeolocationPrivacyError('invalid_coordinate');
        }
        $stmt = $pdo->prepare('SELECT id, source_at FROM geo_location_points WHERE user_email = :owner AND id = :id');
        $stmt->execute([':owner' => $owner, ':id' => $id]);
        $point = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$point) throw new StepsGeolocationPrivacyError('point_not_found', 404);
        $at = $observedAt !== null && trim($observedAt) !== '' ? gmdate('c', self::parseTime($observedAt, 'observed_at')) : (string)$point['source_at'];
        $correction = json_encode([
            'corrected_at' => gmdate('c'),
            'source' => 'manual',
        ], JSON_UNESCAPED_SLASHES);
        $pdo->prepare(
            "UPDATE geo_location_points
                SET position = ST_SetSRID(ST_MakePoint(CAST(:lon AS double precision), CAST(:lat AS double precision)), 4326),
                    source_at = CAST(:at AS timestamptz),
                    quality_state = 'accepted', quality_reason = 'manual_correction',
                    correction_json = correction_json || CAST(:correction AS jsonb),
                    received_at = now()
              WHERE user_email = :owner AND id = :id"
        )->execute([
            ':lon' => (float)$longitude, ':lat' => (float)$latitude, ':at' => $at,
            ':correction' => $correction, ':owner' => $owner, ':id' => $id,
        ]);
        self::invalidate($pdo, $owner, $at, gmdate('c', strtotime($at) + 86400));
        StepsGeolocationSecurity::audit($pdo, $owner, $owner, 'privacy.point_corrected', ['point_id' => $id]);
        StepsGeolocationObservability::invalidateOwner($owner);
        return ['point_id' => $id, 'corrected' => true, 'source_at' => $at];
    }

    /**
     * Delete points and derived rows overlapping a bounded interval.
     *
     * @return array<string,int>
     */
    public static function deleteRange(PDO $pdo, string $owner, ?string $from, ?string $to): array
    {
        [$fromAt, $toAt] = self::interval($from, $to);
        $counts = [];
        $pdo->beginTransaction();
        try {
            $statements = [
                'trips' => 'DELETE FROM geo_trips WHERE user_email = :owner AND started_at < :to AND ended_at >= :from',
                'visits' => 'DELETE FROM geo_visits WHERE user_email = :owner AND arrived_at < :to AND COALESCE(departed_at, arrived_at) >= :from',
                'stops' => 'DELETE FROM geo_stops WHERE user_email = :owner AND started_at < :to AND COALESCE(ended_at, started_at) >= :from',
                'geofence_events' => 'DELETE FROM geo_geofence_events WHERE user_email = :owner AND occurred_at >= :from AND occurred_at < :to',
                'matched_segments' => 'DELETE FROM geo_matched_segments WHERE user_email = :owner AND started_at < :to AND ended_at >= :from',
                'quality_corrections' => 'DELETE FROM geo_quality_corrections WHERE user_email = :owner',
                'points' => 'DELETE FROM geo_location_points WHERE user_email = :owner AND source_at >= :from AND source_at < :to',
            ];
            foreach ($statements as $label => $sql) {
                $stmt = $pdo->prepare($sql);
                $params = [':owner' => $owner];
                if (str_contains($sql, ':from')) $params[':from'] = $fromAt;
                if (str_contains($sql, ':to')) $params[':to'] = $toAt;
                $stmt->execute($params);
                $counts[$label] = $stmt->rowCount();
            }
            if (self::tableExists($pdo, 'geo_statistics_daily')) {
                $pdo->prepare('DELETE FROM geo_statistics_daily WHERE user_email = :owner AND day BETWEEN CAST(:from AS date) AND CAST(:to AS date)')
                    ->execute([':owner' => $owner, ':from' => substr($fromAt, 0, 10), ':to' => substr($toAt, 0, 10)]);
            }
            if (self::tableExists($pdo, 'geo_statistics_cache')) {
                $pdo->prepare('DELETE FROM geo_statistics_cache WHERE user_email = :owner')->execute([':owner' => $owner]);
            }
            self::invalidate($pdo, $owner, $fromAt, $toAt, false);
            StepsGeolocationSecurity::audit($pdo, $owner, $owner, 'privacy.range_deleted', $counts + ['from' => $fromAt, 'to' => $toAt]);
            $pdo->commit();
            StepsGeolocationObservability::invalidateOwner($owner);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return $counts;
    }

    /**
     * Purge every tenant-owned row and artifact. The caller must confirm.
     *
     * @return array<string,int>
     */
    public static function purge(PDO $pdo, string $owner, string $actor, string $confirmation): array
    {
        if (trim($confirmation) !== self::PURGE_CONFIRMATION) throw new StepsGeolocationPrivacyError('confirmation_required', 409);
        return self::purgeOwner($pdo, $owner, $actor);
    }

    public static function enqueuePurge(PDO $pdo, string $owner): string
    {
        $stmt = $pdo->prepare(
            "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, details_json)
             SELECT :id, :owner, 'privacy_purge', 'pending', 'privacy-1', :details
              WHERE NOT EXISTS (SELECT 1 FROM geo_processor_runs WHERE user_email = :owner AND kind = 'privacy_purge' AND state IN ('pending','processing'))"
        );
        $id = self::uuid();
        $stmt->execute([':id' => $id, ':owner' => $owner, ':details' => json_encode(['requested_at' => gmdate('c')])]);
        StepsGeolocationSecurity::audit($pdo, $owner, $owner, 'privacy.purge_queued', ['request_id' => $id]);
        return $id;
    }

    /** @return array<string,int> */
    private static function purgeOwner(PDO $pdo, string $owner, string $actor): array
    {
        $owner = StepsGeolocationSecurity::normalizeOwner($owner);
        $artifactPaths = [];
        if (self::tableExists($pdo, 'geo_artifacts')) {
            $stmt = $pdo->prepare('SELECT storage_path FROM geo_artifacts WHERE user_email = :owner AND storage_path <> \'\'');
            $stmt->execute([':owner' => $owner]);
            $artifactPaths = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $tables = [
            'geo_webhook_deliveries', 'geo_notification_outbox', 'geo_trip_photos', 'geo_trip_places',
            'geo_saved_views', 'geo_analytics_cache', 'geo_visits', 'geo_geofence_events', 'geo_geofences', 'geo_stops', 'geo_matched_segments',
            'geo_quality_corrections', 'geo_match_settings', 'geo_trips',
            'geo_places', 'geo_immich_assets', 'geo_immich_connections', 'geo_point_sources',
            'geo_import_points', 'geo_import_files', 'geo_imports', 'geo_location_points',
            'geo_devices', 'geo_render_jobs', 'geo_artifacts', 'geo_exports',
            'geo_statistics_daily', 'geo_statistics_cache', 'geo_achievements',
            'geo_achievement_settings', 'geo_map_preferences', 'geo_api_keys',
            'geo_notification_preferences', 'geo_webhooks', 'geo_processor_runs',
        ];
        $counts = [];
        $pdo->beginTransaction();
        try {
            foreach ($tables as $table) {
                if (!self::tableExists($pdo, $table)) continue;
                $count = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE user_email = :owner");
                $count->execute([':owner' => $owner]);
                $counts[$table] = (int)$count->fetchColumn();
                $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
            }
            if (self::tableExists($pdo, 'geo_location_grants')) {
                $stmt = $pdo->prepare('DELETE FROM geo_location_grants WHERE owner_email = :owner OR viewer_email = :owner');
                $stmt->execute([':owner' => $owner]);
                $counts['geo_location_grants'] = $stmt->rowCount();
            }
            if (self::tableExists($pdo, 'geo_family_invitations')) {
                $stmt = $pdo->prepare('DELETE FROM geo_family_invitations WHERE inviter_email = :owner OR invitee_email = :owner OR group_id IN (SELECT id FROM geo_family_groups WHERE owner_email = :owner)');
                $stmt->execute([':owner' => $owner]);
                $counts['geo_family_invitations'] = $stmt->rowCount();
            }
            if (self::tableExists($pdo, 'geo_family_members')) {
                $stmt = $pdo->prepare('DELETE FROM geo_family_members WHERE user_email = :owner');
                $stmt->execute([':owner' => $owner]);
                $counts['geo_family_members'] = $stmt->rowCount();
                $pdo->prepare('DELETE FROM geo_family_members WHERE group_id IN (SELECT id FROM geo_family_groups WHERE owner_email = :owner)')
                    ->execute([':owner' => $owner]);
            }
            if (self::tableExists($pdo, 'geo_family_groups')) {
                $stmt = $pdo->prepare('DELETE FROM geo_family_groups WHERE owner_email = :owner');
                $stmt->execute([':owner' => $owner]);
                $counts['geo_family_groups'] = $stmt->rowCount();
            }
            if (self::tableExists($pdo, 'geo_privacy_settings')) {
                $pdo->prepare('DELETE FROM geo_privacy_settings WHERE user_email = :owner')->execute([':owner' => $owner]);
            }
            if (self::tableExists($pdo, 'geo_audit_events')) {
                $stmt = $pdo->prepare('DELETE FROM geo_audit_events WHERE user_email = :owner');
                $stmt->execute([':owner' => $owner]);
                $counts['geo_audit_events'] = $stmt->rowCount();
            }
            StepsGeolocationSecurity::audit($pdo, $owner, $actor, 'privacy.account_purged', ['tables' => $counts]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        foreach ($artifactPaths as $path) {
            if ($path !== '' && is_file($path)) @unlink($path);
        }
        self::removeUserDir(class_exists('StepsGeolocationImports') ? StepsGeolocationImports::userDir($owner) : '');
        self::removeUserDir(class_exists('StepsGeolocationExports') ? StepsGeolocationExports::userDir($owner) : '');
        StepsGeolocationObservability::invalidateOwner($owner);
        return $counts;
    }

    private static function removeUserDir(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) return;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) @rmdir($item->getPathname());
            else @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    /** Queue the processors that rebuild derived data after a change. */
    private static function invalidate(PDO $pdo, string $owner, string $from, string $to): void
    {
        if (self::tableExists($pdo, 'geo_statistics_cache')) {
            $pdo->prepare('DELETE FROM geo_statistics_cache WHERE user_email = :owner')->execute([':owner' => $owner]);
        }
        foreach (['trips', 'stops', 'statistics', 'achievements'] as $kind) {
            try {
                $pdo->prepare(
                    "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, input_from, input_to, details_json)
                     SELECT :id, :owner, :kind, 'pending', 'privacy-1', :from, :to, '{}'::jsonb
                      WHERE NOT EXISTS (SELECT 1 FROM geo_processor_runs WHERE user_email = :owner AND kind = :kind AND state IN ('pending','processing'))"
                )->execute([':id' => self::uuid(), ':owner' => $owner, ':kind' => $kind, ':from' => $from, ':to' => $to]);
            } catch (Throwable $e) {
                error_log('privacy enqueue ' . $kind . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Apply per-tenant retention and prune old audit rows.
     *
     * @return array{owners:int,points:int,derived:int,audit:int}
     */
    public static function applyRetention(PDO $pdo): array
    {
        $result = ['owners' => 0, 'points' => 0, 'derived' => 0, 'audit' => 0];
        if (!self::tableExists($pdo, 'geo_privacy_settings')) return $result;
        $stmt = $pdo->query('SELECT * FROM geo_privacy_settings WHERE retention_days > 0 OR audit_retention_days > 0');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $settings) {
            $owner = StepsGeolocationSecurity::normalizeOwner((string)$settings['user_email']);
            $days = (int)$settings['retention_days'];
            $deleted = 0;
            if ($days > 0) {
                $cutoff = gmdate('c', time() - $days * 86400);
                $targets = [
                    'geo_location_points' => 'source_at < :cutoff',
                    'geo_trips' => 'ended_at < :cutoff',
                    'geo_visits' => 'COALESCE(departed_at, arrived_at) < :cutoff',
                    'geo_stops' => 'COALESCE(ended_at, started_at) < :cutoff',
                    'geo_geofence_events' => 'occurred_at < :cutoff',
                    'geo_immich_assets' => 'captured_at < :cutoff',
                    'geo_statistics_daily' => 'day < CAST(:cutoff AS date)',
                ];
                foreach ($targets as $table => $where) {
                    if (!self::tableExists($pdo, $table)) continue;
                    $del = $pdo->prepare("DELETE FROM $table WHERE user_email = :owner AND $where");
                    $del->execute([':owner' => $owner, ':cutoff' => $cutoff]);
                    $count = $del->rowCount();
                    $deleted += $count;
                    if ($table === 'geo_location_points') $result['points'] += $count;
                }
                if (self::tableExists($pdo, 'geo_statistics_cache')) {
                    $pdo->prepare('DELETE FROM geo_statistics_cache WHERE user_email = :owner')->execute([':owner' => $owner]);
                }
                self::invalidate($pdo, $owner, $cutoff, gmdate('c'));
                if ($deleted > 0) {
                    StepsGeolocationSecurity::audit($pdo, $owner, 'privacy-worker', 'privacy.retention_applied', [
                        'retention_days' => $days,
                        'rows_deleted' => $deleted,
                    ]);
                }
            }
            $auditDays = (int)$settings['audit_retention_days'];
            if ($auditDays > 0) {
                $cutoff = gmdate('c', time() - $auditDays * 86400);
                $del = $pdo->prepare('DELETE FROM geo_audit_events WHERE user_email = :owner AND created_at < :cutoff');
                $del->execute([':owner' => $owner, ':cutoff' => $cutoff]);
                $result['audit'] += $del->rowCount();
            }
            if ($deleted > 0 || $auditDays > 0) {
                $result['owners']++;
                $result['derived'] += $deleted;
            }
        }
        return $result;
    }

    /** Process queued account purges. */
    public static function processPending(PDO $pdo): int
    {
        if (!self::tableExists($pdo, 'geo_processor_runs')) return 0;
        $stmt = $pdo->query("SELECT id, user_email FROM geo_processor_runs WHERE kind = 'privacy_purge' AND state = 'pending' ORDER BY created_at LIMIT 10");
        $done = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $run) {
            try {
                self::purgeOwner($pdo, (string)$run['user_email'], 'privacy-worker');
                $done++;
            } catch (Throwable $e) {
                error_log('privacy purge ' . $run['id'] . ': ' . $e->getMessage());
                try {
                    $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', details_json = :details, updated_at = now() WHERE id = :id")
                        ->execute([':details' => json_encode(['error' => 'purge_failed']), ':id' => $run['id']]);
                } catch (Throwable $ignored) {}
            }
        }
        return $done;
    }

    public static function run(PDO $pdo): array
    {
        $purged = self::processPending($pdo);
        $retention = self::applyRetention($pdo);
        $expired = class_exists('StepsGeolocationExports') ? StepsGeolocationExports::cleanupExpired($pdo) : 0;
        return ['purged' => $purged, 'retention' => $retention, 'expired_exports' => $expired];
    }
}
