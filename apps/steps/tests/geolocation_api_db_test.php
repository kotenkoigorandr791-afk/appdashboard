<?php
/**
 * Integration test for API keys and v1 read services (task 28).
 *
 *   php apps/steps/tests/geolocation_api_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_api.php';

function geo_api_db_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-geo-api@example.test';
$other = 'qa-geo-api-other@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, string $owner): void {
    foreach (['geo_api_keys', 'geo_location_points', 'geo_places', 'geo_trips', 'geo_devices'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
    }
};
$cleanup($pdo, $owner);
$cleanup($pdo, $other);

try {
    $device = StepsGeolocationApi::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA api', 'android', 'mobile', 'active')")
        ->execute([':id' => $device, ':owner' => $owner]);
    $otherDevice = StepsGeolocationApi::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'Other api', 'android', 'mobile', 'active')")
        ->execute([':id' => $otherDevice, ':owner' => $other]);
    $insert = $pdo->prepare(
        "INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, quality_state)
         VALUES(:id, :owner, :device, :event, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 'accepted')"
    );
    foreach ([0, 1] as $i) {
        $insert->execute([
            ':id' => StepsGeolocationApi::uuid(), ':owner' => $owner, ':device' => $device,
            ':event' => StepsGeolocationApi::uuid(), ':at' => gmdate('c', strtotime('2026-03-01T10:00:00Z') + $i * 300),
            ':lon' => 30.55 + $i * 0.001, ':lat' => 50.45,
        ]);
    }
    $insert->execute([
        ':id' => StepsGeolocationApi::uuid(), ':owner' => $other, ':device' => $otherDevice,
        ':event' => StepsGeolocationApi::uuid(), ':at' => gmdate('c', strtotime('2026-03-01T10:00:00Z')),
        ':lon' => 20.5, ':lat' => 50.5,
    ]);

    $issued = StepsGeolocationApi::issueKey($pdo, $owner, 'QA key', ['read:points', 'read:trips']);
    geo_api_db_assert(str_starts_with($issued['secret'], StepsGeolocationApi::KEY_PREFIX), 'secret prefix missing');
    geo_api_db_assert($issued['scopes'] === ['read:points', 'read:trips'], 'scopes not stored');
    geo_api_db_assert($issued['prefix'] === substr($issued['secret'], 0, 12), 'prefix metadata wrong');

    $row = $pdo->prepare('SELECT key_hash, key_prefix, scopes_json, last_used_at FROM geo_api_keys WHERE id = :id');
    $row->execute([':id' => $issued['id']]);
    $stored = $row->fetch(PDO::FETCH_ASSOC);
    geo_api_db_assert($stored !== false && $stored['key_hash'] === hash('sha256', $issued['secret']), 'key is not hash-stored');
    geo_api_db_assert($stored['key_hash'] !== $issued['secret'], 'plaintext secret stored');
    geo_api_db_assert(json_decode((string)$stored['scopes_json'], true) === ['read:points', 'read:trips'], 'scopes json wrong');

    $auth = StepsGeolocationApi::authenticate($pdo, $issued['secret']);
    geo_api_db_assert($auth !== null && $auth['user_email'] === $owner, 'valid key rejected');
    geo_api_db_assert(in_array('read:points', $auth['scopes'], true), 'scopes missing after authentication');
    geo_api_db_assert(StepsGeolocationApi::authenticate($pdo, 'stp_' . str_repeat('a', 48)) === null, 'random key accepted');
    $row->execute([':id' => $issued['id']]);
    geo_api_db_assert($row->fetch(PDO::FETCH_ASSOC)['last_used_at'] !== null, 'last-used metadata not updated');

    // Rotation invalidates the previous secret in place.
    $rotated = StepsGeolocationApi::rotateKey($pdo, $owner, $issued['id']);
    geo_api_db_assert($rotated['id'] === $issued['id'], 'rotation changed the key id');
    geo_api_db_assert($rotated['secret'] !== $issued['secret'], 'rotation reused the secret');
    geo_api_db_assert(StepsGeolocationApi::authenticate($pdo, $issued['secret']) === null, 'old secret still valid');
    geo_api_db_assert(StepsGeolocationApi::authenticate($pdo, $rotated['secret']) !== null, 'rotated secret rejected');

    // Revocation is immediate and keys stay tenant-owned.
    try {
        StepsGeolocationApi::revokeKey($pdo, $other, $issued['id']);
        throw new RuntimeException('another owner revoked the key');
    } catch (StepsGeolocationApiException $e) {
        geo_api_db_assert($e->errorCode === 'not_found' && $e->status === 404, 'cross-owner revoke not denied');
    }
    StepsGeolocationApi::revokeKey($pdo, $owner, $issued['id']);
    geo_api_db_assert(StepsGeolocationApi::authenticate($pdo, $rotated['secret']) === null, 'revoked key still valid');
    $listed = StepsGeolocationApi::listKeys($pdo, $owner);
    geo_api_db_assert(count($listed) === 1, 'key list wrong');
    geo_api_db_assert($listed[0]['active'] === false, 'revoked key reported active');
    geo_api_db_assert(!array_key_exists('secret', $listed[0]) && !array_key_exists('key_hash', $listed[0]), 'key list leaks a secret');

    // A second owner cannot see the first owner's keys.
    $otherKey = StepsGeolocationApi::issueKey($pdo, $other, 'Other key');
    geo_api_db_assert(count(StepsGeolocationApi::listKeys($pdo, $owner)) === 1, 'key list not owner-scoped');
    geo_api_db_assert(count(StepsGeolocationApi::listKeys($pdo, $other)) === 1, 'other key missing');
    StepsGeolocationApi::revokeKey($pdo, $other, $otherKey['id']);

    // Read services enforce ownership at the service layer and support filters.
    $page = StepsGeolocationApi::points($pdo, $owner, ['limit' => '50']);
    geo_api_db_assert($page['total'] === 2 && count($page['items']) === 2, 'point page not owner-scoped');
    geo_api_db_assert($page['next_offset'] === null, 'next offset set for a complete page');
    $pageOne = StepsGeolocationApi::points($pdo, $owner, ['limit' => '1', 'offset' => '0']);
    geo_api_db_assert(count($pageOne['items']) === 1 && $pageOne['next_offset'] === 1, 'pagination offset wrong');
    $ranged = StepsGeolocationApi::points($pdo, $owner, ['from' => '2026-03-01T10:04:00Z', 'to' => '2026-03-01T10:05:00Z']);
    geo_api_db_assert($ranged['total'] === 1, 'date filter wrong');
    $bounded = StepsGeolocationApi::points($pdo, $owner, ['bbox' => '30.54,50.44,30.56,50.46']);
    geo_api_db_assert($bounded['total'] === 2, 'viewport filter wrong');
    $none = StepsGeolocationApi::points($pdo, $other, []);
    geo_api_db_assert($none['total'] === 1, 'other owner point count wrong');
    $placePage = StepsGeolocationApi::places($pdo, $owner, []);
    geo_api_db_assert($placePage['total'] === 0 && $placePage['items'] === [], 'place service not owner-scoped');
    $tripPage = StepsGeolocationApi::trips($pdo, $owner, []);
    geo_api_db_assert($tripPage['total'] === 0, 'trip service not owner-scoped');

    try {
        StepsGeolocationApi::trip($pdo, $owner, 'not-a-uuid');
        throw new RuntimeException('invalid trip id accepted');
    } catch (StepsGeolocationApiException $e) {
        geo_api_db_assert($e->errorCode === 'invalid_id', 'invalid trip id error wrong');
    }

    // Key issuance validates names and scopes.
    try {
        StepsGeolocationApi::issueKey($pdo, $owner, '', []);
        throw new RuntimeException('empty key name accepted');
    } catch (StepsGeolocationApiException $e) {
        geo_api_db_assert($e->errorCode === 'invalid_request', 'empty name error wrong');
    }
    try {
        StepsGeolocationApi::issueKey($pdo, $owner, 'bad', ['admin:all']);
        throw new RuntimeException('unknown scope accepted');
    } catch (StepsGeolocationApiException $e) {
        geo_api_db_assert($e->errorCode === 'invalid_scope', 'unknown scope error wrong');
    }

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
    $cleanup($pdo, $other);
}
