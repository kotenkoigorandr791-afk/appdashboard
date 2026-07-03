<?php
function ap_normalize_i18n_lang(string $lang): string {
    $lang = preg_replace('/[^a-z0-9_-]/i', '', strtolower($lang));
    if ($lang === 'ua') return 'uk';
    if ($lang === 'by') return 'be';
    return $lang !== '' ? $lang : 'en';
}
function ap_i18n_supported_langs(): array {
    return ['en', 'ru', 'uk', 'be', 'pl', 'cs'];
}
function ap_load_i18n_file(string $path): array {
    static $cache = [];
    if (array_key_exists($path, $cache)) return $cache[$path];
    if (!is_readable($path)) return $cache[$path] = [];
    $data = json_decode((string)file_get_contents($path), true);
    return $cache[$path] = is_array($data) ? $data : [];
}
function ap_load_i18n_merged_lang(string $i18nBase, string $lang): array {
    $single = $i18nBase . '/' . $lang . '.json';
    if (is_readable($single)) return ap_load_i18n_file($single);
    $subdir = $i18nBase . '/' . $lang;
    if (!is_dir($subdir)) return [];
    $merged = [];
    foreach (glob($subdir . '/*.json') ?: [] as $file) {
        $chunk = ap_load_i18n_file($file);
        if ($chunk !== []) $merged = array_merge($merged, $chunk);
    }
    return $merged;
}
function ap_load_i18n_from_dir(string $dir, string $lang, ?string $fallbackLang = 'en'): array {
    $lang = ap_normalize_i18n_lang($lang);
    $base = rtrim($dir, '/') . '/i18n';
    $data = ap_load_i18n_merged_lang($base, $lang);
    if ($data === [] && $fallbackLang !== null) {
        $fb = ap_normalize_i18n_lang($fallbackLang);
        if ($fb !== $lang) $data = ap_load_i18n_merged_lang($base, $fb);
    }
    return $data;
}
function ap_load_i18n_all_langs(string $dir): array {
    $result = [];
    foreach (ap_i18n_supported_langs() as $lang) {
        $t = ap_load_i18n_from_dir($dir, $lang, null);
        if ($t !== []) $result[$lang] = $t;
    }
    return $result;
}
function ap_i18n_lang_exists_in_dir(string $dir, string $lang): bool {
    $lang = ap_normalize_i18n_lang($lang);
    $base = rtrim($dir, '/') . '/i18n';
    return is_readable($base . '/' . $lang . '.json') || is_dir($base . '/' . $lang);
}
function ap_i18n_t(array $translations, string $key, string $default = ''): string {
    $v = $translations[$key] ?? $default;
    return is_string($v) ? $v : $default;
}
