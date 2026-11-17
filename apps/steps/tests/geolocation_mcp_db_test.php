<?php
/**
 * Integration test for read-only MCP tools and family capabilities (task 29).
 *
 *   php apps/steps/tests/geolocation_mcp_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_permissions.php';
require_once dirname(__DIR__) . '/core/geolocation_mcp.php';

function geo_mcp_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-geo-mcp@example.test';
$viewer = 'qa-geo-mcp-family@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, string $owner): void {
    foreach (['geo_location_grants', 'geo_family_members', 'geo_family_groups', 'geo_audit_events', 'geo_immich_assets', 'geo_immich_connections', 'geo_visits', 'geo_places', 'geo_trips', 'geo_stops', 'geo_location_points', 'geo_devices', 'geo_statistics_daily', 'geo_statistics_cache', 'geo_processor_runs', 'geo_achievements', 'geo_achievement_settings'] as $table) {
        try { $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]); } catch (Throwable $e) {}
    }
    try { $pdo->prepare('DELETE FROM geo_location_grants WHERE owner_email = :owner OR viewer_email = :owner')->execute([':owner' => $owner]); } catch (Throwable $e) {}
};
$cleanup($pdo, $owner);
$cleanup($pdo, $viewer);

$device = StepsGeolocationMcp::uuid();
$otherDevice = StepsGeolocationMcp::uuid();
$placeId = StepsGeolocationMcp::uuid();
$connectionId = StepsGeolocationMcp::uuid();
$assetId = StepsGeolocationMcp::uuid();
$lat = 50.44444;
$lon = 30.55555;

try {
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA MCP', 'android', 'mobile', 'active')")
        ->execute([':id' => $device, ':owner' => $owner]);
    $pdo->prepare("INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, quality_state)
                   VALUES(:id, :owner, :device, :event, 'mobile', now(), ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 'accepted')")
        ->execute([':id' => StepsGeolocationMcp::uuid(), ':owner' => $owner, ':device' => $device, ':event' => StepsGeolocationMcp::uuid(), ':lon' => $lon, ':lat' => $lat]);
    // An old point must not make a current-only grant leak an old fix.
    $pdo->prepare("INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, quality_state)
                   VALUES(:id, :owner, :device, :event, 'mobile', now() - interval '2 days', ST_SetSRID(ST_MakePoint(20.1, 40.1), 4326), 'accepted')")
        ->execute([':id' => StepsGeolocationMcp::uuid(), ':owner' => $owner, ':device' => $device, ':event' => StepsGeolocationMcp::uuid()]);

    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, timezone) VALUES(:id, :owner, 'Cafe', 'cafe', ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 60, 'UTC')")
        ->execute([':id' => $placeId, ':owner' => $owner, ':lon' => $lon, ':lat' => $lat]);
    // Fixed historical interval: background derivation workers only rewrite
    // recent windows, so this fixture stays deterministic.
    $pdo->prepare("INSERT INTO geo_visits(id, user_email, place_id, arrived_at, departed_at, confidence, state) VALUES(:id, :owner, :place, '2026-03-01T11:00:00Z', '2026-03-01T12:00:00Z', 0.9, 'ready')")
        ->execute([':id' => StepsGeolocationMcp::uuid(), ':owner' => $owner, ':place' => $placeId]);
    $pdo->prepare("INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, duration_s)
                   VALUES(:id, :owner, '2026-03-01T09:00:00Z', '2026-03-01T10:30:00Z', ST_SetSRID(ST_GeomFromText('LINESTRING(30.5 50.4,30.6 50.5)'), 4326), 1200, 5400)")
        ->execute([':id' => StepsGeolocationMcp::uuid(), ':owner' => $owner]);

    $pdo->prepare("INSERT INTO geo_immich_connections(id, user_email, server_url_ciphertext, credential_ciphertext, state) VALUES(:id, :owner, 'x', 'y', 'active')")
        ->execute([':id' => $connectionId, ':owner' => $owner]);
    $pdo->prepare("INSERT INTO geo_immich_assets(id, user_email, connection_id, immich_asset_id, captured_at, position, state, original_filename)
                   VALUES(:id, :owner, :connection, 'asset-1', now() - interval '1 minute', ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 'matched', 'photo.jpg')")
        ->execute([':id' => $assetId, ':owner' => $owner, ':connection' => $connectionId, ':lon' => $lon, ':lat' => $lat]);

    // Read-only allowlist rejects mutations/exports/shares before dispatch.
    foreach (['delete_point', 'export_data', 'share_location', 'create_place', 'grant_access', 'purge_account'] as $forbidden) {
        try {
            StepsGeolocationMcp::run($pdo, $owner, $forbidden, [], 'qa');
            throw new RuntimeException('mutation tool dispatched: ' . $forbidden);
        } catch (StepsGeolocationMcpError $e) {
            geo_mcp_assert($e->errorCode === 'mcp_read_only' && $e->status === 403, 'mutation not rejected before dispatch: ' . $forbidden);
        }
    }

    // Summary coordinates are rounded; detail returns the permitted precision.
    $summary = StepsGeolocationMcp::run($pdo, $owner, 'get_current_location', [], 'qa-summary');
    geo_mcp_assert($summary['read_only'] === true && $summary['result_count'] === 1, 'current location missing');
    geo_mcp_assert(abs($summary['result']['location']['latitude'] - 50.444) < 1e-9 && abs($summary['result']['location']['longitude'] - 30.556) < 1e-9, 'summary not rounded to 3 decimals');
    $detailed = StepsGeolocationMcp::run($pdo, $owner, 'get_current_location', ['detail' => true], 'qa-detail');
    geo_mcp_assert(abs($detailed['result']['location']['latitude'] - $lat) < 1e-5, 'detailed latitude wrong');
    geo_mcp_assert(abs($detailed['result']['location']['longitude'] - $lon) < 1e-5, 'detailed longitude wrong');

    // No grant means no family access.
    try {
        StepsGeolocationMcp::run($pdo, $viewer, 'get_current_location', ['target' => $owner], 'qa-denied');
        throw new RuntimeException('family access without a grant succeeded');
    } catch (StepsGeolocationMcpError $e) {
        geo_mcp_assert($e->errorCode === 'family_access_denied' && $e->status === 403, 'family denial wrong');
    }

    // A recent grant with 500 m precision returns rounded coordinates only.
    StepsGeolocationPermissions::grant($pdo, $owner, [
        'viewer_email' => $viewer,
        'access_level' => 'recent',
        'precision_m' => 500,
        'consent' => true,
        'reason' => 'QA family',
    ]);
    $family = StepsGeolocationMcp::run($pdo, $viewer, 'get_current_location', ['target' => $owner], 'qa-family');
    geo_mcp_assert($family['target_is_self'] === false && $family['access_level'] === 'recent', 'family scope not applied');
    geo_mcp_assert($family['precision_decimals'] === 3, 'grant precision not translated');
    geo_mcp_assert(abs($family['result']['location']['latitude'] - 50.444) < 1e-9 && abs($family['result']['location']['longitude'] - 30.556) < 1e-9, 'family coordinates not rounded');

    // A places-only grant can read places/visits but no current fix, trips or stats.
    StepsGeolocationPermissions::grant($pdo, $owner, [
        'viewer_email' => $viewer,
        'access_level' => 'places',
        'precision_m' => 0,
        'consent' => true,
    ]);
    $places = StepsGeolocationMcp::run($pdo, $viewer, 'search_places', ['target' => $owner, 'from' => '2026-03-01T00:00:00Z', 'to' => '2026-03-02T00:00:00Z'], 'qa-places');
    geo_mcp_assert(count($places['result']['places']) === 1, 'places-only place search failed');
    foreach (['get_current_location', 'get_trips', 'get_statistics'] as $deniedTool) {
        try {
            StepsGeolocationMcp::run($pdo, $viewer, $deniedTool, ['target' => $owner], 'qa-places');
            throw new RuntimeException('places-only grant allowed ' . $deniedTool);
        } catch (StepsGeolocationMcpError $e) {
            geo_mcp_assert($e->errorCode === 'places_only_scope_denied' && $e->status === 403, 'places-only denial wrong for ' . $deniedTool);
        }
    }
    $timeline = StepsGeolocationMcp::run($pdo, $viewer, 'get_timeline', ['target' => $owner, 'from' => '2026-03-01T00:00:00Z', 'to' => '2026-03-02T00:00:00Z'], 'qa-timeline');
    $kinds = array_values(array_unique(array_column($timeline['result']['entries'], 'kind')));
    geo_mcp_assert($kinds === ['visit'], 'places-only timeline exposed non-visit entries');

    // Range and limit caps are enforced.
    try {
        StepsGeolocationMcp::run($pdo, $owner, 'get_timeline', ['from' => gmdate('c', time() - 40 * 86400), 'to' => gmdate('c')], 'qa-range');
        throw new RuntimeException('oversized MCP range accepted');
    } catch (StepsGeolocationMcpError $e) {
        geo_mcp_assert($e->errorCode === 'range_too_large', 'range cap error wrong');
    }
    $capped = StepsGeolocationMcp::run($pdo, $owner, 'search_visits', ['limit' => 500], 'qa-limit');
    geo_mcp_assert($capped['limited'] === 100, 'MCP result limit not capped');

    // Photos are owner-only and metadata-only.
    StepsGeolocationPermissions::grant($pdo, $owner, [
        'viewer_email' => $viewer,
        'access_level' => 'recent',
        'precision_m' => 0,
        'consent' => true,
    ]);
    try {
        StepsGeolocationMcp::run($pdo, $viewer, 'get_immich_photos', ['target' => $owner], 'qa-photo');
        throw new RuntimeException('family grant exposed photos');
    } catch (StepsGeolocationMcpError $e) {
        geo_mcp_assert($e->errorCode === 'photo_access_denied' && $e->status === 403, 'photo denial wrong');
    }
    $photos = StepsGeolocationMcp::run($pdo, $owner, 'get_immich_photos', [], 'qa-photo-self');
    geo_mcp_assert(count($photos['result']['photos']) === 1 && $photos['result']['photos'][0]['immich_asset_id'] === 'asset-1', 'own photos missing');
    geo_mcp_assert(!isset($photos['result']['photos'][0]['latitude']), 'summary photo response leaked coordinates');

    // Revocation is immediate and audit rows contain no raw coordinates.
    $grants = StepsGeolocationPermissions::grants($pdo, $owner);
    StepsGeolocationPermissions::revoke($pdo, $owner, (string)$grants[0]['id']);
    try {
        StepsGeolocationMcp::run($pdo, $viewer, 'search_places', ['target' => $owner], 'qa-revoked');
        throw new RuntimeException('revoked grant remained usable');
    } catch (StepsGeolocationMcpError $e) {
        geo_mcp_assert($e->errorCode === 'family_access_denied', 'revocation error wrong');
    }

    $audit = $pdo->prepare("SELECT context_json FROM geo_audit_events WHERE user_email = :owner AND event_type IN ('mcp.tool_used','mcp.shared_access')");
    $audit->execute([':owner' => $owner]);
    $rows = $audit->fetchAll(PDO::FETCH_COLUMN);
    geo_mcp_assert(count($rows) >= 4, 'MCP usage not audited');
    $walk = static function (array $value) use (&$walk): void {
        foreach ($value as $key => $child) {
            geo_mcp_assert(!in_array(strtolower((string)$key), ['latitude', 'longitude', 'lat', 'lon', 'position', 'geometry', 'bbox'], true), 'MCP audit leaked a coordinate key');
            if (is_array($child)) $walk($child);
        }
    };
    foreach ($rows as $json) {
        $decoded = json_decode((string)$json, true) ?: [];
        $walk($decoded);
        geo_mcp_assert(!str_contains((string)$json, '30.55555') && !str_contains((string)$json, '50.44444'), 'MCP audit leaked raw coordinates');
    }

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
    $cleanup($pdo, $viewer);
}
