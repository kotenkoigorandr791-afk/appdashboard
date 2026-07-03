<?php
declare(strict_types=1);

/**
 * ai_tools_speech.php
 * 
 * Protected AI tool wrappers for TTS (Text-to-Speech) and Transcription APIs.
 * All functions require authentication and forward the session cookie.
 */

/**
 * Build URL for internal API requests to the same server.
 */
function cloud_ai_speech_api_url(string $endpoint): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    return rtrim($scheme . $host . $baseDir, '/') . '/' . ltrim($endpoint, '/');
}

/**
 * Forward session cookies for internal API requests.
 */
function cloud_ai_speech_cookie_string(): string
{
    $cookies = [];
    if (session_id() !== '' && isset($_COOKIE[session_name()])) {
        // Forward the PHP session cookie explicitly
        $cookies[] = session_name() . '=' . rawurlencode($_COOKIE[session_name()]);
    }
    foreach ($_COOKIE as $name => $value) {
        if ($name === session_name()) {
            continue; // already added above
        }
        $cookies[] = rawurlencode((string)$name) . '=' . rawurlencode((string)$value);
    }
    return implode('; ', $cookies);
}

/**
 * Make an internal POST request to a local API endpoint.
 * Forwards the session cookie for authentication.
 */
function cloud_ai_speech_api_post(string $endpoint, array $params): array
{
    $url = cloud_ai_speech_api_url($endpoint);
    $cookieStr = cloud_ai_speech_cookie_string();

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_COOKIE => $cookieStr,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            return ['ok' => false, 'error' => 'API request failed: ' . $error];
        }
    } else {
        // Fallback: use file_get_contents
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' =>
                    "Content-Type: application/x-www-form-urlencoded\r\n" .
                    "Cookie: {$cookieStr}\r\n",
                'content' => http_build_query($params),
                'timeout' => 120,
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['ok' => false, 'error' => 'API request failed: unable to reach ' . $endpoint];
        }
    }

    if (!is_string($response) || $response === '') {
        return ['ok' => false, 'error' => 'Empty response from ' . $endpoint];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid JSON response from ' . $endpoint];
    }

    return $data;
}

/**
 * Make an internal GET request to a local API endpoint.
 * Forwards the session cookie for authentication.
 */
function cloud_ai_speech_api_get(string $endpoint, array $params): array
{
    $query = http_build_query($params);
    $separator = (strpos($endpoint, '?') === false) ? '?' : '&';
    $url = cloud_ai_speech_api_url($endpoint . $separator . $query);
    $cookieStr = cloud_ai_speech_cookie_string();

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_COOKIE => $cookieStr,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            return ['ok' => false, 'error' => 'API request failed: ' . $error];
        }
    } else {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "Cookie: {$cookieStr}\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ];
        $response = @file_get_contents($url, false, stream_context_create($opts));
        if ($response === false) {
            return ['ok' => false, 'error' => 'API request failed: unable to reach ' . $endpoint];
        }
    }

    if (!is_string($response) || $response === '') {
        return ['ok' => false, 'error' => 'Empty response from ' . $endpoint];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid JSON response from ' . $endpoint];
    }

    return $data;
}

// ---------------------------------------------------------------------------
// TTS (Text-to-Speech) Tools
// ---------------------------------------------------------------------------

/**
 * Generate speech from text.
 *
 * @param string $text     Text to convert to speech
 * @param string $language Language code (e.g. 'en', 'ru', 'pl')
 * @param float  $speed    Speech speed multiplier
 * @return array Result with progress_id or error
 */
function cloud_ai_tool_generate_speech(string $text, string $language = 'en', float $speed = 1.0): array
{
    $user = get_current_user_from_sessions();
    if ($user === '') {
        return ['ok' => false, 'error' => 'not authenticated', 'code' => 401];
    }

    if ($text === '') {
        return ['ok' => false, 'error' => 'text is required'];
    }

    $result = cloud_ai_speech_api_post('tts_api.php', [
        'action' => 'generate_tts',
        'text' => $text,
        'language' => $language,
        'speed' => $speed,
        'user' => $user,
    ]);

    if (!empty($result['success'])) {
        return [
            'ok' => true,
            'progress_id' => $result['progress_id'] ?? '',
            'message' => 'TTS generation started',
        ];
    }

    return [
        'ok' => false,
        'error' => $result['error'] ?? 'TTS generation failed',
    ];
}

