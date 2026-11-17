<?php
/**
 * On-demand Immich thumbnail proxy for the signed-in owner.
 *
 * The Immich API key never reaches the browser, and every request re-checks
 * asset ownership and the active connection before calling Immich.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_immich.php';
require_once dirname(__DIR__) . '/core/geolocation_features.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_immich_thumbnail', 120, 60);
$pdo = app_main_db()->getPdo();
StepsGeolocationFeatures::requireEnabled($pdo, $owner, 'immich');
$thumb = StepsGeolocationImmich::thumbnail($pdo, $owner, (string)($_GET['id'] ?? ''));
if ($thumb === null) public_api_json(['ok' => false, 'error' => 'thumbnail_unavailable'], 404);
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: ' . $thumb['mime']);
header('Content-Length: ' . strlen($thumb['body']));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $thumb['body'];
exit;
