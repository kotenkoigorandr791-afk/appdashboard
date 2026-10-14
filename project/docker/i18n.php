<?php
/**
 * Docker Manager — translations.
 *
 * Every string lives in the shared dictionaries under project/i18n/<lang>.json.
 * Docker-owned keys carry a `docker_` prefix so they never collide with the
 * generic `search`, `remove`, `copy`, `status`, ... keys used by other apps.
 */
declare(strict_types=1);

function docker_supported_langs(): array {
    return ['en', 'ru', 'uk', 'be', 'pl', 'cs'];
}

function docker_normalize_lang($lang): string {
    $lang = strtolower(trim((string)$lang));
    return in_array($lang, docker_supported_langs(), true) ? $lang : 'en';
}

/**
 * Returns translations keyed by language code: ['en' => [...], <lang> => [...]].
 * The active locale is the English dictionary with the locale on top, so
 * t() / the JS I18N payload can fall back key-by-key.
 */
function docker_load_i18n(string $lang): array {
    $lang = docker_normalize_lang($lang);
    $base = dirname(__DIR__) . '/i18n';
    $merged = [];
    foreach (array_unique(['en', $lang]) as $code) {
        $path = $base . '/' . $code . '.json';
        if (!is_readable($path)) continue;
        $data = json_decode((string)file_get_contents($path), true);
        if (is_array($data)) $merged[$code] = $data;
    }
    $en = $merged['en'] ?? [];
    $result = ['en' => $en];
    $result[$lang] = ($lang === 'en') ? $en : array_merge($en, $merged[$lang] ?? []);
    return $result;
}

function t($key) {
    global $i18n, $lang;
    return $i18n[$lang][$key] ?? $i18n['en'][$key] ?? $key;
}

function tf($key, array $vars = []) {
    $text = t($key);
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}
