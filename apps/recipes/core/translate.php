<?php
function translate_text($text, $source_lang, $target_lang, $retries = 3) {
    if (!in_array($source_lang, ['en', 'uk', 'ru', 'be'])) {
        $source_lang = 'en';
    }

    if ($source_lang === $target_lang) {
        return $text;
    }

    $cleanText = trim($text);
    if (empty($cleanText)) {
        error_log("Translation error: Empty input text");
        return $text;
    }

    // Build target languages array (exclude source lang)
    $targetLangs = array_filter(['en', 'uk', 'ru', 'be'], function($l) use ($source_lang) {
        return $l !== $source_lang;
    });

    // If translating to a single target language, use it directly
    if (!in_array($target_lang, $targetLangs)) {
        $targetLangs = [$target_lang];
    }

    // Call translate_api.php with food context
    $apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/apps/recipes/translate_api.php';
    
    $postData = json_encode([
        'text' => $cleanText,
        'source_lang' => $source_lang,
        'target_langs' => $targetLangs,
        'context' => 'food'  // Use food context for better translations
    ]);

    error_log("Translation request via translate_api.php: '$cleanText' from $source_lang to " . implode(',', $targetLangs));

    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'RecipesApp/1.0',
                CURLOPT_ENCODING => '',
                CURLOPT_FOLLOWLOCATION => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("HTTP error: $httpCode");
            }

            if ($error) {
                throw new Exception("cURL error: $error");
            }

            if (!mb_check_encoding($response, 'UTF-8')) {
                $response = mb_convert_encoding($response, 'UTF-8', 'auto');
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['success']) || !$data['success']) {
                $errorMsg = $data['error'] ?? 'Unknown error';
                throw new Exception("API error: $errorMsg");
            }

            // Get the translation for the target language
            if (isset($data['translations'][$target_lang])) {
                $translatedText = $data['translations'][$target_lang];
                error_log("Translation success: '$cleanText' -> '$translatedText' (with food context)");
                return $translatedText;
            }

            // Fallback: if target translation not found, try Russian for Belarusian
            if ($target_lang === 'be' && isset($data['translations']['ru'])) {
                $translatedText = $data['translations']['ru'];
                error_log("Fallback to Russian for Belarusian: '$translatedText'");
                return $translatedText;
            }

            throw new Exception('No translation found for target language');

        } catch (Exception $e) {
            error_log("Translation attempt $attempt failed for $source_lang|$target_lang: " . $e->getMessage());
            if ($attempt === $retries) {
                error_log("All $retries translation attempts failed, returning original text: '$text'");
                return $text;
            }
            usleep(300000); // 0.3 second delay between attempts
        }
    }

    return $text;
}
