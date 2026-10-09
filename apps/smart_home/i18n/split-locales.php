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
    'map'     => '/^(map_|floor_plan|room|home_scope|home_view|home_group)/i',
    'devices' => '/^(device|devices|add_device|map_add_device|device_|no_devices|online_count|offline_count|unknown_count|devices_count|status|actions|events|prop_)/i',
    'settings'=> '/^(settings|api_|mqtt_|server_|ap_domain|endpoints_|save|cancel|search|period|statistics|admin|role)/i',
];

$chunks = ['core' => [], 'map' => [], 'devices' => [], 'settings' => []];

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