<?php
declare(strict_types=1);

require_once __DIR__ . '/../../cloud/includes/ai_config_store.php';
require_once __DIR__ . '/translate.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/ai_agent.php';

if (!defined('RECIPES_PRODUCT_PREVIEW_FILE')) {
    define('RECIPES_PRODUCT_PREVIEW_FILE', 'preview.webp');
}

const RECIPES_AI_MAX_ITERATIONS = 18;
const RECIPES_AI_CATEGORIES = [
    'vegetables', 'fruits', 'meat', 'fish', 'dairy', 'grains', 'spices', 'oils',
    'sauces', 'nuts', 'legumes', 'bread', 'pasta', 'rice', 'flour', 'sugar',
    'salt', 'vinegar', 'condiments', 'herbs',
];
const RECIPES_AI_UNITS = ['grams', 'pieces', 'teaspoon', 'tablespoon'];

function recipes_ai_json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function recipes_ai_require_role(int $minRole = 1): void
{
    require_once __DIR__ . '/session.php';
    $db = getDB();
    $role = 0;
    $sid = session_id();
    if ($db && $sid) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1');
            $stmt->bindValue(':sid', $sid, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($row && !empty($row['email'])) {
                $role = getUserRoleByEmail($row['email']);
            }
        } catch (Throwable $e) {
        }
    }
    if ($role < $minRole && isset($_COOKIE['PHPSESSID']) && $db) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1');
            $stmt->bindValue(':sid', (string)$_COOKIE['PHPSESSID'], SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($row && !empty($row['email'])) {
                $role = getUserRoleByEmail($row['email']);
            }
        } catch (Throwable $e) {
        }
    }
    if ($role < $minRole) {
        recipes_ai_json_out(['success' => false, 'message' => 'forbidden'], 403);
    }
}

function recipes_ai_public_config(): array
{
    $cfg = cloud_ai_load_config();
    return [
        'default_model' => $cfg['default_model'],
        'models' => $cfg['models'],
        'has_key' => $cfg['api_key'] !== '',
    ];
}

function recipes_ai_chat(array $config, string $model, array $messages, ?array $tools = null): array
{
    if ($config['api_key'] === '') {
        throw new RuntimeException('AI API key is not configured');
    }
    $data = [
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => 16384,
        'temperature' => 0.35,
        'stream' => false,
    ];
    if ($tools) {
        $data['tools'] = $tools;
        $data['tool_choice'] = 'auto';
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['api_endpoint'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['api_key'],
        ],
        CURLOPT_TIMEOUT => 180,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        throw new RuntimeException('CURL error: ' . $error);
    }
    if ($httpCode !== 200) {
        throw new RuntimeException('API error HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 500));
    }
    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid AI response');
    }
    return $decoded;
}

function recipes_ai_extract_json(string $text): ?array
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    if ($text[0] === '{') {
        $j = json_decode($text, true);
        return is_array($j) ? $j : null;
    }
    if (preg_match('/```(?:json)?\s*([\s\S]*?)```/u', $text, $m)) {
        $j = json_decode(trim($m[1]), true);
        return is_array($j) ? $j : null;
    }
    if (preg_match('/\{[\s\S]*\}/u', $text, $m)) {
        $j = json_decode($m[0], true);
        return is_array($j) ? $j : null;
    }
    return null;
}

function recipes_ai_search_products_sqlite($db, string $query, int $limit = 12): array
{
    $q = recipes_normalize_product_name($query);
    if ($q === '') {
        return [];
    }
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $out = [];
    try {
        $sql = "SELECT id, name_en, name_uk, name_ru, name_be, category, image, image_preview FROM " . RECIPES_DB_TABLE_PREFIX . "products
            WHERE LOWER(name_en) LIKE :q1
               OR LOWER(name_uk) LIKE :q2
               OR LOWER(name_ru) LIKE :q3
               OR LOWER(name_be) LIKE :q4
            ORDER BY name_en LIMIT :lim";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':q1', $like, SQLITE3_TEXT);
        $stmt->bindValue(':q2', $like, SQLITE3_TEXT);
        $stmt->bindValue(':q3', $like, SQLITE3_TEXT);
        $stmt->bindValue(':q4', $like, SQLITE3_TEXT);
        $stmt->bindValue(':lim', $limit, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $out[] = [
                'id' => $row['id'],
                'name' => [
                    'en' => $row['name_en'],
                    'uk' => $row['name_uk'],
                    'ru' => $row['name_ru'],
                    'be' => $row['name_be'],
                ],
                'category' => $row['category'] ?? '',
                'image' => $row['image'] ?? null,
                'image_preview' => $row['image_preview'] ?? null,
                'has_image' => !empty($row['image']),
            ];
        }
    } catch (Throwable $e) {
        if (function_exists('cloud_safe_error_log')) {
            cloud_safe_error_log('recipes_ai_search_products: ' . $e->getMessage());
        }
    }
    return $out;
}

function recipes_ai_find_product_by_name($db, string $name): ?array
{
    return recipes_ai_find_product_exact($db, $name);
}

function recipes_ai_url_fetch_safe(string $url): bool
{
    $p = parse_url($url);
    if ($p === false || empty($p['scheme']) || empty($p['host'])) {
        return false;
    }
    if (!in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
        return false;
    }
    $host = strtolower($p['host']);
    if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    $ip = @gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function recipes_ai_download_image(string $url): ?array
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048 || !recipes_ai_url_fetch_safe($url) || !function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'RecipeManager/1.0',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300 || strlen($body) > 10485760) {
        return null;
    }
    $info = @getimagesizefromstring($body);
    if ($info === false || empty($info['mime']) || strpos((string)$info['mime'], 'image/') !== 0) {
        return null;
    }
    $ext = 'jpg';
    if ($info['mime'] === 'image/png') {
        $ext = 'png';
    } elseif ($info['mime'] === 'image/gif') {
        $ext = 'gif';
    } elseif ($info['mime'] === 'image/webp') {
        $ext = 'webp';
    } elseif ($info['mime'] === 'image/avif') {
        $ext = 'avif';
    }
    return ['data' => $body, 'ext' => $ext, 'mime' => $info['mime']];
}

