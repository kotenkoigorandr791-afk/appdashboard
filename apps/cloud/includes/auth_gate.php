<?php
declare(strict_types=1);
// Server-side auth gate for cloud pages that nginx serves directly
// (index.php, 3mf-viewer.php, utility controllers, ...).
// The dashboard router (project/routes.php) can't protect these files, so every
// directly-openable page must call cloud_require_session_auth() before output.

if (!function_exists('cloud_session_email_for_gate')) {
    function cloud_session_email_for_gate(): ?string
    {
        // api.php may not be loaded on plain pages; resolve email locally.
        $sessionEmail = $_SESSION['user']['email'] ?? $_SESSION['email'] ?? null;
        if (is_string($sessionEmail) && $sessionEmail !== '') {
            return strtolower(trim($sessionEmail));
        }

        // Check the active_sessions table (DB) — same source as the dashboard.
        static $db = null;
        if ($db === null) {
            try {
                require_once dirname(__DIR__, 3) . '/lib/app_db.php';
                $db = app_main_db();
            } catch (Throwable $e) {
                $db = false;
            }
        }
        if (!$db) {
            return null;
        }

        $ids = [];
        foreach ([session_id(), (string)($_COOKIE['PHPSESSID'] ?? '')] as $sid) {
            if (is_string($sid) && $sid !== '' && !isset($ids[$sid])) {
                $ids[$sid] = $sid;
            }
        }

        foreach ($ids as $sid) {
            try {
                $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1');
                $stmt->bindValue(':sid', $sid, PDO::PARAM_STR);
                $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['email'])) {
                    return strtolower(trim((string)$row['email']));
                }
            } catch (Throwable $e) {
            }
        }
        return null;
    }
}

if (!function_exists('cloud_session_storage_user')) {
    function cloud_session_storage_user(string $fallback = 'default'): string
    {
        $email = cloud_session_email_for_gate();
        if ($email === null || $email === '') {
            return $fallback;
        }
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', str_replace(['@', '.'], '_', $email));
        return $name !== '' ? $name : $fallback;
    }
}

if (!function_exists('cloud_require_session_auth')) {
    function cloud_require_session_auth(): void
    {
        if (cloud_session_email_for_gate() !== null) {
            return;
        }
        if (headers_sent()) {
            http_response_code(401);
            exit('Unauthorized');
        }
        // Preserve theme/lang/color when redirecting to the login page.
        $params = [];
        foreach (['theme', 'lang', 'color'] as $k) {
            $v = trim((string)($_GET[$k] ?? ''));
            if ($v !== '' && preg_match('/^[A-Za-z0-9#%_\-\.]{1,64}$/', $v)) {
                $params[$k] = $v;
            }
        }
        $qs = $params ? ('?' . http_build_query($params)) : '';
        header('Location: /login' . $qs);
        exit;
    }
}
