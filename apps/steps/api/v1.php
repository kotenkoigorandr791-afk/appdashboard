<?php
/**
 * Authenticated REST API v1 (task 28).
 *
 * Base path: /apps/steps/api/v1.php
 * Path form: /apps/steps/api/v1.php/<resource>[/<id>]
 * Query form: /apps/steps/api/v1.php?path=<resource>/<id>
 *
 * A session cookie (dashboard) or a scoped API key authenticates the request.
 * API keys are sent as `X-API-Key: stp_...` or `Authorization: Bearer stp_...`.
 * Every response includes api_version and request_id. No public-link endpoint
 * exists in this version.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_api.php';
require_once dirname(__DIR__) . '/core/geolocation_ingestion.php';
require_once dirname(__DIR__) . '/core/geolocation_permissions.php';
require_once dirname(__DIR__) . '/core/geolocation_exports.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';
require_once dirname(__DIR__) . '/core/geolocation_achievements.php';
require_once dirname(__DIR__) . '/core/geolocation_security.php';

public_api_bootstrap();
$pdo = app_main_db()->getPdo();
$requestId = StepsGeolocationApi::requestId();

/** Extract a presented API key from the supported headers. */
function steps_api_secret(): string
{
    $direct = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($direct !== '') return $direct;
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ($_SERVER['Authorization'] ?? ''));
    if (preg_match('/^\s*Bearer\s+(stp_[A-Za-z0-9]+)\s*$/i', $auth, $m)) return $m[1];
    return '';
}

$secret = steps_api_secret();
$key = null;
$owner = null;
if ($secret !== '') {
    $key = StepsGeolocationApi::authenticate($pdo, $secret);
    if ($key === null) StepsGeolocationApi::fail('invalid_api_key', 401);
    $owner = $key['user_email'];
} else {
    $owner = get_current_user_email();
    if (!$owner) StepsGeolocationApi::fail('unauthorized', 401);
    // Session callers act with the full scope set; API keys stay restricted.
    $key = ['id' => 'session', 'user_email' => $owner, 'scopes' => StepsGeolocationApi::SCOPES];
}
$owner = StepsGeolocationDomain::normalizeOwner((string)$owner);
$key['user_email'] = $owner;
$GLOBALS['public_api_user'] = ['email' => $owner];

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST' && $secret === '') {
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($origin !== '' && strcasecmp((string)parse_url($origin, PHP_URL_HOST), (string)$host) !== 0) {
        StepsGeolocationApi::fail('invalid_request', 403, ['reason' => 'forbidden_origin']);
    }
}

$path = trim((string)($_GET['path'] ?? ''), '/');
if ($path === '') {
    $path = trim((string)($_SERVER['PATH_INFO'] ?? ''), '/');
}
if ($path === '') {
    $uri = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
    if (preg_match('#/api/v1(?:\.php)?/(.+)$#', $uri, $m)) $path = trim($m[1], '/');
}
if ($path === '') $path = trim((string)($_GET['resource'] ?? ''), '/');
$segments = $path === '' ? [] : explode('/', $path);
$resource = strtolower(trim((string)($segments[0] ?? '')));
$id = trim((string)($segments[1] ?? ($_GET['id'] ?? '')));

public_api_rate_limit('api_v1_read', 600, 60);
if ($method === 'POST') public_api_rate_limit('api_v1_write', 120, 60);
if ($method === 'OPTIONS') StepsGeolocationApi::respond(['ok' => true], 204);

$params = $_GET;
if ($method === 'POST') {
    $body = public_api_body();
    if (!is_array($body)) StepsGeolocationApi::fail('invalid_body', 400);
    $params = array_merge($params, $body);
}