function recipes_ai_wiki_http_get(string $url): ?string
{
    static $mem = [];
    $key = md5($url);
    if (array_key_exists($key, $mem)) {
        return $mem[$key];
    }
    $cacheDir = sys_get_temp_dir() . '/recipes_wikimedia_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $cacheFile = $cacheDir . '/' . $key . '.cache';
    if (is_file($cacheFile) && filemtime($cacheFile) > time() - 172800) {
        $body = file_get_contents($cacheFile);
        if ($body !== false && str_starts_with(trim($body), '{')) {
            $mem[$key] = $body;
            return $body;
        }
        @unlink($cacheFile);
    }
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'RecipeManager/1.0 (recipes-app; contact@localhost)',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code === 200 && str_starts_with(trim($body), '{')) {
            @file_put_contents($cacheFile, $body);
            $mem[$key] = $body;
            return $body;
        }
        if ($attempt < 2) {
            usleep(800000);
        }
    }
    $mem[$key] = null;
    return null;
}

function recipes_ai_image_reject_homonym(string $title, string $query, string $label): bool
{
    $t = mb_strtolower(recipes_normalize_product_name($title), 'UTF-8');
    foreach (array_filter([$query, $label]) as $q) {
        $qn = mb_strtolower(recipes_normalize_product_name($q), 'UTF-8');
        if ($qn === '' || mb_strlen($qn, 'UTF-8') < 4) {
            continue;
        }
        if (str_starts_with($t, $qn) && mb_strlen($t, 'UTF-8') > mb_strlen($qn, 'UTF-8') + 1) {
            $suffix = mb_substr($t, mb_strlen($qn, 'UTF-8'), null, 'UTF-8');
            if ($suffix !== '' && !preg_match('/^[\s\-_(.]/u', $suffix)) {
                return true;
            }
        }
    }
    return false;
}

function recipes_ai_image_reject_non_food(string $title, string $description, string $kind): bool
{
    $text = mb_strtolower($title . ' ' . $description, 'UTF-8');
    $reject = [
        'cuisine', 'national dish', 'culture', 'history of', 'historic', 'list of', 'restaurant chain',
        'fast food chain', 'cookbook', 'ingredients of', 'world war', 'city of', 'city in', 'county',
        'province of', 'country', 'empire', 'biography', 'film ', 'album', 'song ', 'band ',
        'company', 'corporation', 'airport', 'stadium', 'university', 'museum', 'flag of',
        'coat of arms', 'dynasty', 'language', 'alphabet', 'religion', 'mythology', 'cartoon',
        'mcdonald', 'logo', 'icon', 'map of', 'geography', 'politics', 'election', 'president',
        'football', 'basketball', 'olympics', 'television', 'actor', 'actress', 'singer',
        'novel', 'poem', 'painting by', 'sculpture', 'monument', 'bridge', 'railway', 'airline',
        'recipe book', 'book cover', 'cover page', 'cookbook cover', 'magazine cover', 'menu card',
        'menu cover', 'manuscript', 'printed page', 'scan of', 'scanned', 'catalogue', 'catalog',
        '.pdf', 'pdf', 'document', 'nutrition tips',
        'drawing of', 'illustration of', 'clip art', 'hidden categories', 'wikimedia category',
        'palette', 'pallet', 'color chart', 'colour chart', 'swatch', 'paint', 'material sample',
        'борщевик', 'hemlock', 'heracleum', 'hogweed', 'poisonous plant', 'toxic plant',
    ];
    foreach ($reject as $bad) {
        if (str_contains($text, $bad)) {
            return true;
        }
    }
    if ($kind === 'product' && preg_match('/\b(soup|stew|salad|sandwich|pizza|burger|pasta dish|main course|breakfast|dinner|lunch|meal|cuisine|recipe for|cake|cakes|dessert|biscuit|cookie|cookies|balls|treat|candy|ice cream|cone|recalled|packaged|quiche|chili|ham|cheddar|cheese)\b/u', $text)) {
        return true;
    }
    return false;
}

function recipes_ai_image_reject_product_mismatch(string $query, string $label, string $title, string $description): bool
{
    $needle = recipes_normalize_product_name($query . ' ' . $label);
    $text = recipes_normalize_product_name($title . ' ' . $description);
    if (preg_match('/\b(cherry|cherries)\b/u', $needle) && preg_match('/\b(tomato|tomatoes)\b/u', $text)) {
        return true;
    }
    return false;
}

function recipes_ai_image_score_candidate(string $title, string $description, string $query, string $label, string $kind): int
{
    if (recipes_ai_image_reject_non_food($title, $description, $kind)) {
        return -1000;
    }
    if (recipes_ai_image_reject_homonym($title, $query, $label)) {
        return -1000;
    }
    if ($kind === 'product' && recipes_ai_image_reject_product_mismatch($query, $label, $title, $description)) {
        return -1000;
    }
    $score = $kind === 'product' ? 4 : 3;
    $titleNorm = recipes_normalize_product_name($title);
    $queryNorm = recipes_normalize_product_name($query);
    $labelNorm = recipes_normalize_product_name($label);
    $candidateNorm = recipes_normalize_product_name($title . ' ' . $description);
    $queryWords = array_values(array_unique(array_filter(preg_split('/\s+/u', $queryNorm . ' ' . $labelNorm), static function ($w) {
        return mb_strlen($w, 'UTF-8') >= 4 && !in_array($w, ['food', 'photo', 'dish', 'plate', 'plated', 'finished', 'served', 'with', 'cake', 'dessert'], true);
    })));
    if ($kind === 'recipe' && count($queryWords) >= 2) {
        $overlap = 0;
        foreach ($queryWords as $w) {
            $variants = recipes_ai_search_word_variants($w);
            foreach ($variants as $variant) {
                if (str_contains($candidateNorm, $variant)) {
                    $overlap++;
                    break;
                }
            }
        }
        if ($overlap < 2 && str_contains($candidateNorm, 'black forest') && in_array('chocolate', $queryWords, true) && in_array('cherry', $queryWords, true)) {
            $overlap++;
        }
        if ($overlap < 2) {
            return -1000;
        }
        $score += $overlap * 4;
    }
    if ($queryNorm !== '' && ($titleNorm === $queryNorm || str_contains($titleNorm, $queryNorm))) {
        $score += 18;
    }
    if ($labelNorm !== '' && $labelNorm !== $queryNorm && ($titleNorm === $labelNorm || str_contains($titleNorm, $labelNorm))) {
        $score += 14;
    }
    $text = mb_strtolower($title . ' ' . $description, 'UTF-8');
    if (preg_match('/\b(file|image|photo):/u', $text)) {
        $score += 1;
    }
    if (preg_match('/\b(photo|photograph|plate|plated|served|bowl|dish)\b/u', $text)) {
        $score += $kind === 'recipe' ? 6 : 3;
    }
    if (preg_match('/\b(book|cover|page|scan|scanned|illustration|drawing|diagram|logo|svg)\b/u', $text)) {
        $score -= 20;
    }
    if (preg_match('/\b(palette|pallet|color chart|colour chart|swatch|paint|sample)\b/u', $text)) {
        return -1000;
    }
    $foodHints = $kind === 'product'
        ? ['fruit', 'vegetable', 'root', 'tuber', 'plant', 'crop', 'herb', 'spice', 'nut', 'seed', 'berry', 'mushroom', 'fungus', 'legume', 'grain', 'cereal', 'meat', 'beef', 'pork', 'lamb', 'poultry', 'chicken', 'fish', 'seafood', 'cheese', 'milk', 'egg', 'ingredient', 'food']
        : ['food', 'dish', 'soup', 'stew', 'salad', 'cake', 'bread', 'pasta', 'meat', 'fish', 'dessert', 'pie', 'sauce', 'porridge', 'cutlet', 'patty', 'casserole', 'roast', 'fried', 'baked', 'grilled', 'traditional', 'national dish'];
    foreach ($foodHints as $hint) {
        if ($kind === 'recipe' && $hint === 'national dish') {
            continue;
        }
        if (str_contains($text, $hint)) {
            $score += 3;
        }
    }
    if (!preg_match('/\b(cuisine|culture|history|list|country|region)\b/u', $text)) {
        $score += 2;
    }
    return $score;
}

