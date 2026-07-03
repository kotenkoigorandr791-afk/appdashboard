<?php
function films_t(string $key): string {
    static $t = null;
    if ($t === null) {
        if (!function_exists('ap_load_i18n_from_dir')) {
            require_once dirname(__DIR__, 3) . '/project/i18n_helpers.php';
        }
        $lang = ap_normalize_i18n_lang($_GET['lang'] ?? 'en');
        $t = ap_load_i18n_from_dir(dirname(__DIR__), $lang);
    }
    $v = $t[$key] ?? $key;
    return is_string($v) ? $v : $key;
}
function streamFile($filePath) {
    // Disable PHP output buffering for streaming
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Try to open file directly (works better with symlinks to network paths)
    $fp = @fopen($filePath, 'rb');
    if (!$fp) {
        // Fallback: try with error suppression and check if file exists
        if (!@file_exists($filePath)) {
            http_response_code(404);
            exit(films_t('file_not_found'));
        }
        // File exists but couldn't be opened
        http_response_code(403);
        exit(films_t('file_not_accessible'));
    }

    $size = @filesize($filePath);
    if ($size === false || $size <= 0) {
        $stat = @fstat($fp);
        if ($stat && !empty($stat['size']) && (int)$stat['size'] > 0) {
            $size = (int)$stat['size'];
        }
    }
    if ($size <= 0 && @fseek($fp, 0, SEEK_END) === 0) {
        $pos = ftell($fp);
        if ($pos > 0) {
            $size = $pos;
        }
        fseek($fp, 0, SEEK_SET);
    }
    if ($size <= 0) {
        fclose($fp);
        http_response_code(503);
        exit(films_t('cannot_determine_file_size'));
    }

    $start = 0;
    $end = $size - 1;
    $length = $size;

    // Get file extension for correct content type
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $contentType = 'video/mp4';
    if ($ext === 'webm') $contentType = 'video/webm';
    if ($ext === 'ogg') $contentType = 'video/ogg';

    // Handle Range requests
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        $range = str_replace('bytes=', '', $range);
        $range = explode('-', $range);
        $start = intval($range[0]);
        $end = isset($range[1]) && is_numeric($range[1]) ? intval($range[1]) : $size - 1;
        $length = $end - $start + 1;

        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Type: ' . $contentType);

    header('Accept-Ranges: bytes');
    header("Content-Length: $length");
    header("Content-Disposition: inline; filename=\"" . basename($filePath) . "\"");
    header('Connection: close');

    // Cache control for streaming
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Seek to start position for range requests
    if ($start > 0) {
        fseek($fp, $start);
    }

    // Stream in chunks with proper buffering
    $bufferSize = 65536; // 64KB buffer for better network streaming
    $bytesSent = 0;

    while (!feof($fp) && $bytesSent < $length && connection_status() === CONNECTION_NORMAL) {
        $remaining = $length - $bytesSent;
        $readSize = min($bufferSize, $remaining);
        $chunk = fread($fp, $readSize);

        if ($chunk === false || $chunk === '') {
            break;
        }

        echo $chunk;
        $bytesSent += strlen($chunk);

        // Flush output buffers immediately for streaming
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    fclose($fp);
    exit;
}

function makeRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Movie Manager)');
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $response;
}

function translate_text($text, $source_lang, $target_lang, $retries = 5) {
    if (!in_array($source_lang, ['en', 'uk', 'ru', 'be'])) { $source_lang = 'en'; }
    if ($source_lang === $target_lang) { return $text; }
    
    $cleanText = trim($text);
    if (empty($cleanText)) {
        error_log("Translation error: Empty input text");
        return $text;
    }
    
    $encodedText = urlencode($cleanText);
    $langPair = "$source_lang|$target_lang";
    $apiUrl = "https://api.mymemory.translated.net/get?q=$encodedText&langpair=$langPair";
    
    error_log("Translation request: $apiUrl");
    
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        try {
            $response = makeRequest($apiUrl);
            if (!mb_check_encoding($response, 'UTF-8')) {
                $response = mb_convert_encoding($response, 'UTF-8', 'auto');
            }
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['responseData'])) {
                throw new Exception('Invalid API response format');
            }
            if ($data['responseStatus'] != 200) {
                throw new Exception('API error: ' . ($data['responseDetails'] ?? 'Unknown'));
            }
            
            $translatedText = $data['responseData']['translatedText'];
            
            if ($translatedText === $cleanText && $target_lang === 'be') {
                $translatedText = translate_text($cleanText, $source_lang, 'ru', 1);
                error_log("Fallback to Russian for Belarusian: '$translatedText'");
            }
            
            error_log("Translation success: '$cleanText' -> '$translatedText'");
            return $translatedText;
            
        } catch (Exception $e) {
            error_log("Translation attempt $attempt failed for $langPair: " . $e->getMessage());
            if ($attempt === $retries) {
                error_log("All $retries translation attempts failed, returning original text: '$text'");
                return $text;
            }
            usleep(500000);
        }
    }
    return $text;
}

function resolve_user_photo_url(string $photo): string {
    $photo = trim($photo);
    if ($photo === '' || $photo === 'default_avatar.png') return '/default_avatar.png';
    if (filter_var($photo, FILTER_VALIDATE_URL)) return $photo;
    if (str_starts_with($photo, '/')) return $photo;
    return '/' . ltrim($photo, '/');
}
