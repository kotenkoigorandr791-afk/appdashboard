<?php
require_once dirname(__DIR__, 2) . '/apps/steps/core/geolocation_domain.php';
function geo_device_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
// Active devices can be paused, revoked or deleted; paused devices resume.
geo_device_assert(StepsGeolocationDomain::canTransitionDevice('active', 'paused'), 'active -> paused rejected');
geo_device_assert(StepsGeolocationDomain::canTransitionDevice('active', 'revoked'), 'active -> revoked rejected');
geo_device_assert(StepsGeolocationDomain::canTransitionDevice('paused', 'active'), 'paused -> active rejected');
geo_device_assert(StepsGeolocationDomain::canTransitionDevice('revoked', 'active'), 'explicit re-enrollment rejected');
// Deleted devices are tombstones and never come back.
geo_device_assert(!StepsGeolocationDomain::canTransitionDevice('deleted', 'active'), 'deleted device reactivated');
geo_device_assert(!StepsGeolocationDomain::canTransitionDevice('revoked', 'paused'), 'revoked -> paused accepted');
geo_device_assert(!StepsGeolocationDomain::canTransitionDevice('active', 'bogus'), 'unknown target accepted');
// Insertion follows the same rule: only active devices may upload.
geo_device_assert(StepsGeolocationDomain::deviceAcceptsPoints('active'), 'active device refused points');
geo_device_assert(!StepsGeolocationDomain::deviceAcceptsPoints('paused'), 'paused device accepted points');
geo_device_assert(!StepsGeolocationDomain::deviceAcceptsPoints('revoked'), 'revoked device accepted points');
geo_device_assert(!StepsGeolocationDomain::deviceAcceptsPoints('deleted'), 'deleted device accepted points');
// Names are user-visible and must stay bounded and single-line.
geo_device_assert(StepsGeolocationDomain::normalizeDeviceName("  Pixel\n8  ") === 'Pixel 8', 'name normalization failed');
geo_device_assert(StepsGeolocationDomain::normalizeDeviceName("   ") === '', 'blank name accepted');
geo_device_assert(strlen(StepsGeolocationDomain::normalizeDeviceName(str_repeat('a', 200))) === 64, 'name not capped');
geo_device_assert(StepsGeolocationDomain::isDeviceStatus('active'), 'active status rejected');
geo_device_assert(!StepsGeolocationDomain::isDeviceStatus('lost'), 'invalid status accepted');
echo "ok\n";