function recipes_ai_search_word_variants(string $word): array
{
    $word = recipes_normalize_product_name($word);
    if ($word === '') {
        return [];
    }
    $variants = [$word];
    if (str_ends_with($word, 'y')) {
        $variants[] = mb_substr($word, 0, -1, 'UTF-8') . 'ies';
    }
    if (preg_match('/ies$/u', $word)) {
        $variants[] = mb_substr($word, 0, -3, 'UTF-8') . 'y';
    }
    if (preg_match('/(?:oes|ses|xes|ches|shes)$/u', $word)) {
        $variants[] = mb_substr($word, 0, -2, 'UTF-8');
    }
    if (preg_match('/s$/u', $word) && !preg_match('/ss$/u', $word)) {
        $variants[] = mb_substr($word, 0, -1, 'UTF-8');
    }
    return array_values(array_unique($variants));
}

function recipes_ai_image_query_variants(string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    $variants = [$query];
    $words = preg_split('/\s+/u', recipes_normalize_product_name($query));
    if (count($words) === 1) {
        $variants = array_merge($variants, recipes_ai_search_word_variants($words[0]));
    }
    return array_values(array_unique(array_filter($variants)));
}

function recipes_ai_wiki_fetch_pages(string $searchQuery, string $wikiLang, int $limit): array
{
    $searchQuery = trim($searchQuery);
    if ($searchQuery === '') {
        return [];
    }
    $url = 'https://' . $wikiLang . '.wikipedia.org/w/api.php?' . http_build_query([
        'action' => 'query',
        'generator' => 'search',
        'gsrsearch' => $searchQuery,
        'gsrlimit' => $limit,
        'prop' => 'pageimages|description',
        'piprop' => 'thumbnail|original',
        'pithumbsize' => 800,
        'format' => 'json',
    ]);
    $body = recipes_ai_wiki_http_get($url);
    if ($body === null) {
        return [];
    }
    $json = json_decode($body, true);
    $pages = $json['query']['pages'] ?? null;
    if (!is_array($pages)) {
        return [];
    }
    $out = [];
    foreach ($pages as $page) {
        $img = $page['original']['source'] ?? $page['thumbnail']['source'] ?? null;
        if (!$img) {
            continue;
        }
        $out[] = [
            'url' => (string)$img,
            'title' => (string)($page['title'] ?? ''),
            'description' => strip_tags((string)($page['description'] ?? '')),
        ];
    }
    return $out;
}

function recipes_ai_commons_food_candidates(string $query, int $limit = 8): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    $titles = [];
    foreach (['intitle:"' . $query . '" food photo -book -cover -scan -page -logo -palette -pallet -swatch -paint -pdf', $query . ' food photo -book -cover -scan -page -logo -palette -pallet -swatch -paint -pdf'] as $search) {
        $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $search,
            'srnamespace' => 6,
            'srlimit' => $limit * 2,
            'format' => 'json',
        ]);
        $body = recipes_ai_wiki_http_get($url);
        if ($body === null) {
            continue;
        }
        $json = json_decode($body, true);
        foreach ($json['query']['search'] ?? [] as $item) {
            if (!empty($item['title'])) {
                $titles[] = (string)$item['title'];
            }
        }
    }
    $titles = array_values(array_unique($titles));
    if ($titles === []) {
        return [];
    }
    $infoUrl = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action' => 'query',
        'titles' => implode('|', array_slice($titles, 0, 12)),
        'prop' => 'imageinfo',
        'iiprop' => 'url',
        'iiurlwidth' => 800,
        'format' => 'json',
    ]);
    $body = recipes_ai_wiki_http_get($infoUrl);
    if ($body === null) {
        return [];
    }
    $json = json_decode($body, true);
    $out = [];
    foreach ($json['query']['pages'] ?? [] as $page) {
        $img = $page['imageinfo'][0]['thumburl'] ?? $page['imageinfo'][0]['url'] ?? null;
        if (!$img) {
            continue;
        }
        $title = (string)($page['title'] ?? '');
        $out[] = [
            'url' => (string)$img,
            'title' => preg_replace('/^File:/', '', $title),
            'description' => 'food',
        ];
    }
    return $out;
}

