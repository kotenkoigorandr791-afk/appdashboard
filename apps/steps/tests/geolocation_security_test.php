<?php
/**
 * Unit tests for centralized geolocation security primitives (task 30).
 *
 *   php apps/steps/tests/geolocation_security_test.php
 */
require_once dirname(__DIR__) . '/core/geolocation_security.php';

function geo_sec_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

// Audit context redaction is recursive and strips secrets/coordinates.
$clean = StepsGeolocationSecurity::redactContext([
    'resource' => 'points',
    'latitude' => 50.45,
    'nested' => ['longitude' => 30.52, 'count' => 2, 'server_url' => 'http://x'],
    'api_key' => 'stp_secret',
    'Authorization' => 'Bearer token',
    'token' => 'hidden',
]);
geo_sec_assert($clean['resource'] === 'points', 'non-sensitive context lost');
geo_sec_assert(!isset($clean['latitude']) && !isset($clean['nested']['longitude']), 'coordinates survived redaction');
geo_sec_assert($clean['nested']['count'] === 2, 'nested safe value lost');
geo_sec_assert(!isset($clean['api_key']) && !isset($clean['Authorization']) && !isset($clean['token']), 'secret survived redaction');

// Ordinary tenant predicate accepts the same normalized owner and fails closed.
StepsGeolocationSecurity::requireOwner('Owner@Example.test', 'owner@example.test');
foreach ([[null, 'owner@example.test'], ['other@example.test', 'owner@example.test'], ['', '']] as $pair) {
    try {
        StepsGeolocationSecurity::requireOwner($pair[0], $pair[1]);
        throw new RuntimeException('cross-tenant row accepted');
    } catch (StepsGeolocationSecurityError $e) {
        geo_sec_assert($e->errorCode === 'not_found' && $e->status === 404, 'wrong tenant error');
    }
}

// Coordinate rounding honors capability decimals.
geo_sec_assert(StepsGeolocationSecurity::roundCoordinate(50.456789, 3) === 50.457, 'coordinate rounding wrong');
geo_sec_assert(StepsGeolocationSecurity::roundCoordinate(30.526789, 0) === 31.0, 'zero-decimal rounding wrong');
$rounded = StepsGeolocationSecurity::applyCoordinatePrecision(['latitude' => 50.456789, 'nested' => ['longitude' => 30.526789]], 2);
geo_sec_assert($rounded['latitude'] === 50.46 && $rounded['nested']['longitude'] === 30.53, 'nested precision missing');

// SSRF: schemes, credentials, loopback, metadata and private DNS results are denied.
$bad = [
    'file:///etc/passwd',
    'ftp://example.com/file',
    'http://user:pass@example.com',
    'http://127.0.0.1:2283',
    'http://169.254.169.254/latest/meta-data',
    'http://[::1]:2283',
    'http://localhost:2283',
    'http://immich.internal:2283',
];
foreach ($bad as $url) {
    try {
        StepsGeolocationSecurity::validateOutboundUrl($url);
        throw new RuntimeException('unsafe outbound URL accepted: ' . $url);
    } catch (StepsGeolocationSecurityError $e) {
        geo_sec_assert(in_array($e->errorCode, ['invalid_server_url', 'outbound_private_address_denied', 'outbound_host_unresolved'], true), 'wrong SSRF error for ' . $url);
    }
}

// An operator can explicitly allow a private LAN host, but never the metadata IP.
putenv('GEO_SSRF_ALLOW_PRIVATE_HOSTS=10.0.0.5,169.254.169.254');
geo_sec_assert(StepsGeolocationSecurity::validateOutboundUrl('http://10.0.0.5:2283') === 'http://10.0.0.5:2283', 'allowlisted private host rejected');
try {
    StepsGeolocationSecurity::validateOutboundUrl('http://169.254.169.254/latest');
    throw new RuntimeException('metadata endpoint accepted via allowlist');
} catch (StepsGeolocationSecurityError $e) {
    geo_sec_assert($e->errorCode === 'outbound_private_address_denied', 'metadata allowlist error wrong');
}
putenv('GEO_SSRF_ALLOW_PRIVATE_HOSTS');

// Upload guard uses the bounded import limit and fails with 413.
$old = $_SERVER['CONTENT_LENGTH'] ?? null;
$_SERVER['CONTENT_LENGTH'] = (string)(StepsGeolocationSecurity::maxUploadBytes() + 1);
try {
    StepsGeolocationSecurity::assertUploadSize();
    throw new RuntimeException('oversized upload accepted');
} catch (StepsGeolocationSecurityError $e) {
    geo_sec_assert($e->errorCode === 'payload_too_large' && $e->status === 413, 'upload error wrong');
}
$_SERVER['CONTENT_LENGTH'] = '1024';
geo_sec_assert(StepsGeolocationSecurity::assertUploadSize() === null, 'valid upload rejected');
if ($old === null) unset($_SERVER['CONTENT_LENGTH']); else $_SERVER['CONTENT_LENGTH'] = $old;

echo "ok\n";