try {
    if ($resource === 'points') {
        if ($method === 'GET') {
            StepsGeolocationApi::requireScope($key, 'read:points');
            $result = StepsGeolocationApi::points($pdo, $owner, $params);
            StepsGeolocationSecurity::auditRead($pdo, $owner, 'points', (int)($result['total'] ?? 0), ['api' => 'v1']);
            StepsGeolocationApi::respond(['ok' => true] + $result);
        }
        if ($method === 'POST') {
            StepsGeolocationApi::requireScope($key, 'write:points');
            $points = isset($params['points']) && is_array($params['points']) ? $params['points'] : [$params];
            if (count($points) === 0 || count($points) > 500) StepsGeolocationApi::fail('invalid_request', 400, ['reason' => 'invalid_batch']);
            $results = [];
            foreach ($points as $point) {
                if (!is_array($point)) { $results[] = ['status' => 'rejected', 'error' => 'invalid_point']; continue; }
                $results[] = StepsGeolocationIngestion::point($pdo, $owner, $point);
            }
            StepsGeolocationApi::respond(['ok' => true, 'results' => $results], 202);
        }
        StepsGeolocationApi::fail('method_not_allowed', 405);
    }

    if ($resource === 'trips') {
        if ($method !== 'GET') StepsGeolocationApi::fail('method_not_allowed', 405);
        StepsGeolocationApi::requireScope($key, 'read:trips');
        if ($id !== '') {
            $trip = StepsGeolocationApi::trip($pdo, $owner, $id);
            StepsGeolocationSecurity::auditRead($pdo, $owner, 'trip', 1, ['api' => 'v1']);
            StepsGeolocationApi::respond(['ok' => true, 'trip' => $trip]);
        }
        $result = StepsGeolocationApi::trips($pdo, $owner, $params);
        StepsGeolocationSecurity::auditRead($pdo, $owner, 'trips', (int)($result['total'] ?? 0), ['api' => 'v1']);
        StepsGeolocationApi::respond(['ok' => true] + $result);
    }

    if ($resource === 'places') {
        if ($method !== 'GET') StepsGeolocationApi::fail('method_not_allowed', 405);
        StepsGeolocationApi::requireScope($key, 'read:places');
        $result = StepsGeolocationApi::places($pdo, $owner, $params);
        StepsGeolocationSecurity::auditRead($pdo, $owner, 'places', (int)($result['total'] ?? 0), ['api' => 'v1']);
        StepsGeolocationApi::respond(['ok' => true] + $result);
    }

    if ($resource === 'visits') {
        if ($method !== 'GET') StepsGeolocationApi::fail('method_not_allowed', 405);
        StepsGeolocationApi::requireScope($key, 'read:visits');
        $result = StepsGeolocationApi::visits($pdo, $owner, $params);
        StepsGeolocationSecurity::auditRead($pdo, $owner, 'visits', (int)($result['total'] ?? 0), ['api' => 'v1']);
        StepsGeolocationApi::respond(['ok' => true] + $result);
    }

    if ($resource === 'family' && ($segments[1] ?? '') === 'permissions') {
        if ($method !== 'GET') StepsGeolocationApi::fail('method_not_allowed', 405);
        StepsGeolocationApi::requireScope($key, 'read:family');
        StepsGeolocationApi::respond(['ok' => true, 'grants' => StepsGeolocationPermissions::grants($pdo, $owner), 'incoming' => StepsGeolocationPermissions::incoming($pdo, $owner)]);
    }

    if ($resource === 'immich') {
        if ($method !== 'GET') StepsGeolocationApi::fail('method_not_allowed', 405);
        StepsGeolocationApi::requireScope($key, 'read:immich');
        $limit = StepsGeolocationApi::limit($params['limit'] ?? null);
        $offset = StepsGeolocationApi::offset($params['offset'] ?? null);
        $stmt = $pdo->prepare(
            "SELECT id, immich_asset_id, captured_at, original_filename, state, match_distance_m, match_time_delta_s,
                    ST_Y(position) AS latitude, ST_X(position) AS longitude
               FROM geo_immich_assets
              WHERE user_email = :owner AND deleted_at IS NULL
           ORDER BY captured_at DESC NULLS LAST, id DESC LIMIT $limit OFFSET $offset"
        );
        $stmt->execute([':owner' => $owner]);
        $assets = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['id'] = (string)$row['id'];
            $row['latitude'] = $row['latitude'] !== null ? (float)$row['latitude'] : null;
            $row['longitude'] = $row['longitude'] !== null ? (float)$row['longitude'] : null;
            $assets[] = $row;
        }
        StepsGeolocationSecurity::auditRead($pdo, $owner, 'immich_assets', count($assets), ['api' => 'v1']);
        StepsGeolocationApi::respond(['ok' => true, 'assets' => $assets, 'limit' => $limit, 'offset' => $offset]);
    }

    if ($resource === 'imports') {
        if ($method !== 'GET') StepsGeolocationApi::fail('method_not_allowed', 405);
        StepsGeolocationApi::requireScope($key, 'read:imports');
        $limit = StepsGeolocationApi::limit($params['limit'] ?? null);
        $offset = StepsGeolocationApi::offset($params['offset'] ?? null);
        $stmt = $pdo->prepare(
            "SELECT id, source_type, state, format, progress_json, error_json, created_at, updated_at, completed_at
               FROM geo_imports WHERE user_email = :owner AND deleted_at IS NULL
           ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset"
        );
        $stmt->execute([':owner' => $owner]);
        $imports = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['id'] = (string)$row['id'];
            $row['progress'] = json_decode((string)$row['progress_json'], true) ?: [];
            $row['error'] = json_decode((string)$row['error_json'], true) ?: [];
            unset($row['progress_json'], $row['error_json']);
            $imports[] = $row;
        }
        StepsGeolocationApi::respond(['ok' => true, 'imports' => $imports, 'limit' => $limit, 'offset' => $offset]);
    }

    if ($resource === 'exports') {
        StepsGeolocationApi::requireScope($key, $method === 'POST' ? 'write:exports' : 'read:exports');
        if ($method === 'GET') {
            StepsGeolocationApi::respond(['ok' => true, 'exports' => StepsGeolocationExports::history($pdo, $owner), 'formats' => StepsGeolocationExports::FORMATS]);
        }
        if ($method === 'POST') {
            $format = strtolower(trim((string)($params['format'] ?? 'geojson')));
            $scope = is_array($params['scope'] ?? null) ? $params['scope'] : [];
            $export = StepsGeolocationExports::enqueue($pdo, $owner, $format, $scope);
            StepsGeolocationApi::respond(['ok' => true, 'export' => $export], 202);
        }
        StepsGeolocationApi::fail('method_not_allowed', 405);
    }

    if ($resource === 'statistics') {
        if ($method !== 'GET') StepsGeolocationApi::fail('method_not_allowed', 405);
        StepsGeolocationApi::requireScope($key, 'read:statistics');
        $timezone = StepsGeolocationStatistics::timezone((string)($params['tz'] ?? 'UTC'));
        $from = isset($params['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$params['from']) ? (string)$params['from'] : null;
        $to = isset($params['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$params['to']) ? (string)$params['to'] : null;
        if (($from === null) !== ($to === null)) StepsGeolocationApi::fail('invalid_range', 400);
        StepsGeolocationApi::respond(['ok' => true] + StepsGeolocationStatistics::summary($pdo, $owner, $timezone, $from, $to));
    }

    if ($resource === 'achievements') {
        if ($method !== 'GET') StepsGeolocationApi::fail('method_not_allowed', 405);
        StepsGeolocationApi::requireScope($key, 'read:achievements');
        StepsGeolocationApi::respond(['ok' => true] + StepsGeolocationAchievements::progress($pdo, $owner));
    }

    StepsGeolocationApi::fail('unknown_resource', 404);
} catch (StepsGeolocationApiException $e) {
    StepsGeolocationApi::failFrom($e);
} catch (InvalidArgumentException $e) {
    StepsGeolocationApi::fail('invalid_request', 400);
} catch (StepsGeolocationExportError $e) {
    StepsGeolocationApi::fail($e->errorCode, $e->status);
} catch (Throwable $e) {
    error_log('geolocation api v1 [' . $requestId . ']: ' . $e->getMessage());
    StepsGeolocationApi::fail('internal_error', 500);
}