function recipes_ai_product_en_fallback(string $name, string $lang): string
{
    if ($lang === 'en') {
        return '';
    }
    static $maps = [
        'ru' => [
            'картофель' => 'potato', 'лук' => 'onion', 'морковь' => 'carrot', 'капуста' => 'cabbage',
            'помидор' => 'tomato', 'томат' => 'tomato', 'огурец' => 'cucumber', 'яйцо' => 'egg', 'яйца' => 'eggs',
            'молоко' => 'milk', 'мука' => 'flour', 'сахар' => 'sugar', 'соль' => 'salt', 'перец' => 'pepper',
            'мясо' => 'meat', 'говядина' => 'beef', 'свинина' => 'pork', 'курица' => 'chicken',
            'чеснок' => 'garlic', 'сметана' => 'sour cream', 'масло' => 'butter', 'рис' => 'rice',
            'какао' => 'cocoa powder', 'какао-порошок' => 'cocoa powder', 'сливки' => 'cream',
            'сливки 33%' => 'heavy cream', 'темный шоколад' => 'dark chocolate', 'чёрный шоколад' => 'dark chocolate',
        ],
        'uk' => [
            'картопля' => 'potato', 'цибуля' => 'onion', 'морква' => 'carrot', 'капуста' => 'cabbage',
            'помідор' => 'tomato', 'огірок' => 'cucumber', 'яйце' => 'egg', 'молоко' => 'milk',
            'какао' => 'cocoa powder', 'вершки' => 'cream', 'чорний шоколад' => 'dark chocolate',
        ],
        'be' => [
            'бульба' => 'potato', 'цыбуля' => 'onion', 'морква' => 'carrot',
        ],
    ];
    $norm = recipes_normalize_product_name($name);
    return $maps[$lang][$norm] ?? '';
}

function recipes_ai_recipe_image_searches(string $label, string $query): array
{
    $searches = array_values(array_unique(array_filter([
        $query . ' plated food photo',
        $query . ' finished dish',
        $query,
        $label . ' plated food photo',
        $label,
        $query . ' dish',
        $label . ' food',
        $query . ' food',
    ])));
    $norm = recipes_normalize_product_name($label . ' ' . $query);
    if (preg_match('/котлет|cutlet|patty|teftel|meatball/u', $norm)) {
        $searches[] = 'cutlet food';
        $searches[] = 'meat cutlet dish';
        $searches[] = 'котлета';
    }
    if (preg_match('/борщ|borscht|borsch/u', $norm)) {
        $searches[] = 'borscht soup';
        $searches[] = 'борщ суп';
    }
    if (preg_match('/cherr|виш|roll|рулет|roulade/u', $norm) && preg_match('/chocolate|шоколад/u', $norm)) {
        array_unshift($searches, 'chocolate cherry swiss roll cake', 'chocolate cherry roulade cake', 'chocolate cherry cake dessert', 'black forest roll cake');
    }
    return array_values(array_unique(array_filter($searches)));
}

function recipes_ai_wikimedia_product_candidates(string $query, string $wikiLang = 'en', int $limit = 8): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    $merged = [];
    foreach (recipes_ai_wiki_fetch_pages($query, $wikiLang, $limit) as $c) {
        $merged[] = $c;
    }
    if (count($merged) < 2) {
        foreach (recipes_ai_wiki_fetch_pages($query . ' vegetable', $wikiLang, $limit) as $c) {
            $merged[] = $c;
        }
    }
    foreach (recipes_ai_commons_food_candidates($query, $limit) as $c) {
        $merged[] = $c;
    }
    return $merged;
}

function recipes_ai_product_image_query(string $name, string $lang): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    $fallback = recipes_ai_product_en_fallback($name, $lang);
    if ($fallback !== '') {
        return $fallback;
    }
    if ($lang !== 'en' && function_exists('translate_text')) {
        $en = trim(translate_text($name, $lang, 'en'));
        if ($en !== '' && recipes_normalize_product_name($en) !== recipes_normalize_product_name($name)) {
            return $en;
        }
    }
    return $name;
}

function recipes_ai_gather_image_options(string $label, string $query, string $lang, string $kind = 'recipe', int $limit = 4): array
{
    $label = trim($label);
    $query = trim($query) !== '' ? trim($query) : $label;
    if ($query === '' && $label === '') {
        return [];
    }
    $scored = [];
    $seen = [];
    $wikiLangs = array_values(array_unique([$lang === 'en' ? 'en' : $lang, 'en']));
    foreach ($wikiLangs as $wl) {
        if (!in_array($wl, ['en', 'uk', 'ru', 'be'], true)) {
            $wl = 'en';
        }
        $searches = $kind === 'recipe'
            ? recipes_ai_recipe_image_searches($label, $query)
            : array_values(array_unique(array_filter(array_merge(
                recipes_ai_image_query_variants(recipes_ai_product_image_query($label, $lang)),
                recipes_ai_image_query_variants(recipes_ai_product_image_query($query, $lang)),
                recipes_ai_image_query_variants($query),
                recipes_ai_image_query_variants($label),
                recipes_ai_image_query_variants(recipes_ai_product_en_fallback($label, $lang))
            ))));
        foreach (array_slice($searches, 0, 3) as $sq) {
            $raw = $kind === 'product'
                ? recipes_ai_wikimedia_product_candidates($sq, $wl, 8)
                : recipes_ai_wikimedia_candidates($sq, $wl, 8);
            foreach ($raw as $c) {
                if (isset($seen[$c['url']])) {
                    continue;
                }
                $score = recipes_ai_image_score_candidate(
                    (string)($c['title'] ?? ''),
                    (string)($c['description'] ?? ''),
                    $query,
                    $label,
                    $kind
                );
                if ($score < ($kind === 'product' ? 10 : 12)) {
                    continue;
                }
                $seen[$c['url']] = true;
                $scored[] = ['url' => $c['url'], 'title' => $c['title'] ?? '', '_score' => $score];
            }
        }
    }
    if ($kind === 'product') {
        foreach (recipes_ai_commons_food_candidates($query, 8) as $c) {
            if (isset($seen[$c['url']])) {
                continue;
            }
            $score = recipes_ai_image_score_candidate(
                (string)($c['title'] ?? ''),
                (string)($c['description'] ?? ''),
                $query,
                $label,
                $kind
            );
            if ($score < 10) {
                continue;
            }
            $seen[$c['url']] = true;
            $scored[] = ['url' => $c['url'], 'title' => $c['title'] ?? '', '_score' => $score];
        }
    }
    usort($scored, static fn($a, $b) => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));
    $out = [];
    foreach (array_slice($scored, 0, max(1, $limit)) as $c) {
        unset($c['_score']);
        $out[] = $c;
    }
    return $out;
}