/**
 * Poll the progress of a TTS generation job.
 * Uses GET request as tts_api.php handles get_progress via GET.
 *
 * @param string $progressId The progress ID from generate_speech
 * @return array Progress data including audio_data when complete
 */
function cloud_ai_tool_get_speech_progress(string $progressId): array
{
    $user = get_current_user_from_sessions();
    if ($user === '') {
        return ['ok' => false, 'error' => 'not authenticated', 'code' => 401];
    }

    if ($progressId === '') {
        return ['ok' => false, 'error' => 'progress_id is required'];
    }

    $result = cloud_ai_speech_api_get('tts_api.php', [
        'action' => 'get_progress',
        'progress_id' => $progressId,
    ]);

    if (isset($result['status'])) {
        return [
            'ok' => true,
            'progress' => (int)($result['progress'] ?? 0),
            'status' => (string)($result['status'] ?? 'unknown'),
            'message' => (string)($result['message'] ?? ''),
            'audio_data' => $result['audio_data'] ?? null,
            'audio_url' => $result['audio_url'] ?? '',
            'error' => $result['error'] ?? '',
        ];
    }

    return [
        'ok' => false,
        'error' => $result['error'] ?? 'Failed to get progress',
    ];
}

// ---------------------------------------------------------------------------
// Transcription Tools
// ---------------------------------------------------------------------------

/**
 * Start transcribing an audio file.
 *
 * @param string $audioPath Path to the audio file in the user's workspace
 * @param string $language  Language code (e.g. 'pl', 'en', 'ru')
 * @return array Result with progress_id or error
 */
function cloud_ai_tool_transcribe_audio(string $audioPath, string $language = 'pl'): array
{
    $user = get_current_user_from_sessions();
    if ($user === '') {
        return ['ok' => false, 'error' => 'not authenticated', 'code' => 401];
    }

    if ($audioPath === '') {
        return ['ok' => false, 'error' => 'audio_path is required'];
    }

    $result = cloud_ai_speech_api_post('transcription_api.php', [
        'action' => 'start_transcription',
        'audio_path' => $audioPath,
        'language' => $language,
        'user' => $user,
    ]);

    if (!empty($result['success'])) {
        return [
            'ok' => true,
            'progress_id' => $result['progress_id'] ?? '',
            'message' => 'Transcription started',
        ];
    }

    return [
        'ok' => false,
        'error' => $result['error'] ?? 'Transcription failed to start',
    ];
}

/**
 * Poll the progress of a transcription job.
 *
 * @param string $progressId The progress ID from transcribe_audio
 * @return array Progress data including transcription text when complete
 */
function cloud_ai_tool_get_transcription_progress(string $progressId): array
{
    $user = get_current_user_from_sessions();
    if ($user === '') {
        return ['ok' => false, 'error' => 'not authenticated', 'code' => 401];
    }

    if ($progressId === '') {
        return ['ok' => false, 'error' => 'progress_id is required'];
    }

    $result = cloud_ai_speech_api_post('transcription_api.php', [
        'action' => 'get_progress',
        'progress_id' => $progressId,
    ]);

    if (isset($result['progress']) || isset($result['transcription'])) {
        return [
            'ok' => true,
            'progress' => (int)($result['progress'] ?? 0),
            'status' => (string)($result['status'] ?? ''),
            'message' => (string)($result['message'] ?? ''),
            'transcription' => (string)($result['transcription'] ?? ''),
            'is_processing' => !empty($result['isProcessing']),
            'is_active' => !empty($result['isActive']),
        ];
    }

    return [
        'ok' => false,
        'error' => $result['error'] ?? 'Failed to get transcription progress',
    ];
}

