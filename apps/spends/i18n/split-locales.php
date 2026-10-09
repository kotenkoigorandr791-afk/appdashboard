<?php
declare(strict_types=1);
/**
 * Rebuilds i18n/<lang>/<chunk>.json from the flat i18n/<lang>.json dictionaries.
 *
 * The flat files stay the source of truth for editing; this only redistributes
 * them so the browser can fetch one chunk at a time. Run after editing a locale:
 *
 *     php i18n/split-locales.php
 *
 * Rules are ordered: the first regex that matches decides the chunk.
 */
$dir = __DIR__;
$langs = ['en', 'ru', 'uk', 'be', 'pl', 'cs'];

$rules = [
    'chart'    => '/^(chart|rate_|compare_to|base_currency|select_currency|vs_usd|total_|last_update|monthly_budget)/i',
    'settings' => '/^(settings|section|role|admin|theme|color|hide_language|username|current_user|user_management|rows_per_page|months_before_after|preview|record_count)/i',
    'import'   => '/^(msg_|import_|csv|backup|restore|confirm_delete_backup|preview_title|file_view_title|existing_backups|empty_backup)/i',
    'stores'   => '/^(store|stores|add_store|edit_store|delete_store|merchant|store_category|store_photo|store_name)/i',
    'users'    => '/^(add_user|delete_user|share_|support_chat|message_sent|link_copied|no_users_found|confirm_delete_user)/i',
];

$assigned = ['ai' => [], 'core' => [], 'search' => [], 'settings' => [],
             'sync' => [], 'upload' => [], 'chart' => [], 'import' => [],
             'stores' => [], 'users' => []];

foreach ($langs as $lang) {
    $src = $dir . '/' . $lang . '.json';
    if (!is_file($src)) {
        continue;
    }
    $data = json_decode((string)file_get_contents($src), true);
    if (!is_array($data)) {
        fwrite(STDERR, "$lang: invalid JSON, skipped\n");
        continue;
    }

    $chunks = array_fill_keys(array_keys($assigned), []);
    foreach ($data as $key => $value) {
        $chunk = 'core';
        foreach ($rules as $name => $pattern) {
            if (preg_match($pattern, (string)$key)) {
                $chunk = $name;
                break;
            }
        }
        $chunks[$chunk][$key] = $value;
    }

    $target = $dir . '/' . $lang;
    if (!is_dir($target)) {
        mkdir($target, 0755, true);
    }
    foreach ($chunks as $name => $entries) {
        if ($entries === []) {
            $file = $target . '/' . $name . '.json';
            if (is_file($file)) {
                unlink($file);
            }
            continue;
        }
        ksort($entries);
        file_put_contents(
            $target . '/' . $name . '.json',
            json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
        );
    }
    $count = array_sum(array_map('count', $chunks));
    echo "$lang: $count keys -> " . count(array_filter($chunks)) . " chunks\n";
}