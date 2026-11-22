#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/db.php';
require_once APP_ROOT . '/lib/cookie_consent.php';
$loop = in_array('--loop', $argv ?? [], true);
$intervalSec = 3 * 3600;
$runOnce = static function (): int {
    $deleted = cleanupStaleCookieConsents();
    fwrite(STDOUT, date('c') . ' pending_ttl=' . APP_COOKIE_CONSENT_PENDING_TTL . ' cleaned=' . $deleted . "\n");
    return 0;
};
if (!$loop) {
    exit($runOnce());
}
while (true) {
    $runOnce();
    sleep($intervalSec);
}
