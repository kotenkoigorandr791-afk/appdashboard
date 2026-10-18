<?php
declare(strict_types=1);

/**
 * Speech-to-text through the AI hub (OmniRoute).
 *
 * The hub exposes the OpenAI-compatible POST /v1/audio/transcriptions endpoint.
 * The base URL and API key are the ones configured in Admin Panel → Settings → AI
 * Assistant, and the model is the saved "speech_model" value, so transcription
 * runs on exactly the model that was set up for it instead of a local Whisper
 * install.
 */

// The CLI worker does not load smart_log_boot.php, so make the logger used by
// the shared config store available there as well.
if (!function_exists('cloud_safe_error_log')) {
    function cloud_safe_error_log(string $message): void
    {
        error_log($message);
    }
}

require_once __DIR__ . '/ai_config_store.php';
require_once __DIR__ . '/ai_proxy.php';

const CLOUD_AI_SPEECH_FALLBACK_MODEL = 'whisper-1';
const CLOUD_AI_SPEECH_TIMEOUT_SECONDS = 900;

/**
 * Model saved for speech recognition (Admin settings → speech_model).
 */
function cloud_ai_speech_model(): string
{
    $config = cloud_ai_load_config();
    $model = trim((string)($config['speech_model'] ?? ''));
    if ($model === '') {
        $model = CLOUD_AI_SPEECH_FALLBACK_MODEL;
    }
    return cloud_ai_normalize_model_id($model);
}

/**
 * Transcription endpoint derived from the configured chat completions URL:
 * https://hub.example/v1/chat/completions → https://hub.example/v1/audio/transcriptions
 */
function cloud_ai_speech_endpoint(array $config): string
{
    $endpoint = trim((string)($config['api_endpoint'] ?? ''));
    if ($endpoint === '') {
        $endpoint = cloud_ai_default_endpoint();
    }
    $base = preg_replace('#/(?:chat/)?completions/?$#i', '', $endpoint);
    return rtrim((string)$base, '/') . '/audio/transcriptions';
}

/**
 * Sends a local audio file to the AI hub and returns the recognized text.
 *
 * @param array{timeout?: int, on_tick?: callable(int, string): bool} $options
 *        on_tick receives an estimated percentage and the progress message; it
 *        returns false to abort the request (used by the stop button).
 * @return array{ok: bool, text: string, error: string, http: int, model: string, raw: string}
 */
function cloud_ai_transcribe_file(string $filePath, string $language = '', array $options = []): array
{
    $fail = static fn(string $error, int $http = 0): array => [
        'ok' => false,
        'text' => '',
        'error' => $error,
        'http' => $http,
        'model' => '',
        'raw' => '',
    ];

    if ($filePath === '' || !is_file($filePath)) {
        return $fail('Audio file not found');
    }
    if (!is_readable($filePath)) {
        return $fail('Audio file is not readable');
    }

    $config = cloud_ai_load_config();
    $apiKey = trim((string)($config['api_key'] ?? ''));
    if ($apiKey === '') {
        return $fail('AI hub API key is not configured (Admin Panel → Settings → AI Assistant)');
    }

    $model = cloud_ai_speech_model();
    $endpoint = cloud_ai_speech_endpoint($config);
    $timeout = (int)($options['timeout'] ?? CLOUD_AI_SPEECH_TIMEOUT_SECONDS);
    if ($timeout <= 0) {
        $timeout = CLOUD_AI_SPEECH_TIMEOUT_SECONDS;
    }
    $onTick = $options['on_tick'] ?? null;
    $aborted = false;

    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $detected = @mime_content_type($filePath);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }

    $fields = [
        'file' => new CURLFile($filePath, $mime, basename($filePath)),
        'model' => $model,
        'response_format' => 'json',
        'temperature' => '0',
    ];
    $language = trim($language);
    if ($language !== '' && strtolower($language) !== 'auto') {
        $fields['language'] = $language;
    }

    $ch = curl_init($endpoint);
    if ($ch === false) {
        return $fail('Failed to initialise the HTTP client');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
        ],
    ]);

    if (is_callable($onTick)) {
        $lastTick = 0.0;
        if (defined('CURLOPT_XFERINFOFUNCTION')) {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_XFERINFOFUNCTION, static function ($ch, $dlTotal, $dlNow, $ulTotal, $ulNow) use ($onTick, &$lastTick, &$aborted) {
                // Progress ticks are throttled: the worker writes each tick to the
                // database, and curl calls this callback very often while uploading.
                $now = microtime(true);
                if ($now - $lastTick < 1.5) {
                    return 0;
                }
                $lastTick = $now;
                // Upload of the audio file is the first part of the work; the hub
                // does not report progress while it recognizes the audio.
                $percentage = 20;
                if ($ulTotal > 0) {
                    $percentage = 20 + (int)floor(40 * min(1, $ulNow / $ulTotal));
                }
                if ($onTick($percentage, 'Transcribing audio...') === false) {
                    $aborted = true;
                    return 1;
                }
                return 0;
            });
        }
    }

    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);

    if ($curlErrno === CURLE_ABORTED_BY_CALLBACK || $aborted) {
        return $fail('Transcription stopped', $httpCode);
    }
    if ($raw === false) {
        return $fail('AI hub request failed: ' . ($curlError !== '' ? $curlError : 'unknown error'), $httpCode);
    }

    $raw = (string)$raw;
    $decoded = json_decode($raw, true);
    $upstreamMessage = '';
    if (is_array($decoded)) {
        $upstreamMessage = trim((string)($decoded['error']['message'] ?? $decoded['message'] ?? $decoded['detail'] ?? ''));
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        if ($upstreamMessage === '') {
            $upstreamMessage = 'HTTP ' . $httpCode;
        }
        if ($httpCode === 401 || $httpCode === 403) {
            $upstreamMessage = 'AI hub rejected the API key (' . $httpCode . '): ' . $upstreamMessage;
        }
        return [
            'ok' => false,
            'text' => '',
            'error' => $upstreamMessage,
            'http' => $httpCode,
            'model' => $model,
            'raw' => substr($raw, 0, 2000),
        ];
    }

    $text = '';
    if (is_array($decoded)) {
        $text = (string)($decoded['text'] ?? $decoded['transcription'] ?? $decoded['result'] ?? '');
    }
    if ($text === '' && $raw !== '' && !is_array($decoded)) {
        // Some hubs answer with the plain transcript instead of JSON.
        $text = $raw;
    }
    $text = trim($text);
    if ($text === '') {
        return [
            'ok' => false,
            'text' => '',
            'error' => $upstreamMessage !== '' ? $upstreamMessage : 'AI hub returned an empty transcription',
            'http' => $httpCode,
            'model' => $model,
            'raw' => substr($raw, 0, 2000),
        ];
    }

    return [
        'ok' => true,
        'text' => $text,
        'error' => '',
        'http' => $httpCode,
        'model' => $model,
        'raw' => '',
    ];
}
