<?php
declare(strict_types=1);

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

function cloud_ai_proxy_json(array $config, string $url, ?array $payload = null, string $method = 'GET'): array
{
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => cloud_ai_upstream_headers($config),
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 30,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error !== '') {
        return ['ok' => false, 'http' => 0, 'body' => '', 'error' => 'CURL error: ' . $error];
    }
    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'http' => $httpCode, 'body' => (string)$body, 'error' => ''];
}

function cloud_ai_proxy_models(array $config): void
{
    header('Content-Type: application/json; charset=utf-8');
    $result = cloud_ai_proxy_json($config, cloud_ai_upstream_models_url($config), null, 'GET');
    if (!$result['ok']) {
        $models = $config['models'] ?? [];
        if (is_array($models) && $models) {
            echo json_encode(['data' => array_map(static fn($id) => ['id' => (string)$id, 'object' => 'model'], $models)], JSON_UNESCAPED_SLASHES);
            return;
        }
        echo json_encode(['error' => ['message' => $result['error'] ?: ('HTTP ' . $result['http'])]]);
        return;
    }
    echo $result['body'];
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
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        $ch = curl_init();
        $status = 0;
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['api_endpoint'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => cloud_ai_upstream_headers($config),
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) {
                echo $chunk;
                if (function_exists('flush')) {
                    flush();
                }
                return strlen($chunk);
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
        $error = curl_error($ch);
        curl_close($ch);
        if ($error !== '' && $httpCode === 0) {
            echo "data: " . json_encode(['error' => ['message' => 'CURL error: ' . $error]]) . "\n\n";
        } elseif (($httpCode >= 400 || $status >= 400) && !headers_sent()) {
            echo "data: " . json_encode(['error' => ['message' => 'Upstream HTTP ' . ($httpCode ?: $status)]]) . "\n\n";
        }
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    $result = cloud_ai_proxy_json($config, $config['api_endpoint'], $payload, 'POST');
    http_response_code($result['http'] > 0 ? $result['http'] : 502);
    if ($result['body'] !== '') {
        echo $result['body'];
        return;
    }
    echo json_encode(['error' => ['message' => $result['error'] ?: 'Upstream request failed']]);
}
