<?php
declare(strict_types=1);

// Cloudflare drops an origin request after 100s without a response (error 524),
// so streaming keeps the socket warm and blocking calls stay below that budget.
const CLOUD_AI_SSE_HEARTBEAT_SECONDS = 12;
const CLOUD_AI_BLOCKING_TIMEOUT_SECONDS = 85;

function cloud_ai_upstream_models_url(array $config): string
{
    return preg_replace('#/chat/completions(?:\?.*)?$#i', '/models', $config['api_endpoint']);
}

function cloud_ai_upstream_headers(array $config): array
{
    return [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api_key'],
        'Accept: application/json',
    ];
}

function cloud_ai_normalize_model_id(string $model): string
{
    $model = trim($model);
    if ($model === '') return $model;
    if (preg_match('#^kr/(claude-(?:sonnet|opus)-.+)$#i', $model, $m)) {
        return 'kc/anthropic/' . $m[1];
    }
    return $model;
}

function cloud_ai_proxy_json(array $config, string $url, ?array $payload = null, string $method = 'GET', ?int $timeout = null): array
{
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => cloud_ai_upstream_headers($config),
        CURLOPT_TIMEOUT => $timeout ?: CLOUD_AI_BLOCKING_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => 30,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    if ($error !== '') {
        return ['ok' => false, 'http' => 0, 'errno' => $errno, 'body' => '', 'error' => 'CURL error: ' . $error];
    }
    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'http' => $httpCode, 'errno' => 0, 'body' => (string)$body, 'error' => ''];
}

function cloud_ai_proxy_models(array $config): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    // The admin-configured model list (Admin Panel → Settings → AI Assistant)
    // is the source of truth; the upstream /models endpoint is only a fallback
    // when nothing is configured.
    $models = $config['models'] ?? [];
    if (is_array($models) && $models) {
        echo json_encode(['data' => array_map(static fn($id) => ['id' => (string)$id, 'object' => 'model'], $models)], JSON_UNESCAPED_SLASHES);
        return;
    }
    $result = cloud_ai_proxy_json($config, cloud_ai_upstream_models_url($config), null, 'GET');
    if (!$result['ok']) {
        echo json_encode(['error' => ['message' => $result['error'] ?: ('HTTP ' . $result['http'])]]);
        return;
    }
    echo $result['body'];
}


function cloud_ai_proxy_fallback_models(array $config): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    $result = cloud_ai_proxy_json($config, cloud_ai_upstream_models_url($config), null, 'GET', 20);
    if (!$result['ok']) {
        echo json_encode(['data' => [], 'error' => ['message' => $result['error'] ?: ('HTTP ' . $result['http'])]]);
        return;
    }
    $decoded = json_decode((string)$result['body'], true);
    if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
        echo json_encode(['data' => []]);
        return;
    }
    $models = [];
    foreach ($decoded['data'] as $model) {
        $id = is_array($model) ? trim((string)($model['id'] ?? '')) : trim((string)$model);
        if ($id !== '') {
            $models[] = ['id' => $id, 'object' => 'model'];
        }
    }
    echo json_encode(['data' => $models], JSON_UNESCAPED_SLASHES);
}


function cloud_ai_rate_limit_limits(string $kind): array
{
    // Generous for normal work; speech is stricter (costlier upstream).
    if ($kind === 'speech') {
        return [12, 60]; // 12 / minute
    }
    return [60, 60]; // 60 chat/completions / minute
}

function cloud_ai_rate_limit_check(string $userKey, string $kind = 'chat'): array
{
    $userKey = strtolower(trim($userKey));
    if ($userKey === '') {
        return ['ok' => true, 'retry_after' => 0];
    }
    [$max, $windowSec] = cloud_ai_rate_limit_limits($kind);
    $bucket = 'ai_rl:' . $kind . ':' . hash('sha256', $userKey);
    $now = time();
    $redisPath = dirname(__DIR__, 3) . '/lib/redis.php';
    if (is_file($redisPath)) {
        require_once $redisPath;
    }
    if (function_exists('app_redis') && ($redis = app_redis())) {
        try {
            $rk = function_exists('app_redis_key') ? app_redis_key($bucket) : $bucket;
            $count = (int)$redis->incr($rk);
            if ($count === 1) {
                $redis->expire($rk, $windowSec);
            }
            $ttl = (int)$redis->ttl($rk);
            if ($count > $max) {
                return ['ok' => false, 'retry_after' => max(1, $ttl > 0 ? $ttl : $windowSec)];
            }
            return ['ok' => true, 'retry_after' => 0];
        } catch (Throwable $e) {
            // fall through to session counter
        }
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!isset($_SESSION['_ai_rate_limit']) || !is_array($_SESSION['_ai_rate_limit'])) {
            $_SESSION['_ai_rate_limit'] = [];
        }
        $entry = $_SESSION['_ai_rate_limit'][$bucket] ?? ['t' => $now, 'n' => 0];
        if (($now - (int)$entry['t']) > $windowSec) {
            $entry = ['t' => $now, 'n' => 0];
        }
        $entry['n'] = (int)$entry['n'] + 1;
        $_SESSION['_ai_rate_limit'][$bucket] = $entry;
        if ($entry['n'] > $max) {
            return ['ok' => false, 'retry_after' => max(1, $windowSec - ($now - (int)$entry['t']))];
        }
    }
    return ['ok' => true, 'retry_after' => 0];
}

