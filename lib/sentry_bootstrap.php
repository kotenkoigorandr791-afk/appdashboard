<?php
require_once __DIR__ . '/cookie_consent.php';
function app_sentry_bootstrap(string $appId): void
{
    static $booted = false;
    $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
    error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    if (!$booted) {
        foreach ([$root . '/vendor/autoload.php', $root . '/apps/cloud/vendor/autoload.php', $root . '/webssh/vendor/autoload.php'] as $autoload) {
            if (!function_exists('Sentry\\init') && is_file($autoload)) {
                require_once $autoload;
            }
        }
        if (!function_exists('Sentry\\init')) {
            $booted = true;
            return;
        }
        \Sentry\init([
            'dsn' => (string)(getenv('SENTRY_DSN') ?: 'https://ba5ecc6db1bf4b3491c6247dc6903e47@sentry.serverkherson.xyz/1'),
            'environment' => (string)(getenv('SENTRY_ENVIRONMENT') ?: (getenv('APP_ENV') ?: 'production')),
            'release' => (string)(getenv('SENTRY_RELEASE') ?: ''),
            'traces_sample_rate' => (float)(getenv('SENTRY_TRACES_SAMPLE_RATE') ?: 0.01),
            'error_types' => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED,
            'before_send' => static function ($event, $hint = null) {
                $exception = is_object($hint) && property_exists($hint, 'exception') ? $hint->exception : null;
                if ($exception instanceof ErrorException && in_array($exception->getSeverity(), [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                    return null;
                }
                return $event;
            },
        ]);
        $booted = true;
    }
    if (function_exists('Sentry\\configureScope')) {
        \Sentry\configureScope(static function ($scope) use ($appId): void {
            if (method_exists($scope, 'setTag')) {
                $scope->setTag('app', $appId);
            }
        });
    }
}
function app_sentry_capture_exception(Throwable $exception): void
{
    if (!function_exists('Sentry\\captureException')) {
        return;
    }
    try {
        \Sentry\captureException($exception);
    } catch (Throwable) {
    }
}

function app_sentry_browser_script(string $appId): void
{
    $dsn = (string)(getenv('SENTRY_BROWSER_DSN') ?: (getenv('SENTRY_DSN') ?: 'https://ba5ecc6db1bf4b3491c6247dc6903e47@sentry.serverkherson.xyz/1'));
    if ($dsn === '' || !app_cookie_consent_allows('analytics')) {
        return;
    }
    $environment = (string)(getenv('SENTRY_ENVIRONMENT') ?: (getenv('APP_ENV') ?: 'production'));
    $release = (string)(getenv('SENTRY_RELEASE') ?: '');
    echo '<script src="https://browser.sentry-cdn.com/8.55.0/bundle.min.js" crossorigin="anonymous"></script>';
    echo '<script>(function(){if(!window.Sentry)return;Sentry.init({dsn:' . json_encode($dsn, JSON_HEX_TAG) . ',environment:' . json_encode($environment, JSON_HEX_TAG) . ',release:' . json_encode($release, JSON_HEX_TAG) . ',tracesSampleRate:0.01,initialScope:{tags:{app:' . json_encode($appId, JSON_HEX_TAG) . '}}});var isNetworkAbort=function(e){return e&&(e.name==="AbortError"||(e.name==="TypeError"&&/fetch|load failed|network/i.test(e.message||"")))};var original=console.error;console.error=function(){var args=[].slice.call(arguments);try{var err=args.find(function(v){return v instanceof Error});if(!(err&&isNetworkAbort(err))){var text=args.map(function(v){if(typeof v==="string")return v;if(v instanceof Error)return v.name+": "+v.message;try{return JSON.stringify(v)}catch(e){return String(v)}}).join(" ");if(err)Sentry.captureException(err,{extra:{console:text}});else Sentry.captureMessage(text,"error");}}catch(e){}return original.apply(console,args);};})();</script>';
}
