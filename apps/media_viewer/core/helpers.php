<?php
function adjustBrightness($hex, $steps) {
    $steps = max(-255, min(255, $steps));
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
    }
    $color_parts = str_split($hex, 2);
    $return = '#';
    foreach ($color_parts as $color) {
        $color = hexdec($color);
        $color = max(0, min(255, $color + $steps));
        $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT);
    }
    return $return;
}

function loadTranslations(string $lang, string $file = ''): array {
    if (!function_exists('ap_load_i18n_from_dir')) {
        require_once dirname(__DIR__, 3) . '/project/i18n_helpers.php';
    }
    return ap_load_i18n_from_dir(dirname(__DIR__), ap_normalize_i18n_lang($lang));
}
