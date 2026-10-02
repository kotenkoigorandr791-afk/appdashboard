<?php
require_once APP_ROOT . '/project/oauth.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$parts = public_api_parse_path();
$service = strtolower((string)($parts[1] ?? ''));
$email = (string)($_SESSION['user']['email'] ?? '');
$refresh = (string)($_GET['refresh'] ?? '') === '1';
if ($email === '') {
    public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
}
if ($method !== 'GET') {
    public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if ($service === 'immich') {
    $out = integrations_immich_credentials($email, $refresh);
    public_api_json($out, !empty($out['ok']) ? 200 : 503);
}
if ($service === 'gitea') {
    $out = integrations_gitea_credentials($email, $refresh);
    public_api_json($out, !empty($out['ok']) ? 200 : 503);
}
if ($service === 'kanboard') {
    $out = integrations_kanboard_credentials($email, $refresh);
    public_api_json($out, !empty($out['ok']) ? 200 : 503);
}
if ($service === 'sentry') {
    $out = integrations_sentry_credentials($email, $refresh);
    public_api_json($out, !empty($out['ok']) ? 200 : 503);
}
public_api_json(['ok' => false, 'error' => 'unknown_integration'], 404);

function integrations_meta_get(string $key): string {
    $db = getDB();
    if (!$db) return '';
    try {
        $stmt = $db->prepare('SELECT value FROM app_meta WHERE key = :k LIMIT 1');
        $stmt->bindValue(':k', $key);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return '';
        $v = $row['value'] ?? '';
        return is_string($v) ? $v : '';
    } catch (Throwable $e) {
        return '';
    }
}

function integrations_meta_set(string $key, string $value): void {
    $db = getDB();
    if (!$db) return;
    monitorRetentionSetMeta($db, $key, $value);
}

function integrations_public_service_url(string $url, string $sub): string {
    if (!str_ends_with(parse_url($url, PHP_URL_HOST) ?: '', '.localhost')) return $url;
    $originHost = function_exists('oauth_public_origin') ? (string)(parse_url(oauth_public_origin(), PHP_URL_HOST) ?: '') : '';
    $host = strtolower((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? '';
    if ($originHost !== '' && $originHost !== 'localhost' && !str_ends_with($originHost, '.localhost')) $host = $originHost;
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) $host = trim((string)(getenv('BASE_DOMAIN') ?: 'serverkherson.xyz'));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) return $url;
    $parts = explode('.', strtolower($host));
    $base = count($parts) > 2 ? implode('.', array_slice($parts, -2)) : $host;
    return 'https://' . $sub . '.' . $base;
}

function integrations_immich_url(): string {
    $url = trim((string)(getenv('IMMICH_MCP_URL') ?: getenv('IMMICH_INTERNAL_URL') ?: getenv('IMMICH_PUBLIC_URL') ?: ''));
    if ($url === '' && function_exists('app_public_url')) $url = app_public_url('photo');
    $url = integrations_public_service_url($url, 'photo');
    return rtrim($url, '/');
}

function integrations_sentry_url(): string {
    $url = trim((string)(getenv('SENTRY_MCP_URL') ?: getenv('SENTRY_PUBLIC_URL') ?: (function_exists('app_public_url') ? app_public_url('sentry') : '')));
    $url = integrations_public_service_url($url, 'sentry');
    return rtrim($url, '/');
}

function integrations_sentry_create_token(string $email): ?string {
    $py = <<<'PY'
from django.apps import apps
from django.contrib.auth import get_user_model
email = "__EMAIL__"
User = get_user_model()
user = User.objects.filter(email__iexact=email).first() or User.objects.filter(is_superuser=True).first() or User.objects.first()
if not user:
    raise SystemExit(2)
Token = apps.get_model("api_tokens", "APIToken")
Token.objects.filter(user=user, label="MCP").delete()
token = Token.objects.create(user=user, label="MCP")
token.add_permissions(["project:read", "event:read", "event:write"])
print(token.token)
PY;
    $py = str_replace('__EMAIL__', str_replace(['\\', '"'], ['\\\\', '\\"'], $email), $py);
    $cmd = 'docker exec glitchtip_web python manage.py shell -c ' . escapeshellarg($py) . ' 2>/dev/null';
    $out = trim((string)shell_exec($cmd));
    foreach (array_reverse(preg_split('/\R+/', $out) ?: []) as $line) {
        $line = trim($line);
        if ($line !== '' && preg_match('/^[A-Za-z0-9]{32,128}$/', $line)) return $line;
    }
    return null;
}

function integrations_sentry_http(string $url, string $token): int {
    $ch = curl_init(rtrim($url, '/') . '/api/0/projects/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token],
    ]);
    curl_exec($ch);
    return (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
}

function integrations_sentry_credentials(string $email, bool $refresh = false): array {
    $url = integrations_sentry_url();
    if ($url === '') return ['ok' => false, 'error' => 'sentry_url_missing'];
    $metaKey = 'mcp_sentry_api_token:' . strtolower($email);
    $stored = trim(integrations_meta_get($metaKey));
    if ($stored !== '' && !$refresh) {
        $code = integrations_sentry_http($url, $stored);
        if ($code >= 200 && $code < 300) return ['ok' => true, 'url' => $url, 'token' => $stored];
    }
    $token = integrations_sentry_create_token($email);
    if (!$token) return ['ok' => false, 'error' => 'sentry_token_create_failed'];
    integrations_meta_set($metaKey, $token);
    return ['ok' => true, 'url' => $url, 'token' => $token];
}

function integrations_immich_full_permissions(): string {
    return '{all}';
}

function integrations_immich_credentials(string $email, bool $refresh = false): array {
    $url = integrations_immich_url();
    $metaKey = 'mcp_immich_api_key:' . strtolower($email);
    $stored = trim(integrations_meta_get($metaKey));
    oauth_ensure_immich_user($email, (string)($_SESSION['user']['name'] ?? $email), ((int)($_SESSION['user']['role'] ?? 0)) >= 2);
    $pdo = oauth_immich_db();
    if (!$pdo) {
        return ['ok' => false, 'error' => 'immich_unavailable'];
    }
    $immichUserId = null;
    try {
        $stmt = $pdo->prepare('SELECT id FROM "user" WHERE lower(email) = lower(:email) AND "deletedAt" IS NULL LIMIT 1');
        $stmt->execute([':email' => $email]);
        $immichUserId = $stmt->fetchColumn() ?: null;
    } catch (Throwable $e) {
        $immichUserId = null;
    }
    if ($immichUserId === null) {
        return ['ok' => false, 'error' => 'immich_user_missing'];
    }
    if ($stored !== '' && !$refresh) {
        try {
            $hashHex = hash('sha256', $stored);
            $chk = $pdo->prepare('SELECT 1 FROM api_key WHERE "userId" = :uid AND name = :name AND key = decode(:hex, \'hex\') LIMIT 1');
            $chk->execute([':uid' => $immichUserId, ':name' => 'MCP', ':hex' => $hashHex]);
            if ($chk->fetchColumn()) {
                return ['ok' => true, 'url' => $url, 'api_key' => $stored];
            }
        } catch (Throwable $e) {
        }
    }
    try {
        $pdo->prepare('DELETE FROM api_key WHERE "userId" = :uid AND name = :name')->execute([
            ':uid' => $immichUserId,
            ':name' => 'MCP',
        ]);
        $token = oauth_immich_api_token();
        $ins = $pdo->prepare('INSERT INTO api_key (name, key, "userId", permissions) VALUES (:name, decode(:hex, \'hex\'), :uid, :perms)');
        $ins->execute([
            ':name' => 'MCP',
            ':hex' => hash('sha256', $token),
            ':uid' => $immichUserId,
            ':perms' => integrations_immich_full_permissions(),
        ]);
        integrations_meta_set($metaKey, $token);
        return ['ok' => true, 'url' => $url, 'api_key' => $token];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'immich_key_create_failed', 'message' => $e->getMessage()];
    }
}

