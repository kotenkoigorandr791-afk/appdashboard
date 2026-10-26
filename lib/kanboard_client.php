<?php

function kanboard_client_public_url(): string
{
    $env = trim((string) (getenv('KANBOARD_PUBLIC_URL') ?: ''));
    if ($env !== '') {
        return rtrim($env, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https://' : 'http://') . $host . '/apps/kanboard';
}

function kanboard_client_rpc_url(): string
{
    $env = trim((string) (getenv('KANBOARD_INTERNAL_URL') ?: getenv('KANBOARD_MCP_URL') ?: ''));
    if ($env !== '') {
        return rtrim($env, '/');
    }
    // Same-container Apache listens on 8080 (docker maps host :80 -> :8080).
    if (is_file(APP_ROOT . '/apps/kanboard/jsonrpc.php')) {
        $probe = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 0.2);
        if (is_resource($probe)) {
            fclose($probe);
            return 'http://127.0.0.1:8080/apps/kanboard';
        }
    }
    return kanboard_client_public_url();
}

function kanboard_client_url(): string
{
    return kanboard_client_public_url();
}

function kanboard_client_pdo(): ?PDO
{
    require_once APP_ROOT . '/apps/kanboard/integration/bootstrap.php';
    return kanboard_pdo();
}

function kanboard_client_ensure_user(PDO $pdo, string $email, string $name, bool $isManager): ?array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }
    $local = strtolower((string) (strstr($email, '@', true) ?: $email));
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

function kanboard_client_meta_get(string $key): string
{
    $db = getDB();
    if (!$db) {
        return '';
    }
    try {
        $stmt = $db->prepare('SELECT value FROM app_meta WHERE key = :k LIMIT 1');
        $stmt->bindValue(':k', $key);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return '';
        }
        $v = $row['value'] ?? '';
        return is_string($v) ? $v : '';
    } catch (Throwable $e) {
        return '';
    }
}

function kanboard_client_meta_set(string $key, string $value): void
{
    $db = getDB();
    if (!$db) {
        return;
    }
    if (function_exists('monitorRetentionSetMeta')) {
        monitorRetentionSetMeta($db, $key, $value);
        return;
    }
    try {
        $stmt = $db->prepare('INSERT INTO app_meta (key, value) VALUES (:k, :v) ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value');
        $stmt->bindValue(':k', $key);
        $stmt->bindValue(':v', $value);
        $stmt->execute();
    } catch (Throwable $e) {
    }
}

function kanboard_client_credentials(string $email, bool $refresh = false): array
{
    $url = kanboard_client_url();
    $metaKey = 'mcp_kanboard_api_token:' . strtolower($email);
    $pdo = kanboard_client_pdo();
    if (!$pdo) {
        return ['ok' => false, 'error' => 'kanboard_db_unavailable'];
    }
    try {
        $pdo->query('SELECT 1 FROM users LIMIT 1');
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'kanboard_not_migrated'];
    }
    $name = (string) ($_SESSION['user']['name'] ?? '');
    $isManager = ((int) ($_SESSION['user']['role'] ?? 0)) >= 1;
    $user = kanboard_client_ensure_user($pdo, $email, $name, $isManager);
    if (!$user) {
        return ['ok' => false, 'error' => 'kanboard_user_missing'];
    }
    $username = (string) $user['username'];
    $userId = (int) ($user['id'] ?? 0);
    if ($userId > 0) {
        $pdo->prepare('UPDATE users SET notifications_enabled = TRUE, nb_failed_login = 0, lock_expiration_date = 0 WHERE id = :id')
            ->execute([':id' => $userId]);
    }
    $cached = trim(kanboard_client_meta_get($metaKey));
    $kbToken = trim((string) ($user['api_access_token'] ?? ''));
    if ($cached !== '' && !$refresh) {
        if ($kbToken !== $cached) {
            $pdo->prepare('UPDATE users SET api_access_token = :t WHERE username = :u')->execute([':t' => $cached, ':u' => $username]);
        }
        return [
            'ok' => true,
            'url' => $url,
            'rpc_url' => kanboard_client_rpc_url(),
            'username' => $username,
            'api_token' => $cached,
            'user_id' => $userId,
        ];
    }
    $token = (!$refresh && $kbToken !== '') ? $kbToken : bin2hex(random_bytes(24));
    $pdo->prepare('UPDATE users SET api_access_token = :t WHERE username = :u')->execute([':t' => $token, ':u' => $username]);
    kanboard_client_meta_set($metaKey, $token);
    return [
        'ok' => true,
        'url' => $url,
        'rpc_url' => kanboard_client_rpc_url(),
        'username' => $username,
        'api_token' => $token,
        'user_id' => $userId,
    ];
}

function kanboard_client_rpc(array $creds, string $method, $params = [])
{
    $base = (string) ($creds['rpc_url'] ?? $creds['url'] ?? '');
    $endpoint = rtrim($base, '/') . '/jsonrpc.php';
    $auth = base64_encode(($creds['username'] ?? '') . ':' . ($creds['api_token'] ?? ''));
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method' => $method,
        'id' => (int) (microtime(true) * 1000),
        'params' => $params,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . $auth,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return ['ok' => false, 'error' => 'kanboard_http_error', 'message' => $err, 'status' => 502];
    }
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'kanboard_bad_json', 'status' => 502, 'raw' => substr((string) $raw, 0, 200)];
    }
    if ($code === 401 || (($data['error']['code'] ?? null) === 401)) {
        return ['ok' => false, 'error' => 'kanboard_unauthorized', 'status' => 401, 'data' => $data];
    }
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'error' => 'kanboard_http_' . $code, 'status' => $code, 'data' => $data];
    }
    if (!empty($data['error'])) {
        return [
            'ok' => false,
            'error' => 'kanboard_rpc_error',
            'message' => (string) ($data['error']['message'] ?? 'rpc_failed'),
            'status' => 400,
            'data' => $data,
        ];
    }
    return ['ok' => true, 'result' => $data['result'] ?? null];
}

function kanboard_client_call(string $email, string $method, $params = [], bool $retried = false)
{
    $creds = kanboard_client_credentials($email, $retried);
    if (empty($creds['ok'])) {
        return $creds + ['status' => 503];
    }
    $out = kanboard_client_rpc($creds, $method, $params);
    if (!$retried && (($out['status'] ?? 0) === 401 || ($out['error'] ?? '') === 'kanboard_unauthorized')) {
        return kanboard_client_call($email, $method, $params, true);
    }
    $out['creds'] = $creds;
    return $out;
}
