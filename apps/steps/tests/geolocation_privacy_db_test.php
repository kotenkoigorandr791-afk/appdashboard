<?php
/**
 * Integration test for privacy correction, deletion, retention and purge.
 *
 *   php apps/steps/tests/geolocation_privacy_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_privacy.php';
require_once dirname(__DIR__) . '/core/geolocation_exports.php';

function geo_privacy_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-geo-privacy@example.test';
$other = 'qa-geo-privacy-other@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$tables = [
    'geo_audit_events', 'geo_privacy_settings', 'geo_location_points', 'geo_devices', 'geo_trips',
    'geo_stops', 'geo_places', 'geo_visits', 'geo_geofences', 'geo_geofence_events',
    'geo_imports', 'geo_import_files', 'geo_import_points', 'geo_point_sources',
    'geo_exports', 'geo_artifacts', 'geo_immich_assets', 'geo_immich_connections',
    'geo_api_keys', 'geo_family_groups', 'geo_family_members', 'geo_family_invitations',
    'geo_location_grants', 'geo_statistics_daily', 'geo_statistics_cache', 'geo_achievements',
    'geo_achievement_settings', 'geo_processor_runs', 'geo_map_preferences', 'geo_notification_outbox',
    'geo_notification_preferences', 'geo_webhooks', 'geo_webhook_deliveries', 'geo_render_jobs', 'geo_trip_photos', 'geo_trip_places',
];
$cleanup = static function (PDO $pdo, string $owner) use ($tables): void {
    foreach ($tables as $table) {
        try { $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]); } catch (Throwable $e) {}
    }
    try { $pdo->prepare('DELETE FROM geo_location_grants WHERE owner_email = :owner OR viewer_email = :owner')->execute([':owner' => $owner]); } catch (Throwable $e) {}
    try { $pdo->prepare('DELETE FROM geo_family_members WHERE user_email = :owner')->execute([':owner' => $owner]); } catch (Throwable $e) {}
    try { $pdo->prepare('DELETE FROM geo_family_invitations WHERE inviter_email = :owner OR invitee_email = :owner')->execute([':owner' => $owner]); } catch (Throwable $e) {}
    try { $pdo->prepare('DELETE FROM geo_family_groups WHERE owner_email = :owner')->execute([':owner' => $owner]); } catch (Throwable $e) {}
    $dir = StepsGeolocationExports::userDir($owner);
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
        @rmdir($dir);
    }
};
$cleanup($pdo, $owner);
$cleanup($pdo, $other);

$device = StepsGeolocationPrivacy::uuid();
$otherDevice = StepsGeolocationPrivacy::uuid();
$pointA = StepsGeolocationPrivacy::uuid();
$pointB = StepsGeolocationPrivacy::uuid();
$place = StepsGeolocationPrivacy::uuid();
$trip = StepsGeolocationPrivacy::uuid();

try {
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA privacy', 'android', 'mobile', 'active')")
        ->execute([':id' => $device, ':owner' => $owner]);
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'Other privacy', 'android', 'mobile', 'active')")
        ->execute([':id' => $otherDevice, ':owner' => $other]);

    $insertPoint = $pdo->prepare(
        "INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, quality_state)
         VALUES(:id, :owner, :device, :event, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 'accepted')"
    );
    $insertPoint->execute([':id' => $pointA, ':owner' => $owner, ':device' => $device, ':event' => StepsGeolocationPrivacy::uuid(), ':at' => '2026-03-01T10:00:00Z', ':lon' => 30.5, ':lat' => 50.45]);
    $insertPoint->execute([':id' => $pointB, ':owner' => $owner, ':device' => $device, ':event' => StepsGeolocationPrivacy::uuid(), ':at' => '2026-03-02T10:00:00Z', ':lon' => 30.6, ':lat' => 50.46]);
    $insertPoint->execute([':id' => StepsGeolocationPrivacy::uuid(), ':owner' => $other, ':device' => $otherDevice, ':event' => StepsGeolocationPrivacy::uuid(), ':at' => '2026-03-01T10:00:00Z', ':lon' => 20.5, ':lat' => 50.5]);

    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, timezone) VALUES(:id, :owner, 'Cafe', 'cafe', ST_SetSRID(ST_MakePoint(30.5, 50.45), 4326), 60, 'UTC')")
        ->execute([':id' => $place, ':owner' => $owner]);
    $pdo->prepare("INSERT INTO geo_visits(id, user_email, place_id, arrived_at, departed_at, confidence, state) VALUES(:id, :owner, :place, '2026-03-01T11:00:00Z', '2026-03-01T12:00:00Z', 0.9, 'ready')")
        ->execute([':id' => StepsGeolocationPrivacy::uuid(), ':owner' => $owner, ':place' => $place]);
    $pdo->prepare("INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, duration_s) VALUES(:id, :owner, '2026-03-01T09:00:00Z', '2026-03-01T10:30:00Z', ST_SetSRID(ST_GeomFromText('LINESTRING(30.5 50.45,30.62 50.47)'), 4326), 1200, 5400)")
        ->execute([':id' => $trip, ':owner' => $owner]);

    // Policy bounds and defaults.
    $defaults = StepsGeolocationPrivacy::policy($pdo, $owner);
    geo_privacy_assert($defaults['summary_precision_decimals'] === 2 && $defaults['retention_days'] === 0, 'privacy defaults wrong');
    $saved = StepsGeolocationPrivacy::savePolicy($pdo, $owner, ['summary_precision_decimals' => 3, 'retention_days' => 30, 'audit_retention_days' => 90]);
    geo_privacy_assert($saved['summary_precision_decimals'] === 3 && $saved['retention_days'] === 30 && $saved['audit_retention_days'] === 90, 'policy not saved');
    try {
        StepsGeolocationPrivacy::savePolicy($pdo, $owner, ['summary_precision_decimals' => 9]);
        throw new RuntimeException('out-of-range precision accepted');
    } catch (StepsGeolocationSecurityError $e) {
        geo_privacy_assert($e->errorCode === 'invalid_precision', 'precision error wrong');
    }
    try {
        StepsGeolocationPrivacy::savePolicy($pdo, $owner, ['audit_retention_days' => 1]);
        throw new RuntimeException('short audit retention accepted');
    } catch (StepsGeolocationSecurityError $e) {
        geo_privacy_assert($e->errorCode === 'invalid_audit_retention', 'audit retention error wrong');
    }

    // Make point B the most recent, then correct it; another owner is untouched.
    $pdo->prepare("UPDATE geo_location_points SET source_at = now() WHERE id = :id AND user_email = :owner")->execute([':id' => $pointB, ':owner' => $owner]);
    $corrected = StepsGeolocationPrivacy::correctPoint($pdo, $owner, $pointB, 51.5, 31.5);
    geo_privacy_assert($corrected['corrected'] === true, 'correction not applied');
    $pos = $pdo->prepare('SELECT ST_Y(position) AS lat, ST_X(position) AS lon, quality_state, quality_reason FROM geo_location_points WHERE id = :id AND user_email = :owner');
    $pos->execute([':id' => $pointB, ':owner' => $owner]);
    $row = $pos->fetch(PDO::FETCH_ASSOC);
    geo_privacy_assert(abs((float)$row['lat'] - 51.5) < 1e-6 && abs((float)$row['lon'] - 31.5) < 1e-6, 'coordinates not corrected');
    geo_privacy_assert($row['quality_state'] === 'accepted' && $row['quality_reason'] === 'manual_correction', 'correction quality state wrong');
    try {
        StepsGeolocationPrivacy::correctPoint($pdo, $other, $pointB, 0, 0);
        throw new RuntimeException('cross-owner correction accepted');
    } catch (StepsGeolocationPrivacyError $e) {
        geo_privacy_assert($e->errorCode === 'point_not_found' && $e->status === 404, 'correction IDOR error wrong');
    }

    // Delete a bounded range: point A, overlapping trip and visit disappear.
    $deleted = StepsGeolocationPrivacy::deleteRange($pdo, $owner, '2026-03-01T00:00:00Z', '2026-03-02T00:00:00Z');
    geo_privacy_assert($deleted['points'] === 1 && $deleted['trips'] === 1 && $deleted['visits'] === 1, 'range deletion counts wrong');
    $remaining = $pdo->prepare('SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner');
    $remaining->execute([':owner' => $owner]);
    geo_privacy_assert((int)$remaining->fetchColumn() === 1, 'range deletion removed too much');
    $otherPoints = $pdo->prepare('SELECT COUNT(*) FROM geo_location_points WHERE user_email = :other');
    $otherPoints->execute([':other' => $other]);
    geo_privacy_assert((int)$otherPoints->fetchColumn() === 1, 'range deletion crossed tenants');
    try {
        StepsGeolocationPrivacy::deleteRange($pdo, $owner, '2026-03-02T00:00:00Z', '2025-03-02T00:00:00Z');
        throw new RuntimeException('inverted range accepted');
    } catch (StepsGeolocationPrivacyError $e) {
        geo_privacy_assert($e->errorCode === 'invalid_range', 'range error wrong');
    }

    // Retention removes only data older than the configured cutoff.
    $insertPoint->execute([':id' => StepsGeolocationPrivacy::uuid(), ':owner' => $owner, ':device' => $device, ':event' => StepsGeolocationPrivacy::uuid(), ':at' => '2020-01-01T00:00:00Z', ':lon' => 30.1, ':lat' => 50.1]);
    StepsGeolocationPrivacy::applyRetention($pdo);
    $old = $pdo->prepare("SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner AND source_at < now() - interval '30 days'");
    $old->execute([':owner' => $owner]);
    geo_privacy_assert((int)$old->fetchColumn() === 0, 'expired point not deleted');
    $fresh = $pdo->prepare('SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner');
    $fresh->execute([':owner' => $owner]);
    geo_privacy_assert((int)$fresh->fetchColumn() === 1, 'fresh point deleted by retention');

    // Purge requires explicit confirmation, removes files and leaves other tenants.
    $exportDir = StepsGeolocationExports::userDir($owner);
    if (!is_dir($exportDir)) mkdir($exportDir, 0775, true);
    $file = $exportDir . '/artifact.json';
    file_put_contents($file, '{"qa":true}');
    $exportId = StepsGeolocationPrivacy::uuid();
    $pdo->prepare("INSERT INTO geo_exports(id, user_email, format, scope_json, state) VALUES(:id, :owner, 'json', '{}'::jsonb, 'ready')")
        ->execute([':id' => $exportId, ':owner' => $owner]);
    $pdo->prepare("INSERT INTO geo_artifacts(id, user_email, export_id, kind, storage_path, checksum) VALUES(:id, :owner, :export, 'export', :path, 'qa')")
        ->execute([':id' => StepsGeolocationPrivacy::uuid(), ':owner' => $owner, ':export' => $exportId, ':path' => $file]);
    $pdo->prepare("INSERT INTO geo_api_keys(id, user_email, name, key_prefix, key_hash) VALUES(:id, :owner, 'QA', 'stp_qa', :hash)")
        ->execute([':id' => StepsGeolocationPrivacy::uuid(), ':owner' => $owner, ':hash' => hash('sha256', 'qa-' . $owner)]);

    try {
        StepsGeolocationPrivacy::purge($pdo, $owner, $owner, 'nope');
        throw new RuntimeException('purge without confirmation accepted');
    } catch (StepsGeolocationPrivacyError $e) {
        geo_privacy_assert($e->errorCode === 'confirmation_required' && $e->status === 409, 'purge confirmation error wrong');
    }
    $purged = StepsGeolocationPrivacy::purge($pdo, $owner, $owner, 'PURGE');
    geo_privacy_assert(($purged['geo_location_points'] ?? 0) >= 1, 'purge counts missing points');
    foreach (['geo_location_points', 'geo_devices', 'geo_places', 'geo_trips', 'geo_exports', 'geo_artifacts', 'geo_api_keys', 'geo_privacy_settings'] as $table) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE user_email = :owner");
        $check->execute([':owner' => $owner]);
        geo_privacy_assert((int)$check->fetchColumn() === 0, 'purge left rows in ' . $table);
    }
    geo_privacy_assert(!is_file($file), 'purge left an artifact file');
    $otherAfter = $pdo->prepare('SELECT COUNT(*) FROM geo_location_points WHERE user_email = :other');
    $otherAfter->execute([':other' => $other]);
    geo_privacy_assert((int)$otherAfter->fetchColumn() === 1, 'purge crossed tenants');
    $audit = $pdo->prepare("SELECT context_json FROM geo_audit_events WHERE user_email = :owner AND event_type = 'privacy.account_purged' ORDER BY created_at DESC LIMIT 1");
    $audit->execute([':owner' => $owner]);
    $context = (string)$audit->fetchColumn();
    geo_privacy_assert($context !== '', 'purge audit missing');
    $decoded = json_decode($context, true) ?: [];
    $walk = static function (array $value) use (&$walk): void {
        foreach ($value as $key => $child) {
            geo_privacy_assert(!in_array(strtolower((string)$key), ['latitude', 'longitude', 'lat', 'lon', 'position', 'geometry'], true), 'purge audit leaked coordinates');
            if (is_array($child)) $walk($child);
        }
    };
    $walk($decoded);

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
    $cleanup($pdo, $other);
}