function integrations_gitea_url(): string {
    $url = trim((string)(getenv('GITEA_PUBLIC_URL') ?: (function_exists('app_public_url') ? app_public_url('git') : '')));
    $url = integrations_public_service_url($url, 'git');
    return rtrim($url, '/');
}

function integrations_gitea_api_base(): string {
    $internal = trim((string)(getenv('GITEA_INTERNAL_URL') ?: 'http://gitea:3000'));
    return rtrim($internal !== '' ? $internal : integrations_gitea_url(), '/');
}

function integrations_gitea_admin_auth(): ?array {
    $user = trim((string)(getenv('GITEA_ADMIN_USER') ?: ''));
    $pass = (string)(getenv('GITEA_ADMIN_PASSWORD') ?: '');
    if ($user !== '' && $pass !== '') {
        return ['user' => $user, 'pass' => $pass];
    }
    $iniPath = '/opt/docker/gitea/gitea/conf/app.ini';
    if (!is_readable($iniPath)) {
        $iniPath = '/data/gitea/conf/app.ini';
    }
    if (!is_readable($iniPath)) return null;
    $ini = @parse_ini_file($iniPath, true, INI_SCANNER_RAW);
    if (!is_array($ini)) return null;
    $user = trim((string)($ini['admin']['USERNAME'] ?? $ini['security']['ADMIN_USER'] ?? ''));
    $pass = (string)($ini['admin']['PASSWORD'] ?? $ini['security']['ADMIN_PASSWORD'] ?? '');
    if ($user === '' || $pass === '') return null;
    return ['user' => $user, 'pass' => $pass];
}

