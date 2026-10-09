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
    'charts'  => '/^(chart_|statistics_|line_chart|bar_chart|avg_daily_load|year_|days_in_month|today_steps|this_week|remaining)/i',
    'import'  => '/^(external_|takeout_|zeep_|instruction_|no_step_files|organize_file|import_|invalid_image|invalid_url|invalid_)/i',
    'backup'  => '/^(backup|restore|create_backup|download_backup|file_not_found|failed_|data_saved|invalid_data|invalid_date_format)/i',
    'sync'    => '/^(sync_|mobile_app_token|mobile_app_)/i',
    'goals'   => '/^(goal|profile_|add_profile|weight|height|age|units_|daily_|step_goal)/i',
];

$chunks = ['core' => [], 'charts' => [], 'import' => [], 'backup' => [], 'sync' => [], 'goals' => []];

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

    $buckets = $chunks;
    foreach ($data as $key => $value) {
        $chunk = 'core';
        foreach ($rules as $name => $pattern) {
            if (preg_match($pattern, (string)$key)) {
                $chunk = $name;
                break;
            }
        }
        $buckets[$chunk][$key] = $value;
    }

    $target = $dir . '/' . $lang;
    if (!is_dir($target)) {
        mkdir($target, 0755, true);
    }
    foreach ($buckets as $name => $entries) {
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
    $count = array_sum(array_map('count', $buckets));
    echo "$lang: $count keys -> " . count(array_filter($buckets)) . " chunks\n";
}