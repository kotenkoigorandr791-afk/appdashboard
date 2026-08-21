<?php
if (!function_exists('dupBackupCryptKeyBinary')) {
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
if (!defined('DUP_BACKUP_LEGACY_KEY')) {
    define('DUP_BACKUP_LEGACY_KEY', hash('sha256', 'DuplicatiManager_SecretKey_2024_CHANGE_ME', true));
}
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
if (!defined('DUP_BACKUP_CONFIG_KEY')) {
    define('DUP_BACKUP_CONFIG_KEY', 'dup_backup_secrets_enc');
}
function dupBackupNormalizeHost(string $host): string {
    $h = trim($host);
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
    return rtrim($h, '/');
}
function dupBackupProxyHostCandidates(string $host): array {
    $host = dupBackupNormalizeHost($host);
    $candidates = [$host];
    $parts = parse_url($host);
    $name = strtolower((string)($parts['host'] ?? ''));
    if (in_array($name, ['localhost', '127.0.0.1', '::1'], true)) {
        $fallbacks = ['host.docker.internal'];
        $gateway = trim((string)@shell_exec("ip route 2>/dev/null | awk '/default/ {print $3; exit}'"));
        if ($gateway !== '') {
            $fallbacks[] = $gateway;
        }
        $fallbacks[] = '172.17.0.1';
        foreach ($fallbacks as $fallback) {
            $candidate = preg_replace('#^(https?://)(\[[^\]]+\]|[^/:]+)#i', '${1}' . $fallback, $host);
            if (is_string($candidate) && $candidate !== '') {
                $candidates[] = $candidate;
            }
        }
    }
    return array_values(array_unique($candidates));
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
    $legacyFile = APP_ROOT . '/dup_config.json';
    if (is_readable($legacyFile)) {
        $data = json_decode((string)file_get_contents($legacyFile), true);
        if (is_array($data)) {
            $h = !empty($data['host']) ? dupBackupDecryptLegacyField((string)$data['host']) : '';
            $t = !empty($data['token']) ? dupBackupDecryptLegacyField((string)$data['token']) : '';
            if ($h !== '' && $t !== '') {
                $h = dupBackupNormalizeHost($h);
                $t = dupBackupNormalizeToken($t);
                if (function_exists('dupBackupSaveEncrypted')) {
                    dupBackupSaveEncrypted($h, $t);
                }
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
}
function duplicati_call(string $endpoint, string $method = 'GET', $data = null): array {
    $creds = dupBackupLoadDecrypted();
    if ($creds['host'] === '' || $creds['token'] === '') {
        return ['code' => 503, 'data' => ['error' => 'Duplicati is not configured'], 'raw' => ''];
    }
    $token = $creds['token'];
    $cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dup_api_' . md5($creds['host']) . '.txt';
    $response = false;
    $httpCode = 0;
    $curlError = '';
    foreach (dupBackupProxyHostCandidates($creds['host']) as $candidateHost) {
        $url = rtrim($candidateHost, '/') . '/api/v1/' . ltrim($endpoint, '/');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => [
                dupBackupBuildAuthHeader($token),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
        ]);
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, ($data !== null) ? json_encode($data) : '{}');
        }
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($curlError === '') break;
    }
    if ($curlError !== '') {
        return ['code' => 502, 'data' => ['error' => 'Cannot reach Duplicati: ' . $curlError], 'raw' => ''];
    }
    $decoded = json_decode((string)$response, true);
    return ['code' => $httpCode, 'data' => $decoded !== null ? $decoded : $response, 'raw' => (string)$response];
}
