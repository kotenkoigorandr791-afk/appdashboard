<?php
/**
 * Public SharePoint path dispatcher.
 *
 * Shared links must render without an authenticated shell session. Keeping the
 * parsing here (instead of only in project/index.php) guarantees that any
 * entrypoint which reaches routes.php still resolves /s/{token} through
 * SharePoint before the auth guards run.
 */
if (!function_exists('sharepoint_dispatch_path')) {
    function sharepoint_dispatch_path(string $path): bool
    {
        if (!preg_match('#^/s/([A-Za-z0-9_-]+)(?:/(.*))?$#', $path, $match)) {
            return false;
        }

        $_GET['share_token'] = $match[1];
        $sub = trim((string)($match[2] ?? ''), '/');
        if ($match[1] === 'admin' && $sub === '') {
            $_GET['share_action'] = 'admin';
        } elseif ($sub === '') {
            $_GET['share_action'] = '';
        } else {
            $parts = explode('/', $sub);
            $head = strtolower((string)array_shift($parts));
            if (!in_array($head, ['unlock', 'content', 'download', 'actions'], true)) {
                http_response_code(404);
                return true;
            }
            $_GET['share_action'] = $head;
            $rest = implode('/', $parts);
            if ($head === 'download') {
                $_GET['download_item'] = $rest;
            } elseif ($head === 'content') {
                $_GET['content_path'] = $rest;
            }
        }

        require_once __DIR__ . '/router.php';
        return true;
    }
}
