<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Context dictionaries for different languages
// These add contextual hints to help the API disambiguate words
$contextHints = [
    'food' => [
        'en' => '(food, cooking, ingredient)',
        'uk' => '(їжа, приготування, інгредієнт)',
        'ru' => '(еда, кулинария, ингредиент)',
        'be' => '(ежа, гатаванне, інгрэдыент)',
    ],
    'technology' => [
        'en' => '(technology, computer, software)',
        'uk' => '(технологія, комп\'ютер, програмне забезпечення)',
        'ru' => '(технология, компьютер, программное обеспечение)',
        'be' => '(тэхналогія, камп\'ютар, праграмнае забеспячэнне)',
    ],
    'medicine' => [
        'en' => '(medicine, health, doctor)',
        'uk' => '(медицина, здоров\'я, лікар)',
        'ru' => '(медицина, здоровье, врач)',
        'be' => '(медыцына, здароўе, урач)',
    ],
    'nature' => [
        'en' => '(nature, outdoors, environment)',
        'uk' => '(природа, відпочинок на природі, довкілля)',
        'ru' => '(природа, отдых на природе, окружающая среда)',
        'be' => '(прырода, адпачынак на прыродзе, навакольнае асяроддзе)',
    ],
    'business' => [
        'en' => '(business, finance, economy)',
        'uk' => '(бізнес, фінанси, економіка)',
        'ru' => '(бизнес, финансы, экономика)',
        'be' => '(бізнес, фінансы, эканоміка)',
    ],
    'sport' => [
        'en' => '(sport, exercise, game)',
        'uk' => '(спорт, вправи, гра)',
        'ru' => '(спорт, упражнения, игра)',
        'be' => '(спорт, практыкаванні, гульня)',
    ],
    'education' => [
        'en' => '(education, school, study)',
        'uk' => '(освіта, школа, навчання)',
        'ru' => '(образование, школа, учёба)',
        'be' => '(адукацыя, школа, вучоба)',
    ],
    'music' => [
        'en' => '(music, song, instrument)',
        'uk' => '(музика, пісня, інструмент)',
        'ru' => '(музыка, песня, инструмент)',
        'be' => '(музыка, песня, інструмент)',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['text']) || !isset($input['source_lang'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters: text, source_lang']);
        exit();
    }

    $text = trim($input['text']);
    $sourceLang = $input['source_lang'];
    $targetLangs = $input['target_langs'] ?? ['en', 'ru', 'be'];
    $context = isset($input['context']) ? trim($input['context']) : '';

    if (empty($text)) {
        http_response_code(400);
        echo json_encode(['error' => 'Text cannot be empty']);
        exit();
    }

    // Build contextual query to improve accuracy
    $translations = [];
    $errors = [];

    foreach ($targetLangs as $targetLang) {
        if ($sourceLang === $targetLang) {
            $translations[$targetLang] = $text;
            continue;
        }

        $langPair = $sourceLang . '|' . $targetLang;

        // Build query with context hints
        $queryText = $text;
        if ($context && isset($contextHints[$context])) {
            // Add contextual hint in source language
            $hint = $contextHints[$context][$sourceLang] ?? $contextHints[$context]['en'];
            $queryText = $text . ' ' . $hint;
        }

        $url = 'https://api.mymemory.translated.net/get?q=' . urlencode($queryText) . '&langpair=' . urlencode($langPair);

        $httpContext = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'user_agent' => 'TranslationApp/1.0',
                'header' => 'Accept: application/json'
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $response = @file_get_contents($url, false, $httpContext);

        if ($response === false) {
            $errors[$targetLang] = 'Failed to fetch translation';
            continue;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[$targetLang] = 'Invalid JSON response';
            continue;
        }

        if (isset($data['responseData']['translatedText'])) {
            $translatedText = $data['responseData']['translatedText'];

            // Clean up: remove the context hint from the translation if present
            // The context hint often gets translated too, so we try to strip it
            if ($context && isset($contextHints[$context])) {
                $targetHint = $contextHints[$context][$targetLang] ?? null;
                if ($targetHint) {
                    // Try multiple patterns to clean up the hint
                    $patterns = [
                        '/\s*' . preg_quote($targetHint, '/') . '/i',
                        '/\s*\([^)]*' . preg_quote(explode(',', $targetHint)[0], '/') . '[^)]*\)/i',
                        '/\s*\(.*?\)$/',  // Remove any trailing parenthetical
                    ];
                    foreach ($patterns as $pattern) {
                        $translatedText = preg_replace($pattern, '', $translatedText);
                    }
                    $translatedText = trim($translatedText, ' (),.;:');
                }
            }

            $translations[$targetLang] = $translatedText;
        } else {
            $errors[$targetLang] = 'No translation found';
        }

        // Sleep briefly to avoid rate limiting
        usleep(200000);
    }

    echo json_encode([
        'success' => true,
        'source_text' => $text,
        'source_lang' => $sourceLang,
        'context' => $context,
        'translations' => $translations,
        'errors' => $errors
    ]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
}
?>