function recipes_ai_search_product_image(string $productName, string $lang): ?string
{
    $productName = trim($productName);
    if ($productName === '') {
        return null;
    }
    $query = recipes_ai_product_image_query($productName, $lang);
    $wikiLangs = ['en', $lang === 'en' ? 'en' : $lang];
    $wikiLangs = array_values(array_unique($wikiLangs));
    $candidates = [];
    $seen = [];
    foreach ($wikiLangs as $wl) {
        if (!in_array($wl, ['en', 'uk', 'ru', 'be'], true)) {
            $wl = 'en';
        }
        foreach (recipes_ai_wikimedia_product_candidates($query, $wl, 6) as $c) {
            if (!isset($seen[$c['url']])) {
                $seen[$c['url']] = true;
                $candidates[] = $c;
            }
        }
    }
    if ($candidates === []) {
        return null;
    }
    $url = recipes_ai_verify_image_choice($productName, array_slice($candidates, 0, 8));
    return $url;
}

function recipes_ai_wikimedia_candidates(string $query, string $wikiLang = 'en', int $limit = 8): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    $searches = array_values(array_unique(array_filter([
        $query,
        $query . ' dish',
    ])));
    $merged = [];
    foreach ($searches as $sq) {
        foreach (recipes_ai_wiki_fetch_pages($sq, $wikiLang, $limit) as $c) {
            $merged[] = $c;
        }
    }
    foreach (recipes_ai_commons_food_candidates($query, $limit) as $c) {
        $merged[] = $c;
    }
    return $merged;
}

function recipes_ai_verify_image_choice(string $recipeTitle, array $candidates): ?string
{
    if ($candidates === []) {
        return null;
    }
    $config = cloud_ai_load_config();
    if ($config['api_key'] === '') {
        return null;
    }
    $lines = [];
    foreach ($candidates as $i => $c) {
        $lines[] = ($i + 1) . '. "' . ($c['title'] ?? '') . '"';
    }
    $user = "Recipe dish: \"{$recipeTitle}\"\n"
        . "Pick the Wikipedia page whose photo best matches THIS finished dish.\n"
        . "Reply with ONLY one digit (1-" . count($candidates) . ") or 0 if none match.\n"
        . "Reject: burgers/hamburgers for meat cutlets/kotleti/tefteli, logos, unrelated foods.\n\n"
        . implode("\n", $lines);
    try {
        $response = recipes_ai_chat($config, $config['default_model'], [
            ['role' => 'system', 'content' => 'You verify food photos for recipes. Output only a single digit.'],
            ['role' => 'user', 'content' => $user],
        ]);
        $text = recipes_ai_message_text($response['choices'][0]['message'] ?? []);
        if (preg_match('/(\d+)/', $text, $m)) {
            $idx = (int)$m[1] - 1;
            if ($idx >= 0 && isset($candidates[$idx])) {
                return $candidates[$idx]['url'];
            }
        }
    } catch (Throwable $e) {
        if (function_exists('cloud_safe_error_log')) {
            cloud_safe_error_log('recipes_ai_verify_image: ' . $e->getMessage());
        }
    }
    return null;
}

function recipes_ai_search_recipe_image(string $recipeTitle, string $imageQuery, string $lang): ?array
{
    $imageQuery = trim($imageQuery) !== '' ? trim($imageQuery) : $recipeTitle;
    $wikiLangs = [$lang === 'en' ? 'en' : $lang, 'en'];
    $wikiLangs = array_values(array_unique($wikiLangs));
    $candidates = [];
    $seen = [];
    foreach ($wikiLangs as $wl) {
        if (!in_array($wl, ['en', 'uk', 'ru', 'be'], true)) {
            $wl = 'en';
        }
        foreach (recipes_ai_wikimedia_candidates($imageQuery, $wl, 6) as $c) {
            if (!isset($seen[$c['url']])) {
                $seen[$c['url']] = true;
                $candidates[] = $c;
            }
        }
        foreach (recipes_ai_wikimedia_candidates($recipeTitle, $wl, 4) as $c) {
            if (!isset($seen[$c['url']])) {
                $seen[$c['url']] = true;
                $candidates[] = $c;
            }
        }
    }
    if ($candidates === []) {
        return null;
    }
    $url = recipes_ai_verify_image_choice($recipeTitle, array_slice($candidates, 0, 10));
    if (!$url) {
        return null;
    }
    foreach ($candidates as $c) {
        if ($c['url'] === $url) {
            return ['url' => $url, 'source_title' => $c['title'], 'verified' => true];
        }
    }
    return ['url' => $url, 'source_title' => '', 'verified' => true];
}

function recipes_ai_search_wikimedia_image(string $query): ?string
{
    $result = recipes_ai_search_recipe_image($query, $query, 'en');
    return $result['url'] ?? null;
}

function recipes_ai_search_image(string $query, ?string $directUrl = null): ?string
{
    if ($directUrl && recipes_ai_url_fetch_safe($directUrl)) {
        $dl = recipes_ai_download_image($directUrl);
        if ($dl) {
            return $directUrl;
        }
    }
    $result = recipes_ai_search_product_image($query, 'en');
    if ($result) {
        return $result;
    }
    $result = recipes_ai_search_recipe_image($query, $query, 'en');
    return $result['url'] ?? null;
}

function recipes_ai_make_placeholder_image(string $product_dir): ?string
{
    if (!extension_loaded('gd')) {
        return null;
    }
    $im = imagecreatetruecolor(400, 400);
    if (!$im) {
        return null;
    }
    $bg = imagecolorallocate($im, 120, 163, 0);
    imagefilledrectangle($im, 0, 0, 400, 400, $bg);
    $path = rtrim($product_dir, '/') . '/image.jpg';
    $ok = imagejpeg($im, $path, 85);
    imagedestroy($im);
    return ($ok && is_file($path)) ? 'image.jpg' : null;
}

