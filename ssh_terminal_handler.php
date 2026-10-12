<?php
require_once __DIR__ . '/smart_log.php';
require_once __DIR__ . '/storage_helper.php';
smart_log_bootstrap('dashboard');
if (!defined('AP_WEBSHH_VENDOR_AUTOLOAD')) {
    define('AP_WEBSHH_VENDOR_AUTOLOAD', __DIR__ . '/webssh/vendor/autoload.php');
}

if (!function_exists('apSshEnsureVendorLoaded')) {
    function apSshEnsureVendorLoaded(): void
    {
        static $loaded = false;
        if ($loaded || !is_readable(AP_WEBSHH_VENDOR_AUTOLOAD)) {
            return;
        }
        require_once AP_WEBSHH_VENDOR_AUTOLOAD;
        $loaded = true;
    }
}

if (!function_exists('apSshJsonResponse')) {
    function apSshJsonResponse(array $payload): void
    {
        while (ob_get_level() > 1) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{"success":false,"error":"Failed to encode JSON response"}';
        }
        echo $json;
    }
}

if (!function_exists('apSshReadPostString')) {
    function apSshReadPostString(string $key, string $default = ''): string
    {
        return trim((string)($_POST[$key] ?? $default));
    }
}

if (!function_exists('apSshValidateHost')) {
    function apSshValidateHost(string $host): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9._-]+$/', $host);
    }
}

