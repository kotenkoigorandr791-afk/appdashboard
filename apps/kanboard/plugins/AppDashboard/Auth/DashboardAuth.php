<?php

namespace Kanboard\Plugin\AppDashboard\Auth;

use Kanboard\Core\Security\Role;
use Kanboard\Core\Base;
use Kanboard\Core\Security\OptionalAuthenticationProviderInterface;
use Kanboard\Core\Security\PreAuthenticationProviderInterface;
use Kanboard\Core\Security\SessionCheckProviderInterface;

class DashboardAuth extends Base implements PreAuthenticationProviderInterface, SessionCheckProviderInterface, OptionalAuthenticationProviderInterface
{
    protected $userInfo = null;

    public function getName()
    {
        return 'AppDashboard';
    }

    public function authenticate()
    {
        $user = function_exists('kanboard_bridge_user') ? kanboard_bridge_user() : null;
        if (!is_array($user) || empty($user['username'])) {
            return false;
        }
        $profile = $this->userCacheDecorator->getByUsername($user['username']);
        if (empty($profile) && !empty($user['email'])) {
            $byEmail = $this->userModel->getByEmail($user['email']);
            $profile = is_array($byEmail) ? $byEmail : [];
        }
        $this->userInfo = new DashboardUserProvider($user, is_array($profile) ? $profile : []);
        return true;
    }

    public function isValidSession()
    {
        $user = function_exists('kanboard_bridge_user') ? kanboard_bridge_user() : null;
        if (!is_array($user) || empty($user['username'])) {
            return false;
        }
        $ok = $this->userSession->getUsername() === $user['username']
            || strtolower((string)$this->userSession->getEmail()) === strtolower((string)($user['email'] ?? ''));
        if ($ok) {
            $session = session_get('user');
            if (is_array($session)) {
                $locale = (string)($GLOBALS['APPDASHBOARD_KB_LOCALE'] ?? '');
                $theme = (string)($GLOBALS['APPDASHBOARD_KB_THEME'] ?? '');
                if ($locale !== '') {
                    $session['language'] = $locale;
                }
                if ($theme === 'device') {
                    $theme = 'auto';
                }
                if (in_array($theme, ['light', 'dark', 'auto'], true)) {
                    $session['theme'] = $theme;
                }
                $session['role'] = Role::APP_MANAGER;
                session_set('user', $session);
            }
        }
        return $ok;
    }

    public function getUser()
    {
        return $this->userInfo;
    }

    public function isEnabled()
    {
        $user = function_exists('kanboard_bridge_user') ? kanboard_bridge_user() : null;
        return is_array($user) && !empty($user['username']);
    }
}
