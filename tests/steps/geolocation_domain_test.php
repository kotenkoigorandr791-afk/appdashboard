<?php
require_once dirname(__DIR__, 2) . '/apps/steps/core/geolocation_domain.php';
function geo_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
geo_assert(StepsGeolocationDomain::normalizeOwner(' User@Example.COM ') === 'user@example.com', 'owner normalization failed');
geo_assert(StepsGeolocationDomain::isUuid('3fa85f64-5717-4562-b3fc-2c963f66afa6'), 'valid UUID rejected');
geo_assert(!StepsGeolocationDomain::isUuid('not-a-uuid'), 'invalid UUID accepted');
geo_assert(StepsGeolocationDomain::isCoordinate(50.4501, 30.5234), 'valid coordinate rejected');
geo_assert(!StepsGeolocationDomain::isCoordinate(90.1, 30.5234), 'invalid latitude accepted');
geo_assert(StepsGeolocationDomain::isSourceType('mobile'), 'mobile source rejected');
geo_assert(!StepsGeolocationDomain::isSourceType('webhook'), 'invalid source accepted');
geo_assert(StepsGeolocationDomain::canTransitionDerivedState('pending', 'processing'), 'valid transition rejected');
geo_assert(!StepsGeolocationDomain::canTransitionDerivedState('ready', 'processing'), 'invalid transition accepted');
geo_assert(StepsGeolocationDomain::isFamilyAccess('range'), 'range access rejected');
geo_assert(!StepsGeolocationDomain::isFamilyAccess('public'), 'public access accepted');
echo "ok\n";