function recipes_ai_create_product($db, string $products_dir, string $name, string $category, string $lang, ?string $imageUrl = null, ?string $imageQuery = null): array
{
    $name = trim($name);
    if ($name === '') {
        return ['ok' => false, 'error' => 'name required'];
    }
    if (!in_array($category, RECIPES_AI_CATEGORIES, true)) {
        $category = 'vegetables';
    }
    $names = ['en' => $name, 'uk' => $name, 'ru' => $name, 'be' => $name];
    foreach (['en', 'uk', 'ru', 'be'] as $l) {
        if ($l !== $lang) {
            $names[$l] = translate_text($name, $lang, $l);
        }
    }
    if (recipes_product_name_exists($db, $names)) {
        $existing = recipes_ai_find_product_by_name($db, $name);
        if ($existing) {
            return ['ok' => true, 'id' => $existing['id'], 'created' => false, 'name' => $existing['name']];
        }
        return ['ok' => false, 'error' => 'duplicate product'];
    }
    $product_id = uniqid();
    $product_dir = __DIR__ . '/../' . $products_dir . '/' . $product_id;
    if (!is_dir($product_dir)) {
        mkdir($product_dir, 0777, true);
    }
    $image_name = null;
    $preview_name = null;
    $imgUrl = $imageUrl;
    if ($imgUrl) {
        $dl = recipes_ai_download_image($imgUrl);
        if ($dl) {
            $image_name = recipes_image_save_optimized_blob($dl['data'], $product_dir);
            if ($image_name) {
                $preview_name = recipes_product_preview_refresh($products_dir, $product_id, $image_name);
            }
        }
    }
    $stmt = $db->prepare("INSERT INTO " . RECIPES_DB_TABLE_PREFIX . "products
        (id, name_en, name_uk, name_ru, name_be, category, image, image_preview)
        VALUES (:id, :ne, :nu, :nr, :nb, :cat, :img, :prev)");
    $stmt->bindValue(':id', $product_id, SQLITE3_TEXT);
    $stmt->bindValue(':ne', $names['en'], SQLITE3_TEXT);
    $stmt->bindValue(':nu', $names['uk'], SQLITE3_TEXT);
    $stmt->bindValue(':nr', $names['ru'], SQLITE3_TEXT);
    $stmt->bindValue(':nb', $names['be'], SQLITE3_TEXT);
    $stmt->bindValue(':cat', $category, SQLITE3_TEXT);
    $stmt->bindValue(':img', $image_name, $image_name === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':prev', $preview_name, $preview_name === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->execute();
    $row = recipes_load_product_row($db, $product_id);
    if ($row) {
        recipes_save_product_json($products_dir, $row);
    }
    return [
        'ok' => true,
        'id' => $product_id,
        'created' => true,
        'name' => $names,
        'category' => $category,
        'image' => $image_name,
    ];
}

function recipes_ai_tool_definitions(): array
{
    $cats = implode(', ', RECIPES_AI_CATEGORIES);
    $units = implode('|', RECIPES_AI_UNITS);
    return [
        [
            'type' => 'function',
            'function' => [
                'name' => 'search_products',
                'description' => 'Search existing products/ingredients in the database by name.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Ingredient name to search'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results', 'default' => 10],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'create_product',
                'description' => 'Create a missing ingredient in the database with image from Wikimedia search.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'category' => ['type' => 'string', 'description' => 'One of: ' . $cats],
                        'image_query' => ['type' => 'string', 'description' => 'English search query for product photo'],
                    ],
                    'required' => ['name', 'category'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'search_image',
                'description' => 'Find a photo URL for a dish or ingredient via Wikimedia/Wikipedia.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'submit_recipe',
                'description' => 'Submit the final recipe draft when all ingredients are resolved to product_id values.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'recipe_image_query' => ['type' => 'string', 'description' => 'English query for finished dish photo'],
                        'steps' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['type' => 'string', 'enum' => ['product', 'instruction']],
                                    'product_id' => ['type' => 'string'],
                                    'content' => ['type' => 'string'],
                                    'quantity_value' => ['type' => 'number'],
                                    'quantity_unit' => ['type' => 'string', 'enum' => RECIPES_AI_UNITS],
                                    'timer_hours' => ['type' => 'integer'],
                                    'timer_minutes' => ['type' => 'integer'],
                                ],
                                'required' => ['type'],
                            ],
                        ],
                    ],
                    'required' => ['title', 'steps'],
                ],
            ],
        ],
    ];
}

function recipes_ai_execute_tool(string $name, array $args, $db, string $products_dir, string $lang, array &$log): array
{
    switch ($name) {
        case 'search_products':
            $hits = recipes_ai_search_products_sqlite($db, (string)($args['query'] ?? ''), (int)($args['limit'] ?? 10));
            $log[] = ['tool' => $name, 'query' => $args['query'] ?? '', 'count' => count($hits)];
            return ['ok' => true, 'products' => $hits];

        case 'create_product':
            $res = recipes_ai_create_product(
                $db,
                $products_dir,
                (string)($args['name'] ?? ''),
                (string)($args['category'] ?? 'vegetables'),
                $lang,
                null,
                recipes_ai_product_image_query((string)($args['image_query'] ?? $args['name'] ?? ''), $lang)
            );
            $log[] = ['tool' => $name, 'name' => $args['name'] ?? '', 'result' => $res['ok'] ?? false];
            return $res;

        case 'search_image':
            $url = recipes_ai_search_image((string)($args['query'] ?? ''));
            $log[] = ['tool' => $name, 'query' => $args['query'] ?? '', 'found' => $url !== null];
            return ['ok' => $url !== null, 'url' => $url];

        case 'submit_recipe':
            return ['ok' => true, 'draft' => $args, 'final' => true];

        default:
            return ['ok' => false, 'error' => 'unknown tool'];
    }
}