if (!function_exists('apSshTcpReachable')) {
    function apSshTcpReachable(string $host, int $port, float $timeout = 2.0): bool
    {
        $targets = [];
        $v4 = @gethostbyname($host);
        // gethostbyname() returns the input unchanged when resolution fails.
        if ($v4 !== $host && filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $targets[] = $v4;
        }
        $targets[] = $host;
        foreach (array_unique($targets) as $target) {
            $errno = 0;
            $errstr = '';
            $sock = @fsockopen($target, $port, $errno, $errstr, $timeout);
            if ($sock !== false) {
                fclose($sock);
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('apSshResolveTargetHost')) {
    /**
     * Picks the first host that actually accepts SSH connections.
     * SSH_HOST overrides everything; otherwise the common container-to-host
     * aliases are probed in order.
     */
    function apSshResolveTargetHost(): string
    {
        $configured = trim((string)(getenv('SSH_DEFAULT_HOST') ?: getenv('SSH_HOST') ?: ''));
        $candidates = [];
        if ($configured !== '') {
            $candidates[] = $configured;
        }
        foreach (['host.docker.internal', '172.17.0.1', '172.18.0.1'] as $alias) {
            if (!in_array($alias, $candidates, true)) {
                $candidates[] = $alias;
            }
        }
        foreach ($candidates as $candidate) {
            if (apSshTcpReachable($candidate, 22)) {
                return $candidate;
            }
        }
        return $configured !== '' ? $configured : 'host.docker.internal';
    }
}

if (!function_exists('apSshAuthorizeHostKey')) {
    /**
     * Returns the public key that matches the configured private key.
     * ssh-keygen -y is authoritative because the .pub file can be stale.
     */
    function apSshPublicKeyFor(string $privateKeyPath): string
    {
        $out = [];
        $code = 0;
        @exec('ssh-keygen -y -f ' . escapeshellarg($privateKeyPath) . ' 2>/dev/null', $out, $code);
        if ($code === 0 && !empty($out[0])) {
            return trim($out[0]);
        }
        $pubPath = $privateKeyPath . '.pub';
        if (is_readable($pubPath)) {
            return trim((string)@file_get_contents($pubPath));
        }
        return '';
    }
}

if (!function_exists('apSshResolveTargetUser')) {
    /**
     * SSH_USER wins. Otherwise a normal account is preferred over root.
     * Web-server service accounts (www-data, apache, nginx, …) are skipped:
     * they only exist inside the container and have no login shell on the host.
     */
    function apSshResolveTargetUser(): string
    {
        $configured = trim((string)(getenv('SSH_DEFAULT_USER') ?: getenv('SSH_USER') ?: ''));
        if ($configured !== '' && apSshValidateUser($configured)) {
            return $configured;
        }
        $serviceAccounts = ['www-data', 'apache', 'nginx', 'httpd', 'nobody', 'daemon', 'wwwrun'];
        $current = function_exists('get_current_user') ? trim((string)get_current_user()) : '';
        if ($current !== ''
            && apSshValidateUser($current)
            && !in_array(strtolower($current), $serviceAccounts, true)) {
            return $current;
        }
        return 'root';
    }
}

if (!function_exists('apSshValidateUser')) {
    function apSshValidateUser(string $user): bool
    {
        return (bool)preg_match('/^[a-z_][a-z0-9_-]*[$]?$/i', $user);
    }
}

if (!function_exists('apSshKeysDir')) {
    function apSshKeysDir(): string
    {
        $custom = trim((string)(getenv('SSH_KEYS_DIR') ?: ''));
        if ($custom !== '') {
            return rtrim($custom, '/');
        }
        return appdata_data_dir() . '/.ssh';
    }
}

if (!function_exists('apSshGetHostKeyPath')) {
    function apSshGetHostKeyPath(): string
    {
        return apSshKeysDir() . '/id_ed25519_host';
    }
}

if (!function_exists('apSshRotateHostKey')) {
    function apSshRotateHostKey(): ?array
    {
        $keysDir = apSshKeysDir();
        if (!is_dir($keysDir)) {
            mkdir($keysDir, 0700, true);
        }

        $privateKeyPath = apSshGetHostKeyPath();
        $publicKeyPath = $privateKeyPath . '.pub';

        foreach ([$privateKeyPath, $publicKeyPath] as $stale) {
            if (is_file($stale)) {
                @unlink($stale);
            }
        }

        $cmd = "ssh-keygen -t ed25519 -N '' -f " . escapeshellarg($privateKeyPath) . " -C 'appdashboard-ssh-host'";
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !is_file($privateKeyPath) || !is_file($publicKeyPath)) {
            return null;
        }

        // Set permissions
        chmod($privateKeyPath, 0600);
        chmod($publicKeyPath, 0644);

        // ssh-keygen -y is authoritative: it reflects the actual private key.
        $publicKey = apSshPublicKeyFor($privateKeyPath);
        if ($publicKey === '') {
            return null;
        }

        // Change ownership to www-data
        $uid = posix_getpwnam('www-data');
        if ($uid) {
            chown($privateKeyPath, $uid['uid']);
            chown($publicKeyPath, $uid['uid']);
        }

        return [
            'private_key_path' => $privateKeyPath,
            'public_key_path' => $publicKeyPath,
            'public_key' => $publicKey,
        ];
    }
}

if (!function_exists('apSshDiscoverKeyPath')) {
    function apSshDiscoverKeyPath(): string
    {
        $dir = apSshKeysDir();
        if (!is_dir($dir)) {
            return '';
        }
        // First check for the dedicated host key
        $hostKeyPath = apSshGetHostKeyPath();
        if (is_readable($hostKeyPath)) {
            return $hostKeyPath;
        }
        // Fall back to any other key
        foreach (['id_ed25519', 'id_rsa', 'id_ecdsa'] as $name) {
            $path = $dir . '/' . $name;
            if (is_readable($path)) {
                return $path;
            }
        }
        return '';
    }
}

if (!function_exists('apSshValidateKeyPath')) {
    function apSshValidateKeyPath(string $keyPath, bool $mustBeReadable = true): bool
    {
        if ($keyPath === '') {
            return false;
        }
        if ($mustBeReadable && (!is_readable($keyPath) || !is_file($keyPath))) {
            return false;
        }
        $real = realpath($keyPath);
        if ($real === false) {
            if ($mustBeReadable) {
                return false;
            }
            $dir = dirname($keyPath);
            $base = basename($keyPath);
            if ($base === '' || $base === '.' || $base === '..') {
                return false;
            }
            if (!preg_match('/^(id_(ed25519|rsa|ecdsa)|[^\\/]+)$/', $base)) {
                return false;
            }
            $dirReal = realpath($dir);
            if ($dirReal === false || !is_dir($dirReal)) {
                return false;
            }
            $real = $dirReal . DIRECTORY_SEPARATOR . $base;
        }
        $allowedRoots = [];
        $keysDir = apSshKeysDir();
        $keysReal = realpath($keysDir);
        if ($keysReal !== false) {
            $allowedRoots[] = $keysReal;
        }
        $allowedRoots = array_values(array_unique(array_filter($allowedRoots)));
        foreach ($allowedRoots as $root) {
            if ($real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('apSshResolveKeyPath')) {
    function apSshResolveKeyPath(string $keyPath = '', bool $mustBeReadable = true): string
    {
        $keyPath = trim($keyPath);
        if ($keyPath !== '' && apSshValidateKeyPath($keyPath, $mustBeReadable)) {
            $real = realpath($keyPath);
            return $real !== false ? $real : $keyPath;
        }
        if ($mustBeReadable) {
            $discovered = apSshDiscoverKeyPath();
            return $discovered !== '' ? $discovered : '';
        }
        foreach (['id_ed25519', 'id_rsa', 'id_ecdsa'] as $name) {
            $candidate = apSshKeysDir() . '/' . $name;
            if (apSshValidateKeyPath($candidate, false)) {
                return $candidate;
            }
        }
        return '';
    }
}

if (!function_exists('apSshEnsureUtf8')) {
    function apSshEnsureUtf8(string $text): string
    {
        if ($text === '') {
            return $text;
        }
        if (preg_match('//u', $text)) {
            return $text;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8,CP866,Windows-1251,ISO-8859-1,Windows-1252');
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv('CP866', 'UTF-8//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }
        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text) ?? $text;
    }
}

if (!function_exists('apSshCredentialsFile')) {
    /**
     * Legacy file path — migrated to SQLite `config` on first read/write.
     */
    function apSshCredentialsFile(): string
    {
        return __DIR__ . '/ssh_terminal_credentials.enc.json';
    }
}

if (!function_exists('apSshCredentialsConfigKey')) {
    function apSshCredentialsConfigKey(): string
    {
        return 'ssh_terminal_credentials_json';
    }
}

if (!function_exists('apSshCryptoKeys')) {
    /**
     * Returns crypto keys to try (in order). The first key is used for encryption;
     * decryption may fall back to legacy keys for migration.
     * Priority: env var → DB-persisted random key (auto-generated) → legacy fallback.
     */
    function apSshCryptoKeys(): array
    {
        $keys = [];
        $env = (string)getenv('SSH_CREDENTIALS_CRYPT_KEY');
        if ($env !== '') {
            $keys[] = hash('sha256', $env, true);
        }
        if (function_exists('getConfig') && function_exists('setConfig')) {
            $stored = (string)getConfig('ssh_credentials_crypt_secret', '');
            if ($stored === '') {
                $stored = bin2hex(random_bytes(32));
                try { setConfig('ssh_credentials_crypt_secret', $stored); } catch (Throwable $e) {}
            }
            if ($stored !== '') {
                $keys[] = hash('sha256', $stored, true);
            }
        }
        $keys[] = hash('sha256', 'ap-ssh-credentials-default-key-change-me', true);
        return array_values(array_unique($keys));
    }
}
if (!function_exists('apSshCryptoKey')) {
    function apSshCryptoKey(): string
    {
        $keys = apSshCryptoKeys();
        return $keys[0] ?? hash('sha256', bin2hex(random_bytes(32)), true);
    }
}

if (!function_exists('apSshEncrypt')) {
    function apSshEncrypt(string $plaintext): array
    {
        $iv = random_bytes(12); // recommended size for GCM
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            apSshCryptoKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            // Still return something JSON-compatible; decrypt will produce ''.
            return ['iv' => base64_encode($iv), 'cipher' => '', 'tag' => base64_encode('')];
        }

        return [
            'iv' => base64_encode($iv),
            'cipher' => base64_encode($ciphertext),
            'tag' => base64_encode($tag),
        ];
    }
}

if (!function_exists('apSshDecrypt')) {
    function apSshDecrypt($enc): string
    {
        if (!is_array($enc)) {
            return '';
        }
        $iv = isset($enc['iv']) ? base64_decode((string)$enc['iv'], true) : false;
        $cipher = isset($enc['cipher']) ? base64_decode((string)$enc['cipher'], true) : false;
        $tag = isset($enc['tag']) ? base64_decode((string)$enc['tag'], true) : false;

        if ($iv === false || $cipher === false || $tag === false) {
            return '';
        }

        foreach (apSshCryptoKeys() as $key) {
            $pt = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if (is_string($pt)) {
                return $pt;
            }
        }
        return '';
    }
}

if (!function_exists('apSshReadSavedProfile')) {
    function apSshReadSavedProfile(): array
    {
        if (!function_exists('getConfig') || !function_exists('setConfig')) {
            return [];
        }

        $raw = getConfig(apSshCredentialsConfigKey(), '');
        $path = apSshCredentialsFile();
        if ($raw === '' && is_readable($path)) {
            $raw = (string)@file_get_contents($path);
            if ($raw !== '') {
                setConfig(apSshCredentialsConfigKey(), $raw);
                @unlink($path);
            }
        }
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $host = (string)($data['host'] ?? '');
        $port = (int)($data['port'] ?? 22);
        $keyPath = (string)($data['key_path'] ?? '');

        $user = apSshDecrypt($data['user_enc'] ?? []);
        $password = apSshDecrypt($data['password_enc'] ?? []);
        $keyPassphrase = apSshDecrypt($data['key_passphrase_enc'] ?? []);

        if ($host === '' || $user === '' || $port < 1 || $port > 65535) {
            // Treat invalid storage as "no profile".
            return [];
        }

        return [
            'host' => $host,
            'port' => $port,
            'backend' => 'phpseclib',
            'user' => $user,
            'password' => '',
            'key_path' => apSshResolveKeyPath($keyPath, false) ?: apSshResolveKeyPath('', false),
            'key_passphrase' => $keyPassphrase,
        ];
    }
}

if (!function_exists('apSshWriteSavedProfile')) {
    function apSshWriteSavedProfile(array $profile): bool
    {
        $host = (string)($profile['host'] ?? '');
        $port = (int)($profile['port'] ?? 22);
        $user = (string)($profile['user'] ?? '');
        $keyPath = apSshResolveKeyPath((string)($profile['key_path'] ?? ''), false);
        if ($keyPath === '') {
            $keyPath = apSshResolveKeyPath('', false);
        }
        $keyPassphrase = (string)($profile['key_passphrase'] ?? '');

        if ($host === '' || $user === '' || $port < 1 || $port > 65535 || $keyPath === '') {
            return false;
        }

        $payload = [
            'version' => 1,
            'updated_at' => gmdate('c'),
            'host' => $host,
            'port' => $port,
            'key_path' => $keyPath,
            'user_enc' => apSshEncrypt($user),
            'password_enc' => apSshEncrypt(''),
            'key_passphrase_enc' => apSshEncrypt($keyPassphrase),
        ];

        if (!function_exists('setConfig')) {
            return false;
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return false;
        }

        try {
            setConfig(apSshCredentialsConfigKey(), $json);
        } catch (Throwable $e) {
            return false;
        }

        $legacy = apSshCredentialsFile();
        if (is_file($legacy)) {
            @unlink($legacy);
        }
        return true;
    }
}

if (!function_exists('apSshEnsureSessionProfile')) {
    function apSshEnsureSessionProfile(): array
    {
        $cfg = $_SESSION['ssh_terminal'] ?? [];
        $needs = empty($cfg) || empty($cfg['host']) || empty($cfg['user']);
        if ($needs) {
            $saved = apSshReadSavedProfile();
            if (!empty($saved)) {
                $_SESSION['ssh_terminal'] = $saved;
                $cfg = $saved;
            }
        }
        return $cfg;
    }
}

if (!function_exists('apSshLoginPhpseclib')) {
    function apSshLoginPhpseclib(array $cfg): array
    {
        apSshEnsureVendorLoaded();
        if (!class_exists('\phpseclib3\Net\SSH2')) {
            return ['success' => false, 'error' => 'phpseclib is not installed'];
        }
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = (int)($cfg['port'] ?? 22);
        $user = $cfg['user'] ?? 'root';
        $keyPath = apSshResolveKeyPath((string)($cfg['key_path'] ?? ''));
        $keyPassphrase = (string)($cfg['key_passphrase'] ?? '');
        if ($keyPath === '' || !class_exists('\phpseclib3\Crypt\PublicKeyLoader')) {
            return ['success' => false, 'error' => 'SSH private key is not configured or readable'];
        }
        try {
            $ssh = new \phpseclib3\Net\SSH2($host, $port, 30);
            $rawKey = (string)file_get_contents($keyPath);
            $key = \phpseclib3\Crypt\PublicKeyLoader::loadPrivateKey($rawKey, $keyPassphrase !== '' ? $keyPassphrase : null);
            $ok = $ssh->login($user, $key);
            if (!$ok) {
                return ['success' => false, 'error' => 'SSH key authentication failed'];
            }
            return ['success' => true, 'ssh' => $ssh];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => apSshEnsureUtf8('phpseclib error: ' . $e->getMessage())];
        }
    }
}

if (!function_exists('apSshSanitizeWin')) {
    function apSshSanitizeWin($value, int $default, int $min, int $max): int
    {
        if (is_int($value)) {
            $n = $value;
        } elseif (is_float($value)) {
            $n = (int) round($value);
        } elseif (is_string($value) && $value !== '' && is_numeric($value)) {
            $n = (int) round((float) $value);
        } else {
            $n = $default;
        }
        return max($min, min($max, $n));
    }
}

if (!function_exists('apSshConnectShell')) {
    function apSshConnectShell(array $cfg, int $cols = 80, int $rows = 24): array
    {
        $login = apSshLoginPhpseclib($cfg);
        if (empty($login['success']) || !($login['ssh'] instanceof \phpseclib3\Net\SSH2)) {
            return ['success' => false, 'error' => (string)($login['error'] ?? 'SSH login failed')];
        }
        $ssh = $login['ssh'];
        $cols = apSshSanitizeWin($cols, 80, 20, 500);
        $rows = apSshSanitizeWin($rows, 24, 5, 200);
        try {
            $ssh->setTerminal('xterm-256color');
            $ssh->setWindowSize($cols, $rows);
            if (!$ssh->openShell() || !$ssh->isShellOpen()) {
                return ['success' => false, 'error' => 'Failed to open interactive shell'];
            }
            $ssh->setTimeout(0.1);
            return ['success' => true, 'ssh' => $ssh];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => apSshEnsureUtf8($e->getMessage())];
        }
    }
}

if (!function_exists('apSshRunPhpseclibCommand')) {
    function apSshRunPhpseclibCommand(array $cfg, string $command): array
    {
        $login = apSshLoginPhpseclib($cfg);
        if (empty($login['success']) || !($login['ssh'] instanceof \phpseclib3\Net\SSH2)) {
            return ['success' => false, 'error' => (string)($login['error'] ?? 'phpseclib login failed')];
        }
        try {
            $ssh = $login['ssh'];
            $output = (string)$ssh->exec($command . ' 2>&1');
            $exit = $ssh->getExitStatus();
            $cleanOutput = apSshEnsureUtf8(trim($output));
            return [
                'success' => $exit === null || $exit === 0,
                'exit_code' => $exit,
                'output' => $cleanOutput,
                'error' => $exit === null || $exit === 0 ? null : ($cleanOutput !== '' ? $cleanOutput : 'Command failed'),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => apSshEnsureUtf8('phpseclib error: ' . $e->getMessage())];
        }
    }
}

if (!function_exists('apSshWsTokenDir')) {
    function apSshWsTokenDir(): string
    {
        $dir = appdata_data_dir() . '/ssh_ws_tokens';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }
}

if (!function_exists('apSshWsTokenPath')) {
    function apSshWsTokenPath(string $token): string
    {
        return apSshWsTokenDir() . '/' . hash('sha256', $token) . '.json';
    }
}

if (!function_exists('apSshIssueWsToken')) {
    function apSshIssueWsToken(array $cfg): string
    {
        $token = bin2hex(random_bytes(32));
        $payload = [
            'exp' => time() + (int)(getenv('SSH_WS_TOKEN_TTL') ?: 28800),
            'cfg' => [
                'host' => (string)($cfg['host'] ?? ''),
                'port' => (int)($cfg['port'] ?? 22),
                'user' => (string)($cfg['user'] ?? ''),
                'key_path' => apSshResolveKeyPath((string)($cfg['key_path'] ?? '')),
                'key_passphrase' => (string)($cfg['key_passphrase'] ?? ''),
            ],
        ];
        $path = apSshWsTokenPath($token);
        file_put_contents($path, json_encode($payload), LOCK_EX);
        @chmod($path, 0600);
        return $token;
    }
}

if (!function_exists('apSshLoadWsToken')) {
    function apSshLoadWsToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $path = apSshWsTokenPath($token);
        if (!is_readable($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data) || empty($data['cfg']) || (int)($data['exp'] ?? 0) < time()) {
            @unlink($path);
            return null;
        }
        $cfg = $data['cfg'];
        if (empty($cfg['host']) || empty($cfg['user']) || empty($cfg['key_path'])) {
            return null;
        }
        return $cfg;
    }
}

if (!function_exists('apSshWsPublicUrl')) {
    function apSshWsPublicUrl(): string
    {
        $path = (string)(getenv('SSH_WS_PATH') ?: '/ssh-ws');
        if ($path === '' || $path[0] !== '/') {
            $path = '/ssh-ws';
        }
        $custom = (string)(getenv('SSH_WS_PUBLIC_URL') ?: '');
        if ($custom !== '') {
            return rtrim($custom, '/') . $path;
        }
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $proto = 'ws';
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')) {
            $proto = 'wss';
        }
        return $proto . '://' . $host . $path;
    }
}

if (!function_exists('handleSshTerminalRequest')) {
    function handleSshTerminalRequest(bool $sessionIsAdmin): bool
    {
        $isInit = isset($_POST['ssh_terminal_init']) && isset($_POST['ajax']);
        $isExec = isset($_POST['ssh_terminal_exec']) && isset($_POST['ajax']);
        $isGetProfile = isset($_POST['ssh_terminal_get_profile']) && isset($_POST['ajax']);
        $isWsToken = isset($_POST['ssh_terminal_ws_token']) && isset($_POST['ajax']);
        if (!$isInit && !$isExec && !$isGetProfile && !$isWsToken) {
            return false;
        }

        if (!$sessionIsAdmin) {
            apSshJsonResponse(['success' => false, 'error' => 'Forbidden']);
            return true;
        }

        if (!isset($_SESSION['ssh_terminal']) || !is_array($_SESSION['ssh_terminal'])) {
            $_SESSION['ssh_terminal'] = [];
        }

        // Handle SSH key rotation
        $isRotateKey = isset($_POST['ssh_rotate_key']);
        if ($isRotateKey) {
            adminRequireCapability('ssh');
            adminRequireMutationThrottle('ssh', 5, 60);
            $rotated = apSshRotateHostKey();
            if ($rotated) {
                apSshJsonResponse([
                    'success' => true,
                    'message' => 'SSH key rotated successfully. The public key has been updated.',
                    'public_key' => $rotated['public_key'] ?? '',
                ]);
            } else {
                apSshJsonResponse([
                    'success' => false,
                    'error' => 'Failed to rotate SSH key',
                ]);
            }
            return true;
        }

        if ($isGetProfile) {
            $saved = apSshReadSavedProfile();
            $defaultKey = apSshDiscoverKeyPath();
            $keysDir = apSshKeysDir();
            if (empty($saved)) {
                apSshJsonResponse([
                    'success' => false,
                    'error' => 'No saved SSH profile',
                    'default_key_path' => $defaultKey,
                    'keys_dir' => $keysDir,
                ]);
                return true;
            }
            apSshJsonResponse([
                'success' => true,
                'host' => $saved['host'],
                'user' => $saved['user'],
                'port' => (int)$saved['port'],
                'key_path' => (string)($saved['key_path'] ?? ''),
                'default_key_path' => apSshDiscoverKeyPath(),
                'keys_dir' => apSshKeysDir(),
                'has_key_passphrase' => !empty($saved['key_passphrase'] ?? ''),
                'backend' => $saved['backend'] ?? 'phpseclib',
            ]);
            return true;
        }

        if ($isWsToken) {
            $cfg = apSshEnsureSessionProfile();
            if (empty($cfg['host']) || empty($cfg['user'])) {
                apSshJsonResponse(['success' => false, 'error' => 'SSH is not initialized. Save SSH profile first.']);
                return true;
            }
            $token = apSshIssueWsToken($cfg);
            $_SESSION['ssh_ws_token'] = $token;
            apSshJsonResponse([
                'success' => true,
                'token' => $token,
                'ws_url' => apSshWsPublicUrl(),
            ]);
            return true;
        }

        if ($isInit) {
            // Auto-configure to connect to host machine
            $host = apSshResolveTargetHost();
            $port = (int)(getenv('SSH_DEFAULT_PORT') ?: getenv('SSH_PORT') ?: 22);
            $user = apSshResolveTargetUser();
            $backend = 'phpseclib';
            // Use the dedicated host key
            $keyPath = apSshGetHostKeyPath();

            // If host key doesn't exist, discover any available key
            if (!is_readable($keyPath)) {
                $keyPath = apSshDiscoverKeyPath();
            }
            
            if ($keyPath === '') {
                apSshJsonResponse([
                    'success' => false,
                    'error' => 'SSH private key not found. Place id_ed25519_host or id_ed25519 in ' . apSshKeysDir(),
                ]);
                return true;
            }
            
            $keyPassphrase = '';

            $_SESSION['ssh_terminal'] = [
                'host' => $host,
                'user' => $user,
                'port' => $port,
                'backend' => $backend,
                'password' => '',
                'key_path' => $keyPath,
                'key_passphrase' => $keyPassphrase,
            ];

            apSshWriteSavedProfile($_SESSION['ssh_terminal']);

            $probe = apSshRunPhpseclibCommand($_SESSION['ssh_terminal'], 'echo SSH_CONNECTED');
            $wsToken = apSshIssueWsToken($_SESSION['ssh_terminal']);
            $_SESSION['ssh_ws_token'] = $wsToken;

            $hint = '';
            if (empty($probe['success'])) {
                $hint = ' Add this public key to ~/.ssh/authorized_keys of ' . $user . '@' . $host . " on the host:\n"
                    . apSshPublicKeyFor($keyPath);
            }

            apSshJsonResponse([
                'success' => !empty($probe['success']),
                'message' => 'SSH profile saved',
                'backend' => $backend,
                'host' => $host,
                'connected' => !empty($probe['success']),
                'connect_output' => apSshEnsureUtf8((string)($probe['output'] ?? '')),
                'connect_error' => apSshEnsureUtf8((string)($probe['error'] ?? '')) . $hint,
                'ws_token' => $wsToken,
                'ws_url' => apSshWsPublicUrl(),
            ]);
            return true;
        }

        $cmd = trim((string)($_POST['command'] ?? ''));
        if ($cmd === '') {
            apSshJsonResponse([
                'success' => false,
                'error' => 'Command is empty',
                'backend' => 'unknown',
            ]);
            return true;
        }

        $cfg = apSshEnsureSessionProfile();
        if (empty($cfg['host']) || empty($cfg['user'])) {
            apSshJsonResponse([
                'success' => false,
                'error' => 'SSH is not initialized. Click "Save SSH Profile" first and wait for connection status.',
                'backend' => 'unknown',
            ]);
            return true;
        }

        $backend = 'phpseclib';
        $res = apSshRunPhpseclibCommand($cfg, $cmd);

        $res['backend'] = $backend;
        $res['output'] = apSshEnsureUtf8((string)($res['output'] ?? ''));
        $res['error'] = apSshEnsureUtf8((string)($res['error'] ?? ''));
        apSshJsonResponse($res);
        return true;
    }
}
