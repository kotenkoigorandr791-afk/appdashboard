<?php

namespace Kanboard\Plugin\AppDashboard\Auth;

use Kanboard\Core\Security\Role;
use Kanboard\Core\User\UserProviderInterface;

class DashboardUserProvider implements UserProviderInterface
{
    private array $bridge;
    private array $profile;

    public function __construct(array $bridge, array $profile = [])
    {
        $this->bridge = $bridge;
        $this->profile = $profile;
    }

    public function isUserCreationAllowed()
    {
        return true;
    }

    public function getInternalId()
    {
        return isset($this->profile['id']) ? (int)$this->profile['id'] : 0;
    }

    public function getExternalIdColumn()
    {
        return 'username';
    }

    public function getExternalId()
    {
        return (string)($this->bridge['username'] ?? '');
    }

    public function getRole()
    {
        // Never APP_ADMIN: shell owns users/settings. Managers can create projects.
        if (!empty($this->bridge['is_manager']) || (int)($this->bridge['role'] ?? 0) >= 1) {
            return Role::APP_MANAGER;
        }
        return Role::APP_USER;
    }

    public function getUsername()
    {
        return (string)($this->bridge['username'] ?? '');
    }

    public function getName()
    {
        return (string)($this->bridge['name'] ?? $this->getUsername());
    }

    public function getEmail()
    {
        return (string)($this->bridge['email'] ?? '');
    }

    public function getExternalGroupIds()
    {
        return [];
    }

    public function getExtraAttributes()
    {
        $locale = (string)($GLOBALS['APPDASHBOARD_KB_LOCALE'] ?? 'en_US');
        $theme = (string)($GLOBALS['APPDASHBOARD_KB_THEME'] ?? 'light');
        if ($theme === 'device') {
            $theme = 'auto';
        }
        if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
            $theme = 'light';
        }
        return [
            'is_ldap_user' => 1,
            'disable_login_form' => 1,
            'notifications_enabled' => 1,
            'language' => $locale !== '' ? $locale : 'en_US',
            'theme' => $theme,
        ];
    }
}
