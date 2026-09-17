<?php
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
            'dsn' => (string)(getenv('SENTRY_DSN') ?: 'https://d1dce542fdd62eb4e5cc6dae1a4cc7ac@o4512096935280640.ingest.de.sentry.io/4512096937902160'),
            'environment' => (string)(getenv('SENTRY_ENVIRONMENT') ?: (getenv('APP_ENV') ?: 'production')),
            'release' => (string)(getenv('SENTRY_RELEASE') ?: ''),
            'traces_sample_rate' => (float)(getenv('SENTRY_TRACES_SAMPLE_RATE') ?: 0.1),
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
    $dsn = (string)(getenv('SENTRY_BROWSER_DSN') ?: (getenv('SENTRY_DSN') ?: 'https://d1dce542fdd62eb4e5cc6dae1a4cc7ac@o4512096935280640.ingest.de.sentry.io/4512096937902160'));
    if ($dsn === '') {
        return;
    }
    $environment = (string)(getenv('SENTRY_ENVIRONMENT') ?: (getenv('APP_ENV') ?: 'production'));
    $release = (string)(getenv('SENTRY_RELEASE') ?: '');
    echo '<script src="https://browser.sentry-cdn.com/8.55.0/bundle.min.js" crossorigin="anonymous"></script>';
    echo '<script>(function(){if(!window.Sentry)return;Sentry.init({dsn:' . json_encode($dsn) . ',environment:' . json_encode($environment) . ',release:' . json_encode($release) . ',tracesSampleRate:0.1,initialScope:{tags:{app:' . json_encode($appId) . '}}});var original=console.error;console.error=function(){try{var args=[].slice.call(arguments);var first=args[0];if(first instanceof Error)Sentry.captureException(first);else Sentry.captureMessage(args.map(function(v){try{return typeof v==="string"?v:JSON.stringify(v)}catch(e){return String(v)}}).join(" "),"error");}catch(e){}return original.apply(console,args);};})();</script>';
}
