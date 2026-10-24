<?php
final class ShareAccessPolicy
{
    public const PERMISSIONS = ['view', 'download', 'interact', 'edit', 'share'];
    public const ACCESS_MODES = ['public', 'restricted'];

    public static function check(?Share $share, string $requiredPermission = 'view', array $context = []): AccessResult
    {
        if (!$share) {
            return AccessResult::deny('not_found');
        }
        if ($share->isRevoked()) {
            return AccessResult::deny('revoked');
        }
        if ($share->isExpired()) {
            return AccessResult::deny('expired');
        }
        if ($share->max_views > 0 && $share->view_count >= $share->max_views) {
            return AccessResult::deny('max_views_exceeded');
        }
        if ($share->access_mode === 'restricted') {
            $email = strtolower(trim((string)(sp_session_user()['email'] ?? '')));
            if ($email === '') {
                return AccessResult::deny('login_required');
            }
            $allowed = array_map('strtolower', (array)($share->resource_data['allowed_emails'] ?? []));
            if ($email !== $share->owner_email && $allowed !== [] && !in_array($email, $allowed, true)) {
                return AccessResult::deny('user_not_allowed');
            }
        }
        if ($share->isPasswordProtected() && !self::isUnlocked($share)) {
            return AccessResult::deny('password_required');
        }
        if (!in_array($requiredPermission, self::PERMISSIONS, true) || !$share->hasPermission($requiredPermission)) {
            return AccessResult::deny('permission_denied');
        }

        $resource = ResourceResolver::resolve($share);
        if (!$resource) {
            return AccessResult::deny('resource_unavailable');
        }

        if ($share->resource_type === 'folder' && isset($context['child_path'])) {
            $handler = ShareRegistry::getHandler('folder');
            if ($handler && !$handler->checkChildAccess($share, (string)$context['child_path'])) {
                return AccessResult::deny('path_traversal');
            }
        }

        return AccessResult::allow($resource);
    }

    public static function isUnlocked(Share $share): bool
    {
        $at = (int)($_SESSION['share_unlocked'][$share->id] ?? 0);
        return $at > 0 && (time() - $at) < 1800;
    }

    public static function markUnlocked(Share $share): void
    {
        $_SESSION['share_unlocked'][$share->id] = time();
    }

    public static function clientIp(): string
    {
        return (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    }

    public static function dataMode(Share $share): string
    {
        return $share->dataMode();
    }
}
