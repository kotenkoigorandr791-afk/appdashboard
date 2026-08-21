<?php
require_once APP_ROOT . '/project/oauth.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$parts = public_api_parse_path();
$service = strtolower((string)($parts[1] ?? ''));
$email = (string)($_SESSION['user']['email'] ?? '');
if ($email === '') {
    public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
}
if ($method !== 'GET') {
    public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if ($service === 'immich') {
    $out = integrations_immich_credentials($email);
    public_api_json($out, !empty($out['ok']) ? 200 : 503);
}
if ($service === 'gitea') {
    $out = integrations_gitea_credentials($email);
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

function integrations_immich_url(): string {
    $url = trim((string)(getenv('IMMICH_PUBLIC_URL') ?: 'https://photo.serverkherson.xyz'));
    return rtrim($url, '/');
}

function integrations_immich_full_permissions(): string {
    return '{all}';
}

function integrations_immich_credentials(string $email): array {
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
    if ($stored !== '') {
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
    $url = trim((string)(getenv('GITEA_PUBLIC_URL') ?: 'https://git.serverkherson.xyz'));
    return rtrim($url, '/');
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
    $url = integrations_gitea_url() . '/api/v1' . $path;
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
    curl_close($ch);
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

function integrations_gitea_credentials(string $email): array {
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
    if ($stored !== '') {
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
