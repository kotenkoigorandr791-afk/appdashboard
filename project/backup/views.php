<?php
/**
 * Backup Manager — standalone page shell.
 *
 * The same panel markup (project/views/modals/backup_manager.php) is embedded
 * directly into the Admin Panel. This shell only exists for direct access to
 * /backup_dashboard.php.
 */
declare(strict_types=1);

function backupThemeParam(): string {
    $theme = strtolower(trim((string)($_GET['theme'] ?? 'dark')));
    return in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';
}

function backupAccentParam(): string {
    $color = trim((string)($_GET['color'] ?? ''));
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#007bff';
}

function backupViewShell(string $title, string $innerHtml, string $lang = 'en'): void {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    $theme = backupThemeParam();
    $accent = backupAccentParam();
    $csrf = (string)($_SESSION['_csrf_token'] ?? '');
    $lang = backup_normalize_lang($lang);
    echo '<!DOCTYPE html><html lang="' . backupEscape($lang) . '"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
        . '<meta name="csrf-token" content="' . backupEscape($csrf) . '">'
        . '<title>' . backupEscape($title) . '</title>'
        . '<link rel="stylesheet" href="/project/backup/assets/backup.css">'
        . '<style>.bdp-body{--dynamic-accent:' . backupEscape($accent) . ';}</style>'
        . '</head><body class="bdp-body bdp-theme-' . backupEscape($theme) . '">'
        . $innerHtml
        . '<script src="/project/backup/assets/backup.js" defer></script>'
        . '</body></html>';
}
