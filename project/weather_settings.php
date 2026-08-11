<?php
require_once __DIR__ . '/../lib/app_db.php';
require_once __DIR__ . '/../lib/db_auth_security.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user']['email'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$email = $_SESSION['user']['email'];

$weather_http_base = [
    'timeout' => 15,
    'ignore_errors' => true,
    'follow_location' => 1,
    'max_redirects' => 5,
    'user_agent' => 'AppDashboard/1.0',
];

function weather_http_get(string $url): ?string {
    global $weather_http_base;
    $ctx = stream_context_create([
        'http' => $weather_http_base,
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $result = @file_get_contents($url, false, $ctx);
    return $result === false ? null : $result;
}

function weather_http_post(string $url, array $payload): ?string {
    global $weather_http_base;
    $ctx = stream_context_create([
        'http' => array_merge($weather_http_base, [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]),
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $result = @file_get_contents($url, false, $ctx);
    return $result === false ? null : $result;
}

function weather_country_language(string $countryCode): ?string {
    static $map = [
        'ua' => 'uk', 'ru' => 'ru', 'by' => 'be', 'pl' => 'pl', 'cz' => 'cs', 'sk' => 'sk',
        'de' => 'de', 'at' => 'de', 'ch' => 'de', 'fr' => 'fr', 'es' => 'es', 'it' => 'it',
        'pt' => 'pt', 'nl' => 'nl', 'be' => 'nl', 'jp' => 'ja', 'kr' => 'ko', 'cn' => 'zh',
        'tw' => 'zh', 'hk' => 'zh', 'sa' => 'ar', 'ae' => 'ar', 'eg' => 'ar', 'il' => 'he',
        'tr' => 'tr', 'gr' => 'el', 'ro' => 'ro', 'hu' => 'hu', 'bg' => 'bg', 'hr' => 'hr',
        'rs' => 'sr', 'si' => 'sl', 'lt' => 'lt', 'lv' => 'lv', 'ee' => 'et', 'fi' => 'fi',
        'se' => 'sv', 'no' => 'no', 'dk' => 'da', 'is' => 'is', 'md' => 'ro', 'ge' => 'ka',
        'am' => 'hy', 'az' => 'az', 'kz' => 'kk', 'uz' => 'uz', 'th' => 'th', 'vn' => 'vi',
        'id' => 'id', 'my' => 'ms', 'in' => 'hi', 'bd' => 'bn', 'pk' => 'ur', 'ir' => 'fa',
        'mx' => 'es', 'ar' => 'es', 'cl' => 'es', 'co' => 'es', 'br' => 'pt',
    ];
    $code = strtolower($countryCode);
    return $map[$code] ?? null;
}

function weather_parse_geocode_results(array $decoded, string $countryCode): array {
    $countryCode = strtoupper($countryCode);
    $cities = [];
    if (!isset($decoded['results']) || !is_array($decoded['results'])) {
        return $cities;
    }
    foreach ($decoded['results'] as $result) {
        $resultCountryCode = strtoupper((string)($result['country_code'] ?? ''));
        if ($countryCode !== '' && $resultCountryCode !== $countryCode) continue;
        $name = (string)($result['name'] ?? '');
        if ($name === '' || !isset($result['latitude'], $result['longitude'])) continue;
        $admin = (string)($result['admin1'] ?? '');
        $countryName = (string)($result['country'] ?? '');
        $cities[] = [
            'name' => $name,
            'admin1' => $admin,
            'country' => $countryName,
            'country_code' => strtolower($resultCountryCode ?: $countryCode),
            'lat' => (float)$result['latitude'],
            'lon' => (float)$result['longitude'],
            'label' => implode(', ', array_filter([$name, $admin, $countryName])),
        ];
    }
    return $cities;
}

function weather_geocode_search(string $query, string $countryCode, string $countryName, ?string $language = null): array {
    $searchName = $query;
    if ($countryName !== '' && stripos($query, $countryName) === false) {
        $searchName = trim($query . ', ' . $countryName);
    }
    $url = 'https://geocoding-api.open-meteo.com/v1/search?count=50&format=json&name=' . rawurlencode($searchName);
    if ($countryCode !== '') {
        $url .= '&country_code=' . rawurlencode(strtoupper($countryCode));
    }
    if ($language !== null && $language !== '') {
        $url .= '&language=' . rawurlencode(strtolower($language));
    }
    $data = weather_http_get($url);
    if ($data === null) return [];
    $decoded = json_decode($data, true);
    if (!is_array($decoded)) return [];
    return weather_parse_geocode_results($decoded, $countryCode);
}

function weather_search_cities(string $query, string $countryCode, string $countryName): array {
    $languages = ['en'];
    $localLang = weather_country_language($countryCode);
    if ($localLang !== null && $localLang !== 'en') {
        $languages[] = $localLang;
    }
    $merged = [];
    foreach ($languages as $language) {
        foreach (weather_geocode_search($query, $countryCode, $countryName, $language) as $city) {
            $key = round($city['lat'], 3) . '|' . round($city['lon'], 3);
            if (!isset($merged[$key])) {
                $merged[$key] = $city;
            }
        }
    }
    return array_values($merged);
}

function weather_geocode_one(string $city, string $countryCode, string $countryName): ?array {
    $results = weather_search_cities($city, $countryCode, $countryName);
    return $results[0] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'countries') {
        $data = weather_http_get('https://countriesnow.space/api/v0.1/countries/flag/images');
        $countries = [];
        if ($data !== null) {
            $decoded = json_decode($data, true);
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                foreach ($decoded['data'] as $c) {
                    $name = (string)($c['name'] ?? '');
                    $iso2 = strtolower((string)($c['iso2'] ?? ''));
                    if ($name === '' || $iso2 === '') continue;
                    $countries[] = [
                        'name' => $name,
                        'iso2' => $iso2,
                        'flag' => 'https://flagcdn.com/w40/' . $iso2 . '.png',
                    ];
                }
                usort($countries, fn($a, $b) => strcmp($a['name'], $b['name']));
            }
        }
        if (!$countries) {
            http_response_code(502);
            echo json_encode(['error' => 'Upstream unavailable', 'countries' => []]);
            exit;
        }
        echo json_encode(['countries' => $countries]);
        exit;
    }

    if ($action === 'regions') {
        $country = (string)($_GET['country'] ?? '');
        if ($country === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing country']);
            exit;
        }
        $data = weather_http_post('https://countriesnow.space/api/v0.1/countries/states', ['country' => $country]);
        if ($data === null) {
            http_response_code(502);
            echo json_encode(['error' => 'Upstream unavailable']);
            exit;
        }
        $decoded = json_decode($data, true);
        $states = [];
        if (isset($decoded['data']['states']) && is_array($decoded['data']['states'])) {
            foreach ($decoded['data']['states'] as $s) {
                if (!empty($s['name'])) {
                    $states[] = ['name' => (string)$s['name'], 'state_code' => (string)($s['state_code'] ?? '')];
                }
            }
        }
        echo json_encode(['regions' => $states]);
        exit;
    }

    if ($action === 'coords') {
        $city = (string)($_GET['city'] ?? '');
        $country = (string)($_GET['country'] ?? '');
        $countryCode = strtoupper((string)($_GET['country_code'] ?? ''));
        if ($city === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing city']);
            exit;
        }
        $result = weather_geocode_one($city, $countryCode, $country);
        if ($result) {
            echo json_encode($result);
            exit;
        }
        http_response_code(404);
        echo json_encode(['error' => 'City not found', 'lat' => null, 'lon' => null]);
        exit;
    }

    if ($action === 'city_search') {
        $query = trim((string)($_GET['q'] ?? ''));
        $country = trim((string)($_GET['country'] ?? ''));
        $countryCode = strtoupper((string)($_GET['country_code'] ?? ''));
        if (strlen($query) < 2) {
            echo json_encode(['cities' => []]);
            exit;
        }
        if ($countryCode === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing country_code', 'cities' => []]);
            exit;
        }
        $cities = weather_search_cities($query, $countryCode, $country);
        echo json_encode(['cities' => $cities]);
        exit;
    }

    if ($action === 'cities') {
        $country = (string)($_GET['country'] ?? '');
        $region = (string)($_GET['region'] ?? '');
        if ($country === '' || $region === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing country/region']);
            exit;
        }
        $data = weather_http_post('https://countriesnow.space/api/v0.1/countries/state/cities', ['country' => $country, 'state' => $region]);
        if ($data === null) {
            http_response_code(502);
            echo json_encode(['error' => 'Upstream unavailable']);
            exit;
        }
        $decoded = json_decode($data, true);
        $cities = [];
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            foreach ($decoded['data'] as $city) {
                if (is_string($city) && $city !== '') {
                    $cities[] = ['name' => $city];
                }
            }
        }
        echo json_encode(['cities' => $cities]);
        exit;
    }

    if ($action === 'country_cities') {
        $country = trim((string)($_GET['country'] ?? ''));
        if ($country === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing country', 'cities' => []]);
            exit;
        }
        $data = weather_http_post('https://countriesnow.space/api/v0.1/countries/cities', ['country' => $country]);
        if ($data === null) {
            http_response_code(502);
            echo json_encode(['error' => 'Upstream unavailable', 'cities' => []]);
            exit;
        }
        $decoded = json_decode($data, true);
        $cities = isset($decoded['data']) && is_array($decoded['data'])
            ? array_values(array_filter($decoded['data'], static fn($city) => is_string($city) && $city !== ''))
            : [];
        sort($cities, SORT_NATURAL | SORT_FLAG_CASE);
        echo json_encode(['cities' => $cities]);
        exit;
    }

    $settings = app_get_weather_settings($email);
    echo json_encode($settings);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input']);
        exit;
    }

    $useCurrent = $input['use_current_location'] ?? true;
    $country = $input['selected_country'] ?? null;
    $countryCode = $input['selected_country_code'] ?? null;
    $region = $input['selected_region'] ?? null;
    $city = $input['selected_city'] ?? null;

    $lat = null;
    $lon = null;

    if (isset($input['lat'], $input['lon'])) {
        $lat = (float)$input['lat'];
        $lon = (float)$input['lon'];
    }
    if (!$useCurrent && $city && $country && ($lat === null || $lon === null)) {
        $result = weather_geocode_one((string)$city, strtoupper((string)$countryCode), (string)$country);
        if ($result) {
            $lat = (float)$result['lat'];
            $lon = (float)$result['lon'];
            $countryCode = strtolower((string)($result['country_code'] ?? $countryCode));
        }
    }

    app_set_weather_settings($email, [
        'use_current_location' => $useCurrent,
        'selected_country' => $country,
        'selected_country_code' => $countryCode,
        'selected_region' => $region,
        'selected_city' => $city,
        'lat' => $lat,
        'lon' => $lon
    ]);

    echo json_encode([
        'success' => true,
        'lat' => $lat,
        'lon' => $lon
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
