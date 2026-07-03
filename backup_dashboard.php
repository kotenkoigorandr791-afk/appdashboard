<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(0); }
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smart_log.php';
smart_log_bootstrap('dashboard');
// Get URL parameters for theme and accent color
$bdTheme = isset($_GET['theme']) && preg_match('/^(dark|light)$/', (string)$_GET['theme']) ? $_GET['theme'] : 'dark';
$bdColor = isset($_GET['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$_GET['color']) ? $_GET['color'] : '#007bff';
function backupDashboardIsEmbeddedRequest(): bool {
    if (isset($_GET['embed']) && $_GET['embed'] === '1') { return true; }
    return isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe';
}
function backupDashboardDeny(int $httpCode, string $htmlMsg, string $jsonMsg = ''): void {
    $j = $jsonMsg !== '' ? $jsonMsg : $htmlMsg;
    if (isset($_GET['config']) || isset($_GET['proxy']) || isset($_GET['path_rw_test']) || isset($_GET['host_fs'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode);
        echo json_encode(['error' => $j], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    http_response_code($httpCode);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access denied</title></head><body><p>'
        . htmlspecialchars($htmlMsg, ENT_QUOTES, 'UTF-8')
        . '</p><p><a href="/">Home</a></p></body></html>';
    exit;
}
if (isset($_SESSION['user'], $_SESSION['auth_time']) && !getUserFromActiveSessions(session_id())) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
$backupDashLoggedIn = isset($_SESSION['user']['email']);
$backupDashRole = $backupDashLoggedIn ? (int)($_SESSION['user']['role'] ?? 0) : -1;
if (!$backupDashLoggedIn) {
    backupDashboardDeny(401, 'You must be logged in.', 'Unauthorized');
}
if ($backupDashRole < 2 && !backupDashboardIsEmbeddedRequest()) {
    backupDashboardDeny(
        403,
        'Opening this page in a separate window is allowed only for administrators. Use the Backup section inside the Admin Panel.',
        'Forbidden: use embedded admin panel or log in as admin (role 2)'
    );
}
function dupBackupCryptKeyBinary(): string {
    $env = getenv('BACKUP_DASHBOARD_CRYPT_KEY');
    if ($env !== false && $env !== '') {
        return hash('sha256', $env, true);
    }
    $stored = getConfig('backup_dashboard_data_key', '');
    if ($stored === '') {
        $stored = bin2hex(random_bytes(32));
        setConfig('backup_dashboard_data_key', $stored);
    }
    return hash('sha256', $stored, true);
}
function dupBackupEncryptPayload(string $plain): string {
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', dupBackupCryptKeyBinary(), OPENSSL_RAW_DATA, $iv, $tag, '');
    if ($ct === false) {
        throw new RuntimeException('Encryption failed');
    }
    return json_encode([
        'v' => 1,
        'iv' => base64_encode($iv),
        'ct' => base64_encode($ct),
        'tag' => base64_encode($tag),
    ], JSON_UNESCAPED_SLASHES);
}
function dupBackupDecryptPayload(string $json): ?string {
    $w = json_decode($json, true);
    if (!is_array($w) || !isset($w['iv'], $w['tag']) || !array_key_exists('ct', $w)) {
        return null;
    }
    if ((string)$w['iv'] === '' || (string)$w['tag'] === '') {
        return null;
    }
    $iv = base64_decode((string)$w['iv'], true);
    $ct = base64_decode((string)$w['ct'], true);
    $tag = base64_decode((string)$w['tag'], true);
    if ($iv === false || $ct === false || $tag === false) {
        return null;
    }
    $pt = openssl_decrypt($ct, 'aes-256-gcm', dupBackupCryptKeyBinary(), OPENSSL_RAW_DATA, $iv, $tag, '');
    return $pt === false ? null : $pt;
}
define('DUP_BACKUP_LEGACY_KEY', hash('sha256', 'DuplicatiManager_SecretKey_2024_CHANGE_ME', true));
function dupBackupDecryptLegacyField(string $encB64): string {
    $raw = base64_decode($encB64, true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }
    $iv = substr($raw, 0, 16);
    $ct = substr($raw, 16);
    $pt = openssl_decrypt($ct, 'AES-256-CBC', DUP_BACKUP_LEGACY_KEY, OPENSSL_RAW_DATA, $iv);
    return $pt === false ? '' : $pt;
}
const DUP_BACKUP_CONFIG_KEY = 'dup_backup_secrets_enc';
function dupBackupNormalizeHost(string $host): string {
    $h = trim($host);
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
    return rtrim($h, '/');
}
function dupBackupNormalizeToken(string $token): string {
    $t = trim((string)preg_replace('/^\xEF\xBB\xBF/', '', $token));
    while ($t !== '' && preg_match('/^(authorization\s*:\s*)?bearer\s+/i', $t)) {
        $t = trim((string)preg_replace('/^(authorization\s*:\s*)?bearer\s+/i', '', $t, 1));
    }
    while ($t !== '' && preg_match('/^(authorization\s*:\s*)?preauth\s+/i', $t)) {
        $t = trim((string)preg_replace('/^(authorization\s*:\s*)?preauth\s+/i', '', $t, 1));
    }
    return $t;
}
function dupBackupBuildAuthHeader(string $token): string {
    $t = dupBackupNormalizeToken($token);
    if ($t !== '' && preg_match('/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/', $t)) {
        return 'Authorization: Bearer ' . $t;
    }
    return 'Authorization: PreAuth ' . $t;
}
function dupBackupLoadDecrypted(): array {
    $row = getConfig(DUP_BACKUP_CONFIG_KEY, '');
    if ($row !== '') {
        $inner = dupBackupDecryptPayload($row);
        if ($inner !== null && $inner !== '') {
            $obj = json_decode($inner, true);
            if (is_array($obj)) {
                return [
                    'host' => dupBackupNormalizeHost((string)($obj['host'] ?? '')),
                    'token' => dupBackupNormalizeToken((string)($obj['token'] ?? '')),
                ];
            }
        }
    }
    $legacyFile = __DIR__ . '/dup_config.json';
    if (is_readable($legacyFile)) {
        $data = json_decode((string)file_get_contents($legacyFile), true);
        if (is_array($data)) {
            $h = !empty($data['host']) ? dupBackupDecryptLegacyField((string)$data['host']) : '';
            $t = !empty($data['token']) ? dupBackupDecryptLegacyField((string)$data['token']) : '';
            if ($h !== '' && $t !== '') {
                $h = dupBackupNormalizeHost($h);
                $t = dupBackupNormalizeToken($t);
                dupBackupSaveEncrypted($h, $t);
                @unlink($legacyFile);
                return ['host' => $h, 'token' => $t];
            }
        }
    }
    return ['host' => '', 'token' => ''];
}
function dupBackupSaveEncrypted(string $host, string $token): void {
    $host = dupBackupNormalizeHost($host);
    $token = dupBackupNormalizeToken($token);
    $inner = json_encode(['host' => $host, 'token' => $token], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($inner === false) {
        throw new RuntimeException('JSON encode failed');
    }
    setConfig(DUP_BACKUP_CONFIG_KEY, dupBackupEncryptPayload($inner));
}
function dupBackupClear(): void {
    setConfig(DUP_BACKUP_CONFIG_KEY, '');
}
function backupDashboardParseRwTestPath(string $raw): ?string {
    $s = trim($raw);
    if ($s === '' || str_contains($s, "\0")) {
        return null;
    }
    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $s)) {
        return null;
    }
    if (preg_match('/^file:/i', $s)) {
        $u = preg_replace('/^file:\\/\\//i', '', $s);
        $u = preg_replace('/^localhost\\/?/i', '', $u);
        $u = preg_replace('/^\\/+/', '/', $u);
        $decoded = rawurldecode($u);
        return ($decoded !== '' && isset($decoded[0]) && $decoded[0] === '/') ? $decoded : null;
    }
    return (isset($s[0]) && $s[0] === '/') ? rawurldecode($s) : null;
}
function backupDashboardMainBackupMounted(string $mountPoint): bool {
    $mounts = @file('/proc/mounts', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($mounts)) {
        return false;
    }
    foreach ($mounts as $line) {
        $parts = preg_split('/\s+/', $line);
        if (count($parts) >= 3 && $parts[1] === $mountPoint) {
            return true;
        }
    }
    return false;
}
if (isset($_GET['config'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['config'];
    if ($action === 'save') {
        $raw   = json_decode(file_get_contents('php://input'), true);
        $host  = 'http://localhost:8200';
        $token = dupBackupNormalizeToken((string)($raw['token'] ?? ''));
        if (!$token) { http_response_code(400); echo json_encode(['error'=>'Missing token']); exit; }
        try {
            dupBackupSaveEncrypted($host, $token);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Save failed']);
            exit;
        }
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'load') {
        $creds = dupBackupLoadDecrypted();
        echo json_encode(['host' => $creds['host'], 'token' => $creds['token']]);
        exit;
    }
    if ($action === 'raw') {
        $blob = getConfig(DUP_BACKUP_CONFIG_KEY, '');
        header('Content-Type: application/json; charset=utf-8');
        echo $blob !== '' ? $blob : json_encode(['encrypted' => '']);
        exit;
    }
    if ($action === 'delete') {
        dupBackupClear();
        $legacyFile = __DIR__ . '/dup_config.json';
        if (is_file($legacyFile)) {
            @unlink($legacyFile);
        }
        echo json_encode(['ok'=>true]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['error'=>'Unknown action']);
    exit;
}
if (isset($_GET['disk'])) {
    header('Content-Type: application/json; charset=utf-8');
    $path = $_GET['disk'] ?? '';
    if ($path === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Path required']);
        exit;
    }
    $normalizedPath = realpath($path);
    if ($normalizedPath === false || !is_dir($normalizedPath) && !is_dir(dirname($normalizedPath))) {
        $normalizedPath = $path;
    }
    $driveRoot = $normalizedPath;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        if (preg_match('/^([a-zA-Z]):/', $normalizedPath, $matches)) {
            $driveRoot = $matches[1] . ':\\';
        }
    } else {
        $driveRoot = '/';
    }
    $total = @disk_total_space($driveRoot);
    $free = @disk_free_space($driveRoot);
    if ($total === false || $free === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Could not get disk space for path', 'path' => $driveRoot]);
        exit;
    }
    $used = $total - $free;
    $percentUsed = $total > 0 ? round(($used / $total) * 100, 2) : 0;
    echo json_encode([
        'path' => $driveRoot,
        'total' => $total,
        'free' => $free,
        'used' => $used,
        'percentUsed' => $percentUsed,
    ]);
    exit;
}
if (isset($_GET['path_rw_test'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST required']);
        exit;
    }
    $body = json_decode((string)file_get_contents('php://input'), true);
    $pathIn = is_array($body) ? (string)($body['path'] ?? '') : '';
    $local = backupDashboardParseRwTestPath($pathIn);
    if ($local === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Only local absolute paths or file:// URLs']);
        exit;
    }
    if (str_starts_with($local, '/mnt/main_backup') && !backupDashboardMainBackupMounted('/mnt/main_backup')) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Backup disk not mounted',
            'hint' => 'Mount /dev/sdc1 on /mnt/main_backup (see /etc/fstab).',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $real = realpath($local);
    if ($real === false || !is_dir($real)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Directory does not exist', 'path' => $local]);
        exit;
    }
    $phpUser = 'unknown';
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $pw = posix_getpwuid(posix_geteuid());
        if (is_array($pw) && isset($pw['name'])) {
            $phpUser = (string)$pw['name'];
        }
    }
    $tmpBase = $real . DIRECTORY_SEPARATOR . '.backup_dashboard_rw_' . bin2hex(random_bytes(8)) . '.tmp';
    $payload = 'backup_dashboard_rw_' . bin2hex(random_bytes(8));
    if (@file_put_contents($tmpBase, $payload, LOCK_EX) === false) {
        $why = error_get_last();
        echo json_encode([
            'ok' => false,
            'write' => false,
            'read' => false,
            'path' => $real,
            'phpUser' => $phpUser,
            'error' => 'Write denied',
            'detail' => is_array($why) ? (string)($why['message'] ?? '') : '',
            'hint' => 'Check write permissions on /mnt/main_backup for the PHP/web user.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $got = @file_get_contents($tmpBase);
    @unlink($tmpBase);
    $readOk = ($got === $payload);
    echo json_encode([
        'ok' => $readOk,
        'write' => true,
        'read' => $readOk,
        'path' => $real,
        'phpUser' => $phpUser,
        'hint' => $readOk ? '' : 'Read mismatch after write',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
function backupDashboardHostFsDecodePath(string $raw): ?string {
    $s = trim($raw);
    if ($s === '' || str_contains($s, "\0")) {
        return null;
    }
    if (preg_match('/^file:/i', $s)) {
        return backupDashboardParseRwTestPath($s);
    }
    if (isset($s[0]) && $s[0] === '/') {
        return rawurldecode($s);
    }
    return null;
}
function backupDashboardHostFsListDir(string $pathIn, bool $onlyFolders, bool $showHidden): array {
    $local = backupDashboardHostFsDecodePath($pathIn);
    if ($local === null) {
        throw new InvalidArgumentException('Invalid path');
    }
    if (str_starts_with($local, '/mnt/main_backup') && !backupDashboardMainBackupMounted('/mnt/main_backup')) {
        throw new RuntimeException('Backup disk not mounted');
    }
    $real = realpath($local);
    if ($real === false || !is_dir($real)) {
        throw new RuntimeException('Not a directory');
    }
    $dh = @opendir($real);
    if ($dh === false) {
        throw new RuntimeException('Cannot read directory');
    }
    $out = [];
    while (($name = readdir($dh)) !== false) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if (!$showHidden && isset($name[0]) && $name[0] === '.') {
            continue;
        }
        $full = $real . DIRECTORY_SEPARATOR . $name;
        $isDir = is_dir($full);
        if ($onlyFolders && !$isDir) {
            continue;
        }
        $mtime = @filemtime($full);
        $iso = ($mtime !== false) ? gmdate('Y-m-d\TH:i:s\Z', (int)$mtime) : null;
        $out[] = [
            'path' => $full,
            'Path' => $full,
            'leaf' => !$isDir,
            'Leaf' => !$isDir,
            'LastWriteTime' => $iso,
        ];
    }
    closedir($dh);
    usort($out, function ($a, $b) {
        $adir = !$a['leaf'];
        $bdir = !$b['leaf'];
        if ($adir !== $bdir) {
            return $adir ? -1 : 1;
        }
        return strcasecmp(basename($a['path']), basename($b['path']));
    });
    return $out;
}
if (isset($_GET['host_fs'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string)($_GET['host_fs'] ?? '');
    if ($action === 'list') {
        $pathIn = (string)($_GET['path'] ?? '');
        $onlyFolders = (string)($_GET['onlyFolders'] ?? 'true') !== 'false';
        $showHidden = (string)($_GET['showHidden'] ?? 'false') === 'true';
        try {
            $list = backupDashboardHostFsListDir($pathIn, $onlyFolders, $showHidden);
            echo json_encode($list, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $e) {
            http_response_code(400);
            $payload = ['error' => $e->getMessage(), 'path' => $pathIn];
            if ($e->getMessage() === 'Backup disk not mounted') {
                $payload['hint'] = 'Mount /dev/sdc1 on /mnt/main_backup (see /etc/fstab).';
            } elseif ($e->getMessage() === 'Not a directory') {
                $payload['hint'] = 'Directory does not exist or is not accessible.';
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    if ($action === 'mkdir') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            exit;
        }
        $body = json_decode((string)file_get_contents('php://input'), true);
        $parentRaw = is_array($body) ? (string)($body['parent'] ?? '') : '';
        $name = is_array($body) ? trim((string)($body['name'] ?? '')) : '';
        $parentLocal = backupDashboardHostFsDecodePath($parentRaw);
        if ($parentLocal === null || $name === '' || preg_match('/[\/\\\\]/', $name) || $name === '.' || $name === '..') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parent or name']);
            exit;
        }
        if (str_starts_with($parentLocal, '/mnt/main_backup') && !backupDashboardMainBackupMounted('/mnt/main_backup')) {
            http_response_code(400);
            echo json_encode(['error' => 'Backup disk not mounted']);
            exit;
        }
        $parentReal = realpath($parentLocal);
        if ($parentReal === false || !is_dir($parentReal)) {
            http_response_code(400);
            echo json_encode(['error' => 'Parent not found']);
            exit;
        }
        $newPath = $parentReal . DIRECTORY_SEPARATOR . $name;
        if (file_exists($newPath)) {
            http_response_code(400);
            echo json_encode(['error' => 'Already exists']);
            exit;
        }
        if (!is_writable($parentReal)) {
            http_response_code(403);
            echo json_encode([
                'error' => 'Write denied for parent directory',
                'parent' => $parentReal,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!@mkdir($newPath, 0775)) {
            $why = error_get_last();
            http_response_code(500);
            echo json_encode([
                'error' => 'mkdir failed',
                'parent' => $parentReal,
                'path' => $newPath,
                'detail' => is_array($why) ? (string)($why['message'] ?? '') : '',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $final = realpath($newPath) ?: $newPath;
        echo json_encode(['ok' => true, 'path' => $final], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => 'Unknown host_fs action']);
    exit;
}
if (isset($_GET['proxy'])) {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $raw      = json_decode(file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $token    = dupBackupNormalizeToken((string)($raw['token']    ?? ''));
    $endpoint = $raw['endpoint'] ?? '';
    $method   = strtoupper($raw['method'] ?? 'GET');
    $data     = $raw['data']     ?? null;
    if (!$endpoint) {
        http_response_code(400);
        echo json_encode(['error'=>'Missing endpoint']);
        exit;
    }
    $creds     = dupBackupLoadDecrypted();
    if ($creds['host'] === '' || ($token === '' && $creds['token'] === '')) {
        http_response_code(503);
        echo json_encode(['error' => 'Duplicati is not configured', 'hint' => 'Set Duplicati host and API token in Backup settings.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($token === '') {
        $token = $creds['token'];
    }
    $proxyHost = $creds['host'];
    $url       = rtrim($proxyHost, '/') . '/api/v1/' . ltrim($endpoint, '/');
    $cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dup_sess_' . md5(session_id()) . '.txt';
    session_write_close();
    $ch  = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            dupBackupBuildAuthHeader($token),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_COOKIEJAR      => $cookieFile,
    ]);
    if (in_array($method, ['POST','PUT','PATCH'])) {
        $body = ($data !== null) ? json_encode($data) : '{}';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) { http_response_code(502); echo json_encode(['error'=>'cURL: '.$curlError]); }
    else            { http_response_code($httpCode); echo $response ?: '{}'; }
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php $bdLang = isset($_GET['lang']) && preg_match('/^(en|ru|uk|be)$/', (string)$_GET['lang']) ? $_GET['lang'] : 'en'; echo htmlspecialchars($bdLang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Duplicati Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --dynamic-accent: <?php echo htmlspecialchars($bdColor, ENT_QUOTES, 'UTF-8'); ?>;
}
[data-theme="dark"] {
    --accent:       var(--dynamic-accent);
    --accent-hover: <?php 
        // Calculate darker hover color
        $color = $bdColor;
        $r = max(0, hexdec(substr($color, 1, 2)) - 30);
        $g = max(0, hexdec(substr($color, 3, 2)) - 30);
        $b = max(0, hexdec(substr($color, 5, 2)) - 30);
        echo sprintf('#%02x%02x%02x', $r, $g, $b);
    ?>;
    --accent-dim:   <?php echo $bdColor; ?>2e;
    --green:        #28a745;
    --green-dim:    rgba(40, 167, 69, 0.15);
    --red:          #dc3545;
    --red-dim:      rgba(220, 53, 69, 0.15);
    --amber:        #ffc107;
    --amber-dim:    rgba(255, 193, 7, 0.15);
    --bg-base:      #454446;
    --bg-raised:    #333333;
    --bg-overlay:   #444442;
    --bg-hover:     #555555;
    --border:       #5A5A5A;
    --border-lit:   #6B6B6B;
    --text-primary:   #E0E0E0;
    --text-secondary: #A0A0A0;
    --text-muted:     #8A8A8A;
}
[data-theme="light"] {
    --accent:       #6b6f78;
    --accent-hover: #555962;
    --accent-dim:   color-mix(in srgb, var(--accent) 16%, transparent);
    --green:        #3d7a52;
    --green-dim:    #e4f0e8;
    --red:          #b84c4c;
    --red-dim:      #fce8e8;
    --amber:        #b8862a;
    --amber-dim:    #f7efd9;
    --bg-base:      #e8eaed;
    --bg-raised:    #f2f3f6;
    --bg-overlay:   #e2e4e9;
    --bg-hover:     #dadde3;
    --border:       #c8ccd4;
    --border-lit:   #aeb3bd;
    --text-primary:   #1a1c20;
    --text-secondary: #4a4e58;
    --text-muted:     #7a7f8a;
}
:root {
    --radius-sm: 6px; --radius-md: 10px; --radius-lg: 16px;
    --shadow:    0 2px 12px rgba(0,0,0,.45);
    --shadow-lg: 0 8px 32px rgba(0,0,0,.6);
}
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg-base);
    color: var(--text-primary);
    min-height: 100vh;
    font-size: 14px;
    line-height: 1.6;
    transition: background .2s, color .2s;
}
::-webkit-scrollbar { width:6px; height:6px; }
::-webkit-scrollbar-track { background: var(--bg-base); }
::-webkit-scrollbar-thumb { background: var(--border-lit); border-radius:3px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
.shell {
    display: grid;
    grid-template-columns: 220px 1fr;
    grid-template-rows: auto 1fr;
    min-height: 100vh;
}
.topbar {
    grid-column: 1/-1;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 24px; height: 56px;
    background: var(--bg-raised);
    border-bottom: 1px solid var(--border);
    position: sticky; top:0; z-index:100;
}
.brand { display:flex; align-items:center; gap:10px; }
.brand-icon { color: var(--accent); }
.brand h1 {
    font-family: 'JetBrains Mono', monospace;
    font-size:15px; font-weight:600; letter-spacing:-.3px; color:var(--text-primary);
}
.brand span { color: var(--accent); }
.topbar-right { display:flex; align-items:center; gap:10px; }
.status-pill {
    display:flex; align-items:center; gap:6px;
    padding:5px 12px; border-radius:20px;
    font-size:12px; font-weight:500;
    background: var(--bg-overlay); border:1px solid var(--border); color:var(--text-secondary);
}
.status-pill.online  { border-color:var(--green); color:var(--green); }
.status-pill.offline { border-color:var(--red);   color:var(--red); }
.status-dot { width:7px; height:7px; border-radius:50%; background:currentColor; }
.status-pill.online .status-dot { animation: pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.3} }
.sidebar {
    background: var(--bg-raised);
    border-right: 1px solid var(--border);
    padding: 20px 0;
    display: flex; flex-direction:column; gap:2px;
    overflow-y: auto;
    max-height: calc(100vh - 56px);
}
.sidebar-section { padding:0 12px; margin-bottom:8px; }
.sidebar-label {
    font-size:10px; font-weight:600; letter-spacing:1px;
    text-transform:uppercase; color:var(--text-muted);
    padding:0 8px; margin-bottom:4px;
}
.nav-item {
    display:flex; align-items:center; gap:10px;
    padding:9px 10px; border-radius:var(--radius-sm);
    color:var(--text-secondary); cursor:pointer;
    transition:all .15s; font-size:13px; font-weight:500;
    border:none; background:none; width:100%; text-align:left;
}
.nav-item svg { flex-shrink:0; opacity:.7; }
.nav-item:hover { background:var(--bg-hover); color:var(--text-primary); }
.nav-item:hover svg { opacity:1; }
.nav-item.active { background:var(--accent-dim); color:var(--accent); }
.nav-item.active svg { opacity:1; }
.main { padding:28px; overflow-y:auto; display:flex; flex-direction:column; gap:24px; }
.btn {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 16px; border-radius:var(--radius-sm);
    font-size:13px; font-weight:500; font-family:inherit;
    cursor:pointer; border:1px solid var(--border);
    background:var(--bg-overlay); color:var(--text-primary);
    transition:all .15s; white-space:nowrap;
}
.btn:hover { background:var(--bg-hover); border-color:var(--border-lit); }
.btn-primary { background:var(--accent); border-color:var(--accent); color:#fff; }
.btn-primary:hover { background:var(--accent-hover); border-color:var(--accent-hover); }
.btn-success { background:var(--green); border-color:var(--green); color:#0a1f15; }
.btn-success:hover { background:#32b87a; }
.btn-danger  { background:var(--red);   border-color:var(--red);   color:#fff; }
.btn-danger:hover  { background:#dc5c5c; }
.btn-amber   { background:var(--amber); border-color:var(--amber); color:#1a0a00; }
.btn-amber:hover   { background:#dc9520; }
.btn-ghost   { background:transparent; border-color:var(--border); }
.btn-ghost:hover { background:var(--bg-hover); }
.btn-sm   { padding:6px 12px; font-size:12px; }
.btn-icon { padding:7px; }
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px,1fr)); gap:14px; margin-bottom:28px; }
.stat-card {
    background:var(--bg-raised); border:1px solid var(--border);
    border-radius:var(--radius-md); padding:18px;
    transition:border-color .2s, transform .2s;
}
.stat-card:hover { border-color:var(--border-lit); transform:translateY(-1px); }
.stat-icon {
    width:36px; height:36px; border-radius:var(--radius-sm);
    display:flex; align-items:center; justify-content:center; margin-bottom:14px;
}
.stat-icon.blue   { background:var(--accent-dim); color:var(--accent); }
.stat-icon.green  { background:var(--accent-dim); color:var(--green); }
.stat-icon.amber  { background:var(--accent-dim); color:var(--amber); }
.stat-icon.purple { background:var(--accent-dim); color:var(--accent); }
.stat-value { font-family:'JetBrains Mono',monospace; font-size:28px; font-weight:600; line-height:1; margin-bottom:6px; }
.stat-label { font-size:12px; color:var(--text-secondary); font-weight:500; }
.card { background:var(--bg-raised); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.card-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 20px; border-bottom:1px solid var(--border);
    gap:12px; flex-wrap:wrap;
}
.card-title { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--text-primary); }
.card-title svg { color:var(--accent); }
.card-actions { display:flex; gap:8px; flex-wrap:wrap; }
.card-body { padding:20px; }
.table-wrap { overflow-x:auto; max-width:100%; }
table { width:100%; border-collapse:collapse; max-width:100%; }
thead th {
    padding:10px 16px; text-align:left; font-size:11px; font-weight:600;
    text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted);
    border-bottom:1px solid var(--border); white-space:nowrap;
}
tbody td { padding:12px 16px; border-bottom:1px solid var(--border); vertical-align:middle; font-size:13px; }
tbody tr:last-child td { border-bottom:none; }
tbody tr:hover td { background:var(--bg-overlay); }
.backup-name { font-weight:500; }
.backup-id { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--text-muted); }
.badge { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; }
.badge-success { background:var(--green-dim); color:var(--green); }
.badge-error   { background:var(--red-dim);   color:var(--red); }
.badge-warning { background:var(--amber-dim); color:var(--amber); }
.badge-neutral { background:var(--bg-overlay);color:var(--text-secondary); }
.badge-running { background:var(--accent-dim);color:var(--accent); }
.badge-idle { background:var(--bg-overlay);color:var(--text-secondary); }
.row-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.action-btn {
    display:inline-flex; align-items:center; justify-content:center;
    gap:5px; padding:5px 10px; border-radius:var(--radius-sm);
    font-size:11px; font-weight:500; cursor:pointer;
    border:1px solid transparent; transition:all .15s; font-family:inherit; white-space:nowrap;
}
.ab-run    { background:rgba(108,117,125,.12);  color:var(--green);  border-color:var(--green-dim); }
.ab-edit   { background:rgba(108,117,125,.12);  color:var(--accent); border-color:var(--accent-dim); }
.ab-delete { background:rgba(242,107,107,.12); color:var(--red);    border-color:var(--red-dim); }
.ab-logs   { background:rgba(108,117,125,.12); color:var(--accent); border-color:var(--accent-dim); }
.ab-stop   { background:rgba(108,117,125,.12);  color:var(--amber);  border-color:var(--amber-dim); }
.ab-verify { background:rgba(108,117,125,.08);  color:var(--green);  border-color:var(--green-dim); }
.ab-info   { background:rgba(108,117,125,.12);  color:var(--accent); border-color:var(--accent-dim); }
.action-btn:hover { filter:brightness(1.08); transform:translateY(-1px); border-color:var(--accent-dim); color:var(--accent); }
.action-btn.ab-run:hover, .action-btn.ab-verify:hover { color:var(--green); border-color:var(--green-dim); }
.action-btn.ab-stop:hover { color:var(--amber); border-color:var(--amber-dim); }
.action-btn.ab-delete:hover { color:var(--red); border-color:var(--red-dim); filter:brightness(1.05); }
.ab-more { min-width:34px; padding:5px 8px; color:var(--accent); border-color:var(--accent-dim); }
.actions-dropdown { position:relative; display:inline-flex; vertical-align:middle; z-index:1; }
.actions-dropdown.is-open { z-index:500; }
tbody tr.actions-menu-row-open { position:relative; z-index:500; }
.actions-menu {
    display:none; position:fixed; z-index:99999;
    min-width:188px; padding:6px 0; margin:0; list-style:none;
    background:var(--bg-raised); border:1px solid var(--border-lit); border-radius:var(--radius-sm);
    box-shadow:var(--shadow-lg); max-width:min(220px, calc(100vw - 16px)); max-height:calc(100vh - 16px); overflow:auto;
}
.actions-menu.open { display:block; }
.actions-menu li { list-style:none; margin:0; padding:0; }
.actions-menu button {
    display:flex; align-items:center; gap:8px; width:100%; padding:8px 14px; border:none; background:none;
    font:inherit; font-size:12px; font-weight:500; color:var(--text-primary); cursor:pointer; text-align:left;
}
.actions-menu button:hover { background:var(--accent-dim); color:var(--accent); }
.actions-menu button.danger:hover { background:var(--red-dim); color:var(--red); }
.source-tree-panel {
    border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-base);
    max-height:220px; margin-bottom:10px; display:flex; flex-direction:column; overflow:hidden;
    min-height:120px;
}
.dest-tree-panel {
    border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-base);
    max-height:220px; margin-bottom:10px; display:flex; flex-direction:column; overflow:hidden;
    min-height:120px;
}
.fs-tree-toolbar { flex-shrink:0; display:flex; gap:8px; align-items:center; padding:8px; border-bottom:1px solid var(--border); background:var(--bg-overlay); }
.fs-tree-toolbar .fs-tree-filter {
    flex:1 1 280px; min-width:220px; padding:6px 10px 6px 32px; font-size:12px; border:1px solid var(--border); border-radius:var(--radius-sm);
    background-color:var(--bg-base); color:var(--text-primary);
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%238b93a7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-3.5-3.5'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:10px 50%; background-size:14px 14px;
}
.fs-tree-toolbar .fs-tree-filter::-webkit-search-cancel-button { -webkit-appearance:none; appearance:none; }
.fs-tree-current-path { flex:1 1 100%; min-width:0; font-size:11px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.source-tree-root { flex:1; overflow:auto; overflow-x:hidden; min-height:80px; }
.source-tree-hint, .source-tree-loading { padding:12px 14px; font-size:12px; color:var(--text-muted); }
.fs-tree-row {
    display:flex; align-items:center; gap:6px; min-height:30px; padding:2px 8px 2px 4px;
    font-size:12px; border-radius:var(--radius-sm); max-width:100%;
}
.fs-tree-row:hover { background:var(--bg-overlay); }
.fs-tree-spacer { display:inline-block; width:18px; flex-shrink:0; }
.fs-tree-caret {
    width:22px; height:22px; flex-shrink:0; border:none; background:none; padding:0; cursor:pointer;
    color:var(--text-secondary); border-radius:4px; display:inline-flex; align-items:center; justify-content:center;
}
.fs-tree-caret:hover { background:var(--accent-dim); color:var(--accent); }
.fs-tree-caret.loading { opacity:.45; pointer-events:none; }
.fs-tree-item { display:block; }
.fs-tree-children { margin-left:18px; padding-left:8px; border-left:1px solid var(--border); }
.fs-tree-check { flex:0 0 14px; flex-shrink:0; width:14px; height:14px; margin:0; padding:0; border:none; background:none; box-shadow:none; accent-color:var(--accent); cursor:pointer; appearance:auto; -webkit-appearance:auto; }
.fs-tree-label { flex:1; min-width:0; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-right:6px; }
.target-url-pill {
    display:inline-flex; align-items:center; gap:8px; max-width:100%;
    padding:4px 10px; border-radius:var(--radius-sm); background:var(--bg-overlay); border:1px solid var(--border);
    font-size:11px; vertical-align:middle;
}
.target-url-kind { font-weight:600; color:var(--accent); text-transform:uppercase; letter-spacing:.4px; font-size:10px; flex-shrink:0; }
.target-url-path { font-family:'JetBrains Mono',monospace; color:var(--text-primary); word-break:break-all; }
body.bd-embed { overflow:hidden; min-height:100vh; max-height:100vh; height:100vh; -webkit-overflow-scrolling:touch; }
body.bd-embed .shell { min-height:0; height:100%; max-height:100vh; grid-template-rows:auto minmax(0,1fr); }
body.bd-embed .sidebar, body.bd-embed .main { min-height:0; }
body.bd-embed .main { overflow-y:auto; overflow-x:hidden; -webkit-overflow-scrolling:touch; padding:12px 14px; gap:14px; padding-bottom: 20px; }
body.bd-embed #section-logs .card { height:calc(100vh - 150px); display:flex; flex-direction:column; }
body.bd-embed #section-logs .logs-wrap { flex:1; min-height:220px; max-height:none; }
body.bd-embed .stats-grid { margin-bottom:12px; gap:8px; }
body.bd-embed .topbar { height:52px; padding:0 12px; flex-wrap:wrap; }
body.bd-embed .card-header { padding:12px 14px; }
body.bd-embed .card-body { padding:14px; }
body.bd-embed .table-wrap { margin:0; }
body.bd-embed table { font-size:12px; }
body.bd-embed thead th, body.bd-embed tbody td { padding:8px 10px; }
.logs-wrap {
    padding:12px 16px;
    font-family:'JetBrains Mono',monospace; font-size:12px;
    max-height:min(78vh, calc(100vh - 190px)); overflow-y:auto;
    background:var(--bg-base);
}
.log-line {
    display:flex; gap:10px; padding:5px 0;
    border-bottom:1px solid rgba(51,55,63,.3); line-height:1.6;
}
.log-line:last-child { border-bottom:none; }
.log-ts  { color:var(--text-muted); flex-shrink:0; font-size:10px; min-width:65px; }
.log-badge {
    flex-shrink:0; padding:1px 7px; border-radius:4px;
    font-size:10px; font-weight:700; align-self:flex-start; margin-top:2px;
}
.lb-error   { background:var(--red-dim);    color:var(--red); }
.lb-warning { background:var(--amber-dim);  color:var(--amber); }
.lb-success { background:var(--green-dim);  color:var(--green); }
.lb-info    { background:var(--accent-dim); color:var(--accent); }
.lb-debug   { background:var(--bg-overlay); color:var(--text-muted); }
.log-msg { color:var(--text-secondary); word-break:break-word; flex:1; }
.log-line.log-error   .log-msg { color:var(--red); }
.log-line.log-warning .log-msg { color:var(--amber); }
.log-line.log-success .log-msg { color:var(--green); }
.log-line.log-info    .log-msg { color:var(--text-primary); }
.log-report {
    background:var(--bg-base); border-radius:var(--radius-md);
    padding:16px; margin:12px 16px;
    border:1px solid var(--border);
}
.log-report-title {
    font-size:12px; font-weight:600; color:var(--text-secondary);
    text-transform:uppercase; letter-spacing:.6px; margin-bottom:12px;
}
.log-report-grid {
    display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:10px;
}
.log-kv { display:flex; flex-direction:column; gap:2px; }
.log-kv-key { font-size:10px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; }
.log-kv-val { font-size:13px; font-weight:500; color:var(--text-primary); font-family:'JetBrains Mono',monospace; }
.log-kv-val.ok  { color:var(--green); }
.log-kv-val.err { color:var(--red); }
.log-messages { margin-top:12px; }
.log-msg-item {
    display:flex; gap:8px; padding:4px 0;
    border-bottom:1px solid var(--border); font-size:12px; font-family:'JetBrains Mono',monospace;
}
.log-msg-item:last-child { border-bottom:none; }
.log-msg-ts { color:var(--text-muted); flex-shrink:0; font-size:10px; }
.log-msg-text { color:var(--text-secondary); word-break:break-word; flex:1; }
.progress-track { width:100%; height:8px; background:var(--border); border-radius:6px; overflow:hidden; box-shadow:inset 0 1px 3px rgba(0,0,0,.3); }
.progress-fill  { height:100%; background:linear-gradient(90deg,var(--green),var(--accent)); border-radius:6px; transition:width .5s cubic-bezier(.4,0,.2,1); box-shadow:0 0 8px color-mix(in srgb,var(--accent) 50%,transparent); }
@keyframes indeterminate { 0%{transform:translateX(-100%);width:55%} 100%{transform:translateX(220%);width:55%} }
.progress-shimmer { position:relative; overflow:hidden; }
.progress-shimmer::after { content:''; position:absolute; inset:0; background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.15) 50%,transparent 100%); animation:shimmer 2s infinite; }
@keyframes shimmer { 0%{transform:translateX(-100%)} 100%{transform:translateX(100%)} }
.modal {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.55); backdrop-filter:blur(4px);
    z-index:20000; justify-content:center; align-items:center; padding:20px;
}
.modal.active { display:flex; }
.modal-box {
    background:var(--bg-raised); border:1px solid var(--border-lit);
    border-radius:var(--radius-lg); padding:24px;
    width:100%; max-width:720px; max-height:90vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:var(--shadow-lg);
}
.modal-box .modal-form, .modal-box > div:not(.modal-head):not(.modal-footer) { overflow-y:auto; flex:1; min-height:0; }
.modal-scroll-body { overflow-y:auto; flex:1; min-height:0; padding-right:2px; }
.modal-head {
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid var(--border);
}
.modal-head h3 { font-size:15px; font-weight:600; }
.close-btn {
    background:none; border:none; color:var(--text-secondary); cursor:pointer;
    padding:4px; border-radius:var(--radius-sm); display:flex; align-items:center; transition:background .15s;
}
.close-btn:hover { background:var(--bg-hover); color:var(--text-primary); }
.modal-form .field { margin-bottom:14px; }
.modal-footer {
    display:flex; justify-content:flex-end; gap:8px;
    margin-top:20px; padding-top:16px; border-top:1px solid var(--border);
}
.field small { display:block; font-size:11px; color:var(--text-muted); margin-top:4px; }
.form-note {
    background:var(--bg-overlay); border:1px solid var(--border); border-radius:var(--radius-sm);
    padding:10px 14px; font-size:12px; color:var(--text-secondary); margin-top:14px; display:flex; gap:8px;
}
.form-note svg { flex-shrink:0; margin-top:2px; color:var(--amber); }
.field label {
    display:block; font-size:11px; font-weight:600;
    color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px;
}
.field input:not([type="checkbox"]):not([type="radio"]), .field select, .field textarea {
    width:100%; padding:9px 12px;
    background:var(--bg-base); border:1px solid var(--border);
    border-radius:var(--radius-sm); color:var(--text-primary);
    font-size:13px; font-family:inherit; transition:border-color .15s, box-shadow .15s;
}
.field input:not([type="checkbox"]):not([type="radio"]):focus, .field select:focus, .field textarea:focus {
    outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-dim);
}
.field input:not([type="checkbox"]):not([type="radio"])::placeholder, .field textarea::placeholder { color:var(--text-muted); }
.field select { appearance:none; cursor:pointer; }
.field-tree-caption { font-size:11px; font-weight:600; color:var(--text-secondary); margin:0 0 6px; }
.field { flex:1; min-width:180px; }
.modal:has(.wizard-modal-box) { padding:0; align-items:stretch; background:rgba(0,0,0,.42); }
.wizard-modal-box {
    max-width:100vw; width:100vw;
    max-height:100vh; height:100vh; padding:10px 14px;
    border-radius:0; border:none; box-shadow:none;
}
.wizard-modal-box .modal-head { margin-bottom:10px; padding-bottom:10px; }
body.bd-embed .modal:has(.wizard-modal-box) { padding:0; }
body.bd-embed .wizard-modal-box {
    max-width:100vw; width:100vw;
    max-height:100vh; height:100vh;
}
.modal-box.wizard-modal-box .modal-scroll-body {
    overflow-y:auto; overflow-x:hidden; flex:1; min-height:0; padding-right:2px;
}
.modal-box.wizard-modal-box .modal-form.backup-wizard {
    overflow:visible; display:block; height:auto; min-height:0;
}
.backup-wizard { display:block; }
.wizard-steps {
    --wizard-progress: 0%;
    --wizard-progress-ratio: 0;
    --wizard-step-count: 5;
    position:relative; display:flex; align-items:flex-start; justify-content:space-between;
    gap:10px; padding:4px 4px 14px; margin-bottom:8px; flex-shrink:0;
}
.wizard-steps::before,
.wizard-steps::after {
    content:''; position:absolute;
    left:calc(50% / var(--wizard-step-count));
    right:calc(50% / var(--wizard-step-count));
    top:17px; height:2px; border-radius:999px;
}
.wizard-steps::before { background:var(--border); }
.wizard-steps::after {
    right:auto;
    width:calc((100% - (100% / var(--wizard-step-count))) * var(--wizard-progress-ratio));
    background:linear-gradient(90deg, var(--accent), color-mix(in srgb, var(--accent) 55%, #fff));
    transition:width .35s cubic-bezier(.4,0,.2,1);
}
.wizard-step {
    position:relative; z-index:1; flex:1; min-width:0;
    display:flex; flex-direction:column; align-items:center; gap:6px;
    border:none; background:transparent; color:var(--text-muted); cursor:pointer;
    padding:0; font:inherit; transition:color .2s ease;
}
.wizard-step:hover { color:var(--text-primary); }
.wizard-step-dot {
    width:24px; height:24px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    border:2px solid var(--border-lit); background:var(--bg-raised);
    color:var(--text-muted); font-size:11px; font-weight:700;
    transition:all .3s ease;
}
.wizard-step-label {
    font-size:11px; font-weight:600; text-align:center; line-height:1.2;
    max-width:100%;
}
.wizard-step.completed .wizard-step-dot,
.wizard-step.active .wizard-step-dot {
    border-color:var(--accent); background:var(--accent); color:#fff;
    box-shadow:0 0 0 4px color-mix(in srgb, var(--accent) 16%, transparent);
}
.wizard-step.active .wizard-step-label,
.wizard-step.completed .wizard-step-label { color:var(--text-primary); }
.wizard-panel.active[data-step-panel="1"] .field.full,
.wizard-panel.active[data-step-panel="2"] .field.full,
.wizard-panel.active[data-edit-step-panel="1"] .field.full,
.wizard-panel.active[data-edit-step-panel="2"] .field.full {
    display:grid; grid-template-columns:minmax(0,1fr) minmax(260px,38%);
    grid-template-rows:auto; column-gap:14px; row-gap:8px; align-items:start;
    margin-bottom:0;
}
.wizard-panel.active[data-step-panel="1"] .field.full > label,
.wizard-panel.active[data-step-panel="1"] .field.full > .field-tree-caption,
.wizard-panel.active[data-step-panel="1"] .field.full > small,
.wizard-panel.active[data-step-panel="1"] .field.full > input,
.wizard-panel.active[data-step-panel="1"] .field.full > .wizard-inline-actions,
.wizard-panel.active[data-step-panel="1"] .field.full > .field-hint,
.wizard-panel.active[data-step-panel="1"] .field.full > .field-error,
.wizard-panel.active[data-step-panel="2"] .field.full > label,
.wizard-panel.active[data-step-panel="2"] .field.full > .field-tree-caption,
.wizard-panel.active[data-step-panel="2"] .field.full > small,
.wizard-panel.active[data-step-panel="2"] .field.full > textarea,
.wizard-panel.active[data-step-panel="2"] .field.full > .field-error,
.wizard-panel.active[data-edit-step-panel="1"] .field.full > label,
.wizard-panel.active[data-edit-step-panel="1"] .field.full > .field-tree-caption,
.wizard-panel.active[data-edit-step-panel="1"] .field.full > small,
.wizard-panel.active[data-edit-step-panel="1"] .field.full > input,
.wizard-panel.active[data-edit-step-panel="1"] .field.full > .wizard-inline-actions,
.wizard-panel.active[data-edit-step-panel="1"] .field.full > .field-hint,
.wizard-panel.active[data-edit-step-panel="1"] .field.full > .field-error,
.wizard-panel.active[data-edit-step-panel="2"] .field.full > label,
.wizard-panel.active[data-edit-step-panel="2"] .field.full > .field-tree-caption,
.wizard-panel.active[data-edit-step-panel="2"] .field.full > small,
.wizard-panel.active[data-edit-step-panel="2"] .field.full > textarea,
.wizard-panel.active[data-edit-step-panel="2"] .field.full > .field-error {
    grid-column:1;
}
.wizard-panel.active[data-step-panel="1"] .dest-tree-panel,
.wizard-panel.active[data-step-panel="2"] .source-tree-panel,
.wizard-panel.active[data-edit-step-panel="1"] .dest-tree-panel,
.wizard-panel.active[data-edit-step-panel="2"] .source-tree-panel {
    grid-column:2; grid-row:1 / span 20; align-self:start;
    margin-bottom:0; width:100%;
    height:min(360px, calc(100vh - 220px)); max-height:min(360px, calc(100vh - 220px)); min-height:180px;
}
.wizard-panel { display:none; }
.wizard-panel.active { display:block; }
.wizard-panel.active .wizard-grid { display:grid; gap:10px; }
.backup-wizard .field { margin-bottom:10px; }
.backup-wizard .field:last-child { margin-bottom:0; }
.backup-wizard .wizard-panel h4 { margin:0 0 10px; font-size:14px; }
.backup-wizard .wizard-nav { margin-top:12px; padding-top:12px; }
.backup-wizard .form-note { margin-top:10px; }
.backup-wizard .schedule-preview { margin-bottom:0; }
.backup-wizard .retention-box { gap:8px; padding:10px 12px; }
.backup-wizard .password-row { gap:8px; }
.backup-wizard .field input:not([type="checkbox"]):not([type="radio"]),
.backup-wizard .field select,
.backup-wizard .field textarea { padding:8px 10px; font-size:13px; }
.backup-wizard .field textarea { min-height:0; resize:vertical; }
.backup-wizard .field label { margin-bottom:4px; }
.backup-wizard .field small { margin-top:3px; line-height:1.35; }
.backup-wizard .field-hint { margin-top:4px; line-height:1.35; }
.wizard-grid { display:grid; gap:10px; grid-template-columns:repeat(2, minmax(0, 1fr)); }
.wizard-grid .field.full { grid-column:1 / -1; }
.wizard-static-info {
    padding:10px 12px; border-radius:var(--radius-sm); background:var(--bg-overlay);
    border:1px solid var(--border); color:var(--text-secondary); font-size:12px;
}
.password-row { display:grid; gap:10px; grid-template-columns:minmax(0, 1fr) minmax(0, 1fr); }
.wizard-inline-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.checkline, .radioline, .switchline { display:flex; align-items:center; gap:8px; color:var(--text-secondary); font-size:12px; font-weight:500; }
.checkline input, .radioline input, .switchline input { width:auto; accent-color:var(--accent); }
.eye-toggle {
    display:inline-flex; align-items:center; justify-content:center;
    width:36px; height:36px; border:1px solid var(--border); border-radius:50%;
    background:var(--bg-overlay); color:var(--text-secondary); cursor:pointer; transition:all .15s;
}
.eye-toggle:hover { background:var(--bg-hover); color:var(--text-primary); border-color:var(--border-lit); }
.eye-toggle.active { color:var(--accent); border-color:var(--accent); background:var(--accent-dim); }
.wizard-inline-group { display:grid; gap:10px; grid-template-columns:120px minmax(0, 1fr); align-items:end; }
.schedule-preview {
    display:flex; align-items:center; justify-content:space-between; gap:16px;
    padding:12px 14px; border-radius:var(--radius-md); background:var(--bg-overlay); border:1px solid var(--border);
}
.schedule-preview-main { display:flex; flex-direction:column; gap:2px; }
.schedule-preview-label { font-size:12px; font-weight:600; color:var(--text-primary); }
.schedule-preview-sub { font-size:11px; color:var(--text-muted); }
.days-grid { display:grid; gap:8px 12px; grid-template-columns:repeat(2, minmax(0, 1fr)); }
.retention-box, .advanced-box {
    display:flex; flex-direction:column; gap:10px; padding:12px;
    border:1px solid var(--border); border-radius:var(--radius-md); background:color-mix(in srgb, var(--bg-overlay) 72%, transparent);
}
.retention-inline { display:grid; gap:10px; grid-template-columns:minmax(0, 1fr) 92px 90px; align-items:center; }
.advanced-toggle {
    display:inline-flex; align-items:center; gap:8px; padding:0; border:none; background:none;
    color:var(--accent); cursor:pointer; font:inherit; font-size:12px; font-weight:600;
}
.advanced-options[hidden] { display:none !important; }
.wizard-nav {
    display:flex; align-items:center; justify-content:space-between; gap:12px;
    margin-top:6px; padding-top:14px; border-top:1px solid var(--border);
}
.wizard-nav-actions { display:flex; align-items:center; gap:8px; margin-left:auto; }
.wizard-nav-actions .btn[disabled] { opacity:.55; cursor:not-allowed; }
.field-error { display:none; margin-top:6px; font-size:11px; color:var(--red); font-weight:600; }
.field-error.active { display:block; }
.field-hint { margin-top:6px; font-size:11px; color:var(--text-muted); }
.confirm-modal-body { display:flex; flex-direction:column; gap:10px; color:var(--text-secondary); font-size:13px; }
.confirm-modal-body p { margin:0; }
.modal-input {
    width:100%; padding:10px 12px; background:var(--bg-base); border:1px solid var(--border);
    border-radius:var(--radius-sm); color:var(--text-primary); font:inherit;
}
.modal-input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-dim); }
.connect-panel {
    background:var(--bg-raised); border:1px solid var(--border);
    border-radius:var(--radius-lg); padding:20px;
}
.connect-panel-title {
    font-size:12px; font-weight:600; text-transform:uppercase;
    letter-spacing:.8px; color:var(--text-muted); margin-bottom:14px;
}
.connect-row { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
.server-banner {
    display:none; align-items:center; gap:10px;
    padding:10px 16px; border-radius:var(--radius-md); font-size:12px; font-weight:500;
}
.server-banner.paused {
    display:flex; background:rgba(245,166,35,.1);
    border:1px solid var(--amber-dim); color:var(--amber);
}
.settings-table { width:100%; }
.settings-table th {
    padding:8px 16px; font-size:11px; color:var(--text-muted);
    text-align:left; border-bottom:1px solid var(--border);
}
.settings-table td { padding:9px 16px; border-bottom:1px solid var(--border); font-size:13px; }
.settings-table tr:last-child td { border-bottom:none; }
.settings-key { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--accent); }
.settings-val { color:var(--text-secondary); word-break:break-all; }
.progress-info {
    display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:8px;
    padding:12px 16px; background:var(--bg-overlay); border-top:1px solid var(--border);
    font-size:12px;
}
.progress-storage-sizes {
    display:flex; flex-wrap:wrap; gap:8px 20px; margin-bottom:10px;
    font-size:12px; color:var(--text-secondary);
}
.progress-storage-sizes strong { color:var(--text-muted); font-weight:600; margin-right:4px; }
.progress-storage-sizes span { font-family:'JetBrains Mono',monospace; color:var(--text-primary); }
.pi-item { display:flex; flex-direction:column; gap:2px; min-width:0; }
.pi-label { font-size:10px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; }
.pi-value { font-family:'JetBrains Mono',monospace; font-size:12px; color:var(--text-primary); word-break:break-word; overflow-wrap:break-word; min-width:0; }
.tabs { display:flex; gap:2px; border-bottom:1px solid var(--border); padding:0 20px; }
.tab-btn {
    padding:10px 16px; font-size:12px; font-weight:500; font-family:inherit;
    cursor:pointer; border:none; background:none; color:var(--text-secondary);
    border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .15s;
}
.tab-btn:hover { color:var(--text-primary); }
.tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }
.tab-panel { display:none; }
.tab-panel.active { display:block; }
.loading-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.5); z-index:9500;
    justify-content:center; align-items:center; flex-direction:column; gap:14px;
}
.loading-overlay.active { display:flex; }
.spinner {
    width:40px; height:40px;
    border:3px solid var(--border-lit);
    border-top-color:var(--accent);
    border-radius:50%; animation:spin .8s linear infinite;
}
@keyframes spin { to{ transform:rotate(360deg); } }
.spinner-label { font-size:13px; color:var(--text-secondary); }
#toast-container { position:fixed; bottom:24px; right:24px; z-index:21000; display:flex; flex-direction:column; gap:8px; }
.toast {
    display:flex; align-items:center; gap:10px;
    padding:11px 16px; border-radius:var(--radius-md);
    background:var(--bg-raised); border:1px solid var(--border-lit);
    box-shadow:var(--shadow-lg); font-size:13px;
    animation:slideUp .2s ease; max-width:320px;
}
@keyframes slideUp { from{transform:translateY(10px);opacity:0} to{transform:translateY(0);opacity:1} }
.toast-icon { flex-shrink:0; }
.toast.success { border-left:3px solid var(--green); }
.toast.error   { border-left:3px solid var(--red); }
.toast.info    { border-left:3px solid var(--accent); }
.toast.warning { border-left:3px solid var(--amber); }
.empty-state {
    display:flex; flex-direction:column; align-items:center;
    justify-content:center; padding:50px 20px;
    color:var(--text-muted); gap:10px; text-align:center;
}
.empty-state svg { opacity:.4; }
.empty-state p { font-size:13px; }
.cred-info {
    background:var(--bg-overlay); border:1px solid var(--border); border-radius:var(--radius-sm);
    padding:12px 14px; font-family:'JetBrains Mono',monospace; font-size:11px;
    color:var(--text-secondary); word-break:break-all; margin-top:8px;
    max-height:120px; overflow-y:auto;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.info-section {
    background: var(--bg-overlay);
    border-radius: var(--radius-md);
    padding: 16px;
}
.info-section h4 {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 12px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 6px;
}
.info-row {
    display: flex;
    margin-bottom: 8px;
    font-size: 12px;
}
.info-label {
    width: 100px;
    font-weight: 500;
    color: var(--text-muted);
    flex-shrink: 0;
}
.info-value {
    color: var(--text-primary);
    word-break: break-word;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
}
.versions-table {
    width: 100%;
    font-size: 12px;
}
.versions-table th {
    font-size: 10px;
    padding: 8px 8px;
}
.versions-table td {
    padding: 8px 8px;
    font-size: 11px;
}
.badge-version {
    background: var(--accent-dim);
    color: var(--accent);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
}
.info-status-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; }
.info-status-success { background:var(--green-dim); color:var(--green); border:1px solid var(--green); }
.info-status-error   { background:var(--red-dim);   color:var(--red);   border:1px solid var(--red); }
.info-status-warning { background:var(--amber-dim); color:var(--amber); border:1px solid var(--amber); }
.info-status-idle    { background:var(--bg-overlay);color:var(--text-secondary); border:1px solid var(--border); }
.info-status-running { background:var(--accent-dim);color:var(--accent); border:1px solid var(--accent); }
.lastrun-frame {
    display:inline-flex; align-items:center; gap:8px;
    background:var(--bg-overlay); border:1px solid var(--border-lit);
    border-radius:var(--radius-sm); padding:6px 12px;
    font-family:'JetBrains Mono',monospace; font-size:12px; color:var(--text-primary);
}
.lastrun-frame svg { color:var(--accent); flex-shrink:0; }
.allowed-days-list { display:flex; flex-direction:column; gap:4px; margin:0; padding:0; list-style:none; }
.allowed-days-list li { display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-primary); }
.allowed-days-list li::before { content:''; display:inline-block; width:6px; height:6px; border-radius:50%; background:var(--accent); flex-shrink:0; }
.filebrowser-panel { padding:16px; }
.filebrowser-panel .fb-tree { border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-base); min-height:220px; max-height:420px; overflow:auto; }
.fb-item { display:block; }
.fb-row { display:flex; align-items:center; gap:6px; padding:5px 8px; font-size:12px; cursor:pointer; border-radius:var(--radius-sm); color:var(--text-primary); user-select:none; }
.fb-row:hover { background:var(--bg-overlay); }
.fb-row.fb-selected { background:var(--accent-dim); color:var(--accent); }
.fb-caret { width:20px; height:20px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border:none; background:none; color:var(--text-secondary); cursor:pointer; border-radius:3px; padding:0; transition:transform .15s; }
.fb-caret:hover { background:var(--accent-dim); color:var(--accent); }
.fb-caret.open { transform:rotate(90deg); }
.fb-icon { flex-shrink:0; color:var(--amber); }
.fb-icon.fb-icon-file { color:var(--text-muted); }
.fb-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.fb-children { margin-left:20px; padding-left:8px; border-left:1px solid var(--border); }
.fb-loading { padding:8px 12px; font-size:12px; color:var(--text-muted); font-style:italic; }
.fb-breadcrumb { display:flex; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:10px; font-size:12px; }
.fb-breadcrumb-item { color:var(--accent); cursor:pointer; }
.fb-breadcrumb-item:hover { text-decoration:underline; }
.fb-breadcrumb-sep { color:var(--text-muted); }
.fb-table-wrap { border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-base); overflow:auto; max-height:420px; }
.fb-table { width:100%; border-collapse:collapse; font-size:12px; }
.fb-table th { background:var(--bg-overlay); padding:8px 12px; text-align:left; color:var(--text-muted); font-size:10px; text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border); position:sticky; top:0; }
.fb-table td { padding:8px 12px; border-bottom:1px solid var(--border); color:var(--text-secondary); vertical-align:middle; }
.fb-table tr:last-child td { border-bottom:none; }
.fb-table tr:hover td { background:var(--bg-overlay); }
.fb-table-icon { width:16px; height:16px; flex-shrink:0; display:inline-flex; align-items:center; justify-content:center; color:var(--amber); }
.fb-table-icon-file { color:var(--text-muted); }
.fb-table-name { color:var(--text-primary); }
.fb-table-date { color:var(--text-muted); font-size:11px; white-space:nowrap; }
.fb-table-caret { width:20px; height:20px; flex-shrink:0; display:inline-flex; align-items:center; justify-content:center; border:none; background:none; color:var(--text-secondary); cursor:pointer; border-radius:3px; padding:0; transition:transform .15s; }
.fb-table-caret:hover { background:var(--accent-dim); color:var(--accent); }
.fb-table-caret.open { transform:rotate(90deg); }
.fb-table-children { margin-left:0; }
.fb-table-children .fb-table { margin-left:0; }
.fb-children-row td { padding:0; border:none; }
.md-content { font-family:'DM Sans',sans-serif; font-size:14px; color:var(--text-primary); line-height:1.7; }
.md-content h1,.md-content h2,.md-content h3 { margin:1em 0 .4em; font-weight:600; color:var(--text-primary); }
.md-content h1 { font-size:20px; border-bottom:1px solid var(--border); padding-bottom:8px; }
.md-content h2 { font-size:17px; border-bottom:1px solid var(--border); padding-bottom:6px; }
.md-content h3 { font-size:15px; }
.md-content p { margin:.5em 0; }
.md-content ul,.md-content ol { margin:.5em 0 .5em 1.4em; }
.md-content li { margin:.2em 0; }
.md-content code { background:var(--bg-overlay); border:1px solid var(--border); border-radius:3px; padding:1px 5px; font-family:'JetBrains Mono',monospace; font-size:12px; color:var(--accent); }
.md-content pre { background:var(--bg-overlay); border:1px solid var(--border); border-radius:var(--radius-sm); padding:12px; overflow-x:auto; margin:.8em 0; }
.md-content pre code { background:none; border:none; padding:0; color:var(--text-secondary); }
.md-content blockquote { border-left:3px solid var(--accent); margin:.5em 0; padding:.3em 12px; color:var(--text-secondary); background:var(--bg-overlay); border-radius:0 var(--radius-sm) var(--radius-sm) 0; }
.md-content hr { border:none; border-top:1px solid var(--border); margin:1em 0; }
.md-content a { color:var(--accent); }
.md-content strong { color:var(--text-primary); }
.json-table { width:100%; border-collapse:collapse; font-family:'JetBrains Mono',monospace; font-size:11px; }
.json-table th { background:var(--bg-overlay); padding:6px 10px; text-align:left; color:var(--text-muted); font-size:10px; text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border); }
.json-table td { padding:5px 10px; border-bottom:1px solid var(--border); color:var(--text-secondary); word-break:break-all; vertical-align:top; }
.json-table tr:last-child td { border-bottom:none; }
.json-table td:first-child { color:var(--accent); font-weight:600; min-width:120px; }
.json-expand { background:none; border:none; color:var(--accent); cursor:pointer; font-size:11px; padding:0; font-family:'JetBrains Mono',monospace; }
.json-nested { margin-top:4px; }
.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.password-wrapper input {
    padding-right: 32px;
}
.toggle-password {
    position: absolute;
    right: 8px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    padding: 4px;
    border-radius: var(--radius-sm);
}
.toggle-password:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}
@media (max-width:768px) {
    .shell { grid-template-columns: 1fr; }
    .sidebar { display: none; position: fixed; top: 56px; left: 0; width: min(280px, 92vw); height: calc(100vh - 56px); z-index: 400; background: var(--bg-raised); transform: translateX(-100%); transition: transform 0.2s; box-shadow: var(--shadow-lg); }
    .sidebar.open { display: flex; transform: translateX(0); }
    .main { padding: 12px 14px; gap: 14px; min-height: calc(100vh - 56px); }
    .topbar { padding: 0 10px; height: auto; min-height: 52px; gap: 8px; flex-wrap: wrap; }
    .menu-toggle { display: flex; }
    .topbar-right { gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    .status-pill { padding: 4px 10px; font-size: 11px; }
    .btn-sm { padding: 5px 10px; font-size: 11px; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-card { padding: 12px; }
    .stat-value { font-size: 22px; }
    .card { margin: 0 6px 0 8px; }
    .card-header { flex-direction: column; align-items: flex-start; gap: 10px; }
    .card-actions { width: 100%; justify-content: flex-start; flex-wrap: wrap; gap: 6px; }
    .modal-box { max-width: calc(100% - 16px); padding: 14px; max-height: min(95vh, 100%); margin: 8px; }
    .wizard-modal-box { max-width:100vw; width:100vw; max-height:100vh; height:100vh; }
    .modal-head { flex-wrap: wrap; gap: 8px; margin-bottom: 14px; padding-bottom: 10px; }
    .modal-head h3 { font-size: 14px; flex: 1; min-width: 200px; }
    .modal-footer { flex-wrap: wrap; gap: 6px; margin-top: 14px; padding-top: 12px; }
    .modal-footer .btn { flex: 1; min-width: 140px; justify-content: center; }
    .info-grid { grid-template-columns: 1fr; }
    .row-actions { flex-wrap: wrap; gap: 4px; }
    .action-btn { padding: 4px 8px; font-size: 10px; }
    .backup-jobs-table { display: block; overflow-x: hidden; }
    .backup-jobs-table thead { display: none; }
    .backup-jobs-table tbody { display: block; width: 100%; }
    .backup-jobs-table tbody tr { display: block; width: min(100%, 760px); border: 1px solid var(--border); border-radius: var(--radius-md); margin: 0 auto 10px; padding: 12px; background: var(--bg-overlay); }
    .backup-jobs-table tbody td { display: block; border: none; padding: 8px 12px; text-align: center; }
    .backup-jobs-table tbody td:first-child { padding-top: 0; padding-left: 12px; padding-right: 12px; text-align: left; }
    .backup-jobs-table tbody td:last-child { padding-top: 12px; border-top: 1px solid var(--border); margin-top: 8px; padding-left: 12px; padding-right: 12px; }
    .backup-jobs-table tbody td::before { content: attr(data-h); font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); display: block; margin-bottom: 4px; text-align: left; }
    .table-wrap { overflow-x: visible; margin-bottom: 0; padding-bottom: 0; }
    .backup-name { font-size: 12px; font-weight: 600; }
    .backup-id { font-size: 10px; }
    .target-url-pill { max-width: 100%; padding: 3px 8px; font-size: 10px; justify-content: center; }
    .target-url-path { font-size: 10px; }
    .badge { padding: 2px 7px; font-size: 10px; }
    .row-actions { justify-content: center; }
    .server-banner { flex-wrap: wrap; padding: 8px 12px; font-size: 11px; }
    .server-banner svg { flex-shrink: 0; }
    .server-banner .btn-sm { margin-left: auto !important; }
    .cred-info { font-size: 10px; max-height: 100px; }
    .field input, .field select, .field textarea { font-size: 12px; padding: 8px 10px; }
    .form-note { flex-wrap: wrap; padding: 8px 10px; font-size: 11px; }
    .form-note svg { flex-shrink: 0; margin-top: 1px; }
    .password-wrapper input { padding-right: 36px; font-size: 12px; }
    .toggle-password { padding: 3px; }
    .toggle-password svg { width: 13px; height: 13px; }
    .close-btn { padding: 3px; }
    .close-btn svg { width: 15px; height: 15px; }
    .logs-wrap { padding: 10px 12px; font-size: 11px; max-height: min(72vh, calc(100vh - 150px)); }
    .log-line { gap: 6px; }
    .log-ts { font-size: 9px; min-width: 55px; }
    .log-badge { font-size: 9px; padding: 1px 5px; }
    .progress-info { grid-template-columns: repeat(2, 1fr); gap: 6px; padding: 10px 12px; font-size: 11px; }
    .pi-value { font-size: 11px; }
    .tabs { padding: 0 12px; flex-wrap: wrap; }
    .tab-btn { padding: 8px 12px; font-size: 11px; }
    .wizard-panel.active[data-step-panel="1"] .field.full,
    .wizard-panel.active[data-step-panel="2"] .field.full,
    .wizard-panel.active[data-edit-step-panel="1"] .field.full,
    .wizard-panel.active[data-edit-step-panel="2"] .field.full { grid-template-columns:1fr; }
    .wizard-panel.active[data-step-panel="1"] .dest-tree-panel,
    .wizard-panel.active[data-step-panel="2"] .source-tree-panel,
    .wizard-panel.active[data-edit-step-panel="1"] .dest-tree-panel,
    .wizard-panel.active[data-edit-step-panel="2"] .source-tree-panel {
        grid-column:1; grid-row:auto; height:min(280px, calc(100vh - 260px)); max-height:min(280px, calc(100vh - 260px)); min-height:160px;
    }
    .wizard-grid, .password-row, .wizard-inline-group, .retention-inline { grid-template-columns: 1fr; }
    .wizard-nav { flex-wrap: wrap; }
    .wizard-nav-actions { width: 100%; justify-content: stretch; margin-left: 0; }
    .wizard-nav-actions .btn { flex: 1; justify-content: center; }
    .fs-tree-row { font-size: 11px; min-height: 26px; }
    .fs-tree-children { margin-left: 14px; }
    .fs-tree-check { width: 13px; height: 13px; }
    .fb-tree { min-height: 180px; max-height: 320px; }
    .fb-row { font-size: 11px; padding: 4px 6px; }
    .fb-children { margin-left: 16px; }
    .fb-breadcrumb { font-size: 11px; flex-wrap: wrap; }
    .info-section { padding: 12px; }
    .info-row { font-size: 11px; flex-wrap: wrap; }
    .info-label { width: 90px; font-size: 10px; }
    .info-value { font-size: 10px; }
    .versions-table { font-size: 11px; }
    .versions-table th, .versions-table td { padding: 6px; }
    .log-report { padding: 12px; margin: 10px 12px; }
    .log-report-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
    .log-kv-val { font-size: 11px; }
    .log-msg-text { font-size: 11px; }
    .json-table { font-size: 10px; }
    .json-table td { padding: 4px 8px; }
    .allowed-days-list li { font-size: 11px; }
    .lastrun-frame { padding: 4px 10px; font-size: 11px; }
    .info-status-badge { padding: 3px 10px; font-size: 11px; }
    #licensesContent { overflow-x: hidden; word-break: break-word; overflow-wrap:anywhere; }
    #licensesContent .info-section { min-width: 0; overflow-x: hidden; }
    #licensesContent pre { white-space: pre-wrap; word-wrap: break-word; overflow-wrap:anywhere; max-width: 100%; overflow-x: hidden; }
    .log-line { flex-wrap: wrap; }
    .log-ts { width: 100%; min-width:0; text-align: center; padding-bottom: 4px; border-bottom: 1px solid var(--border); margin-bottom: 4px; }
    .log-badge { align-self: flex-start; }
    .log-msg { width: 100%; }
    .modal-box { overflow-x: hidden; }
    .modal-scroll-body { overflow-x: hidden; }
    #systemInfoContent { overflow-x: hidden; }
    #systemInfoContent .settings-table { min-width: 100%; }
    #systemInfoContent .settings-table td { word-break: break-word; }
    /* System info and log modals: 40%/60% column split on mobile */
    #systemInfoContent .settings-table td:first-child { width: 40%; min-width: 40%; font-weight: 600; }
    #systemInfoContent .settings-table td:last-child { width: 60%; min-width: 60%; }
    /* Log modal: timestamp as separator above content on mobile */
    #logsModalContent .log-line { flex-wrap: wrap; }
    #logsModalContent .log-ts { width: 100%; min-width:0; text-align: center; padding-bottom: 6px; border-bottom: 1px solid var(--border); margin-bottom: 6px; }
    #logsModalContent .log-badge { align-self: flex-start; }
    #logsModalContent .log-msg { width: 100%; }
}
@media (max-width:480px) {
    .topbar { padding: 0 8px; }
    .brand h1 { font-size: 13px; }
    .brand-icon { width: 18px; height: 18px; }
    .status-pill { display: none; }
    .topbar-right .btn span { display: none; }
    .topbar-right .btn { padding: 7px 10px; }
    .topbar-right .btn svg { width: 14px; height: 14px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; margin-bottom: 10px; }
    .stat-card { padding: 8px 10px; }
    .stat-icon { width: 24px; height: 24px; margin-bottom: 6px; }
    .stat-icon svg { width: 14px; height: 14px; }
    .stat-value { font-size: 18px; }
    .stat-label { font-size: 10px; }
    .modal-box { max-width: calc(100% - 12px); padding: 12px; margin: 6px; }
    .wizard-steps { gap: 6px; }
    .wizard-step { width: 100%; text-align: left; }
    .modal-head h3 { font-size: 13px; min-width: 100%; }
    .modal-footer .btn { min-width: 100%; }
    .card-header { padding: 12px; }
    .card-body { padding: 12px; }
    .card-title { font-size: 12px; }
    .card-title svg { width: 13px; height: 13px; }
    .btn { padding: 7px 12px; font-size: 11px; }
    .btn-sm { padding: 5px 8px; font-size: 10px; }
    .action-btn { padding: 4px 6px; font-size: 9px; min-width: 28px; justify-content: center; }
    .ab-more { min-width: 28px; }
    .row-actions { gap: 3px; }
    .backup-jobs-table tbody tr { padding: 10px; }
    .backup-jobs-table tbody td { padding: 6px 10px; }
    .backup-jobs-table tbody td::before { font-size: 9px; }
    .progress-info { grid-template-columns: 1fr; }
    .logs-wrap { padding: 8px 10px; font-size: 10px; max-height: min(68vh, calc(100vh - 120px)); }
    .wizard-panel.active .source-tree-panel, .wizard-panel.active .dest-tree-panel { min-height: 120px; }
    .days-grid { grid-template-columns: 1fr; }
    .fb-tree { min-height: 160px; max-height: 280px; }
    .field { margin-bottom: 12px; }
    .form-note { font-size: 10px; padding: 7px 9px; }
    .cred-info { font-size: 9px; max-height: 80px; }
    .toast { padding: 9px 12px; font-size: 12px; max-width: 280px; }
    #toast-container { bottom: 16px; right: 16px; }
}
.menu-toggle { display: none; background: none; border: none; color: var(--text-primary); cursor: pointer; padding: 8px; margin-right: 8px; }
@media (max-width:768px) {
    .menu-toggle { display: flex; align-items: center; }
    .topbar-left { display: flex; align-items: center; }
}
</style>
</head>
<body data-theme="<?php echo htmlspecialchars($bdTheme, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo backupDashboardIsEmbeddedRequest() ? 'bd-embed' : ''; ?>">
<div class="shell">
<header class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" onclick="toggleMobileMenu()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="brand">
            <svg class="brand-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
            <h1>Duplicati<span>.</span>Manager</h1>
        </div>
    </div>
    <div class="topbar-right">
        <div class="status-pill" id="serverStatusBadge"><span class="status-dot"></span><span id="serverStatusText">—</span></div>
        <button class="btn btn-ghost btn-sm" onclick="refreshData()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg><span data-i18n="refresh">Refresh</span></button>
    </div>
</header>
<nav class="sidebar" id="mobileSidebar">
    <div class="sidebar-section"><div class="sidebar-label" data-i18n="navigation">Navigation</div>
        <button class="nav-item active" data-section="backups" onclick="showSection('backups');closeMobileMenu()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span data-i18n="backupJobs">Backup Jobs</span></button>
        <button class="nav-item" data-section="logs" onclick="showSection('logs');closeMobileMenu()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 14v2.2l1.6 1"/><path d="M16 4h2a2 2 0 0 1 2 2v.832"/><path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h2"/><circle cx="16" cy="16" r="6"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg><span data-i18n="logs">Logs</span></button>
        <button class="nav-item" data-section="explorer" onclick="showSection('explorer');closeMobileMenu()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg><span data-i18n="fileExplorer">File Explorer</span></button>
        <button class="nav-item" onclick="openDocumentation();closeMobileMenu()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span data-i18n="view_documentation">View Documentation</span></button>
    </div>
    <div class="sidebar-section"><div class="sidebar-label" data-i18n="serverInfo">Server</div>
        <button class="nav-item" onclick="openSystemInfoModal();closeMobileMenu()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M16 12h2"/><path d="M16 8h2"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/><path d="M6 12h2"/><path d="M6 8h2"/></svg><span data-i18n="systemInfo">System info</span></button>
        <button class="nav-item" onclick="openChangelogModal();closeMobileMenu()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg><span data-i18n="changelog">Changelog</span></button>
        <button class="nav-item" onclick="openLicensesModal();closeMobileMenu()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span data-i18n="licenses">Licenses</span></button>
        <button class="nav-item" onclick="openCommandlineModal();closeMobileMenu()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg><span data-i18n="cliCommands">CLI commands</span></button>
    </div>
    <div class="sidebar-section" style="margin-top:auto;padding-top:12px;border-top:1px solid var(--border);"><div class="sidebar-label" data-i18n="controls">Controls</div>
        <button class="nav-item" onclick="pauseServer();closeMobileMenu()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg><span data-i18n="pauseServer">Pause Server</span></button>
        <button class="nav-item" onclick="resumeServer();closeMobileMenu()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg><span data-i18n="resumeServer">Resume Server</span></button>
        <button class="nav-item" onclick="openDuplicatiInterface();closeMobileMenu()"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.686 15A14.5 14.5 0 0 1 12 22a14.5 14.5 0 0 1 0-20 10 10 0 1 0 9.542 13"/><path d="M2 12h8.5"/><path d="M20 6V4a2 2 0 1 0-4 0v2"/><rect width="8" height="5" x="14" y="6" rx="1"/></svg><span data-i18n="openInterface">Open Interface</span></button>
    </div>
</nav>
<main class="main">
    <div id="dashboardContent" style="display:none;display:contents;">
        <div class="server-banner" id="serverBanner"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg><span id="serverBannerText"></span><button class="btn btn-sm btn-amber" onclick="resumeServer()" style="margin-left:auto;" data-i18n="resume">Resume</button></div>
        <div class="stats-grid" id="statsGrid"></div>
        <div id="section-backups"><div class="card"><div class="card-header"><div class="card-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span data-i18n="backupJobs">Backup Jobs</span></div><div class="card-actions"><button class="btn btn-success btn-sm" onclick="showModal('createModal')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span data-i18n="newBackup">New Backup</span></button></div></div><div id="activeProgressPanel" style="display:none;padding:14px 20px 0;"></div><div class="table-wrap" style="margin-top:14px;"><table class="backup-jobs-table"><thead><tr><th data-i18n="name">Name</th><th data-i18n="status">Status</th><th data-i18n="progress">Progress</th><th data-i18n="schedule">Schedule</th><th data-i18n="target">Target</th><th data-i18n="actions">Actions</th></tr></thead><tbody id="backupsTableBody"><tr><td colspan="6"><div class="empty-state"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/></svg><p data-i18n="connectToLoad">Connect to load backup jobs</p></div></td></tr></tbody></table></div></div></div>
        <div id="section-logs" style="display:none;"><div class="card"><div class="card-header"><div class="card-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span data-i18n="systemLogs">System Logs</span></div><div class="card-actions"><div class="field" style="min-width:160px;margin:0;"><select id="logBackupSelector" onchange="viewLogs(this.value)" style="padding:6px 10px;font-size:12px;"><option value="" data-i18n-opt="selectBackup">Select backup…</option></select></div></div></div><div class="logs-wrap" id="logsContent"><div class="empty-state"><p data-i18n="selectBackupToView">Select a backup above to view its logs</p></div></div></div></div>
        <div id="section-explorer" style="display:none;"><div class="card"><div class="card-header"><div class="card-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg><span data-i18n="fileExplorer">File Explorer</span></div><div class="card-actions"><div class="field" style="min-width:180px;margin:0;"><select id="explorerBackupSelector" onchange="initExplorer(this.value)" style="padding:6px 10px;font-size:12px;"><option value="" data-i18n-opt="selectBackup">Select backup…</option></select></div></div></div><div class="filebrowser-panel"><div class="fb-breadcrumb" id="fbBreadcrumb"></div><div id="fbDiskSpace" style="padding:14px 16px;border-bottom:1px solid var(--border);display:none;"></div><div class="fb-tree" id="fbTree"><div class="empty-state"><p data-i18n="selectBackupBrowse">Select a backup to browse its destination folder</p></div></div></div></div></div>
    </div>
</main>
</div>
<div class="loading-overlay" id="loadingOverlay"><div class="spinner"></div><div class="spinner-label" id="spinnerLabel">Loading…</div></div>
<div id="toast-container"></div>
<div class="modal" id="systemInfoModal"><div class="modal-box" style="max-width:640px;"><div class="modal-head"><h3 data-i18n="systemInfo">System info</h3><button class="close-btn" onclick="closeModal('systemInfoModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div id="systemInfoContent" class="logs-wrap" style="max-height:min(70vh,520px);"><div class="empty-state"><p data-i18n="loading">Loading…</p></div></div></div></div>
<div class="modal" id="changelogModal"><div class="modal-box" style="max-width:720px;"><div class="modal-head"><h3 data-i18n="changelog">Changelog</h3><button class="close-btn" onclick="closeModal('changelogModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div id="changelogContent" class="logs-wrap" style="max-height:min(70vh,560px);white-space:pre-wrap;font-family:inherit;"><div class="empty-state"><p data-i18n="loading">Loading…</p></div></div></div></div>
<div class="modal" id="licensesModal"><div class="modal-box" style="max-width:800px;"><div class="modal-head"><h3 data-i18n="licenses">Licenses</h3><button class="close-btn" onclick="closeModal('licensesModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div id="licensesContent" class="logs-wrap" style="max-height:min(70vh,560px);"><div class="empty-state"><p data-i18n="loading">Loading…</p></div></div></div></div>
<div class="modal" id="commandlineModal"><div class="modal-box" style="max-width:560px;"><div class="modal-head"><h3 data-i18n="cliCommands">CLI commands</h3><button class="close-btn" onclick="closeModal('commandlineModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div id="commandlineContent" class="logs-wrap" style="max-height:min(70vh,480px);"><div class="empty-state"><p data-i18n="loading">Loading…</p></div></div></div></div>
<div class="modal" id="backupInfoModal"><div class="modal-box" style="max-width:800px;"><div class="modal-head"><h3><span data-i18n="backupDetails">Backup Details</span> — <span id="backupInfoTitle"></span></h3><button class="close-btn" onclick="closeModal('backupInfoModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div id="backupInfoContent" style="max-height:620px;overflow-y:auto;"><div class="empty-state"><p data-i18n="loading">Loading…</p></div></div></div></div>
<div class="modal" id="createModal"><div class="modal-box wizard-modal-box"><div class="modal-head"><h3 data-i18n="createNewBackup">Create New Backup</h3><button class="close-btn" onclick="closeModal('createModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div class="modal-scroll-body"><form class="modal-form backup-wizard" id="createBackupForm"><div class="wizard-steps" id="createWizardSteps"><button type="button" class="wizard-step active" data-step-index="0"><span class="wizard-step-dot">1</span><span class="wizard-step-label">General</span></button><button type="button" class="wizard-step" data-step-index="1"><span class="wizard-step-dot">2</span><span class="wizard-step-label">Destination</span></button><button type="button" class="wizard-step" data-step-index="2"><span class="wizard-step-dot">3</span><span class="wizard-step-label">Source Data</span></button><button type="button" class="wizard-step" data-step-index="3"><span class="wizard-step-dot">4</span><span class="wizard-step-label">Schedule</span></button><button type="button" class="wizard-step" data-step-index="4"><span class="wizard-step-dot">5</span><span class="wizard-step-label">Options</span></button></div><div class="wizard-panel active" data-step-panel="0"><h4>General backup settings</h4><div class="wizard-grid"><div class="field"><label data-i18n="backupName">Backup Name *</label><input type="text" id="newBackupName" required placeholder="My Documents Backup"><div class="field-error" id="newBackupNameError">Backup name is required.</div></div><div class="field"><label>Backup description</label><input type="text" id="newBackupDescription" placeholder="Your backup description"></div><div class="field full"><label>Encryption</label><select id="newEncryptionMode"><option value="aes">AES-256 encryption</option><option value="none">No encryption</option></select><div class="field-hint">Choose encrypted backup or create it without a password.</div></div><div class="field full" id="passwordFieldsWrap"><div class="password-row"><div><label>Create a password *</label><input type="password" id="newPassphrase" placeholder="Password" autocomplete="new-password"></div><div><label>Repeat password</label><input type="password" id="newPassphraseRepeat" placeholder="Repeat password" autocomplete="new-password"></div></div><div class="wizard-inline-actions" style="margin-top:10px;"><button type="button" class="eye-toggle" id="togglePasswordVisibilityBtn" aria-label="Show password"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg></button><button type="button" class="btn btn-ghost btn-sm" id="generateBackupPasswordBtn">Generate password</button></div><div class="field-error" id="newPassphraseError">Password is required and must match.</div><div class="field-hint">The passphrase is sent to Duplicati for built-in AES encryption.</div></div><div class="wizard-nav"><button type="button" class="btn btn-primary wizard-next-inline" data-next-step="1">Continue</button></div></div></div><div class="wizard-panel" data-step-panel="1"><h4>Destination</h4><div class="wizard-grid"><div class="field full"><label data-i18n="destinationURL">Destination URL *</label><p class="field-tree-caption" data-i18n="destTreeCaption">Destination folder</p><div id="destTreePanel" class="dest-tree-panel" aria-label="destination tree"><div class="fs-tree-toolbar"><button type="button" class="btn btn-ghost btn-sm" id="destTreeBackBtn">← Back</button><input type="search" class="fs-tree-filter" id="destTreeFilter" data-i18n-placeholder="filterFolders" placeholder="Filter folders…" autocomplete="off"><button type="button" class="btn btn-ghost btn-sm" id="destTreeMkdirBtn" data-i18n="newFolder">New folder</button><div class="fs-tree-current-path" id="destTreeCurrentPath">/</div></div><div id="destTreeRoot" class="source-tree-root"></div></div><small data-i18n="destBrowseHint">Select a destination folder from the tree above, or enter manually below.</small><input type="text" id="newTargetUrl" required placeholder="file:///backup/ or s3://bucket/path"><div class="wizard-inline-actions" style="margin-top:8px;"><button type="button" class="btn btn-ghost btn-sm" id="testNewDestRwBtn" data-i18n="testDestRw">Test read/write</button></div><div id="newDestRwTestResult" class="field-hint" style="margin-top:6px;"></div><div class="field-error" id="newTargetUrlError">Destination URL is required.</div></div><div class="wizard-nav"><button type="button" class="btn btn-primary wizard-next-inline" data-next-step="2">Continue</button></div></div></div><div class="wizard-panel" data-step-panel="2"><h4>Source data</h4><div class="wizard-grid"><div class="field full"><label data-i18n="sourceFolders">Source Folder(s) *</label><textarea id="newSourcePath" rows="3" placeholder="/mnt/data;/var/www/site" spellcheck="false"></textarea><p class="field-tree-caption" data-i18n="sourceTreeCaption">Folder list</p><div id="sourceTreePanel" class="source-tree-panel" aria-label="source tree"><div class="fs-tree-toolbar"><input type="search" class="fs-tree-filter" id="sourceTreeFilter" data-i18n-placeholder="filterFolders" placeholder="Filter folders…" autocomplete="off"><button type="button" class="btn btn-ghost btn-sm" id="sourceTreeMkdirBtn" data-i18n="newFolder">New folder</button></div><div id="sourceTreeRoot" class="source-tree-root"></div></div><small data-i18n="semicolonTip">Separate multiple paths with semicolons ( ; )</small><small style="display:block;margin-top:6px;" data-i18n="browseHint">Expand drives or folders and tick paths to include. You can still edit the field manually.</small><div class="field-error" id="newSourcePathError">At least one source path is required.</div></div><div class="wizard-nav"><button type="button" class="btn btn-primary wizard-next-inline" data-next-step="3">Continue</button></div></div></div><div class="wizard-panel" data-step-panel="3"><h4 data-i18n="schedule">Schedule</h4><div class="schedule-preview"><div class="schedule-preview-main"><span class="schedule-preview-label" id="schedulePreviewLabel">Daily</span><span class="schedule-preview-sub" id="schedulePreviewSub">13:00</span></div><span aria-hidden="true" style="color:var(--text-muted);font-size:18px;">⌵</span></div><div class="retention-box" style="margin-top:14px;"><label class="switchline"><input type="checkbox" id="newScheduleEnabled" checked> Automatically run backups</label><div class="field-hint">If a date was missed, the job will run as soon as possible.</div><div class="wizard-grid" id="scheduleFieldsWrap"><div class="field"><label>Next time</label><input type="time" id="newScheduleTime" value="13:00"></div><div class="field"><label>Run again every</label><div class="wizard-inline-group"><input type="number" id="newScheduleEvery" value="1" min="1"><select id="newScheduleUnit"><option value="D">days</option><option value="h">hours</option></select></div></div><div class="field full"><label>Allowed days</label><div class="days-grid" id="newAllowedDaysGrid"><label class="checkline"><input type="checkbox" value="Monday" checked> Monday</label><label class="checkline"><input type="checkbox" value="Tuesday" checked> Tuesday</label><label class="checkline"><input type="checkbox" value="Wednesday" checked> Wednesday</label><label class="checkline"><input type="checkbox" value="Thursday" checked> Thursday</label><label class="checkline"><input type="checkbox" value="Friday" checked> Friday</label><label class="checkline"><input type="checkbox" value="Saturday" checked> Saturday</label><label class="checkline"><input type="checkbox" value="Sunday" checked> Sunday</label></div></div></div></div><div class="wizard-nav"><button type="button" class="btn btn-primary wizard-next-inline" data-next-step="4">Continue</button></div></div><div class="wizard-panel" data-step-panel="4"><h4>Options</h4><div class="wizard-grid"><div class="field full"><div class="retention-box"><label class="radioline"><input type="radio" name="backupRetentionMode" value="keep-all" checked> Keep all backups</label><div class="retention-inline"><label class="radioline"><input type="radio" name="backupRetentionMode" value="delete-older"> Delete backups that are older than</label><input type="number" id="newRetentionDays" min="1" value="30" disabled><select id="newRetentionUnit" disabled><option value="D">days</option></select></div></div></div><div class="field full"><div class="advanced-box"><button type="button" class="advanced-toggle" id="toggleAdvancedOptionsBtn">Add advanced option +</button><div class="advanced-options" id="advancedOptionsPanel" hidden><div class="wizard-grid"><div class="field"><label>Limit the size of the volumes</label><div class="wizard-inline-group"><input type="number" id="newVolumeSize" min="1" value="50"><select id="newVolumeSizeUnit"><option value="MB">MB</option><option value="GB">GB</option></select></div><div class="field-hint">Stored as the Duplicati `dblock-size` option.</div></div></div></div></div></div><div class="form-note"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span data-i18n="createNote">Creating via API creates a basic configuration. Edit in the Duplicati UI for advanced options.</span></div></div><div class="wizard-nav"><button type="button" class="btn btn-ghost" id="createWizardBackBtn">← Go back</button><div class="wizard-nav-actions"><button type="submit" class="btn btn-primary" id="createWizardSubmitBtn" data-i18n="createBackup">Create Backup</button></div></div></div></form></div></div></div>
<div class="modal" id="editModal"><div class="modal-box wizard-modal-box"><div class="modal-head"><h3 data-i18n="editBackup">Edit Backup</h3><button class="close-btn" onclick="closeModal('editModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div class="modal-scroll-body"><form class="modal-form backup-wizard" id="editBackupForm"><input type="hidden" id="editBackupId"><div class="wizard-steps" id="editWizardSteps"><button type="button" class="wizard-step active" data-edit-step-index="0"><span class="wizard-step-dot">1</span><span class="wizard-step-label">General</span></button><button type="button" class="wizard-step" data-edit-step-index="1"><span class="wizard-step-dot">2</span><span class="wizard-step-label">Destination</span></button><button type="button" class="wizard-step" data-edit-step-index="2"><span class="wizard-step-dot">3</span><span class="wizard-step-label">Source Data</span></button><button type="button" class="wizard-step" data-edit-step-index="3"><span class="wizard-step-dot">4</span><span class="wizard-step-label">Schedule</span></button><button type="button" class="wizard-step" data-edit-step-index="4"><span class="wizard-step-dot">5</span><span class="wizard-step-label">Options</span></button></div><div class="wizard-panel active" data-edit-step-panel="0"><h4>General backup settings</h4><div class="wizard-grid"><div class="field"><label data-i18n="backupName">Backup Name *</label><input type="text" id="editBackupName" required placeholder="My Documents Backup"><div class="field-error" id="editBackupNameError">Backup name is required.</div></div><div class="field"><label>Backup description</label><input type="text" id="editBackupDescription" placeholder="Your backup description"></div><div class="field full"><div class="field-hint">Encryption settings are preserved from the existing backup and cannot be changed here.</div></div><div class="wizard-nav"><button type="button" class="btn btn-primary edit-wizard-next-inline" data-edit-next-step="1">Continue</button></div></div></div><div class="wizard-panel" data-edit-step-panel="1"><h4>Destination</h4><div class="wizard-grid"><div class="field full"><label data-i18n="destinationURL">Destination URL *</label><p class="field-tree-caption" data-i18n="destTreeCaption">Destination folder</p><div id="editDestTreePanel" class="dest-tree-panel" aria-label="edit destination tree"><div class="fs-tree-toolbar"><button type="button" class="btn btn-ghost btn-sm" id="editDestTreeBackBtn">← Back</button><input type="search" class="fs-tree-filter" id="editDestTreeFilter" data-i18n-placeholder="filterFolders" placeholder="Filter folders…" autocomplete="off"><button type="button" class="btn btn-ghost btn-sm" id="editDestTreeMkdirBtn" data-i18n="newFolder">New folder</button><div class="fs-tree-current-path" id="editDestTreeCurrentPath">/</div></div><div id="editDestTreeRoot" class="source-tree-root"></div></div><small data-i18n="destBrowseHint">Select a destination folder from the tree above, or enter manually below.</small><input type="text" id="editTargetUrl" required placeholder="file:///backup/ or s3://bucket/path"><div class="wizard-inline-actions" style="margin-top:8px;"><button type="button" class="btn btn-ghost btn-sm" id="testEditDestRwBtn" data-i18n="testDestRw">Test read/write</button></div><div id="editDestRwTestResult" class="field-hint" style="margin-top:6px;"></div><div class="field-error" id="editTargetUrlError">Destination URL is required.</div></div><div class="wizard-nav"><button type="button" class="btn btn-primary edit-wizard-next-inline" data-edit-next-step="2">Continue</button></div></div></div><div class="wizard-panel" data-edit-step-panel="2"><h4>Source data</h4><div class="wizard-grid"><div class="field full"><label data-i18n="sourceFolders">Source Folder(s) *</label><textarea id="editSourcePath" rows="3" placeholder="/mnt/data;/var/www/site" spellcheck="false"></textarea><p class="field-tree-caption" data-i18n="sourceTreeCaption">Folder list</p><div id="editSourceTreePanel" class="source-tree-panel" aria-label="edit source tree"><div class="fs-tree-toolbar"><input type="search" class="fs-tree-filter" id="editSourceTreeFilter" data-i18n-placeholder="filterFolders" placeholder="Filter folders…" autocomplete="off"><button type="button" class="btn btn-ghost btn-sm" id="editSourceTreeMkdirBtn" data-i18n="newFolder">New folder</button></div><div id="editSourceTreeRoot" class="source-tree-root"></div></div><small data-i18n="semicolonTip">Separate multiple paths with semicolons ( ; )</small><small style="display:block;margin-top:6px;" data-i18n="browseHint">Expand drives or folders and tick paths to include. You can still edit the field manually.</small><div class="field-error" id="editSourcePathError">At least one source path is required.</div></div><div class="wizard-nav"><button type="button" class="btn btn-primary edit-wizard-next-inline" data-edit-next-step="3">Continue</button></div></div></div><div class="wizard-panel" data-edit-step-panel="3"><h4 data-i18n="schedule">Schedule</h4><div class="schedule-preview"><div class="schedule-preview-main"><span class="schedule-preview-label" id="editSchedulePreviewLabel">Daily</span><span class="schedule-preview-sub" id="editSchedulePreviewSub">13:00</span></div><span aria-hidden="true" style="color:var(--text-muted);font-size:18px;">⌵</span></div><div class="retention-box" style="margin-top:14px;"><label class="switchline"><input type="checkbox" id="editScheduleEnabled" checked> Automatically run backups</label><div class="field-hint">If a date was missed, the job will run as soon as possible.</div><div class="wizard-grid" id="editScheduleFieldsWrap"><div class="field"><label>Next time</label><input type="time" id="editScheduleTime" value="13:00"></div><div class="field"><label>Run again every</label><div class="wizard-inline-group"><input type="number" id="editScheduleEvery" value="1" min="1"><select id="editScheduleUnit"><option value="D">days</option><option value="h">hours</option></select></div></div><div class="field full"><label>Allowed days</label><div class="days-grid" id="editAllowedDaysGrid"><label class="checkline"><input type="checkbox" value="Monday" checked> Monday</label><label class="checkline"><input type="checkbox" value="Tuesday" checked> Tuesday</label><label class="checkline"><input type="checkbox" value="Wednesday" checked> Wednesday</label><label class="checkline"><input type="checkbox" value="Thursday" checked> Thursday</label><label class="checkline"><input type="checkbox" value="Friday" checked> Friday</label><label class="checkline"><input type="checkbox" value="Saturday" checked> Saturday</label><label class="checkline"><input type="checkbox" value="Sunday" checked> Sunday</label></div></div></div></div><div class="wizard-nav"><button type="button" class="btn btn-primary edit-wizard-next-inline" data-edit-next-step="4">Continue</button></div></div><div class="wizard-panel" data-edit-step-panel="4"><h4>Options</h4><div class="wizard-grid"><div class="field full"><div class="retention-box"><label class="radioline"><input type="radio" name="editBackupRetentionMode" value="keep-all" checked> Keep all backups</label><div class="retention-inline"><label class="radioline"><input type="radio" name="editBackupRetentionMode" value="delete-older"> Delete backups that are older than</label><input type="number" id="editRetentionDays" min="1" value="30" disabled><select id="editRetentionUnit" disabled><option value="D">days</option></select></div></div></div><div class="field full"><div class="advanced-box"><button type="button" class="advanced-toggle" id="toggleEditAdvancedOptionsBtn">Add advanced option +</button><div class="advanced-options" id="editAdvancedOptionsPanel" hidden><div class="wizard-grid"><div class="field"><label>Limit the size of the volumes</label><div class="wizard-inline-group"><input type="number" id="editVolumeSize" min="1" value="50"><select id="editVolumeSizeUnit"><option value="MB">MB</option><option value="GB">GB</option></select></div><div class="field-hint">Stored as the Duplicati `dblock-size` option.</div></div></div></div></div></div><div class="form-note"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span data-i18n="editNote">Some settings can only be changed from the Duplicati web UI.</span></div></div><div class="wizard-nav"><button type="button" class="btn btn-ghost" id="editWizardBackBtn">← Go back</button><div class="wizard-nav-actions"><button type="button" class="btn btn-ghost" onclick="closeModal('editModal')" data-i18n="cancel">Cancel</button><button type="submit" class="btn btn-primary" id="editWizardSubmitBtn" data-i18n="saveChanges">Save Changes</button></div></div></div></form></div></div></div>
<div class="modal" id="logsModal"><div class="modal-box" style="max-width:780px;"><div class="modal-head"><h3><span data-i18n="backupLogs">Backup Logs</span> — <span id="logsModalTitle"></span></h3><button class="close-btn" onclick="closeModal('logsModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div id="logsModalContent" style="max-height:520px;overflow-y:auto;"><div class="empty-state"><p data-i18n="loading">Loading…</p></div></div></div></div>
<div class="modal" id="confirmActionModal"><div class="modal-box" style="max-width:460px;"><div class="modal-head"><h3 id="confirmActionTitle">Confirm action</h3><button class="close-btn" type="button" onclick="closeConfirmActionModal(false)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div class="confirm-modal-body"><p id="confirmActionText"></p></div><div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeConfirmActionModal(false)">Cancel</button><button type="button" class="btn btn-danger" id="confirmActionOkBtn">Confirm</button></div></div></div>
<div class="modal" id="promptActionModal"><div class="modal-box" style="max-width:460px;"><div class="modal-head"><h3 id="promptActionTitle">Input required</h3><button class="close-btn" type="button" onclick="closePromptActionModal(null)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div class="confirm-modal-body"><p id="promptActionText"></p><input type="text" class="modal-input" id="promptActionInput" placeholder=""></div><div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closePromptActionModal(null)">Cancel</button><button type="button" class="btn btn-primary" id="promptActionOkBtn">Continue</button></div></div></div>
<div class="modal" id="interfaceModal" style="background:var(--bg-base);"><div class="modal-box" style="max-width:98vw;max-height:98vh;width:98vw;height:98vh;padding:0;border:none;"><div class="modal-head" style="padding:10px 16px;margin:0;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;"><div style="display:flex;align-items:center;gap:10px;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.686 15A14.5 14.5 0 0 1 12 22a14.5 14.5 0 0 1 0-20 10 10 0 1 0 9.542 13"/><path d="M2 12h8.5"/><path d="M20 6V4a2 2 0 1 0-4 0v2"/><rect width="8" height="5" x="14" y="6" rx="1"/></svg><span style="font-size:14px;font-weight:600;" data-i18n="duplicatiInterface">Duplicati Web Interface</span></div><div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;"><button class="btn btn-sm btn-ghost" onclick="retryInterfaceLoad()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg><span data-i18n="reload">Reload</span></button><button class="btn btn-sm btn-ghost" onclick="openInNewWindow()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><span data-i18n="openInNewWindow">Open in New Window</span></button><button class="close-btn" onclick="closeInterfaceModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div></div><div style="flex:1;min-height:0;display:flex;flex-direction:column;position:relative;"><iframe id="interfaceFrame" name="interfaceFrame" src="" style="flex:1;width:100%;height:100%;border:none;background:var(--bg-base);" sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-top-navigation allow-popups-to-escape-sandbox allow-modals"></iframe></div></div></div>
<script>
'use strict';
const TRANSLATIONS = {
    en: { refresh:'Refresh', credentials:'Credentials', navigation:'Navigation', backupJobs:'Backup Jobs', logs:'Logs', notifications:'Notifications', serverSettings:'Server Settings', controls:'Controls', pauseServer:'Pause Server', resumeServer:'Resume Server', serverHost:'Server Host', apiToken:'API Token (Forever Token)', connect:'Connect', clear:'Clear', selectBackupToView:'Select a backup above to view its logs', systemLogs:'System Logs', serverLog:'Server Log', reload:'Reload', saveSettings:'Save Settings', loading:'Loading…', credentialsSettings:'Credentials Settings', credNote:'Credentials are stored encrypted (AES-256-GCM) in the database.', saveAndConnect:'Save & Connect', delete:'Delete', editServerSetting:'Edit Server Setting', settingKey:'Setting Key', settingValue:'Setting Value', cancel:'Cancel', save:'Save', createNewBackup:'Create New Backup', backupName:'Backup Name', sourceFolders:'Source Folder(s)', destinationURL:'Destination URL', passphrase:'Encryption Passphrase', schedule:'Schedule', manualOnly:'Manual only', daily:'Daily', weekly:'Weekly', monthly:'Monthly', createNote:'Creating via API creates a basic config. Edit in the Duplicati UI for advanced options.', createBackup:'Create Backup', editBackup:'Edit Backup', editNote:'Some settings can only be changed from the Duplicati web UI.', saveChanges:'Save Changes', backupLogs:'Backup Logs', name:'Name', status:'Status', progress:'Progress', target:'Target', actions:'Actions', connectToLoad:'Connect to load backup jobs', resume:'Resume', totalJobs:'Total Jobs', successful:'Successful', errors:'Errors', scheduled:'Scheduled', selectBackup:'Select backup…', pauseDuration:'Pause duration (e.g. 30m, 2h). Leave blank for indefinite:', serverPaused:'Server is paused — backups are not running', connected:'Connected', offline:'Offline', deleteConfirm:'Delete backup "{name}"? This cannot be undone.', verifying:'Verifying…', verifyDone:'Verification complete', noNotifications:'No notifications', noLogs:'No log entries', bytes:'bytes', files:'files', phase:'Phase', speed:'Speed', elapsed:'Elapsed', remaining:'Remaining', processed:'Processed', backupDetails:'Backup Details', versions:'Versions', viewInfo:'Info', moreActions:'More', verify:'Verify', browseHint:'Expand drives or folders and tick items to add paths. You can still edit the field manually.', sourceTreeCaption:'Folder list', destTreeCaption:'Destination folder', destBrowseHint:'Select a destination folder from the tree above, or enter manually below.', connectToBrowse:'Connect to the Duplicati server to browse folders.', localDestination:'Local', pathLabel:'Path', noVersions:'No backup versions found', generalInfo:'General Info', targetInfo:'Target Info', lastRun:'Last Run', duration:'Duration', filesCount:'Files', size:'Size', fullBackup:'Full Backup', version:'Version', time:'Time', type:'Type', scheduleInfo:'Schedule', nextRun:'Next Run', repeat:'Repeat', allowedDays:'Allowed Days', newBackup:'New Backup', semicolonTip:'Separate multiple paths with semicolons ( ; )', run:'Run', stop:'Stop', edit:'Edit', logsShort:'Logs', serverInfo:'Server', systemInfo:'System info', changelog:'Changelog', licenses:'Licenses', cliCommands:'CLI commands', idleStatus:'Idle', incrementalBackup:'Incremental', openInterface:'Open Interface', webPassword:'Web Interface Password', passwordNote:'This password will be auto-filled when opening the web interface', duplicatiInterface:'Duplicati Web Interface', interfaceNote:'Interface loads in isolated mode. Use credentials from settings.', openInNewWindow:'Open in New Window', loginToDuplicati:'Login to Duplicati', loginNote:'Enter your password to access the Duplicati web interface.', fileExplorer:'File Explorer', selectBackupBrowse:'Select a backup to browse its destination folder', emptyFolder:'Empty folder', folder:'Folder', file:'File', noSourceFolders:'No destination folders configured', loadingFiles:'Loading files…', destinationFolder:'Destination Folder', sourceFolder:'Source Folder', diskSpace:'Disk Space', used:'Used', free:'Free', total:'Total', },
    ru: { refresh:'Обновить', credentials:'Учётные данные', navigation:'Навигация', backupJobs:'Задания резервного копирования', logs:'Журналы', notifications:'Уведомления', serverSettings:'Настройки сервера', controls:'Управление', pauseServer:'Пауза сервера', resumeServer:'Возобновить сервер', serverHost:'Хост сервера', apiToken:'API Токен (Forever Token)', connect:'Подключиться', clear:'Очистить', selectBackupToView:'Выберите задание для просмотра журналов', systemLogs:'Системный журнал', serverLog:'Журнал сервера', reload:'Перезагрузить', saveSettings:'Сохранить настройки', loading:'Загрузка…', credentialsSettings:'Настройки учётных данных', credNote:'Учётные данные хранятся в зашифрованном виде (AES-256-GCM) в базе данных.', saveAndConnect:'Сохранить и подключиться', delete:'Удалить', editServerSetting:'Редактировать настройку', settingKey:'Ключ настройки', settingValue:'Значение', cancel:'Отмена', save:'Сохранить', createNewBackup:'Создать новое задание', backupName:'Название', sourceFolders:'Исходные папки', destinationURL:'URL назначения', passphrase:'Пароль шифрования', schedule:'Расписание', manualOnly:'Только вручную', daily:'Ежедневно', weekly:'Еженедельно', monthly:'Ежемесячно', createNote:'Создание через API создаёт базовую конфигурацию. Для расширенных настроек используйте Duplicati UI.', createBackup:'Создать задание', editBackup:'Редактировать задание', editNote:'Некоторые настройки можно изменить только в Duplicati UI.', saveChanges:'Сохранить изменения', backupLogs:'Журнал задания', name:'Название', status:'Статус', progress:'Прогресс', target:'Назначение', actions:'Действия', connectToLoad:'Подключитесь для загрузки заданий', resume:'Возобновить', totalJobs:'Всего заданий', successful:'Успешных', errors:'Ошибок', scheduled:'По расписанию', selectBackup:'Выбрать задание…', pauseDuration:'Длительность паузы (например 30m, 2h). Оставьте пустым для бессрочной:', serverPaused:'Сервер на паузе — резервные копии не создаются', connected:'Подключено', offline:'Не в сети', deleteConfirm:'Удалить задание «{name}»? Это действие необратимо.', verifying:'Проверка…', verifyDone:'Проверка завершена', noNotifications:'Нет уведомлений', noLogs:'Нет записей журнала', bytes:'байт', files:'файлов', phase:'Фаза', speed:'Скорость', elapsed:'Прошло', remaining:'Осталось', processed:'Обработано', backupDetails:'Детали задания', versions:'Версии', viewInfo:'Инфо', moreActions:'Ещё', verify:'Проверить', browseHint:'Разверните диски или папки и отметьте нужные пути. Поле можно править вручную.', sourceTreeCaption:'Список папок', destTreeCaption:'Папка назначения', destBrowseHint:'Выберите папку назначения из дерева выше или введите вручную ниже.', connectToBrowse:'Подключитесь к серверу Duplicati, чтобы просматривать папки.', localDestination:'Локально', pathLabel:'Путь', noVersions:'Версии не найдены', generalInfo:'Общая информация', targetInfo:'Информация о цели', lastRun:'Последний запуск', duration:'Длительность', filesCount:'Файлы', size:'Размер', fullBackup:'Полная копия', version:'Версия', time:'Время', type:'Тип', scheduleInfo:'Расписание', nextRun:'Следующий запуск', repeat:'Повторение', allowedDays:'Разрешённые дни', newBackup:'Новое задание', semicolonTip:'Разделяйте несколько путей точкой с запятой ( ; )', run:'Запустить', stop:'Остановить', edit:'Изменить', logsShort:'Журнал', serverInfo:'Сервер', systemInfo:'О системе', changelog:'Изменения', licenses:'Лицензии', cliCommands:'Команды CLI', idleStatus:'Готово', incrementalBackup:'Инкремент', openInterface:'Открыть интерфейс', webPassword:'Пароль веб-интерфейса', passwordNote:'Этот пароль будет автоматически введён при открытии веб-интерфейса', duplicatiInterface:'Веб-интерфейс Duplicati', interfaceNote:'Интерфейс загружается в изолированном режиме. Используйте учётные данные из настроек.', openInNewWindow:'Открыть в новом окне', loginToDuplicati:'Вход в Duplicati', loginNote:'Введите пароль для доступа к веб-интерфейсу Duplicati.', fileExplorer:'Файловый менеджер', selectBackupBrowse:'Выберите задание для просмотра папки назначения', emptyFolder:'Папка пуста', folder:'Папка', file:'Файл', noSourceFolders:'Папки назначения не настроены', loadingFiles:'Загрузка файлов…', destinationFolder:'Папка назначения', sourceFolder:'Исходная папка', diskSpace:'Дисковое пространство', used:'Занято', free:'Свободно', total:'Всего', },
    uk: { refresh:'Оновити', credentials:'Облікові дані', navigation:'Навігація', backupJobs:'Завдання резервного копіювання', logs:'Журнали', notifications:'Сповіщення', serverSettings:'Налаштування сервера', controls:'Керування', pauseServer:'Пауза сервера', resumeServer:'Відновити сервер', serverHost:'Хост сервера', apiToken:'API Токен (Forever Token)', connect:'Підключитися', clear:'Очистити', selectBackupToView:'Виберіть завдання для перегляду журналів', systemLogs:'Системний журнал', serverLog:'Журнал сервера', reload:'Перезавантажити', saveSettings:'Зберегти налаштування', loading:'Завантаження…', credentialsSettings:'Налаштування облікових даних', credNote:'Облікові дані зберігаються у зашифрованому вигляді (AES-256-GCM) в базі даних.', saveAndConnect:'Зберегти та підключитися', delete:'Видалити', editServerSetting:'Редагувати налаштування', settingKey:'Ключ налаштування', settingValue:'Значення', cancel:'Скасувати', save:'Зберегти', createNewBackup:'Створити нове завдання', backupName:'Назва', sourceFolders:'Вихідні папки', destinationURL:'URL призначення', passphrase:'Пароль шифрування', schedule:'Розклад', manualOnly:'Лише вручну', daily:'Щодня', weekly:'Щотижня', monthly:'Щомісяця', createNote:'Створення через API створює базову конфігурацію. Для розширених налаштувань використовуйте Duplicati UI.', createBackup:'Створити завдання', editBackup:'Редагувати завдання', editNote:'Деякі налаштування можна змінити лише в Duplicati UI.', saveChanges:'Зберегти зміни', backupLogs:'Журнал завдання', name:'Назва', status:'Статус', progress:'Прогрес', target:'Призначення', actions:'Дії', connectToLoad:'Підключіться для завантаження завдань', resume:'Відновити', totalJobs:'Всього завдань', successful:'Успішних', errors:'Помилок', scheduled:'За розкладом', selectBackup:'Вибрати завдання…', pauseDuration:'Тривалість паузи (наприклад 30m, 2h). Залиште порожнім для безстрокової:', serverPaused:'Сервер на паузі — резервні копії не створюються', connected:'Підключено', offline:'Не в мережі', deleteConfirm:'Видалити завдання «{name}»? Цю дію не можна скасувати.', verifying:'Перевірка…', verifyDone:'Перевірку завершено', noNotifications:'Немає сповіщень', noLogs:'Немає записів журнала', bytes:'байт', files:'файлів', phase:'Фаза', speed:'Швидкість', elapsed:'Минуло', remaining:'Залишилось', processed:'Оброблено', backupDetails:'Деталі завдання', versions:'Версії', viewInfo:'Інфо', moreActions:'Ще', verify:'Перевірити', browseHint:'Розгорніть диски чи папки та позначте шляхи. Поле можна змінювати вручну.', sourceTreeCaption:'Список папок', destTreeCaption:'Папка призначення', destBrowseHint:'Виберіть папку призначення з дерева вище або введіть вручну нижче.', connectToBrowse:'Підключіться до сервера Duplicati, щоб переглядати папки.', localDestination:'Локально', pathLabel:'Шлях', noVersions:'Версії не знайдено', generalInfo:'Загальна інформація', targetInfo:'Інформація про ціль', lastRun:'Останній запуск', duration:'Тривалість', filesCount:'Файли', size:'Розмір', fullBackup:'Повна копія', version:'Версія', time:'Час', type:'Тип', scheduleInfo:'Розклад', nextRun:'Наступний запуск', repeat:'Повторення', allowedDays:'Дозволені дні', newBackup:'Нове завдання', semicolonTip:'Розділяйте кілька шляхів крапкою з комою ( ; )', run:'Запустити', stop:'Зупинити', edit:'Змінити', logsShort:'Журнал', serverInfo:'Сервер', systemInfo:'Про систему', changelog:'Зміни', licenses:'Ліцензії', cliCommands:'Команди CLI', idleStatus:'Очікує', incrementalBackup:'Інкремент', openInterface:'Відкрити інтерфейс', webPassword:'Пароль веб-інтерфейсу', passwordNote:'Цей пароль буде автоматично введено при відкритті веб-інтерфейсу', duplicatiInterface:'Веб-інтерфейс Duplicati', interfaceNote:'Інтерфейс завантажується в ізольованому режимі. Використовуйте облікові дані з налаштувань.', openInNewWindow:'Відкрити в новому вікні', loginToDuplicati:'Вхід до Duplicati', loginNote:'Введіть пароль для доступу до веб-інтерфейсу Duplicati.', fileExplorer:'Файловий менеджер', selectBackupBrowse:'Виберіть завдання для перегляду папки призначення', emptyFolder:'Папка пуста', folder:'Папка', file:'Файл', noSourceFolders:'Папки призначення не налаштовані', loadingFiles:'Завантаження файлів…', destinationFolder:'Папка призначення', sourceFolder:'Вихідна папка', diskSpace:'Дисковий простір', used:'Зайнято', free:'Вільно', total:'Всього', },
    be: { refresh:'Абнавіць', credentials:'Уліковыя дадзеныя', navigation:'Навігацыя', backupJobs:'Заданні рэзервовага капіравання', logs:'Журналы', notifications:'Апавяшчэнні', serverSettings:'Налады сервера', controls:'Кіраванне', pauseServer:'Паўза сервера', resumeServer:'Аднавіць сервер', serverHost:'Хост сервера', apiToken:'API Токен (Forever Token)', connect:'Падключыцца', clear:'Ачысціць', selectBackupToView:'Выберыце заданне для прагляду журналаў', systemLogs:'Сістэмны журнал', serverLog:'Журнал сервера', reload:'Перазагрузіць', saveSettings:'Захаваць налады', loading:'Загрузка…', credentialsSettings:'Налады ўліковых дадзеных', credNote:'Уліковыя дадзеныя захоўваюцца ў зашыфраваным выглядзе (AES-256-GCM) у базе дадзеных.', saveAndConnect:'Захаваць і падключыцца', delete:'Выдаліць', editServerSetting:'Рэдагаваць наладу', settingKey:'Ключ налады', settingValue:'Значэнне', cancel:'Адмена', save:'Захаваць', createNewBackup:'Стварыць новае заданне', backupName:'Назва', sourceFolders:'Зыходныя папкі', destinationURL:'URL прызначэння', passphrase:'Пароль шыфравання', schedule:'Расклад', manualOnly:'Толькі ўручную', daily:'Штодня', weekly:'Штотыдзень', monthly:'Штомесяц', createNote:'Стварэнне праз API стварае базавую канфігурацыю. Для пашыраных налад выкарыстоўвайце Duplicati UI.', createBackup:'Стварыць заданне', editBackup:'Рэдагаваць заданне', editNote:'Некаторыя налады можна змяніць толькі ў Duplicati UI.', saveChanges:'Захаваць змены', backupLogs:'Журнал задання', name:'Назва', status:'Статус', progress:'Прагрэс', target:'Прызначэнне', actions:'Дзеянні', connectToLoad:'Падключыцеся для загрузкі заданняў', resume:'Аднавіць', totalJobs:'Усяго заданняў', successful:'Паспяховых', errors:'Памылак', scheduled:'Па раскладзе', selectBackup:'Выбраць заданне…', pauseDuration:'Працягласць паўзы (напр. 30m, 2h). Пакіньце пустым для бестэрміновай:', serverPaused:'Сервер на паўзе — рэзервовыя копіі не ствараюцца', connected:'Падключана', offline:'Не ў сетцы', deleteConfirm:'Выдаліць заданне «{name}»? Гэта дзеянне немагчыма адмяніць.', verifying:'Праверка…', verifyDone:'Праверка завершана', noNotifications:'Няма апавяшчэнняў', noLogs:'Няма запісаў журнала', bytes:'байт', files:'файлаў', phase:'Фаза', speed:'Хуткасць', elapsed:'Прайшло', remaining:'Засталося', processed:'Апрацавана', backupDetails:'Дэталі задання', versions:'Версіі', viewInfo:'Інфо', moreActions:'Яшчэ', verify:'Праверыць', browseHint:'Разгарніце дыскі або папкі і пазначце шляхі. Поле можна рэдагаваць уручную.', sourceTreeCaption:'Спіс папак', destTreeCaption:'Папка прызначэння', destBrowseHint:'Выберыце папку прызначэння з дрэва вышэй або ўвядзіце ўручную ніжэй.', connectToBrowse:'Падключыцеся да сервера Duplicati, каб праглядаць папкі.', localDestination:'Лакальна', pathLabel:'Шлях', noVersions:'Версіі не знойдзены', generalInfo:'Агульная інфармацыя', targetInfo:'Інфармацыя пра мэту', lastRun:'Апошні запуск', duration:'Працягласць', filesCount:'Файлы', size:'Памер', fullBackup:'Поўная копія', version:'Версія', time:'Час', type:'Тып', scheduleInfo:'Расклад', nextRun:'Наступны запуск', repeat:'Паўтарэнне', allowedDays:'Дазволеныя дні', newBackup:'Новае заданне', semicolonTip:'Раздзяляйце некалькі шляхоў кропкай з коскі ( ; )', run:'Запусціць', stop:'Спыніць', edit:'Змяніць', logsShort:'Журнал', serverInfo:'Сервер', systemInfo:'Пра сістэму', changelog:'Змены', licenses:'Ліцэнзіі', cliCommands:'Каманды CLI', idleStatus:'Чакае', incrementalBackup:'Інкрэмент', openInterface:'Адкрыць інтэрфейс', webPassword:'Пароль вэб-інтэрфейсу', passwordNote:'Гэты пароль будзе аўтаматычна ўведзены пры адкрыцці вэб-інтэрфейсу', duplicatiInterface:'Вэб-інтэрфейс Duplicati', interfaceNote:'Інтэрфейс загружаецца ў ізаляваным рэжыме. Выкарыстоўвайце ўліковыя дадзеныя з налад.', openInNewWindow:'Адкрыць у новым акне', loginToDuplicati:'Уваход у Duplicati', loginNote:'Увядзіце пароль для доступу да вэб-інтэрфейсу Duplicati.', fileExplorer:'Файлавы менеджэр', selectBackupBrowse:'Выберыце заданне для прагляду папки прызначэння', emptyFolder:'Папка пуста', folder:'Папка', file:'Файл', noSourceFolders:'Папкі прызначэння не настроены', loadingFiles:'Загрузка файлаў…', destinationFolder:'Папка прызначэння', sourceFolder:'Зыходная папка', diskSpace:'Дыскавая прастора', used:'Занята', free:'Свабодна', total:'Усяго', },
};
Object.assign(TRANSLATIONS.en, { dayMonday:'Monday', dayTuesday:'Tuesday', dayWednesday:'Wednesday', dayThursday:'Thursday', dayFriday:'Friday', daySaturday:'Saturday', daySunday:'Sunday', unnamed:'Unnamed', newFolder:'New folder', filterFolders:'Filter folders…', newFolderNameHint:'New folder name (created inside the tree root shown above):', treeBrowseFromField:'Enter a single absolute path (e.g. /mnt) to list only that folder — not parents above.' });
Object.assign(TRANSLATIONS.ru, { dayMonday:'Понедельник', dayTuesday:'Вторник', dayWednesday:'Среда', dayThursday:'Четверг', dayFriday:'Пятница', daySaturday:'Суббота', daySunday:'Воскресенье', unnamed:'Без имени', newFolder:'Новая папка', filterFolders:'Поиск папок…', newFolderNameHint:'Имя новой папки (в корне дерева выше):', treeBrowseFromField:'Введите один абсолютный путь (например /mnt), чтобы показать только его содержимое, без уровней выше.' });
Object.assign(TRANSLATIONS.uk, { newFolder:'Нова папка', filterFolders:'Фільтр папок…', newFolderNameHint:'Назва нової папки (у корені дерева вище):', treeBrowseFromField:'Введіть один абсолютний шлях (наприклад /mnt), щоб показати лише його вміст.' });
Object.assign(TRANSLATIONS.be, { newFolder:'Новая папка', filterFolders:'Фільтр папак…', newFolderNameHint:'Назва новай папкі (у карані дрэва вышэй):', treeBrowseFromField:'Увядзіце адзін абсалютны шлях (напрыклад /mnt), каб паказаць толькі яго змест.' });
Object.assign(TRANSLATIONS.uk, { dayMonday:'Понеділок', dayTuesday:'Вівторок', dayWednesday:'Середа', dayThursday:'Четвер', dayFriday:'Пʼятниця', daySaturday:'Субота', daySunday:'Неділя', unnamed:'Без назви' });
Object.assign(TRANSLATIONS.be, { dayMonday:'Панядзелак', dayTuesday:'Аўторак', dayWednesday:'Серада', dayThursday:'Чацвер', dayFriday:'Пятніца', daySaturday:'Субота', daySunday:'Нядзеля', unnamed:'Без назвы' });
Object.assign(TRANSLATIONS.en, { testDestRw:'Test read/write', testDestRwRunning:'Testing…', testDestRwOk:'OK — read/write (PHP: {user})', testDestRwFail:'Failed: {msg}', testDestRwRemote:'Only local paths (/…) or file:// can be tested here.', testDestRwNeed:'Enter a destination path first.', destBrowseHint:'Select a destination folder from the tree on the right, or enter manually on the left.', browseHint:'Expand folders on the right and tick paths to include. You can still edit the field on the left.' });
Object.assign(TRANSLATIONS.ru, { testDestRw:'Проверить запись и чтение', testDestRwRunning:'Проверка…', testDestRwOk:'ОК — запись и чтение (PHP: {user})', testDestRwFail:'Ошибка: {msg}', testDestRwRemote:'Проверяются только локальные пути (/…) или file://.', testDestRwNeed:'Сначала укажите путь назначения.', destBrowseHint:'Выберите папку назначения в дереве справа или введите вручную слева.', browseHint:'Разверните папки справа и отметьте нужные пути. Поле слева можно править вручную.' });
Object.assign(TRANSLATIONS.uk, { testDestRw:'Перевірити запис і читання', testDestRwRunning:'Перевірка…', testDestRwOk:'ОК — запис і читання (PHP: {user})', testDestRwFail:'Помилка: {msg}', testDestRwRemote:'Лише локальні шляхи (/…) або file://.', testDestRwNeed:'Спочатку вкажіть шлях призначення.', });
Object.assign(TRANSLATIONS.be, { testDestRw:'Праверыць запіс і чытанне', testDestRwRunning:'Праверка…', testDestRwOk:'ОК — запіс і чытанне (PHP: {user})', testDestRwFail:'Памылка: {msg}', testDestRwRemote:'Толькі лакальныя шляхі (/…) або file://.', testDestRwNeed:'Спачатку ўкажыце шлях прызначэння.', });
const BACKUP_PHASE_I18N = {
    en: {
        statusRunning:'Running', statusSuccess:'Success', statusError:'Error', statusWarning:'Warning',
        sourceSizeLabel:'Source', destinationSizeLabel:'Destination',
        phase_Starting_Operation:'Starting operation', phase_Backup_Begin:'Backup started', phase_Backup_PreBackupVerify:'Pre-backup verification',
        phase_Backup_ProcessingFiles:'Processing files', phase_Backup_Finalize:'Finalizing backup', phase_Backup_WaitForUpload:'Waiting for upload',
        phase_Backup_Delete:'Deleting old files', phase_Backup_Compact:'Compacting', phase_Backup_VerificationUpload:'Verifying upload',
        phase_Backup_PostBackupVerify:'Post-backup verification', phase_Backup_PostBackupTest:'Post-backup test', phase_Backup_PreviousBackupFinalize:'Finalizing previous backup',
        phase_Backup_Complete:'Complete', phase_Verify_Running:'Verifying', phase_Restore_Begin:'Restore started',
        phase_Restore_DownloadingRemoteFiles:'Downloading remote files', phase_Restore_Complete:'Restore complete',
        phase_Recreate_Running:'Recreating database', phase_Vacuum_Running:'Vacuuming database', phase_Error:'Error',
    },
    ru: {
        statusRunning:'Выполняется', statusSuccess:'Успешно', statusError:'Ошибка', statusWarning:'Предупреждение',
        sourceSizeLabel:'Источник', destinationSizeLabel:'Назначение',
        phase_Starting_Operation:'Запуск операции', phase_Backup_Begin:'Начало резервного копирования', phase_Backup_PreBackupVerify:'Проверка перед копированием',
        phase_Backup_ProcessingFiles:'Обработка файлов', phase_Backup_Finalize:'Завершение резервного копирования', phase_Backup_WaitForUpload:'Ожидание загрузки',
        phase_Backup_Delete:'Удаление старых файлов', phase_Backup_Compact:'Сжатие', phase_Backup_VerificationUpload:'Проверка загрузки',
        phase_Backup_PostBackupVerify:'Проверка после копирования', phase_Backup_PostBackupTest:'Тест после копирования', phase_Backup_PreviousBackupFinalize:'Завершение предыдущей копии',
        phase_Backup_Complete:'Завершено', phase_Verify_Running:'Проверка', phase_Restore_Begin:'Начало восстановления',
        phase_Restore_DownloadingRemoteFiles:'Загрузка удалённых файлов', phase_Restore_Complete:'Восстановление завершено',
        phase_Recreate_Running:'Пересоздание базы', phase_Vacuum_Running:'Очистка базы', phase_Error:'Ошибка',
    },
    uk: {
        statusRunning:'Виконується', statusSuccess:'Успішно', statusError:'Помилка', statusWarning:'Попередження',
        sourceSizeLabel:'Джерело', destinationSizeLabel:'Призначення',
        phase_Starting_Operation:'Запуск операції', phase_Backup_Begin:'Початок резервного копіювання', phase_Backup_PreBackupVerify:'Перевірка перед копіюванням',
        phase_Backup_ProcessingFiles:'Обробка файлів', phase_Backup_Finalize:'Завершення резервного копіювання', phase_Backup_WaitForUpload:'Очікування завантаження',
        phase_Backup_Delete:'Видалення старих файлів', phase_Backup_Compact:'Стиснення', phase_Backup_VerificationUpload:'Перевірка завантаження',
        phase_Backup_PostBackupVerify:'Перевірка після копіювання', phase_Backup_PostBackupTest:'Тест після копіювання', phase_Backup_PreviousBackupFinalize:'Завершення попередньої копії',
        phase_Backup_Complete:'Завершено', phase_Verify_Running:'Перевірка', phase_Restore_Begin:'Початок відновлення',
        phase_Restore_DownloadingRemoteFiles:'Завантаження віддалених файлів', phase_Restore_Complete:'Відновлення завершено',
        phase_Recreate_Running:'Перестворення бази', phase_Vacuum_Running:'Очищення бази', phase_Error:'Помилка',
    },
    be: {
        statusRunning:'Выконваецца', statusSuccess:'Паспяхова', statusError:'Памылка', statusWarning:'Папярэджанне',
        sourceSizeLabel:'Крыніца', destinationSizeLabel:'Прызначэнне',
        phase_Starting_Operation:'Запуск аперацыі', phase_Backup_Begin:'Пачатак рэзервовага капіравання', phase_Backup_PreBackupVerify:'Праверка перад капіраваннем',
        phase_Backup_ProcessingFiles:'Апрацоўка файлаў', phase_Backup_Finalize:'Завяршэнне рэзервовага капіравання', phase_Backup_WaitForUpload:'Чаканне загрузкі',
        phase_Backup_Delete:'Выдаленне старых файлаў', phase_Backup_Compact:'Сціск', phase_Backup_VerificationUpload:'Праверка загрузкі',
        phase_Backup_PostBackupVerify:'Праверка пасля капіравання', phase_Backup_PostBackupTest:'Тест пасля капіравання', phase_Backup_PreviousBackupFinalize:'Завяршэнне папярэдняй копіі',
        phase_Backup_Complete:'Завершана', phase_Verify_Running:'Праверка', phase_Restore_Begin:'Пачатак аднаўлення',
        phase_Restore_DownloadingRemoteFiles:'Загрузка аддаленых файлаў', phase_Restore_Complete:'Аднаўленне завершана',
        phase_Recreate_Running:'Перастварэнне базы', phase_Vacuum_Running:'Ачыстка базы', phase_Error:'Памылка',
    },
};
Object.keys(BACKUP_PHASE_I18N).forEach(lang => Object.assign(TRANSLATIONS[lang], BACKUP_PHASE_I18N[lang]));
const URL_PARAMS = new URLSearchParams(location.search);
const LANG  = (['en','ru','uk','be'].includes(URL_PARAMS.get('lang'))) ? URL_PARAMS.get('lang') : 'en';
function resolveThemeParam() {
    const p = (URL_PARAMS.get('theme') || '').toLowerCase();
    if (p === 'light') return 'light';
    if (p === 'dark') return 'dark';
    if (p === 'auto' || p === '') try { return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'; } catch (_) { return 'dark'; }
    return 'dark';
}
function applyAccentFromUrl() {
    let c = URL_PARAMS.get('color');
    if (!c) return;
    try { c = decodeURIComponent(c); } catch (_) {}
    if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(c)) return;
    const root = document.documentElement;
    root.style.setProperty('--accent', c);
    const h = c.length === 7 ? [parseInt(c.slice(1,3),16),parseInt(c.slice(3,5),16),parseInt(c.slice(5,7),16)] : [parseInt(c[1]+c[1],16),parseInt(c[2]+c[2],16),parseInt(c[3]+c[3],16)];
    const mix = (t) => 'rgb(' + h.map((x,i)=>Math.round(x+(t[i]-x)*0.22)).join(',') + ')';
    root.style.setProperty('--accent-hover', mix([255,255,255]));
    root.style.setProperty('--accent-dim', 'rgba(' + h[0] + ',' + h[1] + ',' + h[2] + ',0.22)');
}
document.body.setAttribute('data-theme', resolveThemeParam());
applyAccentFromUrl();
try {
    window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', () => {
        if ((URL_PARAMS.get('theme') || '').toLowerCase() === 'auto' || URL_PARAMS.get('theme') === 'auto' || !URL_PARAMS.get('theme'))
            document.body.setAttribute('data-theme', resolveThemeParam());
    });
} catch (_) {}
function t(key, vars = {}) { let str = (TRANSLATIONS[LANG] || TRANSLATIONS.en)[key] || TRANSLATIONS.en[key] || key; Object.entries(vars).forEach(([k,v]) => { str = str.replace('{'+k+'}', v); }); return str; }
function applyTranslations() { document.querySelectorAll('[data-i18n]').forEach(el => { const k = el.getAttribute('data-i18n'); el.textContent = t(k); }); document.querySelectorAll('[data-i18n-opt]').forEach(el => { el.textContent = t(el.getAttribute('data-i18n-opt')); }); document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { el.placeholder = t(el.getAttribute('data-i18n-placeholder')); }); }
let currentHost = '', currentToken = '', backupsList = [], serverStatus = false, progressState = {}, lastServerState = {}, autoRefreshTimer = null, fastPollTimer = null, logsAutoRefreshTimer = null, lastFetchWarnAt = 0, lastProgressSnapshot = null, previousActiveTaskBackupId = null, progressCompletionRefreshInFlight = false;
let refreshBackgroundInFlight = false, loadBackupsInFlight = null, loadBackupsSeq = 0, enrichStatusesSeq = 0;
const backupStatusCache = new Map();
const API_CALL_TIMEOUT_MS = 15000;
function normalizeBackupHost(h) { let s = String(h || '').trim(); if (s.charCodeAt(0) === 0xFEFF) s = s.slice(1).trim(); return s.replace(/\/+$/, ''); }
function normalizeBackupToken(t) { let s = String(t || '').trim(); if (s.charCodeAt(0) === 0xFEFF) s = s.slice(1).trim(); while (/^bearer\s+/i.test(s)) s = s.replace(/^bearer\s+/i, '').trim(); return s; }
function isFetchFailure(err) { return err && (err.name === 'AbortError' || /Failed to fetch|NetworkError|Load failed|fetch/i.test(String(err.message || err))); }
function warnFetchFailureOnce(prefix, err) { const now = Date.now(); if (now - lastFetchWarnAt < 30000) return; lastFetchWarnAt = now; console.warn(prefix + ':', err?.message || err); }
async function apiCall(endpoint, method = 'GET', data = null) { const payload = { host: currentHost, token: currentToken, endpoint, method }; if (data !== null) payload.data = data; let res; const controller = new AbortController(); const timeoutId = setTimeout(() => controller.abort(), API_CALL_TIMEOUT_MS); try { res = await fetch('?proxy=1', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload), signal: controller.signal }); } catch (err) { if (isFetchFailure(err)) { const e = new Error('Network request failed'); e.isFetchFailure = true; throw e; } throw err; } finally { clearTimeout(timeoutId); } const text = await res.text(); if (!res.ok) { let msg = `HTTP ${res.status}`; try { const j = JSON.parse(text); msg = j.error || j.Error || msg; } catch(_){} const _e = new Error(msg); _e.statusCode = res.status; throw _e; } if (!text || !text.trim() || text.trim() === 'null') return null; try { return JSON.parse(text); } catch(e) { throw new Error('Invalid JSON: ' + text.substring(0,120)); } }
async function loadSavedCredentials() { try { const res = await fetch('?config=load'); const data = await res.json(); if (data.host) currentHost = normalizeBackupHost(data.host); if (data.token) currentToken = normalizeBackupToken(data.token); if (data.host && data.token) await connectAndLoad(); } catch(_) {} }
function clearSettings() { try { localStorage.removeItem('dup_host'); localStorage.removeItem('dup_token'); } catch(_){} currentHost = ''; currentToken = ''; serverStatus = false; if (autoRefreshTimer) clearInterval(autoRefreshTimer); if (fastPollTimer) clearInterval(fastPollTimer); if (logsAutoRefreshTimer) clearInterval(logsAutoRefreshTimer); setStatus(false); document.getElementById('dashboardContent').style.cssText = 'display:none'; toast(t('clear'), 'info'); }
async function connectAndLoad() { const host = normalizeBackupHost(currentHost); const token = normalizeBackupToken(currentToken); if (!host || !token) { toast('Set Backup host and token in the main Admin Panel settings.', 'error'); return; } currentHost = host; currentToken = token; loading(true, t('loading')); try { const state = await apiCall('serverstate'); serverStatus = true; setStatus(true); if (state && state.ProgramState === 'Paused') { const banner = document.getElementById('serverBanner'); banner.classList.add('paused'); document.getElementById('serverBannerText').textContent = t('serverPaused'); } document.getElementById('dashboardContent').style.cssText = ''; await Promise.all([loadBackups({ force: true }), loadProgressState()]); startAutoRefresh(); toast(t('connected') + ' — ' + backupsList.length + ' jobs', 'success'); } catch(err) { serverStatus = false; setStatus(false); document.getElementById('dashboardContent').style.cssText = 'display:none'; toast('Connection failed: ' + err.message, 'error'); } finally { loading(false); } }
function setStatus(online) { const badge = document.getElementById('serverStatusBadge'); const text = document.getElementById('serverStatusText'); badge.className = 'status-pill ' + (online ? 'online' : 'offline'); text.textContent = online ? t('connected') : t('offline'); }
function mapRawToStatus(raw) { if (!raw) return null; const s = String(raw); if (/success/i.test(s)) return 'Success'; if (/error|fail|fatal/i.test(s)) return 'Error'; if (/warn/i.test(s)) return 'Warning'; return null; }
function mapParsedResultString(pr) { if (!pr) return null; const s = String(pr); if (/^Success/i.test(s)) return 'Success'; if (/^Warning/i.test(s)) return 'Warning'; if (/^Error|^Fatal/i.test(s)) return 'Error'; return null; }
async function fetchParsedResultFromLog(backupId) { try { const log = await apiCall(`backup/${backupId}/log?pagesize=50`); const entries = Array.isArray(log) ? log : []; for (const e of entries) { const typ = String(e.Type || e.type || ''); if (typ !== 'Result' && typ !== 'Fatal') continue; const msg = e.Message || e.message || ''; if (!msg) continue; try { const j = JSON.parse(msg); if (j && j.ParsedResult) { const m = mapParsedResultString(j.ParsedResult); if (m) return m; } } catch (_) {} } } catch (_) {} return null; }
function applyBackupStatusesFromMetadata() {
    backupsList.forEach(b => {
        const rawMeta = b.Metadata?.LastResult ?? b.Metadata?.ParsedResult ?? '';
        let st = mapRawToStatus(rawMeta);
        if (!st) st = backupStatusCache.get(String(b.ID)) || null;
        if (!st && b.Metadata?.LastBackupDate) st = 'Idle';
        if (!st) st = 'Idle';
        if (b.IsActive) st = 'Running';
        b.Status = st;
    });
}
function refreshBackupSelectors() {
    const sel = document.getElementById('logBackupSelector');
    const previousValue = sel.value;
    sel.innerHTML = `<option value="">${t('selectBackup')}</option>` + backupsList.map(b => `<option value="${b.ID}">${esc(b.Name)}</option>`).join('');
    if (previousValue && backupsList.some(b => String(b.ID) === String(previousValue))) sel.value = previousValue;
    else if (backupsList.length > 0) sel.value = backupsList[backupsList.length - 1].ID;
}
function renderBackupsSummary() {
    refreshBackupSelectors();
    populateExplorerSelector();
    renderStats();
    renderBackupsTable();
}
async function enrichBackupStatusesFromLogs(seq) {
    const pending = backupsList.filter(b => !mapRawToStatus(b.Metadata?.LastResult ?? b.Metadata?.ParsedResult ?? '') && !b.IsActive);
    if (!pending.length) return;
    const concurrency = 3;
    let index = 0;
    async function worker() {
        while (index < pending.length) {
            const current = pending[index++];
            const status = await fetchParsedResultFromLog(current.ID);
            if (seq !== enrichStatusesSeq) return;
            if (status) {
                backupStatusCache.set(String(current.ID), status);
                const target = backupsList.find(b => String(b.ID) === String(current.ID));
                if (target && !target.IsActive && !mapRawToStatus(target.Metadata?.LastResult ?? target.Metadata?.ParsedResult ?? '')) {
                    target.Status = status;
                }
                renderStats();
                renderBackupsTable();
            }
        }
    }
    await Promise.all(Array.from({ length: Math.min(concurrency, pending.length) }, () => worker()));
}
async function loadBackups(options = {}) {
    const { force = false } = options;
    if (loadBackupsInFlight && !force) return loadBackupsInFlight;
    const seq = ++loadBackupsSeq;
    const task = (async () => {
        const result = await apiCall('backups');
        if (!Array.isArray(result)) throw new Error('Unexpected /backups response');
        if (seq !== loadBackupsSeq) return;
        backupsList = result.map(item => ({ ID: item.Backup.ID, Name: item.Backup.Name, Description: item.Backup.Description || '', TargetURL: item.Backup.TargetURL || '', Sources: item.Backup.Sources || [], Schedule: item.Schedule, Metadata: item.Backup.Metadata || {}, IsActive: item.ActiveRunId != null, ActiveRunId: item.ActiveRunId ?? null, }));
        applyBackupStatusesFromMetadata();
        renderBackupsSummary();
        const enrichSeq = ++enrichStatusesSeq;
        enrichBackupStatusesFromLogs(enrichSeq).catch(err => {
            if (!isFetchFailure(err) && !err?.isFetchFailure) console.warn('enrichBackupStatusesFromLogs:', err?.message || err);
        });
    })();
    loadBackupsInFlight = task;
    try {
        await task;
    } finally {
        if (loadBackupsInFlight === task) loadBackupsInFlight = null;
    }
}
function renderStats() { const total = backupsList.length; const sched = backupsList.filter(b => b.Schedule?.Repeat).length; const errored = backupsList.filter(b => b.Status === 'Error').length; const success = backupsList.filter(b => b.Status === 'Success').length; const icon = (path, cls) => `<div class="stat-icon ${cls}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg></div>`; document.getElementById('statsGrid').innerHTML = `<div class="stat-card">${icon('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>','blue')}<div class="stat-value">${total}</div><div class="stat-label">${t('totalJobs')}</div></div><div class="stat-card">${icon('<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>','green')}<div class="stat-value">${success}</div><div class="stat-label">${t('successful')}</div></div><div class="stat-card">${icon('<circle cx="12" cy="12" r="10"/><path d="M4.929 4.929 19.07 19.071"/>','red')}<div class="stat-value">${errored}</div><div class="stat-label">${t('errors')}</div></div><div class="stat-card">${icon('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>','purple')}<div class="stat-value">${sched}</div><div class="stat-label">${t('scheduled')}</div></div>`; }
async function viewBackupDetails(id, name) {
    document.getElementById('backupInfoTitle').textContent = name;
    const contentEl = document.getElementById('backupInfoContent');
    contentEl.innerHTML = `<div class="empty-state"><p>${t('loading')}</p></div>`;
    showModal('backupInfoModal');
    try {
        const backupDetail = backupsList.find(b => String(b.ID) === String(id));
        let versions = [];
        try { versions = await apiCall(`backup/${id}/filesets`); if (!Array.isArray(versions)) versions = []; } catch(e) { console.warn('Could not load versions', e); }
        const status = backupDetail?.Status || 'Idle';
        const isRunning = String(id) === String(resolveActiveTaskBackupId(lastServerState, progressState)) && isProgressPhaseActive(progressState);
        const phaseRaw = progressState?.Phase ?? progressState?.BackupPhase ?? '';
        const statusCls = status === 'Success' ? 'info-status-success' : status === 'Error' ? 'info-status-error' : status === 'Warning' ? 'info-status-warning' : (isRunning || status === 'Running') ? 'info-status-running' : 'info-status-idle';
        const statusDot = `<span class="info-status-badge ${statusCls}">${esc(translateJobStatus(isRunning ? 'Running' : status, phaseRaw))}</span>`;
        const lastRunDate = backupDetail?.Metadata?.LastBackupDate || backupDetail?.Metadata?.LastBackupStarted;
        const lastRunHtml = lastRunDate ? `<span class="lastrun-frame"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>${formatDate(lastRunDate)}</span>` : '—';
        const sources = (backupDetail?.Sources || []).map(s => { try { return decodeURIComponent(s); } catch(_) { return s; } });
        const allowedDays = backupDetail?.Schedule?.AllowedDays;
        const dayShortToLong = {
            mon:'monday', tue:'tuesday', wed:'wednesday', thu:'thursday', fri:'friday', sat:'saturday', sun:'sunday'
        };
        const dayNames = {
            monday:t('dayMonday'), tuesday:t('dayTuesday'), wednesday:t('dayWednesday'), thursday:t('dayThursday'), friday:t('dayFriday'), saturday:t('daySaturday'), sunday:t('daySunday')
        };
        const allowedDaysHtml = Array.isArray(allowedDays) && allowedDays.length ? (() => {
            const seen = new Set();
            const unique = allowedDays.filter(d => {
                const normalized = String(d || '').trim().toLowerCase();
                if (!normalized || seen.has(normalized)) return false;
                seen.add(normalized);
                return true;
            });
            return `<ul class="allowed-days-list">${unique.map(d => {
                const short = String(d || '').trim().toLowerCase();
                const full = dayShortToLong[short] || short;
                return `<li>${esc(dayNames[full] || d)}</li>`;
            }).join('')}</ul>`;
        })() : '—';
        let html = `<div class="tabs" style="margin-bottom:0;"><button class="tab-btn active" onclick="switchInfoTab(this,'info-tab-general')">${t('generalInfo')}</button><button class="tab-btn" onclick="switchInfoTab(this,'info-tab-schedule')">${t('scheduleInfo')}</button><button class="tab-btn" onclick="switchInfoTab(this,'info-tab-versions')">${t('versions')}</button><button class="tab-btn" onclick="switchInfoTab(this,'info-tab-explorer')">${t('fileExplorer')}</button></div>`;
        html += `<div id="info-tab-general" class="tab-panel active" style="padding:16px;">`;
        html += `<div class="info-grid">`;
        html += `<div class="info-section"><h4>${t('generalInfo')}</h4>`;
        html += `<div class="info-row"><span class="info-label">${t('name')}:</span><span class="info-value">${esc(backupDetail?.Name || 'N/A')}</span></div>`;
        html += `<div class="info-row"><span class="info-label">ID:</span><span class="info-value" style="font-family:'JetBrains Mono',monospace;">${esc(String(id))}</span></div>`;
        html += `<div class="info-row"><span class="info-label">${t('status')}:</span><span class="info-value">${statusDot}</span></div>`;
        html += `<div class="info-row" style="flex-direction:column;gap:4px;"><span class="info-label">${t('sourceFolders')}:</span><div>${sources.length ? sources.map(s => `<div style="font-family:'JetBrains Mono',monospace;font-size:11px;padding:3px 6px;background:var(--bg-overlay);border-radius:4px;margin-top:4px;word-break:break-all;">${esc(s)}</div>`).join('') : '—'}</div></div>`;
        html += `</div>`;
        html += `<div class="info-section"><h4>${t('targetInfo')}</h4>`;
        html += `<div class="info-row"><span class="info-label">URL:</span><span class="info-value">${backupDetail?.TargetURL ? formatTargetUrlHtml(backupDetail.TargetURL) : '—'}</span></div>`;
        if (backupDetail?.Metadata) {
            const m = backupDetail.Metadata;
            if (m.TargetFilesCount != null || m.SourceFilesCount != null) html += `<div class="info-row"><span class="info-label">${t('filesCount')}:</span><span class="info-value">${esc(String(m.TargetFilesCount ?? m.SourceFilesCount ?? '—'))}</span></div>`;
            if (m.TargetFilesSize != null || m.SourceSizeString) html += `<div class="info-row"><span class="info-label">${t('size')}:</span><span class="info-value">${m.TargetFilesSize != null ? fmtBytes(Number(m.TargetFilesSize)) : esc(m.SourceSizeString || '—')}</span></div>`;
            html += `<div class="info-row"><span class="info-label">${t('lastRun')}:</span><span class="info-value">${lastRunHtml}</span></div>`;
            if (m.LastBackupDuration) html += `<div class="info-row"><span class="info-label">${t('duration')}:</span><span class="info-value">${formatDuration(m.LastBackupDuration)}</span></div>`;
        }
        html += `</div></div></div>`;
        html += `<div id="info-tab-schedule" class="tab-panel" style="padding:16px;">`;
        if (backupDetail?.Schedule) {
            const sc = backupDetail.Schedule;
            html += `<div class="info-section"><h4>${t('scheduleInfo')}</h4>`;
            if (sc.Time) html += `<div class="info-row"><span class="info-label">${t('nextRun')}:</span><span class="info-value"><span class="lastrun-frame"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>${formatDate(sc.Time)}</span></span></div>`;
            if (sc.Repeat) html += `<div class="info-row"><span class="info-label">${t('repeat')}:</span><span class="info-value">${esc(sc.Repeat)}</span></div>`;
            html += `<div class="info-row" style="flex-direction:column;gap:6px;"><span class="info-label">${t('allowedDays')}:</span>${allowedDaysHtml}</div>`;
            html += `</div>`;
        } else {
            html += `<div class="empty-state"><p>${t('manualOnly')}</p></div>`;
        }
        html += `</div>`;
        html += `<div id="info-tab-versions" class="tab-panel" style="padding:16px;">`;
        if (versions.length === 0) {
            html += `<div class="empty-state"><p>${t('noVersions')}</p></div>`;
        } else {
            html += `<div class="table-wrap"><table class="versions-table"><thead><tr><th>${t('version')}</th><th>${t('time')}</th><th>${t('type')}</th><th>${t('filesCount')}</th><th>${t('size')}</th></tr></thead><tbody>`;
            versions.forEach(v => { const isFull = v.IsFullBackup == 1; html += `<tr><td><span class="badge-version">v${v.Version}</span></td><td>${formatDate(v.Time)}</td><td>${isFull ? t('fullBackup') : t('incrementalBackup')}</td><td>${v.FileCount > 0 ? v.FileCount : '—'}</td><td>${v.FileSizes > 0 ? fmtBytes(v.FileSizes) : '—'}</td></tr>`; });
            html += `</tbody></table></div>`;
        }
        html += `</div>`;
        const destPath = parseTargetPath(backupDetail?.TargetURL || '');
        html += `<div id="info-tab-explorer" class="tab-panel" style="padding:16px;"><div id="infoFbDiskSpace" style="padding:12px 16px;margin-bottom:12px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--bg-overlay);display:none;"></div><div class="fb-breadcrumb" id="infoFbBreadcrumb"></div><div class="fb-tree" id="infoFbTree"><div class="fb-loading">${esc(t('loading'))}</div></div></div>`;
        contentEl.innerHTML = html;
        setTimeout(() => {
            const infoTree = document.getElementById('infoFbTree');
            const infoBc = document.getElementById('infoFbBreadcrumb');
            if (!infoTree) return;
            if (!destPath) { infoTree.innerHTML = `<div class="empty-state"><p>${esc(t('connectToBrowse'))}</p></div>`; if (infoBc) infoBc.innerHTML = ''; return; }
            renderFbTree(infoTree, destPath, infoBc);
            loadDiskSpaceForModal(destPath);
        }, 100);
    } catch(err) { contentEl.innerHTML = `<div class="empty-state"><p style="color:var(--red);">${esc(err.message)}</p></div>`; }
}
function switchInfoTab(btn, tabId) {
    const box = btn.closest('#backupInfoContent');
    box.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    box.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const panel = box.querySelector('#' + tabId);
    if (panel) panel.classList.add('active');
    if (tabId === 'info-tab-explorer') {
        const tree = box.querySelector('#infoFbTree');
        if (tree && tree.dataset.loaded !== '1') { tree.dataset.loaded = '1'; }
    }
}
function handleRowClick(event, id, name) {
    if (event.target.closest('button') || event.target.closest('.actions-dropdown') || event.target.closest('.actions-menu')) return;
    viewBackupDetails(id, name);
}
function formatDate(dateStr) { if (!dateStr) return '—'; try { let s = String(dateStr).trim(); const compact = /^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z?$/.exec(s); if (compact) s = `${compact[1]}-${compact[2]}-${compact[3]}T${compact[4]}:${compact[5]}:${compact[6]}Z`; const d = new Date(s); if (isNaN(d.getTime())) return dateStr; return d.toLocaleString(); } catch(e) { return dateStr; } }
function computeDerivedProgressSpeed(ps) {
    if (!ps || ps.ProcessedFileSize == null) return null;
    const taskId = ps.TaskID ?? ps.BackupID ?? null;
    const now = Date.now();
    let derived = null;
    if (lastProgressSnapshot && lastProgressSnapshot.taskId === taskId) {
        const deltaBytes = Number(ps.ProcessedFileSize) - Number(lastProgressSnapshot.processedSize);
        const deltaMs = now - lastProgressSnapshot.at;
        if (deltaBytes > 0 && deltaMs > 250) derived = deltaBytes / (deltaMs / 1000);
    }
    lastProgressSnapshot = { taskId, processedSize: Number(ps.ProcessedFileSize) || 0, at: now };
    return derived;
}
function resolveProgressSpeedBytes(ps) {
    const backendSpeed = Number(ps?.BackendSpeed);
    if (Number.isFinite(backendSpeed) && backendSpeed > 0) return backendSpeed;
    const derived = Number(ps?.DerivedSpeed);
    if (Number.isFinite(derived) && derived > 0) return derived;
    return null;
}
function formatProgressSpeed(ps) {
    const value = resolveProgressSpeedBytes(ps);
    return value != null ? `${fmtBytes(value)}/s` : '—';
}
function isProgressPhaseActive(ps) {
    if (!ps || typeof ps !== 'object') return false;
    const phase = String(ps.Phase || ps.BackupPhase || '').trim();
    if (!phase || phase === 'Backup_Complete') return false;
    const overall = Number(ps.OverallProgress ?? ps.Progress ?? ps.OverallPercent);
    if (Number.isFinite(overall) && overall >= 1) return false;
    return true;
}
function resolveActiveTaskBackupId(serverState, ps) {
    const task = serverState?.ActiveTask;
    if (task) {
        if (task.BackupID != null && task.BackupID !== '') return task.BackupID;
        if (task.Item2 != null && task.Item2 !== '') return task.Item2;
    }
    if (isProgressPhaseActive(ps) && ps?.BackupID != null && ps.BackupID !== '') return ps.BackupID;
    return null;
}
function isBackupTaskRunning(serverState, ps) {
    return resolveActiveTaskBackupId(serverState, ps) != null;
}
function translatePhase(phase) {
    const raw = String(phase || '').trim();
    if (!raw) return t('statusRunning');
    const key = 'phase_' + raw;
    const translated = t(key);
    return translated !== key ? translated : raw.replace(/_/g, ' ');
}
function translateJobStatus(status, phase) {
    const st = String(status || '').trim();
    if (st === 'Running') return translatePhase(phase);
    const map = { Success: 'statusSuccess', Error: 'statusError', Warning: 'statusWarning', Idle: 'idleStatus' };
    return map[st] ? t(map[st]) : st;
}
function renderJobStatusBadge(b, isRunning, phaseRaw) {
    let badgeClass = 'badge-neutral';
    if (isRunning) badgeClass = 'badge-running';
    else if (b.Status === 'Success') badgeClass = 'badge-success';
    else if (b.Status === 'Error') badgeClass = 'badge-error';
    else if (b.Status === 'Warning') badgeClass = 'badge-warning';
    else if (b.Status === 'Idle') badgeClass = 'badge-idle';
    return `<span class="badge ${badgeClass}">${esc(translateJobStatus(isRunning ? 'Running' : b.Status, phaseRaw))}</span>`;
}
function calculateBackendTransferRatio(ps) {
    const transfers = Array.isArray(ps?.ActiveTransfers) ? ps.ActiveTransfers : [];
    if (transfers.length) {
        let totalSize = 0, totalProgress = 0;
        for (const tr of transfers) {
            const size = Number(tr.BackendFileSize) || 0;
            const progress = Number(tr.BackendFileProgress) || 0;
            if (size > 0) { totalSize += size; totalProgress += Math.min(progress, size); }
        }
        if (totalSize > 0) return totalProgress / totalSize;
    }
    const backendSize = Number(ps?.BackendFileSize) || 0;
    const backendProgress = Number(ps?.BackendFileProgress) || 0;
    if (backendSize > 0) return Math.min(1, backendProgress / backendSize);
    return null;
}
function formatStorageSizeLabel(bytes, stringFallback) {
    if (stringFallback) return String(stringFallback);
    if (bytes != null && Number.isFinite(Number(bytes)) && Number(bytes) >= 0) return fmtBytes(Number(bytes));
    return '—';
}
function getProgressStorageSizes(backupId, ps) {
    const backup = backupsList.find(b => String(b.ID) === String(backupId));
    const meta = backup?.Metadata || {};
    const totalSrc = Number(ps?.TotalFileSize) || Number(meta.SourceFilesSize) || 0;
    const procSrc = Number(ps?.ProcessedFileSize) || 0;
    const sourceBytes = procSrc > 0 ? procSrc : totalSrc;
    const sourceSize = formatStorageSizeLabel(sourceBytes > 0 ? sourceBytes : null, meta.SourceSizeString);
    let destBytes = Number(meta.TargetFilesSize) || 0;
    const transfers = Array.isArray(ps?.ActiveTransfers) ? ps.ActiveTransfers : [];
    if (transfers.length) {
        const uploadBytes = transfers.reduce((sum, tr) => sum + (Number(tr.BackendFileProgress) || 0), 0);
        if (uploadBytes > 0) destBytes = Math.max(destBytes, uploadBytes);
    } else {
        const backendProg = Number(ps?.BackendFileProgress) || 0;
        if (backendProg > 0) destBytes = Math.max(destBytes, backendProg);
    }
    const destSize = formatStorageSizeLabel(destBytes > 0 ? destBytes : null, meta.TargetSizeString);
    return { sourceSize, destSize };
}
function calculateProgressPercent(ps) {
    if (!ps || typeof ps !== 'object') return null;
    const phase = String(ps.Phase || ps.BackupPhase || '').trim();
    if (phase === 'Backup_Complete') return 100;
    const postUploadPhases = ['Backup_Finalize', 'Backup_WaitForUpload', 'Backup_Delete', 'Backup_Compact', 'Backup_VerificationUpload', 'Backup_PostBackupVerify'];
    if (postUploadPhases.includes(phase)) {
        const ratio = calculateBackendTransferRatio(ps);
        const base = 0.6666667;
        if (ratio != null) return Math.min(99, Math.max(Math.round(base * 100), Math.round((base + ratio * (1 - base)) * 100)));
        const totalFiles = Number(ps.TotalFileCount) || 0;
        const processedFiles = Number(ps.ProcessedFileCount) || 0;
        if (totalFiles > 0 && processedFiles >= totalFiles) return Math.min(99, Math.round(base * 100));
    }
    const overallRaw = ps.OverallProgress ?? ps.Progress ?? ps.OverallPercent;
    const overall = Number(overallRaw);
    const hasOverall = overallRaw != null && overallRaw !== '' && Number.isFinite(overall);
    if (hasOverall && overall >= 1) return 100;
    const backendSize = Number(ps.BackendFileSize) || 0;
    const backendProgress = Number(ps.BackendFileProgress) || 0;
    if (hasOverall && overall > 0) {
        const base = overall <= 1 ? overall : overall / 100;
        let value = base;
        if (backendSize > 0 && backendProgress >= 0) {
            const slice = Math.max(0, 1 - base);
            value = base + (backendProgress / backendSize) * slice;
        }
        return Math.min(100, Math.max(0, Math.round(value * 100)));
    }
    const totalSize = Number(ps.TotalFileSize) || 0;
    const processedSize = Number(ps.ProcessedFileSize) || 0;
    if (totalSize > 0 && processedSize >= 0) {
        return Math.min(99, Math.max(0, Math.round((processedSize / totalSize) * 66.67)));
    }
    const totalFiles = Number(ps.TotalFileCount) || 0;
    const processedFiles = Number(ps.ProcessedFileCount) || 0;
    if (totalFiles > 0 && processedFiles >= 0) {
        return Math.min(99, Math.max(0, Math.round((processedFiles / totalFiles) * 66.67)));
    }
    const currentSize = Number(ps.CurrentFilesize) || 0;
    const currentOffset = Number(ps.CurrentFileoffset) || 0;
    if (currentSize > 0 && currentOffset >= 0) {
        return Math.min(99, Math.max(0, Math.round((currentOffset / currentSize) * 66.67)));
    }
    if (phase === 'Starting_Operation') return 0;
    return null;
}
function buildProgressCellHtml(isRunning, ps) {
    if (!isRunning) return `<span style="color:var(--text-muted);font-size:11px;">—</span>`;
    const pct = calculateProgressPercent(ps);
    if (pct !== null) {
        return `<div style="display:flex;align-items:center;gap:8px;"><div class="progress-track" style="flex:1;min-width:60px;"><div class="progress-fill" style="width:${pct}%"></div></div><span style="font-size:10px;color:var(--accent);white-space:nowrap;">${pct}%</span></div>`;
    }
    return `<div class="progress-track"><div class="progress-fill" style="width:100%;animation:indeterminate 1.5s infinite;"></div></div>`;
}
function syncFastPollTimer(shouldRun) {
    if (shouldRun) {
        if (!fastPollTimer) fastPollTimer = setInterval(() => { loadProgressState(); }, 1500);
    } else if (fastPollTimer) {
        clearInterval(fastPollTimer);
        fastPollTimer = null;
    }
}
function renderActiveProgressPanel() {
    const activeTaskBackupId = resolveActiveTaskBackupId(lastServerState, progressState);
    const activePanel = document.getElementById('activeProgressPanel');
    if (activeTaskBackupId && isProgressPhaseActive(progressState)) {
        const ps = progressState;
        const phaseLabel = translatePhase(ps.Phase ?? ps.BackupPhase ?? '');
        const pct = calculateProgressPercent(ps);
        const speed = formatProgressSpeed(ps);
        const proc = ps.ProcessedFileCount != null ? ps.ProcessedFileCount : '—';
        const procSz = ps.ProcessedFileSize != null ? fmtBytes(ps.ProcessedFileSize) : '—';
        const elapsed = ps.ElapsedDuration ?? '';
        const sizes = getProgressStorageSizes(activeTaskBackupId, ps);
        activePanel.style.display = 'block';
        activePanel.innerHTML = `<div style="margin-bottom:12px;background:var(--bg-overlay);border:1px solid var(--border);border-radius:var(--radius-md);padding:14px;"><div class="progress-storage-sizes"><div><strong>${esc(t('sourceSizeLabel'))}:</strong> <span>${esc(sizes.sourceSize)}</span></div><div><strong>${esc(t('destinationSizeLabel'))}:</strong> <span>${esc(sizes.destSize)}</span></div></div><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;"><span style="font-size:12px;font-weight:600;color:var(--accent);">${esc(phaseLabel)}</span>${pct !== null ? `<span style="font-size:12px;font-weight:700;color:var(--green);font-family:'JetBrains Mono',monospace;">${pct}%</span>` : ''}</div><div class="progress-track progress-shimmer" style="height:10px;"><div class="progress-fill" style="width:${pct !== null ? pct : 100}%;${pct === null ? 'animation:indeterminate 1.5s infinite;' : ''}"></div></div></div><div class="progress-info"><div class="pi-item"><div class="pi-label">${t('phase')}</div><div class="pi-value">${esc(phaseLabel)}</div></div><div class="pi-item"><div class="pi-label">${t('speed')}</div><div class="pi-value">${esc(speed)}</div></div><div class="pi-item"><div class="pi-label">${t('processed')} ${t('files')}</div><div class="pi-value">${proc}</div></div><div class="pi-item"><div class="pi-label">${t('processed')} ${t('bytes')}</div><div class="pi-value">${procSz}</div></div><div class="pi-item"><div class="pi-label">${t('elapsed')}</div><div class="pi-value">${esc(elapsed||'—')}</div></div></div>`;
    } else {
        activePanel.style.display = 'none';
        activePanel.innerHTML = '';
    }
}
function updateProgressUI() {
    renderActiveProgressPanel();
    const activeTaskBackupId = resolveActiveTaskBackupId(lastServerState, progressState);
    backupsList.forEach(b => {
        const tr = document.querySelector(`tr[data-backup-id="${b.ID}"]`);
        if (!tr) return;
        const isRunning = activeTaskBackupId != null && String(b.ID) === String(activeTaskBackupId) && isProgressPhaseActive(progressState);
        const phase = progressState?.Phase ?? progressState?.BackupPhase ?? '';
        const tdProg = tr.querySelector('td[data-col="progress"]');
        const tdStat = tr.querySelector('td[data-col="status"]');
        if (tdProg) tdProg.innerHTML = buildProgressCellHtml(isRunning, progressState);
        if (tdStat) tdStat.innerHTML = renderJobStatusBadge(b, isRunning, phase);
    });
}
function renderBackupsTable() {
    if (!backupsList.length) {
        document.getElementById('backupsTableBody').innerHTML = `<tr><td colspan="6"><div class="empty-state"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/></svg><p>${t('connectToLoad')}</p></div></td></tr>`;
        return;
    }
    renderActiveProgressPanel();
    const activeTaskBackupId = resolveActiveTaskBackupId(lastServerState, progressState);
    document.getElementById('backupsTableBody').innerHTML = backupsList.map(b => {
        const isRunning = activeTaskBackupId != null && String(b.ID) === String(activeTaskBackupId) && isProgressPhaseActive(progressState);
        const phase = progressState?.Phase ?? progressState?.BackupPhase ?? '';
        const progressHtml = buildProgressCellHtml(isRunning, progressState);
        const schedule = b.Schedule?.Repeat ? `<span style="font-size:11px;">${esc(b.Schedule.Repeat)}</span>` : `<span style="font-size:11px;color:var(--text-muted);">${t('manualOnly')}</span>`;
        const decodedTarget = (() => { try { return decodeURIComponent(b.TargetURL||''); } catch(_){ return b.TargetURL||''; } })();
        const targetDisplay = decodedTarget ? formatTargetUrlHtml(decodedTarget) : `<span style="color:var(--text-muted);">—</span>`;
        const lastRun = b.Metadata?.LastBackupDate ? `<div style="font-size:10px;color:var(--text-muted);margin-top:2px;">${new Date(b.Metadata.LastBackupDate).toLocaleString()}</div>` : '';
        const hn = esc(t('name')), hs = esc(t('status')), hp = esc(t('progress')), hsc = esc(t('schedule')), ht = esc(t('target')), ha = esc(t('actions'));
        return `<tr data-backup-id="${esc(String(b.ID))}" style="cursor:pointer;" onclick="handleRowClick(event,${b.ID},'${esc(b.Name).replace(/'/g,"\\'")}')"><td data-col="name" data-h="${hn}"><div class="backup-name">${esc(b.Name)}</div><div class="backup-id">ID: ${b.ID}</div>${lastRun}</td><td data-col="status" data-h="${hs}">${renderJobStatusBadge(b, isRunning, phase)}</td><td data-col="progress" data-h="${hp}">${progressHtml}</td><td data-col="schedule" data-h="${hsc}">${schedule}</td><td data-col="target" data-h="${ht}">${targetDisplay}</td><td data-col="actions" data-h="${ha}" onclick="event.stopPropagation()"><div class="row-actions">${isRunning ? `<button class="action-btn ab-stop" onclick="abortBackup(${b.ID})"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>${t('stop')}</button>` : `<button class="action-btn ab-run" onclick="runBackup(${b.ID})"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>${t('run')}</button>`}<div class="actions-dropdown"><button type="button" class="action-btn ab-more" onclick="toggleBackupActionsMenu(event,this)"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>${t('moreActions')}</button><ul class="actions-menu" role="menu" onclick="event.stopPropagation()"><li><button type="button" role="menuitem" onclick="closeAllBackupActionMenus();editBackup(${b.ID})"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>${t('edit')}</button></li><li><button type="button" role="menuitem" onclick="closeAllBackupActionMenus();verifyBackup(${b.ID})"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>${t('verify')}</button></li><li><button type="button" role="menuitem" onclick="closeAllBackupActionMenus();viewBackupDetails(${b.ID},'${esc(b.Name).replace(/'/g,"\\'")}')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>${t('viewInfo')}</button></li><li><button type="button" role="menuitem" onclick="closeAllBackupActionMenus();openLogsModal(${b.ID},'${esc(b.Name).replace(/'/g,"\\'")}')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 14v2.2l1.6 1"/><path d="M16 4h2a2 2 0 0 1 2 2v.832"/><path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h2"/><circle cx="16" cy="16" r="6"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>${t('logsShort')}</button></li><li><button type="button" role="menuitem" class="danger" onclick="closeAllBackupActionMenus();deleteBackup(${b.ID})"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>${t('delete')}</button></li></ul></div></div></td></tr>`;
    }).join('');
}
async function runBackup(id) { loading(true, t('loading')); try { await apiCall(`backup/${id}/run`, 'POST'); toast(t('run'), 'success'); await loadProgressState(); setTimeout(async () => { await loadProgressState(); await loadBackups(); }, 2000); } catch(err) { toast(err.message, 'error'); } finally { loading(false); } }
async function abortBackup(id) {
    loading(true, t('loading'));
    try {
        let taskId = backupsList.find(x => String(x.ID) === String(id))?.ActiveRunId ?? null;
        if (taskId == null) {
            const state = await apiCall('serverstate');
            if (state?.ActiveTask?.Item2 != null && String(state.ActiveTask.Item2) === String(id)) {
                taskId = state.ActiveTask.Item1 ?? null;
            }
            if (taskId == null && state?.ActiveTask?.BackupID != null && String(state.ActiveTask.BackupID) === String(id)) {
                taskId = state.ActiveTask.TaskID ?? state.ActiveTask.Item1 ?? null;
            }
        }
        if (taskId == null && progressState?.BackupID != null && String(progressState.BackupID) === String(id)) {
            taskId = progressState.TaskID ?? null;
        }
        if (taskId == null) throw new Error('No active task found for this backup');
        await apiCall(`task/${taskId}/stop`, 'POST');
        toast('Stopped', 'warning');
        setTimeout(refreshData, 1500);
    } catch(err) {
        toast(err.message, 'error');
    } finally {
        loading(false);
    }
}
async function verifyBackup(id) { loading(true, t('verifying')); try { await apiCall(`backup/${id}/verify`, 'POST'); toast(t('verifyDone'), 'success'); setTimeout(refreshData, 2000); } catch(err) { toast(err.message, 'error'); } finally { loading(false); } }
async function deleteBackup(id) {
    const b = backupsList.find(x => String(x.ID) === String(id));
    const confirmed = await openConfirmActionModal('Delete backup', t('deleteConfirm', { name: b?.Name || id }), true);
    if (!confirmed) return;
    loading(true, t('loading'));
    try {
        await apiCall(`backup/${id}`, 'DELETE');
        toast(t('delete'), 'success');
        await loadBackups();
    } catch(err) {
        toast(err.message, 'error');
    } finally {
        loading(false);
    }
}
let editSourceSelected = new Set();
let editDestSelectedPath = '';
let editBackupSettings = [];
let editBackupDescription = '';
const editWizardStepOrder = ['General', 'Destination', 'Source Data', 'Schedule', 'Options'];
let editWizardStep = 0;
let editSourceDraft = '';
let editBackupSources = [];
function decodeBackupSourceEntry(entry) {
    if (entry == null || entry === '') return '';
    if (typeof entry === 'string') {
        let s = entry.trim();
        try { s = decodeURIComponent(s); } catch (_) {}
        if (/^file:/i.test(s)) {
            const parsed = parseTargetPath(s);
            if (parsed) return containerPathToHost(parsed);
        }
        return containerPathToHost(normalizeFilesystemPath(s) || s);
    }
    if (typeof entry === 'object') {
        const candidate = entry.Path ?? entry.path ?? entry.Source ?? entry.source ?? entry.Uri ?? entry.uri ?? entry.Value ?? entry.value ?? '';
        return decodeBackupSourceEntry(candidate);
    }
    return '';
}
function decodeBackupSourcesForEdit(list) {
    const out = [];
    (Array.isArray(list) ? list : []).forEach(item => {
        if (typeof item === 'string' && item.includes(';')) {
            splitPathList(item).forEach(part => {
                const path = decodeBackupSourceEntry(part);
                if (path) out.push(path);
            });
            return;
        }
        const path = decodeBackupSourceEntry(item);
        if (path) out.push(path);
    });
    return [...new Set(out)];
}
function getEditSourcePathsRaw() {
    const inp = document.getElementById('editSourcePath');
    const fieldVal = inp?.value?.trim() || '';
    if (fieldVal) return fieldVal;
    if (editSourceDraft) return editSourceDraft;
    if (editSourceSelected.size) return [...editSourceSelected].join(';');
    const fromBackup = decodeBackupSourcesForEdit(editBackupSources);
    return fromBackup.length ? fromBackup.join(';') : '';
}
function applyEditSourceFields(sources, force = false) {
    const paths = decodeBackupSourcesForEdit(sources);
    editBackupSources = Array.isArray(sources) ? sources.slice() : [];
    if (!paths.length && !force) return;
    editSourceDraft = paths.join(';');
    editSourceSelected = new Set(paths);
    const inp = document.getElementById('editSourcePath');
    if (inp) inp.value = editSourceDraft;
}
function ensureEditSourceFields() {
    const inp = document.getElementById('editSourcePath');
    const raw = getEditSourcePathsRaw();
    if (!raw) return;
    editSourceDraft = raw;
    replaceSelectedSetFromInput(editSourceSelected, raw);
    if (inp && !inp.value.trim()) inp.value = raw;
}
function syncEditSourcePathInput() {
    const inp = document.getElementById('editSourcePath');
    if (!inp) return;
    const joined = [...editSourceSelected].join(';');
    if (joined) {
        inp.value = joined;
        editSourceDraft = joined;
    } else {
        const fallback = getEditSourcePathsRaw();
        if (fallback) {
            inp.value = fallback;
            editSourceDraft = fallback;
        }
    }
    setFieldError('editSourcePathError', false);
}
function syncEditDestPathInput() {
    const inp = document.getElementById('editTargetUrl');
    if (!inp) return;
    if (editDestSelectedPath) inp.value = containerPathToHost(editDestSelectedPath);
    setFieldError('editTargetUrlError', false);
}
function normalizeHostPathList(raw) {
    return splitPathList(raw).map(p => containerPathToHost(p)).filter(Boolean);
}
function seedDestSelectedSet(selectedSet, rawPath) {
    selectedSet.clear();
    const hostPath = containerPathToHost(normalizeFilesystemPath(parseTargetPath(rawPath) || rawPath));
    if (hostPath) selectedSetAddPath(selectedSet, hostPath);
    return hostPath;
}
function initEditSourceTree(preSelected) {
    const root = document.getElementById('editSourceTreeRoot'); if (!root) return;
    ensureEditSourceFields();
    let raw = '';
    if (Array.isArray(preSelected)) raw = decodeBackupSourcesForEdit(preSelected).join(';');
    else if (typeof preSelected === 'string') raw = normalizeHostPathList(preSelected).join(';');
    else raw = getEditSourcePathsRaw();
    if (raw) {
        editSourceDraft = raw;
        const inp = document.getElementById('editSourcePath');
        if (inp) inp.value = raw;
    }
    editSourceSelected = new Set();
    if (raw) replaceSelectedSetFromInput(editSourceSelected, raw);
    const anchor = getTreePanelRememberedPath('editSource') || getBrowseAnchorForSourceField(raw);
    setTreePanelCurrentPath('editSource', anchor);
    root.innerHTML = '';
    renderFilesystemNodes(root, anchor, 0, editSourceSelected, syncEditSourcePathInput, true).then(() => { const sf = document.getElementById('editSourceTreeFilter'); if (sf) applyFsTreeFilter(root, sf.value); }).catch(err => { root.innerHTML = `<div class="source-tree-hint" style="color:var(--red);">${esc(err.message)}</div>`; });
}
function initEditDestTree(preSelectedPath) {
    const root = document.getElementById('editDestTreeRoot'); if (!root) return;
    const tf = document.getElementById('editTargetUrl');
    if (preSelectedPath !== undefined) {
        editDestSelectedPath = containerPathToHost(normalizeFilesystemPath(parseTargetPath(preSelectedPath) || preSelectedPath)) || String(preSelectedPath || '').trim();
    }
    const raw = editDestSelectedPath || tf?.value?.trim() || '';
    if (raw && !editDestSelectedPath) editDestSelectedPath = containerPathToHost(normalizeFilesystemPath(parseTargetPath(raw) || raw)) || raw;
    if (tf && editDestSelectedPath && !tf.value.trim()) tf.value = containerPathToHost(editDestSelectedPath);
    const anchor = getTreePanelRememberedPath('editDest') || getBrowseAnchorForDestField(raw || editDestSelectedPath);
    setTreePanelCurrentPath('editDest', anchor);
    updateDestExplorerUi('editDest');
    root.innerHTML = '';
    const selectedSet = new Set();
    const seeded = seedDestSelectedSet(selectedSet, editDestSelectedPath || raw);
    if (seeded) editDestSelectedPath = seeded;
    renderFilesystemNodes(root, anchor, 0, selectedSet, () => {
        editDestSelectedPath = [...selectedSet][0] || editDestSelectedPath;
        syncEditDestPathInput();
    }, false, true).then(() => { const sf = document.getElementById('editDestTreeFilter'); if (sf) applyFsTreeFilter(root, sf.value); }).catch(err => { root.innerHTML = `<div class="source-tree-hint" style="color:var(--red);">${esc(err.message)}</div>`; });
}
function updateEditSchedulePreview() {
    const enabled = document.getElementById('editScheduleEnabled')?.checked;
    const every = Math.max(1, parseInt(document.getElementById('editScheduleEvery')?.value || '1', 10) || 1);
    const unit = document.getElementById('editScheduleUnit')?.value || 'D';
    const time = document.getElementById('editScheduleTime')?.value || '13:00';
    const label = document.getElementById('editSchedulePreviewLabel');
    const sub = document.getElementById('editSchedulePreviewSub');
    if (!label || !sub) return;
    if (!enabled) {
        label.textContent = 'Manual only';
        sub.textContent = 'Runs only when started manually';
        return;
    }
    label.textContent = every === 1 ? (unit === 'D' ? 'Daily' : 'Hourly') : `Every ${every} ${unit === 'D' ? 'days' : 'hours'}`;
    sub.textContent = time;
}
function toggleEditScheduleFields() {
    const enabled = document.getElementById('editScheduleEnabled')?.checked;
    const wrap = document.getElementById('editScheduleFieldsWrap');
    if (!wrap) return;
    wrap.style.opacity = enabled ? '1' : '.55';
    wrap.querySelectorAll('input, select').forEach(el => { el.disabled = !enabled; });
    updateEditSchedulePreview();
}
function toggleEditRetentionInputs() {
    const custom = document.querySelector('input[name="editBackupRetentionMode"]:checked')?.value === 'delete-older';
    const days = document.getElementById('editRetentionDays');
    const unit = document.getElementById('editRetentionUnit');
    if (days) days.disabled = !custom;
    if (unit) unit.disabled = !custom;
}
function updateEditWizardButtons() {
    const backBtn = document.getElementById('editWizardBackBtn');
    const submitBtn = document.getElementById('editWizardSubmitBtn');
    if (backBtn) backBtn.style.visibility = editWizardStep === 0 ? 'hidden' : 'visible';
    if (submitBtn) submitBtn.style.display = editWizardStep === editWizardStepOrder.length - 1 ? '' : 'none';
}
function renderEditWizardStep() {
    document.querySelectorAll('#editBackupForm .wizard-panel').forEach((panel, idx) => panel.classList.toggle('active', idx === editWizardStep));
    document.querySelectorAll('#editBackupForm .wizard-step').forEach((btn, idx) => {
        btn.classList.toggle('active', idx === editWizardStep);
        btn.classList.toggle('completed', idx < editWizardStep);
    });
    const stepsWrap = document.getElementById('editWizardSteps');
    if (stepsWrap) {
        const progress = editWizardStepOrder.length > 1 ? (editWizardStep / (editWizardStepOrder.length - 1)) * 100 : 0;
        const ratio = editWizardStepOrder.length > 1 ? (editWizardStep / (editWizardStepOrder.length - 1)) : 0;
        stepsWrap.style.setProperty('--wizard-progress', `${progress}%`);
        stepsWrap.style.setProperty('--wizard-progress-ratio', `${ratio}`);
    }
    updateEditWizardButtons();
    if (editWizardStep === 1) initEditDestTree();
    if (editWizardStep === 2) {
        ensureEditSourceFields();
        initEditSourceTree();
    }
}
function validateEditWizardStep(stepIndex) {
    let valid = true;
    if (stepIndex === 0) {
        const name = document.getElementById('editBackupName')?.value.trim() || '';
        setFieldError('editBackupNameError', !name);
        valid = !!name;
    } else if (stepIndex === 1) {
        const target = document.getElementById('editTargetUrl')?.value.trim() || '';
        setFieldError('editTargetUrlError', !target);
        valid = !!target;
    } else if (stepIndex === 2) {
        const sources = splitPathList(getEditSourcePathsRaw());
        setFieldError('editSourcePathError', sources.length === 0);
        valid = sources.length > 0;
    }
    return valid;
}
function gotoEditWizardStep(stepIndex, force = false) {
    const nextIndex = Math.max(0, Math.min(editWizardStepOrder.length - 1, stepIndex));
    if (!force && nextIndex > editWizardStep && !validateEditWizardStep(editWizardStep)) return false;
    editWizardStep = nextIndex;
    renderEditWizardStep();
    return true;
}
function buildAllowedDaysDataForGrid(gridId) {
    const checked = Array.from(document.querySelectorAll(`#${gridId} input[type="checkbox"]:checked`)).map(el => el.value);
    const shortMap = { Monday: 'mon', Tuesday: 'tue', Wednesday: 'wed', Thursday: 'thu', Friday: 'fri', Saturday: 'sat', Sunday: 'sun' };
    return {
        long: checked,
        short: checked.map(day => shortMap[day]).filter(Boolean),
        rule: checked.length ? `AllowedWeekDays=${checked.join(',')}` : '',
    };
}
function getBackupSettingValue(settings, name) {
    const key = String(name || '').toLowerCase();
    const row = Array.isArray(settings) ? settings.find(item => String(item?.Name || '').toLowerCase() === key) : null;
    return row ? String(row.Value ?? '') : '';
}
function parseIntervalValue(raw, fallbackNumber = 1, fallbackUnit = 'D') {
    const m = String(raw || '').trim().match(/^(\d+)([A-Za-z]+)$/);
    if (!m) return { every: fallbackNumber, unit: fallbackUnit };
    return { every: Math.max(1, parseInt(m[1], 10) || fallbackNumber), unit: m[2] || fallbackUnit };
}
function parseNumericSetting(raw, allowedUnits, fallbackValue, fallbackUnit) {
    const m = String(raw || '').trim().match(/^(\d+)\s*([A-Za-z]+)$/);
    if (!m) return { value: fallbackValue, unit: fallbackUnit };
    const unit = (m[2] || '').toUpperCase();
    return {
        value: Math.max(1, parseInt(m[1], 10) || fallbackValue),
        unit: allowedUnits.includes(unit) ? unit : fallbackUnit,
    };
}
function resetEditWizardForm() {
    const form = document.getElementById('editBackupForm');
    if (form) form.reset();
    document.getElementById('editBackupId').value = '';
    document.getElementById('editBackupDescription').value = '';
    document.getElementById('editScheduleEnabled').checked = true;
    document.getElementById('editScheduleTime').value = '13:00';
    document.getElementById('editScheduleEvery').value = '1';
    document.getElementById('editScheduleUnit').value = 'D';
    document.querySelectorAll('#editAllowedDaysGrid input[type="checkbox"]').forEach(el => { el.checked = true; });
    document.querySelector('input[name="editBackupRetentionMode"][value="keep-all"]').checked = true;
    document.getElementById('editRetentionDays').value = '30';
    document.getElementById('editRetentionUnit').value = 'D';
    document.getElementById('editVolumeSize').value = '50';
    document.getElementById('editVolumeSizeUnit').value = 'MB';
    document.getElementById('editAdvancedOptionsPanel').hidden = true;
    document.getElementById('editSourcePath').value = '';
    document.getElementById('editTargetUrl').value = '';
    document.getElementById('editDestRwTestResult').textContent = '';
    editSourceSelected = new Set();
    editSourceDraft = '';
    editBackupSources = [];
    editDestSelectedPath = '';
    treePanelAnchors.editSource = '/';
    treePanelAnchors.editDest = '/';
    const editSourceRoot = document.getElementById('editSourceTreeRoot');
    const editDestRoot = document.getElementById('editDestTreeRoot');
    if (editSourceRoot) delete editSourceRoot.dataset.currentPath;
    if (editDestRoot) delete editDestRoot.dataset.currentPath;
    ['editBackupNameError', 'editTargetUrlError', 'editSourcePathError'].forEach(id => setFieldError(id, false));
    editWizardStep = 0;
    toggleEditScheduleFields();
    toggleEditRetentionInputs();
    updateEditSchedulePreview();
    renderEditWizardStep();
}
function populateEditScheduleFields(schedule) {
    const enabled = !!(schedule && (schedule.Time || schedule.Repeat || (Array.isArray(schedule.AllowedDays) && schedule.AllowedDays.length)));
    document.getElementById('editScheduleEnabled').checked = enabled;
    const repeat = parseIntervalValue(schedule?.Repeat || '1D');
    document.getElementById('editScheduleEvery').value = String(repeat.every);
    document.getElementById('editScheduleUnit').value = repeat.unit === 'h' ? 'h' : 'D';
    let timeValue = '13:00';
    if (schedule?.Time) {
        const dt = new Date(schedule.Time);
        if (!Number.isNaN(dt.getTime())) {
            timeValue = `${String(dt.getHours()).padStart(2, '0')}:${String(dt.getMinutes()).padStart(2, '0')}`;
        }
    }
    document.getElementById('editScheduleTime').value = timeValue;
    const allowed = new Set((Array.isArray(schedule?.AllowedDays) ? schedule.AllowedDays : []).map(v => String(v || '').trim().toLowerCase()));
    const map = { monday: ['monday', 'mon'], tuesday: ['tuesday', 'tue'], wednesday: ['wednesday', 'wed'], thursday: ['thursday', 'thu'], friday: ['friday', 'fri'], saturday: ['saturday', 'sat'], sunday: ['sunday', 'sun'] };
    const hasAllowed = allowed.size > 0;
    document.querySelectorAll('#editAllowedDaysGrid input[type="checkbox"]').forEach(el => {
        const key = String(el.value || '').trim().toLowerCase();
        el.checked = !hasAllowed || (map[key] || [key]).some(v => allowed.has(v));
    });
    toggleEditScheduleFields();
}
function populateEditOptionFields(settings) {
    const keepTimeRaw = getBackupSettingValue(settings, 'keep-time');
    const keepTime = parseNumericSetting(keepTimeRaw, ['D'], 30, 'D');
    document.querySelector(`input[name="editBackupRetentionMode"][value="${keepTimeRaw !== '' ? 'delete-older' : 'keep-all'}"]`).checked = true;
    document.getElementById('editRetentionDays').value = String(keepTime.value);
    document.getElementById('editRetentionUnit').value = keepTime.unit;
    const dblockRaw = getBackupSettingValue(settings, 'dblock-size');
    const dblock = parseNumericSetting(dblockRaw, ['MB', 'GB'], 50, 'MB');
    document.getElementById('editVolumeSize').value = String(dblock.value);
    document.getElementById('editVolumeSizeUnit').value = dblock.unit;
    document.getElementById('editAdvancedOptionsPanel').hidden = dblockRaw === '';
    toggleEditRetentionInputs();
}
async function editBackup(id) {
    const b = backupsList.find(x => String(x.ID) === String(id)); if (!b) return;
    resetEditWizardForm();
    editBackupSettings = [];
    editBackupDescription = b.Description || '';
    let editSchedule = b.Schedule || null;
    let sourceList = Array.isArray(b.Sources) ? b.Sources.slice() : [];
    let targetUrl = b.TargetURL || '';
    let detail = null;
    try {
        detail = await apiCall(`backup/${id}`);
        const detailBackup = detail?.Backup || detail?.backup || (detail?.ID ? detail : null);
        if (detailBackup) {
            editBackupSettings = Array.isArray(detailBackup.Settings) ? detailBackup.Settings : [];
            editBackupDescription = detailBackup.Description || editBackupDescription;
            if (Array.isArray(detailBackup.Sources)) sourceList = detailBackup.Sources.slice();
            if (detailBackup.TargetURL) targetUrl = detailBackup.TargetURL;
        }
        if (detail?.Schedule !== undefined) editSchedule = detail.Schedule;
    } catch (err) {
        console.warn('editBackup details:', err?.message || err);
    }
    document.getElementById('editBackupId').value = b.ID;
    document.getElementById('editBackupName').value = b.Name;
    document.getElementById('editBackupDescription').value = editBackupDescription;
    applyEditSourceFields(sourceList, true);
    const decodedTarget = (() => { try { return formatTargetUrlDisplayParts(decodeURIComponent(targetUrl || '')).path || (targetUrl || ''); } catch(_) { return formatTargetUrlDisplayParts(targetUrl || '').path || (targetUrl || ''); } })();
    document.getElementById('editTargetUrl').value = decodedTarget;
    editDestSelectedPath = containerPathToHost(normalizeFilesystemPath(parseTargetPath(decodedTarget) || decodedTarget)) || decodedTarget;
    populateEditScheduleFields(editSchedule);
    populateEditOptionFields(editBackupSettings);
    showModal('editModal');
    renderEditWizardStep();
}
document.getElementById('editBackupForm').addEventListener('submit', async e => {
    e.preventDefault();
    for (let stepIndex = 0; stepIndex <= 2; stepIndex++) {
        if (!validateEditWizardStep(stepIndex)) {
            editWizardStep = stepIndex;
            renderEditWizardStep();
            return;
        }
    }
    const id = document.getElementById('editBackupId').value;
    const settings = (Array.isArray(editBackupSettings) ? editBackupSettings : []).filter(item => {
        const key = String(item?.Name || '').toLowerCase();
        return key !== 'keep-time' && key !== 'dblock-size';
    });
    if (document.querySelector('input[name="editBackupRetentionMode"]:checked')?.value === 'delete-older') {
        const retentionDays = Math.max(1, parseInt(document.getElementById('editRetentionDays').value || '30', 10) || 30);
        settings.push({ Name: 'keep-time', Value: `${retentionDays}${document.getElementById('editRetentionUnit').value || 'D'}` });
    }
    if (!document.getElementById('editAdvancedOptionsPanel').hidden) {
        const volumeSize = Math.max(1, parseInt(document.getElementById('editVolumeSize').value || '50', 10) || 50);
        settings.push({ Name: 'dblock-size', Value: `${volumeSize}${document.getElementById('editVolumeSizeUnit').value || 'MB'}` });
    }
    const payload = {
        Backup: {
            ID: id,
            Name: document.getElementById('editBackupName').value.trim(),
            Description: document.getElementById('editBackupDescription').value.trim(),
            Sources: splitPathList(getEditSourcePathsRaw()).map(hostPathToContainer),
            TargetURL: toBackupTargetUrl(document.getElementById('editTargetUrl').value),
            Settings: settings,
        },
        Schedule: null,
    };
    if (document.getElementById('editScheduleEnabled').checked) {
        const every = Math.max(1, parseInt(document.getElementById('editScheduleEvery').value || '1', 10) || 1);
        const unit = document.getElementById('editScheduleUnit').value || 'D';
        const time = document.getElementById('editScheduleTime').value || '13:00';
        const [hour, minute] = time.split(':').map(v => parseInt(v, 10));
        const nextRun = new Date();
        nextRun.setSeconds(0, 0);
        nextRun.setHours(Number.isFinite(hour) ? hour : 13, Number.isFinite(minute) ? minute : 0, 0, 0);
        if (nextRun.getTime() <= Date.now()) nextRun.setDate(nextRun.getDate() + 1);
        const allowedDays = buildAllowedDaysDataForGrid('editAllowedDaysGrid');
        payload.Schedule = {
            Time: nextRun.toISOString(),
            Repeat: `${every}${unit}`,
        };
        if (allowedDays.short.length) payload.Schedule.AllowedDays = allowedDays.short;
        if (allowedDays.rule) payload.Schedule.Rule = allowedDays.rule;
    }
    loading(true, t('loading'));
    try {
        await apiCall(`backup/${id}`, 'PUT', payload);
        toast(t('saveChanges'), 'success');
        closeModal('editModal');
        await loadBackups();
    } catch(err) {
        toast(err.message, 'error');
    } finally {
        loading(false);
    }
});
const createWizardStepOrder = ['General', 'Destination', 'Source Data', 'Schedule', 'Options'];
let createWizardStep = 0;
function setFieldError(id, active) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('active', !!active);
}
function updateSchedulePreview() {
    const enabled = document.getElementById('newScheduleEnabled')?.checked;
    const every = Math.max(1, parseInt(document.getElementById('newScheduleEvery')?.value || '1', 10) || 1);
    const unit = document.getElementById('newScheduleUnit')?.value || 'D';
    const time = document.getElementById('newScheduleTime')?.value || '13:00';
    const label = document.getElementById('schedulePreviewLabel');
    const sub = document.getElementById('schedulePreviewSub');
    if (!label || !sub) return;
    if (!enabled) {
        label.textContent = 'Manual only';
        sub.textContent = 'Runs only when started manually';
        return;
    }
    label.textContent = every === 1 ? (unit === 'D' ? 'Daily' : 'Hourly') : `Every ${every} ${unit === 'D' ? 'days' : 'hours'}`;
    sub.textContent = time;
}
function toggleScheduleFields() {
    const enabled = document.getElementById('newScheduleEnabled')?.checked;
    const wrap = document.getElementById('scheduleFieldsWrap');
    if (!wrap) return;
    wrap.style.opacity = enabled ? '1' : '.55';
    wrap.querySelectorAll('input, select').forEach(el => { el.disabled = !enabled; });
    updateSchedulePreview();
}
function toggleRetentionInputs() {
    const custom = document.querySelector('input[name="backupRetentionMode"]:checked')?.value === 'delete-older';
    const days = document.getElementById('newRetentionDays');
    const unit = document.getElementById('newRetentionUnit');
    if (days) days.disabled = !custom;
    if (unit) unit.disabled = !custom;
}
function updateEncryptionFields() {
    const mode = document.getElementById('newEncryptionMode')?.value || 'aes';
    const wrap = document.getElementById('passwordFieldsWrap');
    const pass = document.getElementById('newPassphrase');
    const repeat = document.getElementById('newPassphraseRepeat');
    if (wrap) wrap.hidden = mode === 'none';
    if (pass) {
        pass.disabled = mode === 'none';
        if (mode === 'none') pass.value = '';
    }
    if (repeat) {
        repeat.disabled = mode === 'none';
        if (mode === 'none') repeat.value = '';
    }
    setFieldError('newPassphraseError', false);
    togglePassphraseVisibility(false);
}
function togglePassphraseVisibility(force) {
    const btn = document.getElementById('togglePasswordVisibilityBtn');
    const show = typeof force === 'boolean' ? force : !btn?.classList.contains('active');
    ['newPassphrase', 'newPassphraseRepeat'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.type = show ? 'text' : 'password';
    });
    if (btn) btn.classList.toggle('active', show);
}
function generateBackupPassword() {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*_-+=';
    let out = '';
    const buf = new Uint32Array(24);
    crypto.getRandomValues(buf);
    for (let i = 0; i < buf.length; i++) out += alphabet[buf[i] % alphabet.length];
    const pass = document.getElementById('newPassphrase');
    const repeat = document.getElementById('newPassphraseRepeat');
    if (pass) pass.value = out;
    if (repeat) repeat.value = out;
    togglePassphraseVisibility(true);
    setFieldError('newPassphraseError', false);
}
function updateCreateWizardButtons() {
    const backBtn = document.getElementById('createWizardBackBtn');
    const submitBtn = document.getElementById('createWizardSubmitBtn');
    if (backBtn) backBtn.style.visibility = createWizardStep === 0 ? 'hidden' : 'visible';
    if (submitBtn) submitBtn.style.display = createWizardStep === createWizardStepOrder.length - 1 ? '' : 'none';
}
function renderCreateWizardStep() {
    document.querySelectorAll('#createBackupForm .wizard-panel').forEach((panel, idx) => panel.classList.toggle('active', idx === createWizardStep));
    document.querySelectorAll('#createBackupForm .wizard-step').forEach((btn, idx) => {
        btn.classList.toggle('active', idx === createWizardStep);
        btn.classList.toggle('completed', idx < createWizardStep);
    });
    const stepsWrap = document.getElementById('createWizardSteps');
    if (stepsWrap) {
        const progress = createWizardStepOrder.length > 1 ? (createWizardStep / (createWizardStepOrder.length - 1)) * 100 : 0;
        const ratio = createWizardStepOrder.length > 1 ? (createWizardStep / (createWizardStepOrder.length - 1)) : 0;
        stepsWrap.style.setProperty('--wizard-progress', `${progress}%`);
        stepsWrap.style.setProperty('--wizard-progress-ratio', `${ratio}`);
    }
    updateCreateWizardButtons();
    if (createWizardStep === 1) initDestTree();
    if (createWizardStep === 2) initCreateSourceTree();
}
function validateCreateWizardStep(stepIndex) {
    let valid = true;
    if (stepIndex === 0) {
        const name = document.getElementById('newBackupName')?.value.trim() || '';
        const encryptionMode = document.getElementById('newEncryptionMode')?.value || 'aes';
        const pass = document.getElementById('newPassphrase')?.value || '';
        const repeat = document.getElementById('newPassphraseRepeat')?.value || '';
        const needsPassphrase = encryptionMode !== 'none';
        setFieldError('newBackupNameError', !name);
        setFieldError('newPassphraseError', needsPassphrase && (!pass || pass !== repeat));
        valid = !!name && (!needsPassphrase || (!!pass && pass === repeat));
    } else if (stepIndex === 1) {
        const target = document.getElementById('newTargetUrl')?.value.trim() || '';
        setFieldError('newTargetUrlError', !target);
        valid = !!target;
    } else if (stepIndex === 2) {
        const sources = splitPathList(document.getElementById('newSourcePath')?.value || '');
        setFieldError('newSourcePathError', sources.length === 0);
        valid = sources.length > 0;
    }
    return valid;
}
function gotoCreateWizardStep(stepIndex, force = false) {
    const nextIndex = Math.max(0, Math.min(createWizardStepOrder.length - 1, stepIndex));
    if (!force && nextIndex > createWizardStep && !validateCreateWizardStep(createWizardStep)) return false;
    createWizardStep = nextIndex;
    renderCreateWizardStep();
    return true;
}
function buildAllowedDaysData() {
    return buildAllowedDaysDataForGrid('newAllowedDaysGrid');
}
function buildCreateBackupPayload() {
    const name = document.getElementById('newBackupName').value.trim();
    const description = document.getElementById('newBackupDescription').value.trim();
    const sources = splitPathList(document.getElementById('newSourcePath').value).map(hostPathToContainer);
    const target = toBackupTargetUrl(document.getElementById('newTargetUrl').value);
    const encryptionMode = document.getElementById('newEncryptionMode')?.value || 'aes';
    const pass = document.getElementById('newPassphrase').value;
    const settings = [];
    if (encryptionMode !== 'none') {
        settings.push(
            { Name: 'encryption-module', Value: 'aes' },
            { Name: 'passphrase', Value: pass },
        );
    } else {
        settings.push({ Name: '--no-encryption', Value: 'true' });
    }
    if (document.querySelector('input[name="backupRetentionMode"]:checked')?.value === 'delete-older') {
        const retentionDays = Math.max(1, parseInt(document.getElementById('newRetentionDays').value || '30', 10) || 30);
        settings.push({ Name: 'keep-time', Value: `${retentionDays}${document.getElementById('newRetentionUnit').value || 'D'}` });
    }
    const advancedVisible = !document.getElementById('advancedOptionsPanel').hidden;
    if (advancedVisible) {
        const volumeSize = Math.max(1, parseInt(document.getElementById('newVolumeSize').value || '50', 10) || 50);
        settings.push({ Name: 'dblock-size', Value: `${volumeSize}${document.getElementById('newVolumeSizeUnit').value || 'MB'}` });
    }
    const payload = {
        Backup: {
            Name: name,
            Description: description,
            Sources: sources,
            TargetURL: target,
            Settings: settings,
        },
    };
    if (document.getElementById('newScheduleEnabled').checked) {
        const every = Math.max(1, parseInt(document.getElementById('newScheduleEvery').value || '1', 10) || 1);
        const unit = document.getElementById('newScheduleUnit').value || 'D';
        const time = document.getElementById('newScheduleTime').value || '13:00';
        const [hour, minute] = time.split(':').map(v => parseInt(v, 10));
        const nextRun = new Date();
        nextRun.setSeconds(0, 0);
        nextRun.setHours(Number.isFinite(hour) ? hour : 13, Number.isFinite(minute) ? minute : 0, 0, 0);
        if (nextRun.getTime() <= Date.now()) nextRun.setDate(nextRun.getDate() + 1);
        const allowedDays = buildAllowedDaysData();
        payload.Schedule = {
            Time: nextRun.toISOString(),
            Repeat: `${every}${unit}`,
        };
        if (allowedDays.short.length) payload.Schedule.AllowedDays = allowedDays.short;
        if (allowedDays.rule) payload.Schedule.Rule = allowedDays.rule;
    }
    return payload;
}
function resetCreateWizardForm() {
    const form = document.getElementById('createBackupForm');
    if (form) form.reset();
    document.getElementById('newEncryptionMode').value = 'aes';
    document.getElementById('newScheduleEnabled').checked = true;
    document.getElementById('newScheduleTime').value = '13:00';
    document.getElementById('newScheduleEvery').value = '1';
    document.getElementById('newScheduleUnit').value = 'D';
    document.querySelectorAll('#newAllowedDaysGrid input[type="checkbox"]').forEach(el => { el.checked = true; });
    document.querySelector('input[name="backupRetentionMode"][value="keep-all"]').checked = true;
    document.getElementById('newRetentionDays').value = '30';
    document.getElementById('newRetentionUnit').value = 'D';
    document.getElementById('newVolumeSize').value = '50';
    document.getElementById('newVolumeSizeUnit').value = 'MB';
    document.getElementById('advancedOptionsPanel').hidden = true;
    document.getElementById('newSourcePath').value = '';
    document.getElementById('newTargetUrl').value = '';
    createSourceSelected = new Set();
    destSelectedPath = '';
    treePanelAnchors.createSource = '/';
    treePanelAnchors.createDest = '/';
    const createSourceRoot = document.getElementById('sourceTreeRoot');
    const createDestRoot = document.getElementById('destTreeRoot');
    if (createSourceRoot) delete createSourceRoot.dataset.currentPath;
    if (createDestRoot) delete createDestRoot.dataset.currentPath;
    ['newBackupNameError', 'newPassphraseError', 'newTargetUrlError', 'newSourcePathError'].forEach(id => setFieldError(id, false));
    createWizardStep = 0;
    updateEncryptionFields();
    togglePassphraseVisibility(false);
    toggleScheduleFields();
    toggleRetentionInputs();
    updateSchedulePreview();
    renderCreateWizardStep();
}
document.getElementById('createWizardBackBtn')?.addEventListener('click', () => gotoCreateWizardStep(createWizardStep - 1, true));
document.querySelectorAll('#createBackupForm .wizard-step').forEach(btn => btn.addEventListener('click', () => gotoCreateWizardStep(Number(btn.dataset.stepIndex), Number(btn.dataset.stepIndex) <= createWizardStep)));
document.querySelectorAll('#createBackupForm .wizard-next-inline').forEach(btn => btn.addEventListener('click', () => gotoCreateWizardStep(Number(btn.dataset.nextStep))));
document.getElementById('editWizardBackBtn')?.addEventListener('click', () => gotoEditWizardStep(editWizardStep - 1, true));
document.querySelectorAll('#editBackupForm .wizard-step').forEach(btn => btn.addEventListener('click', () => gotoEditWizardStep(Number(btn.dataset.editStepIndex), Number(btn.dataset.editStepIndex) <= editWizardStep)));
document.querySelectorAll('#editBackupForm .edit-wizard-next-inline').forEach(btn => btn.addEventListener('click', () => gotoEditWizardStep(Number(btn.dataset.editNextStep))));
document.getElementById('newEncryptionMode')?.addEventListener('change', updateEncryptionFields);
document.getElementById('togglePasswordVisibilityBtn')?.addEventListener('click', () => togglePassphraseVisibility());
document.getElementById('generateBackupPasswordBtn')?.addEventListener('click', generateBackupPassword);
document.getElementById('toggleAdvancedOptionsBtn')?.addEventListener('click', () => {
    const panel = document.getElementById('advancedOptionsPanel');
    panel.hidden = !panel.hidden;
});
document.getElementById('toggleEditAdvancedOptionsBtn')?.addEventListener('click', () => {
    const panel = document.getElementById('editAdvancedOptionsPanel');
    panel.hidden = !panel.hidden;
});
document.getElementById('newScheduleEnabled')?.addEventListener('change', toggleScheduleFields);
document.getElementById('newScheduleTime')?.addEventListener('input', updateSchedulePreview);
document.getElementById('newScheduleEvery')?.addEventListener('input', updateSchedulePreview);
document.getElementById('newScheduleUnit')?.addEventListener('change', updateSchedulePreview);
document.querySelectorAll('input[name="backupRetentionMode"]').forEach(el => el.addEventListener('change', toggleRetentionInputs));
document.getElementById('editScheduleEnabled')?.addEventListener('change', toggleEditScheduleFields);
document.getElementById('editScheduleTime')?.addEventListener('input', updateEditSchedulePreview);
document.getElementById('editScheduleEvery')?.addEventListener('input', updateEditSchedulePreview);
document.getElementById('editScheduleUnit')?.addEventListener('change', updateEditSchedulePreview);
document.querySelectorAll('input[name="editBackupRetentionMode"]').forEach(el => el.addEventListener('change', toggleEditRetentionInputs));
document.getElementById('newBackupName')?.addEventListener('input', () => setFieldError('newBackupNameError', false));
document.getElementById('newPassphrase')?.addEventListener('input', () => setFieldError('newPassphraseError', false));
document.getElementById('newPassphraseRepeat')?.addEventListener('input', () => setFieldError('newPassphraseError', false));
document.getElementById('newTargetUrl')?.addEventListener('input', () => { setFieldError('newTargetUrlError', false); scheduleDestTreeRefresh(); });
document.getElementById('editBackupName')?.addEventListener('input', () => setFieldError('editBackupNameError', false));
document.getElementById('editTargetUrl')?.addEventListener('input', () => { setFieldError('editTargetUrlError', false); scheduleEditDestTreeRefresh(); });
document.getElementById('createBackupForm').addEventListener('submit', async e => {
    e.preventDefault();
    for (let stepIndex = 0; stepIndex <= 2; stepIndex++) {
        if (!validateCreateWizardStep(stepIndex)) {
            createWizardStep = stepIndex;
            renderCreateWizardStep();
            return;
        }
    }
    const payload = buildCreateBackupPayload();
    loading(true, t('loading'));
    try {
        await apiCall('backups', 'POST', payload);
        toast(t('createBackup'), 'success');
        closeModal('createModal');
        resetCreateWizardForm();
        await loadBackups();
    } catch(err) {
        toast(err.message, 'error');
    } finally {
        loading(false);
    }
});
const newSourcePathInput = document.getElementById('newSourcePath');
if (newSourcePathInput) {
    newSourcePathInput.addEventListener('input', () => { replaceSelectedSetFromInput(createSourceSelected, newSourcePathInput.value); setFieldError('newSourcePathError', false); scheduleCreateSourceTreeRefresh(); });
    newSourcePathInput.addEventListener('change', () => { clearTimeout(createSourceTreeRefreshTimer); initCreateSourceTree(); });
}
const editSourcePathInput = document.getElementById('editSourcePath');
if (editSourcePathInput) {
    editSourcePathInput.addEventListener('input', () => { editSourceDraft = editSourcePathInput.value; replaceSelectedSetFromInput(editSourceSelected, editSourcePathInput.value); setFieldError('editSourcePathError', false); scheduleEditSourceTreeRefresh(); });
    editSourcePathInput.addEventListener('change', () => { clearTimeout(editSourceTreeRefreshTimer); initEditSourceTree(); });
}
document.getElementById('sourceTreeFilter')?.addEventListener('input', e => { const r = document.getElementById('sourceTreeRoot'); if (r) applyFsTreeFilter(r, e.target.value); });
document.getElementById('destTreeFilter')?.addEventListener('input', e => { const r = document.getElementById('destTreeRoot'); if (r) applyFsTreeFilter(r, e.target.value); });
document.getElementById('editSourceTreeFilter')?.addEventListener('input', e => { const r = document.getElementById('editSourceTreeRoot'); if (r) applyFsTreeFilter(r, e.target.value); });
document.getElementById('editDestTreeFilter')?.addEventListener('input', e => { const r = document.getElementById('editDestTreeRoot'); if (r) applyFsTreeFilter(r, e.target.value); });
document.getElementById('sourceTreeMkdirBtn')?.addEventListener('click', () => mkdirForTreePanel('createSource'));
document.getElementById('destTreeMkdirBtn')?.addEventListener('click', () => mkdirForTreePanel('createDest'));
document.getElementById('editSourceTreeMkdirBtn')?.addEventListener('click', () => mkdirForTreePanel('editSource'));
document.getElementById('editDestTreeMkdirBtn')?.addEventListener('click', () => mkdirForTreePanel('editDest'));
document.getElementById('destTreeBackBtn')?.addEventListener('click', () => navigateDestPanelUp('createDest'));
document.getElementById('editDestTreeBackBtn')?.addEventListener('click', () => navigateDestPanelUp('editDest'));
async function testDestinationRw(inputId, resultId) {
    const inp = document.getElementById(inputId);
    const rel = document.getElementById(resultId);
    if (!inp || !rel) return;
    const raw = inp.value.trim();
    rel.textContent = '';
    if (!raw) { toast(t('testDestRwNeed'), 'warning'); return; }
    if (!/^file:/i.test(raw) && raw.indexOf('/') !== 0) { rel.style.color = 'var(--amber)'; rel.textContent = t('testDestRwRemote'); return; }
    rel.style.color = 'var(--text-secondary)';
    rel.textContent = t('testDestRwRunning');
    try {
        const res = await fetch(location.pathname + '?path_rw_test=1', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ path: raw }) });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            rel.style.color = 'var(--red)';
            rel.textContent = t('testDestRwFail', { msg: data.error || ('HTTP ' + res.status) });
            toast(t('testDestRwFail', { msg: data.error || res.status }), 'error');
            return;
        }
        if (data.ok) {
            rel.style.color = 'var(--green)';
            rel.textContent = t('testDestRwOk', { user: data.phpUser || '?' });
            toast(t('testDestRwOk', { user: data.phpUser || '?' }), 'success');
            return;
        }
        rel.style.color = 'var(--red)';
        const parts = [t('testDestRwFail', { msg: data.error || data.detail || 'write/read' })];
        if (data.phpUser) parts.push('PHP: ' + data.phpUser);
        if (data.hint) parts.push(data.hint);
        rel.textContent = parts.join(' — ');
        toast(t('testDestRwFail', { msg: data.error || 'write denied' }), 'error');
    } catch (e) {
        rel.style.color = 'var(--red)';
        rel.textContent = String(e.message || e);
    }
}
document.getElementById('testNewDestRwBtn')?.addEventListener('click', () => testDestinationRw('newTargetUrl', 'newDestRwTestResult'));
document.getElementById('testEditDestRwBtn')?.addEventListener('click', () => testDestinationRw('editTargetUrl', 'editDestRwTestResult'));
async function pauseServer() {
    const dur = await openPromptActionModal('Pause server', t('pauseDuration'), '1h');
    if (dur === null) return;
    const ep = dur ? `serverstate/pause?duration=${encodeURIComponent(dur)}` : 'serverstate/pause';
    loading(true, t('loading'));
    try {
        await apiCall(ep, 'POST');
        document.getElementById('serverBanner').classList.add('paused');
        document.getElementById('serverBannerText').textContent = t('serverPaused');
        toast(t('pauseServer'), 'warning');
    } catch(err) {
        toast(err.message, 'error');
    } finally {
        loading(false);
    }
}
async function resumeServer() { loading(true, t('loading')); try { await apiCall('serverstate/resume', 'POST'); document.getElementById('serverBanner').classList.remove('paused'); toast(t('resumeServer'), 'success'); } catch(err) { toast(err.message, 'error'); } finally { loading(false); } }
async function viewLogs(id) { if (!id) return; const el = document.getElementById('logsContent'); el.innerHTML = `<div class="empty-state"><p>${t('loading')}</p></div>`; try { const result = await apiCall(`backup/${id}/log?pagesize=50`); renderLogsInEl(result, el); } catch(err) { el.innerHTML = `<div class="empty-state"><p style="color:var(--red);">${esc(err.message)}</p></div>`; } }
async function openLogsModal(id, name) { document.getElementById('logsModalTitle').textContent = name; const el = document.getElementById('logsModalContent'); el.innerHTML = `<div class="empty-state"><p>${t('loading')}</p></div>`; showModal('logsModal'); try { const result = await apiCall(`backup/${id}/log?pagesize=100`); renderLogsInEl(result, el); } catch(err) { el.innerHTML = `<div class="empty-state"><p style="color:var(--red);">${esc(err.message)}</p></div>`; } }
async function openSystemInfoModal() { showModal('systemInfoModal'); const el = document.getElementById('systemInfoContent'); el.innerHTML = `<div class="empty-state"><p>${t('loading')}</p></div>`; try { const data = await apiCall('systeminfo'); el.innerHTML = '<table class="settings-table"><tbody>' + Object.entries(data || {}).filter(([,v]) => v === null || typeof v !== 'object').map(([k,v]) => `<tr><td class="settings-key">${esc(k)}</td><td class="settings-val">${esc(String(v ?? ''))}</td></tr>`).join('') + '</tbody></table>'; } catch (err) { el.innerHTML = `<div class="empty-state"><p style="color:var(--red);">${esc(err.message)}</p></div>`; } }
async function openChangelogModal() { showModal('changelogModal'); const el = document.getElementById('changelogContent'); el.innerHTML = `<div class="empty-state"><p>${t('loading')}</p></div>`; try { const data = await apiCall('changelog'); const text = data && (data.Changelog || data.changelog) ? String(data.Changelog || data.changelog) : JSON.stringify(data, null, 2); el.style.whiteSpace = ''; el.style.fontFamily = ''; el.innerHTML = `<div class="md-content" style="padding:16px;">${parseMarkdown(text)}</div>`; } catch (err) { el.innerHTML = `<div class="empty-state"><p style="color:var(--red);">${esc(err.message)}</p></div>`; } }
function parseMarkdown(md) {
    let html = esc(md);
    html = html.replace(/^######\s+(.+)$/gm, '<h6>$1</h6>');
    html = html.replace(/^#####\s+(.+)$/gm, '<h5>$1</h5>');
    html = html.replace(/^####\s+(.+)$/gm, '<h4>$1</h4>');
    html = html.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^##\s+(.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^#\s+(.+)$/gm, '<h1>$1</h1>');
    html = html.replace(/^---+$/gm, '<hr>');
    html = html.replace(/^={4,}$/gm, '<hr>');
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    const lines = html.split('\n');
    const out = []; let inUl = false, inOl = false, inPre = false;
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        if (line.startsWith('```')) { if (inPre) { out.push('</code></pre>'); inPre = false; } else { out.push('<pre><code>'); inPre = true; } continue; }
        if (inPre) { out.push(line); continue; }
        if (/^[-*]\s+/.test(line)) { if (!inUl) { out.push('<ul>'); inUl = true; } out.push('<li>' + line.replace(/^[-*]\s+/,'') + '</li>'); continue; }
        if (inUl) { out.push('</ul>'); inUl = false; }
        if (/^\d+\.\s+/.test(line)) { if (!inOl) { out.push('<ol>'); inOl = true; } out.push('<li>' + line.replace(/^\d+\.\s+/,'') + '</li>'); continue; }
        if (inOl) { out.push('</ol>'); inOl = false; }
        if (line.trim() === '') { out.push('<br>'); continue; }
        if (/<h[1-6]>|<hr>|<pre>/.test(line)) { out.push(line); continue; }
        out.push('<p>' + line + '</p>');
    }
    if (inUl) out.push('</ul>');
    if (inOl) out.push('</ol>');
    if (inPre) out.push('</code></pre>');
    return out.join('');
}
async function openLicensesModal() { showModal('licensesModal'); const el = document.getElementById('licensesContent'); el.innerHTML = `<div class="empty-state"><p>${t('loading')}</p></div>`; try { const data = await apiCall('licenses'); const arr = Array.isArray(data) ? data : []; el.innerHTML = arr.map(L => `<div class="info-section" style="margin-bottom:12px;"><h4 style="margin:0 0 8px;">${esc(L.Title||'')}</h4><pre style="white-space:pre-wrap;font-size:11px;color:var(--text-secondary);">${esc(L.License||'')}</pre></div>`).join('') || `<div class="empty-state"><p>${t('noLogs')}</p></div>`; } catch (err) { el.innerHTML = `<div class="empty-state"><p style="color:var(--red);">${esc(err.message)}</p></div>`; } }
async function openCommandlineModal() { showModal('commandlineModal'); const el = document.getElementById('commandlineContent'); el.innerHTML = `<div class="empty-state"><p>${t('loading')}</p></div>`; try { const data = await apiCall('commandline'); const arr = Array.isArray(data) ? data : []; el.innerHTML = `<div style="display:flex;flex-wrap:wrap;gap:6px;">` + arr.map(c => `<span class="badge-version">${esc(c)}</span>`).join('') + `</div>`; } catch (err) { el.innerHTML = `<div class="empty-state"><p style="color:var(--red);">${esc(err.message)}</p></div>`; } }
function renderLogsInEl(data, el) {
    const entries = Array.isArray(data) ? data : (data?.Data ?? data?.Messages ?? []);
    if (!entries.length) { el.innerHTML = `<div class="empty-state"><p>${t('noLogs')}</p></div>`; return; }
    el.innerHTML = '';
    entries.slice(0, 200).forEach(e => {
        const msg = typeof e === 'string' ? e : (e.Message || e.message || '');
        const ts = e.Timestamp ? new Date(e.Timestamp * 1000).toLocaleString() : e.When ? new Date(e.When).toLocaleString() : '';
        const type = (e.Type || e.type || '').toLowerCase();
        const rawData = e.Data;
        if (rawData) {
            try {
                const parsed = typeof rawData === 'string' ? JSON.parse(rawData) : rawData;
                if (parsed && (parsed.ParsedResult !== undefined || parsed.AddedFiles !== undefined)) {
                    el.appendChild(buildBackupResultBlock(parsed, ts)); return;
                }
            } catch(_) {}
        }
        let lineCls = '', badgeCls = 'lb-info', badgeTxt = 'INFO';
        if (type === 'error' || type === 'fatal') { lineCls='log-error'; badgeCls='lb-error'; badgeTxt='ERROR'; }
        else if (type === 'warning' || type === 'warn') { lineCls='log-warning'; badgeCls='lb-warning'; badgeTxt='WARN'; }
        else if (type === 'success' || type === 'result') { lineCls='log-success'; badgeCls='lb-success'; badgeTxt='OK'; }
        else if (type === 'debug') { badgeCls='lb-debug'; badgeTxt='DEBUG'; }
        else if (type === 'information' || type === 'info') { badgeCls='lb-info'; badgeTxt='INFO'; }
        else if (/error|fail/i.test(msg) && type === '') { lineCls='log-error'; badgeCls='lb-error'; badgeTxt='ERROR'; }
        else if (/warn/i.test(msg) && type === '') { lineCls='log-warning'; badgeCls='lb-warning'; badgeTxt='WARN'; }
        let msgDisplay = esc(msg);
        try {
            const maybeJson = JSON.parse(msg);
            if (maybeJson && typeof maybeJson === 'object') { msgDisplay = renderJsonAsTable(maybeJson); }
        } catch(_) {}
        const line = document.createElement('div');
        line.className = `log-line ${lineCls}`;
        line.innerHTML = `<span class="log-ts">${esc(ts)}</span><span class="log-badge ${badgeCls}">${badgeTxt}</span><span class="log-msg">${msgDisplay}</span>`;
        el.appendChild(line);
    });
}
function renderJsonAsTable(obj, depth) {
    depth = depth || 0;
    if (typeof obj !== 'object' || obj === null) return esc(String(obj));
    const entries = Array.isArray(obj) ? obj.map((v,i)=>[String(i),v]) : Object.entries(obj);
    const filtered = entries.filter(([,v]) => typeof v !== 'object' || v === null);
    const nested = entries.filter(([,v]) => typeof v === 'object' && v !== null);
    let html = `<table class="json-table">`;
    filtered.forEach(([k,v]) => { html += `<tr><td>${esc(k)}</td><td>${esc(String(v))}</td></tr>`; });
    nested.forEach(([k,v]) => { html += `<tr><td>${esc(k)}</td><td><div class="json-nested">${renderJsonAsTable(v, depth+1)}</div></td></tr>`; });
    html += `</table>`;
    return html;
}
function buildBackupResultBlock(r, ts) { const wrap = document.createElement('div'); wrap.className = 'log-report'; const result = r.ParsedResult || r.MainOperation || '?'; const isOk = /success/i.test(result); const dur = r.Duration || r.MainOperation || ''; const ver = r.Version || ''; const fmtN = v => v != null ? Number(v).toLocaleString() : '—'; const fmtB = v => v != null ? fmtBytes(Number(v)) : '—'; const fmtT = s => { try { return new Date(s).toLocaleString(); } catch(_){ return s||'—'; } }; const statusCls = isOk ? 'ok' : 'err'; wrap.innerHTML = `<div class="log-report-title"><span style="font-size:13px;font-weight:700;color:var(--${isOk?'green':'red'});">${esc(result)}</span>${ver ? `<span style="font-size:10px;color:var(--text-muted);margin-left:8px;">v${esc(ver)}</span>` : ''}${ts ? `<span style="font-size:10px;color:var(--text-muted);margin-left:8px;">${esc(ts)}</span>` : ''}</div>`; const kvs = [['AddedFiles', r.AddedFiles, fmtN(r.AddedFiles)], ['ModifiedFiles', r.ModifiedFiles, fmtN(r.ModifiedFiles)], ['DeletedFiles', r.DeletedFiles, fmtN(r.DeletedFiles)], ['ExaminedFiles', r.ExaminedFiles, fmtN(r.ExaminedFiles)], ['SizeAdded', r.SizeOfAddedFiles, fmtB(r.SizeOfAddedFiles)], ['SizeExamined', r.SizeOfExaminedFiles, fmtB(r.SizeOfExaminedFiles)], ['Duration', dur, formatDuration(dur)], ['BeginTime', r.BeginTime, fmtT(r.BeginTime)],].filter(([,raw]) => raw != null && raw !== 0 || raw === 0); if (kvs.length) { const grid = document.createElement('div'); grid.className = 'log-report-grid'; kvs.forEach(([label,,display]) => { grid.innerHTML += `<div class="log-kv"><div class="log-kv-key">${esc(label)}</div><div class="log-kv-val">${display}</div></div>`; }); wrap.appendChild(grid); } const bs = r.BackendStatistics; if (bs) { const bsGrid = document.createElement('div'); bsGrid.style.cssText = 'margin-top:10px;'; bsGrid.innerHTML = `<div class="log-report-title" style="margin-bottom:8px;font-size:10px;">Backend</div>`; const bsKvs = [['Uploaded', fmtB(bs.BytesUploaded)], ['Downloaded', fmtB(bs.BytesDownloaded)], ['FilesUploaded', fmtN(bs.FilesUploaded)], ['RemoteCalls', fmtN(bs.RemoteCalls)], ['KnownFiles', fmtN(bs.KnownFileCount)], ['FreeQuota', fmtB(bs.FreeQuotaSpace)],]; const bsInner = document.createElement('div'); bsInner.className = 'log-report-grid'; bsKvs.forEach(([k,v]) => { bsInner.innerHTML += `<div class="log-kv"><div class="log-kv-key">${k}</div><div class="log-kv-val">${v}</div></div>`; }); bsGrid.appendChild(bsInner); wrap.appendChild(bsGrid); } const msgs = r.Messages; if (Array.isArray(msgs) && msgs.length) { const msgsDiv = document.createElement('div'); msgsDiv.className = 'log-messages'; msgsDiv.innerHTML = `<div class="log-report-title" style="margin:10px 0 6px;font-size:10px;">Messages (${msgs.length})</div>`; msgs.slice(0,20).forEach(m => { const match = String(m).match(/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \S+)\s+-\s+\[([^\]]+)\]:\s+(.+)$/s); const mts = match ? match[1] : ''; const mtxt = match ? match[3] : String(m); msgsDiv.innerHTML += `<div class="log-msg-item"><span class="log-msg-ts">${esc(mts)}</span><span class="log-msg-text">${esc(mtxt)}</span></div>`; }); wrap.appendChild(msgsDiv); } return wrap; }
async function loadProgressState() {
    try {
        const state = await apiCall('serverstate');
        lastServerState = state || {};
        const banner = document.getElementById('serverBanner');
        if (state?.ProgramState === 'Paused') {
            banner.classList.add('paused');
            document.getElementById('serverBannerText').textContent = t('serverPaused');
        } else {
            banner.classList.remove('paused');
        }
        let ps = {};
        try {
            ps = (await apiCall('progressstate')) || {};
        } catch (err) {
            if (!isFetchFailure(err) && !err?.isFetchFailure && err?.statusCode !== 401) console.warn('progressstate:', err.message || err);
            ps = {};
        }
        const activeTaskBackupId = resolveActiveTaskBackupId(state, ps);
        const running = isBackupTaskRunning(state, ps) && isProgressPhaseActive(ps);
        if (running) {
            progressState = ps;
            progressState.DerivedSpeed = computeDerivedProgressSpeed(progressState);
        } else {
            progressState = {};
            lastProgressSnapshot = null;
        }
        syncFastPollTimer(running);
        updateProgressUI();
        if (previousActiveTaskBackupId != null && !running && !progressCompletionRefreshInFlight) {
            progressCompletionRefreshInFlight = true;
            try {
                await loadBackups();
            } catch (err) {
                if (!isFetchFailure(err) && !err?.isFetchFailure) console.warn('post-completion loadBackups:', err?.message || err);
            } finally {
                progressCompletionRefreshInFlight = false;
            }
        }
        previousActiveTaskBackupId = running ? activeTaskBackupId : null;
    } catch(err) {
        if (isFetchFailure(err) || err?.isFetchFailure) { warnFetchFailureOnce('loadProgressState', err); return; }
        if (err?.statusCode === 401) {
            serverStatus = false;
            setStatus(false);
            syncFastPollTimer(false);
            if (autoRefreshTimer) { clearInterval(autoRefreshTimer); autoRefreshTimer = null; }
            if (logsAutoRefreshTimer) { clearInterval(logsAutoRefreshTimer); logsAutoRefreshTimer = null; }
            toast('Authentication error – please reconnect.', 'error');
            return;
        }
        console.warn('loadProgressState:', err.message || err);
    }
}
async function refreshData() { if (!serverStatus || !currentHost || !currentToken) return; loading(true, t('refresh') + '…'); try { await Promise.all([loadBackups({ force: true }), loadProgressState()]); } catch(err) { toast(err.message, 'error'); } finally { loading(false); } }
async function refreshBackgroundData() {
    if (!serverStatus || !currentHost || !currentToken) return;
    if (refreshBackgroundInFlight) return;
    refreshBackgroundInFlight = true;
    try {
        const [progressResult, backupsResult] = await Promise.allSettled([
            loadProgressState(),
            loadBackups({ force: true })
        ]);
        if (progressResult.status === 'rejected') {
            const err = progressResult.reason;
            if (isFetchFailure(err) || err?.isFetchFailure) warnFetchFailureOnce('loadProgressState', err);
        }
        if (backupsResult.status === 'rejected') {
            const err = backupsResult.reason;
            if (isFetchFailure(err) || err?.isFetchFailure) warnFetchFailureOnce('loadBackups', err);
            else console.warn('loadBackups:', err?.message || err);
        }
    } finally {
        refreshBackgroundInFlight = false;
    }
    const logsSection = document.getElementById('section-logs');
    if (logsSection && logsSection.style.display !== 'none') {
        const sel = document.getElementById('logBackupSelector');
        if (sel?.value) {
            try { await viewLogs(sel.value); } catch (err) { console.warn('viewLogs:', err?.message || err); }
        }
    }
}
function startAutoRefresh() {
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    if (fastPollTimer) clearInterval(fastPollTimer);
    if (logsAutoRefreshTimer) clearInterval(logsAutoRefreshTimer);
    fastPollTimer = null;
    logsAutoRefreshTimer = null;
    autoRefreshTimer = setInterval(refreshBackgroundData, 10000);
}
function showSection(name) { ['backups','logs','explorer'].forEach(s => { const el = document.getElementById('section-' + s); if (el) el.style.display = s === name ? '' : 'none'; }); document.querySelectorAll('.nav-item[data-section]').forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-section') === name)); if (name === 'logs' && serverStatus) { const sel = document.getElementById('logBackupSelector'); if (sel.value) viewLogs(sel.value); } if (name === 'explorer') { populateExplorerSelector(); } closeMobileMenu(); }
function openDocumentation() { const params=new URLSearchParams({embed:'1',lang:'<?php echo $bdLang; ?>',theme:'<?php echo $bdTheme; ?>',color:'<?php echo $bdColor; ?>'}); window.open('/doc_viewer.php?'+params.toString(),'_blank'); }
function showModal(id) {
    document.getElementById(id).classList.add('active');
    if (id === 'createModal') {
        resetCreateWizardForm();
        initCreateSourceTree();
        initDestTree();
    }
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    if (id === 'createModal') resetCreateWizardForm();
    if (id === 'editModal') resetEditWizardForm();
}
let confirmActionResolver = null;
let promptActionResolver = null;
function openConfirmActionModal(title, text, danger = false) {
    const titleEl = document.getElementById('confirmActionTitle');
    const textEl = document.getElementById('confirmActionText');
    const okBtn = document.getElementById('confirmActionOkBtn');
    if (titleEl) titleEl.textContent = title;
    if (textEl) textEl.textContent = text;
    if (okBtn) okBtn.className = `btn ${danger ? 'btn-danger' : 'btn-primary'}`;
    showModal('confirmActionModal');
    return new Promise(resolve => { confirmActionResolver = resolve; });
}
function closeConfirmActionModal(result) {
    closeModal('confirmActionModal');
    if (confirmActionResolver) {
        const resolve = confirmActionResolver;
        confirmActionResolver = null;
        resolve(!!result);
    }
}
function openPromptActionModal(title, text, initialValue = '') {
    const titleEl = document.getElementById('promptActionTitle');
    const textEl = document.getElementById('promptActionText');
    const inputEl = document.getElementById('promptActionInput');
    if (titleEl) titleEl.textContent = title;
    if (textEl) textEl.textContent = text;
    if (inputEl) {
        inputEl.value = initialValue;
        setTimeout(() => inputEl.focus(), 0);
    }
    showModal('promptActionModal');
    return new Promise(resolve => { promptActionResolver = resolve; });
}
function closePromptActionModal(result) {
    closeModal('promptActionModal');
    if (promptActionResolver) {
        const resolve = promptActionResolver;
        promptActionResolver = null;
        resolve(result);
    }
}
function closeAllBackupActionMenus() {
    document.querySelectorAll('.actions-menu.open').forEach(menu => {
        menu.classList.remove('open');
        menu.style.top = '';
        menu.style.left = '';
        if (menu._actionsDropdownHost) {
            menu._actionsDropdownHost.classList.remove('is-open');
            menu._actionsDropdownHost.appendChild(menu);
            menu._actionsDropdownHost = null;
        }
    });
    document.querySelectorAll('tr.actions-menu-row-open').forEach(row => row.classList.remove('actions-menu-row-open'));
}
function positionBackupActionsMenu(menu, btn) {
    const rect = btn.getBoundingClientRect();
    const margin = 8;
    menu.style.top = '0px';
    menu.style.left = '0px';
    const menuRect = menu.getBoundingClientRect();
    let left = rect.right - menuRect.width;
    let top = rect.bottom + 4;
    if (left < margin) left = margin;
    if (left + menuRect.width > window.innerWidth - margin) left = Math.max(margin, window.innerWidth - menuRect.width - margin);
    if (top + menuRect.height > window.innerHeight - margin) top = rect.top - menuRect.height - 4;
    if (top < margin) top = Math.max(margin, window.innerHeight - menuRect.height - margin);
    menu.style.left = `${Math.round(left)}px`;
    menu.style.top = `${Math.round(top)}px`;
}
function toggleBackupActionsMenu(ev, btn) {
    if (ev) { ev.preventDefault(); ev.stopPropagation(); }
    const dropdown = btn.closest('.actions-dropdown');
    const menu = btn.nextElementSibling;
    const wasOpen = menu && menu.classList.contains('open');
    closeAllBackupActionMenus();
    if (wasOpen || !menu || !dropdown) return;
    menu._actionsDropdownHost = dropdown;
    dropdown.classList.add('is-open');
    btn.closest('tr')?.classList.add('actions-menu-row-open');
    document.body.appendChild(menu);
    menu.classList.add('open');
    positionBackupActionsMenu(menu, btn);
}
document.getElementById('confirmActionOkBtn')?.addEventListener('click', () => closeConfirmActionModal(true));
document.getElementById('promptActionOkBtn')?.addEventListener('click', () => closePromptActionModal(document.getElementById('promptActionInput')?.value ?? ''));
document.getElementById('promptActionInput')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
        e.preventDefault();
        closePromptActionModal(document.getElementById('promptActionInput')?.value ?? '');
    }
});
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal')) {
        if (e.target.id === 'confirmActionModal') closeConfirmActionModal(false);
        else if (e.target.id === 'promptActionModal') closePromptActionModal(null);
        else closeModal(e.target.id);
    }
    if (!e.target.closest('.actions-dropdown') && !e.target.closest('.actions-menu')) closeAllBackupActionMenus();
});
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (document.getElementById('promptActionModal')?.classList.contains('active')) closePromptActionModal(null);
    else if (document.getElementById('confirmActionModal')?.classList.contains('active')) closeConfirmActionModal(false);
});
const HOST_FILESYSTEM_ROOT = '/source';
function containerPathToHost(raw) {
    let path = normalizeFilesystemPath(raw);
    if (!path) return '';
    if (path === HOST_FILESYSTEM_ROOT) return '/';
    if (path.startsWith(HOST_FILESYSTEM_ROOT + '/')) return path.slice(HOST_FILESYSTEM_ROOT.length) || '/';
    return path;
}
function hostPathToContainer(raw) {
    let path = normalizeFilesystemPath(raw);
    if (!path) return '';
    if (/^[A-Za-z]:/.test(path)) return path;
    if (path === HOST_FILESYSTEM_ROOT || path.startsWith(HOST_FILESYSTEM_ROOT + '/')) return path;
    if (path === '/') return HOST_FILESYSTEM_ROOT;
    if (path.startsWith('/')) return normalizeFilesystemPath(HOST_FILESYSTEM_ROOT + path);
    return path;
}
function formatTargetUrlDisplayParts(raw) { let u = String(raw || '').trim(); try { u = decodeURIComponent(u); } catch (_) {} if (!/^file:/i.test(u)) return { kind: 'remote', path: u, full: u }; let rest = u.replace(/^file:\/\//i, '').replace(/^localhost\/?/i, ''); rest = rest.replace(/^\/+/, ''); const win = /^([a-zA-Z]):[/\\](.*)$/i.exec(rest); if (win) { const tail = win[2].replace(/\//g, '\\'); const path = win[1].toUpperCase() + ':\\' + tail.replace(/\\+$/g, ''); return { kind: 'file', kindLabel: t('localDestination'), path, full: u }; } const unix = containerPathToHost('/' + rest.replace(/\\/g, '/').replace(/\/{2,}/g, '/')); return { kind: 'file', kindLabel: t('localDestination'), path: unix, full: u }; }
function formatTargetUrlHtml(raw) { const p = formatTargetUrlDisplayParts(raw); if (p.kind === 'file') { return `<span class="target-url-pill" title="${esc(p.full)}"><span class="target-url-kind">${esc(p.kindLabel)}</span><span class="target-url-path">${esc(p.path)}</span></span>`; } return `<span class="target-url-pill" title="${esc(p.full)}"><span class="target-url-path">${esc(p.path)}</span></span>`; }
function toBackupTargetUrl(raw) {
    let value = String(raw || '').trim();
    if (!value) return '';
    try { value = decodeURIComponent(value); } catch (_) {}
    if (/^file:/i.test(value)) {
        const parsed = parseTargetPath(value);
        if (parsed) return /^[A-Za-z]:/.test(parsed) ? 'file:///' + parsed.replace(/\\/g, '/') : 'file://' + hostPathToContainer(parsed);
        return value;
    }
    if (/^[a-z][a-z0-9+.-]*:\/\//i.test(value)) return value;
    if (/^[A-Za-z]:[\\/]/.test(value)) return 'file:///' + value.replace(/\\/g, '/');
    if (value.startsWith('/')) return 'file://' + hostPathToContainer(value);
    return value;
}
let createSourceSelected = new Set();
let destSelectedPath = '';
const treePanelAnchors = { createSource:'/', createDest:'/', editSource:'/', editDest:'/' };
function getTreePanelRoot(panel) {
    if (panel === 'createSource') return document.getElementById('sourceTreeRoot');
    if (panel === 'createDest') return document.getElementById('destTreeRoot');
    if (panel === 'editSource') return document.getElementById('editSourceTreeRoot');
    if (panel === 'editDest') return document.getElementById('editDestTreeRoot');
    return null;
}
function getTreePanelKeyFromElement(el) {
    const panel = el ? el.closest('#sourceTreePanel,#destTreePanel,#editSourceTreePanel,#editDestTreePanel') : null;
    if (!panel) return '';
    if (panel.id === 'sourceTreePanel') return 'createSource';
    if (panel.id === 'destTreePanel') return 'createDest';
    if (panel.id === 'editSourceTreePanel') return 'editSource';
    if (panel.id === 'editDestTreePanel') return 'editDest';
    return '';
}
function getTreePanelCurrentPath(panel) {
    const root = getTreePanelRoot(panel);
    return normalizeFilesystemPath(root?.dataset.currentPath || treePanelAnchors[panel] || '/');
}
function getTreePanelRememberedPath(panel) {
    const root = getTreePanelRoot(panel);
    const raw = String(root?.dataset.currentPath || treePanelAnchors[panel] || '').trim();
    if (!raw || raw === '/') return '';
    return normalizeFilesystemPath(raw);
}
function setTreePanelCurrentPath(panel, path) {
    const next = normalizeFilesystemPath(path) || '/';
    treePanelAnchors[panel] = next;
    const root = getTreePanelRoot(panel);
    if (root) root.dataset.currentPath = next;
}
function refreshTreePanel(panel) {
    if (panel === 'createSource') initCreateSourceTree();
    else if (panel === 'createDest') initDestTree();
    else if (panel === 'editSource') initEditSourceTree();
    else if (panel === 'editDest') initEditDestTree();
}
function isDestTreePanel(panel) {
    return panel === 'createDest' || panel === 'editDest';
}
function updateDestExplorerUi(panel) {
    if (!isDestTreePanel(panel)) return;
    const current = getTreePanelCurrentPath(panel);
    const backBtn = document.getElementById(panel === 'createDest' ? 'destTreeBackBtn' : 'editDestTreeBackBtn');
    const pathEl = document.getElementById(panel === 'createDest' ? 'destTreeCurrentPath' : 'editDestTreeCurrentPath');
    if (backBtn) backBtn.disabled = current === '/' || /^[A-Za-z]:\\$/.test(current);
    if (pathEl) pathEl.textContent = current || '/';
}
function navigateDestPanelUp(panel) {
    if (!isDestTreePanel(panel)) return;
    const current = getTreePanelCurrentPath(panel);
    const parent = fsPathParent(current);
    if (!parent || filesystemPathsEqual(parent, current)) return;
    setTreePanelCurrentPath(panel, parent);
    refreshTreePanel(panel);
}
function enterDestPanelFolder(panel, path, selectedSet, syncCallback) {
    if (!isDestTreePanel(panel) || !path) return;
    setTreePanelCurrentPath(panel, path);
    if (selectedSet) {
        selectedSet.clear();
        selectedSetAddPath(selectedSet, path);
    }
    if (typeof syncCallback === 'function') syncCallback();
    refreshTreePanel(panel);
}
function fsPathParent(path) {
    const p = normalizeFilesystemPath(path);
    if (!p || p === '/') return '/';
    if (/^[A-Za-z]:\\/.test(p)) {
        const idx = p.lastIndexOf('\\');
        if (idx <= 2) return p.slice(0, 3);
        return p.slice(0, idx) || p.slice(0, 3);
    }
    const idx = p.lastIndexOf('/');
    if (idx <= 0) return '/';
    return p.slice(0, idx) || '/';
}
function getBrowseAnchorForSourceField(raw) {
    const parts = splitPathList(raw).map(item => normalizeFilesystemPath(containerPathToHost(item) || item)).filter(Boolean);
    if (!parts.length) return '/';
    if (parts.length === 1) return fsPathParent(parts[0]);
    if (parts.every(p => p.startsWith('/'))) {
        const split = parts.map(p => p.split('/').filter(Boolean));
        const common = [];
        for (let i = 0; i < split[0].length; i++) {
            const seg = split[0][i];
            if (split.every(s => s[i] === seg)) common.push(seg);
            else break;
        }
        return common.length ? '/' + common.join('/') : '/';
    }
    return '/';
}
function getBrowseAnchorForDestField(raw) {
    const s = String(raw || '').trim();
    if (!s) return '/';
    if (!/^file:/i.test(s) && !/^[A-Za-z]:/.test(s) && !s.startsWith('/')) return '/';
    let p = '';
    if (/^file:/i.test(s)) { const z = parseTargetPath(s); p = z ? normalizeFilesystemPath(z) : ''; }
    else p = normalizeFilesystemPath(containerPathToHost(s) || s);
    if (!p || (p[0] !== '/' && !/^[A-Za-z]:/.test(p))) return '/';
    return fsPathParent(p);
}
function fsSubtreeMatchesQuery(itemEl, q) {
    if (!q) return true;
    const path = (itemEl.dataset.fsPath || '').toLowerCase();
    const labelEl = itemEl.querySelector(':scope > .fs-tree-row .fs-tree-label');
    const label = (labelEl && labelEl.textContent) ? labelEl.textContent.toLowerCase() : '';
    if (path.includes(q) || label.includes(q)) return true;
    const childItems = itemEl.querySelectorAll(':scope > .fs-tree-children > .fs-tree-item');
    for (const c of childItems) if (fsSubtreeMatchesQuery(c, q)) return true;
    return false;
}
function applyFsTreeFilter(scrollRoot, query) {
    if (!scrollRoot) return;
    const q = String(query || '').trim().toLowerCase();
    scrollRoot.querySelectorAll(':scope > .fs-tree-item').forEach(el => { el.style.display = fsSubtreeMatchesQuery(el, q) ? '' : 'none'; });
}
let createSourceTreeRefreshTimer = null;
function scheduleCreateSourceTreeRefresh() { clearTimeout(createSourceTreeRefreshTimer); createSourceTreeRefreshTimer = setTimeout(() => initCreateSourceTree(), 320); }
let destTreeRefreshTimer = null;
function scheduleDestTreeRefresh() { clearTimeout(destTreeRefreshTimer); destTreeRefreshTimer = setTimeout(() => initDestTree(), 320); }
let editSourceTreeRefreshTimer = null;
function scheduleEditSourceTreeRefresh() { clearTimeout(editSourceTreeRefreshTimer); editSourceTreeRefreshTimer = setTimeout(() => initEditSourceTree(), 320); }
let editDestTreeRefreshTimer = null;
function scheduleEditDestTreeRefresh() { clearTimeout(editDestTreeRefreshTimer); editDestTreeRefreshTimer = setTimeout(() => initEditDestTree(), 320); }
async function fetchHostFilesystemList(path, onlyFolders = true, showHidden = false) {
    const p = normalizeFilesystemPath(path);
    if (!p || p[0] !== '/') return null;
    const q = new URLSearchParams({ host_fs: 'list', path: p, onlyFolders: onlyFolders ? 'true' : 'false', showHidden: showHidden ? 'true' : 'false' });
    const res = await fetch(`${window.location.pathname}?${q}`, { credentials: 'same-origin' });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch (_) { return null; }
    if (!res.ok) throw new Error((data && data.error) ? data.error : ('HTTP ' + res.status));
    if (!Array.isArray(data)) throw new Error('Invalid list');
    return data;
}
async function hostFsMkdir(parentPath, name) {
    const res = await fetch(`${window.location.pathname}?host_fs=mkdir`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ parent: normalizeFilesystemPath(parentPath), name: String(name || '').trim() }) });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) {
        const parts = [data.error || ('HTTP ' + res.status)];
        if (data.detail) parts.push(data.detail);
        if (data.parent) parts.push(`parent: ${data.parent}`);
        throw new Error(parts.filter(Boolean).join(' | '));
    }
    return data.path;
}
async function mkdirForTreePanel(panel) {
    const anchor = getTreePanelCurrentPath(panel);
    const name = await openPromptActionModal(t('newFolder'), t('newFolderNameHint'), '');
    if (name == null || !String(name).trim()) return;
    try {
        await hostFsMkdir(anchor, name);
        toast(t('newFolder'), 'success');
        refreshTreePanel(panel);
    } catch (e) { toast(e.message || String(e), 'error'); }
}
function syncNewSourcePathInput() { const inp = document.getElementById('newSourcePath'); if (inp) { inp.value = [...createSourceSelected].join(';'); setFieldError('newSourcePathError', false); } }
function syncDestPathInput() {
    const inp = document.getElementById('newTargetUrl');
    if (!inp) return;
    if (destSelectedPath) inp.value = containerPathToHost(destSelectedPath);
    setFieldError('newTargetUrlError', false);
}
/* ── Filesystem tree helpers ── */
function splitPathList(raw) { return String(raw || '').split(';').map(s => s.trim()).filter(Boolean); }
function normalizeFilesystemPath(path) {
    let s = String(path || '').trim();
    if (!s) return '';
    try { s = decodeURIComponent(s); } catch (_) {}
    if (/^file:/i.test(s)) {
        const parsed = parseTargetPath(s);
        if (parsed) s = parsed;
    }
    if (/^[A-Za-z]:/.test(s)) {
        s = s.replace(/\//g, '\\').replace(/\\+/g, '\\');
        if (/^[A-Za-z]:\\?$/.test(s)) return s.slice(0, 1).toUpperCase() + ':\\';
        s = s.replace(/\\+$/g, '');
        return s.slice(0, 1).toUpperCase() + s.slice(1);
    }
    s = s.replace(/\\/g, '/').replace(/\/{2,}/g, '/');
    if (s !== '/') s = s.replace(/\/+$/g, '');
    return s || '/';
}
function filesystemPathsEqual(a, b) {
    const left = normalizeFilesystemPath(a);
    const right = normalizeFilesystemPath(b);
    return left !== '' && left === right;
}
function selectedSetHasPath(selectedSet, path) {
    for (const item of selectedSet) if (filesystemPathsEqual(item, path)) return true;
    return false;
}
function selectedSetAddPath(selectedSet, path) {
    const raw = String(path || '').trim();
    if (!raw) return;
    selectedSetDeletePath(selectedSet, raw);
    selectedSet.add(raw);
}
function selectedSetDeletePath(selectedSet, path) {
    for (const item of [...selectedSet]) {
        if (filesystemPathsEqual(item, path)) selectedSet.delete(item);
    }
}
function replaceSelectedSetFromInput(selectedSet, raw) {
    selectedSet.clear();
    splitPathList(raw).forEach(path => selectedSetAddPath(selectedSet, path));
}
function buildFilesystemPathVariants(path) {
    const raw = String(path == null || path === '' ? '/' : path).trim() || '/';
    const normalized = normalizeFilesystemPath(hostPathToContainer(raw) || raw) || HOST_FILESYSTEM_ROOT;
    const variants = [];
    const push = value => {
        const candidate = String(value || '').trim();
        if (!candidate) return;
        if (!variants.some(existing => filesystemPathsEqual(existing, candidate) || existing === candidate)) {
            variants.push(candidate);
        }
    };
    push(raw);
    push(normalized);
    if (normalized !== '/' && !/^[A-Za-z]:\\$/.test(normalized)) push(normalized + '/');
    if (/^\//.test(normalized)) {
        push('file://' + normalized);
        if (normalized !== '/' && !normalized.endsWith('/')) push('file://' + normalized + '/');
    }
    if (/^[A-Za-z]:\\/.test(normalized)) {
        push('file:///' + normalized.replace(/\\/g, '/'));
    }
    return variants;
}
function fsNodeBestPath(n) {
    const direct = [n?.path,n?.Path,n?.fullPath,n?.FullPath,n?.id,n?.Id,n?.value,n?.Value,n?.target,n?.Target,n?.href,n?.Href,n?.url,n?.Url].map(v=>v==null?'':String(v)).map(v=>v.trim()).filter(Boolean);
    const looksLikePath = s => /[\\/]/.test(s) || /^[A-Za-z]:$/.test(s) || /^file:/i.test(s) || s === '/';
    const pickBest = arr => arr.filter(looksLikePath).sort((a,b)=>b.length-a.length)[0] || '';
    const d = pickBest(direct);
    if (d) return d;
    const vals = [];
    try { for (const v of Object.values(n||{})) if (typeof v === 'string' || typeof v === 'number') vals.push(String(v).trim()); } catch(_) {}
    return pickBest(vals) || direct.sort((a,b)=>b.length-a.length)[0] || '';
}
function fsNodePath(n) { return fsNodeBestPath(n); }
function fsNodeLeaf(n) { const L = n.leaf !== undefined ? n.leaf : n.Leaf; return !!L; }
function fsNodeBasename(fullPath) {
    if (!fullPath) return '—';
    // Normalize slashes, then extract last non-empty segment
    let p = String(fullPath).replace(/\\/g, '/').replace(/\/+$/, '');
    if (p === '') return '/';
    // Windows drive root: C: or C:/
    if (/^[A-Za-z]:$/.test(p)) return p + '\\';
    const idx = p.lastIndexOf('/');
    if (idx === -1) return p; // already a bare name or Windows drive
    const seg = p.slice(idx + 1);
    return seg !== '' ? seg : p;
}
function fsCleanDisplayText(v) {
    if (v == null) return '';
    const raw = String(v).replace(/<[^>]*>/g, ' ').replace(/&nbsp;/gi, ' ').trim();
    return raw.replace(/\s+/g, ' ');
}
function fsNodeText(n) {
    const p = containerPathToHost(fsNodePath(n));
    if (p) return fsNodeBasename(p);
    const explicit = fsCleanDisplayText(n?.text ?? n?.Text ?? n?.name ?? n?.Name ?? n?.label ?? n?.Label ?? n?.display ?? n?.Display ?? n?.Title ?? n?.title ?? n?.basename ?? n?.Basename ?? n?.fileName ?? n?.FileName);
    return explicit || t('unnamed');
}
function parseTargetPath(targetUrl) {
    if (!targetUrl) return null;
    try {
        let u = decodeURIComponent(targetUrl);
        if (!/^file:/i.test(u)) return null;
        u = u.replace(/^file:\/\//i, '').replace(/^localhost\/?/i, '').replace(/^\/+/, '');
        const win = /^([a-zA-Z]):[/\\](.*)$/i.exec(u);
        if (win) return win[1].toUpperCase() + ':\\' + win[2].replace(/\//g, '\\');
        return containerPathToHost('/' + u.replace(/\\/g, '/').replace(/\/{2,}/g, '/'));
    } catch(_) {
        return targetUrl;
    }
}
async function fetchFilesystemList(path, onlyFolders = true) {
    const p = normalizeFilesystemPath(path);
    if (p && p[0] === '/') {
        try {
            const hostList = await fetchHostFilesystemList(p, onlyFolders, false);
            if (Array.isArray(hostList)) return hostList;
        } catch (e) {
            /* fall through to Duplicati */
        }
    }
    const query = `onlyFolders=${onlyFolders ? 'true' : 'false'}&showHidden=false`;
    let lastError = null;
    for (const candidate of buildFilesystemPathVariants(path)) {
        const attempts = [
            { endpoint: `filesystem?${query}`, method: 'POST', data: { path: candidate } },
            { endpoint: `filesystem?${query}&path=${encodeURIComponent(candidate)}`, method: 'POST', data: { path: candidate } },
            { endpoint: `filesystem?${query}&path=${encodeURIComponent(candidate)}`, method: 'GET' },
            { endpoint: 'filesystem', method: 'POST', data: { path: candidate } },
        ];
        for (const attempt of attempts) {
            try {
                const arr = await apiCall(attempt.endpoint, attempt.method, attempt.data ?? null);
                if (Array.isArray(arr)) return arr;
            } catch (e) {
                lastError = e;
            }
        }
    }
    if (lastError) throw lastError;
    return [];
}
function initCreateSourceTree() { const root = document.getElementById('sourceTreeRoot'); if (!root) return; createSourceSelected = new Set(); const inp = document.getElementById('newSourcePath'); if (inp) replaceSelectedSetFromInput(createSourceSelected, inp.value); const anchor = getTreePanelRememberedPath('createSource') || getBrowseAnchorForSourceField(inp ? inp.value : ''); setTreePanelCurrentPath('createSource', anchor); root.innerHTML = ''; renderFilesystemNodes(root, anchor, 0, createSourceSelected, syncNewSourcePathInput, true).then(() => { const sf = document.getElementById('sourceTreeFilter'); if (sf) applyFsTreeFilter(root, sf.value); }).catch(err => { root.innerHTML = `<div class="source-tree-hint" style="color:var(--red);">${esc(err.message)}</div>`; }); }
function initDestTree() {
    const root = document.getElementById('destTreeRoot'); if (!root) return;
    const tin = document.getElementById('newTargetUrl');
    const raw = tin ? tin.value : '';
    if (raw) destSelectedPath = seedDestSelectedSet(new Set(), raw) || destSelectedPath;
    const anchor = getTreePanelRememberedPath('createDest') || getBrowseAnchorForDestField(raw || destSelectedPath);
    setTreePanelCurrentPath('createDest', anchor);
    updateDestExplorerUi('createDest');
    root.innerHTML = '';
    const selectedSet = new Set();
    if (destSelectedPath || raw) seedDestSelectedSet(selectedSet, destSelectedPath || raw);
    renderFilesystemNodes(root, anchor, 0, selectedSet, () => {
        destSelectedPath = [...selectedSet][0] || destSelectedPath;
        syncDestPathInput();
    }, false, true).then(() => { const sf = document.getElementById('destTreeFilter'); if (sf) applyFsTreeFilter(root, sf.value); }).catch(err => { root.innerHTML = `<div class="source-tree-hint" style="color:var(--red);">${esc(err.message)}</div>`; });
}
async function renderFilesystemNodes(container, path, depth, selectedSet, syncCallback, isMultiSelect, isDest = false) {
    container.innerHTML = '';
    container.dataset.currentPath = normalizeFilesystemPath(path) || '/';
    let list = [];
    try { list = await fetchFilesystemList(path); } catch (e) { container.innerHTML = `<div class="source-tree-hint" style="color:var(--red);">${esc(e.message)}</div>`; return; }
    if (!list.length) { container.innerHTML = `<div class="source-tree-hint" style="color:var(--text-muted);font-style:italic;">${esc(t('emptyFolder'))}</div>`; return; }
    for (const node of list) { container.appendChild(buildFsTreeItem(node, depth, selectedSet, syncCallback, isMultiSelect, isDest)); }
}
function buildFsTreeItem(node, depth, selectedSet, syncCallback, isMultiSelect, isDest) {
    
    const nodePath = containerPathToHost(fsNodePath(node));
    const displayName = fsNodeText(node);
    const leaf = fsNodeLeaf(node);
    const item = document.createElement('div');
    item.className = 'fs-tree-item';
    item.dataset.fsPath = nodePath || '';
    const row = document.createElement('div');
    row.className = 'fs-tree-row';
    row.style.paddingLeft = (4 + depth * 16) + 'px';
    // Caret / expand toggle
    const caret = document.createElement('button');
    caret.type = 'button';
    caret.className = 'fs-tree-caret';
    caret.setAttribute('aria-expanded', 'false');
    if (leaf) {
        caret.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>';
        caret.style.opacity = '.3';
        caret.disabled = true;
    } else {
        caret.innerHTML = '<svg class="caret-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="transition:transform .18s;"><polyline points="9 18 15 12 9 6"/></svg>';
    }
    // Folder icon
    const icon = document.createElement('span');
    icon.className = 'fs-tree-icon';
    icon.style.cssText = 'flex-shrink:0;color:var(--amber);margin-right:2px;';
    icon.innerHTML = leaf
        ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted)"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>'
        : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
    // Checkbox / radio
    const chk = document.createElement('input');
    chk.type = isMultiSelect ? 'checkbox' : 'radio';
    chk.name = isDest ? 'destPath' : 'sourcePath';
    chk.className = 'fs-tree-check';
    const uid = 'fstree-' + Math.random().toString(36).slice(2);
    chk.id = uid;
    const nameEl = document.createElement('span');
    nameEl.className = 'fs-tree-label';
    nameEl.textContent = displayName || t('unnamed');
    nameEl.title = nodePath || nameEl.textContent;
    nameEl.style.cursor = 'pointer';
    if (isMultiSelect) {
        chk.checked = selectedSetHasPath(selectedSet, nodePath);
        chk.addEventListener('change', () => {
            if (chk.checked) selectedSetAddPath(selectedSet, nodePath);
            else selectedSetDeletePath(selectedSet, nodePath);
            syncCallback();
        });
    } else {
        chk.checked = selectedSetHasPath(selectedSet, nodePath);
        chk.addEventListener('change', () => {
            if (chk.checked) {
                selectedSet.clear();
                selectedSetAddPath(selectedSet, nodePath);
                syncCallback();
                const panel = chk.closest('.source-tree-panel,.dest-tree-panel,[id$="TreePanel"]');
                if (panel) panel.querySelectorAll('input[type="radio"]').forEach(r => { if (r !== chk && r.checked) r.checked = false; });
            }
        });
    }
    // Children container
    const childBox = document.createElement('div');
    childBox.className = 'fs-tree-children';
    childBox.style.display = 'none';
    if (!leaf) {
        const resolvePanelKey = () => getTreePanelKeyFromElement(row);
        const navigateIntoFolder = () => {
            const panelKey = resolvePanelKey();
            if (!panelKey || !nodePath) return;
            setTreePanelCurrentPath(panelKey, nodePath);
            if (!isMultiSelect && !isDestTreePanel(panelKey)) {
                selectedSet.clear();
                selectedSetAddPath(selectedSet, nodePath);
                syncCallback();
            }
        };
        if (isDest) {
            caret.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                const panelKey = resolvePanelKey();
                enterDestPanelFolder(panelKey, nodePath, selectedSet, syncCallback);
            });
            row.addEventListener('dblclick', e => {
                if (e.target === chk) return;
                e.preventDefault();
                e.stopPropagation();
                const panelKey = resolvePanelKey();
                enterDestPanelFolder(panelKey, nodePath, selectedSet, syncCallback);
            });
            nameEl.addEventListener('dblclick', e => {
                e.preventDefault();
                e.stopPropagation();
                const panelKey = resolvePanelKey();
                enterDestPanelFolder(panelKey, nodePath, selectedSet, syncCallback);
            });
            icon.style.cursor = 'pointer';
            icon.addEventListener('dblclick', e => {
                e.preventDefault();
                e.stopPropagation();
                const panelKey = resolvePanelKey();
                enterDestPanelFolder(panelKey, nodePath, selectedSet, syncCallback);
            });
        } else {
        const toggleExpand = async () => {
            const open = childBox.style.display !== 'none';
            const svg = caret.querySelector('.caret-icon');
            if (open) {
                childBox.style.display = 'none';
                caret.setAttribute('aria-expanded', 'false');
                if (svg) svg.style.transform = '';
                return;
            }
            childBox.style.display = 'block';
            caret.setAttribute('aria-expanded', 'true');
            if (svg) svg.style.transform = 'rotate(90deg)';
            if (childBox.dataset.loaded) return;
            caret.classList.add('loading');
            childBox.innerHTML = `<div class="source-tree-hint" style="font-style:italic;">${esc(t('loading'))}</div>`;
            try {
                await renderFilesystemNodes(childBox, nodePath, depth + 1, selectedSet, syncCallback, isMultiSelect, isDest);
                childBox.dataset.loaded = '1';
                const panelRoot = childBox.closest('.source-tree-root');
                const panel = childBox.closest('.source-tree-panel');
                const flt = panel && panel.querySelector('.fs-tree-filter');
                if (panelRoot && flt && flt.value) applyFsTreeFilter(panelRoot, flt.value);
            } catch (err) {
                childBox.innerHTML = `<div class="source-tree-hint" style="color:var(--red);">${esc(err.message)}</div>`;
            } finally {
                caret.classList.remove('loading');
            }
        };
        caret.addEventListener('click', e => { e.stopPropagation(); toggleExpand(); });
        nameEl.addEventListener('click', e => { e.stopPropagation(); toggleExpand(); });
        icon.style.cursor = 'pointer';
        icon.addEventListener('click', e => { e.stopPropagation(); toggleExpand(); });
        row.addEventListener('dblclick', async e => {
            if (e.target === chk || e.target === caret) return;
            e.preventDefault();
            e.stopPropagation();
            await toggleExpand();
            navigateIntoFolder();
        });
        }
    }
    row.addEventListener('click', e => { if (e.target === chk || e.target === caret) return; chk.click(); });
    row.appendChild(caret); row.appendChild(icon); row.appendChild(nameEl); row.appendChild(chk);
    item.appendChild(row); item.appendChild(childBox);
    return item;
}
function getDuplicatiUrl() {
    let baseUrl = currentHost.replace(/\/+$/, '');
    if (!baseUrl.includes('/ngclient/')) {
        baseUrl = baseUrl + '/ngclient/index.html';
    }
    return baseUrl;
}
function openDuplicatiInterface() {
    if (!currentHost) { toast('Set Backup host and token in the main Admin Panel settings.', 'error'); return; }
    const url = getDuplicatiUrl();
    const interfaceFrame = document.getElementById('interfaceFrame');
    interfaceFrame.src = url;
    showModal('interfaceModal');
}
function retryInterfaceLoad() {
    if (!currentHost) { toast(t('serverHost') + ' - ' + t('connectToLoad'), 'error'); return; }
    const url = getDuplicatiUrl();
    document.getElementById('interfaceFrame').src = url;
}
function openInNewWindow() {
    if (!currentHost) { toast(t('serverHost') + ' - ' + t('connectToLoad'), 'error'); return; }
    const url = getDuplicatiUrl();
    const timestamp = new Date().getTime();
    const finalUrl = url + (url.includes('?') ? '&' : '?') + '_=' + timestamp;
    window.open(finalUrl, '_blank');
}
function closeInterfaceModal() {
    const interfaceFrame = document.getElementById('interfaceFrame');
    interfaceFrame.src = '';
    closeModal('interfaceModal');
}
function toggleMobileMenu() { const sidebar = document.getElementById('mobileSidebar'); sidebar.classList.toggle('open'); }
function closeMobileMenu() { const sidebar = document.getElementById('mobileSidebar'); sidebar.classList.remove('open'); }
function loading(show, label = t('loading')) { document.getElementById('spinnerLabel').textContent = label; document.getElementById('loadingOverlay').classList.toggle('active', show); }
function toast(msg, type = 'info') { const icons = { success:`<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>`, error:`<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M4.929 4.929 19.07 19.071"/></svg>`, warning:`<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>`, info:`<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>`, }; const colorMap = { success:'var(--green)', error:'var(--red)', warning:'var(--amber)', info:'var(--accent)' }; const el = document.createElement('div'); el.className = `toast ${type}`; el.innerHTML = `<span class="toast-icon" style="color:${colorMap[type]||colorMap.info}">${icons[type]||''}</span>${esc(msg)}`; document.getElementById('toast-container').appendChild(el); setTimeout(() => el.remove(), 4500); }
function esc(s) { if (s == null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtBytes(bytes) { if (bytes == null || isNaN(bytes)) return '—'; if (bytes === 0) return '0 B'; const k = 1024, sizes = ['B','KiB','MiB','GiB','TiB']; const i = Math.floor(Math.log(Math.abs(bytes)) / Math.log(k)); return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i]; }
function formatDuration(dur) { if (!dur) return '—'; const m = String(dur).match(/^(\d+):(\d+):(\d+)/); if (!m) return String(dur); const h = parseInt(m[1]), min = parseInt(m[2]), sec = parseInt(m[3]); let out = ''; if (h) out += h + 'h '; if (min) out += min + 'm '; out += sec + 's'; return out.trim(); }
function populateExplorerSelector() {
    const sel = document.getElementById('explorerBackupSelector'); if (!sel) return;
    sel.innerHTML = `<option value="">${t('selectBackup')}</option>` + backupsList.map(b => `<option value="${b.ID}">${esc(b.Name)}</option>`).join('');
}
function initExplorer(id) {
    const b = backupsList.find(x => String(x.ID) === String(id));
    const tree = document.getElementById('fbTree');
    const bc = document.getElementById('fbBreadcrumb');
    if (!b || !tree) return;

    const targetUrl = b.TargetURL || '';
    if (!targetUrl) {
        tree.innerHTML = '<div class="empty-state"><p>' + esc(t('noSourceFolders')) + '</p></div>';
        return;
    }
    const rootPath = parseTargetPath(targetUrl);
    if (!rootPath) {
        tree.innerHTML = '<div class="empty-state"><p>' + esc(t('connectToBrowse')) + '</p></div>';
        return;
    }

    if (rootPath) {
        renderFbTree(tree, rootPath, bc);
        loadDiskSpace(rootPath);
    }
}
async function loadDiskSpaceForModal(path) {
    const diskPanel = document.getElementById('infoFbDiskSpace');
    if (!diskPanel) return;
    diskPanel.style.display = 'block';
    diskPanel.innerHTML = '<div class="fb-loading">' + esc(t('loading')) + '</div>';
    try {
        const res = await fetch('?disk=' + encodeURIComponent(path));
        if (!res.ok) throw new Error('Failed to get disk space');
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        
        const pctUsed = data.percentUsed || 0;
        const colorClass = pctUsed > 90 ? 'var(--red)' : pctUsed > 75 ? 'var(--amber)' : 'var(--green)';
        
        diskPanel.innerHTML = `
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="2"/></svg>
                <span style="font-size:12px;font-weight:600;color:var(--text-secondary);">${t('diskSpace')}</span>
                <span style="font-size:11px;color:var(--text-muted);margin-left:auto;">${esc(data.path)}</span>
            </div>
            <div class="progress-track" style="height:10px;margin-bottom:8px;">
                <div class="progress-fill" style="width:${pctUsed}%;background:linear-gradient(90deg,${colorClass},var(--accent));"></div>
            </div>
            <div style="display:flex;justify-content:space-between;gap:16px;font-size:11px;">
                <span style="color:var(--text-muted);">${t('used')}: <span style="color:var(--text-primary);font-family:'JetBrains Mono',monospace;">${fmtBytes(data.used)}</span></span>
                <span style="color:var(--text-muted);">${t('free')}: <span style="color:${(100 - pctUsed) > 25 ? 'var(--green)' : 'var(--red)'};font-family:'JetBrains Mono',monospace;">${fmtBytes(data.free)}</span></span>
                <span style="color:var(--text-muted);">${t('total')}: <span style="color:var(--text-primary);font-family:'JetBrains Mono',monospace;">${fmtBytes(data.total)}</span></span>
            </div>
        `;
    } catch(e) {
        diskPanel.innerHTML = `<div style="font-size:11px;color:var(--text-muted);padding:8px 0;">${esc(e.message)}</div>`;
    }
}
async function loadDiskSpace(path) {
    const diskPanel = document.getElementById('fbDiskSpace');
    if (!diskPanel) return;
    diskPanel.style.display = 'block';
    diskPanel.innerHTML = '<div class="fb-loading">' + esc(t('loading')) + '</div>';
    try {
        const res = await fetch('?disk=' + encodeURIComponent(path));
        if (!res.ok) throw new Error('Failed to get disk space');
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        
        const pctUsed = data.percentUsed || 0;
        const colorClass = pctUsed > 90 ? 'var(--red)' : pctUsed > 75 ? 'var(--amber)' : 'var(--green)';
        
        diskPanel.innerHTML = `
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="2"/></svg>
                <span style="font-size:12px;font-weight:600;color:var(--text-secondary);">${t('diskSpace')}</span>
                <span style="font-size:11px;color:var(--text-muted);margin-left:auto;">${esc(data.path)}</span>
            </div>
            <div class="progress-track" style="height:10px;margin-bottom:8px;">
                <div class="progress-fill" style="width:${pctUsed}%;background:linear-gradient(90deg,${colorClass},var(--accent));"></div>
            </div>
            <div style="display:flex;justify-content:space-between;gap:16px;font-size:11px;">
                <span style="color:var(--text-muted);">${t('used')}: <span style="color:var(--text-primary);font-family:'JetBrains Mono',monospace;">${fmtBytes(data.used)}</span></span>
                <span style="color:var(--text-muted);">${t('free')}: <span style="color:${(100 - pctUsed) > 25 ? 'var(--green)' : 'var(--red)'};font-family:'JetBrains Mono',monospace;">${fmtBytes(data.free)}</span></span>
                <span style="color:var(--text-muted);">${t('total')}: <span style="color:var(--text-primary);font-family:'JetBrains Mono',monospace;">${fmtBytes(data.total)}</span></span>
            </div>
        `;
    } catch(e) {
        diskPanel.innerHTML = `<div style="font-size:11px;color:var(--text-muted);padding:8px 0;">${esc(e.message)}</div>`;
    }
}
async function renderFbTree(container, path, breadcrumbEl) {
    if (!container) return;
    container.innerHTML = '<div class="fb-loading">' + esc(t('loadingFiles')) + '</div>';
    if (breadcrumbEl) renderFbBreadcrumb(breadcrumbEl, path, container);
    try {
        const list = await fetchFilesystemList(path, false);
        container.innerHTML = '';
        if (!list.length) { container.innerHTML = '<div class="fb-loading">' + esc(t('emptyFolder')) + '</div>'; return; }
        
        // Create table structure
        const tableWrap = document.createElement('div');
        tableWrap.className = 'fb-table-wrap';
        const table = document.createElement('table');
        table.className = 'fb-table';
        table.innerHTML = `<thead><tr><th style="width:40px;"></th><th>${esc(t('name'))}</th><th style="width:180px;">${esc(t('type'))}</th><th style="width:200px;">${esc(t('time'))}</th></tr></thead><tbody></tbody>`;
        const tbody = table.querySelector('tbody');
        
        list.forEach(node => {
            const row = buildFbTableRow(node, path, container, breadcrumbEl);
            if (row) tbody.appendChild(row);
        });
        
        tableWrap.appendChild(table);
        container.appendChild(tableWrap);
    } catch(e) { container.innerHTML = `<div class="fb-loading" style="color:var(--red);">${esc(e.message)}</div>`; }
}
function renderFbBreadcrumb(el, path, container) {
    const parts = path.replace(/\\/g,'/').split('/').filter(Boolean);
    let html = `<span class="fb-breadcrumb-item" onclick="renderFbTree(document.getElementById('${container.id}'),'/',document.getElementById('${el.id}'))">/</span>`;
    let acc = '';
    parts.forEach((p,i) => {
        const isWin = i === 0 && /^[a-z]:$/i.test(p);
        acc = acc ? acc + '/' + p : (isWin ? p + ':' : p);
        const capAcc = acc;
        html += `<span class="fb-breadcrumb-sep">›</span><span class="fb-breadcrumb-item" onclick="renderFbTree(document.getElementById('${container.id}'),'${capAcc.replace(/'/g,"\\'")}',document.getElementById('${el.id}'))">${esc(p)}</span>`;
    });
    el.innerHTML = html;
}
function buildFbTableRow(node, parentPath, container, breadcrumbEl) {
    const nodePath = fsNodePath(node);
    const displayName = fsNodeText(node);
    const leaf = fsNodeLeaf(node);
    
    // Get date information from node (Duplicati API returns LastWriteTime, CreationTime, etc.)
    const lastWriteTime = node.LastWriteTime || node.lastWriteTime || node.Modified || null;
    const createTime = node.CreationTime || node.creationTime || node.Created || null;
    const dateStr = lastWriteTime || createTime || '—';
    const formattedDate = dateStr !== '—' ? formatDate(dateStr) : '—';
    
    const tr = document.createElement('tr');
    
    // Caret cell (for folders)
    const caretTd = document.createElement('td');
    const caret = document.createElement('button');
    caret.className = 'fb-table-caret';
    caret.type = 'button';
    if (leaf) {
        caret.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:.3"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>';
        caret.disabled = true;
    } else {
        caret.innerHTML = '<svg class="fb-caret-svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="transition:transform .18s;"><polyline points="9 18 15 12 9 6"/></svg>';
    }
    caretTd.appendChild(caret);
    
    // Name cell with icon
    const nameTd = document.createElement('td');
    const nameWrapper = document.createElement('div');
    nameWrapper.style.cssText = 'display:flex;align-items:center;gap:8px;';
    
    const icon = document.createElement('span');
    icon.className = leaf ? 'fb-table-icon fb-table-icon-file' : 'fb-table-icon';
    icon.innerHTML = leaf
        ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>'
        : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
    
    const name = document.createElement('span');
    name.className = 'fb-table-name';
    name.textContent = displayName;
    name.title = nodePath + ' (' + (leaf ? t('file') : t('folder')) + ')';
    name.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:400px;';
    
    nameWrapper.appendChild(icon);
    nameWrapper.appendChild(name);
    nameTd.appendChild(nameWrapper);
    
    // Type cell
    const typeTd = document.createElement('td');
    typeTd.textContent = leaf ? t('file') : t('folder');
    typeTd.style.color = leaf ? 'var(--text-muted)' : 'var(--accent)';
    typeTd.style.fontWeight = leaf ? '400' : '500';
    typeTd.style.fontSize = '11px';
    typeTd.style.textTransform = 'uppercase';
    
    // Date cell
    const dateTd = document.createElement('td');
    dateTd.className = 'fb-table-date';
    dateTd.textContent = formattedDate;
    dateTd.title = lastWriteTime ? `Modified: ${formattedDate}` : `Created: ${formattedDate}`;
    
    tr.appendChild(caretTd);
    tr.appendChild(nameTd);
    tr.appendChild(typeTd);
    tr.appendChild(dateTd);
    
    // Add expand functionality for folders
    if (!leaf) {
        const childRow = document.createElement('tr');
        childRow.className = 'fb-children-row';
        const childCell = document.createElement('td');
        childCell.colSpan = 4;
        childCell.style.cssText = 'padding:0;border:none;';
        const childBox = document.createElement('div');
        childBox.className = 'fb-table-children';
        childBox.style.cssText = 'display:none;padding-left:24px;';
        childCell.appendChild(childBox);
        childRow.appendChild(childCell);
        
        const expandFn = async () => {
            const open = childBox.style.display !== 'none';
            const svg = caret.querySelector('.fb-caret-svg');
            if (open) {
                childBox.style.display = 'none';
                caret.classList.remove('open');
                if (svg) svg.style.transform = '';
                tr.nextSibling?.remove();
                return;
            }
            childBox.style.display = 'block';
            caret.classList.add('open');
            if (svg) svg.style.transform = 'rotate(90deg)';
            
            if (childBox.dataset.loaded) return;
            childBox.innerHTML = `<div class="fb-loading">${esc(t('loadingFiles'))}</div>`;
            try {
                const list = await fetchFilesystemList(nodePath, false);
                childBox.innerHTML = '';
                if (!list.length) {
                    childBox.innerHTML = '<div class="fb-loading" style="padding:8px 12px;">' + esc(t('emptyFolder')) + '</div>';
                } else {
                    const innerTable = document.createElement('table');
                    innerTable.className = 'fb-table';
                    innerTable.style.cssText = 'margin:8px 0;';
                    const innerTbody = document.createElement('tbody');
                    list.forEach(n => {
                        const subRow = buildFbTableRow(n, nodePath, container, breadcrumbEl);
                        if (subRow) innerTbody.appendChild(subRow);
                    });
                    innerTable.appendChild(innerTbody);
                    childBox.innerHTML = '';
                    childBox.appendChild(innerTable);
                }
                childBox.dataset.loaded = '1';
            } catch(e) {
                childBox.innerHTML = `<div class="fb-loading" style="color:var(--red);padding:8px 12px;">${esc(e.message)}</div>`;
            }
        };
        
        caret.addEventListener('click', e => {
            e.stopPropagation();
            expandFn();
            // Insert child row after current row
            if (childBox.style.display !== 'none' && !tr.nextSibling?.classList?.contains('fb-children-row')) {
                tr.parentNode.insertBefore(childRow, tr.nextSibling);
            }
        });
        
        nameTd.style.cursor = 'pointer';
        nameTd.addEventListener('click', expandFn);
    }
    
    return tr;
}
function buildFbItem(node, parentPath, container, breadcrumbEl) {
    const nodePath = fsNodePath(node);
    const displayName = fsNodeText(node);
    const leaf = fsNodeLeaf(node);
    const item = document.createElement('div');
    item.className = 'fb-item';
    const row = document.createElement('div');
    row.className = 'fb-row';
    const caret = document.createElement('button');
    caret.className = 'fb-caret';
    caret.type = 'button';
    if (leaf) { caret.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:.3"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>'; caret.disabled = true; }
    else { caret.innerHTML = '<svg class="fb-caret-svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="transition:transform .18s;"><polyline points="9 18 15 12 9 6"/></svg>'; }
    const icon = document.createElement('span');
    icon.className = leaf ? 'fb-icon fb-icon-file' : 'fb-icon';
    icon.innerHTML = leaf
        ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>'
        : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
    const name = document.createElement('span');
    name.className = 'fb-name';
    name.textContent = displayName;
    name.title = nodePath + ' (' + (leaf ? t('file') : t('folder')) + ')';
    const childBox = document.createElement('div');
    childBox.className = 'fb-children';
    childBox.style.display = 'none';
    if (!leaf) {
        const expandFn = async () => {
            const open = childBox.style.display !== 'none';
            const svg = caret.querySelector('.fb-caret-svg');
            if (open) { childBox.style.display = 'none'; caret.classList.remove('open'); if (svg) svg.style.transform = ''; return; }
            childBox.style.display = 'block'; caret.classList.add('open'); if (svg) svg.style.transform = 'rotate(90deg)';
            if (childBox.dataset.loaded) return;
            childBox.innerHTML = `<div class="fb-loading">${esc(t('loadingFiles'))}</div>`;
            try {
                const list = await fetchFilesystemList(nodePath, false);
                childBox.innerHTML = '';
                if (!list.length) { childBox.innerHTML = '<div class="fb-loading">' + esc(t('emptyFolder')) + '</div>'; }
                else { list.forEach(n => childBox.appendChild(buildFbItem(n, nodePath, container, breadcrumbEl))); }
                childBox.dataset.loaded = '1';
            } catch(e) { childBox.innerHTML = `<div class="fb-loading" style="color:var(--red);">${esc(e.message)}</div>`; }
        };
        caret.addEventListener('click', e => { e.stopPropagation(); expandFn(); });
        row.addEventListener('click', expandFn);
    }
    row.appendChild(caret); row.appendChild(icon); row.appendChild(name);
    item.appendChild(row); item.appendChild(childBox);
    return item;
}
document.addEventListener('DOMContentLoaded', () => { applyTranslations(); loadSavedCredentials().catch(() => {}); });
</script>
</body>
</html>
