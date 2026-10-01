<?php
declare(strict_types=1);

if (!function_exists('app_base_domain')) {
    function app_base_domain(): string
    {
        $domain = trim((string)(getenv('BASE_DOMAIN') ?: ''));
        if ($domain !== '') {
            return strtolower($domain);
        }
        $fromUrl = trim((string)(getenv('APP_PUBLIC_BASE_URL') ?: ''));
        if ($fromUrl !== '' && preg_match('#^https?://([^/]+)#i', $fromUrl, $m)) {
            return strtolower($m[1]);
        }
        return 'localhost';
    }
}

if (!function_exists('app_public_scheme')) {
    function app_public_scheme(): string
    {
        $base = app_base_domain();
        $isLocal = ($base === 'localhost' || $base === '127.0.0.1' || str_ends_with($base, '.local') || str_ends_with($base, '.localhost'));
        $scheme = strtolower(trim((string)(getenv('APP_PUBLIC_SCHEME') ?: '')));
        if ($scheme === 'http' || $scheme === 'https') {
            // Never force http for a real public domain (breaks OIDC issuer vs https clients).
            if ($scheme === 'http' && !$isLocal) {
                return 'https';
            }
            return $scheme;
        }
        return $isLocal ? 'http' : 'https';
    }
}

if (!function_exists('app_subdomain_host')) {
    function app_subdomain_host(string $sub = ''): string
    {
        $base = app_base_domain();
        $sub = strtolower(trim($sub));
        return $sub === '' ? $base : $sub . '.' . $base;
    }
}

if (!function_exists('app_public_url')) {
    function app_public_url(string $sub = ''): string
    {
        static $envKeys = [
            '' => 'APP_PUBLIC_BASE_URL',
            'ai' => 'AI_PUBLIC_URL',
            'search' => 'SEARCH_PUBLIC_URL',
            'git' => 'GITEA_PUBLIC_URL',
            'photo' => 'IMMICH_PUBLIC_URL',
            'document' => 'DOCUMENT_PUBLIC_URL',
            'docker' => 'DOCKER_PUBLIC_URL',
            'dawarich' => 'DAWARICH_PUBLIC_URL',
            'sentry' => 'SENTRY_PUBLIC_URL',
        ];
        $sub = strtolower(trim($sub));
        $envKey = $envKeys[$sub] ?? '';
        if ($envKey !== '') {
            $configured = trim((string)(getenv($envKey) ?: ''));
            if ($configured !== '') {
                return rtrim($configured, '/');
            }
        }
        return app_public_scheme() . '://' . app_subdomain_host($sub);
    }
}

if (!function_exists('app_service_hosts')) {
    /** @return array<string,string> */
    function app_service_hosts(): array
    {
        return [
            'app' => app_subdomain_host(''),
            'ai' => app_subdomain_host('ai'),
            'search' => app_subdomain_host('search'),
            'git' => app_subdomain_host('git'),
            'photo' => app_subdomain_host('photo'),
            'document' => app_subdomain_host('document'),
            'docker' => app_subdomain_host('docker'),
            'dawarich' => app_subdomain_host('dawarich'),
            'sentry' => app_subdomain_host('sentry'),
        ];
    }
}
