<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    cloud_session_start();
}
require_once __DIR__ . '/auth_gate.php';

if (!function_exists('cloud_endpoint_require_email')) {
    function cloud_endpoint_require_email(): string
    {
        $email = cloud_session_email_for_gate();
        if ($email === null || $email === '') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(401);
            }
            echo json_encode(['success' => false, 'ok' => false, 'error' => 'not authenticated']);
            exit;
        }
        return $email;
    }
}

if (!function_exists('cloud_endpoint_username')) {
    function cloud_endpoint_username(string $email): string
    {
        $username = str_replace(['@', '.'], '_', strtolower(trim($email)));
        $username = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username);
        return $username !== '' ? $username : 'user';
    }
}

if (!function_exists('cloud_endpoint_rate_limit')) {
    function cloud_endpoint_rate_limit(string $email, string $kind = 'utility'): void
    {
        require_once __DIR__ . '/cloud_hardening.php';
        if (!function_exists('json_out')) {
            function json_out($data, int $code = 200): void
            {
                if (!headers_sent()) {
                    http_response_code($code);
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }
        cloud_require_rate_limit($email, $kind);
    }
}