function integrations_gitea_username(string $email): string {
    $pdo = oauth_gitea_db();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT name FROM "user" WHERE lower(email) = lower(:email) LIMIT 1');
            $stmt->execute([':email' => $email]);
            $name = $stmt->fetchColumn();
            if (is_string($name) && $name !== '') return $name;
        } catch (Throwable $e) {
        }
    }
    return oauth_sanitize_username(strstr($email, '@', true) ?: $email);
}

function integrations_gitea_http(string $method, string $path, ?array $auth, ?array $json = null, string $token = ''): array {
    $url = integrations_gitea_api_base() . '/api/v1' . $path;
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: token ' . $token;
    } elseif ($auth) {
        $headers[] = 'Authorization: Basic ' . base64_encode($auth['user'] . ':' . $auth['pass']);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $json === null ? null : json_encode($json, JSON_UNESCAPED_UNICODE),
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    $data = null;
    if (is_string($body) && $body !== '') {
        $decoded = json_decode($body, true);
        $data = is_array($decoded) ? $decoded : ['raw' => $body];
    }
    return ['code' => $code, 'data' => $data, 'error' => $err];
}

function integrations_gitea_create_token_cli(string $username): ?string {
    $username = preg_replace('/[^A-Za-z0-9._-]/', '', $username) ?? '';
    if ($username === '') return null;
    $cmd = 'docker exec -u git gitea gitea admin user generate-access-token -u '
        . escapeshellarg($username)
        . ' --token-name MCP --scopes all --raw 2>/dev/null';
    $out = trim((string)shell_exec($cmd));
    if ($out !== '' && preg_match('/^[a-f0-9]{40,}$/i', $out)) return $out;
    // Host may reach remote docker via ssh when local docker has no gitea container
    $cmd2 = 'ssh -o BatchMode=yes -o ConnectTimeout=5 VPNserver '
        . escapeshellarg('docker exec -u git gitea gitea admin user generate-access-token -u ' . escapeshellarg($username) . ' --token-name MCP --scopes all --raw 2>/dev/null');
    $out2 = trim((string)shell_exec($cmd2));
    if ($out2 !== '' && preg_match('/^[a-f0-9]{40,}$/i', $out2)) return $out2;
    return null;
}

