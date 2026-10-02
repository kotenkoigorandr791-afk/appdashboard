<?php
declare(strict_types=1);
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
require_once __DIR__ . '/../../lib/app_session.php';
app_session_start();
header('Content-Type: application/json; charset=utf-8');

const RECIPES_IMAGE_FETCH_MAX_BYTES = 10485760;
require_once __DIR__ . '/../../lib/app_db.php';
require_once __DIR__ . '/../../lib/redis.php';
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
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['role'])) {
            return (int) $row['role'];
        }
    } catch (Exception $e) {
    }
    return 0;
}

function recipes_bearer_email_from_auth(): ?string {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^\s*Bearer\s+([A-Fa-f0-9]{64})\s*$/', $auth, $m)) {
        return null;
    }
    $db = getDB();
    if (!$db) {
        return null;
    }
    try {
        require_once __DIR__ . '/../../lib/public_api_auth.php';
        if (function_exists('public_api_ensure_token_schema')) {
            public_api_ensure_token_schema($db);
        }
        $tokenHash = hash('sha256', $m[1]);
        $stmt = $db->prepare("SELECT email FROM client_auth_tokens WHERE token_hash = :h AND app_id = 'recipes' AND revoked = 0 AND expires_at > :now LIMIT 1");
        $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['email'])) {
            $upd = $db->prepare('UPDATE client_auth_tokens SET last_used_at = :t WHERE token_hash = :h');
            $upd->bindValue(':t', time(), PDO::PARAM_INT);
            $upd->bindValue(':h', $tokenHash, PDO::PARAM_STR);
            $upd->execute();
            return (string)$row['email'];
        }
    } catch (Throwable $e) {
    }
    return null;
}

function recipes_resolve_session_role(): int {
    $db = getDB();
    if (!$db) {
        return 0;
    }

    $bearerEmail = recipes_bearer_email_from_auth();
    if (!empty($bearerEmail)) {
        return getUserRoleByEmail($bearerEmail);
    }

    $getEmail = static function (string $sid) use ($db): ?string {
        $sid = trim($sid);
        if ($sid === '') return null;
        if (function_exists('app_redis')) {
            try {
                $redis = app_redis();
                if ($redis) {
                    $raw = $redis->get(app_redis_key('active_session:' . $sid));
                    if ($raw) {
                        $row = json_decode((string)$raw, true);
                        if (is_array($row) && !empty($row['email'])) {
                            return (string)$row['email'];
                        }
                    }
                }
            } catch (Throwable $e) {
            }
        }
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id LIMIT 1');
            $stmt->bindValue(':session_id', $sid, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['email'])) {
                return (string)$row['email'];
            }
        } catch (Throwable $e) {
        }
        return null;
    };

    $sid = session_id();
    if ($sid) {
        $email = $getEmail((string)$sid);
        if (!empty($email)) {
            return getUserRoleByEmail($email);
        }
    }
    if (isset($_COOKIE['PHPSESSID'])) {
        $cookieSid = (string)$_COOKIE['PHPSESSID'];
        $email = $getEmail($cookieSid);
        if (!empty($email)) {
            return getUserRoleByEmail($email);
        }
    }
    if (!empty($_SESSION['user']['email'])) {
        return getUserRoleByEmail((string)$_SESSION['user']['email']);
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
