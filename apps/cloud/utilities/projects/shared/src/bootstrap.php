<?php

declare(strict_types=1);

if (!defined('CLOUD_APP_ROOT')) {
    define('CLOUD_APP_ROOT', dirname(__DIR__, 4));
}

if (!defined('CLOUD_SERVER_ROOT')) {
    define('CLOUD_SERVER_ROOT', dirname(CLOUD_APP_ROOT, 2));
}

if (!function_exists('cloud_projects_bootstrap')) {
    function cloud_projects_bootstrap(bool $withStorageHelper = true): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }

        $searchDir = CLOUD_APP_ROOT;
        for ($level = 0; $level < 16; $level++) {
            $candidate = $searchDir . '/smart_log_boot.php';
            if (is_file($candidate)) {
                require_once $candidate;
                break;
            }

            $parent = dirname($searchDir);
            if ($parent === $searchDir) {
                break;
            }
            $searchDir = $parent;
        }

        if ($withStorageHelper) {
            $storageHelper = CLOUD_SERVER_ROOT . '/storage_helper.php';
            if (is_file($storageHelper)) {
                require_once $storageHelper;
            }
        }

        require_once __DIR__ . '/i18n.php';

        require_once CLOUD_APP_ROOT . '/includes/session.php';
        cloud_session_start();

        $booted = true;
    }
}

if (!function_exists('cloud_app_base_url')) {
    function cloud_app_base_url(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '' && preg_match('#^(.*)/utilities/projects/[^/]+/public/index\.php$#', $scriptName, $matches)) {
            return rtrim($matches[1], '/');
        }

        if ($scriptName !== '') {
            $dir = str_replace('\\', '/', dirname($scriptName));
            return $dir === '/' ? '' : rtrim($dir, '/');
        }

        return '';
    }
}

if (!function_exists('cloud_url')) {
    function cloud_url(string $path = ''): string
    {
        $base = cloud_app_base_url();
        if ($path === '') {
            return $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}
