<?php
declare(strict_types=1);

if (!function_exists('cloud_api_user')) {
    function cloud_api_user(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $email = function_exists('get_session_email') ? get_session_email() : null;
        if (!$email) {
            json_out(['error' => 'not authenticated', 'ok' => false], 401);
        }
        $user = function_exists('email_to_cloud_username')
            ? email_to_cloud_username($email)
            : preg_replace('/[^a-zA-Z0-9_\-]/', '_', str_replace(['@', '.'], '_', $email));
        if ($user === '' || $user === 'guest') {
            json_out(['error' => 'not authenticated', 'ok' => false], 401);
        }
        $cached = $user;
        return $cached;
    }
}

if (!function_exists('cloud_api_email')) {
    function cloud_api_email(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $email = function_exists('get_session_email') ? get_session_email() : null;
        if (!$email) {
            json_out(['error' => 'not authenticated', 'ok' => false], 401);
        }
        $cached = strtolower(trim((string)$email));
        return $cached;
    }
}

if (!function_exists('cloud_public_api_actions')) {
    function cloud_public_api_actions(): array
    {
        return [
            'pdf_stream',
            'archive_job_download',
            'archive_worker_run',
            'video_generation_worker',
            'office_callback',
        ];
    }
}

if (!function_exists('cloud_require_api_auth_unless_public')) {
    function cloud_require_api_auth_unless_public(string $action): void
    {
        if ($action === '' || in_array($action, cloud_public_api_actions(), true)) {
            return;
        }
        cloud_api_user();
    }
}

if (!function_exists('cloud_request_host_ok')) {
    function cloud_request_host_ok(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $reqHost = strtolower((string)$parts['host']);
        $allowed = strtolower(function_exists('cloud_public_host') ? cloud_public_host() : (string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($allowed === '') {
            return false;
        }
        // HTTP_HOST may include a port ("127.0.0.1:8080"), while parse_url()
        // returns the host without it. Compare the hosts only, otherwise every
        // same-origin POST is rejected as CSRF on non-default ports.
        $allowedHost = $allowed;
        $colon = strrpos($allowedHost, ':');
        if ($colon !== false && strpos($allowedHost, ']') === false) {
            $allowedHost = substr($allowedHost, 0, $colon);
        }
        $allowedHost = trim($allowedHost, '[]');
        if ($allowedHost === '') {
            return false;
        }
        return hash_equals($allowedHost, $reqHost);
    }
}

if (!function_exists('cloud_require_cookie_csrf')) {
    function cloud_require_cookie_csrf(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        if (function_exists('get_email_from_client_auth_header') && get_email_from_client_auth_header()) {
            return;
        }
        $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
        $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        $csrf = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($origin !== '' && cloud_request_host_ok($origin)) {
            return;
        }
        if ($referer !== '' && cloud_request_host_ok($referer)) {
            return;
        }
        if ($csrf !== '' && isset($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
            return;
        }
        if ($origin === '' && $referer === '' && $csrf === '') {
            $secFetchSite = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
            if ($secFetchSite === 'same-origin' || $secFetchSite === 'same-site') {
                return;
            }
        }
        json_out(['error' => 'csrf rejected', 'ok' => false], 403);
    }
}

if (!function_exists('cloud_rate_limit_hit')) {
    function cloud_rate_limit_hit(string $bucket, int $max, int $windowSec): bool
    {
        $redis = function_exists('app_redis') ? @app_redis() : null;
        if ($redis) {
            try {
                $rk = 'cloud_rl:' . hash('sha256', $bucket) . ':' . intdiv(time(), max(1, $windowSec));
                $n = (int)$redis->incr($rk);
                if ($n === 1) $redis->expire($rk, $windowSec + 1);
                return $n > $max;
            } catch (Throwable $e) {
            }
        }
        $dir = sys_get_temp_dir() . '/cloud_rate_limit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . hash('sha256', $bucket) . '.json';
        $now = time();
        $data = ['t' => $now, 'n' => 0];
        $fh = @fopen($file, 'c+');
        if (!$fh) {
            return false;
        }
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if (($now - (int)($data['t'] ?? 0)) >= $windowSec) {
            $data = ['t' => $now, 'n' => 0];
        }
        $data['n'] = (int)($data['n'] ?? 0) + 1;
        $over = $data['n'] > $max;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        return $over;
    }
}

if (!function_exists('cloud_require_rate_limit')) {
    function cloud_require_rate_limit(string $key, string $kind = 'api'): void
    {
        $limits = [
            'api' => [240, 60],
            'upload' => [600, 60],
            'utility' => [30, 60],
        ];
        [$max, $window] = $limits[$kind] ?? [60, 60];
        if (cloud_rate_limit_hit($kind . ':' . $key, $max, $window)) {
            json_out(['error' => 'rate_limited', 'ok' => false], 429);
        }
    }
}

if (!function_exists('cloud_touch_client_token_throttled')) {
    function cloud_touch_client_token_throttled(AppDatabase $db, string $tokenHash, int $minIntervalSec = 600): void
    {
        static $done = [];
        if (isset($done[$tokenHash])) {
            return;
        }
        $done[$tokenHash] = true;
        try {
            $stmt = $db->prepare('UPDATE client_auth_tokens SET last_used_at = :t WHERE token_hash = :h AND (last_used_at IS NULL OR last_used_at < :min)');
            $now = time();
            $stmt->bindValue(':t', $now, PDO::PARAM_INT);
            $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
            $stmt->bindValue(':min', $now - $minIntervalSec, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('cloud_used_bytes_cached')) {
    function cloud_used_bytes_cached(?string $email, string $user, bool $forceRefresh = false, int $ttlSec = 300): int
    {
        $root = function_exists('user_root') ? user_root($user) : '';
        if ($email && function_exists('getDB')) {
            $db = getDB();
            if ($db) {
                try {
                    if (function_exists('ensure_storage_usage_table')) {
                        ensure_storage_usage_table($db);
                    }
                    if (!$forceRefresh) {
                        $stmt = $db->prepare('SELECT root_total_bytes, updated_at FROM cloud_storage_usage_stats WHERE email = :e LIMIT 1');
                        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
                        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
                        if ($row && (time() - (int)($row['updated_at'] ?? 0)) <= $ttlSec) {
                            return max(0, (int)($row['root_total_bytes'] ?? 0));
                        }
                    }
                    if (function_exists('save_storage_usage_stats')) {
                        $res = save_storage_usage_stats($email, $user);
                        if (!empty($res['ok'])) {
                            return max(0, (int)($res['root_total_bytes'] ?? 0));
                        }
                    }
                } catch (Throwable $e) {
                }
            }
        }
        if ($root !== '' && is_dir($root) && function_exists('dir_size')) {
            return dir_size($root);
        }
        return 0;
    }
}

if (!function_exists('cloud_adjust_usage_cache')) {
    function cloud_adjust_usage_cache(?string $email, int $deltaBytes): void
    {
        if (!$email || $deltaBytes === 0 || !function_exists('getDB')) {
            return;
        }
        $db = getDB();
        if (!$db) {
            return;
        }
        try {
            if (function_exists('ensure_storage_usage_table')) {
                ensure_storage_usage_table($db);
            }
            $stmt = $db->prepare('UPDATE cloud_storage_usage_stats SET root_total_bytes = GREATEST(0, root_total_bytes + :d), updated_at = extract(epoch from now())::bigint WHERE email = :e');
            $stmt->bindValue(':d', $deltaBytes, PDO::PARAM_INT);
            $stmt->bindValue(':e', $email, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('cloud_session_close_early')) {
    function cloud_session_close_early(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
