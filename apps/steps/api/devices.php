<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_devices', 120, 60);
$pdo = app_main_db()->getPdo();

function geo_devices_audit(PDO $pdo, string $owner, string $event, array $context): void
{
    $stmt = $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)');
    $stmt->execute([
        ':id' => geo_devices_uuid(),
        ':owner' => $owner,
        ':actor' => $owner,
        ':event' => $event,
        ':context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function geo_devices_uuid(): string
{
    $b = bin2hex(random_bytes(16));
    return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
}

/** @return array<string,mixed> */
function geo_devices_shape(array $row): array
{
    return [
        'id' => (string)$row['id'],
        'name' => (string)$row['name'],
        'platform' => (string)($row['platform'] ?? ''),
        'app_version' => (string)($row['app_version'] ?? ''),
        'source_type' => (string)$row['source_type'],
        'status' => (string)$row['status'],
        'last_seen_at' => $row['last_seen_at'] ?? null,
        'last_upload_at' => $row['last_upload_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'point_count' => (int)($row['point_count'] ?? 0),
        'last_point_at' => $row['last_point_at'] ?? null,
    ];
}

function geo_devices_find(PDO $pdo, string $owner, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, platform, app_version, source_type, status, last_seen_at, last_upload_at, created_at FROM geo_devices WHERE user_email = :owner AND id = :id');
    $stmt->execute([':owner' => $owner, ':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$body = $method === 'GET' ? [] : public_api_body();
$action = strtolower(trim((string)($_GET['action'] ?? $body['action'] ?? '')));

if ($method === 'GET') {
    $includeDeleted = !empty($_GET['include_deleted']);
    $where = 'd.user_email = :owner' . ($includeDeleted ? '' : " AND d.status <> 'deleted'");
    $stmt = $pdo->prepare(
        "SELECT d.id, d.name, d.platform, d.app_version, d.source_type, d.status,
                d.last_seen_at, d.last_upload_at, d.created_at,
                COUNT(p.id) AS point_count, MAX(p.received_at) AS last_point_at
           FROM geo_devices d
      LEFT JOIN geo_location_points p
             ON p.user_email = d.user_email AND p.device_id = d.id
          WHERE $where
       GROUP BY d.id
       ORDER BY d.last_seen_at DESC NULLS LAST, d.created_at DESC"
    );
    $stmt->execute([':owner' => $owner]);
    $devices = array_map('geo_devices_shape', $stmt->fetchAll(PDO::FETCH_ASSOC));
    public_api_json(['ok' => true, 'devices' => $devices]);
}

if (!in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
    public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
if ($method === 'DELETE' && $action === '') $action = 'delete';
if (!in_array($action, ['rename', 'pause', 'resume', 'revoke', 'delete', 'enroll'], true)) {
    public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
}
$deviceId = trim((string)($body['device_id'] ?? $_GET['device_id'] ?? ''));
if (!StepsGeolocationDomain::isUuid($deviceId)) {
    public_api_json(['ok' => false, 'error' => 'invalid_device_id'], 400);
}
$device = geo_devices_find($pdo, $owner, $deviceId);
if ($device === null) {
    // Never reveal whether the id exists under another account.
    public_api_json(['ok' => false, 'error' => 'device_not_found'], 404);
}

try {
    if ($action === 'rename') {
        $name = StepsGeolocationDomain::normalizeDeviceName((string)($body['name'] ?? ''));
        if ($name === '') public_api_json(['ok' => false, 'error' => 'invalid_name'], 400);
        if ($device['status'] === 'deleted') public_api_json(['ok' => false, 'error' => 'device_deleted'], 409);
        $stmt = $pdo->prepare('UPDATE geo_devices SET name = :name, updated_at = now() WHERE user_email = :owner AND id = :id');
        $stmt->execute([':name' => $name, ':owner' => $owner, ':id' => $deviceId]);
        geo_devices_audit($pdo, $owner, 'device.renamed', ['device_id' => $deviceId, 'previous_name' => $device['name'], 'name' => $name]);
    } else {
        $target = [
            'pause' => 'paused',
            'resume' => 'active',
            'enroll' => 'active',
            'revoke' => 'revoked',
            'delete' => 'deleted',
        ][$action];
        $from = (string)$device['status'];
        if (!StepsGeolocationDomain::canTransitionDevice($from, $target)) {
            public_api_json(['ok' => false, 'error' => 'invalid_state', 'status' => $from], 409);
        }
        // Revoke and delete clear the scoped credential in the same statement, so
        // the row can never authorize ingestion again without an explicit enroll.
        $clearCredential = in_array($action, ['revoke', 'delete'], true);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE geo_devices SET status = :status, updated_at = now()'
                . ($clearCredential ? ', credential_hash = NULL' : '')
                . ' WHERE user_email = :owner AND id = :id'
            );
            $stmt->execute([':status' => $target, ':owner' => $owner, ':id' => $deviceId]);
            geo_devices_audit($pdo, $owner, 'device.' . $action . 'd', ['device_id' => $deviceId, 'from' => $from, 'to' => $target]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
} catch (Throwable $e) {
    error_log('geolocation devices: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'device_update_failed'], 500);
}

$updated = geo_devices_find($pdo, $owner, $deviceId);
public_api_json(['ok' => true, 'device' => geo_devices_shape($updated ?? $device)]);
