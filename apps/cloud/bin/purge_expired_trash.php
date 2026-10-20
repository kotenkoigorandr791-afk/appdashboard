#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}
define('APP_ROOT', dirname(__DIR__, 3));
require APP_ROOT . '/apps/cloud/api.php';

$loop = in_array('--loop', $argv ?? [], true);

$secondsUntilMidnight = static function (): int {
    $now = time();
    $next = strtotime('tomorrow 00:00:00', $now);
    if ($next === false || $next <= $now) {
        $next = $now + 86400;
    }
    return max(1, $next - $now);
};

$runOnce = static function (): int {
    $stats = cloud_purge_expired_trash_all_users();
    fwrite(STDOUT, date('c') . ' users=' . (int)$stats['users'] . ' removed=' . (int)$stats['removed'] . "\n");
    return 0;
};

if (!$loop) {
    exit($runOnce());
}

while (true) {
    $sleep = $secondsUntilMidnight();
    fwrite(STDOUT, date('c') . ' sleeping_until_midnight seconds=' . $sleep . "\n");
    sleep($sleep);
    $runOnce();
    sleep(60);
}
