<?php

declare(strict_types=1);

if (!function_exists('cloud_load_translation_map')) {
    function cloud_load_translation_map(string $controllerDir): array
    {
        $langDir = rtrim($controllerDir, '/\\') . DIRECTORY_SEPARATOR . 'lang';
        if (!is_dir($langDir)) {
            return [];
        }

        $translations = [];
        $files = glob($langDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            $locale = pathinfo($file, PATHINFO_FILENAME);
            $data = require $file;
            if (is_array($data)) {
                $translations[$locale] = $data;
            }
        }

        return $translations;
    }
}
