<?php

/**
 * Shared embedded-request (iframe) context detection.
 *
 * Consolidates the historical copies in backup_dashboard.php, db_admin.php and
 * doc_viewer.php. A request is treated as embedded when it explicitly opts in
 * via ?embed=1 or when the browser marks it as an iframe navigation
 * (Sec-Fetch-Dest: iframe).
 */
if (!function_exists('app_is_embedded_request')) {
    function app_is_embedded_request(): bool {
        if (isset($_GET['embed']) && (string)$_GET['embed'] === '1') return true;
        $dest = strtolower((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
        return $dest === 'iframe' || $dest === 'frame';
    }
}

/**
 * Renders a minimal HTML notice with an explicit user action (a link that
 * navigates the TOP frame to the login page). Used instead of silent
 * redirects/JS top-frame hijacks when an iframe has no authenticated session.
 */
if (!function_exists('app_embedded_login_notice_html')) {
    function app_embedded_login_notice_html(string $loginUrl): void {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $u = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Sign in required</title></head>'
            . '<body style="font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f5f7fb;">'
            . '<div style="text-align:center;padding:32px 40px;border-radius:16px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.12);">'
            . '<p style="margin:0 0 18px 0;color:#1a2332;font-size:15px;">Sign-in is required to open this application.</p>'
            . '<a href="' . $u . '" target="_top" style="display:inline-block;padding:10px 22px;border-radius:12px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;font-size:14px;">Sign in</a>'
            . '</div></body></html>';
        exit;
    }
}