function cloud_ai_require_rate_limit(string $userKey, string $kind = 'chat'): void
{
    $check = cloud_ai_rate_limit_check($userKey, $kind);
    if (!empty($check['ok'])) {
        return;
    }
    $retry = (int)($check['retry_after'] ?? 60);
    if (!headers_sent()) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        header('Retry-After: ' . $retry);
        header('Cache-Control: no-store');
    }
    echo json_encode([
        'error' => [
            'message' => 'Too many AI requests. Try again in ' . $retry . ' seconds.',
            'code' => 429,
            'retry_after' => $retry,
        ],
        'success' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function cloud_ai_proxy_completions(array $config, array $payload): void
{
    if (!empty($payload['model'])) {
        $payload['model'] = cloud_ai_normalize_model_id((string)$payload['model']);
    }
    $stream = !empty($payload['stream']);
    if ($stream) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        @set_time_limit(0);
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        // Flush the response head immediately so proxies (Cloudflare 524) do not
        // time out while the upstream model is still preparing the first token.
        echo ': open' . "\n\n";
        flush();
        $lastByteAt = microtime(true);
        $atEventBoundary = true;
        $errorBody = '';
        $ch = curl_init();
        $status = 0;
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['api_endpoint'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => cloud_ai_upstream_headers($config),
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$lastByteAt, &$status, &$errorBody, &$atEventBoundary) {
                $lastByteAt = microtime(true);
                $atEventBoundary = str_ends_with($chunk, "\n");
                if ($status >= 400) {
                    if (strlen($errorBody) < 8192) {
                        $errorBody .= $chunk;
                    }
                    return strlen($chunk);
                }
                echo $chunk;
                if (function_exists('flush')) {
                    flush();
                }
                return strlen($chunk);
            },
            CURLOPT_XFERINFOFUNCTION => static function ($ch, $dlTotal, $dlNow, $ulTotal, $ulNow) use (&$lastByteAt, &$atEventBoundary) {
                if (connection_aborted()) {
                    return 1;
                }
                if ($atEventBoundary && microtime(true) - $lastByteAt >= CLOUD_AI_SSE_HEARTBEAT_SECONDS) {
                    echo ': ping' . "\n\n";
                    if (function_exists('flush')) {
                        flush();
                    }
                    $lastByteAt = microtime(true);
                }
                return 0;
            },
            CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use (&$status) {
                if (preg_match('#^HTTP/\\S+\\s+(\\d+)#', $header, $m)) {
                    $status = (int)$m[1];
                }
                return strlen($header);
            },
        ]);
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        if ($errno === CURLE_ABORTED_BY_CALLBACK) {
            return;
        }
        if ($error !== '' && $httpCode === 0) {
            echo "data: " . json_encode(['error' => ['message' => 'CURL error: ' . $error]]) . "\n\n";
        } elseif ($httpCode >= 400 || $status >= 400) {
            $upstreamCode = $httpCode ?: $status;
            $decoded = json_decode($errorBody, true);
            $message = is_array($decoded) ? (string)($decoded['error']['message'] ?? $decoded['message'] ?? '') : '';
            if ($message === '') {
                $message = $upstreamCode === 524 || $upstreamCode === 504
                    ? 'Upstream timeout ' . $upstreamCode . ': the model did not answer in time'
                    : 'Upstream HTTP ' . $upstreamCode;
            }
            echo "data: " . json_encode(['error' => ['message' => $message, 'code' => $upstreamCode]]) . "\n\n";
        }
        flush();
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    $result = cloud_ai_proxy_json($config, $config['api_endpoint'], $payload, 'POST');
    $timedOut = (int)($result['errno'] ?? 0) === CURLE_OPERATION_TIMEDOUT;
    http_response_code($result['http'] > 0 ? $result['http'] : ($timedOut ? 504 : 502));
    if ($result['body'] !== '') {
        // Always answer with JSON: an upstream/WAF error page is HTML and would
        // otherwise reach the browser as a non-JSON body ("network error").
        $decoded = json_decode($result['body'], true);
        if (is_array($decoded) && isset($decoded['error'])) {
            echo json_encode($decoded);
            return;
        }
        if (is_array($decoded) && !isset($decoded['error']) && $result['ok']) {
            echo json_encode($decoded);
            return;
        }
        $message = is_array($decoded)
            ? (string)($decoded['error']['message'] ?? $decoded['message'] ?? '')
            : '';
        if ($message === '') {
            $message = 'Upstream HTTP ' . ($result['http'] ?: 502) . ': the request was blocked or failed by the gateway';
        }
        echo json_encode(['error' => ['message' => $message, 'code' => $result['http']]]);
        return;
    }
    echo json_encode(['error' => ['message' => $result['error'] ?: 'Upstream request failed']]);
}
