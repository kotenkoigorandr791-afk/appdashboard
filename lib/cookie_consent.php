<?php
const APP_COOKIE_CONSENT_NAME = 'ap_cookie_consent';
const APP_COOKIE_CONSENT_OPTIONAL = ['preferences', 'analytics'];
const APP_COOKIE_CONSENT_PREFERENCE_COOKIES = ['sk_language', 'sk_theme', 'sk_color', 'sk_user_has_account', 'browser_theme', 'grid_columns', 'font_size', 'albums_per_page'];
function app_cookie_request_secure(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}
function app_cookie_consent_set(string $name, string $value, int $expires): void
{
    setcookie($name, $value, ['expires' => $expires, 'path' => '/', 'secure' => app_cookie_request_secure(), 'httponly' => false, 'samesite' => 'Lax']);
}
function app_cookie_consent(): array
{
    if (!preg_match('/^v1\.p([01])a([01])$/', (string)($_COOKIE[APP_COOKIE_CONSENT_NAME] ?? ''), $m)) {
        return ['decided' => false, 'preferences' => false, 'analytics' => false];
    }
    return ['decided' => true, 'preferences' => $m[1] === '1', 'analytics' => $m[2] === '1'];
}
function app_cookie_consent_allows(string $category): bool
{
    return $category === 'necessary' || !empty(app_cookie_consent()[$category]);
}
function app_cookie_consent_write(bool $preferences, bool $analytics): void
{
    $value = 'v1.p' . (int)$preferences . 'a' . (int)$analytics;
    $_COOKIE[APP_COOKIE_CONSENT_NAME] = $value;
    if (headers_sent()) return;
    app_cookie_consent_set(APP_COOKIE_CONSENT_NAME, $value, time() + 365 * 86400);
    if (!$preferences) app_cookie_consent_purge(APP_COOKIE_CONSENT_PREFERENCE_COOKIES);
}
function app_cookie_consent_purge(array $names): void
{
    foreach ($names as $name) {
        if (!isset($_COOKIE[$name])) continue;
        app_cookie_consent_set($name, '', time() - 3600);
        unset($_COOKIE[$name]);
    }
}
function app_cookie_consent_browser_script(): void
{
    echo '<script>window.apCookieConsentAllows=function(c){if(c===\'necessary\')return true;var m=/(?:^|;\s*)' . APP_COOKIE_CONSENT_NAME . '=v1\.p([01])a([01])/.exec(document.cookie);return !!m&&(c===\'preferences\'?m[1]:c===\'analytics\'?m[2]:\'0\')===\'1\';};window.apSetPreferenceCookie=function(n,v,a){if(window.apCookieConsentAllows(\'preferences\'))document.cookie=n+\'=\'+encodeURIComponent(v)+\'; path=/; max-age=\'+a+\'; samesite=Lax\';};</script>';
}
