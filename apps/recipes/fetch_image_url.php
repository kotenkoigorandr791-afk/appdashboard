<?php
declare(strict_types=1);
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}

session_start();
header('Content-Type: application/json; charset=utf-8');

const RECIPES_IMAGE_FETCH_MAX_BYTES = 10485760;
require_once __DIR__ . '/../../lib/app_db.php';
function getDB(): ?AppDatabase {
    static $db = null;
    if ($db === null) {
        try {
            $db = app_main_db();
        } catch (Exception $e) {
            return null;
        }
    }
    return $db;
}

function getUserRoleByEmail(string $email): int {
    $db = getDB();
    if (!$db || $email === '') {
        return 0;
    }
    try {
        $stmt = $db->prepare('SELECT role FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['role'])) {
            return (int) $row['role'];
        }
    } catch (Exception $e) {
    }
    return 0;
}

function recipes_resolve_session_role(): int {
    $db = getDB();
    if (!$db) {
        return 0;
    }
    $sid = session_id();
    if ($sid) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
            $stmt->bindValue(':session_id', $sid, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && !empty($row['email'])) {
                return getUserRoleByEmail($row['email']);
            }
        } catch (Exception $e) {
        }
    }
    if (isset($_COOKIE['PHPSESSID'])) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
            $stmt->bindValue(':session_id', (string) $_COOKIE['PHPSESSID'], SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && !empty($row['email'])) {
                return getUserRoleByEmail($row['email']);
            }
        } catch (Exception $e) {
        }
    }
    return 0;
}

function recipes_url_fetch_safe(string $url): bool {
    $p = parse_url($url);
    if ($p === false || empty($p['scheme']) || empty($p['host'])) {
        return false;
    }
    if (!in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
        return false;
    }
    $host = strtolower($p['host']);
    if ($host === 'localhost' || str_ends_with($host, '.localhost') || $host === '0') {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    $ip = @gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'method_not_allowed']);
    exit;
}

if (recipes_resolve_session_role() < 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$url = is_array($data) && isset($data['url']) ? trim((string) $data['url']) : '';
if ($url === '') {
    echo json_encode(['success' => false, 'message' => 'invalid_url']);
    exit;
}

if (strlen($url) > 2048 || !recipes_url_fetch_safe($url)) {
    echo json_encode(['success' => false, 'message' => 'invalid_url']);
    exit;
}

if (!function_exists('curl_init')) {
    echo json_encode(['success' => false, 'message' => 'curl_missing']);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERAGENT => 'RecipeManager/1.0',
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_MAXFILESIZE => RECIPES_IMAGE_FETCH_MAX_BYTES,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($body === false || $code < 200 || $code >= 300) {
    echo json_encode(['success' => false, 'message' => 'fetch_failed', 'detail' => $err ?: null]);
    exit;
}

if (strlen($body) > RECIPES_IMAGE_FETCH_MAX_BYTES) {
    echo json_encode(['success' => false, 'message' => 'too_large']);
    exit;
}

$info = @getimagesizefromstring($body);
if ($info === false || empty($info['mime']) || strpos((string) $info['mime'], 'image/') !== 0) {
    echo json_encode(['success' => false, 'message' => 'not_image']);
    exit;
}

$mime = $info['mime'];
$ext = 'jpg';
if ($mime === 'image/png') {
    $ext = 'png';
} elseif ($mime === 'image/gif') {
    $ext = 'gif';
} elseif ($mime === 'image/webp') {
    $ext = 'webp';
}

echo json_encode([
    'success' => true,
    'mime' => $mime,
    'filename' => 'fetched.' . $ext,
    'data' => base64_encode($body),
], JSON_UNESCAPED_UNICODE);