function integrations_gitea_create_token_db(string $email, string $username): ?string {
    // Prefer CLI (correct PBKDF2 hashing for Gitea 1.27+)
    $cli = integrations_gitea_create_token_cli($username);
    if ($cli) return $cli;
    $pdo = oauth_gitea_db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare('SELECT id FROM "user" WHERE lower(email) = lower(:email) OR lower_name = :name LIMIT 1');
        $stmt->execute([':email' => $email, ':name' => strtolower($username)]);
        $uid = $stmt->fetchColumn();
        if (!$uid) return null;
        $pdo->prepare('DELETE FROM access_token WHERE uid = :uid AND name = :name')->execute([
            ':uid' => (int)$uid,
            ':name' => 'MCP',
        ]);
        $token = bin2hex(random_bytes(20));
        $salt = bin2hex(random_bytes(5));
        // Gitea 1.27+: PBKDF2-HMAC-SHA256, 10000 iter, 50-byte key
        $hash = bin2hex(hash_pbkdf2('sha256', $token, $salt, 10000, 50, true));
        $now = time();
        $ins = $pdo->prepare('INSERT INTO access_token (uid, name, token_hash, token_salt, token_last_eight, scope, created_unix, updated_unix)
            VALUES (:uid, :name, :hash, :salt, :last8, :scope, :now, :now)');
        $ins->execute([
            ':uid' => (int)$uid,
            ':name' => 'MCP',
            ':hash' => $hash,
            ':salt' => $salt,
            ':last8' => substr($token, -8),
            ':scope' => 'all',
            ':now' => $now,
        ]);
        return $token;
    } catch (Throwable $e) {
        return null;
    }
}

function integrations_gitea_credentials(string $email, bool $refresh = false): array {
    $url = integrations_gitea_url();
    $metaKey = 'mcp_gitea_access_token:' . strtolower($email);
    $stored = trim(integrations_meta_get($metaKey));
    $user = $_SESSION['user'] ?? [];
    oauth_ensure_gitea_user(
        $email,
        (string)($user['name'] ?? $email),
        ((int)($user['role'] ?? 0)) >= 2,
        (string)($user['theme'] ?? 'light'),
        (string)($user['photo'] ?? '')
    );
    $username = integrations_gitea_username($email);
    if ($stored !== '' && !$refresh) {
        $probe = integrations_gitea_http('GET', '/user', null, null, $stored);
        if ($probe['code'] >= 200 && $probe['code'] < 300) {
            return ['ok' => true, 'url' => $url, 'access_token' => $stored];
        }
    }
    $auth = integrations_gitea_admin_auth();
    $token = '';
    if ($auth) {
        $list = integrations_gitea_http('GET', '/users/' . rawurlencode($username) . '/tokens', $auth);
        if ($list['code'] >= 200 && $list['code'] < 300 && is_array($list['data'])) {
            foreach ($list['data'] as $row) {
                if (!is_array($row)) continue;
                if (strcasecmp((string)($row['name'] ?? ''), 'MCP') === 0) {
                    $id = $row['id'] ?? $row['name'] ?? null;
                    if ($id !== null) {
                        integrations_gitea_http('DELETE', '/users/' . rawurlencode($username) . '/tokens/' . rawurlencode((string)$id), $auth);
                    }
                }
            }
        }
        $scopes = [
            'read:activitypub', 'write:activitypub',
            'read:issue', 'write:issue',
            'read:misc', 'write:misc',
            'read:notification', 'write:notification',
            'read:organization', 'write:organization',
            'read:package', 'write:package',
            'read:repository', 'write:repository',
            'read:user', 'write:user',
        ];
        $created = integrations_gitea_http('POST', '/users/' . rawurlencode($username) . '/tokens', $auth, [
            'name' => 'MCP',
            'scopes' => $scopes,
        ]);
        if ($created['code'] >= 200 && $created['code'] < 300 && is_array($created['data'])) {
            $token = trim((string)($created['data']['sha1'] ?? $created['data']['token'] ?? ''));
        }
    }
    if ($token === '') {
        $token = (string)(integrations_gitea_create_token_db($email, $username) ?? '');
    }
    if ($token === '') {
        return ['ok' => false, 'error' => 'gitea_token_create_failed'];
    }
    integrations_meta_set($metaKey, $token);
    return ['ok' => true, 'url' => $url, 'access_token' => $token];
}






