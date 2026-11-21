#!/usr/bin/env php
<?php
/**
 * Completely remove a user account: files, file-index rows, application
 * tables, per-user schemas/tables and sessions.
 *
 * Usage:
 *   php shell/bin/purge_user.php --email=user@example.com [--keep-files] [--dry-run]
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}
define('APP_ROOT', dirname(__DIR__, 2));
chdir(APP_ROOT);
require APP_ROOT . '/db.php';
require APP_ROOT . '/shell/storage_helper.php';
require APP_ROOT . '/lib/Storage/bootstrap.php';
require APP_ROOT . '/lib/UserDataPurger.php';

$email = '';
$deleteFiles = true;
$dryRun = false;
foreach ($argv ?? [] as $arg) {
    $arg = (string)$arg;
    if (str_starts_with($arg, '--email=')) $email = substr($arg, 8);
    if ($arg === '--keep-files') $deleteFiles = false;
    if ($arg === '--dry-run') $dryRun = true;
}
if ($email === '') {
    fwrite(STDERR, "Usage: php shell/bin/purge_user.php --email=user@example.com [--keep-files] [--dry-run]\n");
    exit(2);
}
try {
    $report = UserDataPurger::purge($email, ['delete_files' => $deleteFiles, 'dry_run' => $dryRun]);
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    exit(empty($report['errors']) ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'purge failed: ' . $e->getMessage() . "\n");
    exit(1);
}
