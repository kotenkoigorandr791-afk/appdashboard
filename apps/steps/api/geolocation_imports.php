<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_imports.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_imports', 60, 60);
$pdo = app_main_db()->getPdo();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    public_api_json([
        'ok' => true,
        'imports' => StepsGeolocationImports::history($pdo, $owner),
        'formats' => StepsGeoImportParsers::FORMATS,
        'max_bytes' => StepsGeolocationImports::maxBytes(),
    ]);
}
if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);

if (isset($_FILES['file']) && is_array($_FILES['file'])) {
    $file = $_FILES['file'];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'file_too_large', UPLOAD_ERR_FORM_SIZE => 'file_too_large',
            UPLOAD_ERR_PARTIAL => 'upload_incomplete', UPLOAD_ERR_NO_FILE => 'upload_missing',
        ];
        public_api_json(['ok' => false, 'error' => $messages[$error] ?? 'upload_failed'], $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE ? 413 : 400);
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    $filename = (string)($file['name'] ?? 'import');
    try {
        $import = StepsGeolocationImports::acceptFile($tmp, $filename, $owner, ['delete_source' => !empty($_POST['delete_source'])], true);
        public_api_json(['ok' => true, 'import' => $import], 201);
    } catch (RuntimeException $e) {
        $code = $e->getMessage();
        $status = $code === 'file_too_large' ? 413 : ($code === 'unsupported_format' ? 415 : 400);
        public_api_json(['ok' => false, 'error' => $code], $status);
    }
}

$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? '')));
$id = trim((string)($body['id'] ?? ''));
if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
if ($action === 'delete') {
    try {
        $result = StepsGeolocationImports::remove($pdo, $owner, $id);
        public_api_json(['ok' => true] + $result);
    } catch (RuntimeException $e) {
        public_api_json(['ok' => false, 'error' => $e->getMessage()], $e->getMessage() === 'import_not_found' ? 404 : 400);
    }
}
if ($action === 'cleanup_file' || $action === 'delete_source') {
    if (StepsGeolocationImports::find($pdo, $owner, $id) === null) public_api_json(['ok' => false, 'error' => 'import_not_found'], 404);
    public_api_json(['ok' => true, 'removed' => StepsGeolocationImports::deleteSourceFiles($pdo, $owner, $id)]);
}
public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
