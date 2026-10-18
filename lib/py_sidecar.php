<?php
declare(strict_types=1);

function py_sidecar_url(): string
{
    return rtrim((string)(getenv('PY_SIDECAR_URL') ?: ''), '/');
}

function py_sidecar_enabled(): bool
{
    return py_sidecar_url() !== '';
}

/**
 * @param array<string, mixed> $formFields
 * @return array{body: mixed, code: int, raw: string, error: string}
 */
function py_sidecar(string $endpoint, array $formFields = [], ?string $filePath = null, ?string $fileField = 'file', int $timeout = 300): array
{
    $url = py_sidecar_url() . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        return ['body' => null, 'code' => 0, 'raw' => '', 'error' => 'curl_init_failed'];
    }
    $postFields = $formFields;
    if ($filePath !== null && $filePath !== '' && is_file($filePath)) {
        $postFields[$fileField] = new CURLFile($filePath, mime_content_type($filePath) ?: null, basename($filePath));
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        return ['body' => null, 'code' => $code, 'raw' => '', 'error' => $error ?: 'curl_failed'];
    }
    $decoded = json_decode($raw, true);
    return ['body' => $decoded, 'code' => $code, 'raw' => $raw, 'error' => ''];
}

/**
 * @param array<string, mixed> $formFields
 * @return array{ok: bool, code: int, raw: string, error: string}
 */
function py_sidecar_binary(string $endpoint, array $formFields, string $filePath, string $fileField = 'markdown', int $timeout = 120): array
{
    $url = py_sidecar_url() . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'code' => 0, 'raw' => '', 'error' => 'curl_init_failed'];
    }
    $postFields = $formFields;
    $postFields[$fileField] = new CURLFile($filePath, 'text/markdown', basename($filePath));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        return ['ok' => false, 'code' => $code, 'raw' => '', 'error' => $error ?: 'curl_failed'];
    }
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'raw' => $raw, 'error' => ''];
}