function recipes_ai_normalize_draft(array $raw, $db, string $lang): array
{
    $title = trim((string)($raw['title'] ?? ''));
    $steps = [];
    foreach ($raw['steps'] ?? [] as $step) {
        if (!is_array($step)) {
            continue;
        }
        $type = (string)($step['type'] ?? 'instruction');
        if ($type === 'product') {
            $pid = (string)($step['product_id'] ?? $step['content'] ?? '');
            $row = $pid ? recipes_load_product_row($db, $pid) : null;
            $pname = (string)($step['product_name'] ?? '');
            if (!$row && $pname !== '') {
                $found = recipes_ai_find_product_by_name($db, $pname);
                if ($found) {
                    $pid = $found['id'];
                    $row = recipes_load_product_row($db, $pid);
                }
            }
            $unit = (string)($step['quantity_unit'] ?? ($step['quantity']['unit'] ?? 'grams'));
            if (!in_array($unit, RECIPES_AI_UNITS, true)) {
                $unit = 'grams';
            }
            $qtyVal = (float)($step['quantity_value'] ?? ($step['quantity']['value'] ?? 100));
            if ($row) {
                $steps[] = [
                    'type' => 'product',
                    'content' => $pid,
                    'product_id' => $pid,
                    'product_name' => $row['name_' . $lang] ?? $row['name_en'],
                    'quantity' => ['value' => $qtyVal, 'unit' => $unit],
                ];
            } elseif ($pname !== '') {
                $steps[] = [
                    'type' => 'product',
                    'product_name' => $pname,
                    'category' => (string)($step['category'] ?? 'vegetables'),
                    'quantity' => ['value' => $qtyVal, 'unit' => $unit],
                ];
            }
        } else {
            $content = (string)($step['content'] ?? '');
            $timer = null;
            $th = (int)($step['timer_hours'] ?? 0);
            $tm = (int)($step['timer_minutes'] ?? 0);
            if ($th > 0 || $tm > 0) {
                $timer = ['hours' => $th, 'minutes' => $tm];
            }
            $steps[] = ['type' => 'instruction', 'content' => $content, 'timer' => $timer];
        }
    }
    $imageQuery = (string)($raw['recipe_image_query'] ?? $title);
    $existingUrl = trim((string)($raw['image_url'] ?? ''));
    return [
        'title' => $title,
        'steps' => $steps,
        'image_url' => $existingUrl !== '' ? $existingUrl : null,
        'recipe_image_query' => $imageQuery,
        'image_pending' => $existingUrl === '',
        'image_source' => $raw['image_source'] ?? null,
        'lang' => $lang,
    ];
}

function recipes_ai_message_text(array $message): string
{
    $content = trim((string)($message['content'] ?? ''));
    if ($content !== '') {
        return $content;
    }
    return trim((string)($message['reasoning_content'] ?? ''));
}

function recipes_ai_json_system_prompt(string $lang): string
{
    $cats = implode(', ', RECIPES_AI_CATEGORIES);
    $units = implode(', ', RECIPES_AI_UNITS);
    return "You are a recipe assistant. Interface language: {$lang}. The user request may be in any language.\n"
        . "Product categories: {$cats}\n"
        . "Quantity units: {$units}\n\n"
        . "Return ONLY valid JSON (no markdown) with this schema:\n"
        . "{\n"
        . "  \"title\": \"natural dish name in interface language only\",\n"
        . "  \"recipe_image_query\": \"English photo search for the FINISHED dish\",\n"
        . "  \"ingredients\": [\n"
        . "    {\"name\": \"ingredient in interface language\", \"quantity_value\": 200, \"quantity_unit\": \"grams\", \"category\": \"vegetables\"}\n"
        . "  ],\n"
        . "  \"instructions\": [\n"
        . "    {\"content\": \"step text in interface language\", \"timer_hours\": 0, \"timer_minutes\": 15}\n"
        . "  ]\n"
        . "}\n"
        . "Title, ingredient names and instructions must be in interface language ({$lang}) only — never add translations in parentheses, alternate names, or \"X-style\" prefixes.\n"
        . "Instructions: exactly 2 or 3 steps total in interface language — combine related actions, never more than 3.\n"
        . "recipe_image_query: English description of the finished dish (NOT burger/hamburger for cutlets/kotleti).\n"
        . "Include realistic quantities. At least 2 ingredients.";
}

function recipes_ai_generate_json(string $prompt, string $lang, ?string $model, ?array $currentDraft = null): array
{
    $config = cloud_ai_load_config();
    $model = $model && in_array($model, $config['models'], true) ? $model : $config['default_model'];
    $userMsg = $prompt;
    if ($currentDraft) {
        $userMsg = "Current recipe JSON:\n" . json_encode($currentDraft, JSON_UNESCAPED_UNICODE)
            . "\n\nApply this change and return the full updated JSON:\n" . $prompt;
    }
    $response = recipes_ai_chat($config, $model, [
        ['role' => 'system', 'content' => recipes_ai_json_system_prompt($lang)],
        ['role' => 'user', 'content' => $userMsg],
    ]);
    $choice = $response['choices'][0]['message'] ?? [];
    $text = recipes_ai_message_text(is_array($choice) ? $choice : []);
    $parsed = recipes_ai_extract_json($text);
    return ['json' => is_array($parsed) ? $parsed : [], 'model' => $model, 'raw' => substr($text, 0, 400)];
}

function recipes_ai_build_steps_from_json(array $json): array
{
    if (!empty($json['steps']) && is_array($json['steps'])) {
        return $json['steps'];
    }
    $steps = [];
    foreach ($json['ingredients'] ?? [] as $ing) {
        if (!is_array($ing)) {
            continue;
        }
        $unit = (string)($ing['quantity_unit'] ?? 'grams');
        if (!in_array($unit, RECIPES_AI_UNITS, true)) {
            $unit = 'grams';
        }
        $steps[] = [
            'type' => 'product',
            'product_name' => (string)($ing['name'] ?? ''),
            'category' => (string)($ing['category'] ?? 'vegetables'),
            'quantity_value' => (float)($ing['quantity_value'] ?? 100),
            'quantity_unit' => $unit,
        ];
    }
    foreach ($json['instructions'] ?? [] as $instr) {
        if (!is_array($instr)) {
            continue;
        }
        $steps[] = [
            'type' => 'instruction',
            'content' => (string)($instr['content'] ?? ''),
            'timer_hours' => (int)($instr['timer_hours'] ?? 0),
            'timer_minutes' => (int)($instr['timer_minutes'] ?? 0),
        ];
    }
    return $steps;
}

function recipes_ai_json_to_draft(array $json, $db, string $lang): array
{
    $raw = [
        'title' => trim((string)($json['title'] ?? '')),
        'recipe_image_query' => trim((string)($json['recipe_image_query'] ?? $json['title'] ?? '')),
        'steps' => recipes_ai_build_steps_from_json($json),
    ];
    return recipes_ai_normalize_draft($raw, $db, $lang);
}

