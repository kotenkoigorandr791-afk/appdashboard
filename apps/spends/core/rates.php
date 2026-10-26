<?php
function spendsRatesHttpJson(string $url): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => true]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$raw || $code < 200 || $code >= 300) return null;
    $json = json_decode((string)$raw, true);
    return is_array($json) ? $json : null;
}
function spendsRatesClean(array $raw, array $skip = []): array
{
    $out = [];
    foreach ($raw as $code => $value) {
        $key = strtoupper(trim((string)$code));
        if (in_array($key, $skip, true) || !is_numeric($value) || (float)$value <= 0) continue;
        $out[$key] = (float)$value;
    }
    return $out;
}
function spendsRatesFetch(string $base): array
{
    $primary = spendsRatesHttpJson('https://open.er-api.com/v6/latest/' . $base);
    $rates = ($primary && ($primary['result'] ?? '') === 'success' && is_array($primary['rates'] ?? null)) ? spendsRatesClean($primary['rates']) : [];
    if ($base === 'SYP') return $rates;
    $lower = strtolower($base);
    foreach (['https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/' . $lower . '.min.json', 'https://latest.currency-api.pages.dev/v1/currencies/' . $lower . '.min.json'] as $url) {
        $extra = spendsRatesHttpJson($url);
        if ($extra && is_array($extra[$lower] ?? null)) {
            return $rates + spendsRatesClean($extra[$lower], ['SYP']);
        }
    }
    return $rates;
}
