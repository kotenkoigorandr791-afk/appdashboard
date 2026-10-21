<?php
declare(strict_types=1);

$lang = preg_replace('/[^a-z]/', '', strtolower((string)($_GET['lang'] ?? 'en')));
if ($lang === '') {
    $lang = 'en';
}
$root = __DIR__ . '/i18n';
$load = static function (string $code) use ($root): array {
    $file = $root . '/' . $code . '.json';
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
};
$messages = array_merge($load('en'), $load($lang));

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo 'window.SHAREPOINT_SHARE_I18N = ' . json_encode(
    $messages,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) . ';';
