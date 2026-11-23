<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/i18n_helpers.php';

function sharepoint_resolve_share_lang(?string $explicit = null): string
{
    if ($explicit !== null && $explicit !== '') {
        return ap_normalize_i18n_lang($explicit);
    }
    $fromRequest = $_GET['lang'] ?? '';
    if ($fromRequest !== '') {
        return ap_normalize_i18n_lang((string)$fromRequest);
    }
    if (!empty($_SESSION['user']['language'])) {
        return ap_normalize_i18n_lang((string)$_SESSION['user']['language']);
    }
    if (!empty($_COOKIE['sk_language'])) {
        return ap_normalize_i18n_lang((string)$_COOKIE['sk_language']);
    }
    if (!empty($_SESSION['lang'])) {
        return ap_normalize_i18n_lang((string)$_SESSION['lang']);
    }
    return 'en';
}

function sharepoint_load_share_i18n(?string $lang = null): array
{
    $lang = sharepoint_resolve_share_lang($lang);
    $root = __DIR__ . '/i18n';
    $load = static function (string $code) use ($root): array {
        $file = $root . '/' . $code . '.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    };
    return array_merge($load('en'), $load($lang));
}