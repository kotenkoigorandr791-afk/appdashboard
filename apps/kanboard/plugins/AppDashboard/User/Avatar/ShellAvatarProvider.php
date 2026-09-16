<?php

namespace Kanboard\Plugin\AppDashboard\User\Avatar;

use Kanboard\Core\Base;
use Kanboard\Core\User\Avatar\AvatarProviderInterface;

class ShellAvatarProvider extends Base implements AvatarProviderInterface
{
    public function render(array $user, $size)
    {
        $src = $this->resolvePhotoSrc($user);
        $name = $this->helper->text->e($user['name'] ?: $user['username'] ?: 'User');
        $px = max(16, (int)$size);
        if ($src === '') {
            $initials = $this->helper->user->getInitials($user['name'] ?: $user['username'] ?: '?');
            return sprintf(
                '<div class="avatar-letter shell-avatar" style="background:var(--primary-color,#2563eb);color:#fff;text-align:center;width:%dpx;height:%dpx;line-height:%dpx;font-size:%dpx;border-radius:50%%" title="%s" role="img" aria-label="%s">%s</div>',
                $px,
                $px,
                $px,
                max(10, (int)($px * 0.4)),
                $name,
                $name,
                $this->helper->text->e($initials)
            );
        }
        return sprintf(
            '<img src="%s" alt="%s" title="%s" class="avatar-img shell-avatar" width="%d" height="%d" style="width:%dpx;height:%dpx;border-radius:50%%;object-fit:cover">',
            htmlspecialchars($src, ENT_QUOTES, 'UTF-8'),
            $name,
            $name,
            $px,
            $px,
            $px,
            $px
        );
    }

    public function isActive(array $user)
    {
        return true;
    }

    private function resolvePhotoSrc(array $user): string
    {
        $bridge = function_exists('kanboard_bridge_user') ? kanboard_bridge_user() : null;
        $photo = '';
        if (is_array($bridge) && isset($bridge['photo']) && (string)$bridge['photo'] !== '') {
            $email = strtolower((string)($user['email'] ?? ''));
            $bridgeEmail = strtolower((string)($bridge['email'] ?? ''));
            if ($email === '' || $email === $bridgeEmail || empty($user['id'])) {
                $photo = (string)$bridge['photo'];
            }
        }
        if ($photo === '' && !empty($user['email'])) {
            $photo = $this->lookupDashboardPhoto((string)$user['email']);
        }
        return $this->normalizeAvatarSrc($photo);
    }

    private function normalizeAvatarSrc(string $photo): string
    {
        $src = trim($photo);
        if ($src === '') {
            return '';
        }
        if (preg_match('#^https://lh3\.googleusercontent\.com/#i', $src)) {
            return '/main?api=avatar_proxy&u=' . rawurlencode($src);
        }
        if ($src === 'default_avatar.png') {
            return '/default_avatar.png';
        }
        if (!preg_match('#^(https?:)?/#', $src) && !str_starts_with($src, 'data:')) {
            return '/' . ltrim($src, '/');
        }
        return $src;
    }

    private function lookupDashboardPhoto(string $email): string
    {
        try {
            require_once dirname(__DIR__, 6) . '/lib/app_db.php';
            $db = app_main_db();
            $st = $db->prepare('SELECT photo FROM users WHERE lower(email) = lower(:e) LIMIT 1');
            $st->bindValue(':e', $email);
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            return is_array($row) ? (string)($row['photo'] ?? '') : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
