<?php
/**
 * Integration test for family permissions and notification webhooks.
 *   php apps/steps/tests/geolocation_permissions_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_permissions.php';
require_once dirname(__DIR__) . '/core/geolocation_notifications.php';

function geo_perm_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-perm-owner@example.test';
$viewer = 'qa-perm-viewer@example.test';
$stranger = 'qa-perm-stranger@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, array $emails): void {
    foreach ($emails as $email) {
        foreach (['geo_notification_preferences', 'geo_webhooks', 'geo_audit_events', 'geo_devices', 'geo_location_points'] as $table) {
            $pdo->prepare("DELETE FROM $table WHERE user_email = :email")->execute([':email' => $email]);
        }
        $pdo->prepare('DELETE FROM geo_location_grants WHERE owner_email = :email')->execute([':email' => $email]);
        $pdo->prepare('DELETE FROM geo_location_grants WHERE viewer_email = :email')->execute([':email' => $email]);
        $pdo->prepare('DELETE FROM geo_webhook_deliveries WHERE user_email = :email')->execute([':email' => $email]);
    }
};
$cleanup($pdo, [$owner, $viewer, $stranger]);

try {
    $range = ['from' => gmdate('c', time() - 86400), 'to' => gmdate('c', time() + 3600)];

    // Deny by default: no grant, no access.
    $denied = StepsGeolocationPermissions::authorize($pdo, $viewer, $owner, $range['from'], $range['to']);
    geo_perm_assert($denied['error'] === 'family_access_denied', 'ungranted family read allowed');
    geo_perm_assert($denied['owner'] === null, 'denied scope leaked owner');

    // Explicit consent is required before a grant exists.
    $threw = false;
    try {
        StepsGeolocationPermissions::grant($pdo, $owner, ['viewer_email' => $viewer, 'access_level' => 'recent']);
    } catch (StepsGeolocationPermissionsError $e) {
        $threw = $e->errorCode === 'consent_required';
    }
    geo_perm_assert($threw, 'grant without consent accepted');

    $grant = StepsGeolocationPermissions::grant($pdo, $owner, [
        'viewer_email' => $viewer,
        'access_level' => 'range',
        'range_from' => $range['from'],
        'range_to' => $range['to'],
        'ends_at' => gmdate('c', time() + 86400),
        'precision_m' => 100,
        'reason' => 'family trip',
        'consent' => true,
    ]);
    geo_perm_assert((string)$grant['access_level'] === 'range' && $grant['consent_at'] !== null, 'grant not persisted with consent');
    geo_perm_assert((int)$grant['generation'] === 1, 'first grant generation wrong');

    $allowed = StepsGeolocationPermissions::authorize($pdo, $viewer, $owner, $range['from'], $range['to']);
    geo_perm_assert($allowed['error'] === null && $allowed['owner'] === $owner, 'granted range denied');
    geo_perm_assert($allowed['precision_m'] === 100.0, 'precision not returned');
    geo_perm_assert(StepsGeolocationPermissions::precisionDecimals(100) === 4, 'precision decimals wrong: ' . StepsGeolocationPermissions::precisionDecimals(100));

    // A requested window outside the granted range is denied, not clamped.
    $outside = StepsGeolocationPermissions::authorize($pdo, $viewer, $owner, gmdate('c', time() + 3600), gmdate('c', time() + 7200));
    geo_perm_assert($outside['error'] === 'outside_granted_range', 'out-of-range window allowed: ' . var_export($outside['error'], true));

    // Strangers never see anything.
    geo_perm_assert(StepsGeolocationPermissions::authorize($pdo, $stranger, $owner, $range['from'], $range['to'])['error'] === 'family_access_denied', 'stranger allowed');

    // Pause revokes access immediately and increments the generation.
    $alive = StepsGeolocationPermissions::grants($pdo, $owner);
    $activeId = '';
    foreach ($alive as $row) if ($row['revoked_at'] === null && $row['paused_at'] === null) $activeId = (string)$row['id'];
    geo_perm_assert($activeId !== '', 'no active grant listed');
    StepsGeolocationPermissions::pause($pdo, $owner, $activeId, true);
    geo_perm_assert(StepsGeolocationPermissions::authorize($pdo, $viewer, $owner, $range['from'], $range['to'])['error'] === 'family_access_denied', 'paused grant still allowed');
    StepsGeolocationPermissions::pause($pdo, $owner, $activeId, false);
    geo_perm_assert(StepsGeolocationPermissions::authorize($pdo, $viewer, $owner, $range['from'], $range['to'])['error'] === null, 'resumed grant denied');

    // Revocation bumps the generation and blocks future reads.
    StepsGeolocationPermissions::revoke($pdo, $owner, $activeId);
    $revoked = null;
    foreach (StepsGeolocationPermissions::grants($pdo, $owner) as $row) if ((string)$row['id'] === $activeId) $revoked = $row;
    geo_perm_assert($revoked !== null && $revoked['revoked_at'] !== null, 'grant not revoked');
    geo_perm_assert((int)$revoked['generation'] >= 4, 'generation did not increase: ' . var_export($revoked['generation'], true));
    geo_perm_assert(StepsGeolocationPermissions::authorize($pdo, $viewer, $owner, $range['from'], $range['to'])['error'] === 'family_access_denied', 'revoked grant still allowed');

    // "Current location only" cannot read yesterday's history.
    $current = StepsGeolocationPermissions::grant($pdo, $owner, ['viewer_email' => $viewer, 'access_level' => 'current', 'consent' => true]);
    geo_perm_assert((string)$current['access_level'] === 'current', 'current grant not stored');
    geo_perm_assert(StepsGeolocationPermissions::authorize($pdo, $viewer, $owner, gmdate('c', time() - 86400), gmdate('c', time() - 80000))['error'] === 'historical_access_denied', 'current grant exposed history');
    $live = StepsGeolocationPermissions::authorize($pdo, $viewer, $owner, gmdate('c', time() - 60), gmdate('c'));
    geo_perm_assert($live['error'] === null, 'current grant denied live data');

    // Notice preferences round-trip and respect quiet hours.
    $prefs = StepsGeolocationNotifications::savePreferences($pdo, $owner, [
        'in_app' => false,
        'events' => ['geofence_enter' => false],
        'quiet_hours' => ['enabled' => true, 'start' => '22:00', 'end' => '07:00'],
    ]);
    geo_perm_assert($prefs['in_app'] === false && $prefs['events']['geofence_enter'] === false, 'preferences not saved');
    geo_perm_assert(StepsGeolocationNotifications::quietNow($prefs, '23:30') === true, 'quiet hours not applied at night');
    geo_perm_assert(StepsGeolocationNotifications::quietNow($prefs, '12:00') === false, 'quiet hours applied at noon');

    // Webhook must be HTTPS and must not point at private networks.
    geo_perm_assert(StepsGeolocationNotifications::urlAllowed('http://example.com/hook') === false, 'plain http webhook allowed');
    geo_perm_assert(StepsGeolocationNotifications::urlAllowed('https://localhost/hook') === false, 'localhost webhook allowed');
    geo_perm_assert(StepsGeolocationNotifications::urlAllowed('https://127.0.0.1/hook') === false, 'loopback webhook allowed');
    geo_perm_assert(StepsGeolocationNotifications::urlAllowed('https://192.168.1.10/hook') === false, 'private webhook allowed');
    geo_perm_assert(StepsGeolocationNotifications::urlAllowed('https://169.254.169.254/latest/meta-data') === false, 'metadata endpoint allowed');

    // Signatures are stable and cover timestamp, nonce and canonical body.
    $body = '{"b":1,"a":{"d":2,"c":3}}';
    $sigA = StepsGeolocationNotifications::sign('secret', '1700000000', 'abc', $body);
    $sigB = StepsGeolocationNotifications::sign('secret', '1700000000', 'abc', '{"a":{"c":3,"d":2},"b":1}');
    geo_perm_assert($sigA === $sigB, 'signature depends on key order');
    geo_perm_assert($sigA !== StepsGeolocationNotifications::sign('secret', '1700000001', 'abc', $body), 'signature ignores timestamp');

    // Outbox is idempotent for the same dedupe key.
    $first = StepsGeolocationNotifications::enqueue($pdo, $owner, 'geofence_enter', 'Home', 'You arrived at Home.', [], 'info', 'geo:enter:test');
    $second = StepsGeolocationNotifications::enqueue($pdo, $owner, 'geofence_enter', 'Home', 'You arrived at Home.', [], 'info', 'geo:enter:test');
    geo_perm_assert($first === true && $second === false, 'duplicate notification enqueued');

    echo "ok\n";
} finally {
    $cleanup($pdo, [$owner, $viewer, $stranger]);
    $pdo->prepare("DELETE FROM geo_notification_outbox WHERE user_email IN (:a, :b, :c)")->execute([':a' => $owner, ':b' => $viewer, ':c' => $stranger]);
}
