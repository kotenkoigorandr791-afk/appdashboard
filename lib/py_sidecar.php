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
 * POST form fields and return raw body (PDF/binary or JSON error text).
 *
 * @param array<string, mixed> $formFields
 * @return array{ok: bool, code: int, raw: string, error: string, content_type: string, headers: array<string, string>}
 */
function py_sidecar_raw(string $endpoint, array $formFields = [], int $timeout = 300): array
{
    $url = py_sidecar_url() . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'code' => 0, 'raw' => '', 'error' => 'curl_init_failed', 'content_type' => '', 'headers' => []];
    }
    $headerBag = [];
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $formFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use (&$headerBag): int {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headerBag[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        },
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if ($raw === false) {
        return ['ok' => false, 'code' => $code, 'raw' => '', 'error' => $error ?: 'curl_failed', 'content_type' => '', 'headers' => $headerBag];
    }
    return [
        'ok' => $code >= 200 && $code < 300,
        'code' => $code,
        'raw' => $raw,
        'error' => '',
        'content_type' => $contentType,
        'headers' => $headerBag,
    ];
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