function recipes_ai_save_draft(array $draft, string $lang, string $recipes_dir = 'recipes', ?callable $emit = null): array
{
    $emitFn = $emit ?? static fn() => null;
    $db = getDB();
    $title = trim((string)($draft['title'] ?? ''));
    $steps = $draft['steps'] ?? [];
    if ($title === '' || !is_array($steps) || $steps === []) {
        return ['success' => false, 'message' => 'invalid draft'];
    }
    $langs = ['en', 'uk', 'ru', 'be'];
    $targetLangs = array_values(array_filter($langs, static fn($l) => $l !== $lang));
    $instructionSteps = array_values(array_filter($steps, static fn($s) => is_array($s) && ($s['type'] ?? '') === 'instruction'));
    $total = count($targetLangs) + count($instructionSteps) * count($targetLangs);
    $done = 0;
    $emitFn('progress', ['done' => 0, 'total' => max($total, 1), 'label' => 'start']);
    $emitProgress = static function (string $label) use (&$done, $total, $emitFn): void {
        $done++;
        $emitFn('progress', ['done' => $done, 'total' => $total, 'label' => $label]);
    };
    $recipe_id = uniqid();
    $recipe_dir = __DIR__ . '/../' . $recipes_dir . '/' . $recipe_id;
    if (!is_dir($recipe_dir)) {
        mkdir($recipe_dir, 0777, true);
    }
    $recipe = ['id' => $recipe_id, 'title' => []];
    foreach ($langs as $l) {
        $recipe['title'][$l] = ($l === $lang) ? $title : translate_text($title, $lang, $l);
        if ($l !== $lang) {
            $emitProgress('title');
        }
    }
    $translated_steps = [];
    foreach ($steps as $step) {
        if (!is_array($step) || empty($step['type'])) {
            continue;
        }
        if ($step['type'] === 'instruction') {
            $text = is_string($step['content'] ?? null) ? $step['content'] : '';
            $content_trans = [];
            foreach ($langs as $l) {
                $content_trans[$l] = ($l === $lang) ? $text : translate_text($text, $lang, $l);
            }
            foreach ($targetLangs as $l) {
                $emitProgress('instruction');
            }
            $translated_steps[] = [
                'type' => 'instruction',
                'content' => $content_trans,
                'timer' => $step['timer'] ?? null,
            ];
        } elseif ($step['type'] === 'product') {
            $pid = (string)($step['product_id'] ?? $step['content'] ?? '');
            if ($pid === '' || !recipes_load_product_row($db, $pid)) {
                continue;
            }
            $translated_steps[] = [
                'type' => 'product',
                'content' => $pid,
                'quantity' => $step['quantity'] ?? ['value' => 100, 'unit' => 'grams'],
                'timer' => null,
            ];
        }
    }
    if ($translated_steps === []) {
        return ['success' => false, 'message' => 'no valid steps'];
    }
    $image_name = null;
    $imageUrl = trim((string)($draft['image_url'] ?? ''));
    if ($imageUrl !== '') {
        $dl = recipes_ai_download_image($imageUrl);
        if ($dl) {
            $image_name = recipes_image_save_optimized_blob($dl['data'], $recipe_dir);
        }
    }
    $recipe['image'] = $image_name;
    $recipe['steps'] = $translated_steps;
    try {
        $db->exec('BEGIN TRANSACTION');
        $stmt = $db->prepare('INSERT INTO ' . RECIPES_DB_TABLE_PREFIX . 'data
            (id, title_en, title_uk, title_ru, title_be, image)
            VALUES (:id, :te, :tu, :tr, :tb, :img)');
        $stmt->bindValue(':id', $recipe_id, SQLITE3_TEXT);
        $stmt->bindValue(':te', $recipe['title']['en'], SQLITE3_TEXT);
        $stmt->bindValue(':tu', $recipe['title']['uk'], SQLITE3_TEXT);
        $stmt->bindValue(':tr', $recipe['title']['ru'], SQLITE3_TEXT);
        $stmt->bindValue(':tb', $recipe['title']['be'], SQLITE3_TEXT);
        $stmt->bindValue(':img', $image_name, $image_name === null ? SQLITE3_NULL : SQLITE3_TEXT);
        $stmt->execute();
        foreach ($translated_steps as $idx => $step) {
            $stmt = $db->prepare('INSERT INTO ' . RECIPES_STEPS_TABLE . ' (
                recipe_id, step_order, step_type, content,
                quantity_value, quantity_unit, timer_hours, timer_minutes
            ) VALUES (
                :rid, :ord, :typ, :cnt,
                :qv, :qu, :th, :tm
            )');
            $content = '';
            $qv = null;
            $qu = null;
            $th = null;
            $tm = null;
            if ($step['type'] === 'product') {
                $content = $step['content'];
                $qv = $step['quantity']['value'] ?? null;
                $qu = $step['quantity']['unit'] ?? null;
            } else {
                $content = json_encode($step['content'], JSON_UNESCAPED_UNICODE);
                if (!empty($step['timer']) && is_array($step['timer'])) {
                    $th = $step['timer']['hours'] ?? null;
                    $tm = $step['timer']['minutes'] ?? null;
                }
            }
            $stmt->bindValue(':rid', $recipe_id, SQLITE3_TEXT);
            $stmt->bindValue(':ord', $idx, SQLITE3_INTEGER);
            $stmt->bindValue(':typ', $step['type'], SQLITE3_TEXT);
            $stmt->bindValue(':cnt', $content, SQLITE3_TEXT);
            $stmt->bindValue(':qv', $qv, $qv === null ? SQLITE3_NULL : SQLITE3_FLOAT);
            $stmt->bindValue(':qu', $qu, $qu === null ? SQLITE3_NULL : SQLITE3_TEXT);
            $stmt->bindValue(':th', $th, $th === null ? SQLITE3_NULL : SQLITE3_INTEGER);
            $stmt->bindValue(':tm', $tm, $tm === null ? SQLITE3_NULL : SQLITE3_INTEGER);
            $stmt->execute();
        }
        $db->exec('COMMIT');
        @file_put_contents($recipe_dir . '/recipe.json', json_encode($recipe, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return ['success' => true, 'recipe_id' => $recipe_id, 'message' => 'saved'];
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
