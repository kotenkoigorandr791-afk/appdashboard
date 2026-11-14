<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_render.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_renders', 30, 60);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET' && !empty($_GET['download'])) {
    $id = trim((string)$_GET['download']);
    if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
    $artifact = StepsGeolocationRender::artifactFor($pdo, $owner, $id);
    if (!$artifact || !is_file((string)$artifact['storage_path'])) public_api_json(['ok' => false, 'error' => 'artifact_expired'], 404);
    if (empty($_SERVER['HTTP_RANGE']) || str_starts_with((string)$_SERVER['HTTP_RANGE'], 'bytes=0-')) $pdo->prepare('UPDATE geo_artifacts SET download_count = download_count + 1, last_downloaded_at = now() WHERE id = :id AND user_email = :owner')
        ->execute([':id' => $artifact['id'], ':owner' => $owner]);
    header('Content-Type: ' . ($artifact['content_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: ' . (empty($_GET['inline']) ? 'attachment' : 'inline') . '; filename="' . basename((string)$artifact['filename']) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    $path = (string)$artifact['storage_path'];
    $size = filesize($path);
    $start = 0;
    $end = $size - 1;
    if (preg_match('/^bytes=(\d*)-(\d*)$/', (string)($_SERVER['HTTP_RANGE'] ?? ''), $m) && ($m[1] !== '' || $m[2] !== '')) {
        if ($m[1] === '') {
            $start = max(0, $size - (int)$m[2]);
        } else {
            $start = (int)$m[1];
            if ($m[2] !== '') $end = min($end, (int)$m[2]);
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        http_response_code(206);
        header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
    }
    header('Content-Length: ' . ($end - $start + 1));
    $fh = fopen($path, 'rb');
    fseek($fh, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fh) && !connection_aborted()) {
        $chunk = fread($fh, min(65536, $left));
        if ($chunk === false || $chunk === '') break;
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fh);
    exit;
}

if ($method === 'GET') {
    $id = trim((string)($_GET['id'] ?? ''));
    $stuck = $pdo->prepare("SELECT 1 FROM geo_render_jobs WHERE user_email = :o AND state = 'pending' AND created_at < now() - interval '5 seconds' LIMIT 1");
    $stuck->execute([':o' => $owner]);
    if ($stuck->fetchColumn()) StepsGeolocationRender::kick();
    public_api_json([
        'ok' => true,
        'encoder' => StepsGeolocationRender::encoderStatus(),
        'job' => $id !== '' ? StepsGeolocationRender::find($pdo, $owner, $id) : null,
        'jobs' => StepsGeolocationRender::list($pdo, $owner),
        'formats' => StepsGeolocationRender::FORMATS,
        'themes' => StepsGeolocationRender::THEMES,
    ]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? 'create')));
try {
    if ($action === 'cancel') {
        $id = trim((string)($body['id'] ?? ''));
        if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
        StepsGeolocationRender::cancel($pdo, $owner, $id);
        public_api_json(['ok' => true, 'cancelled' => $id]);
    }
    if ($action !== 'create') public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
    $kind = strtolower(trim((string)($body['kind'] ?? 'poster')));
    $job = StepsGeolocationRender::create($pdo, $owner, $kind, $body);
    StepsGeolocationRender::kick();
    public_api_json(['ok' => true, 'job' => $job], 202);
} catch (StepsGeolocationRenderError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
} catch (Throwable $e) {
    error_log('geolocation render: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'render_failed'], 500);
}
