(function () {
    var consent = /(?:^|;\s*)ap_cookie_consent=v1\.p[01]a([01])/.exec(document.cookie);
    if (!consent || consent[1] !== '1' || !window.Sentry) return;
    Sentry.init({
        dsn: 'https://ba5ecc6db1bf4b3491c6247dc6903e47@sentry.serverkherson.xyz/1',
        environment: 'production',
        tracesSampleRate: 0.01,
        initialScope: {tags: {app: 'lopaka'}}
    });
    window.addEventListener('error', function (event) {
        if (event.error) Sentry.captureException(event.error);
    });
    window.addEventListener('unhandledrejection', function (event) {
        Sentry.captureException(event.reason instanceof Error ? event.reason : new Error(String(event.reason)));
    });
})();
