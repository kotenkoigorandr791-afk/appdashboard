<?php
/**
 * Stable REST API contract (task 28), pure checks.
 *
 *   php apps/steps/tests/geolocation_api_test.php
 */
require_once dirname(__DIR__) . '/core/geolocation_api.php';

function geo_api_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function geo_api_throws(callable $fn, string $code, int $status): void
{
    try {
        $fn();
    } catch (StepsGeolocationApiException $e) {
        geo_api_assert($e->errorCode === $code, 'expected ' . $code . ', got ' . $e->errorCode);
        geo_api_assert($e->status === $status, 'expected status ' . $status . ', got ' . $e->status);
        return;
    }
    throw new RuntimeException('expected ' . $code . ' to be thrown');
}

geo_api_assert(StepsGeolocationApi::VERSION === 'v1', 'API version must be v1');
geo_api_assert(StepsGeolocationApi::KEY_PREFIX === 'stp_', 'unexpected key prefix');
geo_api_assert(StepsGeolocationApi::PUBLIC === false, 'API must not expose a public variant');

$scopes = StepsGeolocationApi::validateScopes(['read:points', 'read:points', ' READ:TRIPS ']);
geo_api_assert($scopes === ['read:points', 'read:trips'], 'scope normalization or dedupe failed');
geo_api_throws(static fn() => StepsGeolocationApi::validateScopes(['admin:everything']), 'invalid_scope', 400);
geo_api_throws(static fn() => StepsGeolocationApi::validateScopes([123]), 'invalid_scope', 400);

$key = ['id' => 'k1', 'user_email' => 'a@example.test', 'scopes' => ['read:points']];
geo_api_assert(StepsGeolocationApi::hasScope($key, 'read:points'), 'scope check failed');
geo_api_assert(!StepsGeolocationApi::hasScope($key, 'write:points'), 'write scope granted by default key');
try {
    StepsGeolocationApi::requireScope($key, 'write:points');
    throw new RuntimeException('missing scope accepted');
} catch (StepsGeolocationApiException $e) {
    geo_api_assert($e->errorCode === 'scope_required' && $e->status === 403, 'scope_required body wrong');
    geo_api_assert(($e->details['required_scope'] ?? '') === 'write:points', 'required scope missing from details');
}

// Pagination bounds are strict and stable.
geo_api_assert(StepsGeolocationApi::limit(null) === 50, 'default limit wrong');
geo_api_assert(StepsGeolocationApi::limit('200') === 200, 'max limit rejected');
geo_api_throws(static fn() => StepsGeolocationApi::limit('0'), 'invalid_limit', 400);
geo_api_throws(static fn() => StepsGeolocationApi::limit('201'), 'invalid_limit', 400);
geo_api_throws(static fn() => StepsGeolocationApi::limit('abc'), 'invalid_limit', 400);
geo_api_assert(StepsGeolocationApi::offset(null) === 0, 'default offset wrong');
geo_api_assert(StepsGeolocationApi::offset('25') === 25, 'offset rejected');
geo_api_throws(static fn() => StepsGeolocationApi::offset('-1'), 'invalid_offset', 400);

// Date and viewport filters fail closed.
$range = StepsGeolocationApi::range('2026-01-01T00:00:00Z', '2026-01-02T00:00:00Z');
geo_api_assert($range['from'] !== null && $range['to'] !== null, 'valid range rejected');
geo_api_throws(static fn() => StepsGeolocationApi::range('nonsense', null), 'invalid_range', 400);
geo_api_throws(static fn() => StepsGeolocationApi::range('2026-02-01', '2026-01-01'), 'invalid_range', 400);
$bbox = StepsGeolocationApi::bbox('30.1,50.1,30.9,50.9');
geo_api_assert($bbox !== null && $bbox['minLon'] === 30.1 && $bbox['maxLat'] === 50.9, 'valid bbox rejected');
foreach (['30.1,50.1,30.9', '30.1,50.1,30.9,95', '30.9,50.1,30.1,50.9', 'a,b,c,d'] as $bad) {
    geo_api_throws(static fn() => StepsGeolocationApi::bbox($bad), 'invalid_bounds', 400);
}

// Stable identifiers and a complete error catalogue.
geo_api_assert(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', StepsGeolocationApi::uuid()) === 1, 'uuid v4 format broken');
foreach (['scope_required', 'invalid_bounds', 'rate_limited', 'not_found', 'internal_error'] as $code) {
    geo_api_assert(isset(StepsGeolocationApi::ERROR_CATALOG[$code]), 'error catalogue missing ' . $code);
}
geo_api_throws(static fn() => StepsGeolocationApi::requireUuid('not-a-uuid'), 'invalid_id', 400);
geo_api_throws(static fn() => StepsGeolocationApi::requireOwner('other@example.test', 'owner@example.test'), 'not_found', 404);
StepsGeolocationApi::requireOwner('OWNER@example.test', 'owner@example.test');

$reflection = new ReflectionClass(StepsGeolocationApi::class);
foreach ($reflection->getConstants() as $name => $value) {
    if (!is_array($value)) continue;
    if ($name === 'SCOPES' || $name === 'DEFAULT_SCOPES') {
        foreach ($value as $scope) geo_api_assert(in_array($scope, StepsGeolocationApi::SCOPES, true), 'default scope not supported: ' . $scope);
    }
}

echo "ok\n";
