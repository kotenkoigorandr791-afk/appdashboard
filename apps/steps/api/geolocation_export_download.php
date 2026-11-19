<?php
/**
 * Authenticated artifact download. The session is re-checked here, then the
 * owner lookup and checksum verification happen again in the export manager.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_exports.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_export_download', 60, 60);
$id = trim((string)($_GET['id'] ?? ''));
if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
$artifact = StepsGeolocationExports::download(app_main_db()->getPdo(), $owner, $id);
if ($artifact === null) public_api_json(['ok' => false, 'error' => 'artifact_unavailable'], 404);

$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$artifact['filename']) ?: 'steps-export';
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: ' . (string)$artifact['content_type']);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string)(int)$artifact['size_bytes']);
header('X-Content-SHA256: ' . (string)$artifact['checksum']);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile((string)$artifact['storage_path']);
exit;