function integrations_kanboard_url(): string {
    $env = trim((string)(getenv('KANBOARD_PUBLIC_URL') ?: ''));
    if ($env !== '') return rtrim($env, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https://' : 'http://') . $host . '/apps/kanboard';
}

function integrations_kanboard_pdo(): ?PDO {
    require_once APP_ROOT . '/apps/kanboard/integration/bootstrap.php';
    return kanboard_pdo();
}

function integrations_kanboard_ensure_user(PDO $pdo, string $email, string $name, bool $isManager): ?array {
    $email = strtolower(trim($email));
    if ($email === '') return null;
    $local = strtolower((string)(strstr($email, '@', true) ?: $email));
    $username = preg_replace('/[^a-z0-9_\-\.]/', '_', $local) ?: 'user';
    $role = $isManager ? 'app-manager' : 'app-user';
    $stmt = $pdo->prepare('SELECT id, username, api_access_token FROM users WHERE lower(email) = lower(:email) OR username = :u LIMIT 1');
    $stmt->execute([':email' => $email, ':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare('UPDATE users SET email = :email, name = :name, role = :role, is_active = TRUE, notifications_enabled = TRUE WHERE id = :id')
            ->execute([':email' => $email, ':name' => $name !== '' ? $name : $username, ':role' => $role, ':id' => $row['id']]);
        $row['username'] = $username;
        return $row;
    }
    $ins = $pdo->prepare('INSERT INTO users (username, password, email, name, role, is_active, notifications_enabled) VALUES (:u, :p, :e, :n, :r, TRUE, TRUE) RETURNING id, username, api_access_token');
    try {
        $ins->execute([
            ':u' => $username,
            ':p' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            ':e' => $email,
            ':n' => $name !== '' ? $name : $username,
            ':r' => $role,
        ]);
        $created = $ins->fetch(PDO::FETCH_ASSOC);
        return is_array($created) ? $created : null;
    } catch (Throwable $e) {
        return null;
    }
}

function integrations_kanboard_credentials(string $email, bool $refresh = false): array {
    $url = integrations_kanboard_url();
    $metaKey = 'mcp_kanboard_api_token:' . strtolower($email);
    $pdo = integrations_kanboard_pdo();
    if (!$pdo) {
        return ['ok' => false, 'error' => 'kanboard_db_unavailable'];
    }
    try {
        $pdo->query('SELECT 1 FROM users LIMIT 1');
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'kanboard_not_migrated'];
    }
    $name = (string)($_SESSION['user']['name'] ?? '');
    $isManager = ((int)($_SESSION['user']['role'] ?? 0)) >= 1;
    $user = integrations_kanboard_ensure_user($pdo, $email, $name, $isManager);
    if (!$user) {
        return ['ok' => false, 'error' => 'kanboard_user_missing'];
    }
    $username = (string)$user['username'];
    $userId = (int)($user['id'] ?? 0);
    if ($userId > 0) {
        $pdo->prepare('UPDATE users SET notifications_enabled = TRUE, nb_failed_login = 0, lock_expiration_date = 0 WHERE id = :id')
            ->execute([':id' => $userId]);
        try {
            $hasWeb = $pdo->prepare('SELECT 1 FROM user_has_notification_types WHERE user_id = :id AND notification_type = :t LIMIT 1');
            $hasWeb->execute([':id' => $userId, ':t' => 'web']);
            if (!$hasWeb->fetchColumn()) {
                $pdo->prepare('INSERT INTO user_has_notification_types (user_id, notification_type) VALUES (:id, :t)')
                    ->execute([':id' => $userId, ':t' => 'web']);
            }
        } catch (Throwable $e) {
        }
    }
    $cached = trim(integrations_meta_get($metaKey));
    $kbToken = trim((string)($user['api_access_token'] ?? ''));
    if ($cached !== '' && !$refresh) {
        if ($kbToken !== $cached) {
            $pdo->prepare('UPDATE users SET api_access_token = :t WHERE username = :u')->execute([':t' => $cached, ':u' => $username]);
        }
        return ['ok' => true, 'url' => $url, 'username' => $username, 'api_token' => $cached];
    }
    $token = (!$refresh && $kbToken !== '') ? $kbToken : bin2hex(random_bytes(24));
    $pdo->prepare('UPDATE users SET api_access_token = :t WHERE username = :u')->execute([':t' => $token, ':u' => $username]);
    integrations_meta_set($metaKey, $token);
    return ['ok' => true, 'url' => $url, 'username' => $username, 'api_token' => $token];
}
