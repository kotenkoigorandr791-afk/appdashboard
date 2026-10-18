<?php
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('memory_limit', '512M');
@set_time_limit(120);
@ignore_user_abort(true);

header('Content-Type: application/json; charset=utf-8');

function bg_fail(int $code, string $message, array $extra = []): void {
    http_response_code($code);
    $payload = array_merge(['success' => false, 'message' => $message], $extra);
    error_log('remove_background: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bg_fail(405, 'method_not_allowed');
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    bg_fail(200, 'image_required', ['upload_error' => $_FILES['image']['error'] ?? null]);
}

$tmpInput = tempnam(sys_get_temp_dir(), 'bgin_');
$tmpOutput = tempnam(sys_get_temp_dir(), 'bgout_');
if ($tmpInput === false || $tmpOutput === false) {
    bg_fail(200, 'tempfile_failed');
}

$inputPath = $tmpInput . '.img';
$outputPath = $tmpOutput . '.png';
@unlink($tmpInput);
@unlink($tmpOutput);

if (!move_uploaded_file($_FILES['image']['tmp_name'], $inputPath)) {
    bg_fail(200, 'upload_failed');
}

$rawImage = @file_get_contents($inputPath);
if ($rawImage === false || $rawImage === '') {
    @unlink($inputPath);
    @unlink($outputPath);
    bg_fail(200, 'not_image');
}
$imageInfo = @getimagesizefromstring($rawImage);
if ($imageInfo === false || empty($imageInfo['mime']) || strpos((string)$imageInfo['mime'], 'image/') !== 0) {
    @unlink($inputPath);
    @unlink($outputPath);
    bg_fail(200, 'not_image');
}
$supportedMime = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array((string)$imageInfo['mime'], $supportedMime, true)) {
    @unlink($inputPath);
    @unlink($outputPath);
    bg_fail(200, 'unsupported_image', ['mime' => (string)$imageInfo['mime']]);
}

require_once dirname(__DIR__, 2) . '/lib/py_sidecar.php';
if (py_sidecar_enabled()) {
    $res = py_sidecar('remove-bg', [], $inputPath, 'file', 180);
    @unlink($inputPath);
    @unlink($outputPath);
    if (($res['body']['success'] ?? false) !== true) {
        bg_fail(200, 'background_remove_failed', [
            'details' => $res['body']['details'] ?? $res['error'] ?? $res['raw'],
            'exit_code' => $res['body']['exit_code'] ?? $res['code'],
        ]);
    }
    echo json_encode([
        'success' => true,
        'mime' => 'image/png',
        'filename' => 'background-removed.png',
        'data' => $res['body']['data'] ?? '',
        'stats' => $res['body']['stats'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$python = is_executable('/usr/bin/python3') ? '/usr/bin/python3' : trim((string)shell_exec('command -v python3'));
$script = __DIR__ . '/bg_remove.py';
$cwd = __DIR__;
if (!$python || !is_file($script)) {
    @unlink($inputPath);
    @unlink($outputPath);
    bg_fail(200, 'runtime_missing', ['python' => $python, 'script' => $script]);
}
$cmd = 'cd ' . escapeshellarg($cwd) . ' && ' . escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($inputPath) . ' ' . escapeshellarg($outputPath) . ' 2>&1';
$output = [];
$exitCode = 0;
exec($cmd, $output, $exitCode);

if ($exitCode !== 0 || !is_file($outputPath)) {
    @unlink($inputPath);
    @unlink($outputPath);
    bg_fail(200, 'background_remove_failed', [
        'details' => implode("\n", $output),
        'exit_code' => $exitCode,
        'python' => $python,
        'cwd' => $cwd,
    ]);
}

$image = @file_get_contents($outputPath);
$meta = json_decode(end($output) ?: '{}', true);
@unlink($inputPath);
@unlink($outputPath);

if ($image === false) {
    bg_fail(200, 'result_read_failed');
}

echo json_encode([
    'success' => true,
    'mime' => 'image/png',
    'filename' => 'background-removed.png',
    'data' => base64_encode($image),
    'stats' => $meta['stats'] ?? null,
], JSON_UNESCAPED_UNICODE);
