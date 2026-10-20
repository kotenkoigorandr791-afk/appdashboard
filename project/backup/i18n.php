<?php
/**
 * Backup Manager — translations.
 *
 * Every string lives in the shared dictionaries under project/i18n/<lang>.json
 * (or in the per-language chunks, when present). Backup-owned keys carry a
 * `backup_` prefix so they never collide with the generic keys used by other
 * apps.
 */
declare(strict_types=1);

function backup_supported_langs(): array {
    return ['en', 'ru', 'uk', 'be', 'pl', 'cs'];
}

function backup_normalize_lang($lang): string {
    $lang = strtolower(trim((string)$lang));
    if ($lang === 'ua') $lang = 'uk';
    if ($lang === 'by') $lang = 'be';
    return in_array($lang, backup_supported_langs(), true) ? $lang : 'en';
}

function backup_read_lang_dictionary(string $base, string $code): array {
    $chunksDir = $base . '/' . $code;
    if (is_dir($chunksDir)) {
        $merged = [];
        $files = glob($chunksDir . '/*.json') ?: [];
        sort($files);
        foreach ($files as $file) {
            if (!is_readable($file)) continue;
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) $merged = array_merge($merged, $data);
        }
        if ($merged !== []) return $merged;
    }
    $path = $base . '/' . $code . '.json';
    if (!is_readable($path)) return [];
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

/**
 * Returns translations keyed by language code: ['en' => [...], <lang> => [...]].
 * The active locale is the English dictionary with the locale on top, so
 * bt() / the JS payload can fall back key-by-key.
 */
function backup_load_i18n(string $lang): array {
    $lang = backup_normalize_lang($lang);
    $base = dirname(__DIR__) . '/i18n';
    $en = backup_read_lang_dictionary($base, 'en');
    if ($lang === 'en') {
        return ['en' => $en, 'en' => $en];
    }
    $active = array_merge($en, backup_read_lang_dictionary($base, $lang));
    return ['en' => $en, $lang => $active];
}

function bt(string $key): string {
    global $backupI18n, $backupLang;
    $lang = isset($backupLang) ? (string)$backupLang : 'en';
    return (string)($backupI18n[$lang][$key] ?? $backupI18n['en'][$key] ?? $key);
}

function btf(string $key, array $vars = []): string {
    $text = bt($key);
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}

/**
 * Payload consumed by assets/backup.js. Keys are exposed without the
 * `backup_` prefix, matching the data-bkp-i18n attribute convention.
 */
function backup_i18n_payload(string $lang): array {
    global $backupI18n;
    $lang = backup_normalize_lang($lang);
    $dict = $backupI18n[$lang] ?? $backupI18n['en'] ?? [];
    $out = [];
    foreach ($dict as $key => $value) {
        if (!is_string($key) || !is_string($value)) continue;
        if (strpos($key, 'backup_') !== 0) continue;
        $out[substr($key, 7)] = $value;
    }
    return $out;
}

/** Initialise the globals used by bt()/btf() for a request. */
function backup_boot_i18n($lang): string {
    global $backupI18n, $backupLang;
    $backupLang = backup_normalize_lang($lang);
    $backupI18n = backup_load_i18n($backupLang);
    return $backupLang;
}
